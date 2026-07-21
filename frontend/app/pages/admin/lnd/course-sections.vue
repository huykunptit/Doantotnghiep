<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NamedRef { id: number, code?: string, name?: string, title?: string }
interface ClassSection {
  id: number
  code: string
  name?: string | null
  capacity: number
  enrolled_count: number
  status: string
  description?: string | null
  course?: NamedRef | null
  term?: NamedRef | null
  cohort?: NamedRef | null
  lecturer?: { id: number, name: string, email?: string } | null
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
const rows = ref<ClassSection[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  term_id: null as number | null,
  status: null as string | null,
})

const termOptions = ref<{ label: string, value: number }[]>([])
const cohortOptions = ref<{ label: string, value: number }[]>([])
const courseOptions = ref<{ label: string, value: number }[]>([])
const instructorOptions = ref<{ label: string, value: number }[]>([])

const modalOpen = ref(false)
const editing = ref<ClassSection | null>(null)
const form = reactive({
  course_id: null as number | null,
  term_id: null as number | null,
  cohort_id: null as number | null,
  lecturer_id: null as number | null,
  code: '',
  name: '',
  capacity: 50,
  status: 'planned',
  description: '',
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.sections.statuses.planned'), value: 'planned' },
  { label: t('admin.sections.statuses.open'), value: 'open' },
  { label: t('admin.sections.statuses.closed'), value: 'closed' },
  { label: t('admin.sections.statuses.cancelled'), value: 'cancelled' },
])

const formStatusOptions = computed(() => statusOptions.value.filter(o => o.value))

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.term_id) n++
  if (filters.status) n++
  return n
})

function mapNamedOptions(items: any[]) {
  return (items || []).map(item => ({
    label: item.code ? `${item.code} — ${item.name}` : item.name,
    value: item.id,
  }))
}

function statusTone(status: string) {
  if (status === 'open') return 'tone-open'
  if (status === 'planned') return 'tone-planned'
  if (status === 'closed') return 'tone-closed'
  if (status === 'cancelled') return 'tone-cancelled'
  return 'tone-neutral'
}

function toQuery() {
  return {
    page: page.value,
    per_page: perPage.value,
    q: tableSearch.value || undefined,
    term_id: filters.term_id || undefined,
    status: filters.status || undefined,
  }
}

async function loadOptions() {
  try {
    const [terms, cohorts, courses, instructors] = await Promise.all([
      useApi<any>('/admin/academic/terms', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/cohorts', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/courses', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/users', { query: { user_type: 'instructor', per_page: 100 } }).catch(() => ({ data: [] })),
    ])
    termOptions.value = mapNamedOptions(terms.data)
    cohortOptions.value = mapNamedOptions(cohorts.data)
    courseOptions.value = (courses.data || []).map((c: any) => ({
      label: c.title || `#${c.id}`,
      value: c.id,
    }))
    instructorOptions.value = (instructors.data || []).map((u: any) => ({
      label: u.staff_code ? `${u.staff_code} — ${u.name}` : u.name,
      value: u.id,
    }))
  }
  catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<ClassSection>>('/admin/academic/class-sections', { query: toQuery() })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.sections.loadError'),
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
  filters.term_id = null
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
    course_id: null,
    term_id: null,
    cohort_id: null,
    lecturer_id: null,
    code: '',
    name: '',
    capacity: 50,
    status: 'planned',
    description: '',
  })
}

function openCreate() {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: ClassSection) {
  editing.value = item
  Object.assign(form, {
    course_id: item.course?.id ?? null,
    term_id: item.term?.id ?? null,
    cohort_id: item.cohort?.id ?? null,
    lecturer_id: item.lecturer?.id ?? null,
    code: item.code,
    name: item.name || '',
    capacity: item.capacity ?? 50,
    status: item.status || 'planned',
    description: item.description || '',
  })
  modalOpen.value = true
}

function buildPayload() {
  const base = {
    term_id: form.term_id || null,
    cohort_id: form.cohort_id || null,
    lecturer_id: form.lecturer_id || null,
    code: form.code.trim(),
    name: form.name.trim() || null,
    capacity: Number(form.capacity) || 0,
    status: form.status,
    description: form.description.trim() || null,
  }
  if (editing.value) return base
  return { ...base, course_id: form.course_id }
}

async function save() {
  if (!form.code.trim() || (!editing.value && !form.course_id)) {
    toast.add({ severity: 'warn', summary: t('admin.sections.requiredFields'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const body = buildPayload()
    if (editing.value) {
      await useApi(`/admin/academic/class-sections/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.sections.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/academic/class-sections', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.sections.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.sections.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: ClassSection) {
  confirm.require({
    message: t('admin.sections.deleteConfirm', { code: item.code }),
    header: t('admin.sections.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/class-sections/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.sections.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.sections.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

onMounted(async () => {
  await Promise.all([loadOptions(), load()])
})
</script>

<template>
  <div class="page sections-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.academic') }}</span>
        <h1>{{ t('admin.sections.title') }}</h1>
        <p>{{ t('admin.sections.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.sections.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.sections.term') }}</span>
            <Select
              v-model="filters.term_id"
              :options="[{ label: t('common.all'), value: null }, ...termOptions]"
              option-label="label"
              option-value="value"
              filter
              show-clear
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.sections.status') }}</span>
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
          <Button :label="t('admin.sections.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.sections.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.sections.searchPh')" @input="onTableSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button :label="t('admin.sections.add')" icon="pi pi-plus" size="small" @click="openCreate" />
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
        <Column field="code" :header="t('admin.sections.code')" style="min-width:110px">
          <template #body="{ data }"><code>{{ data.code }}</code></template>
        </Column>
        <Column :header="t('admin.sections.course')" style="min-width:180px">
          <template #body="{ data }">
            <div>
              <strong>{{ data.course?.title || '—' }}</strong>
              <small v-if="data.name">{{ data.name }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.sections.term')" style="min-width:120px">
          <template #body="{ data }">{{ data.term?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.sections.lecturer')" style="min-width:130px">
          <template #body="{ data }">{{ data.lecturer?.name || t('admin.sections.noLecturer') }}</template>
        </Column>
        <Column :header="t('admin.sections.capacityCol')" style="width:120px">
          <template #body="{ data }">
            <strong>{{ data.enrolled_count ?? 0 }}</strong> / {{ data.capacity ?? 0 }}
          </template>
        </Column>
        <Column :header="t('admin.sections.status')" style="width:120px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ t(`admin.sections.statuses.${data.status}`) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:7rem">
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
      :header="editing ? t('admin.sections.edit') : t('admin.sections.add')"
      :style="{ width: 'min(720px, 96vw)' }"
    >
      <div class="form">
        <label v-if="!editing" class="field full">
          <span>{{ t('admin.sections.course') }} *</span>
          <Select v-model="form.course_id" :options="courseOptions" option-label="label" option-value="value" filter class="w-full" />
        </label>
        <label v-else class="field full">
          <span>{{ t('admin.sections.course') }}</span>
          <InputText :model-value="editing?.course?.title || '—'" class="w-full" disabled />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.code') }} *</span>
          <InputText v-model="form.code" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.name') }}</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.term') }}</span>
          <Select v-model="form.term_id" :options="termOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.cohort') }}</span>
          <Select v-model="form.cohort_id" :options="cohortOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.lecturer') }}</span>
          <Select v-model="form.lecturer_id" :options="instructorOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.capacity') }}</span>
          <InputNumber v-model="form.capacity" :min="0" class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.sections.status') }}</span>
          <Select v-model="form.status" :options="formStatusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.sections.description') }}</span>
          <Textarea v-model="form.description" rows="3" class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.sections-page { gap: 14px; }
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
.tone-open { background: #dcfce7; color: #15803d; }
.tone-planned { background: #fef9c3; color: #a16207; }
.tone-closed { background: #e0f2fe; color: #0369a1; }
.tone-cancelled { background: #fce7f3; color: #be185d; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.empty { padding: 36px; text-align: center; color: var(--text-muted); }
.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }
</style>
