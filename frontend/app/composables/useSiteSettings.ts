import { resolveMediaUrl } from '~/utils/media-url'

export interface SiteSettings {
  site_name?: string
  site_description?: string
  /** Actual keys returned by GET /site-settings — resolved, absolute media URLs. */
  site_logo?: string | null
  brand_logo?: string | null
  site_favicon?: string | null
  auth_page_image?: string | null
  logo?: string | null
  favicon?: string | null
  primary_color?: string
  theme_color_primary?: string
  contact_email?: string
  contact_phone?: string
  contact_address?: string
  address?: string
  legal_company_name?: string
}

function darken(hex: string, amount = 18) {
  const normalized = hex.replace('#', '')
  if (!/^[0-9a-f]{6}$/i.test(normalized)) return hex
  const channels = [0, 2, 4].map(index => Math.max(0, parseInt(normalized.slice(index, index + 2), 16) - amount))
  return `#${channels.map(value => value.toString(16).padStart(2, '0')).join('')}`
}

export function useSiteSettings() {
  const settings = useState<SiteSettings>('site-settings', () => ({}))
  const loaded = useState('site-settings-loaded', () => false)

  function applyBranding() {
    if (!import.meta.client) return
    const primary = settings.value.theme_color_primary || settings.value.primary_color
    if (primary) {
      document.documentElement.style.setProperty('--brand', primary)
      document.documentElement.style.setProperty('--brand-hover', darken(primary))
      document.documentElement.style.setProperty('--p-primary-color', primary)
      document.documentElement.style.setProperty('--p-primary-hover-color', darken(primary))
      document.documentElement.style.setProperty('--p-primary-active-color', darken(primary, 28))
    }
    if (settings.value.site_name) document.title = settings.value.site_name
    const favicon = resolveMediaUrl(settings.value.site_favicon || settings.value.favicon)
    if (favicon) {
      let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
      if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
      }
      link.href = favicon
    }
  }

  async function load() {
    if (loaded.value) return settings.value
    try {
      settings.value = await useApi<SiteSettings>('/site-settings', { token: null })
      applyBranding()
    }
    finally {
      loaded.value = true
    }
    return settings.value
  }

  return { settings, loaded, load, applyBranding }
}
