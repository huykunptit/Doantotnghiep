<script setup lang="ts">
import { computed, onMounted, ref, reactive } from 'vue'
import { useAuthUserCookie, useAuthTokenCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

// Unified UI Components

definePageMeta({
  layout: 'admin',
  adminSearchPlaceholder: 'Tìm giao dịch, học viên, khóa học...'
})

interface OrderItem {
  id: number
  amount: number
  status: string
  payment_method?: string | null
  payment_ref?: string | null
  paid_at?: string | null
  created_at?: string | null
  user?: {
    name: string
    email: string
    avatar?: string | null
  } | null
  course?: {
    title: string
    thumbnail?: string | null
  } | null
  gateway_response?: Record<string, unknown> | null
}

interface PaginatedOrders {
  data: OrderItem[]
  current_page: number
  last_page: number
  total: number
}

const user = useAuthUserCookie()
if (!user.value) {
  await navigateTo('/login', { replace: true })
}

const token = useAuthTokenCookie()
const orders = ref<OrderItem[]>([])
const loading = ref(false)
const detailOpen = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const totalOrders = ref(0)
const perPage = ref(10)
const search = ref('')
const status = ref('')
const selectedOrder = ref<OrderItem | null>(null)
const selectedIds = ref<number[]>([])
const activeDropdown = ref<number | null>(null)

const sortBy = ref('')
const sortOrder = ref<'asc' | 'desc' | ''>('')

const { exportToCSV } = useExport()
const toast = useToast()

const columns = [
  { id: 'select', accessorKey: 'select', header: '' },
  { id: 'index', accessorKey: 'index', header: '#' },
  { id: 'user', accessorKey: 'user', header: 'Người mua' },
  { id: 'course', accessorKey: 'course', header: 'Khóa học' },
  { id: 'amount', accessorKey: 'amount', header: 'Số tiền', sortable: true },
  { id: 'status', accessorKey: 'status', header: 'Thanh toán', sortable: true },
  { id: 'created_at', accessorKey: 'created_at', header: 'Thời gian', sortable: true },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

function exportData() {
  const cols = [
    { key: 'id', label: 'ID Đơn hàng' },
    { key: 'user_name', label: 'Học viên', format: (_: any, row: OrderItem) => row.user?.name || '--' },
    { key: 'user_email', label: 'Email', format: (_: any, row: OrderItem) => row.user?.email || '--' },
    { key: 'course_title', label: 'Khóa học', format: (_: any, row: OrderItem) => row.course?.title || '--' },
    { key: 'amount', label: 'Số tiền (VND)', format: (val: any) => String(val || 0) },
    { key: 'status', label: 'Trạng thái' },
    { key: 'payment_method', label: 'Phương thức', format: (val: any) => String(val || '--') },
    { key: 'created_at', label: 'Thời gian', format: (val: any) => formatDate(val) }
  ]
  exportToCSV(orders.value, cols, 'danh_sach_don_hang')
}

const isAllSelected = computed(() => {
  return orders.value.length > 0 && orders.value.every(o => selectedIds.value.includes(o.id))
})

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = orders.value.map(o => o.id)
  }
}

function toggleDropdown(id: number) {
  activeDropdown.value = activeDropdown.value === id ? null : id
}

const statuses = [
  { label: 'Tất cả', value: '' },
  { label: 'Đã thanh toán', value: 'completed' },
  { label: 'Đang xử lý', value: 'pending' },
  { label: 'Thất bại', value: 'failed' }
]

const authHeaders = () => ({
  Authorization: `Bearer ${token.value}`
})

const paidCount = computed(() => {
  return orders.value.filter(item => ['completed', 'paid'].includes(item.status)).length
})

const totalRevenue = computed(() => {
  return orders.value
    .filter(item => ['completed', 'paid'].includes(item.status))
    .reduce((sum, item) => sum + (item.amount || 0), 0)
})

function formatMoney(value: number) {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND'
  }).format(value || 0)
}

function formatDate(value?: string | null) {
  return value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : '--'
}

async function fetchOrders(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams({
      page: String(page),
      per_page: String(perPage.value)
    })

    if (search.value.trim()) {
      query.set('search', search.value.trim())
    }

    if (status.value) {
      query.set('status', status.value)
    }

    if (sortBy.value && sortOrder.value) {
      query.set('sort_by', sortBy.value)
      query.set('sort_order', sortOrder.value)
    }

    const response = await useApi<PaginatedOrders>(`/admin/orders?${query.toString()}`, {
      headers: authHeaders()
    })

    orders.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    totalOrders.value = response.total
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tải đơn hàng.')
  } finally {
    loading.value = false
  }
}

async function openDetail(order: OrderItem) {
  selectedOrder.value = await useApi<OrderItem>(`/admin/orders/${order.id}`, {
    headers: authHeaders()
  })
  detailOpen.value = true
}

const activeFilterCount = computed(() => {
  return [status.value].filter(Boolean).length
})

const activeChips = computed(() => {
  const chips = []
  if (status.value) {
    const map: Record<string, string> = { completed: 'Đã thanh toán', pending: 'Đang xử lý', failed: 'Thất bại' }
    chips.push({ key: 'status', label: `Trạng thái: ${map[status.value] || status.value}` })
  }
  return chips
})

function handleRemoveChip(key: string) {
  if (key === 'status') {
    status.value = ''
    fetchOrders(1)
  }
}

function handleSort(event: { key: string; order: 'asc' | 'desc' | '' }) {
  sortBy.value = event.key
  sortOrder.value = event.order
  fetchOrders(1)
}

function resetFilters() {
  search.value = ''
  status.value = ''
  sortBy.value = ''
  sortOrder.value = ''
  fetchOrders(1)
}

onMounted(fetchOrders)
</script>

<template>
  <div class="report-page">
    <Toast/>
    <header class="page-header"><div><span>Tài chính</span><h1>Quản lý đơn hàng</h1><p>Theo dõi giao dịch và trạng thái thanh toán.</p></div><Button label="Xuất Excel" icon="pi pi-download" severity="secondary" outlined @click="exportData"/></header>
    <Card><template #title>Bộ lọc</template><template #content><div class="filters"><InputText v-model="search" placeholder="Tìm giao dịch, học viên, khóa học..." @keyup.enter="fetchOrders(1)"/><Select v-model="status" :options="statuses" option-label="label" option-value="value" placeholder="Trạng thái" @change="fetchOrders(1)"/><Button label="Tìm kiếm" icon="pi pi-search" @click="fetchOrders(1)"/><Button label="Đặt lại" severity="secondary" outlined @click="resetFilters"/></div></template></Card>
    <div class="metrics"><Card v-for="m in [{l:'Tổng đơn hàng',v:totalOrders},{l:'Đã thanh toán',v:paidCount},{l:'Doanh thu trang',v:formatMoney(totalRevenue)}]" :key="m.l" class="metric-card"><template #content><small>{{m.l}}</small><strong>{{m.v}}</strong></template></Card></div>
    <Card><template #content>
      <DataTable :value="orders" :loading="loading" data-key="id" striped-rows responsive-layout="scroll" lazy paginator :rows="perPage" :total-records="totalOrders" :first="(currentPage-1)*perPage" @page="perPage=$event.rows;fetchOrders($event.page+1)" @sort="sortBy=$event.sortField||'';sortOrder=$event.sortOrder===1?'asc':$event.sortOrder===-1?'desc':'';fetchOrders(1)">
        <Column header-style="width:3rem"><template #header><Checkbox :model-value="isAllSelected" binary aria-label="Chọn tất cả" @update:model-value="toggleSelectAll"/></template><template #body="{data}"><Checkbox v-model="selectedIds" :value="data.id" :input-id="`order-${data.id}`"/></template></Column>
        <Column header="#"><template #body="{index}">{{(currentPage-1)*perPage+index+1}}</template></Column>
        <Column header="Người mua"><template #body="{data}"><div class="primary-cell"><strong>{{data.user?.name||'—'}}</strong><small>{{data.user?.email||'—'}}</small></div></template></Column>
        <Column header="Khóa học"><template #body="{data}"><span class="wrap-text">{{data.course?.title||'—'}}</span></template></Column>
        <Column field="amount" header="Số tiền" sortable><template #body="{data}"><span class="money">{{formatMoney(data.amount)}}</span></template></Column>
        <Column field="status" header="Thanh toán" sortable><template #body="{data}"><Tag :value="['completed','paid'].includes(data.status)?'Thành công':data.status==='failed'?'Thất bại':'Đang xử lý'" :severity="['completed','paid'].includes(data.status)?'success':data.status==='failed'?'danger':'warn'"/></template></Column>
        <Column field="created_at" header="Thời gian" sortable><template #body="{data}"><span class="muted">{{formatDate(data.paid_at||data.created_at)}}</span></template></Column>
        <Column header=""><template #body="{data}"><Button label="Chi tiết" size="small" severity="secondary" text @click="openDetail(data)"/></template></Column>
        <template #empty>Không tìm thấy đơn hàng.</template>
      </DataTable>
    </template></Card>
    <Dialog v-model:visible="detailOpen" modal :header="`Hóa đơn #${selectedOrder?.id||''}`" :style="{width:'min(42rem,95vw)'}">
      <div class="detail-grid"><div><small>Học viên</small><strong>{{selectedOrder?.user?.name||'—'}}</strong><span>{{selectedOrder?.user?.email||'—'}}</span></div><div><small>Khóa học</small><strong>{{selectedOrder?.course?.title||'—'}}</strong></div><div><small>Số tiền</small><strong class="money">{{selectedOrder?formatMoney(selectedOrder.amount):'—'}}</strong></div><div><small>Phương thức</small><strong>{{selectedOrder?.payment_method||'—'}}</strong></div><div><small>Mã tham chiếu</small><code>{{selectedOrder?.payment_ref||'—'}}</code></div><div><small>Trạng thái</small><Tag :value="selectedOrder?.status||'—'"/></div></div>
      <template #footer><Button label="Đóng" severity="secondary" @click="detailOpen=false"/></template>
    </Dialog>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>

<style scoped>.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.detail-grid>div{display:flex;flex-direction:column;gap:.25rem}.detail-grid small{color:var(--p-text-muted-color)}.revenue-chart{display:flex;align-items:flex-end;height:13rem;gap:1rem;border-bottom:1px solid var(--p-content-border-color)}.chart-bar-wrap{height:100%;flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:.5rem}.chart-bar{width:min(3rem,70%);min-height:.25rem;background:var(--p-primary-color);border-radius:.4rem .4rem 0 0}@media(max-width:520px){.detail-grid{grid-template-columns:1fr}}</style>