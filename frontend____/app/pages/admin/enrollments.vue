<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import DataTableFooter from '~/components/common/DataTableFooter.vue'

// Unified UI Components
import UiFilters from '~/components/ui/UiFilters.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

const { addToast } = useToast()

// Tabs: admin-class | class-section
const activeTab = ref<'admin-class' | 'class-section'>('admin-class')

// ==========================================
// SHARED DATA (Cohorts, Terms, AdminClasses, etc.)
// ==========================================
const cohorts = ref<any[]>([])
const terms = ref<any[]>([])
const adminClasses = ref<any[]>([])
const courses = ref<any[]>([])
const classSections = ref<any[]>([])

const fetchSharedData = async () => {
  try {
    const [cohRes, termRes, crsRes] = await Promise.all([
      useNuxtApp().$api('/user/academic/cohorts?per_page=100'),
      useNuxtApp().$api('/user/academic/terms?per_page=100'),
      useNuxtApp().$api('/user/courses?per_page=100'),
    ])
    cohorts.value = cohRes.data || []
    terms.value = termRes.data || []
    courses.value = crsRes.data || []
  } catch (error: any) {
    console.error('Lỗi tải dữ liệu chung:', error)
  }
}

// ==========================================
// TAB 1: LỚP HÀNH CHÍNH
// ==========================================
const selectedCohortIdForAdmin = ref<number | ''>('')
const selectedAdminClassId = ref<number | ''>('')
const studentsInAdminClass = ref<any[]>([])
const unassignedStudents = ref<any[]>([])
const loadingAdminClassStudents = ref(false)

const loadAdminClassesForCohort = async () => {
  if (!selectedCohortIdForAdmin.value) {
    adminClasses.value = []
    selectedAdminClassId.value = ''
    return
  }
  try {
    const res = await useNuxtApp().$api(`/user/academic/administrative-classes?cohort_id=${selectedCohortIdForAdmin.value}&per_page=100`)
    adminClasses.value = res.data || []
  } catch (error: any) {
    console.error(error)
  }
}

const loadStudentsForAdminClass = async () => {
  if (!selectedAdminClassId.value) {
    studentsInAdminClass.value = []
    unassignedStudents.value = []
    return
  }
  loadingAdminClassStudents.value = true
  try {
    // 1. Get students currently in this admin class
    const resIn = await useNuxtApp().$api(`/user/users?administrative_class_id=${selectedAdminClassId.value}&per_page=100`)
    studentsInAdminClass.value = resIn.data || []
    
    // 2. Get students in this cohort but NO admin class
    const resAllCohort = await useNuxtApp().$api(`/user/users?cohort_id=${selectedCohortIdForAdmin.value}&per_page=500`)
    unassignedStudents.value = (resAllCohort.data || []).filter((u: any) => !u.administrative_class_id)
  } catch (error: any) {
    addToast('Lỗi tải danh sách sinh viên', 'error')
  } finally {
    loadingAdminClassStudents.value = false
  }
}

const assignStudentToAdminClass = async (studentId: number) => {
  try {
    await useNuxtApp().$api(`/user/users/${studentId}`, {
      method: 'PUT',
      body: { administrative_class_id: selectedAdminClassId.value }
    })
    addToast('Gán lớp thành công')
    await loadStudentsForAdminClass()
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi khi gán lớp', 'error')
  }
}

const removeStudentFromAdminClass = async (studentId: number) => {
  try {
    await useNuxtApp().$api(`/user/users/${studentId}`, {
      method: 'PUT',
      body: { administrative_class_id: null }
    })
    addToast('Đã xóa khỏi lớp')
    await loadStudentsForAdminClass()
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi khi xóa khỏi lớp', 'error')
  }
}

// ==========================================
// TAB 2: LỚP TÍN CHỈ (ENROLLMENTS)
// ==========================================
const enrollTermId = ref<number | ''>('')
const enrollCohortId = ref<number | ''>('')
const enrollCourseId = ref<number | ''>('')
const enrollmentsList = ref<any[]>([])
const loadingEnrollments = ref(false)

const sortBy = ref('')
const sortOrder = ref<'asc' | 'desc' | ''>('')

const loadClassSectionsForEnrollment = async () => {
  if (!enrollTermId.value) return
  try {
    const q = new URLSearchParams({ term_id: String(enrollTermId.value), per_page: '100' })
    if (enrollCourseId.value) q.append('course_id', String(enrollCourseId.value))
    const res = await useNuxtApp().$api(`/user/academic/class-sections?${q.toString()}`)
    classSections.value = res.data || []
  } catch (error) {
    console.error(error)
  }
}

const loadEnrollments = async () => {
  loadingEnrollments.value = true
  try {
    const q = new URLSearchParams({ per_page: '100' })
    if (enrollTermId.value) q.append('term_id', String(enrollTermId.value))
    if (enrollCohortId.value) q.append('cohort_id', String(enrollCohortId.value))
    if (enrollCourseId.value) q.append('course_id', String(enrollCourseId.value))
    
    if (sortBy.value && sortOrder.value) {
      q.append('sort_by', sortBy.value)
      q.append('sort_order', sortOrder.value)
    }
    
    const res = await useNuxtApp().$api(`/user/academic/enrollments?${q.toString()}`)
    enrollmentsList.value = res.data || []
  } catch (error: any) {
    addToast('Lỗi tải danh sách ghi danh', 'error')
  } finally {
    loadingEnrollments.value = false
  }
}

// Bulk Enroll Core
const bulkEnrollLoading = ref(false)
const handleBulkEnrollCore = async () => {
  if (!enrollCohortId.value || !enrollTermId.value) {
    return addToast('Vui lòng chọn Học kỳ và Khóa để ghi danh hàng loạt.', 'warning')
  }
  if (!confirm('Bạn có chắc muốn tự động ghi danh sinh viên của khóa này vào các môn Bắt buộc của học kỳ?')) return
  
  bulkEnrollLoading.value = true
  try {
    const res = await useNuxtApp().$api(`/user/academic/cohorts/${enrollCohortId.value}/enroll-core`, {
      method: 'POST',
      body: { term_id: enrollTermId.value }
    })
    addToast(res.message || `Đã ghi danh ${res.created} SV (Bỏ qua ${res.skipped})`)
    loadEnrollments()
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi ghi danh hàng loạt', 'error')
  } finally {
    bulkEnrollLoading.value = false
  }
}

// Manual Enroll Single
const manualEnrollStudentCode = ref('')
const manualEnrollCourseId = ref<number | ''>('')
const manualEnrollSectionId = ref<number | ''>('')
const manualEnrollLoading = ref(false)

const handleManualEnroll = async () => {
  if (!manualEnrollStudentCode.value || !manualEnrollCourseId.value || !enrollTermId.value) {
    return addToast('Vui lòng nhập Mã SV, chọn Học kỳ và Môn học', 'warning')
  }
  manualEnrollLoading.value = true
  try {
    const res = await useNuxtApp().$api('/user/academic/enrollments/manual', {
      method: 'POST',
      body: {
        student_codes: [manualEnrollStudentCode.value.trim()],
        course_id: manualEnrollCourseId.value,
        term_id: enrollTermId.value,
        class_section_id: manualEnrollSectionId.value || null
      }
    })
    addToast(res.message || 'Ghi danh thành công')
    manualEnrollStudentCode.value = ''
    loadEnrollments()
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi ghi danh', 'error')
  } finally {
    manualEnrollLoading.value = false
  }
}

// Import CSV Preview & Execute
const importFile = ref<File | null>(null)
const importPreviewData = ref<any[]>([])
const importLoading = ref(false)

const handleFileSelect = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    importFile.value = target.files[0]
  }
}

const handlePreviewImport = async () => {
  if (!importFile.value) return addToast('Vui lòng chọn tệp CSV', 'warning')
  
  const formData = new FormData()
  formData.append('file', importFile.value)
  
  importLoading.value = true
  try {
    const res = await useNuxtApp().$api('/user/academic/enrollments/import-preview', {
      method: 'POST',
      body: formData
    })
    importPreviewData.value = res.preview || []
    addToast(res.message || 'Xem trước dữ liệu thành công')
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi đọc tệp CSV', 'error')
  } finally {
    importLoading.value = false
  }
}

const handleExecuteImport = async () => {
  if (!importFile.value || !enrollTermId.value) {
    return addToast('Vui lòng chọn Học kỳ và tệp CSV đã Preview', 'warning')
  }
  
  const formData = new FormData()
  formData.append('file', importFile.value)
  formData.append('term_id', String(enrollTermId.value))
  
  importLoading.value = true
  try {
    const res = await useNuxtApp().$api('/user/academic/enrollments/import-execute', {
      method: 'POST',
      body: formData
    })
    addToast(res.message || 'Ghi danh hàng loạt thành công')
    importPreviewData.value = []
    importFile.value = null
    const fileInput = document.getElementById('csv_upload') as HTMLInputElement
    if (fileInput) fileInput.value = ''
    loadEnrollments()
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi khi import ghi danh', 'error')
  } finally {
    importLoading.value = false
  }
}

const unenrollRecord = async (id: number) => {
  if (!confirm('Bạn có chắc muốn hủy ghi danh sinh viên này?')) return
  try {
    await useNuxtApp().$api('/user/academic/enrollments/delete', {
      method: 'POST',
      body: { enrollment_ids: [id] }
    })
    addToast('Hủy ghi danh thành công')
    loadEnrollments()
  } catch (err: any) {
    addToast(err.data?.message || 'Lỗi hủy ghi danh', 'error')
  }
}

// Columns for Enrollments list using UiTable
const enrollmentColumns = [
  { id: 'code', accessorKey: 'user.student_code', header: 'Mã SV', sortable: true },
  { id: 'name', accessorKey: 'user.name', header: 'Họ tên', sortable: true },
  { id: 'course', accessorKey: 'course.title', header: 'Môn học' },
  { id: 'cohort', accessorKey: 'cohort.code', header: 'Khóa' },
  { id: 'source', accessorKey: 'enrollment_source', header: 'Nguồn' },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

function handleSort(event: { key: string; order: 'asc' | 'desc' | '' }) {
  sortBy.value = event.key
  sortOrder.value = event.order
  loadEnrollments()
}

onMounted(() => {
  fetchSharedData()
})

watch(selectedCohortIdForAdmin, loadAdminClassesForCohort)
watch(selectedAdminClassId, loadStudentsForAdminClass)
watch(enrollTermId, () => { loadClassSectionsForEnrollment(); loadEnrollments() })
watch(enrollCohortId, loadEnrollments)
watch(enrollCourseId, loadEnrollments)

</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Tổ chức Đào tạo</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Xếp lớp & Ghi danh</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý danh sách sinh viên lớp hành chính và ghi danh lớp học phần (tín chỉ).</p>
      </div>
    </div>

    <!-- TABS -->
    <nav class="flex gap-4 border-b border-[var(--line)] mb-2 shrink-0">
      <button 
        type="button" 
        class="inline-flex items-center gap-2 pb-3 text-sm font-semibold border-b-2 transition-colors cursor-pointer"
        :class="activeTab === 'admin-class' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'"
        @click="activeTab = 'admin-class'"
      >
        <i class="pi pi-users text-sm" /> Gán Lớp Hành Chính
      </button>
      <button 
        type="button" 
        class="inline-flex items-center gap-2 pb-3 text-sm font-semibold border-b-2 transition-colors cursor-pointer"
        :class="activeTab === 'class-section' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'"
        @click="activeTab = 'class-section'"
      >
        <i class="pi pi-book text-sm" /> Ghi Danh Lớp Tín Chỉ
      </button>
    </nav>

    <!-- PANEL 1: LỚP HÀNH CHÍNH -->
    <div v-if="activeTab === 'admin-class'" class="flex flex-col gap-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-[var(--surface)] border border-[var(--line)] rounded-2xl shadow-sm">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-[var(--text)]">Chọn Khóa học (Cohort)</label>
          <select v-model="selectedCohortIdForAdmin" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full">
            <option value="">— Vui lòng chọn khóa —</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
          </select>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-[var(--text)]">Chọn Lớp hành chính</label>
          <select v-model="selectedAdminClassId" :disabled="!selectedCohortIdForAdmin" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full disabled:opacity-40">
            <option value="">— Chọn lớp hành chính —</option>
            <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="selectedAdminClassId" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Students IN class -->
        <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] font-bold text-xs uppercase tracking-wider text-[var(--text)]">
            Sinh viên trong lớp ({{ studentsInAdminClass.length }})
          </div>
          <div class="max-h-[400px] overflow-y-auto divide-y divide-[var(--line)] flex flex-col">
            <div v-if="loadingAdminClassStudents" class="p-5 text-center text-xs text-[var(--muted)]">Đang tải...</div>
            <div v-else-if="!studentsInAdminClass.length" class="p-5 text-center text-xs text-[var(--muted)]">Lớp chưa có sinh viên.</div>
            <div v-else v-for="st in studentsInAdminClass" :key="st.id" class="flex justify-between items-center p-4 hover:bg-[var(--surface)] transition-colors">
              <div class="flex flex-col">
                <span class="text-xs font-bold text-[var(--text)]">{{ st.name }}</span>
                <span class="text-[10px] text-[var(--muted)] mt-0.5">{{ st.student_code }} - {{ st.email }}</span>
              </div>
              <button 
                class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 transition-colors cursor-pointer" 
                title="Xóa khỏi lớp" 
                @click="removeStudentFromAdminClass(st.id)"
              >
                <i class="pi pi-times text-xs" />
              </button>
            </div>
          </div>
        </div>

        <!-- Unassigned Students -->
        <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] font-bold text-xs uppercase tracking-wider text-[var(--text)]">
            Sinh viên chưa có lớp (Khóa {{ cohorts.find(c=>c.id===selectedCohortIdForAdmin)?.code }}) ({{ unassignedStudents.length }})
          </div>
          <div class="max-h-[400px] overflow-y-auto divide-y divide-[var(--line)] flex flex-col">
            <div v-if="loadingAdminClassStudents" class="p-5 text-center text-xs text-[var(--muted)]">Đang tải...</div>
            <div v-else-if="!unassignedStudents.length" class="p-5 text-center text-xs text-[var(--muted)]">Không có sinh viên nào chưa xếp lớp.</div>
            <div v-else v-for="st in unassignedStudents" :key="st.id" class="flex justify-between items-center p-4 hover:bg-[var(--surface)] transition-colors">
              <div class="flex flex-col">
                <span class="text-xs font-bold text-[var(--text)]">{{ st.name }}</span>
                <span class="text-[10px] text-[var(--muted)] mt-0.5">{{ st.student_code }} - {{ st.email }}</span>
              </div>
              <button 
                class="w-8 h-8 rounded-lg flex items-center justify-center bg-emerald-50 hover:bg-emerald-100 text-emerald-600 transition-colors cursor-pointer" 
                title="Thêm vào lớp" 
                @click="assignStudentToAdminClass(st.id)"
              >
                <i class="pi pi-arrow-right text-xs" />
              </button>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-12 px-6 text-[var(--muted)] border border-dashed border-[var(--line)] rounded-2xl bg-white flex flex-col items-center gap-3">
        <i class="pi pi-users text-4xl opacity-30" />
        <p class="text-sm">Vui lòng chọn khóa và lớp hành chính để bắt đầu xếp lớp.</p>
      </div>
    </div>

    <!-- PANEL 2: LỚP TÍN CHỈ -->
    <div v-if="activeTab === 'class-section'" class="flex flex-col gap-5">
      <!-- Context Selection -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 bg-[var(--surface)] border border-[var(--line)] rounded-2xl shadow-sm">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-[var(--text)]">Học kỳ <span class="text-red-500">*</span></label>
          <select v-model="enrollTermId" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full">
            <option value="">— Chọn học kỳ —</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.code }} - {{ t.name }}</option>
          </select>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-[var(--text)]">Khóa học (Lọc)</label>
          <select v-model="enrollCohortId" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full">
            <option value="">— Tương đối / Tất cả —</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.code }}</option>
          </select>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-[var(--text)]">Môn học (Lọc)</label>
          <select v-model="enrollCourseId" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer w-full">
            <option value="">— Tất cả môn —</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
          </select>
        </div>
      </div>

      <div v-if="enrollTermId" class="grid grid-cols-1 xl:grid-cols-3 gap-5 border border-dashed border-[var(--line)] rounded-2xl p-5 bg-white">
        <!-- 1. Ghi danh tự động môn bắt buộc -->
        <div class="flex flex-col gap-3 xl:border-r border-[var(--line)] xl:pr-5">
          <div>
            <label class="text-xs font-bold text-[var(--text)] block">1. Ghi danh tự động (Môn Bắt Buộc)</label>
            <p class="text-[10px] text-[var(--muted)] mt-1 leading-relaxed">Đẩy toàn bộ SV trong Khóa vào các Môn học Bắt buộc (Core) được phép mở.</p>
          </div>
          <button 
            class="inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50 w-full mt-auto cursor-pointer" 
            :disabled="!enrollCohortId || bulkEnrollLoading" 
            @click="handleBulkEnrollCore"
          >
            <i class="pi pi-play-circle text-sm" /> 
            {{ bulkEnrollLoading ? 'Đang chạy...' : 'Thực thi tự động' }}
          </button>
        </div>

        <!-- 2. Ghi danh thủ công -->
        <div class="flex flex-col gap-3 xl:border-r border-[var(--line)] xl:px-5">
          <label class="text-xs font-bold text-[var(--text)]">2. Ghi danh thủ công</label>
          <div class="flex flex-col sm:flex-row gap-2 mt-auto">
            <input type="text" v-model="manualEnrollStudentCode" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75] flex-1 min-w-[120px]" placeholder="Mã SV">
            <select v-model="manualEnrollCourseId" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer flex-1 min-w-[120px]">
              <option value="">— Môn học —</option>
              <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <button 
              class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors disabled:opacity-40 cursor-pointer" 
              :disabled="manualEnrollLoading" 
              @click="handleManualEnroll"
            >
              <i class="pi pi-plus" />
            </button>
          </div>
        </div>

        <!-- 3. Import CSV -->
        <div class="flex flex-col gap-3 xl:pl-5">
          <div>
            <label class="text-xs font-bold text-[var(--text)] block">3. Import qua CSV</label>
            <p class="text-[10px] text-[var(--muted)] mt-1 leading-relaxed">Cột 1: Mã SV, Cột 2: Mã Môn</p>
          </div>
          <div class="flex gap-2 mt-auto">
            <input type="file" id="csv_upload" accept=".csv" @change="handleFileSelect" class="hidden">
            <label for="csv_upload" class="inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors cursor-pointer flex-1">
              <i class="pi pi-upload" /> {{ importFile ? 'Đã chọn file' : 'Chọn Tệp' }}
            </label>
            <button 
              class="inline-flex items-center justify-center h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50 cursor-pointer" 
              :disabled="!importFile || importLoading" 
              @click="handlePreviewImport"
            >
              Xem trước
            </button>
          </div>
        </div>
      </div>

      <!-- Import Preview Section -->
      <div v-if="importPreviewData.length" class="border border-[var(--line)] rounded-2xl p-5 bg-white shadow-sm flex flex-col gap-4">
        <div class="flex justify-between items-center">
          <h3 class="text-sm font-semibold text-[var(--text)]">Xem trước Import CSV ({{ importPreviewData.length }} dòng)</h3>
          <div class="flex gap-2">
            <button class="h-8 px-3 rounded-lg border border-[var(--line)] text-xs font-semibold hover:bg-[var(--surface)] transition-colors cursor-pointer" @click="importPreviewData = []; importFile = null">Hủy</button>
            <button class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors cursor-pointer" @click="handleExecuteImport">
              <i class="pi pi-check-circle" /> Xác nhận Import
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left border-collapse">
            <thead>
              <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[var(--muted)] uppercase font-semibold">
                <th class="px-4 py-2">Dòng</th>
                <th class="px-4 py-2">Mã SV</th>
                <th class="px-4 py-2">Mã Môn</th>
                <th class="px-4 py-2">Tình trạng</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in importPreviewData" :key="idx" class="border-b border-[var(--line)] hover:bg-[var(--surface)]">
                <td class="px-4 py-2 text-[var(--text)] font-semibold">{{ row.row_number }}</td>
                <td class="px-4 py-2 text-[var(--text)]">{{ row.student_code }}</td>
                <td class="px-4 py-2 text-[var(--text)]">{{ row.course_code }}</td>
                <td class="px-4 py-2">
                  <span v-if="row.status === 'valid'" class="text-emerald-600 font-semibold">Hợp lệ</span>
                  <span v-else class="text-red-500 font-semibold">{{ row.message }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Standardized Enrollments Table -->
      <div v-if="enrollTermId" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] font-bold text-xs uppercase tracking-wider text-[var(--text)] flex justify-between items-center">
          <span>Danh sách Ghi danh</span>
          <button class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--text)] transition-colors cursor-pointer" @click="loadEnrollments" title="Tải lại">
            <i class="pi pi-refresh text-xs" />
          </button>
        </div>
        
        <UiTable
          :columns="enrollmentColumns"
          :data="enrollmentsList"
          :loading="loadingEnrollments"
          :sort-by="sortBy"
          :sort-order="sortOrder"
          @sort="handleSort"
        >
          <!-- Code cell -->
          <template #code-cell="{ row }">
            <strong class="text-xs font-bold text-[var(--text)]">{{ row.original.user?.student_code }}</strong>
          </template>
          
          <!-- Name cell -->
          <template #name-cell="{ row }">
            <span class="text-xs text-[var(--text)]">{{ row.original.user?.name }}</span>
          </template>

          <!-- Course cell -->
          <template #course-cell="{ row }">
            <div class="flex items-center gap-2">
              <span class="text-xs text-[var(--text)]">{{ row.original.course?.title }}</span>
              <span class="inline-block text-[9px] font-bold uppercase px-1.5 py-0.5 rounded" :class="row.original.course?.course_mode === 'core' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">{{ row.original.course?.course_mode }}</span>
            </div>
          </template>

          <!-- Cohort cell -->
          <template #cohort-cell="{ row }">
            <span class="text-xs text-[var(--text)]">{{ row.original.cohort?.code || '-' }}</span>
          </template>

          <!-- Source cell -->
          <template #source-cell="{ row }">
            <span class="inline-block text-[9px] font-bold px-1.5 py-0.5 rounded bg-[var(--surface)] border border-[var(--line)] text-[var(--text)]">{{ row.original.enrollment_source }}</span>
          </template>

          <!-- Actions cell -->
          <template #actions-cell="{ row }">
            <button class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 transition-colors ml-auto cursor-pointer" title="Hủy ghi danh" @click="unenrollRecord(row.original.id)">
              <i class="pi pi-trash text-xs" />
            </button>
          </template>
          
          <template #empty>
            <div class="flex flex-col items-center justify-center py-16 gap-2 text-[var(--color-text-muted)]">
              <i class="pi pi-inbox text-3xl opacity-40" />
              <p class="text-sm font-medium">Chương trình chưa ghi nhận danh sách tín chỉ</p>
            </div>
          </template>
        </UiTable>
      </div>

      <div v-if="!enrollTermId" class="text-center py-12 px-6 text-[var(--muted)] border border-dashed border-[var(--line)] rounded-2xl bg-white flex flex-col items-center gap-3">
        <i class="pi pi-bookmark text-4xl opacity-30" />
        <p class="text-sm">Vui lòng chọn Học kỳ để tải danh sách và thực hiện các thao tác ghi danh.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal to match design aesthetics */
</style>
