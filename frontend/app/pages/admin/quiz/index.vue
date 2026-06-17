<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý quiz / đề thi...' })

interface CourseItem { id: number; title: string; category?: { name: string } | null }
interface ExamItem {
  id: number; title: string; description?: string | null; type?: string
  duration?: number | null; pass_score?: number | null; max_attempts?: number
  status?: string | null; starts_at?: string | null; ends_at?: string | null
  exam_enrollments_count?: number
}

const STATUS_MAP: Record<string, string> = {
  draft: 'Nháp', scheduled: 'Lên lịch', active: 'Đang thi', closed: 'Đã đóng', archived: 'Lưu trữ'
}
const STATUS_COLOR: Record<string, string> = {
  draft: '#999', scheduled: 'var(--green)', active: 'var(--green)', closed: '#f44336', archived: '#666'
}

const user = useAuthUserCookie(); const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const activeTab = ref<'course' | 'standalone'>('standalone')
const courses = ref<CourseItem[]>([])
const exams = ref<ExamItem[]>([])
const standaloneExams = ref<ExamItem[]>([])
const selectedCourseId = ref<number | null>(null)
const loadingCourses = ref(false)
const loadingExams = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const confirmOpen = ref(false)
const selectedExam = ref<ExamItem | null>(null)

const currentExams = computed(() => activeTab.value === 'standalone' ? standaloneExams.value : exams.value)
const selectedCourse = computed(() => courses.value.find(c => c.id === selectedCourseId.value))

async function fetchCourses() {
  loadingCourses.value = true
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    courses.value = res.data || []
  } catch { errorMessage.value = 'Không thể tải danh sách khóa học.' }
  finally { loadingCourses.value = false }
}

async function fetchExams() {
  if (!selectedCourseId.value) return
  loadingExams.value = true
  try {
    exams.value = await useApi<ExamItem[]>(`/courses/${selectedCourseId.value}/exams`, { headers: authHeaders() })
  } catch { errorMessage.value = 'Không thể tải danh sách đề thi.' }
  finally { loadingExams.value = false }
}

async function fetchStandaloneExams() {
  loadingExams.value = true
  try {
    standaloneExams.value = await useApi<ExamItem[]>('/exams/standalone', { headers: authHeaders() })
  } catch { errorMessage.value = 'Không thể tải kỳ thi độc lập.' }
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
    successMessage.value = 'Đã xóa đề thi.'
    confirmOpen.value = false; selectedExam.value = null
  } catch (e: any) { errorMessage.value = e?.data?.message || 'Không thể xóa đề thi.' }
}

function onTabChange(tab: 'course' | 'standalone') {
  activeTab.value = tab; errorMessage.value = ''; successMessage.value = ''
  if (tab === 'standalone') fetchStandaloneExams()
  else if (selectedCourseId.value) fetchExams()
}

function onCourseChange() { fetchExams() }

const { exportToCSV } = useExport()

function exportData() {
  const cols = [
    { key: 'id', label: 'ID Đề thi' },
    { key: 'title', label: 'Tên đề thi' },
    { key: 'duration', label: 'Thời lượng (phút)', format: (val: any) => String(val || 0) },
    { key: 'pass_score', label: 'Điểm đạt (%)', format: (val: any) => String(val || 0) },
    { key: 'max_attempts', label: 'Số lần thi tối đa', format: (val: any) => String(val || 1) },
    { key: 'exam_enrollments_count', label: 'Học viên tham gia', format: (val: any) => String(val || 0) },
    { key: 'status', label: 'Trạng thái', format: (val: any) => STATUS_MAP[val] || val }
  ]
  exportToCSV(currentExams.value, cols, `danh_sach_de_thi_${activeTab.value}`)
}

function goCreate() {
  navigateTo(`/admin/quiz/create?type=${activeTab.value === 'standalone' ? 'standalone' : 'course_final'}`)
}

onMounted(async () => {
  await fetchCourses()
  await fetchStandaloneExams()
})
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý thi', 'Quiz / Đề thi']"
    description="Quản lý đề thi theo từng khóa học hoặc kỳ thi độc lập."
    title="Quản lý quiz / đề thi"
  >
    <!-- Tab switcher -->
    <section class="dashboard-card crud-panel">
      <div class="tab-row">
        <button :class="['tab-btn', { active: activeTab === 'standalone' }]" type="button" @click="onTabChange('standalone')">
          Kỳ thi độc lập
        </button>
        <button :class="['tab-btn', { active: activeTab === 'course' }]" type="button" @click="onTabChange('course')">
          Đề thi khóa học
        </button>
      </div>
    </section>

    <!-- Course selector -->
    <section v-if="activeTab === 'course'" class="dashboard-card crud-panel" style="position:relative;z-index:20;">
      <div class="crud-toolbar">
        <label class="crud-filter-group">
          <span class="crud-filter-label">Khóa học</span>
          <SearchableCourseSelect v-model="selectedCourseId" :courses="courses" :loading="loadingCourses" @change="onCourseChange" />
        </label>
      </div>
    </section>

    <!-- Exam list -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">{{ activeTab === 'standalone' ? 'Kỳ thi độc lập' : 'Đề thi khóa học' }}</p>
          <h3>{{ activeTab === 'standalone' ? 'Danh sách kỳ thi' : (selectedCourse?.title || 'Chưa chọn khóa học') }}</h3>
        </div>
        <div class="crud-toolbar-right">
          <button class="crud-export-btn" type="button" @click="exportData">
            <span class="material-symbols-outlined">download</span>
            Xuất Excel
          </button>
          <button class="crud-primary-btn" type="button" @click="goCreate">+ Thêm đề thi mới</button>
        </div>
      </div>

      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <div class="crud-table-wrap">
        <table class="crud-table">
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
              <td colspan="8" class="crud-empty">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="currentExams.length === 0">
              <td colspan="8" class="crud-empty">
                Chưa có đề thi nào.
                <button class="inline-link" type="button" @click="goCreate">Tạo đề thi đầu tiên</button>
              </td>
            </tr>
            <tr v-for="exam in currentExams" :key="exam.id">
              <td><strong>{{ exam.title }}</strong></td>
              <td>{{ exam.duration ?? 0 }} phút</td>
              <td>{{ exam.pass_score ?? 0 }}%</td>
              <td>{{ exam.max_attempts ?? 1 }}</td>
              <td>{{ exam.exam_enrollments_count ?? '—' }}</td>
              <td style="font-size:0.8rem;white-space:nowrap;">
                <template v-if="exam.starts_at">{{ new Date(exam.starts_at).toLocaleDateString('vi') }}</template>
                <template v-else>—</template>
              </td>
              <td>
                <span class="crud-badge" :style="{ color: STATUS_COLOR[exam.status ?? 'draft'] }">
                  {{ STATUS_MAP[exam.status ?? 'draft'] ?? exam.status }}
                </span>
              </td>
              <td>
                <div class="crud-actions">
                  <NuxtLink v-if="exam.id" :to="`/exam/${exam.id}`" class="action-btn is-view" target="_blank">Thi thử</NuxtLink>
                  <NuxtLink v-if="exam.id" :to="`/admin/exam-monitor?exam=${exam.id}`" class="action-btn is-edit">Giám sát</NuxtLink>
                  <button class="action-btn is-delete" type="button" @click="selectedExam = exam; confirmOpen = true">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <CrudConfirmModal
      :open="confirmOpen"
      title="Xóa đề thi"
      :description="'Bạn có chắc chắn muốn xóa ' + (selectedExam?.title ?? 'đề thi này') + '? Thao tác này không thể hoàn tác.'"
      confirm-text="Xóa đề thi"
      tone="danger"
      @close="confirmOpen = false"
      @confirm="deleteExam"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.tab-row {
  display: flex; border-bottom: 2px solid var(--border-color, #e0e0e0);
}
.tab-btn {
  padding: 0.75rem 1.5rem; border: none; background: transparent; cursor: pointer;
  font-weight: 600; font-size: 0.9rem; color: var(--text-secondary, #666);
  border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s;
}
.tab-btn.active { color: var(--primary, var(--green)); border-bottom-color: var(--primary, var(--green)); }
.tab-btn:hover { color: var(--primary, var(--green)); }

.list-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 1.25rem; gap: 1rem;
}
.inline-link {
  background: none; border: none; color: var(--primary, var(--green));
  cursor: pointer; font-size: 0.85rem; text-decoration: underline; margin-left: 0.5rem;
}
</style>
