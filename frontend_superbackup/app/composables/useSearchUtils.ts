/**
 * Search utilities for menu search
 */

export function removeVietnameseTones(str: string): string {
  if (!str) return ''
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // Remove diacritical marks
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
}

export interface FlattenedMenuItem {
  label: string
  to: string
  icon?: string
  path: string
}

function flattenMenuItems(items: any[], parentPath = ''): FlattenedMenuItem[] {
  const flattened: FlattenedMenuItem[] = []
  if (!items || !Array.isArray(items)) return flattened

  items.forEach((item) => {
    if (!item) return
    const currentPath = parentPath ? `${parentPath} > ${item.label}` : item.label

    // Only add items with actual path (leaf nodes)
    if (item.to) {
      flattened.push({
        label: item.label,
        to: item.to,
        icon: item.icon || 'pi pi-file',
        path: currentPath,
      })
    }

    if (item.items && Array.isArray(item.items)) {
      flattened.push(...flattenMenuItems(item.items, currentPath))
    }
  })

  return flattened
}

export function searchMenuItems(query: string, menuItems: any[]): FlattenedMenuItem[] {
  if (!query || !query.trim()) return []
  if (!menuItems || !Array.isArray(menuItems)) return []

  const flattened = flattenMenuItems(menuItems)
  const normalizedQuery = removeVietnameseTones(query.trim())

  return flattened.filter((item) => {
    const normalizedLabel = removeVietnameseTones(item.label)
    const normalizedPath = removeVietnameseTones(item.path)
    return normalizedLabel.includes(normalizedQuery) || normalizedPath.includes(normalizedQuery)
  })
}
