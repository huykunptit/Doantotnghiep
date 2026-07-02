<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, MoreVertical, ShoppingBag, CreditCard, Banknote } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
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
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản trị hệ thống', 'Đơn hàng']"
    description="Theo dõi giao dịch thanh toán theo chuẩn bảng quản trị thống nhất, có bộ lọc, thống kê nhanh và modal xem chi tiết."
    title="Quản lý đơn hàng"
  >
    <div class="ds-stats mb-5">
      <div class="ds-stat ds-stat--green">
        <div class="ds-stat-icon"><ShoppingBag :size="16" /></div>
        <p class="ds-stat-label">Tổng đơn</p>
        <strong class="ds-stat-value">{{ totalOrders }}</strong>
        <span class="ds-stat-sub">theo bộ lọc hiện tại</span>
      </div>
      <div class="ds-stat ds-stat--amber">
        <div class="ds-stat-icon"><CreditCard :size="16" /></div>
        <p class="ds-stat-label">Đã thanh toán</p>
        <strong class="ds-stat-value">{{ paidCount }}</strong>
        <span class="ds-stat-sub">đơn thành công</span>
      </div>
      <div class="ds-stat ds-stat--blue">
        <div class="ds-stat-icon"><Banknote :size="16" /></div>
        <p class="ds-stat-label">Doanh thu</p>
        <strong class="ds-stat-value" style="font-size:1.4rem">{{ formatMoney(totalRevenue) }}</strong>
        <span class="ds-stat-sub">tạm tính hiện tại</span>
      </div>
    </div>

    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent="fetchOrders(1)">
          <input
            v-model="search"
            class="crud-search"
            type="text"
            placeholder="Tìm theo học viên, email hoặc khóa học..."
          >
          <select v-model="status" class="crud-select">
            <option v-for="item in statuses" :key="item.value" :value="item.value">
              {{ item.label }}
            </option>
          </select>
          <button class="crud-secondary-btn" type="submit">
            Tìm kiếm
          </button>
        </form>

        <div class="crud-toolbar-right">
          <button class="crud-export-btn" type="button" @click="exportData">
            <Download :size="20" :stroke-width="1.75" />
            Xuất Excel
          </button>
        </div>
      </div>

      <div class="crud-table-wrap">
        <UTable :columns="columns" :data="orders" :loading="loading">
          <!-- Header slot for select checkbox -->
          <template #select-header>
            <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll">
          </template>

          <template #select-cell="{ row }">
            <input type="checkbox" v-model="selectedIds" :value="row.original.id">
          </template>
          
          <template #index-cell="{ row }">
            {{ (currentPage - 1) * perPage + row.index + 1 }}
          </template>
          
          <template #user-cell="{ row }">
            <div class="crud-profile">
              <div v-if="row.original.user?.avatar" class="crud-avatar">
                <img :src="row.original.user.avatar" :alt="row.original.user.name">
              </div>
              <div v-else class="crud-avatar crud-avatar-fallback">
                {{ row.original.user?.name?.slice(0,2).toUpperCase() || 'KH' }}
              </div>
              <div>
                <strong>{{ row.original.user?.name || '--' }}</strong>
                <p>{{ row.original.user?.email || '--' }}</p>
              </div>
            </div>
          </template>

          <template #course-cell="{ row }">
            <div class="crud-course">
              <div class="crud-course-thumb">
                <img v-if="row.original.course?.thumbnail" :src="row.original.course.thumbnail" :alt="row.original.course.title">
                <span v-else>📘</span>
              </div>
              <div>
                <strong>{{ row.original.course?.title || '--' }}</strong>
                <p>#{{ row.original.id }}</p>
              </div>
            </div>
          </template>

          <template #amount-cell="{ row }">
            {{ formatMoney(row.original.amount) }}
          </template>

          <template #status-cell="{ row }">
            <span
              class="crud-badge"
              :class="['completed','paid'].includes(row.original.status) ? 'role-instructor' : 'role-admin'"
            >
              {{ row.original.status }}
            </span>
            <p>{{ row.original.payment_method || '--' }}</p>
          </template>

          <template #created_at-cell="{ row }">
            {{ formatDate(row.original.paid_at || row.original.created_at) }}
          </template>

          <template #actions-cell="{ row }">
            <div class="crud-actions-dropdown" style="text-align: right">
              <button class="action-toggle-btn" type="button" @click.stop="toggleDropdown(row.original.id)">
                <MoreVertical :size="20" :stroke-width="1.75" />
              </button>
              <div v-if="activeDropdown === row.original.id" class="dropdown-menu">
                <button class="dropdown-item" type="button" @click="openDetail(row.original)">
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

    <UModal v-model:open="detailOpen" :ui="{ width: 'max-w-2xl' }">
      <template #content>
        <div class="crud-modal modal-lnd" :style="{ width: '100%' }">
          <div class="crud-modal-head is-neutral">
            <div>
              <p class="section-kicker">Chi tiết đơn hàng</p>
              <h3>Hóa đơn #{{ selectedOrder?.id }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button>
          </div>
          <div class="crud-modal-body">
            <div class="um-view-profile">
              <div class="um-vp-header">
                <div class="ds-avatar ds-avatar--xl" style="display:flex; align-items:center; justify-content:center; background:rgba(29,158,117,0.1); color:var(--green-deep); border-radius:50%; width:56px; height:56px; font-weight:800; font-size:1.4rem;">
                  {{ selectedOrder?.user?.name ? selectedOrder.user.name.charAt(0).toUpperCase() : 'U' }}
                </div>
                <div class="um-vp-title">
                  <h4>{{ selectedOrder?.user?.name || 'Học viên' }}</h4>
                  <span class="um-vp-email">{{ selectedOrder?.user?.email || '—' }}</span>
                </div>
              </div>

              <div class="um-vp-grid" style="margin-top: 24px;">
                <div class="um-vp-field">
                  <label>Khóa học mua</label>
                  <p>{{ selectedOrder?.course?.title || '—' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Số tiền thanh toán</label>
                  <p style="color: var(--green-deep); font-weight: 700;">{{ selectedOrder ? formatMoney(selectedOrder.amount) : '—' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Phương thức thanh toán</label>
                  <p>{{ selectedOrder?.payment_method || '—' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Mã tham chiếu giao dịch</label>
                  <p class="cell-mono">{{ selectedOrder?.payment_ref || '—' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Thời gian giao dịch</label>
                  <p>{{ selectedOrder?.paid_at ? formatDate(selectedOrder.paid_at) : formatDate(selectedOrder?.created_at) }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Trạng thái</label>
                  <p>
                    <span class="status-badge" :class="selectedOrder?.status === 'completed' || selectedOrder?.status === 'paid' ? 'is-success' : 'is-muted'">
                      {{ selectedOrder?.status === 'completed' || selectedOrder?.status === 'paid' ? 'Hoạt động / Thành công' : 'Đang xử lý' }}
                    </span>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="detailOpen = false">
              Đóng
            </button>
          </div>
        </div>
      </template>
    </UModal>
  </AdminWorkspaceShell>
</template>

<style scoped>
/* Dropdown Styles */
.crud-actions-dropdown {
  position: relative;
  display: block;
}

.action-toggle-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  transition: background-color 0.2s;
}

.action-toggle-btn:hover {
  background-color: rgba(17, 17, 17, 0.05);
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: white;
  border: 1px solid rgba(17, 17, 17, 0.1);
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  min-width: 160px;
  z-index: 50;
  padding: 8px 0;
  display: flex;
  flex-direction: column;
  text-align: left;
}

.dropdown-item {
  background: transparent;
  border: none;
  width: 100%;
  text-align: left;
  padding: 8px 16px;
  font-size: 0.9rem;
  cursor: pointer;
  color: #1e293b;
  transition: all 0.2s;
}

.dropdown-item:hover {
  background-color: rgba(var(--green-rgb), 0.08);
  color: var(--green);
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .dropdown-menu { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .dropdown-item { color: var(--text); }

/* Modal layout */
.modal-lnd {
  width: 100% !important;
}

/* Scroll and body padding */
.crud-modal-body {
  padding: 24px 28px;
  max-height: 70vh;
  overflow-y: auto;
}

/* Profile Detail View classes */
.um-view-profile {
  padding: 0 4px;
}
.um-vp-header {
  display: flex;
  align-items: center;
  gap: 20px;
}
.um-vp-title {
  display: flex;
  flex-direction: column;
}
.um-vp-title h4 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text);
}
.um-vp-email {
  color: var(--muted);
  font-size: 0.9rem;
}
.um-vp-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 24px;
  margin-top: 24px;
  border-top: 1px solid var(--line, #dde5e1);
  padding-top: 24px;
}
.um-vp-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.um-vp-field label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--muted);
}
.um-vp-field p {
  margin: 0;
  font-size: 0.95rem;
  color: var(--text);
  font-weight: 500;
}
.status-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}
.status-badge.is-success {
  background: rgba(16, 185, 129, 0.08);
  color: #10b981;
}
.status-badge.is-muted {
  background: rgba(0, 0, 0, 0.05);
  color: #666;
}
</style>
