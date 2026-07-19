<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
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
  layout: 'admin'
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
  administrative_class?: RelItem | null
  cohort?: RelItem | null
  program?: RelItem | null
  major?: RelItem | null
  unit?: RelItem | null
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

const token = useAuthTokenCookie()

const filters = reactive({
  search: '',
  role: '',
  study_status: '',
  gender: '',
  cohort_id: '',
  program_id: '',
  administrative_class_id: '',
  unit_id: ''
})

const activeFilterCount = computed(() => {
  return [filters.role, filters.study_status, filters.gender, filters.cohort_id, filters.program_id, filters.administrative_class_id, filters.unit_id]
    .filter(Boolean).length
})

const columns = [
  { id: 'select', accessorKey: 'select', header: '' },
  { id: 'index', accessorKey: 'index', header: '#' },
  { id: 'user', accessorKey: 'name', header: 'Người dùng', sortable: true },
  { id: 'role', accessorKey: 'role', header: 'Vai trò', sortable: true },
  { id: 'class', accessorKey: 'class', header: 'Lớp HC / Khóa' },
  { id: 'code', accessorKey: 'student_code', header: 'MSSV / Mã NV', sortable: true },
  { id: 'phone', accessorKey: 'phone', header: 'SĐT', sortable: true },
  { id: 'status', accessorKey: 'study_status', header: 'Trạng thái', sortable: true },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

const sortBy = ref('')
const sortOrder = ref<'asc' | 'desc' | ''>('')

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
const statsStudents = ref(0)
const statsInstructors = ref(0)
const statsAdmins = ref(0)

// Dropdowns lists
const optAdminClasses = ref<RelItem[]>([])
const optCohorts = ref<RelItem[]>([])
const optPrograms = ref<RelItem[]>([])
const optUnits = ref<RelItem[]>([])
const optionsLoaded = ref(false)

const unitSearchQuery = ref('')
const filteredUnits = computed(() => {
  if (!unitSearchQuery.value.trim()) return optUnits.value
  const q = unitSearchQuery.value.toLowerCase()
  return optUnits.value.filter(u => u.name.toLowerCase().includes(q))
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

// Import States
const importModalOpen = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'student',
  student_code: '',
  staff_code: '',
  administrative_class_id: '',
  cohort_id: '',
  program_id: '',
  study_status: 'dang_hoc',
  phone: '',
  gender: 'male',
})

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

function resolveRole(item: AdminUser | null) {
  if (!item) return 'student'
  return item.roles?.[0]?.name || 'student'
}

function avatarInitials(name: string) {
  return name.split(' ').slice(-2).map(p => p.charAt(0)).join('').toUpperCase()
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
    if (filters.unit_id) query.set('unit_id', filters.unit_id)
    
    if (sortBy.value && sortOrder.value) {
      query.set('sort_by', sortBy.value)
      query.set('sort_order', sortOrder.value)
    }

    const response = await useApi<any>(`/admin/users?${query.toString()}`, { headers: authHeaders() })
    users.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    totalUsers.value = response.total
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
    const [adminClasses, cohorts, programs, units] = await Promise.all([
      useApi<any>('/admin/academic/administrative-classes?per_page=200', { headers }),
      useApi<any>('/admin/academic/cohorts?per_page=200', { headers }),
      useApi<any>('/admin/academic/programs?per_page=200', { headers }),
      useApi<any>('/admin/academic/units?per_page=200', { headers }),
    ])
    optAdminClasses.value = adminClasses.data || []
    optCohorts.value = cohorts.data || []
    optPrograms.value = programs.data || []
    optUnits.value = units.data || []
    optionsLoaded.value = true
  } catch (_) {}
}

function handleRemoveChip(key: string) {
  if (key in filters) {
    filters[key as keyof typeof filters] = ''
    fetchUsers(1)
  }
}

function handleSort(event: { key: string; order: 'asc' | 'desc' | '' }) {
  sortBy.value = event.key
  sortOrder.value = event.order
  fetchUsers(1)
}

function resetFilters() {
  filters.search = ''
  filters.role = ''
  filters.study_status = ''
  filters.gender = ''
  filters.cohort_id = ''
  filters.program_id = ''
  filters.administrative_class_id = ''
  filters.unit_id = ''
  sortBy.value = ''
  sortOrder.value = ''
  fetchUsers(1)
}

function openCreateModal() {
  modalMode.value = 'create'
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = 'student'
  form.student_code = ''
  form.staff_code = ''
  form.administrative_class_id = ''
  form.cohort_id = ''
  form.program_id = ''
  form.study_status = 'dang_hoc'
  form.phone = ''
  form.gender = 'male'
  modalOpen.value = true
}

function openEditModal(item: AdminUser) {
  modalMode.value = 'edit'
  selectedUser.value = item
  form.name = item.name
  form.email = item.email
  form.password = ''
  form.role = resolveRole(item)
  form.student_code = item.student_code || ''
  form.staff_code = item.staff_code || ''
  form.administrative_class_id = item.administrative_class ? String(item.administrative_class.id) : ''
  form.cohort_id = item.cohort ? String(item.cohort.id) : ''
  form.program_id = item.program ? String(item.program.id) : ''
  form.study_status = item.study_status || 'dang_hoc'
  form.phone = item.phone || ''
  form.gender = item.gender || 'male'
  modalOpen.value = true
}

function openViewModal(item: AdminUser) {
  modalMode.value = 'view'
  selectedUser.value = item
  modalOpen.value = true
}

async function saveUser() {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  
  const payload: Record<string, any> = {
    name: form.name,
    email: form.email,
    role: form.role,
    study_status: form.study_status,
    phone: form.phone,
    gender: form.gender,
  }
  
  if (form.password) payload.password = form.password
  if (form.role === 'student') {
    payload.student_code = form.student_code
    if (form.administrative_class_id) payload.administrative_class_id = Number(form.administrative_class_id)
    if (form.cohort_id) payload.cohort_id = Number(form.cohort_id)
    if (form.program_id) payload.program_id = Number(form.program_id)
  } else {
    payload.staff_code = form.staff_code
  }
  
  try {
    if (modalMode.value === 'create') {
      await useApi('/admin/users', { method: 'POST', headers: authHeaders(), body: payload })
      successMessage.value = 'Tạo người dùng thành công.'
    } else if (selectedUser.value) {
      await useApi(`/admin/users/${selectedUser.value.id}`, { method: 'PUT', headers: authHeaders(), body: payload })
      successMessage.value = 'Cập nhật người dùng thành công.'
    }
    modalOpen.value = false
    await fetchUsers(currentPage.value)
    await fetchStats()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Lỗi khi lưu người dùng.'
  } finally {
    saving.value = false
  }
}

function confirmDelete(item: AdminUser) {
  selectedUser.value = item
  deleteModalOpen.value = true
}

async function deleteUser() {
  if (!selectedUser.value) return
  saving.value = true
  try {
    await useApi(`/admin/users/${selectedUser.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Xóa người dùng thành công.'
    deleteModalOpen.value = false
    await fetchUsers(1)
    await fetchStats()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Lỗi khi xóa người dùng.'
  } finally {
    saving.value = false
  }
}

const activeChips = computed(() => {
  const chips = []
  if (filters.role) {
    chips.push({ key: 'role', label: `Vai trò: ${roleOptions.find(r => r.value === filters.role)?.label}` })
  }
  if (filters.study_status) {
    chips.push({ key: 'study_status', label: `Trạng thái: ${STUDY_STATUS_LABELS[filters.study_status]}` })
  }
  if (filters.gender) {
    chips.push({ key: 'gender', label: `Giới tính: ${filters.gender === 'male' ? 'Nam' : 'Nữ'}` })
  }
  if (filters.cohort_id) {
    const c = optCohorts.value.find(x => String(x.id) === filters.cohort_id)
    if (c) chips.push({ key: 'cohort_id', label: `Khóa: ${c.name}` })
  }
  if (filters.administrative_class_id) {
    const ac = optAdminClasses.value.find(x => String(x.id) === filters.administrative_class_id)
    if (ac) chips.push({ key: 'administrative_class_id', label: `Lớp HC: ${ac.name}` })
  }
  return chips
})

onMounted(() => {
  fetchUsers()
  fetchStats()
  fetchFormOptions()
})
</script>

<template>
  <AdminWorkspaceShell 
    title="Quản lý học viên" 
    subtitle="Quản lý tài khoản học viên và cán bộ giảng viên."
    :breadcrumbs="[{ label: 'Người dùng & Tổ chức' }, { label: 'Học viên' }]"
  >
    <div class="flex flex-col gap-5">
      
      <!-- Toolbar Filters at the top -->
      <UiFilters
        v-model:search="filters.search"
        search-placeholder="Tìm người dùng, email, MSSV..."
        :active-filter-count="activeFilterCount"
        :active-chips="activeChips"
        :show-export="true"
        :show-import="true"
        :always-open="true"
        @submit-search="fetchUsers(1)"
        @reset-filters="resetFilters"
        @remove-chip="handleRemoveChip"
        @export="exportToCSV(users, [], 'danh_sach_hoc_vien')"
        @import="importModalOpen = true"
      >
        <!-- Custom Actions Slot -->
        <template #actions>
          <button
            class="inline-flex items-center gap-2 h-9 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#158260] text-white text-xs font-bold transition-all shrink-0 cursor-pointer"
            type="button"
            @click="openCreateModal"
          >
            <i class="pi pi-plus" />
            Tạo học viên
          </button>
        </template>

        <!-- Advanced Filters Slot -->
        <template #advanced>
          <label class="flex flex-col gap-1 min-w-[170px]">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Vai trò</span>
            <UiSelect
              v-model="filters.role"
              :options="roleOptions"
              placeholder="Tất cả vai trò"
              @update:modelValue="fetchUsers(1)"
            />
          </label>
          <label class="flex flex-col gap-1 min-w-[170px]">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Trạng thái</span>
            <UiSelect
              v-model="filters.study_status"
              :options="statusOptions"
              placeholder="Tất cả trạng thái"
              @update:modelValue="fetchUsers(1)"
            />
          </label>
          <label class="flex flex-col gap-1 min-w-[170px]">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Giới tính</span>
            <UiSelect
              v-model="filters.gender"
              :options="genderOptions"
              placeholder="Tất cả giới tính"
              @update:modelValue="fetchUsers(1)"
            />
          </label>
          <label class="flex flex-col gap-1 min-w-[170px]">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Khóa</span>
            <UiSelect
              v-model="filters.cohort_id"
              :options="cohortOptions"
              placeholder="Tất cả khóa"
              @update:modelValue="fetchUsers(1)"
            />
          </label>
          <label class="flex flex-col gap-1 min-w-[170px]">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Lớp hành chính</span>
            <UiSelect
              v-model="filters.administrative_class_id"
              :options="classOptions"
              placeholder="Tất cả lớp"
              @update:modelValue="fetchUsers(1)"
            />
          </label>
        </template>
      </UiFilters>

      <!-- Main Layout Grid -->
      <div class="flex flex-col lg:flex-row gap-5 items-start">
        
        <!-- Organization Tree Filter (Left Panel) -->
        <aside class="w-full lg:w-80 shrink-0 bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-0.5">Sơ đồ tổ chức</p>
            <h3 class="text-sm font-bold text-[var(--text)]">Cơ cấu đơn vị</h3>
          </div>

          <!-- Search Unit -->
          <div class="relative">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)] text-sm" />
            <input
              v-model="unitSearchQuery"
              type="text"
              placeholder="Tìm kiếm đơn vị..."
              class="w-full h-9 pl-9 pr-3 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]"
            />
          </div>

          <!-- Units Scrollable List -->
          <div class="flex flex-col gap-1 max-h-[420px] overflow-y-auto pr-1">
            <!-- All units selector -->
            <button
              type="button"
              class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all text-left cursor-pointer border border-transparent"
              :class="!filters.unit_id ? 'bg-[rgba(29,158,117,0.08)] text-[#085041] border-[rgba(29,158,117,0.15)] font-bold' : 'bg-transparent text-[var(--muted)] hover:bg-[#f1f5f9] hover:text-[var(--text)]'"
              @click="filters.unit_id = ''; fetchUsers(1)"
            >
              <i class="pi pi-globe text-sm shrink-0" />
              <span class="truncate">Tất cả đơn vị</span>
            </button>

            <!-- Dynamic units list -->
            <button
              v-for="u in filteredUnits"
              :key="u.id"
              type="button"
              class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all text-left cursor-pointer border border-transparent"
              :class="filters.unit_id === String(u.id) ? 'bg-[rgba(29,158,117,0.08)] text-[#085041] border-[rgba(29,158,117,0.15)] font-bold' : 'text-[var(--text)] hover:bg-[#f1f5f9]'"
              @click="filters.unit_id = String(u.id); fetchUsers(1)"
            >
              <i class="pi pi-building text-sm shrink-0 text-[var(--muted)]" :class="{ '!text-[#1d9e75]': filters.unit_id === String(u.id) }" />
              <span class="truncate">{{ u.name }}</span>
            </button>
          </div>
        </aside>

        <!-- Right Panel: KPIs & Data Grid -->
        <div class="flex-1 w-full flex flex-col gap-5">
          <!-- KPIs Cards -->
          <UiKpiCards
            :items="[
              { label: 'Sinh viên', value: statsStudents, subText: 'đang học', color: 'info', icon: 'pi-graduation-cap' },
              { label: 'Giảng viên', value: statsInstructors, subText: 'đang công tác', color: 'warning', icon: 'pi-user' },
              { label: 'Quản trị viên', value: statsAdmins, subText: 'đang hoạt động', color: 'purple', icon: 'pi-cog' }
            ]"
          />

          <!-- Alert Notices -->
          <div v-if="successMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl flex items-center gap-2">
            <i class="pi pi-check-circle" /> {{ successMessage }}
          </div>
          <div v-if="errorMessage" class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
            <i class="pi pi-exclamation-circle" /> {{ errorMessage }}
          </div>

          <!-- Data Grid Container -->
          <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <UiTable
              :columns="columns"
              :data="users"
              :loading="loading"
              :sort-by="sortBy"
              :sort-order="sortOrder"
              @sort="handleSort"
            >
              <!-- Checkbox Headers & Cells -->
              <template #select-header>
                <input type="checkbox" :checked="isAllSelected" class="rounded border-[#cbd5e1]" @change="toggleSelectAll" />
              </template>
              <template #select-cell="{ row }">
                <input type="checkbox" :value="row.original.id" v-model="selectedIds" class="rounded border-[#cbd5e1]" />
              </template>

              <!-- Index Column -->
              <template #index-cell="{ row }">
                <span class="text-xs text-[var(--muted)] font-medium">{{ (currentPage - 1) * perPage + row.index + 1 }}</span>
              </template>

              <!-- User Cell -->
              <template #user-cell="{ row }">
                <div class="flex items-center gap-3">
                  <div class="w-8.5 h-8.5 rounded-full shrink-0 overflow-hidden flex items-center justify-center bg-[rgba(29,158,117,0.1)] text-[#085041] text-xs font-bold border border-[rgba(29,158,117,0.2)]">
                    <img v-if="row.original.avatar" :src="row.original.avatar" :alt="row.original.name" class="w-full h-full object-cover" />
                    <span v-else>{{ avatarInitials(row.original.name) }}</span>
                  </div>
                  <div class="flex flex-col min-w-0">
                    <strong class="text-xs font-bold text-[var(--text)] truncate">{{ row.original.name }}</strong>
                    <span class="text-[10px] text-[var(--muted)] truncate mt-0.5">{{ row.original.email }}</span>
                  </div>
                </div>
              </template>

              <!-- Role Cell -->
              <template #role-cell="{ row }">
                <span class="inline-flex items-center h-5 px-2.5 rounded-full text-[10px] font-bold" :class="{
                  'bg-purple-50 text-purple-700 border border-purple-200': resolveRole(row.original) === 'admin',
                  'bg-blue-50 text-blue-700 border border-blue-200': resolveRole(row.original) === 'instructor',
                  'bg-emerald-50 text-[#085041] border border-[rgba(29,158,117,0.2)]': resolveRole(row.original) === 'student',
                  'bg-amber-50 text-amber-700 border border-amber-200': resolveRole(row.original) === 'academic_manager',
                }">{{ resolveRole(row.original) }}</span>
              </template>

              <!-- Administrative Class Cell -->
              <template #class-cell="{ row }">
                <div v-if="row.original.administrative_class || row.original.cohort" class="flex flex-col">
                  <span v-if="row.original.administrative_class" class="text-xs font-semibold text-[var(--text)]">{{ row.original.administrative_class.name }}</span>
                  <span v-if="row.original.cohort" class="text-[10px] text-[var(--muted)] mt-0.5">{{ row.original.cohort.name }}</span>
                </div>
                <span v-else class="text-[var(--muted)] text-xs">—</span>
              </template>

              <!-- Code Cell -->
              <template #code-cell="{ row }">
                <span class="text-xs font-mono text-[var(--text)] font-semibold">{{ row.original.student_code || row.original.staff_code || '—' }}</span>
              </template>

              <!-- Phone Cell -->
              <template #phone-cell="{ row }">
                <span class="text-xs text-[var(--muted)]">{{ row.original.phone || '—' }}</span>
              </template>

              <!-- Status Switch Cell (Modified to look like admin-ui switch) -->
              <template #status-cell="{ row }">
                <div class="flex items-center">
                  <span class="inline-flex items-center h-5 px-2 rounded-full text-[10px] font-bold" :class="{
                    'bg-emerald-50 text-emerald-700 border border-emerald-200': ['dang_hoc','dang_cong_tac'].includes(row.original.study_status || ''),
                    'bg-amber-50 text-amber-700 border border-amber-200': ['bao_luu','nghi_phep'].includes(row.original.study_status || ''),
                    'bg-blue-50 text-blue-700 border border-blue-200': row.original.study_status === 'tot_nghiep',
                    'bg-red-50 text-red-700 border border-red-200': ['thoi_hoc','dinh_chi'].includes(row.original.study_status || ''),
                    'bg-slate-100 text-slate-500 border border-slate-200': row.original.study_status === 'nghi_huu',
                  }">{{ STUDY_STATUS_LABELS[row.original.study_status || ''] || '—' }}</span>
                </div>
              </template>

              <!-- Action Buttons -->
              <template #actions-cell="{ row }">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    class="w-7 h-7 rounded-lg border border-[var(--line)] text-[var(--muted)] hover:text-[var(--text)] hover:bg-[#f1f5f9] flex items-center justify-center transition-colors cursor-pointer"
                    title="Xem chi tiết"
                    type="button"
                    @click="openViewModal(row.original)"
                  >
                    <i class="pi pi-eye text-xs" />
                  </button>
                  <button
                    class="w-7 h-7 rounded-lg border border-[rgba(29,158,117,0.2)] bg-[rgba(29,158,117,0.05)] text-[#085041] hover:bg-[rgba(29,158,117,0.12)] flex items-center justify-center transition-colors cursor-pointer"
                    title="Chỉnh sửa"
                    type="button"
                    @click="openEditModal(row.original)"
                  >
                    <i class="pi pi-pencil text-xs" />
                  </button>
                  <button
                    class="w-7 h-7 rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center transition-colors cursor-pointer"
                    title="Xóa"
                    type="button"
                    @click="confirmDelete(row.original)"
                  >
                    <i class="pi pi-trash text-xs" />
                  </button>
                </div>
              </template>
            </UiTable>

            <!-- Table Pagination -->
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
      </div>
    </div>

    <!-- User Create / Edit Dialog Modal -->
    <UModal
      v-model:open="modalOpen"
      :title="modalMode === 'create' ? 'Tạo tài khoản học viên' : modalMode === 'edit' ? 'Chỉnh sửa tài khoản học viên' : 'Hồ sơ chi tiết học viên'"
      :ui="{ width: 'max-w-xl' }"
    >
      <div class="flex flex-col gap-4 py-3">
        <!-- Dialog Details fields -->
        <div v-if="modalMode === 'view' && selectedUser" class="flex flex-col gap-4">
          <div class="flex items-center gap-4 bg-[#f8fafc] p-4 rounded-xl border border-[var(--line)]">
            <div class="w-12 h-12 rounded-full bg-[rgba(29,158,117,0.1)] text-[#085041] flex items-center justify-center font-bold text-lg">
              {{ avatarInitials(selectedUser.name) }}
            </div>
            <div class="flex flex-col">
              <strong class="text-sm font-bold text-[var(--text)]">{{ selectedUser.name }}</strong>
              <span class="text-xs text-[var(--muted)] mt-0.5">{{ selectedUser.email }}</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4 text-xs">
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-bold uppercase text-[var(--muted)]">Vai trò</span>
              <span class="font-semibold">{{ resolveRole(selectedUser) }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-bold uppercase text-[var(--muted)]">Mã số</span>
              <span class="font-semibold">{{ selectedUser.student_code || selectedUser.staff_code || '—' }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-bold uppercase text-[var(--muted)]">Số điện thoại</span>
              <span class="font-semibold">{{ selectedUser.phone || '—' }}</span>
            </div>
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-bold uppercase text-[var(--muted)]">Trạng thái</span>
              <span class="font-semibold">{{ STUDY_STATUS_LABELS[selectedUser.study_status || ''] || '—' }}</span>
            </div>
          </div>
        </div>

        <form v-else class="flex flex-col gap-4" @submit.prevent="saveUser">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Họ và tên</span>
            <input v-model="form.name" type="text" required class="h-10 px-3.5 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]" />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Email đăng nhập</span>
            <input v-model="form.email" type="email" required class="h-10 px-3.5 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]" />
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Mật khẩu {{ modalMode === 'edit' ? '(để trống nếu không đổi)' : '' }}</span>
            <input v-model="form.password" type="password" :required="modalMode === 'create'" class="h-10 px-3.5 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]" />
          </label>

          <div class="grid grid-cols-2 gap-4">
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-bold text-[var(--text)]">Vai trò</span>
              <UiSelect v-model="form.role" :options="roleOptions" />
            </label>
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-bold text-[var(--text)]">Trạng thái</span>
              <UiSelect v-model="form.study_status" :options="statusOptions" />
            </label>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-bold text-[var(--text)]">Mã (MSSV/Mã NV)</span>
              <input v-if="form.role === 'student'" v-model="form.student_code" type="text" class="h-10 px-3.5 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]" />
              <input v-else v-model="form.staff_code" type="text" class="h-10 px-3.5 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]" />
            </label>
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-bold text-[var(--text)]">Số điện thoại</span>
              <input v-model="form.phone" type="text" class="h-10 px-3.5 rounded-xl border border-[var(--line)] bg-[#f8fafc] text-xs focus:outline-none focus:border-[#1d9e75]" />
            </label>
          </div>

          <div class="flex items-center justify-end gap-3 mt-4">
            <button type="button" class="h-10 px-4 rounded-xl border border-[var(--line)] text-xs font-bold text-[var(--muted)] hover:bg-[#f1f5f9] cursor-pointer" @click="modalOpen = false">Hủy</button>
            <button type="submit" :disabled="saving" class="h-10 px-5 rounded-xl bg-[#1d9e75] hover:bg-[#158260] text-white text-xs font-bold shadow-md cursor-pointer flex items-center gap-2">
              <i v-if="saving" class="pi pi-spin pi-spinner text-xs" />
              <span>{{ modalMode === 'create' ? 'Tạo tài khoản' : 'Cập nhật' }}</span>
            </button>
          </div>
        </form>
      </div>
    </UModal>

    <!-- Delete Confirmation Modal -->
    <CrudConfirmModal
      v-model:open="deleteModalOpen"
      title="Xóa tài khoản người dùng"
      message="Hành động này không thể hoàn tác. Bạn có chắc chắn muốn xóa tài khoản này?"
      :loading="saving"
      @confirm="deleteUser"
    />
  </AdminWorkspaceShell>
</template>
