<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ &bull; Điểm danh QR</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Phiên học & điểm danh QR</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý các buổi học offline và tạo mã QR điểm danh cho sinh viên.</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink :to="`/instructor/sections/${sectionId}/attendance-stats`" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <span class="material-symbols-outlined text-sm">bar_chart</span>
          <span>Thống kê điểm danh</span>
        </NuxtLink>
        <button class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors" type="button" @click="openCreate">
          <span class="material-symbols-outlined text-sm">add</span>
          <span>Tạo phiên học</span>
        </button>
      </div>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm text-center py-12 text-xs text-[var(--muted)]">
      Đang tải...
    </div>
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>
    <div v-else-if="sessions.length === 0" class="bg-white border border-[var(--line)] rounded-2xl p-8 shadow-sm text-center text-xs text-[var(--muted)]">
      Chưa có phiên học nào. Hãy tạo phiên học đầu tiên.
    </div>

    <div v-else class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
      <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Lớp học offline</p>
        <h3 class="text-xs font-bold text-[var(--text)] mt-0.5">Danh sách phiên học ({{ sessions.length }})</h3>
      </div>
      <div class="flex flex-col p-4 gap-3">
        <div
          v-for="s in sessions"
          :key="s.id"
          class="border border-[var(--line)] bg-[var(--surface-strong)] rounded-2xl p-4 flex flex-col md:flex-row justify-between md:items-center gap-4 hover:shadow-sm transition-shadow"
        >
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="s.is_active ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-[var(--surface)] text-[var(--muted)] border-[var(--line)]'">
                {{ s.is_active ? 'Đang mở' : 'Chưa mở' }}
              </span>
              <strong class="text-sm font-bold text-[var(--text)]">{{ s.title }}</strong>
            </div>
            <div class="flex items-center gap-4 mt-3 flex-wrap text-xs text-[var(--muted)] font-semibold">
              <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">location_on</span> {{ s.location }}</span>
              <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span> {{ formatDate(s.start_at) }}</span>
              <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">timer</span> {{ s.duration }} phút</span>
              <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">how_to_reg</span> {{ s.attendances_count ?? 0 }} điểm danh</span>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 pt-3 border-t md:border-t-0 border-[var(--line)] md:pt-0">
            <button
              class="h-7 px-3 rounded-lg text-[10px] font-bold text-white transition-colors"
              :class="s.is_active ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700'"
              type="button"
              @click="toggleActive(s)"
            >
              {{ s.is_active ? 'Đóng' : 'Mở điểm danh' }}
            </button>
            <button class="inline-flex items-center gap-1 h-7 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] transition-colors" type="button" @click="openQr(s)">
              <span class="material-symbols-outlined text-sm">qr_code_2</span> QR
            </button>
            <NuxtLink :to="`/instructor/sessions/${s.id}/attendance`" class="inline-flex items-center gap-1 h-7 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] flex items-center justify-center transition-colors">
              <span class="material-symbols-outlined text-sm">list_alt</span> Báo cáo
            </NuxtLink>
            <button class="w-7 h-7 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[var(--muted)] flex items-center justify-center transition-colors" type="button" @click="openEdit(s)">
              <span class="material-symbols-outlined text-sm">edit</span>
            </button>
            <button class="w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 flex items-center justify-center transition-colors" type="button" @click="deleteSession(s)">
              <span class="material-symbols-outlined text-sm">delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-[999]" @click.self="showForm = false">
        <div class="bg-white border border-[var(--line)] rounded-2xl w-full max-w-lg shadow-xl overflow-hidden flex flex-col">
          <header class="px-6 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex justify-between items-center">
            <h3 class="text-sm font-bold text-[var(--text)]">{{ editTarget ? 'Sửa phiên học' : 'Tạo phiên học mới' }}</h3>
            <button class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-[var(--line)] text-[var(--muted)]" type="button" @click="showForm = false">
              <span class="material-symbols-outlined text-sm">close</span>
            </button>
          </header>
          <form class="p-6 flex flex-col gap-4" @submit.prevent="saveForm">
            <div class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold text-[var(--text)]">Tiêu đề *</span>
              <input v-model="form.title" required class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold text-[var(--text)]">Địa điểm *</span>
              <input v-model="form.location" required class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <span class="text-xs font-semibold text-[var(--text)]">Thời gian bắt đầu *</span>
                <input v-model="form.start_at" type="datetime-local" required class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
              </div>
              <div class="flex flex-col gap-1.5">
                <span class="text-xs font-semibold text-[var(--text)]">Thời lượng (phút) *</span>
                <input v-model.number="form.duration" type="number" min="1" required class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <span class="text-xs font-semibold text-[var(--text)]">Vĩ độ (Latitude) *</span>
                <input v-model.number="form.latitude" type="number" step="any" required class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
              </div>
              <div class="flex flex-col gap-1.5">
                <span class="text-xs font-semibold text-[var(--text)]">Kinh độ (Longitude) *</span>
                <input v-model.number="form.longitude" type="number" step="any" required class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
              </div>
            </div>
            <p class="text-[10px] text-[var(--muted)] leading-relaxed">Toạ độ được tự động điền theo vị trí hiện tại. Sinh viên phải ở trong phạm vi 10m.</p>
            <div v-if="formError" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ formError }}</div>
            <div class="flex justify-end gap-2 border-t border-[var(--line)] pt-4 mt-2">
              <button type="button" class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" @click="showForm = false">Huỷ</button>
              <button type="submit" :disabled="saving" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50">
                <span class="material-symbols-outlined text-sm">save</span>
                <span>{{ saving ? 'Đang lưu...' : 'Lưu' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- QR Modal -->
      <div v-if="qrSession" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-[999]" @click.self="closeQr">
        <div class="bg-white border border-[var(--line)] rounded-2xl w-full max-w-sm shadow-xl overflow-hidden flex flex-col">
          <header class="px-6 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex justify-between items-center">
            <h3 class="text-sm font-bold text-[var(--text)]">Mã QR điểm danh</h3>
            <button class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-[var(--line)] text-[var(--muted)]" type="button" @click="closeQr">
              <span class="material-symbols-outlined text-sm">close</span>
            </button>
          </header>
          <div class="p-6 flex flex-col items-center gap-4 text-center">
            <p class="text-xs font-bold text-[var(--text)]">{{ qrSession.title }}</p>
            <p class="text-[11px] text-[var(--muted)] font-semibold flex items-center gap-1">
              <span class="material-symbols-outlined text-xs">location_on</span> {{ qrSession.location }}
            </p>
            <div v-if="qrLoading" class="w-[200px] h-[200px] bg-[var(--surface)] border border-[var(--line)] rounded-2xl flex items-center justify-center text-xs text-[var(--muted)]">Đang tạo mã QR...</div>
            <div v-else class="w-[200px] h-[200px] border border-[var(--line)] bg-white p-2 rounded-2xl flex items-center justify-center shadow-inner">
              <img
                :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrPayload)}`"
                alt="QR Code"
                class="w-full h-full object-contain"
              />
            </div>
            <p class="text-xs text-[var(--muted)] font-semibold">
              Hết hạn lúc: <strong class="text-[var(--text)]">{{ qrExpiresAt ? new Date(qrExpiresAt).toLocaleTimeString('vi-VN') : '—' }}</strong>
            </p>
            <p class="text-[10px] text-[var(--muted)]">QR tự động làm mới sau 5 phút</p>
            <button class="inline-flex items-center justify-center gap-1.5 h-9 w-full px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors" type="button" @click="refreshQr(qrSession!)">
              <span class="material-symbols-outlined text-sm">refresh</span> Làm mới QR ngay
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
