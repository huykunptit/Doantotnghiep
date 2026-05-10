export default defineNuxtPlugin(async () => {
  const { settings, siteTitle, siteFavicon, refreshSettings } = useSiteSettings()

  if (!settings.value) {
    await refreshSettings()
  }

  useHead({
    title: () => siteTitle.value,
    titleTemplate: (chunk) => (chunk && chunk !== siteTitle.value ? `${chunk} · ${siteTitle.value}` : siteTitle.value),
    link: () => {
      const favicon = siteFavicon.value
      return favicon
        ? [{ rel: 'icon', href: favicon, type: favicon.endsWith('.svg') ? 'image/svg+xml' : undefined }]
        : []
    },
  })
})
