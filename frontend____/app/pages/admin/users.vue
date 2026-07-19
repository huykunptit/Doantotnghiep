<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import MediaUpload from '~/components/common/MediaUpload.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useExport } from '~/composables/useExport'

// Unified UI Components
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UiImportModal from '~/components/ui/UiImportModal.vue'
import UModal from '~/components/UModal.vue'
import UiSelect from '~/components/ui/UiSelect.vue'

definePageMeta({
  layout: 'admin',
  adminSearchPlaceholder: 'Tìm người dùng, email, MSSV...',
})

const roleOptions = [
  { label: 'Admin', value: 'admin' },
  { label: 'Giảng viên', value: 'instructor' },
  { label: 'Sinh viên', value: 'student' },
  { label: 'Quản lý học vụ', value: 'academic_manager' },
]

const statusOptions = computed(() => {
  return Object.entries(STUDY_STATUS_LABELS).map(([value, label]) => ({ label, value }))
})

const genderOptions = [
  { label: 'Nam', value: 'male' },
  { label: 'Nữ', value: 'female' },
  { label: 'Khác', value: 'other' },
]

const cohortOptions = computed(() => {
  return optCohorts.value.map(c => ({ label: c.name, value: String(c.id) }))
})

const classOptions = computed(() => {
  return optAdminClasses.value.map(c => ({ label: c.name, value: String(c.id) }))
})

interface RoleItem { id: number; name: 'admin' | 'instructor' | 'student' | 'academic_manager' }
interface RelItem { id: number; name: string; code?: string }

interface AdminUser {
  id: number
  name: string
  email: string
  avatar?: string | null
  created_at?: string
  updated_at?: string
  roles?: RoleItem[]
  // Academic fields
  student_code?: string | null
  staff_code?: string | null
  phone?: string | null
  gender?: 'male' | 'female' | 'other' | null
  date_of_birth?: string | null
  nationality?: string | null
  hometown?: string | null
  permanent_address?: string | null
  id_card_number?: string | null
  study_status?: string | null
  bio?: string | null
  // Relations
  administrative_class?: RelItem | null
  administrative_class_id?: number | null
  cohort?: RelItem | null
  cohort_id?: number | null
  program?: RelItem | null
  program_id?: number | null
  major?: RelItem | null
  major_id?: number | null
  unit?: RelItem | null
  unit_id?: number | null
}

interface AcademicSummary {
  overall_gpa: number | null
  total_credits: number
  total_courses: number
  terms: any[]
}

const STUDY_STATUS_LABELS: Record<string, string> = {
  dang_hoc: 'Đang học',
  bao_luu: 'Bảo lưu',
  tot_nghiep: 'Tốt nghiệp',
  thoi_hoc: 'Thôi học',
  dinh_chi: 'Đình chỉ',
  dang_cong_tac: 'Đang công tác',
  nghi_phep: 'Nghỉ phép',
  nghi_huu: 'Nghỉ hưu',
}


const user = useAuthUserCookie()
const token = useAuthTokenCookie()

if (!user.value || !token.value) await navigateTo('/login', { replace: true })
if (user.value && normalizeRole(user.value.role) !== 'admin') await navigateTo(getDashboardPath(user.value.role), { replace: true })

const filters = reactive({
  search: '',
  role: '',
  study_status: '',
  gender: '',
  cohort_id: '',
  program_id: '',
  administrative_class_id: '',
  unit_id: '',
})

const filterOpen = ref(false)

const activeFilterCount = computed(() => {
  return [filters.study_status, filters.gender, filters.cohort_id, filters.program_id, filters.administrative_class_id, filters.unit_id]
    .filter(Boolean).length
})
const users = ref<AdminUser[]>([])
const loading = ref(false)
const saving = ref(false)
const deletingId = ref<number | null>(null)
const errorMessage = ref('')
const successMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalUsers = ref(0)
const perPage = ref(15)
const selectedIds = ref<number[]>([])

// Stats
const statsTotal = ref(0)
const statsStudents = ref(0)
const statsInstructors = ref(0)
const statsAdmins = ref(0)

// Academic summary (loaded per user on view)
const academicSummary = ref<AcademicSummary | null>(null)
const loadingSummary = ref(false)

// Form options for dropdowns
const optAdminClasses = ref<RelItem[]>([])
const optCohorts = ref<RelItem[]>([])
const optPrograms = ref<RelItem[]>([])
const optMajors = ref<RelItem[]>([])
const optUnits = ref<RelItem[]>([])
const optionsLoaded = ref(false)

// Sơ đồ tổ chức state
const unitSearchQuery = ref('')
const filteredUnits = computed(() => {
  if (!unitSearchQuery.value.trim()) return optUnits.value
  const q = unitSearchQuery.value.toLowerCase()
  return optUnits.value.filter(u => u.name.toLowerCase().includes(q))
})

const { exportToCSV } = useExport()

const isAllSelected = computed(() =>
  users.value.length > 0 && users.value.every(u => selectedIds.value.includes(u.id))
)

function toggleSelectAll() {
  isAllSelected.value
    ? (selectedIds.value = [])
    : (selectedIds.value = users.value.map(u => u.id))
}

const modalMode = ref<'create' | 'edit' | 'view'>('create')
const modalOpen = ref(false)
const deleteModalOpen = ref(false)
const selectedUser = ref<AdminUser | null>(null)
const activeSection = ref<'account' | 'academic' | 'personal'>('account')

const form = reactive({
  name: '',
  email: '',
  password: '',
  avatar: '',
  role: 'student',
  bio: '',
  // Academic
  student_code: '',
  staff_code: '',
  administrative_class_id: '',
  cohort_id: '',
  program_id: '',
  major_id: '',
  study_status: '',
  // Personal
  phone: '',
  gender: '',
  date_of_birth: '',
  nationality: 'Việt Nam',
  hometown: '',
  permanent_address: '',
  id_card_number: '',
})

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

function resolveRole(item: AdminUser) {
  return item.roles?.[0]?.name || 'student'
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
}

function avatarInitials(name: string) {
  return name.split(' ').slice(-2).map(p => p.charAt(0)).join('').toUpperCase()
}

function studyStatusBadgeClass(status?: string | null) {
  if (!status) return 'ds-badge ds-badge--draft'
  const map: Record<string, string> = {
    dang_hoc: 'ds-badge ds-badge--active',
    bao_luu: 'ds-badge ds-badge--pending',
    tot_nghiep: 'ds-badge ds-badge--info',
    thoi_hoc: 'ds-badge ds-badge--closed',
    dinh_chi: 'ds-badge ds-badge--closed',
    dang_cong_tac: 'ds-badge ds-badge--active',
    nghi_phep: 'ds-badge ds-badge--pending',
    nghi_huu: 'ds-badge ds-badge--archived',
  }
  return map[status] || 'ds-badge ds-badge--draft'
}

function gpaClassification(gpa: number | null) {
  if (gpa === null) return { label: '—', cls: '' }
  if (gpa >= 3.6) return { label: 'Xuất sắc', cls: 'ds-badge--violet' }
  if (gpa >= 3.2) return { label: 'Giỏi', cls: 'ds-badge--active' }
  if (gpa >= 2.5) return { label: 'Khá', cls: 'ds-badge--info' }
  if (gpa >= 2.0) return { label: 'Trung bình', cls: 'ds-badge--pending' }
  return { label: 'Yếu / Kém', cls: 'ds-badge--closed' }
}

async function fetchUsers(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const query = new URLSearchParams()
    query.set('page', String(page))
    query.set('per_page', String(perPage.value))
    if (filters.search.trim()) query.set('search', filters.search.trim())
    if (filters.role) query.set('role', filters.role)
    if (filters.study_status) query.set('study_status', filters.study_status)
    if (filters.gender) query.set('gender', filters.gender)
    if (filters.cohort_id) query.set('cohort_id', filters.cohort_id)
    if (filters.program_id) query.set('program_id', filters.program_id)
    if (filters.administrative_class_id) query.set('administrative_class_id', filters.administrative_class_id)

    const response = await useApi<any>(`/admin/users?${query.toString()}`, { headers: authHeaders() })
    users.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    totalUsers.value = response.total
    statsTotal.value = response.total
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải danh sách người dùng.'
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const [students, instructors, admins] = await Promise.all([
      useApi<any>('/admin/users?role=student&per_page=1', { headers: authHeaders() }),
      useApi<any>('/admin/users?role=instructor&per_page=1', { headers: authHeaders() }),
      useApi<any>('/admin/users?role=admin&per_page=1', { headers: authHeaders() }),
    ])
    statsStudents.value = students.total
    statsInstructors.value = instructors.total
    statsAdmins.value = admins.total
  } catch (_) {}
}

async function fetchFormOptions() {
  if (optionsLoaded.value) return
  try {
    const headers = authHeaders()
    const [adminClasses, cohorts, programs, majors] = await Promise.all([
      useApi<any>('/admin/academic/administrative-classes?per_page=200', { headers }),
      useApi<any>('/admin/academic/cohorts?per_page=200', { headers }),
      useApi<any>('/admin/academic/programs?per_page=200', { headers }),
      useApi<any>('/admin/academic/majors?per_page=200', { headers }),
    ])
    optAdminClasses.value = adminClasses.data || []
    optCohorts.value = cohorts.data || []
    optPrograms.value = programs.data || []
    optMajors.value = majors.data || []
    optionsLoaded.value = true
  } catch (_) {}
}

async function fetchAcademicSummary(userId: number) {
  academicSummary.value = null
  loadingSummary.value = true
  try {
    const data = await useApi<AcademicSummary>(`/admin/users/${userId}/academic-summary`, { headers: authHeaders() })
    academicSummary.value = data
  } catch (_) {
    academicSummary.value = null
  } finally {
    loadingSummary.value = false
  }
}

function resetForm() {
  form.name = ''; form.email = ''; form.password = ''; form.avatar = ''
  form.role = 'student'; form.bio = ''
  form.student_code = ''; form.staff_code = ''
  form.administrative_class_id = ''; form.cohort_id = ''
  form.program_id = ''; form.major_id = ''
  form.study_status = ''
  form.phone = ''; form.gender = ''; form.date_of_birth = ''
  form.nationality = 'Việt Nam'; form.hometown = ''
  form.permanent_address = ''; form.id_card_number = ''
}

function fillForm(item: AdminUser) {
  form.name = item.name
  form.email = item.email
  form.password = ''
  form.avatar = item.avatar || ''
  form.role = resolveRole(item)
  form.bio = item.bio || ''
  form.student_code = item.student_code || ''
  form.staff_code = item.staff_code || ''
  form.administrative_class_id = item.administrative_class_id ? String(item.administrative_class_id) : ''
  form.cohort_id = item.cohort_id ? String(item.cohort_id) : ''
  form.program_id = item.program_id ? String(item.program_id) : ''
  form.major_id = item.major_id ? String(item.major_id) : ''
  form.study_status = item.study_status || ''
  form.phone = item.phone || ''
  form.gender = item.gender || ''
  form.date_of_birth = item.date_of_birth ? item.date_of_birth.substring(0, 10) : ''
  form.nationality = item.nationality || 'Việt Nam'
  form.hometown = item.hometown || ''
  form.permanent_address = item.permanent_address || ''
  form.id_card_number = item.id_card_number || ''
}

function openCreateModal() {
  modalMode.value = 'create'
  selectedUser.value = null
  activeSection.value = 'account'
  academicSummary.value = null
  resetForm()
  modalOpen.value = true
  fetchFormOptions()
}

function openViewModal(item: AdminUser) {
  modalMode.value = 'view'
  selectedUser.value = item
  activeSection.value = 'account'
  fillForm(item)
  modalOpen.value = true
  if (resolveRole(item) === 'student') fetchAcademicSummary(item.id)
}

function openEditModal(item: AdminUser) {
  modalMode.value = 'edit'
  selectedUser.value = item
  activeSection.value = 'account'
  academicSummary.value = null
  fillForm(item)
  modalOpen.value = true
  fetchFormOptions()
}

function closeModal() {
  modalOpen.value = false
  selectedUser.value = null
  academicSummary.value = null
  errorMessage.value = ''
  successMessage.value = ''
}

function onAvatarUploaded() { successMessage.value = 'Đã tải ảnh đại diện lên.' }
function onAvatarError(message: string) { errorMessage.value = message }

async function saveUser() {
  if (modalMode.value === 'view') return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const payload: Record<string, any> = {
    name: form.name,
    email: form.email,
    avatar: form.avatar || null,
    role: form.role,
    bio: form.bio || null,
    student_code: form.student_code || null,
    staff_code: form.staff_code || null,
    administrative_class_id: form.administrative_class_id ? Number(form.administrative_class_id) : null,
    cohort_id: form.cohort_id ? Number(form.cohort_id) : null,
    program_id: form.program_id ? Number(form.program_id) : null,
    major_id: form.major_id ? Number(form.major_id) : null,
    study_status: form.study_status || null,
    phone: form.phone || null,
    gender: form.gender || null,
    date_of_birth: form.date_of_birth || null,
    nationality: form.nationality || null,
    hometown: form.hometown || null,
    permanent_address: form.permanent_address || null,
    id_card_number: form.id_card_number || null,
  }

  try {
    if (modalMode.value === 'create') {
      payload.password = form.password
      await useApi('/admin/users', { method: 'POST', headers: authHeaders(), body: payload })
      successMessage.value = 'Đã tạo người dùng mới.'
    } else if (selectedUser.value) {
      if (form.password.trim()) payload.password = form.password
      await useApi(`/admin/users/${selectedUser.value.id}`, { method: 'PUT', headers: authHeaders(), body: payload })
      successMessage.value = 'Đã cập nhật người dùng.'
    }
    closeModal()
    await fetchUsers(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể lưu thông tin người dùng.'
  } finally {
    saving.value = false
  }
}

async function deleteUser(item?: AdminUser) {
  if (item) { selectedUser.value = item; deleteModalOpen.value = true; return }
  if (!selectedUser.value) return
  deletingId.value = selectedUser.value.id
  try {
    await useApi(`/admin/users/${selectedUser.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa người dùng.'
    deleteModalOpen.value = false
    await fetchUsers(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa người dùng.'
  } finally {
    deletingId.value = null
  }
}

function exportData() {
  exportToCSV(users.value, [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Họ và tên' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'SĐT' },
    { key: 'student_code', label: 'MSSV' },
    { key: 'role', label: 'Vai trò', format: (_: any, row: AdminUser) => resolveRole(row) },
    { key: 'study_status', label: 'Trạng thái', format: (v: any) => STUDY_STATUS_LABELS[v] || v || '—' },
    { key: 'administrative_class', label: 'Lớp HC', format: (_: any, row: AdminUser) => row.administrative_class?.name || '—' },
    { key: 'cohort', label: 'Khóa', format: (_: any, row: AdminUser) => row.cohort?.name || '—' },
    { key: 'created_at', label: 'Ngày tạo', format: (v: any) => formatDate(v) },
  ], 'danh_sach_nguoi_dung')
}

function resetFilters() {
  filters.search = ''
  filters.role = ''
  filters.study_status = ''
  filters.gender = ''
  filters.cohort_id = ''
  filters.program_id = ''
  filters.administrative_class_id = ''
  fetchUsers(1)
}

onMounted(() => {
  fetchUsers()
  fetchStats()
  fetchFormOptions()
})
</script>

<template>
  <div class="flex flex-col gap-5">

    <!-- ── Page header ── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khu vực quản trị</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Quản lý người dùng</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Thêm, chỉnh sửa, xem hồ sơ học vụ và quản lý tài khoản.</p>
      </div>
      <button
        class="inline-flex items-center gap-2 h-10 px-5 rounded-xl bg-[#1d9e75] hover:bg-[#17876200] text-white text-sm font-semibold transition-colors shrink-0"
        type="button"
        @click="openCreateModal"
      >
        <i class="pi pi-plus" style="font-size:0.875rem" />
        Thêm người dùng
      </button>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="stat in [
        { label: 'Tổng người dùng', value: totalUsers, sub: 'tất cả vai trò', color: '#1d9e75', bg: 'rgba(29,158,117,0.08)', border: 'rgba(29,158,117,0.2)' },
        { label: 'Sinh viên', value: statsStudents, sub: 'đang học', color: '#378add', bg: 'rgba(55,138,221,0.08)', border: 'rgba(55,138,221,0.2)' },
        { label: 'Giảng viên', value: statsInstructors, sub: 'instructor', color: '#f59e0b', bg: 'rgba(245,158,11,0.08)', border: 'rgba(245,158,11,0.2)' },
        { label: 'Quản trị viên', value: statsAdmins, sub: 'admin', color: '#8b5cf6', bg: 'rgba(139,92,246,0.08)', border: 'rgba(139,92,246,0.2)' },
      ]" :key="stat.label"
        class="rounded-2xl p-5 flex flex-col gap-2 border"
        :style="`background:${stat.bg}; border-color:${stat.border}`"
      >
        <p class="text-xs font-bold uppercase tracking-wider" :style="`color:${stat.color}`">{{ stat.label }}</p>
        <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ stat.value }}</strong>
        <span class="text-xs text-[var(--muted)] font-medium">{{ stat.sub }}</span>
      </div>
    </div>

    <!-- ── Main table panel ── -->
    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">

      <!-- Toolbar -->
      <div class="flex flex-wrap gap-3 items-center px-5 py-4 border-b border-[var(--line)]">
        <form class="flex flex-1 min-w-0 gap-2" @submit.prevent="fetchUsers(1)">
          <div class="relative flex-1 min-w-[180px] max-w-xs">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)]" style="font-size:0.8rem" />
            <input
              v-model="filters.search"
              class="w-full h-9 pl-8 pr-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]"
              placeholder="Tên, email, MSSV..."
              type="text"
            >
          </div>
          <select
            v-model="filters.role"
            class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer"
            @change="fetchUsers(1)"
          >
            <option value="">Tất cả vai trò</option>
            <option value="admin">Admin</option>
            <option value="instructor">Giảng viên</option>
            <option value="student">Sinh viên</option>
          </select>
        </form>
        <div class="flex items-center gap-2 shrink-0">
          <button
            class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border text-sm font-semibold transition-colors"
            :class="filterOpen || activeFilterCount > 0
              ? 'bg-[rgba(29,158,117,0.1)] border-[rgba(29,158,117,0.35)] text-[#085041]'
              : 'bg-[var(--surface)] border-[var(--line)] text-[var(--muted)] hover:text-[var(--text)] hover:bg-[var(--surface)]'"
            type="button"
            @click="filterOpen = !filterOpen"
          >
            <i class="pi pi-filter" style="font-size:0.8rem" />
            Bộ lọc
            <span v-if="activeFilterCount > 0" class="flex items-center justify-center w-4 h-4 rounded-full bg-[#1d9e75] text-white text-[0.6rem] font-extrabold">{{ activeFilterCount }}</span>
          </button>
          <button
            class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors"
            type="button"
            @click="exportData"
          >
            <i class="pi pi-download" style="font-size:0.8rem" /> Xuất CSV
          </button>
        </div>
      </div>

      <!-- Advanced filter panel -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out overflow-hidden"
        leave-active-class="transition-all duration-200 ease-in overflow-hidden"
        enter-from-class="max-h-0 opacity-0"
        enter-to-class="max-h-96 opacity-100"
        leave-from-class="max-h-96 opacity-100"
        leave-to-class="max-h-0 opacity-0"
      >
        <div v-if="filterOpen" class="px-5 py-4 bg-[var(--surface)] border-b border-[var(--line)]">
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 items-end">
            <label v-for="f in [
              { label: 'Trạng thái học vụ', model: 'study_status', options: [
                { v:'dang_hoc',l:'Đang học'},{ v:'bao_luu',l:'Bảo lưu'},{ v:'tot_nghiep',l:'Tốt nghiệp'},
                { v:'thoi_hoc',l:'Thôi học'},{ v:'dinh_chi',l:'Đình chỉ'},{ v:'dang_cong_tac',l:'Đang công tác'},
                { v:'nghi_phep',l:'Nghỉ phép'},{ v:'nghi_huu',l:'Nghỉ hưu'},
              ]},
              { label: 'Giới tính', model: 'gender', options: [{ v:'male',l:'Nam'},{ v:'female',l:'Nữ'},{ v:'other',l:'Khác'}]},
            ]" :key="f.model" class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">{{ f.label }}</span>
              <select
                v-model="filters[f.model as keyof typeof filters]"
                class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer"
                @change="fetchUsers(1)"
              >
                <option value="">Tất cả</option>
                <option v-for="o in f.options" :key="o.v" :value="o.v">{{ o.l }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Khóa</span>
              <select v-model="filters.cohort_id" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="fetchUsers(1)">
                <option value="">Tất cả</option>
                <option v-for="c in optCohorts" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Chương trình</span>
              <select v-model="filters.program_id" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="fetchUsers(1)">
                <option value="">Tất cả</option>
                <option v-for="p in optPrograms" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Lớp HC</span>
              <select v-model="filters.administrative_class_id" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="fetchUsers(1)">
                <option value="">Tất cả</option>
                <option v-for="c in optAdminClasses" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </label>
            <div class="flex items-end">
              <button v-if="activeFilterCount > 0" class="h-8 px-3 rounded-lg border border-red-200 bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-100 transition-colors" type="button" @click="resetFilters">
                Xóa bộ lọc ({{ activeFilterCount }})
              </button>
            </div>
          </div>
          <!-- Active chips -->
          <div v-if="activeFilterCount > 0" class="flex flex-wrap gap-2 mt-3">
            <span v-if="filters.study_status" class="inline-flex items-center gap-1.5 h-6 px-3 rounded-full bg-[rgba(29,158,117,0.1)] border border-[rgba(29,158,117,0.22)] text-[#085041] text-xs font-semibold">
              {{ STUDY_STATUS_LABELS[filters.study_status] }}
              <button type="button" class="opacity-60 hover:opacity-100" @click="filters.study_status = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.gender" class="inline-flex items-center gap-1.5 h-6 px-3 rounded-full bg-[rgba(29,158,117,0.1)] border border-[rgba(29,158,117,0.22)] text-[#085041] text-xs font-semibold">
              {{ { male:'Nam', female:'Nữ', other:'Khác' }[filters.gender as 'male'|'female'|'other'] }}
              <button type="button" class="opacity-60 hover:opacity-100" @click="filters.gender = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.cohort_id" class="inline-flex items-center gap-1.5 h-6 px-3 rounded-full bg-[rgba(29,158,117,0.1)] border border-[rgba(29,158,117,0.22)] text-[#085041] text-xs font-semibold">
              {{ optCohorts.find(c => String(c.id) === filters.cohort_id)?.name }}
              <button type="button" class="opacity-60 hover:opacity-100" @click="filters.cohort_id = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.program_id" class="inline-flex items-center gap-1.5 h-6 px-3 rounded-full bg-[rgba(29,158,117,0.1)] border border-[rgba(29,158,117,0.22)] text-[#085041] text-xs font-semibold">
              {{ optPrograms.find(p => String(p.id) === filters.program_id)?.name }}
              <button type="button" class="opacity-60 hover:opacity-100" @click="filters.program_id = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.administrative_class_id" class="inline-flex items-center gap-1.5 h-6 px-3 rounded-full bg-[rgba(29,158,117,0.1)] border border-[rgba(29,158,117,0.22)] text-[#085041] text-xs font-semibold">
              {{ optAdminClasses.find(c => String(c.id) === filters.administrative_class_id)?.name }}
              <button type="button" class="opacity-60 hover:opacity-100" @click="filters.administrative_class_id = ''; fetchUsers(1)">×</button>
            </span>
          </div>
        </div>
      </Transition>

      <!-- Alerts -->
      <div v-if="errorMessage" class="mx-5 mt-4 flex items-center gap-2 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
        <i class="pi pi-exclamation-circle shrink-0" style="font-size:0.875rem" />{{ errorMessage }}
      </div>
      <div v-if="successMessage" class="mx-5 mt-4 flex items-center gap-2 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
        <i class="pi pi-check-circle shrink-0" style="font-size:0.875rem" />{{ successMessage }}
      </div>

      <!-- Meta bar -->
      <div class="flex items-center justify-between px-5 py-2.5 text-xs text-[var(--muted)]">
        <span>{{ totalUsers }} người dùng phù hợp</span>
        <button v-if="activeFilterCount > 0 || filters.search || filters.role" class="text-red-500 hover:text-red-700 underline" type="button" @click="resetFilters">
          Xóa tất cả bộ lọc
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-t border-b border-[var(--line)] bg-[var(--surface)]">
              <th class="w-9 px-4 py-3 text-left"><input type="checkbox" :checked="isAllSelected" class="rounded" @change="toggleSelectAll"></th>
              <th class="w-10 px-2 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">#</th>
              <th class="min-w-[220px] px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Người dùng</th>
              <th class="w-28 px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Vai trò</th>
              <th class="min-w-[160px] px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Lớp HC / Khóa</th>
              <th class="min-w-[110px] px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">MSSV / Mã NV</th>
              <th class="min-w-[110px] px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">SĐT</th>
              <th class="min-w-[110px] px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái</th>
              <th class="min-w-[120px] px-4 py-3 text-right text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading skeletons -->
            <tr v-if="loading" v-for="n in 5" :key="n" class="border-b border-[var(--line)]">
              <td class="px-4 py-3"><div class="w-4 h-4 rounded bg-[var(--line)] animate-pulse" /></td>
              <td class="px-2 py-3"><div class="w-5 h-3 rounded bg-[var(--line)] animate-pulse" /></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-[var(--line)] animate-pulse shrink-0" />
                  <div class="flex flex-col gap-1.5">
                    <div class="w-28 h-3 rounded bg-[var(--line)] animate-pulse" />
                    <div class="w-36 h-2.5 rounded bg-[var(--line)] animate-pulse" />
                  </div>
                </div>
              </td>
              <td v-for="i in 6" :key="i" class="px-4 py-3"><div class="w-20 h-3 rounded bg-[var(--line)] animate-pulse" /></td>
            </tr>
            <!-- Empty state -->
            <tr v-else-if="users.length === 0">
              <td colspan="9">
                <div class="flex flex-col items-center justify-center py-16 gap-3 text-[var(--muted)]">
                  <i class="pi pi-users opacity-30" style="font-size:2.5rem" />
                  <strong class="text-sm font-semibold text-[var(--text)]">Chưa có người dùng</strong>
                  <p class="text-xs">Thêm người dùng đầu tiên hoặc thay đổi bộ lọc.</p>
                </div>
              </td>
            </tr>
            <!-- Data rows -->
            <tr
              v-for="(item, index) in users"
              :key="item.id"
              class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
            >
              <td class="px-4 py-3"><input type="checkbox" :value="item.id" v-model="selectedIds" class="rounded"></td>
              <td class="px-2 py-3 text-xs text-[var(--muted)]">{{ (currentPage - 1) * perPage + index + 1 }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full shrink-0 overflow-hidden flex items-center justify-center bg-[rgba(29,158,117,0.1)] text-[#085041] text-xs font-extrabold border border-[rgba(29,158,117,0.2)]">
                    <img v-if="item.avatar" :src="item.avatar" :alt="item.name" class="w-full h-full object-cover">
                    <span v-else>{{ avatarInitials(item.name) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 min-w-0">
                    <strong class="text-sm font-semibold text-[var(--text)] truncate">{{ item.name }}</strong>
                    <span class="text-xs text-[var(--muted)] truncate">{{ item.email }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold" :class="{
                  'bg-[rgba(139,92,246,0.1)] text-purple-700 border border-purple-200': resolveRole(item) === 'admin',
                  'bg-[rgba(55,138,221,0.1)] text-blue-700 border border-blue-200': resolveRole(item) === 'instructor',
                  'bg-[rgba(29,158,117,0.1)] text-[#085041] border border-[rgba(29,158,117,0.2)]': resolveRole(item) === 'student',
                  'bg-amber-50 text-amber-700 border border-amber-200': resolveRole(item) === 'academic_manager',
                }">{{ resolveRole(item) }}</span>
              </td>
              <td class="px-4 py-3">
                <div v-if="item.administrative_class || item.cohort" class="flex flex-col gap-0.5">
                  <span v-if="item.administrative_class" class="text-sm font-medium text-[var(--text)]">{{ item.administrative_class.name }}</span>
                  <span v-if="item.cohort" class="text-xs text-[var(--muted)]">{{ item.cohort.name }}</span>
                </div>
                <span v-else class="text-[var(--muted)]">—</span>
              </td>
              <td class="px-4 py-3 text-xs font-mono text-[var(--text)]">{{ item.student_code || item.staff_code || '—' }}</td>
              <td class="px-4 py-3 text-xs text-[var(--muted)]">{{ item.phone || '—' }}</td>
              <td class="px-4 py-3">
                <span v-if="item.study_status" class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold" :class="{
                  'bg-emerald-50 text-emerald-700 border border-emerald-200': ['dang_hoc','dang_cong_tac'].includes(item.study_status),
                  'bg-amber-50 text-amber-700 border border-amber-200': ['bao_luu','nghi_phep'].includes(item.study_status),
                  'bg-blue-50 text-blue-700 border border-blue-200': item.study_status === 'tot_nghiep',
                  'bg-red-50 text-red-700 border border-red-200': ['thoi_hoc','dinh_chi'].includes(item.study_status),
                  'bg-slate-100 text-slate-500 border border-slate-200': item.study_status === 'nghi_huu',
                }">{{ STUDY_STATUS_LABELS[item.study_status] || item.study_status }}</span>
                <span v-else class="text-[var(--muted)] text-xs">—</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="inline-flex items-center justify-center h-7 px-2.5 rounded-lg border border-[var(--line)] bg-transparent text-xs font-semibold text-[var(--muted)] hover:text-[var(--text)] hover:bg-[var(--surface)] transition-colors"
                    type="button"
                    @click="openViewModal(item)"
                  >Xem</button>
                  <button
                    class="inline-flex items-center justify-center h-7 px-2.5 rounded-lg border border-[rgba(29,158,117,0.3)] bg-[rgba(29,158,117,0.07)] text-xs font-semibold text-[#085041] hover:bg-[rgba(29,158,117,0.13)] transition-colors"
                    type="button"
                    @click="openEditModal(item)"
                  >Sửa</button>
                  <button
                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 text-xs font-bold transition-colors disabled:opacity-40"
                    type="button"
                    :disabled="deletingId === item.id"
                    @click="deleteUser(item)"
                  >✕</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <DataTableFooter
        :current="currentPage"
        :last="lastPage"
        :total="totalUsers"
        :per-page="perPage"
        @page="fetchUsers"
        @update:per-page="perPage = $event; fetchUsers(1)"
      />
    </section>

    <!-- ── CRUD Modal ── -->
    <Teleport to="body">
      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="closeModal">
        <div class="relative w-full max-w-2xl max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-2xl overflow-hidden">

          <!-- Modal Header -->
          <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-[var(--line)] shrink-0">
            <div>
              <p class="text-[0.68rem] font-bold uppercase tracking-widest text-[var(--muted)]">
                {{ modalMode === 'create' ? 'Tạo mới' : modalMode === 'edit' ? 'Chỉnh sửa' : 'Chi tiết' }}
              </p>
              <h3 class="text-lg font-bold tracking-tight text-[var(--text)] mt-0.5">
                {{ modalMode === 'create' ? 'Thêm người dùng' : modalMode === 'edit' ? 'Cập nhật người dùng' : selectedUser?.name }}
              </h3>
            </div>
            <button
              class="w-8 h-8 rounded-xl flex items-center justify-center border border-[var(--line)] text-[var(--muted)] hover:text-[var(--text)] hover:bg-[var(--surface)] transition-colors shrink-0 mt-0.5"
              type="button"
              @click="closeModal"
            >✕</button>
          </div>

          <!-- Scrollable body -->
          <div class="flex-1 overflow-y-auto px-6 py-5">

            <!-- ── VIEW MODE ── -->
            <template v-if="modalMode === 'view'">
              <!-- Avatar + name -->
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full shrink-0 overflow-hidden flex items-center justify-center bg-[rgba(29,158,117,0.1)] text-[#085041] text-xl font-extrabold border-2 border-[rgba(29,158,117,0.2)]">
                  <img v-if="selectedUser?.avatar" :src="selectedUser.avatar" :alt="selectedUser?.name" class="w-full h-full object-cover">
                  <span v-else>{{ avatarInitials(selectedUser?.name || '') }}</span>
                </div>
                <div class="flex flex-col gap-1 min-w-0">
                  <h4 class="text-lg font-bold text-[var(--text)] truncate">{{ selectedUser?.name }}</h4>
                  <span class="text-sm text-[var(--muted)] truncate">{{ selectedUser?.email }}</span>
                  <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold w-fit" :class="{
                    'bg-purple-50 text-purple-700 border border-purple-200': resolveRole(selectedUser) === 'admin',
                    'bg-blue-50 text-blue-700 border border-blue-200': resolveRole(selectedUser) === 'instructor',
                    'bg-emerald-50 text-emerald-700 border border-emerald-200': resolveRole(selectedUser) === 'student',
                    'bg-amber-50 text-amber-700 border border-amber-200': resolveRole(selectedUser) === 'academic_manager',
                  }">{{ resolveRole(selectedUser) }}</span>
                </div>
              </div>

              <!-- Bio -->
              <p v-if="selectedUser?.bio" class="mt-4 text-sm text-[var(--text)] leading-relaxed bg-[var(--surface)] px-4 py-3 rounded-xl border border-[var(--line)]">{{ selectedUser.bio }}</p>

              <!-- GPA strip for students -->
              <div v-if="resolveRole(selectedUser) === 'student'" class="mt-4 grid grid-cols-4 gap-3 bg-[var(--surface)] border border-[var(--line)] rounded-xl px-4 py-3">
                <div v-if="loadingSummary" class="col-span-4 flex items-center gap-2 text-sm text-[var(--muted)]">
                  <i class="pi pi-spin pi-spinner" style="font-size:0.875rem" /> Đang tải GPA...
                </div>
                <template v-else-if="academicSummary">
                  <div v-for="item in [
                    { label: 'GPA', value: academicSummary.overall_gpa?.toFixed(2) ?? '—' },
                    { label: 'Xếp loại', value: gpaClassification(academicSummary.overall_gpa).label },
                    { label: 'Tín chỉ', value: academicSummary.total_credits },
                    { label: 'Số môn', value: academicSummary.total_courses },
                  ]" :key="item.label" class="flex flex-col gap-1">
                    <span class="text-[0.65rem] font-bold uppercase tracking-wide text-[var(--muted)]">{{ item.label }}</span>
                    <strong class="text-xl font-extrabold tracking-tight text-[var(--text)]">{{ item.value }}</strong>
                  </div>
                </template>
                <div v-else class="col-span-4 text-sm text-[var(--muted)]">Chưa có dữ liệu điểm.</div>
              </div>

              <!-- Info grid -->
              <div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 pt-4 border-t border-[var(--line)]">
                <div v-for="field in [
                  { label: 'Mã SV/NV', value: selectedUser?.student_code || selectedUser?.staff_code || '—', mono: true },
                  { label: 'Trạng thái', value: selectedUser?.study_status ? (STUDY_STATUS_LABELS[selectedUser.study_status] || selectedUser.study_status) : '—' },
                  ...(resolveRole(selectedUser) === 'student' ? [
                    { label: 'Lớp HC', value: selectedUser?.administrative_class?.name || '—' },
                    { label: 'Khóa', value: selectedUser?.cohort?.name || '—' },
                    { label: 'Chương trình', value: selectedUser?.program?.name || '—' },
                    { label: 'Ngành học', value: selectedUser?.major?.name || '—' },
                  ] : []),
                  { label: 'SĐT', value: selectedUser?.phone || '—' },
                  { label: 'Giới tính', value: { male:'Nam', female:'Nữ', other:'Khác' }[selectedUser?.gender as 'male'|'female'|'other'] || '—' },
                  { label: 'Ngày sinh', value: selectedUser?.date_of_birth ? formatDate(selectedUser.date_of_birth) : '—' },
                  { label: 'Quốc tịch', value: selectedUser?.nationality || '—' },
                  { label: 'CMND/CCCD', value: selectedUser?.id_card_number || '—' },
                  { label: 'Quê quán', value: selectedUser?.hometown || '—' },
                ]" :key="field.label" class="flex flex-col gap-0.5">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">{{ field.label }}</span>
                  <span class="text-sm font-medium text-[var(--text)]" :class="{ 'font-mono': field.mono }">{{ field.value }}</span>
                </div>
                <div class="col-span-2 flex flex-col gap-0.5">
                  <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Địa chỉ</span>
                  <span class="text-sm font-medium text-[var(--text)]">{{ selectedUser?.permanent_address || '—' }}</span>
                </div>
              </div>

              <!-- Transcript table -->
              <div v-if="academicSummary && academicSummary.terms.length" class="mt-4">
                <span class="text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)] block mb-2">Bảng điểm theo học kỳ</span>
                <div class="rounded-xl border border-[var(--line)] overflow-hidden">
                  <table class="w-full text-sm border-collapse">
                    <thead>
                      <tr class="bg-[var(--surface)] border-b border-[var(--line)]">
                        <th class="px-4 py-2 text-left text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Học kỳ</th>
                        <th class="px-4 py-2 text-left text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Số môn</th>
                        <th class="px-4 py-2 text-left text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Tín chỉ</th>
                        <th class="px-4 py-2 text-left text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">GPA</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="t in academicSummary.terms" :key="t.term?.id" class="border-b border-[var(--line)] last:border-0">
                        <td class="px-4 py-2 text-[var(--text)]">{{ t.term?.name || '—' }}</td>
                        <td class="px-4 py-2 text-[var(--muted)]">{{ t.course_count }}</td>
                        <td class="px-4 py-2 text-[var(--muted)]">{{ t.credit_count }}</td>
                        <td class="px-4 py-2"><strong class="text-[var(--text)]">{{ t.gpa ?? '—' }}</strong></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>

            <!-- ── EDIT / CREATE MODE ── -->
            <template v-else>
              <!-- Section tabs -->
              <div class="flex gap-1 pb-4 border-b border-[var(--line)] mb-5">
                <button
                  v-for="tab in [{ key:'account', label:'Tài khoản' }, { key:'academic', label:'Học vụ' }, { key:'personal', label:'Cá nhân' }]"
                  :key="tab.key"
                  class="h-8 px-4 rounded-xl text-sm font-semibold border transition-colors"
                  :class="activeSection === tab.key
                    ? 'bg-[rgba(29,158,117,0.1)] border-[rgba(29,158,117,0.3)] text-[#085041]'
                    : 'bg-transparent border-transparent text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--text)]'"
                  type="button"
                  @click="activeSection = tab.key as 'account'|'academic'|'personal'"
                >{{ tab.label }}</button>
              </div>

              <div v-if="errorMessage" class="mb-4 flex items-center gap-2 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                <i class="pi pi-exclamation-circle shrink-0" />{{ errorMessage }}
              </div>

              <!-- Field helpers -->
              <div class="grid grid-cols-2 gap-4">

                <!-- Tab: Tài khoản -->
                <template v-if="activeSection === 'account'">
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Họ và tên <em class="text-red-500 not-italic">*</em></span>
                    <input v-model="form.name" type="text" placeholder="Nguyễn Văn A" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Email <em class="text-red-500 not-italic">*</em></span>
                    <input v-model="form.email" type="email" placeholder="user@ptit.edu.vn" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Vai trò <em class="text-red-500 not-italic">*</em></span>
                    <select v-model="form.role" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="student">Sinh viên</option>
                      <option value="instructor">Giảng viên</option>
                      <option value="admin">Admin</option>
                      <option value="academic_manager">Quản lý học vụ</option>
                    </select>
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">{{ modalMode === 'edit' ? 'Mật khẩu mới (bỏ trống nếu không đổi)' : 'Mật khẩu *' }}</span>
                    <input v-model="form.password" type="password" :placeholder="modalMode === 'edit' ? 'Bỏ trống nếu không đổi' : 'Tối thiểu 6 ký tự'" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]">
                  </label>
                  <label class="col-span-2 flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Giới thiệu / Bio</span>
                    <textarea v-model="form.bio" rows="2" placeholder="Mô tả ngắn..." class="px-3 py-2 rounded-xl border border-[var(--line)] bg-white text-sm resize-y focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]"></textarea>
                  </label>
                  <div class="col-span-2 flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Ảnh đại diện</span>
                    <MediaUpload v-model="form.avatar" folder="users" variant="avatar" label="Ảnh đại diện" hint="JPG, PNG, WEBP — tối đa 5MB." :placeholder-initial="form.name ? avatarInitials(form.name) : 'AV'" @uploaded="onAvatarUploaded" @error="onAvatarError" />
                  </div>
                </template>

                <!-- Tab: Học vụ -->
                <template v-if="activeSection === 'academic'">
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Mã sinh viên</span>
                    <input v-model="form.student_code" type="text" placeholder="B21DCCN123" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Mã nhân viên</span>
                    <input v-model="form.staff_code" type="text" placeholder="GV001" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Lớp hành chính</span>
                    <select v-model="form.administrative_class_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="">— Chọn lớp HC —</option>
                      <option v-for="c in optAdminClasses" :key="c.id" :value="String(c.id)">{{ c.name }}<template v-if="c.code"> ({{ c.code }})</template></option>
                    </select>
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Khóa / Niên khóa</span>
                    <select v-model="form.cohort_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="">— Chọn khóa —</option>
                      <option v-for="c in optCohorts" :key="c.id" :value="String(c.id)">{{ c.name }}<template v-if="c.code"> ({{ c.code }})</template></option>
                    </select>
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Chương trình đào tạo</span>
                    <select v-model="form.program_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="">— Chọn chương trình —</option>
                      <option v-for="p in optPrograms" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                    </select>
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Ngành học</span>
                    <select v-model="form.major_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="">— Chọn ngành —</option>
                      <option v-for="m in optMajors" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                    </select>
                  </label>
                  <label class="col-span-2 flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Trạng thái học vụ</span>
                    <select v-model="form.study_status" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="">— Chưa xác định —</option>
                      <option value="dang_hoc">Đang học</option>
                      <option value="bao_luu">Bảo lưu</option>
                      <option value="tot_nghiep">Tốt nghiệp</option>
                      <option value="thoi_hoc">Thôi học</option>
                      <option value="dinh_chi">Đình chỉ</option>
                      <option value="dang_cong_tac">Đang công tác</option>
                      <option value="nghi_phep">Nghỉ phép</option>
                      <option value="nghi_huu">Nghỉ hưu</option>
                    </select>
                  </label>
                </template>

                <!-- Tab: Cá nhân -->
                <template v-if="activeSection === 'personal'">
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Số điện thoại</span>
                    <input v-model="form.phone" type="tel" placeholder="0987 654 321" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Giới tính</span>
                    <select v-model="form.gender" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                      <option value="">— Chưa xác định —</option>
                      <option value="male">Nam</option>
                      <option value="female">Nữ</option>
                      <option value="other">Khác</option>
                    </select>
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Ngày sinh</span>
                    <input v-model="form.date_of_birth" type="date" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Quốc tịch</span>
                    <input v-model="form.nationality" type="text" placeholder="Việt Nam" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">CMND / CCCD</span>
                    <input v-model="form.id_card_number" type="text" placeholder="001234567890" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Quê quán</span>
                    <input v-model="form.hometown" type="text" placeholder="Hà Nội" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                  <label class="col-span-2 flex flex-col gap-1.5">
                    <span class="text-xs font-semibold text-[var(--text)]">Địa chỉ thường trú</span>
                    <input v-model="form.permanent_address" type="text" placeholder="Số nhà, đường, phường, quận, tỉnh..." class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
                  </label>
                </template>

              </div>
            </template>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-[var(--line)] bg-[var(--surface)] shrink-0">
            <button
              class="h-9 px-5 rounded-xl border border-[var(--line)] bg-transparent text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] hover:bg-white transition-colors"
              type="button"
              @click="closeModal"
            >Đóng</button>
            <button
              v-if="modalMode !== 'view'"
              class="h-9 px-5 rounded-xl bg-[#1d9e75] hover:bg-[#17876a] text-white text-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              type="button"
              :disabled="saving"
              @click="saveUser"
            >
              <i v-if="saving" class="pi pi-spin pi-spinner mr-1.5" style="font-size:0.8rem" />
              {{ saving ? 'Đang lưu...' : modalMode === 'create' ? 'Tạo người dùng' : 'Lưu thay đổi' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <CrudConfirmModal
      :open="deleteModalOpen"
      title="Xóa người dùng"
      :description="`Bạn có chắc chắn muốn xóa ${selectedUser?.name || 'người dùng này'}? Thao tác này không thể hoàn tác.`"
      confirm-text="Xóa người dùng"
      tone="danger"
      :loading="deletingId === selectedUser?.id"
      @close="deleteModalOpen = false"
      @confirm="deleteUser()"
    />
  </div>
</template>

<style scoped>
/* All Tailwind — no custom classes needed */
</style>
