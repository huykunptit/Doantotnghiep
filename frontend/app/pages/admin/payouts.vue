<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
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
  <AdminWorkspaceShell
    title="Chi trả giảng viên (Payouts)"
    description="Tổng hợp doanh thu từ đơn hàng đã thanh toán. Payout mặc định 70% doanh thu. Đánh dấu để theo dõi trạng thái chi trả."
    :breadcrumb="['Trang chủ', 'Tài chính', 'Chi trả giảng viên']"
  >
    <div v-if="loading" class="dashboard-card crud-empty">Đang tính toán dữ liệu payout...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- KPI Cards -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article class="dashboard-card mini-card tone-green">
          <p class="mini-title">Giảng viên có doanh thu</p>
          <div class="mini-head">
            <strong>{{ payoutRows.length }}</strong>
            <span>Người</span>
          </div>
        </article>
        <article class="dashboard-card mini-card tone-blue">
          <p class="mini-title">Tổng doanh thu lọc</p>
          <div class="mini-head">
            <strong>{{ formatMoney(totalRevenue) }}</strong>
            <span>Đã thanh toán</span>
          </div>
        </article>
        <article class="dashboard-card mini-card">
          <p class="mini-title">Tổng payout (70%)</p>
          <div class="mini-head">
            <strong>{{ formatMoney(totalPayout) }}</strong>
            <span>Cần chi trả</span>
          </div>
        </article>
        <article class="dashboard-card mini-card tone-amber">
          <p class="mini-title">Đã đánh dấu chi trả</p>
          <div class="mini-head">
            <strong>{{ paidCount }} / {{ payoutRows.length }}</strong>
            <span>Giảng viên</span>
          </div>
        </article>
      </section>

      <!-- Filter & toolbar -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div class="crud-toolbar-main">
            <input
              v-model="search"
              class="crud-search"
              type="text"
              placeholder="Tìm tên hoặc email giảng viên..."
            >
            <select v-model="periodFilter" class="crud-select">
              <option value="all">Tất cả thời gian</option>
              <option value="this_month">Tháng này</option>
              <option value="last_month">Tháng trước</option>
              <option value="this_year">Năm nay</option>
            </select>
          </div>
          <div class="crud-toolbar-right">
            <button class="crud-secondary-btn" type="button" @click="markAllAsPaid">
              Đánh dấu tất cả đã trả
            </button>
            <button class="crud-export-btn" type="button" @click="exportCSV">
              <span class="material-symbols-outlined">download</span>
              Xuất Excel
            </button>
          </div>
        </div>

        <div v-if="payoutRows.length === 0" class="crud-empty">
          Không có dữ liệu payout cho kỳ được chọn.
        </div>

        <div v-else class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Giảng viên</th>
                <th>Khoá học</th>
                <th>Số đơn hàng</th>
                <th>Doanh thu</th>
                <th>Thanh toán (70%)</th>
                <th>Trạng thái chi trả</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in pagedPayoutRows"
                :key="row.instructorId"
                :class="{ 'row-paid': markedPaid.has(row.instructorId) }"
              >
                <td>
                  <div class="crud-profile">
                    <div class="crud-avatar crud-avatar-fallback">
                      {{ row.instructorName.slice(0, 2).toUpperCase() }}
                    </div>
                    <div>
                      <strong>{{ row.instructorName }}</strong>
                      <p>{{ row.email || 'Không có email' }}</p>
                    </div>
                  </div>
                </td>
                <td>
                  <div style="max-width: 22ch;">
                    <p
                      v-for="(title, i) in row.courseTitles.slice(0, 2)"
                      :key="i"
                      style="font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0;"
                    >
                      {{ title }}
                    </p>
                    <p
                      v-if="row.courseTitles.length > 2"
                      style="font-size: 0.75rem; color: var(--muted); margin: 0;"
                    >
                      +{{ row.courseTitles.length - 2 }} khoá khác
                    </p>
                  </div>
                </td>
                <td>
                  <strong>{{ row.orderCount }}</strong>
                </td>
                <td>
                  <strong>{{ formatMoney(row.revenue) }}</strong>
                </td>
                <td>
                  <strong style="color: var(--green-deep);">{{ formatMoney(row.payout) }}</strong>
                </td>
                <td>
                  <span
                    class="crud-badge"
                    :class="markedPaid.has(row.instructorId) ? 'role-instructor' : 'role-student'"
                  >
                    {{ markedPaid.has(row.instructorId) ? 'Đã chi trả' : 'Chưa chi trả' }}
                  </span>
                </td>
                <td>
                  <button
                    type="button"
                    class="action-btn"
                    :class="markedPaid.has(row.instructorId) ? 'is-view' : 'is-edit'"
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
        <div style="margin-top: 16px; padding: 12px 16px; background: rgba(var(--green-rgb), 0.04); border-radius: 12px; font-size: 0.8rem; color: var(--muted); line-height: 1.6;">
          <strong style="color: var(--text);">Lưu ý:</strong>
          Trạng thái "Đã chi trả" chỉ được lưu trên trình duyệt trong phiên hiện tại. Để lưu trạng thái vĩnh viễn cần tích hợp API payout vào backend.
          Tỉ lệ payout mặc định: <strong>70%</strong> doanh thu (có thể điều chỉnh trong Cài đặt hệ thống).
        </div>
      </section>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.row-paid { opacity: 0.6; }
.row-paid td { text-decoration: line-through; text-decoration-color: rgba(17,17,17,0.2); }
.row-paid td strong, .row-paid td .crud-profile strong { text-decoration: none; }
.row-paid td:last-child { text-decoration: none; }
</style>
