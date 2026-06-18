<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { LayoutDashboard } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
  adminSearchPlaceholder: 'Tìm CTĐT, khóa, lớp hành chính, lớp tín chỉ...',
})

type Id = number

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

interface TermItem {
  id: Id
  name: string
  code: string
  academic_year_id: Id
  is_current?: boolean
  status?: string
}

interface ProgramItem {
  id: Id
  name: string
  code: string
  institution_id?: Id
  unit_id?: Id | null
}

interface MajorItem {
  id: Id
  name: string
  code: string
  program_id: Id
  unit_id?: Id | null
}

interface CurriculumItem {
  id: Id
  name: string
  code: string
  program_id: Id
  major_id?: Id | null
  curriculum_courses_count?: number
}

interface CohortItem {
  id: Id
  name: string
  code: string
  start_year: number
  program_id: Id
  major_id?: Id | null
  institution_id?: Id
}

interface UnitItem {
  id: Id
  name: string
  code: string
  institution_id: Id
}

interface CurriculumCourseItem {
  id: Id
  curriculum_id: Id
  course_id: Id
  term_number: number
  is_required: boolean
  credits?: number | null
  position?: number
  course?: {
    id: Id
    title: string
    slug?: string
    credit_value?: number | null
    course_mode?: string
  }
}

interface CurriculumCoursesResponse {
  curriculum: CurriculumItem & {
    program?: { id: Id; code: string; name: string }
    major?: { id: Id; code: string; name: string } | null
  }
  by_term: Record<string, CurriculumCourseItem[]>
  summary?: {
    total_subjects: number
    required_subjects: number
    elective_subjects: number
    term_count?: number
  }
}

interface AdministrativeClassItem {
  id: Id
  code: string
  name: string
  cohort_id?: Id | null
  program_id: Id
  unit_id?: Id | null
  major_id?: Id | null
  status?: string
}

interface ClassSectionItem {
  id: Id
  code: string
  name?: string | null
  course_id: Id
  term_id?: Id | null
  cohort_id?: Id | null
  lecturer_id?: Id | null
  capacity?: number
  status?: string
  course?: { id: Id; title: string }
  term?: { id: Id; name: string; code: string }
}

const user = useAuthUserCookie()
const token = useAuthTokenCookie()

if (!user.value || !token.value) await navigateTo('/login', { replace: true })
if (user.value && normalizeRole(user.value.role) !== 'admin') await navigateTo(getDashboardPath(user.value.role), { replace: true })

function headers() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const terms = ref<TermItem[]>([])
const programs = ref<ProgramItem[]>([])
const majors = ref<MajorItem[]>([])
const curricula = ref<CurriculumItem[]>([])
const cohorts = ref<CohortItem[]>([])
const units = ref<UnitItem[]>([])
const adminClasses = ref<AdministrativeClassItem[]>([])
const classSections = ref<ClassSectionItem[]>([])

const selectedTermId = ref<Id | ''>('')
const selectedProgramId = ref<Id | ''>('')
const selectedMajorId = ref<Id | ''>('')
const selectedCurriculumId = ref<Id | ''>('')
const selectedCohortId = ref<Id | ''>('')
const selectedAdminClassId = ref<Id | ''>('')
const selectedTermNumber = ref<number>(1)

const curriculumCourses = ref<CurriculumCoursesResponse | null>(null)

const byTerm = computed(() => curriculumCourses.value?.by_term || {})
const availableTermNumbers = computed(() => {
  const keys = Object.keys(byTerm.value).map(k => Number(k)).filter(n => Number.isFinite(n)).sort((a, b) => a - b)
  return keys.length ? keys : [1]
})
const selectedCoursesInTerm = computed(() => byTerm.value[String(selectedTermNumber.value)] || [])
const selectedCourseIds = computed(() => selectedCoursesInTerm.value.map(item => item.course_id))

/** Khi chương trình không có `unit_id`, bắt buộc chọn đơn vị để tạo lớp hành chính (API yêu cầu). */
const selectedFallbackUnitId = ref<Id | ''>('')

const selectionSummary = computed(() => ({
  term: terms.value.find(item => item.id === selectedTermId.value),
  program: programs.value.find(item => item.id === selectedProgramId.value),
  major: majors.value.find(item => item.id === selectedMajorId.value),
  curriculum: curricula.value.find(item => item.id === selectedCurriculumId.value),
  cohort: cohorts.value.find(item => item.id === selectedCohortId.value),
  adminClass: adminClasses.value.find(item => item.id === selectedAdminClassId.value),
}))

const unitsForSelectedInstitution = computed(() => {
  const inst =
    selectionSummary.value.cohort?.institution_id
    ?? selectionSummary.value.program?.institution_id
  if (!inst) return units.value
  return units.value.filter(u => u.institution_id === inst)
})

const effectiveAdminClassUnitId = computed((): Id | '' => {
  const p = selectionSummary.value.program
  if (p?.unit_id) return p.unit_id
  return selectedFallbackUnitId.value
})

const effectiveInstitutionIdForAdminClass = computed((): Id | '' => {
  return (
    selectionSummary.value.cohort?.institution_id
    ?? selectionSummary.value.program?.institution_id
    ?? ''
  )
})

const selectedCourseLabel = computed(() => {
  const item = selectedCoursesInTerm.value.find(course => course.course_id === createSectionCourseId.value)
  return item?.course?.title || (createSectionCourseId.value ? `Course #${createSectionCourseId.value}` : 'Chưa chọn môn')
})

const assignModalOpen = ref(false)
const assigning = ref(false)

const createSectionCourseId = ref<Id | ''>('')
const createSectionCode = ref('')
const creatingSection = ref(false)

const creatingAdminClass = ref(false)
const newAdminClass = ref({ code: '', name: '' })

const attaching = ref(false)
const selectedSectionIdsToAttach = ref<Id[]>([])

type TabId = 'context' | 'curriculum' | 'admin-classes' | 'class-sections'
const activeTab = ref<TabId>('context')

// Tránh watcher "nuốt" quá trình set mặc định khi mới mở trang.
const isBootstrapping = ref(true)

const tabItems = [
  { id: 'context' as const, label: '1. Bối cảnh', icon: 'trending-up' },
  { id: 'curriculum' as const, label: '2. CTĐT theo kỳ', icon: 'list' },
  { id: 'admin-classes' as const, label: '3. Lớp hành chính', icon: 'git-branch' },
  { id: 'class-sections' as const, label: '4. Lớp tín chỉ', icon: 'book-open' },
] as const satisfies ReadonlyArray<{ id: TabId; label: string; icon: string }>

async function fetchList<T>(resource: string, params: Record<string, string | number | boolean | undefined> = {}) {
  const query = new URLSearchParams()
  query.set('per_page', '200')
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === '' || v === null) continue
    query.set(k, String(v))
  }
  return await useApi<PaginatedResponse<T>>(`/admin/academic/${resource}?${query.toString()}`, { headers: headers() })
}

async function bootstrap() {
  isBootstrapping.value = true
  loading.value = true
  errorMessage.value = ''
  try {
    const [t, p, u] = await Promise.all([
      fetchList<TermItem>('terms'),
      fetchList<ProgramItem>('programs'),
      fetchList<UnitItem>('units'),
    ])
    terms.value = t.data
    programs.value = p.data
    units.value = u.data

    const current = terms.value.find(x => x.is_current) || terms.value[0]
    if (current) selectedTermId.value = current.id

    // Preview dữ liệu sẵn có ngay khi vào trang:
    // - chọn 1 program đầu tiên
    // - load majors/curricula/cohorts
    // - chọn curriculum/cohort đầu tiên
    // - load courses + admin classes + class sections
    if (programs.value.length) {
      selectedProgramId.value = programs.value[0]!.id

      await loadMajors()
      // Nếu chỉ có đúng 1 ngành thì auto-chọn để giảm thao tác.
      if (majors.value.length === 1) selectedMajorId.value = majors.value[0]!.id

      await Promise.all([loadCurricula(), loadCohorts()])

      if (curricula.value.length) selectedCurriculumId.value = curricula.value[0]!.id
      if (cohorts.value.length) selectedCohortId.value = cohorts.value[0]!.id

      await Promise.all([
        loadCurriculumCourses(),
        loadAdminClasses(),
        loadClassSections(),
      ])

      if (adminClasses.value.length) selectedAdminClassId.value = adminClasses.value[0]!.id
    }
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể tải dữ liệu học vụ.'
  } finally {
    loading.value = false
    isBootstrapping.value = false
  }
}

async function loadMajors() {
  majors.value = []
  selectedMajorId.value = ''
  if (!selectedProgramId.value) return
  const res = await fetchList<MajorItem>('majors')
  majors.value = res.data.filter(m => m.program_id === selectedProgramId.value)
}

async function loadCurricula() {
  curricula.value = []
  selectedCurriculumId.value = ''
  if (!selectedProgramId.value) return
  const res = await fetchList<CurriculumItem>('curricula', {
    program_id: selectedProgramId.value,
    major_id: selectedMajorId.value || undefined,
  })
  curricula.value = res.data
}

async function loadCohorts() {
  cohorts.value = []
  selectedCohortId.value = ''
  if (!selectedProgramId.value) return
  const res = await fetchList<CohortItem>('cohorts')
  cohorts.value = res.data.filter(c => c.program_id === selectedProgramId.value && (!selectedMajorId.value || c.major_id === selectedMajorId.value))
}

async function loadCurriculumCourses() {
  curriculumCourses.value = null
  if (!selectedCurriculumId.value) return
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await useApi<CurriculumCoursesResponse>(`/admin/academic/curricula/${selectedCurriculumId.value}/courses`, {
      headers: headers(),
    })
    curriculumCourses.value = res
    const numbers = Object.keys(res.by_term).map(k => Number(k)).filter(n => Number.isFinite(n)).sort((a, b) => a - b)
    selectedTermNumber.value = numbers[0] || 1
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể tải môn học theo CTĐT.'
  } finally {
    loading.value = false
  }
}

async function loadAdminClasses() {
  adminClasses.value = []
  selectedAdminClassId.value = ''
  if (!selectedCohortId.value) return
  try {
    const res = await fetchList<AdministrativeClassItem>('administrative-classes', { cohort_id: selectedCohortId.value })
    adminClasses.value = res.data
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể tải lớp hành chính.'
  }
}

async function loadClassSections() {
  classSections.value = []
  selectedSectionIdsToAttach.value = []
  if (!selectedTermId.value) return
  try {
    const query = new URLSearchParams()
    query.set('per_page', '50')
    query.set('term_id', String(selectedTermId.value))
    if (selectedCohortId.value) query.set('cohort_id', String(selectedCohortId.value))
    const res = await useApi<PaginatedResponse<ClassSectionItem>>(`/admin/academic/class-sections?${query.toString()}`, { headers: headers() })
    classSections.value = res.data
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể tải lớp tín chỉ.'
  }
}

function openAssignModal() {
  if (!selectedTermId.value || !selectedCurriculumId.value || !selectedCohortId.value) return
  assignModalOpen.value = true
}

async function assignCohortTermCourses() {
  if (!selectedTermId.value || !selectedCurriculumId.value || !selectedCohortId.value) return
  assigning.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const payload = {
      term_id: selectedTermId.value,
      curriculum_id: selectedCurriculumId.value,
      course_ids: selectedCourseIds.value,
    }
    await useApi(`/admin/academic/cohorts/${selectedCohortId.value}/enroll-core`, {
      method: 'POST',
      headers: headers(),
      body: payload,
    })
    successMessage.value = `Đã gán học phần kỳ ${selectedTermNumber.value} cho khóa.`
    assignModalOpen.value = false
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể gán học phần cho khóa.'
  } finally {
    assigning.value = false
  }
}

async function createAdministrativeClass() {
  if (!selectedCohortId.value || !selectedProgramId.value || !newAdminClass.value.code.trim() || !newAdminClass.value.name.trim()) return
  const institutionId = effectiveInstitutionIdForAdminClass.value
  const unitId = effectiveAdminClassUnitId.value
  if (!institutionId || !unitId) {
    errorMessage.value = 'Thiếu institution_id hoặc unit_id: hãy chọn chương trình/khóa hợp lệ; nếu CT không gắn đơn vị, chọn đơn vị trong danh sách.'
    return
  }
  creatingAdminClass.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const cohort = cohorts.value.find(c => c.id === selectedCohortId.value)
    await useApi('/admin/academic/administrative-classes', {
      method: 'POST',
      headers: headers(),
      body: {
        institution_id: institutionId,
        unit_id: unitId,
        program_id: selectedProgramId.value,
        major_id: selectedMajorId.value || null,
        cohort_id: selectedCohortId.value,
        advisor_id: null,
        code: newAdminClass.value.code.trim(),
        name: newAdminClass.value.name.trim(),
        status: 'active',
        capacity: null,
        expected_graduation_year: cohort ? cohort.start_year + 4 : null,
      },
    })
    newAdminClass.value.code = ''
    newAdminClass.value.name = ''
    await loadAdminClasses()
    successMessage.value = 'Đã tạo lớp hành chính.'
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể tạo lớp hành chính.'
  } finally {
    creatingAdminClass.value = false
  }
}

async function createClassSection() {
  if (!selectedTermId.value || !createSectionCourseId.value || !createSectionCode.value.trim()) return
  creatingSection.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await useApi('/admin/academic/class-sections', {
      method: 'POST',
      headers: headers(),
      body: {
        course_id: createSectionCourseId.value,
        term_id: selectedTermId.value,
        cohort_id: selectedCohortId.value || null,
        code: createSectionCode.value.trim(),
        status: 'planned',
        capacity: 0,
      },
    })
    createSectionCourseId.value = ''
    createSectionCode.value = ''
    await loadClassSections()
    successMessage.value = 'Đã tạo lớp tín chỉ.'
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể tạo lớp tín chỉ.'
  } finally {
    creatingSection.value = false
  }
}

async function attachSectionsToAdminClass() {
  if (!selectedAdminClassId.value || selectedSectionIdsToAttach.value.length === 0) return
  attaching.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await useApi(`/admin/academic/admin-classes/${selectedAdminClassId.value}/sections`, {
      method: 'POST',
      headers: headers(),
      body: {
        class_section_ids: selectedSectionIdsToAttach.value,
        term_number: selectedTermNumber.value,
      },
    })
    selectedSectionIdsToAttach.value = []
    successMessage.value = 'Đã gán lớp tín chỉ cho lớp hành chính.'
  } catch (e: any) {
    errorMessage.value = e?.data?.message || 'Không thể gán lớp tín chỉ.'
  } finally {
    attaching.value = false
  }
}

watch(selectedProgramId, async () => {
  if (isBootstrapping.value) return
  curriculumCourses.value = null
  selectedFallbackUnitId.value = ''
  await loadMajors()
  await Promise.all([loadCurricula(), loadCohorts()])
  const p = programs.value.find(x => x.id === selectedProgramId.value)
  const cand = p?.institution_id ? units.value.filter(u => u.institution_id === p.institution_id) : units.value
  if (p && !p.unit_id && cand.length === 1) selectedFallbackUnitId.value = cand[0]!.id
})

watch(selectedMajorId, async () => {
  if (isBootstrapping.value) return
  curriculumCourses.value = null
  await Promise.all([loadCurricula(), loadCohorts()])
})

watch(selectedCurriculumId, async () => {
  if (isBootstrapping.value) return
  await loadCurriculumCourses()
})

watch(selectedCohortId, async () => {
  if (isBootstrapping.value) return
  // Clear downstream data to avoid showing stale data when switching tabs.
  adminClasses.value = []
  classSections.value = []
  selectedAdminClassId.value = ''
  selectedSectionIdsToAttach.value = []

  // Luôn load admin classes để phần summary hiển thị dữ liệu sẵn có.
  if (selectedCohortId.value) await loadAdminClasses()
  if (adminClasses.value.length) selectedAdminClassId.value = adminClasses.value[0]!.id
  if (activeTab.value === 'class-sections') await loadClassSections()
})

watch(selectedTermId, async () => {
  if (isBootstrapping.value) return
  classSections.value = []
  selectedSectionIdsToAttach.value = []
  if (activeTab.value === 'class-sections') await loadClassSections()
})

watch(activeTab, async (tab) => {
  if (isBootstrapping.value) return
  if (tab === 'admin-classes') await loadAdminClasses()
  if (tab === 'class-sections') await loadClassSections()
})

onMounted(bootstrap)
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Tổ chức & Học vụ']"
    title="Tổ chức & Học vụ"
    description="Gán nhanh CTĐT, khóa và lớp để điều phối môn học theo học kỳ thực tế."
  >
    <section class="dashboard-card crud-panel academic-v2 academic-v2-hero">
      <div class="academic-v2-head academic-v2-head--hero">
        <div>
          <p class="section-kicker">Tổng quan</p>
          <h3>Tổ chức học vụ rõ ràng hơn, ít phải đoán hơn</h3>
          <p class="academic-v2-muted">
            Chọn học kỳ, CTĐT và khóa theo trình tự. Hệ thống sẽ hiển thị ngay các môn theo kỳ, lớp hành chính và lớp tín chỉ liên quan.
          </p>
        </div>
        <span class="academic-v2-chip">
          <LayoutDashboard :size="16" :stroke-width="1.75" />
          Quy trình 4 bước
        </span>
      </div>

      <div class="academic-v2-summary">
        <div class="academic-v2-summary-card">
          <span>Học kỳ</span>
          <strong>{{ selectionSummary.term ? `${selectionSummary.term.code} — ${selectionSummary.term.name}` : 'Chưa chọn' }}</strong>
        </div>
        <div class="academic-v2-summary-card">
          <span>CTĐT</span>
          <strong>{{ selectionSummary.curriculum ? `${selectionSummary.curriculum.code} — ${selectionSummary.curriculum.name}` : 'Chưa chọn' }}</strong>
        </div>
        <div class="academic-v2-summary-card">
          <span>Khóa</span>
          <strong>{{ selectionSummary.cohort ? `${selectionSummary.cohort.code} — ${selectionSummary.cohort.name}` : 'Chưa chọn' }}</strong>
        </div>
        <div class="academic-v2-summary-card">
          <span>Lớp hành chính</span>
          <strong>{{ selectionSummary.adminClass ? `${selectionSummary.adminClass.code} — ${selectionSummary.adminClass.name}` : 'Chưa chọn' }}</strong>
        </div>
      </div>

      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <div class="academic-v2-tabs-nav" role="tablist" aria-label="Tổ chức & Học vụ Tabs">
        <button
          v-for="t in tabItems"
          :key="t.id"
          type="button"
          class="academic-v2-tab-btn"
          :class="{ 'is-active': activeTab === t.id }"
          role="tab"
          :aria-selected="activeTab === t.id"
          @click="activeTab = t.id"
        >
          <SylvaIcon :name="t.icon" :size="18" />
          <span>{{ t.label }}</span>
        </button>
      </div>

      <div v-if="activeTab === 'context'" class="academic-v2-grid academic-v2-grid--adaptive">
        <label class="crud-field">
          <span>1. Học kỳ thực tế</span>
          <select v-model="selectedTermId">
            <option value="">-- Chọn học kỳ --</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.code }} — {{ t.name }} {{ t.is_current ? '(Hiện hành)' : '' }}
            </option>
          </select>
        </label>

        <label class="crud-field">
          <span>2. Chương trình</span>
          <select v-model="selectedProgramId">
            <option value="">-- Chọn chương trình --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} — {{ p.name }}</option>
          </select>
        </label>

        <label class="crud-field">
          <span>3. Ngành</span>
          <select v-model="selectedMajorId" :disabled="!selectedProgramId">
            <option value="">-- Tất cả ngành --</option>
            <option v-for="m in majors" :key="m.id" :value="m.id">{{ m.code }} — {{ m.name }}</option>
          </select>
        </label>

        <label class="crud-field">
          <span>4. CTĐT</span>
          <select v-model="selectedCurriculumId" :disabled="!selectedProgramId">
            <option value="">-- Chọn CTĐT --</option>
            <option v-for="c in curricula" :key="c.id" :value="c.id">
              {{ c.code }} — {{ c.name }} {{ typeof c.curriculum_courses_count === 'number' ? `(${c.curriculum_courses_count} môn)` : '' }}
            </option>
          </select>
        </label>

        <label class="crud-field">
          <span>5. Khóa</span>
          <select v-model="selectedCohortId" :disabled="!selectedProgramId">
            <option value="">-- Chọn khóa --</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
          </select>
        </label>

        <label class="crud-field">
          <span>Kỳ trong CTĐT</span>
          <select v-model.number="selectedTermNumber" :disabled="!selectedCurriculumId">
            <option v-for="n in availableTermNumbers" :key="n" :value="n">Kỳ {{ n }}</option>
          </select>
        </label>
      </div>
    </section>

    <section v-if="activeTab === 'curriculum'" class="dashboard-card crud-panel academic-v2">
      <div class="academic-v2-head">
        <div>
          <p class="section-kicker">CTĐT theo kỳ</p>
          <h3>Danh sách môn của kỳ {{ selectedTermNumber }}</h3>
          <p class="academic-v2-muted" v-if="selectedCurriculumId && curriculumCourses?.summary">
            {{ curriculumCourses.summary.total_subjects }} môn · {{ curriculumCourses.summary.required_subjects }} bắt buộc ·
            {{ curriculumCourses.summary.elective_subjects }} tự chọn
          </p>
        </div>
        <button
          class="crud-primary-btn"
          type="button"
          :disabled="!selectedTermId || !selectedCohortId || !selectedCurriculumId || selectedCourseIds.length === 0"
          @click="openAssignModal"
        >
          Gán môn cho khóa
        </button>
      </div>

      <div class="academic-v2-hint">
        <span class="academic-v2-hint-dot"></span>
        <p>Chọn đúng CTĐT và kỳ để tránh gán nhầm học phần. Bảng này phản ánh chính xác các môn sẽ được đẩy sang khóa đã chọn.</p>
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table academic-v2-table">
          <thead>
            <tr>
              <th style="width: 70px">Học kỳ</th>
              <th>Tên môn học</th>
              <th style="width: 140px">Số tín chỉ</th>
              <th style="width: 160px">Loại môn</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="4" class="crud-empty">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="!selectedCurriculumId">
              <td colspan="4" class="crud-empty">Chọn CTĐT để xem môn theo kỳ.</td>
            </tr>
            <tr v-else-if="selectedCoursesInTerm.length === 0">
              <td colspan="4" class="crud-empty">Kỳ này chưa có môn trong CTĐT.</td>
            </tr>
            <tr v-else v-for="item in selectedCoursesInTerm" :key="item.id">
              <td><span class="crud-badge">{{ item.term_number }}</span></td>
              <td>
                <strong>{{ item.course?.title || `Course #${item.course_id}` }}</strong>
                <p class="academic-v2-muted">ID: {{ item.course_id }}</p>
              </td>
              <td>{{ item.credits ?? item.course?.credit_value ?? '--' }}</td>
              <td>
                <span class="crud-badge" :class="item.is_required ? 'is-success' : 'is-muted'">
                  {{ item.is_required ? 'Bắt buộc' : 'Tự chọn' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="activeTab === 'admin-classes'" class="dashboard-card crud-panel academic-v2">
      <div class="academic-v2-head">
        <div>
          <p class="section-kicker">Lớp hành chính</p>
          <h3>Theo khóa đã chọn</h3>
          <p class="academic-v2-muted">Mỗi khóa có thể có nhiều lớp như CNTT01, CNTT02...</p>
        </div>
      </div>

      <div class="academic-v2-stack">
        <div class="academic-v2-grid academic-v2-grid--3">
          <label class="crud-field">
            <span>Chọn lớp hành chính</span>
            <select v-model="selectedAdminClassId" :disabled="!selectedCohortId">
              <option value="">-- Chọn lớp --</option>
              <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
            </select>
          </label>

          <label
            v-if="selectionSummary.program && !selectionSummary.program.unit_id"
            class="crud-field academic-v2-field-span"
          >
            <span>Đơn vị quản lý (bắt buộc nếu CT chưa gắn đơn vị)</span>
            <select v-model="selectedFallbackUnitId" :disabled="!selectedProgramId">
              <option value="">-- Chọn đơn vị --</option>
              <option v-for="u in unitsForSelectedInstitution" :key="u.id" :value="u.id">{{ u.code }} — {{ u.name }}</option>
            </select>
          </label>

          <label class="crud-field">
            <span>Mã lớp mới</span>
            <input v-model="newAdminClass.code" :disabled="!selectedCohortId || creatingAdminClass" placeholder="CNTT01">
          </label>

          <label class="crud-field">
            <span>Tên lớp mới</span>
            <input v-model="newAdminClass.name" :disabled="!selectedCohortId || creatingAdminClass" placeholder="D21 CNTT - Lớp 1">
          </label>
        </div>

        <div class="academic-v2-actions">
          <button class="crud-secondary-btn" type="button" :disabled="!selectedCohortId" @click="loadAdminClasses">Làm mới</button>
          <button
            class="crud-primary-btn"
            type="button"
            :disabled="
              !selectedCohortId
                || creatingAdminClass
                || !newAdminClass.code.trim()
                || !newAdminClass.name.trim()
                || !effectiveInstitutionIdForAdminClass
                || !effectiveAdminClassUnitId
            "
            @click="createAdministrativeClass"
          >
            {{ creatingAdminClass ? 'Đang tạo...' : 'Tạo lớp hành chính' }}
          </button>
        </div>
      </div>
    </section>

    <section v-if="activeTab === 'class-sections'" class="dashboard-card crud-panel academic-v2">
      <div class="academic-v2-head">
        <div>
          <p class="section-kicker">Lớp tín chỉ</p>
          <h3>Tạo và gán lớp tín chỉ theo CTĐT</h3>
          <p class="academic-v2-muted">Lớp tín chỉ là các lớp học phần được tạo từ môn trong CTĐT theo học kỳ thực tế.</p>
        </div>
      </div>

      <div class="academic-v2-stack">
        <div class="academic-v2-grid academic-v2-grid--3">
          <label class="crud-field">
            <span>Môn trong kỳ {{ selectedTermNumber }}</span>
            <select v-model="createSectionCourseId" :disabled="!selectedCurriculumId || selectedCoursesInTerm.length === 0">
              <option value="">-- Chọn môn --</option>
              <option v-for="item in selectedCoursesInTerm" :key="item.course_id" :value="item.course_id">
                {{ item.course?.title || `Course #${item.course_id}` }}
              </option>
            </select>
            <small class="academic-v2-muted">Đang chọn: {{ selectedCourseLabel }}</small>
          </label>
          <label class="crud-field">
            <span>Mã lớp tín chỉ</span>
            <input v-model="createSectionCode" placeholder="INT101-01" :disabled="!selectedTermId || creatingSection">
          </label>
          <div class="crud-field">
            <span>&nbsp;</span>
            <button
              class="crud-primary-btn"
              type="button"
              :disabled="!selectedTermId || !createSectionCourseId || !createSectionCode.trim() || creatingSection"
              @click="createClassSection"
            >
              {{ creatingSection ? 'Đang tạo...' : 'Tạo lớp tín chỉ' }}
            </button>
          </div>
        </div>

        <div class="academic-v2-actions">
          <button class="crud-secondary-btn" type="button" :disabled="!selectedTermId" @click="loadClassSections">Làm mới danh sách</button>
          <button
            class="crud-primary-btn"
            type="button"
            :disabled="!selectedAdminClassId || selectedSectionIdsToAttach.length === 0 || attaching"
            @click="attachSectionsToAdminClass"
          >
            {{ attaching ? 'Đang gán...' : `Gán vào lớp hành chính (kỳ ${selectedTermNumber})` }}
          </button>
        </div>

        <div class="crud-table-wrap">
          <table class="crud-table academic-v2-table">
            <thead>
              <tr>
                <th style="width: 48px"></th>
                <th style="width: 120px">Mã lớp học phần</th>
                <th>Tên môn học</th>
                <th style="width: 160px">Trạng thái lớp</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!selectedTermId">
                <td colspan="4" class="crud-empty">Chọn học kỳ để xem lớp tín chỉ.</td>
              </tr>
              <tr v-else-if="classSections.length === 0">
                <td colspan="4" class="crud-empty">Chưa có lớp tín chỉ nào trong học kỳ này.</td>
              </tr>
              <tr v-else v-for="s in classSections" :key="s.id">
                <td>
                  <input v-model="selectedSectionIdsToAttach" type="checkbox" :value="s.id" :disabled="!selectedAdminClassId">
                </td>
                <td><strong>{{ s.code }}</strong></td>
                <td>{{ s.course?.title || `Course #${s.course_id}` }}</td>
                <td>
                  <span class="crud-badge is-muted">{{ s.status || 'planned' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <CrudConfirmModal
      :open="assignModalOpen"
      title="Gán học phần cho khóa"
      :description="`Gán ${selectedCourseIds.length} môn của kỳ ${selectedTermNumber} (CTĐT) cho khóa đã chọn trong học kỳ thực tế?`"
      confirm-text="Gán môn"
      :loading="assigning"
      @close="assignModalOpen = false"
      @confirm="assignCohortTermCourses"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.academic-v2 { display: grid; gap: 14px; }
.academic-v2-tabs-nav {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.academic-v2-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 18px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: rgba(255, 255, 255, 0.92);
  cursor: pointer;
  transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}
.academic-v2-tab-btn:hover { transform: translateY(-1px); }
.academic-v2-tab-btn.is-active {
  border-color: rgba(var(--green-rgb), 0.35);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
  background: rgba(var(--green-rgb), 0.08);
}
.academic-v2-tab-btn svg { font-size: 18px; color: var(--green-deep); }
.academic-v2-hero {
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(circle at top right, rgba(var(--green-rgb), 0.12), transparent 28%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.96));
}
.academic-v2-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.academic-v2-head--hero { align-items: flex-start; }
.academic-v2-muted { margin: 6px 0 0; color: var(--muted); font-size: 0.9rem; }
.academic-v2-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  font-weight: 800;
  font-size: 0.8rem;
  white-space: nowrap;
}
.academic-v2-chip svg { font-size: 16px; }
.academic-v2-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
.academic-v2-summary-card {
  border: 1px solid rgba(var(--green-rgb), 0.12);
  background: rgba(255, 255, 255, 0.72);
  border-radius: 16px;
  padding: 14px;
  display: grid;
  gap: 6px;
}
.academic-v2-summary-card span { color: var(--muted); font-size: 0.82rem; }
.academic-v2-summary-card strong { font-size: 0.95rem; line-height: 1.4; }
.academic-v2-hint {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding: 12px 14px;
  border-radius: 14px;
  background: rgba(var(--green-rgb), 0.08);
}
.academic-v2-hint-dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: var(--green-deep);
  margin-top: 5px;
  flex: none;
}
.academic-v2-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.academic-v2-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.academic-v2-field-span { grid-column: 1 / -1; }
.academic-v2-grid--adaptive { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.academic-v2-stack { display: grid; gap: 14px; }
.academic-v2-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
.academic-v2-table strong { display: block; }
@media (max-width: 960px) {
  .academic-v2-grid, .academic-v2-grid--3, .academic-v2-grid--adaptive, .academic-v2-summary { grid-template-columns: 1fr; }
}
</style>
