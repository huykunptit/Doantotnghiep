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
      // Bumped at image build time → useful for debugging which build is live
      buildId: process.env.NUXT_PUBLIC_BUILD_ID || 'dev',
    },
  },
  // HTML/SSR must revalidate after deploy; hashed /_nuxt assets stay cacheable.
  routeRules: {
    '/**': {
      headers: {
        'Cache-Control': 'no-cache, no-store, must-revalidate',
      },
    },
    '/_nuxt/**': {
      headers: {
        'Cache-Control': 'public, max-age=31536000, immutable',
      },
    },
  },
  app: {
    head: {
      title: 'ERIPT LMS',
      meta: [
        { name: 'description', content: 'Nền tảng quản trị và học tập ERIPT LMS' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'referrer', content: 'strict-origin-when-cross-origin' },
      ],
      link: [
        // Explicit tag so the tab icon matches the brand logo from first
        // paint (SSR) — useSiteSettings.applyBranding() overwrites this
        // client-side if an admin has uploaded a custom favicon.
        { rel: 'icon', type: 'image/png', href: '/images/eript-logo.png' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Roboto+Condensed:wght@500;600;700&display=swap',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,700&display=swap',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap',
        },
      ],
    },
  },
})
