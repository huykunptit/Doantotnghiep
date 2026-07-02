<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const orders = ref<any[]>([])
const selectedOrder = ref<any>(null)
const showModal = ref(false)

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0] = await Promise.allSettled([
    useApi<any>('/orders', { headers: h }),
  ])
  if (r0.status === 'fulfilled') {
    const d = r0.value
    orders.value = Array.isArray(d) ? d : (d?.data || [])
  }
  loading.value = false
})

const totalPaid = computed(() => orders.value.filter(o => o.status === 'paid' || o.status === 'completed').reduce((s, o) => s + (o.amount || o.total || 0), 0))
const paidCount = computed(() => orders.value.filter(o => o.status === 'paid' || o.status === 'completed').length)

function formatPrice(v: number) {
  return v.toLocaleString('vi-VN') + '₫'
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function statusLabel(s: string) {
  const m: Record<string, string> = { paid: 'Đã thanh toán', completed: 'Đã thanh toán', pending: 'Chờ thanh toán', failed: 'Thất bại', cancelled: 'Đã hủy', refunded: 'Hoàn tiền' }
  return m[s] || s
}

function statusClass(s: string) {
  if (s === 'paid' || s === 'completed') return 'paid'
  if (s === 'pending') return 'pending'
  return 'failed'
}

function openInvoice(order: any) {
  selectedOrder.value = order
  showModal.value = true
}
</script>

<template>
  <div class="tu-page">
    <!-- Header -->
    <div>
      <p class="section-kicker">Học vụ</p>
      <h1 class="tu-title">Học phí & Thanh toán</h1>
    </div>

    <!-- Summary strip -->
    <div class="tu-summary">
      <div class="dashboard-card tu-sum-card">
        <div class="tu-sum-icon tone-green"><SylvaIcon name="credit-card" :size="20" /></div>
        <div>
          <p class="tu-sum-val">{{ loading ? '…' : formatPrice(totalPaid) }}</p>
          <p class="tu-sum-lbl">Tổng đã thanh toán</p>
        </div>
      </div>
      <div class="dashboard-card tu-sum-card">
        <div class="tu-sum-icon tone-blue"><SylvaIcon name="file-text" :size="20" /></div>
        <div>
          <p class="tu-sum-val">{{ loading ? '…' : orders.length }}</p>
          <p class="tu-sum-lbl">Tổng đơn hàng</p>
        </div>
      </div>
      <div class="dashboard-card tu-sum-card">
        <div class="tu-sum-icon tone-amber"><SylvaIcon name="check-circle" :size="20" /></div>
        <div>
          <p class="tu-sum-val">{{ loading ? '…' : paidCount }}</p>
          <p class="tu-sum-lbl">Đơn thành công</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="dashboard-card tu-table-wrap">
      <div class="card-head">
        <h2 class="tu-section-title">Lịch sử giao dịch</h2>
      </div>

      <div v-if="loading" class="tu-table-skeleton">
        <span v-for="i in 5" :key="i" class="sd-shimmer" style="height:48px;border-radius:8px;display:block;margin-bottom:8px"></span>
      </div>

      <div v-else-if="orders.length" class="tu-table-scroll">
        <table class="tu-table">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Khóa học</th>
              <th>Ngày</th>
              <th>Số tiền</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td><span class="tu-order-id">#{{ order.order_number || order.id }}</span></td>
              <td class="tu-course-cell">
                <p class="tu-course-name">{{ order.course?.title || order.items?.[0]?.title || 'Khóa học' }}</p>
                <p v-if="order.items?.length > 1" class="tu-course-more">+{{ order.items.length - 1 }} khóa khác</p>
              </td>
              <td>{{ formatDate(order.created_at || order.paid_at) }}</td>
              <td><strong class="tu-amount">{{ formatPrice(order.amount || order.total || 0) }}</strong></td>
              <td>
                <span class="tu-status-badge" :class="statusClass(order.status)">
                  {{ statusLabel(order.status) }}
                </span>
              </td>
              <td>
                <button class="tu-btn-detail" @click="openInvoice(order)">Chi tiết</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="sd-empty">
        <SylvaIcon name="credit-card" :size="40" />
        <p>Chưa có giao dịch nào.</p>
        <NuxtLink to="/student/recommendations" class="tu-btn-cta">Đăng ký khóa học</NuxtLink>
      </div>
    </div>

    <!-- Invoice modal -->
    <Teleport to="body">
      <div v-if="showModal" class="tu-modal-overlay" @click.self="showModal=false">
        <div class="tu-modal">
          <div class="tu-modal-head">
            <h3>Chi tiết đơn hàng</h3>
            <button class="tu-modal-close" @click="showModal=false">
              <SylvaIcon name="x" :size="18" />
            </button>
          </div>
          <div v-if="selectedOrder" class="tu-modal-body">
            <div class="tu-invoice-row">
              <span>Mã đơn</span>
              <strong>#{{ selectedOrder.order_number || selectedOrder.id }}</strong>
            </div>
            <div class="tu-invoice-row">
              <span>Ngày</span>
              <strong>{{ formatDate(selectedOrder.created_at || selectedOrder.paid_at) }}</strong>
            </div>
            <div class="tu-invoice-row">
              <span>Trạng thái</span>
              <span class="tu-status-badge" :class="statusClass(selectedOrder.status)">{{ statusLabel(selectedOrder.status) }}</span>
            </div>
            <div v-if="selectedOrder.payment_method" class="tu-invoice-row">
              <span>Phương thức</span>
              <strong>{{ selectedOrder.payment_method }}</strong>
            </div>
            <div class="tu-invoice-divider"></div>
            <div v-for="(item, idx) in (selectedOrder.items || [{title: selectedOrder.course?.title || 'Khóa học', price: selectedOrder.amount || selectedOrder.total}])" :key="idx" class="tu-invoice-item">
              <span>{{ item.title || item.name }}</span>
              <strong>{{ formatPrice(item.price || item.amount || 0) }}</strong>
            </div>
            <div class="tu-invoice-divider"></div>
            <div class="tu-invoice-row tu-invoice-total">
              <span>Tổng cộng</span>
              <strong class="tu-total-val">{{ formatPrice(selectedOrder.amount || selectedOrder.total || 0) }}</strong>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.tu-page { display: flex; flex-direction: column; gap: 20px; }
.tu-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin: 4px 0 0; }

.tu-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.tu-sum-card { display: flex; align-items: center; gap: 14px; padding: 16px; }
.tu-sum-icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.tu-sum-icon.tone-green { background: var(--green-soft); color: var(--green-deep); }
.tu-sum-icon.tone-blue { background: var(--secondary-soft); color: var(--secondary); }
.tu-sum-icon.tone-amber { background: var(--accent-soft); color: #92400e; }
.tu-sum-val { font-size: 1.3rem; font-weight: 800; color: var(--text); margin: 0 0 2px; }
.tu-sum-lbl { font-size: 0.72rem; color: var(--muted); font-weight: 600; margin: 0; }

.tu-table-wrap { padding: 20px; }
.tu-section-title { font-size: 1rem; font-weight: 700; color: var(--text); margin: 0; }
.tu-table-scroll { overflow-x: auto; margin-top: 16px; }
.tu-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.tu-table th {
  padding: 10px 12px; text-align: left;
  font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--muted); border-bottom: 2px solid var(--line);
}
.tu-table td { padding: 12px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.tu-table tr:last-child td { border-bottom: none; }
.tu-table tr:hover td { background: var(--bg); }
.tu-order-id { font-family: monospace; font-size: 0.82rem; color: var(--muted); }
.tu-course-name { font-weight: 600; color: var(--text); margin: 0; }
.tu-course-more { font-size: 0.72rem; color: var(--muted); margin: 2px 0 0; }
.tu-amount { color: var(--text); font-size: 0.9rem; }
.tu-status-badge {
  display: inline-flex; align-items: center;
  font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px;
  background: var(--bg); color: var(--muted);
}
.tu-status-badge.paid { background: var(--green-soft); color: var(--green-deep); }
.tu-status-badge.pending { background: var(--accent-soft); color: #92400e; }
.tu-status-badge.failed { background: rgba(239,68,68,0.1); color: var(--danger, #ef4444); }
.tu-btn-detail {
  padding: 5px 12px; border-radius: 7px; border: 1px solid var(--line);
  background: transparent; color: var(--muted); font-size: 0.78rem; font-weight: 600; cursor: pointer;
  transition: background 150ms, color 150ms;
}
.tu-btn-detail:hover { background: var(--green-soft); color: var(--green-deep); border-color: transparent; }
.tu-btn-cta {
  display: inline-flex; align-items: center; margin-top: 8px;
  padding: 7px 16px; border-radius: 8px;
  background: var(--green); color: #fff;
  font-size: 0.82rem; font-weight: 700; text-decoration: none;
}

/* Modal */
.tu-modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.tu-modal {
  background: var(--surface-strong); border-radius: 16px;
  width: 100%; max-width: 480px;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}
.tu-modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--line);
}
.tu-modal-head h3 { font-size: 1rem; font-weight: 700; color: var(--text); margin: 0; }
.tu-modal-close {
  width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--line);
  background: transparent; color: var(--muted); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 150ms;
}
.tu-modal-close:hover { background: var(--bg); color: var(--text); }
.tu-modal-body { padding: 20px; display: flex; flex-direction: column; gap: 10px; }
.tu-invoice-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.86rem; }
.tu-invoice-row span:first-child { color: var(--muted); }
.tu-invoice-row strong { color: var(--text); }
.tu-invoice-divider { height: 1px; background: var(--line); margin: 4px 0; }
.tu-invoice-item { display: flex; justify-content: space-between; font-size: 0.86rem; }
.tu-invoice-item span { color: var(--text); flex: 1; }
.tu-invoice-total span:first-child { font-weight: 700; font-size: 0.9rem; color: var(--text); }
.tu-total-val { font-size: 1.1rem; color: var(--green, #0F6E8C); }

.sd-shimmer { background: linear-gradient(90deg, var(--line) 25%, var(--bg) 50%, var(--line) 75%); background-size: 200% 100%; animation: sd-shimmer 1.5s infinite; border-radius: 6px; }
@keyframes sd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.sd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 20px; color: var(--muted); gap: 10px; }
.sd-empty p { font-size: 0.9rem; }

[data-theme="dark"] .tu-modal { background: var(--surface-strong); }
[data-theme="dark"] .tu-table tr:hover td { background: var(--surface); }

@media (max-width: 768px) {
  .tu-summary { grid-template-columns: 1fr 1fr; }
  .tu-table { min-width: 600px; }
}
@media (max-width: 480px) {
  .tu-summary { grid-template-columns: 1fr; }
}
</style>
