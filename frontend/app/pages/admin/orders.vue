<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, MoreVertical } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'

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
const errorMessage = ref('')
const selectedIds = ref<number[]>([])
const activeDropdown = ref<number | null>(null)

const { exportToCSV } = useExport()

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
    errorMessage.value = error?.data?.message || 'Không thể tải đơn hàng.'
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
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Tổng đơn</p>
        <div class="mini-head">
          <strong>{{ totalOrders }}</strong>
          <span>Theo bộ lọc hiện tại</span>
        </div>
      </article>

      <article class="dashboard-card mini-card tone-amber">
        <p class="mini-title">Đã thanh toán</p>
        <div class="mini-head">
          <strong>{{ paidCount }}</strong>
          <span>Đơn thành công trên trang hiện tại</span>
        </div>
      </article>

      <article class="dashboard-card mini-card">
        <p class="mini-title">Doanh thu</p>
        <div class="mini-head">
          <strong>{{ formatMoney(totalRevenue) }}</strong>
          <span>Tạm tính trên dữ liệu đang hiển thị</span>
        </div>
      </article>
    </section>

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

      <div v-if="errorMessage" class="crud-alert is-error">
        {{ errorMessage }}
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th style="width: 40px">
                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll">
              </th>
              <th style="width: 60px">STT</th>
              <th>Người mua</th>
              <th>Khóa học</th>
              <th>Số tiền</th>
              <th>Thanh toán</th>
              <th>Thời gian</th>
              <th style="text-align: right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="crud-empty">Đang tải đơn hàng...</td>
            </tr>
            <tr v-else-if="orders.length === 0">
              <td colspan="6" class="crud-empty">Không có đơn hàng phù hợp.</td>
            </tr>
            <tr v-for="(order, idx) in orders" :key="order.id">
              <td>
                <input type="checkbox" v-model="selectedIds" :value="order.id">
              </td>
              <td>{{ (currentPage - 1) * perPage + idx + 1 }}</td>
              <td>
                <div class="crud-profile">
                  <div v-if="order.user?.avatar" class="crud-avatar">
                    <img :src="order.user.avatar" :alt="order.user.name">
                  </div>
                  <div v-else class="crud-avatar crud-avatar-fallback">
                    {{ order.user?.name?.slice(0,2).toUpperCase() || 'KH' }}
                  </div>
                  <div>
                    <strong>{{ order.user?.name || '--' }}</strong>
                    <p>{{ order.user?.email || '--' }}</p>
                  </div>
                </div>
              </td>
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="order.course?.thumbnail" :src="order.course.thumbnail" :alt="order.course.title">
                    <span v-else>📘</span>
                  </div>
                  <div>
                    <strong>{{ order.course?.title || '--' }}</strong>
                    <p>#{{ order.id }}</p>
                  </div>
                </div>
              </td>
              <td>{{ formatMoney(order.amount) }}</td>
              <td>
                <span
                  class="crud-badge"
                  :class="['completed','paid'].includes(order.status) ? 'role-instructor' : 'role-admin'"
                >
                  {{ order.status }}
                </span>
                <p>{{ order.payment_method || '--' }}</p>
              </td>
              <td>{{ formatDate(order.paid_at || order.created_at) }}</td>
              <td>
                <div class="crud-actions-dropdown" style="text-align: right">
                  <button class="action-toggle-btn" type="button" @click.stop="toggleDropdown(order.id)">
                    <MoreVertical :size="20" :stroke-width="1.75" />
                  </button>
                  <div v-if="activeDropdown === order.id" class="dropdown-menu">
                    <button class="dropdown-item" type="button" @click="openDetail(order)">
                      Xem chi tiết
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
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

    <Teleport to="body">
      <div v-if="detailOpen" class="crud-modal-backdrop" @click.self="detailOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Chi tiết đơn hàng</p>
              <h3>Đơn hàng #{{ selectedOrder?.id }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button>
          </div>
          <div class="crud-form-grid">
            <div class="crud-field">
              <span>Học viên</span>
              <strong>{{ selectedOrder?.user?.name || '--' }}</strong>
            </div>
            <div class="crud-field">
              <span>Email</span>
              <strong>{{ selectedOrder?.user?.email || '--' }}</strong>
            </div>
            <div class="crud-field">
              <span>Khóa học</span>
              <strong>{{ selectedOrder?.course?.title || '--' }}</strong>
            </div>
            <div class="crud-field">
              <span>Số tiền</span>
              <strong>{{ formatMoney(selectedOrder?.amount || 0) }}</strong>
            </div>
            <div class="crud-field">
              <span>Phương thức</span>
              <strong>{{ selectedOrder?.payment_method || '--' }}</strong>
            </div>
            <div class="crud-field">
              <span>Mã tham chiếu</span>
              <strong>{{ selectedOrder?.payment_ref || '--' }}</strong>
            </div>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="detailOpen = false">
              Đóng
            </button>
          </div>
        </div>
      </div>
    </Teleport>
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
</style>

