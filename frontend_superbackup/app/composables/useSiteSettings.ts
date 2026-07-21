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

const FALLBACK_NAME = 'Sylva LMS'
const FALLBACK_PRIMARY = '#0ea5e9'
const FALLBACK_SECONDARY = '#0369a1'

// ── Hex helpers ─────────────────────────────────────────────────

function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
  const clean = hex.replace('#', '')
  const full = clean.length === 3
    ? clean.split('').map(c => c + c).join('')
    : clean
  const num = parseInt(full, 16)
  if (isNaN(num)) return null
  return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 }
}

function adjustBrightness(hex: string, pct: number): string {
  const rgb = hexToRgb(hex)
  if (!rgb) return hex
  const clamp = (v: number) => Math.max(0, Math.min(255, Math.round(v)))
  const factor = 1 + pct / 100
  return `#${[rgb.r, rgb.g, rgb.b].map(c => clamp(c * factor).toString(16).padStart(2, '0')).join('')}`
}

/** Build a 9-step palette (50–900) similar to admin-ui themeStore */
function buildPalette(hex: string): Record<string, string> {
  const steps = [
    [50,  92], [100, 85], [200, 65], [300, 40],
    [400, 20], [500, 0],  [600, -12], [700, -20],
    [800, -28], [900, -36],
  ] as const
  return Object.fromEntries(
    steps.map(([step, pct]) => [step, pct === 0 ? hex : adjustBrightness(hex, pct)]),
  )
}

/**
 * Write all theme CSS variables to :root.
 * Called client-side after site-settings load.
 * Mirrors admin-ui themeStore.applyTheme().
 */
export function applyThemeCssVars(primary: string, secondary: string): void {
  if (typeof document === 'undefined') return

  const rgb = hexToRgb(primary)
  const rgbStr = rgb ? `${rgb.r}, ${rgb.g}, ${rgb.b}` : '14, 165, 233'

  const secRgb = hexToRgb(secondary)
  const secRgbStr = secRgb ? `${secRgb.r}, ${secRgb.g}, ${secRgb.b}` : '3, 105, 161'

  const palette = buildPalette(primary)
  const root = document.documentElement

  // Primary vars
  root.style.setProperty('--theme-primary', primary)
  root.style.setProperty('--theme-primary-hover', adjustBrightness(primary, -12))
  root.style.setProperty('--theme-primary-active', adjustBrightness(primary, -20))
  root.style.setProperty('--theme-primary-dark', adjustBrightness(primary, -25))
  root.style.setProperty('--theme-primary-light', palette[200])
  root.style.setProperty('--theme-primary-lighter', palette[100])
  root.style.setProperty('--theme-primary-rgb', rgbStr)

  // Secondary vars
  root.style.setProperty('--theme-secondary', secondary)
  root.style.setProperty('--theme-secondary-hover', adjustBrightness(secondary, -12))
  root.style.setProperty('--theme-secondary-rgb', secRgbStr)

  // PrimeVue palette aliases (--p-primary-50 … --p-primary-900)
  for (const [step, color] of Object.entries(palette)) {
    root.style.setProperty(`--p-primary-${step}`, color)
  }
  // Also set --p-primary-500 = primary (PrimeVue uses this for button bg)
  root.style.setProperty('--p-primary-500', primary)
}

// ── Composable ───────────────────────────────────────────────────

export function useSiteSettingsState() {
  return useState<PublicSiteSettings | null>('site-settings', () => null)
}

export function useSiteSettings() {
  const settings = useSiteSettingsState()

  const brandName = computed(() =>
    settings.value?.brand_name?.trim() || settings.value?.site_name?.trim() || FALLBACK_NAME,
  )
  const brandMark = computed(() =>
    settings.value?.brand_mark?.trim() || brandName.value.slice(0, 1).toUpperCase(),
  )
  const brandLogo = computed(() =>
    settings.value?.brand_logo || settings.value?.site_logo || '/logo.png',
  )
  const siteTitle       = computed(() => settings.value?.site_title?.trim() || brandName.value)
  const siteName        = computed(() => brandName.value)
  const siteLogo        = computed(() => brandLogo.value)
  const siteFavicon     = computed(() => settings.value?.site_favicon || '/logo.png')
  const siteTagline     = computed(() => settings.value?.site_tagline || null)
  const authPageImage   = computed(() =>
    settings.value?.auth_page_image || '/hoc-vien-cong-nghe-buu-chinh-vien-thong.jpg',
  )
  const themeColorPrimary   = computed(() => settings.value?.theme_color_primary || FALLBACK_PRIMARY)
  const themeColorDeep      = computed(() => settings.value?.theme_color_deep || FALLBACK_SECONDARY)

  async function refreshSettings() {
    try {
      settings.value = await useApi<PublicSiteSettings>('/site-settings')
    } catch {
      // keep existing values on failure
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
