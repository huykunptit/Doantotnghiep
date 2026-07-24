<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import MediaUpload from '~/components/common/MediaUpload.vue'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
  adminSearchPlaceholder: 'Tìm người dùng, email, MSSV...',
})

interface RelItem { id: number; name: string; code?: string }
interface RoleItem { id: number; name: string }
interface AdminUser {
  id: number
  name: string
  email: string
  avatar?: string | null
  created_at?: string | null
  roles?: RoleItem[]
  student_code?: string | null
  staff_code?: string | null
  phone?: string | null
  gender?: string | null
  date_of_birth?: string | null
  nationality?: string | null
  hometown?: string | null
  permanent_address?: string | null
  id_card_number?: string | null
  study_status?: string | null
  bio?: string | null
  administrative_class?: RelItem | null
  administrative_class_id?: number | null
  cohort?: RelItem | null
  cohort_id?: number | null
  program?: RelItem | null
  program_id?: number | null
  major?: RelItem | null
  major_id?: number | null
}
interface AcademicSummary {
  overall_gpa: number | null
  total_credits: number
  total_courses: number
  terms: Array<{
    term?: { id: number; name?: string } | null
    course_count: number
    credit_count: number
    gpa: number | null
  }>
}

const ROLE_LABELS: Record<string, string> = {
  admin: 'Quản trị viên',
  instructor: 'Giảng viên',
  student: 'Sinh viên',
  academic_manager: 'Quản lý học vụ',
}
const STATUS_LABELS: Record<string, string> = {
  dang_hoc: 'Đang học',
  bao_luu: 'Bảo lưu',
  tot_nghiep: 'Tốt nghiệp',
  thoi_hoc: 'Thôi học',
  dinh_chi: 'Đình chỉ',
  dang_cong_tac: 'Đang công tác',
  nghi_phep: 'Nghỉ phép',
  nghi_huu: 'Nghỉ hưu',
}
const GENDER_LABELS: Record<string, string> = {
  male: 'Nam',
  female: 'Nữ',
  other: 'Khác',
}

const roleOptions = Object.entries(ROLE_LABELS).map(([value, label]) => ({ value, label }))
const statusOptions = Object.entries(STATUS_LABELS).map(([value, label]) => ({ value, label }))
const genderOptions = Object.entries(GENDER_LABELS).map(([value, label]) => ({ value, label }))

const token = useAuthTokenCookie()
const toast = useToast()
const { exportToCSV } = useExport()

const users = ref<AdminUser[]>([])
const selectedUsers = ref<AdminUser[]>([])
const expandedRows = ref<Record<number, boolean>>({})
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const currentPage = ref(1)
const perPage = ref(25)
const totalUsers = ref(0)
const stats = reactive({ total: 0, students: 0, instructors: 0, admins: 0 })

const filters = reactive({
  search: '',
  role: '',
  study_status: '',
  cohort_id: '',
  administrative_class_id: '',
})

const optClasses = ref<RelItem[]>([])
const optCohorts = ref<RelItem[]>([])
const optPrograms = ref<RelItem[]>([])
const optMajors = ref<RelItem[]>([])

const editorOpen = ref(false)
const detailOpen = ref(false)
const confirmOpen = ref(false)
const bulkConfirmOpen = ref(false)
const editorMode = ref<'create' | 'edit'>('create')
const editorTab = ref('account')
const selectedUser = ref<AdminUser | null>(null)
const formError = ref('')
const academicSummary = ref<AcademicSummary | null>(null)
const loadingSummary = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  avatar: '',
  role: 'student',
  bio: '',
  student_code: '',
  staff_code: '',
  administrative_class_id: '',
  cohort_id: '',
  program_id: '',
  major_id: '',
  study_status: '',
  phone: '',
  gender: '',
  date_of_birth: '',
  nationality: 'Việt Nam',
  hometown: '',
  permanent_address: '',
  id_card_number: '',
})

const firstRow = computed(() => (currentPage.value - 1) * perPage.value)
const activeFilterCount = computed(() => Object.values(filters).filter(Boolean).length)
const selectedIds = computed(() => selectedUsers.value.map(item => item.id))
const summaryItems = computed(() => [
  { label: 'Tất cả', value: stats.total, icon: 'pi-users', tone: 'primary' },
  { label: 'Sinh viên', value: stats.students, icon: 'pi-graduation-cap', tone: 'blue' },
  { label: 'Giảng viên', value: stats.instructors, icon: 'pi-id-card', tone: 'amber' },
  { label: 'Quản trị', value: stats.admins, icon: 'pi-shield', tone: 'violet' },
])

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}
function roleOf(user?: AdminUser | null) {
  return user?.roles?.[0]?.name || 'student'
}
function roleSeverity(role: string) {
  if (role === 'admin') return 'danger'
  if (role === 'instructor') return 'info'
  if (role === 'academic_manager') return 'warn'
  return 'success'
}
function statusSeverity(status?: string | null) {
  if (['dang_hoc', 'dang_cong_tac'].includes(status || '')) return 'success'
  if (['bao_luu', 'nghi_phep'].includes(status || '')) return 'warn'
  if (status === 'tot_nghiep') return 'info'
  if (['thoi_hoc', 'dinh_chi'].includes(status || '')) return 'danger'
  return 'secondary'
}
function initials(name = '') {
  return name.split(' ').filter(Boolean).slice(-2).map(item => item[0]).join('').toUpperCase() || '?'
}
function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}
function buildQuery(page = 1, limit = perPage.value) {
  const query = new URLSearchParams({ page: String(page), per_page: String(limit) })
  Object.entries(filters).forEach(([key, value]) => {
    if (value.trim()) query.set(key, value.trim())
  })
  return query
}

async function loadUsers(page = 1) {
  loading.value = true
  try {
    const response = await useApi<any>(`/admin/users?${buildQuery(page)}`, { headers: authHeaders() })
    users.value = response.data || []
    currentPage.value = response.current_page || 1
    totalUsers.value = response.total || 0
    selectedUsers.value = selectedUsers.value.filter(item => users.value.some(user => user.id === item.id))
  }
  catch (error: any) {
    toast.error('Không thể tải người dùng', error?.data?.message)
  }
  finally {
    loading.value = false
  }
}
async function loadStats() {
  try {
    const [all, students, instructors, admins] = await Promise.all([
      useApi<any>('/admin/users?per_page=1', { headers: authHeaders() }),
      useApi<any>('/admin/users?role=student&per_page=1', { headers: authHeaders() }),
      useApi<any>('/admin/users?role=instructor&per_page=1', { headers: authHeaders() }),
      useApi<any>('/admin/users?role=admin&per_page=1', { headers: authHeaders() }),
    ])
    Object.assign(stats, {
      total: all.total || 0,
      students: students.total || 0,
      instructors: instructors.total || 0,
      admins: admins.total || 0,
    })
  }
  catch {}
}
async function loadOptions() {
  try {
    const headers = authHeaders()
    const [classes, cohorts, programs, majors] = await Promise.all([
      useApi<any>('/admin/academic/administrative-classes?per_page=200', { headers }),
      useApi<any>('/admin/academic/cohorts?per_page=200', { headers }),
      useApi<any>('/admin/academic/programs?per_page=200', { headers }),
      useApi<any>('/admin/academic/majors?per_page=200', { headers }),
    ])
    optClasses.value = classes.data || []
    optCohorts.value = cohorts.data || []
    optPrograms.value = programs.data || []
    optMajors.value = majors.data || []
  }
  catch {
    toast.error('Không thể tải danh mục học vụ')
  }
}
async function loadAcademicSummary(id: number) {
  academicSummary.value = null
  loadingSummary.value = true
  try {
    academicSummary.value = await useApi<AcademicSummary>(`/admin/users/${id}/academic-summary`, {
      headers: authHeaders(),
    })
  }
  catch {
    academicSummary.value = null
  }
  finally {
    loadingSummary.value = false
  }
}

function resetForm() {
  Object.assign(form, {
    name: '', email: '', password: '', avatar: '', role: 'student', bio: '',
    student_code: '', staff_code: '', administrative_class_id: '', cohort_id: '',
    program_id: '', major_id: '', study_status: '', phone: '', gender: '',
    date_of_birth: '', nationality: 'Việt Nam', hometown: '',
    permanent_address: '', id_card_number: '',
  })
  formError.value = ''
}
function fillForm(user: AdminUser) {
  Object.assign(form, {
    name: user.name,
    email: user.email,
    password: '',
    avatar: user.avatar || '',
    role: roleOf(user),
    bio: user.bio || '',
    student_code: user.student_code || '',
    staff_code: user.staff_code || '',
    administrative_class_id: user.administrative_class_id ? String(user.administrative_class_id) : '',
    cohort_id: user.cohort_id ? String(user.cohort_id) : '',
    program_id: user.program_id ? String(user.program_id) : '',
    major_id: user.major_id ? String(user.major_id) : '',
    study_status: user.study_status || '',
    phone: user.phone || '',
    gender: user.gender || '',
    date_of_birth: user.date_of_birth?.slice(0, 10) || '',
    nationality: user.nationality || 'Việt Nam',
    hometown: user.hometown || '',
    permanent_address: user.permanent_address || '',
    id_card_number: user.id_card_number || '',
  })
  formError.value = ''
}
function createUser() {
  editorMode.value = 'create'
  editorTab.value = 'account'
  selectedUser.value = null
  resetForm()
  editorOpen.value = true
}
function editUser(user: AdminUser) {
  editorMode.value = 'edit'
  editorTab.value = 'account'
  selectedUser.value = user
  fillForm(user)
  editorOpen.value = true
}
function viewUser(user: AdminUser) {
  selectedUser.value = user
  detailOpen.value = true
  if (roleOf(user) === 'student') loadAcademicSummary(user.id)
}
function askDelete(user: AdminUser) {
  selectedUser.value = user
  confirmOpen.value = true
}
function resetFilters() {
  Object.assign(filters, {
    search: '', role: '', study_status: '', cohort_id: '', administrative_class_id: '',
  })
  loadUsers(1)
}
function validateForm() {
  if (!form.name.trim()) formError.value = 'Vui lòng nhập họ và tên.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) formError.value = 'Email không hợp lệ.'
  else if (editorMode.value === 'create' && form.password.length < 6) formError.value = 'Mật khẩu phải có ít nhất 6 ký tự.'
  else if (editorMode.value === 'edit' && form.password && form.password.length < 6) formError.value = 'Mật khẩu mới phải có ít nhất 6 ký tự.'
  else formError.value = ''
  if (formError.value) editorTab.value = 'account'
  return !formError.value
}

async function saveUser() {
  if (!validateForm()) return
  saving.value = true
  const payload: Record<string, any> = {
    name: form.name.trim(),
    email: form.email.trim(),
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
    if (editorMode.value === 'create') {
      payload.password = form.password
      await useApi('/admin/users', { method: 'POST', headers: authHeaders(), body: payload })
      toast.success('Đã tạo người dùng')
    }
    else if (selectedUser.value) {
      if (form.password) payload.password = form.password
      await useApi(`/admin/users/${selectedUser.value.id}`, {
        method: 'PUT',
        headers: authHeaders(),
        body: payload,
      })
      toast.success('Đã cập nhật người dùng')
    }
    editorOpen.value = false
    await Promise.all([loadUsers(currentPage.value), loadStats()])
  }
  catch (error: any) {
    formError.value = error?.data?.message || 'Không thể lưu thông tin người dùng.'
  }
  finally {
    saving.value = false
  }
}
async function deleteOne() {
  if (!selectedUser.value) return
  deleting.value = true
  try {
    await useApi(`/admin/users/${selectedUser.value.id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })
    toast.success('Đã xóa người dùng')
    confirmOpen.value = false
    selectedUser.value = null
    await Promise.all([loadUsers(currentPage.value), loadStats()])
  }
  catch (error: any) {
    toast.error('Không thể xóa', error?.data?.message)
  }
  finally {
    deleting.value = false
  }
}
async function deleteSelected() {
  if (!selectedIds.value.length) return
  deleting.value = true
  try {
    await Promise.all(selectedIds.value.map(id =>
      useApi(`/admin/users/${id}`, { method: 'DELETE', headers: authHeaders() }),
    ))
    toast.success(`Đã xóa ${selectedIds.value.length} người dùng`)
    selectedUsers.value = []
    bulkConfirmOpen.value = false
    await Promise.all([loadUsers(1), loadStats()])
  }
  catch (error: any) {
    toast.error('Xóa hàng loạt thất bại', error?.data?.message)
  }
  finally {
    deleting.value = false
  }
}
async function exportUsers() {
  try {
    const max = Math.min(Math.max(totalUsers.value, 1), 1000)
    const response = await useApi<any>(`/admin/users?${buildQuery(1, max)}`, { headers: authHeaders() })
    const rows: AdminUser[] = response.data || []
    exportToCSV(rows, [
      { key: 'name', label: 'Họ và tên' },
      { key: 'email', label: 'Email' },
      { key: 'student_code', label: 'MSSV' },
      { key: 'phone', label: 'Số điện thoại' },
      { key: 'role', label: 'Vai trò', format: (_: unknown, row: AdminUser) => ROLE_LABELS[roleOf(row)] },
      { key: 'study_status', label: 'Trạng thái', format: (value: string) => STATUS_LABELS[value] || '—' },
      { key: 'created_at', label: 'Ngày tạo', format: (value: string) => formatDate(value) },
    ], 'danh_sach_nguoi_dung')
    toast.success(`Đã xuất ${rows.length} người dùng`)
  }
  catch (error: any) {
    toast.error('Xuất CSV thất bại', error?.data?.message)
  }
}
function onPage(event: { page: number; rows: number }) {
  perPage.value = event.rows
  loadUsers(event.page + 1)
}

onMounted(() => {
  loadUsers()
  loadStats()
  loadOptions()
})
</script>

<template>
  <div class="user-directory">
    <section class="directory-head">
      <div>
        <div class="eyebrow"><i class="pi pi-circle-fill" /> Trung tâm tài khoản</div>
        <h1>Danh bạ người dùng</h1>
        <p>Quản lý tài khoản, vai trò và hồ sơ học vụ trong một không gian duy nhất.</p>
      </div>
      <div class="head-actions">
        <Button label="Xuất dữ liệu" icon="pi pi-download" severity="secondary" outlined @click="exportUsers" />
        <Button label="Tạo người dùng" icon="pi pi-plus" @click="createUser" />
      </div>
    </section>

    <section class="summary-strip" aria-label="Thống kê người dùng">
      <div v-for="item in summaryItems" :key="item.label" class="summary-item" :class="`is-${item.tone}`">
        <div class="summary-icon"><i :class="['pi', item.icon]" /></div>
        <div><strong>{{ item.value.toLocaleString('vi-VN') }}</strong><span>{{ item.label }}</span></div>
      </div>
    </section>

    <Card class="directory-card">
      <template #content>
        <div class="filter-deck">
          <div class="filter-title">
            <span>Tìm kiếm & bộ lọc</span>
            <small>{{ totalUsers.toLocaleString('vi-VN') }} kết quả</small>
          </div>
          <form class="filter-grid" @submit.prevent="loadUsers(1)">
            <IconField class="search-field">
              <InputIcon class="pi pi-search" />
              <InputText v-model="filters.search" placeholder="Tên, email hoặc mã sinh viên" fluid />
            </IconField>
            <Select v-model="filters.role" :options="roleOptions" option-label="label" option-value="value" placeholder="Vai trò" show-clear fluid @change="loadUsers(1)" />
            <Select v-model="filters.study_status" :options="statusOptions" option-label="label" option-value="value" placeholder="Trạng thái" show-clear fluid @change="loadUsers(1)" />
            <Select v-model="filters.cohort_id" :options="optCohorts" option-label="name" option-value="id" placeholder="Khóa tuyển sinh" show-clear fluid @change="filters.cohort_id = filters.cohort_id ? String(filters.cohort_id) : ''; loadUsers(1)" />
            <Select v-model="filters.administrative_class_id" :options="optClasses" option-label="name" option-value="id" placeholder="Lớp hành chính" show-clear fluid @change="filters.administrative_class_id = filters.administrative_class_id ? String(filters.administrative_class_id) : ''; loadUsers(1)" />
            <Button type="submit" label="Áp dụng" icon="pi pi-filter" />
            <Button v-if="activeFilterCount" label="Đặt lại" icon="pi pi-filter-slash" severity="secondary" text @click="resetFilters" />
          </form>
        </div>

        <div v-if="selectedUsers.length" class="selection-bar">
          <div><i class="pi pi-check-square" /><strong>{{ selectedUsers.length }}</strong> tài khoản được chọn</div>
          <div>
            <Button label="Bỏ chọn" severity="secondary" text size="small" @click="selectedUsers = []" />
            <Button label="Xóa đã chọn" icon="pi pi-trash" severity="danger" size="small" @click="bulkConfirmOpen = true" />
          </div>
        </div>

        <DataTable
          v-model:selection="selectedUsers"
          v-model:expanded-rows="expandedRows"
          :value="users"
          data-key="id"
          :loading="loading"
          lazy
          paginator
          :first="firstRow"
          :rows="perPage"
          :total-records="totalUsers"
          :rows-per-page-options="[10, 25, 50, 100]"
          striped-rows
          class="people-table"
          @page="onPage"
        >
          <template #empty>
            <div class="empty-state"><i class="pi pi-users" /><strong>Không tìm thấy người dùng</strong><span>Hãy thử thay đổi bộ lọc hoặc tạo tài khoản mới.</span></div>
          </template>
          <Column expander style="width:3rem" />
          <Column selection-mode="multiple" header-style="width:3rem" />
          <Column header="Người dùng" style="min-width:18rem">
            <template #body="{ data }">
              <div class="person">
                <Avatar v-if="data.avatar" :image="data.avatar" shape="circle" size="large" />
                <Avatar v-else :label="initials(data.name)" shape="circle" size="large" />
                <div><strong>{{ data.name }}</strong><span>{{ data.email }}</span></div>
              </div>
            </template>
          </Column>
          <Column header="Vai trò" style="min-width:10rem">
            <template #body="{ data }"><Tag :value="ROLE_LABELS[roleOf(data)] || roleOf(data)" :severity="roleSeverity(roleOf(data)) as any" /></template>
          </Column>
          <Column header="Hồ sơ" style="min-width:11rem" class="optional-col">
            <template #body="{ data }">
              <div class="profile-code"><strong>{{ data.student_code || data.staff_code || 'Chưa cấp mã' }}</strong><span>{{ data.administrative_class?.name || data.cohort?.name || 'Chưa xếp lớp' }}</span></div>
            </template>
          </Column>
          <Column header="Trạng thái" style="min-width:9rem">
            <template #body="{ data }">
              <Tag v-if="data.study_status" :value="STATUS_LABELS[data.study_status] || data.study_status" :severity="statusSeverity(data.study_status) as any" />
              <Tag v-else value="Chưa xác định" severity="secondary" />
            </template>
          </Column>
          <Column header="Ngày tạo" style="min-width:8rem" class="optional-col">
            <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
          </Column>
          <Column header="Thao tác" frozen align-frozen="right" style="width:10rem">
            <template #body="{ data }">
              <div class="row-actions">
                <Button icon="pi pi-eye" severity="secondary" text rounded aria-label="Xem hồ sơ" @click="viewUser(data)" />
                <Button icon="pi pi-pencil" text rounded aria-label="Chỉnh sửa" @click="editUser(data)" />
                <Button icon="pi pi-trash" severity="danger" text rounded aria-label="Xóa" @click="askDelete(data)" />
              </div>
            </template>
          </Column>
          <template #expansion="{ data }">
            <div class="expanded-profile">
              <div><span>Mã SV/NV</span><strong>{{ data.student_code || data.staff_code || '—' }}</strong></div>
              <div><span>Lớp hành chính</span><strong>{{ data.administrative_class?.name || '—' }}</strong></div>
              <div><span>Khóa tuyển sinh</span><strong>{{ data.cohort?.name || '—' }}</strong></div>
              <div><span>Chương trình</span><strong>{{ data.program?.name || '—' }}</strong></div>
              <div><span>Số điện thoại</span><strong>{{ data.phone || '—' }}</strong></div>
              <div><span>Ngày tạo</span><strong>{{ formatDate(data.created_at) }}</strong></div>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <Drawer v-model:visible="detailOpen" position="right" class="profile-drawer" header="Hồ sơ người dùng">
      <div v-if="selectedUser" class="drawer-content">
        <div class="profile-identity">
          <Avatar v-if="selectedUser.avatar" :image="selectedUser.avatar" shape="circle" size="xlarge" />
          <Avatar v-else :label="initials(selectedUser.name)" shape="circle" size="xlarge" />
          <div><h2>{{ selectedUser.name }}</h2><p>{{ selectedUser.email }}</p><Tag :value="ROLE_LABELS[roleOf(selectedUser)]" :severity="roleSeverity(roleOf(selectedUser)) as any" /></div>
        </div>
        <p v-if="selectedUser.bio" class="profile-bio">{{ selectedUser.bio }}</p>
        <div v-if="roleOf(selectedUser) === 'student'" class="academic-score">
          <Skeleton v-if="loadingSummary" height="5rem" />
          <template v-else-if="academicSummary">
            <div><strong>{{ academicSummary.overall_gpa?.toFixed(2) ?? '—' }}</strong><span>GPA</span></div>
            <div><strong>{{ academicSummary.total_credits }}</strong><span>Tín chỉ</span></div>
            <div><strong>{{ academicSummary.total_courses }}</strong><span>Môn học</span></div>
          </template>
        </div>
        <div class="detail-list">
          <div><span>Mã SV/NV</span><strong>{{ selectedUser.student_code || selectedUser.staff_code || '—' }}</strong></div>
          <div><span>Trạng thái</span><strong>{{ STATUS_LABELS[selectedUser.study_status || ''] || '—' }}</strong></div>
          <div><span>Lớp hành chính</span><strong>{{ selectedUser.administrative_class?.name || '—' }}</strong></div>
          <div><span>Khóa</span><strong>{{ selectedUser.cohort?.name || '—' }}</strong></div>
          <div><span>Chương trình</span><strong>{{ selectedUser.program?.name || '—' }}</strong></div>
          <div><span>Ngành học</span><strong>{{ selectedUser.major?.name || '—' }}</strong></div>
          <div><span>Số điện thoại</span><strong>{{ selectedUser.phone || '—' }}</strong></div>
          <div><span>Ngày sinh</span><strong>{{ formatDate(selectedUser.date_of_birth) }}</strong></div>
          <div><span>Quốc tịch</span><strong>{{ selectedUser.nationality || '—' }}</strong></div>
          <div><span>Địa chỉ</span><strong>{{ selectedUser.permanent_address || '—' }}</strong></div>
        </div>
        <Button label="Chỉnh sửa hồ sơ" icon="pi pi-pencil" class="drawer-edit" @click="detailOpen = false; editUser(selectedUser)" />
      </div>
    </Drawer>

    <Dialog v-model:visible="editorOpen" modal :header="editorMode === 'create' ? 'Tạo người dùng mới' : 'Chỉnh sửa người dùng'" :style="{ width: 'min(54rem, 96vw)' }">
      <Message v-if="formError" severity="error" :closable="false" class="form-message">{{ formError }}</Message>
      <Tabs v-model:value="editorTab">
        <TabList><Tab value="account">Tài khoản</Tab><Tab value="academic">Học vụ</Tab><Tab value="personal">Cá nhân</Tab></TabList>
        <TabPanels>
          <TabPanel value="account">
            <div class="form-grid">
              <label><span>Họ và tên *</span><InputText v-model="form.name" fluid /></label>
              <label><span>Email *</span><InputText v-model="form.email" type="email" fluid /></label>
              <label><span>Vai trò *</span><Select v-model="form.role" :options="roleOptions" option-label="label" option-value="value" fluid /></label>
              <label><span>{{ editorMode === 'create' ? 'Mật khẩu *' : 'Mật khẩu mới' }}</span><Password v-model="form.password" :feedback="false" toggle-mask fluid /></label>
              <label class="wide"><span>Giới thiệu</span><Textarea v-model="form.bio" rows="3" fluid /></label>
              <div class="wide"><span class="field-name">Ảnh đại diện</span><MediaUpload v-model="form.avatar" folder="users" variant="avatar" label="Ảnh đại diện" hint="JPG, PNG, WEBP — tối đa 5MB." :placeholder-initial="initials(form.name)" /></div>
            </div>
          </TabPanel>
          <TabPanel value="academic">
            <div class="form-grid">
              <label><span>Mã sinh viên</span><InputText v-model="form.student_code" fluid /></label>
              <label><span>Mã nhân viên</span><InputText v-model="form.staff_code" fluid /></label>
              <label><span>Lớp hành chính</span><Select v-model="form.administrative_class_id" :options="optClasses" option-label="name" option-value="id" show-clear filter fluid /></label>
              <label><span>Khóa tuyển sinh</span><Select v-model="form.cohort_id" :options="optCohorts" option-label="name" option-value="id" show-clear filter fluid /></label>
              <label><span>Chương trình đào tạo</span><Select v-model="form.program_id" :options="optPrograms" option-label="name" option-value="id" show-clear filter fluid /></label>
              <label><span>Ngành học</span><Select v-model="form.major_id" :options="optMajors" option-label="name" option-value="id" show-clear filter fluid /></label>
              <label class="wide"><span>Trạng thái học vụ</span><Select v-model="form.study_status" :options="statusOptions" option-label="label" option-value="value" show-clear fluid /></label>
            </div>
          </TabPanel>
          <TabPanel value="personal">
            <div class="form-grid">
              <label><span>Số điện thoại</span><InputText v-model="form.phone" fluid /></label>
              <label><span>Giới tính</span><Select v-model="form.gender" :options="genderOptions" option-label="label" option-value="value" show-clear fluid /></label>
              <label><span>Ngày sinh</span><InputText v-model="form.date_of_birth" type="date" fluid /></label>
              <label><span>Quốc tịch</span><InputText v-model="form.nationality" fluid /></label>
              <label><span>CMND / CCCD</span><InputText v-model="form.id_card_number" fluid /></label>
              <label><span>Quê quán</span><InputText v-model="form.hometown" fluid /></label>
              <label class="wide"><span>Địa chỉ thường trú</span><InputText v-model="form.permanent_address" fluid /></label>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
      <template #footer>
        <Button label="Hủy" severity="secondary" text @click="editorOpen = false" />
        <Button :label="editorMode === 'create' ? 'Tạo tài khoản' : 'Lưu thay đổi'" icon="pi pi-check" :loading="saving" @click="saveUser" />
      </template>
    </Dialog>

    <Dialog v-model:visible="confirmOpen" modal header="Xóa người dùng" :style="{ width: 'min(28rem, 92vw)' }">
      <div class="confirm-copy"><i class="pi pi-trash" /><p>Bạn có chắc muốn xóa <strong>{{ selectedUser?.name }}</strong>? Thao tác này không thể hoàn tác.</p></div>
      <template #footer><Button label="Hủy" severity="secondary" text @click="confirmOpen = false" /><Button label="Xóa người dùng" icon="pi pi-trash" severity="danger" :loading="deleting" @click="deleteOne" /></template>
    </Dialog>
    <Dialog v-model:visible="bulkConfirmOpen" modal header="Xóa các tài khoản đã chọn" :style="{ width: 'min(28rem, 92vw)' }">
      <div class="confirm-copy"><i class="pi pi-trash" /><p>Xóa vĩnh viễn <strong>{{ selectedUsers.length }}</strong> tài khoản đã chọn?</p></div>
      <template #footer><Button label="Hủy" severity="secondary" text @click="bulkConfirmOpen = false" /><Button label="Xóa tất cả" icon="pi pi-trash" severity="danger" :loading="deleting" @click="deleteSelected" /></template>
    </Dialog>
  </div>
</template>

<style scoped>
.user-directory { display:flex; flex-direction:column; gap:1rem; }
.directory-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.directory-head h1 { margin:.25rem 0 0; font-size:1.5rem; line-height:1.2; color:var(--text); letter-spacing:-.035em; }
.directory-head p { margin:.4rem 0 0; color:var(--muted); font-size:.875rem; }
.eyebrow { display:flex; align-items:center; gap:.45rem; color:var(--theme-primary); font-size:.7rem; font-weight:750; text-transform:uppercase; letter-spacing:.12em; }
.eyebrow i { font-size:.4rem; }
.head-actions { display:flex; gap:.5rem; flex-wrap:wrap; }

.summary-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:.65rem; }
.summary-item { display:flex; align-items:center; gap:.7rem; min-height:68px; padding:.7rem .85rem; border:1px solid var(--line); border-radius:12px; background:var(--surface-strong); }
.summary-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:9px; background:rgba(var(--theme-primary-rgb),.1); color:var(--theme-primary); }
.summary-item > div:last-child { display:flex; flex-direction:column; }
.summary-item strong { color:var(--text); font-size:1.2rem; line-height:1.15; }
.summary-item span { color:var(--muted); font-size:.72rem; }
.summary-item.is-blue .summary-icon { background:rgba(59,130,246,.1); color:#3b82f6; }
.summary-item.is-amber .summary-icon { background:rgba(245,158,11,.1); color:#f59e0b; }
.summary-item.is-violet .summary-icon { background:rgba(139,92,246,.1); color:#8b5cf6; }

.directory-card :deep(.p-card-body), .directory-card :deep(.p-card-content) { padding:0; }
.filter-deck { padding:.9rem 1rem; border-bottom:1px solid var(--line); background:var(--surface); }
.filter-title { display:flex; justify-content:space-between; align-items:center; margin-bottom:.65rem; }
.filter-title span { color:var(--text); font-size:.78rem; font-weight:700; }
.filter-title small { color:var(--muted); }
.filter-grid { display:grid; grid-template-columns:minmax(220px,1.5fr) repeat(4,minmax(130px,1fr)) auto auto; gap:.5rem; align-items:center; }
.selection-bar { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.6rem 1rem; background:rgba(var(--theme-primary-rgb),.08); border-bottom:1px solid rgba(var(--theme-primary-rgb),.18); color:var(--text); font-size:.8rem; }
.selection-bar > div { display:flex; align-items:center; gap:.45rem; }
.person { display:flex; align-items:center; gap:.65rem; min-width:0; }
.person > div { display:flex; flex-direction:column; min-width:0; }
.person strong, .profile-code strong { color:var(--text); font-size:.82rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.person span, .profile-code span { color:var(--muted); font-size:.72rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.profile-code { display:flex; flex-direction:column; }
.row-actions { display:flex; justify-content:flex-end; gap:.1rem; }
.expanded-profile { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; padding:1rem 1.5rem; background:var(--surface); }
.expanded-profile div, .detail-list div { display:flex; flex-direction:column; gap:.2rem; }
.expanded-profile span, .detail-list span { color:var(--muted); font-size:.68rem; font-weight:650; text-transform:uppercase; letter-spacing:.05em; }
.expanded-profile strong, .detail-list strong { color:var(--text); font-size:.82rem; }
.empty-state { display:flex; flex-direction:column; align-items:center; gap:.4rem; padding:2rem; color:var(--muted); }
.empty-state i { font-size:2rem; opacity:.35; }
.empty-state strong { color:var(--text); }

.drawer-content { display:flex; flex-direction:column; gap:1rem; }
.profile-identity { display:flex; align-items:center; gap:.9rem; padding-bottom:1rem; border-bottom:1px solid var(--line); }
.profile-identity h2 { margin:0; color:var(--text); font-size:1.15rem; }
.profile-identity p { margin:.2rem 0 .4rem; color:var(--muted); font-size:.8rem; }
.profile-bio { margin:0; padding:.8rem; background:var(--surface); border-radius:10px; color:var(--text); font-size:.82rem; line-height:1.55; }
.academic-score { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; }
.academic-score > div { display:flex; flex-direction:column; align-items:center; padding:.75rem; border:1px solid var(--line); border-radius:10px; background:var(--surface); }
.academic-score strong { color:var(--text); font-size:1.2rem; }
.academic-score span { color:var(--muted); font-size:.68rem; }
.detail-list { display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
.drawer-edit { margin-top:auto; }

.form-message { margin-bottom:.75rem; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; padding-top:.5rem; }
.form-grid label { display:flex; flex-direction:column; gap:.35rem; }
.form-grid label > span, .field-name { color:var(--muted); font-size:.75rem; font-weight:650; }
.wide { grid-column:1/-1; }
.confirm-copy { display:flex; align-items:flex-start; gap:.8rem; }
.confirm-copy > i { display:grid; place-items:center; width:40px; height:40px; flex:0 0 40px; border-radius:10px; background:rgba(239,68,68,.1); color:#ef4444; }
.confirm-copy p { margin:0; color:var(--muted); line-height:1.6; }
.confirm-copy strong { color:var(--text); }

:global(.profile-drawer) { width:min(28rem,92vw) !important; }
@media (max-width:1200px) {
  .filter-grid { grid-template-columns:repeat(3,1fr); }
  .search-field { grid-column:span 2; }
}
@media (max-width:900px) {
  .summary-strip { grid-template-columns:repeat(2,1fr); }
  .optional-col { display:none; }
  .expanded-profile { grid-template-columns:repeat(2,1fr); }
}
@media (max-width:640px) {
  .filter-grid, .form-grid, .expanded-profile, .detail-list { grid-template-columns:1fr; }
  .search-field, .wide { grid-column:auto; }
  .summary-item { min-height:60px; }
  .head-actions { width:100%; }
  .head-actions :deep(.p-button) { flex:1; }
}
</style>
