<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface AttendanceRow {
  user_id: number
  name: string
  student_code: string | null
  email: string
  avatar: string | null
  status: 'present' | 'late' | 'absent'
  checked_in_at: string | null
  distance_meters: number | null
}
interface Summary { total: number; present: number; late: number; absent: number }
interface ReportResponse {
  session: any
  summary: Summary
  rows: AttendanceRow[]
}

const route = useRoute()
const sessionId = Number(route.params.id)
const token = useAuthTokenCookie()
const headers = { Authorization: `Bearer ${token.value}` }

const data = ref<ReportResponse | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi<ReportResponse>(
      `/instructor/sessions/${sessionId}/attendance`, { headers }
    )
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải báo cáo.'
  } finally {
    loading.value = false
  }
}

function statusLabel(s: string) {
  return s === 'present' ? 'Có mặt' : s === 'late' ? 'Đi muộn' : 'Vắng'
}
function statusClass(s: string) {
  return s === 'present'
    ? 'bg-green-100 text-green-700'
    : s === 'late'
    ? 'bg-yellow-100 text-yellow-700'
    : 'bg-red-100 text-red-700'
}
function formatTime(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ &bull; Phiên học</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Báo cáo điểm danh</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">{{ data?.session ? `${data.session.title} — ${new Date(data.session.start_at).toLocaleDateString('vi-VN')}` : '' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink
          :to="`/instructor/sections/${data?.session?.class_section_id ?? ''}/sessions`"
          class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors"
        >
          <i class="pi pi-arrow-left text-xs" />
          <span>Phiên học</span>
        </NuxtLink>
      </div>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm text-center py-12 text-xs text-[var(--muted)]">
      Đang tải...
    </div>
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>

    <template v-else-if="data">
      <!-- KPI -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
            <span class="material-symbols-outlined text-lg">people</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng số</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.total }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
            <span class="material-symbols-outlined text-lg">how_to_reg</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Có mặt</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.present }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600">
            <span class="material-symbols-outlined text-lg">schedule</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Đi muộn</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.late }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-red-50 text-red-600">
            <span class="material-symbols-outlined text-lg">person_off</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Vắng</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.absent }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex flex-col">
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Chi tiết</p>
          <h3 class="text-xs font-bold text-[var(--text)] mt-0.5">Danh sách điểm danh</h3>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
                <th class="px-5 py-3">Sinh viên</th>
                <th class="px-5 py-3">MSSV</th>
                <th class="px-5 py-3">Trạng thái</th>
                <th class="px-5 py-3">Thời gian</th>
                <th class="px-5 py-3">Khoảng cách</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!data.rows.length">
                <td colspan="5" class="px-5 py-8 text-center text-xs text-[var(--muted)]">Chưa có dữ liệu điểm danh.</td>
              </tr>
              <tr v-for="row in data.rows" :key="row.user_id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
                <td class="px-5 py-4"><strong class="text-xs font-bold text-[var(--text)]">{{ row.name }}</strong></td>
                <td class="px-5 py-4 text-xs text-[var(--muted)] font-semibold">{{ row.student_code ?? '—' }}</td>
                <td class="px-5 py-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border" :class="statusClass(row.status)">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-xs text-[var(--muted)] font-semibold">{{ formatTime(row.checked_in_at) }}</td>
                <td class="px-5 py-4 text-xs text-[var(--muted)] font-semibold">
                  {{ row.distance_meters !== null ? `${row.distance_meters.toFixed(1)} m` : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
