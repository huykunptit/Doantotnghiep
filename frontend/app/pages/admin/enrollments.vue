<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useToast } from '~/composables/useToast'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

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
    const resUn = await useNuxtApp().$api(`/user/users?cohort_id=${selectedCohortIdForAdmin.value}&administrative_class_id_null=1&per_page=100`)
    // The backend might not support `administrative_class_id_null=1` directly, so we just fetch all for cohort and filter:
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
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Tổ chức Đào tạo', 'Xếp lớp & Ghi danh']"
    title="Xếp lớp & Ghi danh"
    description="Quản lý danh sách sinh viên lớp hành chính và ghi danh lớp học phần (tín chỉ)."
  >
    <!-- TABS -->
    <div class="enr-tabs">
      <button class="enr-tab" :class="{'is-active': activeTab === 'admin-class'}" @click="activeTab = 'admin-class'">
        <i class="pi pi-users" style="font-size:1.125rem" /> Gán Lớp Hành Chính
      </button>
      <button class="enr-tab" :class="{'is-active': activeTab === 'class-section'}" @click="activeTab = 'class-section'">
        <i class="pi pi-book" style="font-size:1.125rem" /> Ghi Danh Lớp Tín Chỉ
      </button>
    </div>

    <!-- PANEL 1: LỚP HÀNH CHÍNH -->
    <div v-if="activeTab === 'admin-class'" class="enr-panel">
      <div class="enr-filters">
        <div class="enr-filter-group">
          <label>Chọn Khóa học (Cohort)</label>
          <select v-model="selectedCohortIdForAdmin" class="enr-select">
            <option value="">— Vui lòng chọn khóa —</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
          </select>
        </div>
        <div class="enr-filter-group">
          <label>Chọn Lớp hành chính</label>
          <select v-model="selectedAdminClassId" :disabled="!selectedCohortIdForAdmin" class="enr-select">
            <option value="">— Chọn lớp hành chính —</option>
            <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="selectedAdminClassId" class="enr-grid-2">
        <!-- Students IN class -->
        <div class="enr-card">
          <div class="enr-card-header">
            Sinh viên trong lớp ({{ studentsInAdminClass.length }})
          </div>
          <div class="enr-list">
            <div v-if="loadingAdminClassStudents" style="padding:1rem;text-align:center">Đang tải...</div>
            <div v-else-if="!studentsInAdminClass.length" style="padding:1rem;text-align:center;color:var(--text-secondary)">Lớp chưa có sinh viên.</div>
            <div v-else v-for="st in studentsInAdminClass" :key="st.id" class="enr-list-item">
              <div class="enr-user-info">
                <span class="enr-user-name">{{ st.name }}</span>
                <span class="enr-user-code">{{ st.student_code }} - {{ st.email }}</span>
              </div>
              <button class="enr-btn-icon danger" title="Xóa khỏi lớp" @click="removeStudentFromAdminClass(st.id)">
                <i class="pi pi-times" style="font-size:1.0rem" />
              </button>
            </div>
          </div>
        </div>

        <!-- Unassigned Students -->
        <div class="enr-card">
          <div class="enr-card-header">
            Sinh viên chưa có lớp (Khóa {{ cohorts.find(c=>c.id===selectedCohortIdForAdmin)?.code }}) ({{ unassignedStudents.length }})
          </div>
          <div class="enr-list">
            <div v-if="loadingAdminClassStudents" style="padding:1rem;text-align:center">Đang tải...</div>
            <div v-else-if="!unassignedStudents.length" style="padding:1rem;text-align:center;color:var(--text-secondary)">Không có sinh viên nào chưa xếp lớp.</div>
            <div v-else v-for="st in unassignedStudents" :key="st.id" class="enr-list-item">
              <div class="enr-user-info">
                <span class="enr-user-name">{{ st.name }}</span>
                <span class="enr-user-code">{{ st.student_code }} - {{ st.email }}</span>
              </div>
              <button class="enr-btn-icon success" title="Thêm vào lớp" @click="assignStudentToAdminClass(st.id)">
                <i class="pi pi-arrow-right" style="font-size:1.0rem" />
              </button>
            </div>
          </div>
        </div>
      </div>
      <div v-else style="text-align:center;padding:3rem;color:var(--text-secondary);border:1px dashed var(--border);border-radius:8px">
        <i class="pi pi-users" style="font-size:3.0rem" />
        <p>Vui lòng chọn khóa và lớp hành chính để bắt đầu xếp lớp.</p>
      </div>
    </div>

    <!-- PANEL 2: LỚP TÍN CHỈ -->
    <div v-if="activeTab === 'class-section'" class="enr-panel">
      <!-- Context Selection -->
      <div class="enr-filters">
        <div class="enr-filter-group">
          <label>Học kỳ <span style="color:red">*</span></label>
          <select v-model="enrollTermId" class="enr-select">
            <option value="">— Chọn học kỳ —</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.code }} - {{ t.name }}</option>
          </select>
        </div>
        <div class="enr-filter-group">
          <label>Khóa học (Lọc)</label>
          <select v-model="enrollCohortId" class="enr-select">
            <option value="">— Tương đối / Tất cả —</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.code }}</option>
          </select>
        </div>
        <div class="enr-filter-group">
          <label>Môn học (Lọc)</label>
          <select v-model="enrollCourseId" class="enr-select">
            <option value="">— Tất cả môn —</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
          </select>
        </div>
      </div>

      <div v-if="enrollTermId" class="enr-actions-row">
        <!-- 1. Ghi danh tự động môn bắt buộc -->
        <div class="enr-filter-group" style="flex:1; border-right:1px solid var(--border); padding-right:1rem">
          <label>1. Ghi danh tự động (Môn Bắt Buộc)</label>
          <p style="font-size:0.85rem;color:var(--text-secondary);margin:0.2rem 0 0.5rem">Đẩy toàn bộ SV trong Khóa vào các Môn học Bắt buộc (Core) được phép mở.</p>
          <button class="enr-btn enr-btn-primary" :disabled="!enrollCohortId || bulkEnrollLoading" @click="handleBulkEnrollCore">
            <i class="pi pi-play-circle" style="font-size:1.0rem" /> {{ bulkEnrollLoading ? 'Đang chạy...' : 'Thực thi ghi danh tự động' }}
          </button>
        </div>

        <!-- 2. Ghi danh thủ công -->
        <div class="enr-filter-group" style="flex:1.5; border-right:1px solid var(--border); padding-right:1rem">
          <label>2. Ghi danh thủ công</label>
          <div style="display:flex;gap:0.5rem;margin-top:0.5rem">
            <input type="text" v-model="manualEnrollStudentCode" class="enr-input" placeholder="Mã SV (VD: B21DCCN...)" style="width:140px">
            <select v-model="manualEnrollCourseId" class="enr-select" style="width:160px">
              <option value="">— Môn học —</option>
              <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <button class="enr-btn enr-btn-outline" :disabled="manualEnrollLoading" @click="handleManualEnroll">
              <i class="pi pi-plus" style="font-size:1.0rem" /> {{ manualEnrollLoading ? '...' : 'Gán' }}
            </button>
          </div>
        </div>

        <!-- 3. Import CSV -->
        <div class="enr-filter-group" style="flex:1">
          <label>3. Import qua CSV</label>
          <p style="font-size:0.85rem;color:var(--text-secondary);margin:0.2rem 0 0.5rem">Cột 1: Mã SV, Cột 2: Mã Môn</p>
          <div style="display:flex;gap:0.5rem">
            <input type="file" id="csv_upload" accept=".csv" @change="handleFileSelect" style="display:none">
            <label for="csv_upload" class="enr-btn enr-btn-outline" style="cursor:pointer;flex:1;justify-content:center">
              <i class="pi pi-upload" style="font-size:1.0rem" /> Chọn Tệp
            </label>
            <button class="enr-btn enr-btn-primary" :disabled="!importFile || importLoading" @click="handlePreviewImport">
              Xem trước
            </button>
          </div>
        </div>
      </div>

      <!-- Import Preview Modal/Section -->
      <div v-if="importPreviewData.length" style="margin-bottom:1.5rem;border:1px solid var(--border);border-radius:8px;padding:1rem;background:var(--background)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
          <h3 style="margin:0;font-size:1rem;font-weight:600">Xem trước Import CSV ({{ importPreviewData.length }} dòng)</h3>
          <div style="display:flex;gap:0.5rem">
            <button class="enr-btn enr-btn-outline" @click="importPreviewData = []; importFile = null">Hủy</button>
            <button class="enr-btn enr-btn-primary" @click="handleExecuteImport">
              <i class="pi pi-check-circle" style="font-size:1.0rem" /> Xác nhận Import
            </button>
          </div>
        </div>
        <div class="enr-list">
          <table class="enr-table">
            <thead>
              <tr>
                <th>Dòng</th>
                <th>Mã SV</th>
                <th>Mã Môn</th>
                <th>Tình trạng</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in importPreviewData" :key="idx">
                <td>{{ row.row_number }}</td>
                <td>{{ row.student_code }}</td>
                <td>{{ row.course_code }}</td>
                <td>
                  <span v-if="row.status === 'valid'" style="color:var(--success)">Hợp lệ</span>
                  <span v-else style="color:var(--danger)">{{ row.message }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Enrollments Table -->
      <div v-if="enrollTermId" class="enr-card">
        <div class="enr-card-header">
          Danh sách Ghi danh
          <button class="enr-btn-icon" @click="loadEnrollments" title="Tải lại">
            <i class="pi pi-refresh" style="font-size:1.0rem" />
          </button>
        </div>
        <div v-if="loadingEnrollments" style="padding:2rem;text-align:center">Đang tải...</div>
        <div v-else-if="!enrollmentsList.length" style="padding:2rem;text-align:center;color:var(--text-secondary)">
          Chưa có ghi danh nào thỏa mãn điều kiện lọc.
        </div>
        <div v-else style="overflow-x:auto">
          <table class="enr-table">
            <thead>
              <tr>
                <th>Mã SV</th>
                <th>Họ tên</th>
                <th>Môn học</th>
                <th>Khóa</th>
                <th>Nguồn</th>
                <th style="text-align:right">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in enrollmentsList" :key="e.id">
                <td><strong>{{ e.user?.student_code }}</strong></td>
                <td>{{ e.user?.name }}</td>
                <td>
                  {{ e.course?.title }}
                  <span class="enr-badge" :class="e.course?.course_mode">{{ e.course?.course_mode }}</span>
                </td>
                <td>{{ e.cohort?.code || '-' }}</td>
                <td>
                  <span class="enr-badge" style="background:#f3f4f6;color:#374151">{{ e.enrollment_source }}</span>
                </td>
                <td style="text-align:right">
                  <button class="enr-btn-icon danger" title="Hủy ghi danh" @click="unenrollRecord(e.id)">
                    <i class="pi pi-trash" style="font-size:1.0rem" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <div v-if="!enrollTermId" style="text-align:center;padding:3rem;color:var(--text-secondary);border:1px dashed var(--border);border-radius:8px">
        <i class="pi pi-bookmark" style="font-size:3rem;opacity:0.2;margin-bottom:1rem" />
        <p>Vui lòng chọn Học kỳ để tải danh sách và thực hiện các thao tác ghi danh.</p>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.enr-tabs { display: flex; gap: 1.5rem; border-bottom: 1px solid var(--line); margin-bottom: 1.5rem; }
.enr-tab { padding: 0.75rem 1rem; background: transparent; border: none; border-bottom: 2px solid transparent; font-weight: 500; color: var(--muted); cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
.enr-tab:hover { color: var(--text); }
.enr-tab.is-active { color: var(--green); border-bottom-color: var(--green); }
.enr-panel { background: var(--surface-strong, #fff); border: 1px solid var(--line); border-radius: 12px; padding: 1.5rem; box-shadow: var(--shadow-sm); }
.enr-filters { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; padding: 1rem; background: var(--bg); border-radius: 8px; border: 1px solid var(--line); }
.enr-filter-group { display: flex; flex-direction: column; gap: 0.25rem; min-width: 200px; flex: 1; }
.enr-filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--muted); }
.enr-select, .enr-input { width: 100%; padding: 0.6rem; border: 1px solid var(--line); border-radius: 6px; background: var(--surface-strong, #fff); font-size: 0.95rem; transition: all 0.2s; }
.enr-select:focus, .enr-input:focus { border-color: var(--green); outline: none; box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.1); }
.enr-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.enr-card { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
.enr-card-header { background: var(--bg); padding: 1rem; border-bottom: 1px solid var(--line); font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.enr-list { max-height: 400px; overflow-y: auto; }
.enr-list-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid var(--line); transition: background 0.1s; }
.enr-list-item:hover { background: var(--bg); }
.enr-list-item:last-child { border-bottom: none; }
.enr-user-info { display: flex; flex-direction: column; }
.enr-user-name { font-weight: 500; font-size: 0.95rem; }
.enr-user-code { font-size: 0.85rem; color: var(--muted); }
.enr-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
.enr-btn-primary { background: var(--green); color: white; }
.enr-btn-primary:hover { filter: brightness(1.1); }
.enr-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.enr-btn-outline { border-color: var(--line); background: transparent; color: var(--text); }
.enr-btn-outline:hover { background: var(--bg); }
.enr-btn-icon { padding: 0.4rem; border-radius: 4px; color: var(--muted); background: transparent; border: none; cursor: pointer; }
.enr-btn-icon:hover { background: var(--bg); color: var(--text); }
.enr-btn-icon.danger:hover { color: #ef4444; background: #fee2e2; }
.enr-btn-icon.success:hover { color: #10b981; background: #d1fae5; }
.enr-table { width: 100%; border-collapse: collapse; }
.enr-table th, .enr-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--line); }
.enr-table th { background: var(--bg); font-weight: 600; color: var(--muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
.enr-table tr:hover td { background: var(--bg); }
.enr-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: var(--bg); color: var(--muted); }
.enr-badge.core { background: #e0e7ff; color: #4f46e5; }
.enr-badge.elective { background: #fef3c7; color: #d97706; }
.enr-actions-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; padding: 1.5rem; border: 1px dashed var(--line); border-radius: 8px; align-items: flex-end; }
</style>
