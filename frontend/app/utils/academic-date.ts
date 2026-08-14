/** Parse API date/datetime (Y-m-d, ISO, datetime) without producing Invalid Date. */
export function parseAcademicDate(value: string | Date | null | undefined): Date | null {
  if (!value) return null
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value

  const raw = String(value).trim()
  if (!raw) return null

  const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (ymd) {
    const local = new Date(Number(ymd[1]), Number(ymd[2]) - 1, Number(ymd[3]))
    return Number.isNaN(local.getTime()) ? null : local
  }

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function toYmd(value: Date | string | null | undefined): string | null {
  const date = parseAcademicDate(value)
  if (!date) return null
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function formatAcademicDate(
  value: string | Date | null | undefined,
  locale: string = 'vi',
): string {
  const date = parseAcademicDate(value)
  if (!date) return '—'
  return date.toLocaleDateString(String(locale).toLowerCase().startsWith('vi') ? 'vi-VN' : 'en-GB')
}

export function formatAcademicRange(
  from: string | Date | null | undefined,
  to: string | Date | null | undefined,
  locale: string = 'vi',
): string {
  if (!from && !to) return '—'
  return `${formatAcademicDate(from, locale)} — ${formatAcademicDate(to, locale)}`
}
