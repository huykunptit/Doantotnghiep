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

  function exportToPDF(
    title: string,
    subtitle: string,
    tableHeaders: string[],
    tableRows: (string | number)[][][],
    filename = 'report',
  ) {
    if (!import.meta.client) return
    const date = new Date().toLocaleDateString('vi-VN', { year: 'numeric', month: 'long', day: 'numeric' })
    const thead = `<tr>${tableHeaders.map(h => `<th>${h}</th>`).join('')}</tr>`
    const tbody = tableRows.map(row =>
      `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`
    ).join('')

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>${title}</title>
    <style>
      body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 24px; color: #111; }
      .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 20px; }
      .header h1 { margin: 0; font-size: 22px; }
      .header p { margin: 4px 0 0; font-size: 13px; color: #666; }
      .date { font-size: 11px; color: #999; margin-top: 4px; }
      table { width: 100%; border-collapse: collapse; font-size: 12px; }
      th { background: #f3f4f6; text-align: left; padding: 8px 10px; font-weight: 700; border-bottom: 2px solid #e5e7eb; }
      td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
      tr:nth-child(even) td { background: #f9fafb; }
      @page { size: A4 landscape; margin: 15mm; }
      @media print { body { padding: 0; } }
    </style></head><body>
    <div class="header">
      <h1>${title}</h1>
      <p>${subtitle}</p>
      <p class="date">Xuất ngày: ${date}</p>
    </div>
    <table><thead>${thead}</thead><tbody>${tbody}</tbody></table>
    <script>window.onload=function(){window.print();window.close();}<\/script>
    </body></html>`

    const w = window.open('', '_blank')
    if (!w) return
    w.document.write(html)
    w.document.close()
  }

  return {
    exportToCSV,
    exportToPDF,
  }
}

