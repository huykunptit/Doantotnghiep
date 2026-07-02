<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import { Users, BookOpen, Search, UserCheck, Building, GraduationCap, Trash2, Trash, Upload, X, FileSpreadsheet, ChevronLeft, ChevronRight, Check, Play } from 'lucide-vue-next'
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
const selectedEnrollmentIds = ref<Id[]>([])

const showBulkDeleteModal = ref(false)
const deleteFile = ref<File | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const deletePreviewData = ref<any>(null)
const deleteProcessing = ref(false)
const deleteStep = ref<1 | 2>(1)

const route = useRoute()

onMounted(async () => {
  if (route.query.tab && ['class-auto','direct-manual','enrollment-list'].includes(route.query.tab as string))
    activeTab.value = route.query.tab as any
  await bootstrapFilters()
  await loadCourses()
})

watch(selectedCohortId, () => loadAdminClasses())
watch(selectedClassId, () => loadClassStudents())
watch(selectedDirectCourseId, () => loadClassSections())
watch(activeTab, async (t) => { if (t === 'enrollment-list') { enrollmentsPage.value = 1; await loadEnrollments() } })

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
    let url = `/admin/academic/enrollments?page=${enrollmentsPage.value}&per_page=15`
    if (enrollListCourseId.value) url += `&course_id=${enrollListCourseId.value}`
    if (selectedTermId.value) url += `&term_id=${selectedTermId.value}`
    if (enrollListSearchQuery.value.trim()) url += `&search=${encodeURIComponent(enrollListSearchQuery.value.trim())}`
    const res = await useApi<any>(url, { headers: headers() })
    enrollments.value = res.data ?? []
    enrollmentsTotalPages.value = res.last_page ?? 1
    enrollmentsTotal.value = res.total ?? 0
    selectedEnrollmentIds.value = []
  } catch { toast.error('Không thể tải danh sách ghi danh.') }
  finally { loading.value = false }
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
        <Building :size="15" /> Tự động theo lớp
      </button>
      <button class="enroll-tab" :class="{ 'is-active': activeTab === 'direct-manual' }" @click="activeTab = 'direct-manual'">
        <UserCheck :size="15" /> Ghi danh thủ công
      </button>
      <button class="enroll-tab" :class="{ 'is-active': activeTab === 'enrollment-list' }" @click="activeTab = 'enrollment-list'">
        <BookOpen :size="15" /> Danh sách ghi danh
      </button>
    </div>

    <!-- TAB 1: AUTO -->
    <template v-if="activeTab === 'class-auto'">
      <div class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div class="crud-toolbar-main">
            <select v-model="selectedTermId" class="crud-search" style="max-width:220px;">
              <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <select v-model="selectedCohortId" class="crud-search" style="max-width:200px;">
              <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="selectedClassId" :disabled="!selectedCohortId" class="crud-search" style="max-width:220px;">
              <option value="">— Chọn lớp hành chính —</option>
              <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
            </select>
          </div>
          <div v-if="selectedClassId" style="display:flex;align-items:center;gap:12px;">
            <span v-if="adminClasses.find(c => c.id === selectedClassId)?.curriculum_id" class="has-ctdt-tag">
              <GraduationCap :size="14" /> Đã gán CTĐT
            </span>
            <span v-else class="no-ctdt-tag"><GraduationCap :size="14" /> Chưa gán CTĐT</span>
            <button class="crud-primary-btn" :disabled="processingEnrollment || !adminClasses.find(c => c.id === selectedClassId)?.curriculum_id" @click="runAutoEnrollment">
              <Play :size="15" /> {{ processingEnrollment ? 'Đang ghi danh...' : 'Kích hoạt ghi danh' }}
            </button>
          </div>
        </div>
      </div>

      <div class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div><p class="section-kicker">Thành viên</p><h3 class="ds-section-title">Danh sách lớp hành chính ({{ classStudents.length }})</h3></div>
        </div>
        <div v-if="loading" class="crud-empty" style="padding:2rem;">Đang tải...</div>
        <div v-else-if="classStudents.length === 0" class="crud-empty">
          <Users :size="40" style="opacity:0.2;" /><div><strong>Lớp chưa có sinh viên</strong></div>
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
        <div class="dashboard-card crud-panel">
          <div class="crud-toolbar"><div><p class="section-kicker">Cấu hình</p><h3 class="ds-section-title">Chọn học phần & học kỳ</h3></div></div>
          <div style="display:flex;flex-direction:column;gap:14px;margin-top:8px;">
            <div class="form-field">
              <label>Học kỳ</label>
              <select v-model="selectedTermId" class="crud-search" style="width:100%;">
                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <div class="form-field">
              <label>Khóa học (Học phần) *</label>
              <select v-model="selectedDirectCourseId" class="crud-search" style="width:100%;">
                <option value="">— Chọn khóa học —</option>
                <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
              </select>
            </div>
            <div class="form-field">
              <label>Lớp tín chỉ (tuỳ chọn)</label>
              <select v-model="selectedDirectSectionId" :disabled="!selectedDirectCourseId" class="crud-search" style="width:100%;">
                <option value="">— Không gán lớp tín chỉ —</option>
                <option v-for="s in classSections" :key="s.id" :value="s.id">{{ s.code }}</option>
              </select>
            </div>
          </div>
          <div style="margin-top:20px;padding-top:16px;border-top:1px dashed var(--line);display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;color:var(--muted);">
              <span>Đang chọn</span>
              <strong style="color:var(--green-deep);">{{ selectedDirectUserIds.length }} sinh viên</strong>
            </div>
            <button class="crud-primary-btn" style="width:100%;" :disabled="!selectedDirectCourseId || !selectedDirectUserIds.length || processingEnrollment" @click="runDirectManualEnrollment">
              <UserCheck :size="16" /> {{ processingEnrollment ? 'Đang ghi danh...' : 'Xác nhận ghi danh' }}
            </button>
          </div>
        </div>

        <div class="dashboard-card crud-panel">
          <div class="crud-toolbar"><div><p class="section-kicker">Tìm kiếm</p><h3 class="ds-section-title">Chọn sinh viên</h3></div></div>
          <div class="search-wrap">
            <Search :size="15" class="search-ico" />
            <input v-model="searchStudentQuery" type="text" placeholder="Mã SV hoặc tên..." class="crud-search" style="padding-left:34px;width:100%;" @input="searchStudents" />
          </div>
          <div class="picker-list">
            <div v-if="!searchedStudents.length" class="crud-empty" style="padding:2rem;font-size:0.85rem;">Nhập mã hoặc tên để tìm sinh viên.</div>
            <div v-else v-for="s in searchedStudents" :key="s.id" class="picker-row" :class="{ 'is-sel': selectedDirectUserIds.includes(s.id) }" @click="toggleDirectUser(s.id)">
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
      <div class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div class="crud-toolbar-main">
            <input v-model="enrollListSearchQuery" type="text" placeholder="Tìm mã SV, tên..." class="crud-search" style="max-width:220px;" @keyup.enter="loadEnrollments" />
            <select v-model="enrollListCourseId" class="crud-search" style="max-width:220px;">
              <option value="">Tất cả học phần</option>
              <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <button class="crud-primary-btn" @click="loadEnrollments"><Search :size="14" /> Tìm</button>
          </div>
          <div style="display:flex;gap:8px;">
            <button v-if="selectedEnrollmentIds.length" class="crud-danger-btn" @click="deleteSelectedEnrollments">
              <Trash :size="14" /> Xoá {{ selectedEnrollmentIds.length }} đã chọn
            </button>
            <button class="crud-secondary-btn" @click="openBulkDeleteModal">
              <Upload :size="14" /> Xoá bằng CSV
            </button>
          </div>
        </div>
      </div>

      <div class="dashboard-card crud-panel">
        <div v-if="loading" class="crud-empty" style="padding:2rem;">Đang tải...</div>
        <div v-else-if="!enrollments.length" class="crud-empty">
          <BookOpen :size="40" style="opacity:0.2;" /><div><strong>Không có dữ liệu</strong></div>
        </div>
        <div v-else class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" :checked="selectedEnrollmentIds.length === enrollments.length && enrollments.length > 0" @change="toggleSelectAllEnrollments" /></th>
                <th>Mã SV</th><th>Sinh viên</th><th>Học phần</th><th>Học kỳ</th><th>Lớp tín chỉ</th>
                <th>Nguồn</th><th>Ngày</th><th style="text-align:right;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in enrollments" :key="e.id">
                <td><input type="checkbox" :checked="selectedEnrollmentIds.includes(e.id)" @change="toggleSelectEnrollment(e.id)" /></td>
                <td><span class="mono-code">{{ e.user?.student_code }}</span></td>
                <td><strong>{{ e.user?.name }}</strong></td>
                <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ e.course?.title }}</td>
                <td>{{ e.term?.name || '—' }}</td>
                <td>{{ e.class_section?.code || '—' }}</td>
                <td><span class="source-tag" :class="`src-${e.enrollment_source}`">{{ e.enrollment_source }}</span></td>
                <td style="color:var(--muted);font-size:0.82rem;">{{ new Date(e.enrolled_at).toLocaleDateString('vi-VN') }}</td>
                <td style="text-align:right;"><button class="del-icon-btn" @click="deleteOneEnrollment(e.id)"><Trash2 :size="14" /></button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="enrollmentsTotalPages > 1" class="crud-pagination">
          <p>Tổng <strong>{{ enrollmentsTotal }}</strong> bản ghi — Trang <strong>{{ enrollmentsPage }}</strong> / {{ enrollmentsTotalPages }}</p>
          <div class="crud-pagination-btns">
            <button class="crud-secondary-btn" :disabled="enrollmentsPage <= 1" @click="enrollmentsPage--; loadEnrollments()"><ChevronLeft :size="15" /></button>
            <button class="crud-secondary-btn" :disabled="enrollmentsPage >= enrollmentsTotalPages" @click="enrollmentsPage++; loadEnrollments()"><ChevronRight :size="15" /></button>
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
            <button class="topbar-ghost" @click="showBulkDeleteModal = false"><X :size="18" /></button>
          </div>
          <div class="crud-modal-body" style="padding:24px 28px;">
            <div v-if="deleteStep === 1">
              <div class="dropzone" @click="fileInputRef?.click()">
                <FileSpreadsheet :size="36" style="color:var(--muted);opacity:0.5;" />
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
.source-tag { font-size:0.7rem;font-weight:700;text-transform:uppercase;padding:2px 7px;border-radius:5px; }
.src-manual    { background:rgba(59,130,246,0.08);color:#3b82f6; }
.src-automatic { background:rgba(var(--green-rgb),0.08);color:var(--green-deep); }
.src-excel_import { background:rgba(139,92,246,0.08);color:#8b5cf6; }

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
</style>
