/**
 * Client search: bỏ dấu tiếng Việt, HTML, và khoảng trắng thừa.
 * "toan" khớp "Toán", "nhap mon" khớp "Nhập môn".
 */
export function normalizeSearch(input: unknown): string {
  return String(input ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'd')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase()
}

export function textMatches(haystack: unknown, query: unknown): boolean {
  const needle = normalizeSearch(query)
  if (!needle) return true
  return normalizeSearch(haystack).includes(needle)
}

export function matchesAny(query: unknown, ...fields: unknown[]): boolean {
  return fields.some(field => textMatches(field, query))
}
