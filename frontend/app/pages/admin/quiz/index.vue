<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CourseItem { id: number, title: string }
interface ExamItem {
  id: number
  title: string
  description?: string | null
  type?: string | null
  duration?: number | null
  pass_score?: number | null
  max_attempts?: number | null
  status?: string | null
  starts_at?: string | null
  ends_at?: string | null
  exam_enrollments_count?: number
  quiz?: { id?: number } | null
  course_id?: number | null
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const activeScope = ref<'standalone' | 'course'>('standalone')
const courses = ref<CourseItem[]>([])
const exams = ref<ExamItem[]>([])
const selectedCourseId = ref<number | null>(null)
const loadingCourses = ref(false)
const loading = ref(false)
const tableSearch = ref('')
const statusFilter = ref<string | null>(null)

const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const editing = ref<ExamItem | null>(null)
const saving = ref(false)

const form = reactive({
  title: '',
  description: '',
  status: 'draft',
  duration: 60,
  pass_score: 70,
  max_attempts: 1,
  shuffle_questions: false,
  shuffle_answers: false,
  course_id: null as number | null,
})

const statusOptions = computed(() => [
  { label: t('admin.reports.examStatuses.draft'), value: 'draft' },
  { label: t('admin.reports.examStatuses.scheduled'), value: 'scheduled' },
  { label: t('admin.reports.examStatuses.active'), value: 'active' },
  { label: t('admin.reports.examStatuses.closed'), value: 'closed' },
  { label: t('admin.reports.examStatuses.archived'), value: 'archived' },
])

const scopeOptions = computed(() => [
  { label: t('admin.quiz.standalone'), value: 'standalone' },
  { label: t('admin.quiz.courseExams'), value: 'course' },
])

const filtered = computed(() => {
  const q = tableSearch.value.trim().toLowerCase()
  return exams.value.filter((exam) => {
    if (statusFilter.value && (exam.status || 'draft') !== statusFilter.value) return false
    if (!q) return true
    return exam.title.toLowerCase().includes(q)
      || (exam.description || '').toLowerCase().includes(q)
  })
})

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

function statusLabel(status?: string | null) {
  const key = `admin.reports.examStatuses.${status || 'draft'}`
  const translated = t(key)
  return translated === key ? (status || 'draft') : translated
}

function statusTone(status?: string | null) {
  if (status === 'active') return 'tone-ok'
  if (status === 'scheduled') return 'tone-info'
  if (status === 'closed') return 'tone-danger'
  if (status === 'archived') return 'tone-muted'
  return 'tone-warn'
}

async function loadCourses() {
  loadingCourses.value = true
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100')
    courses.value = res.data || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.quiz.coursesError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loadingCourses.value = false
  }
}

async function loadExams() {
  loading.value = true
  try {
    if (activeScope.value === 'standalone') {
      exams.value = await useApi<ExamItem[]>('/exams/standalone')
    }
    else if (selectedCourseId.value) {
      exams.value = await useApi<ExamItem[]>(`/courses/${selectedCourseId.value}/exams`)
    }
    else {
      exams.value = []
    }
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.quiz.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function onScopeChange(value: 'standalone' | 'course') {
  activeScope.value = value
  if (value === 'standalone') loadExams()
  else if (selectedCourseId.value) loadExams()
  else exams.value = []
}

function resetForm() {
  form.title = ''
  form.description = ''
  form.status = 'draft'
  form.duration = 60
  form.pass_score = 70
  form.max_attempts = 1
  form.shuffle_questions = false
  form.shuffle_answers = false
  form.course_id = selectedCourseId.value
}

function openCreate() {
  modalMode.value = 'create'
  editing.value = null
  resetForm()
  if (activeScope.value === 'course') form.course_id = selectedCourseId.value
  modalOpen.value = true
}

function openEdit(exam: ExamItem) {
  modalMode.value = 'edit'
  editing.value = exam
  form.title = exam.title
  form.description = exam.description || ''
  form.status = exam.status || 'draft'
  form.duration = exam.duration ?? 60
  form.pass_score = exam.pass_score ?? 70
  form.max_attempts = exam.max_attempts ?? 1
  form.shuffle_questions = false
  form.shuffle_answers = false
  form.course_id = exam.course_id || selectedCourseId.value
  modalOpen.value = true
}

async function saveExam() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.quiz.titleRequired'), life: 2500 })
    return
  }
  if (activeScope.value === 'course' && modalMode.value === 'create' && !form.course_id) {
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
      shuffle_questions: form.shuffle_questions,
      shuffle_answers: form.shuffle_answers,
    }

    if (modalMode.value === 'create') {
      if (activeScope.value === 'standalone') {
        await useApi('/exams/standalone', { method: 'POST', body })
      }
      else {
        await useApi(`/courses/${form.course_id}/exams`, { method: 'POST', body })
      }
      toast.add({ severity: 'success', summary: t('admin.quiz.created'), life: 2500 })
    }
    else if (editing.value) {
      if (activeScope.value === 'standalone' || !editing.value.course_id) {
        await useApi(`/exams/${editing.value.id}`, { method: 'PUT', body })
      }
      else {
        await useApi(`/courses/${editing.value.course_id}/exams/${editing.value.id}`, {
          method: 'PUT',
          body,
        })
      }
      toast.add({ severity: 'success', summary: t('admin.quiz.updated'), life: 2500 })
    }

    modalOpen.value = false
    await loadExams()
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

function askDelete(exam: ExamItem) {
  confirm.require({
    message: t('admin.quiz.deleteConfirm', { title: exam.title }),
    header: t('admin.quiz.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        if (activeScope.value === 'standalone' || !exam.course_id) {
          await useApi(`/exams/${exam.id}`, { method: 'DELETE' })
        }
        else {
          await useApi(`/courses/${exam.course_id}/exams/${exam.id}`, { method: 'DELETE' })
        }
        toast.add({ severity: 'success', summary: t('admin.quiz.deleted'), life: 2500 })
        await loadExams()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.quiz.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

function goCreatePage() {
  navigateTo(`/admin/quiz/create?type=${activeScope.value === 'standalone' ? 'standalone' : 'course_final'}`)
}

onMounted(async () => {
  await loadCourses()
  await loadExams()
})
</script>

<template>
  <div class="page quiz-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.assessment') }}</span>
        <h1>{{ t('admin.quiz.title') }}</h1>
        <p>{{ t('admin.quiz.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <i class="pi pi-filter" />
          <strong>{{ t('admin.quiz.filters') }}</strong>
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.quiz.scope') }}</span>
            <Select
              :model-value="activeScope"
              :options="scopeOptions"
              option-label="label"
              option-value="value"
              class="w-full"
              @update:model-value="onScopeChange"
            />
          </label>
          <label v-if="activeScope === 'course'" class="field">
            <span>{{ t('admin.quiz.course') }}</span>
            <Select
              v-model="selectedCourseId"
              :options="courses"
              option-label="title"
              option-value="id"
              filter
              :loading="loadingCourses"
              :placeholder="t('admin.quiz.selectCourse')"
              class="w-full"
              @change="loadExams"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.quiz.status') }}</span>
            <Select
              v-model="statusFilter"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              show-clear
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.quiz.apply')" icon="pi pi-filter" size="small" @click="loadExams" />
          <Button
            :label="t('admin.quiz.reset')"
            icon="pi pi-times"
            size="small"
            severity="secondary"
            text
            @click="statusFilter = null; tableSearch = ''"
          />
        </div>
      </div>

      <div class="table-toolbar">
        <div class="toolbar-left">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="tableSearch" :placeholder="t('admin.quiz.searchPh')" />
          </IconField>
          <strong>{{ t('admin.users.result', { n: filtered.length }) }}</strong>
        </div>
        <div class="toolbar-actions">
          <Button
            :label="t('admin.quiz.add')"
            icon="pi pi-plus"
            size="small"
            :disabled="activeScope === 'course' && !selectedCourseId"
            @click="openCreate"
          />
          <Button
            :label="t('admin.quiz.builder')"
            icon="pi pi-cog"
            size="small"
            severity="secondary"
            outlined
            @click="goCreatePage"
          />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="loadExams" />
        </div>
      </div>

      <DataTable
        :value="filtered"
        data-key="id"
        :loading="loading"
        paginator
        :rows="15"
        :rows-per-page-options="[10, 15, 25]"
        striped-rows
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="title" :header="t('admin.quiz.examTitle')" sortable style="min-width:220px">
          <template #body="{ data }">
            <div class="exam-cell">
              <strong>{{ data.title }}</strong>
              <small>{{ data.description || '—' }}</small>
            </div>
          </template>
        </Column>
        <Column field="duration" :header="t('admin.quiz.duration')" sortable style="min-width:100px">
          <template #body="{ data }">{{ data.duration ?? 0 }} {{ t('admin.quiz.minutes') }}</template>
        </Column>
        <Column field="pass_score" :header="t('admin.quiz.passScore')" sortable style="min-width:90px">
          <template #body="{ data }">{{ data.pass_score ?? 0 }}%</template>
        </Column>
        <Column field="status" :header="t('admin.quiz.status')" sortable style="min-width:110px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.quiz.enrolled')" style="min-width:90px">
          <template #body="{ data }">{{ data.exam_enrollments_count ?? '—' }}</template>
        </Column>
        <Column :header="t('admin.quiz.schedule')" style="min-width:140px">
          <template #body="{ data }">{{ fmtDate(data.starts_at) }} – {{ fmtDate(data.ends_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:9rem">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="t('admin.quiz.edit')" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" :aria-label="t('admin.quiz.deleteTitle')" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <div class="empty">
            {{ activeScope === 'course' && !selectedCourseId ? t('admin.quiz.pickCourse') : t('common.noData') }}
          </div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="modalMode === 'create' ? t('admin.quiz.add') : t('admin.quiz.edit')"
      :style="{ width: 'min(640px, 96vw)' }"
      :dismissable-mask="true"
    >
      <div class="modal-grid">
        <label v-if="modalMode === 'create' && activeScope === 'course'" class="field full">
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
          <InputNumber v-model="form.duration" :min="0" suffix=" min" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.quiz.passScore') }}</span>
          <InputNumber v-model="form.pass_score" :min="0" :max="100" suffix="%" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.quiz.maxAttempts') }}</span>
          <InputNumber v-model="form.max_attempts" :min="1" :max="99" class="w-full" />
        </label>
        <label class="field switch-field">
          <span>{{ t('admin.quiz.shuffleQuestions') }}</span>
          <ToggleSwitch v-model="form.shuffle_questions" />
        </label>
        <label class="field switch-field">
          <span>{{ t('admin.quiz.shuffleAnswers') }}</span>
          <ToggleSwitch v-model="form.shuffle_answers" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveExam" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.quiz-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle); }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.filter-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.w-full { width: 100%; }

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toolbar-left strong { font-size: .92rem; white-space: nowrap; }
.toolbar-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.exam-cell strong { display: block; }
.exam-cell small {
  display: block; color: var(--text-muted); font-size: .78rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 28rem;
}

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.tone-ok { background: #dcfce7; color: #15803d; }
.tone-info { background: #e0f2fe; color: #0369a1; }
.tone-warn { background: #fef9c3; color: #a16207; }
.tone-danger { background: #fee2e2; color: #b91c1c; }
.tone-muted { background: var(--surface-hover); color: var(--text-muted); }

.empty { padding: 40px; color: var(--text-muted); text-align: center; }
.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field.full { grid-column: 1 / -1; }
.switch-field { flex-direction: row; align-items: center; justify-content: space-between; }

@media (max-width: 720px) {
  .modal-grid { grid-template-columns: 1fr; }
}
</style>
