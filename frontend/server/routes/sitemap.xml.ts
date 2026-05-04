interface Course { id: number; updated_at?: string; slug?: string }
interface Category { id: number; slug?: string; name: string }

export default defineEventHandler(async (event) => {
  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=3600')

  const config = useRuntimeConfig(event)
  const siteUrl =
    (config.public as Record<string, string>).siteUrl || 'https://learn.ptit.edu.vn'
  const apiBase = config.apiProxyBase || 'http://backend:8000/api'

  const staticUrls = [
    { loc: '/', priority: '1.0', changefreq: 'daily' },
    { loc: '/courses', priority: '0.9', changefreq: 'daily' },
    { loc: '/career', priority: '0.7', changefreq: 'weekly' },
    { loc: '/login', priority: '0.3', changefreq: 'yearly' },
    { loc: '/register', priority: '0.4', changefreq: 'yearly' },
  ]

  let courseUrls: Array<{ loc: string; lastmod?: string; priority: string; changefreq: string }> = []
  let categoryUrls: Array<{ loc: string; priority: string; changefreq: string }> = []

  try {
    const coursesRes = await $fetch<{ data?: Course[] }>(`${apiBase}/courses`, {
      query: { per_page: 100, status: 'published' },
    })
    courseUrls = (coursesRes.data || []).map((c) => ({
      loc: `/courses/${c.id}`,
      lastmod: c.updated_at,
      priority: '0.8',
      changefreq: 'weekly',
    }))
  } catch {
    // Silent fail — sitemap should still serve static URLs.
  }

  try {
    const cats = await $fetch<Category[]>(`${apiBase}/courses/categories`)
    categoryUrls = (cats || []).map((cat) => ({
      loc: `/courses?category=${cat.slug || cat.id}`,
      priority: '0.6',
      changefreq: 'weekly',
    }))
  } catch {
    // Silent fail.
  }

  const all = [...staticUrls, ...categoryUrls, ...courseUrls]

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${all
  .map((u) => {
    const lastmod = 'lastmod' in u && u.lastmod ? `    <lastmod>${u.lastmod}</lastmod>\n` : ''
    return `  <url>
    <loc>${siteUrl}${u.loc}</loc>
${lastmod}    <changefreq>${u.changefreq}</changefreq>
    <priority>${u.priority}</priority>
  </url>`
  })
  .join('\n')}
</urlset>`

  return xml
})
