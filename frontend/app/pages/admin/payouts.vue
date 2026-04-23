<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      <div class="flex flex-col gap-4 border-b border-surface-dim/30 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Instructor Payouts</p>
          <h2 class="mt-1 text-3xl font-bold font-headline tracking-tight text-on-surface">Tạm tính chi trả giảng viên</h2>
          <p class="mt-2 text-sm text-on-surface-variant">Tổng hợp từ đơn hàng đã thanh toán, dùng để kiểm tra trước khi đối soát thật.</p>
        </div>
        <UiButton variant="secondary" @click="exportPayouts">Xuất CSV</UiButton>
      </div>

      <div class="grid gap-6 md:grid-cols-3">
        <UiCard>
          <p class="text-xs font-bold uppercase tracking-widest text-outline">Giảng viên có doanh thu</p>
          <p class="mt-3 text-4xl font-headline font-bold text-on-surface">{{ payoutRows.length }}</p>
        </UiCard>
        <UiCard>
          <p class="text-xs font-bold uppercase tracking-widest text-outline">Doanh thu gộp</p>
          <p class="mt-3 text-4xl font-headline font-bold text-on-surface">{{ formatCurrency(totalRevenue) }}</p>
        </UiCard>
        <UiCard>
          <p class="text-xs font-bold uppercase tracking-widest text-outline">Tạm tính payout 70%</p>
          <p class="mt-3 text-4xl font-headline font-bold text-on-surface">{{ formatCurrency(totalPayout) }}</p>
        </UiCard>
      </div>

      <UiCard>
        <div v-if="loading" class="grid gap-4 md:grid-cols-2">
          <div v-for="item in 4" :key="item" class="h-28 rounded-2xl bg-surface-high animate-pulse" />
        </div>

        <div v-else-if="payoutRows.length === 0">
          <UiEmptyState title="Chưa có dữ liệu payout" description="Cần có đơn hàng paid/completed để tính tạm chi trả cho giảng viên." />
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="row in payoutRows"
            :key="row.instructor_id"
            class="flex flex-col gap-4 rounded-2xl border border-surface-dim bg-surface-low p-5 md:flex-row md:items-center md:justify-between"
          >
            <div class="min-w-0">
              <p class="text-base font-semibold text-on-surface">{{ row.instructor_name }}</p>
              <p class="mt-1 text-sm text-on-surface-variant">{{ row.course_titles.join(', ') }}</p>
            </div>
            <div class="grid gap-2 text-sm md:text-right">
              <p class="text-on-surface-variant">Doanh thu: <strong class="text-on-surface">{{ formatCurrency(row.revenue) }}</strong></p>
              <p class="text-on-surface-variant">Payout 70%: <strong class="text-primary">{{ formatCurrency(row.payout) }}</strong></p>
            </div>
          </div>
        </div>
      </UiCard>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const loading = ref(true)
const payoutRows = ref<Array<{ instructor_id: number; instructor_name: string; revenue: number; payout: number; course_titles: string[] }>>([])
const { exportToCSV } = useExport()

const totalRevenue = computed(() => payoutRows.value.reduce((sum, row) => sum + row.revenue, 0))
const totalPayout = computed(() => payoutRows.value.reduce((sum, row) => sum + row.payout, 0))
const formatCurrency = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)

const exportPayouts = () => {
  exportToCSV(
    payoutRows.value,
    [
      { key: 'instructor_id', label: 'Instructor ID' },
      { key: 'instructor_name', label: 'Giảng viên' },
      { key: 'revenue', label: 'Doanh thu' },
      { key: 'payout', label: 'Payout 70%' },
      { key: 'course_titles', label: 'Khóa học', format: (value) => Array.isArray(value) ? value.join('; ') : String(value || '') },
    ],
    'admin_payouts',
  )
}

onMounted(async () => {
  try {
    const [coursesResponse, ordersResponse] = await Promise.all([
      $fetch<any>('/api/admin/courses?per_page=100', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [] })),
      $fetch<any>('/api/admin/orders?per_page=200', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [] })),
    ])

    const courseMap = new Map((coursesResponse.data || []).map((course: any) => [course.id, course]))
    const aggregates = new Map<number, { instructor_id: number; instructor_name: string; revenue: number; payout: number; course_titles: Set<string> }>()

    for (const order of ordersResponse.data || []) {
      if (!['paid', 'completed'].includes(order.status)) continue

      const course = courseMap.get(order.course_id)
      const instructorId = Number(course?.instructor?.id || course?.user_id || 0)
      if (!instructorId) continue

      const existing = aggregates.get(instructorId) || {
        instructor_id: instructorId,
        instructor_name: course?.instructor?.name || 'Giảng viên',
        revenue: 0,
        payout: 0,
        course_titles: new Set<string>(),
      }

      existing.revenue += Number(order.amount || 0)
      existing.payout += Math.round(Number(order.amount || 0) * 0.7)
      if (course?.title) existing.course_titles.add(course.title)
      aggregates.set(instructorId, existing)
    }

    payoutRows.value = Array.from(aggregates.values())
      .map((row) => ({ ...row, course_titles: Array.from(row.course_titles) }))
      .sort((a, b) => b.revenue - a.revenue)
  } finally {
    loading.value = false
  }
})
</script>
