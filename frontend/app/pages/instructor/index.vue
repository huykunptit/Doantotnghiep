<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor'],
})

interface MonthPoint { month: string, label: string, value: number }
interface TopCourse { id: number, title: string, enrollments_count: number, price?: number }
interface InstructorStats {
  total_courses?: number
  total_students?: number
  total_revenue?: number
  courses_by_status?: Record<string, number>
  revenue_by_month?: MonthPoint[]
  students_by_month?: MonthPoint[]
  top_courses?: TopCourse[]
}

const auth = useAuthStore()
const toast = useToast()
const { t, locale } = useI18n()
const loading = ref(true)
const stats = ref<InstructorStats>({})

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return t('instructor.dashboard.greetingMorning')
  if (hour < 18) return t('instructor.dashboard.greetingAfternoon')
  return t('instructor.dashboard.greetingEvening')
})

const todayLabel = computed(() =>
  new Intl.DateTimeFormat(numberLocale.value, {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  }).format(new Date()),
)

const formatVnd = (value = 0) =>
  new Intl.NumberFormat(numberLocale.value, {
    style: 'currency', currency: 'VND', maximumFractionDigits: 0,
  }).format(value)

const formatNumber = (value = 0) => value.toLocaleString(numberLocale.value)

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
        label: t('instructor.dashboard.revenue'),
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

const studentsChart = computed(() => {
  const c = chartColors()
  const points = stats.value.students_by_month || []
  return {
    data: {
      labels: points.map(p => p.label),
      datasets: [{
        label: t('instructor.dashboard.students'),
        data: points.map(p => p.value),
        backgroundColor: c.brand,
        borderRadius: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: c.text }, grid: { display: false } },
        y: { ticks: { color: c.text }, grid: { color: c.grid } },
      },
    },
  }
})

const insights = computed(() => {
  const status = stats.value.courses_by_status || {}
  const items: { key: string, text: string, to: string, severity: string }[] = []
  const draft = Number(status.draft || 0)
  const pending = Number(status.pending_review || 0)
  const rejected = Number(status.rejected || 0)
  if (draft > 0) {
    items.push({ key: 'draft', text: t('instructor.dashboard.insightDraft', { n: draft }), to: '/instructor/courses', severity: 'warn' })
  }
  if (pending > 0) {
    items.push({ key: 'pending', text: t('instructor.dashboard.insightPending', { n: pending }), to: '/instructor/courses', severity: 'info' })
  }
  if (rejected > 0) {
    items.push({ key: 'rejected', text: t('instructor.dashboard.insightRejected', { n: rejected }), to: '/instructor/courses', severity: 'danger' })
  }
  const noEnroll = (stats.value.top_courses || []).some(c => (c.enrollments_count || 0) === 0)
  if (noEnroll && Number(status.published || 0) > 0) {
    items.push({ key: 'noEnroll', text: t('instructor.dashboard.insightNoEnroll'), to: '/instructor/revenue', severity: 'warn' })
  }
  const rev = stats.value.revenue_by_month || []
  if (rev.length >= 2) {
    const last = rev[rev.length - 1]?.value || 0
    const prev = rev[rev.length - 2]?.value || 0
    if (prev > 0 && last < prev) {
      items.push({ key: 'drop', text: t('instructor.dashboard.insightRevenueDrop'), to: '/instructor/revenue', severity: 'danger' })
    }
  }
  return items
})

async function load() {
  loading.value = true
  try {
    stats.value = await useApi<InstructorStats>('/instructor/stats')
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.dashboard.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page dash">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('instructor.console') }}</span>
        <h1>{{ greeting }}, {{ auth.user?.name || t('instructor.roleLabel') }}</h1>
        <p>{{ todayLabel }} · {{ t('instructor.dashboard.subtitle') }}</p>
      </div>
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="kpi-rail">
      <div class="kpi">
        <span>{{ t('instructor.dashboard.courses') }}</span>
        <strong>{{ formatNumber(stats.total_courses || 0) }}</strong>
      </div>
      <div class="kpi">
        <span>{{ t('instructor.dashboard.students') }}</span>
        <strong>{{ formatNumber(stats.total_students || 0) }}</strong>
      </div>
      <div class="kpi accent">
        <span>{{ t('instructor.dashboard.revenue') }}</span>
        <strong>{{ formatVnd(stats.total_revenue || 0) }}</strong>
      </div>
    </section>

    <section class="grid-main">
      <div class="panel">
        <header class="panel-head">
          <strong>{{ t('instructor.dashboard.revenueTrend') }}</strong>
        </header>
        <ChartsUiChart type="line" :data="revenueChart.data" :options="revenueChart.options" height="240px" />
      </div>
      <div class="panel">
        <header class="panel-head">
          <strong>{{ t('instructor.dashboard.studentsTrend') }}</strong>
        </header>
        <ChartsUiChart type="bar" :data="studentsChart.data" :options="studentsChart.options" height="240px" />
      </div>
    </section>

    <section class="grid-side">
      <div class="panel">
        <header class="panel-head">
          <strong>{{ t('instructor.dashboard.insights') }}</strong>
        </header>
        <ul v-if="insights.length" class="insight-list">
          <li v-for="item in insights" :key="item.key">
            <NuxtLink :to="item.to" class="insight" :class="item.severity">
              {{ item.text }}
            </NuxtLink>
          </li>
        </ul>
        <p v-else class="empty">{{ t('instructor.dashboard.noInsights') }}</p>
      </div>

      <div class="panel">
        <header class="panel-head">
          <strong>{{ t('instructor.dashboard.topCourses') }}</strong>
        </header>
        <ul class="top-list">
          <li v-for="course in (stats.top_courses || [])" :key="course.id">
            <NuxtLink :to="`/instructor/courses/${course.id}/edit`">{{ course.title }}</NuxtLink>
            <span>{{ t('instructor.dashboard.enrollments', { n: course.enrollments_count || 0 }) }}</span>
          </li>
          <li v-if="!(stats.top_courses || []).length" class="empty">{{ t('common.noData') }}</li>
        </ul>
      </div>

      <div class="panel">
        <header class="panel-head">
          <strong>{{ t('instructor.dashboard.quickActions') }}</strong>
        </header>
        <div class="actions">
          <Button :label="t('instructor.dashboard.createCourse')" icon="pi pi-plus" @click="navigateTo('/instructor/courses')" />
          <Button :label="t('instructor.dashboard.manageCourses')" icon="pi pi-book" severity="secondary" @click="navigateTo('/instructor/courses')" />
          <Button :label="t('instructor.dashboard.viewRevenue')" icon="pi pi-wallet" severity="secondary" @click="navigateTo('/instructor/revenue')" />
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.dash { display: flex; flex-direction: column; gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.45rem, 2vw, 1.8rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.kpi-rail { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.kpi {
  display: flex; flex-direction: column; gap: 4px;
  min-height: 84px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.kpi span { color: var(--text-muted); font-size: .78rem; font-weight: 650; }
.kpi strong { font-family: var(--font-display); font-size: 1.45rem; font-weight: 700; }
.kpi.accent { border-color: color-mix(in srgb, var(--brand) 35%, var(--border)); background: var(--brand-soft); }

.grid-main { display: grid; grid-template-columns: 1.3fr 1fr; gap: 12px; }
.grid-side { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 12px; }
.panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 12px 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.panel-head { margin-bottom: 10px; }
.panel-head strong { font-size: .95rem; }

.insight-list, .top-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.insight {
  display: block; padding: 10px 12px; border-radius: 10px; font-size: .88rem; font-weight: 600;
  border: 1px solid var(--border); background: var(--surface-subtle); color: var(--text);
}
.insight.warn { border-color: #f59e0b55; background: #f59e0b14; }
.insight.info { border-color: #0ea5e955; background: #0ea5e914; }
.insight.danger { border-color: #ef444455; background: #ef444414; }
.top-list li { display: flex; justify-content: space-between; gap: 10px; font-size: .88rem; font-weight: 600; }
.top-list a:hover { color: var(--brand); }
.top-list span { color: var(--text-muted); font-weight: 500; white-space: nowrap; }
.actions { display: flex; flex-direction: column; gap: 8px; }
.empty { margin: 0; color: var(--text-muted); font-size: .9rem; }

@media (max-width: 1100px) {
  .grid-main, .grid-side, .kpi-rail { grid-template-columns: 1fr; }
}
</style>
