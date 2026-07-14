export default defineNuxtPlugin(async () => {
  const { settings, siteTitle, siteFavicon, themeColorPrimary, themeColorDeep, refreshSettings } = useSiteSettings()

  if (!settings.value) {
    await refreshSettings()
  }

  // Write CSS vars to :root so all --theme-primary / --p-primary-* classes work
  applyThemeCssVars(themeColorPrimary.value, themeColorDeep.value)

  // Re-apply whenever settings change (e.g. after hot-reload or future refresh)
  watch(
    [themeColorPrimary, themeColorDeep],
    ([primary, secondary]) => applyThemeCssVars(primary, secondary),
  )

  useHead({
    title: () => siteTitle.value,
    titleTemplate: (chunk) =>
      chunk && chunk !== siteTitle.value ? `${chunk} · ${siteTitle.value}` : siteTitle.value,
    link: () => {
      const favicon = siteFavicon.value
      return favicon
        ? [{ rel: 'icon', href: favicon, type: favicon.endsWith('.svg') ? 'image/svg+xml' : undefined }]
        : []
    },
  })
})
