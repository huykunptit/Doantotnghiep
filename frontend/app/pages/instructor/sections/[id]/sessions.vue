<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface Session {
  id: number
  title: string
  location: string
  start_at: string
  duration: number
  max_participants: number | null
  latitude: number | null
  longitude: number | null
  is_active: boolean
  qr_token: string | null
  qr_expires_at: string | null
  attendances_count?: number
}

const route = useRoute()
const sectionId = Number(route.params.id)
const token = useAuthTokenCookie()

const sessions = ref<Session[]>([])
const loading = ref(true)
const error = ref('')

// QR modal
const qrSession = ref<Session | null>(null)
const qrPayload = ref('')
const qrExpiresAt = ref('')
const qrLoading = ref(false)
let qrRefreshTimer: ReturnType<typeof setTimeout> | null = null

// Form modal
const showForm = ref(false)
const editTarget = ref<Session | null>(null)
const saving = ref(false)
const formError = ref('')
const form = ref({
  title: '',
  location: '',
  start_at: '',
  duration: 90,
  max_participants: null as number | null,
  latitude: 0,
  longitude: 0,
})

const headers = { Authorization: `Bearer ${token.value}` }

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<{ sessions: Session[] }>(
      `/instructor/sections/${sectionId}/sessions`, { headers }
    )
    sessions.value = res.sessions
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải danh sách phiên học.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editTarget.value = null
  form.value = { title: '', location: '', start_at: '', duration: 90, max_participants: null, latitude: 0, longitude: 0 }
  formError.value = ''
  showForm.value = true
  // Pre-fill GPS from browser
  navigator.geolocation?.getCurrentPosition((pos) => {
    form.value.latitude = pos.coords.latitude
    form.value.longitude = pos.coords.longitude
  })
}

function openEdit(s: Session) {
  editTarget.value = s
  form.value = {
    title: s.title,
    location: s.location,
    start_at: s.start_at ? s.start_at.substring(0, 16) : '',
    duration: s.duration,
    max_participants: s.max_participants,
    latitude: s.latitude ?? 0,
    longitude: s.longitude ?? 0,
  }
  formError.value = ''
  showForm.value = true
}

async function saveForm() {
  saving.value = true
  formError.value = ''
  try {
    if (editTarget.value) {
      await useApi(`/instructor/sessions/${editTarget.value.id}`, {
        method: 'PUT', headers, body: form.value,
      })
    } else {
      await useApi(`/instructor/sections/${sectionId}/sessions`, {
        method: 'POST', headers, body: form.value,
      })
    }
    showForm.value = false
    await load()
  } catch (e: any) {
    formError.value = e?.data?.message || 'Lỗi khi lưu phiên học.'
  } finally {
    saving.value = false
  }
}

async function deleteSession(s: Session) {
  if (!confirm(`Xoá phiên "${s.title}"?`)) return
  try {
    await useApi(`/instructor/sessions/${s.id}`, { method: 'DELETE', headers })
    await load()
  } catch (e: any) {
    alert(e?.data?.message || 'Không thể xoá phiên học.')
  }
}

async function toggleActive(s: Session) {
  await useApi(`/instructor/sessions/${s.id}`, {
    method: 'PUT', headers, body: { is_active: !s.is_active },
  })
  s.is_active = !s.is_active
}

async function openQr(s: Session) {
  qrSession.value = s
  await refreshQr(s)
}

async function refreshQr(s: Session) {
  qrLoading.value = true
  try {
    const res = await useApi<{ qr_payload: string; qr_expires_at: string }>(
      `/instructor/sessions/${s.id}/qr`, { method: 'POST', headers }
    )
    qrPayload.value = res.qr_payload
    qrExpiresAt.value = res.qr_expires_at
    // Auto-refresh 5 minutes
    if (qrRefreshTimer) clearTimeout(qrRefreshTimer)
    qrRefreshTimer = setTimeout(() => { if (qrSession.value) refreshQr(qrSession.value) }, 5 * 60 * 1000)
  } finally {
    qrLoading.value = false
  }
}

function closeQr() {
  qrSession.value = null
  if (qrRefreshTimer) { clearTimeout(qrRefreshTimer); qrRefreshTimer = null }
}

function formatDate(d: string) {
  return new Date(d).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(load)
onUnmounted(closeQr)
</script>

<template>
  <InstructorWorkspaceShell
    title="Phiên học & điểm danh QR"
    description="Quản lý các buổi học offline và tạo mã QR điểm danh cho sinh viên."
    :breadcrumb="['Trang chủ', 'Học vụ', 'Lớp học phần', 'Phiên học']"
  >
    <template #actions>
      <NuxtLink :to="`/instructor/sections/${sectionId}/attendance-stats`" class="crud-secondary-btn">
        <span class="material-symbols-outlined">bar_chart</span>
        Thống kê điểm danh
      </NuxtLink>
      <button class="crud-primary-btn" type="button" @click="openCreate">
        <span class="material-symbols-outlined">add</span>
        Tạo phiên học
      </button>
    </template>

    <div v-if="loading" class="dashboard-card crud-panel">
      <div class="crud-empty" style="padding:3rem;">Đang tải...</div>
    </div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>
    <div v-else-if="sessions.length === 0" class="dashboard-card crud-panel">
      <div class="crud-empty" style="padding:3rem;">Chưa có phiên học nào. Hãy tạo phiên học đầu tiên.</div>
    </div>

    <div v-else class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Lớp học offline</p>
          <h3 class="ds-section-title">Danh sách phiên học ({{ sessions.length }})</h3>
        </div>
      </div>
      <div class="sessions-list">
        <div
          v-for="s in sessions"
          :key="s.id"
          class="session-row"
        >
          <div class="session-row__info">
            <div class="session-row__top">
              <span class="session-badge" :class="s.is_active ? 'session-badge--open' : 'session-badge--closed'">
                {{ s.is_active ? 'Đang mở' : 'Chưa mở' }}
              </span>
              <strong class="session-row__title">{{ s.title }}</strong>
            </div>
            <div class="session-row__meta">
              <span><span class="material-symbols-outlined">location_on</span> {{ s.location }}</span>
              <span><span class="material-symbols-outlined">schedule</span> {{ formatDate(s.start_at) }}</span>
              <span><span class="material-symbols-outlined">timer</span> {{ s.duration }} phút</span>
              <span><span class="material-symbols-outlined">how_to_reg</span> {{ s.attendances_count ?? 0 }} điểm danh</span>
            </div>
          </div>
          <div class="session-row__actions">
            <button
              :class="s.is_active ? 'sess-btn sess-btn--pause' : 'sess-btn sess-btn--open'"
              type="button"
              @click="toggleActive(s)"
            >
              {{ s.is_active ? 'Đóng' : 'Mở điểm danh' }}
            </button>
            <button class="sess-btn sess-btn--qr" type="button" @click="openQr(s)">
              <span class="material-symbols-outlined">qr_code_2</span> QR
            </button>
            <NuxtLink :to="`/instructor/sessions/${s.id}/attendance`" class="sess-btn sess-btn--view">
              <span class="material-symbols-outlined">list_alt</span> Báo cáo
            </NuxtLink>
            <button class="ds-btn ds-btn--edit" type="button" @click="openEdit(s)">
              <span class="material-symbols-outlined">edit</span>
            </button>
            <button class="ds-btn ds-btn--delete" type="button" @click="deleteSession(s)">
              <span class="material-symbols-outlined">delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
        <div class="modal-box">
          <header class="modal-header">
            <h3>{{ editTarget ? 'Sửa phiên học' : 'Tạo phiên học mới' }}</h3>
            <button class="modal-close" type="button" @click="showForm = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </header>
          <form class="modal-body" @submit.prevent="saveForm">
            <label class="form-field">
              <span>Tiêu đề *</span>
              <input v-model="form.title" required class="form-input" />
            </label>
            <label class="form-field">
              <span>Địa điểm *</span>
              <input v-model="form.location" required class="form-input" />
            </label>
            <div class="form-row">
              <label class="form-field">
                <span>Thời gian bắt đầu *</span>
                <input v-model="form.start_at" type="datetime-local" required class="form-input" />
              </label>
              <label class="form-field">
                <span>Thời lượng (phút) *</span>
                <input v-model.number="form.duration" type="number" min="1" required class="form-input" />
              </label>
            </div>
            <div class="form-row">
              <label class="form-field">
                <span>Vĩ độ (Latitude) *</span>
                <input v-model.number="form.latitude" type="number" step="any" required class="form-input" />
              </label>
              <label class="form-field">
                <span>Kinh độ (Longitude) *</span>
                <input v-model.number="form.longitude" type="number" step="any" required class="form-input" />
              </label>
            </div>
            <p class="form-hint">Toạ độ được tự động điền theo vị trí hiện tại. Sinh viên phải ở trong phạm vi 10m.</p>
            <div v-if="formError" class="crud-alert is-error">{{ formError }}</div>
            <div class="modal-footer">
              <button type="button" class="crud-secondary-btn" @click="showForm = false">Huỷ</button>
              <button type="submit" :disabled="saving" class="crud-primary-btn">
                <span class="material-symbols-outlined">save</span>
                {{ saving ? 'Đang lưu...' : 'Lưu' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- QR Modal -->
      <div v-if="qrSession" class="modal-overlay" @click.self="closeQr">
        <div class="modal-box modal-box--sm">
          <header class="modal-header">
            <h3>Mã QR điểm danh</h3>
            <button class="modal-close" type="button" @click="closeQr">
              <span class="material-symbols-outlined">close</span>
            </button>
          </header>
          <div class="qr-body">
            <p class="qr-session-title">{{ qrSession.title }}</p>
            <p class="qr-session-loc">
              <span class="material-symbols-outlined">location_on</span> {{ qrSession.location }}
            </p>
            <div v-if="qrLoading" class="qr-placeholder">Đang tạo mã QR...</div>
            <div v-else class="qr-img-wrap">
              <img
                :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrPayload)}`"
                alt="QR Code"
                class="qr-img"
              />
            </div>
            <p class="qr-expire">
              Hết hạn lúc: <strong>{{ qrExpiresAt ? new Date(qrExpiresAt).toLocaleTimeString('vi-VN') : '—' }}</strong>
            </p>
            <p class="qr-note">QR tự động làm mới sau 5 phút</p>
            <button class="crud-primary-btn w-full" type="button" @click="refreshQr(qrSession!)">
              <span class="material-symbols-outlined">refresh</span> Làm mới QR ngay
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </InstructorWorkspaceShell>
</template>

<style scoped>
/* Session list */
.sessions-list { display: flex; flex-direction: column; gap: 10px; padding: 4px 0; }

.session-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--bg);
  transition: border-color 150ms, box-shadow 150ms;
}
.session-row:hover { border-color: rgba(var(--green-rgb),0.3); box-shadow: 0 2px 8px rgba(31,49,43,0.06); }

.session-row__info { flex: 1; min-width: 0; }
.session-row__top  { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.session-row__title { font-size: 0.9rem; font-weight: 700; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.session-badge {
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 9px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  white-space: nowrap;
}
.session-badge--open   { background: rgba(29,158,117,0.1); color: var(--green-deep); }
.session-badge--closed { background: rgba(17,17,17,0.06); color: var(--muted); }

.session-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 0.8rem;
  color: var(--muted);
}
.session-row__meta span { display: inline-flex; align-items: center; gap: 4px; }
.session-row__meta .material-symbols-outlined { font-size: 15px; }

.session-row__actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

.sess-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 30px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-size: 0.78rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
  transition: background 120ms, border-color 120ms;
}
.sess-btn .material-symbols-outlined { font-size: 15px; }
.sess-btn--open  { background: rgba(29,158,117,0.1); color: var(--green-deep); border-color: rgba(29,158,117,0.2); }
.sess-btn--pause { background: rgba(217,119,6,0.1); color: #b45309; border-color: rgba(217,119,6,0.2); }
.sess-btn--qr    { background: rgba(99,102,241,0.1); color: #4338ca; border-color: rgba(99,102,241,0.2); }
.sess-btn--view  { background: rgba(55,138,221,0.08); color: #1a5fa8; border-color: rgba(55,138,221,0.18); }
.sess-btn--open:hover  { background: rgba(29,158,117,0.18); }
.sess-btn--pause:hover { background: rgba(217,119,6,0.18); }
.sess-btn--qr:hover    { background: rgba(99,102,241,0.18); }
.sess-btn--view:hover  { background: rgba(55,138,221,0.15); }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(15,23,42,0.5);
  display: flex; align-items: center; justify-content: center;
  padding: 1rem; z-index: 70;
}
.modal-box {
  width: min(100%, 520px); background: var(--surface-lowest);
  border: 1px solid var(--line); border-radius: 20px;
  box-shadow: 0 20px 60px rgba(15,23,42,0.2);
  overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;
}
.modal-box--sm { width: min(100%, 380px); }
.modal-header {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--line);
}
.modal-header h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--text); }
.modal-close { background: transparent; border: none; cursor: pointer; color: var(--muted); display: flex; align-items: center; }
.modal-close:hover { color: var(--text); }
.modal-body { padding: 1.25rem; overflow: auto; display: flex; flex-direction: column; gap: 14px; }

.form-field { display: grid; gap: 5px; }
.form-field span { font-size: 0.8rem; font-weight: 700; color: var(--muted); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-input {
  width: 100%; padding: 7px 10px; border: 1px solid var(--line);
  border-radius: 10px; font: inherit; font-size: 0.875rem;
  background: var(--bg); color: var(--text);
}
.form-input:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(var(--green-rgb),0.12); }
.form-hint { font-size: 0.78rem; color: var(--muted); margin: 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 8px; }

/* QR modal */
.qr-body { padding: 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.qr-session-title { font-size: 0.95rem; font-weight: 700; color: var(--text); margin: 0; }
.qr-session-loc { font-size: 0.82rem; color: var(--muted); display: inline-flex; align-items: center; gap: 4px; margin: 0; }
.qr-session-loc .material-symbols-outlined { font-size: 15px; }
.qr-placeholder { height: 200px; display: grid; place-items: center; color: var(--muted); font-size: 0.875rem; }
.qr-img-wrap { display: flex; justify-content: center; }
.qr-img { width: 200px; height: 200px; border-radius: 12px; border: 1px solid var(--line); }
.qr-expire { font-size: 0.78rem; color: var(--muted); margin: 0; }
.qr-note { font-size: 0.75rem; color: #b45309; margin: 0; }
.w-full { width: 100%; justify-content: center; }
</style>
