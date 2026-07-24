import { computed } from 'vue'

export function useTheme() {
  const preference = useCookie<'light' | 'dark'>('sylva-theme', {
    default: () => 'light',
    sameSite: 'lax',
  })

  const isDark = computed(() => preference.value === 'dark')

  function apply() {
    if (!import.meta.client) return
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  function toggle() {
    preference.value = isDark.value ? 'light' : 'dark'
    apply()
  }

  return { preference, isDark, apply, toggle }
}
