<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CourseRow {
  id: number
  title: string
  status: string
  enrollments_count?: number
  lessons_count?: number
  instructor?: { name?: string } | null
}

interface Paginator<T> { data: T[], total: number }

interface Stats {
  total_courses?: number
  published_courses?: number
  pending_courses?: number
  courses_by_status?: Record<string, number>
  top_courses?: { id: number, title: string, enrollments_count: number }[]
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(false)

const stats = ref<Stats>({})
const courses = ref<CourseRow[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtNumber(value = 0) {
  return value.toLocaleString(numberLocale.value)
}

function statusTone(status: string) {
  const map: Record<string, string> = {
    published: 'tone-published',
    pending_review: 'tone-pending',
    draft: 'tone-draft',
    rejected: 'tone-rejected',
    archived: 'tone-archived',
  }
  return map[status] || 'tone-neutral'
}

function statusLabel(status: string) {
  const key = `admin.dashboard.status.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

const kpis = computed(() => [
  { label: t('admin.reports.courses.totalCourses'), value: fmtNumber(stats.value.total_courses || 0) },
  { label: t('admin.reports.courses.published'), value: fmtNumber(stats.value.published_courses || 0) },
  { label: t('admin.reports.courses.pendingReview'), value: fmtNumber(stats.value.pending_courses || 0) },
])

const statusRows = computed(() => {
  const mix = stats.value.courses_by_status || {}
  return Object.entries(mix)
    .map(([status, count]) => ({ status, count: Number(count) }))
    .sort((a, b) => b.count - a.count)
})

async function load() {
  loading.value = true
  try {
    const [statsRes, coursesRes] = await Promise.all([
      useApi<Stats>('/admin/stats'),
      useApi<Paginator<CourseRow>>('/admin/courses', { query: { per_page: 100 } }),
    ])
    stats.value = statsRes
    courses.value = (coursesRes.data || []).sort((a, b) => (b.enrollments_count || 0) - (a.enrollments_count || 0))
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.reports.common.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page report-page">
    <header class="workspace-head">
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="kpi-rail">
      <article v-for="kpi in kpis" :key="kpi.label" class="kpi">
        <span>{{ kpi.label }}</span>
        <strong>{{ kpi.value }}</strong>
      </article>
    </section>

    <div class="split">
      <section class="table-panel">
        <div class="panel-head">
          <strong>{{ t('admin.reports.courses.topEnrollments') }}</strong>
        </div>
        <DataTable :value="stats.top_courses || []" :loading="loading" size="small" striped-rows>
          <Column :header="t('admin.users.stt')" style="width:3rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column field="title" :header="t('admin.orders.course')" />
          <Column field="enrollments_count" :header="t('admin.reports.courses.enrollments')" style="width:7rem" />
          <template #empty><CommonEmptyState :description="t('common.noData')" /></template>
        </DataTable>
      </section>

      <section class="table-panel">
        <div class="panel-head">
          <strong>{{ t('admin.reports.courses.statusMix') }}</strong>
        </div>
        <DataTable :value="statusRows" :loading="loading" size="small" striped-rows>
          <Column :header="t('admin.orders.paymentStatus')">
            <template #body="{ data }">
              <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
            </template>
          </Column>
          <Column field="count" :header="t('admin.reports.common.total')" style="width:6rem" />
          <template #empty><CommonEmptyState :description="t('common.noData')" /></template>
        </DataTable>
      </section>
    </div>

    <section class="table-panel">
      <div class="panel-head">
        <strong>{{ t('admin.reports.courses.courseList') }}</strong>
      </div>
      <DataTable :value="courses" :loading="loading" data-key="id" striped-rows scrollable scroll-height="400px">
        <Column :header="t('admin.users.stt')" style="width:4rem" frozen>
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="title" :header="t('admin.orders.course')" style="min-width:180px" />
        <Column :header="t('admin.reports.courses.instructor')" style="min-width:120px">
          <template #body="{ data }">{{ data.instructor?.name || '—' }}</template>
        </Column>
        <Column field="lessons_count" :header="t('admin.reports.courses.lessons')" style="width:6rem" />
        <Column field="enrollments_count" :header="t('admin.reports.courses.enrollments')" style="width:7rem" />
        <Column :header="t('admin.orders.paymentStatus')" style="width:120px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
          </template>
        </Column>
        <template #empty><CommonEmptyState :description="t('common.noData')" /></template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.report-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: flex-end; gap: 16px; }

.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.kpi-rail { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.kpi {
  display: flex; flex-direction: column; gap: 4px; padding: 14px 16px;
  border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.kpi span { color: var(--text-muted); font-size: .76rem; font-weight: 600; }
.kpi strong { font-family: var(--font-display); font-size: 1.35rem; font-weight: 700; }

.split { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.panel-head { margin-bottom: 10px; }
.panel-head strong { font-size: .92rem; }

.pill {
  display: inline-flex; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.tone-published { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef9c3; color: #a16207; }
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-rejected { background: #fee2e2; color: #b91c1c; }
.tone-archived { background: #ede9fe; color: #6d28d9; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

@media (max-width: 900px) { .split, .kpi-rail { grid-template-columns: 1fr; } }
</style>
