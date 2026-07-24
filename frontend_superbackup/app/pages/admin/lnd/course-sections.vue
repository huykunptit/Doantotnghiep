<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import { useAuthTokenCookie } from '~/composables/useAuthSession'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

const token = useAuthTokenCookie()
const toast = useToast()

const STATUS_LABELS: Record<string, string> = {
  planned: 'Kế hoạch', open: 'Đang mở', closed: 'Đã đóng', cancelled: 'Huỷ'
}
const STATUS_BADGE: Record<string, string> = {
  planned: 'ds-badge--pending', open: 'ds-badge--active', closed: 'ds-badge--draft', cancelled: 'ds-badge--closed'
}

interface Section {
  id: number; code: string; name: string | null
  course: { id: number; title: string } | null
  term: { id: number; name: string } | null
  cohort: { id: number; name: string } | null
  lecturer: { id: number; name: string; email: string } | null
  capacity: number; enrolled_count: number; status: string
}

const sections = ref<Section[]>([])
const terms = ref<any[]>([])
const cohorts = ref<any[]>([])
const courses = ref<any[]>([])
const instructors = ref<any[]>([])
const loading = ref(true)

const filterTerm = ref('')
const filterStatus = ref('')

const showModal = ref(false)
const editing = ref<Section | null>(null)
const deleting = ref<Section | null>(null)
const saving = ref(false)

const form = ref({ course_id: '', term_id: '', cohort_id: '', lecturer_id: '', code: '', name: '', capacity: 50, status: 'planned' })

// Schedule (sessions)
interface Session {
  id: number; title: string; location: string | null
  start_at: string; duration: number; max_participants: number | null
}
const scheduleSection = ref<Section | null>(null)
const sessions = ref<Session[]>([])
const sessionsLoading = ref(false)
const showSessionModal = ref(false)
const editingSession = ref<Session | null>(null)
const savingSession = ref(false)
const sessionForm = ref({ title: '', location: '', start_at: '', duration: 90, max_participants: '' })

async function openSchedule(s: Section) {
  scheduleSection.value = s
  sessionsLoading.value = true
  sessions.value = []
  try {
    const res = await api(`/instructor/sections/${s.id}/sessions`)
    sessions.value = Array.isArray(res) ? res : res?.data ?? []
  } catch (e: any) {
    toast.error('Không thể tải lịch học', e.message)
  } finally { sessionsLoading.value = false }
}

function openCreateSession() {
  editingSession.value = null
  sessionForm.value = { title: '', location: '', start_at: '', duration: 90, max_participants: '' }
  showSessionModal.value = true
}

function openEditSession(s: Session) {
  editingSession.value = s
  sessionForm.value = {
    title: s.title, location: s.location ?? '',
    start_at: s.start_at ? s.start_at.slice(0, 16) : '',
    duration: s.duration,
    max_participants: s.max_participants != null ? String(s.max_participants) : '',
  }
  showSessionModal.value = true
}

async function saveSession() {
  if (!sessionForm.value.title || !sessionForm.value.start_at) {
    toast.error('Thiếu thông tin', 'Vui lòng nhập tiêu đề và thời gian.'); return
  }
  savingSession.value = true
  const body = {
    title: sessionForm.value.title,
    location: sessionForm.value.location || null,
    start_at: sessionForm.value.start_at,
    duration: Number(sessionForm.value.duration) || 90,
    max_participants: sessionForm.value.max_participants ? Number(sessionForm.value.max_participants) : null,
  }
  try {
    if (editingSession.value) {
      const updated = await api(`/instructor/sessions/${editingSession.value.id}`, { method: 'PUT', body: JSON.stringify(body) })
      const idx = sessions.value.findIndex(s => s.id === editingSession.value!.id)
      if (idx >= 0) sessions.value[idx] = updated
      toast.success('Đã cập nhật lịch học')
    } else {
      const created = await api(`/instructor/sections/${scheduleSection.value!.id}/sessions`, { method: 'POST', body: JSON.stringify(body) })
      sessions.value.push(created)
      toast.success('Đã thêm buổi học')
    }
    showSessionModal.value = false
  } catch (e: any) {
    toast.error('Lưu thất bại', e.message)
  } finally { savingSession.value = false }
}

async function deleteSession(s: Session) {
  if (!confirm(`Xoá buổi "${s.title}"?`)) return
  try {
    await api(`/instructor/sessions/${s.id}`, { method: 'DELETE' })
    sessions.value = sessions.value.filter(x => x.id !== s.id)
    toast.success('Đã xoá buổi học')
  } catch (e: any) { toast.error('Xoá thất bại', e.message) }
}

function formatDateTime(dt: string) {
  const d = new Date(dt)
  return d.toLocaleString('vi-VN', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const filtered = computed(() => sections.value.filter(s => {
  if (filterTerm.value && String(s.term?.id) !== filterTerm.value) return false
  if (filterStatus.value && s.status !== filterStatus.value) return false
  return true
}))

const kpi = computed(() => ({
  total: sections.value.length,
  open: sections.value.filter(s => s.status === 'open').length,
  capacity: sections.value.reduce((a, s) => a + (s.capacity || 0), 0),
  enrolled: sections.value.reduce((a, s) => a + (s.enrolled_count || 0), 0),
}))

async function api(path: string, opts: RequestInit = {}) {
  const res = await fetch(`/api${path}`, {
    ...opts,
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token.value}`, ...opts.headers },
  })
  if (!res.ok) { const e = await res.json().catch(() => ({})); throw new Error(e.message || 'Lỗi') }
  return res.status === 204 ? null : res.json()
}

async function load() {
  loading.value = true
  try {
    const [sec, t, c, cr, inst] = await Promise.all([
      api('/admin/academic/class-sections?per_page=100'),
      api('/admin/academic/terms?per_page=100'),
      api('/admin/academic/cohorts?per_page=100'),
      api('/courses?per_page=200'),
      api('/admin/instructors'),
    ])
    sections.value = sec?.data ?? sec ?? []
    terms.value = t?.data ?? t ?? []
    cohorts.value = c?.data ?? c ?? []
    courses.value = cr?.data ?? cr ?? []
    instructors.value = Array.isArray(inst) ? inst : inst?.data ?? []
  } catch (e: any) {
    toast.error('Lỗi tải dữ liệu', e.message)
  } finally { loading.value = false }
}

function openCreate() {
  editing.value = null
  form.value = { course_id: '', term_id: '', cohort_id: '', lecturer_id: '', code: '', name: '', capacity: 50, status: 'planned' }
  showModal.value = true
}

function openEdit(s: Section) {
  editing.value = s
  form.value = {
    course_id: String(s.course?.id ?? ''), term_id: String(s.term?.id ?? ''),
    cohort_id: String(s.cohort?.id ?? ''), lecturer_id: String(s.lecturer?.id ?? ''),
    code: s.code, name: s.name ?? '', capacity: s.capacity, status: s.status,
  }
  showModal.value = true
}

async function save() {
  if (!form.value.course_id || !form.value.code) { toast.error('Thiếu thông tin', 'Vui lòng nhập mã lớp và chọn khóa học.'); return }
  saving.value = true
  const body = { ...form.value, course_id: Number(form.value.course_id) || undefined, term_id: Number(form.value.term_id) || undefined, cohort_id: Number(form.value.cohort_id) || undefined, lecturer_id: Number(form.value.lecturer_id) || undefined }
  try {
    if (editing.value) {
      const updated = await api(`/admin/academic/class-sections/${editing.value.id}`, { method: 'PUT', body: JSON.stringify(body) })
      const idx = sections.value.findIndex(s => s.id === editing.value!.id)
      if (idx >= 0) sections.value[idx] = updated
      toast.success('Đã cập nhật', `Lớp ${updated.code} đã được cập nhật.`)
    } else {
      const created = await api('/admin/academic/class-sections', { method: 'POST', body: JSON.stringify(body) })
      sections.value.unshift(created)
      toast.success('Đã tạo lớp tín chỉ', `Lớp ${created.code} đã được mở.`)
    }
    showModal.value = false
  } catch (e: any) {
    toast.error('Lưu thất bại', e.message)
  } finally { saving.value = false }
}

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await api(`/admin/academic/class-sections/${deleting.value.id}`, { method: 'DELETE' })
    sections.value = sections.value.filter(s => s.id !== deleting.value!.id)
    toast.success('Đã xoá', `Lớp ${deleting.value.code} đã bị xoá.`)
  } catch (e: any) {
    toast.error('Xoá thất bại', e.message)
  } finally { deleting.value = null }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Đào tạo & Học vụ</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Lớp tín chỉ</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Mở lớp học phần theo học kỳ, phân công giảng viên và quản lý sĩ số.</p>
      </div>
    </div>

    <!-- Filters & Toolbar (Always Open) -->
    <Card><template #content><div class="flex flex-col gap-4">
      <div class="flex flex-wrap items-end gap-2">
        <button class="inline-flex items-center gap-2 h-9 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#178762] text-white text-xs font-semibold transition-colors shrink-0 cursor-pointer mr-2" type="button" @click="openCreate">
          <i class="pi pi-plus" />
          <span>Mở lớp tín chỉ</span>
        </button>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="flex flex-col gap-1">
          <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Học kỳ</span>
          <select v-model="filterTerm" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">Tất cả học kỳ</option>
            <option v-for="t in terms" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
          </select>
        </label>

        <label class="flex flex-col gap-1">
          <span class="text-[0.68rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái</span>
          <select v-model="filterStatus" class="h-8 px-2 rounded-lg border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">Tất cả trạng thái</option>
            <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
      </div>
    </div></template></Card>

    <Card><template #content>
      <DataTable :value="filtered" :loading="loading" data-key="id" striped-rows responsive-layout="scroll">
        <Column field="code" header="Mã lớp">
          <template #body="{ data }">
          <div class="flex flex-col gap-0.5">
            <strong>{{ data.code }}</strong><small v-if="data.name">{{ data.name }}</small>
          </div>
          </template>
        </Column>
        <Column header="Khóa học">
          <template #body="{ data }">
          <div class="flex flex-col gap-0.5 max-w-[200px]">
            <span class="truncate">{{ data.course?.title ?? '—' }}</span><small v-if="data.cohort">{{ data.cohort.name }}</small>
          </div>
          </template>
        </Column>
        <Column header="Học kỳ" class="hidden md:table-cell"><template #body="{ data }">{{ data.term?.name ?? '—' }}</template></Column>
        <Column header="Giảng viên" class="hidden lg:table-cell"><template #body="{ data }">{{ data.lecturer?.name ?? '—' }}</template></Column>
        <Column header="Sĩ số"><template #body="{ data }"><strong>{{ data.enrolled_count }}</strong> / {{ data.capacity || '∞' }}</template></Column>
        <Column field="status" header="Trạng thái">
          <template #body="{ data }"><Tag :value="STATUS_LABELS[data.status] ?? data.status" :severity="data.status === 'open' ? 'success' : data.status === 'cancelled' ? 'danger' : 'secondary'" /></template>
        </Column>
        <Column header="Thao tác" frozen align-frozen="right">
          <template #body="{ data }">
          <div class="flex justify-end gap-1.5">
            <Button icon="pi pi-calendar" text rounded aria-label="Lịch học" @click="scheduleSection?.id === data.id ? scheduleSection = null : openSchedule(data)" />
            <Button icon="pi pi-pencil" severity="secondary" text rounded aria-label="Sửa" @click="openEdit(data)" />
            <Button icon="pi pi-trash" severity="danger" text rounded aria-label="Xóa" @click="deleting = data" />
          </div>
          </template>
        </Column>
        <template #empty><div class="py-10 text-center">Chưa có lớp tín chỉ nào</div></template>
      </DataTable>
    </template></Card>

    <!-- Schedule panel -->
    <div v-if="scheduleSection" class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Lịch học</p>
          <h3 class="ds-section-title">{{ scheduleSection.code }} — {{ scheduleSection.course?.title }}</h3>
        </div>
        <div style="display:flex;gap:8px;">
          <button class="crud-primary-btn" @click="openCreateSession">
            <i class="pi pi-plus" style="font-size:0.9375rem" /> Thêm buổi học
          </button>
          <button class="topbar-ghost" @click="scheduleSection = null"><i class="pi pi-times" style="font-size:1.0rem" /></button>
        </div>
      </div>

      <div v-if="sessionsLoading" class="crud-empty" style="padding:2rem;">Đang tải lịch học...</div>
      <div v-else-if="sessions.length === 0" class="crud-empty">
        <i class="pi pi-calendar" style="font-size:2.5rem" />
        <div><strong>Chưa có buổi học</strong><p>Nhấn "Thêm buổi học" để nhập lịch thủ công.</p></div>
      </div>
      <div v-else class="session-list">
        <div v-for="s in sessions" :key="s.id" class="session-card">
          <div class="session-icon"><i class="pi pi-calendar" style="font-size:1.125rem" /></div>
          <div class="session-info">
            <strong>{{ s.title }}</strong>
            <div class="session-meta">
              <span><i class="pi pi-clock" style="font-size:0.75rem" /> {{ formatDateTime(s.start_at) }} · {{ s.duration }} phút</span>
              <span v-if="s.location"><i class="pi pi-map-marker" style="font-size:0.75rem" /> {{ s.location }}</span>
            </div>
          </div>
          <div class="row-actions">
            <button class="icon-btn" title="Sửa" @click="openEditSession(s)"><i class="pi pi-pencil" style="font-size:0.875rem" /></button>
            <button class="icon-btn is-danger" title="Xoá" @click="deleteSession(s)"><i class="pi pi-trash" style="font-size:0.875rem" /></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Session create/edit modal -->
    <Dialog
      v-model:visible="showSessionModal"
      :header="editingSession ? 'Chỉnh sửa buổi học' : 'Thêm buổi học'"
      :subtitle="scheduleSection?.code || 'Lớp tín chỉ'"
      :modal="true" :style="{ width: '34rem' }"
    >
      <div class="flex flex-col gap-4 text-left">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Tiêu đề buổi học *</span>
          <input v-model="sessionForm.title" type="text" placeholder="VD: Buổi 1 — Giới thiệu môn học" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <div class="grid grid-cols-2 gap-4">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Thời gian bắt đầu *</span>
            <input v-model="sessionForm.start_at" type="datetime-local" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Thời lượng (phút)</span>
            <input v-model.number="sessionForm.duration" type="number" min="15" step="15" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
          </label>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Phòng học / Địa điểm</span>
            <input v-model="sessionForm.location" type="text" placeholder="VD: A201, Online, …" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Sĩ số tối đa (tuỳ chọn)</span>
            <input v-model="sessionForm.max_participants" type="number" min="0" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
          </label>
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showSessionModal = false">Huỷ</button>
        <button class="btn-primary" :disabled="savingSession" @click="saveSession">
          {{ savingSession ? 'Đang lưu...' : (editingSession ? 'Lưu thay đổi' : 'Thêm buổi học') }}
        </button>
      </template>
    </Dialog>

    <!-- Course Section Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editing ? `Lớp ${editing.code}` : 'Mở lớp tín chỉ'"
      :subtitle="editing ? 'Chỉnh sửa' : 'Mở lớp mới'"
      :modal="true" :style="{ width: '48rem' }"
    >
      <div class="grid grid-cols-2 gap-4 text-left">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Khóa học (Học phần) *</span>
          <select v-model="form.course_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn khóa học —</option>
            <option v-for="c in courses" :key="c.id" :value="String(c.id)">{{ c.title }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Mã lớp tín chỉ *</span>
          <input v-model="form.code" type="text" placeholder="VD: CNTT301-01" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5 col-span-2">
          <span class="text-xs font-semibold text-[var(--text)]">Tên lớp (tuỳ chọn)</span>
          <input v-model="form.name" type="text" placeholder="VD: Lập trình Web nhóm 1" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Học kỳ</span>
          <select v-model="form.term_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chọn học kỳ —</option>
            <option v-for="t in terms" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Khóa / Nhóm</span>
          <select v-model="form.cohort_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Không giới hạn —</option>
            <option v-for="c in cohorts" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Giảng viên phụ trách</span>
          <select v-model="form.lecturer_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">— Chưa phân công —</option>
            <option v-for="i in instructors" :key="i.id" :value="String(i.id)">{{ i.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Sĩ số tối đa</span>
          <input v-model.number="form.capacity" type="number" min="0" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]" />
        </label>
        <label class="flex flex-col gap-1.5 col-span-2">
          <span class="text-xs font-semibold text-[var(--text)]">Trạng thái</span>
          <select v-model="form.status" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showModal = false">Huỷ</button>
        <button class="btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Đang lưu...' : (editing ? 'Lưu thay đổi' : 'Mở lớp') }}
        </button>
      </template>
    </Dialog>

    <!-- Delete confirm -->
    <CrudConfirmModal
      v-if="deleting"
      :title="`Xoá lớp ${deleting.code}?`"
      description="Hành động này không thể hoàn tác. Tất cả ghi danh liên quan sẽ bị xoá theo."
      confirm-label="Xoá lớp tín chỉ"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>

<style scoped>
.full-badge { color: #b91c1c; font-weight: 700; }
.row-actions { display: flex; gap: 6px; justify-content: flex-end; }
.icon-btn {
  width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--line);
  background: var(--bg); color: var(--muted); display: flex; align-items: center;
  justify-content: center; cursor: pointer; transition: all 0.15s;
}
.icon-btn:hover { border-color: var(--green); color: var(--green-deep); background: var(--green-soft); }
.icon-btn.is-danger:hover { border-color: #fca5a5; color: #b91c1c; background: #fef2f2; }
.icon-btn.is-calendar:hover { border-color: rgba(124,58,237,0.4); color: #7c3aed; background: rgba(124,58,237,0.06); }
.icon-btn.is-active { border-color: rgba(124,58,237,0.4); color: #7c3aed; background: rgba(124,58,237,0.08); }
.form-field { display: flex; flex-direction: column; gap: 6px; }
.form-field label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
.req { color: #ef4444; }
.crud-modal-body { padding: 24px 28px; }

.session-list { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.session-card {
  display: flex; align-items: center; gap: 14px; padding: 14px 16px;
  border: 1px solid var(--line); border-radius: 14px; background: var(--bg);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.session-card:hover { border-color: rgba(124,58,237,0.25); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.session-icon {
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
  background: rgba(124,58,237,0.08); color: #7c3aed;
  display: flex; align-items: center; justify-content: center;
}
.session-info { flex: 1; min-width: 0; }
.session-info strong { display: block; font-size: 0.88rem; color: var(--text); }
.session-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
.session-meta span { display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem; color: var(--muted); }
</style>
