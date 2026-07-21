export default defineEventHandler((event) => {
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=86400')

  const config = useRuntimeConfig(event)
  const siteUrl = (config.public as Record<string, string>).siteUrl || 'https://learn.ptit.edu.vn'

  return [
    'User-agent: *',
    'Allow: /',
    'Disallow: /admin/',
    'Disallow: /instructor/',
    'Disallow: /student/',
    'Disallow: /profile',
    'Disallow: /orders',
    'Disallow: /checkout/',
    'Disallow: /payment/',
    'Disallow: /api/',
    '',
    `Sitemap: ${siteUrl}/sitemap.xml`,
    '',
  ].join('\n')
})
