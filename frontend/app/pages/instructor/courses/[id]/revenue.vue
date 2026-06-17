<template>
  <section class="space-y-8">
    <AppPageHeader eyebrow="Instructor" title="Doanh thu khóa học" description="Theo dõi doanh thu, đơn hàng đã thanh toán và giá trị trung bình của từng giao dịch.">
      <template #actions>
        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-5 py-2.5 text-sm font-bold text-on-surface shadow-sm hover:bg-surface-low transition-all" @click="exportCSV">
          <span class="material-symbols-outlined text-base">download</span> Xuất CSV
        </button>
        <UiButton to="/instructor/courses" variant="secondary">Quay lại</UiButton>
      </template>
    </AppPageHeader>

    <!-- Date range filter -->
    <UiCard>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-outline">Từ ngày</label>
          <input v-model="dateFrom" type="date" class="h-10 w-full rounded-xl border border-surface-dim/60 bg-surface-lowest px-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10">
        </div>
        <div class="flex-1">
          <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-outline">Đến ngày</label>
          <input v-model="dateTo" type="date" class="h-10 w-full rounded-xl border border-surface-dim/60 bg-surface-lowest px-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10">
        </div>
        <button type="button" class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-white hover:bg-primary-dark transition-all" @click="loadData">
          Lọc
        </button>
        <button type="button" class="inline-flex h-10 items-center gap-2 rounded-xl border border-surface-dim/60 bg-surface-lowest px-5 text-sm font-bold text-on-surface hover:bg-surface-low transition-all" @click="resetFilter">
          Đặt lại
        </button>
      </div>
    </UiCard>

    <!-- KPI cards skeleton -->
    <div v-if="loading" class="grid gap-4 md:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-28 rounded-3xl border border-surface-dim bg-surface-lowest animate-pulse" />
    </div>

    <template v-else>
      <!-- KPI cards -->
      <div class="grid gap-4 md:grid-cols-3">
        <UiCard>
          <p class="text-xs font-semibold uppercase tracking-wide text-outline">Tổng doanh thu</p>
          <p class="mt-2 text-2xl font-bold text-on-surface">{{ money(summary.total_revenue) }}</p>
          <p class="mt-1 text-xs text-on-surface-variant">Từ {{ filteredOrders.length }} giao dịch</p>
        </UiCard>
        <UiCard>
          <p class="text-xs font-semibold uppercase tracking-wide text-outline">Đơn đã thanh toán</p>
          <p class="mt-2 text-2xl font-bold text-on-surface">{{ summary.paid_orders || 0 }}</p>
          <p class="mt-1 text-xs text-on-surface-variant">Trạng thái: completed / paid</p>
        </UiCard>
        <UiCard>
          <p class="text-xs font-semibold uppercase tracking-wide text-outline">Giá trị trung bình</p>
          <p class="mt-2 text-2xl font-bold text-primary">{{ money(summary.average_order_value) }}</p>
          <p class="mt-1 text-xs text-on-surface-variant">Mỗi đơn hàng thành công</p>
        </UiCard>
      </div>

      <!-- Monthly chart -->
      <UiCard v-if="monthlyChart.length">
        <h2 class="text-base font-semibold text-on-surface">Doanh thu theo tháng</h2>
        <div class="mt-4 flex items-end justify-around gap-2" style="height: 160px;">
          <div v-for="bar in monthlyChart" :key="bar.label" class="flex flex-1 flex-col items-center gap-2">
            <span class="text-[10px] font-bold text-primary opacity-0 transition-opacity group-hover:opacity-100">{{ money(bar.value) }}</span>
            <div
              class="chart-bar w-full rounded-t-lg bg-primary/80 hover:bg-primary transition-all cursor-default"
              :style="{ height: `${maxBarValue ? (bar.value / maxBarValue) * 120 : 0}px` }"
              :title="money(bar.value)"
            />
            <span class="text-[10px] font-semibold text-on-surface-variant">{{ bar.label }}</span>
          </div>
        </div>
      </UiCard>

      <!-- Orders table -->
      <UiCard>
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-on-surface">Danh sách đơn hàng</h2>
          <p class="text-sm text-on-surface-variant">{{ totalOrders }} đơn</p>
        </div>

        <div v-if="paginatedOrders.length === 0" class="mt-6">
          <UiEmptyState title="Chưa có dữ liệu doanh thu" description="Doanh thu sẽ xuất hiện khi khóa học bắt đầu có giao dịch." />
        </div>

        <div v-else class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-surface-dim">
                <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wide text-outline">Học viên</th>
                <th class="pb-3 text-right text-xs font-semibold uppercase tracking-wide text-outline">Số tiền</th>
                <th class="pb-3 text-center text-xs font-semibold uppercase tracking-wide text-outline">Trạng thái</th>
                <th class="pb-3 text-right text-xs font-semibold uppercase tracking-wide text-outline">Ngày thanh toán</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-dim">
              <tr v-for="order in paginatedOrders" :key="order.id" class="hover:bg-surface-low/40 transition-colors">
                <td class="py-3 pr-4">
                  <p class="font-semibold text-on-surface">{{ order.user?.name || '—' }}</p>
                  <p class="text-xs text-on-surface-variant">{{ order.user?.email || '—' }}</p>
                </td>
                <td class="py-3 text-right font-semibold text-on-surface">{{ money(order.amount) }}</td>
                <td class="py-3 text-center">
                  <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-bold" :class="statusClass(order.status)">
                    {{ statusLabel(order.status) }}
                  </span>
                </td>
                <td class="py-3 text-right text-on-surface-variant">
                  {{ order.paid_at ? formatDate(order.paid_at) : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="mt-4 flex items-center justify-between border-t border-surface-dim pt-4">
          <p class="text-sm text-on-surface-variant">Trang {{ currentPage }} / {{ lastPage }}</p>
          <div class="flex gap-2">
            <button type="button" class="rounded-lg border border-surface-dim/60 bg-surface-lowest px-3 py-1.5 text-sm font-semibold disabled:opacity-40 hover:bg-surface-low transition-all" :disabled="currentPage <= 1" @click="currentPage--">
              ← Trước
            </button>
            <button type="button" class="rounded-lg border border-surface-dim/60 bg-surface-lowest px-3 py-1.5 text-sm font-semibold disabled:opacity-40 hover:bg-surface-low transition-all" :disabled="currentPage >= lastPage" @click="currentPage++">
              Sau →
            </button>
          </div>
        </div>
      </UiCard>
    </template>
  </section>
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

const formatDate = (value: string) =>
  new Date(value).toLocaleDateString('vi-VN')

function statusLabel(status: string) {
  const map: Record<string, string> = {
    completed: 'Đã thanh toán',
    paid: 'Đã thanh toán',
    pending: 'Đang xử lý',
    failed: 'Thất bại',
  }
  return map[status] || status
}

function statusClass(status: string) {
  if (['completed', 'paid'].includes(status))
    return 'bg-green-100 text-green-700'
  if (status === 'pending')
    return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}

const filteredOrders = computed(() => {
  return allOrders.value.filter(o => {
    const date = new Date(o.paid_at || o.created_at)
    if (dateFrom.value && date < new Date(dateFrom.value)) return false
    if (dateTo.value && date > new Date(dateTo.value + 'T23:59:59')) return false
    return true
  })
})

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

const maxBarValue = computed(() =>
  Math.max(...monthlyChart.value.map(b => b.value), 1)
)

async function loadData() {
  loading.value = true
  currentPage.value = 1
  try {
    const res = await useApi<any>(`/instructor/courses/${courseId}/revenue`, { token: auth.token })
    summary.value = res.summary || {}
    allOrders.value = res.orders?.data || []
  }
  finally {
    loading.value = false
  }
}

function resetFilter() {
  dateFrom.value = ''
  dateTo.value = ''
  currentPage.value = 1
}

function exportCSV() {
  const rows = filteredOrders.value.map(o => [
    o.user?.name || '',
    o.user?.email || '',
    o.amount || 0,
    statusLabel(o.status),
    o.paid_at ? formatDate(o.paid_at) : '',
  ])
  const header = ['Học viên', 'Email', 'Số tiền (VND)', 'Trạng thái', 'Ngày thanh toán']
  const csv = [header, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `doanh-thu-khoa-hoc-${courseId}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(loadData)
</script>

<style scoped>
.chart-bar {
  min-height: 4px;
  transition: height 0.4s ease;
}
</style>
