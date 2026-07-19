<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useAuthTokenCookie } from '~/composables/useAuthSession'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div>
      <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Đào tạo & Học vụ</p>
      <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Ghi Danh Học Phần</h1>
      <p class="text-sm mt-0.5" style="color:var(--muted)">Ghi danh tự động theo lớp hành chính, ghi danh thủ công và tra cứu danh sách đăng ký.</p>
    </div>
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
              <div class="pick-check"><i v-if="selectedDirectUserIds.includes(s.id)" class="pi pi-check" style="font-size:0.6875rem" /></div>
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
      <div class="flex flex-col gap-4">
        
        <!-- Filters & Toolbar (Always Open) -->
        <UiFilters
          v-model:search="enrollListSearchQuery"
          search-placeholder="Tìm sinh viên, mã SV..."
          :always-open="true"
          @submit-search="enrollmentsPage = 1; loadEnrollments()"
        >
          <template #actions>
            <button class="inline-flex items-center gap-2 h-9 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors shrink-0 cursor-pointer mr-2" type="button" @click="openBulkDeleteModal">
              <i class="pi pi-trash" />
              <span>Xóa bằng CSV</span>
            </button>
            <button v-if="selectedEnrollmentIds.length > 0" class="inline-flex items-center gap-2 h-9 px-4 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-semibold transition-colors shrink-0 cursor-pointer mr-2" type="button" @click="deleteSelectedEnrollments">
              <i class="pi pi-trash" />
              <span>Hủy ghi danh {{ selectedEnrollmentIds.length }} SV</span>
            </button>
          </template>
          <template #advanced>
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Học kỳ</span>
              <select v-model="selectedTermId" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="enrollmentsPage = 1; loadEnrollments()">
                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </label>
            
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Khóa học vụ</span>
              <select v-model="enrollListCohortId" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="enrollmentsPage = 1; loadEnrollments()">
                <option value="">Tất cả khóa</option>
                <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
            
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Học phần</span>
              <select v-model="enrollListCourseId" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="enrollmentsPage = 1; loadEnrollments()">
                <option value="">Tất cả học phần</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
              </select>
            </label>
            
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Lớp tín chỉ</span>
              <select v-model="enrollListClassSectionId" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="enrollmentsPage = 1; loadEnrollments()">
                <option value="">Tất cả lớp tín chỉ</option>
                <option v-for="s in classSectionsForFilter" :key="s.id" :value="s.id">{{ s.code }}</option>
              </select>
            </label>
            
            <label class="flex flex-col gap-1">
              <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Nguồn</span>
              <select v-model="enrollListSource" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer" @change="enrollmentsPage = 1; loadEnrollments()">
                <option value="">Tất cả nguồn</option>
                <option value="academic">Học vụ</option>
                <option value="manual">Thủ công</option>
                <option value="automatic">Tự động</option>
                <option value="excel_import">Excel Import</option>
              </select>
            </label>
          </template>
        </UiFilters>

        <!-- Table -->
        <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col gap-5">
          <UiTable
            :columns="[
              { id: 'select', accessorKey: 'select', header: '', class: 'w-[48px] text-center' },
              { id: 'student_code', accessorKey: 'user.student_code', header: 'Mã SV' },
              { id: 'student', accessorKey: 'user.name', header: 'Sinh viên' },
              { id: 'course', accessorKey: 'course.title', header: 'Học phần' },
              { id: 'term', accessorKey: 'term.name', header: 'Học kỳ' },
              { id: 'section', accessorKey: 'class_section.code', header: 'Lớp tín chỉ' },
              { id: 'source', accessorKey: 'enrollment_source', header: 'Nguồn' },
              { id: 'date', accessorKey: 'enrolled_at', header: 'Ngày ghi danh' },
              { id: 'actions', accessorKey: 'actions', header: '', class: 'text-right w-[60px]' }
            ]"
            :data="enrollments"
            :loading="loading"
          >
            <!-- Select Header -->
            <template #header-select>
              <input type="checkbox" :checked="selectedEnrollmentIds.length === enrollments.length && enrollments.length > 0" @change="toggleSelectAllEnrollments" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75] cursor-pointer" />
            </template>
            <!-- Select Cell -->
            <template #select-cell="{ row }">
              <input type="checkbox" :checked="selectedEnrollmentIds.includes(row.original.id)" @change="toggleSelectEnrollment(row.original.id)" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75] cursor-pointer" />
            </template>

            <!-- Student Code Cell -->
            <template #student_code-cell="{ row }">
              <span class="mono-code font-mono text-xs font-semibold">{{ row.original.user?.student_code }}</span>
            </template>

            <!-- Student Cell -->
            <template #student-cell="{ row }">
              <div class="flex flex-col gap-0.5">
                <strong class="text-sm font-semibold text-[var(--text)]">{{ row.original.user?.name }}</strong>
                <span class="text-xs text-[var(--muted)]">{{ row.original.user?.email }}</span>
              </div>
            </template>

            <!-- Course Cell -->
            <template #course-cell="{ row }">
              <span class="text-sm font-medium truncate block max-w-[220px]" :title="row.original.course?.title">{{ row.original.course?.title }}</span>
            </template>

            <!-- Term Cell -->
            <template #term-cell="{ row }">
              <span class="text-xs font-semibold text-[var(--text)]">{{ row.original.term?.name || '—' }}</span>
            </template>

            <!-- Section Cell -->
            <template #section-cell="{ row }">
              <span v-if="row.original.class_section?.code" class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                {{ row.original.class_section?.code }}
              </span>
              <span v-else class="text-[var(--muted)] text-xs">—</span>
            </template>

            <!-- Source Cell -->
            <template #source-cell="{ row }">
              <span class="source-badge text-[11px] font-bold" :class="`src-${row.original.enrollment_source}`">
                {{ formatSource(row.original.enrollment_source) }}
              </span>
            </template>

            <!-- Date Cell -->
            <template #date-cell="{ row }">
              <span class="text-xs text-[var(--muted)]">
                {{ new Date(row.original.enrolled_at).toLocaleDateString('vi-VN', { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
              </span>
            </template>

            <!-- Actions Cell -->
            <template #actions-cell="{ row }">
              <button class="w-7 h-7 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 flex items-center justify-center text-red-600 transition-colors cursor-pointer" title="Hủy ghi danh học phần" type="button" @click="deleteOneEnrollment(row.original.id)">
                <i class="pi pi-trash" />
              </button>
            </template>

            <template #empty>
              <div class="flex flex-col items-center justify-center py-16 gap-2 text-[var(--color-text-muted)]">
                <i class="pi pi-book text-3xl opacity-40" />
                <p class="text-sm font-medium">Không tìm thấy dữ liệu ghi danh</p>
              </div>
            </template>
          </UiTable>

          <DataTableFooter
            :current="enrollmentsPage"
            :last="enrollmentsTotalPages"
            :total="enrollmentsTotal"
            :per-page="50"
            @page="enrollmentsPage = $event; loadEnrollments()"
          />
        </section>
      </div>
    </template>

    <!-- Bulk Delete Modal -->
    <UModal
      v-model:open="showBulkDeleteModal"
      title="Xoá ghi danh bằng tệp CSV"
      subtitle="Xoá hàng loạt"
      :ui="{ width: 'max-w-lg' }"
    >
      <div class="flex flex-col gap-4 text-left">
        <div v-if="deleteStep === 1" class="flex flex-col gap-3">
          <div class="border-2 border-dashed border-[var(--line)] hover:border-red-500 rounded-2xl p-8 flex flex-col items-center justify-center gap-2 cursor-pointer transition-colors text-center" @click="fileInputRef?.click()">
            <i class="pi pi-file-excel text-3xl text-[var(--muted)]" />
            <div v-if="!deleteFile" class="flex flex-col gap-0.5">
              <strong class="text-sm">Chọn tệp CSV</strong>
              <span class="text-xs text-[var(--muted)]">Mã sinh viên + mã khóa học</span>
            </div>
            <div class="flex flex-col gap-0.5 text-red-600" v-else>
              <strong class="text-sm">{{ deleteFile.name }}</strong>
              <span class="text-xs text-[var(--muted)]">{{ (deleteFile.size/1024).toFixed(1) }} KB</span>
            </div>
            <input ref="fileInputRef" type="file" accept=".csv" style="display:none;" @change="handleDeleteFileChange" />
          </div>
        </div>

        <div v-else-if="deleteStep === 2 && deletePreviewData" class="flex flex-col gap-4">
          <div class="grid grid-cols-3 gap-4">
            <div class="border border-[var(--line)] rounded-xl p-3 text-center flex flex-col gap-0.5">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng dòng</span>
              <strong class="text-lg font-bold">{{ deletePreviewData.total_rows }}</strong>
            </div>
            <div class="border border-[var(--line)] rounded-xl p-3 text-center flex flex-col gap-0.5 bg-red-50/50 border-red-100">
              <span class="text-[10px] font-bold uppercase tracking-wider text-red-600">Có thể xoá</span>
              <strong class="text-lg font-bold text-red-600">{{ deletePreviewData.valid_rows }}</strong>
            </div>
            <div class="border border-[var(--line)] rounded-xl p-3 text-center flex flex-col gap-0.5">
              <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Lỗi</span>
              <strong class="text-lg font-bold text-[var(--muted)]">{{ deletePreviewData.invalid_rows }}</strong>
            </div>
          </div>

          <div class="border border-[var(--line)] rounded-xl overflow-hidden max-h-[220px] overflow-y-auto">
            <table class="w-full text-left text-xs divide-y divide-[var(--line)]">
              <thead class="bg-[var(--surface)] text-[var(--muted)] font-semibold">
                <tr>
                  <th class="p-2.5">Dòng</th>
                  <th class="p-2.5">Mã SV</th>
                  <th class="p-2.5">Sinh viên</th>
                  <th class="p-2.5">Mã môn</th>
                  <th class="p-2.5">Kết quả</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--line)]">
                <tr v-for="row in deletePreviewData.preview_data" :key="row.row_number" class="hover:bg-[var(--surface)] transition-colors">
                  <td class="p-2.5">{{ row.row_number }}</td>
                  <td class="p-2.5 font-mono">{{ row.student_code }}</td>
                  <td class="p-2.5 font-medium">{{ row.student_name || '—' }}</td>
                  <td class="p-2.5 font-mono">{{ row.course_code }}</td>
                  <td class="p-2.5 font-semibold" :class="row.status === 'valid' ? 'text-red-600' : 'text-[var(--muted)]'">
                    {{ row.message }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary mr-2" @click="showBulkDeleteModal = false">Đóng</button>
        <button v-if="deleteStep === 1" class="btn-primary" :disabled="loading || !deleteFile" @click="validateDeleteFile">
          {{ loading ? 'Đang phân tích...' : 'Kiểm tra tệp' }}
        </button>
        <button v-else class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors shrink-0 cursor-pointer" :disabled="!deletePreviewData?.valid_rows || deleteProcessing" @click="executeBulkDelete">
          {{ deleteProcessing ? 'Đang xoá...' : `Xoá ${deletePreviewData?.valid_rows} dòng` }}
        </button>
      </template>
    </UModal>
  </div>
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
