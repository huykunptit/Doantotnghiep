<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

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
  if (rate >= 80) return 'text-green-600'
  if (rate >= 60) return 'text-yellow-600'
  return 'text-red-600'
}

onMounted(load)
</script>

<template>
  <InstructorWorkspaceShell
    title="Thống kê điểm danh"
    :description="data?.class_section ? `${data.class_section.code} — ${data.sessions_count} buổi học` : ''"
    :breadcrumb="['Trang chủ', 'Học vụ', 'Lớp học phần', 'Thống kê điểm danh']"
  >
    <template #actions>
      <NuxtLink :to="`/instructor/sections/${sectionId}/sessions`" class="crud-secondary-btn">
        <span class="material-symbols-outlined">arrow_back</span>
        Phiên học
      </NuxtLink>
    </template>

    <div v-if="loading" class="dashboard-card crud-panel">
      <div class="crud-empty" style="padding:3rem;">Đang tải...</div>
    </div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <div v-else-if="data" class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Điểm danh</p>
          <h3 class="ds-section-title">Danh sách sinh viên</h3>
        </div>
      </div>
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Sinh viên</th>
              <th>MSSV</th>
              <th class="text-center">Có mặt</th>
              <th class="text-center">Đi muộn</th>
              <th class="text-center">Vắng</th>
              <th class="text-center">Tỷ lệ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!data.students.length">
              <td colspan="6" class="crud-empty">Chưa có dữ liệu điểm danh.</td>
            </tr>
            <tr v-for="s in data.students" :key="s.user_id">
              <td><strong>{{ s.name }}</strong></td>
              <td class="text-muted">{{ s.student_code ?? '—' }}</td>
              <td class="text-center"><span class="att-present">{{ s.present }}</span></td>
              <td class="text-center"><span class="att-late">{{ s.late }}</span></td>
              <td class="text-center"><span class="att-absent">{{ s.absent }}</span></td>
              <td class="text-center">
                <span class="att-rate" :class="rateClass(s.attendance_rate)">{{ s.attendance_rate }}%</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </InstructorWorkspaceShell>
</template>

<style scoped>
.att-present { color: var(--green-deep); font-weight: 700; }
.att-late    { color: #b45309; font-weight: 700; }
.att-absent  { color: #b91c1c; font-weight: 700; }
.att-rate    { font-weight: 700; }
.att-rate.text-green-600  { color: var(--green-deep); }
.att-rate.text-yellow-600 { color: #b45309; }
.att-rate.text-red-600    { color: #b91c1c; }
.text-muted { color: var(--muted); font-size: 0.85rem; }
.text-center { text-align: center; }
</style>
