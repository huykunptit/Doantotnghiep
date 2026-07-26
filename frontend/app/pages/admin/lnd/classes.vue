<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NamedRef { id: number, code?: string, name?: string }
interface AdminClass {
  id: number
  code: string
  name: string
  status: string
  capacity: number
  expected_graduation_year?: number | null
  description?: string | null
  program?: NamedRef | null
  unit?: NamedRef | null
  cohort?: NamedRef | null
  major?: NamedRef | null
  advisor?: { id: number, name: string, email?: string } | null
  curriculum?: NamedRef | null
  students_count?: number
}

interface StudentUser {
  id: number
  name: string
  email: string
  student_code?: string | null
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const rows = ref<AdminClass[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  cohort_id: [] as number[],
  program_id: [] as number[],
  unit_id: [] as number[],
  status: null as string | null,
})

const institutionId = ref<number | null>(null)
const cohortOptions = ref<{ label: string, value: number }[]>([])
const programOptions = ref<{ label: string, value: number }[]>([])
const unitOptions = ref<{ label: string, value: number }[]>([])
const majorOptions = ref<{ label: string, value: number }[]>([])
const curriculumOptions = ref<{ label: string, value: number }[]>([])
const advisorOptions = ref<{ label: string, value: number }[]>([])
const studentOptions = ref<{ label: string, value: number }[]>([])

const modalOpen = ref(false)
const editing = ref<AdminClass | null>(null)
const form = reactive({
  unit_id: null as number | null,
  program_id: null as number | null,
  major_id: null as number | null,
  cohort_id: null as number | null,
  advisor_id: null as number | null,
  curriculum_id: null as number | null,
  code: '',
  name: '',
  capacity: 40,
  expected_graduation_year: null as number | null,
  status: 'active',
  description: '',
})

const enrollOpen = ref(false)
const enrollClass = ref<AdminClass | null>(null)
const enrollMode = ref<'select' | 'codes'>('select')
const enrollStudentIds = ref<number[]>([])
const enrollCodesText = ref('')
const enrollSaving = ref(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.classes.statuses.active'), value: 'active' },
  { label: t('admin.classes.statuses.graduated'), value: 'graduated' },
  { label: t('admin.classes.statuses.suspended'), value: 'suspended' },
])

const formStatusOptions = computed(() => statusOptions.value.filter(o => o.value))

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.cohort_id.length) n++
  if (filters.program_id.length) n++
  if (filters.unit_id.length) n++
  if (filters.status) n++
  return n
})

function mapOptions(items: any[]) {
  return (items || []).map(item => ({
    label: item.code ? `${item.code} — ${item.name}` : item.name,
    value: item.id,
  }))
}

function statusTone(status: string) {
  if (status === 'active') return 'tone-active'
  if (status === 'graduated') return 'tone-graduated'
  if (status === 'suspended') return 'tone-suspended'
  return 'tone-neutral'
}

function toQuery() {
  return {
    page: page.value,
    per_page: perPage.value,
    q: tableSearch.value || undefined,
    cohort_id: filters.cohort_id.length ? filters.cohort_id : undefined,
    program_id: filters.program_id.length ? filters.program_id : undefined,
    unit_id: filters.unit_id.length ? filters.unit_id : undefined,
    status: filters.status || undefined,
  }
}

async function loadInstitution() {
  try {
    const res = await useApi<Paginator<{ id: number }>>('/admin/academic/institutions', { query: { per_page: 1 } })
    institutionId.value = res.data?.[0]?.id ?? 1
  }
  catch {
    institutionId.value = 1
  }
}

async function loadOptions() {
  try {
    const [cohorts, programs, units, majors, curricula, advisors, students] = await Promise.all([
      useApi<any>('/admin/academic/cohorts', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/programs', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/units', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/majors', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/curricula', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/instructors', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/students', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
    ])
    cohortOptions.value = mapOptions(cohorts.data)
    programOptions.value = mapOptions(programs.data)
    unitOptions.value = mapOptions(units.data)
    majorOptions.value = mapOptions(majors.data)
    curriculumOptions.value = mapOptions(curricula.data)
    advisorOptions.value = (advisors.data || []).map((u: any) => ({
      label: u.staff_code ? `${u.staff_code} — ${u.name}` : u.name,
      value: u.id,
    }))
    studentOptions.value = (students.data || []).map((u: StudentUser) => ({
      label: u.student_code ? `${u.student_code} — ${u.name}` : u.name,
      value: u.id,
    }))
  }
  catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AdminClass>>('/admin/academic/administrative-classes', { query: toQuery() })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.classes.loadError'),
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
  filters.cohort_id = []
  filters.program_id = []
  filters.unit_id = []
  filters.status = null
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
    unit_id: null,
    program_id: null,
    major_id: null,
    cohort_id: null,
    advisor_id: null,
    curriculum_id: null,
    code: '',
    name: '',
    capacity: 40,
    expected_graduation_year: null,
    status: 'active',
    description: '',
  })
}

function openCreate() {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: AdminClass) {
  editing.value = item
  Object.assign(form, {
    unit_id: item.unit?.id ?? null,
    program_id: item.program?.id ?? null,
    major_id: item.major?.id ?? null,
    cohort_id: item.cohort?.id ?? null,
    advisor_id: item.advisor?.id ?? null,
    curriculum_id: item.curriculum?.id ?? null,
    code: item.code,
    name: item.name,
    capacity: item.capacity ?? 40,
    expected_graduation_year: item.expected_graduation_year ?? null,
    status: item.status || 'active',
    description: item.description || '',
  })
  modalOpen.value = true
}

function buildPayload() {
  return {
    institution_id: institutionId.value ?? 1,
    unit_id: form.unit_id,
    program_id: form.program_id,
    cohort_id: form.cohort_id,
    major_id: form.major_id || null,
    advisor_id: form.advisor_id || null,
    curriculum_id: form.curriculum_id || null,
    code: form.code.trim(),
    name: form.name.trim(),
    capacity: Number(form.capacity) || 40,
    expected_graduation_year: form.expected_graduation_year || null,
    status: form.status,
    description: form.description.trim() || null,
  }
}

async function save() {
  if (!form.code.trim() || !form.name.trim() || !form.unit_id || !form.program_id || !form.cohort_id) {
    toast.add({ severity: 'warn', summary: t('admin.classes.requiredFields'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const body = buildPayload()
    if (editing.value) {
      await useApi(`/admin/academic/administrative-classes/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.classes.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/academic/administrative-classes', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.classes.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.classes.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: AdminClass) {
  confirm.require({
    message: t('admin.classes.deleteConfirm', { name: item.name, code: item.code }),
    header: t('admin.classes.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/administrative-classes/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.classes.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.classes.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

function openEnroll(item: AdminClass) {
  enrollClass.value = item
  enrollMode.value = 'select'
  enrollStudentIds.value = []
  enrollCodesText.value = ''
  enrollOpen.value = true
}

async function resolveStudentIdsFromCodes(codes: string[]): Promise<{ ids: number[], missing: string[] }> {
  const ids: number[] = []
  const missing: string[] = []
  for (const code of codes) {
    try {
      const res = await useApi<Paginator<StudentUser>>('/admin/users', {
        query: { search: code, role: 'student', per_page: 10 },
      })
      const match = (res.data || []).find(u => u.student_code?.toLowerCase() === code.toLowerCase())
      if (match) ids.push(match.id)
      else missing.push(code)
    }
    catch {
      missing.push(code)
    }
  }
  return { ids: [...new Set(ids)], missing }
}

async function submitEnroll() {
  if (!enrollClass.value) return

  let studentIds = [...enrollStudentIds.value]
  if (enrollMode.value === 'codes') {
    const codes = enrollCodesText.value
      .split(/[\n,;]+/)
      .map(c => c.trim())
      .filter(Boolean)
    if (!codes.length) {
      toast.add({ severity: 'warn', summary: t('admin.classes.enrollEmpty'), life: 2500 })
      return
    }
    const resolved = await resolveStudentIdsFromCodes(codes)
    studentIds = resolved.ids
    if (resolved.missing.length) {
      toast.add({
        severity: 'warn',
        summary: t('admin.classes.codesNotFound', { codes: resolved.missing.join(', ') }),
        life: 4000,
      })
    }
  }

  if (!studentIds.length) {
    toast.add({ severity: 'warn', summary: t('admin.classes.enrollEmpty'), life: 2500 })
    return
  }

  enrollSaving.value = true
  try {
    const res = await useApi<{ updated: number, message?: string }>('/admin/academic/administrative-classes/enroll-students', {
      method: 'POST',
      body: {
        administrative_class_id: enrollClass.value.id,
        student_ids: studentIds,
      },
    })
    toast.add({
      severity: 'success',
      summary: t('admin.classes.enrollSuccess', { n: res.updated ?? studentIds.length }),
      life: 2500,
    })
    enrollOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.classes.enrollError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    enrollSaving.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadInstitution(), loadOptions(), load()])
})
</script>

<template>
  <div class="page classes-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.academic') }}</span>
        <h1>{{ t('admin.classes.title') }}</h1>
        <p>{{ t('admin.classes.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.classes.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.classes.cohort') }}</span>
            <MultiSelect
              v-model="filters.cohort_id"
              :options="cohortOptions"
              option-label="label"
              option-value="value"
              filter
              display="chip"
              :max-selected-labels="2"
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.classes.program') }}</span>
            <MultiSelect
              v-model="filters.program_id"
              :options="programOptions"
              option-label="label"
              option-value="value"
              filter
              display="chip"
              :max-selected-labels="2"
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.classes.unit') }}</span>
            <MultiSelect
              v-model="filters.unit_id"
              :options="unitOptions"
              option-label="label"
              option-value="value"
              filter
              display="chip"
              :max-selected-labels="2"
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.classes.status') }}</span>
            <Select
              v-model="filters.status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.classes.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.classes.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.classes.searchPh')" @input="onTableSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button :label="t('admin.classes.add')" icon="pi pi-plus" size="small" @click="openCreate" />
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
        <Column field="code" :header="t('admin.classes.code')" style="min-width:110px">
          <template #body="{ data }"><code>{{ data.code }}</code></template>
        </Column>
        <Column field="name" :header="t('admin.classes.name')" style="min-width:180px">
          <template #body="{ data }">
            <div>
              <strong>{{ data.name }}</strong>
              <small v-if="data.unit">{{ data.unit.name }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.classes.cohort')" style="min-width:140px">
          <template #body="{ data }">
            <div>
              <span>{{ data.cohort?.name || '—' }}</span>
              <small>{{ data.program?.name || '—' }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.classes.advisor')" style="min-width:130px">
          <template #body="{ data }">{{ data.advisor?.name || t('admin.classes.noAdvisor') }}</template>
        </Column>
        <Column :header="t('admin.classes.students')" style="width:100px">
          <template #body="{ data }"><strong>{{ data.students_count ?? 0 }}</strong> / {{ data.capacity }}</template>
        </Column>
        <Column :header="t('admin.classes.status')" style="width:120px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ t(`admin.classes.statuses.${data.status}`) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:10rem">
          <template #body="{ data }">
            <Button icon="pi pi-user-plus" text rounded severity="success" :aria-label="t('admin.classes.enroll')" @click="openEnroll(data)" />
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
      :header="editing ? t('admin.classes.edit') : t('admin.classes.add')"
      :style="{ width: 'min(720px, 96vw)' }"
    >
      <div class="form">
        <label class="field">
          <span>{{ t('admin.classes.code') }}</span>
          <InputText v-model="form.code" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.name') }}</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.cohort') }}</span>
          <Select v-model="form.cohort_id" :options="cohortOptions" option-label="label" option-value="value" filter class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.program') }}</span>
          <Select v-model="form.program_id" :options="programOptions" option-label="label" option-value="value" filter class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.unit') }}</span>
          <Select v-model="form.unit_id" :options="unitOptions" option-label="label" option-value="value" filter class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.major') }}</span>
          <Select v-model="form.major_id" :options="majorOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.curriculum') }}</span>
          <Select v-model="form.curriculum_id" :options="curriculumOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.advisor') }}</span>
          <Select v-model="form.advisor_id" :options="advisorOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.capacity') }}</span>
          <InputNumber v-model="form.capacity" :min="1" class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.graduationYear') }}</span>
          <InputNumber v-model="form.expected_graduation_year" :min="2000" class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.classes.status') }}</span>
          <Select v-model="form.status" :options="formStatusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.classes.description') }}</span>
          <CommonRichTextEditor v-model="form.description" height="180px" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="enrollOpen"
      modal
      :header="`${t('admin.classes.enrollTitle')} — ${enrollClass?.name || ''}`"
      :style="{ width: 'min(640px, 96vw)' }"
    >
      <div class="enroll-tabs">
        <button type="button" class="enroll-tab" :class="{ on: enrollMode === 'select' }" @click="enrollMode = 'select'">
          {{ t('admin.classes.enrollSelect') }}
        </button>
        <button type="button" class="enroll-tab" :class="{ on: enrollMode === 'codes' }" @click="enrollMode = 'codes'">
          {{ t('admin.classes.enrollCodes') }}
        </button>
      </div>

      <div v-if="enrollMode === 'select'" class="enroll-body">
        <MultiSelect
          v-model="enrollStudentIds"
          :options="studentOptions"
          option-label="label"
          option-value="value"
          filter
          display="chip"
          :placeholder="t('admin.classes.enrollSearchPh')"
          class="w-full"
        />
        <small>{{ t('admin.classes.enrollSelected', { n: enrollStudentIds.length }) }}</small>
      </div>
      <div v-else class="enroll-body">
        <small>{{ t('admin.classes.enrollCodesHint') }}</small>
        <Textarea v-model="enrollCodesText" rows="8" :placeholder="t('admin.classes.enrollCodesPh')" class="w-full" />
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="enrollOpen = false" />
        <Button
          :label="t('admin.classes.enrollSubmit', { n: enrollMode === 'select' ? enrollStudentIds.length : '…' })"
          icon="pi pi-user-plus"
          :loading="enrollSaving"
          @click="submitEnroll"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.classes-page { gap: 14px; }
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
.filter-bar { margin-bottom: 12px; }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;
}
.filter-actions { display: flex; gap: 8px; margin-top: 10px; }
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.toolbar-actions strong { font-size: .9rem; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.field small { color: var(--text-muted); font-size: .78rem; }
.w-full { width: 100%; }

code { font-family: ui-monospace, monospace; font-size: .82rem; font-weight: 700; color: var(--brand); }
.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-active { background: #dcfce7; color: #15803d; }
.tone-graduated { background: #e0f2fe; color: #0369a1; }
.tone-suspended { background: #fce7f3; color: #be185d; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.empty { padding: 36px; text-align: center; color: var(--text-muted); }
.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }

.enroll-tabs { display: flex; gap: 6px; margin-bottom: 12px; border-bottom: 1px solid var(--border); }
.enroll-tab {
  border: 0; background: none; padding: 8px 12px; cursor: pointer;
  font: inherit; font-weight: 700; color: var(--text-muted); border-bottom: 2px solid transparent;
}
.enroll-tab.on { color: var(--brand); border-bottom-color: var(--brand); }
.enroll-body { display: grid; gap: 8px; }
</style>
