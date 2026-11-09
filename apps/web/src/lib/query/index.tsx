'use client';

import * as React from 'react';

export type QueryKey = ReadonlyArray<unknown>;

interface CacheEntry {
  data: unknown;
  updatedAt: number;
}

export class QueryClient {
  private cache = new Map<string, CacheEntry>();
  private listeners = new Set<() => void>();

  public serializeKey(key: QueryKey): string {
    return JSON.stringify(key);
  }

  public getQueryData<T>(key: QueryKey): T | undefined {
    const entry = this.cache.get(this.serializeKey(key));
    return entry?.data as T | undefined;
  }

  public getEntry(key: QueryKey): CacheEntry | undefined {
    return this.cache.get(this.serializeKey(key));
  }

  public setQueryData<T>(key: QueryKey, data: T): void {
    this.cache.set(this.serializeKey(key), {
      data,
      updatedAt: Date.now(),
    });
    this.notify();
  }

  public invalidateQueries(options?: { queryKey?: QueryKey }): void {
    if (!options?.queryKey) {
      this.cache.clear();
    } else {
      const prefix = JSON.stringify(options.queryKey).slice(0, -1);
      for (const k of Array.from(this.cache.keys())) {
        if (k.startsWith(prefix)) {
          this.cache.delete(k);
        }
      }
    }
    this.notify();
  }

  public subscribe(listener: () => void): () => void {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  }

  private notify(): void {
    for (const listener of this.listeners) {
      listener();
    }
  }
}

const QueryClientContext = React.createContext<QueryClient | null>(null);

export function QueryClientProvider({
  client,
  children,
}: {
  client?: QueryClient;
  children: React.ReactNode;
}) {
  const [defaultClient] = React.useState(() => client ?? new QueryClient());
  return (
    <QueryClientContext.Provider value={defaultClient}>
      {children}
    </QueryClientContext.Provider>
  );
}

export function useQueryClient(): QueryClient {
  const context = React.useContext(QueryClientContext);
  const [fallback] = React.useState(() => new QueryClient());
  return context ?? fallback;
}

export interface UseQueryOptions<TData> {
  queryKey: QueryKey;
  queryFn: (context: { signal: AbortSignal }) => Promise<TData>;
  enabled?: boolean;
  staleTime?: number;
  initialData?: TData;
}

export interface UseQueryResult<TData> {
  data: TData | undefined;
  error: Error | null;
  isLoading: boolean;
  isFetching: boolean;
  isError: boolean;
  isSuccess: boolean;
  refetch: () => Promise<TData | undefined>;
}

export function useQuery<TData>(options: UseQueryOptions<TData>): UseQueryResult<TData> {
  const { queryKey, queryFn, enabled = true, staleTime = 15_000, initialData } = options;
  const queryClient = useQueryClient();
  const serializedKey = queryClient.serializeKey(queryKey);

  const cachedEntry = queryClient.getEntry(queryKey);
  const [data, setData] = React.useState<TData | undefined>(
    (cachedEntry?.data as TData | undefined) ?? initialData
  );
  const [error, setError] = React.useState<Error | null>(null);
  const [isFetching, setIsFetching] = React.useState<boolean>(false);

  const queryFnRef = React.useRef(queryFn);
  queryFnRef.current = queryFn;

  const executeFetch = React.useCallback(
    async (signal?: AbortSignal): Promise<TData | undefined> => {
      setIsFetching(true);
      setError(null);
      const controller = new AbortController();
      const activeSignal = signal ?? controller.signal;

      try {
        const result = await queryFnRef.current({ signal: activeSignal });
        if (!activeSignal.aborted) {
          queryClient.setQueryData(queryKey, result);
          setData(result);
        }
        return result;
      } catch (err) {
        if (!activeSignal.aborted) {
          const normalized = err instanceof Error ? err : new Error(String(err));
          setError(normalized);
        }
        return undefined;
      } finally {
        if (!activeSignal.aborted) {
          setIsFetching(false);
        }
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [queryClient, serializedKey]
  );

  React.useEffect(() => {
    if (!enabled) {
      return;
    }

    const existing = queryClient.getEntry(queryKey);
    if (existing && Date.now() - existing.updatedAt < staleTime) {
      setData(existing.data as TData);
      return;
    }

    const controller = new AbortController();
    void executeFetch(controller.signal);

    return () => {
      controller.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enabled, serializedKey, staleTime, executeFetch]);

  React.useEffect(() => {
    return queryClient.subscribe(() => {
      const latest = queryClient.getEntry(queryKey);
      if (latest) {
        setData(latest.data as TData);
      }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queryClient, serializedKey]);

  return {
    data,
    error,
    isLoading: enabled && data === undefined && isFetching,
    isFetching,
    isError: error !== null,
    isSuccess: data !== undefined && error === null,
    refetch: () => executeFetch(),
  };
}

export interface UseMutationOptions<TData, TVariables> {
  mutationFn: (variables: TVariables) => Promise<TData>;
  onSuccess?: (data: TData, variables: TVariables) => void | Promise<void>;
  onError?: (error: Error, variables: TVariables) => void;
}

export interface UseMutationResult<TData, TVariables> {
  mutate: (variables: TVariables) => void;
  mutateAsync: (variables: TVariables) => Promise<TData>;
  data: TData | undefined;
  error: Error | null;
  isPending: boolean;
  isError: boolean;
  isSuccess: boolean;
  reset: () => void;
}

export function useMutation<TData, TVariables = void>(
  options: UseMutationOptions<TData, TVariables>
): UseMutationResult<TData, TVariables> {
  const [data, setData] = React.useState<TData | undefined>(undefined);
  const [error, setError] = React.useState<Error | null>(null);
  const [isPending, setIsPending] = React.useState<boolean>(false);

  const optionsRef = React.useRef(options);
  optionsRef.current = options;

  const mutateAsync = React.useCallback(async (variables: TVariables): Promise<TData> => {
    setIsPending(true);
    setError(null);
    try {
      const result = await optionsRef.current.mutationFn(variables);
      setData(result);
      await optionsRef.current.onSuccess?.(result, variables);
      return result;
    } catch (err) {
      const normalized = err instanceof Error ? err : new Error(String(err));
      setError(normalized);
      optionsRef.current.onError?.(normalized, variables);
      throw normalized;
    } finally {
      setIsPending(false);
    }
  }, []);

  const mutate = React.useCallback(
    (variables: TVariables) => {
      void mutateAsync(variables).catch(() => {
        // Error captured in state
      });
    },
    [mutateAsync]
  );

  const reset = React.useCallback(() => {
    setData(undefined);
    setError(null);
    setIsPending(false);
  }, []);

  return {
    mutate,
    mutateAsync,
    data,
    error,
    isPending,
    isError: error !== null,
    isSuccess: data !== undefined && error === null,
    reset,
  };
}
