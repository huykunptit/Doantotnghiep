import { getQuery, getRouterParam, proxyRequest } from 'h3'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const path = getRouterParam(event, 'path') || ''
  const query = new URLSearchParams(getQuery(event) as Record<string, string>).toString()
  const target = `${config.apiProxyBase}/${path}${query ? `?${query}` : ''}`

  return proxyRequest(event, target)
})
