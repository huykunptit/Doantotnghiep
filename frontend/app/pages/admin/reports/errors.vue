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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div>
      <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Hỗ trợ</p>
      <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Lịch sử lỗi & Giao dịch thất bại</h1>
      <p class="text-sm mt-0.5" style="color:var(--muted)">Danh sách các giao dịch thanh toán thất bại cần kiểm tra.</p>
    </div>

    <!-- Notice banner -->
    <div class="flex items-start gap-3.5 px-5 py-4 rounded-2xl border-l-4 border-amber-400 bg-amber-50">
      <span class="material-symbols-outlined text-amber-600 shrink-0 mt-0.5" style="font-size:20px">info</span>
      <div>
        <strong class="text-sm font-semibold" style="color:var(--text)">Về tính năng này</strong>
        <p class="text-sm mt-1" style="color:var(--muted);line-height:1.6">
          Hiện tại hiển thị <strong>giao dịch thanh toán thất bại</strong> như một proxy cho lỗi hệ thống.
          Để có nhật ký lỗi đầy đủ, cần tích hợp thêm <strong>Sentry</strong> hoặc <strong>ELK Stack</strong> ở tầng backend.
        </p>
      </div>
    </div>

    <div v-if="loading && allOrders.length === 0" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tải dữ liệu lỗi...</div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(239,68,68,0.06);border-color:rgba(239,68,68,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-red-500">Giao dịch thất bại</p>
          <strong class="text-3xl font-extrabold tracking-tight text-red-500">{{ total }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Cần kiểm tra</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Tổng giá trị mất</p>
          <strong class="text-2xl font-extrabold tracking-tight leading-tight" style="color:var(--text)">{{ formatMoney(allOrders.reduce((s, o) => s + (o.amount || 0), 0)) }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Không thu được</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border border-[var(--line)]" style="background:var(--surface)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--muted)">Trên trang này</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ filteredOrders.length }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Bản ghi hiển thị</span>
        </div>
      </div>

      <!-- Table -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-[var(--line)]">
          <form class="flex-1 min-w-0" @submit.prevent>
            <input
              v-model="search"
              type="text"
              placeholder="Tìm học viên, email hoặc khóa học..."
              class="w-full max-w-xs h-9 px-4 rounded-xl text-sm border border-[var(--line)] bg-transparent focus:outline-none focus:border-[#1d9e75] transition-colors"
              style="color:var(--text)"
            >
          </form>
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
              @click="fetchFailedOrders(currentPage)"
            >
              ↻ Làm mới
            </button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[var(--line)]" style="background:var(--surface)">
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Người dùng</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Khóa học</th>
                <th class="text-right px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Số tiền</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Phương thức TT</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Mã tham chiếu</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Thời gian</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredOrders.length === 0">
                <td colspan="6" class="py-12 text-center text-sm" style="color:var(--muted)">
                  Không có giao dịch thất bại nào. Hệ thống hoạt động bình thường.
                </td>
              </tr>
              <tr
                v-for="order in filteredOrders"
                :key="order.id"
                class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
              >
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[0.65rem] font-bold shrink-0" style="background:rgba(239,68,68,0.1);color:#dc2626">
                      {{ (order.user?.name || 'KH').slice(0, 2).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <p class="font-semibold truncate" style="color:var(--text)">{{ order.user?.name || '—' }}</p>
                      <p class="text-xs truncate" style="color:var(--muted)">{{ order.user?.email || '—' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3">
                  <p class="font-semibold truncate max-w-[22ch]" style="color:var(--text)">{{ order.course?.title || '—' }}</p>
                  <p class="text-xs" style="color:var(--muted)">Đơn #{{ order.id }}</p>
                </td>
                <td class="px-5 py-3 text-right font-bold text-red-500">{{ formatMoney(order.amount) }}</td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold" style="background:rgba(17,17,17,.06);color:var(--muted)">
                    {{ order.payment_method || 'Không rõ' }}
                  </span>
                </td>
                <td class="px-5 py-3">
                  <code class="text-xs px-1.5 py-0.5 rounded-md" style="background:rgba(17,17,17,.05);color:var(--muted)">
                    {{ order.payment_ref || order.id }}
                  </code>
                </td>
                <td class="px-5 py-3 text-xs" style="color:var(--muted)">{{ formatDate(order.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-[var(--line)]">
          <p class="text-sm" style="color:var(--muted)">Trang {{ currentPage }} / {{ lastPage }} ({{ total }} giao dịch lỗi)</p>
          <div class="flex items-center gap-1">
            <button
              type="button"
              :disabled="currentPage <= 1"
              class="h-8 px-3 rounded-lg text-sm font-medium border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="fetchFailedOrders(currentPage - 1)"
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
              @click="fetchFailedOrders(p)"
            >
              {{ p }}
            </button>
            <button
              type="button"
              :disabled="currentPage >= lastPage"
              class="h-8 px-3 rounded-lg text-sm font-medium border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
              style="color:var(--muted)"
              @click="fetchFailedOrders(currentPage + 1)"
            >
              Sau
            </button>
          </div>
        </div>
      </section>

      <!-- System health -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="px-5 pt-4 pb-3 border-b border-[var(--line)]">
          <h3 class="text-base font-semibold" style="color:var(--text)">Chỉ số hệ thống</h3>
          <p class="text-xs mt-0.5" style="color:var(--muted)">Cần tích hợp health check endpoint để hiển thị dữ liệu thực.</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 p-5">
          <div
            v-for="svc in [
              { name: 'API Backend', status: 'online', icon: 'pi-server' },
              { name: 'Database (MySQL)', status: 'online', icon: 'pi-database' },
              { name: 'Cache (Redis)', status: 'online', icon: 'pi-microchip' },
              { name: 'File Storage (MinIO)', status: 'online', icon: 'pi-cloud' },
              { name: 'AI Service', status: 'online', icon: 'pi-sparkles' },
              { name: 'Payment Gateway', status: total > 0 ? 'warning' : 'online', icon: 'pi-credit-card' },
            ]"
            :key="svc.name"
            class="flex items-center gap-3 p-3.5 rounded-xl border border-[var(--line)]"
            style="background:var(--surface)"
          >
            <i :class="['pi', svc.icon, 'text-lg opacity-60']" />
            <div>
              <p class="text-sm font-semibold" style="color:var(--text)">{{ svc.name }}</p>
              <p class="text-xs font-bold mt-0.5" :style="{ color: svc.status === 'online' ? '#1d9e75' : '#d97706' }">
                {{ svc.status === 'online' ? '● Hoạt động' : '⚠ Cần kiểm tra' }}
              </p>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
