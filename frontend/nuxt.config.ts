export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    apiProxyBase: process.env.NUXT_API_PROXY_BASE || 'http://backend:8000/api',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api',
    },
  },
  app: {
    head: {
      title: 'PTIT LMS',
      meta: [
        {
          name: 'description',
          content: 'Nen tang hoc tap truc tuyen voi giao dien toi gian, than thien va hien dai.',
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
          href: 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap',
        },
      ],
    },
  },
})
