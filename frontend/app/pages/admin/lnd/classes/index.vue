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

const { t, locale } = useI18n()
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
const termOptions = ref<{ label: string, value: number }[]>([])

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

interface AdminImportPreviewRow {
  row_number: number
  student_code: string
  student_name: string | null
  status: 'valid' | 'invalid'
  message: string
}

interface AdminImportPreviewResponse {
  import_token: string
  total_rows: number
  valid_rows: number
  invalid_rows: number
  preview_data: AdminImportPreviewRow[]
}

const enrollOpen = ref(false)
const enrollClass = ref<AdminClass | null>(null)
const enrollMode = ref<'select' | 'import'>('select')
const enrollStudentIds = ref<number[]>([])
const enrollSaving = ref(false)

const importFile = ref<File | null>(null)
const importDragging = ref(false)
const importPreviewing = ref(false)
const importExecuting = ref(false)
const importPreviewResult = ref<AdminImportPreviewResponse | null>(null)
const importFileInput = ref<HTMLInputElement | null>(null)

const bulkImportOpen = ref(false)
const bulkImportFile = ref<File | null>(null)
const bulkImportDragging = ref(false)
const bulkImportPreviewing = ref(false)
const bulkImportExecuting = ref(false)
const bulkImportPreviewResult = ref<AdminImportPreviewResponse | null>(null)
const bulkImportFileInput = ref<HTMLInputElement | null>(null)

interface TermMapTermOption {
  id: number
  code?: string
  name?: string
  label: string
  start_date?: string | null
  end_date?: string | null
}
interface TermMapYearOption {
  id: number
  name: string
  start_date?: string | null
  end_date?: string | null
  terms: TermMapTermOption[]
}
interface TermMapRow {
  term_number: number
  academic_year_id: number | null
  term_id: number | null
  suggested_term_id?: number | null
  suggested_academic_year_id?: number | null
  suggested_label?: string | null
  start_date?: string | null
  end_date?: string | null
}

const curriculumEnrollOpen = ref(false)
const curriculumEnrollClass = ref<AdminClass | null>(null)
const termMap = ref<TermMapRow[]>([])
const termMapYears = ref<TermMapYearOption[]>([])
const termMapMeta = ref<{ start_year?: number | null, end_year?: number | null, auto_filled?: boolean }>({})
const termMapLoading = ref(false)
const termMapSaving = ref(false)
const curriculumEnrollSaving = ref(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const rowMenu = ref()
const rowMenuTarget = ref<AdminClass | null>(null)
const rowMenuItems = computed(() => {
  const item = rowMenuTarget.value
  if (!item) return []
  return [
    { label: t('admin.classes.viewDetail'), icon: 'pi pi-eye', command: () => navigateTo(`/admin/lnd/classes/${item.id}`) },
    { label: t('admin.classes.enroll'), icon: 'pi pi-user-plus', command: () => openEnroll(item) },
    { label: t('admin.classes.enrollCurriculum'), icon: 'pi pi-graduation-cap', command: () => openCurriculumEnroll(item) },
    { label: t('admin.classes.edit'), icon: 'pi pi-pencil', command: () => openEdit(item) },
    { separator: true },
    { label: t('admin.classes.delete'), icon: 'pi pi-trash', class: 'danger-item', command: () => askDelete(item) },
  ]
})

function toggleRowMenu(event: Event, item: AdminClass) {
  rowMenuTarget.value = item
  rowMenu.value?.toggle(event)
}

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
    const [cohorts, programs, units, majors, curricula, advisors, students, terms] = await Promise.all([
      useApi<any>('/admin/academic/cohorts', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/programs', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/units', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/majors', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/curricula', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/instructors', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/students', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/terms', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
    ])
    cohortOptions.value = mapOptions(cohorts.data)
    programOptions.value = mapOptions(programs.data)
    unitOptions.value = mapOptions(units.data)
    majorOptions.value = mapOptions(majors.data)
    curriculumOptions.value = mapOptions(curricula.data)
    termOptions.value = (terms.data || []).map((item: any) => ({
      label: item.academic_year?.name
        ? `${item.code || item.name} — ${item.academic_year.name}`
        : (item.code ? `${item.code} — ${item.name}` : item.name),
      value: item.id,
    }))
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
  resetImportState()
  enrollOpen.value = true
}

function resetImportState() {
  importFile.value = null
  importPreviewResult.value = null
  if (importFileInput.value) importFileInput.value.value = ''
}

async function submitEnroll() {
  if (!enrollClass.value) return

  const studentIds = [...enrollStudentIds.value]
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

function csvEscape(value: unknown) {
  const v = String(value ?? '')
  return /["\n,]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v
}

function downloadEnrollImportTemplate() {
  const rows = [
    ['Mã sinh viên'],
    ['SV0001'],
    ['SV0002'],
  ]
  const content = rows.map(r => r.map(csvEscape).join(',')).join('\r\n')
  const blob = new Blob(['﻿' + content], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'mau_ghi_danh_lop_hanh_chinh.csv'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
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
    toast.add({ severity: 'warn', summary: t('admin.classes.importCsvOnly'), life: 2500 })
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
  if (!importFile.value || !enrollClass.value) return
  importPreviewing.value = true
  try {
    const formData = new FormData()
    formData.append('file', importFile.value)
    formData.append('administrative_class_id', String(enrollClass.value.id))
    const res = await useApi<AdminImportPreviewResponse>('/admin/academic/administrative-classes/import-students-preview', {
      method: 'POST',
      body: formData,
    })
    importPreviewResult.value = res
    toast.add({
      severity: res.invalid_rows ? 'warn' : 'success',
      summary: t('admin.classes.importPreviewReady'),
      detail: t('admin.classes.importPreviewSummary', { valid: res.valid_rows, invalid: res.invalid_rows }),
      life: 3500,
    })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.importPreviewError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    importPreviewing.value = false
  }
}

async function runImportExecute() {
  if (!importPreviewResult.value?.import_token) return
  importExecuting.value = true
  try {
    const res = await useApi<{ created: number }>('/admin/academic/administrative-classes/import-students-execute', {
      method: 'POST',
      body: { import_token: importPreviewResult.value.import_token },
    })
    toast.add({ severity: 'success', summary: t('admin.classes.importSuccess', { n: res.created ?? 0 }), life: 3200 })
    enrollOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.importError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    importExecuting.value = false
  }
}

function openBulkImport() {
  bulkImportFile.value = null
  bulkImportPreviewResult.value = null
  if (bulkImportFileInput.value) bulkImportFileInput.value.value = ''
  bulkImportOpen.value = true
}

function downloadBulkImportTemplate() {
  const rows = [
    ['Mã sinh viên', 'Mã lớp'],
    ['SV0001', 'D23CN01'],
    ['SV0002', 'D23CN01'],
  ]
  const content = rows.map(r => r.map(csvEscape).join(',')).join('\r\n')
  const blob = new Blob(['﻿' + content], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'mau_import_hang_loat_lop_hanh_chinh.csv'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

function onBulkImportDrop(event: DragEvent) {
  bulkImportDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) void selectBulkImportFile(file)
}

function onBulkImportFilePicked(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) void selectBulkImportFile(file)
}

async function selectBulkImportFile(file: File) {
  if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
    toast.add({ severity: 'warn', summary: t('admin.classes.importCsvOnly'), life: 2500 })
    return
  }
  bulkImportFile.value = file
  bulkImportPreviewResult.value = null
  await runBulkImportPreview()
}

function clearBulkImportFile() {
  bulkImportFile.value = null
  bulkImportPreviewResult.value = null
  if (bulkImportFileInput.value) bulkImportFileInput.value.value = ''
}

async function runBulkImportPreview() {
  if (!bulkImportFile.value) return
  bulkImportPreviewing.value = true
  try {
    const formData = new FormData()
    formData.append('file', bulkImportFile.value)
    const res = await useApi<AdminImportPreviewResponse>('/admin/academic/administrative-classes/bulk-import-preview', {
      method: 'POST',
      body: formData,
    })
    bulkImportPreviewResult.value = res
    toast.add({
      severity: res.invalid_rows ? 'warn' : 'success',
      summary: t('admin.classes.importPreviewReady'),
      detail: t('admin.classes.importPreviewSummary', { valid: res.valid_rows, invalid: res.invalid_rows }),
      life: 3500,
    })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.importPreviewError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    bulkImportPreviewing.value = false
  }
}

async function runBulkImportExecute() {
  if (!bulkImportPreviewResult.value?.import_token) return
  bulkImportExecuting.value = true
  try {
    const res = await useApi<{ created: number }>('/admin/academic/administrative-classes/bulk-import-execute', {
      method: 'POST',
      body: { import_token: bulkImportPreviewResult.value.import_token },
    })
    toast.add({ severity: 'success', summary: t('admin.classes.bulkImportSuccess', { n: res.created ?? 0 }), life: 3200 })
    bulkImportOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.bulkImportError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    bulkImportExecuting.value = false
  }
}

async function openCurriculumEnroll(item: AdminClass) {
  if (!item.curriculum?.id) {
    toast.add({ severity: 'warn', summary: t('admin.classes.enrollCurriculumNoCurriculum'), life: 3500 })
    return
  }
  curriculumEnrollClass.value = item
  curriculumEnrollOpen.value = true
  await loadTermMap()
}

async function loadTermMap() {
  if (!curriculumEnrollClass.value) return
  termMapLoading.value = true
  try {
    const res = await useApi<{
      term_count: number
      map: TermMapRow[]
      years?: TermMapYearOption[]
      start_year?: number | null
      end_year?: number | null
      auto_filled?: boolean
    }>(
      `/admin/academic/administrative-classes/${curriculumEnrollClass.value.id}/term-map`,
    )
    termMapYears.value = res.years || []
    termMap.value = (res.map || []).map((row) => {
      const termId = row.term_id ?? row.suggested_term_id ?? null
      const yearId = row.academic_year_id
        ?? row.suggested_academic_year_id
        ?? findYearIdByTerm(termId)
      const dates = termDates(yearId, termId)
      return {
        term_number: row.term_number,
        academic_year_id: yearId,
        term_id: termId,
        suggested_term_id: row.suggested_term_id ?? null,
        suggested_academic_year_id: row.suggested_academic_year_id ?? null,
        suggested_label: row.suggested_label ?? null,
        start_date: dates.start_date,
        end_date: dates.end_date,
      }
    })
    termMapMeta.value = {
      start_year: res.start_year ?? null,
      end_year: res.end_year ?? null,
      auto_filled: !!res.auto_filled,
    }
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.termMapLoadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    termMapLoading.value = false
  }
}

function findYearIdByTerm(termId: number | null | undefined): number | null {
  if (!termId) return null
  for (const year of termMapYears.value) {
    if (year.terms.some(term => term.id === termId)) return year.id
  }
  return null
}

function termsForYear(yearId: number | null | undefined): TermMapTermOption[] {
  if (!yearId) return []
  return termMapYears.value.find(y => y.id === yearId)?.terms || []
}

function termDates(yearId: number | null | undefined, termId: number | null | undefined) {
  const term = termsForYear(yearId).find(item => item.id === termId)
  return {
    start_date: term?.start_date || null,
    end_date: term?.end_date || null,
  }
}

function fmtTermDate(value?: string | null) {
  if (!value) return '—'
  const d = new Date(`${value}T00:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'vi-VN')
}

function onTermMapYearChange(row: TermMapRow) {
  const options = termsForYear(row.academic_year_id)
  if (!options.some(term => term.id === row.term_id)) {
    // Kỳ lẻ → ưu tiên HK1, kỳ chẵn → HK2
    const preferHk1 = row.term_number % 2 === 1
    const picked = options.find(term => preferHk1 ? term.code === 'HK1' : term.code === 'HK2')
      || options[0]
      || null
    row.term_id = picked?.id ?? null
  }
  const dates = termDates(row.academic_year_id, row.term_id)
  row.start_date = dates.start_date
  row.end_date = dates.end_date
}

function onTermMapTermChange(row: TermMapRow) {
  const dates = termDates(row.academic_year_id, row.term_id)
  row.start_date = dates.start_date
  row.end_date = dates.end_date
}

function applySuggestedTermMap() {
  termMap.value = termMap.value.map((row) => {
    const termId = row.suggested_term_id ?? row.term_id
    const yearId = row.suggested_academic_year_id ?? findYearIdByTerm(termId) ?? row.academic_year_id
    const dates = termDates(yearId, termId)
    return {
      ...row,
      academic_year_id: yearId,
      term_id: termId,
      start_date: dates.start_date,
      end_date: dates.end_date,
    }
  })
}

async function saveTermMap() {
  if (!curriculumEnrollClass.value) return
  termMapSaving.value = true
  try {
    await useApi(`/admin/academic/administrative-classes/${curriculumEnrollClass.value.id}/term-map`, {
      method: 'POST',
      body: { map: termMap.value },
    })
    toast.add({ severity: 'success', summary: t('admin.classes.termMapSaved'), life: 2200 })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.classes.termMapSaveError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    termMapSaving.value = false
  }
}

async function submitCurriculumEnroll() {
  if (!curriculumEnrollClass.value) return

  curriculumEnrollSaving.value = true
  try {
    // Ghi danh dùng đúng ánh xạ đang có trên server, nên lưu ánh xạ hiện tại trước.
    await useApi(`/admin/academic/administrative-classes/${curriculumEnrollClass.value.id}/term-map`, {
      method: 'POST',
      body: { map: termMap.value },
    })

    const res = await useApi<{
      created: number
      skipped: number
      courses_skipped_no_term?: number
      missing_term_mapping?: number[]
      message?: string
    }>(
      `/admin/academic/administrative-classes/${curriculumEnrollClass.value.id}/enroll-curriculum`,
      { method: 'POST' },
    )

    toast.add({
      severity: 'success',
      summary: t('admin.classes.enrollCurriculumSuccess', { created: res.created ?? 0, skipped: res.skipped ?? 0 }),
      life: 3500,
    })
    if (res.missing_term_mapping?.length) {
      toast.add({
        severity: 'warn',
        summary: t('admin.classes.enrollCurriculumMissingTerms', { terms: res.missing_term_mapping.join(', ') }),
        life: 6000,
      })
    }
    curriculumEnrollOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.classes.enrollCurriculumError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    curriculumEnrollSaving.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadInstitution(), loadOptions(), load()])
})
</script>

<template>
  <div class="page classes-page">

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
          <Button :label="t('admin.classes.bulkImport')" icon="pi pi-upload" severity="secondary" outlined size="small" @click="openBulkImport" />
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
          <template #body="{ data }">
            <NuxtLink :to="`/admin/lnd/classes/${data.id}`" class="code-link"><code>{{ data.code }}</code></NuxtLink>
          </template>
        </Column>
        <Column field="name" :header="t('admin.classes.name')" style="min-width:200px">
          <template #body="{ data }">
            <div class="name-cell">
              <strong>{{ data.name }}</strong>
              <small>{{ [data.unit?.name, data.cohort?.name].filter(Boolean).join(' · ') || '—' }}</small>
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
        <Column :header="t('admin.users.actions')" style="width:5rem">
          <template #body="{ data }">
            <Button icon="pi pi-ellipsis-v" text rounded severity="secondary" :aria-label="t('admin.users.actions')" @click="toggleRowMenu($event, data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
      <Menu ref="rowMenu" :model="rowMenuItems" popup />
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="editing ? t('admin.classes.edit') : t('admin.classes.add')"
      :style="{ width: 'min(960px, 96vw)' }"
      class="scroll-fix-modal"
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
      :style="{ width: 'min(720px, 96vw)' }"
      class="scroll-fix-modal"
    >
      <div class="enroll-tabs">
        <button type="button" class="enroll-tab" :class="{ on: enrollMode === 'select' }" @click="enrollMode = 'select'">
          {{ t('admin.classes.enrollSelect') }}
        </button>
        <button type="button" class="enroll-tab" :class="{ on: enrollMode === 'import' }" @click="enrollMode = 'import'">
          {{ t('admin.classes.enrollImport') }}
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
      <div v-else class="enroll-body import-box">
        <div class="import-step">
          <div class="step-head">
            <span class="step-num">1</span>
            <div>
              <strong>{{ t('admin.classes.importStepTemplate') }}</strong>
              <p>{{ t('admin.classes.importStepTemplateHint') }}</p>
            </div>
          </div>
          <Button
            :label="t('admin.classes.downloadTemplate')"
            icon="pi pi-download"
            severity="secondary"
            outlined
            @click="downloadEnrollImportTemplate"
          />
        </div>

        <div class="import-step">
          <div class="step-head">
            <span class="step-num">2</span>
            <div>
              <strong>{{ t('admin.classes.importStepUpload') }}</strong>
              <p>{{ t('admin.classes.importStepUploadHint') }}</p>
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
            <strong>{{ t('admin.classes.importDropTitle') }}</strong>
            <span>{{ t('admin.classes.importDropHint') }}</span>
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
        </div>

        <div v-if="importPreviewResult || importPreviewing" class="import-step">
          <div class="step-head">
            <span class="step-num">3</span>
            <div>
              <strong>{{ t('admin.classes.importStepResult') }}</strong>
              <p>{{ t('admin.classes.importStepResultHint') }}</p>
            </div>
          </div>
          <div v-if="importPreviewing" class="checking">
            <ProgressSpinner style="width:28px;height:28px" stroke-width="4" />
            <span>{{ t('admin.classes.importChecking') }}</span>
          </div>
          <template v-else-if="importPreviewResult">
            <div class="preview-meta">
              <Tag :value="t('admin.classes.importTotalRows', { n: importPreviewResult.total_rows })" severity="info" />
              <Tag :value="t('admin.classes.importValidRows', { n: importPreviewResult.valid_rows })" severity="success" />
              <Tag :value="t('admin.classes.importInvalidRows', { n: importPreviewResult.invalid_rows })" severity="danger" />
            </div>
            <DataTable
              v-if="importPreviewResult.preview_data?.length"
              :value="importPreviewResult.preview_data.slice(0, 60)"
              data-key="row_number"
              size="small"
              class="preview-table"
              scrollable
              scroll-height="200px"
            >
              <Column field="row_number" header="#" style="width:3rem" />
              <Column field="student_code" :header="t('admin.classes.enrollStudentCode')" />
              <Column field="student_name" :header="t('admin.users.name')" />
              <Column :header="t('admin.classes.importStatus')">
                <template #body="{ data }">
                  <Tag :value="data.message" :severity="data.status === 'valid' ? 'success' : 'danger'" />
                </template>
              </Column>
            </DataTable>
          </template>
        </div>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="enrollOpen = false" />
        <Button
          v-if="enrollMode === 'select'"
          :label="t('admin.classes.enrollSubmit', { n: enrollStudentIds.length })"
          icon="pi pi-user-plus"
          :loading="enrollSaving"
          @click="submitEnroll"
        />
        <Button
          v-else
          :label="t('admin.classes.importExecuteBtn', { n: importPreviewResult?.valid_rows ?? 0 })"
          icon="pi pi-check"
          :disabled="!importPreviewResult?.import_token || !importPreviewResult?.valid_rows"
          :loading="importExecuting"
          @click="runImportExecute"
        />
      </template>
    </Dialog>

    <!-- Bulk import modal: assign many students into many different classes in one CSV -->
    <Dialog
      v-model:visible="bulkImportOpen"
      modal
      :header="t('admin.classes.bulkImportTitle')"
      :style="{ width: 'min(760px, 96vw)' }"
      :dismissable-mask="true"
      class="scroll-fix-modal"
    >
      <div class="import-box">
        <p class="bulk-hint">{{ t('admin.classes.bulkImportHint') }}</p>

        <div class="import-step">
          <div class="step-head">
            <span class="step-num">1</span>
            <div>
              <strong>{{ t('admin.classes.importStepTemplate') }}</strong>
              <p>{{ t('admin.classes.bulkImportTemplateHint') }}</p>
            </div>
          </div>
          <Button
            :label="t('admin.classes.downloadTemplate')"
            icon="pi pi-download"
            severity="secondary"
            outlined
            @click="downloadBulkImportTemplate"
          />
        </div>

        <div class="import-step">
          <div class="step-head">
            <span class="step-num">2</span>
            <div>
              <strong>{{ t('admin.classes.importStepUpload') }}</strong>
              <p>{{ t('admin.classes.importStepUploadHint') }}</p>
            </div>
          </div>

          <div
            class="dropzone"
            :class="{ dragging: bulkImportDragging, hasFile: !!bulkImportFile }"
            @dragenter.prevent="bulkImportDragging = true"
            @dragover.prevent="bulkImportDragging = true"
            @dragleave.prevent="bulkImportDragging = false"
            @drop.prevent="onBulkImportDrop"
            @click="bulkImportFileInput?.click()"
          >
            <input ref="bulkImportFileInput" type="file" accept=".csv,text/csv" hidden @change="onBulkImportFilePicked">
            <i class="pi pi-cloud-upload" />
            <strong>{{ t('admin.classes.importDropTitle') }}</strong>
            <span>{{ t('admin.classes.importDropHint') }}</span>
          </div>

          <div v-if="bulkImportFile" class="file-card">
            <div class="file-card-main">
              <i class="pi pi-file" />
              <div>
                <strong>{{ bulkImportFile.name }}</strong>
                <small>{{ (bulkImportFile.size / 1024).toFixed(1) }} KB</small>
              </div>
            </div>
            <Button icon="pi pi-times" text rounded severity="secondary" @click.stop="clearBulkImportFile" />
          </div>
        </div>

        <div v-if="bulkImportPreviewResult || bulkImportPreviewing" class="import-step">
          <div class="step-head">
            <span class="step-num">3</span>
            <div>
              <strong>{{ t('admin.classes.importStepResult') }}</strong>
              <p>{{ t('admin.classes.importStepResultHint') }}</p>
            </div>
          </div>
          <div v-if="bulkImportPreviewing" class="checking">
            <ProgressSpinner style="width:28px;height:28px" stroke-width="4" />
            <span>{{ t('admin.classes.importChecking') }}</span>
          </div>
          <template v-else-if="bulkImportPreviewResult">
            <div class="preview-meta">
              <Tag :value="t('admin.classes.importTotalRows', { n: bulkImportPreviewResult.total_rows })" severity="info" />
              <Tag :value="t('admin.classes.importValidRows', { n: bulkImportPreviewResult.valid_rows })" severity="success" />
              <Tag :value="t('admin.classes.importInvalidRows', { n: bulkImportPreviewResult.invalid_rows })" severity="danger" />
            </div>
            <DataTable
              v-if="bulkImportPreviewResult.preview_data?.length"
              :value="bulkImportPreviewResult.preview_data.slice(0, 60)"
              data-key="row_number"
              size="small"
              class="preview-table"
              scrollable
              scroll-height="200px"
            >
              <Column field="row_number" header="#" style="width:3rem" />
              <Column field="student_code" :header="t('admin.classes.enrollStudentCode')" />
              <Column field="student_name" :header="t('admin.users.name')" />
              <Column :header="t('admin.classes.importStatus')">
                <template #body="{ data }">
                  <Tag :value="data.message" :severity="data.status === 'valid' ? 'success' : 'danger'" />
                </template>
              </Column>
            </DataTable>
          </template>
        </div>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="bulkImportOpen = false" />
        <Button
          :label="t('admin.classes.bulkImportExecuteBtn', { n: bulkImportPreviewResult?.valid_rows ?? 0 })"
          icon="pi pi-check"
          :disabled="!bulkImportPreviewResult?.import_token || !bulkImportPreviewResult?.valid_rows"
          :loading="bulkImportExecuting"
          @click="runBulkImportExecute"
        />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="curriculumEnrollOpen"
      modal
      :header="`${t('admin.classes.enrollCurriculumTitle')} — ${curriculumEnrollClass?.name || ''}`"
      :style="{ width: 'min(720px, 96vw)' }"
      class="scroll-fix-modal"
    >
      <div class="enroll-body">
        <small>{{ t('admin.classes.enrollCurriculumHint') }}</small>
        <small v-if="termMapMeta.start_year" class="term-map-auto-hint">
          {{ t('admin.classes.termMapAutoHint', {
            start: termMapMeta.start_year,
            end: termMapMeta.end_year || (Number(termMapMeta.start_year) + 4),
          }) }}
        </small>
        <div class="term-map-calendar-link">
          <Button
            :label="t('admin.classes.termMapOpenCalendar')"
            icon="pi pi-calendar"
            size="small"
            text
            @click="navigateTo('/admin/lnd/academic-calendar')"
          />
        </div>

        <div v-if="termMapLoading" class="term-map-loading">
          <i class="pi pi-spin pi-spinner" />
        </div>
        <div v-else class="term-map-grid">
          <div v-for="row in termMap" :key="row.term_number" class="term-map-row cascaded">
            <span class="term-map-label">{{ t('admin.classes.termMapTermNumber', { n: row.term_number }) }}</span>
            <div class="term-map-fields">
              <Select
                v-model="row.academic_year_id"
                :options="termMapYears"
                option-label="name"
                option-value="id"
                filter
                show-clear
                :placeholder="t('admin.classes.termMapPickYear')"
                class="w-full"
                @update:model-value="onTermMapYearChange(row)"
              />
              <Select
                v-model="row.term_id"
                :options="termsForYear(row.academic_year_id)"
                option-label="label"
                option-value="id"
                filter
                show-clear
                :disabled="!row.academic_year_id"
                :placeholder="t('admin.classes.termMapPickTerm')"
                class="w-full"
                @update:model-value="onTermMapTermChange(row)"
              />
              <small v-if="row.term_id" class="term-map-dates">
                {{ t('admin.classes.termMapDateRange', {
                  start: fmtTermDate(row.start_date),
                  end: fmtTermDate(row.end_date),
                }) }}
              </small>
            </div>
          </div>
          <p v-if="!termMap.length" class="term-map-empty">{{ t('admin.classes.termMapEmpty') }}</p>
        </div>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="curriculumEnrollOpen = false" />
        <Button
          :label="t('admin.classes.termMapAutoApply')"
          severity="secondary"
          text
          icon="pi pi-refresh"
          :disabled="termMapLoading || !termMap.length"
          @click="applySuggestedTermMap"
        />
        <Button
          :label="t('admin.classes.termMapSave')"
          severity="secondary"
          icon="pi pi-save"
          :loading="termMapSaving"
          @click="saveTermMap"
        />
        <Button
          :label="t('admin.classes.enrollCurriculumSubmit')"
          icon="pi pi-graduation-cap"
          :loading="curriculumEnrollSaving"
          @click="submitCurriculumEnroll"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.classes-page { gap: 14px; }

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

.name-cell { display: flex; flex-direction: column; gap: 2px; }
.name-cell small { display: block; color: var(--text-muted); font-size: .78rem; }

:deep(.danger-item .p-menu-item-link) { color: var(--p-red-500, #ef4444); }
:deep(.danger-item .p-menu-item-icon) { color: var(--p-red-500, #ef4444); }

code { font-family: ui-monospace, monospace; font-size: .82rem; font-weight: 700; color: var(--brand); }
.code-link { text-decoration: none; }
.code-link:hover code { text-decoration: underline; }
.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-active { background: #dcfce7; color: #15803d; }
.tone-graduated { background: #e0f2fe; color: #0369a1; }
.tone-suspended { background: #fce7f3; color: #be185d; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.scroll-fix-modal :deep(.p-dialog-content) { overflow: auto; }

.form { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 16px; }
.form .full { grid-column: 1 / -1; }

@media (max-width: 720px) {
  .form { grid-template-columns: 1fr; }
}

.enroll-tabs { display: flex; gap: 6px; margin-bottom: 12px; border-bottom: 1px solid var(--border); }
.enroll-tab {
  border: 0; background: none; padding: 8px 12px; cursor: pointer;
  font: inherit; font-weight: 700; color: var(--text-muted); border-bottom: 2px solid transparent;
}
.enroll-tab.on { color: var(--brand); border-bottom-color: var(--brand); }
.enroll-body { display: grid; gap: 8px; }

.bulk-hint { margin: 0; color: var(--text-muted); font-size: .85rem; }
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

.term-map-grid { display: grid; gap: 8px; max-height: 360px; overflow-y: auto; padding-right: 4px; }
.term-map-row { display: grid; grid-template-columns: 72px 1fr; align-items: start; gap: 10px; }
.term-map-row.cascaded { align-items: start; }
.term-map-fields { display: grid; gap: 8px; }
.term-map-dates { color: var(--text-muted); font-size: .78rem; }
.term-map-label { font-size: .85rem; font-weight: 700; color: var(--text-muted); padding-top: 10px; }
.term-map-loading { display: flex; justify-content: center; padding: 24px; font-size: 1.4rem; color: var(--text-muted); }
.term-map-empty { color: var(--text-muted); font-size: .85rem; margin: 8px 0; }
.term-map-auto-hint { display: block; margin-top: 6px; color: var(--brand, #0f766e); font-weight: 600; }
.term-map-calendar-link { margin-top: 4px; }
</style>
