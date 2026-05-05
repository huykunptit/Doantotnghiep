import { useAuthStore } from '~/stores/auth'

type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

type ApiBody = BodyInit | Record<string, unknown> | unknown[] | null | undefined

interface ApiOptions<TBody> {
  method?: ApiMethod
  body?: TBody
  headers?: Record<string, string>
  token?: string | null
}

export async function useApi<TResponse = unknown, TBody extends ApiBody = ApiBody>(
  path: string,
  options: ApiOptions<TBody> = {},
): Promise<TResponse> {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData
  const token = options.token ?? authStore.token
  const authHeader = token ? { Authorization: `Bearer ${token}` } : {}

  return await $fetch<TResponse>(path, {
    baseURL: config.public.apiBase,
    method: options.method || 'GET',
    body: options.body as TBody,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...authHeader,
      ...options.headers,
    },
  })
}
