export interface PublicSiteSettings {
  theme_color_primary: string | null
  theme_color_deep: string | null
  brand_name: string | null
  brand_mark: string | null
  brand_logo: string | null
  site_title: string | null
  auth_page_image: string | null
  site_name: string | null
  site_tagline: string | null
  site_description: string | null
  site_logo: string | null
  site_favicon: string | null
  contact_email: string | null
  contact_phone: string | null
  contact_address: string | null
  support_hours: string | null
  social_facebook: string | null
  social_youtube: string | null
  social_tiktok: string | null
  social_linkedin: string | null
  social_zalo: string | null
  footer_copyright: string | null
  legal_company_name: string | null
  legal_tax_code: string | null
  terms_url: string | null
  privacy_url: string | null
  default_locale: string | null
  default_currency: string | null
  timezone: string | null
}

const FALLBACK_NAME = 'PTIT LMS'

export function useSiteSettingsState() {
  return useState<PublicSiteSettings | null>('site-settings', () => null)
}

export function useSiteSettings() {
  const settings = useSiteSettingsState()

  const brandName = computed(() => settings.value?.brand_name?.trim() || settings.value?.site_name?.trim() || FALLBACK_NAME)
  const brandMark = computed(() => settings.value?.brand_mark?.trim() || brandName.value.slice(0, 1).toUpperCase())
  const brandLogo = computed(() => settings.value?.brand_logo || settings.value?.site_logo || null)
  const siteTitle = computed(() => settings.value?.site_title?.trim() || brandName.value)
  const siteName = computed(() => brandName.value)
  const siteLogo = computed(() => brandLogo.value)
  const siteFavicon = computed(() => settings.value?.site_favicon || null)
  const siteTagline = computed(() => settings.value?.site_tagline || null)
  const authPageImage = computed(() => settings.value?.auth_page_image || null)
  const themeColorPrimary = computed(() => settings.value?.theme_color_primary || '#2f7a45')
  const themeColorDeep = computed(() => settings.value?.theme_color_deep || '#1f5d33')

  async function refreshSettings() {
    try {
      settings.value = await useApi<PublicSiteSettings>('/site-settings')
    } catch {
      // Keep existing values on failure
    }
  }

  return {
    settings,
    brandName,
    brandMark,
    brandLogo,
    siteTitle,
    siteName,
    siteLogo,
    siteFavicon,
    siteTagline,
    authPageImage,
    themeColorPrimary,
    themeColorDeep,
    refreshSettings,
  }
}
