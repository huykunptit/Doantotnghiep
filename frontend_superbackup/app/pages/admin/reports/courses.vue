<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const courses = ref<any[]>([])
const stats = ref<any>({})

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [coursesRes, statsRes] = await Promise.all([
      useApi<any>('/admin/courses?per_page=200', { headers: authHeaders() }),
      useApi<any>('/admin/stats', { headers: authHeaders() }),
    ])
    courses.value = coursesRes.data || []
    stats.value = statsRes || {}
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu.'
  }
  finally {
    loading.value = false
  }
}

const performanceMetrics = computed(() => [
  {
    label: 'Tổng khóa học',
    value: stats.value.courses_count || courses.value.length,
    icon: 'graduation-cap',
    color: 'tone-blue',
  },
  {
    label: 'Đã xuất bản',
    value: courses.value.filter(c => c.status === 'published').length,
    icon: 'circle-check-big',
    color: 'tone-green',
  },
  {
    label: 'Tổng đăng ký',
    value: stats.value.students_count || courses.value.reduce((s, c) => s + (c.enrollments_count || 0), 0),
    icon: 'users',
    color: 'tone-amber',
  },
])

const statusDistribution = computed(() => {
  const map: Record<string, number> = {}
  courses.value.forEach(c => {
    map[c.status] = (map[c.status] || 0) + 1
  })
  return Object.entries(map).map(([status, count]) => ({
    status,
    count,
    label: { published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[status] || status,
  })).sort((a, b) => b.count - a.count)
})

const categoryDistribution = computed(() => {
  const map: Record<string, number> = {}
  courses.value.forEach(c => {
    const cat = c.category?.name || 'Chưa phân loại'
    map[cat] = (map[cat] || 0) + 1
  })
  const total = courses.value.length || 1
  return Object.entries(map)
    .sort(([, a], [, b]) => b - a)
    .slice(0, 6)
    .map(([name, count]) => ({ name, count, percentage: Math.round((count / total) * 100) }))
})

const topByEnrollment = computed(() =>
  [...courses.value]
    .sort((a, b) => (b.enrollments_count || 0) - (a.enrollments_count || 0))
    .slice(0, 8)
)

const maxEnrollment = computed(() =>
  Math.max(...topByEnrollment.value.map(c => c.enrollments_count || 0), 1)
)

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Tên khóa học', 'Danh mục', 'Trạng thái', 'Lượt đăng ký', 'Giảng viên']
  const rows = courses.value.slice(0, 200).map(c => [
    c.title || '—',
    c.category?.name || '—',
    { published: 'Xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[c.status] || c.status,
    c.enrollments_count || 0,
    c.user?.name || '—',
  ])
  exportToPDF('Báo cáo Khóa học', `Tổng ${courses.value.length} khóa học`, headers, rows, 'bao-cao-khoa-hoc')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Khóa học' },
    { key: 'title', label: 'Tên khóa học' },
    { key: 'category', label: 'Danh mục', format: (_: any, row: any) => row.category?.name || 'Chưa phân loại' },
    { key: 'status', label: 'Trạng thái', format: (val: any) => ({ published: 'Xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[val] || val) },
    { key: 'enrollments_count', label: 'Lượt đăng ký', format: (val: any) => String(val || 0) },
    { key: 'lessons_count', label: 'Bài học', format: (val: any) => String(val || 0) },
    { key: 'price', label: 'Giá', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(courses.value, cols, 'bao-cao-khoa-hoc')
}

onMounted(fetchData)
</script>

<template>
  <div class="report-page">
    <header class="page-header"><div><span>Báo cáo</span><h1>Báo cáo theo khóa học</h1><p>Phân bổ danh mục, trạng thái và hiệu quả đào tạo.</p></div><div class="actions"><Button label="Excel" icon="pi pi-download" severity="secondary" outlined @click="exportCSV"/><Button label="PDF" icon="pi pi-file-pdf" @click="exportPDF"/></div></header>
    <div class="metrics"><Card v-for="m in performanceMetrics" :key="m.label" class="metric-card"><template #content><small>{{m.label}}</small><strong>{{m.value}}</strong></template></Card></div>
    <Message v-if="error" severity="error" :closable="false">{{error}}</Message>
    <div v-else class="distribution">
      <Card><template #title>Phân bổ theo danh mục</template><template #content><div class="list"><div v-for="cat in categoryDistribution" :key="cat.name"><div class="list-row"><strong>{{cat.name}}</strong><span>{{cat.count}} khóa · {{cat.percentage}}%</span></div><div class="bar"><i :style="{width:`${cat.percentage}%`}"/></div></div></div></template></Card>
      <Card><template #title>Theo trạng thái</template><template #content><div class="list"><div v-for="s in statusDistribution" :key="s.status" class="list-row"><span>{{s.label}}</span><Tag :value="String(s.count)" :severity="s.status==='published'?'success':s.status==='rejected'?'danger':'secondary'"/></div></div></template></Card>
    </div>
    <Card><template #title>Khóa học nhiều học viên nhất</template><template #content>
      <DataTable :value="topByEnrollment" :loading="loading" data-key="id" striped-rows responsive-layout="scroll">
        <Column field="title" header="Khóa học"/>
        <Column header="Danh mục"><template #body="{data}">{{data.category?.name||'Chưa phân loại'}}</template></Column>
        <Column header="Trạng thái"><template #body="{data}"><Tag :value="data.status" :severity="data.status==='published'?'success':'secondary'"/></template></Column>
        <Column field="enrollments_count" header="Đăng ký"/>
        <Column header="Giảng viên"><template #body="{data}">{{data.user?.name||'—'}}</template></Column>
        <template #empty>Chưa có dữ liệu.</template>
      </DataTable>
    </template></Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>
