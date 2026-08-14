<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { matchesAny } from '~/utils/search'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface ExamInfo {
  id: number
  title: string
  status?: string | null
  duration?: number | null
  pass_score?: number | null
  max_attempts?: number | null
  starts_at?: string | null
  ends_at?: string | null
}
interface AttemptRow {
  id: number
  status: string
  score: number | null
  passed: boolean | null
  started_at?: string | null
  completed_at?: string | null
}
interface StudentUser {
  id: number
  name: string
  email?: string | null
  student_code?: string | null
  avatar?: string | null
}
interface ResultRow {
  user: StudentUser | null
  attempts_count: number
  best_score: number | null
  passed: boolean | null
  latest_status: string
  viewable_attempt_id: number | null
  last_activity_at?: string | null
  attempts: AttemptRow[]
}
interface ResultsResponse {
  exam: ExamInfo
  rows: ResultRow[]
  summary: { total_students: number, attempted: number, passed: number, avg_score: number | null }
}
interface DetailAnswer { id: number, content: string, is_correct: boolean }
interface DetailQuestion {
  id: number
  content: string
  type: string
  is_correct?: boolean
  answers?: DetailAnswer[]
}
interface AttemptDetail {
  attempt_id: number
  status: string
  score?: number
  passed?: boolean
  student_answers?: Record<string, any>
  questions?: DetailQuestion[]
  overall_feedback?: string
}

const route = useRoute()
const { t, locale } = useI18n()
const toast = useToast()
const examId = computed(() => Number(route.params.id))

const data = ref<ResultsResponse | null>(null)
const loading = ref(true)
const tableSearch = ref('')

const detailRow = ref<ResultRow | null>(null)
const detailAttemptId = ref<number | null>(null)
const detail = ref<AttemptDetail | null>(null)
const detailLoading = ref(false)
const detailOpen = computed({
  get: () => detailRow.value !== null,
  set: (open: boolean) => { if (!open) { detailRow.value = null; detail.value = null; detailAttemptId.value = null } },
})

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtDateTime(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value))
}

const filteredRows = computed(() => {
  const rows = data.value?.rows || []
  const q = tableSearch.value
  if (!q.trim()) return rows
  return rows.filter(r => matchesAny(q, r.user?.name, r.user?.email, r.user?.student_code))
})

function statusLabel(status: string) {
  const key = `admin.examResults.status.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}
function statusTone(status: string) {
  if (status === 'submitted') return 'tone-ok'
  if (status === 'in_progress') return 'tone-info'
  if (status === 'paused') return 'tone-warn'
  if (status === 'force_stopped') return 'tone-danger'
  return 'tone-muted'
}

async function load() {
  loading.value = true
  try {
    data.value = await useApi<ResultsResponse>(`/exams/${examId.value}/results`)
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.examResults.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function loadDetail(attemptId: number) {
  detailLoading.value = true
  detail.value = null
  try {
    detail.value = await useApi<AttemptDetail>(`/exams/${examId.value}/results/${attemptId}`)
    detailAttemptId.value = attemptId
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.examResults.detailLoadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    detailLoading.value = false
  }
}

function openDetail(row: ResultRow) {
  if (!row.viewable_attempt_id) return
  detailRow.value = row
  loadDetail(row.viewable_attempt_id)
}

function switchAttempt(attemptId: number) {
  if (attemptId === detailAttemptId.value) return
  loadDetail(attemptId)
}

function isChoiceType(type: string) {
  return ['single_choice', 'true_false', 'multiple_choice'].includes(type)
}

function isSelected(question: DetailQuestion, answerId: number): boolean {
  const submitted = detail.value?.student_answers?.[question.id]
  if (submitted === null || submitted === undefined) return false
  if (Array.isArray(submitted)) return submitted.some(v => Number(v) === Number(answerId))
  return Number(submitted) === Number(answerId)
}

function rawAnswerText(question: DetailQuestion): string {
  const submitted = detail.value?.student_answers?.[question.id]
  if (submitted === null || submitted === undefined || submitted === '') return t('admin.examResults.noAnswer')
  if (typeof submitted === 'object') return JSON.stringify(submitted)
  return String(submitted)
}

onMounted(load)
</script>

<template>
  <div class="page results-page">
    <header class="workspace-head">
      <div>
        <p class="eyebrow">{{ t('admin.quiz.results') }}</p>
        <h1>{{ data?.exam?.title || t('admin.examResults.loading') }}</h1>
        <p v-if="data?.exam">
          {{ t('admin.quiz.passScore') }}: {{ data.exam.pass_score ?? 0 }}% ·
          {{ t('admin.quiz.duration') }}: {{ data.exam.duration ?? 0 }} {{ t('admin.quiz.minutes') }}
        </p>
      </div>
      <div class="head-actions">
        <Button
          :label="t('admin.examResults.back')"
          icon="pi pi-arrow-left"
          severity="secondary"
          outlined
          @click="navigateTo('/admin/quiz')"
        />
        <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
      </div>
    </header>

    <section v-if="data" class="summary-grid">
      <div class="summary-card">
        <span>{{ t('admin.examResults.summaryTotal') }}</span>
        <strong>{{ data.summary.total_students }}</strong>
      </div>
      <div class="summary-card">
        <span>{{ t('admin.examResults.summaryAttempted') }}</span>
        <strong>{{ data.summary.attempted }}</strong>
      </div>
      <div class="summary-card">
        <span>{{ t('admin.examResults.summaryPassed') }}</span>
        <strong>{{ data.summary.passed }}</strong>
      </div>
      <div class="summary-card">
        <span>{{ t('admin.examResults.summaryAvg') }}</span>
        <strong>{{ data.summary.avg_score ?? '—' }}</strong>
      </div>
    </section>

    <section class="table-panel">
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.examResults.searchPh')" />
        </IconField>
        <strong>{{ t('admin.users.result', { n: filteredRows.length }) }}</strong>
      </div>

      <DataTable
        :value="filteredRows"
        data-key="user.id"
        :loading="loading"
        paginator
        :rows="15"
        :rows-per-page-options="[10, 15, 25]"
        striped-rows
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column :header="t('admin.examResults.colStudent')" sortable style="min-width:220px">
          <template #body="{ data: row }">
            <div class="student-cell">
              <strong>{{ row.user?.name || '—' }}</strong>
              <small>{{ row.user?.student_code || row.user?.email || '—' }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.examResults.colAttempts')" sortable style="min-width:90px">
          <template #body="{ data: row }">{{ row.attempts_count }}</template>
        </Column>
        <Column :header="t('admin.examResults.colBestScore')" sortable style="min-width:110px">
          <template #body="{ data: row }">
            <strong v-if="row.best_score !== null" :class="row.passed ? 'pass' : 'fail'">
              {{ row.best_score }}%
            </strong>
            <span v-else>—</span>
          </template>
        </Column>
        <Column :header="t('admin.examResults.colStatus')" sortable style="min-width:130px">
          <template #body="{ data: row }">
            <div class="status-cell">
              <span class="pill" :class="statusTone(row.latest_status)">{{ statusLabel(row.latest_status) }}</span>
              <Tag
                v-if="row.passed !== null"
                :value="row.passed ? t('admin.examResults.passed') : t('admin.examResults.failed')"
                :severity="row.passed ? 'success' : 'danger'"
              />
            </div>
          </template>
        </Column>
        <Column :header="t('admin.examResults.colLastActivity')" sortable style="min-width:160px">
          <template #body="{ data: row }">{{ fmtDateTime(row.last_activity_at) }}</template>
        </Column>
        <Column :header="t('admin.examResults.colActions')" style="width:8rem">
          <template #body="{ data: row }">
            <Button
              icon="pi pi-eye"
              text
              rounded
              size="small"
              :disabled="!row.viewable_attempt_id"
              :aria-label="t('admin.examResults.viewDetail')"
              :title="row.viewable_attempt_id ? t('admin.examResults.viewDetail') : t('admin.examResults.noAttemptToView')"
              @click="openDetail(row)"
            />
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('admin.examResults.empty') }}</div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="detailOpen"
      modal
      :header="t('admin.examResults.detailTitle')"
      :style="{ width: 'min(760px, 96vw)' }"
    >
      <template v-if="detailRow">
        <div class="dlg-head">
          <div>
            <p class="dlg-name">{{ detailRow.user?.name }} <small>{{ detailRow.user?.student_code || detailRow.user?.email }}</small></p>
            <div v-if="detailRow.attempts.length > 1" class="attempt-switch">
              <span>{{ t('admin.examResults.attemptLabel') }}:</span>
              <button
                v-for="(a, i) in detailRow.attempts"
                :key="a.id"
                type="button"
                class="attempt-chip"
                :class="{ active: a.id === detailAttemptId }"
                :disabled="a.status !== 'submitted' && a.status !== 'force_stopped'"
                @click="switchAttempt(a.id)"
              >
                #{{ detailRow.attempts.length - i }}
              </button>
            </div>
          </div>
          <div v-if="detail" class="dlg-score">
            <strong :class="detail.passed ? 'pass' : 'fail'">{{ detail.score ?? 0 }}%</strong>
            <Tag :value="detail.passed ? t('admin.examResults.passed') : t('admin.examResults.failed')" :severity="detail.passed ? 'success' : 'danger'" />
          </div>
        </div>

        <div v-if="detailLoading" class="empty">{{ t('admin.examResults.loading') }}</div>
        <template v-else-if="detail">
          <p v-if="detail.overall_feedback" class="overall-feedback">{{ detail.overall_feedback }}</p>

          <div v-if="detail.questions?.length" class="question-list">
            <div v-for="(q, qi) in detail.questions" :key="q.id" class="question-card">
              <p class="q-content"><strong>{{ qi + 1 }}.</strong> {{ q.content }}</p>

              <ul v-if="isChoiceType(q.type) && q.answers?.length" class="answer-list">
                <li
                  v-for="ans in q.answers"
                  :key="ans.id"
                  class="answer-item"
                  :class="{
                    correct: ans.is_correct,
                    selected: isSelected(q, ans.id),
                    wrong: isSelected(q, ans.id) && !ans.is_correct,
                  }"
                >
                  <i
                    class="pi"
                    :class="ans.is_correct ? 'pi-check-circle' : (isSelected(q, ans.id) ? 'pi-times-circle' : 'pi-circle')"
                  />
                  <span>{{ ans.content }}</span>
                  <Tag v-if="isSelected(q, ans.id)" :value="t('admin.examResults.selectedTag')" severity="info" />
                  <Tag v-else-if="ans.is_correct" :value="t('admin.examResults.correctTag')" severity="success" />
                </li>
              </ul>

              <div v-else class="freeform-answer">
                <p><span>{{ t('admin.examResults.studentAnswer') }}:</span> {{ rawAnswerText(q) }}</p>
                <p v-if="q.answers?.some(a => a.is_correct)">
                  <span>{{ t('admin.examResults.correctAnswerLabel') }}:</span>
                  {{ q.answers.filter(a => a.is_correct).map(a => a.content).join(', ') }}
                </p>
              </div>
            </div>
          </div>
          <div v-else class="empty">{{ t('admin.examResults.noQuestions') }}</div>
        </template>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.workspace-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.eyebrow { margin: 0; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
.workspace-head h1 { margin: .2rem 0; font-size: 1.45rem; }
.workspace-head p { margin: 0; color: var(--text-muted); }
.head-actions { display: flex; flex-wrap: wrap; gap: .5rem; }

.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.summary-card {
  border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px;
  background: var(--surface); display: flex; flex-direction: column; gap: 6px;
}
.summary-card span { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); }
.summary-card strong { font-size: 1.4rem; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }

.student-cell strong { display: block; }
.student-cell small { display: block; color: var(--text-muted); font-size: .78rem; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.tone-ok { background: #dcfce7; color: #15803d; }
.tone-info { background: #e0f2fe; color: #0369a1; }
.tone-warn { background: #fef9c3; color: #a16207; }
.tone-danger { background: #fee2e2; color: #b91c1c; }
.tone-muted { background: var(--surface-hover); color: var(--text-muted); }
.status-cell { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pass { color: #15803d; }
.fail { color: #b91c1c; }

.empty { padding: 40px; color: var(--text-muted); text-align: center; }

.dlg-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.dlg-name { margin: 0 0 6px; font-weight: 700; }
.dlg-name small { color: var(--text-muted); font-weight: 500; }
.dlg-score { display: flex; align-items: center; gap: 8px; font-size: 1.1rem; }
.attempt-switch { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: .78rem; color: var(--text-muted); }
.attempt-chip {
  border: 1px solid var(--border); border-radius: 999px; padding: 2px 10px;
  background: var(--surface); color: inherit; cursor: pointer; font: inherit; font-size: .78rem;
}
.attempt-chip.active { background: var(--primary, #2563eb); color: #fff; border-color: transparent; }
.attempt-chip:disabled { opacity: .4; cursor: not-allowed; }

.overall-feedback {
  margin: 0 0 12px; padding: 10px 12px; border-radius: 10px;
  background: var(--surface-subtle); font-size: .88rem;
}

.question-list { display: flex; flex-direction: column; gap: 14px; max-height: 55vh; overflow-y: auto; }
.question-card { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.q-content { margin: 0 0 10px; }

.answer-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.answer-item {
  display: flex; align-items: center; gap: 8px; padding: 6px 10px;
  border: 1px solid var(--border); border-radius: 8px; font-size: .88rem;
}
.answer-item .pi { flex-shrink: 0; }
.answer-item span { flex: 1; }
.answer-item.correct { border-color: #86efac; background: color-mix(in srgb, #22c55e 10%, transparent); }
.answer-item.correct .pi { color: #16a34a; }
.answer-item.wrong { border-color: #fca5a5; background: color-mix(in srgb, #ef4444 8%, transparent); }
.answer-item.wrong .pi { color: #dc2626; }
.answer-item.selected:not(.wrong):not(.correct) { border-color: #93c5fd; }

.freeform-answer { display: flex; flex-direction: column; gap: 4px; font-size: .88rem; }
.freeform-answer span { color: var(--text-muted); font-weight: 600; }
</style>
