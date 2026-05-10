<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

type ResourceKey =
  | 'institutions'
  | 'units'
  | 'program-types'
  | 'academic-years'
  | 'terms'
  | 'programs'
  | 'majors'
  | 'curricula'
  | 'cohorts'
  | 'class-sections'
  | 'plos'
  | 'clos'
  | 'skills'

interface ResourceOption {
  key: ResourceKey
  label: string
  icon: string
  description: string
  columns: Array<{ key: string; label: string }>
}

interface LookupOption {
  value: number
  label: string
}

interface PaginatedResponse<T = Record<string, any>> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

const props = withDefaults(defineProps<{
  initialResource?: ResourceKey
  allowResourceSwitch?: boolean
  readonly?: boolean
}>(), {
  initialResource: 'units',
  allowResourceSwitch: true,
  readonly: false,
})

const PER_PAGE = 15

const token = useAuthTokenCookie()
const currentResource = ref<ResourceKey>(props.initialResource)
const rows = ref<Record<string, any>[]>([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalRows = ref(0)

const searchTerm = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')

const modalOpen = ref(false)
const deleteModalOpen = ref(false)
const mode = ref<'create' | 'edit'>('create')
const selectedRow = ref<Record<string, any> | null>(null)
const form = reactive<Record<string, any>>({})
const lookupOptions = ref<Record<string, LookupOption[]>>({})
const fieldErrors = ref<Record<string, string>>({})

// Bulk-enroll modal (cohorts only) — wires the cohort to the
// POST /admin/academic/cohorts/{id}/enroll-core endpoint.
const enrollModalOpen = ref(false)
const enrolling = ref(false)
const enrollCohort = ref<Record<string, any> | null>(null)
const enrollForm = reactive<{ term_id: string | number; curriculum_id: string | number }>({
  term_id: '',
  curriculum_id: '',
})
const enrollResult = ref<{
  message?: string
  students?: number
  courses?: number
  created?: number
  skipped?: number
} | null>(null)

const requiredFieldsByResource: Record<ResourceKey, string[]> = {
  'institutions': ['name', 'code'],
  'units': ['institution_id', 'name', 'code', 'unit_type'],
  'program-types': ['name', 'code'],
  'academic-years': ['institution_id', 'name', 'start_date', 'end_date'],
  'terms': ['academic_year_id', 'name', 'code', 'start_date', 'end_date'],
  'programs': ['institution_id', 'program_type_id', 'name', 'code'],
  'majors': ['program_id', 'name', 'code'],
  'curricula': ['program_id', 'name', 'code'],
  'cohorts': ['institution_id', 'program_id', 'name', 'code', 'start_year'],
  'class-sections': ['course_id', 'code'],
  'plos': ['program_id', 'code', 'description'],
  'clos': ['course_id', 'code', 'description'],
  'skills': ['code', 'name'],
}

function isRequired(field: string) {
  return (requiredFieldsByResource[currentResource.value] || []).includes(field)
}

const resourceOptions: ResourceOption[] = [
  { key: 'institutions', label: 'Trường', icon: 'domain', description: 'Đơn vị cấp trường', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'institution_type', label: 'Loại' }] },
  { key: 'units', label: 'Đơn vị', icon: 'account_tree', description: 'Viện, khoa, bộ môn', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'unit_type', label: 'Loại' }, { key: 'level', label: 'Cấp' }] },
  { key: 'program-types', label: 'Loại chương trình', icon: 'category', description: 'Phân loại CTĐT', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'description', label: 'Mô tả' }] },
  { key: 'academic-years', label: 'Năm học', icon: 'event', description: 'Niên khóa toàn trường', columns: [{ key: 'name', label: 'Tên' }, { key: 'start_date', label: 'Bắt đầu' }, { key: 'end_date', label: 'Kết thúc' }, { key: 'status', label: 'Trạng thái' }] },
  { key: 'terms', label: 'Học kỳ', icon: 'calendar_month', description: 'Học kỳ trong năm học', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'start_date', label: 'Bắt đầu' }, { key: 'end_date', label: 'Kết thúc' }] },
  { key: 'programs', label: 'Chương trình đào tạo', icon: 'school', description: 'Chương trình theo khoa', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'program_type_id', label: 'Loại CT' }, { key: 'duration_months', label: 'Tháng' }] },
  { key: 'majors', label: 'Ngành', icon: 'menu_book', description: 'Ngành đào tạo', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'program_id', label: 'CTĐT' }, { key: 'unit_id', label: 'Đơn vị' }] },
  { key: 'curricula', label: 'Khung chương trình', icon: 'view_list', description: 'Khung CT theo ngành', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'program_id', label: 'CTĐT' }, { key: 'major_id', label: 'Ngành' }] },
  { key: 'cohorts', label: 'Khóa/Lớp hành chính', icon: 'groups', description: 'Khóa - lớp theo niên khóa', columns: [{ key: 'name', label: 'Tên' }, { key: 'code', label: 'Mã' }, { key: 'start_year', label: 'Khóa vào' }, { key: 'status', label: 'Trạng thái' }] },
  { key: 'class-sections', label: 'Lớp học phần', icon: 'class', description: 'Lớp học phần theo kỳ + cohort + giảng viên', columns: [{ key: 'code', label: 'Mã' }, { key: 'course_id', label: 'Học phần' }, { key: 'term_id', label: 'Kỳ' }, { key: 'cohort_id', label: 'Khóa' }, { key: 'lecturer_id', label: 'Giảng viên' }, { key: 'status', label: 'Trạng thái' }] },
  { key: 'plos', label: 'Chuẩn đầu ra CTĐT (PLO)', icon: 'flag', description: 'PLO theo chương trình đào tạo', columns: [{ key: 'code', label: 'Mã' }, { key: 'description', label: 'Mô tả' }, { key: 'program_id', label: 'CTĐT' }, { key: 'level', label: 'Mức độ' }] },
  { key: 'clos', label: 'Chuẩn đầu ra học phần (CLO)', icon: 'task_alt', description: 'CLO theo từng học phần', columns: [{ key: 'code', label: 'Mã' }, { key: 'description', label: 'Mô tả' }, { key: 'course_id', label: 'Học phần' }] },
  { key: 'skills', label: 'Kỹ năng', icon: 'bolt', description: 'Skill taxonomy dùng cho gợi ý khóa học', columns: [{ key: 'code', label: 'Mã' }, { key: 'name', label: 'Tên kỹ năng' }, { key: 'category', label: 'Nhóm' }] },
]

const currentOption = computed<ResourceOption>(() =>
  resourceOptions.find((option) => option.key === currentResource.value) || resourceOptions[0] as ResourceOption,
)

const formSchema = computed(() => {
  const key = currentResource.value
  if (key === 'institutions') return ['name', 'code', 'institution_type', 'is_active']
  if (key === 'units') return ['institution_id', 'parent_id', 'name', 'code', 'unit_type', 'level', 'is_active']
  if (key === 'program-types') return ['name', 'code', 'description', 'is_active']
  if (key === 'academic-years') return ['institution_id', 'name', 'start_date', 'end_date', 'is_current', 'status']
  if (key === 'terms') return ['academic_year_id', 'name', 'code', 'start_date', 'end_date', 'is_current', 'status']
  if (key === 'programs') return ['institution_id', 'unit_id', 'program_type_id', 'name', 'code', 'duration_months', 'is_active']
  if (key === 'majors') return ['program_id', 'unit_id', 'name', 'code', 'is_active']
  if (key === 'curricula') return ['program_id', 'major_id', 'specialization_id', 'name', 'code', 'effective_from', 'effective_to', 'is_active']
  if (key === 'class-sections') return ['course_id', 'term_id', 'cohort_id', 'lecturer_id', 'code', 'name', 'capacity', 'status', 'description']
  if (key === 'plos') return ['program_id', 'code', 'description', 'level', 'position']
  if (key === 'clos') return ['course_id', 'code', 'description', 'position']
  if (key === 'skills') return ['code', 'name', 'category', 'description']
  return ['institution_id', 'program_id', 'major_id', 'name', 'code', 'start_year', 'end_year', 'status']
})

const headers = computed(() =>
  token.value
    ? { Authorization: `Bearer ${token.value}` }
    : {},
)

const fieldToResource: Record<string, ResourceKey> = {
  institution_id: 'institutions',
  parent_id: 'units',
  unit_id: 'units',
  program_type_id: 'program-types',
  academic_year_id: 'academic-years',
  program_id: 'programs',
  major_id: 'majors',
  specialization_id: 'curricula',
  term_id: 'terms',
  cohort_id: 'cohorts',
}

// Fields whose lookup lives outside the academic CRUD endpoints.
// course_id → /admin/courses (filter to course_mode=core)
// lecturer_id → /admin/users (filter to instructor role)
const externalLookupFields = ['course_id', 'lecturer_id']

function labelForField(field: string) {
  const labels: Record<string, string> = {
    institution_id: 'Trường',
    parent_id: 'Đơn vị cha',
    unit_id: 'Đơn vị',
    program_type_id: 'Loại chương trình',
    academic_year_id: 'Năm học',
    program_id: 'Chương trình đào tạo',
    major_id: 'Ngành',
    specialization_id: 'Khung chương trình',
    course_id: 'Học phần (môn)',
    term_id: 'Kỳ học',
    cohort_id: 'Khóa/Lớp',
    lecturer_id: 'Giảng viên',
    name: 'Tên',
    code: 'Mã',
    description: 'Mô tả',
    institution_type: 'Loại trường',
    unit_type: 'Loại đơn vị',
    level: 'Cấp',
    capacity: 'Sĩ số tối đa',
    is_active: 'Kích hoạt',
    is_current: 'Hiện hành',
    start_date: 'Ngày bắt đầu',
    end_date: 'Ngày kết thúc',
    duration_months: 'Thời lượng (tháng)',
    effective_from: 'Hiệu lực từ',
    effective_to: 'Hiệu lực đến',
    start_year: 'Năm bắt đầu',
    end_year: 'Năm kết thúc',
    status: 'Trạng thái',
  }
  return labels[field] || field.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

const statusMap: Record<string, string> = {
  active: 'Đang hoạt động',
  inactive: 'Ngừng hoạt động',
  draft: 'Nháp',
  archived: 'Lưu trữ',
  planned: 'Dự kiến',
  ongoing: 'Đang diễn ra',
  completed: 'Đã kết thúc',
  closed: 'Đã đóng',
  open: 'Đang mở',
}

function normalizeValue(value: any) {
  if (value === null || value === undefined || value === '') return '--'
  if (typeof value === 'boolean') return value ? 'Có' : 'Không'
  if (typeof value === 'string') return statusMap[value.toLowerCase()] || value
  return String(value)
}

const DATE_FIELDS = new Set([
  'start_date',
  'end_date',
  'effective_from',
  'effective_to',
  'enrollment_start_at',
  'enrollment_end_at',
  'exam_start_at',
  'exam_end_at',
  'created_at',
  'updated_at',
])

function formatDateTime(value: any): string {
  if (value === null || value === undefined || value === '') return '--'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function lookupLabel(field: string, value: any) {
  if (value === null || value === undefined || value === '') return '--'
  const opts = lookupOptions.value[field] || []
  const found = opts.find((opt) => Number(opt.value) === Number(value))
  return found?.label || `#${value}`
}

function getCellDisplay(row: Record<string, any>, field: string) {
  const raw = row[field]
  if (field.endsWith('_id')) return lookupLabel(field, raw)
  if (DATE_FIELDS.has(field)) return formatDateTime(raw)
  return normalizeValue(raw)
}

function statusTone(value: string) {
  const v = (value || '').toLowerCase()
  if (['active', 'ongoing', 'open', 'current'].includes(v)) return 'is-success'
  if (['planned', 'draft'].includes(v)) return 'is-warning'
  if (['inactive', 'closed', 'archived', 'completed'].includes(v)) return 'is-muted'
  return 'is-muted'
}

const isTreeView = computed(() => currentResource.value === 'units')
const expandedIds = ref<Set<number>>(new Set())

interface TreeEntry {
  row: Record<string, any>
  depth: number
  hasChildren: boolean
}

function passesStatusFilter(row: Record<string, any>): boolean {
  if (statusFilter.value === 'active') {
    if (row.is_active === false) return false
    if (typeof row.status === 'string' && ['inactive', 'closed', 'archived', 'completed'].includes(row.status.toLowerCase())) return false
  } else if (statusFilter.value === 'inactive') {
    const inactive = row.is_active === false
      || (typeof row.status === 'string' && ['inactive', 'closed', 'archived', 'completed'].includes(row.status.toLowerCase()))
    if (!inactive) return false
  }
  return true
}

const filteredRows = computed(() => {
  const term = searchTerm.value.trim().toLowerCase()
  return rows.value.filter((row) => {
    if (!passesStatusFilter(row)) return false
    if (!term) return true
    const haystack = [row.id, row.name, row.code, row.description].filter(Boolean).join(' ').toLowerCase()
    return haystack.includes(term)
  })
})

// Tree-walked, ordered list for tree-view resources (units).
// Honors status filter + search; search auto-expands matching ancestors.
const treeEntries = computed<TreeEntry[]>(() => {
  if (!isTreeView.value) return []

  const term = searchTerm.value.trim().toLowerCase()
  const visible = rows.value.filter(passesStatusFilter)
  const byId = new Map<number, Record<string, any>>()
  visible.forEach((r) => byId.set(Number(r.id), r))

  const childrenByParent = new Map<number | null, Record<string, any>[]>()
  visible.forEach((r) => {
    const pid = r.parent_id ? Number(r.parent_id) : null
    const list = childrenByParent.get(pid) ?? []
    list.push(r)
    childrenByParent.set(pid, list)
  })

  // When searching, find matches + walk ancestors so the path stays visible.
  const matched = new Set<number>()
  if (term) {
    visible.forEach((r) => {
      const haystack = [r.id, r.name, r.code, r.description].filter(Boolean).join(' ').toLowerCase()
      if (haystack.includes(term)) {
        matched.add(Number(r.id))
        let p = r.parent_id ? Number(r.parent_id) : null
        while (p && byId.has(p)) {
          matched.add(p)
          p = byId.get(p)?.parent_id ? Number(byId.get(p)!.parent_id) : null
        }
      }
    })
  }

  // Roots = items whose parent_id is missing from the visible set.
  const roots = visible
    .filter((r) => !r.parent_id || !byId.has(Number(r.parent_id)))
    .sort((a, b) => (a.level ?? 0) - (b.level ?? 0) || (a.id - b.id))

  const result: TreeEntry[] = []
  const walk = (node: Record<string, any>, depth: number) => {
    if (term && !matched.has(Number(node.id))) return
    const children = (childrenByParent.get(Number(node.id)) ?? [])
      .slice()
      .sort((a, b) => (a.level ?? 0) - (b.level ?? 0) || (a.id - b.id))
    result.push({ row: node, depth, hasChildren: children.length > 0 })
    const isExpanded = term ? matched.has(Number(node.id)) : expandedIds.value.has(Number(node.id))
    if (isExpanded) {
      children.forEach((c) => walk(c, depth + 1))
    }
  }
  roots.forEach((r) => walk(r, 0))
  return result
})

function toggleExpand(id: number) {
  const next = new Set(expandedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expandedIds.value = next
}

function expandAllUnits() {
  expandedIds.value = new Set(rows.value.map((r) => Number(r.id)))
}

function collapseAllUnits() {
  expandedIds.value = new Set()
}

const activeCount = computed(() =>
  rows.value.filter((row) => {
    if (row.is_active === false) return false
    if (typeof row.status === 'string' && ['inactive', 'closed', 'archived', 'completed'].includes(row.status.toLowerCase())) return false
    return true
  }).length,
)

const lastUpdatedAt = computed(() => {
  let max = 0
  rows.value.forEach((row) => {
    const ts = row.updated_at || row.created_at
    if (ts) {
      const t = new Date(ts).getTime()
      if (t > max) max = t
    }
  })
  return max ? new Date(max) : null
})

function resetForm() {
  Object.keys(form).forEach((key) => delete form[key])
  formSchema.value.forEach((field) => {
    form[field] = field.includes('is_') ? false : ''
  })
}

async function fetchRows(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    // Tree view fetches everything in one shot — pagination doesn't make
    // sense when rendering hierarchy.
    const perPage = isTreeView.value ? 500 : PER_PAGE
    const response = await useApi<PaginatedResponse>(`/admin/academic/${currentResource.value}?page=${page}&per_page=${perPage}`, {
      headers: headers.value,
    })
    rows.value = response.data || []
    currentPage.value = response.current_page || 1
    lastPage.value = response.last_page || 1
    totalRows.value = response.total || rows.value.length

    if (isTreeView.value) {
      // Default: expand all so admins see the whole tree on load.
      expandedIds.value = new Set(rows.value.map((r) => Number(r.id)))
    }
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải dữ liệu học vụ.'
  } finally {
    loading.value = false
  }
}

async function loadLookupOptions() {
  const neededFields = formSchema.value.filter((field) => field.endsWith('_id'))
  for (const field of neededFields) {
    if (externalLookupFields.includes(field)) {
      await loadExternalLookup(field)
      continue
    }
    const resource = fieldToResource[field]
    if (!resource) continue
    try {
      const response = await useApi<PaginatedResponse>(`/admin/academic/${resource}?per_page=200`, {
        headers: headers.value,
      })
      lookupOptions.value[field] = (response.data || []).map((item: any) => ({
        value: item.id,
        label: item.name || item.code || `#${item.id}`,
      }))
    } catch {
      lookupOptions.value[field] = []
    }
  }
}

async function loadExternalLookup(field: string) {
  try {
    if (field === 'course_id') {
      // Lecture sections only make sense for academic core courses.
      const response = await useApi<PaginatedResponse>('/admin/courses?per_page=200', {
        headers: headers.value,
      })
      lookupOptions.value['course_id'] = (response.data || [])
        .filter((c: any) => (c.course_mode ?? 'extension') === 'core')
        .map((c: any) => ({ value: c.id, label: c.title || c.slug || `#${c.id}` }))
    } else if (field === 'lecturer_id') {
      const response = await useApi<PaginatedResponse>('/admin/users?per_page=200', {
        headers: headers.value,
      })
      lookupOptions.value['lecturer_id'] = (response.data || [])
        .filter((u: any) => Array.isArray(u.roles)
          ? u.roles.some((r: any) => (typeof r === 'string' ? r : r?.name) === 'instructor')
          : false)
        .map((u: any) => ({ value: u.id, label: u.name || u.email || `#${u.id}` }))
    }
  } catch {
    lookupOptions.value[field] = []
  }
}

function openCreate() {
  mode.value = 'create'
  selectedRow.value = null
  resetForm()
  fieldErrors.value = {}
  errorMessage.value = ''
  modalOpen.value = true
}

function openEdit(row: Record<string, any>) {
  mode.value = 'edit'
  selectedRow.value = row
  resetForm()
  formSchema.value.forEach((field) => {
    form[field] = row[field] ?? (field.includes('is_') ? false : '')
  })
  fieldErrors.value = {}
  errorMessage.value = ''
  modalOpen.value = true
}

function askDelete(row: Record<string, any>) {
  selectedRow.value = row
  deleteModalOpen.value = true
}

function coerceFieldValue(field: string, value: any) {
  if (value === '' || value === undefined) return null
  if (field.endsWith('_id') && value !== null) {
    const num = Number(value)
    return Number.isFinite(num) ? num : value
  }
  // PLO `level` is a string enum; everything else uses level as positional integer.
  if (field === 'level' && currentResource.value === 'plos') {
    return value
  }
  if ((field.includes('year') || field.includes('duration') || field === 'level' || field === 'capacity' || field === 'position') && value !== null) {
    const num = Number(value)
    return Number.isFinite(num) ? num : value
  }
  return value
}

async function saveRow() {
  if (props.readonly) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
  try {
    const body: Record<string, any> = {}
    formSchema.value.forEach((field) => {
      body[field] = coerceFieldValue(field, form[field])
    })
    const wasCreate = mode.value === 'create'
    if (wasCreate) {
      await useApi(`/admin/academic/${currentResource.value}`, { method: 'POST', headers: headers.value, body })
      successMessage.value = 'Đã tạo bản ghi mới.'
    } else if (selectedRow.value?.id) {
      await useApi(`/admin/academic/${currentResource.value}/${selectedRow.value.id}`, { method: 'PUT', headers: headers.value, body })
      successMessage.value = 'Đã cập nhật bản ghi.'
    }
    modalOpen.value = false
    if (wasCreate) {
      searchTerm.value = ''
      statusFilter.value = 'all'
      await fetchRows(1)
    } else {
      await fetchRows(currentPage.value)
    }
  } catch (error: any) {
    const errors = error?.data?.errors
    if (errors && typeof errors === 'object') {
      const collected: Record<string, string> = {}
      Object.entries(errors).forEach(([key, value]) => {
        const arr = Array.isArray(value) ? value : [value]
        collected[key] = String(arr[0] ?? '')
      })
      fieldErrors.value = collected
      const firstMessages = Object.entries(collected).slice(0, 3).map(([k, v]) => `${labelForField(k)}: ${v}`)
      errorMessage.value = firstMessages.join(' • ')
        || error?.data?.message
        || 'Không thể lưu bản ghi.'
    } else {
      errorMessage.value = error?.data?.message || 'Không thể lưu bản ghi.'
    }
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (props.readonly || !selectedRow.value?.id) return
  deleting.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await useApi(`/admin/academic/${currentResource.value}/${selectedRow.value.id}`, { method: 'DELETE', headers: headers.value })
    successMessage.value = 'Đã xóa bản ghi.'
    deleteModalOpen.value = false
    await fetchRows(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa bản ghi.'
  } finally {
    deleting.value = false
  }
}

async function openEnrollModal(cohort: Record<string, any>) {
  enrollCohort.value = cohort
  enrollResult.value = null
  enrollForm.term_id = ''
  enrollForm.curriculum_id = ''
  enrollModalOpen.value = true

  // Make sure term + curriculum lookups are populated even if user lands
  // straight on this modal from the cohorts tab.
  await Promise.all([
    lookupOptions.value['term_id']?.length ? Promise.resolve() : loadResourceLookup('term_id', 'terms'),
    lookupOptions.value['curriculum_id']?.length ? Promise.resolve() : loadResourceLookup('curriculum_id', 'curricula'),
  ])

  const currentTerm = (lookupOptions.value['term_id'] || [])[0]
  if (currentTerm) enrollForm.term_id = currentTerm.value
}

async function loadResourceLookup(field: string, resource: ResourceKey) {
  try {
    const response = await useApi<PaginatedResponse>(`/admin/academic/${resource}?per_page=200`, {
      headers: headers.value,
    })
    lookupOptions.value[field] = (response.data || []).map((item: any) => ({
      value: item.id,
      label: item.name || item.code || `#${item.id}`,
    }))
  } catch {
    lookupOptions.value[field] = []
  }
}

async function submitEnroll() {
  if (props.readonly || !enrollCohort.value?.id || !enrollForm.term_id) return
  enrolling.value = true
  errorMessage.value = ''
  successMessage.value = ''
  enrollResult.value = null
  try {
    const body: Record<string, any> = { term_id: Number(enrollForm.term_id) }
    if (enrollForm.curriculum_id) body.curriculum_id = Number(enrollForm.curriculum_id)

    const result = await useApi<{
      message?: string
      students?: number
      courses?: number
      created?: number
      skipped?: number
    }>(`/admin/academic/cohorts/${enrollCohort.value.id}/enroll-core`, {
      method: 'POST',
      headers: headers.value,
      body,
    })
    enrollResult.value = result
    successMessage.value = `Đã ghi danh ${result.created ?? 0} bản ghi mới (bỏ qua ${result.skipped ?? 0}).`
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể ghi danh hàng loạt.'
  } finally {
    enrolling.value = false
  }
}

function formatRelative(date: Date | null) {
  if (!date) return '—'
  const diff = Date.now() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return 'vừa xong'
  if (minutes < 60) return `${minutes} phút trước`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} giờ trước`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days} ngày trước`
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
}

watch(currentResource, async () => {
  searchTerm.value = ''
  statusFilter.value = 'all'
  await fetchRows(1)
  await loadLookupOptions()
})

onMounted(async () => {
  await fetchRows()
  await loadLookupOptions()
})
</script>

<template>
  <div class="academic-manager">
    <section v-if="allowResourceSwitch" class="dashboard-card academic-tabs-card">
      <div class="academic-tabs-head">
        <div>
          <p class="section-kicker">Loại bản ghi</p>
          <h3>Chọn nhóm dữ liệu để quản lý</h3>
        </div>
        <span class="academic-tabs-meta">{{ resourceOptions.length }} nhóm có sẵn</span>
      </div>
      <div class="academic-tabs">
        <button
          v-for="opt in resourceOptions"
          :key="opt.key"
          type="button"
          :class="['academic-tab', { 'is-active': currentResource === opt.key }]"
          @click="currentResource = opt.key"
        >
          <span class="material-symbols-outlined">{{ opt.icon }}</span>
          <span class="academic-tab-text">
            <strong>{{ opt.label }}</strong>
            <span>{{ opt.description }}</span>
          </span>
        </button>
      </div>
    </section>

    <section class="dashboard-card crud-panel academic-panel">
      <header class="academic-panel-head">
        <div class="academic-panel-title">
          <div class="academic-panel-icon">
            <span class="material-symbols-outlined">{{ currentOption.icon }}</span>
          </div>
          <div>
            <p class="section-kicker">Bản ghi hiện tại</p>
            <h3>{{ currentOption.label }}</h3>
            <p class="academic-panel-desc">{{ currentOption.description }}</p>
          </div>
        </div>
        <div class="academic-stats">
          <article>
            <span>Tổng số</span>
            <strong>{{ totalRows }}</strong>
          </article>
          <article>
            <span>Đang hoạt động</span>
            <strong>{{ activeCount }}</strong>
          </article>
          <article>
            <span>Cập nhật</span>
            <strong>{{ formatRelative(lastUpdatedAt) }}</strong>
          </article>
        </div>
      </header>

      <div class="crud-toolbar academic-toolbar">
        <div class="crud-toolbar-main">
          <div class="academic-search">
            <span class="material-symbols-outlined">search</span>
            <input
              v-model="searchTerm"
              type="text"
              :placeholder="`Tìm theo tên, mã hoặc ID trong ${currentOption.label.toLowerCase()}...`"
            >
          </div>
          <select v-model="statusFilter" class="crud-select academic-status-select">
            <option value="all">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="inactive">Ngừng hoạt động</option>
          </select>
          <button class="crud-secondary-btn academic-refresh" type="button" :disabled="loading" @click="fetchRows(currentPage)">
            <span class="material-symbols-outlined">refresh</span>
            <span>{{ loading ? 'Đang tải...' : 'Làm mới' }}</span>
          </button>
          <template v-if="isTreeView">
            <button class="crud-secondary-btn academic-tree-btn" type="button" @click="expandAllUnits">
              <span class="material-symbols-outlined">unfold_more</span>
              <span>Mở tất cả</span>
            </button>
            <button class="crud-secondary-btn academic-tree-btn" type="button" @click="collapseAllUnits">
              <span class="material-symbols-outlined">unfold_less</span>
              <span>Thu gọn</span>
            </button>
          </template>
        </div>
        <button v-if="!readonly" class="crud-primary-btn academic-add" type="button" @click="openCreate">
          <span class="material-symbols-outlined">add</span>
          <span>Thêm {{ currentOption.label.toLowerCase() }}</span>
        </button>
      </div>

      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <div class="crud-meta academic-meta">
        <p>
          <span>Hiển thị {{ isTreeView ? treeEntries.length : filteredRows.length }} / {{ totalRows }} bản ghi</span>
          <span v-if="searchTerm || statusFilter !== 'all'" class="academic-filter-tag">
            <span class="material-symbols-outlined">filter_alt</span>
            Có bộ lọc đang áp dụng
          </span>
          <span v-if="isTreeView" class="academic-tree-tag">
            <span class="material-symbols-outlined">account_tree</span>
            Cây đơn vị
          </span>
        </p>
        <p v-if="readonly" class="academic-readonly-tag">
          <span class="material-symbols-outlined">lock</span>
          Chế độ chỉ xem
        </p>
      </div>

      <div class="crud-table-wrap academic-table-wrap">
        <table class="crud-table academic-table">
          <thead>
            <tr>
              <th style="width: 64px">STT</th>
              <th v-for="col in currentOption.columns" :key="col.key">{{ col.label }}</th>
              <th v-if="!readonly" style="width: 120px; text-align: right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="readonly ? currentOption.columns.length + 1 : currentOption.columns.length + 2" class="crud-empty">
                <div class="academic-empty">
                  <div class="academic-spinner" />
                  <span>Đang tải dữ liệu {{ currentOption.label.toLowerCase() }}...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="isTreeView ? treeEntries.length === 0 : filteredRows.length === 0">
              <td :colspan="readonly ? currentOption.columns.length + 1 : currentOption.columns.length + 2" class="crud-empty">
                <div class="academic-empty">
                  <span class="material-symbols-outlined">inbox</span>
                  <strong>Không có bản ghi phù hợp</strong>
                  <span>{{ searchTerm || statusFilter !== 'all' ? 'Hãy thử bỏ bộ lọc hoặc tìm bằng từ khoá khác.' : 'Hiện chưa có dữ liệu nào trong nhóm này.' }}</span>
                </div>
              </td>
            </tr>
            <template v-else-if="isTreeView">
              <tr v-for="(entry, index) in treeEntries" :key="entry.row.id" class="academic-tree-row">
                <td>
                  <span class="academic-stt">{{ index + 1 }}</span>
                  <span class="academic-id" :title="`ID: ${entry.row.id}`">#{{ entry.row.id }}</span>
                </td>
                <td v-for="col in currentOption.columns" :key="`${entry.row.id}-${col.key}`">
                  <template v-if="col.key === 'name'">
                    <div class="academic-tree-cell" :style="{ paddingLeft: `${entry.depth * 22}px` }">
                      <button
                        v-if="entry.hasChildren"
                        type="button"
                        class="academic-tree-toggle"
                        :title="expandedIds.has(Number(entry.row.id)) ? 'Thu gọn' : 'Mở rộng'"
                        @click="toggleExpand(Number(entry.row.id))"
                      >
                        <span class="material-symbols-outlined">{{ expandedIds.has(Number(entry.row.id)) || searchTerm ? 'expand_more' : 'chevron_right' }}</span>
                      </button>
                      <span v-else class="academic-tree-spacer" />
                      <span class="material-symbols-outlined academic-tree-icon">{{ entry.hasChildren ? 'folder' : 'apartment' }}</span>
                      <div class="academic-cell-name">
                        <strong>{{ entry.row.name || '--' }}</strong>
                        <span v-if="entry.row.code" class="academic-cell-code">{{ entry.row.code }}</span>
                      </div>
                    </div>
                  </template>
                  <template v-else-if="col.key === 'status'">
                    <span class="crud-badge academic-status" :class="statusTone(entry.row.status)">
                      {{ normalizeValue(entry.row.status) }}
                    </span>
                  </template>
                  <template v-else-if="col.key === 'is_active'">
                    <span class="crud-badge academic-status" :class="entry.row.is_active === false ? 'is-muted' : 'is-success'">
                      {{ entry.row.is_active === false ? 'Ngừng' : 'Hoạt động' }}
                    </span>
                  </template>
                  <template v-else-if="col.key === 'description'">
                    <span class="academic-cell-truncate" :title="entry.row.description || ''">{{ entry.row[col.key] || '--' }}</span>
                  </template>
                  <template v-else>
                    {{ getCellDisplay(entry.row, col.key) }}
                  </template>
                </td>
                <td v-if="!readonly" style="text-align: right">
                  <div class="academic-row-actions">
                    <button class="action-btn is-edit" type="button" title="Sửa" @click="openEdit(entry.row)">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="action-btn is-delete" type="button" title="Xóa" @click="askDelete(entry.row)">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-else v-for="(row, index) in filteredRows" :key="row.id">
              <td>
                <span class="academic-stt">{{ (currentPage - 1) * PER_PAGE + index + 1 }}</span>
                <span class="academic-id" :title="`ID: ${row.id}`">#{{ row.id }}</span>
              </td>
              <td v-for="col in currentOption.columns" :key="`${row.id}-${col.key}`">
                <template v-if="col.key === 'name'">
                  <div class="academic-cell-name">
                    <strong>{{ row.name || '--' }}</strong>
                    <span v-if="row.code" class="academic-cell-code">{{ row.code }}</span>
                  </div>
                </template>
                <template v-else-if="col.key === 'status'">
                  <span class="crud-badge academic-status" :class="statusTone(row.status)">
                    {{ normalizeValue(row.status) }}
                  </span>
                </template>
                <template v-else-if="col.key === 'is_active'">
                  <span class="crud-badge academic-status" :class="row.is_active === false ? 'is-muted' : 'is-success'">
                    {{ row.is_active === false ? 'Ngừng' : 'Hoạt động' }}
                  </span>
                </template>
                <template v-else-if="col.key === 'description'">
                  <span class="academic-cell-truncate" :title="row.description || ''">{{ row[col.key] || '--' }}</span>
                </template>
                <template v-else>
                  {{ getCellDisplay(row, col.key) }}
                </template>
              </td>
              <td v-if="!readonly" style="text-align: right">
                <div class="academic-row-actions">
                  <button
                    v-if="currentResource === 'cohorts'"
                    class="action-btn is-enroll"
                    type="button"
                    title="Ghi danh khóa core cho cohort"
                    @click="openEnrollModal(row)"
                  >
                    <span class="material-symbols-outlined">how_to_reg</span>
                  </button>
                  <button class="action-btn is-edit" type="button" title="Sửa" @click="openEdit(row)">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button class="action-btn is-delete" type="button" title="Xóa" @click="askDelete(row)">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!isTreeView" class="crud-pagination">
        <p>Trang <strong>{{ currentPage }}</strong> / {{ lastPage }}</p>
        <div class="crud-pagination-actions">
          <button class="crud-secondary-btn" type="button" :disabled="currentPage <= 1 || loading" @click="fetchRows(currentPage - 1)">
            <span class="material-symbols-outlined">chevron_left</span>
            <span>Trước</span>
          </button>
          <button class="crud-secondary-btn" type="button" :disabled="currentPage >= lastPage || loading" @click="fetchRows(currentPage + 1)">
            <span>Sau</span>
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="modalOpen && !readonly" class="crud-modal-backdrop" @click.self="modalOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ mode === 'create' ? 'Tạo mới' : 'Chỉnh sửa' }}</p>
              <h3>{{ mode === 'create' ? `Thêm ${currentOption.label.toLowerCase()}` : `Cập nhật ${currentOption.label.toLowerCase()}` }}</h3>
              <p>{{ currentOption.description }}</p>
            </div>
            <button class="topbar-ghost" type="button" @click="modalOpen = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div v-if="errorMessage" class="crud-alert is-error academic-modal-error">{{ errorMessage }}</div>

          <div class="crud-form-grid">
            <label
              v-for="field in formSchema"
              :key="field"
              class="crud-field"
              :class="{ 'crud-field-full': field === 'description', 'has-error': fieldErrors[field] }"
            >
              <span>
                {{ labelForField(field) }}
                <em v-if="isRequired(field)" class="academic-required">*</em>
              </span>
              <select v-if="field.endsWith('_id')" v-model="form[field]">
                <option value="">-- Chọn --</option>
                <option v-for="opt in lookupOptions[field] || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <input v-else-if="field.includes('date') || field.startsWith('effective_')" v-model="form[field]" type="date">
              <select v-else-if="field.startsWith('is_')" v-model="form[field]">
                <option :value="true">Có</option>
                <option :value="false">Không</option>
              </select>
              <select v-else-if="field === 'status'" v-model="form[field]">
                <option value="">-- Chọn --</option>
                <option value="active">Đang hoạt động</option>
                <option value="inactive">Ngừng hoạt động</option>
                <option value="planned">Dự kiến</option>
                <option value="ongoing">Đang diễn ra</option>
                <option value="completed">Đã kết thúc</option>
                <option value="closed">Đã đóng</option>
                <option value="open">Đang mở</option>
                <option value="draft">Nháp</option>
                <option value="archived">Lưu trữ</option>
              </select>
              <select v-else-if="field === 'level' && currentResource === 'plos'" v-model="form[field]">
                <option value="">-- Chọn mức --</option>
                <option value="knowledge">Knowledge — Kiến thức</option>
                <option value="skill">Skill — Kỹ năng</option>
                <option value="attitude">Attitude — Thái độ</option>
              </select>
              <input v-else-if="field.includes('year') || field.includes('duration') || field === 'level' || field === 'capacity' || field === 'position'" v-model.number="form[field]" type="number" min="0">
              <textarea v-else-if="field === 'description' && (currentResource === 'plos' || currentResource === 'clos')" v-model="form[field]" rows="3" :placeholder="`Nhập ${labelForField(field).toLowerCase()}`" class="crud-textarea"></textarea>
              <input v-else v-model="form[field]" type="text" :placeholder="`Nhập ${labelForField(field).toLowerCase()}`">
              <small v-if="fieldErrors[field]" class="academic-field-error">{{ fieldErrors[field] }}</small>
              <small v-else-if="field.endsWith('_id') && !(lookupOptions[field] || []).length" class="academic-field-hint">
                Chưa có dữ liệu trong nhóm liên quan. Hãy tạo trước.
              </small>
            </label>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="modalOpen = false">Đóng</button>
            <button class="crud-primary-btn" type="button" :disabled="saving" @click="saveRow">
              <span v-if="saving" class="academic-spinner is-sm" />
              <span>{{ saving ? 'Đang lưu...' : mode === 'create' ? 'Tạo mới' : 'Lưu thay đổi' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <CrudConfirmModal
      v-if="!readonly"
      :open="deleteModalOpen"
      title="Xóa bản ghi"
      :description="`Bạn chắc chắn muốn xóa ${currentOption.label.toLowerCase()} #${selectedRow?.id || ''} (${selectedRow?.name || selectedRow?.code || ''})?`"
      confirm-text="Xóa"
      tone="danger"
      :loading="deleting"
      @close="deleteModalOpen = false"
      @confirm="confirmDelete"
    />

    <Teleport to="body">
      <div v-if="enrollModalOpen && !readonly" class="crud-modal-backdrop" @click.self="enrollModalOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Ghi danh khóa chính quy</p>
              <h3>Bulk-enroll: {{ enrollCohort?.name }}</h3>
              <p>
                Tự động ghi danh sinh viên trong cohort
                <strong>{{ enrollCohort?.code }}</strong>
                vào tất cả core course thuộc CTĐT.
              </p>
            </div>
            <button class="topbar-ghost" type="button" @click="enrollModalOpen = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="crud-form-grid">
            <label class="crud-field">
              <span>
                Kỳ học <em class="academic-required">*</em>
              </span>
              <select v-model="enrollForm.term_id">
                <option value="">-- Chọn kỳ --</option>
                <option v-for="opt in lookupOptions['term_id'] || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Khung CT (tùy chọn)</span>
              <select v-model="enrollForm.curriculum_id">
                <option value="">-- Tất cả core course phù hợp --</option>
                <option v-for="opt in lookupOptions['curriculum_id'] || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </label>
          </div>

          <div v-if="enrollResult" class="crud-alert is-success academic-modal-success">
            <p><strong>Kết quả:</strong> {{ enrollResult.message }}</p>
            <ul class="academic-enroll-stats">
              <li>Sinh viên trong cohort: <strong>{{ enrollResult.students ?? 0 }}</strong></li>
              <li>Core course phù hợp: <strong>{{ enrollResult.courses ?? 0 }}</strong></li>
              <li>Bản ghi mới: <strong>{{ enrollResult.created ?? 0 }}</strong></li>
              <li>Bỏ qua (đã có): <strong>{{ enrollResult.skipped ?? 0 }}</strong></li>
            </ul>
          </div>

          <div v-if="errorMessage" class="crud-alert is-error academic-modal-error">{{ errorMessage }}</div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="enrollModalOpen = false">Đóng</button>
            <button
              class="crud-primary-btn"
              type="button"
              :disabled="enrolling || !enrollForm.term_id"
              @click="submitEnroll"
            >
              <span v-if="enrolling" class="academic-spinner is-sm" />
              <span>{{ enrolling ? 'Đang ghi danh...' : 'Ghi danh' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.academic-manager {
  display: grid;
  gap: 18px;
}

.academic-tabs-card { display: grid; gap: 14px; }
.academic-tabs-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}
.academic-tabs-head h3 { margin: 0; font-size: 1.25rem; letter-spacing: -0.02em; }
.academic-tabs-meta {
  font-size: 0.82rem;
  color: var(--muted);
  font-weight: 700;
  background: rgba(var(--green-rgb), 0.08);
  padding: 6px 12px;
  border-radius: 999px;
}

.academic-tabs {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}
.academic-tab {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 18px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: #fff;
  text-align: left;
  cursor: pointer;
  transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}
.academic-tab:hover {
  transform: translateY(-1px);
  border-color: rgba(var(--green-rgb), 0.32);
}
.academic-tab.is-active {
  background: rgba(var(--green-rgb), 0.08);
  border-color: rgba(var(--green-rgb), 0.4);
  box-shadow: 0 12px 24px -18px rgba(var(--green-rgb), 0.55);
}
.academic-tab .material-symbols-outlined {
  font-size: 22px;
  color: var(--green-deep);
  background: rgba(var(--green-rgb), 0.12);
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.academic-tab-text {
  display: grid;
  gap: 2px;
  min-width: 0;
}
.academic-tab-text strong { font-size: 0.95rem; }
.academic-tab-text > span {
  color: var(--muted);
  font-size: 0.78rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.academic-panel { display: grid; gap: 18px; }

.academic-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding-bottom: 18px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.06);
  flex-wrap: wrap;
}
.academic-panel-title {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}
.academic-panel-icon {
  width: 56px;
  height: 56px;
  border-radius: 18px;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.academic-panel-icon .material-symbols-outlined { font-size: 28px; }
.academic-panel-title h3 { margin: 0; font-size: 1.4rem; letter-spacing: -0.02em; }
.academic-panel-desc { margin: 2px 0 0; color: var(--muted); font-size: 0.9rem; }

.academic-stats {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.academic-stats article {
  min-width: 130px;
  padding: 12px 16px;
  border-radius: 16px;
  background: rgba(17, 17, 17, 0.03);
  display: grid;
  gap: 4px;
}
.academic-stats article span { color: var(--muted); font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
.academic-stats article strong { font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em; }

.academic-toolbar { gap: 12px; }
.academic-search {
  position: relative;
  flex: 1;
  min-width: 280px;
}
.academic-search .material-symbols-outlined {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 20px;
  pointer-events: none;
}
.academic-search input {
  width: 100%;
  min-height: 52px;
  padding: 0 16px 0 48px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.92);
  outline: none;
  transition: border-color 180ms ease, box-shadow 180ms ease;
}
.academic-search input:focus {
  border-color: rgba(var(--green-rgb), 0.4);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.1);
}

.academic-status-select { min-width: 200px; }

.academic-refresh,
.academic-add {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.academic-refresh .material-symbols-outlined,
.academic-add .material-symbols-outlined { font-size: 18px; }

.academic-meta { font-size: 0.92rem; }
.academic-meta p {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.academic-filter-tag,
.academic-readonly-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}
.academic-filter-tag {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
}
.academic-filter-tag .material-symbols-outlined { font-size: 16px; }
.academic-readonly-tag {
  background: rgba(17, 17, 17, 0.06);
  color: var(--muted);
}
.academic-readonly-tag .material-symbols-outlined { font-size: 16px; }

.academic-table { min-width: 720px; }
.academic-table th { font-size: 0.86rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
.academic-stt {
  display: inline-block;
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text);
}
.academic-id {
  display: block;
  font-family: 'JetBrains Mono', ui-monospace, monospace;
  font-size: 0.72rem;
  color: var(--muted);
  margin-top: 2px;
}

.academic-cell-name { display: grid; gap: 2px; }
.academic-cell-name strong { font-weight: 700; }
.academic-cell-code {
  font-size: 0.78rem;
  color: var(--muted);
  font-family: 'JetBrains Mono', ui-monospace, monospace;
}
.academic-cell-truncate {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  max-width: 320px;
  color: var(--muted);
  font-size: 0.9rem;
}

.academic-status { font-size: 0.74rem; min-height: 26px; padding: 0 10px; text-transform: none; }
.academic-status.is-success { background: rgba(var(--green-rgb), 0.12); color: var(--green-deep); }
.academic-status.is-warning { background: rgba(186, 132, 59, 0.14); color: #9a6117; }
.academic-status.is-muted { background: rgba(17, 17, 17, 0.06); color: var(--muted); }

/* Tree view (units) */
.academic-tree-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep);
}
.academic-tree-tag .material-symbols-outlined { font-size: 16px; }

.academic-tree-btn { white-space: nowrap; }
.academic-tree-btn .material-symbols-outlined { font-size: 18px; }

.academic-tree-row td { vertical-align: middle; }

.academic-tree-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.academic-tree-toggle {
  width: 24px;
  height: 24px;
  padding: 0;
  display: grid;
  place-items: center;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--muted);
  cursor: pointer;
  transition: background-color 120ms ease, color 120ms ease;
  flex-shrink: 0;
}
.academic-tree-toggle:hover {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
}
.academic-tree-toggle .material-symbols-outlined { font-size: 20px; }

.academic-tree-spacer {
  width: 24px;
  height: 24px;
  display: inline-block;
  flex-shrink: 0;
}

.academic-tree-icon {
  font-size: 20px;
  color: var(--green-deep);
  flex-shrink: 0;
}

.academic-row-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  justify-content: flex-end;
}
.academic-row-actions .action-btn {
  width: 36px;
  height: 36px;
  padding: 0;
  display: grid;
  place-items: center;
  min-height: 0;
}
.academic-row-actions .action-btn .material-symbols-outlined { font-size: 20px; }

.academic-row-actions .action-btn.is-enroll {
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  border: 1px solid rgba(var(--green-rgb), 0.25);
  border-radius: 10px;
  cursor: pointer;
  transition: background-color 140ms ease, transform 140ms ease;
}
.academic-row-actions .action-btn.is-enroll:hover {
  background: rgba(var(--green-rgb), 0.2);
  transform: translateY(-1px);
}

.academic-modal-success { margin-bottom: 16px; }
.academic-enroll-stats {
  margin: 8px 0 0;
  padding-left: 18px;
  font-size: 0.88rem;
  display: grid;
  gap: 4px;
}
.academic-enroll-stats strong { color: var(--green-deep); }

.academic-empty {
  display: grid;
  place-items: center;
  gap: 8px;
  padding: 28px 20px;
  color: var(--muted);
}
.academic-empty .material-symbols-outlined {
  font-size: 32px;
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: rgba(17, 17, 17, 0.04);
  color: var(--muted);
  display: grid;
  place-items: center;
}
.academic-empty strong { color: var(--text); font-size: 1rem; }

.academic-spinner {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 3px solid rgba(var(--green-rgb), 0.18);
  border-top-color: var(--green-deep);
  animation: academic-spin 0.8s linear infinite;
}
.academic-spinner.is-sm { width: 14px; height: 14px; border-width: 2px; }
@keyframes academic-spin { to { transform: rotate(360deg); } }

.academic-modal-error {
  margin-bottom: 16px;
}

.academic-required {
  color: var(--danger);
  font-style: normal;
  font-weight: 700;
  margin-left: 4px;
}

.academic-field-error {
  color: var(--danger);
  font-size: 0.78rem;
  font-weight: 600;
  margin-top: 2px;
}

.academic-field-hint {
  color: var(--muted);
  font-size: 0.78rem;
  margin-top: 2px;
  font-style: italic;
}

.crud-field.has-error select,
.crud-field.has-error input {
  border-color: rgba(174, 61, 55, 0.5);
  box-shadow: 0 0 0 3px rgba(174, 61, 55, 0.08);
}

.crud-pagination p strong { color: var(--text); }
.crud-pagination .crud-secondary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.crud-pagination .material-symbols-outlined { font-size: 18px; }

@media (max-width: 880px) {
  .academic-panel-head { flex-direction: column; align-items: stretch; }
  .academic-stats { width: 100%; }
  .academic-stats article { flex: 1; }
  .academic-toolbar .crud-toolbar-main { width: 100%; }
  .academic-search { min-width: 0; width: 100%; }
}

@media (max-width: 600px) {
  .academic-tabs { grid-template-columns: 1fr; }
  .academic-stats { flex-direction: column; }
  .academic-stats article { width: 100%; }
}
</style>
