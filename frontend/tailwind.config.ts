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
          highest: '#d1d5cf',
          dim: '#ced2cc',
          tint: '#e8f2eb',
        },
        // Text on surfaces
        'on-surface': {
          DEFAULT: '#111111',
          variant: '#5f675f',
        },
        // Dynamic Primary from CSS Variables
        primary: {
          DEFAULT: 'rgb(var(--primary-rgb, 47, 122, 69))',
          50: 'rgba(var(--primary-rgb, 47, 122, 69), 0.05)',
          100: 'rgba(var(--primary-rgb, 47, 122, 69), 0.1)',
        },
        secondary: {
          DEFAULT: 'rgba(var(--primary-rgb, 47, 122, 69), 0.8)',
          50: 'rgba(var(--primary-rgb, 47, 122, 69), 0.02)',
          100: 'rgba(var(--primary-rgb, 47, 122, 69), 0.05)',
        },
        // Tertiary (Emerald/Mint)
        tertiary: {
          DEFAULT: '#10b981',
          dark: '#059669',
          50: '#f0fdf4',
          100: '#dcfce7',
        },
        // Warning / Orange
        warning: {
          DEFAULT: '#d97706',
          container: '#fff7ed',
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
          DEFAULT: '#2f7a45', // Align with primary
          container: '#f0faf2',
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
