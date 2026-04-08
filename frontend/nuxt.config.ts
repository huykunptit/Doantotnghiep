export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
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
        { name: 'description', content: 'Nền tảng học tập trực tuyến - Đồ án tốt nghiệp PTIT' },
      ],
      link: [
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        },
      ],
    },
  },
})
