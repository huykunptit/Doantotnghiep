<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface RoleRef { id?: number, name: string }
interface NamedRef { id: number, name?: string, code?: string }
interface AdminUser {
  id: number
  name: string
  email: string
  avatar?: string | null
  face_url?: string | null
  phone?: string | null
  student_code?: string | null
  staff_code?: string | null
  gender?: string | null
  date_of_birth?: string | null
  study_status?: string | null
  email_verified_at?: string | null
  created_at?: string
  roles?: RoleRef[]
  administrative_class?: NamedRef | null
  administrativeClass?: NamedRef | null
  cohort?: NamedRef | null
  program?: NamedRef | null
  major?: NamedRef | null
  unit?: NamedRef | null
  unit_id?: number | null
  program_id?: number | null
  major_id?: number | null
  cohort_id?: number | null
  administrative_class_id?: number | null
  bio?: string | null
  hometown?: string | null
  permanent_address?: string | null
  nationality?: string | null
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
  per_page: number
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const exporting = ref(false)
const saving = ref(false)
const importing = ref(false)
const rows = ref<AdminUser[]>([])
const total = ref(0)
const selected = ref<AdminUser[]>([])
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')
const sortBy = ref('created_at')
const sortDir = ref<'asc' | 'desc'>('desc')

const filters = reactive({
  cohort_id: [] as number[],
  administrative_class_id: [] as Array<number | string>,
  program_id: [] as number[],
  major_id: [] as number[],
  unit_id: [] as number[],
})

/** Role chip quick filter (metrics), not in filter bar */
const roleChip = ref<string | null>(null)

const counts = reactive({ all: 0, student: 0, instructor: 0, admin: 0 })
const cohortOptions = ref<{ label: string, value: number }[]>([])
const classOptions = ref<{ label: string, value: number | string }[]>([])
const programOptions = ref<{ label: string, value: number }[]>([])
const majorOptions = ref<{ label: string, value: number }[]>([])
const unitOptions = ref<{ label: string, value: number }[]>([])

type ModalMode = 'view' | 'create' | 'edit'
const modalOpen = ref(false)
const modalMode = ref<ModalMode>('create')
const editing = ref<AdminUser | null>(null)

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'student',
  phone: '',
  student_code: '',
  staff_code: '',
  gender: null as string | null,
  date_of_birth: null as Date | null,
  study_status: null as string | null,
  cohort_id: null as number | null,
  administrative_class_id: null as number | null,
  program_id: null as number | null,
  major_id: null as number | null,
  unit_id: null as number | null,
  bio: '',
  avatar: '' as string | null,
  face_url: '' as string | null,
})

const uploadingAvatar = ref(false)
const avatarInput = ref<HTMLInputElement | null>(null)
const uploadingFace = ref(false)
const faceInput = ref<HTMLInputElement | null>(null)

const importOpen = ref(false)
const importFile = ref<File | null>(null)
const importPreview = ref<any>(null)
const importToken = ref<string | null>(null)
const importProgress = ref(0)
const importDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const faceImportOpen = ref(false)
const faceImportFiles = ref<File[]>([])
const faceImporting = ref(false)
const faceImportResult = ref<{ updated: any[], skipped: any[] } | null>(null)
const faceFileInput = ref<HTMLInputElement | null>(null)

const roleOptions = computed(() => [
  { label: t('admin.users.roles.student'), value: 'student' },
  { label: t('admin.users.roles.instructor'), value: 'instructor' },
  { label: t('admin.users.roles.admin'), value: 'admin' },
])

const studyStatusOptions = computed(() => [
  { label: t('admin.users.status.dang_hoc'), value: 'dang_hoc' },
  { label: t('admin.users.status.bao_luu'), value: 'bao_luu' },
  { label: t('admin.users.status.tot_nghiep'), value: 'tot_nghiep' },
  { label: t('admin.users.status.thoi_hoc'), value: 'thoi_hoc' },
  { label: t('admin.users.status.dinh_chi'), value: 'dinh_chi' },
  { label: t('admin.users.status.chua_dong_hoc_phi'), value: 'chua_dong_hoc_phi' },
  { label: t('admin.users.status.dang_cong_tac'), value: 'dang_cong_tac' },
  { label: t('admin.users.status.nghi_phep'), value: 'nghi_phep' },
  { label: t('admin.users.status.nghi_huu'), value: 'nghi_huu' },
])

const genderOptions = computed(() => [
  { label: t('admin.users.gender.male'), value: 'male' },
  { label: t('admin.users.gender.female'), value: 'female' },
  { label: t('admin.users.gender.other'), value: 'other' },
])

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.cohort_id.length) n++
  if (filters.administrative_class_id.length) n++
  if (filters.program_id.length) n++
  if (filters.major_id.length) n++
  if (filters.unit_id.length) n++
  return n
})

const modalTitle = computed(() => {
  if (modalMode.value === 'view') return t('admin.users.view')
  if (modalMode.value === 'edit') return t('admin.users.edit')
  return t('admin.users.add')
})

const isReadonly = computed(() => modalMode.value === 'view')

const importFileMeta = computed(() => {
  if (!importFile.value) return null
  const size = importFile.value.size
  const kb = size / 1024
  const sizeLabel = kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(kb))} KB`
  return { name: importFile.value.name, sizeLabel }
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

function roleOf(user: AdminUser) {
  return user.roles?.[0]?.name || '—'
}

function roleLabel(user: AdminUser) {
  const role = roleOf(user)
  if (role === '—') return '—'
  return t(`admin.users.roles.${role}`)
}

function roleTone(role: string) {
  const map: Record<string, string> = {
    admin: 'tone-admin',
    instructor: 'tone-instructor',
    student: 'tone-student',
    academic_manager: 'tone-academic',
    advisor: 'tone-advisor',
    finance: 'tone-finance',
  }
  return map[role] || 'tone-neutral'
}

function statusTone(status?: string | null) {
  if (!status) return 'tone-neutral'
  const map: Record<string, string> = {
    dang_hoc: 'tone-active',
    bao_luu: 'tone-deferred',
    tot_nghiep: 'tone-graduated',
    thoi_hoc: 'tone-dropped',
    dinh_chi: 'tone-suspended',
    chua_dong_hoc_phi: 'tone-unpaid',
    dang_cong_tac: 'tone-staff',
    nghi_phep: 'tone-leave',
    nghi_huu: 'tone-retired',
  }
  return map[status] || 'tone-neutral'
}

function named(ref?: NamedRef | null) {
  if (!ref) return '—'
  return ref.code ? `${ref.code} — ${ref.name || ''}` : (ref.name || '—')
}

function classOf(user: AdminUser) {
  return named(user.administrativeClass || user.administrative_class)
}

function rowIndex(index: number) {
  return (page.value - 1) * perPage.value + index + 1
}

function toQuery() {
  return {
    page: page.value,
    per_page: perPage.value,
    q: tableSearch.value || undefined,
    role: roleChip.value || undefined,
    cohort_id: filters.cohort_id.length ? filters.cohort_id : undefined,
    administrative_class_id: filters.administrative_class_id.length ? filters.administrative_class_id : undefined,
    program_id: filters.program_id.length ? filters.program_id : undefined,
    major_id: filters.major_id.length ? filters.major_id : undefined,
    unit_id: filters.unit_id.length ? filters.unit_id : undefined,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
  }
}

async function loadCounts() {
  try {
    const [all, student, instructor, admin] = await Promise.all([
      useApi<Paginator<AdminUser>>('/admin/users', { query: { per_page: 1 } }),
      useApi<Paginator<AdminUser>>('/admin/users', { query: { per_page: 1, role: 'student' } }),
      useApi<Paginator<AdminUser>>('/admin/users', { query: { per_page: 1, role: 'instructor' } }),
      useApi<Paginator<AdminUser>>('/admin/users', { query: { per_page: 1, role: 'admin' } }),
    ])
    counts.all = all.total || 0
    counts.student = student.total || 0
    counts.instructor = instructor.total || 0
    counts.admin = admin.total || 0
  }
  catch { /* ignore */ }
}

async function loadOptions() {
  const map = (res: any) => (res?.data || []).map((item: any) => ({
    label: item.code ? `${item.code} — ${item.name}` : item.name,
    value: item.id,
  }))
  try {
    const [cohorts, classes, programs, majors, units] = await Promise.all([
      useApi<any>('/admin/academic/cohorts', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/administrative-classes', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/programs', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/majors', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<any>('/admin/academic/units', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
    ])
    cohortOptions.value = map(cohorts)
    classOptions.value = [{ label: t('admin.users.noClass'), value: 'none' }, ...map(classes)]
    programOptions.value = map(programs)
    majorOptions.value = map(majors)
    unitOptions.value = map(units)
  }
  catch { /* ignore */ }
}

async function loadUsers() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AdminUser>>('/admin/users', { query: toQuery() })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.users.loadError'),
      detail: error?.data?.message || t('admin.dashboard.tryAgain'),
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.cohort_id = []
  filters.administrative_class_id = []
  filters.program_id = []
  filters.major_id = []
  filters.unit_id = []
  page.value = 1
  loadUsers()
}

function applyFilters() {
  page.value = 1
  loadUsers()
}

function onTableSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadUsers()
  }, 350)
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  loadUsers()
}

function onSort(event: { sortField?: string | ((item: any) => string) | undefined, sortOrder?: number | null | undefined }) {
  const field = typeof event.sortField === 'string' ? event.sortField : null
  if (!field) return
  sortBy.value = field
  sortDir.value = event.sortOrder === 1 ? 'asc' : 'desc'
  page.value = 1
  loadUsers()
}

function resetForm() {
  Object.assign(form, {
    name: '', email: '', password: '', role: 'student', phone: '',
    student_code: '', staff_code: '', gender: null, date_of_birth: null,
    study_status: 'dang_hoc', cohort_id: null, administrative_class_id: null,
    program_id: null, major_id: null, unit_id: null, bio: '', avatar: '',
    face_url: '',
  })
}

function fillForm(user: AdminUser) {
  Object.assign(form, {
    name: user.name || '',
    email: user.email || '',
    password: '',
    role: roleOf(user) === '—' ? 'student' : roleOf(user),
    phone: user.phone || '',
    student_code: user.student_code || '',
    staff_code: user.staff_code || '',
    gender: user.gender || null,
    date_of_birth: user.date_of_birth ? new Date(user.date_of_birth) : null,
    study_status: user.study_status || null,
    cohort_id: user.cohort_id || user.cohort?.id || null,
    administrative_class_id: user.administrative_class_id || user.administrativeClass?.id || user.administrative_class?.id || null,
    program_id: user.program_id || user.program?.id || null,
    major_id: user.major_id || user.major?.id || null,
    unit_id: user.unit_id || user.unit?.id || null,
    bio: user.bio || '',
    avatar: user.avatar || '',
    face_url: user.face_url || '',
  })
}

function openCreate() {
  editing.value = null
  modalMode.value = 'create'
  resetForm()
  modalOpen.value = true
}

function openEdit(user: AdminUser) {
  editing.value = user
  modalMode.value = 'edit'
  fillForm(user)
  modalOpen.value = true
}

function openView(user: AdminUser) {
  editing.value = user
  modalMode.value = 'view'
  fillForm(user)
  modalOpen.value = true
}

function payloadFromForm() {
  return {
    name: form.name,
    email: form.email,
    password: form.password || undefined,
    role: form.role,
    phone: form.phone || null,
    student_code: form.student_code || null,
    staff_code: form.staff_code || null,
    gender: form.gender,
    date_of_birth: form.date_of_birth ? form.date_of_birth.toISOString().slice(0, 10) : null,
    study_status: form.study_status,
    cohort_id: form.cohort_id,
    administrative_class_id: form.administrative_class_id,
    program_id: form.program_id,
    major_id: form.major_id,
    unit_id: form.unit_id,
    bio: form.bio || null,
    avatar: form.avatar || null,
    face_url: form.face_url || null,
  }
}

async function onAvatarPick(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    toast.add({ severity: 'warn', summary: t('admin.users.avatarImageOnly'), life: 2500 })
    return
  }
  uploadingAvatar.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder', 'users')
    const res = await useApi<{ url?: string, path?: string }>('/admin/upload', { method: 'POST', body: fd })
    form.avatar = res.url || res.path || ''
    toast.add({ severity: 'success', summary: t('admin.users.avatarUpdated'), life: 2000 })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.users.avatarError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    uploadingAvatar.value = false
    input.value = ''
  }
}

function clearAvatar() {
  form.avatar = ''
}

async function onFacePick(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    toast.add({ severity: 'warn', summary: t('admin.users.avatarImageOnly'), life: 2500 })
    return
  }
  uploadingFace.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder', 'faces')
    const res = await useApi<{ url?: string, path?: string }>('/admin/upload', { method: 'POST', body: fd })
    form.face_url = res.url || res.path || ''
    toast.add({ severity: 'success', summary: t('admin.users.faceUpdated'), life: 2000 })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.users.faceError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    uploadingFace.value = false
    input.value = ''
  }
}

function clearFace() {
  form.face_url = ''
}

async function saveUser() {
  if (isReadonly.value) return
  saving.value = true
  try {
    if (editing.value && modalMode.value === 'edit') {
      await useApi(`/admin/users/${editing.value.id}`, { method: 'PUT', body: payloadFromForm() })
      toast.add({ severity: 'success', summary: t('admin.users.updateSuccess'), life: 2500 })
    }
    else {
      if (!form.password || form.password.length < 6) {
        toast.add({ severity: 'warn', summary: t('admin.users.passwordRequired'), life: 2500 })
        return
      }
      await useApi('/admin/users', { method: 'POST', body: payloadFromForm() })
      toast.add({ severity: 'success', summary: t('admin.users.createSuccess'), life: 2500 })
    }
    modalOpen.value = false
    await Promise.all([loadUsers(), loadCounts()])
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.users.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0] || t('admin.dashboard.tryAgain'),
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(user: AdminUser) {
  confirm.require({
    message: t('admin.users.deleteConfirm', { name: user.name }),
    header: t('admin.users.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/users/${user.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.users.deleteSuccess'), life: 2500 })
        selected.value = selected.value.filter(item => item.id !== user.id)
        await Promise.all([loadUsers(), loadCounts()])
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.users.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

function askBulkDelete() {
  if (!selected.value.length) return
  confirm.require({
    message: t('admin.users.bulkDeleteConfirm', { n: selected.value.length }),
    header: t('admin.users.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        const res = await useApi<{ deleted: number, skipped: any[] }>('/admin/users/bulk-delete', {
          method: 'POST',
          body: { ids: selected.value.map(u => u.id) },
        })
        toast.add({
          severity: 'success',
          summary: t('admin.users.bulkDeleted', { n: res.deleted }),
          detail: res.skipped?.length ? t('admin.users.bulkSkipped', { n: res.skipped.length }) : undefined,
          life: 3500,
        })
        selected.value = []
        await Promise.all([loadUsers(), loadCounts()])
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.users.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

async function exportCsv() {
  exporting.value = true
  try {
    const query = { ...toQuery() } as Record<string, any>
    delete query.page
    delete query.per_page
    await useApiDownload('/admin/users/export', {
      query,
      filename: `users_${new Date().toISOString().slice(0, 10)}.csv`,
    })
    toast.add({ severity: 'success', summary: t('admin.users.exported'), life: 2500 })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.users.exportError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    exporting.value = false
  }
}

async function downloadTemplate() {
  await useApiDownload('/admin/users/import-template', { filename: 'users_import_template.csv' })
}

function openImport() {
  importOpen.value = true
  clearImportFile()
}

function clearImportFile() {
  importFile.value = null
  importPreview.value = null
  importToken.value = null
  importProgress.value = 0
  if (fileInput.value) fileInput.value.value = ''
}

function onDrop(event: DragEvent) {
  importDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) void handleImportFile(file)
}

function onFilePicked(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) void handleImportFile(file)
}

async function handleImportFile(file: File) {
  if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
    toast.add({ severity: 'warn', summary: t('admin.users.csvOnly'), life: 2500 })
    return
  }
  importFile.value = file
  importPreview.value = null
  importToken.value = null
  importProgress.value = 0

  // Simulated local read progress for UX feedback, then auto-validate on server
  await new Promise<void>((resolve) => {
    const step = () => {
      importProgress.value = Math.min(100, importProgress.value + 18)
      if (importProgress.value >= 100) resolve()
      else setTimeout(step, 40)
    }
    step()
  })

  await runImportPreview()
}

async function runImportPreview() {
  if (!importFile.value) return
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)
    const res = await useApi<any>('/admin/users/import-preview', { method: 'POST', body: fd })
    importPreview.value = res
    importToken.value = res.import_token
    toast.add({
      severity: res.error_count ? 'warn' : 'success',
      summary: t('admin.users.previewReady'),
      detail: t('admin.users.previewSummary', { valid: res.valid_count || 0, error: res.error_count || 0 }),
      life: 3500,
    })
  }
  catch (error: any) {
    importPreview.value = error?.data || null
    importToken.value = error?.data?.import_token || null
    toast.add({
      severity: 'warn',
      summary: t('admin.users.previewError'),
      detail: error?.data?.message,
      life: 4000,
    })
  }
  finally {
    importing.value = false
  }
}

async function runImportExecute() {
  if (!importToken.value) return
  importing.value = true
  try {
    const res = await useApi<{ created: number }>('/admin/users/import-execute', {
      method: 'POST',
      body: { import_token: importToken.value },
    })
    toast.add({ severity: 'success', summary: t('admin.users.imported', { n: res.created }), life: 3000 })
    importOpen.value = false
    clearImportFile()
    await Promise.all([loadUsers(), loadCounts()])
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('admin.users.importError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    importing.value = false
  }
}

function openFaceImport() {
  faceImportOpen.value = true
  faceImportFiles.value = []
  faceImportResult.value = null
  if (faceFileInput.value) faceFileInput.value.value = ''
}

function onFaceFilesPicked(event: Event) {
  const input = event.target as HTMLInputElement
  faceImportFiles.value = Array.from(input.files || [])
  faceImportResult.value = null
}

async function runFaceImport() {
  if (!faceImportFiles.value.length) return
  faceImporting.value = true
  try {
    const fd = new FormData()
    faceImportFiles.value.forEach(file => fd.append('files[]', file))
    const res = await useApi<{ message: string, updated: any[], skipped: any[] }>('/admin/users/import-faces', {
      method: 'POST',
      body: fd,
    })
    faceImportResult.value = { updated: res.updated || [], skipped: res.skipped || [] }
    toast.add({ severity: 'success', summary: res.message, life: 3500 })
    if (res.updated?.length) await loadUsers()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.users.faceImportError'),
      detail: error?.data?.message,
      life: 4000,
    })
  }
  finally {
    faceImporting.value = false
  }
}

function setRoleChip(role: string | null) {
  roleChip.value = role
  page.value = 1
  loadUsers()
}

onMounted(async () => {
  await Promise.all([loadOptions(), loadCounts(), loadUsers()])
})
</script>

<template>
  <div class="page users-page">

    <section class="metric-rail">
      <button type="button" class="metric" :class="{ on: !roleChip }" @click="setRoleChip(null)">
        <strong>{{ counts.all }}</strong>
        <span>{{ t('admin.users.allUsers') }}</span>
      </button>
      <button type="button" class="metric" :class="{ on: roleChip === 'student' }" @click="setRoleChip('student')">
        <strong>{{ counts.student }}</strong>
        <span>{{ t('admin.users.roles.student') }}</span>
      </button>
      <button type="button" class="metric" :class="{ on: roleChip === 'instructor' }" @click="setRoleChip('instructor')">
        <strong>{{ counts.instructor }}</strong>
        <span>{{ t('admin.users.roles.instructor') }}</span>
      </button>
      <button type="button" class="metric" :class="{ on: roleChip === 'admin' }" @click="setRoleChip('admin')">
        <strong>{{ counts.admin }}</strong>
        <span>{{ t('admin.users.roles.admin') }}</span>
      </button>
    </section>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.users.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>

        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.users.class') }}</span>
            <MultiSelect
              v-model="filters.administrative_class_id"
              :options="classOptions"
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
            <span>{{ t('admin.users.cohort') }}</span>
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
            <span>{{ t('admin.users.program') }}</span>
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
            <span>{{ t('admin.users.major') }}</span>
            <MultiSelect
              v-model="filters.major_id"
              :options="majorOptions"
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
            <span>{{ t('admin.users.unit') }}</span>
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
        </div>

        <div class="filter-actions">
          <Button :label="t('admin.users.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.users.reset')" icon="pi pi-times" size="small" severity="secondary" text @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <div class="toolbar-left">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="tableSearch"
              :placeholder="t('admin.users.tableSearch')"
              @input="onTableSearch"
            />
          </IconField>
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
        </div>
        <div class="toolbar-actions">
          <Button
            v-if="selected.length"
            :label="t('admin.users.bulkDelete', { n: selected.length })"
            icon="pi pi-trash"
            severity="danger"
            outlined
            size="small"
            @click="askBulkDelete"
          />
          <Button :label="t('admin.users.import')" icon="pi pi-upload" severity="secondary" outlined size="small" @click="openImport" />
          <Button :label="t('admin.users.importFaces')" icon="pi pi-id-card" severity="secondary" outlined size="small" @click="openFaceImport" />
          <Button :label="t('admin.users.export')" icon="pi pi-download" severity="secondary" outlined size="small" :loading="exporting" @click="exportCsv" />
          <Button :label="t('admin.users.add')" icon="pi pi-user-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="loadUsers" />
        </div>
      </div>

      <DataTable
        v-model:selection="selected"
        :value="rows"
        data-key="id"
        :loading="loading"
        :paginator="true"
        lazy
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[10, 15, 25, 50]"
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        :current-page-report-template="t('admin.users.pageReport')"
        selection-mode="multiple"
        striped-rows
        sort-mode="single"
        removable-sort
        class="users-table"
        @page="onPage"
        @sort="onSort"
      >
        <Column selection-mode="multiple" header-style="width:3rem" />
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ rowIndex(index) }}</template>
        </Column>
        <Column field="name" :header="t('admin.users.colUser')" sortable style="min-width:220px">
          <template #body="{ data }">
            <div class="user-cell">
              <Avatar v-if="resolveMediaUrl(data.avatar)" :image="resolveMediaUrl(data.avatar)" shape="circle" />
              <Avatar v-else :label="(data.name || '?').slice(0, 1).toUpperCase()" shape="circle" />
              <div>
                <button type="button" class="name-link" @click="openView(data)">{{ data.name }}</button>
                <small>{{ data.email }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.users.role')" style="min-width:120px">
          <template #body="{ data }">
            <span class="pill" :class="roleTone(roleOf(data))">{{ roleLabel(data) }}</span>
          </template>
        </Column>
        <Column field="student_code" :header="t('admin.users.code')" sortable style="min-width:110px">
          <template #body="{ data }">{{ data.student_code || data.staff_code || '—' }}</template>
        </Column>
        <Column :header="t('admin.users.class')" style="min-width:120px">
          <template #body="{ data }">{{ classOf(data) }}</template>
        </Column>
        <Column field="study_status" :header="t('admin.users.studyStatus')" sortable style="min-width:120px">
          <template #body="{ data }">
            <span v-if="data.study_status" class="pill" :class="statusTone(data.study_status)">
              {{ t(`admin.users.status.${data.study_status}`) }}
            </span>
            <span v-else>—</span>
          </template>
        </Column>
        <Column field="created_at" :header="t('admin.users.createdAt')" sortable style="min-width:110px">
          <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:9.5rem">
          <template #body="{ data }">
            <Button icon="pi pi-eye" text rounded severity="secondary" :aria-label="t('admin.users.view')" @click="openView(data)" />
            <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="t('admin.users.edit')" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" :aria-label="t('admin.users.deleteTitle')" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>

    <!-- View / Create / Edit modal (center) -->
    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="modalTitle"
      :style="{ width: 'min(760px, 96vw)' }"
      :dismissable-mask="true"
      class="user-modal"
    >
      <div class="modal-grid" :class="{ readonly: isReadonly }">
        <div class="avatar-block full">
          <Avatar
            v-if="resolveMediaUrl(form.avatar)"
            :image="resolveMediaUrl(form.avatar)"
            shape="circle"
            size="xlarge"
            class="avatar-preview"
          />
          <Avatar
            v-else
            :label="(form.name || '?').slice(0, 1).toUpperCase()"
            shape="circle"
            size="xlarge"
            class="avatar-preview"
          />
          <div class="avatar-meta">
            <strong>{{ t('admin.users.avatar') }}</strong>
            <span>{{ t('admin.users.avatarHint') }}</span>
            <div v-if="!isReadonly" class="avatar-actions">
              <input ref="avatarInput" type="file" accept="image/*" hidden @change="onAvatarPick">
              <Button
                :label="t('admin.users.changeAvatar')"
                icon="pi pi-camera"
                size="small"
                severity="secondary"
                outlined
                :loading="uploadingAvatar"
                @click="avatarInput?.click()"
              />
              <Button
                v-if="form.avatar"
                :label="t('admin.users.removeAvatar')"
                icon="pi pi-times"
                size="small"
                severity="danger"
                text
                @click="clearAvatar"
              />
            </div>
          </div>
        </div>

        <div class="avatar-block full">
          <Avatar
            v-if="form.face_url"
            :image="form.face_url"
            shape="circle"
            size="xlarge"
            class="avatar-preview"
          />
          <Avatar v-else icon="pi pi-id-card" shape="circle" size="xlarge" class="avatar-preview" />
          <div class="avatar-meta">
            <strong>{{ t('admin.users.faceLabel') }}</strong>
            <span>{{ t('admin.users.faceHint') }}</span>
            <div v-if="!isReadonly" class="avatar-actions">
              <input ref="faceInput" type="file" accept="image/*" hidden @change="onFacePick">
              <Button
                :label="t('admin.users.changeFace')"
                icon="pi pi-camera"
                size="small"
                severity="secondary"
                outlined
                :loading="uploadingFace"
                @click="faceInput?.click()"
              />
              <Button
                v-if="form.face_url"
                :label="t('admin.users.removeFace')"
                icon="pi pi-times"
                size="small"
                severity="danger"
                text
                @click="clearFace"
              />
            </div>
          </div>
        </div>

        <label class="field"><span>{{ t('admin.users.name') }}</span><InputText v-model="form.name" class="w-full" :disabled="isReadonly" /></label>
        <label class="field"><span>{{ t('admin.users.email') }}</span><InputText v-model="form.email" type="email" class="w-full" :disabled="isReadonly" /></label>
        <label v-if="!isReadonly" class="field">
          <span>{{ modalMode === 'edit' ? t('admin.users.passwordOptional') : t('admin.users.password') }}</span>
          <Password v-model="form.password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.role') }}</span>
          <Select v-model="form.role" :options="roleOptions" option-label="label" option-value="value" class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field"><span>{{ t('admin.users.studentCode') }}</span><InputText v-model="form.student_code" class="w-full" :disabled="isReadonly" /></label>
        <label class="field"><span>{{ t('admin.users.staffCode') }}</span><InputText v-model="form.staff_code" class="w-full" :disabled="isReadonly" /></label>
        <label class="field"><span>{{ t('admin.users.phone') }}</span><InputText v-model="form.phone" class="w-full" :disabled="isReadonly" /></label>
        <label class="field">
          <span>{{ t('admin.users.genderLabel') }}</span>
          <Select v-model="form.gender" :options="genderOptions" option-label="label" option-value="value" show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.dob') }}</span>
          <DatePicker v-model="form.date_of_birth" date-format="dd/mm/yy" show-icon class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.studyStatus') }}</span>
          <Select v-model="form.study_status" :options="studyStatusOptions" option-label="label" option-value="value" show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.cohort') }}</span>
          <Select v-model="form.cohort_id" :options="cohortOptions" option-label="label" option-value="value" filter show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.class') }}</span>
          <Select v-model="form.administrative_class_id" :options="classOptions.filter(o => o.value !== 'none')" option-label="label" option-value="value" filter show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.program') }}</span>
          <Select v-model="form.program_id" :options="programOptions" option-label="label" option-value="value" filter show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field">
          <span>{{ t('admin.users.major') }}</span>
          <Select v-model="form.major_id" :options="majorOptions" option-label="label" option-value="value" filter show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field full">
          <span>{{ t('admin.users.unit') }}</span>
          <Select v-model="form.unit_id" :options="unitOptions" option-label="label" option-value="value" filter show-clear class="w-full" :disabled="isReadonly" />
        </label>
        <label class="field full bio-field">
          <span>{{ t('admin.users.bio') }}</span>
          <CommonRichTextEditor v-model="form.bio" height="180px" :readonly="isReadonly" />
        </label>
      </div>
      <template #footer>
        <div class="modal-foot">
          <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
          <template v-if="isReadonly">
            <Button :label="t('admin.users.edit')" icon="pi pi-pencil" @click="modalMode = 'edit'" />
          </template>
          <Button v-else :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveUser" />
        </div>
      </template>
    </Dialog>

    <!-- Import modal -->
    <Dialog v-model:visible="importOpen" modal :header="t('admin.users.importTitle')" :style="{ width: 'min(760px, 96vw)' }" :dismissable-mask="true" class="import-modal">
      <div class="import-box">
        <div class="import-step">
          <div class="step-head">
            <span class="step-num">1</span>
            <div>
              <strong>{{ t('admin.users.stepTemplate') }}</strong>
              <p>{{ t('admin.users.stepTemplateHint') }}</p>
            </div>
          </div>
          <Button :label="t('admin.users.downloadTemplate')" icon="pi pi-download" severity="secondary" outlined @click="downloadTemplate" />
        </div>

        <div class="import-step">
          <div class="step-head">
            <span class="step-num">2</span>
            <div>
              <strong>{{ t('admin.users.stepUpload') }}</strong>
              <p>{{ t('admin.users.stepUploadHint') }}</p>
            </div>
          </div>

          <div
            class="dropzone"
            :class="{ dragging: importDragging, hasFile: !!importFile }"
            @dragenter.prevent="importDragging = true"
            @dragover.prevent="importDragging = true"
            @dragleave.prevent="importDragging = false"
            @drop.prevent="onDrop"
            @click="fileInput?.click()"
          >
            <input ref="fileInput" type="file" accept=".csv,text/csv" hidden @change="onFilePicked">
            <i class="pi pi-cloud-upload" />
            <strong>{{ t('admin.users.dropTitle') }}</strong>
            <span>{{ t('admin.users.dropHint') }}</span>
          </div>

          <div v-if="importFileMeta" class="file-card">
            <div class="file-card-main">
              <i class="pi pi-file" />
              <div>
                <strong>{{ importFileMeta.name }}</strong>
                <small>{{ importFileMeta.sizeLabel }}</small>
              </div>
            </div>
            <Button icon="pi pi-times" text rounded severity="secondary" @click.stop="clearImportFile" />
            <ProgressBar :value="importProgress" :show-value="true" class="file-progress" />
          </div>
        </div>

        <div v-if="importPreview || importing" class="import-step">
          <div class="step-head">
            <span class="step-num">3</span>
            <div>
              <strong>{{ t('admin.users.stepResult') }}</strong>
              <p>{{ t('admin.users.stepResultHint') }}</p>
            </div>
          </div>
          <div v-if="importing && !importPreview" class="checking">
            <ProgressSpinner style="width:28px;height:28px" stroke-width="4" />
            <span>{{ t('admin.users.checking') }}</span>
          </div>
          <div v-if="importPreview" class="preview-meta">
            <Tag :value="t('admin.users.validCount', { n: importPreview.valid_count || 0 })" severity="success" />
            <Tag :value="t('admin.users.errorCount', { n: importPreview.error_count || 0 })" severity="danger" />
          </div>
          <DataTable v-if="importPreview?.rows?.length" :value="importPreview.rows.slice(0, 40)" size="small" class="preview-table" scrollable scroll-height="240px">
            <Column field="row" header="#" style="width:3rem" />
            <Column field="name" :header="t('admin.users.name')" />
            <Column field="email" :header="t('admin.users.email')" />
            <Column field="role" :header="t('admin.users.role')" />
            <Column :header="t('admin.users.errors')">
              <template #body="{ data }">
                <span :class="{ bad: data.errors?.length }">{{ data.errors?.length ? data.errors.join(', ') : 'OK' }}</span>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="importOpen = false" />
        <Button :label="t('admin.users.confirmImport')" icon="pi pi-check" :disabled="!importToken" :loading="importing" @click="runImportExecute" />
      </template>
    </Dialog>

    <!-- Face photo bulk import modal -->
    <Dialog v-model:visible="faceImportOpen" modal :header="t('admin.users.importFacesTitle')" :style="{ width: 'min(680px, 96vw)' }" :dismissable-mask="true" class="import-modal">
      <div class="import-box">
        <div class="import-step">
          <div class="step-head">
            <span class="step-num">1</span>
            <div>
              <strong>{{ t('admin.users.faceImportStepName') }}</strong>
              <p>{{ t('admin.users.faceImportStepNameHint') }}</p>
            </div>
          </div>
        </div>

        <div class="import-step">
          <div class="step-head">
            <span class="step-num">2</span>
            <div>
              <strong>{{ t('admin.users.faceImportStepPick') }}</strong>
              <p>{{ t('admin.users.faceImportStepPickHint') }}</p>
            </div>
          </div>
          <div class="dropzone" @click="faceFileInput?.click()">
            <input ref="faceFileInput" type="file" accept="image/*" multiple hidden @change="onFaceFilesPicked">
            <i class="pi pi-images" />
            <strong>{{ t('admin.users.faceImportPickTitle') }}</strong>
            <span v-if="faceImportFiles.length">{{ t('admin.users.faceImportSelected', { n: faceImportFiles.length }) }}</span>
            <span v-else>{{ t('admin.users.faceImportDropHint') }}</span>
          </div>
        </div>

        <div v-if="faceImportResult" class="import-step">
          <div class="step-head">
            <span class="step-num">3</span>
            <div>
              <strong>{{ t('admin.users.stepResult') }}</strong>
            </div>
          </div>
          <div class="preview-meta">
            <Tag :value="t('admin.users.faceImportUpdated', { n: faceImportResult.updated.length })" severity="success" />
            <Tag v-if="faceImportResult.skipped.length" :value="t('admin.users.faceImportSkipped', { n: faceImportResult.skipped.length })" severity="warn" />
          </div>
          <ul v-if="faceImportResult.skipped.length" class="skip-list">
            <li v-for="(s, i) in faceImportResult.skipped" :key="i">{{ s.filename }} — {{ s.reason }}</li>
          </ul>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="faceImportOpen = false" />
        <Button
          :label="t('admin.users.faceImportConfirm')"
          icon="pi pi-check"
          :disabled="!faceImportFiles.length"
          :loading="faceImporting"
          @click="runFaceImport"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.users-page { gap: 14px; }

.metric-rail { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.metric {
  display: flex; flex-direction: column; gap: 2px; align-items: flex-start;
  min-height: 72px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
  color: var(--text); font: inherit; text-align: left; cursor: pointer;
}
.metric strong { font-family: var(--font-display); font-size: 1.35rem; font-weight: 700; }
.metric span { color: var(--text-muted); font-size: .78rem; font-weight: 600; }
.metric.on {
  border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
  background: var(--brand-soft);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 20%, transparent);
}

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px; overflow: hidden;
}

.filter-bar {
  margin-bottom: 12px; padding: 12px; border: 1px solid var(--border);
  border-radius: 12px; background: var(--surface-subtle);
}
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-title strong { font-size: .92rem; }
.filter-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px 12px;
}
.filter-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 12px; }

.field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.w-full { width: 100%; }

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toolbar-left strong { font-size: .92rem; white-space: nowrap; }
.toolbar-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.user-cell { display: flex; align-items: center; gap: 10px; }
.user-cell small { display: block; color: var(--text-muted); font-size: .78rem; }
.name-link {
  border: 0; background: none; padding: 0; color: var(--text);
  font: inherit; font-weight: 700; cursor: pointer;
}
.name-link:hover { color: var(--brand); }

.pill {
  display: inline-flex; align-items: center; max-width: 100%;
  padding: 3px 9px; border-radius: 999px; font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.tone-admin { background: #fee2e2; color: #b91c1c; }
.tone-instructor { background: #ffedd5; color: #c2410c; }
.tone-student { background: #dbeafe; color: #1d4ed8; }
.tone-academic { background: #ede9fe; color: #6d28d9; }
.tone-advisor { background: #e0e7ff; color: #4338ca; }
.tone-finance { background: #fef3c7; color: #a16207; }
.tone-active { background: #dcfce7; color: #15803d; }
.tone-deferred { background: #fef9c3; color: #a16207; }
.tone-graduated { background: #e0f2fe; color: #0369a1; }
.tone-dropped { background: #ffe4e6; color: #be123c; }
.tone-suspended { background: #fce7f3; color: #be185d; }
.tone-unpaid { background: #ffedd5; color: #c2410c; }
.tone-staff { background: #ccfbf1; color: #0f766e; }
.tone-leave { background: #fde68a; color: #92400e; }
.tone-retired { background: #e2e8f0; color: #475569; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.modal-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.modal-grid .full { grid-column: 1 / -1; }
.modal-foot { display: flex; justify-content: flex-end; gap: 8px; width: 100%; }

.avatar-block {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface-subtle);
}
.avatar-preview { flex: 0 0 auto; }
.avatar-meta { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.avatar-meta strong { font-size: .92rem; }
.avatar-meta > span { color: var(--text-muted); font-size: .78rem; }
.avatar-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }

.bio-field :deep(.p-textarea),
.bio-input {
  width: 100% !important;
  min-height: 84px;
  resize: vertical;
  box-shadow: none !important;
}
.bio-field :deep(.p-textarea:enabled:focus) {
  border-color: var(--brand) !important;
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--brand) 35%, transparent) !important;
}

.user-modal :deep(.p-dialog-content),
.import-modal :deep(.p-dialog-content) { overflow: auto; }

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
  display: grid; place-items: center; gap: 6px; min-height: 140px; padding: 20px;
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
.file-progress { grid-column: 1 / -1; height: 8px; }

.checking { display: flex; align-items: center; gap: 10px; color: var(--text-muted); }
.preview-meta { display: flex; gap: 8px; flex-wrap: wrap; }
.preview-table .bad { color: var(--danger); font-size: .8rem; }
.skip-list { margin: 8px 0 0; padding-left: 18px; color: var(--text-muted); font-size: .82rem; display: grid; gap: 3px; max-height: 160px; overflow: auto; }

:deep(.filter-bar .p-multiselect),
:deep(.filter-bar .p-select),
:deep(.filter-bar .p-inputtext),
:deep(.modal-grid .p-select),
:deep(.modal-grid .p-password),
:deep(.modal-grid .p-datepicker) { width: 100%; }

@media (max-width: 1100px) {
  .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .metric-rail { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 720px) {
  .filter-grid, .modal-grid, .metric-rail { grid-template-columns: 1fr; }
}
</style>
