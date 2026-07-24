<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor'],
})

interface MonthPoint { month: string, label: string, value: number }
interface MyCourse {
  id: number
  title: string
  price?: number
  enrollments_count?: number
}
interface InstructorStats {
  total_revenue?: number
  revenue_by_month?: MonthPoint[]
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(true)
const stats = ref<InstructorStats>({})
const courses = ref<MyCourse[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatVnd = (n = 0) => new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n)

function chartColors() {
  const dark = import.meta.client && document.documentElement.classList.contains('dark')
  return {
    text: dark ? '#a8b8b4' : '#4a5a57',
    grid: dark ? 'rgba(255,255,255,.08)' : 'rgba(15,118,110,.12)',
    brand: '#0f766e',
    brandSoft: 'rgba(15,118,110,.25)',
  }
}

const revenueChart = computed(() => {
  const c = chartColors()
  const points = stats.value.revenue_by_month || []
  return {
    data: {
      labels: points.map(p => p.label),
      datasets: [{
        data: points.map(p => p.value),
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
  loading.value = true
  try {
    const [s, c] = await Promise.all([
      useApi<InstructorStats>('/instructor/stats'),
      useApi<{ data: MyCourse[] }>('/my-courses', { query: { per_page: 100 } }),
    ])
    stats.value = s
    courses.value = c.data || []
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.revenue.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('instructor.console') }}</span>
        <h1>{{ t('instructor.revenue.title') }}</h1>
        <p>{{ t('instructor.revenue.subtitle') }}</p>
      </div>
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="kpi">
      <span>{{ t('instructor.revenue.total') }}</span>
      <strong>{{ formatVnd(stats.total_revenue || 0) }}</strong>
    </section>

    <section class="panel">
      <header class="panel-head"><strong>{{ t('instructor.revenue.trend') }}</strong></header>
      <ChartsUiChart type="line" :data="revenueChart.data" :options="revenueChart.options" height="240px" />
    </section>

    <section class="table-panel">
      <header class="panel-head"><strong>{{ t('instructor.revenue.byCourse') }}</strong></header>
      <DataTable :value="courses" data-key="id" :loading="loading">
        <Column :header="t('instructor.revenue.courseTitle')">
          <template #body="{ data }">
            <NuxtLink :to="`/instructor/courses/${data.id}/revenue`" class="link">{{ data.title }}</NuxtLink>
          </template>
        </Column>
        <Column :header="t('instructor.revenue.price')">
          <template #body="{ data }">{{ formatVnd(data.price || 0) }}</template>
        </Column>
        <Column :header="t('instructor.revenue.enrollments')">
          <template #body="{ data }">{{ data.enrollments_count || 0 }}</template>
        </Column>
        <Column style="width:8rem">
          <template #body="{ data }">
            <Button :label="t('instructor.revenue.viewDetail')" size="small" text @click="navigateTo(`/instructor/courses/${data.id}/revenue`)" />
          </template>
        </Column>
        <template #empty><div class="empty">{{ t('common.noData') }}</div></template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head { display: flex; justify-content: space-between; gap: 12px; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.45rem, 2vw, 1.8rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.kpi, .panel, .table-panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.kpi { display: flex; flex-direction: column; gap: 4px; max-width: 320px; }
.kpi span { color: var(--text-muted); font-size: .78rem; font-weight: 650; }
.kpi strong { font-family: var(--font-display); font-size: 1.6rem; }
.panel-head { margin-bottom: 10px; }
.link { font-weight: 700; color: var(--text); }
.link:hover { color: var(--brand); }
.empty { padding: 28px; text-align: center; color: var(--text-muted); }
</style>
