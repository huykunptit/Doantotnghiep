<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const allOrders = ref<any[]>([])
const dateFrom = ref('')
const dateTo = ref('')
const currentPage = ref(1)
const perPage = 20

async function fetchOrders() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>('/admin/orders?per_page=500', { headers: authHeaders() })
    allOrders.value = res.data || []
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu.'
  }
  finally {
    loading.value = false
  }
}

const filteredOrders = computed(() => {
  return allOrders.value.filter(o => {
    const d = new Date(o.paid_at || o.created_at)
    if (dateFrom.value && d < new Date(dateFrom.value)) return false
    if (dateTo.value && d > new Date(`${dateTo.value}T23:59:59`)) return false
    return true
  })
})

const paidOrders = computed(() =>
  filteredOrders.value.filter(o => ['completed', 'paid'].includes(o.status))
)

const kpis = computed(() => {
  const total = paidOrders.value.reduce((s, o) => s + (o.amount || 0), 0)
  const count = paidOrders.value.length
  return {
    totalRevenue: total,
    ordersCount: count,
    averageOrder: count ? Math.round(total / count) : 0,
    failedCount: filteredOrders.value.filter(o => o.status === 'failed').length,
  }
})

const monthlyChart = computed(() => {
  const map: Record<string, number> = {}
  paidOrders.value.forEach(o => {
    const d = new Date(o.paid_at || o.created_at)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    map[key] = (map[key] || 0) + (o.amount || 0)
  })
  return Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b))
    .slice(-6)
    .map(([key, value]) => {
      const [y, m] = key.split('-')
      return { label: `T${m}/${y.slice(2)}`, value }
    })
})

const maxBar = computed(() => Math.max(...monthlyChart.value.map(b => b.value), 1))

const topCourses = computed(() => {
  const map: Record<string, { title: string, revenue: number }> = {}
  paidOrders.value.forEach(o => {
    const title = o.course?.title || 'Không rõ'
    if (!map[title]) map[title] = { title, revenue: 0 }
    map[title].revenue += o.amount || 0
  })
  const list = Object.values(map).sort((a, b) => b.revenue - a.revenue).slice(0, 5)
  const max = Math.max(...list.map(c => c.revenue), 1)
  return list.map(c => ({ ...c, share: Math.round((c.revenue / max) * 100) }))
})

const paymentMethods = computed(() => {
  const map: Record<string, number> = {}
  paidOrders.value.forEach(o => {
    const m = o.payment_method || 'Khác'
    map[m] = (map[m] || 0) + 1
  })
  return Object.entries(map).map(([method, count]) => ({ method, count }))
})

// Pagination
const totalPages = computed(() => Math.max(1, Math.ceil(filteredOrders.value.length / perPage)))
const pagedOrders = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredOrders.value.slice(start, start + perPage)
})
const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  const end = Math.min(totalPages.value, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1)
  for (let i = start; i <= end; i++) range.push(i)
  return range
})

function formatMoney(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

function statusLabel(status: string) {
  const map: Record<string, string> = { completed: 'Hoàn tất', paid: 'Hoàn tất', pending: 'Đang xử lý', failed: 'Thất bại' }
  return map[status] || status
}

function statusClasses(status: string) {
  if (['completed', 'paid'].includes(status)) return 'bg-green-50 text-green-700'
  if (status === 'pending') return 'bg-amber-50 text-amber-700'
  return 'bg-red-50 text-red-600'
}

const { exportToPDF, exportToCSV } = useExport()

function doExportPDF() {
  const headers = ['Học viên', 'Khoá học', 'Số tiền', 'Trạng thái', 'Phương thức', 'Ngày TT']
  const rows = filteredOrders.value.slice(0, 200).map(o => [
    o.user?.name || '—',
    o.course?.title || '—',
    formatMoney(o.amount),
    statusLabel(o.status),
    o.payment_method || '—',
    o.paid_at ? new Date(o.paid_at).toLocaleDateString('vi-VN') : '—',
  ])
  exportToPDF('Báo cáo Doanh thu', `Tổng ${filteredOrders.value.length} giao dịch`, headers, rows, 'bao-cao-doanh-thu')
}

function doExportCSV() {
  const cols = [
    { key: 'id', label: 'ID' },
    { key: 'user_name', label: 'Học viên', format: (_: any, row: any) => row.user?.name || '—' },
    { key: 'user_email', label: 'Email', format: (_: any, row: any) => row.user?.email || '—' },
    { key: 'course_title', label: 'Khóa học', format: (_: any, row: any) => row.course?.title || '—' },
    { key: 'amount', label: 'Số tiền', format: (val: any) => String(val || 0) },
    { key: 'status', label: 'Trạng thái', format: (val: any) => statusLabel(val) },
    { key: 'payment_method', label: 'Phương thức' },
    { key: 'paid_at', label: 'Ngày TT', format: (val: any) => val ? new Date(val).toLocaleDateString('vi-VN') : '—' },
  ]
  exportToCSV(filteredOrders.value, cols, 'bao-cao-doanh-thu')
}

onMounted(fetchOrders)
</script>

<template>
  <div class="report-page">
    <header class="page-header"><div><span>Tài chính</span><h1>Báo cáo thanh toán</h1><p>Phân tích doanh thu và hiệu suất từ dữ liệu giao dịch.</p></div><div class="actions"><Button label="Excel" icon="pi pi-download" severity="secondary" outlined @click="doExportCSV"/><Button label="PDF" icon="pi pi-file-pdf" @click="doExportPDF"/></div></header>
    <Card><template #title>Bộ lọc thời gian</template><template #content><div class="filters"><InputText v-model="dateFrom" type="date" aria-label="Từ ngày"/><InputText v-model="dateTo" type="date" aria-label="Đến ngày"/><Button label="Đặt lại" severity="secondary" outlined @click="dateFrom='';dateTo='';currentPage=1"/></div></template></Card>
    <div class="metrics"><Card v-for="m in [{l:'Tổng doanh thu',v:formatMoney(kpis.totalRevenue)},{l:'Đơn thành công',v:kpis.ordersCount},{l:'Giá trị trung bình',v:formatMoney(kpis.averageOrder)},{l:'Đơn thất bại',v:kpis.failedCount}]" :key="m.l" class="metric-card"><template #content><small>{{m.l}}</small><strong>{{m.v}}</strong></template></Card></div>
    <Message v-if="error" severity="error" :closable="false">{{error}}</Message>
    <div v-else class="distribution">
      <Card><template #title>Doanh thu theo tháng</template><template #content><div v-if="monthlyChart.length" class="revenue-chart"><div v-for="item in monthlyChart" :key="item.label" class="chart-bar-wrap"><div class="chart-bar" :style="{height:`${(item.value/maxBar)*100}%`}" :title="formatMoney(item.value)"/><span>{{item.label}}</span></div></div><p v-else class="muted">Chưa có dữ liệu.</p></template></Card>
      <Card><template #title>Khóa học doanh thu cao</template><template #content><div class="list"><div v-for="c in topCourses" :key="c.title"><div class="list-row"><strong>{{c.title}}</strong><span class="money">{{formatMoney(c.revenue)}}</span></div><div class="bar"><i :style="{width:`${c.share}%`}"/></div></div></div></template></Card>
    </div>
    <Card><template #title>Chi tiết giao dịch</template><template #content>
      <DataTable :value="pagedOrders" :loading="loading" data-key="id" striped-rows responsive-layout="scroll" paginator :rows="perPage" :total-records="filteredOrders.length" :first="(currentPage-1)*perPage" @page="currentPage=$event.page+1">
        <Column header="Học viên"><template #body="{data}"><div class="primary-cell"><strong>{{data.user?.name||'—'}}</strong><small>{{data.user?.email||'—'}}</small></div></template></Column>
        <Column header="Khóa học"><template #body="{data}"><span class="wrap-text">{{data.course?.title||'—'}}</span></template></Column>
        <Column header="Số tiền"><template #body="{data}"><span class="money">{{formatMoney(data.amount||0)}}</span></template></Column>
        <Column header="Trạng thái"><template #body="{data}"><Tag :value="statusLabel(data.status)" :severity="['completed','paid'].includes(data.status)?'success':data.status==='pending'?'warn':'danger'"/></template></Column>
        <Column field="payment_method" header="Phương thức"/>
        <Column header="Ngày TT"><template #body="{data}"><span class="muted">{{data.paid_at?new Date(data.paid_at).toLocaleDateString('vi-VN'):'—'}}</span></template></Column>
        <template #empty>Không có giao dịch nào.</template>
      </DataTable>
    </template></Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>

<style scoped>.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.detail-grid>div{display:flex;flex-direction:column;gap:.25rem}.detail-grid small{color:var(--p-text-muted-color)}.revenue-chart{display:flex;align-items:flex-end;height:13rem;gap:1rem;border-bottom:1px solid var(--p-content-border-color)}.chart-bar-wrap{height:100%;flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:.5rem}.chart-bar{width:min(3rem,70%);min-height:.25rem;background:var(--p-primary-color);border-radius:.4rem .4rem 0 0}@media(max-width:520px){.detail-grid{grid-template-columns:1fr}}</style>