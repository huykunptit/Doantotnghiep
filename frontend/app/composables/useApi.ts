type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
type ApiBody = BodyInit | Record<string, unknown> | unknown[] | null | undefined
type ApiQuery = Record<string, string | number | boolean | Array<string | number> | null | undefined>

interface ApiOptions<TBody> {
  method?: ApiMethod
  body?: TBody
  headers?: Record<string, string>
  token?: string | null
  query?: ApiQuery
}

function buildQuery(query?: ApiQuery) {
  const params = new URLSearchParams()
  if (!query) return params
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) {
      for (const item of value) {
        if (item === undefined || item === null || item === '') continue
        params.append(`${key}[]`, String(item))
      }
      continue
    }
    params.append(key, String(value))
  }
  return params
}

export async function useApi<TResponse = unknown, TBody extends ApiBody = ApiBody>(
  path: string,
  options: ApiOptions<TBody> = {},
) {
  const config = useRuntimeConfig()
  const tokenCookie = useCookie<string | null>('eript-token')
  const token = options.token === undefined ? tokenCookie.value : options.token
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData

  return await $fetch<TResponse>(path, {
    baseURL: config.public.apiBase,
    method: options.method || 'GET',
    body: options.body as TBody,
    query: options.query,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  })
}

/** Download CSV/binary from API with native fetch (avoids ofetch JSON parsing). */
export async function useApiDownload(
  path: string,
  options: {
    query?: ApiQuery
    filename?: string
    method?: ApiMethod
  } = {},
) {
  const config = useRuntimeConfig()
  const tokenCookie = useCookie<string | null>('eript-token')
  const params = buildQuery(options.query)
  const qs = params.toString()
  const url = `${config.public.apiBase}${path.startsWith('/') ? path : `/${path}`}${qs ? `?${qs}` : ''}`

  const response = await fetch(url, {
    method: options.method || 'GET',
    headers: {
      Accept: 'text/csv,application/octet-stream,*/*',
      ...(tokenCookie.value ? { Authorization: `Bearer ${tokenCookie.value}` } : {}),
    },
  })

  if (!response.ok) {
    let message = `Download failed (${response.status})`
    try {
      const data = await response.json()
      message = data?.message || message
    }
    catch {
      /* ignore */
    }
    throw Object.assign(new Error(message), { data: { message }, statusCode: response.status })
  }

  const blob = await response.blob()
  if (!blob || blob.size === 0) {
    throw Object.assign(new Error('Empty file'), { data: { message: 'Empty file' } })
  }

  // If server accidentally returned JSON error as blob
  if (blob.type.includes('application/json')) {
    const text = await blob.text()
    let message = 'Download failed'
    try {
      message = JSON.parse(text)?.message || text || message
    }
    catch {
      message = text || message
    }
    throw Object.assign(new Error(message), { data: { message } })
  }

  const disposition = response.headers.get('content-disposition') || ''
  const matched = disposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i)
  const filename = options.filename || (matched ? decodeURIComponent(matched[1]) : 'download.csv')

  const objectUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = objectUrl
  a.download = filename.replace(/["']/g, '')
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(objectUrl)
}
