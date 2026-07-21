<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CourseItem { id: number, title: string }
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
  default_score?: number
}

const { t } = useI18n()
const toast = useToast()
const route = useRoute()

const initType = route.query.type === 'course_final' ? 'course_final' : 'standalone'
const saving = ref(false)
const loadingBanks = ref(false)
const loadingQuestions = ref(false)
const courses = ref<CourseItem[]>([])
const banks = ref<BankItem[]>([])
const questions = ref<QuestionItem[]>([])
const selectedQuestions = ref<QuestionItem[]>([])

const form = reactive({
  type: initType as 'standalone' | 'course_final',
  title: '',
  description: '',
  course_id: null as number | null,
  status: 'draft',
  duration: 60,
  pass_score: 70,
  max_attempts: 1,
  bank_id: null as number | null,
})

const typeOptions = computed(() => [
  { label: t('admin.quiz.standalone'), value: 'standalone' },
  { label: t('admin.quiz.courseExams'), value: 'course_final' },
])

const statusOptions = computed(() => [
  { label: t('admin.reports.examStatuses.draft'), value: 'draft' },
  { label: t('admin.reports.examStatuses.scheduled'), value: 'scheduled' },
  { label: t('admin.reports.examStatuses.active'), value: 'active' },
  { label: t('admin.reports.examStatuses.closed'), value: 'closed' },
])

const filteredBanks = computed(() => {
  if (form.type === 'course_final' && form.course_id) {
    return banks.value.filter(b => (b.course_id || b.course?.id) === form.course_id)
  }
  return banks.value
})

async function loadCourses() {
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100')
    courses.value = res.data || []
  }
  catch {
    courses.value = []
  }
}

async function loadBanks() {
  loadingBanks.value = true
  try {
    const res = await useApi<{ banks: BankItem[] }>('/admin/question-banks')
    banks.value = res.banks || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.quiz.banksError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loadingBanks.value = false
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
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.quiz.questionsError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loadingQuestions.value = false
  }
}

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

async function save() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.quiz.titleRequired'), life: 2500 })
    return
  }
  if (form.type === 'course_final' && !form.course_id) {
    toast.add({ severity: 'warn', summary: t('admin.quiz.courseRequired'), life: 2500 })
    return
  }

  saving.value = true
  try {
    const body = {
      title: form.title.trim(),
      description: form.description || null,
      status: form.status,
      duration: form.duration,
      pass_score: form.pass_score,
      max_attempts: form.max_attempts,
      type: form.type,
    }

    let exam: { id: number, course_id?: number | null }
    if (form.type === 'standalone') {
      exam = await useApi('/exams/standalone', { method: 'POST', body })
    }
    else {
      exam = await useApi(`/courses/${form.course_id}/exams`, { method: 'POST', body })
    }

    if (selectedQuestions.value.length) {
      const quizBody = {
        title: form.title.trim(),
        description: form.description || null,
        time_limit: form.duration,
        pass_score: form.pass_score,
        question_ids: selectedQuestions.value.map(q => q.id),
      }
      if (form.type === 'standalone') {
        await useApi(`/exams/${exam.id}/quiz`, { method: 'POST', body: quizBody })
      }
      else {
        await useApi(`/courses/${form.course_id}/exams/${exam.id}/quiz`, {
          method: 'POST',
          body: quizBody,
        })
      }
    }

    toast.add({ severity: 'success', summary: t('admin.quiz.created'), life: 2500 })
    await navigateTo('/admin/quiz')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.quiz.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

watch(() => form.bank_id, loadQuestions)
watch(() => form.type, () => {
  form.bank_id = null
  if (form.type === 'standalone') form.course_id = null
})

onMounted(async () => {
  await Promise.all([loadCourses(), loadBanks()])
})
</script>

<template>
  <div class="page create-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.assessment') }}</span>
        <h1>{{ t('admin.quiz.createTitle') }}</h1>
        <p>{{ t('admin.quiz.createSubtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button :label="t('common.cancel')" severity="secondary" outlined @click="navigateTo('/admin/quiz')" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </div>
    </header>

    <section class="panel">
      <h2>{{ t('admin.quiz.basicSettings') }}</h2>
      <div class="form-grid">
        <label class="field">
          <span>{{ t('admin.quiz.scope') }}</span>
          <Select
            v-model="form.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label v-if="form.type === 'course_final'" class="field">
          <span>{{ t('admin.quiz.course') }}</span>
          <Select
            v-model="form.course_id"
            :options="courses"
            option-label="title"
            option-value="id"
            filter
            class="w-full"
          />
        </label>
        <label class="field full">
          <span>{{ t('admin.quiz.examTitle') }} *</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.quiz.description') }}</span>
          <Textarea v-model="form.description" rows="3" auto-resize class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.quiz.status') }}</span>
          <Select
            v-model="form.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.quiz.duration') }}</span>
          <InputNumber v-model="form.duration" :min="0" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.quiz.passScore') }}</span>
          <InputNumber v-model="form.pass_score" :min="0" :max="100" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.quiz.maxAttempts') }}</span>
          <InputNumber v-model="form.max_attempts" :min="1" :max="99" class="w-full" />
        </label>
      </div>
    </section>

    <section class="panel">
      <h2>{{ t('admin.quiz.attachQuestions') }}</h2>
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('admin.quiz.questionBank') }}</span>
          <Select
            v-model="form.bank_id"
            :options="filteredBanks"
            option-label="name"
            option-value="id"
            filter
            show-clear
            :loading="loadingBanks"
            :placeholder="t('admin.quiz.selectBank')"
            class="w-full"
          >
            <template #option="{ option }">
              <div class="bank-opt">
                <strong>{{ option.name }}</strong>
                <small>{{ option.course?.title || '' }} · {{ option.questions_count || 0 }}</small>
              </div>
            </template>
          </Select>
        </label>
      </div>

      <DataTable
        v-model:selection="selectedQuestions"
        :value="questions"
        data-key="id"
        :loading="loadingQuestions"
        selection-mode="multiple"
        paginator
        :rows="10"
        striped-rows
        class="q-table"
      >
        <Column selection-mode="multiple" header-style="width:3rem" />
        <Column :header="t('admin.quiz.question')" style="min-width:280px">
          <template #body="{ data }">{{ stripHtml(data.content) }}</template>
        </Column>
        <Column field="type" :header="t('admin.quiz.qType')" style="min-width:120px" />
        <Column field="difficulty" :header="t('admin.quiz.difficulty')" style="min-width:90px" />
        <template #empty>
          <div class="empty">{{ form.bank_id ? t('common.noData') : t('admin.quiz.selectBankFirst') }}</div>
        </template>
      </DataTable>
      <p class="hint">{{ t('admin.quiz.selectedCount', { n: selectedQuestions.length }) }}</p>
    </section>
  </div>
</template>

<style scoped>
.create-page { gap: 14px; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }
.page-actions { display: flex; gap: 8px; }

.panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 16px;
}
.panel h2 { margin: 0 0 12px; font-size: 1.05rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.bank-opt { display: grid; gap: 2px; }
.bank-opt small { color: var(--text-muted); font-size: .74rem; }
.empty { padding: 28px; color: var(--text-muted); text-align: center; }
.hint { margin: 10px 0 0; color: var(--text-muted); font-size: .84rem; font-weight: 600; }

@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
