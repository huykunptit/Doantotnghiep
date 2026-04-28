interface ExportColumn<T = Record<string, unknown>> {
  key: keyof T | string
  label: string
  format?: (value: unknown, row: T) => string
}

function escapeCsv(value: string) {
  const normalized = value.replace(/"/g, '""')
  return /[",\n]/.test(normalized) ? `"${normalized}"` : normalized
}

export function useExport() {
  function exportToCSV<T extends Record<string, unknown>>(
    rows: T[],
    columns: ExportColumn<T>[],
    filename: string,
  ) {
    if (!import.meta.client) return

    const header = columns.map(column => escapeCsv(column.label)).join(',')
    const body = rows.map((row) => {
      return columns.map((column) => {
        const rawValue = row[column.key as keyof T]
        const formatted = column.format ? column.format(rawValue, row) : String(rawValue ?? '')
        return escapeCsv(formatted)
      }).join(',')
    }).join('\n')

    const csv = `${header}\n${body}`
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${filename}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  return {
    exportToCSV,
  }
}

