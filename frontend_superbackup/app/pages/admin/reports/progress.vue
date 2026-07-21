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
const search = ref('')
const sortBy = ref<'enrollments' | 'lessons' | 'title'>('enrollments')

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

const publishedCourses = computed(() =>
  courses.value.filter(c => c.status === 'published')
)

const filteredAndSorted = computed(() => {
  let list = publishedCourses.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(c => c.title?.toLowerCase().includes(q))
  }
  return [...list].sort((a, b) => {
    if (sortBy.value === 'enrollments') return (b.enrollments_count || 0) - (a.enrollments_count || 0)
    if (sortBy.value === 'lessons') return (b.lessons_count || 0) - (a.lessons_count || 0)
    return (a.title || '').localeCompare(b.title || '')
  })
})

const totalEnrollments = computed(() =>
  publishedCourses.value.reduce((s, c) => s + (c.enrollments_count || 0), 0)
)

const maxEnrollment = computed(() =>
  Math.max(...publishedCourses.value.map(c => c.enrollments_count || 0), 1)
)

const avgEnrollment = computed(() =>
  publishedCourses.value.length
    ? Math.round(totalEnrollments.value / publishedCourses.value.length)
    : 0
)

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Tên khóa học', 'Danh mục', 'Số học viên', 'Số bài học']
  const rows = filteredAndSorted.value.slice(0, 200).map(c => [
    c.title || '—',
    c.category?.name || '—',
    c.enrollments_count || 0,
    c.lessons_count || 0,
  ])
  exportToPDF('Tiến độ học tập', `${publishedCourses.value.length} khóa học đang hoạt động`, headers, rows, 'tien-do-hoc-tap')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Khóa học' },
    { key: 'title', label: 'Tên khóa học' },
    { key: 'category', label: 'Danh mục', format: (_: any, row: any) => row.category?.name || 'Chưa phân loại' },
    { key: 'enrollments_count', label: 'Số học viên', format: (val: any) => String(val || 0) },
    { key: 'lessons_count', label: 'Số bài học', format: (val: any) => String(val || 0) },
    { key: 'price', label: 'Giá tiền', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(filteredAndSorted.value, cols, 'tien-do-hoc-tap')
}

onMounted(fetchData)
</script>

<template>
  <div class="report-page">
    <header class="page-header"><div><span>Báo cáo</span><h1>Tiến độ học tập</h1><p>Theo dõi mức độ phổ biến của các khóa học đã xuất bản.</p></div><div class="actions"><Button label="Excel" icon="pi pi-download" severity="secondary" outlined @click="exportCSV"/><Button label="PDF" icon="pi pi-file-pdf" @click="exportPDF"/></div></header>
    <div class="metrics"><Card v-for="m in [{l:'Tổng học viên',v:stats.students_count||totalEnrollments},{l:'Khóa hoạt động',v:publishedCourses.length},{l:'TB đăng ký / khóa',v:avgEnrollment},{l:'Tổng bài học',v:publishedCourses.reduce((s,c)=>s+(c.lessons_count||0),0)}]" :key="m.l" class="metric-card"><template #content><small>{{m.l}}</small><strong>{{m.v}}</strong></template></Card></div>
    <Card><template #title>Khóa học đã xuất bản</template><template #content>
      <div class="filters"><InputText v-model="search" placeholder="Tìm tên khóa học..."/><Select v-model="sortBy" :options="[{label:'Lượt đăng ký',value:'enrollments'},{label:'Số bài học',value:'lessons'},{label:'Tên khóa học',value:'title'}]" option-label="label" option-value="value"/></div>
      <Message v-if="error" severity="error" :closable="false">{{error}}</Message>
      <DataTable v-else :value="filteredAndSorted" :loading="loading" data-key="id" striped-rows responsive-layout="scroll" paginator :rows="15">
        <Column header="Khóa học"><template #body="{data}"><div class="primary-cell"><strong class="wrap-text">{{data.title}}</strong><small>{{data.category?.name||'Chưa phân loại'}}</small></div></template></Column>
        <Column field="lessons_count" header="Bài học"/>
        <Column header="Đăng ký"><template #body="{data}"><div class="list"><div class="list-row"><strong>{{data.enrollments_count||0}} HV</strong></div><div class="bar"><i :style="{width:`${((data.enrollments_count||0)/maxEnrollment)*100}%`}"/></div></div></template></Column>
        <Column header="Giá"><template #body="{data}"><Tag v-if="!data.price" value="Miễn phí" severity="success"/><span v-else class="money">{{new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(data.price)}}</span></template></Column>
        <template #empty>Không có khóa học nào.</template>
      </DataTable>
    </template></Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>
