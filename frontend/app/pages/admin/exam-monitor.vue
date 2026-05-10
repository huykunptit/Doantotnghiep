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
const success = ref('')
const pollInterval = ref<any>(null)

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const statusLabel: Record<string, string> = { in_progress: '🟢 Đang thi', paused: '🟡 Tạm dừng', submitted: '✅ Đã nộp', force_stopped: '🔴 Bị dừng' }
const statusBg: Record<string, string> = { in_progress: '#e8f5e9', paused: '#fff8e1', submitted: '#e3f2fd', force_stopped: '#fce4ec' }

type ActionType = 'pause' | 'resume' | 'force-stop' | 'extend' | 'warn'
interface ActiveAction { type: ActionType; attempt: any }

const activeAction = ref<ActiveAction | null>(null)
const actionSubmitting = ref(false)
const reasonInput = ref('')
const minutesInput = ref(5)
const messageInput = ref('')
const severityInput = ref<'info' | 'warning' | 'critical'>('warning')

const modalTitle = computed(() => {
  if (!activeAction.value) return ''
  const name = activeAction.value.attempt?.user?.name || 'thí sinh'
  switch (activeAction.value.type) {
    case 'pause': return `Tạm dừng bài thi của ${name}?`
    case 'resume': return `Cho ${name} tiếp tục bài thi?`
    case 'force-stop': return `Dừng hẳn bài thi của ${name}?`
    case 'extend': return `Gia hạn thời gian cho ${name}`
    case 'warn': return `Gửi cảnh báo tới ${name}`
    default: return ''
  }
})

function openAction(type: ActionType, attempt: any) {
  activeAction.value = { type, attempt }
  reasonInput.value = ''
  minutesInput.value = 5
  messageInput.value = ''
  severityInput.value = type === 'force-stop' ? 'critical' : 'warning'
  error.value = ''
  success.value = ''
}

function closeAction() {
  if (actionSubmitting.value) return
  activeAction.value = null
}

function flashSuccess(msg: string) {
  success.value = msg
  setTimeout(() => { if (success.value === msg) success.value = '' }, 4000)
}

function formatTime(seconds: number | null) {
  if (!seconds || seconds <= 0) return '00:00'
  const m = Math.floor(seconds / 60); const s = seconds % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

async function fetchMonitor() {
  if (!examId.value) return
  try {
    monitorData.value = await useApi(`/exams/${examId.value}/live-monitor`, { headers: authHeaders() })
    if (loading.value) loading.value = false
  } catch (e: any) { error.value = e?.data?.message || 'Không thể tải dữ liệu giám sát.' }
  finally { loading.value = false }
}

async function confirmAction() {
  if (!activeAction.value) return
  const { type, attempt } = activeAction.value
  const id = attempt.id

  if (type === 'force-stop' && !reasonInput.value.trim()) {
    error.value = 'Vui lòng nhập lý do dừng bài.'
    return
  }
  if (type === 'extend' && (!minutesInput.value || minutesInput.value <= 0)) {
    error.value = 'Số phút gia hạn phải lớn hơn 0.'
    return
  }
  if (type === 'warn' && !messageInput.value.trim()) {
    error.value = 'Vui lòng nhập nội dung cảnh báo.'
    return
  }

  actionSubmitting.value = true
  error.value = ''
  try {
    if (type === 'pause') {
      await useApi(`/attempts/${id}/pause`, { method: 'POST', headers: authHeaders() })
      flashSuccess('Đã tạm dừng bài thi.')
    } else if (type === 'resume') {
      await useApi(`/attempts/${id}/resume`, { method: 'POST', headers: authHeaders() })
      flashSuccess('Đã cho tiếp tục bài thi.')
    } else if (type === 'force-stop') {
      await useApi(`/attempts/${id}/force-stop`, { method: 'POST', headers: authHeaders(), body: { reason: reasonInput.value.trim() } })
      flashSuccess('Đã dừng bài thi.')
    } else if (type === 'extend') {
      await useApi(`/attempts/${id}/extend-time`, { method: 'POST', headers: authHeaders(), body: { minutes: Number(minutesInput.value) } })
      flashSuccess(`Đã gia hạn thêm ${minutesInput.value} phút.`)
    } else if (type === 'warn') {
      await useApi(`/attempts/${id}/warn`, {
        method: 'POST',
        headers: authHeaders(),
        body: { message: messageInput.value.trim(), severity: severityInput.value },
      })
      flashSuccess('Đã gửi cảnh báo tới thí sinh.')
    }
    activeAction.value = null
    await fetchMonitor()
  } catch (e: any) {
    error.value = e?.data?.message || 'Lỗi thực hiện thao tác.'
  } finally {
    actionSubmitting.value = false
  }
}

onMounted(async () => {
  await fetchMonitor()
  pollInterval.value = setInterval(fetchMonitor, 10000) // Poll every 10s
})
onUnmounted(() => { if (pollInterval.value) clearInterval(pollInterval.value) })
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý thi', 'Giám sát kỳ thi']" title="Giám sát kỳ thi trực tiếp" description="Theo dõi thí sinh đang thi, tạm dừng / cho tiếp tục / dừng / gia hạn / gửi cảnh báo trong trường hợp vi phạm.">

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
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid var(--green);">
          <div style="font-size: 2rem; font-weight: 800; color: var(--green);">{{ monitorData.summary?.in_progress || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Đang thi</div>
        </div>
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid #ff9800;">
          <div style="font-size: 2rem; font-weight: 800; color: #ff9800;">{{ monitorData.summary?.paused || 0 }}</div>
          <div style="font-size: 0.8rem; color: #666;">Tạm dừng</div>
        </div>
        <div class="dashboard-card" style="padding: 1rem; text-align: center; border-left: 3px solid var(--green);">
          <div style="font-size: 2rem; font-weight: 800; color: var(--green);">{{ monitorData.summary?.submitted || 0 }}</div>
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
        <div v-if="error && !activeAction" class="crud-alert is-error">{{ error }}</div>
        <div v-if="success" class="crud-alert is-success">{{ success }}</div>
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
                  <span v-else style="color: var(--green);">0</span>
                </td>
                <td style="font-size: 0.75rem;">{{ a.auto_saved_at ? new Date(a.auto_saved_at).toLocaleTimeString('vi') : '—' }}</td>
                <td>
                  <div class="crud-actions" style="flex-wrap: wrap;">
                    <button v-if="a.status === 'in_progress'" class="action-btn is-edit" type="button" title="Tạm dừng" @click="openAction('pause', a)">⏸ Dừng</button>
                    <button v-if="a.status === 'paused'" class="action-btn is-view" type="button" title="Cho tiếp tục" @click="openAction('resume', a)">▶ Tiếp</button>
                    <button v-if="a.status === 'in_progress' || a.status === 'paused'" class="action-btn is-delete" type="button" title="Dừng hẳn (vi phạm)" @click="openAction('force-stop', a)">⛔ Dừng hẳn</button>
                    <button v-if="a.status === 'in_progress' || a.status === 'paused'" class="action-btn" type="button" title="Gia hạn thời gian" style="color: var(--green);" @click="openAction('extend', a)">⏱ Gia hạn</button>
                    <button v-if="a.status === 'in_progress' || a.status === 'paused'" class="action-btn" type="button" title="Gửi cảnh báo tới màn hình thí sinh" style="color: #d97706;" @click="openAction('warn', a)">⚠ Cảnh báo</button>
                  </div>
                  <div v-if="a.force_stop_reason" style="font-size: 0.75rem; color: #f44336; margin-top: 4px;">Lý do: {{ a.force_stop_reason }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="activeAction" class="proctor-overlay" @click.self="closeAction">
        <div class="proctor-modal">
          <header class="proctor-modal__header">
            <h3>{{ modalTitle }}</h3>
            <button class="proctor-modal__close" type="button" :disabled="actionSubmitting" @click="closeAction">✕</button>
          </header>

          <div class="proctor-modal__body">
            <div v-if="error" class="crud-alert is-error" style="margin-bottom: 12px;">{{ error }}</div>

            <p v-if="activeAction.type === 'pause'" class="proctor-modal__hint">
              Thí sinh sẽ thấy màn hình tạm dừng và đồng hồ sẽ ngừng đếm ngược cho đến khi bạn cho tiếp tục.
            </p>

            <p v-else-if="activeAction.type === 'resume'" class="proctor-modal__hint">
              Bài thi sẽ tiếp tục đếm ngược. Khoảng thời gian tạm dừng sẽ được cộng thêm vào tổng thời gian.
            </p>

            <template v-else-if="activeAction.type === 'force-stop'">
              <p class="proctor-modal__hint">Thao tác này sẽ kết thúc bài thi của thí sinh và đánh dấu là vi phạm.</p>
              <label class="proctor-field">
                <span>Lý do dừng (bắt buộc)</span>
                <textarea v-model="reasonInput" rows="3" maxlength="500" placeholder="VD: Phát hiện gian lận khi sử dụng tài liệu..."></textarea>
              </label>
            </template>

            <template v-else-if="activeAction.type === 'extend'">
              <p class="proctor-modal__hint">Cộng thêm thời gian vào tổng thời gian được phép cho bài thi.</p>
              <label class="proctor-field">
                <span>Số phút gia hạn</span>
                <input v-model.number="minutesInput" type="number" min="1" max="180" placeholder="5">
              </label>
              <div class="proctor-quick-row">
                <button v-for="m in [5, 10, 15, 30]" :key="m" type="button" class="proctor-quick-btn" @click="minutesInput = m">+{{ m }}p</button>
              </div>
            </template>

            <template v-else-if="activeAction.type === 'warn'">
              <p class="proctor-modal__hint">Tin nhắn sẽ hiển thị ngay trên màn hình của thí sinh trong vòng 10 giây tới.</p>
              <label class="proctor-field">
                <span>Mức độ</span>
                <select v-model="severityInput">
                  <option value="info">Thông tin</option>
                  <option value="warning">Cảnh báo</option>
                  <option value="critical">Nghiêm trọng</option>
                </select>
              </label>
              <label class="proctor-field">
                <span>Nội dung cảnh báo</span>
                <textarea v-model="messageInput" rows="3" maxlength="500" placeholder="VD: Vui lòng tập trung vào màn hình bài thi và không mở tab khác."></textarea>
              </label>
            </template>
          </div>

          <footer class="proctor-modal__footer">
            <button class="crud-secondary-btn" type="button" :disabled="actionSubmitting" @click="closeAction">Hủy</button>
            <button class="crud-primary-btn" type="button" :disabled="actionSubmitting" @click="confirmAction">
              {{ actionSubmitting ? 'Đang xử lý...' : 'Xác nhận' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.proctor-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 70;
}
.proctor-modal {
  width: min(100%, 520px);
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}
.proctor-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0;
}
.proctor-modal__header h3 { margin: 0; font-size: 1.05rem; }
.proctor-modal__close {
  background: transparent;
  border: none;
  font-size: 1.1rem;
  color: #64748b;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 8px;
}
.proctor-modal__close:hover:not(:disabled) { background: #f1f5f9; color: #0f172a; }
.proctor-modal__body { padding: 1.25rem; overflow: auto; }
.proctor-modal__hint { margin: 0 0 0.85rem; color: #475569; font-size: 0.9rem; line-height: 1.5; }
.proctor-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.65rem;
  padding: 0.95rem 1.25rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}
.proctor-field {
  display: grid;
  gap: 6px;
  margin-bottom: 0.85rem;
}
.proctor-field span { font-size: 0.8rem; font-weight: 700; color: #334155; }
.proctor-field input,
.proctor-field select,
.proctor-field textarea {
  width: 100%;
  padding: 0.65rem 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  font: inherit;
  background: #fff;
}
.proctor-field input:focus,
.proctor-field select:focus,
.proctor-field textarea:focus {
  outline: none;
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.15);
}
.proctor-quick-row { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.25rem; }
.proctor-quick-btn {
  padding: 0.45rem 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 700;
  color: #334155;
}
.proctor-quick-btn:hover { border-color: var(--green); color: #1558b0; background: rgba(var(--green-rgb), 0.05); }
</style>
