<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import type { QsRandomRule } from '~/components/quiz/QuestionSelector.vue'

definePageMeta({ layout: 'instructor', middleware: ['auth', 'instructor', 'permission'], permission: 'manage_exams' })

interface ExamRow {
  id: number
  title: string
  status: string
  duration?: number
  pass_score?: number
  starts_at?: string | null
  ends_at?: string | null
  exam_enrollments_count?: number
  variant_count?: number
  quiz?: { id: number } | null
}

interface PrintAnswer { label: string, content: string, is_correct: boolean }
interface PrintQuestion { number: number, id: number, content: string, type: string, answers: PrintAnswer[] }

interface AdminClass {
  id: number
  code: string
  name: string
}

interface BankItem {
  id: number
  name: string
  questions_count?: number
  course_id?: number
  course?: { id: number, title: string } | null
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const exams = ref<ExamRow[]>([])
const classes = ref<AdminClass[]>([])
const showCreate = ref(false)
const enrollExamId = ref<number | null>(null)
const enrollClassId = ref<number | null>(null)
const enrolling = ref(false)
const enrollDialog = computed({
  get: () => enrollExamId.value !== null,
  set: (v: boolean) => { if (!v) enrollExamId.value = null },
})

const banks = ref<BankItem[]>([])
const loadingBanks = ref(false)
const questionIds = ref<number[]>([])
const randomRules = ref<QsRandomRule[]>([])

const form = reactive({
  title: '',
  description: '',
  status: 'scheduled',
  duration: 90,
  pass_score: 50,
  max_attempts: 1,
  starts_at: null as Date | null,
  ends_at: null as Date | null,
  administrative_class_id: null as number | null,
  proctoring_enabled: true,
  variant_count: 1,
})

const printExamId = ref<number | null>(null)
const printCode = ref('A')
const printing = ref(false)
const printDialog = computed({
  get: () => printExamId.value !== null,
  set: (v: boolean) => { if (!v) printExamId.value = null },
})
const printCodeOptions = computed(() => {
  const exam = exams.value.find(e => e.id === printExamId.value)
  const n = Math.max(1, Math.min(26, exam?.variant_count || 1))
  return Array.from({ length: n }, (_, i) => String.fromCharCode(65 + i))
})

const statusOptions = computed(() => [
  { label: t('admin.reports.examStatuses.draft'), value: 'draft' },
  { label: t('admin.reports.examStatuses.scheduled'), value: 'scheduled' },
  { label: t('admin.reports.examStatuses.active'), value: 'active' },
  { label: t('admin.reports.examStatuses.closed'), value: 'closed' },
])

function fmt(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
}

function toIso(d: Date | null) {
  return d ? d.toISOString() : null
}

async function loadExams() {
  loading.value = true
  try {
    exams.value = await useApi<ExamRow[]>('/exams/standalone')
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.exams.loadError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function loadClasses() {
  try {
    const res = await useApi<{ data?: AdminClass[] } | AdminClass[]>('/admin/academic/administrative-classes?per_page=100')
    classes.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    classes.value = []
  }
}

async function loadBanks() {
  loadingBanks.value = true
  try {
    const res = await useApi<{ banks: BankItem[] }>('/admin/question-banks')
    banks.value = res.banks || []
  }
  catch {
    banks.value = []
  }
  finally {
    loadingBanks.value = false
  }
}

function sanitizedRandomRules() {
  return randomRules.value.map(r => ({
    bank_id: r.bank_id,
    group_id: r.group_id || undefined,
    difficulty: r.difficulty || undefined,
    count: r.count,
  }))
}

async function createExam() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('instructor.exams.titleRequired'), life: 2500 })
    return
  }
  if (!questionIds.value.length && !randomRules.value.length) {
    toast.add({ severity: 'warn', summary: t('instructor.exams.questionsRequired'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const exam = await useApi<{ id: number }>('/exams/standalone', {
      method: 'POST',
      body: {
        title: form.title.trim(),
        description: form.description || null,
        status: form.status,
        duration: form.duration,
        pass_score: form.pass_score,
        max_attempts: form.max_attempts,
        starts_at: toIso(form.starts_at),
        ends_at: toIso(form.ends_at),
        proctoring_enabled: form.proctoring_enabled,
        variant_count: form.variant_count || 1,
        type: 'standalone',
      },
    })

    await useApi(`/exams/${exam.id}/quiz`, {
      method: 'POST',
      body: {
        title: form.title.trim(),
        description: form.description || null,
        time_limit: form.duration,
        pass_score: form.pass_score,
        question_ids: questionIds.value,
        settings: randomRules.value.length ? { random_rules: sanitizedRandomRules() } : null,
      },
    })

    if (form.administrative_class_id) {
      await useApi(`/exams/${exam.id}/enroll-class`, {
        method: 'POST',
        body: { administrative_class_id: form.administrative_class_id },
      })
    }

    toast.add({ severity: 'success', summary: t('instructor.exams.created'), life: 3000 })
    showCreate.value = false
    resetForm()
    await loadExams()
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.exams.saveError'), detail: e?.data?.message, life: 4000 })
  }
  finally {
    saving.value = false
  }
}

function resetForm() {
  form.title = ''
  form.description = ''
  form.status = 'scheduled'
  form.duration = 90
  form.pass_score = 50
  form.max_attempts = 1
  form.starts_at = null
  form.ends_at = null
  form.administrative_class_id = null
  form.proctoring_enabled = true
  form.variant_count = 1
  questionIds.value = []
  randomRules.value = []
}

function openPrint(exam: ExamRow) {
  printExamId.value = exam.id
  printCode.value = 'A'
}

function stripHtml(html: string) {
  return (html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

function openPrintWindow(data: { exam: { title: string, duration?: number, pass_score?: number }, code: string, questions: PrintQuestion[] }) {
  const win = window.open('', '_blank')
  if (!win) {
    toast.add({ severity: 'warn', summary: t('instructor.exams.popupBlocked'), life: 3500 })
    return
  }

  const rows = data.questions.map(q => `
    <div class="q">
      <p class="q-title"><strong>${t('instructor.exams.printQuestionLabel', { n: q.number })}</strong> ${stripHtml(q.content)}</p>
      <div class="answers">
        ${(q.answers || []).map(a => `<div class="a">${a.label}. ${stripHtml(a.content)}</div>`).join('')}
      </div>
    </div>
  `).join('')

  const answerKey = data.questions
    .map((q) => {
      const correct = (q.answers || []).find(a => a.is_correct)
      return `${q.number}.${correct ? correct.label : '-'}`
    })
    .join('&nbsp;&nbsp;')

  win.document.write(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>${data.exam.title} - ${t('instructor.exams.printCodeLabel')} ${data.code}</title>
<style>
  body { font-family: 'Times New Roman', Georgia, serif; padding: 32px 40px; color: #111; }
  h1 { text-align: center; font-size: 20px; margin: 0 0 6px; }
  .meta { text-align: center; margin: 0 0 4px; font-size: 14px; }
  .code-badge { position: fixed; top: 22px; right: 32px; border: 2px solid #111; border-radius: 6px; padding: 6px 16px; font-weight: bold; font-size: 15px; }
  hr { margin: 16px 0; border: none; border-top: 1px solid #333; }
  .q { margin: 14px 0; page-break-inside: avoid; font-size: 14px; }
  .q-title { margin: 0 0 6px; }
  .answers { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; padding-left: 18px; }
  .answer-key { margin-top: 40px; border-top: 1px dashed #999; padding-top: 12px; font-size: 13px; }
  @media print { .code-badge { position: absolute; } }
</style>
</head>
<body>
  <div class="code-badge">${t('instructor.exams.printCodeLabel')}: ${data.code}</div>
  <h1>${data.exam.title}</h1>
  <p class="meta">${t('instructor.exams.duration')}: ${data.exam.duration || '—'} ${t('instructor.exams.minutes')} &nbsp;|&nbsp; ${t('instructor.exams.passScore')}: ${data.exam.pass_score ?? '—'}</p>
  <hr>
  ${rows}
  <div class="answer-key"><strong>${t('instructor.exams.answerKey')} (${data.code}):</strong> ${answerKey}</div>
</body>
</html>`)
  win.document.close()
  win.focus()
  setTimeout(() => win.print(), 400)
}

async function generatePrint() {
  if (!printExamId.value) return
  printing.value = true
  try {
    const res = await useApi<{
      exam: { title: string, duration?: number, pass_score?: number }
      code: string
      questions: PrintQuestion[]
    }>(`/exams/${printExamId.value}/print`, { query: { code: printCode.value } })

    openPrintWindow(res)
    printExamId.value = null
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.exams.printError'), detail: e?.data?.message, life: 4000 })
  }
  finally {
    printing.value = false
  }
}

async function enrollClass() {
  if (!enrollExamId.value || !enrollClassId.value) return
  enrolling.value = true
  try {
    const res = await useApi<{ message: string }>(`/exams/${enrollExamId.value}/enroll-class`, {
      method: 'POST',
      body: { administrative_class_id: enrollClassId.value },
    })
    toast.add({ severity: 'success', summary: res.message || t('instructor.exams.enrollOk'), life: 3000 })
    enrollExamId.value = null
    enrollClassId.value = null
    await loadExams()
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.exams.enrollError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    enrolling.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadExams(), loadClasses(), loadBanks()])
})
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('instructor.console') }}</span>
        <h1>{{ t('instructor.exams.title') }}</h1>
        <p>{{ t('instructor.exams.subtitle') }}</p>
      </div>
      <Button :label="t('instructor.exams.create')" icon="pi pi-plus" @click="showCreate = true" />
    </header>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="!exams.length" class="empty">{{ t('instructor.exams.empty') }}</div>
    <DataTable v-else :value="exams" class="tbl" responsive-layout="scroll">
      <Column field="title" :header="t('instructor.exams.exam')">
        <template #body="{ data }">
          <strong>{{ data.title }}</strong>
        </template>
      </Column>
      <Column :header="t('instructor.exams.window')">
        <template #body="{ data }">{{ fmt(data.starts_at) }} → {{ fmt(data.ends_at) }}</template>
      </Column>
      <Column field="duration" :header="t('instructor.exams.duration')" />
      <Column :header="t('instructor.exams.enrolled')">
        <template #body="{ data }">{{ data.exam_enrollments_count || 0 }}</template>
      </Column>
      <Column field="status" :header="t('instructor.exams.status')" />
      <Column :header="t('instructor.exams.actions')">
        <template #body="{ data }">
          <div class="row-actions">
            <Button
              :label="t('instructor.exams.enrollClass')"
              size="small"
              severity="secondary"
              outlined
              @click="enrollExamId = data.id"
            />
            <Button
              :label="t('instructor.exams.printExam')"
              icon="pi pi-print"
              size="small"
              severity="secondary"
              outlined
              :disabled="!data.quiz"
              @click="openPrint(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog v-model:visible="showCreate" modal :header="t('instructor.exams.create')" style="width:min(920px,95vw)">
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('instructor.exams.examTitle') }} *</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('instructor.exams.description') }}</span>
          <CommonRichTextEditor v-model="form.description" height="160px" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.status') }}</span>
          <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.duration') }}</span>
          <InputNumber v-model="form.duration" :min="1" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.passScore') }}</span>
          <InputNumber v-model="form.pass_score" :min="0" :max="100" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.maxAttempts') }}</span>
          <InputNumber v-model="form.max_attempts" :min="1" :max="99" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.variantCount') }}</span>
          <InputNumber v-model="form.variant_count" :min="1" :max="26" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.startsAt') }}</span>
          <DatePicker v-model="form.starts_at" show-time hour-format="24" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.exams.endsAt') }}</span>
          <DatePicker v-model="form.ends_at" show-time hour-format="24" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('instructor.exams.adminClass') }}</span>
          <Select
            v-model="form.administrative_class_id"
            :options="classes"
            option-label="code"
            option-value="id"
            filter
            show-clear
            class="w-full"
            :placeholder="t('instructor.exams.selectClass')"
          >
            <template #option="{ option }">
              <div>{{ option.code }} — {{ option.name }}</div>
            </template>
          </Select>
        </label>
        <label class="field check">
          <Checkbox v-model="form.proctoring_enabled" :binary="true" input-id="proc" />
          <label for="proc">{{ t('instructor.exams.proctoring') }}</label>
        </label>
      </div>

      <QuizQuestionSelector
        v-model:question-ids="questionIds"
        v-model:random-rules="randomRules"
        :banks="banks"
        :loading-banks="loadingBanks"
      />

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="showCreate = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="createExam" />
      </template>
    </Dialog>

    <Dialog v-model:visible="enrollDialog" modal :header="t('instructor.exams.enrollClass')" style="width:min(420px,95vw)">
      <label class="field">
        <span>{{ t('instructor.exams.adminClass') }}</span>
        <Select
          v-model="enrollClassId"
          :options="classes"
          option-label="code"
          option-value="id"
          filter
          class="w-full"
        />
      </label>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="enrollExamId = null" />
        <Button :label="t('instructor.exams.enroll')" icon="pi pi-users" :loading="enrolling" @click="enrollClass" />
      </template>
    </Dialog>

    <Dialog v-model:visible="printDialog" modal :header="t('instructor.exams.printExam')" style="width:min(420px,95vw)">
      <label class="field">
        <span>{{ t('instructor.exams.printCodeLabel') }}</span>
        <Select v-model="printCode" :options="printCodeOptions" class="w-full" />
      </label>
      <p class="hint">{{ t('instructor.exams.printHint') }}</p>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="printExamId = null" />
        <Button :label="t('instructor.exams.printGenerate')" icon="pi pi-print" :loading="printing" @click="generatePrint" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.empty { padding: 28px; text-align: center; color: var(--text-muted); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.field.check { flex-direction: row; align-items: center; gap: 8px; grid-column: 1 / -1; }
.w-full { width: 100%; }
.row-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.hint { margin: 8px 0 0; color: var(--text-muted); font-size: .84rem; font-weight: 600; }
@media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
</style>
