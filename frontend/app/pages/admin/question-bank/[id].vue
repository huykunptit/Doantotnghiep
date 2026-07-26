<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface AnswerForm {
  content: string
  is_correct: boolean
}
interface QuestionItem {
  id: number
  content: string
  type: string
  difficulty?: number
  default_score?: number
  explanation?: string | null
  answers?: Array<{ id?: number, content: string, is_correct: boolean }>
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()

const bankId = computed(() => Number(route.params.id))
const courseId = ref<number | null>(Number(route.query.courseId) || null)

const loading = ref(true)
const saving = ref(false)
const bankName = ref('')
const questions = ref<QuestionItem[]>([])
const tableSearch = ref('')

const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const editing = ref<QuestionItem | null>(null)

const form = reactive({
  content: '',
  type: 'multiple_choice',
  difficulty: 1,
  default_score: 1,
  explanation: '',
  answers: [
    { content: '', is_correct: true },
    { content: '', is_correct: false },
  ] as AnswerForm[],
})

const typeOptions = computed(() => [
  { label: t('admin.questionBank.types.multiple_choice'), value: 'multiple_choice' },
  { label: t('admin.questionBank.types.single_choice'), value: 'single_choice' },
  { label: t('admin.questionBank.types.true_false'), value: 'true_false' },
  { label: t('admin.questionBank.types.short_answer'), value: 'short_answer' },
])

const filtered = computed(() => {
  const q = tableSearch.value.trim().toLowerCase()
  if (!q) return questions.value
  return questions.value.filter(item =>
    stripHtml(item.content).toLowerCase().includes(q)
    || item.type.toLowerCase().includes(q),
  )
})

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

function typeLabel(type: string) {
  const key = `admin.questionBank.types.${type}`
  const translated = t(key)
  return translated === key ? type : translated
}

function needsChoices(type: string) {
  return ['multiple_choice', 'single_choice', 'true_false'].includes(type)
}

function resetForm() {
  form.content = ''
  form.type = 'multiple_choice'
  form.difficulty = 1
  form.default_score = 1
  form.explanation = ''
  form.answers = [
    { content: '', is_correct: true },
    { content: '', is_correct: false },
  ]
}

watch(() => form.type, (type) => {
  if (type === 'true_false') {
    form.answers = [
      { content: 'True', is_correct: true },
      { content: 'False', is_correct: false },
    ]
  }
  else if (type === 'short_answer') {
    form.answers = [{ content: '', is_correct: true }]
  }
  else if (form.answers.length < 2) {
    form.answers = [
      { content: '', is_correct: true },
      { content: '', is_correct: false },
    ]
  }
})

async function resolveCourseId() {
  if (courseId.value) return courseId.value
  if (!bankId.value) return null
  try {
    const res = await useApi<any>('/admin/question-banks', { query: { per_page: 200 } })
    const rows = Array.isArray(res) ? res : (res.data || [])
    const bank = rows.find((b: any) => Number(b.id) === bankId.value)
    const resolved = Number(bank?.course_id || bank?.course?.id || 0) || null
    courseId.value = resolved
    return resolved
  }
  catch {
    return null
  }
}

async function load() {
  const resolvedCourseId = await resolveCourseId()
  if (!resolvedCourseId || !bankId.value) {
    toast.add({ severity: 'error', summary: t('admin.questionBank.missingCourse'), life: 3500 })
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await useApi<{
      name?: string
      questions?: QuestionItem[]
      groups?: Array<{ questions?: QuestionItem[] }>
    }>(`/courses/${resolvedCourseId}/question-banks/${bankId.value}`)

    bankName.value = res.name || `#${bankId.value}`
    const fromBank = res.questions || []
    const fromGroups = (res.groups || []).flatMap(g => g.questions || [])
    const map = new Map<number, QuestionItem>()
    for (const q of [...fromBank, ...fromGroups]) map.set(q.id, q)
    questions.value = [...map.values()]
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.questionBank.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  modalMode.value = 'create'
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(question: QuestionItem) {
  modalMode.value = 'edit'
  editing.value = question
  form.content = question.content
  form.type = question.type
  form.difficulty = question.difficulty || 1
  form.default_score = Number(question.default_score || 1)
  form.explanation = question.explanation || ''
  form.answers = (question.answers || []).map(a => ({
    content: a.content,
    is_correct: !!a.is_correct,
  }))
  if (!form.answers.length) resetForm()
  modalOpen.value = true
}

function addAnswer() {
  form.answers.push({ content: '', is_correct: false })
}

function removeAnswer(index: number) {
  if (form.answers.length <= 1) return
  form.answers.splice(index, 1)
}

async function saveQuestion() {
  if (!form.content.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.questionBank.contentRequired'), life: 2500 })
    return
  }

  const answers = needsChoices(form.type) || form.type === 'short_answer'
    ? form.answers
      .filter(a => a.content.trim())
      .map((a, i) => ({
        content: a.content.trim(),
        is_correct: !!a.is_correct,
        sort_order: i,
      }))
    : []

  if (needsChoices(form.type) && answers.length < 2) {
    toast.add({ severity: 'warn', summary: t('admin.questionBank.answersRequired'), life: 2500 })
    return
  }
  if (needsChoices(form.type) && !answers.some(a => a.is_correct)) {
    toast.add({ severity: 'warn', summary: t('admin.questionBank.correctRequired'), life: 2500 })
    return
  }

  saving.value = true
  try {
    const body = {
      content: form.content.trim(),
      type: form.type,
      difficulty: form.difficulty,
      default_score: form.default_score,
      explanation: form.explanation || null,
      answers,
    }

    if (modalMode.value === 'create') {
      await useApi(`/courses/${courseId.value}/question-banks/${bankId.value}/questions`, {
        method: 'POST',
        body,
      })
      toast.add({ severity: 'success', summary: t('admin.questionBank.questionCreated'), life: 2500 })
    }
    else if (editing.value) {
      await useApi(`/courses/${courseId.value}/question-banks/${bankId.value}/questions/${editing.value.id}`, {
        method: 'PUT',
        body,
      })
      toast.add({ severity: 'success', summary: t('admin.questionBank.questionUpdated'), life: 2500 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.questionBank.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(question: QuestionItem) {
  confirm.require({
    message: t('admin.questionBank.deleteQuestionConfirm'),
    header: t('admin.questionBank.deleteQuestionTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/courses/${courseId.value}/question-banks/${bankId.value}/questions/${question.id}`, {
          method: 'DELETE',
        })
        toast.add({ severity: 'success', summary: t('admin.questionBank.questionDeleted'), life: 2500 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.questionBank.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page qb-detail">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.assessment') }}</span>
        <h1>{{ bankName || t('admin.questionBank.detailTitle') }}</h1>
        <p>{{ t('admin.questionBank.detailSubtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button :label="t('admin.questionBank.back')" icon="pi pi-arrow-left" severity="secondary" outlined @click="navigateTo('/admin/question-bank')" />
        <Button :label="t('admin.questionBank.addQuestion')" icon="pi pi-plus" @click="openCreate" />
      </div>
    </header>

    <section class="table-panel">
      <div class="table-toolbar">
        <div class="toolbar-left">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="tableSearch" :placeholder="t('admin.questionBank.searchQuestions')" />
          </IconField>
          <strong>{{ t('admin.users.result', { n: filtered.length }) }}</strong>
        </div>
        <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
      </div>

      <DataTable
        :value="filtered"
        data-key="id"
        :loading="loading"
        paginator
        :rows="15"
        striped-rows
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column :header="t('admin.questionBank.content')" style="min-width:280px">
          <template #body="{ data }">{{ stripHtml(data.content) }}</template>
        </Column>
        <Column field="type" :header="t('admin.questionBank.qType')" style="min-width:130px">
          <template #body="{ data }">
            <span class="pill tone-info">{{ typeLabel(data.type) }}</span>
          </template>
        </Column>
        <Column field="difficulty" :header="t('admin.questionBank.difficulty')" style="min-width:90px" />
        <Column field="default_score" :header="t('admin.questionBank.score')" style="min-width:80px" />
        <Column :header="t('admin.users.actions')" style="width:8rem">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('common.noData') }}</div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="modalMode === 'create' ? t('admin.questionBank.addQuestion') : t('admin.questionBank.editQuestion')"
      :style="{ width: 'min(720px, 96vw)' }"
    >
      <div class="modal-grid">
        <label class="field full">
          <span>{{ t('admin.questionBank.content') }} *</span>
          <CommonRichTextEditor v-model="form.content" height="200px" />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.qType') }}</span>
          <Select
            v-model="form.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.difficulty') }}</span>
          <InputNumber v-model="form.difficulty" :min="1" :max="5" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.score') }}</span>
          <InputNumber v-model="form.default_score" :min="0" :max-fraction-digits="2" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.questionBank.explanation') }}</span>
          <CommonRichTextEditor v-model="form.explanation" height="160px" />
        </label>

        <div v-if="needsChoices(form.type) || form.type === 'short_answer'" class="answers full">
          <div class="answers-head">
            <strong>{{ t('admin.questionBank.answers') }}</strong>
            <Button
              v-if="form.type !== 'true_false'"
              :label="t('admin.questionBank.addAnswer')"
              icon="pi pi-plus"
              size="small"
              text
              @click="addAnswer"
            />
          </div>
          <div v-for="(answer, index) in form.answers" :key="index" class="answer-row">
            <InputText v-model="answer.content" class="w-full" :placeholder="t('admin.questionBank.answerPh')" />
            <div class="answer-flags">
              <label class="check">
                <Checkbox v-model="answer.is_correct" :binary="true" />
                <span>{{ t('admin.questionBank.correct') }}</span>
              </label>
              <Button
                v-if="form.type !== 'true_false' && form.answers.length > 1"
                icon="pi pi-trash"
                text
                rounded
                severity="danger"
                @click="removeAnswer(index)"
              />
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveQuestion" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.qb-detail { gap: 14px; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }
.page-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700;
}
.tone-info { background: #e0f2fe; color: #0369a1; }
.empty { padding: 40px; color: var(--text-muted); text-align: center; }

.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full, .full { grid-column: 1 / -1; }
.w-full { width: 100%; }

.answers { display: grid; gap: 8px; }
.answers-head { display: flex; align-items: center; justify-content: space-between; }
.answer-row {
  display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center;
  padding: 8px; border: 1px solid var(--border); border-radius: 10px;
}
.answer-flags { display: flex; align-items: center; gap: 6px; }
.check { display: flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 600; white-space: nowrap; }

@media (max-width: 720px) {
  .modal-grid, .answer-row { grid-template-columns: 1fr; }
}
</style>
