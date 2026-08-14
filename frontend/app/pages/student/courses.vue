<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface TermInfo {
  id: number
  name: string
  code?: string
  start_date?: string | null
  end_date?: string | null
  is_current?: boolean
  status?: string
  display_name?: string
}
interface EnrollmentRow {
  id: number
  progress?: number
  enrolled_at?: string | null
  enrollment_source?: string | null
  starts_at?: string | null
  ends_at?: string | null
  window_status?: StatusKey | null
  is_planned?: boolean
  term?: TermInfo | null
  course?: { id: number, title: string, thumbnail?: string | null, category?: { name?: string } | null, instructor?: { name?: string } | null } | null
}

type StatusKey = 'current' | 'upcoming' | 'expired'
type TabKey = StatusKey | 'all'

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(true)
const rows = ref<EnrollmentRow[]>([])
const tab = ref<TabKey>('current')

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

async function load() {
  loading.value = true
  try {
    rows.value = await useApi<EnrollmentRow[]>('/enrollments')
    if (!buckets.value.current.length && buckets.value.upcoming.length) {
      tab.value = 'upcoming'
    }
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.courses.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

/** Parse YYYY-MM-DD as local calendar day to avoid UTC shift. */
function parseDay(value?: string | null): Date | null {
  if (!value) return null
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) {
    const d = new Date(value)
    return Number.isNaN(d.getTime()) ? null : d
  }
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

function startOf(item: EnrollmentRow) {
  return item.starts_at || item.term?.start_date || null
}

function endOf(item: EnrollmentRow) {
  return item.ends_at || item.term?.end_date || null
}

function statusOf(item: EnrollmentRow): StatusKey {
  if (item.window_status === 'current' || item.window_status === 'upcoming' || item.window_status === 'expired') {
    return item.window_status
  }
  const start = parseDay(startOf(item))
  const end = parseDay(endOf(item))
  if (!start && !end) return 'current'
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  if (end && end < today) return 'expired'
  if (start && start > today) return 'upcoming'
  return 'current'
}

function timeValue(d?: string | null) {
  return parseDay(d)?.getTime() ?? null
}

const enriched = computed(() => rows.value.map(item => ({ item, status: statusOf(item) })))

const buckets = computed(() => {
  const current = enriched.value.filter(x => x.status === 'current')
  const upcoming = enriched.value.filter(x => x.status === 'upcoming')
  const expired = enriched.value.filter(x => x.status === 'expired')

  current.sort((a, b) => {
    const ea = timeValue(endOf(a.item)) ?? Number.POSITIVE_INFINITY
    const eb = timeValue(endOf(b.item)) ?? Number.POSITIVE_INFINITY
    if (ea !== eb) return ea - eb
    return (timeValue(b.item.enrolled_at) ?? 0) - (timeValue(a.item.enrolled_at) ?? 0)
  })
  upcoming.sort((a, b) => (timeValue(startOf(a.item)) ?? Number.POSITIVE_INFINITY) - (timeValue(startOf(b.item)) ?? Number.POSITIVE_INFINITY))
  expired.sort((a, b) => (timeValue(endOf(b.item)) ?? 0) - (timeValue(endOf(a.item)) ?? 0))

  return { current, upcoming, expired }
})

const tabs = computed(() => [
  { key: 'current' as const, label: t('student.courses.tabCurrent'), count: buckets.value.current.length },
  { key: 'upcoming' as const, label: t('student.courses.tabUpcoming'), count: buckets.value.upcoming.length },
  { key: 'expired' as const, label: t('student.courses.tabExpired'), count: buckets.value.expired.length },
  { key: 'all' as const, label: t('student.courses.tabAll'), count: rows.value.length },
])

const visible = computed(() => {
  if (tab.value === 'all') {
    return [...buckets.value.current, ...buckets.value.upcoming, ...buckets.value.expired]
  }
  return buckets.value[tab.value]
})

const emptyDescription = computed(() => {
  if (!rows.value.length) return t('student.courses.empty')
  if (tab.value === 'current') return t('student.courses.emptyCurrent')
  if (tab.value === 'upcoming') return t('student.courses.emptyUpcoming')
  if (tab.value === 'expired') return t('student.courses.emptyExpired')
  return t('student.courses.emptyFilter')
})

function badgeLabel(status: StatusKey) {
  if (status === 'upcoming') return t('student.courses.badgeUpcoming')
  if (status === 'expired') return t('student.courses.badgeExpired')
  return t('student.courses.badgeCurrent')
}

function badgeTone(status: StatusKey) {
  if (status === 'upcoming') return 'tone-upcoming'
  if (status === 'expired') return 'tone-expired'
  return 'tone-current'
}

function formatDate(d?: string | null) {
  const day = parseDay(d)
  if (!day) return '—'
  return new Intl.DateTimeFormat(dateLocale.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(day)
}

function termLabel(item: EnrollmentRow) {
  return item.term?.display_name || item.term?.name || ''
}

function sourceLabel(item: EnrollmentRow) {
  return item.enrollment_source && item.enrollment_source !== 'marketplace'
    ? t('student.courses.sourceAcademic')
    : t('student.courses.sourceMarket')
}

function ctaLabel(item: EnrollmentRow, status: StatusKey) {
  if (status === 'upcoming' || item.is_planned) return t('student.courses.ctaUpcoming')
  if (status === 'expired' || (item.progress || 0) >= 100) return t('student.courses.ctaReview')
  if ((item.progress || 0) > 0) return t('student.courses.ctaContinue')
  return t('student.courses.ctaStart')
}

function openCourse(item: EnrollmentRow, status: StatusKey) {
  if (!item.course) return
  if (status === 'upcoming' || item.is_planned) {
    navigateTo(`/courses/${item.course.id}`)
    return
  }
  navigateTo(`/learn/${item.course.id}`)
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <h1>{{ t('student.courses.title') }}</h1>
        <p>{{ t('student.courses.subtitle') }}</p>
      </div>
      <Button :label="t('student.dashboard.browse')" icon="pi pi-shop" @click="navigateTo('/courses')" />
    </header>

    <div class="tab-bar">
      <button
        v-for="tb in tabs"
        :key="tb.key"
        type="button"
        class="tab"
        :class="{ active: tab === tb.key }"
        @click="tab = tb.key"
      >
        {{ tb.label }} <small>{{ tb.count }}</small>
      </button>
    </div>

    <section class="grid" :aria-busy="loading">
      <div v-if="loading" class="skeleton-list">
        <div v-for="i in 4" :key="i" class="skeleton-card" />
      </div>
      <template v-else>
        <div v-for="{ item, status } in visible" :key="item.id" class="card">
          <img v-if="item.course?.thumbnail" :src="resolveMediaUrl(item.course.thumbnail)" alt="">
          <div v-else class="thumb-ph"><i class="pi pi-book" /></div>
          <div class="body">
            <div class="body-head">
              <strong>{{ item.course?.title }}</strong>
              <span class="badge" :class="badgeTone(status)">{{ badgeLabel(status) }}</span>
            </div>
            <span class="muted">{{ item.course?.category?.name || item.course?.instructor?.name || t('student.catalog.instructor') }}</span>
            <div class="term-row">
              <span class="tag">{{ sourceLabel(item) }}</span>
              <span v-if="termLabel(item)" class="term-name">{{ termLabel(item) }}</span>
            </div>
            <div v-if="startOf(item) || endOf(item)" class="dates">
              <span><i class="pi pi-calendar" /> {{ t('student.courses.dateStart') }}: <b>{{ formatDate(startOf(item)) }}</b></span>
              <span><i class="pi pi-clock" /> {{ t('student.courses.dateEnd') }}: <b>{{ formatDate(endOf(item)) }}</b></span>
            </div>
            <span v-else class="term-dates muted">{{ t('student.courses.ongoing') }}</span>
            <ProgressBar :value="item.progress || 0" :show-value="false" style="height:8px;margin-top:8px" />
            <small class="muted">{{ t('student.dashboard.progress', { n: item.progress || 0 }) }}</small>
          </div>
          <Button
            :label="ctaLabel(item, status)"
            size="small"
            :severity="status === 'upcoming' ? 'secondary' : undefined"
            :outlined="status === 'upcoming'"
            @click="openCourse(item, status)"
          />
        </div>
        <CommonEmptyState v-if="!visible.length" :description="emptyDescription" />
      </template>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.workspace-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: 1.4rem; }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; font-size: .9rem; }

.tab-bar { display: flex; gap: 6px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 2px; }
.tab {
  padding: 9px 14px; border: 0; border-bottom: 2px solid transparent; background: transparent;
  color: var(--text-muted); font: inherit; font-size: .88rem; font-weight: 650; cursor: pointer;
  display: flex; align-items: center; gap: 6px;
}
.tab small { color: var(--text-muted); font-weight: 600; background: var(--surface-subtle); padding: 1px 7px; border-radius: 999px; font-size: .74rem; }
.tab:hover { color: var(--text); }
.tab.active { color: var(--brand); border-bottom-color: var(--brand); }
.tab.active small { background: color-mix(in srgb, var(--brand) 18%, transparent); color: var(--brand); }

.grid { display: grid; gap: 10px; }
.card {
  display: grid; grid-template-columns: 72px 1fr auto; gap: 14px; align-items: center;
  padding: 12px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); color: var(--text);
}
.card img, .thumb-ph { width: 72px; height: 72px; object-fit: cover; border-radius: 12px; }
.thumb-ph { display: grid; place-items: center; background: var(--surface-subtle); color: var(--text-muted); font-size: 1.3rem; }
.body { min-width: 0; display: grid; gap: 3px; }
.body-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.body-head strong { display: block; }
.muted { color: var(--text-muted); font-size: .85rem; font-weight: 500; }
.term-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 2px; font-size: .82rem; }
.tag {
  padding: 2px 8px; border-radius: 999px; background: var(--surface-subtle); color: var(--text-muted);
  font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
}
.term-name { font-weight: 650; color: var(--text); }
.term-dates { color: var(--text-muted); font-weight: 500; }
.dates {
  display: flex; flex-wrap: wrap; gap: 10px 16px; margin-top: 4px;
  color: var(--text); font-size: .82rem; font-weight: 500;
}
.dates i { margin-right: 4px; color: var(--text-muted); font-size: .78rem; }
.dates b { font-weight: 700; }
.badge { padding: 2px 9px; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
.tone-current { background: #dcfce7; color: #15803d; }
.tone-upcoming { background: #fef3c7; color: #a16207; }
.tone-expired { background: #e2e8f0; color: #475569; }

.skeleton-list { display: grid; gap: 10px; }
.skeleton-card {
  height: 96px; border-radius: 14px; border: 1px solid var(--border);
  background: linear-gradient(90deg, var(--surface-subtle) 25%, var(--surface-hover, #eef2f7) 37%, var(--surface-subtle) 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
}
@keyframes shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

@media (max-width: 700px) { .card { grid-template-columns: 56px 1fr; } .card > :last-child { grid-column: 1 / -1; } }
</style>
