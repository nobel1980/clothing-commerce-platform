import type { CustomerAddress, CustomerProfile } from '@clothing-commerce/types';
import { apiRequest } from '@/lib/api/client';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
}

export async function loginCustomer(payload: LoginPayload): Promise<CustomerProfile> {
  const response = await apiRequest<{ data: CustomerProfile }>('/auth/login', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
  return response.data;
}

export async function registerCustomer(payload: RegisterPayload): Promise<CustomerProfile> {
  const response = await apiRequest<{ data: CustomerProfile }>('/auth/register', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
  return response.data;
}

export async function logoutCustomer(): Promise<void> {
  await apiRequest('/auth/logout', {
    method: 'POST',
    isPrivate: true,
  });
}

export async function fetchCustomerProfile(signal?: AbortSignal): Promise<CustomerProfile> {
  const response = await apiRequest<{ data: CustomerProfile }>('/account/profile', {
    isPrivate: true,
    signal,
  });
  return response.data;
}

export async function updateCustomerProfile(payload: {
  name: string;
  phone?: string;
}): Promise<CustomerProfile> {
  const response = await apiRequest<{ data: CustomerProfile }>('/account/profile', {
    method: 'PUT',
    body: payload,
    isPrivate: true,
  });
  return response.data;
}

export async function fetchCustomerAddresses(signal?: AbortSignal): Promise<CustomerAddress[]> {
  const response = await apiRequest<{ data: CustomerAddress[] }>('/account/addresses', {
    isPrivate: true,
    signal,
  });
  return response.data ?? [];
}

export async function createCustomerAddress(
  payload: Omit<CustomerAddress, 'id' | 'created_at' | 'updated_at'>
): Promise<CustomerAddress> {
  const response = await apiRequest<{ data: CustomerAddress }>('/account/addresses', {
    method: 'POST',
    body: payload,
    isPrivate: true,
  });
  return response.data;
}

export async function setDefaultCustomerAddress(addressId: number): Promise<CustomerAddress> {
  const response = await apiRequest<{ data: CustomerAddress }>(
    `/account/addresses/${addressId}/default`,
    {
      method: 'POST',
      isPrivate: true,
    }
  );
  return response.data;
}

export async function deleteCustomerAddress(addressId: number): Promise<void> {
  await apiRequest(`/account/addresses/${addressId}`, {
    method: 'DELETE',
    isPrivate: true,
  });
}

