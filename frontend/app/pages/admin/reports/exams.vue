<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface ExamRow {
  id: number
  title: string
  status: string
  duration?: number | null
  starts_at?: string | null
  ends_at?: string | null
  exam_enrollments_count?: number
  creator?: { name?: string } | null
  quiz?: { id: number } | null
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(false)
const exams = ref<ExamRow[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

function statusTone(status: string) {
  const map: Record<string, string> = {
    draft: 'tone-draft',
    scheduled: 'tone-scheduled',
    active: 'tone-active',
    closed: 'tone-closed',
    archived: 'tone-archived',
  }
  return map[status] || 'tone-neutral'
}

function statusLabel(status: string) {
  const key = `admin.reports.examStatuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

const kpis = computed(() => {
  const list = exams.value
  return [
    { label: t('admin.reports.exams.totalExams'), value: list.length },
    { label: t('admin.reports.exams.active'), value: list.filter(e => e.status === 'active').length },
    { label: t('admin.reports.exams.scheduled'), value: list.filter(e => e.status === 'scheduled').length },
  ]
})

async function load() {
  loading.value = true
  try {
    exams.value = await useApi<ExamRow[]>('/exams/standalone')
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

    <section class="table-panel">
      <DataTable :value="exams" :loading="loading" data-key="id" striped-rows>
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="title" :header="t('admin.reports.exams.examTitle')" style="min-width:180px" />
        <Column :header="t('admin.orders.paymentStatus')" style="width:110px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.reports.exams.enrolled')" style="width:90px">
          <template #body="{ data }">{{ data.exam_enrollments_count ?? 0 }}</template>
        </Column>
        <Column :header="t('admin.reports.exams.duration')" style="width:90px">
          <template #body="{ data }">{{ data.duration ? `${data.duration}′` : '—' }}</template>
        </Column>
        <Column :header="t('admin.reports.exams.schedule')" style="min-width:160px">
          <template #body="{ data }">
            <small>{{ fmtDate(data.starts_at) }} → {{ fmtDate(data.ends_at) }}</small>
          </template>
        </Column>
        <Column :header="t('admin.reports.exams.creator')" style="min-width:120px">
          <template #body="{ data }">{{ data.creator?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.reports.exams.quiz')" style="width:90px">
          <template #body="{ data }">
            <Tag v-if="data.quiz" :value="t('admin.reports.exams.hasQuiz')" severity="success" />
            <Tag v-else :value="t('admin.reports.exams.noQuiz')" severity="warn" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
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

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}

.pill {
  display: inline-flex; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-scheduled { background: #dbeafe; color: #1d4ed8; }
.tone-active { background: #dcfce7; color: #15803d; }
.tone-closed { background: #fee2e2; color: #b91c1c; }
.tone-archived { background: #ede9fe; color: #6d28d9; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

@media (max-width: 720px) { .kpi-rail { grid-template-columns: 1fr; } }
</style>
