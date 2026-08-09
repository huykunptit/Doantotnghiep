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
  question_group_id?: number | null
  answers?: Array<{ id?: number, content: string, is_correct: boolean }>
}
interface GroupItem {
  id: number
  name: string
  description?: string | null
  sort_order?: number
  questions_count?: number
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const route = useRoute()
const { options: difficultyOptions, difficultyLabel } = useQuestionDifficulty()

const bankId = computed(() => Number(route.params.id))
const courseId = ref<number | null>(Number(route.query.courseId) || null)

const loading = ref(true)
const saving = ref(false)
const bankName = ref('')
const questions = ref<QuestionItem[]>([])
const groups = ref<GroupItem[]>([])
const tableSearch = ref('')
const groupFilter = ref<number | null>(null)

const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const editing = ref<QuestionItem | null>(null)

const groupModalOpen = ref(false)
const groupSaving = ref(false)
const editingGroup = ref<GroupItem | null>(null)
const groupForm = reactive({
  name: '',
  description: '',
})

const form = reactive({
  content: '',
  type: 'multiple_choice',
  difficulty: 1,
  default_score: 1,
  explanation: '',
  question_group_id: null as number | null,
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

const groupOptions = computed(() => groups.value.map(g => ({ label: g.name, value: g.id })))

function groupName(groupId?: number | null) {
  if (!groupId) return t('admin.questionBank.groups.ungrouped')
  return groups.value.find(g => g.id === groupId)?.name || '—'
}

const filtered = computed(() => {
  const q = tableSearch.value.trim().toLowerCase()
  return questions.value.filter((item) => {
    if (groupFilter.value && item.question_group_id !== groupFilter.value) return false
    if (!q) return true
    return stripHtml(item.content).toLowerCase().includes(q) || item.type.toLowerCase().includes(q)
  })
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
  form.question_group_id = groupFilter.value
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
      groups?: Array<GroupItem & { questions?: QuestionItem[] }>
    }>(`/courses/${resolvedCourseId}/question-banks/${bankId.value}`)

    bankName.value = res.name || `#${bankId.value}`
    const resGroups = res.groups || []
    groups.value = resGroups.map(g => ({
      id: g.id,
      name: g.name,
      description: g.description,
      sort_order: g.sort_order,
      questions_count: g.questions?.length || 0,
    }))
    const fromBank = res.questions || []
    const fromGroups = resGroups.flatMap(g => g.questions || [])
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
  form.question_group_id = question.question_group_id || null
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
      question_group_id: form.question_group_id || null,
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

function openCreateGroup() {
  editingGroup.value = null
  groupForm.name = ''
  groupForm.description = ''
  groupModalOpen.value = true
}

function openEditGroup(group: GroupItem) {
  editingGroup.value = group
  groupForm.name = group.name
  groupForm.description = group.description || ''
  groupModalOpen.value = true
}

async function saveGroup() {
  if (!groupForm.name.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.questionBank.groups.nameRequired'), life: 2500 })
    return
  }
  groupSaving.value = true
  try {
    const body = {
      question_bank_id: bankId.value,
      name: groupForm.name.trim(),
      description: groupForm.description || null,
    }
    if (editingGroup.value) {
      await useApi(`/courses/${courseId.value}/question-groups/${editingGroup.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.questionBank.groups.updated'), life: 2500 })
    }
    else {
      await useApi(`/courses/${courseId.value}/question-groups`, { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.questionBank.groups.created'), life: 2500 })
    }
    groupModalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.questionBank.groups.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    groupSaving.value = false
  }
}

function askDeleteGroup(group: GroupItem) {
  confirm.require({
    message: t('admin.questionBank.groups.deleteConfirm', { name: group.name }),
    header: t('admin.questionBank.groups.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/courses/${courseId.value}/question-groups/${group.id}`, { method: 'DELETE' })
        if (groupFilter.value === group.id) groupFilter.value = null
        toast.add({ severity: 'success', summary: t('admin.questionBank.groups.deleted'), life: 2500 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.questionBank.groups.deleteError'),
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
      <div class="page-actions">
        <Button :label="t('admin.questionBank.back')" icon="pi pi-arrow-left" severity="secondary" outlined @click="navigateTo('/admin/question-bank')" />
        <Button :label="t('admin.questionBank.addQuestion')" icon="pi pi-plus" @click="openCreate" />
      </div>
    </header>

    <section class="table-panel groups-panel">
      <div class="table-toolbar">
        <div class="toolbar-left">
          <i class="pi pi-sitemap" />
          <strong>{{ t('admin.questionBank.groups.title') }}</strong>
          <span class="muted">({{ groups.length }})</span>
        </div>
        <Button :label="t('admin.questionBank.groups.add')" icon="pi pi-plus" size="small" severity="secondary" outlined @click="openCreateGroup" />
      </div>
      <div v-if="groups.length" class="group-chips">
        <button
          type="button"
          class="chip"
          :class="{ on: groupFilter === null }"
          @click="groupFilter = null"
        >
          {{ t('admin.questionBank.groups.all') }}
        </button>
        <div v-for="group in groups" :key="group.id" class="chip-wrap">
          <button
            type="button"
            class="chip"
            :class="{ on: groupFilter === group.id }"
            @click="groupFilter = groupFilter === group.id ? null : group.id"
          >
            {{ group.name }} <small>({{ group.questions_count || 0 }})</small>
          </button>
          <Button icon="pi pi-pencil" text rounded size="small" @click="openEditGroup(group)" />
          <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="askDeleteGroup(group)" />
        </div>
      </div>
      <div v-else class="empty small">{{ t('admin.questionBank.groups.empty') }}</div>
    </section>

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
        <Column :header="t('admin.questionBank.groups.column')" style="min-width:130px">
          <template #body="{ data }">{{ groupName(data.question_group_id) }}</template>
        </Column>
        <Column :header="t('admin.questionBank.difficulty')" style="min-width:120px">
          <template #body="{ data }">{{ difficultyLabel(data.difficulty) }}</template>
        </Column>
        <Column field="default_score" :header="t('admin.questionBank.score')" style="min-width:80px" />
        <Column :header="t('admin.users.actions')" style="width:8rem">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
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
          <Select v-model="form.difficulty" :options="difficultyOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.score') }}</span>
          <InputNumber v-model="form.default_score" :min="0" :max-fraction-digits="2" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.groups.column') }}</span>
          <Select
            v-model="form.question_group_id"
            :options="groupOptions"
            option-label="label"
            option-value="value"
            show-clear
            :placeholder="t('admin.questionBank.groups.ungrouped')"
            class="w-full"
          />
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

    <Dialog
      v-model:visible="groupModalOpen"
      modal
      :header="editingGroup ? t('admin.questionBank.groups.edit') : t('admin.questionBank.groups.add')"
      :style="{ width: 'min(480px, 96vw)' }"
    >
      <div class="modal-grid">
        <label class="field full">
          <span>{{ t('admin.questionBank.groups.name') }} *</span>
          <InputText v-model="groupForm.name" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.questionBank.groups.description') }}</span>
          <Textarea v-model="groupForm.description" rows="3" class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="groupModalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="groupSaving" @click="saveGroup" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.qb-detail { gap: 14px; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: flex-end; gap: 16px; flex-wrap: wrap;
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
.muted { color: var(--text-muted); font-size: .82rem; font-weight: 600; }

.groups-panel { margin-bottom: 4px; }
.group-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.chip-wrap { display: flex; align-items: center; gap: 2px; }
.chip {
  display: inline-flex; align-items: center; gap: 4px;
  min-height: 32px; padding: 0 14px; border: 1px solid var(--border); border-radius: 999px;
  background: var(--surface-subtle); color: var(--text-muted); font: inherit; font-size: .84rem; font-weight: 650; cursor: pointer;
}
.chip small { opacity: .75; }
.chip.on { background: var(--brand-soft); border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); color: var(--brand); }
.empty.small { padding: 10px 4px; font-size: .86rem; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700;
}
.tone-info { background: #e0f2fe; color: #0369a1; }

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
