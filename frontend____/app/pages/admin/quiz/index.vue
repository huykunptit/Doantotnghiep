<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'
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

const allCurrentExams = computed(() => activeTab.value === 'standalone' ? standaloneExams.value : exams.value)
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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div>
      <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Khảo thí</p>
      <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Quản lý Quiz / Đề thi</h1>
      <p class="text-sm mt-0.5" style="color:var(--muted)">Quản lý đề thi theo từng khóa học hoặc kỳ thi độc lập.</p>
    </div>
    <!-- ── Tabs + Course selector ── -->
    <div class="dashboard-card qz-tabs-card">
      <div class="qz-tabs">
        <button
          class="qz-tab" :class="{ 'qz-tab--on': activeTab === 'standalone' }"
          type="button" @click="onTabChange('standalone')"
        >
          <i class="pi pi-book" style="font-size:0.9375rem" />
          Kỳ thi độc lập
          <span class="qz-count">{{ standaloneExams.length }}</span>
        </button>
        <button
          class="qz-tab" :class="{ 'qz-tab--on': activeTab === 'course' }"
          type="button" @click="onTabChange('course')"
        >
          <i class="pi pi-circle" style="font-size:0.9375rem" />
          Đề thi khóa học
          <span class="qz-count">{{ exams.length }}</span>
        </button>
      </div>

      <div v-if="activeTab === 'course'" class="qz-course-row" style="z-index:20;position:relative;">
        <label class="qz-course-label">Khóa học</label>
        <SearchableCourseSelect
          v-model="selectedCourseId"
          :courses="courses"
          :loading="loadingCourses"
          @change="fetchExams"
        />
      </div>
    </div>

    <!-- ── Exam table ── -->
    <div class="dashboard-card crud-panel">
      <!-- Toolbar -->
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">{{ activeTab === 'standalone' ? 'Kỳ thi độc lập' : 'Đề thi khóa học' }}</p>
          <h3 class="ds-section-title">
            {{ activeTab === 'standalone' ? 'Danh sách kỳ thi' : (selectedCourse?.title || 'Chưa chọn khóa học') }}
          </h3>
        </div>
        <div class="crud-toolbar-right">
          <button class="crud-export-btn" type="button" @click="exportData">
            <i class="pi pi-download" style="font-size:0.9375rem" /> Xuất CSV
          </button>
          <button class="crud-primary-btn" type="button" @click="goCreate">
            <i class="pi pi-plus" style="font-size:0.9375rem" /> Thêm đề thi
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="crud-table-wrap">
        <table class="crud-table qz-table">
          <thead>
            <tr>
              <th>Tên đề thi</th>
              <th>Thời lượng</th>
              <th>Điểm đạt</th>
              <th>Số lần thi</th>
              <th>Học viên</th>
              <th>Lịch thi</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loadingExams">
              <td colspan="8" class="crud-empty">
                <span class="qz-spin" /> Đang tải...
              </td>
            </tr>
            <tr v-else-if="allCurrentExams.length === 0">
              <td colspan="8">
                <div class="qz-empty">
                  <div class="qz-empty-icon"><i class="pi pi-book" style="font-size:1.5rem" /></div>
                  <strong>Chưa có đề thi nào</strong>
                  <p>{{ activeTab === 'course' && !selectedCourseId ? 'Chọn khóa học để xem danh sách.' : 'Tạo đề thi đầu tiên để bắt đầu.' }}</p>
                  <button v-if="activeTab === 'standalone' || selectedCourseId" class="crud-primary-btn" type="button" @click="goCreate">
                    <i class="pi pi-plus" style="font-size:0.875rem" /> Tạo đề thi
                  </button>
                </div>
              </td>
            </tr>
            <tr v-for="exam in currentExams" :key="exam.id">
              <td>
                <strong class="qz-title">{{ exam.title }}</strong>
                <span v-if="exam.description" class="qz-sub">{{ exam.description }}</span>
              </td>
              <td class="qz-num">{{ exam.duration ?? 0 }}<span class="qz-unit"> phút</span></td>
              <td class="qz-num">{{ exam.pass_score ?? 0 }}<span class="qz-unit">%</span></td>
              <td class="qz-num">{{ exam.max_attempts ?? 1 }}</td>
              <td class="qz-num">{{ exam.exam_enrollments_count ?? '—' }}</td>
              <td class="qz-date">{{ fmtDate(exam.starts_at) }}</td>
              <td>
                <span class="qz-badge" :class="`qz-badge--${exam.status || 'draft'}`">
                  {{ STATUS_MAP[exam.status ?? 'draft'] ?? exam.status }}
                </span>
              </td>
              <td>
                <div class="qz-actions">
                  <NuxtLink :to="`/exam/${exam.id}`" class="qz-btn qz-btn--view" target="_blank">
                    <i class="pi pi-eye" style="font-size:0.8125rem" /> Thi thử
                  </NuxtLink>
                  <NuxtLink :to="`/admin/exam-monitor?exam=${exam.id}`" class="qz-btn qz-btn--monitor">
                    <i class="pi pi-video" style="font-size:0.8125rem" /> Giám sát
                  </NuxtLink>
                  <button class="qz-btn qz-btn--del" type="button" @click="selectedExam = exam; confirmOpen = true">
                    <i class="pi pi-trash" style="font-size:0.8125rem" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DataTableFooter
        :current="examPage" :last="examLastPage"
        :total="allCurrentExams.length" :per-page="examPerPage"
        @page="examPage = $event"
        @update:per-page="examPerPage = $event; examPage = 1"
      />
    </div>

    <CrudConfirmModal
      :open="confirmOpen"
      title="Xóa đề thi"
      :description="'Xóa ' + (selectedExam?.title ?? 'đề thi này') + '? Thao tác không thể hoàn tác.'"
      confirm-text="Xóa đề thi"
      tone="danger"
      @close="confirmOpen = false"
      @confirm="deleteExam"
    />
  </div>
</template>

<style scoped>
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
