const STORAGE_KEY = 'lms-dark-mode'

export function useDarkMode() {
  const isDark = ref(false)

  function apply(dark: boolean) {
    if (!import.meta.client) return
    if (dark) {
      document.documentElement.setAttribute('data-theme', 'dark')
    }
    else {
      document.documentElement.removeAttribute('data-theme')
    }
    try { localStorage.setItem(STORAGE_KEY, dark ? '1' : '0') }
    catch {}
  }

  function toggle() {
    isDark.value = !isDark.value
    apply(isDark.value)
  }

  function init() {
    if (!import.meta.client) return
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored !== null) {
      isDark.value = stored === '1'
    }
    else {
      isDark.value = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false
    }
    apply(isDark.value)
  }

  return { isDark, toggle, init }
}
