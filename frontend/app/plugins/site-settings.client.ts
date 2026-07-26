export default defineNuxtPlugin(() => {
  const { load } = useSiteSettings()
  // Non-blocking: never stall route mount (e.g. Google OAuth return page).
  load().catch(() => undefined)
})
