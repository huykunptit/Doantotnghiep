const STORAGE_KEY = 'lms-dark-mode'

export default defineNuxtPlugin(() => {
  if (!import.meta.client) return

  const stored = localStorage.getItem(STORAGE_KEY)
  const dark = stored !== null
    ? stored === '1'
    : (window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false)

  const root = document.documentElement
  if (dark) {
    root.classList.add('dark')
    root.setAttribute('data-theme', 'dark')
  }
  else {
    root.classList.remove('dark')
    root.removeAttribute('data-theme')
  }
})
