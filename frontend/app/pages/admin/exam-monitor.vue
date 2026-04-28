<script setup lang="ts">
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin' })

const route = useRoute()
const token = useAuthTokenCookie()
const user = useAuthUserCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const examId = computed(() => route.query.exam as string | undefined)
const loading = ref(true)
const monitorData = ref<any>(null)
const error = ref('')
const pollInterval = ref<any>(null)

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const statusLabel: Record<string, string> = { in_progress: '🟢 Đang thi', paused: '🟡 Tạm dừng', submitted: '✅ Đã nộp', force_stopped: '🔴 Bị dừng' }
const statusBg: Record<string, string> = { in_progress: '#e8f5e9', paused: '#fff8e1', submitted: '#e3f2fd', force_stopped: '#fce4ec' }

function formatTime(seconds: number | null) {
  if (!seconds || seconds <= 0) return '00:00'
  const m = Math.floor(seconds / 60); const s = seconds % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

async function fetchMonitor() {
  if (!examId.value) return
  try {
    monitorData.value = await useApi(`/exams/${examId.value}/live-monitor`, { headers: authHeaders() })
  } catch (e: any) { error.value = e?.data?.message || 'Không thể tải dữ liệu giám sát.' }
  finally { loading.value = false }
}

async function pauseAttempt(attemptId: number) {
  try {
    await useApi(`/attempts/${attemptId}/pause`, { method: 'POST', headers: authHeaders() })
    await fetchMonitor()
  } catch (e: any) { error.value = e?.data?.message || 'Lỗi' }
}
async function resumeAttempt(attemptId: number) {
  try {
    await useApi(`/attempts/${attemptId}/resume`, { method: 'POST', headers: authHeaders() })
    await fetchMonitor()
  } catch (e: any) { error.value = e?.data?.message || 'Lỗi' }
}
async function forceStopAttempt(attemptId: number) {
  const reason = prompt('Nhập lý do dừng bài thi:')
  if (!reason) return
  try {
    await useApi(`/attempts/${attemptId}/force-stop`, { method: 'POST', headers: authHeaders(), body: { reason } })
    await fetchMonitor()
  } catch (e: any) { error.value = e?.data?.message || 'Lỗi' }
}
async function extendTime(attemptId: number) {
  const minutes = prompt('Số phút gia hạn thêm:')
  if (!minutes || isNaN(Number(minutes))) return
  try {
    await useApi(`/attempts/${attemptId}/extend-time`, { method: 'POST', headers: authHeaders(), body: { minutes: Number(minutes) } })
    await fetchMonitor()
  } catch (e: any) { error.value = e?.data?.message || 'Lỗi' }
}

onMounted(async () => {
  await fetchMonitor()
  pollInterval.value = setInterval(fetchMonitor, 10000) // Poll every 10s
})
onUnmounted(() => { if (pollInterval.value) clearInterval(pollInterval.value) })
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý thi', 'Giám sát kỳ thi']" title="Giám sát kỳ thi trực tiếp" description="Theo dõi thí sinh đang thi, tạm dừng / cho tiếp tục / dừng bài thi trong trường hợp vi phạm.">

    <div v-if="!examId" class="dashboard-card crud-panel">
      <div class="crud-empty" style="padding: 3rem;">Vui lòng chọn kỳ thi từ trang <NuxtLink to="/admin/quiz" style="color: var(--primary);">Quản lý quiz / đề thi</NuxtLink>.</div>
    </div>

    <template v-else>
      <!-- Summary cards -->
      <section v-if="monitorData" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1rem;">
        <div class="dashboard-card" style="padding: 1rem; text-align: center;">
          <div style="font-size: 2rem; font-weight: 800;">{{ monitorData.summary?.total || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Tổng thí sinh</div>
        </div>
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid #4caf50;">
          <div style="font-size: 2rem; font-weight: 800; color: #4caf50;">{{ monitorData.summary?.in_progress || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Đang thi</div>
        </div>
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid #ff9800;">
          <div style="font-size: 2rem; font-weight: 800; color: #ff9800;">{{ monitorData.summary?.paused || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Tạm dừng</div>
        </div>
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid #2196f3;">
          <div style="font-size: 2rem; font-weight: 800; color: #2196f3;">{{ monitorData.summary?.submitted || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Đã nộp</div>
        </div>
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid #f44336;">
          <div style="font-size: 2rem; font-weight: 800; color: #f44336;">{{ monitorData.summary?.force_stopped || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Bị dừng</div>
        </div>
      </section>

      <!-- Attempts table -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">{{ monitorData?.exam?.title || 'Kỳ thi' }}</p>
            <h3>Danh sách thí sinh</h3>
          </div>
          <button class="crud-primary-btn" type="button" @click="fetchMonitor">↻ Làm mới</button>
        </div>
        <div v-if="error" class="crud-alert is-error">{{ error }}</div>
        <div v-if="loading" class="crud-empty" style="padding: 2rem;">Đang tải...</div>
        <div v-else class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Thí sinh</th><th>Email</th><th>Trạng thái</th>
                <th>Thời gian còn</th><th>Vi phạm</th><th>Lưu tự động</th><th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!monitorData?.attempts?.length"><td colspan="7" class="crud-empty">Chưa có thí sinh nào tham gia.</td></tr>
              <tr v-for="a in monitorData?.attempts || []" :key="a.id" :style="{ background: statusBg[a.status] || 'transparent' }">
                <td><strong>{{ a.user?.name || '—' }}</strong></td>
                <td style="font-size: 0.8rem;">{{ a.user?.email || '—' }}</td>
                <td><span style="font-weight: 600;">{{ statusLabel[a.status] || a.status }}</span></td>
                <td style="font-family: monospace; font-weight: 700;" :style="{ color: (a.remaining_time || 0) < 300 ? '#f44336' : 'inherit' }">
                  {{ a.remaining_time !== null ? formatTime(a.remaining_time) : '∞' }}
                </td>
                <td>
                  <span v-if="a.violations_count > 0" style="color: #f44336; font-weight: 700;">⚠ {{ a.violations_count }}</span>
                  <span v-else style="color: #4caf50;">0</span>
                </td>
                <td style="font-size: 0.75rem;">{{ a.auto_saved_at ? new Date(a.auto_saved_at).toLocaleTimeString('vi') : '—' }}</td>
                <td>
                  <div class="crud-actions" style="flex-wrap: wrap;">
                    <button v-if="a.status === 'in_progress'" class="action-btn is-edit" type="button" @click="pauseAttempt(a.id)" title="Tạm dừng">⏸ Dừng</button>
                    <button v-if="a.status === 'paused'" class="action-btn is-view" type="button" @click="resumeAttempt(a.id)" title="Cho tiếp tục">▶ Tiếp</button>
                    <button v-if="a.status === 'in_progress' || a.status === 'paused'" class="action-btn is-delete" type="button" @click="forceStopAttempt(a.id)" title="Dừng hẳn (vi phạm)">⛔ Dừng hẳn</button>
                    <button v-if="a.status === 'in_progress' || a.status === 'paused'" class="action-btn" type="button" @click="extendTime(a.id)" title="Gia hạn thời gian" style="color: #2196f3;">⏱ Gia hạn</button>
                  </div>
                  <div v-if="a.force_stop_reason" style="font-size: 0.75rem; color: #f44336; margin-top: 4px;">Lý do: {{ a.force_stop_reason }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </AdminWorkspaceShell>
</template>
