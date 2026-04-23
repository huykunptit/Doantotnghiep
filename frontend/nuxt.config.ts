export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],
  components: {
    dirs: [
      {
        path: './app/components',
        pathPrefix: false,
      },
    ],
  },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    apiProxyBase: process.env.NUXT_API_PROXY_BASE || 'http://backend:8000/api',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api',
    },
  },
  typescript: {
    strict: true,
  },
  app: {
    head: {
      title: 'EduPress - Nền tảng học tập trực tuyến',
      meta: [
        { name: 'description', content: 'Nền tảng học tập trực tuyến ' },
      ],
      link: [
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
        },
      ],
    },
  },
})
