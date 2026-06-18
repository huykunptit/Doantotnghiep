<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, FileText } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const allOrders = ref<any[]>([])
const dateFrom = ref('')
const dateTo = ref('')

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

const stats = computed(() => {
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

function formatMoney(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const { exportToPDF } = useExport()

function exportPDF() {
  const headers = ['Học viên', 'Khoá học', 'Số tiền', 'Trạng thái', 'Phương thức', 'Ngày TT']
  const rows = filteredOrders.value.slice(0, 200).map(o => [
    o.user?.name || '—',
    o.course?.title || '—',
    formatMoney(o.amount),
    o.status,
    o.payment_method || '—',
    o.paid_at ? new Date(o.paid_at).toLocaleDateString('vi-VN') : '—',
  ])
  exportToPDF('Báo cáo Doanh thu', `Tổng ${filteredOrders.value.length} giao dịch`, headers, rows, 'bao-cao-doanh-thu')
}

function exportCSV() {
  const rows = filteredOrders.value.map(o => [
    o.user?.name || '',
    o.user?.email || '',
    o.course?.title || '',
    o.amount || 0,
    o.status,
    o.payment_method || '',
    o.paid_at ? new Date(o.paid_at).toLocaleDateString('vi-VN') : '',
  ])
  const header = ['Học viên', 'Email', 'Khóa học', 'Số tiền', 'Trạng thái', 'Phương thức', 'Ngày TT']
  const csv = [header, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = 'bao-cao-doanh-thu.csv'
  a.click()
}

onMounted(fetchOrders)
</script>

<template>
  <AdminWorkspaceShell
    title="Báo cáo thanh toán"
    description="Phân tích doanh thu, dòng tiền và hiệu suất kinh doanh từ dữ liệu giao dịch thực tế."
    :breadcrumb="['Trang chủ', 'Tài chính', 'Báo cáo thanh toán']"
  >
    <!-- Filters -->
    <section class="dashboard-card" style="margin-bottom: 24px; padding: 0; border: none; background: transparent; box-shadow: none;">
      <div class="crud-toolbar">
        <div class="crud-toolbar-main">
          <div class="crud-field" style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Từ</span>
            <input v-model="dateFrom" type="date" style="min-height: 48px; border-radius: 16px;">
          </div>
          <div class="crud-field" style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em;">Đến</span>
            <input v-model="dateTo" type="date" style="min-height: 48px; border-radius: 16px;">
          </div>
          <button class="crud-secondary-btn" type="button" @click="dateFrom = ''; dateTo = ''">Đặt lại</button>
        </div>
        <div class="crud-toolbar-right">
          <button class="crud-export-btn" type="button" @click="exportCSV">
            <Download :size="18" :stroke-width="1.75" />
            Xuất Excel
          </button>
          <button class="crud-primary-btn" type="button" @click="exportPDF" style="display: inline-flex; align-items: center; gap: 6px;">
            <FileText :size="18" :stroke-width="1.75" />
            Xuất PDF
          </button>
        </div>
      </div>
    </section>

    <div v-if="loading" class="dashboard-card crud-empty">Đang tải dữ liệu...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- KPI cards -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article class="dashboard-card mini-card tone-green">
          <p class="mini-title">Tổng doanh thu</p>
          <div class="mini-head">
            <strong>{{ formatMoney(stats.totalRevenue) }}</strong>
            <span>{{ filteredOrders.length }} giao dịch</span>
          </div>
        </article>
        <article class="dashboard-card mini-card tone-blue">
          <p class="mini-title">Đơn thành công</p>
          <div class="mini-head">
            <strong>{{ stats.ordersCount }}</strong>
            <span>Đã hoàn tất thanh toán</span>
          </div>
        </article>
        <article class="dashboard-card mini-card tone-amber">
          <p class="mini-title">Giá trị trung bình</p>
          <div class="mini-head">
            <strong>{{ formatMoney(stats.averageOrder) }}</strong>
            <span>Mỗi đơn hàng</span>
          </div>
        </article>
        <article class="dashboard-card mini-card" style="border-left: 3px solid #ef4444;">
          <p class="mini-title">Đơn thất bại</p>
          <div class="mini-head">
            <strong style="color: #ef4444;">{{ stats.failedCount }}</strong>
            <span>Cần kiểm tra</span>
          </div>
        </article>
      </section>

      <div class="report-layout">
        <!-- Chart -->
        <section class="dashboard-card chart-container">
          <div class="card-head">
            <h3>Doanh thu theo tháng</h3>
            <p>Tổng hợp từ đơn hàng đã thanh toán trong 6 tháng gần nhất.</p>
          </div>
          <div v-if="monthlyChart.length === 0" class="crud-empty" style="padding: 3rem;">
            Chưa có dữ liệu doanh thu theo khoảng thời gian đã chọn.
          </div>
          <div v-else class="revenue-chart">
            <div v-for="item in monthlyChart" :key="item.label" class="chart-bar-wrap">
              <div
                class="chart-bar"
                :style="{ height: `${(item.value / maxBar) * 100}%` }"
                :title="formatMoney(item.value)"
              >
                <span class="bar-value">{{ formatMoney(item.value) }}</span>
              </div>
              <span class="bar-label">{{ item.label }}</span>
            </div>
          </div>
        </section>

        <!-- Side panels -->
        <aside class="report-side">
          <!-- Top courses -->
          <section class="dashboard-card">
            <div class="card-head" style="margin-bottom: 20px;">
              <h3>Khóa học doanh thu cao</h3>
            </div>
            <div v-if="topCourses.length === 0" class="crud-empty" style="padding: 1rem;">Chưa có dữ liệu.</div>
            <div v-else class="top-courses-list">
              <div v-for="course in topCourses" :key="course.title" class="top-course-item">
                <div class="course-info">
                  <strong>{{ course.title }}</strong>
                  <span>{{ formatMoney(course.revenue) }}</span>
                </div>
                <div class="progress-track">
                  <div class="progress-fill" :style="{ width: `${course.share}%` }" />
                </div>
              </div>
            </div>
          </section>

          <!-- Payment methods -->
          <section class="dashboard-card">
            <div class="card-head" style="margin-bottom: 16px;">
              <h3>Phương thức thanh toán</h3>
            </div>
            <div v-if="paymentMethods.length === 0" class="crud-empty" style="padding: 1rem;">Chưa có dữ liệu.</div>
            <div v-else style="display: grid; gap: 12px;">
              <div v-for="pm in paymentMethods" :key="pm.method" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem;">
                <span style="font-weight: 600; text-transform: capitalize;">{{ pm.method }}</span>
                <span class="crud-badge role-instructor">{{ pm.count }} đơn</span>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.report-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
}
.chart-container {
  min-height: 400px;
  display: flex;
  flex-direction: column;
}
.revenue-chart {
  margin-top: auto;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 240px;
  padding: 20px 10px;
  border-bottom: 2px solid var(--line);
}
.chart-bar-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}
.chart-bar {
  width: 42px;
  background: var(--green);
  border-radius: 8px 8px 0 0;
  position: relative;
  transition: all 0.3s ease;
  cursor: pointer;
  min-height: 4px;
}
.chart-bar:hover { background: var(--green-deep); transform: scaleX(1.1); }
.bar-value {
  position: absolute;
  top: -24px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.7rem;
  font-weight: 700;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
  background: white;
  padding: 2px 4px;
  border-radius: 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,.12);
}
.chart-bar:hover .bar-value { opacity: 1; }
.bar-label { font-size: 0.8rem; font-weight: 600; color: var(--muted); }
.report-side { display: flex; flex-direction: column; gap: 24px; }
.top-courses-list { display: grid; gap: 16px; }
.top-course-item { display: grid; gap: 8px; }
.course-info { display: flex; justify-content: space-between; font-size: 0.85rem; }
.course-info strong { max-width: 18ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.progress-track { height: 6px; background: rgba(17,17,17,.05); border-radius: 999px; overflow: hidden; }
.progress-fill { height: 100%; background: var(--green); border-radius: 999px; }
@media (max-width: 1100px) { .report-layout { grid-template-columns: 1fr; } }

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .bar-value { background: var(--surface-strong); color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.5); }
[data-theme="dark"] .progress-track { background: rgba(255, 255, 255, 0.08); }
</style>
