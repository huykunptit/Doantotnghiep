export interface PublicSiteSettings {
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

  const siteName = computed(() => settings.value?.site_name?.trim() || FALLBACK_NAME)
  const siteLogo = computed(() => settings.value?.site_logo || null)
  const siteFavicon = computed(() => settings.value?.site_favicon || null)
  const siteTagline = computed(() => settings.value?.site_tagline || null)

  async function refreshSettings() {
    try {
      settings.value = await useApi<PublicSiteSettings>('/site-settings')
    } catch {
      // Keep existing values on failure
    }
  }

  return { settings, siteName, siteLogo, siteFavicon, siteTagline, refreshSettings }
}
