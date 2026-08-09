<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: 'manage_courses',
})

interface MonthPoint { month: string, label: string, value: number }
interface OrderRow {
  id: number
  amount: number
  paid_at?: string | null
  user?: { name?: string, email?: string } | null
}

const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const courseId = computed(() => Number(route.params.id))

const loading = ref(true)
const courseTitle = ref('')
const summary = ref({ total_revenue: 0, paid_orders: 0, average_order_value: 0 })
const months = ref<MonthPoint[]>([])
const orders = ref<OrderRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatVnd = (n = 0) => new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n)
const fmtDate = (value?: string | null) => {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

const { colors: chartColors } = useChartColors()

const revenueChart = computed(() => {
  const c = chartColors()
  return {
    data: {
      labels: months.value.map(p => p.label),
      datasets: [{
        data: months.value.map(p => p.value),
        borderColor: c.brand,
        backgroundColor: c.brandSoft,
        fill: true,
        tension: .35,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: c.text }, grid: { color: c.grid } },
        y: { ticks: { color: c.text }, grid: { color: c.grid } },
      },
    },
  }
})

async function load() {
  if (!courseId.value) return
  loading.value = true
  try {
    const res = await useApi<any>(`/instructor/courses/${courseId.value}/revenue`, {
      query: { page: page.value, per_page: perPage.value },
    })
    courseTitle.value = res.course?.title || `#${courseId.value}`
    summary.value = {
      total_revenue: res.summary?.total_revenue || 0,
      paid_orders: res.summary?.paid_orders || 0,
      average_order_value: res.summary?.average_order_value || 0,
    }
    months.value = res.revenue_by_month || []
    orders.value = res.orders?.data || []
    total.value = res.orders?.total || 0
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.revenue.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <Button :label="t('instructor.builder.back')" icon="pi pi-arrow-left" text size="small" class="back" @click="navigateTo('/instructor/revenue')" />
        <h1>{{ courseTitle || t('instructor.menu.courseRevenue') }}</h1>
        <p>{{ t('instructor.revenue.subtitle') }}</p>
      </div>
    </header>

    <section class="kpi-rail">
      <div class="kpi">
        <span>{{ t('instructor.revenue.courseTotal') }}</span>
        <strong>{{ formatVnd(summary.total_revenue) }}</strong>
      </div>
      <div class="kpi">
        <span>{{ t('instructor.revenue.paidOrders') }}</span>
        <strong>{{ summary.paid_orders }}</strong>
      </div>
    </section>

    <section class="panel">
      <header class="panel-head"><strong>{{ t('instructor.revenue.trend') }}</strong></header>
      <ChartsUiChart type="line" :data="revenueChart.data" :options="revenueChart.options" height="220px" />
    </section>

    <section class="table-panel">
      <header class="panel-head"><strong>{{ t('instructor.revenue.orders') }}</strong></header>
      <DataTable
        :value="orders"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :rows="perPage"
        :total-records="total"
        @page="onPage"
      >
        <Column :header="t('instructor.revenue.buyer')">
          <template #body="{ data }">
            <div>
              <strong>{{ data.user?.name || '—' }}</strong>
              <small>{{ data.user?.email }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('instructor.revenue.amount')">
          <template #body="{ data }">{{ formatVnd(data.amount || 0) }}</template>
        </Column>
        <Column :header="t('instructor.revenue.paidAt')">
          <template #body="{ data }">{{ fmtDate(data.paid_at) }}</template>
        </Column>
        <template #empty><CommonEmptyState :description="t('instructor.revenue.emptyOrders')" /></template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.back { margin-left: -8px; margin-bottom: 4px; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.35rem, 2vw, 1.7rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.kpi-rail { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; max-width: 560px; }
.kpi, .panel, .table-panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.kpi span { color: var(--text-muted); font-size: .78rem; font-weight: 650; }
.kpi strong { display: block; margin-top: 4px; font-family: var(--font-display); font-size: 1.35rem; }
.panel-head { margin-bottom: 10px; }
small { display: block; color: var(--text-muted); font-size: .78rem; }

@media (max-width: 700px) { .kpi-rail { grid-template-columns: 1fr; } }
</style>
