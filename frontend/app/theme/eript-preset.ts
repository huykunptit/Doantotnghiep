import { definePreset } from '@primevue/themes'
import Aura from '@primevue/themes/aura'

/** Brand teal sampled from CTĐT segment control — rgb(15, 118, 110). */
export const BRAND_TEAL = '#0f766e'
export const BRAND_TEAL_HOVER = '#0d655e'
export const BRAND_TEAL_ACTIVE = '#115e59'
export const BRAND_TEAL_SOFT = '#e7f5f2'

export const EriptAura = definePreset(Aura, {
  semantic: {
    primary: {
      50: '#f0fdfa',
      100: '#ccfbf1',
      200: '#99f6e4',
      300: '#5eead4',
      400: '#2dd4bf',
      500: BRAND_TEAL,
      600: BRAND_TEAL_HOVER,
      700: BRAND_TEAL_ACTIVE,
      800: '#134e4a',
      900: '#134e4a',
      950: '#042f2e',
    },
  },
})
