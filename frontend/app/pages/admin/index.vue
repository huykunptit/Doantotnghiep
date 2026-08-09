<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface MonthPoint { month: string; label: string; value: number }
interface DayPoint { date: string; label: string; value: number }
interface TopCourse { id: number; title: string; enrollments_count: number }
interface ProgressItem { label: string; value: number }
interface UpcomingSection {
  id?: number
  code?: string
  name?: string
  title?: string
  enrolled_count?: number
  capacity?: number
  course?: { title?: string } | null
  lecturer?: { name?: string } | null
  term?: { name?: string } | null
}
interface DashboardNotification {
  id?: number
  title?: string
  message?: string
  created_at?: string
  read_at?: string | null
}

interface DashboardStats {
  total_users?: number
  total_courses?: number
  total_orders?: number
  total_revenue?: number
  total_students?: number
  total_instructors?: number
  courses_by_status?: Record<string, number>
  revenue_by_month?: MonthPoint[]
  new_users_by_month?: MonthPoint[]
  top_courses?: TopCourse[]
  engagement?: {
    avg_quiz_score?: number
    total_completions?: number
    active_students_this_week?: number
  }
  pending_courses?: number
  published_courses?: number
  paid_orders?: number
  enrollments_week?: number
  enrollments_today?: number
  new_users_week?: number
  reviews_count?: number
  open_sections?: number
}

const auth = useAuthStore()
const toast = useToast()
const { t, locale } = useI18n()
const loading = ref(true)
const stats = ref<DashboardStats>({})
const adminClasses = ref(0)
const creditClasses = ref(0)
const dailyEnrollments = ref<DayPoint[]>([])
const classProgress = ref<ProgressItem[]>([])
const upcomingSections = ref<UpcomingSection[]>([])
const notifications = ref<DashboardNotification[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return t('admin.dashboard.greetingMorning')
  if (hour < 18) return t('admin.dashboard.greetingAfternoon')
  return t('admin.dashboard.greetingEvening')
})

const todayLabel = computed(() =>
  new Intl.DateTimeFormat(numberLocale.value, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date()),
)

const formatVnd = (value = 0) =>
  new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(value)

const formatNumber = (value = 0) => value.toLocaleString(numberLocale.value)

const { colors: chartColors } = useChartColors()

const primaryMetrics = computed(() => [
  {
    label: t('admin.dashboard.revenue'),
    value: formatVnd(stats.value.total_revenue || 0),
    hint: t('admin.dashboard.paidOrdersHint', { n: formatNumber(stats.value.paid_orders || 0) }),
    icon: 'pi-wallet',
    tone: 'brand',
  },
  {
    label: t('admin.dashboard.users'),
    value: formatNumber(stats.value.total_users || 0),
    hint: t('admin.dashboard.newUsersHint', { n: formatNumber(stats.value.new_users_week || 0) }),
    icon: 'pi-users',
    tone: 'blue',
  },
  {
    label: t('admin.dashboard.courses'),
    value: formatNumber(stats.value.total_courses || 0),
    hint: t('admin.dashboard.publishedHint', { n: formatNumber(stats.value.published_courses || 0) }),
    icon: 'pi-book',
    tone: 'amber',
  },
  {
    label: t('admin.dashboard.orders'),
    value: formatNumber(stats.value.total_orders || 0),
    hint: t('admin.dashboard.paidOrdersHint', { n: formatNumber(stats.value.paid_orders || 0) }),
    icon: 'pi-shopping-bag',
    tone: 'violet',
  },
])

const pulseMetrics = computed(() => [
  { label: t('admin.dashboard.enrollToday'), value: formatNumber(stats.value.enrollments_today || 0), icon: 'pi-calendar' },
  { label: t('admin.dashboard.enrollWeek'), value: formatNumber(stats.value.enrollments_week || 0), icon: 'pi-chart-line' },
  { label: t('admin.dashboard.activeStudents'), value: formatNumber(stats.value.engagement?.active_students_this_week || 0), icon: 'pi-bolt' },
  { label: t('admin.dashboard.completions'), value: formatNumber(stats.value.engagement?.total_completions || 0), icon: 'pi-check-circle' },
  { label: t('admin.dashboard.pendingCourses'), value: formatNumber(stats.value.pending_courses || 0), icon: 'pi-clock' },
  { label: t('admin.dashboard.openSections'), value: formatNumber(stats.value.open_sections || creditClasses.value), icon: 'pi-building' },
  { label: t('admin.dashboard.reviews'), value: formatNumber(stats.value.reviews_count || 0), icon: 'pi-star' },
  { label: t('admin.dashboard.avgQuiz'), value: `${Number(stats.value.engagement?.avg_quiz_score || 0).toFixed(1)}/10`, icon: 'pi-chart-bar' },
])

const trafficChartData = computed(() => {
  const colors = chartColors()
  return {
    labels: dailyEnrollments.value.map(item => item.label),
    datasets: [{
      label: t('admin.dashboard.enrollments'),
      data: dailyEnrollments.value.map(item => item.value),
      borderColor: colors.brand,
      backgroundColor: colors.brandSoft,
      fill: true,
      tension: 0.38,
      pointRadius: 2,
      pointHoverRadius: 4,
      pointBackgroundColor: colors.brand,
    }],
  }
})

const trafficChartOptions = computed(() => {
  const colors = chartColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { mode: 'index' as const, intersect: false },
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: colors.text, font: { size: 11, weight: 500 as const }, maxRotation: 0 } },
      y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.text, font: { size: 11, weight: 500 as const }, precision: 0 } },
    },
  }
})

const revenueChartData = computed(() => {
  const colors = chartColors()
  const revenue = stats.value.revenue_by_month || []
  const users = stats.value.new_users_by_month || []
  const labels = revenue.length ? revenue.map(i => i.label) : users.map(i => i.label)
  return {
    labels,
    datasets: [
      {
        label: t('admin.dashboard.revenue'),
        data: revenue.map(i => i.value),
        borderColor: colors.brand,
        backgroundColor: colors.brandSoft,
        fill: true,
        tension: 0.4,
        pointRadius: 2,
        yAxisID: 'y',
      },
      ...(users.length
        ? [{
            label: t('admin.dashboard.newUsers'),
            data: users.map(i => i.value),
            borderColor: colors.blue,
            backgroundColor: 'transparent',
            borderDash: [5, 4],
            tension: 0.35,
            pointRadius: 2,
            yAxisID: 'y1',
          }]
        : []),
    ],
  }
})

const revenueChartOptions = computed(() => {
  const colors = chartColors()
  const hasUsers = (stats.value.new_users_by_month || []).length > 0
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
      legend: { position: 'bottom' as const, labels: { usePointStyle: true, color: colors.text, boxWidth: 8, font: { size: 11, weight: 600 as const } } },
      tooltip: {
        callbacks: {
          label(ctx: any) {
            const value = Number(ctx.parsed.y || 0)
            return ctx.dataset.yAxisID === 'y'
              ? `${ctx.dataset.label}: ${formatVnd(value)}`
              : `${ctx.dataset.label}: ${formatNumber(value)}`
          },
        },
      },
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: colors.text, font: { size: 11, weight: 500 as const } } },
      y: {
        beginAtZero: true,
        grid: { color: colors.grid },
        ticks: {
          color: colors.text,
          font: { size: 11, weight: 500 as const },
          callback(value: string | number) {
            const amount = Number(value)
            if (amount >= 1_000_000) return `${Math.round(amount / 1_000_000)}tr`
            if (amount >= 1_000) return `${Math.round(amount / 1_000)}k`
            return String(value)
          },
        },
      },
      ...(hasUsers
        ? { y1: { beginAtZero: true, position: 'right' as const, grid: { drawOnChartArea: false }, ticks: { color: colors.text, font: { size: 11, weight: 500 as const } } } }
        : {}),
    },
  }
})

const statusChartData = computed(() => {
  const colors = chartColors()
  const palette = [colors.brand, colors.amber, colors.slate, colors.rose, '#0f172a']
  const entries = Object.entries(stats.value.courses_by_status || {})
    .map(([key, value]) => ({
      label: t(`admin.dashboard.status.${key}` as any) || key,
      value: Number(value) || 0,
    }))
    .filter(item => item.value > 0)
  return {
    labels: entries.map(i => i.label),
    datasets: [{ data: entries.map(i => i.value), backgroundColor: entries.map((_, i) => palette[i % palette.length]), borderWidth: 0, hoverOffset: 4 }],
  }
})

const statusChartOptions = computed(() => {
  const colors = chartColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '66%',
    plugins: { legend: { position: 'bottom' as const, labels: { usePointStyle: true, color: colors.text, boxWidth: 8, font: { size: 11, weight: 600 as const } } } },
  }
})

const compositionChartData = computed(() => ({
  labels: [
    t('admin.dashboard.studentsShort'),
    t('admin.dashboard.instructorsShort'),
    t('admin.dashboard.adminClassShort'),
    t('admin.dashboard.creditClassShort'),
  ],
  datasets: [{
    data: [stats.value.total_students || 0, stats.value.total_instructors || 0, adminClasses.value, creditClasses.value],
    backgroundColor: ['rgba(37,99,235,.75)', 'rgba(217,119,6,.75)', 'rgba(15,118,110,.75)', 'rgba(124,58,237,.75)'],
    borderWidth: 0,
  }],
}))

const compositionChartOptions = computed(() => {
  const colors = chartColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' as const, labels: { usePointStyle: true, color: colors.text, boxWidth: 8, font: { size: 11, weight: 600 as const } } } },
    scales: { r: { beginAtZero: true, ticks: { display: false }, grid: { color: colors.grid }, angleLines: { color: colors.grid }, pointLabels: { color: colors.text, font: { size: 11, weight: 600 as const } } } },
  }
})

const progressChartData = computed(() => {
  const colors = chartColors()
  return {
    labels: classProgress.value.map(i => i.label),
    datasets: [{
      label: t('admin.dashboard.progressPercent'),
      data: classProgress.value.map(i => i.value),
      backgroundColor: colors.brandSoft,
      borderColor: colors.brand,
      borderWidth: 1,
      borderRadius: 6,
    }],
  }
})

const progressChartOptions = computed(() => {
  const colors = chartColors()
  return {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, max: 100, grid: { color: colors.grid }, ticks: { color: colors.text, font: { size: 11, weight: 500 as const } } },
      y: { grid: { display: false }, ticks: { color: colors.text, font: { size: 11, weight: 600 as const } } },
    },
  }
})

const engagementChartData = computed(() => {
  const colors = chartColors()
  return {
    labels: [t('admin.dashboard.active'), t('admin.dashboard.completed'), t('admin.dashboard.quiz')],
    datasets: [{
      data: [
        stats.value.engagement?.active_students_this_week || 0,
        Math.min(stats.value.engagement?.total_completions || 0, 100),
        stats.value.engagement?.avg_quiz_score || 0,
      ],
      backgroundColor: colors.brandSoft,
      borderColor: colors.brand,
      pointBackgroundColor: colors.brand,
      borderWidth: 2,
    }],
  }
})

const engagementChartOptions = computed(() => {
  const colors = chartColors()
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { r: { beginAtZero: true, ticks: { display: false }, grid: { color: colors.grid }, angleLines: { color: colors.grid }, pointLabels: { color: colors.text, font: { size: 11, weight: 600 as const } } } },
  }
})

const quickActions = computed(() => [
  { label: t('admin.dashboard.shortcut.users'), to: '/admin/users', icon: 'pi-users' },
  { label: t('admin.dashboard.shortcut.adminClasses'), to: '/admin/lnd/classes', icon: 'pi-building' },
  { label: t('admin.dashboard.shortcut.review'), to: '/admin/courses', icon: 'pi-verified' },
  { label: t('admin.dashboard.shortcut.orders'), to: '/admin/orders', icon: 'pi-wallet' },
  { label: t('admin.dashboard.shortcut.examMonitor'), to: '/admin/exam-monitor', icon: 'pi-eye' },
  { label: t('admin.dashboard.shortcut.settings'), to: '/admin/settings', icon: 'pi-cog' },
])

const trafficTotal = computed(() => dailyEnrollments.value.reduce((sum, item) => sum + item.value, 0))
const hasTraffic = computed(() => dailyEnrollments.value.length > 0)
const hasRevenue = computed(() => Boolean((stats.value.revenue_by_month || []).length || (stats.value.new_users_by_month || []).length))
const hasStatus = computed(() => Object.values(stats.value.courses_by_status || {}).some(v => Number(v) > 0))
const hasComposition = computed(() => [stats.value.total_students, stats.value.total_instructors, adminClasses.value, creditClasses.value].some(v => Number(v) > 0))
const hasProgress = computed(() => classProgress.value.length > 0)
const hasEngagement = computed(() => Boolean(stats.value.engagement?.active_students_this_week || stats.value.engagement?.total_completions || stats.value.engagement?.avg_quiz_score))

function timeAgo(value?: string) {
  if (!value) return '—'
  const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60000)
  if (minutes < 1) return t('common.justNow')
  if (minutes < 60) return t('common.minutesAgo', { n: minutes })
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return t('common.hoursAgo', { n: hours })
  return t('common.daysAgo', { n: Math.floor(hours / 24) })
}

async function loadDashboard() {
  loading.value = true
  try {
    const [overview, extra, classes, sections] = await Promise.all([
      useApi<DashboardStats>('/admin/stats'),
      useApi<any>('/admin/dashboard-extra').catch(() => null),
      useApi<{ total?: number }>('/admin/academic/administrative-classes?per_page=1').catch(() => ({})),
      useApi<{ total?: number }>('/admin/academic/class-sections?per_page=1').catch(() => ({})),
    ])
    stats.value = overview || {}
    adminClasses.value = classes.total || 0
    creditClasses.value = sections.total || 0
    dailyEnrollments.value = extra?.daily_enrollments || []
    classProgress.value = extra?.class_progress || []
    upcomingSections.value = extra?.upcoming_sections || []
    notifications.value = extra?.notifications || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.dashboard.loadError'),
      detail: error?.data?.message || t('admin.dashboard.tryAgain'),
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <div class="page dashboard">
    <header class="page-heading dash-head">
      <div>
        <span class="eyebrow">{{ todayLabel }}</span>
        <h1>{{ greeting }}, {{ auth.user?.name || t('admin.dashboard.adminFallback') }}</h1>
        <p>{{ t('admin.dashboard.subtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button :label="t('common.refresh')" icon="pi pi-refresh" severity="secondary" outlined :loading="loading" @click="loadDashboard" />
      </div>
    </header>

    <section class="kpi-row">
      <article v-for="item in primaryMetrics" :key="item.label" class="kpi" :class="`tone-${item.tone}`">
        <div>
          <span>{{ item.label }}</span>
          <Skeleton v-if="loading" width="6.5rem" height="1.45rem" />
          <strong v-else>{{ item.value }}</strong>
          <small>{{ item.hint }}</small>
        </div>
        <i :class="['pi', item.icon]" />
      </article>
    </section>

    <section class="pulse-row">
      <article v-for="item in pulseMetrics" :key="item.label" class="pulse">
        <i :class="['pi', item.icon]" />
        <div>
          <strong>{{ loading ? '—' : item.value }}</strong>
          <span>{{ item.label }}</span>
        </div>
      </article>
    </section>

    <section class="grid-a">
      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.trafficTitle') }}</h2>
            <p>{{ t('admin.dashboard.trafficHint', { n: formatNumber(trafficTotal) }) }}</p>
          </div>
        </div>
        <div v-if="loading" class="chart-box"><Skeleton height="220px" /></div>
        <ChartsUiChart v-else-if="hasTraffic" type="line" :data="trafficChartData" :options="trafficChartOptions" height="220px" />
        <CommonEmptyState v-else :description="t('admin.dashboard.noTraffic')" />
      </article>

      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.statusTitle') }}</h2>
            <p>{{ t('admin.dashboard.statusHint') }}</p>
          </div>
        </div>
        <div v-if="loading" class="chart-box"><Skeleton height="220px" /></div>
        <ChartsUiChart v-else-if="hasStatus" type="doughnut" :data="statusChartData" :options="statusChartOptions" height="220px" />
        <CommonEmptyState v-else :description="t('admin.dashboard.noStatus')" />
      </article>
    </section>

    <section class="grid-b">
      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.revenueTitle') }}</h2>
            <p>{{ t('admin.dashboard.revenueHint') }}</p>
          </div>
        </div>
        <div v-if="loading" class="chart-box"><Skeleton height="240px" /></div>
        <ChartsUiChart v-else-if="hasRevenue" type="line" :data="revenueChartData" :options="revenueChartOptions" height="240px" />
        <CommonEmptyState v-else :description="t('admin.dashboard.noRevenue')" />
      </article>

      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.compositionTitle') }}</h2>
            <p>{{ t('admin.dashboard.compositionHint') }}</p>
          </div>
        </div>
        <div v-if="loading" class="chart-box"><Skeleton height="240px" /></div>
        <ChartsUiChart v-else-if="hasComposition" type="polarArea" :data="compositionChartData" :options="compositionChartOptions" height="240px" />
        <CommonEmptyState v-else :description="t('admin.dashboard.noComposition')" />
      </article>
    </section>

    <section class="grid-c">
      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.progressTitle') }}</h2>
            <p>{{ t('admin.dashboard.progressHint') }}</p>
          </div>
        </div>
        <div v-if="loading" class="chart-box"><Skeleton height="210px" /></div>
        <ChartsUiChart v-else-if="hasProgress" type="bar" :data="progressChartData" :options="progressChartOptions" height="210px" />
        <CommonEmptyState v-else :description="t('admin.dashboard.noProgress')" />
      </article>

      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.engagementTitle') }}</h2>
            <p>{{ t('admin.dashboard.engagementHint') }}</p>
          </div>
        </div>
        <div v-if="loading" class="chart-box"><Skeleton height="210px" /></div>
        <ChartsUiChart v-else-if="hasEngagement" type="radar" :data="engagementChartData" :options="engagementChartOptions" height="210px" />
        <CommonEmptyState v-else :description="t('admin.dashboard.noEngagement')" />
      </article>

      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.shortcutsTitle') }}</h2>
            <p>{{ t('admin.dashboard.shortcutsHint') }}</p>
          </div>
        </div>
        <div class="shortcut-grid">
          <NuxtLink v-for="action in quickActions" :key="action.to" :to="action.to" class="shortcut">
            <i :class="['pi', action.icon]" />
            <span>{{ action.label }}</span>
          </NuxtLink>
        </div>
      </article>
    </section>

    <section class="grid-d">
      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.topCoursesTitle') }}</h2>
            <p>{{ t('admin.dashboard.topCoursesHint') }}</p>
          </div>
          <NuxtLink to="/admin/manage-courses" class="link">{{ t('common.viewAll') }}</NuxtLink>
        </div>
        <div class="list">
          <div v-for="(course, index) in (stats.top_courses || []).slice(0, 5)" :key="course.id" class="list-row">
            <span class="rank">{{ index + 1 }}</span>
            <strong>{{ course.title }}</strong>
            <Tag :value="t('admin.dashboard.learnersTag', { n: course.enrollments_count })" severity="secondary" />
          </div>
          <div v-if="!loading && !(stats.top_courses || []).length" class="empty compact">{{ t('common.noData') }}</div>
        </div>
      </article>

      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.openClassesTitle') }}</h2>
            <p>{{ t('admin.dashboard.openClassesHint') }}</p>
          </div>
        </div>
        <div class="list">
          <div v-for="section in upcomingSections.slice(0, 5)" :key="section.id || section.code" class="list-row stacked">
            <div>
              <strong>{{ section.code || section.name || '—' }}</strong>
              <small>{{ section.course?.title || t('admin.dashboard.noCourseBound') }} · {{ section.lecturer?.name || t('admin.dashboard.noLecturer') }}</small>
            </div>
            <span>{{ section.enrolled_count || 0 }}/{{ section.capacity || '—' }}</span>
          </div>
          <div v-if="!loading && !upcomingSections.length" class="empty compact">{{ t('admin.dashboard.noOpenClasses') }}</div>
        </div>
      </article>

      <article class="panel">
        <div class="panel-head">
          <div>
            <h2>{{ t('admin.dashboard.notificationsTitle') }}</h2>
            <p>{{ t('admin.dashboard.notificationsHint') }}</p>
          </div>
        </div>
        <div class="list">
          <div v-for="item in notifications.slice(0, 5)" :key="item.id || item.title" class="list-row stacked">
            <div>
              <strong>{{ item.title || t('admin.dashboard.notificationFallback') }}</strong>
              <small>{{ item.message || t('admin.dashboard.noContent') }}</small>
            </div>
            <span>{{ timeAgo(item.created_at) }}</span>
          </div>
          <div v-if="!loading && !notifications.length" class="empty compact">{{ t('admin.dashboard.noNotifications') }}</div>
        </div>
      </article>
    </section>
  </div>
</template>

<style scoped>
.dashboard { gap: 14px; }
.dash-head { margin-bottom: 2px; }
.eyebrow {
  display: block;
  margin-bottom: 4px;
  color: var(--brand);
  font-size: .8rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.kpi {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  min-height: 92px;
  padding: 14px 15px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px);
}

.kpi > div { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.kpi span { color: var(--text-muted); font-size: .86rem; font-weight: 650; }
.kpi strong {
  overflow: hidden;
  color: var(--text);
  font-family: var(--font-display);
  font-size: 1.4rem;
  font-weight: 700;
  letter-spacing: -.03em;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.kpi small { color: var(--text-muted); font-size: .8rem; font-weight: 500; }
.kpi > i {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  border-radius: 10px;
  font-size: .9rem;
}
.tone-brand > i { background: var(--brand-soft); color: var(--brand); }
.tone-blue > i { background: #eaf2ff; color: #2563eb; }
.tone-amber > i { background: #fff6df; color: #d97706; }
.tone-violet > i { background: #f2edff; color: #7c3aed; }
:global(.dark) .tone-blue > i,
:global(.dark) .tone-amber > i,
:global(.dark) .tone-violet > i { background: var(--surface-hover); }

.pulse-row {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 8px;
}

.pulse {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 68px;
  padding: 10px 11px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px);
}

.pulse > i {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex: 0 0 28px;
  border-radius: 8px;
  background: var(--surface-subtle);
  color: var(--brand);
  font-size: .78rem;
}

.pulse > div { display: flex; flex-direction: column; min-width: 0; }
.pulse strong {
  overflow: hidden;
  color: var(--text);
  font-size: 1.05rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pulse span {
  overflow: hidden;
  color: var(--text-muted);
  font-size: .76rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.grid-a,
.grid-b,
.grid-c,
.grid-d {
  display: grid;
  gap: 10px;
}

.grid-a { grid-template-columns: minmax(0, 1.55fr) minmax(280px, .85fr); }
.grid-b { grid-template-columns: minmax(0, 1.45fr) minmax(280px, .9fr); }
.grid-c,
.grid-d { grid-template-columns: repeat(3, minmax(0, 1fr)); }

.panel {
  padding: 14px 15px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px);
}

.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.panel-head h2 {
  margin: 0 0 2px;
  color: var(--text);
  font-size: 1.05rem;
  font-weight: 700;
}

.panel-head p {
  margin: 0;
  color: var(--text-muted);
  font-size: .84rem;
  font-weight: 500;
}

.link {
  color: var(--brand);
  font-size: .88rem;
  font-weight: 700;
  white-space: nowrap;
}

.chart-box { padding-top: 2px; }

.shortcut-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.shortcut {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 10px;
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-size: .9rem;
  font-weight: 650;
  transition: .15s ease;
}

.shortcut:hover {
  border-color: var(--brand);
  background: var(--brand-soft);
}

.shortcut i {
  color: var(--brand);
  font-size: .8rem;
}

.list { display: grid; }
.list-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 46px;
  border-bottom: 1px solid var(--border);
}
.list-row:last-child { border-bottom: 0; }
.list-row.stacked { align-items: flex-start; padding: 8px 0; }
.list-row.stacked > div {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.list-row strong {
  overflow: hidden;
  color: var(--text);
  font-size: .92rem;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.list-row small {
  overflow: hidden;
  color: var(--text-muted);
  font-size: .8rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.list-row > span {
  color: var(--text-muted);
  font-size: .8rem;
  font-weight: 600;
  white-space: nowrap;
}

.rank {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  flex: 0 0 22px;
  border-radius: 6px;
  background: var(--surface-subtle);
  color: var(--text-muted);
  font-size: .68rem;
  font-weight: 700;
}

.empty.compact { min-height: 110px; }

@media (max-width: 1280px) {
  .pulse-row { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .grid-c, .grid-d { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 980px) {
  .kpi-row,
  .grid-a,
  .grid-b { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 720px) {
  .kpi-row,
  .pulse-row,
  .grid-a,
  .grid-b,
  .grid-c,
  .grid-d,
  .shortcut-grid { grid-template-columns: 1fr; }
}
</style>
