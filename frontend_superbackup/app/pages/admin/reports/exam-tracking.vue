<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const exams = ref<any[]>([])
const search = ref('')
const selectedExamId = ref<string | null>(null)
const monitorData = ref<any>(null)
const monitorLoading = ref(false)
const monitorError = ref('')
const expandedRows = ref({})

async function fetchExams() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>('/exams/standalone?per_page=100', { headers: authHeaders() })
    exams.value = res.data || res || []
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải danh sách kỳ thi.'
  }
  finally {
    loading.value = false
  }
}

async function selectExam(examId: string) {
  selectedExamId.value = examId
  monitorData.value = null
  monitorError.value = ''
  monitorLoading.value = true
  try {
    monitorData.value = await useApi<any>(`/exams/${examId}/live-monitor`, { headers: authHeaders() })
  }
  catch (e: any) {
    monitorError.value = e?.data?.message || 'Không thể tải dữ liệu giám sát.'
  }
  finally {
    monitorLoading.value = false
  }
}

const filteredExams = computed(() => {
  if (!search.value.trim()) return exams.value
  const q = search.value.toLowerCase()
  return exams.value.filter(e => e.title?.toLowerCase().includes(q))
})

const selectedExam = computed(() =>
  exams.value.find(e => String(e.id) === String(selectedExamId.value))
)

const statusLabel: Record<string, string> = {
  in_progress: 'Đang thi',
  paused: 'Tạm dừng',
  submitted: 'Đã nộp',
  force_stopped: 'Bị dừng',
}

const statusBg: Record<string, string> = {
  in_progress: '#e8f5e9',
  paused: '#fff8e1',
  submitted: '#e3f2fd',
  force_stopped: '#fce4ec',
}
const statusSeverity = (status: string) =>
  status === 'in_progress' ? 'success' : status === 'paused' ? 'warn' : status === 'submitted' ? 'info' : 'danger'

function formatTime(seconds: number | null) {
  if (!seconds || seconds <= 0) return '00:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Tên kỳ thi', 'Trạng thái', 'Thí sinh đăng ký', 'Điểm đạt']
  const rows = exams.value.slice(0, 200).map(e => [
    e.title || '—',
    e.status || '—',
    e.enrollments_count || 0,
    e.pass_score || '—',
  ])
  exportToPDF('Danh sách kỳ thi', `Tổng ${exams.value.length} kỳ thi`, headers, rows, 'theo-doi-ky-thi')
}

function exportCSV() {
  if (!monitorData.value || !monitorData.value.attempts) return
  const cols = [
    { key: 'user_name', label: 'Thí sinh', format: (_: any, row: any) => row.user?.name || '—' },
    { key: 'user_email', label: 'Email', format: (_: any, row: any) => row.user?.email || '—' },
    { key: 'status', label: 'Trạng thái', format: (val: any) => statusLabel[val] || val },
    { key: 'remaining_time', label: 'Thời gian còn lại', format: (val: any) => formatTime(val) },
    { key: 'violations_count', label: 'Số lần vi phạm', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(monitorData.value.attempts, cols, `giam_sat_ky_thi_${selectedExamId.value}`)
}

onMounted(fetchExams)
</script>

<template>
  <div class="assessment-page">
    <Toast />
    <div class="page-heading"><div><span>Khảo thí</span><h1>Theo dõi kỳ thi</h1><p>Chọn kỳ thi để xem trạng thái thí sinh theo thời gian thực.</p></div><Button label="Xuất danh sách PDF" icon="pi pi-file-pdf" severity="secondary" outlined @click="exportPDF" /></div>
    <Message v-if="error" severity="error">{{ error }}</Message>
    <Card><template #title>Bộ lọc kỳ thi</template><template #content><div class="filter-grid"><label class="field"><span>Tìm kỳ thi</span><InputText v-model="search" placeholder="Nhập tên kỳ thi..." /></label><label class="field"><span>Kỳ thi</span><Select :model-value="selectedExamId" :options="filteredExams" option-label="title" option-value="id" filter placeholder="Chọn kỳ thi" :loading="loading" @update:model-value="value=>selectExam(String(value))" /></label></div></template></Card>
    <Card v-if="!selectedExamId"><template #content><div class="empty-state"><i class="pi pi-eye-slash" /><span>Chọn một kỳ thi để xem dữ liệu giám sát.</span></div></template></Card>
    <Message v-else-if="monitorError" severity="error">{{ monitorError }}</Message>
    <template v-else-if="monitorData">
      <Card><template #title>{{ selectedExam?.title }}</template><template #content><div class="monitor-head"><span>Dữ liệu theo dõi trực tiếp</span><Button as="a" :href="`/admin/exam-monitor?exam=${selectedExamId}`" label="Mở giám sát đầy đủ" icon="pi pi-external-link" /></div></template></Card>
      <div class="stats-grid"><Card v-for="item in [{label:'Tổng',value:monitorData.summary?.total||0},{label:'Đang thi',value:monitorData.summary?.in_progress||0},{label:'Tạm dừng',value:monitorData.summary?.paused||0},{label:'Đã nộp',value:monitorData.summary?.submitted||0},{label:'Bị dừng',value:monitorData.summary?.force_stopped||0}]" :key="item.label"><template #content><strong>{{ item.value }}</strong><span>{{ item.label }}</span></template></Card></div>
      <Card><template #title>Danh sách thí sinh</template><template #subtitle>Chỉ xem · Dùng trang giám sát đầy đủ để thao tác</template><template #content>
        <div class="table-actions"><Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined :disabled="!monitorData.attempts?.length" @click="exportCSV" /></div>
        <DataTable v-model:expanded-rows="expandedRows" :value="monitorData.attempts||[]" :loading="monitorLoading" data-key="id" paginator :rows="15" :rows-per-page-options="[15,30,50]" striped-rows responsive-layout="scroll">
          <template #empty>Chưa có thí sinh nào.</template><Column expander style="width:3rem" /><Column header="Thí sinh"><template #body="{data}"><div class="candidate"><strong>{{ data.user?.name||'—' }}</strong><small>{{ data.user?.email||'—' }}</small></div></template></Column><Column field="status" header="Trạng thái" sortable><template #body="{data}"><Tag :value="statusLabel[data.status]||data.status" :severity="statusSeverity(data.status)" /></template></Column><Column field="remaining_time" header="Thời gian còn" sortable><template #body="{data}"><span class="timer" :class="{urgent:(data.remaining_time||0)<300}">{{ data.remaining_time!==null?formatTime(data.remaining_time):'∞' }}</span></template></Column><Column field="violations_count" header="Vi phạm" sortable><template #body="{data}"><Tag :value="String(data.violations_count||0)" :severity="data.violations_count>0?'danger':'success'" /></template></Column><template #expansion="{data}"><div class="expansion-grid"><div><b>Email</b><span>{{ data.user?.email||'—' }}</span></div><div><b>Lưu tự động</b><span>{{ data.auto_saved_at?new Date(data.auto_saved_at).toLocaleTimeString('vi'):'—' }}</span></div></div></template>
        </DataTable>
      </template></Card>
    </template>
  </div>
</template>

<style scoped>
.assessment-page{display:grid;gap:1.25rem}.page-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap}.page-heading span{color:var(--p-text-muted-color);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.page-heading h1{font-size:1.55rem;margin:.2rem 0}.page-heading p{margin:0;color:var(--p-text-muted-color)}.filter-grid{display:grid;grid-template-columns:1fr 2fr;gap:1rem}.field{display:grid;gap:.45rem;font-size:.82rem;font-weight:600}.field :deep(.p-inputtext),.field :deep(.p-select){width:100%}.empty-state{display:grid;place-items:center;gap:1rem;padding:3rem;color:var(--p-text-muted-color)}.empty-state i{font-size:2rem}.monitor-head,.table-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem}.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem}.stats-grid :deep(.p-card-content){display:grid;gap:.25rem}.stats-grid strong{font-size:1.6rem;color:var(--p-primary-color)}.stats-grid span,.candidate small{font-size:.78rem;color:var(--p-text-muted-color)}.candidate{display:grid;gap:.2rem}.table-actions{justify-content:flex-end;margin-bottom:1rem}.timer{font-family:monospace;font-weight:700}.timer.urgent{color:var(--p-red-500)}.expansion-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;padding:1rem}.expansion-grid>div{display:grid;gap:.25rem}.expansion-grid b{font-size:.75rem;color:var(--p-text-muted-color)}.assessment-page :deep(.p-card){border:1px solid var(--p-content-border-color);box-shadow:none}@media(max-width:800px){.stats-grid{grid-template-columns:repeat(2,1fr)}.filter-grid{grid-template-columns:1fr}}
</style>
