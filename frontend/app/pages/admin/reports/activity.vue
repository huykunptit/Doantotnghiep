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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div>
      <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Hệ thống</p>
      <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Nhật ký hoạt động</h1>
      <p class="text-sm mt-0.5" style="color:var(--muted)">Lịch sử các giao dịch và hoạt động mới nhất trên hệ thống theo thời gian thực.</p>
    </div>
    <div v-if="loading && orders.length === 0" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tải hoạt động...</div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(59,130,246,0.06);border-color:rgba(59,130,246,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Hoạt động hôm nay</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ todayCount }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Giao dịch mới</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(29,158,117,0.06);border-color:rgba(29,158,117,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:#1d9e75">Doanh thu hôm nay</p>
          <strong class="text-2xl font-extrabold tracking-tight leading-tight" style="color:var(--text)">{{ formatMoney(paidToday) }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Đã thanh toán</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-amber-500">7 ngày qua</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ weekCount }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Giao dịch trong tuần</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border border-[var(--line)]" style="background:var(--surface)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--muted)">Tổng tất cả</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ total }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Giao dịch hệ thống</span>
        </div>
      </div>

      <!-- Timeline table -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-[var(--line)]">
          <div>
            <p class="text-[0.72rem] font-bold uppercase tracking-wide mb-0.5" style="color:var(--muted)">Nhật ký giao dịch</p>
            <h3 class="text-base font-semibold" style="color:var(--text)">Hoạt động gần nhất</h3>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
              style="color:var(--muted)"
              @click="exportCSV"
            >
              <i class="pi pi-download" />
              Xuất Excel
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 h-9 px-5 rounded-xl text-sm font-semibold text-white transition-colors"
              style="background:#1d9e75"
              @click="exportPDF"
            >
              <i class="pi pi-file" />
              Xuất PDF
            </button>
            <button
              type="button"
              :disabled="loading"
              class="h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="fetchActivity(currentPage)"
            >
              ↻ Làm mới
            </button>
          </div>
        </div>

        <div class="divide-y divide-[var(--line)]">
          <div v-for="order in orders" :key="order.id" class="flex items-start gap-3.5 px-5 py-4 hover:bg-[var(--surface)] transition-colors">
            <img v-if="order.user?.avatar" :src="order.user.avatar" :alt="order.user?.name" class="w-10 h-10 rounded-full object-cover shrink-0 mt-0.5">
            <div v-else class="w-10 h-10 rounded-full flex items-center justify-center text-[0.7rem] font-bold shrink-0 mt-0.5" style="background:rgba(29,158,117,0.1);color:#085041">
              {{ (order.user?.name || 'KH').slice(0, 2).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm" style="color:var(--text)">
                <strong class="font-semibold">{{ order.user?.name || 'Người dùng không rõ' }}</strong>
                <span class="font-normal" style="color:var(--muted)"> đã đăng ký </span>
                <strong class="font-semibold">{{ order.course?.title || 'Khóa học' }}</strong>
              </p>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs" style="color:var(--muted)">{{ timeAgo(order.created_at) }}</span>
                <span
                  class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                  :style="{ color: statusColor(order.status), background: `${statusColor(order.status)}18` }"
                >
                  {{ statusLabel(order.status) }}
                </span>
                <span v-if="order.amount" class="text-xs font-bold" style="color:#1d9e75">
                  {{ formatMoney(order.amount) }}
                </span>
              </div>
            </div>
          </div>
          <div v-if="orders.length === 0" class="py-12 text-center text-sm" style="color:var(--muted)">Không có hoạt động nào.</div>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-[var(--line)]">
          <p class="text-sm" style="color:var(--muted)">Trang {{ currentPage }} / {{ lastPage }} ({{ total }} hoạt động)</p>
          <div class="flex items-center gap-1">
            <button
              type="button"
              :disabled="currentPage <= 1"
              class="h-8 px-3 rounded-lg text-sm font-medium border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="fetchActivity(currentPage - 1)"
            >
              Trước
            </button>
            <button
              v-for="p in visiblePages"
              :key="p"
              type="button"
              class="h-8 w-8 rounded-lg text-sm font-medium border transition-colors"
              :class="p === currentPage ? 'text-white border-transparent' : 'border-[var(--line)] hover:bg-[var(--surface)]'"
              :style="p === currentPage ? 'background:#1d9e75' : 'color:var(--text)'"
              @click="fetchActivity(p)"
            >
              {{ p }}
            </button>
            <button
              type="button"
              :disabled="currentPage >= lastPage"
              class="h-8 px-3 rounded-lg text-sm font-medium border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="fetchActivity(currentPage + 1)"
            >
              Sau
            </button>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
