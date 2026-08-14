<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { textMatches } from '~/utils/search'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NamedRef { id: number, code?: string, name?: string }
interface CurriculumItem {
  id: number
  name: string
  code: string
  program_id: number
  major_id?: number | null
  effective_from?: string | null
  effective_to?: string | null
  is_active?: boolean
  program?: NamedRef | null
  major?: NamedRef | null
  curriculum_courses_count?: number
}
interface CourseOption {
  id: number
  title: string
  credit_value?: number | null
  status?: string
}
interface CurriculumCourseItem {
  id: number
  curriculum_id: number
  course_id: number
  term_number: number
  is_required: boolean
  credits?: number | null
  position?: number
  course?: { id: number, title: string, credit_value?: number | null }
}
interface CurriculumCoursesResponse {
  curriculum: CurriculumItem
  by_term: Record<string, CurriculumCourseItem[]>
  summary?: {
    total_subjects: number
    required_subjects: number
    elective_subjects: number
    total_credits_required: number
    total_credits_elective: number
    term_count?: number
  }
}
interface Paginator<T> { data: T[], total: number }

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const builderLoading = ref(false)
const addingCourses = ref(false)

const rows = ref<CurriculumItem[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  program_id: null as number | null,
  major_id: null as number | null,
})

const programOptions = ref<{ label: string, value: number }[]>([])
const majorOptions = ref<{ label: string, value: number, program_id: number }[]>([])
const allCourses = ref<CourseOption[]>([])

const modalOpen = ref(false)
const editing = ref<CurriculumItem | null>(null)
const form = reactive({
  program_id: null as number | null,
  major_id: null as number | null,
  code: '',
  name: '',
  effective_from: '',
  effective_to: '',
  is_active: true,
})

const builderOpen = ref(false)
const activeCurriculum = ref<CurriculumItem | null>(null)
const curriculumDetails = ref<CurriculumCoursesResponse | null>(null)
const selectedTerm = ref(1)
const courseSearch = ref('')
const selectedCourseIds = ref<number[]>([])
const pickerRequired = ref(true)
const pickerCredits = ref<number | null>(null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const filteredMajors = computed(() => {
  const pid = filters.program_id || form.program_id
  if (!pid) return majorOptions.value
  return majorOptions.value.filter(m => m.program_id === pid)
})

const formMajors = computed(() => {
  if (!form.program_id) return majorOptions.value
  return majorOptions.value.filter(m => m.program_id === form.program_id)
})

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.program_id) n++
  if (filters.major_id) n++
  return n
})

const termNumbers = computed(() => {
  const max = Math.max(8, Number(curriculumDetails.value?.summary?.term_count || 0), selectedTerm.value)
  return Array.from({ length: max }, (_, i) => i + 1)
})

const termCourses = computed(() => {
  if (!curriculumDetails.value?.by_term) return []
  return curriculumDetails.value.by_term[String(selectedTerm.value)] || []
})

const mappedCourseIds = computed(() => {
  const ids = new Set<number>()
  if (!curriculumDetails.value?.by_term) return ids
  Object.values(curriculumDetails.value.by_term).forEach((list) => {
    list.forEach(cc => ids.add(cc.course_id))
  })
  return ids
})

const availableCourses = computed(() => {
  const q = courseSearch.value
  return allCourses.value.filter((c) => {
    if (mappedCourseIds.value.has(c.id)) return false
    if (!q.trim()) return true
    return textMatches(c.title, q)
  })
})

function mapOptions(items: any[]) {
  return (items || []).map(item => ({
    label: item.code ? `${item.code} — ${item.name}` : item.name,
    value: item.id,
    program_id: item.program_id,
  }))
}

function toDateInput(value?: string | null) {
  if (!value) return ''
  return String(value).slice(0, 10)
}

async function loadOptions() {
  try {
    const [programs, majors, courses] = await Promise.all([
      useApi<Paginator<NamedRef>>('/admin/academic/programs', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/majors', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<Paginator<CourseOption>>('/admin/courses', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
    ])
    programOptions.value = mapOptions(programs.data)
    majorOptions.value = mapOptions(majors.data)
    allCourses.value = courses.data || []
  }
  catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<CurriculumItem>>('/admin/academic/curricula', {
      query: {
        page: page.value,
        per_page: perPage.value,
        q: tableSearch.value || undefined,
        search: tableSearch.value || undefined,
        program_id: filters.program_id || undefined,
        major_id: filters.major_id || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.learningPaths.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function onTableSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
}

function applyFilters() {
  page.value = 1
  load()
}

function resetFilters() {
  filters.program_id = null
  filters.major_id = null
  page.value = 1
  load()
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

function resetForm() {
  Object.assign(form, {
    program_id: filters.program_id,
    major_id: filters.major_id,
    code: '',
    name: '',
    effective_from: '',
    effective_to: '',
    is_active: true,
  })
}

function openCreate() {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: CurriculumItem) {
  editing.value = item
  Object.assign(form, {
    program_id: item.program_id ?? item.program?.id ?? null,
    major_id: item.major_id ?? item.major?.id ?? null,
    code: item.code || '',
    name: item.name || '',
    effective_from: toDateInput(item.effective_from),
    effective_to: toDateInput(item.effective_to),
    is_active: item.is_active !== false,
  })
  modalOpen.value = true
}

async function save() {
  if (!form.code.trim() || !form.name.trim() || !form.program_id) {
    toast.add({ severity: 'warn', summary: t('admin.learningPaths.requiredFields'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const body = {
      program_id: form.program_id,
      major_id: form.major_id || null,
      code: form.code.trim(),
      name: form.name.trim(),
      effective_from: form.effective_from || null,
      effective_to: form.effective_to || null,
      is_active: form.is_active,
    }
    if (editing.value) {
      await useApi(`/admin/academic/curricula/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.learningPaths.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/academic/curricula', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.learningPaths.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.learningPaths.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: CurriculumItem) {
  confirm.require({
    message: t('admin.learningPaths.deleteConfirm', { name: item.name, code: item.code }),
    header: t('admin.learningPaths.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/curricula/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.learningPaths.deleted'), life: 2200 })
        if (activeCurriculum.value?.id === item.id) {
          builderOpen.value = false
          activeCurriculum.value = null
          curriculumDetails.value = null
        }
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.learningPaths.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

async function openBuilder(item: CurriculumItem) {
  activeCurriculum.value = item
  selectedTerm.value = 1
  selectedCourseIds.value = []
  courseSearch.value = ''
  pickerRequired.value = true
  pickerCredits.value = null
  builderOpen.value = true
  await loadBuilder()
}

async function loadBuilder() {
  if (!activeCurriculum.value) return
  builderLoading.value = true
  try {
    curriculumDetails.value = await useApi<CurriculumCoursesResponse>(
      `/admin/academic/curricula/${activeCurriculum.value.id}/courses`,
    )
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.learningPaths.builderLoadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    builderLoading.value = false
  }
}

async function addSelectedCourses() {
  if (!activeCurriculum.value || !selectedCourseIds.value.length) {
    toast.add({ severity: 'warn', summary: t('admin.learningPaths.selectCourses'), life: 2500 })
    return
  }
  addingCourses.value = true
  try {
    const payload = selectedCourseIds.value.map((courseId, index) => ({
      course_id: courseId,
      term_number: selectedTerm.value,
      is_required: pickerRequired.value,
      credits: pickerCredits.value ?? allCourses.value.find(c => c.id === courseId)?.credit_value ?? null,
      position: index,
    }))
    await useApi(`/admin/academic/curricula/${activeCurriculum.value.id}/courses`, {
      method: 'POST',
      body: payload,
    })
    toast.add({ severity: 'success', summary: t('admin.learningPaths.coursesAdded'), life: 2200 })
    selectedCourseIds.value = []
    await loadBuilder()
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.learningPaths.coursesAddError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    addingCourses.value = false
  }
}

function askRemoveCourse(cc: CurriculumCourseItem) {
  if (!activeCurriculum.value) return
  confirm.require({
    message: t('admin.learningPaths.removeCourseConfirm', { title: cc.course?.title || `#${cc.course_id}` }),
    header: t('admin.learningPaths.removeCourseTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/curricula/${activeCurriculum.value!.id}/courses/${cc.id}`, {
          method: 'DELETE',
        })
        toast.add({ severity: 'success', summary: t('admin.learningPaths.courseRemoved'), life: 2200 })
        await loadBuilder()
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.learningPaths.courseRemoveError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

onMounted(async () => {
  await Promise.all([loadOptions(), load()])
})
</script>

<template>
  <div class="page lp-page">

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.learningPaths.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.learningPaths.program') }}</span>
            <Select
              v-model="filters.program_id"
              :options="programOptions"
              option-label="label"
              option-value="value"
              filter
              show-clear
              :placeholder="t('common.all')"
              class="w-full"
              @update:model-value="filters.major_id = null"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.learningPaths.major') }}</span>
            <Select
              v-model="filters.major_id"
              :options="filteredMajors"
              option-label="label"
              option-value="value"
              filter
              show-clear
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.learningPaths.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.learningPaths.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.learningPaths.searchPh')" @input="onTableSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button :label="t('admin.learningPaths.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[10, 15, 25, 50]"
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column field="code" :header="t('admin.learningPaths.code')" style="min-width:110px">
          <template #body="{ data }"><code>{{ data.code }}</code></template>
        </Column>
        <Column field="name" :header="t('admin.learningPaths.name')" style="min-width:180px">
          <template #body="{ data }"><strong>{{ data.name }}</strong></template>
        </Column>
        <Column :header="t('admin.learningPaths.program')" style="min-width:140px">
          <template #body="{ data }">{{ data.program?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.learningPaths.major')" style="min-width:140px">
          <template #body="{ data }">{{ data.major?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.learningPaths.coursesCount')" style="width:100px">
          <template #body="{ data }"><strong>{{ data.curriculum_courses_count ?? 0 }}</strong></template>
        </Column>
        <Column :header="t('admin.learningPaths.status')" style="width:110px">
          <template #body="{ data }">
            <span class="pill" :class="data.is_active === false ? 'tone-off' : 'tone-on'">
              {{ data.is_active === false ? t('admin.learningPaths.inactive') : t('admin.learningPaths.active') }}
            </span>
          </template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:11rem">
          <template #body="{ data }">
            <Button icon="pi pi-sitemap" text rounded severity="success" :aria-label="t('admin.learningPaths.builder')" @click="openBuilder(data)" />
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
      :header="editing ? t('admin.learningPaths.edit') : t('admin.learningPaths.add')"
      :style="{ width: 'min(680px, 96vw)' }"
    >
      <div class="form">
        <label class="field">
          <span>{{ t('admin.learningPaths.code') }}</span>
          <InputText v-model="form.code" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.learningPaths.name') }}</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.learningPaths.program') }}</span>
          <Select
            v-model="form.program_id"
            :options="programOptions"
            option-label="label"
            option-value="value"
            filter
            class="w-full"
            @update:model-value="form.major_id = null"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.learningPaths.major') }}</span>
          <Select
            v-model="form.major_id"
            :options="formMajors"
            option-label="label"
            option-value="value"
            filter
            show-clear
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.learningPaths.effectiveFrom') }}</span>
          <InputText v-model="form.effective_from" type="date" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.learningPaths.effectiveTo') }}</span>
          <InputText v-model="form.effective_to" type="date" class="w-full" />
        </label>
        <label class="field full check-row">
          <Checkbox v-model="form.is_active" :binary="true" input-id="lp-active" />
          <label for="lp-active">{{ t('admin.learningPaths.active') }}</label>
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="builderOpen"
      modal
      :header="`${t('admin.learningPaths.builder')} — ${activeCurriculum?.name || ''}`"
      :style="{ width: 'min(960px, 96vw)' }"
    >
      <div v-if="builderLoading" class="empty">{{ t('common.loading') }}</div>
      <div v-else class="builder">
        <div v-if="curriculumDetails?.summary" class="summary">
          <span>{{ t('admin.learningPaths.summarySubjects', { n: curriculumDetails.summary.total_subjects }) }}</span>
          <span>{{ t('admin.learningPaths.summaryRequired', { n: curriculumDetails.summary.required_subjects }) }}</span>
          <span>{{ t('admin.learningPaths.summaryCredits', { n: curriculumDetails.summary.total_credits_required }) }}</span>
        </div>

        <div class="term-tabs">
          <button
            v-for="term in termNumbers"
            :key="term"
            type="button"
            class="term-tab"
            :class="{ on: selectedTerm === term }"
            @click="selectedTerm = term"
          >
            {{ t('admin.learningPaths.term', { n: term }) }}
            <small>({{ (curriculumDetails?.by_term?.[String(term)] || []).length }})</small>
          </button>
        </div>

        <div class="builder-grid">
          <div class="builder-col">
            <h3>{{ t('admin.learningPaths.termCourses') }}</h3>
            <div v-if="!termCourses.length" class="empty small">{{ t('admin.learningPaths.noCoursesInTerm') }}</div>
            <ul v-else class="course-list">
              <li v-for="cc in termCourses" :key="cc.id">
                <div>
                  <strong>{{ cc.course?.title || `#${cc.course_id}` }}</strong>
                  <small>
                    {{ cc.credits ?? cc.course?.credit_value ?? 0 }} {{ t('admin.learningPaths.credits') }}
                    · {{ cc.is_required ? t('admin.learningPaths.required') : t('admin.learningPaths.elective') }}
                  </small>
                </div>
                <Button icon="pi pi-trash" text rounded severity="danger" @click="askRemoveCourse(cc)" />
              </li>
            </ul>
          </div>

          <div class="builder-col">
            <h3>{{ t('admin.learningPaths.addCourses') }}</h3>
            <IconField class="mb">
              <InputIcon class="pi pi-search" />
              <InputText v-model="courseSearch" :placeholder="t('admin.learningPaths.courseSearchPh')" class="w-full" />
            </IconField>
            <MultiSelect
              v-model="selectedCourseIds"
              :options="availableCourses"
              option-label="title"
              option-value="id"
              filter
              display="chip"
              :placeholder="t('admin.learningPaths.pickCourses')"
              class="w-full mb"
            />
            <div class="picker-row">
              <label class="check-row">
                <Checkbox v-model="pickerRequired" :binary="true" input-id="lp-req" />
                <label for="lp-req">{{ t('admin.learningPaths.required') }}</label>
              </label>
              <label class="field inline">
                <span>{{ t('admin.learningPaths.credits') }}</span>
                <InputNumber v-model="pickerCredits" :min="0" class="w-full" input-class="w-full" />
              </label>
            </div>
            <Button
              :label="t('admin.learningPaths.addSelected', { n: selectedCourseIds.length })"
              icon="pi pi-plus"
              class="w-full"
              :loading="addingCourses"
              :disabled="!selectedCourseIds.length"
              @click="addSelectedCourses"
            />
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.lp-page { gap: 14px; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.filter-actions { display: flex; gap: 8px; margin-top: 10px; }
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.toolbar-actions strong { font-size: .9rem; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.field.inline { flex: 1; }
.w-full { width: 100%; }
.mb { margin-bottom: 10px; }

code { font-family: ui-monospace, monospace; font-size: .82rem; font-weight: 700; color: var(--brand); }
.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-on { background: #dcfce7; color: #15803d; }
.tone-off { background: #f1f5f9; color: #64748b; }

.empty { padding: 36px; text-align: center; color: var(--text-muted); }
.empty.small { padding: 16px; }
.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }
.check-row { display: flex; align-items: center; gap: 8px; font-weight: 600; }

.builder { display: grid; gap: 14px; }
.summary {
  display: flex; flex-wrap: wrap; gap: 10px 16px; font-size: .88rem; font-weight: 600;
  color: var(--text-muted);
}
.term-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.term-tab {
  border: 1px solid var(--border); background: var(--surface); border-radius: 10px;
  padding: 6px 10px; cursor: pointer; font: inherit; font-weight: 700; color: var(--text-muted);
}
.term-tab.on { color: var(--brand); border-color: var(--brand); background: color-mix(in srgb, var(--brand) 10%, transparent); }
.builder-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.builder-col {
  border: 1px solid var(--border); border-radius: 12px; padding: 12px;
  background: color-mix(in srgb, var(--surface) 88%, transparent);
}
.builder-col h3 { margin: 0 0 10px; font-size: 1rem; }
.course-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.course-list li {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  padding: 8px 10px; border-radius: 10px; background: var(--surface-hover, #f8fafc);
}
.course-list small { display: block; color: var(--text-muted); margin-top: 2px; }
.picker-row { display: flex; align-items: end; gap: 12px; margin-bottom: 10px; }

@media (max-width: 800px) {
  .form, .builder-grid { grid-template-columns: 1fr; }
}
</style>
