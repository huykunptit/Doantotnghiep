<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
// Icons removed - using PrimeIcons
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useAuthTokenCookie } from '~/composables/useAuthSession'

definePageMeta({ layout: 'admin', middleware: ['auth', 'instructor'] })

type Id = number
const token = useAuthTokenCookie()
const toast = useToast()
function headers() { return token.value ? { Authorization: `Bearer ${token.value}` } : {} }

const activeTab = ref<'class-auto' | 'direct-manual' | 'enrollment-list'>('class-auto')
const loading = ref(false)
const processingEnrollment = ref(false)

const terms = ref<any[]>([])
const cohorts = ref<any[]>([])
const adminClasses = ref<any[]>([])
const courses = ref<any[]>([])
const classSections = ref<any[]>([])
const classStudents = ref<any[]>([])

const selectedCohortId = ref<Id | ''>('')
const selectedClassId = ref<Id | ''>('')
const selectedTermId = ref<Id | ''>('')

const searchStudentQuery = ref('')
const searchedStudents = ref<any[]>([])
const selectedDirectCourseId = ref<Id | ''>('')
const selectedDirectSectionId = ref<Id | ''>('')
const selectedDirectUserIds = ref<Id[]>([])

const enrollments = ref<any[]>([])
const enrollmentsPage = ref(1)
const enrollmentsTotalPages = ref(1)
const enrollmentsTotal = ref(0)
const enrollListSearchQuery = ref('')
const enrollListCourseId = ref<Id | ''>('')
const enrollListCohortId = ref<Id | ''>('')
const enrollListSource = ref('')
const enrollListClassSectionId = ref<Id | ''>('')
const classSectionsForFilter = ref<any[]>([])
const selectedEnrollmentIds = ref<Id[]>([])

const showBulkDeleteModal = ref(false)
const deleteFile = ref<File | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const deletePreviewData = ref<any>(null)
const deleteProcessing = ref(false)
const deleteStep = ref<1 | 2>(1)

const route = useRoute()

const defaultStudents = ref<any[]>([])

async function loadDefaultStudents() {
  try {
    const res = await useApi<any>('/admin/users?user_type=student&per_page=50', { headers: headers() })
    defaultStudents.value = res.data ?? res ?? []
  } catch (error) {
    console.error('load default students error', error)
  }
}

const displayStudents = computed(() => {
  if (searchStudentQuery.value.trim()) {
    return searchedStudents.value
  }
  if (selectedClassId.value) {
    return classStudents.value
  }
  return defaultStudents.value
})

function selectAllDisplayed() {
  displayStudents.value.forEach((s: any) => {
    if (!selectedDirectUserIds.value.includes(s.id)) {
      selectedDirectUserIds.value.push(s.id)
    }
  })
}

function deselectAllDisplayed() {
  displayStudents.value.forEach((s: any) => {
    const idx = selectedDirectUserIds.value.indexOf(s.id)
    if (idx > -1) {
      selectedDirectUserIds.value.splice(idx, 1)
    }
  })
}

onMounted(async () => {
  if (route.query.tab && ['class-auto','direct-manual','enrollment-list'].includes(route.query.tab as string))
    activeTab.value = route.query.tab as any
  await bootstrapFilters()
  await loadCourses()
  await loadDefaultStudents()
})

watch(selectedCohortId, () => loadAdminClasses())
watch(selectedClassId, () => loadClassStudents())
watch(selectedDirectCourseId, () => loadClassSections())
watch(activeTab, async (t) => { if (t === 'enrollment-list') { enrollmentsPage.value = 1; await Promise.all([loadEnrollments(), loadClassSectionsForFilter()]) } })
watch(enrollListCourseId, () => { enrollListClassSectionId.value = ''; loadClassSectionsForFilter() })

async function bootstrapFilters() {
  loading.value = true
  try {
    const [tRes, cRes] = await Promise.all([
      useApi<any>('/admin/academic/terms?per_page=100', { headers: headers() }),
      useApi<any>('/admin/academic/cohorts?per_page=100', { headers: headers() }),
    ])
    terms.value = tRes.data ?? tRes ?? []
    cohorts.value = cRes.data ?? cRes ?? []
    const cur = terms.value.find((t: any) => t.is_current) || terms.value[0]
    if (cur) selectedTermId.value = cur.id
    if (cohorts.value.length > 0) { selectedCohortId.value = cohorts.value[0].id; await loadAdminClasses() }
  } catch { toast.error('Không thể tải bộ lọc học vụ.') }
  finally { loading.value = false }
}

async function loadAdminClasses() {
  if (!selectedCohortId.value) return
  loading.value = true
  try {
    const res = await useApi<any>(`/admin/academic/administrative-classes?cohort_id=${selectedCohortId.value}&per_page=100`, { headers: headers() })
    adminClasses.value = res.data ?? res ?? []
    if (adminClasses.value.length > 0) { selectedClassId.value = adminClasses.value[0].id; await loadClassStudents() }
    else { selectedClassId.value = ''; classStudents.value = [] }
  } catch { toast.error('Không thể tải lớp hành chính.') }
  finally { loading.value = false }
}

async function loadClassStudents() {
  if (!selectedClassId.value) return
  loading.value = true
  try {
    const res = await useApi<any>(`/admin/users?administrative_class_id=${selectedClassId.value}&user_type=student&per_page=200`, { headers: headers() })
    classStudents.value = res.data ?? res ?? []
  } catch { console.error('load students error') }
  finally { loading.value = false }
}

async function loadCourses() {
  try {
    const res = await useApi<any>('/courses?per_page=500', { headers: headers() })
    courses.value = res.data ?? res ?? []
  } catch { console.error('load courses error') }
}

async function loadClassSections() {
  if (!selectedDirectCourseId.value) { classSections.value = []; selectedDirectSectionId.value = ''; return }
  try {
    const res = await useApi<any>(`/admin/academic/class-sections?course_id=${selectedDirectCourseId.value}&per_page=200`, { headers: headers() })
    classSections.value = res.data ?? res ?? []
    selectedDirectSectionId.value = ''
  } catch { console.error('load sections error') }
}

async function searchStudents() {
  if (!searchStudentQuery.value.trim()) { searchedStudents.value = []; return }
  loading.value = true
  try {
    const res = await useApi<any>(`/admin/users?search=${encodeURIComponent(searchStudentQuery.value.trim())}&user_type=student&per_page=30`, { headers: headers() })
    searchedStudents.value = res.data ?? res ?? []
  } catch { console.error('search error') }
  finally { loading.value = false }
}

function toggleDirectUser(id: Id) {
  const i = selectedDirectUserIds.value.indexOf(id)
  if (i > -1) selectedDirectUserIds.value.splice(i, 1)
  else selectedDirectUserIds.value.push(id)
}

async function loadEnrollments() {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(enrollmentsPage.value), per_page: '15' })
    if (enrollListCourseId.value) q.set('course_id', String(enrollListCourseId.value))
    if (selectedTermId.value) q.set('term_id', String(selectedTermId.value))
    if (enrollListCohortId.value) q.set('cohort_id', String(enrollListCohortId.value))
    if (enrollListSource.value) q.set('source', enrollListSource.value)
    if (enrollListClassSectionId.value) q.set('class_section_id', String(enrollListClassSectionId.value))
    if (enrollListSearchQuery.value.trim()) q.set('search', enrollListSearchQuery.value.trim())
    const res = await useApi<any>(`/admin/academic/enrollments?${q}`, { headers: headers() })
    enrollments.value = res.data ?? []
    enrollmentsTotalPages.value = res.last_page ?? 1
    enrollmentsTotal.value = res.total ?? 0
    selectedEnrollmentIds.value = []
  } catch { toast.error('Không thể tải danh sách ghi danh.') }
  finally { loading.value = false }
}

async function loadClassSectionsForFilter() {
  try {
    const q = new URLSearchParams({ per_page: '200' })
    if (enrollListCourseId.value) q.set('course_id', String(enrollListCourseId.value))
    if (selectedTermId.value) q.set('term_id', String(selectedTermId.value))
    const res = await useApi<any>(`/admin/academic/class-sections?${q}`, { headers: headers() })
    classSectionsForFilter.value = res.data ?? res ?? []
  } catch { /* ignore */ }
}

async function runAutoEnrollment() {
  if (!selectedCohortId.value || !selectedTermId.value) return
  const cls = adminClasses.value.find((c: any) => c.id === selectedClassId.value)
  if (!cls?.curriculum_id) { toast.error('Lớp hành chính chưa gán chương trình đào tạo.'); return }
  processingEnrollment.value = true
  try {
    const res = await useApi<any>(`/admin/academic/cohorts/${selectedCohortId.value}/enroll-core`, { method: 'POST', headers: headers(), body: { term_id: selectedTermId.value, curriculum_id: cls.curriculum_id } })
    toast.success(`Đã ghi danh tự động! Mới: ${res.created}, Đã có: ${res.skipped}`)
    await loadClassStudents()
  } catch (e: any) { toast.error(e?.data?.message || 'Không thể ghi danh tự động.') }
  finally { processingEnrollment.value = false }
}

async function runDirectManualEnrollment() {
  if (!selectedDirectCourseId.value || selectedDirectUserIds.value.length === 0) { toast.error('Chọn khóa học và ít nhất 1 sinh viên.'); return }
  processingEnrollment.value = true
  try {
    const res = await useApi<any>('/admin/academic/enrollments/manual', { method: 'POST', headers: headers(), body: { course_id: selectedDirectCourseId.value, class_section_id: selectedDirectSectionId.value ? Number(selectedDirectSectionId.value) : null, user_ids: selectedDirectUserIds.value, term_id: selectedTermId.value || null } })
    toast.success(`Ghi danh xong! Mới: ${res.created}, Đã có: ${res.skipped}`)
    selectedDirectUserIds.value = []; searchStudentQuery.value = ''; searchedStudents.value = []
  } catch (e: any) { toast.error(e?.data?.message || 'Không thể ghi danh.') }
  finally { processingEnrollment.value = false }
}

async function deleteOneEnrollment(id: Id) {
  if (!confirm('Hủy ghi danh học phần này?')) return
  loading.value = true
  try {
    await useApi<any>('/admin/academic/enrollments/delete', { method: 'POST', headers: headers(), body: { enrollment_ids: [id] } })
    toast.success('Đã hủy ghi danh.')
    await loadEnrollments()
  } catch (e: any) { toast.error(e?.data?.message || 'Không thể hủy ghi danh.') }
  finally { loading.value = false }
}

async function deleteSelectedEnrollments() {
  if (!selectedEnrollmentIds.value.length || !confirm(`Xóa ${selectedEnrollmentIds.value.length} ghi danh đã chọn?`)) return
  loading.value = true
  try {
    const res = await useApi<any>('/admin/academic/enrollments/delete', { method: 'POST', headers: headers(), body: { enrollment_ids: selectedEnrollmentIds.value } })
    toast.success(`Đã xóa ${res.deleted} ghi danh.`)
    selectedEnrollmentIds.value = []; await loadEnrollments()
  } catch (e: any) { toast.error(e?.data?.message || 'Không thể xóa.') }
  finally { loading.value = false }
}

function toggleSelectEnrollment(id: Id) { const i = selectedEnrollmentIds.value.indexOf(id); if (i > -1) selectedEnrollmentIds.value.splice(i, 1); else selectedEnrollmentIds.value.push(id) }
function toggleSelectAllEnrollments() { selectedEnrollmentIds.value = selectedEnrollmentIds.value.length === enrollments.value.length ? [] : enrollments.value.map((e: any) => e.id) }

function openBulkDeleteModal() { deleteFile.value = null; deletePreviewData.value = null; deleteStep.value = 1; showBulkDeleteModal.value = true }
function handleDeleteFileChange(e: Event) { const t = e.target as HTMLInputElement; if (t.files?.[0]) deleteFile.value = t.files[0] }

async function validateDeleteFile() {
  if (!deleteFile.value) return
  loading.value = true
  try {
    const fd = new FormData(); fd.append('file', deleteFile.value)
    const res = await useApi<any>('/admin/academic/enrollments/delete-import-preview', { method: 'POST', headers: { Authorization: `Bearer ${token.value}` }, body: fd })
    deletePreviewData.value = res; deleteStep.value = 2; toast.success('Kiểm tra tệp hoàn tất.')
  } catch (e: any) { toast.error(e?.data?.message || 'Lỗi khi đọc tệp.') }
  finally { loading.value = false }
}

async function executeBulkDelete() {
  if (!deletePreviewData.value?.import_token) return
  deleteProcessing.value = true
  try {
    const res = await useApi<any>('/admin/academic/enrollments/delete-import-execute', { method: 'POST', headers: headers(), body: { import_token: deletePreviewData.value.import_token } })
    toast.success(`Đã xóa ${res.deleted} bản ghi.`)
    showBulkDeleteModal.value = false; await loadEnrollments()
  } catch (e: any) { toast.error(e?.data?.message || 'Không thể xóa.') }
  finally { deleteProcessing.value = false }
}

function formatSource(src: string) {
  switch (src) {
    case 'manual': return 'Thủ công'
    case 'automatic': return 'Tự động'
    case 'excel_import': return 'Excel Import'
    default: return src
  }
}
</script>

<template>
  <AdminWorkspaceShell
    title="Ghi Danh Học Phần"
    description="Ghi danh tự động theo lớp hành chính, ghi danh thủ công và tra cứu danh sách đăng ký."
    :breadcrumb="['Trang chủ', 'Đào tạo & Học vụ', 'Ghi Danh']"
  >
    <!-- Tab bar -->
    <div class="enroll-tabs">
      <button class="enroll-tab" :class="{ 'is-active': activeTab === 'class-auto' }" @click="activeTab = 'class-auto'">
        <i class="pi pi-building" style="font-size:0.9375rem" /> Tự động theo lớp
      </button>
      <button class="enroll-tab" :class="{ 'is-active': activeTab === 'direct-manual' }" @click="activeTab = 'direct-manual'">
        <i class="pi pi-user" style="font-size:0.9375rem" /> Ghi danh thủ công
      </button>
      <button class="enroll-tab" :class="{ 'is-active': activeTab === 'enrollment-list' }" @click="activeTab = 'enrollment-list'">
        <i class="pi pi-book" style="font-size:0.9375rem" /> Danh sách ghi danh
      </button>
    </div>

    <!-- TAB 1: AUTO -->
    <template v-if="activeTab === 'class-auto'">
      <div class="dashboard-card crud-panel" style="padding: 24px;">
        <div style="margin-bottom: 20px;">
          <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:6px;">Cấu hình ghi danh tự động</h3>
          <p style="font-size:0.85rem; color:var(--muted); margin:0;">
            Hệ thống tự động quét tất cả sinh viên trong lớp hành chính đã chọn, đối chiếu với chương trình đào tạo (CTĐT) và ghi danh vào các học phần bắt buộc trong học kỳ này.
          </p>
        </div>

        <div class="crud-form-grid" style="padding:0; margin-bottom:20px; gap:16px;">
          <div class="crud-field">
            <span>Học kỳ ghi danh</span>
            <select v-model="selectedTermId" class="crud-select" style="width:100%;">
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div class="crud-field">
            <span>Khóa / Niên khóa</span>
            <select v-model="selectedCohortId" class="crud-select" style="width:100%;">
              <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="crud-field">
            <span>Lớp hành chính</span>
            <select v-model="selectedClassId" :disabled="!selectedCohortId" class="crud-select" style="width:100%;">
              <option value="">— Chọn lớp hành chính —</option>
              <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
            </select>
          </div>
        </div>

        <div v-if="selectedClassId" style="display:flex; align-items:center; justify-content:space-between; background:var(--bg-alt); padding:16px 20px; border-radius:12px; border:1px solid var(--border);">
          <div style="display:flex; align-items:center; gap:16px;">
            <div style="display:flex; flex-direction:column; gap:4px;">
              <strong style="font-size:0.95rem;">Lớp: {{ adminClasses.find(c => c.id === selectedClassId)?.name }} ({{ adminClasses.find(c => c.id === selectedClassId)?.code }})</strong>
              <div style="display:flex; align-items:center; gap:8px;">
                <span v-if="adminClasses.find(c => c.id === selectedClassId)?.curriculum_id" class="has-ctdt-tag" style="margin:0;">
                  <i class="pi pi-graduation-cap" style="font-size:0.875rem" /> Đã gán CTĐT
                </span>
                <span v-else class="no-ctdt-tag" style="margin:0;"><i class="pi pi-graduation-cap" style="font-size:0.875rem" /> Chưa gán CTĐT</span>
              </div>
            </div>
          </div>
          <button class="crud-primary-btn" :disabled="processingEnrollment || !adminClasses.find(c => c.id === selectedClassId)?.curriculum_id" @click="runAutoEnrollment">
            <i class="pi pi-play" style="font-size:0.9375rem" /> {{ processingEnrollment ? 'Đang ghi danh...' : 'Kích hoạt ghi danh' }}
          </button>
        </div>
      </div>

      <div class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div><p class="section-kicker">Thành viên</p><h3 class="ds-section-title">Danh sách lớp hành chính ({{ classStudents.length }})</h3></div>
        </div>
        <div v-if="loading" class="crud-empty" style="padding:2rem;">Đang tải...</div>
        <div v-else-if="classStudents.length === 0" class="crud-empty">
          <i class="pi pi-users" style="font-size:2.5rem" /><div><strong>Lớp chưa có sinh viên</strong></div>
        </div>
        <div v-else class="crud-table-wrap">
          <table class="crud-table">
            <thead><tr><th>Mã sinh viên</th><th>Họ và tên</th><th>Email</th></tr></thead>
            <tbody>
              <tr v-for="s in classStudents" :key="s.id">
                <td><span class="mono-code">{{ s.student_code }}</span></td>
                <td><strong>{{ s.name }}</strong></td>
                <td style="color:var(--muted);font-size:0.85rem;">{{ s.email }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- TAB 2: MANUAL -->
    <template v-if="activeTab === 'direct-manual'">
      <div class="manual-grid">
        <div class="dashboard-card crud-panel" style="padding: 24px;">
          <div style="margin-bottom:18px;">
            <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:4px;">1. Cấu hình lớp học</h3>
            <p style="font-size:0.8rem; color:var(--muted); margin:0;">Chọn học phần và lớp tín chỉ đích để ghi danh học viên.</p>
          </div>
          
          <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="crud-field">
              <span>Học kỳ</span>
              <select v-model="selectedTermId" class="crud-select" style="width:100%;">
                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div class="crud-field">
              <span>Học phần *</span>
              <select v-model="selectedDirectCourseId" class="crud-select" style="width:100%;">
                <option value="">— Chọn học phần —</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
              </select>
            </div>
            <div class="crud-field">
              <span>Lớp tín chỉ (Không bắt buộc)</span>
              <select v-model="selectedDirectSectionId" :disabled="!selectedDirectCourseId" class="crud-select" style="width:100%;">
                <option value="">— Không gán lớp tín chỉ —</option>
                <option v-for="s in classSections" :key="s.id" :value="s.id">{{ s.code }}</option>
              </select>
            </div>
          </div>
          
          <div style="margin-top:24px; padding-top:20px; border-top:1px dashed var(--border); display:flex; flex-direction:column; gap:12px;">
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; color:var(--muted);">
              <span>Đang chọn</span>
              <strong style="color:var(--green-deep); font-size:0.95rem;">{{ selectedDirectUserIds.length }} sinh viên</strong>
            </div>
            <button class="crud-primary-btn" style="width:100%; justify-content:center;" :disabled="!selectedDirectCourseId || !selectedDirectUserIds.length || processingEnrollment" @click="runDirectManualEnrollment">
              <i class="pi pi-user" style="font-size:1.0rem" /> {{ processingEnrollment ? 'Đang ghi danh...' : 'Xác nhận ghi danh' }}
            </button>
          </div>
        </div>
 
        <div class="dashboard-card crud-panel" style="padding: 24px;">
          <div style="margin-bottom:18px;">
            <h3 style="font-size:1.05rem; font-weight:700; margin-bottom:4px;">2. Chọn học viên</h3>
            <p style="font-size:0.8rem; color:var(--muted); margin:0;">Lọc theo lớp hành chính hoặc tìm kiếm học viên tự do.</p>
          </div>

          <div class="crud-form-grid" style="padding:0; margin-bottom:14px; gap:12px; grid-template-columns: 1fr 1fr;">
            <div class="crud-field">
              <span>Khóa học vụ</span>
              <select v-model="selectedCohortId" class="crud-select" style="width:100%;">
                <option value="">— Tất cả khóa —</option>
                <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="crud-field">
              <span>Lớp hành chính</span>
              <select v-model="selectedClassId" :disabled="!selectedCohortId" class="crud-select" style="width:100%;">
                <option value="">— Tất cả lớp —</option>
                <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
              </select>
            </div>
          </div>

          <div class="search-wrap">
            <i class="pi pi-search" style="font-size:0.9375rem" />
            <input v-model="searchStudentQuery" type="text" placeholder="Tìm theo mã SV hoặc tên học viên..." class="crud-search" style="padding-left:34px;width:100%;" @input="searchStudents" />
          </div>
          
          <div v-if="displayStudents.length > 0" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:0.8rem;">
            <span style="color:var(--muted);">Danh sách hiển thị ({{ displayStudents.length }})</span>
            <div style="display:flex; gap:8px;">
              <button type="button" class="action-btn is-edit" style="height:24px; padding:0 8px; font-size:0.7rem;" @click="selectAllDisplayed">Chọn tất cả</button>
              <button type="button" class="action-btn is-delete" style="height:24px; padding:0 8px; font-size:0.7rem;" @click="deselectAllDisplayed">Bỏ chọn tất cả</button>
            </div>
          </div>

          <div class="picker-list" style="border: 1px solid var(--border); border-radius: 12px; max-height: 380px; overflow-y: auto; background: var(--bg-alt);">
            <div v-if="!displayStudents.length" class="crud-empty" style="padding:2.5rem;font-size:0.85rem;">Không tìm thấy học viên phù hợp. Chọn lớp hành chính hoặc tìm kiếm ở trên.</div>
            <div v-else v-for="s in displayStudents" :key="s.id" class="picker-row" :class="{ 'is-sel': selectedDirectUserIds.includes(s.id) }" style="margin: 6px; border-color: transparent;" @click="toggleDirectUser(s.id)">
              <div class="pick-check"><Check v-if="selectedDirectUserIds.includes(s.id)" :size="11" /></div>
              <div style="display:flex;flex-direction:column;gap:2px;">
                <span class="mono-code" style="font-size:0.72rem;">{{ s.student_code }}</span>
                <strong style="font-size:0.88rem;">{{ s.name }}</strong>
                <span style="font-size:0.75rem;color:var(--muted);">{{ s.email }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- TAB 3: LIST -->
    <template v-if="activeTab === 'enrollment-list'">
      <div class="dashboard-card crud-panel" style="padding: 24px;">
        <!-- Header row -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:16px;">
          <div>
            <h3 style="font-size:1.1rem; font-weight:700; margin:0;">Danh sách ghi danh</h3>
            <p style="font-size:0.8rem; color:var(--muted); margin:4px 0 0 0;">Tra cứu, lọc và quản lý danh sách sinh viên ghi danh học phần.</p>
          </div>
          
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button v-if="selectedEnrollmentIds.length" class="action-btn is-delete" style="padding: 0 16px; height: 36px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;" @click="deleteSelectedEnrollments">
              <i class="pi pi-trash" style="font-size:0.9375rem" /> Xóa {{ selectedEnrollmentIds.length }} dòng
            </button>
            <button class="action-btn is-edit" style="padding: 0 16px; height: 36px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;" @click="openBulkDeleteModal">
              <i class="pi pi-upload" style="font-size:0.9375rem" /> Xóa bằng CSV
            </button>
          </div>
        </div>

        <!-- Filters row -->
        <div class="enroll-filter-grid">
          <div class="enroll-filter-search">
            <input
              v-model="enrollListSearchQuery"
              type="text"
              placeholder="Tìm theo mã SV, họ tên, email..."
              class="crud-search"
              style="width:100%;"
              @keyup.enter="enrollmentsPage = 1; loadEnrollments()"
            />
          </div>
          <select v-model="selectedTermId" class="crud-select" @change="enrollmentsPage = 1; loadEnrollments()">
            <option value="">Tất cả học kỳ</option>
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <select v-model="enrollListCohortId" class="crud-select" @change="enrollmentsPage = 1; loadEnrollments()">
            <option value="">Tất cả khóa</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="enrollListCourseId" class="crud-select" @change="enrollmentsPage = 1; loadEnrollments()">
            <option value="">Tất cả học phần</option>
            <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
          </select>
          <select v-model="enrollListClassSectionId" class="crud-select" @change="enrollmentsPage = 1; loadEnrollments()">
            <option value="">Tất cả lớp tín chỉ</option>
            <option v-for="s in classSectionsForFilter" :key="s.id" :value="s.id">{{ s.code }}</option>
          </select>
          <select v-model="enrollListSource" class="crud-select" @change="enrollmentsPage = 1; loadEnrollments()">
            <option value="">Tất cả nguồn</option>
            <option value="academic">Học vụ</option>
            <option value="manual">Thủ công</option>
            <option value="automatic">Tự động</option>
            <option value="excel_import">Excel Import</option>
          </select>
          <button class="crud-primary-btn" style="height:38px;" @click="enrollmentsPage = 1; loadEnrollments()">
            <i class="pi pi-search" style="font-size:0.9375rem" /> Lọc
          </button>
        </div>

        <!-- Table / Loader / Empty state -->
        <div v-if="loading" class="crud-empty" style="padding:4rem;">
          <div class="loader-spinner" style="border:3px solid var(--border); border-top-color:var(--green-deep); width:28px; height:28px; border-radius:50%; animation:spin 1s linear infinite; margin-bottom:12px;"></div>
          <span>Đang tải danh sách ghi danh...</span>
        </div>
        
        <div v-else-if="!enrollments.length" class="crud-empty" style="padding:4rem;">
          <i class="pi pi-book" style="font-size:2.5rem" />
          <div><strong>Không tìm thấy dữ liệu ghi danh</strong></div>
          <span style="font-size:0.8rem; color:var(--muted); margin-top:4px;">Vui lòng đổi bộ lọc hoặc thực hiện ghi danh mới.</span>
        </div>
        
        <div v-else>
          <div class="crud-table-wrap" style="border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 16px;">
            <table class="crud-table">
              <thead>
                <tr>
                  <th style="width:48px; text-align:center;"><input type="checkbox" :checked="selectedEnrollmentIds.length === enrollments.length && enrollments.length > 0" @change="toggleSelectAllEnrollments" class="crud-checkbox" /></th>
                  <th>Mã SV</th>
                  <th>Sinh viên</th>
                  <th>Học phần</th>
                  <th>Học kỳ</th>
                  <th>Lớp tín chỉ</th>
                  <th>Nguồn</th>
                  <th>Ngày ghi danh</th>
                  <th style="text-align:right; width:60px;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in enrollments" :key="e.id">
                  <td style="text-align:center;"><input type="checkbox" :checked="selectedEnrollmentIds.includes(e.id)" @change="toggleSelectEnrollment(e.id)" class="crud-checkbox" /></td>
                  <td><span class="mono-code">{{ e.user?.student_code }}</span></td>
                  <td>
                    <div style="display:flex; flex-direction:column;">
                      <strong>{{ e.user?.name }}</strong>
                      <span style="font-size:0.75rem; color:var(--muted);">{{ e.user?.email }}</span>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 500; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" :title="e.course?.title">
                      {{ e.course?.title }}
                    </div>
                  </td>
                  <td><span style="font-size:0.85rem; font-weight:500;">{{ e.term?.name || '—' }}</span></td>
                  <td>
                    <span v-if="e.class_section?.code" class="mono-code" style="color:var(--text); background:var(--bg-alt); padding:2px 6px; border-radius:4px; font-size:0.75rem; border:1px solid var(--border);">
                      {{ e.class_section?.code }}
                    </span>
                    <span v-else style="color:var(--muted);">—</span>
                  </td>
                  <td>
                    <span class="source-badge" :class="`src-${e.enrollment_source}`">
                      {{ formatSource(e.enrollment_source) }}
                    </span>
                  </td>
                  <td style="color:var(--muted); font-size:0.8rem;">
                    {{ new Date(e.enrolled_at).toLocaleDateString('vi-VN', { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
                  </td>
                  <td style="text-align:right; padding-right:16px;">
                    <button class="del-icon-btn" title="Hủy ghi danh học phần" @click="deleteOneEnrollment(e.id)">
                      <i class="pi pi-trash" style="font-size:0.9375rem" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <div class="crud-pagination" style="border:none; margin:0; padding: 4px 0 0 0;">
            <p>Hiển thị <strong>{{ enrollments.length }}</strong>/<strong>{{ enrollmentsTotal }}</strong> bản ghi — Trang <strong>{{ enrollmentsPage }}</strong> / <strong>{{ enrollmentsTotalPages }}</strong></p>
            <div class="crud-pagination-btns">
              <button class="crud-secondary-btn" style="height:32px; width:36px; padding:0; justify-content:center;" :disabled="enrollmentsPage <= 1" @click="enrollmentsPage--; loadEnrollments()">
                <i class="pi pi-chevron-left" style="font-size:1.0rem" />
              </button>
              <button class="crud-secondary-btn" style="height:32px; width:36px; padding:0; justify-content:center;" :disabled="enrollmentsPage >= enrollmentsTotalPages" @click="enrollmentsPage++; loadEnrollments()">
                <i class="pi pi-chevron-right" style="font-size:1.0rem" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Bulk Delete Modal -->
    <Teleport to="body">
      <div v-if="showBulkDeleteModal" class="crud-modal-backdrop" @click.self="showBulkDeleteModal = false">
        <div class="crud-modal">
          <div class="crud-modal-head is-danger">
            <div><p class="section-kicker">Xoá hàng loạt</p><h3>Xoá ghi danh bằng tệp CSV</h3></div>
            <button class="topbar-ghost" @click="showBulkDeleteModal = false"><i class="pi pi-times" style="font-size:1.125rem" /></button>
          </div>
          <div class="crud-modal-body" style="padding:24px 28px;">
            <div v-if="deleteStep === 1">
              <div class="dropzone" @click="fileInputRef?.click()">
                <i class="pi pi-file-excel" style="font-size:2.25rem" />
                <div v-if="!deleteFile"><strong>Chọn tệp CSV</strong><span>Mã sinh viên + mã khóa học</span></div>
                <div v-else style="color:#ef4444;"><strong>{{ deleteFile.name }}</strong><span>{{ (deleteFile.size/1024).toFixed(1) }} KB</span></div>
                <input ref="fileInputRef" type="file" accept=".csv" style="display:none;" @change="handleDeleteFileChange" />
              </div>
            </div>
            <div v-else-if="deleteStep === 2 && deletePreviewData">
              <div class="preview-stats">
                <div class="pstat"><span>Tổng dòng</span><strong>{{ deletePreviewData.total_rows }}</strong></div>
                <div class="pstat"><span style="color:#ef4444;">Có thể xoá</span><strong style="color:#ef4444;">{{ deletePreviewData.valid_rows }}</strong></div>
                <div class="pstat"><span style="color:var(--muted);">Lỗi</span><strong style="color:var(--muted);">{{ deletePreviewData.invalid_rows }}</strong></div>
              </div>
              <div class="preview-scroll">
                <table class="crud-table" style="font-size:0.8rem;">
                  <thead><tr><th>Dòng</th><th>Mã SV</th><th>Sinh viên</th><th>Mã môn</th><th>Kết quả</th></tr></thead>
                  <tbody>
                    <tr v-for="row in deletePreviewData.preview_data" :key="row.row_number">
                      <td>{{ row.row_number }}</td>
                      <td><span class="mono-code">{{ row.student_code }}</span></td>
                      <td>{{ row.student_name || '—' }}</td>
                      <td>{{ row.course_code }}</td>
                      <td :style="row.status === 'valid' ? 'color:#ef4444;' : 'color:var(--muted);'">{{ row.message }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" @click="showBulkDeleteModal = false">Đóng</button>
            <button v-if="deleteStep === 1" class="crud-primary-btn" :disabled="loading || !deleteFile" @click="validateDeleteFile">{{ loading ? 'Đang phân tích...' : 'Kiểm tra tệp' }}</button>
            <button v-else class="crud-danger-btn" :disabled="!deletePreviewData?.valid_rows || deleteProcessing" @click="executeBulkDelete">{{ deleteProcessing ? 'Đang xoá...' : `Xoá ${deletePreviewData?.valid_rows} dòng` }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.enroll-filter-grid {
  display: grid;
  grid-template-columns: 1fr repeat(5, minmax(140px, 1fr)) auto;
  gap: 10px;
  align-items: center;
  background: var(--bg-alt);
  padding: 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  margin-bottom: 20px;
}
.enroll-filter-search { grid-column: 1 / 2; }
@media (max-width: 1100px) {
  .enroll-filter-grid { grid-template-columns: 1fr 1fr; }
  .enroll-filter-search { grid-column: 1 / -1; }
}
.enroll-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 2px solid var(--line); padding-bottom: 2px; }
.enroll-tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 16px; border: none; background: none; border-radius: 10px 10px 0 0;
  font-size: 0.88rem; font-weight: 600; color: var(--muted); cursor: pointer;
  position: relative; transition: color 0.15s, background 0.15s;
}
.enroll-tab:hover { color: var(--green-deep); background: rgba(var(--green-rgb),0.04); }
.enroll-tab.is-active { color: var(--green-deep); }
.enroll-tab.is-active::after { content:''; position:absolute; bottom:-4px; left:0; right:0; height:3px; background:var(--green-deep); border-radius:99px; }

.has-ctdt-tag { display:inline-flex;align-items:center;gap:6px;background:rgba(var(--green-rgb),0.1);color:var(--green-deep);font-weight:700;font-size:0.76rem;padding:4px 10px;border-radius:99px; }
.no-ctdt-tag  { display:inline-flex;align-items:center;gap:6px;background:#fffbeb;color:#d97706;font-weight:700;font-size:0.76rem;padding:4px 10px;border-radius:99px; }

.manual-grid { display: grid; grid-template-columns: 360px 1fr; gap: 20px; align-items: start; }
@media (max-width: 900px) { .manual-grid { grid-template-columns: 1fr; } }

.form-field { display:flex;flex-direction:column;gap:6px; }
.form-field label { font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted); }

.search-wrap { position:relative; margin-bottom:12px; }
.search-ico { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none; }

.picker-list { display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto; }
.picker-row { display:flex;gap:12px;align-items:center;padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:var(--bg);cursor:pointer;transition:all 0.15s; }
.picker-row:hover { border-color:rgba(var(--green-rgb),0.3); }
.picker-row.is-sel { border-color:rgba(var(--green-rgb),0.4);background:rgba(var(--green-rgb),0.06); }
.pick-check { width:18px;height:18px;border-radius:4px;border:2px solid var(--line);display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--surface);transition:all 0.1s; }
.is-sel .pick-check { background:var(--green-deep);border-color:var(--green-deep);color:#fff; }

.mono-code { font-family:monospace;font-weight:700;color:var(--green-deep);font-size:0.85rem; }
.source-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 6px;
}
.source-badge.src-manual {
  background: rgba(59, 130, 246, 0.08);
  color: #3b82f6;
  border: 1px solid rgba(59, 130, 246, 0.15);
}
.source-badge.src-automatic {
  background: rgba(16, 185, 129, 0.08);
  color: var(--green-deep);
  border: 1px solid rgba(16, 185, 129, 0.15);
}
.source-badge.src-excel_import {
  background: rgba(139, 92, 246, 0.08);
  color: #8b5cf6;
  border: 1px solid rgba(139, 92, 246, 0.15);
}

.del-icon-btn { width:28px;height:28px;border:none;background:none;color:#ef4444;cursor:pointer;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;transition:all 0.15s; }
.del-icon-btn:hover { background:#fef2f2;color:#b91c1c; }

.crud-pagination { display:flex;align-items:center;justify-content:space-between;padding:14px 4px 2px;border-top:1px solid var(--line);margin-top:8px;font-size:0.84rem;color:var(--muted); }
.crud-pagination-btns { display:flex;gap:6px; }

.dropzone { border:2px dashed var(--line);border-radius:14px;padding:32px;text-align:center;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:10px;transition:all 0.15s; }
.dropzone:hover { border-color:rgba(239,68,68,0.35);background:#fef2f2; }
.dropzone strong { font-size:0.9rem;color:var(--text); }
.dropzone span { font-size:0.78rem;color:var(--muted); }

.preview-stats { display:flex;gap:24px;padding-bottom:12px;border-bottom:1px solid var(--line);margin-bottom:12px; }
.pstat { display:flex;flex-direction:column;gap:2px; }
.pstat span { font-size:0.75rem;color:var(--muted); }
.pstat strong { font-size:1.1rem;font-weight:800; }
.preview-scroll { max-height:220px;overflow-y:auto;border:1px solid var(--line);border-radius:10px; }
.loader-spinner {
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
