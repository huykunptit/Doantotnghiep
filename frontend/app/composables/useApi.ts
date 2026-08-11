type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
type ApiBody = BodyInit | Record<string, unknown> | unknown[] | null | undefined
type ApiQuery = Record<string, string | number | boolean | Array<string | number> | null | undefined>

interface ApiOptions<TBody> {
  method?: ApiMethod
  body?: TBody
  headers?: Record<string, string>
  token?: string | null
  query?: ApiQuery
  timeout?: number
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

export function resolveApiBase() {
  const config = useRuntimeConfig()
  const publicBase = String(config.public.apiBase || '/api')
  if (import.meta.server) {
    const internal = String(config.apiInternal || '').trim()
    if (internal) return internal.replace(/\/$/, '')
    // Relative public base cannot be fetched from Node SSR.
    if (publicBase.startsWith('/')) return 'http://nginx/api'
  }
  return publicBase.replace(/\/$/, '') || '/api'
}

/** /auth/* endpoints handle their own 401s on-page; never force-redirect from those. */
const AUTH_ENDPOINT_RE = /^\/auth\//

export async function useApi<TResponse = unknown, TBody extends ApiBody = ApiBody>(
  path: string,
  options: ApiOptions<TBody> = {},
) {
  const tokenCookie = useCookie<string | null>('eript-token')
  const token = options.token === undefined ? tokenCookie.value : options.token
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData

  try {
    return await $fetch<TResponse>(path, {
      baseURL: resolveApiBase(),
      method: options.method || 'GET',
      body: options.body as TBody,
      query: options.query,
      timeout: options.timeout,
      headers: {
        Accept: 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
    })
  }
  catch (error: any) {
    const status = Number(error?.statusCode || error?.response?.status || 0)
    // Session expired mid-use: had a token, server no longer accepts it.
    if (status === 401 && token && !AUTH_ENDPOINT_RE.test(path)) {
      const route = useRoute()
      if (!route.path.startsWith('/login')) {
        tokenCookie.value = null
        useCookie('eript-user').value = null
        await navigateTo(`/login?expired=1&redirect=${encodeURIComponent(route.fullPath)}`)
      }
    }
    throw error
  }
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
  const tokenCookie = useCookie<string | null>('eript-token')
  const params = buildQuery(options.query)
  const qs = params.toString()
  const url = `${resolveApiBase()}${path.startsWith('/') ? path : `/${path}`}${qs ? `?${qs}` : ''}`

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

interface ApiStreamDoneEvent {
  done: true
  tokens_used?: { prompt: number, completion: number, total: number }
  rag_used?: boolean
  sources?: Array<{ source?: string, subject?: string | null, score?: number | null }>
  source?: string
}

interface ApiStreamOptions {
  method?: ApiMethod
  body?: Record<string, unknown>
  token?: string | null
  headers?: Record<string, string>
  signal?: AbortSignal
  onDelta?: (text: string) => void
  onDone?: (data: ApiStreamDoneEvent) => void
  onError?: (message: string) => void
}

function dispatchSseEvent(rawEvent: string, options: ApiStreamOptions) {
  const dataLines = rawEvent
    .split('\n')
    .filter(line => line.startsWith('data:'))
    .map(line => line.slice(5).trim())
  if (!dataLines.length) return

  let parsed: Record<string, unknown>
  try {
    parsed = JSON.parse(dataLines.join('\n'))
  }
  catch {
    return
  }

  if (typeof parsed.delta === 'string') {
    options.onDelta?.(parsed.delta)
  }
  else if (parsed.error) {
    options.onError?.(String(parsed.error))
  }
  else if (parsed.done) {
    options.onDone?.(parsed as unknown as ApiStreamDoneEvent)
  }
}

/**
 * Đọc SSE (Server-Sent Events) từ 1 endpoint streaming của backend (vd. /ai/chat/stream).
 * Dùng fetch() thô vì $fetch (ofetch) không hỗ trợ đọc dần ReadableStream tiện lợi.
 */
export async function useApiStream(path: string, options: ApiStreamOptions = {}): Promise<void> {
  const tokenCookie = useCookie<string | null>('eript-token')
  const token = options.token === undefined ? tokenCookie.value : options.token
  const base = resolveApiBase()
  const url = `${base}${path.startsWith('/') ? path : `/${path}`}`

  let response: Response
  try {
    response = await fetch(url, {
      method: options.method || 'POST',
      headers: {
        Accept: 'text/event-stream',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      signal: options.signal,
    })
  }
  catch {
    options.onError?.('network_error')
    return
  }

  if (!response.ok || !response.body) {
    options.onError?.(`http_${response.status}`)
    return
  }

  const reader = response.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { value, done } = await reader.read()
    if (done) break
    buffer += decoder.decode(value, { stream: true })

    let sepIndex = buffer.indexOf('\n\n')
    while (sepIndex !== -1) {
      dispatchSseEvent(buffer.slice(0, sepIndex), options)
      buffer = buffer.slice(sepIndex + 2)
      sepIndex = buffer.indexOf('\n\n')
    }
  }

  if (buffer.trim()) dispatchSseEvent(buffer, options)
}
