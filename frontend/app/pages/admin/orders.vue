<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useAuthUserCookie, useAuthTokenCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

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

const { exportToCSV } = useExport()
const toast = useToast()

const columns = [
  { id: 'select', accessorKey: 'select', header: '' },
  { id: 'index', accessorKey: 'index', header: 'STT' },
  { id: 'user', accessorKey: 'user', header: 'Người mua' },
  { id: 'course', accessorKey: 'course', header: 'Khóa học' },
  { id: 'amount', accessorKey: 'amount', header: 'Số tiền' },
  { id: 'status', accessorKey: 'status', header: 'Thanh toán' },
  { id: 'created_at', accessorKey: 'created_at', header: 'Thời gian' },
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

onMounted(fetchOrders)
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Quản trị hệ thống</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Quản lý đơn hàng</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Theo dõi giao dịch thanh toán theo chuẩn bảng quản trị thống nhất, có bộ lọc, thống kê nhanh và modal xem chi tiết.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(29,158,117,0.06)] border-[rgba(29,158,117,0.2)]">
        <p class="text-xs font-bold uppercase tracking-wider text-[#1d9e75]">Tổng đơn</p>
        <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ totalOrders }}</strong>
        <span class="text-xs text-[var(--muted)] font-medium">theo bộ lọc hiện tại</span>
      </div>
      <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(245,158,11,0.06)] border-[rgba(245,158,11,0.2)]">
        <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Đã thanh toán</p>
        <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ paidCount }}</strong>
        <span class="text-xs text-[var(--muted)] font-medium">đơn thành công</span>
      </div>
      <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(59,130,246,0.06)] border-[rgba(59,130,246,0.2)]">
        <p class="text-xs font-bold uppercase tracking-wider text-blue-600">Doanh thu</p>
        <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ formatMoney(totalRevenue) }}</strong>
        <span class="text-xs text-[var(--muted)] font-medium">tạm tính hiện tại</span>
      </div>
    </div>

    <!-- Table panel -->
    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
      <div class="flex flex-wrap gap-3 items-center px-5 py-4 border-b border-[var(--line)]">
        <form class="flex flex-1 min-w-0 gap-2" @submit.prevent="fetchOrders(1)">
          <div class="relative flex-1 min-w-[180px] max-w-xs">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)]" style="font-size:0.8rem" />
            <input
              v-model="search"
              class="w-full h-9 pl-8 pr-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]"
              placeholder="Tên, email, khóa học..."
              type="text"
            >
          </div>
          <select
            v-model="status"
            class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer"
            @change="fetchOrders(1)"
          >
            <option v-for="item in statuses" :key="item.value" :value="item.value">
              {{ item.label }}
            </option>
          </select>
        </form>

        <div class="flex items-center gap-2 shrink-0">
          <button
            class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors"
            type="button"
            @click="exportData"
          >
            <i class="pi pi-download" style="font-size:0.8rem" /> Xuất Excel
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <UTable :columns="columns" :data="orders" :loading="loading">
          <!-- Header slot for select checkbox -->
          <template #select-header>
            <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75]">
          </template>

          <template #select-cell="{ row }">
            <input type="checkbox" v-model="selectedIds" :value="row.original.id" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75]">
          </template>
          
          <template #index-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ (currentPage - 1) * perPage + row.index + 1 }}</span>
          </template>
          
          <template #user-cell="{ row }">
            <div class="flex items-center gap-3">
              <div v-if="row.original.user?.avatar" class="w-8 h-8 rounded-full overflow-hidden border border-[var(--line)]">
                <img :src="row.original.user.avatar" :alt="row.original.user.name" class="w-full h-full object-cover">
              </div>
              <div v-else class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-[rgba(29,158,117,0.1)] text-[#085041] border border-[rgba(29,158,117,0.2)]">
                {{ row.original.user?.name?.slice(0,2).toUpperCase() || 'KH' }}
              </div>
              <div class="flex flex-col">
                <span class="text-sm font-semibold text-[var(--text)]">{{ row.original.user?.name || '--' }}</span>
                <span class="text-xs text-[var(--muted)]">{{ row.original.user?.email || '--' }}</span>
              </div>
            </div>
          </template>

          <template #course-cell="{ row }">
            <div class="flex items-center gap-3">
              <div class="w-10 h-7 rounded bg-[var(--surface)] border border-[var(--line)] flex items-center justify-center overflow-hidden shrink-0">
                <img v-if="row.original.course?.thumbnail" :src="row.original.course.thumbnail" :alt="row.original.course.title" class="w-full h-full object-cover">
                <span v-else class="text-xs">📘</span>
              </div>
              <div class="flex flex-col min-w-0">
                <span class="text-sm font-semibold text-[var(--text)] truncate max-w-[200px]" :title="row.original.course?.title">{{ row.original.course?.title || '--' }}</span>
                <span class="text-xs text-[var(--muted)]">#{{ row.original.id }}</span>
              </div>
            </div>
          </template>

          <template #amount-cell="{ row }">
            <span class="text-sm font-semibold text-[var(--text)]">{{ formatMoney(row.original.amount) }}</span>
          </template>

          <template #status-cell="{ row }">
            <div class="flex flex-col items-start gap-1">
              <span
                class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                :class="['completed','paid'].includes(row.original.status) 
                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                  : 'bg-amber-50 text-amber-700 border border-amber-200'"
              >
                {{ ['completed','paid'].includes(row.original.status) ? 'Thành công' : 'Đang xử lý' }}
              </span>
              <span class="text-xs text-[var(--muted)]">{{ row.original.payment_method || '--' }}</span>
            </div>
          </template>

          <template #created_at-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ formatDate(row.original.paid_at || row.original.created_at) }}</span>
          </template>

          <template #actions-cell="{ row }">
            <div class="relative flex justify-end" style="text-align: right">
              <button class="w-7 h-7 rounded-lg hover:bg-[var(--surface)] flex items-center justify-center text-[var(--muted)] hover:text-[var(--text)] transition-colors" type="button" @click.stop="toggleDropdown(row.original.id)">
                <i class="pi pi-ellipsis-v" />
              </button>
              <div v-if="activeDropdown === row.original.id" class="absolute right-0 top-full mt-1 w-40 bg-white border border-[var(--line)] rounded-xl shadow-lg z-10 py-1 flex flex-col text-left">
                <button class="px-4 py-2 text-xs font-semibold text-[var(--text)] hover:bg-[var(--surface)] transition-colors w-full text-left" type="button" @click="openDetail(row.original)">
                  Xem chi tiết
                </button>
              </div>
            </div>
          </template>
        </UTable>
      </div>

      <DataTableFooter
        :current="currentPage"
        :last="lastPage"
        :total="totalOrders"
        :per-page="perPage"
        @page="fetchOrders"
        @update:per-page="perPage = $event; fetchOrders(1)"
      />
    </section>

    <!-- Detail Modal -->
    <UModal v-model:open="detailOpen" :ui="{ width: 'max-w-2xl' }">
      <template #content>
        <div class="w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
          <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-[var(--line)]">
            <div>
              <p class="text-[0.68rem] font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Chi tiết đơn hàng</p>
              <h3 class="text-lg font-bold tracking-tight text-[var(--text)]">Hóa đơn #{{ selectedOrder?.id }}</h3>
            </div>
            <button class="w-8 h-8 rounded-xl flex items-center justify-center border border-[var(--line)] text-sm font-bold text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--text)] transition-colors" type="button" @click="detailOpen = false">✕</button>
          </div>
          
          <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">
            <div class="flex flex-col gap-6">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full flex items-center justify-center text-xl font-bold bg-[rgba(29,158,117,0.1)] text-[#085041] border border-[rgba(29,158,117,0.2)]">
                  {{ selectedOrder?.user?.name ? selectedOrder.user.name.charAt(0).toUpperCase() : 'U' }}
                </div>
                <div class="flex flex-col">
                  <h4 class="text-base font-bold text-[var(--text)]">{{ selectedOrder?.user?.name || 'Học viên' }}</h4>
                  <span class="text-sm text-[var(--muted)]">{{ selectedOrder?.user?.email || '—' }}</span>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4 border-t border-[var(--line)] pt-5">
                <div class="flex flex-col gap-1">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Khóa học mua</span>
                  <p class="text-sm font-semibold text-[var(--text)]">{{ selectedOrder?.course?.title || '—' }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Số tiền thanh toán</span>
                  <p class="text-sm font-bold text-[#085041]">{{ selectedOrder ? formatMoney(selectedOrder.amount) : '—' }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Phương thức thanh toán</span>
                  <p class="text-sm font-semibold text-[var(--text)]">{{ selectedOrder?.payment_method || '—' }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Mã tham chiếu giao dịch</span>
                  <p class="text-sm font-mono text-[var(--text)] break-all">{{ selectedOrder?.payment_ref || '—' }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Thời gian giao dịch</span>
                  <p class="text-sm font-semibold text-[var(--text)]">{{ selectedOrder?.paid_at ? formatDate(selectedOrder.paid_at) : formatDate(selectedOrder?.created_at) }}</p>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái</span>
                  <p class="mt-1">
                    <span 
                      class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                      :class="selectedOrder?.status === 'completed' || selectedOrder?.status === 'paid' 
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                        : 'bg-amber-50 text-amber-700 border border-amber-200'"
                    >
                      {{ selectedOrder?.status === 'completed' || selectedOrder?.status === 'paid' ? 'Hoạt động / Thành công' : 'Đang xử lý' }}
                    </span>
                  </p>
                </div>
              </div>
            </div>
          </div>
          
          <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-[var(--line)] bg-[var(--surface)]">
            <button class="inline-flex items-center gap-2 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors" type="button" @click="detailOpen = false">
              Đóng
            </button>
          </div>
        </div>
      </template>
    </UModal>
  </div>
</template>

<style scoped>
/* Scoped styles only for dropdown item hover and dark mode overrides as required by styling constraints */
.dropdown-menu {
  background: white;
}
[data-theme="dark"] .dropdown-menu {
  background: var(--surface-strong);
}
</style>
