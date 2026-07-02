<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import { 
  Users, 
  BookOpen, 
  Search, 
  Plus, 
  Check, 
  Play, 
  FileText,
  UserCheck,
  Building,
  GraduationCap,
  Trash2,
  Trash,
  Upload,
  Calendar,
  X,
  FileSpreadsheet,
  HelpCircle,
  AlertTriangle,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor'],
  adminSearchPlaceholder: 'Tìm lớp hành chính, sinh viên, môn học...',
})

type Id = number

interface CohortItem {
  id: Id
  name: string
  code: string
}

interface TermItem {
  id: Id
  name: string
  code: string
  is_current?: boolean
}

interface AdministrativeClassItem {
  id: Id
  code: string
  name: string
  cohort_id: Id
  program_id: Id
  curriculum_id?: Id | null
}

interface StudentItem {
  id: Id
  name: string
  email: string
  student_code: string
}

interface CourseItem {
  id: Id
  title: string
  price: number
  course_mode?: string
}

interface ClassSectionItem {
  id: Id
  code: string
  name?: string | null
  course_id: Id
}

interface EnrollmentItem {
  id: Id
  user_id: Id
  course_id: Id
  term_id?: Id | null
  cohort_id?: Id | null
  class_section_id?: Id | null
  enrollment_source: string
  enrolled_at: string
  user?: { name: string; student_code: string; email: string }
  course?: { title: string }
  term?: { name: string }
  class_section?: { code: string }
}

interface ExamItem {
  id: Id
  title: string
  type: string
}

interface ExamEnrollmentItem {
  id: Id
  exam_id: Id
  user_id: Id
  enrolled_at: string
  exam?: { title: string }
  user?: { name: string; student_code: string; email: string }
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

const token = useAuthTokenCookie()
const toast = useToast()

function headers() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

// Global Tabs
const activeTab = ref<'class-auto' | 'direct-manual' | 'enrollment-list' | 'exam-registrations'>('class-auto')
const loading = ref(false)
const processingEnrollment = ref(false)

// Master Lists
const terms = ref<TermItem[]>([])
const cohorts = ref<CohortItem[]>([])
const adminClasses = ref<AdministrativeClassItem[]>([])
const courses = ref<CourseItem[]>([])
const classSections = ref<ClassSectionItem[]>([])
const exams = ref<ExamItem[]>([])

// Selections
const selectedCohortId = ref<Id | ''>('')
const selectedClassId = ref<Id | ''>('')
const selectedTermId = ref<Id | ''>('')

// Tab 1: Auto Enrollment
const classStudents = ref<StudentItem[]>([])

// Tab 2: Direct Manual
const searchStudentQuery = ref('')
const searchedStudents = ref<StudentItem[]>([])
const selectedDirectCourseId = ref<Id | ''>('')
const selectedDirectSectionId = ref<Id | ''>('')
const selectedDirectUserIds = ref<Id[]>([])

// Tab 3: Enrollments List
const enrollments = ref<EnrollmentItem[]>([])
const enrollmentsPage = ref(1)
const enrollmentsTotalPages = ref(1)
const enrollmentsTotal = ref(0)
const enrollListSearchQuery = ref('')
const enrollListCourseId = ref<Id | ''>('')
const selectedEnrollmentIds = ref<Id[]>([])

// Tab 4: Exam Registrations List
const examEnrollments = ref<ExamEnrollmentItem[]>([])
const examEnrollmentsPage = ref(1)
const examEnrollmentsTotalPages = ref(1)
const examEnrollmentsTotal = ref(0)
const examListSearchQuery = ref('')
const examListExamId = ref<Id | ''>('')
const selectedExamEnrollmentIds = ref<Id[]>([])

// Bulk Delete via File Modal
const showBulkDeleteModal = ref(false)
const deleteFile = ref<File | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const deletePreviewData = ref<any>(null)
const deleteProcessing = ref(false)
const deleteStep = ref<1 | 2>(1)

const route = useRoute()

onMounted(async () => {
  if (route.query.tab && ['class-auto', 'direct-manual', 'enrollment-list', 'exam-registrations'].includes(route.query.tab as string)) {
    activeTab.value = route.query.tab as any
  }
  await bootstrapFilters()
  await loadCourses()
  await loadExams()
})

watch(() => route.query.tab, (newTab) => {
  if (newTab && ['class-auto', 'direct-manual', 'enrollment-list', 'exam-registrations'].includes(newTab as string)) {
    activeTab.value = newTab as any
  }
})

watch(selectedCohortId, async () => {
  await loadAdminClasses()
})

watch(selectedClassId, async () => {
  await loadClassStudents()
})

watch(selectedDirectCourseId, async () => {
  await loadClassSections()
})

// Watch tab shifts to load tables
watch(activeTab, async (newTab) => {
  if (newTab === 'enrollment-list') {
    enrollmentsPage.value = 1
    await loadEnrollments()
  } else if (newTab === 'exam-registrations') {
    examEnrollmentsPage.value = 1
    await loadExamEnrollments()
  }
})

async function bootstrapFilters() {
  loading.value = true
  try {
    const [tRes, cRes] = await Promise.all([
      useApi<{ data: TermItem[] }>('/admin/academic/terms?per_page=100', { headers: headers() }),
      useApi<{ data: CohortItem[] }>('/admin/academic/cohorts?per_page=100', { headers: headers() }),
    ])
    terms.value = tRes.data
    cohorts.value = cRes.data
    
    const currentTerm = terms.value.find(t => t.is_current) || terms.value[0]
    if (currentTerm) selectedTermId.value = currentTerm.id
    
    if (cohorts.value.length > 0) {
      selectedCohortId.value = cohorts.value[0].id
      await loadAdminClasses()
    }
  } catch (e) {
    toast.error('Không thể tải bộ lọc học vụ.')
  } finally {
    loading.value = false
  }
}

async function loadAdminClasses() {
  if (!selectedCohortId.value) return
  loading.value = true
  try {
    const res = await useApi<{ data: AdministrativeClassItem[] }>(
      `/admin/academic/administrative-classes?cohort_id=${selectedCohortId.value}&per_page=100`,
      { headers: headers() }
    )
    adminClasses.value = res.data
    if (adminClasses.value.length > 0) {
      selectedClassId.value = adminClasses.value[0].id
      await loadClassStudents()
    } else {
      selectedClassId.value = ''
      classStudents.value = []
    }
  } catch (e) {
    toast.error('Không thể tải lớp hành chính.')
  } finally {
    loading.value = false
  }
}

async function loadClassStudents() {
  if (!selectedClassId.value) return
  loading.value = true
  try {
    const res = await useApi<{ data: StudentItem[] }>(
      `/admin/users?administrative_class_id=${selectedClassId.value}&user_type=student&per_page=200`,
      { headers: headers() }
    )
    classStudents.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadCourses() {
  try {
    const res = await useApi<{ data: CourseItem[] }>('/courses?per_page=500', { headers: headers() })
    courses.value = res.data
  } catch (e) {
    console.error(e)
  }
}

async function loadExams() {
  try {
    const res = await useApi<{ data: ExamItem[] }>('/admin/academic/exams?per_page=200', { headers: headers() })
    exams.value = res.data
  } catch (e) {
    console.error(e)
  }
}

async function loadClassSections() {
  if (!selectedDirectCourseId.value) {
    classSections.value = []
    selectedDirectSectionId.value = ''
    return
  }
  try {
    const res = await useApi<{ data: ClassSectionItem[] }>(
      `/admin/academic/class-sections?course_id=${selectedDirectCourseId.value}&per_page=200`,
      { headers: headers() }
    )
    classSections.value = res.data
    selectedDirectSectionId.value = ''
  } catch (e) {
    console.error(e)
  }
}

// Search direct students
async function searchStudents() {
  if (!searchStudentQuery.value.trim()) {
    searchedStudents.value = []
    return
  }
  loading.value = true
  try {
    const res = await useApi<{ data: StudentItem[] }>(
      `/admin/users?search=${encodeURIComponent(searchStudentQuery.value.trim())}&user_type=student&per_page=30`,
      { headers: headers() }
    )
    searchedStudents.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function toggleDirectUser(userId: Id) {
  const index = selectedDirectUserIds.value.indexOf(userId)
  if (index > -1) {
    selectedDirectUserIds.value.splice(index, 1)
  } else {
    selectedDirectUserIds.value.push(userId)
  }
}

// Load Paginated Enrollments List
async function loadEnrollments() {
  loading.value = true
  try {
    let url = `/admin/academic/enrollments?page=${enrollmentsPage.value}&per_page=15`
    if (enrollListCourseId.value) url += `&course_id=${enrollListCourseId.value}`
    if (selectedTermId.value) url += `&term_id=${selectedTermId.value}`
    
    // search student
    if (enrollListSearchQuery.value.trim()) {
      url += `&search=${encodeURIComponent(enrollListSearchQuery.value.trim())}`
    }
    
    const res = await useApi<PaginatedResponse<EnrollmentItem>>(url, { headers: headers() })
    enrollments.value = res.data
    enrollmentsTotalPages.value = res.last_page
    enrollmentsTotal.value = res.total
    selectedEnrollmentIds.value = []
  } catch (e) {
    toast.error('Không thể tải danh sách ghi danh.')
  } finally {
    loading.value = false
  }
}

// Load Paginated Exam Registrations List
async function loadExamEnrollments() {
  loading.value = true
  try {
    let url = `/admin/academic/exam-enrollments?page=${examEnrollmentsPage.value}&per_page=15`
    if (examListExamId.value) url += `&exam_id=${examListExamId.value}`
    
    const res = await useApi<PaginatedResponse<ExamEnrollmentItem>>(url, { headers: headers() })
    examEnrollments.value = res.data
    examEnrollmentsTotalPages.value = res.last_page
    examEnrollmentsTotal.value = res.total
    selectedExamEnrollmentIds.value = []
  } catch (e) {
    toast.error('Không thể tải danh sách đăng ký thi.')
  } finally {
    loading.value = false
  }
}

// Auto Enroll trigger
async function runAutoEnrollment() {
  if (!selectedCohortId.value || !selectedTermId.value) return
  const currentClass = adminClasses.value.find(c => c.id === selectedClassId.value)
  if (!currentClass || !currentClass.curriculum_id) {
    toast.error('Lớp hành chính chưa gán lộ trình đào tạo.')
    return
  }

  processingEnrollment.value = true
  try {
    const payload = {
      term_id: selectedTermId.value,
      curriculum_id: currentClass.curriculum_id,
    }
    const res = await useApi<{ created: number; skipped: number }>(
      `/admin/academic/cohorts/${selectedCohortId.value}/enroll-core`,
      {
        method: 'POST',
        headers: headers(),
        body: payload
      }
    )
    toast.success(`Đã tự động ghi danh! Đăng ký mới: ${res.created}, Đã có: ${res.skipped}`)
    await loadClassStudents()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tự động ghi danh.')
  } finally {
    processingEnrollment.value = false
  }
}

// Direct Manual Enroll trigger
async function runDirectManualEnrollment() {
  if (!selectedDirectCourseId.value || selectedDirectUserIds.value.length === 0) {
    toast.error('Chọn môn học và ít nhất 1 sinh viên.')
    return
  }
  
  processingEnrollment.value = true
  try {
    const payload = {
      course_id: selectedDirectCourseId.value,
      class_section_id: selectedDirectSectionId.value ? Number(selectedDirectSectionId.value) : null,
      user_ids: selectedDirectUserIds.value,
      term_id: selectedTermId.value || null
    }

    const res = await useApi<{ created: number; skipped: number }>(
      '/admin/academic/enrollments/manual',
      {
        method: 'POST',
        headers: headers(),
        body: payload
      }
    )

    toast.success(`Đăng ký mới: ${res.created}, Đã có: ${res.skipped}`)
    selectedDirectUserIds.value = []
    searchStudentQuery.value = ''
    searchedStudents.value = []
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể ghi danh.')
  } finally {
    processingEnrollment.value = false
  }
}

// Single / Multi Enrollment Deletion
async function deleteOneEnrollment(id: Id) {
  if (!confirm('Bạn có chắc chắn muốn hủy ghi danh học phần cho sinh viên này không?')) return
  loading.value = true
  try {
    const res = await useApi<{ deleted: number }>('/admin/academic/enrollments/delete', {
      method: 'POST',
      headers: headers(),
      body: { enrollment_ids: [id] }
    })
    toast.success('Đã hủy ghi danh học phần thành công.')
    await loadEnrollments()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể hủy ghi danh.')
  } finally {
    loading.value = false
  }
}

async function deleteSelectedEnrollments() {
  if (selectedEnrollmentIds.value.length === 0) return
  if (!confirm(`Bạn có chắc chắn muốn xóa ${selectedEnrollmentIds.value.length} bản ghi ghi danh môn học đã chọn?`)) return
  
  loading.value = true
  try {
    const res = await useApi<{ deleted: number }>('/admin/academic/enrollments/delete', {
      method: 'POST',
      headers: headers(),
      body: { enrollment_ids: selectedEnrollmentIds.value }
    })
    toast.success(`Đã xóa thành công ${res.deleted} ghi danh môn học.`)
    selectedEnrollmentIds.value = []
    await loadEnrollments()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa ghi danh học viên.')
  } finally {
    loading.value = false
  }
}

function toggleSelectEnrollment(id: Id) {
  const index = selectedEnrollmentIds.value.indexOf(id)
  if (index > -1) {
    selectedEnrollmentIds.value.splice(index, 1)
  } else {
    selectedEnrollmentIds.value.push(id)
  }
}

function toggleSelectAllEnrollments() {
  if (selectedEnrollmentIds.value.length === enrollments.value.length) {
    selectedEnrollmentIds.value = []
  } else {
    selectedEnrollmentIds.value = enrollments.value.map(e => e.id)
  }
}

// Delete Exam Registration
async function deleteOneExamEnrollment(id: Id) {
  if (!confirm('Bạn có chắc chắn muốn hủy đăng ký thi cho học viên này không?')) return
  loading.value = true
  try {
    await useApi(`/admin/academic/exam-enrollments/${id}`, {
      method: 'DELETE',
      headers: headers()
    })
    toast.success('Đã hủy đăng ký thi thành công.')
    await loadExamEnrollments()
  } catch (e: any) {
    toast.error('Có lỗi xảy ra khi hủy đăng ký thi.')
  } finally {
    loading.value = false
  }
}

async function deleteSelectedExamEnrollments() {
  if (selectedExamEnrollmentIds.value.length === 0) return
  if (!confirm(`Hủy đăng ký thi cho ${selectedExamEnrollmentIds.value.length} thí sinh đã chọn?`)) return
  
  loading.value = true
  try {
    // Delete exam enrollments one by one or through generic bulk delete
    await Promise.all(
      selectedExamEnrollmentIds.value.map(id => 
        useApi(`/admin/academic/exam-enrollments/${id}`, {
          method: 'DELETE',
          headers: headers()
        })
      )
    )
    toast.success('Đã hủy đăng ký thi thành công.')
    selectedExamEnrollmentIds.value = []
    await loadExamEnrollments()
  } catch (e: any) {
    toast.error('Có lỗi xảy ra khi xóa đăng ký thi.')
  } finally {
    loading.value = false
  }
}

function toggleSelectExamEnrollment(id: Id) {
  const index = selectedExamEnrollmentIds.value.indexOf(id)
  if (index > -1) {
    selectedExamEnrollmentIds.value.splice(index, 1)
  } else {
    selectedExamEnrollmentIds.value.push(id)
  }
}

function toggleSelectAllExamEnrollments() {
  if (selectedExamEnrollmentIds.value.length === examEnrollments.value.length) {
    selectedExamEnrollmentIds.value = []
  } else {
    selectedExamEnrollmentIds.value = examEnrollments.value.map(e => e.id)
  }
}

// File-based Bulk Delete Wizard methods
function openBulkDeleteModal() {
  deleteFile.value = null
  deletePreviewData.value = null
  deleteStep.value = 1
  showBulkDeleteModal.value = true
}

function triggerDeleteFileSelect() {
  fileInputRef.value?.click()
}

function handleDeleteFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    deleteFile.value = target.files[0]
  }
}

async function validateDeleteFile() {
  if (!deleteFile.value) return
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('file', deleteFile.value)

    const res = await useApi<any>('/admin/academic/enrollments/delete-import-preview', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: formData
    })
    deletePreviewData.value = res
    deleteStep.value = 2
    toast.success('Kiểm tra tệp hoàn tất.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi khi đọc tệp tin xóa.')
  } finally {
    loading.value = false
  }
}

async function executeBulkDelete() {
  if (!deletePreviewData.value?.import_token) return
  deleteProcessing.value = true
  try {
    const res = await useApi<{ deleted: number }>('/admin/academic/enrollments/delete-import-execute', {
      method: 'POST',
      headers: headers(),
      body: { import_token: deletePreviewData.value.import_token }
    })
    toast.success(`Đã xóa thành công ${res.deleted} bản ghi ghi danh qua file.`);
    showBulkDeleteModal.value = false
    await loadEnrollments()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa ghi danh học viên.')
  } finally {
    deleteProcessing.value = false
  }
}
</script>

<template>
  <AdminWorkspaceShell
    title="Ghi Danh & Khảo Thí"
    description="Tự động ghi danh lớp học, ghi danh thủ công, tra cứu danh sách đăng ký và hủy ghi danh"
    :breadcrumb="['Trang chủ', 'Đào tạo', 'Ghi Danh']"
  >

    <!-- Stepper Tabs -->
    <div class="lnd-tab-bar">
      <button class="tab-btn" :class="{ 'is-active': activeTab === 'class-auto' }" @click="activeTab = 'class-auto'">
        <Building :size="15" /> Ghi danh lớp học tự động
      </button>
      <button class="tab-btn" :class="{ 'is-active': activeTab === 'direct-manual' }" @click="activeTab = 'direct-manual'">
        <UserCheck :size="15" /> Ghi danh trực tiếp
      </button>
      <button class="tab-btn" :class="{ 'is-active': activeTab === 'enrollment-list' }" @click="activeTab = 'enrollment-list'">
        <BookOpen :size="15" /> Danh sách ghi danh học viên
      </button>
      <button class="tab-btn" :class="{ 'is-active': activeTab === 'exam-registrations' }" @click="activeTab = 'exam-registrations'">
        <FileText :size="15" /> Danh sách đăng ký thi
      </button>
    </div>

    <!-- Active Tab Panel -->
    <div class="lnd-tab-content">
      <!-- 1. AUTOMATIC CLASS ENROLLMENT TAB -->
      <div v-if="activeTab === 'class-auto'" class="class-auto-panel">
        <div class="filter-card dashboard-card">
          <div class="filter-grid">
            <label class="crud-field">
              <span>Học kỳ thực tế</span>
              <select v-model="selectedTermId">
                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
              </select>
            </label>

            <label class="crud-field">
              <span>Khóa đào tạo (Cohort)</span>
              <select v-model="selectedCohortId">
                <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>

            <label class="crud-field">
              <span>Lớp hành chính</span>
              <select v-model="selectedClassId" :disabled="!selectedCohortId">
                <option value="">-- Chọn lớp học --</option>
                <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
              </select>
            </label>
          </div>

          <div class="auto-enroll-actions" v-if="selectedClassId">
            <div class="class-meta-info">
              <span class="info-tag" v-if="adminClasses.find(c => c.id === selectedClassId)?.curriculum_id">
                <GraduationCap :size="14" /> Đã gán CTĐT
              </span>
              <span class="info-tag is-warning" v-else>
                <GraduationCap :size="14" /> Chưa gán CTĐT
              </span>
              <span class="students-count-badge">Sĩ số: {{ classStudents.length }} học viên</span>
            </div>
            
            <button class="crud-primary-btn" :disabled="processingEnrollment || !adminClasses.find(c => c.id === selectedClassId)?.curriculum_id" @click="runAutoEnrollment">
              <Play :size="16" /> Kích hoạt ghi danh tự động
            </button>
          </div>
        </div>

        <div class="dashboard-card students-table-card">
          <h4 class="card-title"><Users :size="18" /> Thành viên lớp hành chính</h4>
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th style="width: 160px">Mã sinh viên</th>
                  <th>Họ và tên</th>
                  <th>Email liên hệ</th>
                  <th style="width: 140px">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="4" class="crud-empty">Đang tải danh sách thành viên...</td>
                </tr>
                <tr v-else-if="classStudents.length === 0">
                  <td colspan="4" class="crud-empty">Lớp học này chưa có học viên.</td>
                </tr>
                <tr v-else v-for="student in classStudents" :key="student.id">
                  <td><span class="student-code">{{ student.student_code }}</span></td>
                  <td><strong>{{ student.name }}</strong></td>
                  <td>{{ student.email }}</td>
                  <td><span class="status-badge">Đang học tập</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 2. DIRECT MANUAL ENROLLMENT TAB -->
      <div v-if="activeTab === 'direct-manual'" class="direct-manual-panel">
        <div class="grid-2-columns">
          <div class="dashboard-card form-selection-card">
            <h4 class="card-title"><BookOpen :size="18" /> Cấu hình ghi danh</h4>
            <div class="field-stack">
              <label class="crud-field">
                <span>Chọn Học kỳ áp dụng</span>
                <select v-model="selectedTermId">
                  <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </label>
              <label class="crud-field">
                <span>Chọn môn học (Hỗ trợ môn trả phí) *</span>
                <select v-model="selectedDirectCourseId">
                  <option value="">-- Chọn môn học --</option>
                  <option v-for="course in courses" :key="course.id" :value="course.id">
                    {{ course.title }} ({{ course.price > 0 ? `${course.price.toLocaleString('vi-VN')} đ` : 'Miễn phí' }})
                  </option>
                </select>
              </label>
              <label class="crud-field">
                <span>Lớp tín chỉ (Tùy chọn)</span>
                <select v-model="selectedDirectSectionId" :disabled="!selectedDirectCourseId">
                  <option value="">-- Ghi danh môn học chung --</option>
                  <option v-for="sec in classSections" :key="sec.id" :value="sec.id">{{ sec.code }}</option>
                </select>
              </label>
            </div>
            <div class="action-summary-box">
              <div class="selected-users-summary">
                <span>Đang chọn:</span>
                <strong>{{ selectedDirectUserIds.length }} học viên</strong>
              </div>
              <button class="crud-primary-btn w-full" :disabled="!selectedDirectCourseId || selectedDirectUserIds.length === 0 || processingEnrollment" @click="runDirectManualEnrollment">
                <UserCheck :size="16" /> Xác nhận ghi danh
              </button>
            </div>
          </div>

          <div class="dashboard-card student-picker-card">
            <h4 class="card-title"><Users :size="18" /> Lọc & Chọn học viên</h4>
            <div class="search-box">
              <Search :size="16" class="search-icon" />
              <input type="text" v-model="searchStudentQuery" placeholder="Mã SV hoặc tên..." @input="searchStudents" />
            </div>
            <div class="students-pick-list">
              <div v-if="searchedStudents.length === 0" class="picker-empty">Nhập mã để tìm kiếm sinh viên.</div>
              <div v-else v-for="student in searchedStudents" :key="student.id" class="picker-row" :class="{ 'is-selected': selectedDirectUserIds.includes(student.id) }" @click="toggleDirectUser(student.id)">
                <div class="checkbox-box">
                  <span class="check-indicator" v-if="selectedDirectUserIds.includes(student.id)"><Check :size="12" /></span>
                </div>
                <div class="student-info">
                  <span class="code">{{ student.student_code }}</span>
                  <strong class="name">{{ student.name }}</strong>
                  <span class="email">{{ student.email }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. ENROLLMENTS LIST & DELETION TAB -->
      <div v-if="activeTab === 'enrollment-list'" class="enrollment-list-panel">
        <!-- Search, filter and actions header -->
        <div class="filter-card dashboard-card">
          <div class="filter-group-inline">
            <div class="search-box-inline">
              <Search :size="15" />
              <input type="text" v-model="enrollListSearchQuery" placeholder="Mã SV, tên, mã môn..." @keyup.enter="loadEnrollments" />
            </div>
            <select v-model="enrollListCourseId" class="filter-select">
              <option value="">-- Tất cả môn học --</option>
              <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <button class="crud-primary-btn" @click="loadEnrollments">Tìm kiếm</button>
            <button class="crud-secondary-btn flex-btn" @click="openBulkDeleteModal">
              <Trash2 :size="14" /> Xóa bằng tệp file
            </button>
          </div>

          <!-- Bulk delete selected elements toolbar -->
          <div class="bulk-selection-toolbar" v-if="selectedEnrollmentIds.length > 0">
            <span>Đã chọn <strong>{{ selectedEnrollmentIds.length }}</strong> học viên ghi danh môn học</span>
            <button class="delete-btn-action" @click="deleteSelectedEnrollments">
              <Trash :size="14" /> Hủy ghi danh đã chọn
            </button>
          </div>
        </div>

        <div class="dashboard-card mt-20">
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th style="width: 50px">
                    <input type="checkbox" :checked="selectedEnrollmentIds.length === enrollments.length && enrollments.length > 0" @change="toggleSelectAllEnrollments" />
                  </th>
                  <th style="width: 130px">Mã SV</th>
                  <th>Họ và tên</th>
                  <th>Môn học / Học phần</th>
                  <th>Học kỳ</th>
                  <th>Lớp tín chỉ</th>
                  <th>Nguồn</th>
                  <th style="width: 140px">Ngày ghi danh</th>
                  <th style="width: 100px; text-align: center;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="9" class="crud-empty">Đang tải danh sách ghi danh...</td>
                </tr>
                <tr v-else-if="enrollments.length === 0">
                  <td colspan="9" class="crud-empty">Không tìm thấy bản ghi ghi danh nào.</td>
                </tr>
                <tr v-else v-for="item in enrollments" :key="item.id">
                  <td>
                    <input type="checkbox" :checked="selectedEnrollmentIds.includes(item.id)" @change="toggleSelectEnrollment(item.id)" />
                  </td>
                  <td><span class="student-code">{{ item.user?.student_code }}</span></td>
                  <td><strong>{{ item.user?.name }}</strong></td>
                  <td>{{ item.course?.title }}</td>
                  <td>{{ item.term?.name || '—' }}</td>
                  <td>{{ item.class_section?.code || '—' }}</td>
                  <td>
                    <span class="source-tag" :class="`source-${item.enrollment_source}`">{{ item.enrollment_source }}</span>
                  </td>
                  <td>{{ new Date(item.enrolled_at).toLocaleDateString('vi-VN') }}</td>
                  <td style="text-align: center;">
                    <button class="delete-icon-btn" title="Hủy ghi danh" @click="deleteOneEnrollment(item.id)">
                      <Trash2 :size="14" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="table-pagination" v-if="enrollmentsTotalPages > 1">
            <span class="total-text">Tổng số: <strong>{{ enrollmentsTotal }}</strong> bản ghi</span>
            <div class="pagination-buttons">
              <button class="page-btn" :disabled="enrollmentsPage === 1" @click="enrollmentsPage--; loadEnrollments()">
                <ChevronLeft :size="16" />
              </button>
              <span class="page-indicator">Trang {{ enrollmentsPage }} / {{ enrollmentsTotalPages }}</span>
              <button class="page-btn" :disabled="enrollmentsPage === enrollmentsTotalPages" @click="enrollmentsPage++; loadEnrollments()">
                <ChevronRight :size="16" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. EXAM REGISTRATIONS TAB -->
      <div v-if="activeTab === 'exam-registrations'" class="exam-registrations-panel">
        <div class="filter-card dashboard-card">
          <div class="filter-group-inline">
            <select v-model="examListExamId" class="filter-select w-wide">
              <option value="">-- Xem tất cả kỳ thi/Đợt thi --</option>
              <option v-for="ex in exams" :key="ex.id" :value="ex.id">{{ ex.title }} ({{ ex.type }})</option>
            </select>
            <button class="crud-primary-btn" @click="loadExamEnrollments">Tìm kiếm</button>
          </div>

          <!-- Bulk delete exam registration -->
          <div class="bulk-selection-toolbar" v-if="selectedExamEnrollmentIds.length > 0">
            <span>Đã chọn <strong>{{ selectedExamEnrollmentIds.length }}</strong> thí sinh đăng ký thi</span>
            <button class="delete-btn-action" @click="deleteSelectedExamEnrollments">
              <Trash :size="14" /> Hủy đăng ký thi đã chọn
            </button>
          </div>
        </div>

        <div class="dashboard-card mt-20">
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th style="width: 50px">
                    <input type="checkbox" :checked="selectedExamEnrollmentIds.length === examEnrollments.length && examEnrollments.length > 0" @change="toggleSelectAllExamEnrollments" />
                  </th>
                  <th style="width: 140px">Mã sinh viên</th>
                  <th>Họ và tên</th>
                  <th>Email</th>
                  <th>Kỳ thi / Đợt thi</th>
                  <th style="width: 160px">Ngày đăng ký</th>
                  <th style="width: 100px; text-align: center;">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="7" class="crud-empty">Đang tải danh sách đăng ký thi...</td>
                </tr>
                <tr v-else-if="examEnrollments.length === 0">
                  <td colspan="7" class="crud-empty">Không tìm thấy đăng ký thi nào.</td>
                </tr>
                <tr v-else v-for="item in examEnrollments" :key="item.id">
                  <td>
                    <input type="checkbox" :checked="selectedExamEnrollmentIds.includes(item.id)" @change="toggleSelectExamEnrollment(item.id)" />
                  </td>
                  <td><span class="student-code">{{ item.user?.student_code }}</span></td>
                  <td><strong>{{ item.user?.name }}</strong></td>
                  <td>{{ item.user?.email }}</td>
                  <td><strong>{{ item.exam?.title }}</strong></td>
                  <td>{{ new Date(item.enrolled_at).toLocaleDateString('vi-VN') }}</td>
                  <td style="text-align: center;">
                    <button class="delete-icon-btn" title="Hủy đăng ký thi" @click="deleteOneExamEnrollment(item.id)">
                      <Trash2 :size="14" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="table-pagination" v-if="examEnrollmentsTotalPages > 1">
            <span class="total-text">Tổng số: <strong>{{ examEnrollmentsTotal }}</strong> bản ghi</span>
            <div class="pagination-buttons">
              <button class="page-btn" :disabled="examEnrollmentsPage === 1" @click="examEnrollmentsPage--; loadExamEnrollments()">
                <ChevronLeft :size="16" />
              </button>
              <span class="page-indicator">Trang {{ examEnrollmentsPage }} / {{ examEnrollmentsTotalPages }}</span>
              <button class="page-btn" :disabled="examEnrollmentsPage === examEnrollmentsTotalPages" @click="examEnrollmentsPage++; loadExamEnrollments()">
                <ChevronRight :size="16" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Delete via File Modal -->
    <div class="custom-modal-overlay" v-if="showBulkDeleteModal" @click.self="showBulkDeleteModal = false">
      <div class="custom-modal modal-wide dashboard-card">
        <div class="modal-header">
          <h3>Xóa Ghi Danh Hàng Loạt Bằng Tệp</h3>
          <button class="close-btn" @click="showBulkDeleteModal = false"><X :size="20" /></button>
        </div>
        
        <div class="modal-body picker-body">
          <!-- Step 1: Upload File -->
          <div v-if="deleteStep === 1" class="upload-step-modal">
            <div class="upload-dropzone" @click="triggerDeleteFileSelect">
              <FileSpreadsheet :size="40" class="upload-icon" />
              <div v-if="!deleteFile" class="dropzone-text">
                <strong>Kéo thả tệp CSV để hủy ghi danh</strong>
                <span>Hỗ trợ định dạng .csv</span>
              </div>
              <div v-else class="dropzone-file">
                <strong>{{ deleteFile.name }}</strong>
                <span>{{ (deleteFile.size / 1024).toFixed(1) }} KB</span>
              </div>
              <input type="file" ref="fileInputRef" class="hidden-input" accept=".csv" @change="handleDeleteFileChange" />
            </div>

            <div class="template-download">
              <p>Mẫu tệp CSV tương tự như tệp ghi danh bao gồm cột mã sinh viên và mã khóa học.</p>
            </div>
          </div>

          <!-- Step 2: Validate Data & Preview -->
          <div v-if="deleteStep === 2 && deletePreviewData" class="preview-step-modal">
            <div class="delete-stats-bar">
              <div class="stat-box">
                <span class="label">Tổng dòng đọc được</span>
                <strong class="value">{{ deletePreviewData.total_rows }} dòng</strong>
              </div>
              <div class="stat-box">
                <span class="label text-danger">Có thể xóa</span>
                <strong class="value text-danger">{{ deletePreviewData.valid_rows }} dòng</strong>
              </div>
              <div class="stat-box">
                <span class="label text-muted">Lỗi bỏ qua</span>
                <strong class="value text-muted">{{ deletePreviewData.invalid_rows }} dòng</strong>
              </div>
            </div>

            <!-- Preview rows list -->
            <div class="preview-table-wrapper-small">
              <table class="crud-table text-small">
                <thead>
                  <tr>
                    <th>Dòng</th>
                    <th>Mã SV</th>
                    <th>Học viên</th>
                    <th>Mã môn</th>
                    <th>Kết quả xác thực</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in deletePreviewData.preview_data" :key="row.row_number">
                    <td>{{ row.row_number }}</td>
                    <td><span class="student-code">{{ row.student_code }}</span></td>
                    <td>{{ row.student_name || '—' }}</td>
                    <td>{{ row.course_code }}</td>
                    <td>
                      <span class="valid-message" :class="row.status === 'valid' ? 'text-danger' : 'text-muted'">
                        {{ row.message }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="crud-secondary-btn" @click="showBulkDeleteModal = false">Đóng</button>
          
          <button v-if="deleteStep === 1" class="crud-primary-btn" :disabled="loading || !deleteFile" @click="validateDeleteFile">
            {{ loading ? 'Đang phân tích...' : 'Kiểm tra tệp tin' }}
          </button>
          <button v-if="deleteStep === 2" class="crud-primary-btn btn-danger" :disabled="deletePreviewData.valid_rows === 0 || deleteProcessing" @click="executeBulkDelete">
            {{ deleteProcessing ? 'Đang xóa...' : `Tiến hành xóa (${deletePreviewData.valid_rows} dòng)` }}
          </button>
        </div>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.lnd-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.lnd-tab-bar {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  border-bottom: 2px solid rgba(0, 0, 0, 0.05);
  padding-bottom: 8px;
  flex-wrap: wrap;
}

.tab-btn {
  background: none;
  border: none;
  padding: 10px 16px;
  border-radius: 12px 12px 0 0;
  font-weight: 600;
  font-size: 0.9rem;
  color: #666;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 160ms ease;
  position: relative;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--green-deep, #047857);
  background: rgba(var(--green-rgb, 16, 185, 129), 0.04);
}

.tab-btn.is-active {
  color: var(--green-deep, #047857);
}

.tab-btn.is-active::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--green-deep, #047857);
  border-radius: 99px;
}

.lnd-tab-content {
  margin-top: 20px;
}

.filter-card {
  padding: 16px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 768px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }
}

.auto-enroll-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 16px;
  border-top: 1px dashed rgba(0, 0, 0, 0.08);
}

.class-meta-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.info-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  font-weight: 700;
  font-size: 0.78rem;
  padding: 4px 10px;
  border-radius: 99px;
}

.info-tag.is-warning {
  background: #fffbeb;
  color: #d97706;
}

.students-count-badge {
  font-size: 0.85rem;
  color: #666;
  font-weight: 600;
}

.students-table-card {
  margin-top: 20px;
  padding: 18px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  margin-bottom: 16px;
  color: #333;
}

.student-code {
  font-family: monospace;
  font-weight: 700;
  color: var(--green-deep, #047857);
}

.status-badge {
  font-size: 0.72rem;
  font-weight: 700;
  background: rgba(16, 185, 129, 0.08);
  color: #10b981;
  padding: 2px 8px;
  border-radius: 6px;
}

/* Direct Enrollment styles */
.grid-2-columns {
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 900px) {
  .grid-2-columns {
    grid-template-columns: 1fr;
  }
}

.form-selection-card, .student-picker-card {
  padding: 20px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.field-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.action-summary-box {
  margin-top: 24px;
  padding-top: 18px;
  border-top: 1px dashed rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.selected-users-summary {
  display: flex;
  justify-content: space-between;
  font-size: 0.88rem;
  color: #666;
}

.selected-users-summary strong {
  color: var(--green-deep, #047857);
  font-size: 0.95rem;
}

.w-full {
  width: 100%;
}

.search-box {
  position: relative;
  margin-bottom: 12px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #aaa;
}

.search-box input {
  width: 100%;
  padding: 10px 14px 10px 38px;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 12px;
  font-size: 0.88rem;
}

.students-pick-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 400px;
  overflow-y: auto;
  padding-right: 4px;
}

.picker-empty {
  padding: 40px;
  text-align: center;
  color: #aaa;
  font-style: italic;
  font-size: 0.88rem;
}

.picker-row {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 12px;
  border-radius: 12px;
  background: #fafafa;
  border: 1px solid rgba(0, 0, 0, 0.03);
  cursor: pointer;
  transition: all 120ms ease;
}

.picker-row:hover {
  background: rgba(0, 0, 0, 0.02);
}

.picker-row.is-selected {
  border-color: rgba(var(--green-rgb, 16, 185, 129), 0.35);
  background: rgba(var(--green-rgb, 16, 185, 129), 0.05);
}

.checkbox-box {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(0, 0, 0, 0.15);
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: #fff;
  transition: all 100ms ease;
}

.is-selected .checkbox-box {
  background: var(--green-deep, #047857);
  border-color: var(--green-deep, #047857);
}

.check-indicator {
  color: #fff;
  display: inline-flex;
}

.student-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.student-info .code {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--green-deep, #047857);
  font-family: monospace;
}

.student-info .name {
  font-size: 0.88rem;
  color: #333;
}

.student-info .email {
  font-size: 0.75rem;
  color: #888;
}

/* Enrollment list & filters inline styles */
.filter-group-inline {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
}

.search-box-inline {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1.5;
  min-width: 200px;
}

.search-box-inline svg {
  position: absolute;
  left: 12px;
  color: #aaa;
}

.search-box-inline input {
  width: 100%;
  padding: 8px 14px 8px 36px;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 10px;
  font-size: 0.88rem;
}

.filter-select {
  flex: 1;
  min-width: 180px;
  padding: 8px 12px;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 10px;
  font-size: 0.88rem;
  background: #fff;
}

.w-wide {
  min-width: 320px;
}

.flex-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.bulk-selection-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: #fef2f2;
  border: 1px solid #fee2e2;
  border-radius: 10px;
  color: #991b1b;
  font-size: 0.88rem;
  animation: slideDown 150ms ease;
}

.delete-btn-action {
  background: #ef4444;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background 150ms ease;
}

.delete-btn-action:hover {
  background: #dc2626;
}

.source-tag {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 4px;
}

.source-manual { background: rgba(59, 130, 246, 0.08); color: #3b82f6; }
.source-automatic { background: rgba(16, 185, 129, 0.08); color: #10b981; }
.source-excel_import { background: rgba(139, 92, 246, 0.08); color: #8b5cf6; }

.table-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 6px 0;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  margin-top: 14px;
}

.total-text {
  font-size: 0.82rem;
  color: #666;
}

.pagination-buttons {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-btn {
  background: #fff;
  border: 1px solid rgba(0, 0, 0, 0.1);
  padding: 6px;
  border-radius: 8px;
  cursor: pointer;
  color: #555;
  display: inline-flex;
  align-items: center;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-indicator {
  font-size: 0.85rem;
  color: #444;
  font-weight: 600;
}

/* Modals styles */
.custom-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.custom-modal {
  width: 100%;
  max-width: 480px;
  background: #fff;
  border-radius: 20px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.modal-wide {
  max-width: 640px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  padding-bottom: 12px;
}

.modal-header h3 {
  font-weight: 700;
  font-size: 1.15rem;
  color: #333;
}

.close-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #888;
  border-radius: 8px;
  padding: 4px;
}

.close-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  padding-top: 14px;
  align-items: center;
}

.picker-body {
  max-height: 480px;
  overflow-y: auto;
}

.upload-dropzone {
  border: 2px dashed rgba(0, 0, 0, 0.12);
  border-radius: 14px;
  padding: 36px 18px;
  text-align: center;
  cursor: pointer;
  background: #fafafa;
  transition: all 160ms ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.upload-dropzone:hover {
  background: #fff8f8;
  border-color: rgba(239, 68, 68, 0.25);
}

.upload-icon {
  color: #bbb;
}

.upload-dropzone:hover .upload-icon {
  color: #ef4444;
}

.dropzone-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropzone-text strong {
  font-size: 0.9rem;
  color: #333;
}

.dropzone-text span {
  font-size: 0.78rem;
  color: #888;
}

.dropzone-file {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dropzone-file strong {
  color: #ef4444;
  font-size: 0.95rem;
}

.dropzone-file span {
  font-size: 0.78rem;
  color: #666;
}

.hidden-input {
  display: none;
}

.template-download {
  font-size: 0.8rem;
  color: #666;
  margin-top: 10px;
}

/* Delete Preview */
.delete-stats-bar {
  display: flex;
  gap: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.delete-stats-bar .stat-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.delete-stats-bar .label {
  font-size: 0.75rem;
  color: #888;
}

.delete-stats-bar .value {
  font-size: 1.1rem;
  font-weight: 800;
}

.preview-table-wrapper-small {
  max-height: 240px;
  overflow-y: auto;
  margin-top: 10px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}

.text-small {
  font-size: 0.8rem !important;
}

.text-small th, .text-small td {
  padding: 8px 10px !important;
}

.valid-message {
  font-size: 0.78rem;
  font-weight: 600;
}

.btn-danger {
  background: #ef4444 !important;
  color: #fff !important;
}

.btn-danger:hover {
  background: #dc2626 !important;
}

@keyframes slideDown {
  from { transform: translateY(-10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.mt-20 {
  margin-top: 20px;
}

.delete-icon-btn {
  background: none;
  border: none;
  color: #ef4444;
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 150ms ease;
}

.delete-icon-btn:hover {
  background: #fee2e2;
  color: #b91c1c;
}
</style>
