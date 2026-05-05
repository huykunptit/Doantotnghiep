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
<<<<<<< HEAD
      title: 'PTIT LMS',
=======
      title: 'ERIPT LMS',
>>>>>>> 7d009d95f0b42efa5409595ad1a720a5e547586f
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
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
        },
      ],
    },
  },
})
