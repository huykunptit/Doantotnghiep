<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import type { QsRandomRule } from '~/components/quiz/QuestionSelector.vue'

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

const { t } = useI18n()
const toast = useToast()
const route = useRoute()

const initType = route.query.type === 'course_final' ? 'course_final' : 'standalone'
const saving = ref(false)
const loadingBanks = ref(false)
const courses = ref<CourseItem[]>([])
const banks = ref<BankItem[]>([])
const questionIds = ref<number[]>([])
const randomRules = ref<QsRandomRule[]>([])

const form = reactive({
  type: initType as 'standalone' | 'course_final',
  title: '',
  description: '',
  course_id: null as number | null,
  status: 'draft',
  duration: 60,
  pass_score: 70,
  max_attempts: 1,
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

function sanitizedRandomRules() {
  return randomRules.value.map(r => ({
    bank_id: r.bank_id,
    group_id: r.group_id || undefined,
    difficulty: r.difficulty || undefined,
    count: r.count,
  }))
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
  if (!questionIds.value.length && !randomRules.value.length) {
    toast.add({ severity: 'warn', summary: t('admin.quiz.questionsRequired'), life: 2800 })
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

    const quizBody = {
      title: form.title.trim(),
      description: form.description || null,
      time_limit: form.duration,
      pass_score: form.pass_score,
      question_ids: questionIds.value,
      settings: randomRules.value.length ? { random_rules: sanitizedRandomRules() } : null,
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

watch(() => form.type, () => {
  questionIds.value = []
  randomRules.value = []
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
          <CommonRichTextEditor v-model="form.description" height="180px" />
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
      <QuizQuestionSelector
        v-model:question-ids="questionIds"
        v-model:random-rules="randomRules"
        :banks="banks"
        :course-id="form.type === 'course_final' ? form.course_id : null"
        :loading-banks="loadingBanks"
      />
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

@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
