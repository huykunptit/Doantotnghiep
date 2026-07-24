<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khóa học &bull; Tài chính</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Doanh thu khóa học</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Theo dõi doanh thu, đơn hàng đã thanh toán và giá trị trung bình của từng giao dịch.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" @click="exportCSV">
          <span class="material-symbols-outlined text-sm">download</span>
          Xuất CSV
        </button>
        <NuxtLink to="/instructor/courses" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <i class="pi pi-arrow-left text-xs" />
          <span>Quay lại</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Date filter -->
    <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="loadData">
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Từ ngày</label>
          <input v-model="dateFrom" type="date" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]">
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Đến ngày</label>
          <input v-model="dateTo" type="date" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]">
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors">
          <span class="material-symbols-outlined text-sm">filter_list</span>
          Lọc
        </button>
        <button type="button" class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" @click="resetFilter">Đặt lại</button>
      </form>
    </div>

    <!-- KPI -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <div v-for="i in 3" :key="i" class="h-24 bg-[var(--line)] rounded-2xl animate-pulse" />
    </div>
    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
            <span class="material-symbols-outlined text-xl">payments</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng doanh thu</p>
            <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ money(summary.total_revenue) }}</strong>
            <span class="text-[10px] text-[var(--muted)]">{{ filteredOrders.length }} giao dịch</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
            <span class="material-symbols-outlined text-xl">receipt_long</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Đơn đã thanh toán</p>
            <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ summary.paid_orders || 0 }}</strong>
            <span class="text-[10px] text-[var(--muted)]">đơn hàng</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600">
            <span class="material-symbols-outlined text-xl">bar_chart</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Giá trị trung bình</p>
            <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ money(summary.average_order_value) }}</strong>
            <span class="text-[10px] text-[var(--muted)]">mỗi đơn</span>
          </div>
        </div>
      </div>

      <!-- Monthly bar chart -->
      <div v-if="monthlyChart.length" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
        <div class="border-b border-[var(--line)] pb-3">
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Thống kê</p>
          <h3 class="text-sm font-bold text-[var(--text)] mt-0.5">Doanh thu theo tháng</h3>
        </div>
        <div class="flex items-end gap-3 h-40 px-1 pt-4">
          <div v-for="bar in monthlyChart" :key="bar.label" class="flex-1 flex flex-col items-center gap-1.5 h-full">
            <span class="text-[10px] font-bold text-emerald-600">{{ moneyShort(bar.value) }}</span>
            <div class="flex-1 w-full bg-[var(--surface)] border border-[var(--line)] rounded-lg overflow-hidden flex items-end">
              <div
                class="w-full bg-[#1d9e75] rounded-t-md min-h-[4px] transition-all duration-500"
                :style="{ height: `${maxBarValue ? (bar.value / maxBarValue) * 100 : 0}%` }"
                :title="money(bar.value)"
              />
            </div>
            <span class="text-[10px] font-semibold text-[var(--muted)]">{{ bar.label }}</span>
          </div>
        </div>
      </div>

      <!-- Orders table -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex flex-col">
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Chi tiết</p>
          <h3 class="text-xs font-bold text-[var(--text)] mt-0.5">Danh sách đơn hàng ({{ totalOrders }})</h3>
        </div>

        <div v-if="paginatedOrders.length === 0" class="text-center py-12 flex flex-col items-center gap-2 text-xs text-[var(--muted)]">
          <span class="material-symbols-outlined text-4xl opacity-20">receipt_long</span>
          <div>
            <strong class="text-xs font-bold text-[var(--text)]">Chưa có dữ liệu doanh thu</strong>
            <p class="text-[10px] text-[var(--muted)] mt-1">Doanh thu sẽ xuất hiện khi khóa học bắt đầu có giao dịch.</p>
          </div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
                <th class="px-4 py-3">Học viên</th>
                <th class="px-4 py-3 text-right">Số tiền</th>
                <th class="px-4 py-3 text-center">Trạng thái</th>
                <th class="px-4 py-3 text-right">Ngày thanh toán</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in paginatedOrders" :key="order.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
                <td class="px-4 py-3">
                  <strong class="text-xs font-bold text-[var(--text)] block">{{ order.user?.name || '—' }}</strong>
                  <span class="text-[10px] text-[var(--muted)] mt-0.5">{{ order.user?.email || '—' }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-[var(--text)] font-bold text-right">{{ money(order.amount) }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="{
                    'bg-emerald-50 text-emerald-600 border-emerald-100': ['completed', 'paid'].includes(order.status),
                    'bg-amber-50 text-amber-700 border-amber-100': order.status === 'pending',
                    'bg-red-50 text-red-500 border-red-100': !['completed', 'paid', 'pending'].includes(order.status)
                  }">
                    {{ statusLabel(order.status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-[var(--muted)] text-right">
                  {{ order.paid_at ? formatDate(order.paid_at) : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="lastPage > 1" class="px-5 py-4 border-t border-[var(--line)] flex justify-between items-center text-xs text-[var(--muted)]">
          <p>Trang <strong>{{ currentPage }}</strong> / {{ lastPage }}</p>
          <div class="flex gap-2">
            <button type="button" class="h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-semibold text-[var(--text)] transition-colors disabled:opacity-40" :disabled="currentPage <= 1" @click="currentPage--">← Trước</button>
            <button type="button" class="h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-semibold text-[var(--text)] transition-colors disabled:opacity-40" :disabled="currentPage >= lastPage" @click="currentPage++">Sau →</button>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
// @ts-nocheck
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

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
/* Scoped styles kept minimal */
</style>
