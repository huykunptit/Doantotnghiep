type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

type ApiBody = BodyInit | Record<string, unknown> | unknown[] | null | undefined

interface ApiOptions<TBody> {
  method?: ApiMethod
  body?: TBody
  headers?: Record<string, string>
}

export async function useApi<TResponse = unknown, TBody extends ApiBody = ApiBody>(
  path: string,
  options: ApiOptions<TBody> = {},
): Promise<TResponse> {
  const config = useRuntimeConfig()
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData

  return await $fetch<TResponse>(path, {
    baseURL: config.public.apiBase,
    method: options.method || 'GET',
    body: options.body as TBody,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...options.headers,
    },
  })
}
