<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const rawOrders = ref<any[]>([])
const rawCourses = ref<any[]>([])
const search = ref('')
const periodFilter = ref<'all' | 'this_month' | 'last_month' | 'this_year'>('all')
const markedPaid = ref<Set<number>>(new Set())
const payoutPage = ref(1)
const payoutPerPage = ref(10)

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [coursesRes, ordersRes] = await Promise.all([
      useApi<any>('/admin/courses?per_page=200', { headers: authHeaders() }),
      useApi<any>('/admin/orders?per_page=500', { headers: authHeaders() }),
    ])
    rawCourses.value = coursesRes.data || []
    rawOrders.value = ordersRes.data || []
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu.'
  }
  finally {
    loading.value = false }
}

const filteredOrders = computed(() => {
  let orders = rawOrders.value.filter(o => ['paid', 'completed'].includes(o.status))
  const now = new Date()
  if (periodFilter.value === 'this_month') {
    orders = orders.filter((o) => {
      const d = new Date(o.created_at)
      return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth()
    })
  }
  else if (periodFilter.value === 'last_month') {
    const lm = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    orders = orders.filter((o) => {
      const d = new Date(o.created_at)
      return d.getFullYear() === lm.getFullYear() && d.getMonth() === lm.getMonth()
    })
  }
  else if (periodFilter.value === 'this_year') {
    orders = orders.filter(o => new Date(o.created_at).getFullYear() === now.getFullYear())
  }
  return orders
})

const courseMap = computed(() =>
  new Map(rawCourses.value.map(c => [c.id, c]))
)

interface PayoutRow {
  instructorId: number
  instructorName: string
  email: string
  revenue: number
  payout: number
  orderCount: number
  courseTitles: string[]
}

const payoutRows = computed<PayoutRow[]>(() => {
  const agg = new Map<number, PayoutRow>()
  for (const order of filteredOrders.value) {
    const course = courseMap.value.get(order.course_id)
    const instructorId = Number(course?.instructor?.id || course?.user_id || 0)
    if (!instructorId) continue
    const existing = agg.get(instructorId) || {
      instructorId,
      instructorName: course?.instructor?.name || 'Giảng viên',
      email: course?.instructor?.email || '',
      revenue: 0,
      payout: 0,
      orderCount: 0,
      courseTitles: [],
    }
    existing.revenue += Number(order.amount || 0)
    existing.payout = Math.round(existing.revenue * 0.7)
    existing.orderCount++
    if (course?.title && !existing.courseTitles.includes(course.title)) {
      existing.courseTitles.push(course.title)
    }
    agg.set(instructorId, existing)
  }
  let rows = Array.from(agg.values()).sort((a, b) => b.revenue - a.revenue)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    rows = rows.filter(r =>
      r.instructorName.toLowerCase().includes(q) || r.email.toLowerCase().includes(q)
    )
  }
  return rows
})

const payoutLastPage = computed(() => Math.max(1, Math.ceil(payoutRows.value.length / payoutPerPage.value)))
const pagedPayoutRows = computed(() => {
  const start = (payoutPage.value - 1) * payoutPerPage.value
  return payoutRows.value.slice(start, start + payoutPerPage.value)
})

const totalRevenue = computed(() => payoutRows.value.reduce((s, r) => s + r.revenue, 0))
const totalPayout = computed(() => payoutRows.value.reduce((s, r) => s + r.payout, 0))
const paidCount = computed(() => payoutRows.value.filter(r => markedPaid.value.has(r.instructorId)).length)

function togglePaid(id: number) {
  const s = new Set(markedPaid.value)
  s.has(id) ? s.delete(id) : s.add(id)
  markedPaid.value = s
}

function markAllAsPaid() {
  markedPaid.value = new Set(payoutRows.value.map(r => r.instructorId))
}

function formatMoney(v: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0)
}

function exportCSV() {
  const rows = payoutRows.value.map(r => [
    r.instructorId,
    r.instructorName,
    r.email,
    r.orderCount,
    r.revenue,
    r.payout,
    markedPaid.value.has(r.instructorId) ? 'Đã chi trả' : 'Chưa chi trả',
    r.courseTitles.join('; '),
  ])
  const header = ['ID', 'Giảng viên', 'Email', 'Đơn hàng', 'Doanh thu', 'Payout (70%)', 'Trạng thái', 'Khoá học']
  const csv = [header, ...rows].map(r => r.join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `payout_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(fetchData)
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Tài chính</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Chi trả giảng viên (Payouts)</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Tổng hợp doanh thu từ đơn hàng đã thanh toán. Payout mặc định 70% doanh thu. Đánh dấu để theo dõi trạng thái chi trả.</p>
      </div>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tính toán dữ liệu payout...</div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(29,158,117,0.08)] border-[rgba(29,158,117,0.2)]">
          <p class="text-xs font-bold uppercase tracking-wider text-[#1d9e75]">Giảng viên có doanh thu</p>
          <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ payoutRows.length }}</strong>
          <span class="text-xs text-[var(--muted)] font-medium">Người</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(59,130,246,0.08)] border-[rgba(59,130,246,0.2)]">
          <p class="text-xs font-bold uppercase tracking-wider text-blue-600">Tổng doanh thu lọc</p>
          <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ formatMoney(totalRevenue) }}</strong>
          <span class="text-xs text-[var(--muted)] font-medium">Đã thanh toán</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(139,92,246,0.08)] border-[rgba(139,92,246,0.2)]">
          <p class="text-xs font-bold uppercase tracking-wider text-violet-600">Tổng payout (70%)</p>
          <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ formatMoney(totalPayout) }}</strong>
          <span class="text-xs text-[var(--muted)] font-medium">Cần chi trả</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(245,158,11,0.08)] border-[rgba(245,158,11,0.2)]">
          <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Đã đánh dấu chi trả</p>
          <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ paidCount }} / {{ payoutRows.length }}</strong>
          <span class="text-xs text-[var(--muted)] font-medium">Giảng viên</span>
        </div>
      </div>

      <!-- Filter & toolbar -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap gap-3 items-center px-5 py-4 border-b border-[var(--line)]">
          <div class="flex flex-1 min-w-0 gap-2">
            <div class="relative flex-1 min-w-[180px] max-w-xs">
              <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)]" style="font-size:0.8rem" />
              <input
                v-model="search"
                class="w-full h-9 pl-8 pr-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]"
                type="text"
                placeholder="Tìm tên hoặc email giảng viên..."
              >
            </div>
            <select
              v-model="periodFilter"
              class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer"
            >
              <option value="all">Tất cả thời gian</option>
              <option value="this_month">Tháng này</option>
              <option value="last_month">Tháng trước</option>
              <option value="this_year">Năm nay</option>
            </select>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button
              class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors"
              type="button"
              @click="markAllAsPaid"
            >
              Đánh dấu tất cả đã trả
            </button>
            <button
              class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors"
              type="button"
              @click="exportCSV"
            >
              <i class="pi pi-download" style="font-size:0.8rem" /> Xuất Excel
            </button>
          </div>
        </div>

        <div v-if="payoutRows.length === 0" class="text-center py-8 text-sm" style="color:var(--muted)">
          Không có dữ liệu payout cho kỳ được chọn.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b border-[var(--line)] bg-[var(--surface)]">
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Giảng viên</th>
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Khoá học</th>
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Số đơn hàng</th>
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Doanh thu</th>
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Thanh toán (70%)</th>
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái chi trả</th>
                <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in pagedPayoutRows"
                :key="row.instructorId"
                class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
                :class="{ 'opacity-65 line-through decoration-gray-300': markedPaid.has(row.instructorId) }"
              >
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-[rgba(29,158,117,0.1)] text-[#085041] border border-[rgba(29,158,117,0.2)]">
                      {{ row.instructorName.slice(0, 2).toUpperCase() }}
                    </div>
                    <div class="flex flex-col no-underline">
                      <strong class="text-sm font-semibold text-[var(--text)]">{{ row.instructorName }}</strong>
                      <p class="text-xs text-[var(--muted)]">{{ row.email || 'Không có email' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-col max-w-[200px]">
                    <p
                      v-for="(title, i) in row.courseTitles.slice(0, 2)"
                      :key="i"
                      class="text-xs text-[var(--text)] truncate"
                      :title="title"
                    >
                      {{ title }}
                    </p>
                    <p
                      v-if="row.courseTitles.length > 2"
                      class="text-xs text-[var(--muted)] font-medium mt-0.5"
                    >
                      +{{ row.courseTitles.length - 2 }} khoá khác
                    </p>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="text-sm font-semibold text-[var(--text)]">{{ row.orderCount }}</span>
                </td>
                <td class="px-4 py-3">
                  <span class="text-sm font-semibold text-[var(--text)]">{{ formatMoney(row.revenue) }}</span>
                </td>
                <td class="px-4 py-3">
                  <span class="text-sm font-bold text-[#085041]">{{ formatMoney(row.payout) }}</span>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                    :class="markedPaid.has(row.instructorId) 
                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                      : 'bg-amber-50 text-amber-700 border border-amber-200'"
                  >
                    {{ markedPaid.has(row.instructorId) ? 'Đã chi trả' : 'Chưa chi trả' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center h-7 px-3 rounded-lg border text-xs font-semibold transition-colors no-underline"
                    :class="markedPaid.has(row.instructorId) 
                      ? 'border-[var(--line)] bg-transparent hover:bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--text)]'
                      : 'border-[rgba(29,158,117,0.3)] bg-[rgba(29,158,117,0.07)] hover:bg-[rgba(29,158,117,0.13)] text-[#085041]'"
                    @click="togglePaid(row.instructorId)"
                  >
                    {{ markedPaid.has(row.instructorId) ? 'Hoàn tác' : 'Đánh dấu đã trả' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DataTableFooter
          :current="payoutPage"
          :last="payoutLastPage"
          :total="payoutRows.length"
          :per-page="payoutPerPage"
          @page="payoutPage = $event"
          @update:per-page="payoutPerPage = $event; payoutPage = 1"
        />

        <!-- Note -->
        <div class="m-5 p-4 bg-[rgba(29,158,117,0.04)] border border-[rgba(29,158,117,0.15)] rounded-2xl text-xs text-[var(--muted)] leading-relaxed">
          <strong class="text-[var(--text)]">Lưu ý:</strong>
          Trạng thái "Đã chi trả" chỉ được lưu trên trình duyệt trong phiên hiện tại. Để lưu trạng thái vĩnh viễn cần tích hợp API payout vào backend.
          Tỉ lệ payout mặc định: <strong class="text-[#085041]">70%</strong> doanh thu (có thể điều chỉnh trong Cài đặt hệ thống).
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
