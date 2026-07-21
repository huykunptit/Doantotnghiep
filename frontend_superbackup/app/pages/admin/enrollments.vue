<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from '~/composables/useToast'

// Unified UI Components

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
  <div class="report-page">
    <Toast/><ConfirmDialog/>
    <header class="page-header"><div><span>Tổ chức đào tạo</span><h1>Xếp lớp & ghi danh</h1><p>Quản lý lớp hành chính và ghi danh lớp học phần.</p></div></header>
    <div class="actions"><Button label="Gán lớp hành chính" icon="pi pi-users" :severity="activeTab==='admin-class'?'primary':'secondary'" :outlined="activeTab!=='admin-class'" @click="activeTab='admin-class'"/><Button label="Ghi danh lớp tín chỉ" icon="pi pi-book" :severity="activeTab==='class-section'?'primary':'secondary'" :outlined="activeTab!=='class-section'" @click="activeTab='class-section'"/></div>
    <template v-if="activeTab==='admin-class'">
      <Card><template #title>Chọn lớp</template><template #content><div class="filters"><Select v-model="selectedCohortIdForAdmin" :options="cohorts" option-label="name" option-value="id" placeholder="Chọn khóa"/><Select v-model="selectedAdminClassId" :options="adminClasses" option-label="name" option-value="id" placeholder="Chọn lớp hành chính" :disabled="!selectedCohortIdForAdmin"/></div></template></Card>
      <div v-if="selectedAdminClassId" class="distribution">
        <Card><template #title>Sinh viên trong lớp ({{studentsInAdminClass.length}})</template><template #content><DataTable :value="studentsInAdminClass" :loading="loadingAdminClassStudents" data-key="id" scrollable scroll-height="25rem"><Column header="Sinh viên"><template #body="{data}"><div class="primary-cell"><strong>{{data.name}}</strong><small>{{data.student_code}} · {{data.email}}</small></div></template></Column><Column header=""><template #body="{data}"><Button icon="pi pi-times" severity="danger" text rounded aria-label="Xóa khỏi lớp" @click="removeStudentFromAdminClass(data.id)"/></template></Column><template #empty>Lớp chưa có sinh viên.</template></DataTable></template></Card>
        <Card><template #title>Sinh viên chưa có lớp ({{unassignedStudents.length}})</template><template #content><DataTable :value="unassignedStudents" :loading="loadingAdminClassStudents" data-key="id" scrollable scroll-height="25rem"><Column header="Sinh viên"><template #body="{data}"><div class="primary-cell"><strong>{{data.name}}</strong><small>{{data.student_code}} · {{data.email}}</small></div></template></Column><Column header=""><template #body="{data}"><Button icon="pi pi-arrow-right" text rounded aria-label="Thêm vào lớp" @click="assignStudentToAdminClass(data.id)"/></template></Column><template #empty>Không có sinh viên chưa xếp lớp.</template></DataTable></template></Card>
      </div>
      <Message v-else severity="info" :closable="false">Chọn khóa và lớp hành chính để bắt đầu xếp lớp.</Message>
    </template>
    <template v-else>
      <Card><template #title>Bộ lọc ghi danh</template><template #content><div class="filters"><Select v-model="enrollTermId" :options="terms" option-label="name" option-value="id" placeholder="Học kỳ *"/><Select v-model="enrollCohortId" :options="cohorts" option-label="name" option-value="id" placeholder="Tất cả khóa"/><Select v-model="enrollCourseId" :options="courses" option-label="title" option-value="id" placeholder="Tất cả môn"/></div></template></Card>
      <div v-if="enrollTermId" class="operation-grid">
        <Card><template #title>Ghi danh môn bắt buộc</template><template #content><p class="muted">Ghi danh toàn bộ sinh viên trong khóa vào các môn Core được mở.</p><Button label="Thực thi tự động" icon="pi pi-play" :disabled="!enrollCohortId" :loading="bulkEnrollLoading" fluid @click="handleBulkEnrollCore"/></template></Card>
        <Card><template #title>Ghi danh thủ công</template><template #content><div class="form-stack"><InputText v-model="manualEnrollStudentCode" placeholder="Mã sinh viên"/><Select v-model="manualEnrollCourseId" :options="courses" option-label="title" option-value="id" placeholder="Môn học"/><Select v-model="manualEnrollSectionId" :options="classSections" option-label="name" option-value="id" placeholder="Lớp học phần (tùy chọn)"/><Button label="Ghi danh" icon="pi pi-plus" :loading="manualEnrollLoading" @click="handleManualEnroll"/></div></template></Card>
        <Card><template #title>Import CSV</template><template #content><div class="form-stack"><input id="csv_upload" type="file" accept=".csv" @change="handleFileSelect"><Button label="Xem trước" icon="pi pi-upload" :disabled="!importFile" :loading="importLoading" @click="handlePreviewImport"/></div></template></Card>
      </div>
      <Message v-else severity="info" :closable="false">Chọn học kỳ để tải danh sách và thực hiện ghi danh.</Message>
      <Card v-if="importPreviewData.length"><template #title>Xem trước import ({{importPreviewData.length}} dòng)</template><template #content><DataTable :value="importPreviewData" striped-rows responsive-layout="scroll" paginator :rows="10"><Column field="row_number" header="Dòng"/><Column field="student_code" header="Mã SV"/><Column field="course_code" header="Mã môn"/><Column header="Tình trạng"><template #body="{data}"><Tag :value="data.status==='valid'?'Hợp lệ':data.message" :severity="data.status==='valid'?'success':'danger'"/></template></Column></DataTable><div class="actions"><Button label="Hủy" severity="secondary" outlined @click="importPreviewData=[];importFile=null"/><Button label="Xác nhận import" icon="pi pi-check" :loading="importLoading" @click="handleExecuteImport"/></div></template></Card>
      <Card v-if="enrollTermId"><template #title><div class="page-header"><span>Danh sách ghi danh</span><Button icon="pi pi-refresh" severity="secondary" text :loading="loadingEnrollments" @click="loadEnrollments"/></div></template><template #content>
        <DataTable :value="enrollmentsList" :loading="loadingEnrollments" data-key="id" striped-rows responsive-layout="scroll" paginator :rows="20" @sort="sortBy=$event.sortField||'';sortOrder=$event.sortOrder===1?'asc':$event.sortOrder===-1?'desc':'';loadEnrollments()">
          <Column field="user.student_code" header="Mã SV" sortable/><Column field="user.name" header="Họ tên" sortable/>
          <Column header="Môn học"><template #body="{data}"><div class="primary-cell"><strong class="wrap-text">{{data.course?.title||'—'}}</strong><Tag :value="data.course?.course_mode||'—'" severity="secondary"/></div></template></Column>
          <Column header="Khóa"><template #body="{data}">{{data.cohort?.code||'—'}}</template></Column>
          <Column header="Nguồn"><template #body="{data}"><Tag :value="data.enrollment_source||'—'" severity="secondary"/></template></Column>
          <Column header=""><template #body="{data}"><Button icon="pi pi-trash" severity="danger" text rounded aria-label="Hủy ghi danh" @click="unenrollRecord(data.id)"/></template></Column>
          <template #empty>Chưa có dữ liệu ghi danh.</template>
        </DataTable>
      </template></Card>
    </template>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>

<style scoped>.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.form-grid label,.form-stack{display:flex;flex-direction:column;gap:.45rem;color:var(--p-text-color);font-size:.85rem;font-weight:600}.form-grid .full{grid-column:1/-1}.check{flex-direction:row!important;align-items:center}.operation-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}@media(max-width:900px){.operation-grid{grid-template-columns:1fr}}@media(max-width:520px){.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}}</style>