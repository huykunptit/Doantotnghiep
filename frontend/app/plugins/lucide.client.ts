import * as LucideIcons from 'lucide-vue-next'
import { defineNuxtPlugin } from '#app'

export default defineNuxtPlugin((nuxtApp) => {
  Object.entries(LucideIcons).forEach(([name, component]) => {
    if (name !== 'default' && typeof component === 'object') {
      nuxtApp.vueApp.component(name, component as any)
    }
  })
})
