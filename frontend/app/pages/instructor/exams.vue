<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'instructor', middleware: ['auth', 'instructor'] })

interface ExamRow {
  id: number
  title: string
  status: string
  duration?: number
  pass_score?: number
  starts_at?: string | null
  ends_at?: string | null
  exam_enrollments_count?: number
  quiz?: { id: number } | null
}

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

interface QuestionItem {
  id: number
  content: string
  type: string
  difficulty?: number
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
const questions = ref<QuestionItem[]>([])
const selectedQuestions = ref<QuestionItem[]>([])
const loadingQuestions = ref(false)

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
  bank_id: null as number | null,
  proctoring_enabled: true,
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

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
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
  try {
    const res = await useApi<{ banks: BankItem[] }>('/admin/question-banks')
    banks.value = res.banks || []
  }
  catch {
    banks.value = []
  }
}

async function loadQuestions() {
  selectedQuestions.value = []
  questions.value = []
  if (!form.bank_id) return
  const bank = banks.value.find(b => b.id === form.bank_id)
  const courseId = bank?.course_id || bank?.course?.id
  if (!courseId) return
  loadingQuestions.value = true
  try {
    const res = await useApi<{
      questions?: QuestionItem[]
      groups?: Array<{ questions?: QuestionItem[] }>
    }>(`/courses/${courseId}/question-banks/${form.bank_id}`)
    const fromBank = res.questions || []
    const fromGroups = (res.groups || []).flatMap(g => g.questions || [])
    const map = new Map<number, QuestionItem>()
    for (const q of [...fromBank, ...fromGroups]) map.set(q.id, q)
    questions.value = [...map.values()]
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.exams.questionsError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loadingQuestions.value = false
  }
}

async function createExam() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('instructor.exams.titleRequired'), life: 2500 })
    return
  }
  if (!selectedQuestions.value.length) {
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
        question_ids: selectedQuestions.value.map(q => q.id),
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
  form.bank_id = null
  form.proctoring_enabled = true
  selectedQuestions.value = []
  questions.value = []
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

watch(() => form.bank_id, loadQuestions)

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
          <Button
            :label="t('instructor.exams.enrollClass')"
            size="small"
            severity="secondary"
            outlined
            @click="enrollExamId = data.id"
          />
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
          <Textarea v-model="form.description" rows="2" auto-resize class="w-full" />
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
        <label class="field full">
          <span>{{ t('instructor.exams.questionBank') }}</span>
          <Select
            v-model="form.bank_id"
            :options="banks"
            option-label="name"
            option-value="id"
            filter
            show-clear
            class="w-full"
          />
        </label>
        <label class="field check">
          <Checkbox v-model="form.proctoring_enabled" :binary="true" input-id="proc" />
          <label for="proc">{{ t('instructor.exams.proctoring') }}</label>
        </label>
      </div>

      <DataTable
        v-model:selection="selectedQuestions"
        :value="questions"
        data-key="id"
        :loading="loadingQuestions"
        selection-mode="multiple"
        paginator
        :rows="8"
        class="q-table"
      >
        <Column selection-mode="multiple" header-style="width:3rem" />
        <Column :header="t('instructor.exams.question')">
          <template #body="{ data }">{{ stripHtml(data.content) }}</template>
        </Column>
        <Column field="type" :header="t('instructor.exams.qType')" />
        <template #empty>
          <div class="empty">{{ form.bank_id ? t('common.noData') : t('instructor.exams.selectBankFirst') }}</div>
        </template>
      </DataTable>
      <p class="hint">{{ t('instructor.exams.selectedCount', { n: selectedQuestions.length }) }}</p>

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
.hint { margin: 8px 0 0; color: var(--text-muted); font-size: .84rem; font-weight: 600; }
@media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
</style>
