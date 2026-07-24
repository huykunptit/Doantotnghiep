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
const statusFilter = ref('')

async function fetchExams() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>('/exams/standalone?per_page=200', { headers: authHeaders() })
    exams.value = res.data || res || []
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu kỳ thi.'
  }
  finally {
    loading.value = false
  }
}

const filteredExams = computed(() => {
  return exams.value.filter(e => {
    if (statusFilter.value && e.status !== statusFilter.value) return false
    if (search.value.trim() && !e.title?.toLowerCase().includes(search.value.toLowerCase())) return false
    return true
  })
})

const stats = computed(() => ({
  total: exams.value.length,
  published: exams.value.filter(e => e.status === 'published').length,
  draft: exams.value.filter(e => e.status === 'draft').length,
  totalEnrolled: exams.value.reduce((s, e) => s + (e.enrollments_count || 0), 0),
}))

const statusLabel: Record<string, string> = {
  published: 'Đã xuất bản',
  draft: 'Bản nháp',
  scheduled: 'Đã lên lịch',
  closed: 'Đã đóng',
}
const statusClass: Record<string, string> = {
  published: 'role-instructor',
  draft: '',
  scheduled: 'role-student',
  closed: 'role-admin',
}

function formatDate(v?: string) {
  return v ? new Date(v).toLocaleDateString('vi-VN') : '—'
}

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Tên đề thi', 'Trạng thái', 'Thời gian (phút)', 'Điểm đạt', 'Số thí sinh', 'Ngày tạo']
  const rows = filteredExams.value.slice(0, 200).map(e => [
    e.title || '—',
    statusLabel[e.status] || e.status,
    e.duration || '—',
    e.pass_score || '—',
    e.enrollments_count || 0,
    formatDate(e.created_at),
  ])
  exportToPDF('Báo cáo Kỳ thi', `Tổng ${exams.value.length} đề thi`, headers, rows, 'bao-cao-ky-thi')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Đề thi' },
    { key: 'title', label: 'Tên đề thi' },
    { key: 'status', label: 'Trạng thái', format: (val: any) => statusLabel[val] || val },
    { key: 'duration', label: 'Thời lượng (phút)', format: (val: any) => String(val || '—') },
    { key: 'pass_score', label: 'Điểm đạt (%)', format: (val: any) => String(val || '—') },
    { key: 'enrollments_count', label: 'Số thí sinh', format: (val: any) => String(val || 0) },
    { key: 'created_at', label: 'Ngày tạo', format: (val: any) => formatDate(val) }
  ]
  exportToCSV(filteredExams.value, cols, 'bao-cao-ky-thi')
}

onMounted(fetchExams)
</script>

<template>
  <div class="report-page">
    <header class="page-header"><div><span>Báo cáo</span><h1>Báo cáo kỳ thi</h1><p>Tổng hợp đề thi và số lượng thí sinh đăng ký.</p></div><div class="actions"><Button label="Excel" icon="pi pi-download" severity="secondary" outlined @click="exportCSV"/><Button label="PDF" icon="pi pi-file-pdf" @click="exportPDF"/></div></header>
    <div class="metrics"><Card v-for="m in [{l:'Tổng đề thi',v:stats.total},{l:'Đang hoạt động',v:stats.published},{l:'Bản nháp',v:stats.draft},{l:'Lượt đăng ký',v:stats.totalEnrolled}]" :key="m.l" class="metric-card"><template #content><small>{{m.l}}</small><strong>{{m.v}}</strong></template></Card></div>
    <Card><template #title>Danh sách kỳ thi</template><template #content>
      <div class="filters"><InputText v-model="search" placeholder="Tìm tên đề thi..."/><Select v-model="statusFilter" :options="[{label:'Tất cả trạng thái',value:''},{label:'Đã xuất bản',value:'published'},{label:'Bản nháp',value:'draft'},{label:'Đã lên lịch',value:'scheduled'},{label:'Đã đóng',value:'closed'}]" option-label="label" option-value="value" placeholder="Trạng thái"/><Button as="router-link" label="Quản lý đề thi" severity="secondary" outlined to="/admin/quiz"/></div>
      <Message v-if="error" severity="error" :closable="false">{{error}}</Message>
      <DataTable v-else :value="filteredExams" :loading="loading" data-key="id" striped-rows responsive-layout="scroll" paginator :rows="15">
        <Column header="Tên đề thi"><template #body="{data}"><div class="primary-cell"><strong>{{data.title}}</strong><small class="wrap-text">{{data.description||'—'}}</small></div></template></Column>
        <Column header="Trạng thái"><template #body="{data}"><Tag :value="statusLabel[data.status]||data.status" :severity="data.status==='published'?'success':data.status==='scheduled'?'info':data.status==='closed'?'danger':'secondary'"/></template></Column>
        <Column header="Thời lượng"><template #body="{data}">{{data.duration||data.quiz?.time_limit||'—'}} phút</template></Column>
        <Column header="Điểm đạt"><template #body="{data}">{{data.pass_score??data.quiz?.pass_score??'—'}}</template></Column>
        <Column field="enrollments_count" header="Thí sinh"/>
        <Column header="Ngày tạo"><template #body="{data}"><span class="muted">{{formatDate(data.created_at)}}</span></template></Column>
        <Column header=""><template #body="{data}"><Button as="router-link" label="Giám sát" size="small" severity="secondary" outlined :to="`/admin/exam-monitor?exam=${data.id}`"/></template></Column>
        <template #empty>Không có đề thi nào.</template>
      </DataTable>
    </template></Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>
