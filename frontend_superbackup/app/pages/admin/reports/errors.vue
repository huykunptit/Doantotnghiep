<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const allOrders = ref<any[]>([])
const search = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

async function fetchFailedOrders(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>(
      `/admin/orders?status=failed&per_page=15&page=${page}`,
      { headers: authHeaders() }
    )
    allOrders.value = res.data || []
    currentPage.value = res.current_page || 1
    lastPage.value = res.last_page || 1
    total.value = res.total || 0
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu.'
  }
  finally {
    loading.value = false
  }
}

const filteredOrders = computed(() => {
  if (!search.value.trim()) return allOrders.value
  const q = search.value.toLowerCase()
  return allOrders.value.filter(o =>
    o.user?.name?.toLowerCase().includes(q)
    || o.user?.email?.toLowerCase().includes(q)
    || o.course?.title?.toLowerCase().includes(q)
  )
})

function formatMoney(v: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0)
}

function formatDate(v?: string) {
  return v ? new Date(v).toLocaleString('vi-VN') : '—'
}

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Người dùng', 'Email', 'Khóa học', 'Số tiền', 'Phương thức', 'Thời gian']
  const rows = filteredOrders.value.slice(0, 200).map(o => [
    o.user?.name || '—',
    o.user?.email || '—',
    o.course?.title || '—',
    formatMoney(o.amount),
    o.payment_method || '—',
    formatDate(o.created_at),
  ])
  exportToPDF('Lịch sử lỗi', `${total.value} giao dịch thất bại`, headers, rows, 'lich-su-loi')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Đơn hàng' },
    { key: 'user_name', label: 'Người dùng', format: (_: any, row: any) => row.user?.name || '—' },
    { key: 'user_email', label: 'Email', format: (_: any, row: any) => row.user?.email || '—' },
    { key: 'course_title', label: 'Khóa học', format: (_: any, row: any) => row.course?.title || '—' },
    { key: 'amount', label: 'Số tiền', format: (val: any) => String(val || 0) },
    { key: 'payment_method', label: 'Phương thức', format: (val: any) => String(val || '—') },
    { key: 'created_at', label: 'Thời gian', format: (val: any) => formatDate(val) }
  ]
  exportToCSV(filteredOrders.value, cols, 'danh_sach_giao_dich_that_bai')
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

onMounted(() => fetchFailedOrders())
</script>

<template>
  <div class="report-page">
    <header class="page-header"><div><span>Hỗ trợ</span><h1>Lịch sử lỗi & giao dịch thất bại</h1><p>Các giao dịch thanh toán thất bại cần kiểm tra.</p></div><div class="actions"><Button label="Excel" icon="pi pi-download" severity="secondary" outlined @click="exportCSV"/><Button label="PDF" icon="pi pi-file-pdf" @click="exportPDF"/><Button icon="pi pi-refresh" severity="secondary" text :loading="loading" @click="fetchFailedOrders(currentPage)"/></div></header>
    <div class="notice"><strong>Phạm vi dữ liệu:</strong> giao dịch thất bại đang được dùng làm chỉ báo lỗi thanh toán; nhật ký ứng dụng đầy đủ cần nguồn Sentry hoặc ELK từ backend.</div>
    <div class="metrics"><Card class="metric-card"><template #content><small>Giao dịch thất bại</small><strong>{{ total }}</strong><span>Cần kiểm tra</span></template></Card><Card class="metric-card"><template #content><small>Tổng giá trị thất bại</small><strong>{{ formatMoney(allOrders.reduce((s,o)=>s+(o.amount||0),0)) }}</strong><span>Không thu được</span></template></Card><Card class="metric-card"><template #content><small>Trên trang này</small><strong>{{ filteredOrders.length }}</strong><span>Bản ghi</span></template></Card></div>
    <Card><template #title>Giao dịch thất bại</template><template #content>
      <div class="filters"><InputText v-model="search" placeholder="Tìm học viên, email hoặc khóa học..."/></div>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <DataTable v-else :value="filteredOrders" :loading="loading" data-key="id" striped-rows responsive-layout="scroll" lazy paginator :rows="15" :total-records="total" :first="(currentPage-1)*15" @page="fetchFailedOrders($event.page+1)">
        <Column header="Người dùng"><template #body="{data}"><div class="primary-cell"><strong>{{ data.user?.name||'—' }}</strong><small>{{ data.user?.email||'—' }}</small></div></template></Column>
        <Column header="Khóa học"><template #body="{data}"><span class="wrap-text">{{ data.course?.title||'—' }}</span></template></Column>
        <Column header="Số tiền"><template #body="{data}"><span class="money">{{ formatMoney(data.amount) }}</span></template></Column>
        <Column field="payment_method" header="Phương thức"/>
        <Column header="Tham chiếu"><template #body="{data}"><code>{{ data.payment_ref||data.id }}</code></template></Column>
        <Column header="Thời gian"><template #body="{data}"><span class="muted">{{ formatDate(data.created_at) }}</span></template></Column>
        <template #empty>Không có giao dịch thất bại.</template>
      </DataTable>
    </template></Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>
