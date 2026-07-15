<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const toast = useToast()
const headers = () => token.value ? { Authorization: `Bearer ${token.value}` } : {}

// ─── Types ───────────────────────────────────────────────────────────────────
interface AdminClass {
  id: number
  code: string
  name: string
  status: string
  capacity: number
  expected_graduation_year?: number | null
  description?: string | null
  program?: { id: number; code: string; name: string } | null
  unit?: { id: number; code: string; name: string } | null
  cohort?: { id: number; code: string; name: string; start_year: number } | null
  advisor?: { id: number; name: string; email: string; staff_code?: string } | null
  curriculum?: { id: number; code: string; name: string } | null
  students_count?: number
}

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(false)
const saving = ref(false)
const classes = ref<AdminClass[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const searchQuery = ref('')
const filterCohortId = ref<number | ''>('')
const filterStatus = ref('')

// Related data for dropdowns
const cohorts = ref<any[]>([])
const programs = ref<any[]>([])
const units = ref<any[]>([])
const curricula = ref<any[]>([])
const advisors = ref<any[]>([])

// Modals
const createOpen = ref(false)
const editOpen = ref(false)
const deleteOpen = ref(false)
const selectedClass = ref<AdminClass | null>(null)

// ─── Enroll modal state ───────────────────────────────────────────────────────
const enrollOpen = ref(false)
const enrollTab = ref<'list' | 'import'>('list')

// Tab: chọn từ list
const enrollSearchQuery = ref('')
const enrollSearchResults = ref<any[]>([])
const enrollDefaultStudents = ref<any[]>([])
const enrollSearchLoading = ref(false)
const selectedEnrollIds = ref<number[]>([])
const enrollSaving = ref(false)

const enrollDisplayStudents = computed(() =>
  enrollSearchQuery.value.trim() ? enrollSearchResults.value : enrollDefaultStudents.value
)

// Tab: import CSV
const importFile = ref<File | null>(null)
const importFileRef = ref<HTMLInputElement | null>(null)
const importStep = ref<1 | 2>(1)
const importPreviewData = ref<any>(null)
const importLoading = ref(false)
const importExecuting = ref(false)

const formDefaults = () => ({
  institution_id: 1,
  unit_id: '' as number | '',
  program_id: '' as number | '',
  major_id: null as number | null,
  cohort_id: '' as number | '',
  advisor_id: null as number | null,
  curriculum_id: null as number | null,
  code: '',
  name: '',
  capacity: 40,
  expected_graduation_year: null as number | null,
  status: 'active',
  description: '',
})

const form = reactive(formDefaults())

const statusOptions = [
  { label: 'Tất cả', value: '' },
  { label: 'Đang hoạt động', value: 'active' },
  { label: 'Tốt nghiệp', value: 'graduated' },
  { label: 'Tạm ngưng', value: 'suspended' },
]

// ─── Computed ─────────────────────────────────────────────────────────────────
const activeCount = computed(() => classes.value.filter(c => c.status === 'active').length)
const totalStudents = computed(() => classes.value.reduce((s, c) => s + (c.students_count ?? 0), 0))

// ─── API ──────────────────────────────────────────────────────────────────────
async function fetchClasses(page = 1) {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (searchQuery.value.trim()) q.set('q', searchQuery.value.trim())
    if (filterCohortId.value) q.set('cohort_id', String(filterCohortId.value))
    if (filterStatus.value) q.set('status', filterStatus.value)

    const res = await useApi<any>(`/admin/academic/administrative-classes?${q}`, { headers: headers() })
    classes.value = res.data ?? []
    currentPage.value = res.current_page ?? 1
    lastPage.value = res.last_page ?? 1
    total.value = res.total ?? 0
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tải danh sách lớp hành chính.')
  } finally {
    loading.value = false
  }
}

async function fetchDropdowns() {
  try {
    const [cohortRes, programRes, unitRes, curriculaRes] = await Promise.all([
      useApi<any>('/admin/academic/cohorts?per_page=200', { headers: headers() }),
      useApi<any>('/admin/academic/programs?per_page=200', { headers: headers() }),
      useApi<any>('/admin/academic/units?per_page=200', { headers: headers() }),
      useApi<any>('/admin/academic/curricula?per_page=200', { headers: headers() }),
    ])
    cohorts.value = cohortRes.data ?? cohortRes ?? []
    programs.value = programRes.data ?? programRes ?? []
    units.value = unitRes.data ?? unitRes ?? []
    curricula.value = curriculaRes.data ?? curriculaRes ?? []
  } catch {
    // non-fatal
  }
}

async function fetchAdvisors(q = '') {
  try {
    const res = await useApi<any>(`/admin/users?user_type=instructor&per_page=50${q ? `&search=${encodeURIComponent(q)}` : ''}`, { headers: headers() })
    advisors.value = res.data ?? []
  } catch { /* ignore */ }
}

async function createClass() {
  if (!form.code.trim() || !form.name.trim() || !form.cohort_id || !form.program_id || !form.unit_id) {
    toast.error('Vui lòng điền đầy đủ các trường bắt buộc.')
    return
  }
  saving.value = true
  try {
    await useApi('/admin/academic/administrative-classes', {
      method: 'POST',
      headers: headers(),
      body: {
        institution_id: form.institution_id,
        unit_id: Number(form.unit_id),
        program_id: Number(form.program_id),
        cohort_id: Number(form.cohort_id),
        advisor_id: form.advisor_id ? Number(form.advisor_id) : null,
        curriculum_id: form.curriculum_id ? Number(form.curriculum_id) : null,
        major_id: form.major_id ? Number(form.major_id) : null,
        code: form.code.trim(),
        name: form.name.trim(),
        capacity: Number(form.capacity) || 40,
        expected_graduation_year: form.expected_graduation_year ? Number(form.expected_graduation_year) : null,
        status: form.status,
        description: form.description || null,
      },
    })
    toast.success('Đã tạo lớp hành chính mới.')
    createOpen.value = false
    Object.assign(form, formDefaults())
    await fetchClasses(1)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tạo lớp hành chính.')
  } finally {
    saving.value = false
  }
}

async function updateClass() {
  if (!selectedClass.value) return
  if (!form.code.trim() || !form.name.trim()) {
    toast.error('Mã lớp và tên lớp không được để trống.')
    return
  }
  saving.value = true
  try {
    await useApi(`/admin/academic/administrative-classes/${selectedClass.value.id}`, {
      method: 'PUT',
      headers: headers(),
      body: {
        institution_id: form.institution_id,
        unit_id: Number(form.unit_id),
        program_id: Number(form.program_id),
        cohort_id: Number(form.cohort_id),
        advisor_id: form.advisor_id ? Number(form.advisor_id) : null,
        curriculum_id: form.curriculum_id ? Number(form.curriculum_id) : null,
        major_id: form.major_id ? Number(form.major_id) : null,
        code: form.code.trim(),
        name: form.name.trim(),
        capacity: Number(form.capacity) || 40,
        expected_graduation_year: form.expected_graduation_year ? Number(form.expected_graduation_year) : null,
        status: form.status,
        description: form.description || null,
      },
    })
    toast.success('Đã cập nhật lớp hành chính.')
    editOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể cập nhật lớp hành chính.')
  } finally {
    saving.value = false
  }
}

async function deleteClass() {
  if (!selectedClass.value) return
  saving.value = true
  try {
    await useApi(`/admin/academic/administrative-classes/${selectedClass.value.id}`, {
      method: 'DELETE',
      headers: headers(),
    })
    toast.success(`Đã xóa lớp "${selectedClass.value.name}".`)
    deleteOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa lớp hành chính.')
  } finally {
    saving.value = false
  }
}

// ─── Enroll functions ─────────────────────────────────────────────────────────
async function openEnroll(cls: AdminClass) {
  selectedClass.value = cls
  enrollTab.value = 'list'
  selectedEnrollIds.value = []
  enrollSearchQuery.value = ''
  enrollSearchResults.value = []
  importFile.value = null
  importPreviewData.value = null
  importStep.value = 1
  enrollOpen.value = true
  await loadEnrollDefaultStudents()
}

async function loadEnrollDefaultStudents() {
  try {
    const res = await useApi<any>('/admin/users?user_type=student&per_page=50', { headers: headers() })
    enrollDefaultStudents.value = res.data ?? []
  } catch { /* ignore */ }
}

async function searchEnrollStudents() {
  if (!enrollSearchQuery.value.trim()) { enrollSearchResults.value = []; return }
  enrollSearchLoading.value = true
  try {
    const res = await useApi<any>(`/admin/users?search=${encodeURIComponent(enrollSearchQuery.value.trim())}&user_type=student&per_page=30`, { headers: headers() })
    enrollSearchResults.value = res.data ?? []
  } catch { /* ignore */ }
  finally { enrollSearchLoading.value = false }
}

function toggleEnrollStudent(id: number) {
  const i = selectedEnrollIds.value.indexOf(id)
  if (i > -1) selectedEnrollIds.value.splice(i, 1)
  else selectedEnrollIds.value.push(id)
}

async function submitEnrollFromList() {
  if (!selectedClass.value || selectedEnrollIds.value.length === 0) {
    toast.error('Chọn ít nhất 1 sinh viên để ghi danh.')
    return
  }
  enrollSaving.value = true
  try {
    const res = await useApi<any>('/admin/academic/administrative-classes/enroll-students', {
      method: 'POST',
      headers: headers(),
      body: {
        administrative_class_id: selectedClass.value.id,
        student_ids: selectedEnrollIds.value,
      },
    })
    toast.success(res.message ?? `Đã ghi danh ${res.updated} sinh viên.`)
    enrollOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể ghi danh.')
  } finally {
    enrollSaving.value = false
  }
}

function handleImportFileChange(e: Event) {
  const t = e.target as HTMLInputElement
  if (t.files?.[0]) importFile.value = t.files[0]
}

async function previewImport() {
  if (!importFile.value || !selectedClass.value) return
  importLoading.value = true
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)
    fd.append('administrative_class_id', String(selectedClass.value.id))
    const res = await useApi<any>('/admin/academic/administrative-classes/import-students-preview', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: fd,
    })
    importPreviewData.value = res
    importStep.value = 2
    toast.success('Kiểm tra tệp hoàn tất.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Lỗi khi đọc tệp.')
  } finally {
    importLoading.value = false
  }
}

async function executeImport() {
  if (!importPreviewData.value?.import_token) return
  importExecuting.value = true
  try {
    const res = await useApi<any>('/admin/academic/administrative-classes/import-students-execute', {
      method: 'POST',
      headers: headers(),
      body: { import_token: importPreviewData.value.import_token },
    })
    toast.success(`Đã ghi danh ${res.updated ?? 0} sinh viên từ tệp.`)
    enrollOpen.value = false
    await fetchClasses(currentPage.value)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể thực hiện import.')
  } finally {
    importExecuting.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function openEdit(cls: AdminClass) {
  selectedClass.value = cls
  Object.assign(form, {
    institution_id: 1,
    unit_id: cls.unit?.id ?? '',
    program_id: cls.program?.id ?? '',
    major_id: null,
    cohort_id: cls.cohort?.id ?? '',
    advisor_id: cls.advisor?.id ?? null,
    curriculum_id: cls.curriculum?.id ?? null,
    code: cls.code,
    name: cls.name,
    capacity: cls.capacity ?? 40,
    expected_graduation_year: cls.expected_graduation_year ?? null,
    status: cls.status,
    description: cls.description ?? '',
  })
  editOpen.value = true
}

function openDelete(cls: AdminClass) {
  selectedClass.value = cls
  deleteOpen.value = true
}

function openCreate() {
  Object.assign(form, formDefaults())
  createOpen.value = true
}

function statusLabel(val: string) {
  return { active: 'Đang hoạt động', graduated: 'Tốt nghiệp', suspended: 'Tạm ngưng' }[val] ?? val
}

function statusClass(val: string) {
  return { active: 'role-instructor', graduated: 'role-student', suspended: 'role-admin' }[val] ?? 'role-admin'
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([fetchClasses(), fetchDropdowns(), fetchAdvisors()])
})

watch(filterCohortId, () => fetchClasses(1))
watch(filterStatus, () => fetchClasses(1))
</script>

<template>
  <AdminWorkspaceShell
    title="Quản lý Lớp Hành Chính"
    description="Tạo, cập nhật, và quản lý danh sách lớp hành chính. Mỗi lớp gắn với khóa đào tạo, chương trình học và cố vấn."
    :breadcrumb="['Trang chủ', 'Đào tạo & Học vụ', 'Lớp Hành Chính']"
  >
    <!-- Overview cards -->
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Tổng lớp</p>
        <div class="mini-head">
          <strong>{{ total }}</strong>
          <span>Theo bộ lọc</span>
        </div>
      </article>
      <article class="dashboard-card mini-card tone-amber">
        <p class="mini-title">Đang hoạt động</p>
        <div class="mini-head">
          <strong>{{ activeCount }}</strong>
          <span>Trang hiện tại</span>
        </div>
      </article>
      <article class="dashboard-card mini-card">
        <p class="mini-title">Tổng sinh viên</p>
        <div class="mini-head">
          <strong>{{ totalStudents }}</strong>
          <span>Trang hiện tại</span>
        </div>
      </article>
    </section>

    <!-- Main panel -->
    <section class="dashboard-card crud-panel">
      <!-- Toolbar -->
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent="fetchClasses(1)">
          <div class="search-wrap">
            <i class="pi pi-search" style="font-size:0.9375rem" />
            <input
              v-model="searchQuery"
              type="text"
              class="crud-search"
              placeholder="Tìm theo mã hoặc tên lớp..."
              style="padding-left: 34px;"
            >
          </div>
          <select v-model="filterCohortId" class="crud-select">
            <option value="">Tất cả khóa học vụ</option>
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="filterStatus" class="crud-select">
            <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <button class="crud-secondary-btn" type="submit">Tìm kiếm</button>
        </form>
        <div class="crud-toolbar-right">
          <button class="crud-primary-btn" type="button" @click="openCreate" style="white-space: nowrap; display: flex; align-items: center; gap: 6px; padding: 0 12px; font-size: 0.85rem; height: 36px;">
            <i class="pi pi-plus" style="font-size:0.9375rem" />
            Tạo lớp mới
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Mã lớp</th>
              <th>Tên lớp</th>
              <th>Khóa / Chương trình</th>
              <th>Cố vấn</th>
              <th>CTĐT</th>
              <th>Sinh viên</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="crud-empty">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="classes.length === 0">
              <td colspan="8" class="crud-empty">
                <i class="pi pi-building" style="font-size:2.25rem" />
                <div>Chưa có lớp hành chính nào.</div>
              </td>
            </tr>
            <tr v-for="cls in classes" :key="cls.id">
              <td><span class="mono-code">{{ cls.code }}</span></td>
              <td>
                <div style="display:flex; flex-direction:column; gap:2px;">
                  <strong>{{ cls.name }}</strong>
                  <span v-if="cls.unit" style="font-size:0.75rem; color:var(--muted);">{{ cls.unit.name }}</span>
                </div>
              </td>
              <td>
                <div style="display:flex; flex-direction:column; gap:2px;">
                  <span style="font-weight:600; font-size:0.88rem;">{{ cls.cohort?.name ?? '—' }}</span>
                  <span style="font-size:0.75rem; color:var(--muted);">{{ cls.program?.name ?? '—' }}</span>
                </div>
              </td>
              <td>
                <div v-if="cls.advisor" style="display:flex; flex-direction:column; gap:1px;">
                  <strong style="font-size:0.85rem;">{{ cls.advisor.name }}</strong>
                  <span style="font-size:0.72rem; color:var(--muted);">{{ cls.advisor.email }}</span>
                </div>
                <span v-else style="color:var(--muted);">—</span>
              </td>
              <td>
                <span v-if="cls.curriculum" class="has-ctdt-tag">
                  <i class="pi pi-graduation-cap" style="font-size:0.8125rem" /> {{ cls.curriculum.code }}
                </span>
                <span v-else class="no-ctdt-tag">
                  <i class="pi pi-graduation-cap" style="font-size:0.8125rem" /> Chưa gán
                </span>
              </td>
              <td>
                <div style="display:flex; align-items:center; gap:6px;">
                  <i class="pi pi-users" style="font-size:0.875rem" />
                  <strong>{{ cls.students_count ?? 0 }}</strong>
                  <span style="font-size:0.75rem; color:var(--muted);">/ {{ cls.capacity }}</span>
                </div>
              </td>
              <td>
                <span class="crud-badge" :class="statusClass(cls.status)">{{ statusLabel(cls.status) }}</span>
              </td>
              <td>
                <div class="crud-actions">
                  <button class="action-btn is-view" type="button" @click="openEnroll(cls)">
                    <i class="pi pi-user-plus" style="font-size:0.8125rem" /> Ghi danh
                  </button>
                  <button class="action-btn is-edit" type="button" @click="openEdit(cls)">
                    <i class="pi pi-pencil" style="font-size:0.8125rem" /> Sửa
                  </button>
                  <button class="action-btn is-delete" type="button" @click="openDelete(cls)">
                    <i class="pi pi-trash" style="font-size:0.8125rem" /> Xóa
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="crud-pagination" style="padding: 14px 4px 2px; border-top: 1px solid var(--line); margin-top: 8px;">
        <p style="font-size:0.84rem; color:var(--muted);">
          Hiển thị <strong>{{ classes.length }}</strong> / <strong>{{ total }}</strong> lớp —
          Trang <strong>{{ currentPage }}</strong> / <strong>{{ lastPage }}</strong>
        </p>
        <div class="crud-pagination-btns">
          <button
            class="crud-secondary-btn"
            style="height:32px; width:36px; padding:0; justify-content:center;"
            :disabled="currentPage <= 1"
            @click="fetchClasses(currentPage - 1)"
          >
            <i class="pi pi-chevron-left" style="font-size:1.0rem" />
          </button>
          <button
            class="crud-secondary-btn"
            style="height:32px; width:36px; padding:0; justify-content:center;"
            :disabled="currentPage >= lastPage"
            @click="fetchClasses(currentPage + 1)"
          >
            <i class="pi pi-chevron-right" style="font-size:1.0rem" />
          </button>
        </div>
      </div>
    </section>

    <!-- Create Modal -->
    <Teleport to="body">
      <div v-if="createOpen" class="crud-modal-backdrop" @click.self="createOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Tạo mới</p>
              <h3>Thêm lớp hành chính</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="createOpen = false">
              <i class="pi pi-times" style="font-size:1.125rem" />
            </button>
          </div>

          <div class="crud-form-grid">
            <label class="crud-field">
              <span>Mã lớp <em>*</em></span>
              <input v-model="form.code" type="text" placeholder="VD: CNTT2021A">
            </label>
            <label class="crud-field">
              <span>Tên lớp <em>*</em></span>
              <input v-model="form.name" type="text" placeholder="VD: Công nghệ thông tin K2021A">
            </label>
            <label class="crud-field">
              <span>Khóa học vụ <em>*</em></span>
              <select v-model="form.cohort_id">
                <option value="">— Chọn khóa —</option>
                <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Chương trình <em>*</em></span>
              <select v-model="form.program_id">
                <option value="">— Chọn chương trình —</option>
                <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Khoa / Đơn vị <em>*</em></span>
              <select v-model="form.unit_id">
                <option value="">— Chọn đơn vị —</option>
                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>CTĐT (Chương trình đào tạo)</span>
              <select v-model="form.curriculum_id">
                <option :value="null">— Chưa gán —</option>
                <option v-for="c in curricula" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Cố vấn học tập</span>
              <select v-model="form.advisor_id">
                <option :value="null">— Không có —</option>
                <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Sĩ số tối đa</span>
              <input v-model="form.capacity" type="number" min="1" placeholder="40">
            </label>
            <label class="crud-field">
              <span>Năm dự kiến tốt nghiệp</span>
              <input v-model="form.expected_graduation_year" type="number" min="2020" placeholder="2025">
            </label>
            <label class="crud-field">
              <span>Trạng thái</span>
              <select v-model="form.status">
                <option value="active">Đang hoạt động</option>
                <option value="graduated">Tốt nghiệp</option>
                <option value="suspended">Tạm ngưng</option>
              </select>
            </label>
            <label class="crud-field crud-field-full">
              <span>Ghi chú</span>
              <textarea v-model="form.description" rows="3" placeholder="Mô tả thêm về lớp hành chính..." />
            </label>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="createOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" :disabled="saving" @click="createClass">
              {{ saving ? 'Đang tạo...' : 'Tạo lớp' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Modal -->
    <Teleport to="body">
      <div v-if="editOpen" class="crud-modal-backdrop" @click.self="editOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Chỉnh sửa</p>
              <h3>{{ selectedClass?.name }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="editOpen = false">
              <i class="pi pi-times" style="font-size:1.125rem" />
            </button>
          </div>

          <div class="crud-form-grid">
            <label class="crud-field">
              <span>Mã lớp <em>*</em></span>
              <input v-model="form.code" type="text">
            </label>
            <label class="crud-field">
              <span>Tên lớp <em>*</em></span>
              <input v-model="form.name" type="text">
            </label>
            <label class="crud-field">
              <span>Khóa học vụ <em>*</em></span>
              <select v-model="form.cohort_id">
                <option value="">— Chọn khóa —</option>
                <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Chương trình <em>*</em></span>
              <select v-model="form.program_id">
                <option value="">— Chọn chương trình —</option>
                <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Khoa / Đơn vị <em>*</em></span>
              <select v-model="form.unit_id">
                <option value="">— Chọn đơn vị —</option>
                <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>CTĐT</span>
              <select v-model="form.curriculum_id">
                <option :value="null">— Chưa gán —</option>
                <option v-for="c in curricula" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Cố vấn học tập</span>
              <select v-model="form.advisor_id">
                <option :value="null">— Không có —</option>
                <option v-for="a in advisors" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </label>
            <label class="crud-field">
              <span>Sĩ số tối đa</span>
              <input v-model="form.capacity" type="number" min="1">
            </label>
            <label class="crud-field">
              <span>Năm dự kiến tốt nghiệp</span>
              <input v-model="form.expected_graduation_year" type="number" min="2020">
            </label>
            <label class="crud-field">
              <span>Trạng thái</span>
              <select v-model="form.status">
                <option value="active">Đang hoạt động</option>
                <option value="graduated">Tốt nghiệp</option>
                <option value="suspended">Tạm ngưng</option>
              </select>
            </label>
            <label class="crud-field crud-field-full">
              <span>Ghi chú</span>
              <textarea v-model="form.description" rows="3" />
            </label>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="editOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" :disabled="saving" @click="updateClass">
              {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Modal -->
    <Teleport to="body">
      <div v-if="deleteOpen" class="crud-modal-backdrop" @click.self="deleteOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head is-danger">
            <div>
              <p class="section-kicker">Xác nhận xóa</p>
              <h3>Xóa lớp hành chính</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="deleteOpen = false">
              <i class="pi pi-times" style="font-size:1.125rem" />
            </button>
          </div>
          <div class="crud-modal-body" style="padding: 20px 28px;">
            <p>Bạn có chắc muốn xóa lớp <strong>{{ selectedClass?.name }}</strong> ({{ selectedClass?.code }})?</p>
            <p style="margin-top:8px; font-size:0.85rem; color:#ef4444;">Hành động này không thể hoàn tác. Dữ liệu sinh viên trong lớp sẽ bị ảnh hưởng.</p>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="deleteOpen = false">Hủy</button>
            <button class="crud-primary-btn crud-danger-btn" type="button" :disabled="saving" @click="deleteClass">
              {{ saving ? 'Đang xóa...' : 'Xác nhận xóa' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Enroll Modal -->
    <Teleport to="body">
      <div v-if="enrollOpen" class="crud-modal-backdrop" @click.self="enrollOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Ghi danh sinh viên</p>
              <h3>{{ selectedClass?.name }} ({{ selectedClass?.code }})</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="enrollOpen = false">
              <i class="pi pi-times" style="font-size:1.125rem" />
            </button>
          </div>

          <!-- Tabs -->
          <div class="enroll-modal-tabs">
            <button
              class="enroll-modal-tab"
              :class="{ 'is-active': enrollTab === 'list' }"
              type="button"
              @click="enrollTab = 'list'"
            >
              <i class="pi pi-users" style="font-size:0.875rem" /> Chọn từ danh sách
            </button>
            <button
              class="enroll-modal-tab"
              :class="{ 'is-active': enrollTab === 'import' }"
              type="button"
              @click="enrollTab = 'import'; importStep = 1; importFile = null; importPreviewData = null"
            >
              <i class="pi pi-file-excel" style="font-size:0.875rem" /> Import từ Excel/CSV
            </button>
          </div>

          <!-- Tab: chọn từ list -->
          <div v-if="enrollTab === 'list'" class="enroll-modal-body">
            <div class="search-wrap" style="margin-bottom: 12px;">
              <i class="pi pi-search" style="font-size:0.9375rem" />
              <input
                v-model="enrollSearchQuery"
                type="text"
                class="crud-search"
                placeholder="Tìm theo mã SV hoặc tên học viên..."
                style="padding-left: 34px; width: 100%;"
                @input="searchEnrollStudents"
              >
            </div>

            <div v-if="enrollDisplayStudents.length > 0" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:0.8rem; color:var(--muted);">
              <span>{{ enrollDisplayStudents.length }} học viên — Đã chọn: <strong style="color:var(--green-deep);">{{ selectedEnrollIds.length }}</strong></span>
            </div>

            <div class="picker-list">
              <div v-if="enrollSearchLoading" class="crud-empty" style="padding: 2rem; font-size:0.85rem;">Đang tìm kiếm...</div>
              <div v-else-if="!enrollDisplayStudents.length" class="crud-empty" style="padding: 2rem; font-size:0.85rem;">
                Không tìm thấy sinh viên. Hãy tìm kiếm hoặc đợi tải xong.
              </div>
              <div
                v-else
                v-for="s in enrollDisplayStudents"
                :key="s.id"
                class="picker-row"
                :class="{ 'is-sel': selectedEnrollIds.includes(s.id) }"
                @click="toggleEnrollStudent(s.id)"
              >
                <div class="pick-check">
                  <i v-if="selectedEnrollIds.includes(s.id)" class="pi pi-check" style="font-size:0.6875rem" />
                </div>
                <div style="display:flex; flex-direction:column; gap:2px;">
                  <span class="mono-code" style="font-size:0.72rem;">{{ s.student_code }}</span>
                  <strong style="font-size:0.88rem;">{{ s.name }}</strong>
                  <span style="font-size:0.75rem; color:var(--muted);">{{ s.email }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab: import CSV -->
          <div v-else class="enroll-modal-body">
            <div v-if="importStep === 1">
              <p style="font-size:0.83rem; color:var(--muted); margin-bottom:16px; line-height:1.6;">
                Tải lên tệp CSV với cột đầu tiên là <strong>Mã sinh viên</strong>. Hệ thống sẽ xem trước kết quả trước khi thực hiện.
              </p>
              <div class="import-dropzone" @click="importFileRef?.click()">
                <i class="pi pi-file-excel" style="font-size:2.25rem" />
                <div v-if="!importFile">
                  <strong>Chọn tệp CSV</strong>
                  <span>Cột 1: Mã sinh viên (student_code)</span>
                </div>
                <div v-else style="color: var(--green-deep);">
                  <strong>{{ importFile.name }}</strong>
                  <span>{{ (importFile.size / 1024).toFixed(1) }} KB — Nhấn để đổi tệp</span>
                </div>
                <input ref="importFileRef" type="file" accept=".csv,.txt" style="display:none;" @change="handleImportFileChange">
              </div>
            </div>

            <div v-else-if="importStep === 2 && importPreviewData">
              <div class="import-preview-stats">
                <div class="pstat">
                  <span>Tổng dòng</span>
                  <strong>{{ importPreviewData.total_rows }}</strong>
                </div>
                <div class="pstat">
                  <span style="color: var(--green-deep);">Hợp lệ</span>
                  <strong style="color: var(--green-deep);">{{ importPreviewData.valid_rows }}</strong>
                </div>
                <div class="pstat">
                  <span style="color: #ef4444;">Lỗi</span>
                  <strong style="color: #ef4444;">{{ importPreviewData.invalid_rows }}</strong>
                </div>
              </div>
              <div class="import-preview-scroll">
                <table class="crud-table" style="font-size:0.8rem;">
                  <thead>
                    <tr>
                      <th>Dòng</th>
                      <th>Mã SV</th>
                      <th>Họ tên</th>
                      <th>Kết quả</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in importPreviewData.preview_data" :key="row.row_number">
                      <td>{{ row.row_number }}</td>
                      <td><span class="mono-code">{{ row.student_code }}</span></td>
                      <td>{{ row.student_name || '—' }}</td>
                      <td :style="row.status === 'valid' ? 'color:var(--green-deep);' : 'color:#ef4444;'">
                        {{ row.message }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="enrollOpen = false">Đóng</button>

            <!-- List tab actions -->
            <template v-if="enrollTab === 'list'">
              <button
                class="crud-primary-btn"
                type="button"
                :disabled="enrollSaving || selectedEnrollIds.length === 0"
                @click="submitEnrollFromList"
              >
                <i class="pi pi-user-plus" style="font-size:0.9375rem" />
                {{ enrollSaving ? 'Đang ghi danh...' : `Ghi danh ${selectedEnrollIds.length} sinh viên` }}
              </button>
            </template>

            <!-- Import tab actions -->
            <template v-else>
              <button
                v-if="importStep === 1"
                class="crud-primary-btn"
                type="button"
                :disabled="importLoading || !importFile"
                @click="previewImport"
              >
                {{ importLoading ? 'Đang kiểm tra...' : 'Xem trước' }}
              </button>
              <template v-else>
                <button
                  class="crud-secondary-btn"
                  type="button"
                  @click="importStep = 1; importFile = null; importPreviewData = null"
                >
                  Chọn lại tệp
                </button>
                <button
                  class="crud-primary-btn"
                  type="button"
                  :disabled="importExecuting || !importPreviewData?.valid_rows"
                  @click="executeImport"
                >
                  {{ importExecuting ? 'Đang ghi danh...' : `Ghi danh ${importPreviewData?.valid_rows ?? 0} sinh viên` }}
                </button>
              </template>
            </template>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.search-wrap { position: relative; }
.search-ico { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }

.mono-code { font-family: monospace; font-weight: 700; color: var(--green-deep); font-size: 0.85rem; }

.has-ctdt-tag {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(var(--green-rgb), 0.1); color: var(--green-deep);
  font-weight: 700; font-size: 0.72rem; padding: 3px 8px; border-radius: 99px;
}
.no-ctdt-tag {
  display: inline-flex; align-items: center; gap: 5px;
  background: #fffbeb; color: #d97706;
  font-weight: 700; font-size: 0.72rem; padding: 3px 8px; border-radius: 99px;
}

.crud-pagination { display: flex; align-items: center; justify-content: space-between; }
.crud-pagination-btns { display: flex; gap: 6px; }

.crud-form-grid textarea {
  resize: vertical;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--surface);
  color: var(--text);
  font-size: 0.9rem;
  font-family: inherit;
  width: 100%;
}

.crud-form-grid em {
  color: #ef4444;
  font-style: normal;
}

.crud-modal-body p { margin: 0; line-height: 1.6; color: var(--text); }

.enroll-modal-tabs {
  display: flex;
  gap: 4px;
  padding: 0 28px;
  border-bottom: 2px solid var(--line);
  margin-bottom: 0;
}
.enroll-modal-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 16px; border: none; background: none;
  font-size: 0.86rem; font-weight: 600; color: var(--muted);
  cursor: pointer; position: relative; border-radius: 8px 8px 0 0;
  transition: color 0.15s;
}
.enroll-modal-tab:hover { color: var(--green-deep); }
.enroll-modal-tab.is-active { color: var(--green-deep); }
.enroll-modal-tab.is-active::after {
  content: ''; position: absolute; bottom: -2px; left: 0; right: 0;
  height: 2px; background: var(--green-deep); border-radius: 99px;
}

.enroll-modal-body {
  padding: 20px 28px;
  max-height: 380px;
  overflow-y: auto;
}

.picker-list { display: flex; flex-direction: column; gap: 6px; }
.picker-row {
  display: flex; gap: 12px; align-items: center;
  padding: 10px 12px; border-radius: 10px;
  border: 1px solid var(--line); background: var(--surface);
  cursor: pointer; transition: all 0.15s;
}
.picker-row:hover { border-color: rgba(var(--green-rgb), 0.3); }
.picker-row.is-sel { border-color: rgba(var(--green-rgb), 0.4); background: rgba(var(--green-rgb), 0.06); }
.pick-check {
  width: 18px; height: 18px; border-radius: 4px;
  border: 2px solid var(--line); display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; background: var(--surface); transition: all 0.1s;
}
.is-sel .pick-check { background: var(--green-deep); border-color: var(--green-deep); color: #fff; }

.import-dropzone {
  border: 2px dashed var(--line); border-radius: 14px; padding: 32px;
  text-align: center; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  transition: all 0.15s;
}
.import-dropzone:hover { border-color: rgba(var(--green-rgb), 0.4); background: rgba(var(--green-rgb), 0.03); }
.import-dropzone strong { font-size: 0.9rem; color: var(--text); display: block; }
.import-dropzone span { font-size: 0.78rem; color: var(--muted); display: block; }

.import-preview-stats { display: flex; gap: 24px; padding-bottom: 12px; border-bottom: 1px solid var(--line); margin-bottom: 12px; }
.pstat { display: flex; flex-direction: column; gap: 2px; }
.pstat span { font-size: 0.75rem; color: var(--muted); }
.pstat strong { font-size: 1.1rem; font-weight: 800; }
.import-preview-scroll { max-height: 200px; overflow-y: auto; border: 1px solid var(--line); border-radius: 10px; }
</style>
