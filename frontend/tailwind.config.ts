import type { Config } from 'tailwindcss'

export default {
  content: [
    './app/**/*.{vue,ts,js,jsx,tsx}',
    './components/**/*.{vue,ts,js,jsx,tsx}',
    './layouts/**/*.{vue,ts,js,jsx,tsx}',
    './pages/**/*.{vue,ts,js,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // Surface scale (light backgrounds)
        surface: {
          DEFAULT: '#eaede9',
          lowest: '#ffffff',
          low: '#f4f6f4',
          high: '#dde0db',
          dim: '#ced2cc',
          tint: '#e8f2eb',
        },
        // Text on surfaces
        'on-surface': {
          DEFAULT: '#111111',
          variant: '#5f675f',
        },
        // Primary green
        primary: {
          DEFAULT: '#2f7a45',
          dark: '#1f5d33',
          300: '#58b56c',
          600: '#1f5d33',
        },
        // Secondary green
        secondary: {
          DEFAULT: '#3a8c55',
          50: '#f0faf2',
        },
        // Outline / muted text
        outline: '#8a9388',
        // Error
        error: {
          DEFAULT: '#ae3d37',
          container: '#fde8e6',
        },
        // Success
        success: {
          DEFAULT: '#16a34a',
          container: '#dcfce7',
        },
      },
      fontFamily: {
        headline: ['Manrope', 'sans-serif'],
        body: ['Manrope', 'sans-serif'],
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
