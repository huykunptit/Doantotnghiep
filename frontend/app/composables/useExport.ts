export function useExport() {
  const exportToCSV = (data: any[], columns: { key: string; label: string; format?: (v: any, row?: any) => string }[], filename: string) => {
    if (!data || !data.length) {
      alert('Không có dữ liệu để xuất CSV')
      return
    }

    // Prepare headers
    const headers = columns.map(col => `"${String(col.label).replace(/"/g, '""')}"`)
    
    // Prepare rows
    const rows = data.map(item => {
      return columns.map(col => {
        let val = item[col.key]

        // Handle nested paths like user.name
        if (col.key.includes('.')) {
            val = col.key.split('.').reduce((obj, k) => (obj || {})[k], item)
        }

        if (col.format) {
          val = col.format(val, item)
        }
        
        // Handle null/undefined
        if (val === null || val === undefined) val = ''
        
        // Escape quotes
        const stringVal = String(val).replace(/"/g, '""')
        return `"${stringVal}"`
      }).join(',')
    })

    // Combine headers and rows
    const csvContent = [headers.join(','), ...rows].join('\n')

    // Add BOM for UTF-8 Excel compatibility
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
    
    // Create download link
    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    link.setAttribute('href', url)
    link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`)
    link.style.visibility = 'hidden'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  return {
    exportToCSV
  }
}
