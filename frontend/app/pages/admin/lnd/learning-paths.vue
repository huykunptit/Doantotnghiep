<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import { 
  BookOpen, 
  Plus, 
  Trash2, 
  Edit, 
  GraduationCap, 
  Calendar,
  Layers, 
  Check, 
  X,
  PlusCircle,
  Eye
} from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor'],
  adminSearchPlaceholder: 'Tìm lộ trình đào tạo...',
})

type Id = number

interface ProgramItem {
  id: Id
  name: string
  code: string
  institution_id: Id
}

interface MajorItem {
  id: Id
  name: string
  code: string
  program_id: Id
}

interface CurriculumItem {
  id: Id
  name: string
  code: string
  program_id: Id
  major_id?: Id | null
  effective_from?: string | null
  program?: { name: string }
  major?: { name: string }
}

interface CourseItem {
  id: Id
  title: string
  credit_value?: number | null
  course_mode?: string
  status?: string
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
    id: Id;
    title: string;
    credit_value?: number | null;
    course_mode?: string;
  }
}

interface CurriculumCoursesResponse {
  curriculum: CurriculumItem
  by_term: Record<string, CurriculumCourseItem[]>
  summary?: {
    total_subjects: number
    required_subjects: number
    elective_subjects: number
    total_credits_required: number
    total_credits_elective: number
    term_count?: number
  }
}

const token = useAuthTokenCookie()
const toast = useToast()

function headers() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

const loading = ref(false)
const savingCurriculum = ref(false)
const addingCourses = ref(false)

const programs = ref<ProgramItem[]>([])
const majors = ref<MajorItem[]>([])
const curricula = ref<CurriculumItem[]>([])
const selectedProgramId = ref<Id | ''>('')
const selectedMajorId = ref<Id | ''>('')

const activeCurriculum = ref<CurriculumItem | null>(null)
const curriculumDetails = ref<CurriculumCoursesResponse | null>(null)
const selectedTermNumber = ref<number>(1)

// Modals
const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit' | 'view'>('create')
const selectedCurriculum = ref<CurriculumItem | null>(null)
const showAddCourseModal = ref(false)
const showDeleteConfirm = ref(false)
const deleteTargetId = ref<Id | null>(null)
const isDeletingCurriculum = ref(false)

// Forms
const curriculumForm = ref({
  name: '',
  code: '',
  program_id: '' as Id | '',
  major_id: '' as Id | '',
})

// Course Picker
const allCourses = ref<CourseItem[]>([])
const courseSearchQuery = ref('')
const selectedPickerCourseIds = ref<Id[]>([])
const pickerIsRequired = ref(true)
const pickerCredits = ref<number | ''>('')

onMounted(async () => {
  await loadFilterData()
  await loadCoursesList()
})

// Watch filters
watch([selectedProgramId, selectedMajorId], async () => {
  await loadCurricula()
})

async function loadFilterData() {
  loading.value = true
  try {
    const [pRes, mRes] = await Promise.all([
      useApi<{ data: ProgramItem[] }>('/admin/academic/programs?per_page=100', { headers: headers() }),
      useApi<{ data: MajorItem[] }>('/admin/academic/majors?per_page=200', { headers: headers() }),
    ])
    programs.value = pRes.data
    majors.value = mRes.data
    if (programs.value.length > 0) {
      selectedProgramId.value = programs.value[0].id
    }
  } catch (e: any) {
    toast.error('Không thể tải thông tin chương trình đào tạo/ngành.')
  } finally {
    loading.value = false
  }
}

const filteredMajors = computed(() => {
  if (!selectedProgramId.value) return []
  return majors.value.filter(m => m.program_id === selectedProgramId.value)
})

async function loadCurricula() {
  if (!selectedProgramId.value) return
  loading.value = true
  try {
    let url = `/admin/academic/curricula?program_id=${selectedProgramId.value}`
    if (selectedMajorId.value) {
      url += `&major_id=${selectedMajorId.value}`
    }
    url += '&per_page=100'
    const res = await useApi<{ data: CurriculumItem[] }>(url, { headers: headers() })
    curricula.value = res.data
    if (curricula.value.length > 0) {
      await selectCurriculum(curricula.value[0])
    } else {
      activeCurriculum.value = null
      curriculumDetails.value = null
    }
  } catch (e: any) {
    toast.error('Không thể tải danh sách lộ trình.')
  } finally {
    loading.value = false
  }
}

async function selectCurriculum(curr: CurriculumItem) {
  activeCurriculum.value = curr
  loading.value = true
  try {
    const res = await useApi<CurriculumCoursesResponse>(`/admin/academic/curricula/${curr.id}/courses`, { headers: headers() })
    curriculumDetails.value = res
  } catch (e: any) {
    toast.error('Không thể tải chi tiết lộ trình học tập.')
  } finally {
    loading.value = false
  }
}

async function loadCoursesList() {
  try {
    const res = await useApi<{ data: CourseItem[] }>('/courses?per_page=500', { headers: headers() })
    allCourses.value = res.data.filter(c => c.status === 'published')
  } catch (e) {
    console.error('Failed to load courses')
  }
}

// Open curriculum create
function openCreateModal() {
  modalMode.value = 'create'
  selectedCurriculum.value = null
  curriculumForm.value = {
    name: '',
    code: '',
    program_id: selectedProgramId.value || '',
    major_id: selectedMajorId.value || '',
  }
  modalOpen.value = true
}

// Open curriculum edit
function openEditModal(curr: CurriculumItem) {
  modalMode.value = 'edit'
  selectedCurriculum.value = curr
  curriculumForm.value = {
    name: curr.name,
    code: curr.code,
    program_id: curr.program_id,
    major_id: curr.major_id || '',
  }
  modalOpen.value = true
}

// Open curriculum view details
function openViewModal(curr: CurriculumItem) {
  modalMode.value = 'view'
  selectedCurriculum.value = curr
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedCurriculum.value = null
}

// Save Curriculum (Create / Update)
async function saveCurriculum() {
  if (!curriculumForm.value.name.trim() || !curriculumForm.value.code.trim() || !curriculumForm.value.program_id) {
    toast.error('Vui lòng điền đầy đủ các trường bắt buộc.')
    return
  }
  savingCurriculum.value = true
  try {
    const payload = {
      name: curriculumForm.value.name.trim(),
      code: curriculumForm.value.code.trim(),
      program_id: Number(curriculumForm.value.program_id),
      major_id: curriculumForm.value.major_id ? Number(curriculumForm.value.major_id) : null,
      effective_from: new Date().toISOString().split('T')[0]
    }
    
    if (selectedCurriculum.value) {
      await useApi(`/admin/academic/curricula/${selectedCurriculum.value.id}`, {
        method: 'PUT',
        headers: headers(),
        body: payload
      })
      toast.success('Đã cập nhật lộ trình thành công.')
    } else {
      await useApi('/admin/academic/curricula', {
        method: 'POST',
        headers: headers(),
        body: payload
      })
      toast.success('Đã tạo lộ trình mới thành công.')
    }
    modalOpen.value = false
    await loadCurricula()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra khi lưu lộ trình.')
  } finally {
    savingCurriculum.value = false
  }
}

// Confirm Delete Curriculum
function confirmDeleteCurriculum(curr: CurriculumItem) {
  deleteTargetId.value = curr.id
  isDeletingCurriculum.value = true
  showDeleteConfirm.value = true
}

async function executeDeleteCurriculum() {
  if (!deleteTargetId.value) return
  loading.value = true
  try {
    await useApi(`/admin/academic/curricula/${deleteTargetId.value}`, {
      method: 'DELETE',
      headers: headers()
    })
    toast.success('Đã xóa lộ trình đào tạo.')
    showDeleteConfirm.value = false
    await loadCurricula()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa lộ trình.')
  } finally {
    loading.value = false
  }
}

// Curriculum Course actions
function openAddCourseModal(term: number) {
  selectedTermNumber.value = term
  selectedPickerCourseIds.value = []
  pickerIsRequired.value = true
  pickerCredits.value = ''
  courseSearchQuery.value = ''
  showAddCourseModal.value = true
}

const unmappedCourses = computed(() => {
  if (!curriculumDetails.value) return allCourses.value
  
  // Lấy tất cả course_id đã có trong CTĐT này
  const mappedIds = new Set<number>()
  Object.values(curriculumDetails.value.by_term).forEach(list => {
    list.forEach(cc => mappedIds.add(cc.course_id))
  })
  
  let list = allCourses.value.filter(c => !mappedIds.has(c.id))
  
  if (courseSearchQuery.value.trim()) {
    const q = courseSearchQuery.value.toLowerCase().trim()
    list = list.filter(c => c.title.toLowerCase().includes(q))
  }
  
  return list
})

function togglePickerCourse(courseId: Id) {
  const index = selectedPickerCourseIds.value.indexOf(courseId)
  if (index > -1) {
    selectedPickerCourseIds.value.splice(index, 1)
  } else {
    selectedPickerCourseIds.value.push(courseId)
  }
}

async function addCoursesToCurriculum() {
  if (selectedPickerCourseIds.value.length === 0 || !activeCurriculum.value) return
  addingCourses.value = true
  try {
    const payload = selectedPickerCourseIds.value.map((id, index) => {
      const course = allCourses.value.find(c => c.id === id)
      return {
        course_id: id,
        term_number: selectedTermNumber.value,
        is_required: pickerIsRequired.value,
        credits: pickerCredits.value ? Number(pickerCredits.value) : (course?.credit_value || null),
        position: index
      }
    })

    await useApi(`/admin/academic/curricula/${activeCurriculum.value.id}/courses`, {
      method: 'POST',
      headers: headers(),
      body: payload
    })
    
    toast.success(`Đã thêm môn vào kỳ ${selectedTermNumber.value} thành công.`)
    showAddCourseModal.value = false
    if (activeCurriculum.value) {
      await selectCurriculum(activeCurriculum.value)
    }
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể thêm môn học vào lộ trình.')
  } finally {
    addingCourses.value = false
  }
}

async function removeCourseFromCurriculum(ccId: Id) {
  if (!activeCurriculum.value) return
  if (!confirm('Bạn có chắc chắn muốn xóa môn này ra khỏi lộ trình?')) return
  
  try {
    await useApi(`/admin/academic/curricula/${activeCurriculum.value.id}/courses/${ccId}`, {
      method: 'DELETE',
      headers: headers()
    })
    toast.success('Đã xóa môn khỏi lộ trình.')
    await selectCurriculum(activeCurriculum.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa môn học.')
  }
}
</script>

<template>
  <AdminWorkspaceShell
    title="Chương Trình Đào Tạo"
    description="Thiết kế và chuẩn hóa khung chương trình học theo học kỳ cho từng ngành."
    :breadcrumb="['Trang chủ', 'Đào tạo & Học vụ', 'Chương Trình Đào Tạo']"
  >
    <template #actions>
      <button class="crud-primary-btn" @click="openCreateModal">
        <Plus :size="18" /> Tạo lộ trình mới
      </button>
    </template>

    <!-- Filters -->
    <div class="lnd-filters dashboard-card">
      <div class="filter-group">
        <label class="crud-field">
          <span>Chương trình đào tạo</span>
          <select v-model="selectedProgramId" :disabled="loading">
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</option>
          </select>
        </label>
        
        <label class="crud-field">
          <span>Chuyên ngành</span>
          <select v-model="selectedMajorId" :disabled="loading">
            <option value="">-- Tất cả chuyên ngành --</option>
            <option v-for="m in filteredMajors" :key="m.id" :value="m.id">{{ m.name }} ({{ m.code }})</option>
          </select>
        </label>
      </div>
    </div>

    <!-- Main Workspace Split Grid -->
    <div class="lnd-workspace-grid">
      <!-- Left sidebar: Curricula List -->
      <div class="lnd-list-sidebar dashboard-card">
        <h4 class="section-title"><Layers :size="16" /> Danh sách lộ trình</h4>
        <div v-if="curricula.length === 0" class="empty-state">
          Không tìm thấy lộ trình nào phù hợp.
        </div>
        <div v-else class="curricula-list">
          <div 
            v-for="curr in curricula" 
            :key="curr.id" 
            class="curriculum-item-card"
            :class="{ 'is-active': activeCurriculum?.id === curr.id }"
            @click="selectCurriculum(curr)"
          >
            <div class="item-main">
              <span class="item-code">{{ curr.code }}</span>
              <strong class="item-name">{{ curr.name }}</strong>
            </div>
            <div class="item-actions">
              <button class="icon-btn text-info" @click.stop="openViewModal(curr)" title="Xem chi tiết">
                <Eye :size="14" />
              </button>
              <button class="icon-btn text-primary" @click.stop="openEditModal(curr)" title="Chỉnh sửa">
                <Edit :size="14" />
              </button>
              <button class="icon-btn text-danger" @click.stop="confirmDeleteCurriculum(curr)" title="Xóa">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right panel: Selected Curriculum Details -->
      <div class="lnd-details-panel">
        <div v-if="!activeCurriculum" class="dashboard-card empty-panel">
          <GraduationCap :size="48" class="text-muted" />
          <h3>Chưa chọn lộ trình</h3>
          <p>Vui lòng tạo hoặc chọn một lộ trình bên trái để xem và thiết kế môn học.</p>
        </div>
        
        <div v-else class="lnd-details-container">
          <!-- Top info summary -->
          <div class="dashboard-card summary-card">
            <div class="summary-head">
              <div>
                <span class="badge">{{ activeCurriculum.code }}</span>
                <h3>{{ activeCurriculum.name }}</h3>
              </div>
            </div>
            <div v-if="curriculumDetails?.summary" class="summary-details">
              <div class="detail-box">
                <span class="label">Tổng số môn</span>
                <strong class="value">{{ curriculumDetails.summary.total_subjects }} môn</strong>
              </div>
              <div class="detail-box">
                <span class="label">Môn bắt buộc</span>
                <strong class="value text-success">{{ curriculumDetails.summary.required_subjects }} môn</strong>
              </div>
              <div class="detail-box">
                <span class="label">Môn tự chọn</span>
                <strong class="value text-warning">{{ curriculumDetails.summary.elective_subjects }} môn</strong>
              </div>
              <div class="detail-box">
                <span class="label">Tổng tín chỉ</span>
                <strong class="value text-primary">
                  {{ (curriculumDetails.summary.total_credits_required || 0) + (curriculumDetails.summary.total_credits_elective || 0) }} TC
                </strong>
              </div>
            </div>
          </div>

          <!-- Academic terms mapping (1 -> 8) -->
          <div class="terms-grid">
            <div 
              v-for="term in 8" 
              :key="term" 
              class="term-section dashboard-card"
            >
              <div class="term-header">
                <div class="term-title">
                  <Calendar :size="16" class="text-primary" />
                  <h4>Học kỳ {{ term }}</h4>
                  <span class="term-count-badge" v-if="curriculumDetails?.by_term[term]">
                    {{ curriculumDetails.by_term[term].length }} môn
                  </span>
                </div>
                <button class="add-btn-small" @click="openAddCourseModal(term)">
                  <PlusCircle :size="14" /> Thêm môn
                </button>
              </div>

              <!-- List of courses in this term -->
              <div class="term-courses-list">
                <div v-if="!curriculumDetails?.by_term[term] || curriculumDetails.by_term[term].length === 0" class="no-courses">
                  Chưa xếp môn học cho học kỳ này.
                </div>
                <div 
                  v-else 
                  v-for="cc in curriculumDetails.by_term[term]" 
                  :key="cc.id"
                  class="term-course-row"
                >
                  <div class="course-info">
                    <span class="course-title">{{ cc.course?.title || `Môn học #${cc.course_id}` }}</span>
                    <div class="course-badges">
                      <span class="badge" :class="cc.is_required ? 'is-success' : 'is-muted'">
                        {{ cc.is_required ? 'Bắt buộc' : 'Tự chọn' }}
                      </span>
                      <span class="credit-span">{{ cc.credits ?? cc.course?.credit_value ?? '--' }} TC</span>
                    </div>
                  </div>
                  <button class="delete-link" @click="removeCourseFromCurriculum(cc.id)">
                    <X :size="14" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit/View Curriculum Modal -->
    <Teleport to="body">
      <div v-if="modalOpen" class="crud-modal-backdrop" @click.self="closeModal">
        <div class="crud-modal modal-lnd" :class="{ 'modal-lnd--wide': modalMode === 'view' }">
          
          <!-- Header -->
          <div class="crud-modal-head" :class="{ 'is-neutral': modalMode === 'view' }">
            <div>
              <p class="section-kicker">
                {{ modalMode === 'create' ? 'Tạo mới' : modalMode === 'edit' ? 'Chỉnh sửa' : 'Chi tiết' }}
              </p>
              <h3>
                {{ modalMode === 'create' ? 'Tạo lộ trình đào tạo mới' : modalMode === 'edit' ? 'Cập nhật lộ trình' : selectedCurriculum?.name }}
              </h3>
            </div>
            <button class="topbar-ghost" type="button" @click="closeModal">✕</button>
          </div>

          <!-- Body -->
          <div class="crud-modal-body">
            <!-- ── View Mode ── -->
            <div v-if="modalMode === 'view'" class="um-view-profile">
              <div class="um-vp-header">
                <div class="ds-avatar ds-avatar--xl">
                  <Layers :size="32" class="text-primary" />
                </div>
                <div class="um-vp-title">
                  <h4>{{ selectedCurriculum?.name }}</h4>
                  <span class="um-vp-email">Mã lộ trình: {{ selectedCurriculum?.code }}</span>
                </div>
              </div>

              <div class="um-vp-grid" style="margin-top: 24px;">
                <div class="um-vp-field">
                  <label>Chương trình đào tạo</label>
                  <p>{{ selectedCurriculum?.program?.name || '—' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Chuyên ngành</label>
                  <p>{{ selectedCurriculum?.major?.name || 'Thuộc chương trình chung (không chia ngành)' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Ngày khởi tạo</label>
                  <p>{{ selectedCurriculum && 'effective_from' in selectedCurriculum && selectedCurriculum.effective_from ? selectedCurriculum.effective_from : '—' }}</p>
                </div>
                <div class="um-vp-field">
                  <label>Tổng số tín chỉ thiết kế</label>
                  <p v-if="curriculumDetails?.summary">
                    <strong>{{ (curriculumDetails.summary.total_credits_required || 0) + (curriculumDetails.summary.total_credits_elective || 0) }}</strong> TC
                  </p>
                  <p v-else>—</p>
                </div>
              </div>
            </div>

            <!-- ── Create/Edit Form Mode ── -->
            <div v-else>
              <div v-if="modalMode === 'edit'" class="edit-banner">
                <span>Đang chỉnh sửa lộ trình đào tạo <strong>{{ selectedCurriculum?.name }}</strong>. Không thể thay đổi Mã lộ trình định danh.</span>
              </div>
              <div class="crud-form-grid" style="padding-top: 10px">
                <label class="crud-field crud-field-full">
                  <span>Tên lộ trình (CTĐT) *</span>
                  <input v-model="curriculumForm.name" placeholder="Ví dụ: Chương trình đào tạo Kỹ sư CNTT khóa 2026">
                </label>
                <label class="crud-field crud-field-full">
                  <span>Mã lộ trình *</span>
                  <input v-model="curriculumForm.code" :disabled="modalMode === 'edit'" placeholder="Ví dụ: CNTT_2026" style="background-color: var(--bg-soft, #f8fafc); cursor: not-allowed" v-if="modalMode === 'edit'">
                  <input v-model="curriculumForm.code" placeholder="Ví dụ: CNTT_2026" v-else>
                </label>
              <label class="crud-field crud-field-full">
                <span>Chương trình đào tạo *</span>
                <select v-model="curriculumForm.program_id">
                  <option value="">-- Chọn chương trình đào tạo --</option>
                  <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </label>
              <label class="crud-field crud-field-full">
                <span>Ngành học (Chuyên ngành)</span>
                <select v-model="curriculumForm.major_id">
                  <option value="">-- Thuộc chương trình chung (không chia ngành) --</option>
                  <option v-for="m in majors" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </label>
            </div>
          </div>
        </div>

          <!-- Footer -->
          <div class="crud-modal-foot" v-if="modalMode !== 'view'">
            <button class="crud-secondary-btn" type="button" @click="closeModal">Hủy</button>
            <button class="crud-primary-btn" type="button" :disabled="savingCurriculum" @click="saveCurriculum">
              {{ savingCurriculum ? 'Đang lưu...' : 'Lưu lộ trình' }}
            </button>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- Add Course Modal -->
    <Teleport to="body">
      <div v-if="showAddCourseModal" class="crud-modal-backdrop" @click.self="showAddCourseModal = false">
        <div class="crud-modal crud-modal-wide">
          
          <!-- Header -->
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Thiết kế môn học</p>
              <h3>Thêm môn học vào Học kỳ {{ selectedTermNumber }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="showAddCourseModal = false">✕</button>
          </div>

          <!-- Body -->
          <div class="crud-modal-body picker-body-wrap">
            <div class="picker-top-config" style="display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px;">
              <label class="crud-field row-layout" style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" v-model="pickerIsRequired" class="checkbox-input" />
                <span>Học phần bắt buộc</span>
              </label>
              <label class="crud-field" style="flex: 1;">
                <span>Số tín chỉ định mức (để trống nếu dùng mặc định của môn học)</span>
                <input type="number" v-model="pickerCredits" placeholder="Ví dụ: 3" min="0" />
              </label>
            </div>

            <!-- Search input with modern style -->
            <div class="picker-search-bar" style="margin: 16px 0;">
              <input type="text" v-model="courseSearchQuery" placeholder="Tìm kiếm môn học cần thêm theo tên..." class="search-input" style="width:100%; padding: 10px 14px; border: 1px solid var(--line, #dde5e1); border-radius: 8px; outline:none;" />
            </div>

            <div class="courses-selector-list">
              <div v-if="unmappedCourses.length === 0" class="empty-state">
                Không tìm thấy môn học nào khả dụng.
              </div>
              <div 
                v-for="course in unmappedCourses" 
                :key="course.id"
                class="picker-course-item"
                :class="{ 'is-selected': selectedPickerCourseIds.includes(course.id) }"
                @click="togglePickerCourse(course.id)"
              >
                <div class="checkbox-box">
                  <span class="check-indicator" v-if="selectedPickerCourseIds.includes(course.id)"><Check :size="12" /></span>
                </div>
                <div class="course-text">
                  <strong>{{ course.title }}</strong>
                  <span class="mode-badge">{{ course.course_mode === 'core' ? 'Chính khóa' : 'Dịch vụ/Bán' }} · {{ course.credit_value ?? 0 }} TC</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="crud-modal-foot">
            <span class="selected-count" style="margin-right:auto; font-size:0.9rem; color:var(--muted)">
              Đã chọn <strong>{{ selectedPickerCourseIds.length }}</strong> môn học
            </span>
            <button class="crud-secondary-btn" type="button" @click="showAddCourseModal = false">Hủy</button>
            <button class="crud-primary-btn" type="button" :disabled="selectedPickerCourseIds.length === 0 || addingCourses" @click="addCoursesToCurriculum">
              {{ addingCourses ? 'Đang thêm...' : 'Xác nhận Thêm' }}
            </button>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- Confirm Delete Curriculum Modal -->
    <CrudConfirmModal
      :open="showDeleteConfirm && isDeletingCurriculum"
      title="Xóa Lộ Trình Đào Tạo"
      description="Bạn có chắc chắn muốn xóa lộ trình đào tạo này không? Tất cả các thông tin định mức môn học đi kèm sẽ bị xóa hoàn toàn khỏi lộ trình này."
      confirm-text="Xóa vĩnh viễn"
      :loading="loading"
      @close="showDeleteConfirm = false"
      @confirm="executeDeleteCurriculum"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.lnd-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.lnd-filters {
  margin-top: 16px;
  padding: 16px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.filter-group {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.filter-group .crud-field {
  flex: 1;
  min-width: 240px;
}

.lnd-workspace-grid {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 20px;
  margin-top: 20px;
  align-items: start;
}

@media (max-width: 960px) {
  .lnd-workspace-grid {
    grid-template-columns: 1fr;
  }
}

.lnd-list-sidebar {
  padding: 16px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  font-weight: 700;
  color: var(--text-color, #333);
}

.curricula-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 560px;
  overflow-y: auto;
}

.curriculum-item-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgba(0, 0, 0, 0.04);
  background: rgba(255, 255, 255, 0.72);
  cursor: pointer;
  transition: all 180ms ease;
}

.curriculum-item-card:hover {
  transform: translateY(-1px);
  border-color: rgba(var(--green-rgb, 16, 185, 129), 0.2);
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.02);
}

.curriculum-item-card.is-active {
  border-color: rgba(var(--green-rgb, 16, 185, 129), 0.35);
  background: rgba(var(--green-rgb, 16, 185, 129), 0.06);
}

.item-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.item-code {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--green-deep, #047857);
  background: rgba(var(--green-rgb, 16, 185, 129), 0.08);
  padding: 2px 6px;
  border-radius: 4px;
  align-self: flex-start;
}

.item-name {
  font-size: 0.88rem;
  color: #333;
  line-height: 1.35;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 160ms ease;
}

.curriculum-item-card:hover .item-actions {
  opacity: 1;
}

.icon-btn {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  cursor: pointer;
  color: #888;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 120ms ease;
}

.icon-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

.icon-btn.text-primary:hover {
  color: var(--green-deep, #047857);
  background: rgba(var(--green-rgb, 16, 185, 129), 0.1);
}

.icon-btn.text-danger:hover {
  color: #ef4444;
  background: #fef2f2;
}

.lnd-details-panel {
  flex: 1;
}

.empty-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  text-align: center;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.empty-panel h3 {
  margin-top: 16px;
  font-weight: 600;
  color: #444;
}

.empty-panel p {
  color: #888;
  font-size: 0.9rem;
  margin-top: 6px;
  max-width: 320px;
}

.lnd-details-container {
  display: grid;
  gap: 20px;
}

.summary-card {
  padding: 20px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.summary-head h3 {
  font-size: 1.2rem;
  font-weight: 700;
  margin-top: 6px;
}

.summary-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px dashed rgba(0, 0, 0, 0.08);
}

.detail-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-box .label {
  font-size: 0.78rem;
  color: #888;
}

.detail-box .value {
  font-size: 1.05rem;
  font-weight: 700;
}

.terms-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 1200px) {
  .terms-grid {
    grid-template-columns: 1fr;
  }
}

.term-section {
  padding: 16px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  display: flex;
  flex-direction: column;
  height: 100%;
}

.term-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.term-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.term-title h4 {
  font-weight: 700;
  font-size: 0.95rem;
  color: #333;
}

.term-count-badge {
  font-size: 0.75rem;
  background: rgba(0, 0, 0, 0.05);
  padding: 1px 6px;
  border-radius: 8px;
  font-weight: 600;
  color: #666;
}

.add-btn-small {
  background: rgba(var(--green-rgb, 16, 185, 129), 0.08);
  border: 1px solid rgba(var(--green-rgb, 16, 185, 129), 0.15);
  color: var(--green-deep, #047857);
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 120ms ease;
}

.add-btn-small:hover {
  background: var(--green-deep, #047857);
  color: #fff;
}

.term-courses-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
  flex: 1;
}

.no-courses {
  padding: 24px 12px;
  text-align: center;
  color: #aaa;
  font-size: 0.82rem;
  font-style: italic;
}

.term-course-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-radius: 10px;
  background: #fafafa;
  border: 1px solid rgba(0, 0, 0, 0.02);
}

.course-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.course-title {
  font-size: 0.82rem;
  font-weight: 600;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.course-badges {
  display: flex;
  align-items: center;
  gap: 8px;
}

.badge {
  font-size: 0.7rem;
  padding: 1px 5px;
  border-radius: 4px;
  font-weight: 700;
}

.badge.is-success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.badge.is-muted {
  background: rgba(120, 120, 120, 0.1);
  color: #666;
}

.credit-span {
  font-size: 0.72rem;
  color: #888;
  font-weight: 600;
}

.delete-link {
  background: none;
  border: none;
  padding: 4px;
  border-radius: 4px;
  color: #bbb;
  cursor: pointer;
  transition: all 120ms ease;
  display: inline-flex;
  align-items: center;
}

.delete-link:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

.icon-btn.text-info:hover {
  color: #0284c7;
  background: rgba(2, 132, 199, 0.1);
}

/* Modal sizing */
.modal-lnd {
  width: min(100%, 640px) !important;
}
.modal-lnd--wide {
  width: min(100%, 780px) !important;
}

/* Scroll and body padding */
.crud-modal-body {
  padding: 24px 28px;
  max-height: 70vh;
  overflow-y: auto;
}

/* Profile Detail View classes */
.um-view-profile {
  padding: 0 4px;
}
.um-vp-header {
  display: flex;
  align-items: center;
  gap: 20px;
}
.um-vp-title {
  display: flex;
  flex-direction: column;
}
.um-vp-title h4 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text);
}
.um-vp-email {
  color: var(--muted);
  font-size: 0.9rem;
}
.um-vp-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-top: 24px;
  border-top: 1px solid var(--line, #dde5e1);
  padding-top: 24px;
}
.um-vp-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 16px;
  background: var(--bg-soft, #f8fafc);
  border: 1px solid var(--line, #e2e8f0);
  border-radius: 12px;
  transition: all 0.2s ease;
}
.um-vp-field:hover {
  background: var(--surface-hover, #f1f5f9);
  border-color: rgba(29, 158, 117, 0.2);
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
}
.um-vp-field label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted, #64748b);
}
.um-vp-field p {
  margin: 0;
  font-size: 0.92rem;
  color: var(--text, #1e293b);
  font-weight: 600;
}
.edit-banner {
  margin: 16px 28px 4px;
  padding: 10px 14px;
  background: rgba(245, 158, 11, 0.06);
  border: 1px solid rgba(245, 158, 11, 0.15);
  border-radius: 10px;
  font-size: 0.82rem;
  color: #d97706;
  font-weight: 500;
}

.picker-body-wrap {
  padding: 24px 28px;
  max-height: 72vh;
  overflow-y: auto;
}

.courses-selector-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  max-height: 240px;
  overflow-y: auto;
  padding: 2px;
}

@media (max-width: 600px) {
  .courses-selector-list {
    grid-template-columns: 1fr;
  }
}

.picker-course-item {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid rgba(0, 0, 0, 0.04);
  background: #fafafa;
  cursor: pointer;
  transition: all 120ms ease;
}

.picker-course-item:hover {
  background: rgba(0, 0, 0, 0.02);
  border-color: rgba(0, 0, 0, 0.08);
}

.picker-course-item.is-selected {
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

.course-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.course-text strong {
  font-size: 0.8rem;
  color: #333;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.is-selected .course-text strong {
  color: var(--green-deep, #047857);
}

.mode-badge {
  font-size: 0.68rem;
  color: #888;
}

.selected-count {
  margin-right: auto;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--green-deep, #047857);
}
</style>
