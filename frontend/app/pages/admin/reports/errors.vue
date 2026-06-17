<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
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
  <AdminWorkspaceShell
    title="Lịch sử lỗi & Giao dịch thất bại"
    description="Danh sách các giao dịch thanh toán thất bại cần kiểm tra. Lỗi hệ thống chuyên sâu cần tích hợp thêm hệ thống log (Sentry / ELK Stack)."
    :breadcrumb="['Trang chủ', 'Hỗ trợ', 'Lịch sử lỗi']"
  >
    <!-- Notice banner -->
    <div class="dashboard-card notice-banner" style="margin-bottom: 24px;">
      <div style="display: flex; align-items: flex-start; gap: 14px;">
        <span class="material-symbols-outlined" style="color: #d97706; font-size: 24px; flex-shrink: 0; margin-top: 2px;">info</span>
        <div>
          <strong style="display: block; margin-bottom: 4px;">Về tính năng này</strong>
          <p style="color: var(--muted); font-size: 0.875rem; margin: 0; line-height: 1.6;">
            Hiện tại hiển thị <strong>giao dịch thanh toán thất bại</strong> như một proxy cho lỗi hệ thống.
            Để có nhật ký lỗi đầy đủ (exception logs, server errors, client-side errors),
            cần tích hợp thêm <strong>Sentry</strong> hoặc <strong>ELK Stack</strong> ở tầng backend.
          </p>
        </div>
      </div>
    </div>

    <div v-if="loading && allOrders.length === 0" class="dashboard-card crud-empty">Đang tải dữ liệu lỗi...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article class="dashboard-card mini-card" style="border-left: 3px solid #ef4444;">
          <p class="mini-title">Giao dịch thất bại</p>
          <div class="mini-head">
            <strong style="color: #ef4444;">{{ total }}</strong>
            <span>Cần kiểm tra</span>
          </div>
        </article>
        <article class="dashboard-card mini-card tone-amber">
          <p class="mini-title">Tổng giá trị mất</p>
          <div class="mini-head">
            <strong>{{ formatMoney(allOrders.reduce((s, o) => s + (o.amount || 0), 0)) }}</strong>
            <span>Không thu được</span>
          </div>
        </article>
        <article class="dashboard-card mini-card">
          <p class="mini-title">Trên trang này</p>
          <div class="mini-head">
            <strong>{{ filteredOrders.length }}</strong>
            <span>Bản ghi hiển thị</span>
          </div>
        </article>
      </section>

      <!-- Table -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <form class="crud-toolbar-main" @submit.prevent>
            <input
              v-model="search"
              class="crud-search"
              type="text"
              placeholder="Tìm học viên, email hoặc khóa học..."
            >
          </form>
          <div class="crud-toolbar-right">
            <button class="crud-export-btn" type="button" @click="exportCSV">
              <span class="material-symbols-outlined">download</span>
              Xuất Excel
            </button>
            <button class="crud-primary-btn" type="button" @click="exportPDF" style="display: inline-flex; align-items: center; gap: 6px;">
              <span class="material-symbols-outlined">picture_as_pdf</span>
              Xuất PDF
            </button>
            <button class="crud-secondary-btn" type="button" :disabled="loading" @click="fetchFailedOrders(currentPage)">
              ↻ Làm mới
            </button>
          </div>
        </div>

        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Người dùng</th>
                <th>Khóa học</th>
                <th>Số tiền</th>
                <th>Phương thức TT</th>
                <th>Mã tham chiếu</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredOrders.length === 0">
                <td colspan="6" class="crud-empty">
                  <span class="material-symbols-outlined" style="font-size: 36px; display: block; margin: 0 auto 8px; opacity: 0.2;">check_circle</span>
                  Không có giao dịch thất bại nào. Hệ thống hoạt động bình thường.
                </td>
              </tr>
              <tr v-for="order in filteredOrders" :key="order.id">
                <td>
                  <div class="crud-profile">
                    <div class="crud-avatar crud-avatar-fallback">
                      {{ order.user?.name?.slice(0, 2).toUpperCase() || 'KH' }}
                    </div>
                    <div>
                      <strong>{{ order.user?.name || '—' }}</strong>
                      <p>{{ order.user?.email || '—' }}</p>
                    </div>
                  </div>
                </td>
                <td>
                  <strong style="display: block; max-width: 22ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ order.course?.title || '—' }}
                  </strong>
                  <p style="font-size: 0.75rem; color: var(--muted);">Đơn #{{ order.id }}</p>
                </td>
                <td>
                  <strong style="color: #ef4444;">{{ formatMoney(order.amount) }}</strong>
                </td>
                <td>
                  <span class="crud-badge">{{ order.payment_method || 'Không rõ' }}</span>
                </td>
                <td>
                  <code style="font-size: 0.78rem; background: rgba(17,17,17,.05); padding: 2px 6px; border-radius: 6px;">
                    {{ order.payment_ref || order.id }}
                  </code>
                </td>
                <td style="font-size: 0.82rem; color: var(--muted);">
                  {{ formatDate(order.created_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="crud-pagination">
          <p>Hiển thị trang {{ currentPage }} / {{ lastPage }} (Tổng số {{ total }} giao dịch lỗi)</p>
          <div class="crud-pagination-actions">
            <button class="pagination-num-btn" type="button" :disabled="currentPage <= 1" @click="fetchFailedOrders(currentPage - 1)">
              Trước
            </button>
            <div class="pagination-numbers">
              <button
                v-for="p in visiblePages"
                :key="p"
                class="pagination-num-btn"
                :class="{ 'is-active': p === currentPage }"
                type="button"
                @click="fetchFailedOrders(p)"
              >
                {{ p }}
              </button>
            </div>
            <button class="pagination-num-btn" type="button" :disabled="currentPage >= lastPage" @click="fetchFailedOrders(currentPage + 1)">
              Sau
            </button>
          </div>
        </div>
      </section>

      <!-- System health indicators -->
      <section class="dashboard-card" style="margin-top: 24px;">
        <div class="card-head" style="margin-bottom: 20px;">
          <h3>Chỉ số hệ thống</h3>
          <p>Trạng thái các thành phần chính. Cần tích hợp health check endpoint để hiển thị dữ liệu thực.</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
          <div v-for="svc in [
            { name: 'API Backend', status: 'online', icon: 'dns' },
            { name: 'Database (MySQL)', status: 'online', icon: 'storage' },
            { name: 'Cache (Redis)', status: 'online', icon: 'memory' },
            { name: 'File Storage (MinIO)', status: 'online', icon: 'cloud' },
            { name: 'AI Service', status: 'online', icon: 'smart_toy' },
            { name: 'Payment Gateway', status: total > 0 ? 'warning' : 'online', icon: 'payment' },
          ]" :key="svc.name" class="health-card">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span class="material-symbols-outlined" style="font-size: 20px; opacity: 0.7;">{{ svc.icon }}</span>
              <div>
                <strong style="font-size: 0.875rem; display: block;">{{ svc.name }}</strong>
                <span :style="{ color: svc.status === 'online' ? 'var(--green-deep)' : '#d97706', fontSize: '0.75rem', fontWeight: '700' }">
                  {{ svc.status === 'online' ? '● Hoạt động' : '⚠ Cần kiểm tra' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.notice-banner {
  border-left: 4px solid #d97706;
  background: #fffbeb;
}
.health-card {
  padding: 14px 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: rgba(17, 17, 17, 0.015);
}
</style>
