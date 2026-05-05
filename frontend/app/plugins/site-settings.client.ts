export default defineNuxtPlugin(async () => {
  const { settings, siteName, siteFavicon, refreshSettings } = useSiteSettings()

  if (!settings.value) {
    await refreshSettings()
  }

  useHead({
    title: () => siteName.value,
    titleTemplate: (chunk) => (chunk && chunk !== siteName.value ? `${chunk} · ${siteName.value}` : siteName.value),
    link: () => {
      const favicon = siteFavicon.value
      return favicon
        ? [{ rel: 'icon', href: favicon, type: favicon.endsWith('.svg') ? 'image/svg+xml' : undefined }]
        : []
    },
  })
})
