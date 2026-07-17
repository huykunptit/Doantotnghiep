<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface StudentStat {
  user_id: number
  name: string
  student_code: string | null
  total_sessions: number
  present: number
  late: number
  absent: number
  attendance_rate: number
}
interface StatsResponse {
  class_section: { id: number; code: string; name: string }
  sessions_count: number
  students: StudentStat[]
}

const route = useRoute()
const sectionId = Number(route.params.id)
const token = useAuthTokenCookie()
const headers = { Authorization: `Bearer ${token.value}` }

const data = ref<StatsResponse | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi<StatsResponse>(
      `/instructor/sections/${sectionId}/attendance-stats`, { headers }
    )
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải thống kê.'
  } finally {
    loading.value = false
  }
}

function rateClass(rate: number) {
  if (rate >= 80) return 'text-emerald-600'
  if (rate >= 60) return 'text-amber-600'
  return 'text-red-600'
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ &bull; Điểm danh</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Thống kê điểm danh</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">{{ data?.class_section ? `${data.class_section.code} — ${data.sessions_count} buổi học` : '' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink :to="`/instructor/sections/${sectionId}/sessions`" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <i class="pi pi-arrow-left text-xs" />
          <span>Phiên học</span>
        </NuxtLink>
      </div>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm text-center py-12 text-xs text-[var(--muted)]">
      Đang tải...
    </div>
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>

    <div v-else-if="data" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
      <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Điểm danh</p>
        <h3 class="text-xs font-bold text-[var(--text)] mt-0.5">Danh sách sinh viên</h3>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
              <th class="px-5 py-3">Sinh viên</th>
              <th class="px-5 py-3">MSSV</th>
              <th class="px-5 py-3 text-center">Có mặt</th>
              <th class="px-5 py-3 text-center">Đi muộn</th>
              <th class="px-5 py-3 text-center">Vắng</th>
              <th class="px-5 py-3 text-center">Tỷ lệ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!data.students.length">
              <td colspan="6" class="px-5 py-8 text-center text-xs text-[var(--muted)]">Chưa có dữ liệu điểm danh.</td>
            </tr>
            <tr v-for="s in data.students" :key="s.user_id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-5 py-4"><strong class="text-xs font-bold text-[var(--text)]">{{ s.name }}</strong></td>
              <td class="px-5 py-4 text-xs text-[var(--muted)] font-semibold">{{ s.student_code ?? '—' }}</td>
              <td class="px-5 py-4 text-center text-xs font-bold text-emerald-600">{{ s.present }}</td>
              <td class="px-5 py-4 text-center text-xs font-bold text-amber-600">{{ s.late }}</td>
              <td class="px-5 py-4 text-center text-xs font-bold text-red-600">{{ s.absent }}</td>
              <td class="px-5 py-4 text-center">
                <span class="text-xs font-bold" :class="rateClass(s.attendance_rate)">{{ s.attendance_rate }}%</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>

