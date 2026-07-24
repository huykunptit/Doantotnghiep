import type { Config } from 'tailwindcss'

export default {
  // admin-ui uses 'selector' (.dark class) — keep consistent
  darkMode: ['selector', '.dark'],
  content: [
    './app/**/*.{vue,ts,js,jsx,tsx}',
    './components/**/*.{vue,ts,js,jsx,tsx}',
    './layouts/**/*.{vue,ts,js,jsx,tsx}',
    './pages/**/*.{vue,ts,js,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // ── Dynamic primary (CSS vars written by useSiteSettings) ──
        primary: {
          DEFAULT: 'var(--theme-primary)',
          50:  'var(--p-primary-50)',
          100: 'var(--p-primary-100)',
          200: 'var(--p-primary-200)',
          300: 'var(--p-primary-300)',
          400: 'var(--p-primary-400)',
          500: 'var(--p-primary-500)',
          600: 'var(--p-primary-600)',
          700: 'var(--p-primary-700)',
          800: 'var(--p-primary-800)',
          900: 'var(--p-primary-900)',
        },
        // ── Emerald mapped to CSS vars (mirrors admin-ui) ──
        emerald: {
          50:  'var(--p-primary-50,  #ecfdf5)',
          100: 'var(--p-primary-100, #d1fae5)',
          200: 'var(--p-primary-200, #a7f3d0)',
          300: 'var(--p-primary-300, #6ee7b7)',
          400: 'var(--p-primary-400, #34d399)',
          500: 'var(--p-primary-500, #10b981)',
          600: 'var(--p-primary-600, #059669)',
          700: 'var(--p-primary-700, #047857)',
          800: 'var(--p-primary-800, #065f46)',
          900: 'var(--p-primary-900, #064e3b)',
        },
        // ── Neutrals — slate is Tailwind default, just document intent ──
        // text-slate-800 → page title
        // text-slate-500 → subtitle/description
        // text-slate-400 → muted/meta
        // border-slate-100/200 → borders
        // ── Page background ──
        // bg-[#f6f6f6] — body/page background (matches admin-ui)
        // ── Semantic status ──
        success: {
          DEFAULT: '#10b981',
          50: '#ecfdf5',
        },
        warning: {
          DEFAULT: '#f59e0b',
          50: '#fffbeb',
        },
        danger: {
          DEFAULT: '#ef4444',
          50: '#fef2f2',
        },
        info: {
          DEFAULT: '#3b82f6',
          50: '#eff6ff',
        },
      },
      fontFamily: {
        // Be Vietnam Pro — set in nuxt.config head
        sans: ['Be Vietnam Pro', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        ambient: '0 8px 32px -8px rgba(17, 17, 17, 0.12)',
      },
      borderRadius: {
        '2xl': '1rem',
        '3xl': '1.5rem',
        '4xl': '2rem',
      },
    },
  },
  plugins: [],
} satisfies Config
