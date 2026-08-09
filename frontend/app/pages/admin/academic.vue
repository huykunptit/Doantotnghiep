<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

/** Danh mục học vụ tối giản — chỉ giữ entity phục vụ filter & lớp. */
type CatalogKey = 'units' | 'programs' | 'majors' | 'cohorts'

interface CatalogItem {
  id: number
  code?: string
  name: string
  institution_id?: number
  unit_type?: string
  program_type_id?: number
  unit_id?: number | null
  program_id?: number
  major_id?: number | null
  start_year?: number
  end_year?: number | null
  program?: { id: number, name?: string, code?: string }
  program_type?: { id: number, name?: string, code?: string }
  unit?: { id: number, name?: string, code?: string }
  major?: { id: number, name?: string, code?: string }
  [key: string]: unknown
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const tabs: { key: CatalogKey, labelKey: string }[] = [
  { key: 'units', labelKey: 'admin.users.unit' },
  { key: 'programs', labelKey: 'admin.users.program' },
  { key: 'majors', labelKey: 'admin.users.major' },
  { key: 'cohorts', labelKey: 'admin.users.cohort' },
]

const active = ref<CatalogKey>('units')
const loading = ref(false)
const saving = ref(false)
const rows = ref<CatalogItem[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const search = ref('')

const institutionId = ref<number | null>(null)
const programTypeOptions = ref<{ label: string, value: number }[]>([])
const programOptions = ref<{ label: string, value: number }[]>([])
const unitOptions = ref<{ label: string, value: number }[]>([])
const majorOptions = ref<{ label: string, value: number }[]>([])

const modalOpen = ref(false)
const editing = ref<CatalogItem | null>(null)
const form = reactive({
  code: '',
  name: '',
  institution_id: null as number | null,
  unit_type: 'faculty',
  program_type_id: null as number | null,
  unit_id: null as number | null,
  program_id: null as number | null,
  major_id: null as number | null,
  start_year: new Date().getFullYear(),
  end_year: null as number | null,
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

const unitTypeOptions = computed(() => [
  { label: t('admin.academic.unitTypes.board'), value: 'board' },
  { label: t('admin.academic.unitTypes.office'), value: 'office' },
  { label: t('admin.academic.unitTypes.faculty'), value: 'faculty' },
  { label: t('admin.academic.unitTypes.department'), value: 'department' },
])

function mapOptions(items: any[]) {
  return (items || []).map(item => ({
    label: item.code ? `${item.code} — ${item.name}` : item.name,
    value: item.id,
  }))
}

async function loadInstitution() {
  try {
    const res = await useApi<{ data: { id: number }[] }>('/admin/academic/institutions', { query: { per_page: 1 } })
    institutionId.value = res.data?.[0]?.id ?? 1
  }
  catch {
    institutionId.value = 1
  }
}

async function loadFormOptions() {
  try {
    const [programTypes, programs, units, majors] = await Promise.all([
      useApi<any>('/admin/academic/program-types', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/programs', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/units', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/majors', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
    ])
    programTypeOptions.value = mapOptions(programTypes.data)
    programOptions.value = mapOptions(programs.data)
    unitOptions.value = mapOptions(units.data)
    majorOptions.value = mapOptions(majors.data)
  }
  catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data: CatalogItem[], total: number }>(`/admin/academic/${active.value}`, {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        q: search.value || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.academic.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function switchTab(key: CatalogKey) {
  active.value = key
  page.value = 1
  search.value = ''
  load()
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

function resetForm() {
  Object.assign(form, {
    code: '',
    name: '',
    institution_id: institutionId.value,
    unit_type: 'faculty',
    program_type_id: null,
    unit_id: null,
    program_id: null,
    major_id: null,
    start_year: new Date().getFullYear(),
    end_year: null,
  })
}

function openCreate() {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: CatalogItem) {
  editing.value = item
  Object.assign(form, {
    code: item.code || '',
    name: item.name || '',
    institution_id: item.institution_id ?? institutionId.value,
    unit_type: item.unit_type || 'faculty',
    program_type_id: item.program_type_id ?? item.program_type?.id ?? null,
    unit_id: item.unit_id ?? item.unit?.id ?? null,
    program_id: item.program_id ?? item.program?.id ?? null,
    major_id: item.major_id ?? item.major?.id ?? null,
    start_year: item.start_year ?? new Date().getFullYear(),
    end_year: item.end_year ?? null,
  })
  modalOpen.value = true
}

function buildBody() {
  const inst = form.institution_id ?? institutionId.value ?? 1
  if (active.value === 'units') {
    return {
      institution_id: inst,
      name: form.name.trim(),
      code: form.code.trim(),
      unit_type: form.unit_type,
    }
  }
  if (active.value === 'programs') {
    return {
      institution_id: inst,
      program_type_id: form.program_type_id,
      name: form.name.trim(),
      code: form.code.trim(),
      unit_id: form.unit_id || null,
    }
  }
  if (active.value === 'majors') {
    return {
      program_id: form.program_id,
      name: form.name.trim(),
      code: form.code.trim(),
    }
  }
  return {
    institution_id: inst,
    program_id: form.program_id,
    name: form.name.trim(),
    code: form.code.trim(),
    start_year: Number(form.start_year),
    major_id: form.major_id || null,
    end_year: form.end_year || null,
  }
}

function validateForm(): boolean {
  if (!form.name.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.academic.nameRequired'), life: 2500 })
    return false
  }
  if (active.value === 'units' && !form.code.trim()) return warnRequired()
  if (active.value === 'programs' && (!form.code.trim() || !form.program_type_id)) return warnRequired()
  if (active.value === 'majors' && (!form.code.trim() || !form.program_id)) return warnRequired()
  if (active.value === 'cohorts' && (!form.code.trim() || !form.program_id || !form.start_year)) return warnRequired()
  return true
}

function warnRequired() {
  toast.add({ severity: 'warn', summary: t('admin.academic.nameRequired'), life: 2500 })
  return false
}

async function save() {
  if (!validateForm()) return
  saving.value = true
  try {
    const body = buildBody()
    if (editing.value) {
      await useApi(`/admin/academic/${active.value}/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.academic.updated'), life: 2200 })
    }
    else {
      await useApi(`/admin/academic/${active.value}`, { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.academic.created'), life: 2200 })
    }
    modalOpen.value = false
    await Promise.all([load(), loadFormOptions()])
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.academic.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: CatalogItem) {
  confirm.require({
    message: t('admin.academic.deleteConfirm', { name: item.name }),
    header: t('admin.academic.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/${active.value}/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.academic.deleted'), life: 2200 })
        await Promise.all([load(), loadFormOptions()])
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.academic.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

onMounted(async () => {
  await loadInstitution()
  await Promise.all([loadFormOptions(), load()])
})
</script>

<template>
  <div class="page academic-page">

    <section class="table-panel">
      <div class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="tab"
          :class="{ on: active === tab.key }"
          @click="switchTab(tab.key)"
        >
          {{ t(tab.labelKey) }}
        </button>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" :placeholder="t('admin.academic.search')" @input="onSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button :label="t('admin.academic.add')" icon="pi pi-plus" size="small" @click="openCreate" />
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
        :rows-per-page-options="[10, 15, 25]"
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column field="code" :header="t('admin.academic.code')" />
        <Column field="name" :header="t('admin.academic.name')" />
        <Column v-if="active === 'programs'" :header="t('admin.academic.programType')">
          <template #body="{ data }">{{ data.program_type?.name || '—' }}</template>
        </Column>
        <Column v-if="active === 'majors' || active === 'cohorts'" :header="t('admin.academic.program')">
          <template #body="{ data }">{{ data.program?.name || '—' }}</template>
        </Column>
        <Column v-if="active === 'cohorts'" field="start_year" :header="t('admin.academic.startYear')" />
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
      :header="editing ? t('admin.academic.edit') : t('admin.academic.add')"
      :style="{ width: 'min(520px, 96vw)' }"
    >
      <div class="form">
        <label class="field">
          <span>{{ t('admin.academic.code') }}</span>
          <InputText v-model="form.code" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.academic.name') }}</span>
          <InputText v-model="form.name" class="w-full" />
        </label>

        <template v-if="active === 'units'">
          <label class="field full">
            <span>{{ t('admin.academic.unitType') }}</span>
            <Select v-model="form.unit_type" :options="unitTypeOptions" option-label="label" option-value="value" class="w-full" />
          </label>
        </template>

        <template v-if="active === 'programs'">
          <label class="field full">
            <span>{{ t('admin.academic.programType') }}</span>
            <Select v-model="form.program_type_id" :options="programTypeOptions" option-label="label" option-value="value" filter class="w-full" />
          </label>
          <label class="field full">
            <span>{{ t('admin.academic.unit') }}</span>
            <Select v-model="form.unit_id" :options="unitOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
          </label>
        </template>

        <template v-if="active === 'majors'">
          <label class="field full">
            <span>{{ t('admin.academic.program') }}</span>
            <Select v-model="form.program_id" :options="programOptions" option-label="label" option-value="value" filter class="w-full" />
          </label>
        </template>

        <template v-if="active === 'cohorts'">
          <label class="field full">
            <span>{{ t('admin.academic.program') }}</span>
            <Select v-model="form.program_id" :options="programOptions" option-label="label" option-value="value" filter class="w-full" />
          </label>
          <label class="field">
            <span>{{ t('admin.academic.startYear') }}</span>
            <InputNumber v-model="form.start_year" :min="2000" class="w-full" input-class="w-full" />
          </label>
          <label class="field">
            <span>{{ t('admin.academic.endYear') }}</span>
            <InputNumber v-model="form.end_year" :min="2000" class="w-full" input-class="w-full" />
          </label>
          <label class="field full">
            <span>{{ t('admin.academic.major') }}</span>
            <Select v-model="form.major_id" :options="majorOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
          </label>
        </template>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.academic-page { gap: 14px; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}

.tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.tab {
  min-height: 36px; padding: 0 14px; border: 1px solid var(--border); border-radius: 999px;
  background: var(--surface-subtle); color: var(--text-muted); font: inherit; font-weight: 650; cursor: pointer;
}
.tab.on { background: var(--brand-soft); border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); color: var(--brand); }

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.toolbar-actions strong { font-size: .9rem; }

.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }
</style>
