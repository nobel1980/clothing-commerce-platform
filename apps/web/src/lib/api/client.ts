/**
 * Modular API Client for Laravel 13 Backend
 *
 * Security & Caching Rules:
 * - Never exposes private backend credentials to client bundles.
 * - Uses Sanctum stateful cookies (`credentials: 'include'`) for customer sessions.
 * - Public storefront catalog requests support Next.js ISR/Tag revalidation (`next.tags`).
 * - Private customer requests (`/account`, `/auth`, `/checkout`, `/orders`) strictly enforce `cache: 'no-store'`.
 */

export class ApiClientError extends Error {
  public readonly status: number;
  public readonly errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiClientError';
    this.status = status;
    this.errors = errors;
  }
}

export interface ApiRequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  headers?: Record<string, string>;
  /**
   * Cache tags for Next.js Server Component fetch revalidation.
   * Ignored when `isPrivate` is true.
   */
  tags?: string[];
  /**
   * Revalidation window in seconds for public catalog endpoints.
   */
  revalidate?: number | false;
  /**
   * Forces `cache: 'no-store'` and disables public caching for customer-specific data.
   */
  isPrivate?: boolean;
  signal?: AbortSignal;
}

export function getApiBaseUrl(): string {
  if (typeof window === 'undefined') {
    const internal =
      process.env.API_INTERNAL_URL ||
      process.env.NEXT_PUBLIC_API_URL ||
      'http://127.0.0.1:8000/api';
    return internal.replace(/\/+$/, '');
  }

  const publicUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost/api';
  return publicUrl.replace(/\/+$/, '');
}

export async function apiRequest<T>(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<T> {
  const {
    method = 'GET',
    body,
    headers = {},
    tags,
    revalidate = 60,
    isPrivate = false,
    signal,
  } = options;

  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  const url = `${getApiBaseUrl()}${cleanEndpoint}`;

  const isPrivateEndpoint =
    isPrivate ||
    cleanEndpoint.startsWith('/account') ||
    cleanEndpoint.startsWith('/auth') ||
    cleanEndpoint.startsWith('/checkout') ||
    cleanEndpoint.startsWith('/orders') ||
    method !== 'GET';

  const requestHeaders: Record<string, string> = {
    Accept: 'application/json',
    ...headers,
  };

  if (body !== undefined) {
    requestHeaders['Content-Type'] = 'application/json';
  }

  const fetchInit: RequestInit & {
    next?: { revalidate?: number | false; tags?: string[] };
  } = {
    method,
    headers: requestHeaders,
    credentials: 'include',
    signal,
  };

  if (body !== undefined) {
    fetchInit.body = JSON.stringify(body);
  }

  if (isPrivateEndpoint) {
    fetchInit.cache = 'no-store';
  } else {
    fetchInit.next = {
      revalidate,
      ...(tags && tags.length > 0 ? { tags } : {}),
    };
  }

  const response = await fetch(url, fetchInit);

  if (response.status === 204) {
    return undefined as unknown as T;
  }

  let payload: unknown = null;
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    payload = await response.json();
  }

  if (!response.ok) {
    const errObj = (payload ?? {}) as {
      message?: string;
      errors?: Record<string, string[]>;
    };
    throw new ApiClientError(
      errObj.message || `Request failed with status ${response.status}`,
      response.status,
      errObj.errors
    );
  }

  return payload as T;
}

