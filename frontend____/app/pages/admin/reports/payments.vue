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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Tài chính</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Báo cáo thanh toán</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Phân tích doanh thu, dòng tiền và hiệu suất kinh doanh từ dữ liệu giao dịch thực tế.</p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          class="inline-flex items-center gap-2 h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] bg-transparent hover:bg-[var(--surface)] transition-colors"
          style="color:var(--muted)"
          @click="doExportCSV"
        >
          <i class="pi pi-download" />
          Xuất Excel
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 h-9 px-5 rounded-xl text-sm font-semibold text-white transition-colors"
          style="background:#1d9e75"
          @click="doExportPDF"
        >
          <i class="pi pi-file" />
          Xuất PDF
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
      <div class="flex flex-wrap gap-3 items-center px-5 py-4">
        <div class="flex items-center gap-2">
          <span class="text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Từ</span>
          <UiDatePicker v-model="dateFrom" placeholder="Từ ngày" size="md" />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Đến</span>
          <UiDatePicker v-model="dateTo" placeholder="Đến ngày" size="md" />
        </div>
        <button
          type="button"
          class="h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
          style="color:var(--muted)"
          @click="dateFrom = ''; dateTo = ''; currentPage = 1"
        >
          Đặt lại
        </button>
      </div>
    </section>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">
      Đang tải dữ liệu...
    </div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(29,158,117,0.06);border-color:rgba(29,158,117,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:#1d9e75">Tổng doanh thu</p>
          <strong class="text-2xl font-extrabold tracking-tight leading-tight" style="color:var(--text)">{{ formatMoney(kpis.totalRevenue) }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">{{ filteredOrders.length }} giao dịch</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(59,130,246,0.06);border-color:rgba(59,130,246,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Đơn thành công</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ kpis.ordersCount }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Đã hoàn tất thanh toán</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Giá trị trung bình</p>
          <strong class="text-2xl font-extrabold tracking-tight leading-tight" style="color:var(--text)">{{ formatMoney(kpis.averageOrder) }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Mỗi đơn hàng</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(239,68,68,0.06);border-color:rgba(239,68,68,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-red-500">Đơn thất bại</p>
          <strong class="text-3xl font-extrabold tracking-tight text-red-500">{{ kpis.failedCount }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Cần kiểm tra</span>
        </div>
      </div>

      <!-- Charts row -->
      <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-5">
        <!-- Revenue area chart -->
        <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-6 pt-5 pb-4 border-b border-[var(--line)]">
            <p class="text-[0.72rem] font-bold uppercase tracking-wide mb-1" style="color:var(--muted)">Biểu đồ doanh thu</p>
            <h3 class="text-base font-semibold" style="color:var(--text)">Doanh thu theo tháng</h3>
            <p class="text-xs mt-0.5" style="color:var(--muted)">Tổng hợp từ đơn hàng đã thanh toán trong 6 tháng gần nhất.</p>
          </div>
          <div class="flex-1 px-6 py-5">
            <div v-if="monthlyChart.length === 0" class="h-40 flex items-center justify-center text-sm" style="color:var(--muted)">
              Chưa có dữ liệu doanh thu theo khoảng thời gian đã chọn.
            </div>
            <div v-else class="revenue-chart">
              <div v-for="item in monthlyChart" :key="item.label" class="chart-bar-wrap">
                <div class="chart-bar" :style="{ height: `${(item.value / maxBar) * 100}%` }" :title="formatMoney(item.value)">
                  <span class="bar-value">{{ formatMoney(item.value) }}</span>
                </div>
                <span class="bar-label">{{ item.label }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Side panels -->
        <div class="flex flex-col gap-5">
          <!-- Top courses -->
          <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 pt-4 pb-3 border-b border-[var(--line)]">
              <h3 class="text-base font-semibold" style="color:var(--text)">Khóa học doanh thu cao</h3>
            </div>
            <div class="px-5 py-4">
              <div v-if="topCourses.length === 0" class="text-sm py-4 text-center" style="color:var(--muted)">Chưa có dữ liệu.</div>
              <div v-else class="flex flex-col gap-4">
                <div v-for="course in topCourses" :key="course.title" class="flex flex-col gap-1.5">
                  <div class="flex justify-between items-center text-sm">
                    <strong class="font-semibold truncate max-w-[18ch]" style="color:var(--text)">{{ course.title }}</strong>
                    <span class="text-xs font-bold ml-2 shrink-0" style="color:#1d9e75">{{ formatMoney(course.revenue) }}</span>
                  </div>
                  <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(17,17,17,.06)">
                    <div class="h-full rounded-full transition-all duration-700" style="background:#1d9e75" :style="{ width: `${course.share}%` }" />
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Payment methods -->
          <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 pt-4 pb-3 border-b border-[var(--line)]">
              <h3 class="text-base font-semibold" style="color:var(--text)">Phương thức thanh toán</h3>
            </div>
            <div class="px-5 py-4">
              <div v-if="paymentMethods.length === 0" class="text-sm py-4 text-center" style="color:var(--muted)">Chưa có dữ liệu.</div>
              <div v-else class="flex flex-col gap-3">
                <div v-for="pm in paymentMethods" :key="pm.method" class="flex justify-between items-center">
                  <span class="text-sm font-semibold capitalize" style="color:var(--text)">{{ pm.method }}</span>
                  <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold" style="background:rgba(29,158,117,0.1);color:#085041">
                    {{ pm.count }} đơn
                  </span>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- Transaction table -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-[var(--line)]">
          <div>
            <p class="text-[0.72rem] font-bold uppercase tracking-wide mb-0.5" style="color:var(--muted)">Chi tiết giao dịch</p>
            <h3 class="text-base font-semibold" style="color:var(--text)">Danh sách thanh toán</h3>
          </div>
          <span class="text-sm" style="color:var(--muted)">{{ filteredOrders.length }} giao dịch</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[var(--line)]" style="background:var(--surface)">
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Học viên</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Khóa học</th>
                <th class="text-right px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Số tiền</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Trạng thái</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Phương thức</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Ngày TT</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="pagedOrders.length === 0">
                <td colspan="6" class="text-center py-12 text-sm" style="color:var(--muted)">Không có giao dịch nào.</td>
              </tr>
              <tr
                v-for="order in pagedOrders"
                :key="order.id"
                class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
              >
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2.5">
                    <img v-if="order.user?.avatar" :src="order.user.avatar" :alt="order.user?.name" class="w-7 h-7 rounded-full object-cover shrink-0">
                    <div v-else class="w-7 h-7 rounded-full flex items-center justify-center text-[0.65rem] font-bold shrink-0" style="background:rgba(29,158,117,0.1);color:#085041">
                      {{ (order.user?.name || 'KH').slice(0, 2).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <p class="font-semibold truncate" style="color:var(--text)">{{ order.user?.name || '—' }}</p>
                      <p class="text-xs truncate" style="color:var(--muted)">{{ order.user?.email || '' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3 max-w-[200px]">
                  <span class="truncate block text-sm font-medium" style="color:var(--text)">{{ order.course?.title || '—' }}</span>
                </td>
                <td class="px-5 py-3 text-right font-bold text-sm" style="color:#085041">
                  {{ formatMoney(order.amount || 0) }}
                </td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold" :class="statusClasses(order.status)">
                    {{ statusLabel(order.status) }}
                  </span>
                </td>
                <td class="px-5 py-3 text-sm capitalize" style="color:var(--muted)">{{ order.payment_method || '—' }}</td>
                <td class="px-5 py-3 text-sm" style="color:var(--muted)">
                  {{ order.paid_at ? new Date(order.paid_at).toLocaleDateString('vi-VN') : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-[var(--line)]">
          <p class="text-sm" style="color:var(--muted)">
            Trang {{ currentPage }} / {{ totalPages }} ({{ filteredOrders.length }} giao dịch)
          </p>
          <div class="flex items-center gap-1">
            <button
              type="button"
              :disabled="currentPage <= 1"
              class="h-8 px-3 rounded-lg text-sm font-medium border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="currentPage--"
            >
              Trước
            </button>
            <button
              v-for="p in visiblePages"
              :key="p"
              type="button"
              class="h-8 w-8 rounded-lg text-sm font-medium border transition-colors"
              :class="p === currentPage
                ? 'text-white border-transparent'
                : 'border-[var(--line)] hover:bg-[var(--surface)]'"
              :style="p === currentPage ? 'background:#1d9e75' : 'color:var(--text)'"
              @click="currentPage = p"
            >
              {{ p }}
            </button>
            <button
              type="button"
              :disabled="currentPage >= totalPages"
              class="h-8 px-3 rounded-lg text-sm font-medium border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="currentPage++"
            >
              Sau
            </button>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.revenue-chart {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 200px;
  padding: 8px 4px 0;
  border-bottom: 2px solid var(--line);
}
.chart-bar-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.chart-bar {
  width: 40px;
  background: #1d9e75;
  border-radius: 6px 6px 0 0;
  position: relative;
  transition: all 0.3s ease;
  cursor: pointer;
  min-height: 4px;
}
.chart-bar:hover { background: #17876a; }
.bar-value {
  position: absolute;
  top: -26px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
  background: white;
  padding: 2px 5px;
  border-radius: 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,.12);
  color: var(--text);
}
.chart-bar:hover .bar-value { opacity: 1; }
.bar-label { font-size: 0.78rem; font-weight: 600; color: var(--muted); }

[data-theme="dark"] .bar-value { background: var(--surface-strong); box-shadow: 0 1px 4px rgba(0,0,0,.5); }
</style>
