<template>
  <InstructorWorkspaceShell
    title="Doanh thu khóa học"
    description="Theo dõi doanh thu, đơn hàng đã thanh toán và giá trị trung bình của từng giao dịch."
    :breadcrumb="['Trang chủ', 'Khóa học', 'Doanh thu']"
  >
    <template #actions>
      <button type="button" class="crud-secondary-btn" @click="exportCSV">
        <span class="material-symbols-outlined">download</span>
        Xuất CSV
      </button>
      <NuxtLink to="/instructor/courses" class="crud-secondary-btn">
        <span class="material-symbols-outlined">arrow_back</span>
        Quay lại
      </NuxtLink>
    </template>

    <!-- Date filter -->
    <div class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent="loadData">
          <div class="filter-field">
            <label>Từ ngày</label>
            <input v-model="dateFrom" type="date" class="crud-search" style="max-width:180px;">
          </div>
          <div class="filter-field">
            <label>Đến ngày</label>
            <input v-model="dateTo" type="date" class="crud-search" style="max-width:180px;">
          </div>
          <button type="submit" class="crud-primary-btn">
            <span class="material-symbols-outlined">filter_list</span>
            Lọc
          </button>
          <button type="button" class="crud-secondary-btn" @click="resetFilter">Đặt lại</button>
        </form>
      </div>
    </div>

    <!-- KPI -->
    <div v-if="loading" class="ds-stats mb-0">
      <div v-for="i in 3" :key="i" class="ds-stat" style="background:var(--bg);animation:pulse 1.5s infinite;" />
    </div>
    <template v-else>
      <div class="ds-stats mb-0">
        <div class="ds-stat ds-stat--green">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">payments</span></div>
          <p class="ds-stat-label">Tổng doanh thu</p>
          <strong class="ds-stat-value">{{ money(summary.total_revenue) }}</strong>
          <span class="ds-stat-sub">{{ filteredOrders.length }} giao dịch</span>
        </div>
        <div class="ds-stat ds-stat--blue">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">receipt_long</span></div>
          <p class="ds-stat-label">Đơn đã thanh toán</p>
          <strong class="ds-stat-value">{{ summary.paid_orders || 0 }}</strong>
          <span class="ds-stat-sub">đơn hàng</span>
        </div>
        <div class="ds-stat ds-stat--amber">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">bar_chart</span></div>
          <p class="ds-stat-label">Giá trị trung bình</p>
          <strong class="ds-stat-value">{{ money(summary.average_order_value) }}</strong>
          <span class="ds-stat-sub">mỗi đơn</span>
        </div>
      </div>

      <!-- Monthly bar chart -->
      <div v-if="monthlyChart.length" class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Thống kê</p>
            <h3 class="ds-section-title">Doanh thu theo tháng</h3>
          </div>
        </div>
        <div class="rev-chart">
          <div v-for="bar in monthlyChart" :key="bar.label" class="rev-bar-col">
            <span class="rev-bar-val">{{ moneyShort(bar.value) }}</span>
            <div class="rev-bar-track">
              <div
                class="rev-bar-fill"
                :style="{ height: `${maxBarValue ? (bar.value / maxBarValue) * 100 : 0}%` }"
                :title="money(bar.value)"
              />
            </div>
            <span class="rev-bar-label">{{ bar.label }}</span>
          </div>
        </div>
      </div>

      <!-- Orders table -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Chi tiết</p>
            <h3 class="ds-section-title">Danh sách đơn hàng ({{ totalOrders }})</h3>
          </div>
        </div>

        <div v-if="paginatedOrders.length === 0" class="crud-empty">
          <span class="material-symbols-outlined" style="font-size:48px;opacity:0.2;">receipt_long</span>
          <div>
            <strong>Chưa có dữ liệu doanh thu</strong>
            <p>Doanh thu sẽ xuất hiện khi khóa học bắt đầu có giao dịch.</p>
          </div>
        </div>

        <div v-else class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Học viên</th>
                <th style="text-align:right;">Số tiền</th>
                <th style="text-align:center;">Trạng thái</th>
                <th style="text-align:right;">Ngày thanh toán</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in paginatedOrders" :key="order.id">
                <td>
                  <strong style="display:block;">{{ order.user?.name || '—' }}</strong>
                  <span style="font-size:0.78rem;color:var(--muted);">{{ order.user?.email || '—' }}</span>
                </td>
                <td style="text-align:right;font-weight:700;">{{ money(order.amount) }}</td>
                <td style="text-align:center;">
                  <span class="rev-status-badge" :class="statusClass(order.status)">
                    {{ statusLabel(order.status) }}
                  </span>
                </td>
                <td style="text-align:right;color:var(--muted);font-size:0.85rem;">
                  {{ order.paid_at ? formatDate(order.paid_at) : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="lastPage > 1" class="crud-pagination">
          <p>Trang <strong>{{ currentPage }}</strong> / {{ lastPage }}</p>
          <div class="crud-pagination-btns">
            <button type="button" class="crud-secondary-btn" :disabled="currentPage <= 1" @click="currentPage--">← Trước</button>
            <button type="button" class="crud-secondary-btn" :disabled="currentPage >= lastPage" @click="currentPage++">Sau →</button>
          </div>
        </div>
      </section>
    </template>
  </InstructorWorkspaceShell>
</template>

<script setup lang="ts">
// @ts-nocheck
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const loading = ref(true)
const summary = ref<any>({})
const allOrders = ref<any[]>([])
const dateFrom = ref('')
const dateTo = ref('')
const currentPage = ref(1)
const perPage = 10

const money = (value: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)

const moneyShort = (n: number) => {
  if (n >= 1_000_000_000) return `${(n / 1_000_000_000).toFixed(1)}B`
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(0)}K`
  return String(n)
}

const formatDate = (value: string) => new Date(value).toLocaleDateString('vi-VN')

function statusLabel(status: string) {
  const map: Record<string, string> = { completed: 'Đã thanh toán', paid: 'Đã thanh toán', pending: 'Đang xử lý', failed: 'Thất bại' }
  return map[status] || status
}

function statusClass(status: string) {
  if (['completed', 'paid'].includes(status)) return 'is-paid'
  if (status === 'pending') return 'is-pending'
  return 'is-failed'
}

const filteredOrders = computed(() =>
  allOrders.value.filter(o => {
    const date = new Date(o.paid_at || o.created_at)
    if (dateFrom.value && date < new Date(dateFrom.value)) return false
    if (dateTo.value && date > new Date(dateTo.value + 'T23:59:59')) return false
    return true
  })
)

const totalOrders = computed(() => filteredOrders.value.length)
const lastPage = computed(() => Math.max(1, Math.ceil(totalOrders.value / perPage)))
const paginatedOrders = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredOrders.value.slice(start, start + perPage)
})

const monthlyChart = computed(() => {
  const map: Record<string, number> = {}
  filteredOrders.value.forEach(o => {
    if (!['completed', 'paid'].includes(o.status)) return
    const d = new Date(o.paid_at || o.created_at)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    map[key] = (map[key] || 0) + (o.amount || 0)
  })
  return Object.entries(map)
    .sort(([a], [b]) => a.localeCompare(b))
    .slice(-6)
    .map(([key, value]) => {
      const [y, m] = key.split('-')
      return { label: `${m}/${y.slice(2)}`, value }
    })
})

const maxBarValue = computed(() => Math.max(...monthlyChart.value.map(b => b.value), 1))

async function loadData() {
  loading.value = true
  currentPage.value = 1
  try {
    const res = await useApi<any>(`/instructor/courses/${courseId}/revenue`, { token: auth.token })
    summary.value = res.summary || {}
    allOrders.value = res.orders?.data || []
  }
  finally { loading.value = false }
}

function resetFilter() {
  dateFrom.value = ''
  dateTo.value = ''
  currentPage.value = 1
}

function exportCSV() {
  const rows = filteredOrders.value.map(o => [
    o.user?.name || '', o.user?.email || '', o.amount || 0, statusLabel(o.status),
    o.paid_at ? formatDate(o.paid_at) : '',
  ])
  const header = ['Học viên', 'Email', 'Số tiền (VND)', 'Trạng thái', 'Ngày thanh toán']
  const csv = [header, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = `doanh-thu-khoa-hoc-${courseId}.csv`; a.click()
  URL.revokeObjectURL(url)
}

onMounted(loadData)
</script>

<style scoped>
.filter-field { display: flex; flex-direction: column; gap: 4px; }
.filter-field label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }

/* Bar chart */
.rev-chart {
  display: flex; align-items: flex-end; gap: 12px; height: 160px; padding: 0 4px;
}
.rev-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; }
.rev-bar-val { font-size: 0.7rem; font-weight: 700; color: var(--green-deep); white-space: nowrap; }
.rev-bar-track {
  flex: 1; width: 100%; display: flex; align-items: flex-end;
  background: var(--bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--line);
}
.rev-bar-fill {
  width: 100%; background: var(--green); border-radius: 8px;
  min-height: 4px; transition: height 0.4s ease;
}
.rev-bar-label { font-size: 0.72rem; font-weight: 600; color: var(--muted); }

/* Status badges */
.rev-status-badge {
  display: inline-flex; align-items: center; height: 22px; padding: 0 10px;
  border-radius: 999px; font-size: 0.72rem; font-weight: 700; border: 1px solid transparent;
}
.is-paid    { background: rgba(29,158,117,0.1); color: var(--green-deep); border-color: rgba(29,158,117,0.2); }
.is-pending { background: rgba(217,119,6,0.1);  color: #b45309;           border-color: rgba(217,119,6,0.2); }
.is-failed  { background: rgba(239,68,68,0.1);  color: #b91c1c;           border-color: rgba(239,68,68,0.2); }

/* Pagination */
.crud-pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px 4px; font-size: 0.85rem; color: var(--muted);
  border-top: 1px solid var(--line); margin-top: 8px;
}
.crud-pagination-btns { display: flex; gap: 8px; }
</style>
