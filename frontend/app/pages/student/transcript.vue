<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface ExamResult {
  exam_id: number
  exam_title: string
  exam_type: string
  course?: { id: number, title: string } | null
  credit_value: number
  pass_score?: number | null
  score?: number | null
  passed?: boolean | null
  taken_at?: string | null
  exam_date?: string | null
}

interface TranscriptResponse {
  results: ExamResult[]
  summary: {
    total_exams: number
    taken: number
    passed: number
    average_score: number | null
  }
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const results = ref<ExamResult[]>([])
const summary = ref<TranscriptResponse['summary'] | null>(null)

async function load() {
  loading.value = true
  try {
    const res = await useApi<TranscriptResponse>('/me/transcript')
    results.value = res.results || []
    summary.value = res.summary || null
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.transcript.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('vi-VN')
}

function statusSeverity(row: ExamResult) {
  if (row.score === null || row.score === undefined) return 'secondary'
  return row.passed ? 'success' : 'danger'
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.transcript.title') }}</h1>
        <p>{{ t('student.transcript.subtitle') }}</p>
      </div>
    </header>

    <section v-if="summary" class="stats">
      <div class="stat">
        <span>{{ t('student.transcript.totalExams') }}</span>
        <strong>{{ summary.total_exams }}</strong>
      </div>
      <div class="stat">
        <span>{{ t('student.transcript.taken') }}</span>
        <strong>{{ summary.taken }}</strong>
      </div>
      <div class="stat">
        <span>{{ t('student.transcript.passed') }}</span>
        <strong>{{ summary.passed }}</strong>
      </div>
      <div class="stat highlight">
        <span>{{ t('student.transcript.average') }}</span>
        <strong>{{ summary.average_score ?? '—' }}</strong>
      </div>
    </section>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="!results.length" class="empty">{{ t('student.transcript.empty') }}</div>
    <DataTable v-else :value="results" responsive-layout="scroll" class="tbl">
      <Column field="exam_title" :header="t('student.transcript.exam')">
        <template #body="{ data }">
          <strong>{{ data.exam_title }}</strong>
          <small v-if="data.course" class="muted"> · {{ data.course.title }}</small>
        </template>
      </Column>
      <Column :header="t('student.transcript.date')">
        <template #body="{ data }">{{ fmtDate(data.exam_date || data.taken_at) }}</template>
      </Column>
      <Column :header="t('student.transcript.score')">
        <template #body="{ data }">
          <span class="score">{{ data.score ?? '—' }}</span>
        </template>
      </Column>
      <Column :header="t('student.transcript.result')">
        <template #body="{ data }">
          <Tag
            v-if="data.score !== null && data.score !== undefined"
            :severity="statusSeverity(data)"
            :value="data.passed ? t('student.transcript.pass') : t('student.transcript.fail')"
          />
          <Tag v-else :severity="statusSeverity(data)" :value="t('student.transcript.notTaken')" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.stat { padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.stat span { display: block; color: var(--text-muted); font-size: .8rem; font-weight: 600; }
.stat strong { font-size: 1.5rem; }
.stat.highlight { background: color-mix(in srgb, var(--brand) 10%, var(--surface)); border-color: var(--brand); }
.score { font-weight: 800; }
.muted { color: var(--text-muted); font-weight: 500; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
@media (max-width: 700px) { .stats { grid-template-columns: repeat(2, 1fr); } }
</style>
