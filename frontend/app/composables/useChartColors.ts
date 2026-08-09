/**
 * Reads the live --brand / --text-muted / --border CSS custom properties
 * instead of hardcoding hex values — so charts follow the admin's theme
 * color (site-settings.theme_color_primary) and dark mode automatically,
 * rather than staying frozen at the original brand teal everywhere else.
 */
export function useChartColors() {
  function read(name: string, fallback: string) {
    if (!import.meta.client) return fallback
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim()
    return value || fallback
  }

  function colors() {
    const isDark = import.meta.client && document.documentElement.classList.contains('dark')
    const brand = read('--brand', '#0f766e')
    return {
      brand,
      brandSoft: hexToRgba(brand, 0.25),
      brandStrong: hexToRgba(brand, 0.35),
      text: read('--text-muted', isDark ? '#a8b8b4' : '#4a5a57'),
      grid: isDark ? 'rgba(255,255,255,.08)' : hexToRgba(brand, 0.12),
      // Deliberately distinct hues for multi-series charts — not tied to the
      // brand color, so they stay visually distinguishable from it either way.
      blue: '#2563eb',
      amber: '#d97706',
      violet: '#7c3aed',
      rose: '#e11d48',
      slate: '#64748b',
    }
  }

  return { colors }
}

function hexToRgba(hex: string, alpha: number) {
  const normalized = hex.replace('#', '')
  if (!/^[0-9a-f]{6}$/i.test(normalized)) return `rgba(15,118,110,${alpha})`
  const r = Number.parseInt(normalized.slice(0, 2), 16)
  const g = Number.parseInt(normalized.slice(2, 4), 16)
  const b = Number.parseInt(normalized.slice(4, 6), 16)
  return `rgba(${r},${g},${b},${alpha})`
}
