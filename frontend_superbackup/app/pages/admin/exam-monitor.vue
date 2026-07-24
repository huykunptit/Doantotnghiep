<script setup lang="ts">

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
const search = ref('')
const statusFilter = ref('')
const expandedRows = ref({})
const filteredAttempts = computed(() => {
  const query = search.value.trim().toLowerCase()
  return (monitorData.value?.attempts || []).filter((attempt: any) =>
    (!statusFilter.value || attempt.status === statusFilter.value)
    && (!query || attempt.user?.name?.toLowerCase().includes(query) || attempt.user?.email?.toLowerCase().includes(query)),
  )
})
const statusSeverity = (status: string) =>
  status === 'in_progress' ? 'success' : status === 'paused' ? 'warn' : status === 'submitted' ? 'info' : 'danger'

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
  <div class="assessment-page">
    <Toast />
    <div class="page-heading"><div><span>Khảo thí</span><h1>Giám sát kỳ thi trực tiếp</h1><p>Theo dõi và can thiệp bài thi theo thời gian thực.</p></div><Button label="Làm mới" icon="pi pi-refresh" :loading="loading" @click="fetchMonitor" /></div>
    <Card v-if="!examId"><template #content>Vui lòng chọn kỳ thi từ trang <NuxtLink to="/admin/quiz">Quản lý quiz / đề thi</NuxtLink>.</template></Card>
    <template v-else>
      <div v-if="monitorData" class="stats-grid"><Card v-for="item in [{label:'Tổng thí sinh',value:monitorData.summary?.total||0},{label:'Đang thi',value:monitorData.summary?.in_progress||0},{label:'Tạm dừng',value:monitorData.summary?.paused||0},{label:'Đã nộp',value:monitorData.summary?.submitted||0},{label:'Bị dừng',value:monitorData.summary?.force_stopped||0}]" :key="item.label"><template #content><strong>{{ item.value }}</strong><span>{{ item.label }}</span></template></Card></div>
      <Message v-if="error && !activeAction" severity="error">{{ error }}</Message><Message v-if="success" severity="success">{{ success }}</Message>
      <Card><template #title>{{ monitorData?.exam?.title || 'Kỳ thi' }}</template><template #subtitle>Danh sách thí sinh</template><template #content>
        <div class="filter-grid"><label class="field"><span>Tìm thí sinh</span><InputText v-model="search" placeholder="Tên hoặc email..." /></label><label class="field"><span>Trạng thái</span><Select v-model="statusFilter" :options="[{label:'Tất cả',value:''},...Object.entries(statusLabel).map(([value,label])=>({value,label}))]" option-label="label" option-value="value" /></label></div>
        <DataTable v-model:expanded-rows="expandedRows" :value="filteredAttempts" :loading="loading" data-key="id" paginator :rows="15" :rows-per-page-options="[15,30,50]" responsive-layout="scroll" striped-rows>
          <template #empty>Chưa có thí sinh nào tham gia.</template><Column expander style="width:3rem" /><Column header="Thí sinh"><template #body="{data}"><div class="candidate"><strong>{{ data.user?.name||'—' }}</strong><small>{{ data.user?.email||'—' }}</small></div></template></Column><Column field="status" header="Trạng thái" sortable><template #body="{data}"><Tag :value="statusLabel[data.status]||data.status" :severity="statusSeverity(data.status)" /></template></Column><Column field="remaining_time" header="Thời gian còn" sortable><template #body="{data}"><span class="timer" :class="{urgent:(data.remaining_time||0)<300}">{{ data.remaining_time!==null?formatTime(data.remaining_time):'∞' }}</span></template></Column><Column field="violations_count" header="Vi phạm" sortable><template #body="{data}"><Tag :value="String(data.violations_count||0)" :severity="data.violations_count>0?'danger':'success'" /></template></Column><Column header="Thao tác" frozen align-frozen="right"><template #body="{data}"><div class="row-actions"><Button v-if="data.status==='in_progress'" icon="pi pi-pause" size="small" severity="warn" text aria-label="Tạm dừng" @click="openAction('pause',data)" /><Button v-if="data.status==='paused'" icon="pi pi-play" size="small" text aria-label="Tiếp tục" @click="openAction('resume',data)" /><Button v-if="['in_progress','paused'].includes(data.status)" icon="pi pi-clock" size="small" text aria-label="Gia hạn" @click="openAction('extend',data)" /><Button v-if="['in_progress','paused'].includes(data.status)" icon="pi pi-exclamation-triangle" size="small" severity="warn" text aria-label="Cảnh báo" @click="openAction('warn',data)" /><Button v-if="['in_progress','paused'].includes(data.status)" icon="pi pi-stop-circle" size="small" severity="danger" text aria-label="Dừng hẳn" @click="openAction('force-stop',data)" /></div></template></Column>
          <template #expansion="{data}"><div class="expansion-grid"><div><b>Lưu tự động</b><span>{{ data.auto_saved_at?new Date(data.auto_saved_at).toLocaleTimeString('vi'):'—' }}</span></div><div><b>Lý do dừng</b><span>{{ data.force_stop_reason||'—' }}</span></div></div></template>
        </DataTable>
      </template></Card>
    </template>
    <Dialog :visible="!!activeAction" modal :header="modalTitle" :closable="!actionSubmitting" :style="{width:'min(34rem,95vw)'}" @update:visible="value=>{if(!value)closeAction()}">
      <Message v-if="error" severity="error">{{ error }}</Message>
      <p v-if="activeAction?.type==='pause'">Thí sinh sẽ thấy màn hình tạm dừng và đồng hồ ngừng đếm.</p><p v-else-if="activeAction?.type==='resume'">Bài thi sẽ tiếp tục và thời gian tạm dừng được cộng bù.</p>
      <label v-else-if="activeAction?.type==='force-stop'" class="field"><span>Lý do dừng *</span><Textarea v-model="reasonInput" rows="4" maxlength="500" /></label>
      <div v-else-if="activeAction?.type==='extend'" class="dialog-form"><label class="field"><span>Số phút gia hạn</span><InputNumber v-model="minutesInput" :min="1" :max="180" /></label><div class="row-actions"><Button v-for="m in [5,10,15,30]" :key="m" :label="`+${m} phút`" size="small" outlined @click="minutesInput=m" /></div></div>
      <div v-else-if="activeAction?.type==='warn'" class="dialog-form"><label class="field"><span>Mức độ</span><Select v-model="severityInput" :options="[{label:'Thông tin',value:'info'},{label:'Cảnh báo',value:'warning'},{label:'Nghiêm trọng',value:'critical'}]" option-label="label" option-value="value" /></label><label class="field"><span>Nội dung cảnh báo</span><Textarea v-model="messageInput" rows="4" maxlength="500" /></label></div>
      <template #footer><Button label="Hủy" severity="secondary" text :disabled="actionSubmitting" @click="closeAction" /><Button label="Xác nhận" icon="pi pi-check" :loading="actionSubmitting" :severity="activeAction?.type==='force-stop'?'danger':undefined" @click="confirmAction" /></template>
    </Dialog>
  </div>
</template>

<style scoped>
.assessment-page{display:grid;gap:1.25rem}.page-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap}.page-heading span{color:var(--p-text-muted-color);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.page-heading h1{font-size:1.55rem;margin:.2rem 0}.page-heading p{margin:0;color:var(--p-text-muted-color)}.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem}.stats-grid :deep(.p-card-body){padding:1rem}.stats-grid :deep(.p-card-content){display:grid;gap:.25rem}.stats-grid strong{font-size:1.6rem;color:var(--p-primary-color)}.stats-grid span{font-size:.78rem;color:var(--p-text-muted-color)}.filter-grid{display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem}.field{display:grid;gap:.45rem;font-size:.82rem;font-weight:600}.field :deep(.p-inputtext),.field :deep(.p-select),.field :deep(.p-inputnumber){width:100%}.candidate{display:grid;gap:.2rem}.candidate small{color:var(--p-text-muted-color)}.timer{font-family:monospace;font-weight:700}.timer.urgent{color:var(--p-red-500)}.row-actions{display:flex;gap:.25rem;flex-wrap:wrap}.expansion-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;padding:1rem}.expansion-grid>div{display:grid;gap:.25rem}.expansion-grid b{font-size:.75rem;color:var(--p-text-muted-color)}.dialog-form{display:grid;gap:1rem}.assessment-page :deep(.p-card){border:1px solid var(--p-content-border-color);box-shadow:none}@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.filter-grid,.expansion-grid{grid-template-columns:1fr}}
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

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .proctor-modal { background: var(--surface-strong); border: 1px solid rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .proctor-modal__header { border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .proctor-modal__header h3 { color: var(--text); }
[data-theme="dark"] .proctor-modal__footer { background: var(--surface); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .proctor-field span { color: var(--text); }
[data-theme="dark"] .proctor-field input, [data-theme="dark"] .proctor-field select, [data-theme="dark"] .proctor-field textarea { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .proctor-quick-btn { background: rgba(255, 255, 255, 0.05); color: var(--text); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .proctor-quick-btn:hover { background: rgba(255, 255, 255, 0.1); color: var(--green); }
[data-theme="dark"] .monitor-card { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); }
</style>
