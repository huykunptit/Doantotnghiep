type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface ApiOptions<TBody> {
  method?: ApiMethod
  body?: TBody
  token?: string | null
  headers?: Record<string, string>
}

export async function useApi<TResponse = unknown, TBody = unknown>(
  path: string,
  options: ApiOptions<TBody> = {}
): Promise<TResponse> {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase || '/api'
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...options.headers,
  }

  // Don't set Content-Type for FormData (browser sets it with boundary automatically)
  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`
  }

  return await $fetch<TResponse>(path, {
    baseURL,
    method: options.method || 'GET',
    body: options.body as any,
    headers,
  })
}
