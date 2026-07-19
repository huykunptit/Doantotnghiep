<script setup lang="ts">
import { computed, onMounted, ref, reactive } from 'vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useAuthUserCookie, useAuthTokenCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

// Unified UI Components
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Quản trị hệ thống</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Quản lý đơn hàng</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Theo dõi giao dịch thanh toán theo chuẩn bảng quản trị thống nhất, có bộ lọc, thống kê nhanh và modal xem chi tiết.</p>
      </div>
    </div>

    <!-- Filters and Toolbar (Moved to the very top, always open) -->
    <UiFilters
      v-model:search="search"
      search-placeholder="Tìm giao dịch, học viên, khóa học..."
      :active-filter-count="activeFilterCount"
      :active-chips="activeChips"
      :show-export="true"
      :always-open="true"
      @submit-search="fetchOrders(1)"
      @reset-filters="resetFilters"
      @remove-chip="handleRemoveChip"
      @export="exportData"
    >
      <template #advanced>
        <label class="flex flex-col gap-1">
          <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái thanh toán</span>
          <select
            v-model="status"
            class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer"
            @change="fetchOrders(1)"
          >
            <option v-for="item in statuses" :key="item.value" :value="item.value">
              {{ item.label }}
            </option>
          </select>
        </label>
      </template>
    </UiFilters>

    <!-- Stats KPI Cards -->
    <UiKpiCards
      :items="[
        { label: 'Tổng đơn hàng', value: totalOrders, subText: 'theo bộ lọc hiện tại', color: 'primary', icon: 'pi-wallet' },
        { label: 'Đã thanh toán', value: paidCount, subText: 'đơn thành công', color: 'success', icon: 'pi-check-circle' },
        { label: 'Doanh thu', value: formatMoney(totalRevenue), subText: 'tạm tính hiện tại', color: 'info', icon: 'pi-chart-line' },
      ]"
    />

    <!-- Table panel -->
    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
      
      <!-- Table Wrapper -->
      <UiTable
        :columns="columns"
        :data="orders"
        :loading="loading"
        :sort-by="sortBy"
        :sort-order="sortOrder"
        @sort="handleSort"
      >
        <!-- Header slot for select checkbox -->
        <template #select-header>
          <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75]">
        </template>
        <template #select-cell="{ row }">
          <input type="checkbox" v-model="selectedIds" :value="row.original.id" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75]">
        </template>
        
        <!-- Index column -->
        <template #index-cell="{ row }">
          <span class="text-xs text-[var(--muted)]">{{ (currentPage - 1) * perPage + row.index + 1 }}</span>
        </template>
        
        <!-- User column -->
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

        <!-- Course column -->
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

        <!-- Amount column -->
        <template #amount-cell="{ row }">
          <span class="text-sm font-semibold text-[var(--text)]">{{ formatMoney(row.original.amount) }}</span>
        </template>

        <!-- Status column -->
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

        <!-- Created At column -->
        <template #created_at-cell="{ row }">
          <span class="text-xs text-[var(--muted)]">{{ formatDate(row.original.paid_at || row.original.created_at) }}</span>
        </template>

        <!-- Actions column -->
        <template #actions-cell="{ row }">
          <div class="relative flex justify-end">
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
        
        <template #empty>
          <div class="flex flex-col items-center justify-center py-16 gap-2 text-[var(--color-text-muted)]">
            <i class="pi pi-inbox text-3xl opacity-40" />
            <p class="text-sm font-medium">Không tìm thấy đơn hàng nào</p>
          </div>
        </template>
      </UiTable>

      <DataTableFooter
        :current="currentPage"
        :last="lastPage"
        :total="totalOrders"
        :per-page="perPage"
        @page="fetchOrders"
        @update:per-page="perPage = $event; fetchOrders(1)"
      />
    </section>

    <!-- Standardized Detail Modal -->
    <UModal 
      v-model:open="detailOpen" 
      :title="`Hóa đơn #${selectedOrder?.id}`"
      subtitle="Chi tiết đơn hàng"
      :ui="{ width: 'max-w-2xl' }"
    >
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
      
      <template #footer>
        <button class="btn-secondary" type="button" @click="detailOpen = false">
          Đóng
        </button>
      </template>
    </UModal>
  </div>
</template>

<style scoped>
/* Clean layouts styled standardly */
</style>
