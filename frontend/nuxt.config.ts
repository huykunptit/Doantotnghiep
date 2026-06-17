export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    apiProxyBase: process.env.NUXT_API_PROXY_BASE || 'http://localhost:8000/api',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'https://learn.ptit.edu.vn',
    },
  },
  app: {
    head: {
      title: 'Sylva LMS',
      meta: [
        {
          name: 'description',
          content: 'Sylva LMS — Nền tảng học tập trực tuyến thế hệ mới, thích nghi và nuôi dưỡng tri thức lâu dài theo triết lý Dương Liễu Mộc.',
        },
      ],
      link: [
        {
          rel: 'preconnect',
          href: 'https://fonts.googleapis.com',
        },
        {
          rel: 'preconnect',
          href: 'https://fonts.gstatic.com',
          crossorigin: '',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
        },
      ],
    },
  },
})
