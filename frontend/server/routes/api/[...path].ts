import { proxyRequest } from 'h3'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig(event)
  const upstreamBase = String(config.apiProxyBase || '').replace(/\/$/, '')
  const requestUrl = getRequestURL(event)
  const requestPath = requestUrl.pathname.replace(/^\/api/, '')
  const target = `${upstreamBase}${requestPath}${requestUrl.search}`

  return proxyRequest(event, target)
})
