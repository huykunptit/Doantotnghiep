<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NamedRef { id: number, code?: string, name?: string, title?: string }
interface EnrollmentRow {
  id: number
  enrollment_source: string
  order_id?: number | null
  enrolled_at?: string | null
  user?: { id: number, name: string, email?: string, student_code?: string | null } | null
  course?: NamedRef | null
  term?: NamedRef | null
  cohort?: NamedRef | null
  classSection?: { id: number, code: string } | null
  class_section?: { id: number, code: string } | null
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
  last_page?: number
}

interface ImportPreviewRow {
  row_number: number
  student_code: string
  student_name: string | null
  course_code: string
  course_title: string | null
  status: 'valid' | 'invalid'
  message: string
}

interface ImportPreviewResponse {
  import_token: string
  total_rows: number
  valid_rows: number
  invalid_rows: number
  preview_data: ImportPreviewRow[]
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const rows = ref<EnrollmentRow[]>([])
const total = ref(0)
const selected = ref<EnrollmentRow[]>([])
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  term_id: null as number | null,
  cohort_id: null as number | null,
  course_id: null as number | null,
  class_section_id: null as number | null,
  source: null as string | null,
})

const termOptions = ref<{ label: string, value: number }[]>([])
const cohortOptions = ref<{ label: string, value: number }[]>([])
const courseOptions = ref<{ label: string, value: number }[]>([])
const sectionOptions = ref<{ label: string, value: number }[]>([])
const studentOptions = ref<{ label: string, value: number }[]>([])

const manualOpen = ref(false)
const manualForm = reactive({
  term_id: null as number | null,
  course_id: null as number | null,
  class_section_id: null as number | null,
  user_ids: [] as number[],
})

const manualSectionOptions = ref<{ label: string, value: number }[]>([])

const exporting = ref(false)

const importOpen = ref(false)
const importFile = ref<File | null>(null)
const importDragging = ref(false)
const importPreviewing = ref(false)
const importExecuting = ref(false)
const importPreviewResult = ref<ImportPreviewResponse | null>(null)
const importTermId = ref<number | null>(null)
const importFileInput = ref<HTMLInputElement | null>(null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const sourceOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.enrollment.sources.academic'), value: 'academic' },
  { label: t('admin.enrollment.sources.manual'), value: 'manual' },
  { label: t('admin.enrollment.sources.excel_import'), value: 'excel_import' },
  { label: t('admin.enrollment.sources.marketplace'), value: 'marketplace' },
])

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.term_id) n++
  if (filters.cohort_id) n++
  if (filters.course_id) n++
  if (filters.class_section_id) n++
  if (filters.source) n++
  return n
})

function mapNamedOptions(items: any[]) {
  return (items || []).map(item => ({
    label: item.code ? `${item.code} — ${item.name}` : item.name,
    value: item.id,
  }))
}

function sectionOf(row: EnrollmentRow) {
  return row.classSection || row.class_section
}

function sourceLabel(src?: string | null) {
  if (!src) return '—'
  const key = `admin.enrollment.sources.${src}`
  const translated = t(key)
  return translated === key ? src : translated
}

function sourceTone(src?: string | null) {
  if (src === 'marketplace') return 'tone-b2c'
  if (src === 'academic') return 'tone-academic'
  if (src === 'manual') return 'tone-manual'
  if (src === 'excel_import') return 'tone-import'
  return 'tone-neutral'
}

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

function toQuery() {
  return {
    page: page.value,
    per_page: perPage.value,
    search: tableSearch.value || undefined,
    term_id: filters.term_id || undefined,
    cohort_id: filters.cohort_id || undefined,
    course_id: filters.course_id || undefined,
    class_section_id: filters.class_section_id || undefined,
    source: filters.source || undefined,
  }
}

async function loadSectionOptions() {
  try {
    const query: Record<string, any> = { per_page: 200 }
    if (filters.course_id) query.course_id = filters.course_id
    if (filters.term_id) query.term_id = filters.term_id
    const res = await useApi<any>('/admin/academic/class-sections', { query })
    sectionOptions.value = (res.data || []).map((s: any) => ({
      label: s.code,
      value: s.id,
    }))
    if (filters.class_section_id && !sectionOptions.value.some(o => o.value === filters.class_section_id)) {
      filters.class_section_id = null
    }
  }
  catch {
    sectionOptions.value = []
  }
}

async function loadManualSections() {
  if (!manualForm.course_id) {
    manualSectionOptions.value = []
    manualForm.class_section_id = null
    return
  }
  try {
    const query: Record<string, any> = { per_page: 200, course_id: manualForm.course_id }
    if (manualForm.term_id) query.term_id = manualForm.term_id
    const res = await useApi<any>('/admin/academic/class-sections', { query })
    manualSectionOptions.value = (res.data || []).map((s: any) => ({
      label: s.code,
      value: s.id,
    }))
  }
  catch {
    manualSectionOptions.value = []
  }
}

async function loadOptions() {
  try {
    const [terms, cohorts, courses, students] = await Promise.all([
      useApi<any>('/admin/academic/terms', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/cohorts', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/courses', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/students', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
    ])
    termOptions.value = mapNamedOptions(terms.data)
    cohortOptions.value = mapNamedOptions(cohorts.data)
    courseOptions.value = (courses.data || []).map((c: any) => ({
      label: c.title || `#${c.id}`,
      value: c.id,
    }))
    studentOptions.value = (students.data || []).map((u: any) => ({
      label: u.student_code ? `${u.student_code} — ${u.name}` : u.name,
      value: u.id,
    }))
  }
  catch { /* ignore */ }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<EnrollmentRow>>('/admin/academic/enrollments', { query: toQuery() })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.enrollment.loadError'),
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
  filters.cohort_id = null
  filters.course_id = null
  filters.class_section_id = null
  filters.source = null
  page.value = 1
  loadSectionOptions()
  load()
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

async function onCourseFilterChange() {
  filters.class_section_id = null
  await loadSectionOptions()
}

async function openManual() {
  Object.assign(manualForm, {
    term_id: filters.term_id,
    course_id: filters.course_id,
    class_section_id: filters.class_section_id,
    user_ids: [],
  })
  await loadManualSections()
  manualOpen.value = true
}

async function submitManual() {
  if (!manualForm.course_id || !manualForm.user_ids.length) {
    toast.add({ severity: 'warn', summary: t('admin.enrollment.enrollEmpty'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const res = await useApi<{ created: number, skipped: number }>('/admin/academic/enrollments/manual', {
      method: 'POST',
      body: {
        course_id: manualForm.course_id,
        class_section_id: manualForm.class_section_id || null,
        user_ids: manualForm.user_ids,
        term_id: manualForm.term_id || null,
      },
    })
    toast.add({
      severity: 'success',
      summary: t('admin.enrollment.enrollSuccess', { created: res.created ?? 0, skipped: res.skipped ?? 0 }),
      life: 3000,
    })
    manualOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.enrollment.enrollError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function csvEscape(value: unknown) {
  const v = String(value ?? '')
  return /["\n,]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v
}

function triggerDownload(content: string, filename: string) {
  const blob = new Blob(['\uFEFF' + content], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

function downloadEnrollmentTemplate() {
  const rows = [
    ['Mã sinh viên', 'Mã học phần (ID hoặc từ khóa tên môn)'],
    ['SV0001', 'INT1234'],
    ['SV0002', 'Lập trình Web'],
  ]
  triggerDownload(rows.map(r => r.map(csvEscape).join(',')).join('\r\n'), 'mau_ghi_danh_hoc_phan.csv')
}

async function exportEnrollmentsCsv() {
  exporting.value = true
  try {
    const baseQuery = { ...toQuery() } as Record<string, any>
    delete baseQuery.page
    baseQuery.per_page = 100

    const all: EnrollmentRow[] = []
    let currentPage = 1
    let lastPage = 1
    do {
      const res = await useApi<Paginator<EnrollmentRow>>('/admin/academic/enrollments', {
        query: { ...baseQuery, page: currentPage },
      })
      all.push(...(res.data || []))
      lastPage = res.last_page || Math.max(1, Math.ceil((res.total || 0) / 100))
      currentPage++
    } while (currentPage <= lastPage && currentPage <= 50)

    if (!all.length) {
      toast.add({ severity: 'warn', summary: t('admin.enrollment.exportEmpty'), life: 2500 })
      return
    }

    const header = [
      t('admin.enrollment.student'),
      'Email',
      t('admin.enrollment.course'),
      t('admin.enrollment.section'),
      t('admin.enrollment.term'),
      t('admin.enrollment.source'),
      t('admin.enrollment.enrolledAt'),
    ]
    const lines = [header.map(csvEscape).join(',')]
    for (const row of all) {
      lines.push([
        row.user?.student_code || '',
        row.user?.name || '',
        row.user?.email || '',
        row.course?.title || '',
        sectionOf(row)?.code || '',
        row.term?.name || '',
        sourceLabel(row.enrollment_source),
        fmtDate(row.enrolled_at),
      ].map(csvEscape).join(','))
    }

    triggerDownload(lines.join('\r\n'), `ghi_danh_hoc_phan_${new Date().toISOString().slice(0, 10)}.csv`)
    toast.add({ severity: 'success', summary: t('admin.enrollment.exportSuccess', { n: all.length }), life: 2500 })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.enrollment.exportError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    exporting.value = false
  }
}

function openImportDialog() {
  resetImportState()
  importOpen.value = true
}

function resetImportState() {
  importFile.value = null
  importPreviewResult.value = null
  importTermId.value = filters.term_id
  if (importFileInput.value) importFileInput.value.value = ''
}

function onImportDrop(event: DragEvent) {
  importDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) void selectImportFile(file)
}

function onImportFilePicked(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) void selectImportFile(file)
}

async function selectImportFile(file: File) {
  if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
    toast.add({ severity: 'warn', summary: t('admin.enrollment.importCsvOnly'), life: 2500 })
    return
  }
  importFile.value = file
  importPreviewResult.value = null
  await runImportPreview()
}

function clearImportFile() {
  importFile.value = null
  importPreviewResult.value = null
  if (importFileInput.value) importFileInput.value.value = ''
}

async function runImportPreview() {
  if (!importFile.value) return
  importPreviewing.value = true
  try {
    const formData = new FormData()
    formData.append('file', importFile.value)
    const res = await useApi<ImportPreviewResponse>('/admin/academic/enrollments/import-preview', {
      method: 'POST',
      body: formData,
    })
    importPreviewResult.value = res
    toast.add({
      severity: res.invalid_rows ? 'warn' : 'success',
      summary: t('admin.enrollment.importPreviewReady'),
      detail: t('admin.enrollment.importPreviewSummary', { valid: res.valid_rows, invalid: res.invalid_rows }),
      life: 3500,
    })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.enrollment.importPreviewError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    importPreviewing.value = false
  }
}

async function runImportExecute() {
  if (!importPreviewResult.value?.import_token) return
  importExecuting.value = true
  try {
    const res = await useApi<{ created: number }>('/admin/academic/enrollments/import-execute', {
      method: 'POST',
      body: {
        import_token: importPreviewResult.value.import_token,
        term_id: importTermId.value || null,
      },
    })
    toast.add({ severity: 'success', summary: t('admin.enrollment.importSuccess', { n: res.created ?? 0 }), life: 3200 })
    importOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.enrollment.importError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    importExecuting.value = false
  }
}

function askDelete(row: EnrollmentRow) {
  confirm.require({
    message: t('admin.enrollment.deleteConfirm', {
      name: row.user?.name || '—',
      course: row.course?.title || '—',
    }),
    header: t('admin.enrollment.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi('/admin/academic/enrollments/delete', {
          method: 'POST',
          body: { enrollment_ids: [row.id] },
        })
        toast.add({ severity: 'success', summary: t('admin.enrollment.deleted'), life: 2200 })
        selected.value = selected.value.filter(item => item.id !== row.id)
        await load()
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.enrollment.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

function askBulkDelete() {
  if (!selected.value.length) return
  confirm.require({
    message: t('admin.enrollment.bulkDeleteConfirm', { n: selected.value.length }),
    header: t('admin.enrollment.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        const res = await useApi<{ deleted: number }>('/admin/academic/enrollments/delete', {
          method: 'POST',
          body: { enrollment_ids: selected.value.map(r => r.id) },
        })
        toast.add({
          severity: 'success',
          summary: t('admin.enrollment.bulkDeleted', { n: res.deleted ?? selected.value.length }),
          life: 2500,
        })
        selected.value = []
        await load()
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.enrollment.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

watch(() => manualForm.course_id, () => { void loadManualSections() })
watch(() => manualForm.term_id, () => { void loadManualSections() })

onMounted(async () => {
  await Promise.all([loadOptions(), loadSectionOptions(), load()])
})
</script>

<template>
  <div class="page enrollment-page">

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.enrollment.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.enrollment.term') }}</span>
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
            <span>{{ t('admin.enrollment.cohort') }}</span>
            <Select
              v-model="filters.cohort_id"
              :options="[{ label: t('common.all'), value: null }, ...cohortOptions]"
              option-label="label"
              option-value="value"
              filter
              show-clear
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.enrollment.course') }}</span>
            <Select
              v-model="filters.course_id"
              :options="[{ label: t('common.all'), value: null }, ...courseOptions]"
              option-label="label"
              option-value="value"
              filter
              show-clear
              class="w-full"
              @change="onCourseFilterChange"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.enrollment.section') }}</span>
            <Select
              v-model="filters.class_section_id"
              :options="[{ label: t('common.all'), value: null }, ...sectionOptions]"
              option-label="label"
              option-value="value"
              filter
              show-clear
              :disabled="!filters.course_id && !sectionOptions.length"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.enrollment.source') }}</span>
            <Select
              v-model="filters.source"
              :options="sourceOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.enrollment.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.enrollment.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.enrollment.searchPh')" @input="onTableSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button
            v-if="selected.length"
            :label="t('admin.users.bulkDelete', { n: selected.length })"
            icon="pi pi-trash"
            severity="danger"
            outlined
            size="small"
            @click="askBulkDelete"
          />
          <Button
            :label="t('admin.enrollment.importCsv')"
            icon="pi pi-upload"
            severity="secondary"
            outlined
            size="small"
            @click="openImportDialog"
          />
          <Button
            :label="t('admin.enrollment.exportCsv')"
            icon="pi pi-download"
            severity="secondary"
            outlined
            size="small"
            :loading="exporting"
            @click="exportEnrollmentsCsv"
          />
          <Button :label="t('admin.enrollment.manualEnroll')" icon="pi pi-user-plus" size="small" @click="openManual" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable
        v-model:selection="selected"
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        selection-mode="multiple"
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[10, 15, 25, 50]"
        @page="onPage"
      >
        <Column selection-mode="multiple" header-style="width:3rem" />
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column :header="t('admin.enrollment.student')" style="min-width:200px">
          <template #body="{ data }">
            <div>
              <strong>{{ data.user?.name || '—' }}</strong>
              <small>{{ data.user?.student_code || data.user?.email || '—' }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.enrollment.course')" style="min-width:160px">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column :header="t('admin.enrollment.section')" style="min-width:100px">
          <template #body="{ data }">{{ sectionOf(data)?.code || t('admin.enrollment.noSection') }}</template>
        </Column>
        <Column :header="t('admin.enrollment.term')" style="min-width:110px">
          <template #body="{ data }">{{ data.term?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.enrollment.source')" style="width:120px">
          <template #body="{ data }">
            <span class="pill" :class="sourceTone(data.enrollment_source)">{{ sourceLabel(data.enrollment_source) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.enrollment.orderId')" style="width:100px">
          <template #body="{ data }">
            <code v-if="data.order_id">#{{ data.order_id }}</code>
            <span v-else>{{ t('admin.enrollment.noOrder') }}</span>
          </template>
        </Column>
        <Column :header="t('admin.enrollment.enrolledAt')" style="width:110px">
          <template #body="{ data }">{{ fmtDate(data.enrolled_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:4rem">
          <template #body="{ data }">
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="manualOpen"
      modal
      :header="t('admin.enrollment.manualTitle')"
      :style="{ width: 'min(640px, 96vw)' }"
    >
      <div class="form">
        <label class="field">
          <span>{{ t('admin.enrollment.term') }}</span>
          <Select v-model="manualForm.term_id" :options="termOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.enrollment.course') }} *</span>
          <Select v-model="manualForm.course_id" :options="courseOptions" option-label="label" option-value="value" filter class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.enrollment.sectionOptional') }}</span>
          <Select
            v-model="manualForm.class_section_id"
            :options="manualSectionOptions"
            option-label="label"
            option-value="value"
            filter
            show-clear
            :disabled="!manualForm.course_id"
            class="w-full"
          />
        </label>
        <label class="field full">
          <span>{{ t('admin.enrollment.students') }} *</span>
          <MultiSelect
            v-model="manualForm.user_ids"
            :options="studentOptions"
            option-label="label"
            option-value="value"
            filter
            display="chip"
            :placeholder="t('admin.enrollment.studentsPh')"
            class="w-full"
          />
          <small>{{ t('admin.enrollment.studentsSelected', { n: manualForm.user_ids.length }) }}</small>
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="manualOpen = false" />
        <Button :label="t('admin.enrollment.manualEnroll')" icon="pi pi-check" :loading="saving" @click="submitManual" />
      </template>
    </Dialog>

    <!-- Import CSV modal -->
    <Dialog
      v-model:visible="importOpen"
      modal
      :header="t('admin.enrollment.importTitle')"
      :style="{ width: 'min(760px, 96vw)' }"
      :dismissable-mask="true"
    >
      <div class="import-box">
        <div class="import-step">
          <div class="step-head">
            <span class="step-num">1</span>
            <div>
              <strong>{{ t('admin.enrollment.importStepTemplate') }}</strong>
              <p>{{ t('admin.enrollment.importStepTemplateHint') }}</p>
            </div>
          </div>
          <Button
            :label="t('admin.enrollment.downloadTemplate')"
            icon="pi pi-download"
            severity="secondary"
            outlined
            @click="downloadEnrollmentTemplate"
          />
        </div>

        <div class="import-step">
          <div class="step-head">
            <span class="step-num">2</span>
            <div>
              <strong>{{ t('admin.enrollment.importStepUpload') }}</strong>
              <p>{{ t('admin.enrollment.importStepUploadHint') }}</p>
            </div>
          </div>

          <div
            class="dropzone"
            :class="{ dragging: importDragging, hasFile: !!importFile }"
            @dragenter.prevent="importDragging = true"
            @dragover.prevent="importDragging = true"
            @dragleave.prevent="importDragging = false"
            @drop.prevent="onImportDrop"
            @click="importFileInput?.click()"
          >
            <input ref="importFileInput" type="file" accept=".csv,text/csv" hidden @change="onImportFilePicked">
            <i class="pi pi-cloud-upload" />
            <strong>{{ t('admin.enrollment.importDropTitle') }}</strong>
            <span>{{ t('admin.enrollment.importDropHint') }}</span>
          </div>

          <div v-if="importFile" class="file-card">
            <div class="file-card-main">
              <i class="pi pi-file" />
              <div>
                <strong>{{ importFile.name }}</strong>
                <small>{{ (importFile.size / 1024).toFixed(1) }} KB</small>
              </div>
            </div>
            <Button icon="pi pi-times" text rounded severity="secondary" @click.stop="clearImportFile" />
          </div>

          <label class="field">
            <span>{{ t('admin.enrollment.importTermLabel') }}</span>
            <Select v-model="importTermId" :options="termOptions" option-label="label" option-value="value" filter show-clear class="w-full" />
          </label>
        </div>

        <div v-if="importPreviewResult || importPreviewing" class="import-step">
          <div class="step-head">
            <span class="step-num">3</span>
            <div>
              <strong>{{ t('admin.enrollment.importStepResult') }}</strong>
              <p>{{ t('admin.enrollment.importStepResultHint') }}</p>
            </div>
          </div>
          <div v-if="importPreviewing" class="checking">
            <ProgressSpinner style="width:28px;height:28px" stroke-width="4" />
            <span>{{ t('admin.enrollment.importChecking') }}</span>
          </div>
          <template v-else-if="importPreviewResult">
            <div class="preview-meta">
              <Tag :value="t('admin.enrollment.importTotalRows', { n: importPreviewResult.total_rows })" severity="info" />
              <Tag :value="t('admin.enrollment.importValidRows', { n: importPreviewResult.valid_rows })" severity="success" />
              <Tag :value="t('admin.enrollment.importInvalidRows', { n: importPreviewResult.invalid_rows })" severity="danger" />
            </div>
            <DataTable
              v-if="importPreviewResult.preview_data?.length"
              :value="importPreviewResult.preview_data.slice(0, 60)"
              data-key="row_number"
              size="small"
              class="preview-table"
              scrollable
              scroll-height="240px"
            >
              <Column field="row_number" header="#" style="width:3rem" />
              <Column field="student_code" :header="t('admin.enrollment.student')" />
              <Column field="student_name" :header="t('admin.users.name')" />
              <Column field="course_code" :header="t('admin.enrollment.course')" class="hidden md:table-cell" />
              <Column :header="t('admin.enrollment.importStatus')">
                <template #body="{ data }">
                  <Tag :value="data.message" :severity="data.status === 'valid' ? 'success' : 'danger'" />
                </template>
              </Column>
            </DataTable>
          </template>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="importOpen = false" />
        <Button
          :label="t('admin.enrollment.importExecuteBtn', { n: importPreviewResult?.valid_rows ?? 0 })"
          icon="pi pi-check"
          :disabled="!importPreviewResult?.import_token || !importPreviewResult?.valid_rows"
          :loading="importExecuting"
          @click="runImportExecute"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.enrollment-page { gap: 14px; }

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
.tone-b2c { background: #fef3c7; color: #a16207; }
.tone-academic { background: #dbeafe; color: #1d4ed8; }
.tone-manual { background: #ede9fe; color: #6d28d9; }
.tone-import { background: #ccfbf1; color: #0f766e; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }

.import-box { display: grid; gap: 16px; }
.import-step {
  display: grid; gap: 10px; padding: 14px; border: 1px solid var(--border);
  border-radius: 12px; background: var(--surface-subtle);
}
.step-head { display: flex; gap: 12px; align-items: flex-start; }
.step-num {
  display: grid; place-items: center; width: 28px; height: 28px; flex: 0 0 28px;
  border-radius: 999px; background: var(--brand); color: #fff; font-size: .8rem; font-weight: 700;
}
.step-head strong { display: block; font-size: .92rem; }
.step-head p { margin: 2px 0 0; color: var(--text-muted); font-size: .8rem; }

.dropzone {
  display: grid; place-items: center; gap: 6px; min-height: 120px; padding: 20px;
  border: 1.5px dashed var(--border-strong); border-radius: 12px;
  background: color-mix(in srgb, var(--surface) 80%, transparent);
  text-align: center; cursor: pointer; transition: .15s ease;
}
.dropzone:hover, .dropzone.dragging {
  border-color: var(--brand); background: var(--brand-soft);
}
.dropzone i { font-size: 1.6rem; color: var(--brand); }
.dropzone strong { font-size: .95rem; }
.dropzone span { color: var(--text-muted); font-size: .8rem; }

.file-card {
  display: grid; grid-template-columns: 1fr auto; gap: 8px 10px; align-items: center;
  padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface);
}
.file-card-main { display: flex; align-items: center; gap: 10px; min-width: 0; }
.file-card-main i { color: var(--brand); }
.file-card-main strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-card-main small { color: var(--text-muted); font-size: .75rem; }

.checking { display: flex; align-items: center; gap: 10px; color: var(--text-muted); }
.preview-meta { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
