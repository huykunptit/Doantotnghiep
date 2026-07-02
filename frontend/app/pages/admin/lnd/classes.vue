<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import { useAuthTokenCookie } from '~/composables/useAuthSession'
import { 
  Building, 
  Plus, 
  Trash2, 
  Edit, 
  Users, 
  Layers, 
  User, 
  Calendar,
  X,
  RefreshCw,
  Eye,
  Check,
  GraduationCap,
  Info,
  CheckCircle2
} from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor'],
  adminSearchPlaceholder: 'Tìm lớp hành chính...',
})

type Id = number

interface ProgramItem {
  id: Id
  name: string
  code: string
  institution_id?: number
  unit_id?: number
}

interface MajorItem {
  id: Id
  name: string
  code: string
  program_id: Id
}

interface CohortItem {
  id: Id
  name: string
  code: string
  program_id: Id
}

interface CurriculumItem {
  id: Id
  name: string
  code: string
  program_id: Id
}

interface AdvisorItem {
  id: Id
  name: string
  email: string
  staff_code?: string
}

interface AdministrativeClassItem {
  id: Id
  code: string
  name: string
  program_id: Id
  major_id?: Id | null
  cohort_id: Id
  curriculum_id?: Id | null
  advisor_id?: Id | null
  expected_graduation_year?: number | null
  capacity?: number | null
  status: string
  program?: { name: string }
  major?: { name: string }
  cohort?: { name: string }
  advisor?: { name: string }
  curriculum?: { name: string }
  students_count?: number
}

const token = useAuthTokenCookie()
const toast = useToast()

function headers(): Record<string, string> {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

const loading = ref(false)
const saving = ref(false)

const programs = ref<ProgramItem[]>([])
const majors = ref<MajorItem[]>([])
const cohorts = ref<CohortItem[]>([])
const curricula = ref<CurriculumItem[]>([])
const advisors = ref<AdvisorItem[]>([])

const adminClasses = ref<AdministrativeClassItem[]>([])
const selectedProgramId = ref<Id | ''>('')
const selectedCohortId = ref<Id | ''>('')
const searchQuery = ref('')
const statusFilter = ref('')

// Pagination
const currentPage = ref(1)
const lastPage = ref(1)
const totalClasses = ref(0)
const perPage = ref(15)

// Form states
const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit' | 'view'>('create')
const selectedClass = ref<AdministrativeClassItem | null>(null)
const showDeleteConfirm = ref(false)
const deleteTargetId = ref<Id | null>(null)

const classForm = ref({
  code: '',
  name: '',
  program_id: '' as Id | '',
  major_id: '' as Id | '',
  cohort_id: '' as Id | '',
  curriculum_id: '' as Id | '',
  advisor_id: '' as Id | '',
  capacity: '' as number | '',
  expected_graduation_year: '' as number | '',
  status: 'active'
})

const columns = [
  { id: 'code', accessorKey: 'code', header: 'Mã lớp' },
  { id: 'name', accessorKey: 'name', header: 'Tên lớp' },
  { id: 'cohort', accessorKey: 'cohort', header: 'Khóa học' },
  { id: 'curriculum', accessorKey: 'curriculum', header: 'Lộ trình đào tạo (CTĐT)' },
  { id: 'advisor', accessorKey: 'advisor', header: 'Cố vấn học tập' },
  { id: 'students_count', accessorKey: 'students_count', header: 'Sĩ số' },
  { id: 'status', accessorKey: 'status', header: 'Trạng thái' },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

onMounted(async () => {
  await bootstrapFilters()
  await loadAdvisors()
})

watch([selectedProgramId, selectedCohortId, statusFilter], async () => {
  await loadAdminClasses(1)
})

let searchTimeout: any = null
watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    await loadAdminClasses(1)
  }, 400)
})

// Watch program changes in form to filter majors and curricula
watch(() => classForm.value.program_id, async (newProgramId) => {
  if (newProgramId) {
    await loadCurriculaForProgram(Number(newProgramId))
  } else {
    curricula.value = []
    classForm.value.curriculum_id = ''
  }
})

// Options computed properties for USelect
const filterProgramOptions = computed(() => {
  return programs.value.map(p => ({ label: p.name, value: String(p.id) }))
})

const filterCohortOptions = computed(() => {
  return [
    { label: '-- Tất cả khóa học --', value: '' },
    ...cohorts.value.map(c => ({ label: c.name, value: String(c.id) }))
  ]
})

const formProgramOptions = computed(() => {
  return [
    { label: '-- Chọn chương trình đào tạo --', value: '' },
    ...programs.value.map(p => ({ label: p.name, value: String(p.id) }))
  ]
})

const formCohortOptions = computed(() => {
  return [
    { label: '-- Chọn khóa học --', value: '' },
    ...cohorts.value.map(c => ({ label: c.name, value: String(c.id) }))
  ]
})

const formMajorOptions = computed(() => {
  return [
    { label: '-- Ngành học chung --', value: '' },
    ...filteredFormMajors.value.map(m => ({ label: m.name, value: String(m.id) }))
  ]
})

const formCurriculumOptions = computed(() => {
  return [
    { label: '-- Chọn lộ trình học --', value: '' },
    ...curricula.value.map(curr => ({ label: curr.name, value: String(curr.id) }))
  ]
})

const formAdvisorOptions = computed(() => {
  return [
    { label: '-- Không chỉ định cố vấn --', value: '' },
    ...advisors.value.map(adv => ({ label: `${adv.name} (${adv.staff_code || 'Giảng viên'})`, value: String(adv.id) }))
  ]
})

const formStatusOptions = [
  { label: 'Hoạt động (Active)', value: 'active' },
  { label: 'Tạm ngưng (Inactive)', value: 'inactive' }
]

const bulkCurriculumOptions = computed(() => {
  return [
    { label: '-- Chọn lộ trình đào tạo --', value: '' },
    ...bulkAssignCurricula.value.map(curr => ({ label: `${curr.name} (${curr.code})`, value: String(curr.id) }))
  ]
})

async function bootstrapFilters() {
  loading.value = true
  try {
    const [pRes, mRes, cRes] = await Promise.all([
      useApi<{ data: ProgramItem[] }>('/admin/academic/programs?per_page=100', { headers: headers() }),
      useApi<{ data: MajorItem[] }>('/admin/academic/majors?per_page=200', { headers: headers() }),
      useApi<{ data: CohortItem[] }>('/admin/academic/cohorts?per_page=100', { headers: headers() }),
    ])
    programs.value = pRes.data
    majors.value = mRes.data
    cohorts.value = cRes.data
    
    if (programs.value.length > 0) {
      selectedProgramId.value = programs.value[0]?.id || ''
    }
    if (cohorts.value.length > 0) {
      selectedCohortId.value = cohorts.value[0]?.id || ''
    }
    
    await loadAdminClasses()
  } catch (e) {
    toast.error('Không thể tải các bộ lọc học vụ.')
  } finally {
    loading.value = false
  }
}

async function loadAdvisors() {
  try {
    const res = await useApi<AdvisorItem[]>('/admin/instructors?per_page=200', { headers: headers() })
    advisors.value = res
  } catch (e) {
    console.error('Failed to load advisors')
  }
}

async function loadCurriculaForProgram(progId: number) {
  try {
    const res = await useApi<{ data: CurriculumItem[] }>(
      `/admin/academic/curricula?program_id=${progId}&per_page=100`,
      { headers: headers() }
    )
    curricula.value = res.data
  } catch (e) {
    console.error('Failed to load curricula')
  }
}

async function loadAdminClasses(page = 1) {
  if (!selectedProgramId.value) return
  loading.value = true
  currentPage.value = page
  try {
    let url = `/admin/academic/administrative-classes?program_id=${selectedProgramId.value}&page=${page}&per_page=${perPage.value}`
    if (selectedCohortId.value) {
      url += `&cohort_id=${selectedCohortId.value}`
    }
    if (searchQuery.value.trim()) {
      url += `&q=${encodeURIComponent(searchQuery.value.trim())}`
    }
    if (statusFilter.value) {
      url += `&status=${statusFilter.value}`
    }
    const res = await useApi<any>(url, { headers: headers() })
    adminClasses.value = res.data
    currentPage.value = res.current_page || 1
    lastPage.value = res.last_page || 1
    totalClasses.value = res.total || 0
  } catch (e) {
    toast.error('Không thể tải danh sách lớp hành chính.')
  } finally {
    loading.value = false
  }
}

const filteredFormMajors = computed(() => {
  if (!classForm.value.program_id) return []
  return majors.value.filter(m => m.program_id === classForm.value.program_id)
})

function openCreateModal() {
  modalMode.value = 'create'
  selectedClass.value = null
  classForm.value = {
    code: '',
    name: '',
    program_id: selectedProgramId.value || '',
    major_id: '',
    cohort_id: selectedCohortId.value || '',
    curriculum_id: '',
    advisor_id: '',
    capacity: 60,
    expected_graduation_year: new Date().getFullYear() + 4,
    status: 'active'
  }
  modalOpen.value = true
}

async function openEditModal(cls: AdministrativeClassItem) {
  modalMode.value = 'edit'
  selectedClass.value = cls
  
  if (cls.program_id) {
    await loadCurriculaForProgram(cls.program_id)
  }
  
  classForm.value = {
    code: cls.code,
    name: cls.name,
    program_id: cls.program_id,
    major_id: cls.major_id || '',
    cohort_id: cls.cohort_id,
    curriculum_id: cls.curriculum_id || '',
    advisor_id: cls.advisor_id || '',
    capacity: cls.capacity || '',
    expected_graduation_year: cls.expected_graduation_year || '',
    status: cls.status
  }
  modalOpen.value = true
}

async function openViewModal(cls: AdministrativeClassItem) {
  modalMode.value = 'view'
  selectedClass.value = cls
  
  if (cls.program_id) {
    await loadCurriculaForProgram(cls.program_id)
  }
  
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedClass.value = null
}

async function saveClass() {
  if (!classForm.value.code.trim() || !classForm.value.name.trim() || !classForm.value.program_id || !classForm.value.cohort_id) {
    toast.error('Vui lòng điền đầy đủ các thông tin bắt buộc.')
    return
  }

  saving.value = true
  try {
    const program = programs.value.find(p => p.id === classForm.value.program_id)
    const institutionId = program?.institution_id
    const unitId = program?.unit_id

    if (!institutionId || !unitId) {
      toast.error('Chương trình đào tạo chưa được gắn đơn vị. Vui lòng cập nhật chương trình trước.')
      saving.value = false
      return
    }

    const payload = {
      institution_id: institutionId,
      unit_id: unitId,
      program_id: Number(classForm.value.program_id),
      major_id: classForm.value.major_id ? Number(classForm.value.major_id) : null,
      cohort_id: Number(classForm.value.cohort_id),
      curriculum_id: classForm.value.curriculum_id ? Number(classForm.value.curriculum_id) : null,
      advisor_id: classForm.value.advisor_id ? Number(classForm.value.advisor_id) : null,
      code: classForm.value.code.trim(),
      name: classForm.value.name.trim(),
      capacity: classForm.value.capacity ? Number(classForm.value.capacity) : null,
      expected_graduation_year: classForm.value.expected_graduation_year ? Number(classForm.value.expected_graduation_year) : null,
      status: classForm.value.status
    }

    if (selectedClass.value) {
      await useApi(`/admin/academic/administrative-classes/${selectedClass.value.id}`, {
        method: 'PUT',
        headers: headers(),
        body: payload
      })
      toast.success('Đã cập nhật lớp hành chính thành công.')
    } else {
      await useApi('/admin/academic/administrative-classes', {
        method: 'POST',
        headers: headers(),
        body: payload
      })
      toast.success('Đã tạo lớp hành chính mới thành công.')
    }

    modalOpen.value = false
    await loadAdminClasses()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra trong quá trình lưu thông tin lớp.')
  } finally {
    saving.value = false
  }
}

// ── Bulk Assign Curriculum ─────────────────────────────────
const showBulkAssignModal = ref(false)
const bulkAssignCurriculumId = ref<Id | ''>('')
const bulkAssignClassIds = ref<Id[]>([])
const bulkAssigning = ref(false)
const bulkAssignCurricula = ref<CurriculumItem[]>([])

async function openBulkAssignModal() {
  bulkAssignCurriculumId.value = ''
  bulkAssignClassIds.value = []
  showBulkAssignModal.value = true
  if (bulkAssignCurricula.value.length === 0) {
    try {
      const url = selectedProgramId.value
        ? `/admin/academic/curricula?program_id=${selectedProgramId.value}&per_page=100`
        : '/admin/academic/curricula?per_page=100'
      const res = await useApi<{ data: CurriculumItem[] }>(url, { headers: headers() })
      bulkAssignCurricula.value = res.data
    } catch (e) {
      toast.error('Không thể tải danh sách CTĐT.')
    }
  }
}

function toggleBulkClass(classId: Id) {
  const idx = bulkAssignClassIds.value.indexOf(classId)
  if (idx > -1) bulkAssignClassIds.value.splice(idx, 1)
  else bulkAssignClassIds.value.push(classId)
}

function toggleSelectAllBulk() {
  if (bulkAssignClassIds.value.length === adminClasses.value.length) {
    bulkAssignClassIds.value = []
  } else {
    bulkAssignClassIds.value = adminClasses.value.map(c => c.id)
  }
}

async function executeBulkAssign() {
  if (!bulkAssignCurriculumId.value || bulkAssignClassIds.value.length === 0) {
    toast.error('Vui lòng chọn CTĐT và ít nhất 1 lớp hành chính.')
    return
  }
  bulkAssigning.value = true
  let successCount = 0
  try {
    await Promise.all(
      bulkAssignClassIds.value.map(async (classId) => {
        const cls = adminClasses.value.find(c => c.id === classId)
        if (!cls) return
        await useApi(`/admin/academic/administrative-classes/${classId}`, {
          method: 'PUT',
          headers: headers(),
          body: {
            institution_id: 1,
            unit_id: 1,
            program_id: cls.program_id,
            major_id: cls.major_id || null,
            cohort_id: cls.cohort_id,
            curriculum_id: Number(bulkAssignCurriculumId.value),
            advisor_id: cls.advisor_id || null,
            code: cls.code,
            name: cls.name,
            capacity: cls.capacity || null,
            expected_graduation_year: cls.expected_graduation_year || null,
            status: cls.status,
          }
        })
        successCount++
      })
    )
    toast.success(`Đã gán CTĐT cho ${successCount} lớp hành chính thành công.`)
    showBulkAssignModal.value = false
    await loadAdminClasses()
  } catch (e: any) {
    toast.error(e?.data?.message || `Đã gán ${successCount}/${bulkAssignClassIds.value.length} lớp, có lỗi xảy ra.`)
  } finally {
    bulkAssigning.value = false
  }
}

function confirmDelete(cls: AdministrativeClassItem) {
  deleteTargetId.value = cls.id
  showDeleteConfirm.value = true
}

async function executeDelete() {
  if (!deleteTargetId.value) return
  loading.value = true
  try {
    await useApi(`/admin/academic/administrative-classes/${deleteTargetId.value}`, {
      method: 'DELETE',
      headers: headers()
    })
    toast.success('Đã xóa lớp hành chính.')
    showDeleteConfirm.value = false
    await loadAdminClasses()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa lớp.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AdminWorkspaceShell
    title="Quản Lý Lớp Hành Chính"
    description="Quản lý cơ cấu các lớp niên chế và gán chương trình lộ trình học chuẩn"
    :breadcrumb="['Trang chủ', 'Đào tạo', 'Lớp Hành Chính']"
  >
    <template #actions>
      <div class="flex items-center gap-3">
        <button class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-xl transition-all duration-300 shadow-sm border border-emerald-200/50" type="button" @click="openBulkAssignModal">
          <Layers :size="16" />
          Gán lộ trình hàng loạt
        </button>
        <button class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5" type="button" @click="openCreateModal">
          <Plus :size="16" />
          Tạo lớp học mới
        </button>
      </div>
    </template>

    <!-- KPI Stats Strip -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="group relative bg-white border border-emerald-200 p-5 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all duration-500"></div>
        <div class="flex items-start justify-between relative z-10">
          <div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-1">Tổng số lớp</p>
            <h4 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ adminClasses.length }}</h4>
          </div>
          <div class="p-3 bg-emerald-100 text-emerald-600 rounded-xl dark:bg-emerald-500/20 dark:text-emerald-400">
            <GraduationCap :size="20" />
          </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-500 mt-3 font-medium">Lớp niên chế hiện có</p>
      </div>

      <div class="group relative bg-white border border-sky-200 p-5 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-sky-500/10 rounded-full blur-2xl group-hover:bg-sky-500/20 transition-all duration-500"></div>
        <div class="flex items-start justify-between relative z-10">
          <div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-1">Đã gán lộ trình</p>
            <h4 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ adminClasses.filter(c => c.curriculum_id).length }}</h4>
          </div>
          <div class="p-3 bg-sky-100 text-sky-600 rounded-xl dark:bg-sky-500/20 dark:text-sky-400">
            <Layers :size="20" />
          </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-500 mt-3 font-medium">Lớp đã có khung CTĐT</p>
      </div>

      <div class="group relative bg-white border border-amber-200 p-5 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all duration-500"></div>
        <div class="flex items-start justify-between relative z-10">
          <div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-1">Tổng sĩ số</p>
            <h4 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ adminClasses.reduce((sum, c) => sum + (c.students_count || 0), 0) }}</h4>
          </div>
          <div class="p-3 bg-amber-100 text-amber-600 rounded-xl dark:bg-amber-500/20 dark:text-amber-400">
            <Users :size="20" />
          </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-500 mt-3 font-medium">Học viên đang theo học</p>
      </div>

      <div class="group relative bg-white border border-violet-200 p-5 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-violet-500/10 rounded-full blur-2xl group-hover:bg-violet-500/20 transition-all duration-500"></div>
        <div class="flex items-start justify-between relative z-10">
          <div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-1">Cố vấn học tập</p>
            <h4 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ new Set(adminClasses.map(c => c.advisor_id).filter(Boolean)).size }}</h4>
          </div>
          <div class="p-3 bg-violet-100 text-violet-600 rounded-xl dark:bg-violet-500/20 dark:text-violet-400">
            <User :size="20" />
          </div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-500 mt-3 font-medium">Giảng viên phụ trách</p>
      </div>
    </div>

    <!-- Filters Ribbon -->
    <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm mb-6 flex flex-wrap items-end gap-4 dark:bg-slate-900 dark:border-slate-800">
      <div class="flex-1 min-w-[200px] flex flex-col gap-1.5">
        <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Tìm kiếm</label>
        <div class="relative">
          <input v-model="searchQuery" placeholder="Tìm mã hoặc tên lớp..." class="w-full h-10 pl-4 pr-10 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none text-sm font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white" />
        </div>
      </div>

      <div class="flex-1 min-w-[200px] flex flex-col gap-1.5">
        <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Chương trình đào tạo</label>
        <select v-model="selectedProgramId" :disabled="loading" class="w-full h-10 px-3 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none text-sm font-medium disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} — {{ p.name }}</option>
        </select>
      </div>

      <div class="flex-1 min-w-[200px] flex flex-col gap-1.5">
        <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Khóa đào tạo</label>
        <select v-model="selectedCohortId" :disabled="loading" class="w-full h-10 px-3 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none text-sm font-medium disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
          <option value="">— Tất cả khóa học —</option>
          <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
        </select>
      </div>

      <div class="flex-1 min-w-[200px] flex flex-col gap-1.5">
        <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Trạng thái</label>
        <select v-model="statusFilter" class="w-full h-10 px-3 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none text-sm font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white">
          <option value="">— Tất cả trạng thái —</option>
          <option value="active">Hoạt động</option>
          <option value="inactive">Tạm dừng</option>
        </select>
      </div>

      <button class="h-10 w-10 flex items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 transition-all disabled:opacity-50 dark:bg-slate-800 dark:text-slate-400 dark:hover:text-emerald-400 dark:hover:bg-emerald-500/10" type="button" @click="loadAdminClasses(1)" :disabled="loading" title="Làm mới">
        <RefreshCw :size="16" :class="{ 'animate-spin': loading }" />
      </button>
    </div>

    <!-- Classes Workspace Panel -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden dark:bg-slate-900 dark:border-slate-800">
      <div class="px-6 py-5 border-b border-slate-200 flex items-center gap-4 bg-slate-50 dark:bg-slate-800/50 dark:border-slate-800">
        <div class="p-2.5 bg-emerald-100 text-emerald-600 rounded-xl dark:bg-emerald-500/20 dark:text-emerald-400">
          <Building :size="22" />
        </div>
        <div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white">Danh sách lớp hành chính</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">Hiển thị cơ cấu tổ chức và lộ trình đào tạo hiện tại.</p>
        </div>
      </div>

      <div class="w-full overflow-x-auto">
        <UTable :columns="columns" :data="adminClasses" :loading="loading" :ui="{ td: { padding: 'py-4 px-4' }, th: { padding: 'py-4 px-4', font: 'font-bold uppercase text-[11px] tracking-wider text-slate-600 dark:text-slate-400' } }">
          <template #code-cell="{ row }">
            <span class="inline-flex font-mono text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-md dark:text-emerald-400 dark:bg-emerald-500/20 dark:border-emerald-500/30">{{ row.original.code }}</span>
          </template>

          <template #name-cell="{ row }">
            <span class="font-bold text-slate-900 dark:text-slate-100">{{ row.original.name }}</span>
          </template>

          <template #cohort-cell="{ row }">
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ row.original.cohort?.name || '—' }}</span>
          </template>

          <template #curriculum-cell="{ row }">
            <div v-if="row.original.curriculum_id" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700 bg-sky-50 border border-sky-100 px-2.5 py-1 rounded-md max-w-[200px] truncate dark:text-sky-400 dark:bg-sky-500/20 dark:border-sky-500/30" :title="row.original.curriculum?.name">
              <Layers :size="12" class="shrink-0" />
              <span class="truncate">{{ row.original.curriculum?.name || `CTĐT #${row.original.curriculum_id}` }}</span>
            </div>
            <span v-else class="text-sm font-medium italic text-slate-500 dark:text-slate-400">Chưa gán lộ trình</span>
          </template>

          <template #advisor-cell="{ row }">
            <div v-if="row.original.advisor" class="flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-300">
              <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 dark:bg-slate-700 dark:text-slate-400">
                <User :size="12" />
              </div>
              <span class="truncate max-w-[150px]">{{ row.original.advisor.name }}</span>
            </div>
            <span v-else class="text-sm font-medium text-slate-500 dark:text-slate-400">—</span>
          </template>

          <template #students_count-cell="{ row }">
            <div class="flex items-center gap-1.5 text-sm font-bold text-slate-700 dark:text-slate-300">
              <Users :size="14" class="text-slate-500 dark:text-slate-400" />
              <span>{{ row.original.students_count || 0 }}</span>
            </div>
          </template>

          <template #status-cell="{ row }">
            <span v-if="row.original.status === 'active'" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full dark:text-emerald-400 dark:bg-emerald-500/20">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span> Hoạt động
            </span>
            <span v-else class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full dark:text-amber-400 dark:bg-amber-500/20">
              <span class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span> Tạm dừng
            </span>
          </template>

          <template #actions-cell="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-500 hover:text-sky-600 hover:bg-sky-50 transition-colors dark:hover:text-sky-400 dark:hover:bg-sky-500/10" type="button" @click="openViewModal(row.original)" title="Xem chi tiết">
                <Eye :size="16" />
              </button>
              <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 transition-colors dark:hover:text-amber-400 dark:hover:bg-amber-500/10" type="button" @click="openEditModal(row.original)" title="Chỉnh sửa">
                <Edit :size="16" />
              </button>
              <button class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors dark:hover:text-rose-400 dark:hover:bg-rose-500/10" type="button" @click="confirmDelete(row.original)" title="Xóa">
                <Trash2 :size="16" />
              </button>
            </div>
          </template>
        </UTable>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-between items-center dark:bg-slate-800/30 dark:border-slate-800">
        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
          Hiển thị <strong class="text-slate-900 dark:text-slate-100">{{ adminClasses.length }}</strong> trên tổng <strong class="text-slate-900 dark:text-slate-100">{{ totalClasses }}</strong>
        </span>
        <UPagination
          v-model="currentPage"
          :page-count="perPage"
          :total="totalClasses"
          @update:model-value="loadAdminClasses"
          :ui="{ wrapper: 'flex items-center gap-1', rounded: 'rounded-lg' }"
        />
      </div>
    </div>

    <!-- Modals (Create/Edit/View & Bulk Assign) - Updated visually -->
    <UModal v-model:open="modalOpen" :ui="{ width: modalMode === 'view' ? 'max-w-3xl' : 'max-w-2xl', rounded: 'rounded-2xl', shadow: 'shadow-2xl' }">
      <template #content>
        <div class="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden flex flex-col max-h-[90vh]">
          <!-- Header -->
          <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center bg-gradient-to-b from-slate-50 to-white dark:from-slate-800 dark:to-slate-900 dark:border-slate-800" :class="{ 'from-sky-50': modalMode === 'view' }">
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-1">
                {{ modalMode === 'create' ? 'Tạo mới' : modalMode === 'edit' ? 'Chỉnh sửa' : 'Chi tiết học vụ' }}
              </p>
              <h3 class="text-xl font-bold text-slate-900 dark:text-white">
                {{ modalMode === 'create' ? 'Tạo lớp hành chính mới' : modalMode === 'edit' ? 'Cập nhật thông tin lớp' : selectedClass?.name }}
              </h3>
            </div>
            <button class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 transition-colors dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-400" @click="closeModal">
              <X :size="16" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto custom-scrollbar">
            <!-- View Mode -->
            <div v-if="modalMode === 'view'" class="space-y-6">
              <div class="flex items-center gap-5 pb-6 border-b border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shadow-inner dark:bg-sky-500/20 dark:text-sky-400">
                  <Building :size="32" />
                </div>
                <div>
                  <h4 class="text-2xl font-black text-slate-900 dark:text-white">{{ selectedClass?.name }}</h4>
                  <div class="flex items-center gap-3 mt-2">
                    <span class="font-mono text-sm font-bold text-slate-700 bg-slate-200 px-2.5 py-0.5 rounded dark:text-slate-200 dark:bg-slate-700">{{ selectedClass?.code }}</span>
                    <span v-if="selectedClass?.status === 'active'" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full dark:text-emerald-400 dark:bg-emerald-500/20">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span> Hoạt động
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full dark:text-amber-400 dark:bg-amber-500/20">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span> Tạm ngưng
                    </span>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                  <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">Chương trình đào tạo</label>
                  <p class="font-semibold text-slate-900 dark:text-slate-200">{{ selectedClass?.program?.name || '—' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                  <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">Khóa đào tạo</label>
                  <p class="font-semibold text-slate-900 dark:text-slate-200">{{ selectedClass?.cohort?.name || '—' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                  <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">Chuyên ngành / Ngành</label>
                  <p class="font-semibold text-slate-900 dark:text-slate-200">{{ selectedClass?.major?.name || 'Ngành học chung' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                  <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">Lộ trình đào tạo (CTĐT)</label>
                  <p class="font-semibold text-sky-700 dark:text-sky-400">{{ curricula.find(c => c.id === selectedClass?.curriculum_id)?.name || 'Chưa gán lộ trình' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                  <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">Cố vấn học tập</label>
                  <p class="font-semibold text-slate-900 dark:text-slate-200">{{ selectedClass?.advisor?.name || 'Chưa chỉ định' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-100 border border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                  <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400 mb-1">Sĩ số / Dự kiến</label>
                  <p class="font-semibold text-slate-900 dark:text-slate-200">
                    <strong class="text-emerald-600 dark:text-emerald-400">{{ selectedClass?.students_count || 0 }}</strong> / {{ selectedClass?.capacity || '—' }} học viên
                  </p>
                </div>
              </div>
            </div>

            <!-- Create / Edit Form -->
            <div v-else class="space-y-5">
              <div v-if="modalMode === 'edit'" class="px-4 py-3 bg-amber-50 border border-amber-200 text-amber-900 text-sm font-medium rounded-xl flex items-center gap-3 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-200">
                <Info :size="18" class="shrink-0 text-amber-600 dark:text-amber-400" />
                <span>Đang chỉnh sửa thông tin lớp. Không thể thay đổi Mã lớp định danh.</span>
              </div>
              
              <div class="grid grid-cols-2 gap-5">
                <div class="col-span-1 space-y-1.5">
                  <label class="text-xs font-bold text-slate-600 uppercase">Mã lớp <span class="text-rose-500">*</span></label>
                  <input v-model="classForm.code" :disabled="modalMode === 'edit'" placeholder="VD: D20CNPM1" class="w-full h-11 px-3.5 rounded-xl border border-slate-300 bg-white focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-medium disabled:opacity-60 disabled:cursor-not-allowed dark:border-slate-700 dark:bg-slate-800 dark:text-white" />
                </div>
                <div class="col-span-1 space-y-1.5">
                  <label class="text-xs font-bold text-slate-600 uppercase">Tên lớp <span class="text-rose-500">*</span></label>
                  <input v-model="classForm.name" placeholder="VD: Công nghệ phần mềm 1" class="w-full h-11 px-3.5 rounded-xl border border-slate-300 bg-white focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white" />
                </div>
                
                <div class="col-span-1 space-y-1.5">
                  <label class="text-xs font-bold text-slate-600 uppercase">Chương trình đào tạo <span class="text-rose-500">*</span></label>
                  <select v-model="classForm.program_id" class="w-full h-11 px-3.5 rounded-xl border border-slate-300 bg-white focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    <option value="">— Chọn chương trình đào tạo —</option>
                    <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div class="col-span-1 space-y-1.5">
                  <label class="text-xs font-bold text-slate-600 uppercase">Khóa đào tạo (Cohort) <span class="text-rose-500">*</span></label>
                  <select v-model="classForm.cohort_id" class="w-full h-11 px-3.5 rounded-xl border border-slate-300 bg-white focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    <option value="">— Chọn khóa học —</option>
                    <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

replace_all_form_selects
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div v-if="modalMode !== 'view'" class="px-6 py-4 bg-slate-100 border-t border-slate-300 flex justify-end gap-3 dark:bg-slate-800 dark:border-slate-700">
            <button class="px-5 py-2.5 rounded-xl font-bold text-slate-700 hover:bg-slate-200 transition-colors dark:text-slate-300 dark:hover:bg-slate-700" type="button" @click="closeModal">Hủy</button>
            <button class="px-5 py-2.5 rounded-xl font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 shadow-md transform hover:-translate-y-0.5 transition-all disabled:opacity-70 disabled:transform-none flex items-center gap-2" type="button" :disabled="saving" @click="saveClass">
              <RefreshCw v-if="saving" :size="16" class="animate-spin" />
              {{ saving ? 'Đang lưu...' : 'Lưu thông tin' }}
            </button>
          </div>
        </div>
      </template>
    </UModal>

    <!-- Bulk Assign Modal -->
    <UModal v-model:open="showBulkAssignModal" :ui="{ width: 'max-w-3xl', rounded: 'rounded-2xl', shadow: 'shadow-2xl' }">
      <template #content>
        <div class="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden flex flex-col max-h-[90vh]">
          <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center bg-gradient-to-b from-slate-50 to-white dark:from-slate-800 dark:to-slate-900 dark:border-slate-800">
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-sky-600 mb-1">Thao tác hàng loạt</p>
              <h3 class="text-xl font-bold text-slate-900 dark:text-white">Gán Chương Trình Đào Tạo</h3>
            </div>
            <button class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 transition-colors dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-400" @click="showBulkAssignModal = false">
              <X :size="16" />
            </button>
          </div>

          <div class="p-6 overflow-y-auto">
            <div class="space-y-6">
              <div class="space-y-2">
                <label class="text-xs font-bold text-slate-600 uppercase">Chọn Chương trình đào tạo (CTĐT) muốn gán <span class="text-rose-500">*</span></label>
                <select v-model="bulkAssignCurriculumId" class="w-full h-12 px-4 rounded-xl border border-sky-300 bg-white focus:bg-white focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-semibold text-slate-900 shadow-sm dark:border-sky-600 dark:bg-slate-800 dark:text-white">
                  <option value="">— Chọn lộ trình đào tạo —</option>
                  <option v-for="curr in bulkAssignCurricula" :key="curr.id" :value="curr.id">{{ curr.name }} ({{ curr.code }})</option>
                </select>
              </div>

              <div>
                <div class="flex justify-between items-center mb-3">
                  <span class="text-sm font-bold text-slate-900 dark:text-white">
                    Chọn lớp hành chính cần gán
                    <span class="text-slate-500 dark:text-slate-400 font-medium ml-1">({{ adminClasses.length }} lớp)</span>
                  </span>
                  <button class="text-xs font-bold text-sky-700 hover:text-sky-800 bg-sky-100 hover:bg-sky-200 px-3 py-1.5 rounded-lg transition-colors dark:text-sky-400 dark:bg-sky-500/20 dark:hover:bg-sky-500/30" type="button" @click="toggleSelectAllBulk">
                    {{ bulkAssignClassIds.length === adminClasses.length && adminClasses.length > 0 ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
                  </button>
                </div>

                <div class="max-h-[300px] overflow-y-auto custom-scrollbar border border-slate-300 rounded-xl p-2 bg-white dark:border-slate-700 dark:bg-slate-800 space-y-1.5">
                  <div
                    v-for="cls in adminClasses"
                    :key="cls.id"
                    class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer bg-white hover:border-sky-300 hover:shadow-sm"
                    :class="bulkAssignClassIds.includes(cls.id) ? 'border-sky-400 ring-1 ring-sky-400/50 bg-sky-50/30' : 'border-slate-100'"
                    @click="toggleBulkClass(cls.id)"
                  >
                    <div class="w-5 h-5 rounded border flex items-center justify-center transition-colors" :class="bulkAssignClassIds.includes(cls.id) ? 'bg-sky-500 border-sky-500 text-white' : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-700'">
                      <Check v-if="bulkAssignClassIds.includes(cls.id)" :size="14" />
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="font-mono text-[10px] font-bold text-sky-700 bg-sky-100 px-1.5 py-0.5 rounded dark:text-sky-300 dark:bg-sky-500/30">{{ cls.code }}</span>
                        <strong class="text-sm text-slate-900 truncate dark:text-white">{{ cls.name }}</strong>
                      </div>
                    </div>
                    <div class="shrink-0">
                      <span v-if="cls.curriculum_id" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-md border border-emerald-100 max-w-[120px] dark:text-emerald-400 dark:bg-emerald-500/20 dark:border-emerald-500/30">
                        <Layers :size="10" /> <span class="truncate">{{ cls.curriculum?.name || 'Đã gán' }}</span>
                      </span>
                      <span v-else class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-1 rounded-md border border-amber-100 dark:text-amber-400 dark:bg-amber-500/20 dark:border-amber-500/30">Chưa gán</span>
                    </div>
                  </div>
                </div>

                <div v-if="bulkAssignClassIds.length > 0" class="mt-4 p-3 bg-sky-50 border border-sky-100 rounded-xl flex items-center gap-3 text-sky-900 text-sm dark:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-200">
                  <CheckCircle2 :size="18" class="text-sky-600 dark:text-sky-400" />
                  <span>Đã chọn <strong>{{ bulkAssignClassIds.length }}</strong> lớp hành chính để gán lộ trình.</span>
                </div>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 bg-slate-100 border-t border-slate-300 flex justify-end gap-3 dark:bg-slate-800 dark:border-slate-700">
            <button class="px-5 py-2.5 rounded-xl font-bold text-slate-700 hover:bg-slate-200 transition-colors dark:text-slate-300 dark:hover:bg-slate-700" type="button" @click="showBulkAssignModal = false">Đóng</button>
            <button class="px-5 py-2.5 rounded-xl font-bold text-white bg-sky-600 hover:bg-sky-500 shadow-md transform hover:-translate-y-0.5 transition-all disabled:opacity-70 flex items-center gap-2" type="button" :disabled="bulkAssigning" @click="executeBulkAssign">
              <RefreshCw v-if="bulkAssigning" :size="16" class="animate-spin" />
              {{ bulkAssigning ? 'Đang cập nhật...' : 'Thực hiện gán' }}
            </button>
          </div>
        </div>
      </template>
    </UModal>

    <CrudConfirmModal
      :open="showDeleteConfirm"
      title="Xóa lớp hành chính"
      message="Hành động này sẽ xóa vĩnh viễn lớp hành chính này khỏi hệ thống. Các sinh viên thuộc lớp vẫn sẽ được bảo lưu trong hệ thống."
      tone="danger"
      :loading="loading"
      @close="showDeleteConfirm = false"
      @confirm="executeDelete"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #475569;
}
</style>
