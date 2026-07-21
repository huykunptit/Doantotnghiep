<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý quiz / đề thi...' })

interface CourseItem { id: number; title: string; category?: { name: string } | null }
interface ExamItem {
  id: number; title: string; description?: string | null; type?: string
  duration?: number | null; pass_score?: number | null; max_attempts?: number
  status?: string | null; starts_at?: string | null; ends_at?: string | null
  exam_enrollments_count?: number
}

const STATUS_MAP: Record<string, string> = {
  draft: 'Nháp', scheduled: 'Lên lịch', active: 'Đang thi', closed: 'Đã đóng', archived: 'Lưu trữ',
}

const user = useAuthUserCookie(); const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const toast = useToast()

const activeTab = ref<'course' | 'standalone'>('standalone')
const courses = ref<CourseItem[]>([])
const exams = ref<ExamItem[]>([])
const standaloneExams = ref<ExamItem[]>([])
const selectedCourseId = ref<number | null>(null)
const loadingCourses = ref(false)
const loadingExams = ref(false)
const confirmOpen = ref(false)
const selectedExam = ref<ExamItem | null>(null)
const examPage = ref(1)
const examPerPage = ref(10)
const search = ref('')
const statusFilter = ref('')
const expandedRows = ref({})

const allCurrentExams = computed(() => activeTab.value === 'standalone' ? standaloneExams.value : exams.value)
const filteredExams = computed(() => {
  const query = search.value.trim().toLowerCase()
  return allCurrentExams.value.filter(exam =>
    (!statusFilter.value || (exam.status || 'draft') === statusFilter.value)
    && (!query || exam.title.toLowerCase().includes(query) || (exam.description || '').toLowerCase().includes(query)),
  )
})
const currentExams = computed(() => {
  const start = (examPage.value - 1) * examPerPage.value
  return allCurrentExams.value.slice(start, start + examPerPage.value)
})
const examLastPage = computed(() => Math.max(1, Math.ceil(allCurrentExams.value.length / examPerPage.value)))
const selectedCourse = computed(() => courses.value.find(c => c.id === selectedCourseId.value))

async function fetchCourses() {
  loadingCourses.value = true
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    courses.value = res.data || []
  } catch { toast.error('Không thể tải danh sách khóa học.') }
  finally { loadingCourses.value = false }
}

async function fetchExams() {
  if (!selectedCourseId.value) return
  loadingExams.value = true
  try {
    exams.value = await useApi<ExamItem[]>(`/courses/${selectedCourseId.value}/exams`, { headers: authHeaders() })
  } catch { toast.error('Không thể tải danh sách đề thi.') }
  finally { loadingExams.value = false }
}

async function fetchStandaloneExams() {
  loadingExams.value = true
  try {
    standaloneExams.value = await useApi<ExamItem[]>('/exams/standalone', { headers: authHeaders() })
  } catch { toast.error('Không thể tải kỳ thi độc lập.') }
  finally { loadingExams.value = false }
}

async function deleteExam() {
  if (!selectedExam.value) return
  try {
    if (activeTab.value === 'standalone') {
      await useApi(`/exams/${selectedExam.value.id}`, { method: 'DELETE', headers: authHeaders() })
      await fetchStandaloneExams()
    } else if (selectedCourseId.value) {
      await useApi(`/courses/${selectedCourseId.value}/exams/${selectedExam.value.id}`, { method: 'DELETE', headers: authHeaders() })
      await fetchExams()
    }
    toast.success('Đã xóa đề thi.')
    confirmOpen.value = false; selectedExam.value = null
  } catch (e: any) { toast.error(e?.data?.message || 'Không thể xóa đề thi.') }
}

function onTabChange(tab: 'course' | 'standalone') {
  activeTab.value = tab
  examPage.value = 1
  if (tab === 'standalone') fetchStandaloneExams()
  else if (selectedCourseId.value) fetchExams()
}

const { exportToCSV } = useExport()
function exportData() {
  const cols = [
    { key: 'id', label: 'ID' },
    { key: 'title', label: 'Tên đề thi' },
    { key: 'duration', label: 'Thời lượng (phút)', format: (v: any) => String(v || 0) },
    { key: 'pass_score', label: 'Điểm đạt (%)', format: (v: any) => String(v || 0) },
    { key: 'max_attempts', label: 'Số lần thi', format: (v: any) => String(v || 1) },
    { key: 'exam_enrollments_count', label: 'Học viên', format: (v: any) => String(v || 0) },
    { key: 'status', label: 'Trạng thái', format: (v: any) => STATUS_MAP[v] || v },
  ]
  exportToCSV(currentExams.value, cols, `de_thi_${activeTab.value}`)
}

function goCreate() {
  navigateTo(`/admin/quiz/create?type=${activeTab.value === 'standalone' ? 'standalone' : 'course_final'}`)
}

const fmtDate = (d?: string | null) =>
  d ? new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'

onMounted(async () => {
  await fetchCourses()
  await fetchStandaloneExams()
})
</script>

<template>
  <div class="assessment-page">
    <Toast />
    <div class="page-heading"><div><span>Khảo thí</span><h1>Quản lý Quiz / Đề thi</h1><p>Quản lý đề thi theo khóa học hoặc kỳ thi độc lập.</p></div><div class="heading-actions"><Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined @click="exportData" /><Button label="Thêm đề thi" icon="pi pi-plus" @click="goCreate" /></div></div>
    <Card><template #title>Bộ lọc</template><template #content>
      <div class="filter-grid">
        <label class="field"><span>Phạm vi</span><Select :model-value="activeTab" :options="[{label:'Kỳ thi độc lập',value:'standalone'},{label:'Đề thi khóa học',value:'course'}]" option-label="label" option-value="value" @update:model-value="onTabChange" /></label>
        <label v-if="activeTab === 'course'" class="field course-field"><span>Khóa học</span><Select v-model="selectedCourseId" :options="courses" option-label="title" option-value="id" filter :loading="loadingCourses" placeholder="Chọn khóa học" @change="fetchExams" /></label>
        <label class="field"><span>Tìm kiếm</span><InputText v-model="search" placeholder="Tên hoặc mô tả đề thi..." /></label>
        <label class="field"><span>Trạng thái</span><Select v-model="statusFilter" :options="[{label:'Tất cả',value:''},...Object.entries(STATUS_MAP).map(([value,label])=>({value,label}))]" option-label="label" option-value="value" /></label>
      </div>
    </template></Card>
    <Card><template #title>{{ activeTab === 'standalone' ? 'Kỳ thi độc lập' : (selectedCourse?.title || 'Chưa chọn khóa học') }}</template><template #subtitle>{{ filteredExams.length }} đề thi</template><template #content>
      <DataTable v-model:expanded-rows="expandedRows" :value="filteredExams" :loading="loadingExams" data-key="id" paginator :rows="10" :rows-per-page-options="[10,20,50]" striped-rows responsive-layout="scroll">
        <template #empty>{{ activeTab === 'course' && !selectedCourseId ? 'Chọn khóa học để xem danh sách.' : 'Chưa có đề thi nào.' }}</template>
        <Column expander style="width:3rem" />
        <Column field="title" header="Tên đề thi" sortable><template #body="{data}"><div class="exam-name"><strong>{{ data.title }}</strong><small>{{ data.description }}</small></div></template></Column>
        <Column field="duration" header="Thời lượng" sortable><template #body="{data}">{{ data.duration ?? 0 }} phút</template></Column>
        <Column field="pass_score" header="Điểm đạt" sortable><template #body="{data}">{{ data.pass_score ?? 0 }}%</template></Column>
        <Column field="status" header="Trạng thái" sortable><template #body="{data}"><Tag :value="STATUS_MAP[data.status || 'draft'] || data.status" :severity="data.status === 'active' ? 'success' : data.status === 'closed' ? 'danger' : data.status === 'scheduled' ? 'info' : 'secondary'" /></template></Column>
        <Column header="Thao tác" frozen align-frozen="right"><template #body="{data}"><div class="row-actions"><Button as="a" :href="`/exam/${data.id}`" target="_blank" icon="pi pi-eye" size="small" text aria-label="Thi thử" /><Button as="a" :href="`/admin/exam-monitor?exam=${data.id}`" icon="pi pi-video" size="small" text aria-label="Giám sát" /><Button icon="pi pi-trash" severity="danger" size="small" text aria-label="Xóa" @click="selectedExam=data;confirmOpen=true" /></div></template></Column>
        <template #expansion="{data}"><div class="expansion-grid"><div><b>Số lần thi</b><span>{{ data.max_attempts ?? 1 }}</span></div><div><b>Học viên</b><span>{{ data.exam_enrollments_count ?? '—' }}</span></div><div><b>Bắt đầu</b><span>{{ fmtDate(data.starts_at) }}</span></div><div><b>Kết thúc</b><span>{{ fmtDate(data.ends_at) }}</span></div></div></template>
      </DataTable>
    </template></Card>
    <Dialog v-model:visible="confirmOpen" modal header="Xóa đề thi" :style="{width:'min(30rem,95vw)'}"><p>Xóa <strong>{{ selectedExam?.title }}</strong>? Thao tác không thể hoàn tác.</p><template #footer><Button label="Hủy" severity="secondary" text @click="confirmOpen=false" /><Button label="Xóa đề thi" icon="pi pi-trash" severity="danger" @click="deleteExam" /></template></Dialog>
  </div>
</template>

<style scoped>
.assessment-page{display:grid;gap:1.25rem}.page-heading{display:flex;justify-content:space-between;gap:1rem;align-items:flex-end;flex-wrap:wrap}.page-heading>div:first-child>span{color:var(--p-text-muted-color);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.page-heading h1{margin:.2rem 0;font-size:1.55rem}.page-heading p{margin:0;color:var(--p-text-muted-color)}.heading-actions,.row-actions{display:flex;gap:.5rem;flex-wrap:wrap}.filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.course-field{grid-column:span 2}.field{display:grid;gap:.45rem;font-size:.82rem;font-weight:600}.field :deep(.p-inputtext),.field :deep(.p-select){width:100%}.exam-name{display:grid;gap:.2rem;max-width:28rem}.exam-name small{color:var(--p-text-muted-color);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.expansion-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;padding:1rem}.expansion-grid>div{display:grid;gap:.25rem}.expansion-grid b{font-size:.75rem;color:var(--p-text-muted-color)}.assessment-page :deep(.p-card){border:1px solid var(--p-content-border-color);box-shadow:none}@media(max-width:900px){.filter-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.filter-grid,.expansion-grid{grid-template-columns:1fr}.course-field{grid-column:auto}}
/* ── Tabs card ── */
.qz-tabs-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.qz-tabs {
  display: flex;
  gap: 6px;
}

.qz-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  font-size: 0.875rem;
  font-weight: 600;
  font-family: inherit;
  color: var(--muted);
  cursor: pointer;
  transition: background 140ms, color 140ms, border-color 140ms;
}

.qz-tab:hover {
  background: var(--bg);
  color: var(--text);
}

.qz-tab--on {
  background: var(--green-soft, #e1f5ee);
  color: var(--green-deep, #085041);
  border-color: rgba(29, 158, 117, 0.3);
}

.qz-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(17, 17, 17, 0.07);
  color: var(--muted);
  font-size: 0.7rem;
  font-weight: 700;
}

.qz-tab--on .qz-count {
  background: rgba(29, 158, 117, 0.18);
  color: var(--green-deep);
}

.qz-course-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 4px;
  border-top: 1px solid var(--line);
}

.qz-course-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted);
  white-space: nowrap;
}

/* ── Table ── */
.qz-table th {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--muted);
  padding: 10px 14px;
  background: transparent;
  border-bottom: 2px solid var(--line-strong, rgba(31,49,43,0.16));
}

.qz-table td {
  padding: 11px 14px;
  vertical-align: middle;
}

.qz-table tbody tr:last-child td {
  border-bottom: none;
}

/* ── Cell content ── */
.qz-title {
  display: block;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--text);
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.qz-sub {
  display: block;
  font-size: 0.72rem;
  color: var(--muted);
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-top: 2px;
}

.qz-num {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
}

.qz-unit {
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 400;
}

.qz-date {
  font-size: 0.78rem;
  color: var(--muted);
  white-space: nowrap;
}

/* ── Status badges ── */
.qz-badge {
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  border: 1px solid transparent;
  white-space: nowrap;
}

.qz-badge--draft    { background: rgba(17,17,17,0.06);   color: var(--muted);          border-color: var(--line); }
.qz-badge--scheduled{ background: rgba(55,138,221,0.1);  color: #1a5fa8;               border-color: rgba(55,138,221,0.22); }
.qz-badge--active   { background: rgba(29,158,117,0.1);  color: var(--green-deep);     border-color: rgba(29,158,117,0.22); }
.qz-badge--closed   { background: rgba(239,68,68,0.1);   color: #b91c1c;               border-color: rgba(239,68,68,0.22); }
.qz-badge--archived { background: rgba(17,17,17,0.04);   color: var(--muted);          border-color: var(--line); }

/* ── Action buttons ── */
.qz-actions {
  display: flex;
  align-items: center;
  gap: 5px;
}

.qz-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 28px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-size: 0.74rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
  transition: background 120ms, border-color 120ms, transform 120ms;
}

.qz-btn:hover { transform: translateY(-1px); }

.qz-btn--view    { background: rgba(55,138,221,0.08); color: #1a5fa8;       border-color: rgba(55,138,221,0.18); }
.qz-btn--monitor { background: rgba(29,158,117,0.08); color: var(--green-deep); border-color: rgba(29,158,117,0.18); }
.qz-btn--del     { background: rgba(239,68,68,0.07);  color: #b91c1c;       border-color: rgba(239,68,68,0.15); padding: 0 8px; }

.qz-btn--view:hover    { background: rgba(55,138,221,0.15); border-color: rgba(55,138,221,0.3); }
.qz-btn--monitor:hover { background: rgba(29,158,117,0.15); border-color: rgba(29,158,117,0.3); }
.qz-btn--del:hover     { background: rgba(239,68,68,0.14);  border-color: rgba(239,68,68,0.28); }

/* ── Loading ── */
.crud-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.qz-spin {
  display: inline-block;
  width: 16px; height: 16px;
  border-radius: 50%;
  border: 2px solid var(--line);
  border-top-color: var(--green);
  animation: qz-spin 0.75s linear infinite;
  flex-shrink: 0;
}

@keyframes qz-spin { to { transform: rotate(360deg); } }

/* ── Empty state ── */
.qz-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 48px 24px;
  text-align: center;
}

.qz-empty-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: var(--bg);
  border: 1px solid var(--line);
  display: grid;
  place-items: center;
  color: var(--muted);
  margin-bottom: 2px;
}

.qz-empty strong { font-size: 0.95rem; font-weight: 700; color: var(--text); }
.qz-empty p { margin: 0; font-size: 0.84rem; color: var(--muted); max-width: 300px; }

/* ── Dark mode ── */
[data-theme="dark"] .qz-tab:hover       { background: rgba(255,255,255,0.06); }
[data-theme="dark"] .qz-tab--on         { background: rgba(29,158,117,0.15); border-color: rgba(29,158,117,0.35); }
[data-theme="dark"] .qz-count           { background: rgba(255,255,255,0.1); }
[data-theme="dark"] .qz-tab--on .qz-count { background: rgba(29,158,117,0.25); }
[data-theme="dark"] .qz-table th        { background: transparent; border-bottom-color: rgba(255,255,255,0.15); }
[data-theme="dark"] .qz-badge--draft,
[data-theme="dark"] .qz-badge--archived { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .qz-badge--scheduled{ background: rgba(55,138,221,0.15); color: #7db8ed; }
[data-theme="dark"] .qz-badge--active   { background: rgba(29,158,117,0.15); color: #5ddfb4; }
[data-theme="dark"] .qz-badge--closed   { background: rgba(239,68,68,0.15);  color: #f87171; }
[data-theme="dark"] .qz-btn--view       { background: rgba(55,138,221,0.12); color: #7db8ed; }
[data-theme="dark"] .qz-btn--monitor    { background: rgba(29,158,117,0.12); color: #5ddfb4; }
[data-theme="dark"] .qz-btn--del        { background: rgba(239,68,68,0.12);  color: #f87171; }
[data-theme="dark"] .qz-empty-icon      { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .qz-course-row      { border-color: rgba(255,255,255,0.08); }
</style>
