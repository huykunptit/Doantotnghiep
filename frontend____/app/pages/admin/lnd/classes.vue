<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useToast } from '~/composables/useToast'
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const toast = useToast()
const headers = () => token.value ? { Authorization: `Bearer ${token.value}` } : {}

// ─── Types ───────────────────────────────────────────────────────────────────
interface AdminClass {
  id: number
  code: string
  name: string
  status: string
  capacity: number
  expected_graduation_year?: number | null
  description?: string | null
  program?: { id: number; code: string; name: string } | null
  unit?: { id: number; code: string; name: string } | null
  cohort?: { id: number; code: string; name: string; start_year: number } | null
  advisor?: { id: number; name: string; email: string; staff_code?: string } | null
  curriculum?: { id: number; code: string; name: string } | null
  students_count?: number
}

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(false)
const saving = ref(false)
const classes = ref<AdminClass[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const searchQuery = ref('')
const filterCohortId = ref<number | ''>('')
const filterStatus = ref('')

// Related data for dropdowns
const cohorts = ref<any[]>([])
const programs = ref<any[]>([])
const units = ref<any[]>([])
const curricula = ref<any[]>([])
const advisors = ref<any[]>([])

// Modals
const createOpen = ref(false)
const editOpen = ref(false)
const deleteOpen = ref(false)
const selectedClass = ref<AdminClass | null>(null)

// ─── Enroll modal state ───────────────────────────────────────────────────────
const enrollOpen = ref(false)
const enrollTab = ref<'list' | 'import'>('list')

// Tab: chọn từ list
const enrollSearchQuery = ref('')
const enrollSearchResults = ref<any[]>([])
const enrollDefaultStudents = ref<any[]>([])
const enrollSearchLoading = ref(false)
const selectedEnrollIds = ref<number[]>([])
const enrollSaving = ref(false)

const enrollDisplayStudents = computed(() =>
  enrollSearchQuery.value.trim() ? enrollSearchResults.value : enrollDefaultStudents.value
)

// Tab: import CSV
const importFile = ref<File | null>(null)
const importFileRef = ref<HTMLInputElement | null>(null)
const importStep = ref<1 | 2>(1)
const importPreviewData = ref<any>(null)
const importLoading = ref(false)
const importExecuting = ref(false)

const formDefaults = () => ({
  institution_id: 1,
  unit_id: '' as number | '',
  program_id: '' as number | '',
  major_id: null as number | null,
  cohort_id: '' as number | '',
  advisor_id: null as number | null,
  curriculum_id: null as number | null,
  code: '',
  name: '',
  capacity: 40,
  expected_graduation_year: null as number | null,
  status: 'active',
  description: '',
})

const form = reactive(formDefaults())

const statusOptions = [
  { label: 'Tất cả', value: '' },
  { label: 'Đang hoạt động', value: 'active' },
  { label: 'Tốt nghiệp', value: 'graduated' },
  { label: 'Tạm ngưng', value: 'suspended' },
]

// ─── Computed ─────────────────────────────────────────────────────────────────
const activeCount = computed(() => classes.value.filter(c => c.status === 'active').length)
const totalStudents = computed(() => classes.value.reduce((s, c) => s + (c.students_count ?? 0), 0))

// ─── API ──────────────────────────────────────────────────────────────────────
async function fetchClasses(page = 1) {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (searchQuery.value.trim()) q.set('q', searchQuery.value.trim())
    if (filterCohortId.value) q.set('cohort_id', String(filterCohortId.value))
    if (filterStatus.value) q.set('status', filterStatus.value)

    const res = await useApi<any>(`/admin/academic/administrative-classes?${q}`, { headers: headers() })
    classes.value = res.data ?? []
    currentPage.value = res.current_page ?? 1
    lastPage.value = res.last_page ?? 1
    total.value = res.total ?? 0
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tải danh sách lớp hành chính.')
  } finally {
    loading.value = false
  }
}

async function fetchDropdowns() {
  try {
    const [cohortRes, programRes, unitRes, curriculaRes] = await Promise.all([
      useApi<any>('/admin/academic/cohorts?per_page=200', { headers: headers() }),
      useApi<any>('/admin/academic/programs?per_page=200', { headers: headers() }),
      useApi<any>('/admin/academic/units?per_page=200', { headers: headers() }),
      useApi<any>('/admin/academic/curricula?per_page=200', { headers: headers() }),
    ])
    cohorts.value = cohortRes.data ?? cohortRes ?? []
    programs.value = programRes.data ?? programRes ?? []
    units.value = unitRes.data ?? unitRes ?? []
    curricula.value = curriculaRes.data ?? curriculaRes ?? []
  } catch {
    // non-fatal
  }
}

async function fetchAdvisors(q = '') {
  try {
    const res = await useApi<any>(`/admin/users?user_type=instructor&per_page=50${q ? `&search=${encodeURIComponent(q)}` : ''}`, { headers: headers() })
    advisors.value = res.data ?? []
  } catch { /* ignore */ }
}

async function createClass() {
  if (!form.code.trim() || !form.name.trim() || !form.cohort_id || !form.program_id || !form.unit_id) {
    toast.error('Vui lòng điền đầy đủ các trường bắt buộc.')
    return
  }
  saving.value = true
  try {
    await useApi('/admin/academic/administrative-classes', {
      method: 'POST',
      headers: headers(),
      body: {
        institution_id: form.institution_id,
        unit_id: Number(form.unit_id),
        program_id: Number(form.program_id),
        cohort_id: Number(form.cohort_id),
        advisor_id: form.advisor_id ? Number(form.advisor_id) : null,
        curriculum_id: form.curriculum_id ? Number(form.curriculum_id) : null,
        major_id: form.major_id ? Number(form.major_id) : null,
        code: form.code.trim(),
        name: form.name.trim(),
        capacity: Number(form.capacity) || 40,
        expected_graduation_year: form.expected_graduation_year ? Number(form.expected_graduation_year) : null,
        status: form.status,
        description: form.description || null,
      },
    })
    toast.success('Đã tạo lớp hành chính mới.')
    createOpen.value = false
    Object.assign(form, formDefaults())
    await fetchClasses(1)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tạo lớp hành chính.')
  } finally {
    saving.value = false
  }
}

async function updateClass() {
  if (!selectedClass.value) return
  if (!form.code.trim() || !form.name.trim()) {
    toast.error('Mã lớp và tên lớp không được để trống.')
    return
  }
  saving.value = true
  try {
    await useApi(`/admin/academic/administrative-classes/${selectedClass.value.id}`, {
      method: 'PUT',
      headers: headers(),
      body: {
        institution_id: form.institution_id,
        unit_id: Number(form.unit_id),
        program_id: Number(form.program_id),
        cohort_id: Number(form.cohort_id),
        advisor_id: form.advisor_id ? Number(form.advisor_id) : null,
        curriculum_id: form.curriculum_id ? Number(form.curriculum_id) : null,
        major_id: form.major_id ? Number(form.major_id) : null,
        code: form.code.trim(),
        name: form.name.trim(),
        capacity: Number(form.capacity) || 40,
        expected_graduation_year: form.expected_graduation_year ? Number(form.expected_graduation_year) : null,
        status: form.status,
        description: form.description || null,
      },
    })
    toast.success('Đã cập nhật lớp hành chính.')
    editOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể cập nhật lớp hành chính.')
  } finally {
    saving.value = false
  }
}

async function deleteClass() {
  if (!selectedClass.value) return
  saving.value = true
  try {
    await useApi(`/admin/academic/administrative-classes/${selectedClass.value.id}`, {
      method: 'DELETE',
      headers: headers(),
    })
    toast.success(`Đã xóa lớp "${selectedClass.value.name}".`)
    deleteOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa lớp hành chính.')
  } finally {
    saving.value = false
  }
}

// ─── Enroll functions ─────────────────────────────────────────────────────────
async function openEnroll(cls: AdminClass) {
  selectedClass.value = cls
  enrollTab.value = 'list'
  selectedEnrollIds.value = []
  enrollSearchQuery.value = ''
  enrollSearchResults.value = []
  importFile.value = null
  importPreviewData.value = null
  importStep.value = 1
  enrollOpen.value = true
  await loadEnrollDefaultStudents()
}

async function loadEnrollDefaultStudents() {
  try {
    const res = await useApi<any>('/admin/users?user_type=student&per_page=50', { headers: headers() })
    enrollDefaultStudents.value = res.data ?? []
  } catch { /* ignore */ }
}

async function searchEnrollStudents() {
  if (!enrollSearchQuery.value.trim()) { enrollSearchResults.value = []; return }
  enrollSearchLoading.value = true
  try {
    const res = await useApi<any>(`/admin/users?search=${encodeURIComponent(enrollSearchQuery.value.trim())}&user_type=student&per_page=30`, { headers: headers() })
    enrollSearchResults.value = res.data ?? []
  } catch { /* ignore */ }
  finally { enrollSearchLoading.value = false }
}

function toggleEnrollStudent(id: number) {
  const i = selectedEnrollIds.value.indexOf(id)
  if (i > -1) selectedEnrollIds.value.splice(i, 1)
  else selectedEnrollIds.value.push(id)
}

async function submitEnrollFromList() {
  if (!selectedClass.value || selectedEnrollIds.value.length === 0) {
    toast.error('Chọn ít nhất 1 sinh viên để ghi danh.')
    return
  }
  enrollSaving.value = true
  try {
    const res = await useApi<any>('/admin/academic/administrative-classes/enroll-students', {
      method: 'POST',
      headers: headers(),
      body: {
        administrative_class_id: selectedClass.value.id,
        student_ids: selectedEnrollIds.value,
      },
    })
    toast.success(res.message ?? `Đã ghi danh ${res.updated} sinh viên.`)
    enrollOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể ghi danh.')
  } finally {
    enrollSaving.value = false
  }
}

function handleImportFileChange(e: Event) {
  const t = e.target as HTMLInputElement
  if (t.files?.[0]) importFile.value = t.files[0]
}

async function previewImport() {
  if (!importFile.value || !selectedClass.value) return
  importLoading.value = true
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)
    fd.append('administrative_class_id', String(selectedClass.value.id))
    const res = await useApi<any>('/admin/academic/administrative-classes/import-students-preview', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: fd,
    })
    importPreviewData.value = res
    importStep.value = 2
    toast.success('Kiểm tra tệp hoàn tất.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Lỗi khi đọc tệp.')
  } finally {
    importLoading.value = false
  }
}

async function executeImport() {
  if (!importPreviewData.value?.import_token) return
  importExecuting.value = true
  try {
    const res = await useApi<any>('/admin/academic/administrative-classes/import-students-execute', {
      method: 'POST',
      headers: headers(),
      body: { import_token: importPreviewData.value.import_token },
    })
    toast.success(`Đã ghi danh ${res.updated ?? 0} sinh viên từ tệp.`)
    enrollOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể thực hiện import.')
  } finally {
    importExecuting.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function openEdit(cls: AdminClass) {
  selectedClass.value = cls
  Object.assign(form, {
    institution_id: 1,
    unit_id: cls.unit?.id ?? '',
    program_id: cls.program?.id ?? '',
    major_id: null,
    cohort_id: cls.cohort?.id ?? '',
    advisor_id: cls.advisor?.id ?? null,
    curriculum_id: cls.curriculum?.id ?? null,
    code: cls.code,
    name: cls.name,
    capacity: cls.capacity ?? 40,
    expected_graduation_year: cls.expected_graduation_year ?? null,
    status: cls.status,
    description: cls.description ?? '',
  })
  editOpen.value = true
}

function openDelete(cls: AdminClass) {
  selectedClass.value = cls
  deleteOpen.value = true
}

function openCreate() {
  Object.assign(form, formDefaults())
  createOpen.value = true
}

function statusLabel(val: string) {
  return { active: 'Đang hoạt động', graduated: 'Tốt nghiệp', suspended: 'Tạm ngưng' }[val] ?? val
}

function statusClass(val: string) {
  return { active: 'role-instructor', graduated: 'role-student', suspended: 'role-admin' }[val] ?? 'role-admin'
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([fetchClasses(), fetchDropdowns(), fetchAdvisors()])
})

watch(filterCohortId, () => fetchClasses(1))
watch(filterStatus, () => fetchClasses(1))
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Đào tạo & Học vụ</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Quản lý Lớp Hành Chính</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Tạo, cập nhật, và quản lý danh sách lớp hành chính. Mỗi lớp gắn với khóa đào tạo, chương trình học và cố vấn.</p>
      </div>
    </div>

    <!-- Filters & Toolbar (Always Open) -->
    <UiFilters
      v-model:search="searchQuery"
      search-placeholder="Tìm theo mã hoặc tên lớp..."
      :always-open="true"
      @submit-search="fetchClasses(1)"
    >
      <template #actions>
        <button class="inline-flex items-center gap-2 h-9 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#178762] text-white text-xs font-semibold transition-colors shrink-0 cursor-pointer mr-2" type="button" @click="openCreate">
          <i class="pi pi-plus" />
          <span>Tạo lớp mới</span>
        </button>
      </template>
      <template #advanced>
        <label class="flex flex-col gap-1">
          <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Khóa học vụ</span>
          <select v-model="filterCohortId" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">Tất cả khóa</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        
        <label class="flex flex-col gap-1">
          <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái</span>
          <select v-model="filterStatus" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </label>
      </template>
    </UiFilters>

    <!-- KPI Cards -->
    <UiKpiCards
      :items="[
        { label: 'Tổng số lớp', value: total, subText: 'Theo bộ lọc', color: 'primary', icon: 'pi-building' },
        { label: 'Đang hoạt động', value: activeCount, subText: 'Lớp học khả dụng', color: 'success', icon: 'pi-check-circle' },
        { label: 'Tổng sinh viên', value: totalStudents, subText: 'Trang hiện tại', color: 'info', icon: 'pi-users' },
      ]"
    />

    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col gap-5">
      <UiTable
        :columns="[
          { id: 'code', accessorKey: 'code', header: 'Mã lớp' },
          { id: 'name', accessorKey: 'name', header: 'Tên lớp' },
          { id: 'cohort_program', accessorKey: 'cohort_program', header: 'Khóa / Chương trình' },
          { id: 'advisor', accessorKey: 'advisor.name', header: 'Cố vấn' },
          { id: 'curriculum', accessorKey: 'curriculum.code', header: 'CTĐT' },
          { id: 'students', accessorKey: 'students_count', header: 'Sinh viên' },
          { id: 'status', accessorKey: 'status', header: 'Trạng thái' },
          { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
        ]"
        :data="classes"
        :loading="loading"
      >
        <!-- Code cell -->
        <template #code-cell="{ row }">
          <span class="mono-code font-mono font-semibold">{{ row.original.code }}</span>
        </template>

        <!-- Name cell -->
        <template #name-cell="{ row }">
          <div class="flex flex-col gap-0.5">
            <strong>{{ row.original.name }}</strong>
            <span v-if="row.original.unit" class="text-xs text-[var(--muted)]">{{ row.original.unit.name }}</span>
          </div>
        </template>

        <!-- Cohort/Program cell -->
        <template #cohort_program-cell="{ row }">
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-semibold">{{ row.original.cohort?.name ?? '—' }}</span>
            <span class="text-xs text-[var(--muted)]">{{ row.original.program?.name ?? '—' }}</span>
          </div>
        </template>

        <!-- Advisor cell -->
        <template #advisor-cell="{ row }">
          <div v-if="row.original.advisor" class="flex flex-col">
            <strong class="text-xs font-bold text-[var(--text)]">{{ row.original.advisor.name }}</strong>
            <span class="text-[10px] text-[var(--muted)]">{{ row.original.advisor.email }}</span>
          </div>
          <span v-else class="text-[var(--muted)] text-xs">—</span>
        </template>

        <!-- Curriculum cell -->
        <template #curriculum-cell="{ row }">
          <span v-if="row.original.curriculum" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
            <i class="pi pi-graduation-cap" /> {{ row.original.curriculum.code }}
          </span>
          <span v-else class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-bold bg-slate-50 text-slate-500 border border-slate-200">
            <i class="pi pi-graduation-cap" /> Chưa gán
          </span>
        </template>

        <!-- Students cell -->
        <template #students-cell="{ row }">
          <div class="flex items-center gap-1.5 text-xs">
            <i class="pi pi-users text-[var(--muted)]" />
            <strong>{{ row.original.students_count ?? 0 }}</strong>
            <span class="text-[var(--muted)]">/ {{ row.original.capacity }}</span>
          </div>
        </template>

        <!-- Status cell -->
        <template #status-cell="{ row }">
          <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold" :class="statusClass(row.original.status)">
            {{ statusLabel(row.original.status) }}
          </span>
        </template>

        <!-- Actions cell -->
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-1.5">
            <button class="inline-flex items-center justify-center gap-1 h-7 px-2.5 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors cursor-pointer" type="button" @click="openEnroll(row.original)">
              <i class="pi pi-user-plus" />
              <span>Ghi danh</span>
            </button>
            <button class="w-7 h-7 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] flex items-center justify-center text-[var(--muted)] hover:text-[var(--text)] transition-colors cursor-pointer" type="button" @click="openEdit(row.original)">
              <i class="pi pi-pencil" />
            </button>
            <button class="w-7 h-7 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 flex items-center justify-center text-red-600 transition-colors cursor-pointer" type="button" @click="openDelete(row.original)">
              <i class="pi pi-trash" />
            </button>
          </div>
        </template>

        <template #empty>
          <div class="flex flex-col items-center justify-center py-16 gap-2 text-[var(--color-text-muted)]">
            <i class="pi pi-building text-3xl opacity-40" />
            <p class="text-sm font-medium">Chưa có lớp hành chính nào</p>
          </div>
        </template>
      </UiTable>

      <DataTableFooter
        :current="currentPage"
        :last="lastPage"
        :total="total"
        :per-page="perPage"
        @page="fetchClasses"
        @update:per-page="perPage = $event; fetchClasses(1)"
      />
    </section>

    <!-- Create Modal -->
    <UModal
      v-model:open="createOpen"
      title="Thêm lớp hành chính"
      subtitle="Tạo mới"
      :ui="{ width: 'max-w-2xl' }"
    >
      <div class="grid grid-cols-2 gap-4 text-left">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Mã lớp *</span>
          <input v-model="form.code" type="text" placeholder="VD: CNTT2021A" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Tên lớp *</span>
          <input v-model="form.name" type="text" placeholder="VD: Công nghệ thông tin K2021A" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Khóa học vụ *</span>
          <select v-model="form.cohort_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn khóa —</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Chương trình *</span>
          <select v-model="form.program_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn chương trình —</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Khoa / Đơn vị *</span>
          <select v-model="form.unit_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn đơn vị —</option>
            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">CTĐT (Chương trình đào tạo)</span>
          <select v-model="form.curriculum_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option :value="null">— Chưa gán —</option>
            <option v-for="c in curricula" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Cố vấn học tập</span>
          <select v-model="form.advisor_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option :value="null">— Không có —</option>
            <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Sĩ số tối đa</span>
          <input v-model="form.capacity" type="number" min="1" placeholder="40" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Năm dự kiến tốt nghiệp</span>
          <input v-model="form.expected_graduation_year" type="number" min="2020" placeholder="2025" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Trạng thái</span>
          <select v-model="form.status" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="active">Đang hoạt động</option>
            <option value="graduated">Tốt nghiệp</option>
            <option value="suspended">Tạm ngưng</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 col-span-2">
          <span class="text-xs font-semibold text-[var(--text)]">Ghi chú</span>
          <textarea v-model="form.description" rows="3" placeholder="Mô tả thêm về lớp hành chính..." class="p-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] resize-y" />
        </label>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="createOpen = false">Hủy</button>
        <button class="btn-primary" :disabled="saving" @click="createClass">
          {{ saving ? 'Đang tạo...' : 'Tạo lớp' }}
        </button>
      </template>
    </UModal>

    <!-- Edit Modal -->
    <UModal
      v-model:open="editOpen"
      :title="selectedClass?.name || 'Cập nhật lớp'"
      subtitle="Chỉnh sửa"
      :ui="{ width: 'max-w-2xl' }"
    >
      <div class="grid grid-cols-2 gap-4 text-left">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Mã lớp *</span>
          <input v-model="form.code" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Tên lớp *</span>
          <input v-model="form.name" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Khóa học vụ *</span>
          <select v-model="form.cohort_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn khóa —</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Chương trình *</span>
          <select v-model="form.program_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn chương trình —</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Khoa / Đơn vị *</span>
          <select v-model="form.unit_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn đơn vị —</option>
            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">CTĐT</span>
          <select v-model="form.curriculum_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option :value="null">— Chưa gán —</option>
            <option v-for="c in curricula" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Cố vấn học tập</span>
          <select v-model="form.advisor_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option :value="null">— Không có —</option>
            <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Sĩ số tối đa</span>
          <input v-model="form.capacity" type="number" min="1" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Năm dự kiến tốt nghiệp</span>
          <input v-model="form.expected_graduation_year" type="number" min="2020" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Trạng thái</span>
          <select v-model="form.status" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="active">Đang hoạt động</option>
            <option value="graduated">Tốt nghiệp</option>
            <option value="suspended">Tạm ngưng</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5 col-span-2">
          <span class="text-xs font-semibold text-[var(--text)]">Ghi chú</span>
          <textarea v-model="form.description" rows="3" class="p-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] resize-y" />
        </label>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="editOpen = false">Hủy</button>
        <button class="btn-primary" :disabled="saving" @click="updateClass">
          {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
        </button>
      </template>
    </UModal>

    <!-- Delete Modal -->
    <CrudConfirmModal
      v-model:open="deleteOpen"
      title="Xóa lớp hành chính"
      :description="`Bạn có chắc muốn xóa lớp ${selectedClass?.name} (${selectedClass?.code})? Hành động này không thể hoàn tác. Dữ liệu sinh viên trong lớp sẽ bị ảnh hưởng.`"
      confirm-label="Xác nhận xóa"
      :loading="saving"
      @confirm="deleteClass"
      @cancel="deleteOpen = false"
    />

    <!-- Enroll Modal -->
    <UModal
      v-model:open="enrollOpen"
      :title="`Ghi danh sinh viên - ${selectedClass?.name || ''}`"
      :subtitle="selectedClass?.code || ''"
      :ui="{ width: 'max-w-3xl' }"
    >
      <!-- Tabs header inside modal body to stay within component container -->
      <div class="flex border-b border-[var(--line)] mb-4">
        <button
          class="inline-flex items-center gap-2 px-4 py-2 border-b-2 font-semibold text-sm transition-colors cursor-pointer"
          :class="enrollTab === 'list' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[#1d9e75]'"
          type="button"
          @click="enrollTab = 'list'"
        >
          <i class="pi pi-users" />
          <span>Chọn từ danh sách</span>
        </button>
        <button
          class="inline-flex items-center gap-2 px-4 py-2 border-b-2 font-semibold text-sm transition-colors cursor-pointer"
          :class="enrollTab === 'import' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[#1d9e75]'"
          type="button"
          @click="enrollTab = 'import'; importStep = 1; importFile = null; importPreviewData = null"
        >
          <i class="pi pi-file-excel" />
          <span>Import từ Excel/CSV</span>
        </button>
      </div>

      <!-- Tab: chọn từ list -->
      <div v-if="enrollTab === 'list'" class="flex flex-col gap-4 text-left">
        <div class="relative">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--muted)]" />
          <input
            v-model="enrollSearchQuery"
            type="text"
            placeholder="Tìm theo mã SV hoặc tên học viên..."
            class="w-full h-9 pl-9 pr-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]"
            @input="searchEnrollStudents"
          />
        </div>

        <div v-if="enrollDisplayStudents.length > 0" class="flex justify-between items-center text-xs text-[var(--muted)]">
          <span>Tìm thấy {{ enrollDisplayStudents.length }} học viên</span>
          <span>Đã chọn: <strong class="text-[#1d9e75] font-bold">{{ selectedEnrollIds.length }}</strong></span>
        </div>

        <div class="grid grid-cols-2 gap-3 max-h-[300px] overflow-y-auto pr-1">
          <div v-if="enrollSearchLoading" class="col-span-2 py-8 text-center text-sm text-[var(--muted)]">Đang tìm kiếm...</div>
          <div v-else-if="!enrollDisplayStudents.length" class="col-span-2 py-8 text-center text-sm text-[var(--muted)]">
            Không tìm thấy sinh viên nào.
          </div>
          <div
            v-else
            v-for="s in enrollDisplayStudents"
            :key="s.id"
            class="flex items-center gap-3 p-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] hover:border-emerald-200 hover:bg-emerald-50/20 cursor-pointer transition-all"
            :class="{ 'border-emerald-300 bg-emerald-50/55': selectedEnrollIds.includes(s.id) }"
            @click="toggleEnrollStudent(s.id)"
          >
            <div class="w-4 h-4 rounded border border-[var(--line)] flex items-center justify-center bg-white shrink-0" :class="{ 'bg-[#1d9e75] border-[#1d9e75] text-white': selectedEnrollIds.includes(s.id) }">
              <i v-if="selectedEnrollIds.includes(s.id)" class="pi pi-check text-[9px]" />
            </div>
            <div class="flex flex-col gap-0.5 min-w-0">
              <span class="font-mono text-[10px] font-bold text-[#1d9e75]">{{ s.student_code }}</span>
              <strong class="text-xs font-bold text-[var(--text)] truncate">{{ s.name }}</strong>
              <span class="text-[10px] text-[var(--muted)] truncate">{{ s.email }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: import CSV -->
      <div v-else class="flex flex-col gap-4 text-left">
        <div v-if="importStep === 1" class="flex flex-col gap-3">
          <p class="text-xs text-[var(--muted)] leading-relaxed">
            Tải lên tệp CSV với cột đầu tiên là <strong>Mã sinh viên</strong>. Hệ thống sẽ kiểm tra xem trước kết quả trước khi ghi danh.
          </p>
          <div class="border-2 border-dashed border-[var(--line)] hover:border-[#1d9e75] rounded-2xl p-8 flex flex-col items-center justify-center gap-2 cursor-pointer transition-colors text-center" @click="importFileRef?.click()">
            <i class="pi pi-file-excel text-3xl text-[var(--muted)]" />
            <div v-if="!importFile" class="flex flex-col gap-0.5">
              <strong class="text-sm">Chọn tệp CSV</strong>
              <span class="text-xs text-[var(--muted)]">Cột 1: Mã sinh viên (student_code)</span>
            </div>
            <div class="flex flex-col gap-0.5 text-[#1d9e75]" v-else>
              <strong class="text-sm">{{ importFile.name }}</strong>
              <span class="text-xs text-[var(--muted)]">{{ (importFile.size / 1024).toFixed(1) }} KB — Nhấn để đổi tệp</span>
            </div>
            <input ref="importFileRef" type="file" accept=".csv,.txt" style="display:none;" @change="handleImportFileChange" />
          </div>
        </div>

        <div v-else-if="importStep === 2 && importPreviewData" class="flex flex-col gap-4">
          <div class="grid grid-cols-3 gap-4">
            <div class="border border-[var(--line)] rounded-xl p-3 text-center flex flex-col gap-0.5">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng dòng</span>
              <strong class="text-lg font-bold">{{ importPreviewData.total_rows }}</strong>
            </div>
            <div class="border border-[var(--line)] rounded-xl p-3 text-center flex flex-col gap-0.5 bg-emerald-50/50 border-emerald-100">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[#1d9e75]">Hợp lệ</span>
              <strong class="text-lg font-bold text-[#1d9e75]">{{ importPreviewData.valid_rows }}</strong>
            </div>
            <div class="border border-[var(--line)] rounded-xl p-3 text-center flex flex-col gap-0.5 bg-red-50/50 border-red-100">
              <span class="text-[10px] font-bold uppercase tracking-wider text-red-600">Lỗi</span>
              <strong class="text-lg font-bold text-red-600">{{ importPreviewData.invalid_rows }}</strong>
            </div>
          </div>

          <div class="border border-[var(--line)] rounded-xl overflow-hidden max-h-[200px] overflow-y-auto">
            <table class="w-full text-left text-xs divide-y divide-[var(--line)]">
              <thead class="bg-[var(--surface)] text-[var(--muted)] font-semibold">
                <tr>
                  <th class="p-2.5">Dòng</th>
                  <th class="p-2.5">Mã SV</th>
                  <th class="p-2.5">Họ tên</th>
                  <th class="p-2.5">Kết quả</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--line)]">
                <tr v-for="row in importPreviewData.preview_data" :key="row.row_number" class="hover:bg-[var(--surface)] transition-colors">
                  <td class="p-2.5">{{ row.row_number }}</td>
                  <td class="p-2.5 font-mono">{{ row.student_code }}</td>
                  <td class="p-2.5 font-medium">{{ row.student_name || '—' }}</td>
                  <td class="p-2.5 font-semibold" :class="row.status === 'valid' ? 'text-[#1d9e75]' : 'text-red-600'">
                    {{ row.message }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <template #footer>
        <button class="btn-secondary mr-2" @click="enrollOpen = false">Đóng</button>

        <!-- List tab actions -->
        <button
          v-if="enrollTab === 'list'"
          class="btn-primary"
          :disabled="enrollSaving || selectedEnrollIds.length === 0"
          @click="submitEnrollFromList"
        >
          {{ enrollSaving ? 'Đang ghi danh...' : `Ghi danh ${selectedEnrollIds.length} sinh viên` }}
        </button>

        <!-- Import tab actions -->
        <button
          v-if="enrollTab !== 'list' && importStep === 1"
          class="btn-primary"
          :disabled="importLoading || !importFile"
          @click="previewImport"
        >
          {{ importLoading ? 'Đang kiểm tra...' : 'Xem trước' }}
        </button>
        
        <button
          v-if="enrollTab !== 'list' && importStep !== 1"
          class="btn-secondary mr-2"
          @click="importStep = 1; importFile = null; importPreviewData = null"
        >
          Chọn lại tệp
        </button>
        <button
          v-if="enrollTab !== 'list' && importStep !== 1"
          class="btn-primary"
          :disabled="importExecuting || !importPreviewData?.valid_rows"
          @click="executeImport"
        >
          {{ importExecuting ? 'Đang ghi danh...' : `Ghi danh ${importPreviewData?.valid_rows ?? 0} sinh viên` }}
        </button>
      </template>
    </UModal>
  </div>
</template>

<style scoped>
.search-wrap { position: relative; }
.search-ico { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }

.mono-code { font-family: monospace; font-weight: 700; color: var(--green-deep); font-size: 0.85rem; }

.has-ctdt-tag {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(var(--green-rgb), 0.1); color: var(--green-deep);
  font-weight: 700; font-size: 0.72rem; padding: 3px 8px; border-radius: 99px;
}
.no-ctdt-tag {
  display: inline-flex; align-items: center; gap: 5px;
  background: #fffbeb; color: #d97706;
  font-weight: 700; font-size: 0.72rem; padding: 3px 8px; border-radius: 99px;
}

.crud-pagination { display: flex; align-items: center; justify-content: space-between; }
.crud-pagination-btns { display: flex; gap: 6px; }

.crud-form-grid textarea {
  resize: vertical;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--surface);
  color: var(--text);
  font-size: 0.9rem;
  font-family: inherit;
  width: 100%;
}

.crud-form-grid em {
  color: #ef4444;
  font-style: normal;
}

.crud-modal-body p { margin: 0; line-height: 1.6; color: var(--text); }

.enroll-modal-tabs {
  display: flex;
  gap: 4px;
  padding: 0 28px;
  border-bottom: 2px solid var(--line);
  margin-bottom: 0;
}
.enroll-modal-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 16px; border: none; background: none;
  font-size: 0.86rem; font-weight: 600; color: var(--muted);
  cursor: pointer; position: relative; border-radius: 8px 8px 0 0;
  transition: color 0.15s;
}
.enroll-modal-tab:hover { color: var(--green-deep); }
.enroll-modal-tab.is-active { color: var(--green-deep); }
.enroll-modal-tab.is-active::after {
  content: ''; position: absolute; bottom: -2px; left: 0; right: 0;
  height: 2px; background: var(--green-deep); border-radius: 99px;
}

.enroll-modal-body {
  padding: 20px 28px;
  max-height: 380px;
  overflow-y: auto;
}

.picker-list { display: flex; flex-direction: column; gap: 6px; }
.picker-row {
  display: flex; gap: 12px; align-items: center;
  padding: 10px 12px; border-radius: 10px;
  border: 1px solid var(--line); background: var(--surface);
  cursor: pointer; transition: all 0.15s;
}
.picker-row:hover { border-color: rgba(var(--green-rgb), 0.3); }
.picker-row.is-sel { border-color: rgba(var(--green-rgb), 0.4); background: rgba(var(--green-rgb), 0.06); }
.pick-check {
  width: 18px; height: 18px; border-radius: 4px;
  border: 2px solid var(--line); display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; background: var(--surface); transition: all 0.1s;
}
.is-sel .pick-check { background: var(--green-deep); border-color: var(--green-deep); color: #fff; }

.import-dropzone {
  border: 2px dashed var(--line); border-radius: 14px; padding: 32px;
  text-align: center; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  transition: all 0.15s;
}
.import-dropzone:hover { border-color: rgba(var(--green-rgb), 0.4); background: rgba(var(--green-rgb), 0.03); }
.import-dropzone strong { font-size: 0.9rem; color: var(--text); display: block; }
.import-dropzone span { font-size: 0.78rem; color: var(--muted); display: block; }

.import-preview-stats { display: flex; gap: 24px; padding-bottom: 12px; border-bottom: 1px solid var(--line); margin-bottom: 12px; }
.pstat { display: flex; flex-direction: column; gap: 2px; }
.pstat span { font-size: 0.75rem; color: var(--muted); }
.pstat strong { font-size: 1.1rem; font-weight: 800; }
.import-preview-scroll { max-height: 200px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px; }
</style>
