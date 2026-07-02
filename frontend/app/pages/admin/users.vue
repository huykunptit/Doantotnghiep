<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Download, Users } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import MediaUpload from '~/components/common/MediaUpload.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useExport } from '~/composables/useExport'

definePageMeta({
  layout: 'admin',
  adminSearchPlaceholder: 'Tìm người dùng, email, MSSV...',
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
})

const filterOpen = ref(false)

const activeFilterCount = computed(() => {
  return [filters.study_status, filters.gender, filters.cohort_id, filters.program_id, filters.administrative_class_id]
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
const optionsLoaded = ref(false)

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
  <AdminWorkspaceShell
    title="Quản lý người dùng"
    description="Thêm, chỉnh sửa, xem hồ sơ học vụ và quản lý tài khoản trong hệ thống."
    :breadcrumb="['Trang chủ', 'Quản lý người dùng']"
  >
    <div class="ds-stack">

      <!-- KPI strip -->
      <div class="ds-stats">
        <div class="ds-stat ds-stat--green">
          <p class="ds-stat-label">Tổng người dùng</p>
          <strong class="ds-stat-value">{{ totalUsers }}</strong>
          <span class="ds-stat-sub">tất cả vai trò</span>
        </div>
        <div class="ds-stat ds-stat--blue">
          <p class="ds-stat-label">Sinh viên</p>
          <strong class="ds-stat-value">{{ statsStudents }}</strong>
          <span class="ds-stat-sub">đang học</span>
        </div>
        <div class="ds-stat ds-stat--amber">
          <p class="ds-stat-label">Giảng viên</p>
          <strong class="ds-stat-value">{{ statsInstructors }}</strong>
          <span class="ds-stat-sub">instructor</span>
        </div>
        <div class="ds-stat ds-stat--violet">
          <p class="ds-stat-label">Quản trị viên</p>
          <strong class="ds-stat-value">{{ statsAdmins }}</strong>
          <span class="ds-stat-sub">admin</span>
        </div>
      </div>

      <!-- Main table panel -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <form class="crud-toolbar-main" @submit.prevent="fetchUsers(1)">
            <input v-model="filters.search" class="crud-search" placeholder="Tên, email, MSSV, mã NV..." type="text">
            <select v-model="filters.role" class="crud-select" @change="fetchUsers(1)">
              <option value="">Tất cả vai trò</option>
              <option value="admin">Admin</option>
              <option value="instructor">Giảng viên</option>
              <option value="student">Sinh viên</option>
            </select>
            <button class="crud-secondary-btn" type="submit">Tìm kiếm</button>
          </form>
          <div class="crud-toolbar-right">
            <!-- Filter toggle button -->
            <button
              class="uf-filter-btn"
              :class="{ 'uf-filter-btn--active': filterOpen || activeFilterCount > 0 }"
              type="button"
              @click="filterOpen = !filterOpen"
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
              Bộ lọc
              <span v-if="activeFilterCount > 0" class="uf-filter-count">{{ activeFilterCount }}</span>
            </button>
            <button class="crud-export-btn" type="button" @click="exportData">
              <Download :size="16" :stroke-width="1.75" /> Xuất CSV
            </button>
            <button class="crud-primary-btn" type="button" @click="openCreateModal">+ Thêm người dùng</button>
          </div>
        </div>

        <!-- Advanced filter panel -->
        <div v-if="filterOpen" class="uf-filter-panel">
          <div class="uf-filter-grid">
            <label class="uf-filter-field">
              <span>Trạng thái học vụ</span>
              <select v-model="filters.study_status" @change="fetchUsers(1)">
                <option value="">Tất cả trạng thái</option>
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
            <label class="uf-filter-field">
              <span>Giới tính</span>
              <select v-model="filters.gender" @change="fetchUsers(1)">
                <option value="">Tất cả giới tính</option>
                <option value="male">Nam</option>
                <option value="female">Nữ</option>
                <option value="other">Khác</option>
              </select>
            </label>
            <label class="uf-filter-field">
              <span>Khóa / Niên khóa</span>
              <select v-model="filters.cohort_id" @change="fetchUsers(1)">
                <option value="">Tất cả khóa</option>
                <option v-for="c in optCohorts" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </label>
            <label class="uf-filter-field">
              <span>Chương trình đào tạo</span>
              <select v-model="filters.program_id" @change="fetchUsers(1)">
                <option value="">Tất cả chương trình</option>
                <option v-for="p in optPrograms" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
              </select>
            </label>
            <label class="uf-filter-field">
              <span>Lớp hành chính</span>
              <select v-model="filters.administrative_class_id" @change="fetchUsers(1)">
                <option value="">Tất cả lớp HC</option>
                <option v-for="c in optAdminClasses" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </label>
            <div class="uf-filter-actions">
              <button
                v-if="activeFilterCount > 0"
                class="uf-reset-btn"
                type="button"
                @click="resetFilters"
              >Xóa bộ lọc ({{ activeFilterCount }})</button>
            </div>
          </div>

          <!-- Active filter chips -->
          <div v-if="activeFilterCount > 0" class="uf-chips">
            <span v-if="filters.study_status" class="uf-chip">
              Trạng thái: {{ STUDY_STATUS_LABELS[filters.study_status] }}
              <button type="button" @click="filters.study_status = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.gender" class="uf-chip">
              Giới tính: {{ { male: 'Nam', female: 'Nữ', other: 'Khác' }[filters.gender] }}
              <button type="button" @click="filters.gender = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.cohort_id" class="uf-chip">
              Khóa: {{ optCohorts.find(c => String(c.id) === filters.cohort_id)?.name }}
              <button type="button" @click="filters.cohort_id = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.program_id" class="uf-chip">
              CT: {{ optPrograms.find(p => String(p.id) === filters.program_id)?.name }}
              <button type="button" @click="filters.program_id = ''; fetchUsers(1)">×</button>
            </span>
            <span v-if="filters.administrative_class_id" class="uf-chip">
              Lớp HC: {{ optAdminClasses.find(c => String(c.id) === filters.administrative_class_id)?.name }}
              <button type="button" @click="filters.administrative_class_id = ''; fetchUsers(1)">×</button>
            </span>
          </div>
        </div>

        <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
        <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

        <div class="crud-meta">
          <p>{{ totalUsers }} người dùng phù hợp</p>
          <button v-if="activeFilterCount > 0 || filters.search || filters.role" class="uf-meta-reset" type="button" @click="resetFilters">
            Xóa tất cả bộ lọc
          </button>
        </div>

        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th style="width:36px"><input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll"></th>
                <th style="width:44px">#</th>
                <th style="min-width:220px">Người dùng</th>
                <th style="width:110px">Vai trò</th>
                <th style="min-width:160px">Lớp HC / Khóa</th>
                <th style="min-width:110px">MSSV / Mã NV</th>
                <th style="min-width:120px">Số điện thoại</th>
                <th style="min-width:110px">Trạng thái</th>
                <th style="min-width:130px; text-align:right">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="9" class="crud-empty"><span class="ds-spin ds-spin--sm" style="margin-right:8px"></span>Đang tải...</td>
              </tr>
              <tr v-else-if="users.length === 0">
                <td colspan="9">
                  <div class="ds-empty">
                    <div class="ds-empty-icon"><Users :size="24" /></div>
                    <strong>Chưa có người dùng</strong>
                    <p>Thêm người dùng đầu tiên hoặc thay đổi bộ lọc.</p>
                  </div>
                </td>
              </tr>
              <tr v-for="(item, index) in users" :key="item.id">
                <td><input type="checkbox" :value="item.id" v-model="selectedIds"></td>
                <td class="cell-muted">{{ (currentPage - 1) * perPage + index + 1 }}</td>
                <td>
                  <div class="user-cell">
                    <div v-if="item.avatar" class="ds-avatar ds-avatar--md">
                      <img :src="item.avatar" :alt="item.name" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                    </div>
                    <div v-else class="ds-avatar ds-avatar--md">{{ avatarInitials(item.name) }}</div>
                    <div class="user-cell-info">
                      <strong>{{ item.name }}</strong>
                      <span class="cell-muted" style="font-size:0.78rem">{{ item.email }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="ds-badge" :class="{
                    'ds-badge--violet': resolveRole(item) === 'admin',
                    'ds-badge--info': resolveRole(item) === 'instructor',
                    'ds-badge--active': resolveRole(item) === 'student',
                    'ds-badge--pending': resolveRole(item) === 'academic_manager',
                  }">{{ resolveRole(item) }}</span>
                </td>
                <td>
                  <div v-if="item.administrative_class || item.cohort" class="cell-stack">
                    <span v-if="item.administrative_class" class="cell-strong">{{ item.administrative_class.name }}</span>
                    <span v-if="item.cohort" class="cell-muted">{{ item.cohort.name }}</span>
                  </div>
                  <span v-else class="cell-muted">—</span>
                </td>
                <td class="cell-mono">{{ item.student_code || item.staff_code || '—' }}</td>
                <td class="cell-muted">{{ item.phone || '—' }}</td>
                <td>
                  <span :class="studyStatusBadgeClass(item.study_status)">
                    {{ item.study_status ? (STUDY_STATUS_LABELS[item.study_status] || item.study_status) : '—' }}
                  </span>
                </td>
                <td style="text-align:right">
                  <div style="display:flex;gap:5px;justify-content:flex-end">
                    <button class="ds-btn ds-btn--view" type="button" @click="openViewModal(item)">Xem</button>
                    <button class="ds-btn ds-btn--edit" type="button" @click="openEditModal(item)">Sửa</button>
                    <button class="ds-btn ds-btn--delete ds-btn--icon" type="button" :disabled="deletingId === item.id" @click="deleteUser(item)">✕</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DataTableFooter
          :current="currentPage"
          :last="lastPage"
          :total="totalUsers"
          :per-page="perPage"
          @page="fetchUsers"
          @update:per-page="perPage = $event; fetchUsers(1)"
        />
      </section>
    </div>

    <!-- ── CRUD Modal ── -->
    <Teleport to="body">
      <div v-if="modalOpen" class="crud-modal-backdrop" @click.self="closeModal">
        <div class="crud-modal um-modal">

          <!-- Header -->
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ modalMode === 'create' ? 'Tạo mới' : modalMode === 'edit' ? 'Chỉnh sửa' : 'Chi tiết' }}</p>
              <h3>{{ modalMode === 'create' ? 'Thêm người dùng' : modalMode === 'edit' ? 'Cập nhật người dùng' : selectedUser?.name }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="closeModal">✕</button>
          </div>

          <!-- ── View Mode: Clean Profile ── -->
          <div v-if="modalMode === 'view'" class="um-view-profile">
            <div class="um-vp-header">
              <div v-if="selectedUser?.avatar" class="ds-avatar ds-avatar--xl">
                <img :src="selectedUser.avatar" :alt="selectedUser.name" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
              </div>
              <div v-else class="ds-avatar ds-avatar--xl" style="font-size: 1.5rem;">{{ avatarInitials(selectedUser?.name || '') }}</div>
              <div class="um-vp-title">
                <h4>{{ selectedUser?.name }}</h4>
                <span class="um-vp-email">{{ selectedUser?.email }}</span>
                <span class="ds-badge" style="margin-top: 8px; width: fit-content;" :class="{
                  'ds-badge--violet': resolveRole(selectedUser) === 'admin',
                  'ds-badge--info': resolveRole(selectedUser) === 'instructor',
                  'ds-badge--active': resolveRole(selectedUser) === 'student',
                  'ds-badge--pending': resolveRole(selectedUser) === 'academic_manager',
                }">{{ resolveRole(selectedUser) }}</span>
              </div>
            </div>
            
            <p v-if="selectedUser?.bio" class="um-vp-bio">{{ selectedUser.bio }}</p>

            <div v-if="resolveRole(selectedUser) === 'student'" class="um-gpa-strip" style="margin-top: 24px;">
              <div v-if="loadingSummary" class="um-gpa-loading">
                <span class="ds-spin ds-spin--sm"></span> Đang tải GPA...
              </div>
              <template v-else-if="academicSummary">
                <div class="um-gpa-item">
                  <p class="um-gpa-label">GPA</p>
                  <strong class="um-gpa-value">{{ academicSummary.overall_gpa?.toFixed(2) ?? '—' }}</strong>
                </div>
                <div class="um-gpa-item">
                  <p class="um-gpa-label">Xếp loại</p>
                  <span class="ds-badge" :class="gpaClassification(academicSummary.overall_gpa).cls">
                    {{ gpaClassification(academicSummary.overall_gpa).label }}
                  </span>
                </div>
                <div class="um-gpa-item">
                  <p class="um-gpa-label">Tín chỉ</p>
                  <strong class="um-gpa-value">{{ academicSummary.total_credits }}</strong>
                </div>
                <div class="um-gpa-item">
                  <p class="um-gpa-label">Số môn</p>
                  <strong class="um-gpa-value">{{ academicSummary.total_courses }}</strong>
                </div>
              </template>
              <div v-else class="um-gpa-empty">Chưa có dữ liệu điểm.</div>
            </div>

            <div class="um-vp-grid" style="margin-top: 24px;">
              <div class="um-vp-field">
                <label>Mã SV/NV</label>
                <p class="cell-mono">{{ selectedUser?.student_code || selectedUser?.staff_code || '—' }}</p>
              </div>
              <div class="um-vp-field">
                <label>Trạng thái</label>
                <p>
                  <span :class="studyStatusBadgeClass(selectedUser?.study_status)">
                    {{ selectedUser?.study_status ? (STUDY_STATUS_LABELS[selectedUser.study_status] || selectedUser.study_status) : '—' }}
                  </span>
                </p>
              </div>
              <div class="um-vp-field" v-if="resolveRole(selectedUser) === 'student'">
                <label>Lớp HC</label>
                <p>{{ selectedUser?.administrative_class?.name || '—' }}</p>
              </div>
              <div class="um-vp-field" v-if="resolveRole(selectedUser) === 'student'">
                <label>Khóa</label>
                <p>{{ selectedUser?.cohort?.name || '—' }}</p>
              </div>
              <div class="um-vp-field" v-if="resolveRole(selectedUser) === 'student'">
                <label>Chương trình</label>
                <p>{{ selectedUser?.program?.name || '—' }}</p>
              </div>
              <div class="um-vp-field" v-if="resolveRole(selectedUser) === 'student'">
                <label>Ngành học</label>
                <p>{{ selectedUser?.major?.name || '—' }}</p>
              </div>
              
              <div class="um-vp-field">
                <label>Số điện thoại</label>
                <p>{{ selectedUser?.phone || '—' }}</p>
              </div>
              <div class="um-vp-field">
                <label>Giới tính</label>
                <p>{{ selectedUser?.gender === 'male' ? 'Nam' : selectedUser?.gender === 'female' ? 'Nữ' : selectedUser?.gender === 'other' ? 'Khác' : '—' }}</p>
              </div>
              <div class="um-vp-field">
                <label>Ngày sinh</label>
                <p>{{ selectedUser?.date_of_birth ? formatDate(selectedUser.date_of_birth) : '—' }}</p>
              </div>
              <div class="um-vp-field">
                <label>Quốc tịch</label>
                <p>{{ selectedUser?.nationality || '—' }}</p>
              </div>
              <div class="um-vp-field">
                <label>Số CMND / CCCD</label>
                <p>{{ selectedUser?.id_card_number || '—' }}</p>
              </div>
              <div class="um-vp-field">
                <label>Quê quán</label>
                <p>{{ selectedUser?.hometown || '—' }}</p>
              </div>
              <div class="um-vp-field" style="grid-column: 1 / -1;">
                <label>Địa chỉ</label>
                <p>{{ selectedUser?.permanent_address || '—' }}</p>
              </div>
            </div>

            <div v-if="academicSummary && academicSummary.terms.length" class="um-vp-terms" style="margin-top: 24px;">
              <span class="um-vp-section-title">Bảng điểm theo học kỳ</span>
              <div class="um-terms-table">
                <table>
                  <thead><tr><th>Học kỳ</th><th>Số môn</th><th>Tín chỉ</th><th>GPA</th></tr></thead>
                  <tbody>
                    <tr v-for="t in academicSummary.terms" :key="t.term?.id">
                      <td>{{ t.term?.name || '—' }}</td>
                      <td>{{ t.course_count }}</td>
                      <td>{{ t.credit_count }}</td>
                      <td><strong>{{ t.gpa ?? '—' }}</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ── Edit/Create Mode: Tabs & Form ── -->
          <template v-else>
            <!-- Section tabs -->
            <div class="um-section-tabs">
              <button
                class="um-section-tab"
                :class="{ 'um-section-tab--on': activeSection === 'account' }"
                type="button"
                @click="activeSection = 'account'"
              >Tài khoản</button>
              <button
                class="um-section-tab"
                :class="{ 'um-section-tab--on': activeSection === 'academic' }"
                type="button"
                @click="activeSection = 'academic'"
              >Học vụ</button>
              <button
                class="um-section-tab"
                :class="{ 'um-section-tab--on': activeSection === 'personal' }"
                type="button"
                @click="activeSection = 'personal'"
              >Cá nhân</button>
            </div>

            <div v-if="errorMessage" class="crud-alert is-error" style="margin: 0 0 12px">{{ errorMessage }}</div>

            <!-- Tab: Tài khoản -->
            <div v-show="activeSection === 'account'" class="um-tab-body">
              <div class="crud-form-grid">
                <label class="crud-field">
                  <span>Họ và tên <em>*</em></span>
                  <input v-model="form.name" type="text" placeholder="Nguyễn Văn A">
                </label>
                <label class="crud-field">
                  <span>Email <em>*</em></span>
                  <input v-model="form.email" type="email" placeholder="user@ptit.edu.vn">
                </label>
                <label class="crud-field">
                  <span>Vai trò <em>*</em></span>
                  <select v-model="form.role">
                    <option value="student">Sinh viên</option>
                    <option value="instructor">Giảng viên</option>
                    <option value="admin">Admin</option>
                    <option value="academic_manager">Quản lý học vụ</option>
                  </select>
                </label>
                <label class="crud-field">
                  <span>{{ modalMode === 'edit' ? 'Mật khẩu mới (bỏ trống nếu không đổi)' : 'Mật khẩu *' }}</span>
                  <input
                    v-model="form.password"
                    type="password"
                    :placeholder="modalMode === 'edit' ? 'Bỏ trống nếu không đổi' : 'Tối thiểu 6 ký tự'"
                  >
                </label>
                <label class="crud-field crud-field-full">
                  <span>Giới thiệu / Bio</span>
                  <textarea v-model="form.bio" rows="2" placeholder="Mô tả ngắn về người dùng..." style="resize:vertical"></textarea>
                </label>
                <div class="crud-field crud-field-full">
                  <span>Ảnh đại diện</span>
                  <MediaUpload
                    v-model="form.avatar"
                    folder="users"
                    variant="avatar"
                    label="Ảnh đại diện"
                    hint="JPG, PNG, WEBP — tối đa 5MB."
                    :placeholder-initial="form.name ? avatarInitials(form.name) : 'AV'"
                    @uploaded="onAvatarUploaded"
                    @error="onAvatarError"
                  />
                </div>
              </div>
            </div>

            <!-- Tab: Học vụ -->
            <div v-show="activeSection === 'academic'" class="um-tab-body">
              <div class="crud-form-grid">
                <label class="crud-field">
                  <span>Mã sinh viên</span>
                  <input v-model="form.student_code" type="text" placeholder="B21DCCN123">
                </label>
                <label class="crud-field">
                  <span>Mã nhân viên</span>
                  <input v-model="form.staff_code" type="text" placeholder="GV001">
                </label>

                <label class="crud-field">
                  <span>Lớp hành chính</span>
                  <select v-model="form.administrative_class_id">
                    <option value="">— Chọn lớp hành chính —</option>
                    <option v-for="c in optAdminClasses" :key="c.id" :value="String(c.id)">{{ c.name }} <template v-if="c.code">({{ c.code }})</template></option>
                  </select>
                </label>
                <label class="crud-field">
                  <span>Khóa / Niên khóa</span>
                  <select v-model="form.cohort_id">
                    <option value="">— Chọn khóa —</option>
                    <option v-for="c in optCohorts" :key="c.id" :value="String(c.id)">{{ c.name }} <template v-if="c.code">({{ c.code }})</template></option>
                  </select>
                </label>
                <label class="crud-field">
                  <span>Chương trình đào tạo</span>
                  <select v-model="form.program_id">
                    <option value="">— Chọn chương trình —</option>
                    <option v-for="p in optPrograms" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                  </select>
                </label>
                <label class="crud-field">
                  <span>Ngành học</span>
                  <select v-model="form.major_id">
                    <option value="">— Chọn ngành —</option>
                    <option v-for="m in optMajors" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                  </select>
                </label>

                <label class="crud-field crud-field-full">
                  <span>Trạng thái học vụ</span>
                  <select v-model="form.study_status">
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
              </div>
            </div>

            <!-- Tab: Cá nhân -->
            <div v-show="activeSection === 'personal'" class="um-tab-body">
              <div class="crud-form-grid">
                <label class="crud-field">
                  <span>Số điện thoại</span>
                  <input v-model="form.phone" type="tel" placeholder="0987 654 321">
                </label>
                <label class="crud-field">
                  <span>Giới tính</span>
                  <select v-model="form.gender">
                    <option value="">— Chưa xác định —</option>
                    <option value="male">Nam</option>
                    <option value="female">Nữ</option>
                    <option value="other">Khác</option>
                  </select>
                </label>
                <label class="crud-field">
                  <span>Ngày sinh</span>
                  <input v-model="form.date_of_birth" type="date">
                </label>
                <label class="crud-field">
                  <span>Quốc tịch</span>
                  <input v-model="form.nationality" type="text" placeholder="Việt Nam">
                </label>
                <label class="crud-field">
                  <span>Số CMND / CCCD</span>
                  <input v-model="form.id_card_number" type="text" placeholder="001234567890">
                </label>
                <label class="crud-field">
                  <span>Quê quán</span>
                  <input v-model="form.hometown" type="text" placeholder="Hà Nội">
                </label>
                <label class="crud-field crud-field-full">
                  <span>Địa chỉ thường trú</span>
                  <input v-model="form.permanent_address" type="text" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố">
                </label>
              </div>
            </div>
          </template>


          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="closeModal">Đóng</button>
            <button
              v-if="modalMode !== 'view'"
              class="crud-primary-btn"
              type="button"
              :disabled="saving"
              @click="saveUser"
            >{{ saving ? 'Đang lưu...' : modalMode === 'create' ? 'Tạo người dùng' : 'Lưu thay đổi' }}</button>
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
  </AdminWorkspaceShell>
</template>

<style scoped>
/* ── View Profile ── */
.um-view-profile {
  padding: 0 4px;
}
.um-vp-header {
  display: flex;
  align-items: center;
  gap: 20px;
}
.um-vp-title {
  display: flex;
  flex-direction: column;
}
.um-vp-title h4 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text);
}
.um-vp-email {
  color: var(--muted);
  font-size: 0.9rem;
}
.um-vp-bio {
  margin-top: 16px;
  font-size: 0.9rem;
  color: var(--text);
  line-height: 1.5;
  background: var(--bg, #eff2f0);
  padding: 12px 16px;
  border-radius: 12px;
}
.um-vp-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 24px;
  margin-top: 24px;
  border-top: 1px solid var(--line, #dde5e1);
  padding-top: 24px;
}
.um-vp-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.um-vp-field label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--muted);
}
.um-vp-field p {
  margin: 0;
  font-size: 0.95rem;
  color: var(--text);
  font-weight: 500;
}
.um-vp-section-title {
  display: block;
  font-size: 0.8rem; 
  font-weight: 600; 
  color: var(--muted); 
  text-transform: uppercase; 
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

/* ── Table cells ── */
.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.user-cell-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.cell-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cell-strong { font-weight: 600; font-size: 0.84rem; color: var(--text); }
.cell-muted  { font-size: 0.78rem; color: var(--muted); }
.cell-mono   { font-size: 0.82rem; font-family: 'JetBrains Mono', monospace; color: var(--text); }

/* ── Modal sizing ── */
.um-modal {
  max-width: 720px;
  width: 100%;
}

/* ── Section tabs ── */
.um-section-tabs {
  display: flex;
  gap: 4px;
  padding: 0 24px 16px;
  border-bottom: 1px solid var(--line, #dde5e1);
  margin-bottom: 20px;
}
.um-section-tab {
  height: 34px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: transparent;
  font-size: 0.84rem;
  font-weight: 600;
  font-family: inherit;
  color: var(--muted);
  cursor: pointer;
  transition: background 120ms, color 120ms;
}
.um-section-tab:hover { background: var(--bg); color: var(--text); }
.um-section-tab--on {
  background: var(--green-soft, #e1f5ee);
  color: var(--green-deep, #085041);
  border-color: rgba(29,158,117,0.3);
}

.um-tab-body { padding: 0 4px; }

/* ── GPA strip ── */
.um-gpa-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 20px;
  background: var(--bg, #eff2f0);
  border: 1px solid var(--line, #dde5e1);
  border-radius: 14px;
  padding: 16px 20px;
}
.um-gpa-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.um-gpa-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: var(--muted); margin: 0; }
.um-gpa-value { font-size: 1.4rem; font-weight: 800; letter-spacing: -0.03em; color: var(--text); }
.um-gpa-loading { grid-column: 1/-1; display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 0.84rem; }
.um-gpa-empty { grid-column: 1/-1; color: var(--muted); font-size: 0.84rem; }

/* ── Terms mini table ── */
.um-terms-table {
  margin-top: 8px;
  border: 1px solid var(--line, #dde5e1);
  border-radius: 10px;
  overflow: hidden;
}
.um-terms-table table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.um-terms-table th {
  padding: 8px 12px;
  text-align: left;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--muted);
  background: transparent;
  border-bottom: 1px solid var(--line);
}
.um-terms-table td { padding: 8px 12px; border-bottom: 1px solid var(--line); color: var(--text); }
.um-terms-table tr:last-child td { border-bottom: none; }

/* ── Dark mode ── */
[data-theme="dark"] .um-section-tab--on { background: rgba(29,158,117,0.15); }
[data-theme="dark"] .um-gpa-strip { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .um-terms-table { border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .um-terms-table th, [data-theme="dark"] .um-terms-table td { border-color: rgba(255,255,255,0.07); }

/* ── Filter panel ── */
.uf-filter-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  height: 36px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--line-strong, rgba(31,49,43,0.16));
  background: var(--surface-strong, #fff);
  font-size: 0.82rem;
  font-weight: 600;
  font-family: inherit;
  color: var(--muted);
  cursor: pointer;
  transition: background 120ms, border-color 120ms, color 120ms;
}
.uf-filter-btn:hover { background: var(--bg); color: var(--text); border-color: var(--line-strong); }
.uf-filter-btn--active { color: var(--green-deep, #085041); border-color: rgba(29,158,117,0.4); background: var(--green-soft, #e1f5ee); }
.uf-filter-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--green, #1d9e75);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
}

.uf-filter-panel {
  border-top: 1px solid var(--line, #dde5e1);
  padding: 16px 24px 12px;
  background: var(--bg, #eff2f0);
  margin: 0 -24px;
}
.uf-filter-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr) auto;
  gap: 10px;
  align-items: end;
}
.uf-filter-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.uf-filter-field span {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--muted);
}
.uf-filter-field select {
  height: 34px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--line-strong, rgba(31,49,43,0.16));
  background: var(--surface-strong, #fff);
  font-size: 0.82rem;
  color: var(--text);
  font-family: inherit;
  cursor: pointer;
  appearance: auto;
}
.uf-filter-actions {
  display: flex;
  align-items: flex-end;
  padding-bottom: 1px;
}
.uf-reset-btn {
  height: 34px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid rgba(239,68,68,0.22);
  background: rgba(239,68,68,0.07);
  color: #b91c1c;
  font-size: 0.78rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition: background 120ms;
}
.uf-reset-btn:hover { background: rgba(239,68,68,0.14); }

/* Active filter chips */
.uf-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}
.uf-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 26px;
  padding: 0 10px 0 12px;
  border-radius: 999px;
  background: rgba(29,158,117,0.1);
  border: 1px solid rgba(29,158,117,0.22);
  color: var(--green-deep, #085041);
  font-size: 0.75rem;
  font-weight: 600;
}
.uf-chip button {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--green-deep, #085041);
  font-size: 1rem;
  line-height: 1;
  padding: 0;
  display: flex;
  align-items: center;
  opacity: 0.7;
}
.uf-chip button:hover { opacity: 1; }

/* Meta bar reset link */
.crud-meta { display: flex; align-items: center; gap: 12px; }
.uf-meta-reset {
  background: none;
  border: none;
  font-size: 0.78rem;
  color: var(--muted);
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
  font-family: inherit;
}
.uf-meta-reset:hover { color: #b91c1c; }

/* Responsive */
@media (max-width: 1000px) {
  .uf-filter-grid { grid-template-columns: repeat(3, 1fr); }
  .uf-filter-actions { grid-column: 1 / -1; justify-content: flex-start; }
}
@media (max-width: 640px) {
  .uf-filter-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Dark mode */
[data-theme="dark"] .uf-filter-btn { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.12); }
[data-theme="dark"] .uf-filter-btn--active { background: rgba(29,158,117,0.15); border-color: rgba(29,158,117,0.35); }
[data-theme="dark"] .uf-filter-panel { background: rgba(0,0,0,0.15); border-color: rgba(255,255,255,0.08); }
[data-theme="dark"] .uf-filter-field select { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); color: #e8eeec; }
[data-theme="dark"] .uf-chip { background: rgba(29,158,117,0.15); border-color: rgba(29,158,117,0.3); }
</style>
