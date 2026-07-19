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
  try {
    const res = await useApi<any>('/orders', { headers: h })
    orders.value = Array.isArray(res) ? res : (res?.data || [])
  } catch {
    // fallback
  } finally {
    loading.value = false
  }
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
  if (s === 'paid' || s === 'completed') return 'bg-emerald-50 text-emerald-700 border-emerald-100'
  if (s === 'pending') return 'bg-amber-50 text-amber-700 border-amber-100'
  return 'bg-red-50 text-red-700 border-red-100'
}

function openInvoice(order: any) {
  selectedOrder.value = order
  showModal.value = true
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ</p>
      <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Học phí & Thanh toán</h1>
    </div>

    <!-- Summary strip -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#1d9e75] flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">payments</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : formatPrice(totalPaid) }}</p>
          <p class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Tổng đã thanh toán</p>
        </div>
      </div>
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">receipt_long</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : orders.length }}</p>
          <p class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Tổng đơn hàng</p>
        </div>
      </div>
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">verified</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : paidCount }}</p>
          <p class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Đơn thành công</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
      <h2 class="text-xs font-bold text-[var(--text)]">Lịch sử giao dịch</h2>

      <!-- Skeleton Loading -->
      <div v-if="loading" class="flex flex-col gap-3 animate-pulse">
        <span v-for="i in 4" :key="i" class="h-12 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl" />
      </div>

      <div v-else-if="orders.length" class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
              <th class="px-5 py-3">Mã đơn</th>
              <th class="px-5 py-3">Khóa học</th>
              <th class="px-5 py-3 text-center">Ngày</th>
              <th class="px-5 py-3 text-center">Số tiền</th>
              <th class="px-5 py-3 text-center">Trạng thái</th>
              <th class="px-5 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-5 py-4"><span class="font-mono text-xs font-bold text-[var(--muted)]">#{{ order.order_number || order.id }}</span></td>
              <td class="px-5 py-4 min-w-[200px]">
                <p class="text-xs font-bold text-[var(--text)] leading-snug">{{ order.course?.title || order.items?.[0]?.title || 'Khóa học' }}</p>
                <p v-if="order.items?.length > 1" class="text-[9px] text-[var(--muted)] mt-1 font-semibold">+{{ order.items.length - 1 }} khóa khác</p>
              </td>
              <td class="px-5 py-4 text-center text-xs text-[var(--muted)] font-semibold">{{ formatDate(order.created_at || order.paid_at) }}</td>
              <td class="px-5 py-4 text-center"><strong class="text-xs font-bold text-[var(--text)]">{{ formatPrice(order.amount || order.total || 0) }}</strong></td>
              <td class="px-5 py-4 text-center">
                <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-bold border" :class="statusClass(order.status)">
                  {{ statusLabel(order.status) }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <button class="h-8 px-4 rounded-xl border border-[var(--line)] hover:bg-[var(--surface)] text-xs font-bold text-[var(--muted)] hover:text-[var(--text)] transition-colors" @click="openInvoice(order)">Chi tiết</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="flex flex-col items-center gap-3 text-center py-12">
        <span class="material-symbols-outlined text-3xl text-[var(--muted)] opacity-60">payments</span>
        <p class="text-xs font-semibold text-[var(--muted)]">Chưa có giao dịch nào.</p>
        <NuxtLink to="/student/recommendations" class="h-9 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#157959] text-white text-xs font-bold flex items-center transition-colors mt-2">Đăng ký khóa học</NuxtLink>
      </div>
    </div>

    <!-- Invoice modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showModal = false">
        <div class="bg-white border border-[var(--line)] rounded-2xl w-full max-w-md shadow-xl overflow-hidden flex flex-col">
          <div class="flex items-center justify-between px-5 py-4 bg-[var(--surface)] border-b border-[var(--line)]">
            <h3 class="text-xs font-bold text-[var(--text)]">Chi tiết đơn hàng</h3>
            <button class="w-8 h-8 rounded-lg border border-[var(--line)] hover:bg-[var(--surface)] flex items-center justify-center text-[var(--muted)]" @click="showModal = false">
              <span class="material-symbols-outlined text-sm leading-none">close</span>
            </button>
          </div>
          <div v-if="selectedOrder" class="p-5 flex flex-col gap-3 text-xs text-[var(--text)]">
            <div class="flex justify-between items-center">
              <span class="text-[var(--muted)] font-semibold">Mã đơn</span>
              <strong class="font-mono font-bold text-slate-800">#{{ selectedOrder.order_number || selectedOrder.id }}</strong>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[var(--muted)] font-semibold">Ngày</span>
              <strong class="font-bold text-slate-800">{{ formatDate(selectedOrder.created_at || selectedOrder.paid_at) }}</strong>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[var(--muted)] font-semibold">Trạng thái</span>
              <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border" :class="statusClass(selectedOrder.status)">{{ statusLabel(selectedOrder.status) }}</span>
            </div>
            <div v-if="selectedOrder.payment_method" class="flex justify-between items-center">
              <span class="text-[var(--muted)] font-semibold">Phương thức</span>
              <strong class="font-bold text-slate-800">{{ selectedOrder.payment_method }}</strong>
            </div>
            
            <div class="h-px bg-[var(--line)] my-1"></div>
            
            <div v-for="(item, idx) in (selectedOrder.items || [{title: selectedOrder.course?.title || 'Khóa học', price: selectedOrder.amount || selectedOrder.total}])" :key="idx" class="flex justify-between items-start gap-4">
              <span class="text-[var(--muted)] font-semibold leading-relaxed flex-1">{{ item.title || item.name }}</span>
              <strong class="font-bold text-slate-800 flex-shrink-0">{{ formatPrice(item.price || item.amount || 0) }}</strong>
            </div>
            
            <div class="h-px bg-[var(--line)] my-1"></div>
            
            <div class="flex justify-between items-center">
              <span class="text-sm font-bold text-[var(--text)]">Tổng cộng</span>
              <strong class="text-base font-black text-[#1d9e75]">{{ formatPrice(selectedOrder.amount || selectedOrder.total || 0) }}</strong>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
