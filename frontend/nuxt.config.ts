import { EriptAura } from './app/theme/eript-preset'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@pinia/nuxt', '@primevue/nuxt-module', '@nuxtjs/i18n'],
  i18n: {
    locales: [
      { code: 'vi', language: 'vi-VN', name: 'Tiếng Việt', file: 'vi.json' },
      { code: 'en', language: 'en-US', name: 'English', file: 'en.json' },
    ],
    defaultLocale: 'vi',
    lazy: true,
    langDir: 'locales',
    strategy: 'no_prefix',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'eript_locale',
      fallbackLocale: 'vi',
      redirectOn: 'root',
    },
  },
  css: [
    'primeicons/primeicons.css',
    '~/assets/css/main.css',
    '~/assets/css/auth-panel.css',
  ],
  primevue: {
    options: {
      ripple: true,
      theme: {
        preset: EriptAura,
        options: {
          prefix: 'p',
          darkModeSelector: '.dark',
          cssLayer: false,
        },
      },
      locale: {
        accept: 'Có',
        reject: 'Không',
        choose: 'Chọn',
        upload: 'Tải lên',
        cancel: 'Hủy',
        clear: 'Xóa',
        apply: 'Áp dụng',
        today: 'Hôm nay',
        firstDayOfWeek: 1,
        dateFormat: 'dd/mm/yy',
        emptyMessage: 'Không có dữ liệu',
        emptyFilterMessage: 'Không tìm thấy kết quả',
      },
    },
    autoImport: true,
  },
  runtimeConfig: {
    // Absolute URL for SSR inside Docker (relative /api fails in Node fetch).
    apiInternal: process.env.NUXT_API_INTERNAL || '',
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
    },
  },
  app: {
    head: {
      title: 'Eript LMS',
      meta: [
        { name: 'description', content: 'Nền tảng quản trị và học tập Eript LMS' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Sans+Condensed:wght@500;600;700&display=swap',
        },
      ],
    },
  },
})
