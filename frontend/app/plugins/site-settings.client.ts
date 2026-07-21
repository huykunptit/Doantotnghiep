export default defineNuxtPlugin(async () => {
  const { load } = useSiteSettings()
  await load().catch(() => undefined)
})
