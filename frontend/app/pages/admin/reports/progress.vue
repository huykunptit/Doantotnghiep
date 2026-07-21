<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface ProgressItem { label: string, value: number }
interface Stats {
  engagement?: {
    avg_quiz_score?: number
    total_completions?: number
    active_students_this_week?: number
  }
  top_courses?: { id: number, title: string, enrollments_count: number }[]
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(false)

const stats = ref<Stats>({})
const classProgress = ref<ProgressItem[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtNumber(value = 0) {
  return value.toLocaleString(numberLocale.value)
}

function fmtScore(value = 0) {
  return `${Math.round(value)}%`
}

const kpis = computed(() => [
  { label: t('admin.reports.progress.completions'), value: fmtNumber(stats.value.engagement?.total_completions || 0), icon: 'pi-check-circle' },
  { label: t('admin.reports.progress.activeStudents'), value: fmtNumber(stats.value.engagement?.active_students_this_week || 0), icon: 'pi-users' },
  { label: t('admin.reports.progress.avgQuizScore'), value: fmtScore(stats.value.engagement?.avg_quiz_score || 0), icon: 'pi-star' },
])

async function load() {
  loading.value = true
  try {
    const [statsRes, extraRes] = await Promise.all([
      useApi<Stats>('/admin/stats'),
      useApi<{ class_progress?: ProgressItem[] }>('/admin/dashboard-extra'),
    ])
    stats.value = statsRes
    classProgress.value = extraRes.class_progress || []
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
      <div>
        <span class="eyebrow">{{ t('admin.menu.reports') }}</span>
        <h1>{{ t('admin.reports.progress.title') }}</h1>
        <p>{{ t('admin.reports.progress.subtitle') }}</p>
      </div>
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="kpi-rail">
      <article v-for="kpi in kpis" :key="kpi.label" class="kpi">
        <i :class="['pi', kpi.icon]" />
        <div>
          <span>{{ kpi.label }}</span>
          <strong>{{ kpi.value }}</strong>
        </div>
      </article>
    </section>

    <section class="table-panel">
      <div class="panel-head">
        <strong>{{ t('admin.reports.progress.classProgress') }}</strong>
        <p>{{ t('admin.reports.progress.classProgressHint') }}</p>
      </div>
      <DataTable :value="classProgress" :loading="loading" striped-rows>
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="label" :header="t('admin.classes.code')" />
        <Column :header="t('admin.dashboard.progressPercent')" style="min-width:200px">
          <template #body="{ data }">
            <div class="bar-wrap">
              <ProgressBar :value="data.value" :show-value="false" class="bar" />
              <span>{{ data.value }}%</span>
            </div>
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('admin.dashboard.noProgress') }}</div>
        </template>
      </DataTable>
    </section>

    <section v-if="stats.top_courses?.length" class="table-panel">
      <div class="panel-head">
        <strong>{{ t('admin.dashboard.topCoursesTitle') }}</strong>
        <p>{{ t('admin.dashboard.topCoursesHint') }}</p>
      </div>
      <DataTable :value="stats.top_courses" size="small" striped-rows>
        <Column :header="t('admin.users.stt')" style="width:3rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="title" :header="t('admin.orders.course')" />
        <Column field="enrollments_count" :header="t('admin.reports.courses.enrollments')" style="width:7rem" />
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.report-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.kpi-rail { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.kpi {
  display: flex; align-items: center; gap: 12px; padding: 16px;
  border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.kpi i { font-size: 1.2rem; color: var(--brand); }
.kpi span { display: block; color: var(--text-muted); font-size: .76rem; font-weight: 600; }
.kpi strong { font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.panel-head { margin-bottom: 10px; }
.panel-head strong { font-size: .92rem; }
.panel-head p { margin: 2px 0 0; color: var(--text-muted); font-size: .82rem; }

.bar-wrap { display: flex; align-items: center; gap: 10px; }
.bar { flex: 1; height: 8px; }
.bar-wrap span { font-size: .82rem; font-weight: 700; min-width: 2.5rem; text-align: right; }
.empty { padding: 32px; text-align: center; color: var(--text-muted); }

@media (max-width: 720px) { .kpi-rail { grid-template-columns: 1fr; } }
</style>
