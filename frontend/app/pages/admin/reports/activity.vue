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
  <AdminWorkspaceShell
    title="Nhật ký hoạt động"
    description="Lịch sử các giao dịch và hoạt động mới nhất trên hệ thống theo thời gian thực."
    :breadcrumb="['Trang chủ', 'Hệ thống', 'Nhật ký hoạt động']"
  >
    <div v-if="loading && orders.length === 0" class="dashboard-card crud-empty">Đang tải hoạt động...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article class="dashboard-card mini-card tone-blue">
          <p class="mini-title">Hoạt động hôm nay</p>
          <div class="mini-head"><strong>{{ todayCount }}</strong><span>Giao dịch mới</span></div>
        </article>
        <article class="dashboard-card mini-card tone-green">
          <p class="mini-title">Doanh thu hôm nay</p>
          <div class="mini-head"><strong>{{ formatMoney(paidToday) }}</strong><span>Đã thanh toán</span></div>
        </article>
        <article class="dashboard-card mini-card tone-amber">
          <p class="mini-title">7 ngày qua</p>
          <div class="mini-head"><strong>{{ weekCount }}</strong><span>Giao dịch trong tuần</span></div>
        </article>
        <article class="dashboard-card mini-card">
          <p class="mini-title">Tổng tất cả</p>
          <div class="mini-head"><strong>{{ total }}</strong><span>Giao dịch hệ thống</span></div>
        </article>
      </section>

      <!-- Timeline -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Nhật ký giao dịch</p>
            <h3>Hoạt động gần nhất</h3>
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
            <button class="crud-secondary-btn" type="button" :disabled="loading" @click="fetchActivity(currentPage)">
              ↻ Làm mới
            </button>
          </div>
        </div>

        <div class="activity-timeline">
          <div v-for="order in orders" :key="order.id" class="timeline-item">
            <div class="timeline-avatar">
              <img v-if="order.user?.avatar" :src="order.user.avatar" :alt="order.user?.name" class="timeline-avatar-img">
              <div v-else class="timeline-avatar-fallback">
                {{ order.user?.name?.slice(0, 2).toUpperCase() || 'KH' }}
              </div>
            </div>
            <div class="timeline-body">
              <div class="timeline-main">
                <div>
                  <strong>{{ order.user?.name || 'Người dùng không rõ' }}</strong>
                  <span style="color: var(--muted); font-size: 0.85rem; margin-left: 6px;">đã đăng ký</span>
                  <em style="font-style: normal; font-weight: 600; margin-left: 4px;">{{ order.course?.title || 'Khóa học' }}</em>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                  <span style="font-size: 0.8rem; color: var(--muted);">{{ timeAgo(order.created_at) }}</span>
                  <span class="crud-badge" :style="{ color: statusColor(order.status), background: `${statusColor(order.status)}18` }">
                    {{ statusLabel(order.status) }}
                  </span>
                  <span v-if="order.amount" style="font-size: 0.85rem; font-weight: 700; color: var(--green);">
                    {{ formatMoney(order.amount) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div v-if="orders.length === 0" class="crud-empty">Không có hoạt động nào.</div>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="crud-pagination">
          <p>Hiển thị trang {{ currentPage }} / {{ lastPage }} (Tổng số {{ total }} hoạt động)</p>
          <div class="crud-pagination-actions">
            <button class="pagination-num-btn" type="button" :disabled="currentPage <= 1" @click="fetchActivity(currentPage - 1)">
              Trước
            </button>
            <div class="pagination-numbers">
              <button
                v-for="p in visiblePages"
                :key="p"
                class="pagination-num-btn"
                :class="{ 'is-active': p === currentPage }"
                type="button"
                @click="fetchActivity(p)"
              >
                {{ p }}
              </button>
            </div>
            <button class="pagination-num-btn" type="button" :disabled="currentPage >= lastPage" @click="fetchActivity(currentPage + 1)">
              Sau
            </button>
          </div>
        </div>
      </section>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.activity-timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
}
.timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px 0;
  border-bottom: 1px solid var(--line);
}
.timeline-item:last-child { border-bottom: none; }
.timeline-avatar { flex-shrink: 0; }
.timeline-avatar-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}
.timeline-avatar-fallback {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
}
.timeline-body { flex: 1; min-width: 0; }
.timeline-main { line-height: 1.4; }
.timeline-main strong { font-size: 0.9rem; }
</style>
