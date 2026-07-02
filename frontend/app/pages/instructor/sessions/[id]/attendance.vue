<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

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
  <InstructorWorkspaceShell
    title="Báo cáo điểm danh"
    :description="data?.session ? `${data.session.title} — ${new Date(data.session.start_at).toLocaleDateString('vi-VN')}` : ''"
    :breadcrumb="['Trang chủ', 'Học vụ', 'Phiên học', 'Điểm danh']"
  >
    <template #actions>
      <NuxtLink
        :to="`/instructor/sections/${data?.session?.class_section_id ?? ''}/sessions`"
        class="crud-secondary-btn"
      >
        <span class="material-symbols-outlined">arrow_back</span>
        Phiên học
      </NuxtLink>
    </template>

    <div v-if="loading" class="dashboard-card crud-panel">
      <div class="crud-empty" style="padding:3rem;">Đang tải...</div>
    </div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else-if="data">
      <!-- KPI -->
      <div class="ds-stats mb-0">
        <div class="ds-stat ds-stat--blue">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">people</span></div>
          <p class="ds-stat-label">Tổng</p>
          <strong class="ds-stat-value">{{ data.summary.total }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
        <div class="ds-stat ds-stat--green">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">how_to_reg</span></div>
          <p class="ds-stat-label">Có mặt</p>
          <strong class="ds-stat-value">{{ data.summary.present }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
        <div class="ds-stat ds-stat--amber">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">schedule</span></div>
          <p class="ds-stat-label">Đi muộn</p>
          <strong class="ds-stat-value">{{ data.summary.late }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
        <div class="ds-stat ds-stat--red">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">person_off</span></div>
          <p class="ds-stat-label">Vắng</p>
          <strong class="ds-stat-value">{{ data.summary.absent }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
      </div>

      <!-- Table -->
      <div class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Chi tiết</p>
            <h3 class="ds-section-title">Danh sách điểm danh</h3>
          </div>
        </div>
        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Sinh viên</th>
                <th>MSSV</th>
                <th>Trạng thái</th>
                <th>Thời gian</th>
                <th>Khoảng cách</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!data.rows.length">
                <td colspan="5" class="crud-empty">Chưa có dữ liệu điểm danh.</td>
              </tr>
              <tr v-for="row in data.rows" :key="row.user_id">
                <td><strong>{{ row.name }}</strong></td>
                <td class="att-mssv">{{ row.student_code ?? '—' }}</td>
                <td>
                  <span class="att-badge" :class="`att-badge--${row.status}`">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td class="att-mssv">{{ formatTime(row.checked_in_at) }}</td>
                <td class="att-mssv">
                  {{ row.distance_meters !== null ? `${row.distance_meters.toFixed(1)} m` : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </InstructorWorkspaceShell>
</template>

<style scoped>
.att-mssv { color: var(--muted); font-size: 0.85rem; }
.att-badge {
  display: inline-flex; align-items: center; height: 22px; padding: 0 10px;
  border-radius: 999px; font-size: 0.72rem; font-weight: 700; border: 1px solid transparent;
}
.att-badge--present { background: rgba(29,158,117,0.1); color: var(--green-deep); border-color: rgba(29,158,117,0.2); }
.att-badge--late    { background: rgba(217,119,6,0.1);  color: #b45309;           border-color: rgba(217,119,6,0.2); }
.att-badge--absent  { background: rgba(239,68,68,0.1);  color: #b91c1c;           border-color: rgba(239,68,68,0.2); }
</style>
