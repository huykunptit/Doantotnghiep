<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const orders = ref<any[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

async function fetchActivity(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>(`/admin/orders?per_page=20&page=${page}`, { headers: authHeaders() })
    orders.value = res.data || []
    currentPage.value = res.current_page || 1
    lastPage.value = res.last_page || 1
    total.value = res.total || 0
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải nhật ký hoạt động.'
  }
  finally {
    loading.value = false
  }
}

const today = new Date().toDateString()
const thisWeekStart = new Date()
thisWeekStart.setDate(thisWeekStart.getDate() - 7)

const todayCount = computed(() =>
  orders.value.filter(o => new Date(o.created_at).toDateString() === today).length
)

const weekCount = computed(() =>
  orders.value.filter(o => new Date(o.created_at) >= thisWeekStart).length
)

const paidToday = computed(() =>
  orders.value
    .filter(o => ['completed', 'paid'].includes(o.status) && new Date(o.created_at).toDateString() === today)
    .reduce((s, o) => s + (o.amount || 0), 0)
)

function timeAgo(dateStr: string) {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Vừa xong'
  if (mins < 60) return `${mins} phút trước`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} giờ trước`
  return new Date(dateStr).toLocaleDateString('vi-VN')
}

function statusLabel(status: string) {
  const map: Record<string, string> = { completed: 'Đã thanh toán', paid: 'Đã thanh toán', pending: 'Đang xử lý', failed: 'Thất bại' }
  return map[status] || status
}

function statusColor(status: string) {
  if (['completed', 'paid'].includes(status)) return '#16a34a'
  if (status === 'pending') return '#d97706'
  return '#dc2626'
}

function formatMoney(v: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0)
}

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Học viên', 'Email', 'Khóa học', 'Số tiền', 'Trạng thái', 'Thời gian']
  const rows = orders.value.slice(0, 200).map(o => [
    o.user?.name || '—',
    o.user?.email || '—',
    o.course?.title || '—',
    formatMoney(o.amount),
    statusLabel(o.status),
    o.created_at ? new Date(o.created_at).toLocaleString('vi-VN') : '—',
  ])
  exportToPDF('Nhật ký hoạt động', `Tổng ${total.value} giao dịch`, headers, rows, 'nhat-ky-hoat-dong')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Giao dịch' },
    { key: 'user_name', label: 'Học viên', format: (_: any, row: any) => row.user?.name || '—' },
    { key: 'user_email', label: 'Email', format: (_: any, row: any) => row.user?.email || '—' },
    { key: 'course_title', label: 'Khóa học', format: (_: any, row: any) => row.course?.title || '—' },
    { key: 'amount', label: 'Số tiền', format: (val: any) => String(val || 0) },
    { key: 'status', label: 'Trạng thái', format: (val: any) => statusLabel(val) },
    { key: 'created_at', label: 'Thời gian', format: (val: any) => val ? new Date(val).toLocaleString('vi-VN') : '—' }
  ]
  exportToCSV(orders.value, cols, 'nhat-ky-hoat-dong')
}

const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(lastPage.value, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) {
    if (i >= 1) range.push(i)
  }
  return range
})

onMounted(() => fetchActivity())
</script>

<template>
  <div class="report-page">
    <header class="page-header">
      <div><span>Hệ thống</span><h1>Nhật ký hoạt động</h1><p>Lịch sử giao dịch và hoạt động mới nhất trên hệ thống.</p></div>
      <div class="actions">
        <Button label="Xuất Excel" icon="pi pi-download" severity="secondary" outlined @click="exportCSV" />
        <Button label="Xuất PDF" icon="pi pi-file-pdf" @click="exportPDF" />
        <Button icon="pi pi-refresh" severity="secondary" text :loading="loading" aria-label="Làm mới" @click="fetchActivity(currentPage)" />
      </div>
    </header>

    <div class="metrics">
      <Card v-for="metric in [
        { label: 'Hôm nay', value: todayCount, note: 'giao dịch mới' },
        { label: 'Doanh thu hôm nay', value: formatMoney(paidToday), note: 'đã thanh toán' },
        { label: '7 ngày qua', value: weekCount, note: 'giao dịch' },
        { label: 'Tổng hoạt động', value: total, note: 'toàn hệ thống' },
      ]" :key="metric.label" class="metric-card">
        <template #content><small>{{ metric.label }}</small><strong>{{ metric.value }}</strong><span>{{ metric.note }}</span></template>
      </Card>
    </div>

    <Card>
      <template #title>Hoạt động gần nhất</template>
      <template #content>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <DataTable
          v-else
          :value="orders"
          :loading="loading"
          data-key="id"
          striped-rows
          responsive-layout="scroll"
          :lazy="true"
          paginator
          :rows="20"
          :total-records="total"
          :first="(currentPage - 1) * 20"
          @page="fetchActivity($event.page + 1)"
        >
          <Column header="Người dùng">
            <template #body="{ data }"><div class="primary-cell"><strong>{{ data.user?.name || 'Người dùng không rõ' }}</strong><small>{{ data.user?.email || '—' }}</small></div></template>
          </Column>
          <Column header="Khóa học"><template #body="{ data }"><span class="wrap-text">{{ data.course?.title || 'Khóa học' }}</span></template></Column>
          <Column header="Số tiền"><template #body="{ data }"><span class="money">{{ formatMoney(data.amount) }}</span></template></Column>
          <Column header="Trạng thái"><template #body="{ data }"><Tag :value="statusLabel(data.status)" :severity="['completed','paid'].includes(data.status) ? 'success' : data.status === 'pending' ? 'warn' : 'danger'" /></template></Column>
          <Column header="Thời gian"><template #body="{ data }"><span class="muted">{{ timeAgo(data.created_at) }}</span></template></Column>
          <template #empty>Không có hoạt động nào.</template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.metric-card span,.muted{color:var(--p-text-muted-color)}.actions{display:flex;gap:.5rem;flex-wrap:wrap}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.5rem;color:var(--p-text-color)}.primary-cell{display:flex;flex-direction:column}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.metrics{grid-template-columns:1fr}}
</style>
