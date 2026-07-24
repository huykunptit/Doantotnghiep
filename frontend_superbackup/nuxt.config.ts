import Aura from '@primevue/themes/aura'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },
  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss', '@primevue/nuxt-module'],
  css: [
    'primeicons/primeicons.css',
    '~/assets/css/design-tokens.css',
    '~/assets/css/main.css',
    '~/assets/css/theme-overrides.css',
    '~/assets/css/dark-theme.css',
  ],
  primevue: {
    options: {
      theme: {
        preset: Aura,
        options: {
          prefix: 'p',
          darkModeSelector: '.dark',
          cssLayer: false,
        },
      },
      ripple: true,
      locale: {
        accept: 'Có',
        reject: 'Không',
        choose: 'Chọn',
        upload: 'Tải lên',
        cancel: 'Hủy',
        clear: 'Xóa',
        apply: 'Áp dụng',
        today: 'Hôm nay',
        weekHeader: 'Tu',
        firstDayOfWeek: 1,
        dateFormat: 'dd/mm/yy',
        weak: 'Yếu',
        medium: 'Trung bình',
        strong: 'Mạnh',
        passwordPrompt: 'Nhập mật khẩu',
        emptyFilterMessage: 'Không tìm thấy kết quả',
        emptyMessage: 'Không có tùy chọn',
        dayNames: ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'],
        dayNamesShort: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
        dayNamesMin: ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
        monthNames: ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'],
        monthNamesShort: ['Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6', 'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'],
      },
    },
    autoImport: true,
  },
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
          href: 'https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&family=JetBrains+Mono:wght@400;500&display=swap',
        },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
        },
      ],
    },
  },
  vite: {
    server: {
      allowedHosts: ['maternity-craftwork-chess.ngrok-free.dev']
    }
  }
})
