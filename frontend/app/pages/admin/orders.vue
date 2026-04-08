<template>
  <div>
    <NuxtLayout name="admin">
      <!-- Filters -->
      <div class="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center justify-between animate-fade-in-up">
        <div class="flex gap-2 w-full sm:w-auto flex-1 max-w-md">
          <div class="relative flex-1">
            <input v-model="search" type="text" placeholder="Tìm người dùng, khóa học..." class="input pl-10 h-10 text-sm w-full"
              @keyup.enter="fetchOrders(1)" />
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          </div>
          <select v-model="statusFilter" class="input h-10 py-1.5 text-sm w-auto focus:ring-0" @change="fetchOrders(1)">
            <option value="">Tất cả TT</option>
            <option value="pending">Chờ thanh toán</option>
            <option value="paid">Đã thanh toán</option>
            <option value="completed">Hoàn thành</option>
            <option value="failed">Thất bại</option>
          </select>
          <button class="btn-secondary text-sm h-10 px-5 flex-shrink-0" @click="fetchOrders(1)">Lọc</button>
        </div>
        
        <button class="btn-export text-sm h-10 px-4 flex items-center gap-2 text-gray-700 w-full sm:w-auto" @click="exportData">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
          Xuất CSV
        </button>
      </div>

      <!-- Stats row -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8 animate-fade-in-up" style="animation-delay: 0.1s">
        <div v-for="stat in orderStats" :key="stat.label" class="stat-card">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ stat.label }}</p>
          <p class="text-2xl font-bold mt-1 tracking-tight" :class="stat.cls">{{ stat.value }}</p>
        </div>
      </div>

      <!-- Table -->
      <div class="card-glass overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s">
        <div v-if="loading" class="divide-y divide-gray-100">
          <div v-for="i in 8" :key="i" class="flex items-center gap-4 px-5 py-3.5 animate-pulse">
            <div class="flex-1 space-y-1.5">
              <div class="h-3.5 bg-gray-200 rounded w-40"></div>
              <div class="h-3 bg-gray-200 rounded w-56"></div>
            </div>
            <div class="h-5 w-20 bg-gray-200 rounded-full hidden sm:block"></div>
            <div class="h-4 w-24 bg-gray-200 rounded hidden md:block"></div>
            <div class="h-5 w-16 bg-gray-200 rounded-full"></div>
          </div>
        </div>

        <div v-else-if="orders.length === 0" class="p-16 text-center">
          <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-gray-100">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
          </div>
          <h3 class="text-base font-semibold text-gray-900 mb-1">Không có đơn hàng nào</h3>
          <p class="text-sm text-gray-500">Chưa tìm thấy đơn hàng nào khớp với điều kiện lọc.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Khóa học</th>
                <th class="hidden sm:table-cell">Người dùng</th>
                <th class="hidden md:table-cell">Số tiền</th>
                <th class="hidden lg:table-cell">Ngày tạo</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders" :key="order.id" class="group">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2.5">
                    <div class="w-10 h-7 rounded bg-gray-100 flex-shrink-0 overflow-hidden">
                      <img v-if="order.course?.thumbnail" :src="order.course.thumbnail" class="w-full h-full object-cover" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate max-w-[180px]">{{ order.course?.title }}</p>
                      <p class="text-xs text-gray-400 sm:hidden">{{ order.user?.name }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3 hidden sm:table-cell">
                  <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-primary-light flex items-center justify-center flex-shrink-0">
                      <span class="text-[9px] font-bold text-primary">{{ order.user?.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-medium text-gray-800 truncate max-w-[120px]">{{ order.user?.name }}</p>
                      <p class="text-[10px] text-gray-400 truncate max-w-[120px]">{{ order.user?.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3 hidden md:table-cell">
                  <span class="text-sm font-semibold" :class="order.amount > 0 ? 'text-gray-900' : 'text-blue-600'">
                    {{ order.amount > 0 ? formatMoney(order.amount) : 'Miễn phí' }}
                  </span>
                </td>
                <td class="px-5 py-3 text-xs text-gray-400 hidden lg:table-cell">{{ formatDate(order.created_at) }}</td>
                <td class="px-5 py-3">
                  <span class="badge text-xs" :class="orderStatusBadge(order.status)">{{ orderStatusLabel(order.status) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1 || totalItems > perPage" class="pagination-wrapper mt-6">
        <div class="flex items-center gap-3">
          <p class="text-xs text-gray-500">Hiển thị <span class="font-medium text-gray-900">{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }}</span> / {{ totalItems }}</p>
          <select v-model="perPage" class="input h-8 py-1.5 text-xs w-auto focus:ring-0" @change="fetchOrders(1)">
            <option :value="10">10 / trang</option>
            <option :value="20">20 / trang</option>
            <option :value="50">50 / trang</option>
          </select>
        </div>
        <div class="flex gap-1">
          <button @click="fetchOrders(currentPage - 1)" :disabled="currentPage <= 1"
            class="pagination-btn" :class="currentPage <= 1 ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">‹</button>
          <button v-for="p in paginationRange" :key="p" @click="typeof p === 'number' && fetchOrders(p)"
            class="pagination-btn" :class="p === currentPage ? 'pagination-btn-active' : p === '…' ? 'cursor-default text-gray-400' : 'pagination-btn-inactive'">{{ p }}</button>
          <button @click="fetchOrders(currentPage + 1)" :disabled="currentPage >= totalPages"
            class="pagination-btn" :class="currentPage >= totalPages ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">›</button>
        </div>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const { exportToCSV } = useExport()
const orders = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(20)
const totalRevenue = ref(0)
const paidCount = ref(0)
const pendingCount = ref(0)

const orderStats = computed(() => [
  { label: 'Tổng đơn hàng', value: totalItems.value, cls: 'text-gray-900' },
  { label: 'Chờ thanh toán', value: pendingCount.value, cls: 'text-amber-600' },
  { label: 'Đã thanh toán', value: paidCount.value, cls: 'text-primary' },
  { label: 'Doanh thu', value: formatMoney(totalRevenue.value), cls: 'text-emerald-600 text-base' },
])

function orderStatusBadge(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-primary-light text-primary-dark',
    completed: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
  }
  return m[s] || 'bg-gray-100 text-gray-600'
}

function orderStatusLabel(s: string): string {
  const m: Record<string, string> = { pending: 'Chờ TT', paid: 'Đã TT', completed: 'Hoàn thành', failed: 'Thất bại' }
  return m[s] || s
}

function formatMoney(v: number): string {
  if (!v) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v)
}

function formatDate(d: string): string {
  if (!d) return ''
  return new Date(d).toLocaleDateString('vi-VN')
}

const paginationRange = computed(() => {
  const total = totalPages.value, cur = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages: (number | string)[] = [1]
  if (cur > 3) pages.push('…')
  for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) pages.push(p)
  if (cur < total - 2) pages.push('…')
  pages.push(total)
  return pages
})

async function fetchOrders(page = 1) {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (statusFilter.value) q.set('status', statusFilter.value)
    const data = await useApi<any>(`/admin/orders?${q}`, { token: auth.token })
    orders.value = data.data || []
    currentPage.value = data.current_page || 1
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0
  } catch {
    orders.value = []
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const stats = await useApi<any>('/admin/stats', { token: auth.token })
    totalRevenue.value = stats.total_revenue || 0
    const [paid, pending] = await Promise.all([
      useApi<any>('/admin/orders?status=paid&per_page=1', { token: auth.token }),
      useApi<any>('/admin/orders?status=pending&per_page=1', { token: auth.token }),
    ])
    paidCount.value = paid.total || 0
    pendingCount.value = pending.total || 0
  } catch {}
}

async function exportData() {
  try {
    const q = new URLSearchParams({ page: '1', per_page: '1000' })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (statusFilter.value) q.set('status', statusFilter.value)
    const data = await useApi<any>(`/admin/orders?${q}`, { token: auth.token })
    const rows = data.data || [] // The API response has data.data based on fetchOrders logic
    
    exportToCSV(rows, [
      { key: 'id', label: 'Mã đơn' },
      { key: 'course.title', label: 'Khóa học' },
      { key: 'user.name', label: 'Khách hàng' },
      { key: 'user.email', label: 'Email' },
      { key: 'amount', label: 'Số tiền(VND)', format: (v) => String(v || 0) },
      { key: 'status', label: 'Trạng thái', format: (v) => orderStatusLabel(v) },
      { key: 'created_at', label: 'Ngày tạo', format: (v) => formatDate(v) }
    ], 'danh_sach_don_hang')
  } catch (e) {
    alert('Không thể xuất dữ liệu')
  }
}

onMounted(() => { fetchOrders(); fetchStats() })
</script>

