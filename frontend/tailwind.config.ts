import type { Config } from 'tailwindcss'

export default {
  content: [
    './app/**/*.{vue,ts,js}',
    './components/**/*.{vue,ts,js}',
    './layouts/**/*.{vue,ts,js}',
    './pages/**/*.{vue,ts,js}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        headline: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
        body: ['Inter', 'system-ui', 'sans-serif'],
        label: ['Inter', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        ambient: '0 20px 50px -12px rgba(25, 28, 30, 0.08)',
        soft: '0 16px 40px -18px rgba(25, 28, 30, 0.12)',
      },
      colors: {
        primary: {
          DEFAULT: '#004ac6',
          dark: '#003ea8',
          light: '#dbe1ff',
          50: '#eef3ff',
          100: '#dbe1ff',
          200: '#b4c5ff',
          300: '#7f9efc',
          400: '#4a78f2',
          500: '#2563eb',
          600: '#004ac6',
          700: '#003ea8',
          800: '#00174b',
          900: '#001129',
        },
        secondary: {
          DEFAULT: '#00687a',
          light: '#57dffe',
          50: '#ecfdff',
          100: '#acedff',
          200: '#57dffe',
          300: '#21c5e3',
          400: '#0097b3',
          500: '#00687a',
          600: '#004e5c',
        },
        tertiary: {
          DEFAULT: '#3e3fcc',
          container: '#585be6',
        },
        surface: {
          DEFAULT: '#eaeff5',
          dim: '#cbd5e1',
          bright: '#f8fafc',
          container: '#f1f5f9',
          low: '#f8fafc',
          lowest: '#ffffff',
          high: '#e2e8f0',
          highest: '#cbd5e1',
          tint: '#2563eb',
        },
        outline: {
          DEFAULT: '#64748b',
          variant: '#94a3b8',
        },
        on: {
          surface: '#0f172a',
          'surface-variant': '#334155',
          primary: '#ffffff',
          'primary-container': '#eeefff',
          secondary: '#ffffff',
          'secondary-container': '#006172',
        },
        error: {
          DEFAULT: '#ba1a1a',
          container: '#ffdad6',
        },
      },
    },
  },
  plugins: [],
} satisfies Config
