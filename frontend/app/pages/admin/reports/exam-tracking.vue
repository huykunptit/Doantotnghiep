<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
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
  <AdminWorkspaceShell
    title="Theo dõi kỳ thi"
    description="Chọn một kỳ thi để xem trạng thái thí sinh theo thời gian thực. Điều hướng sang trang giám sát để thực hiện thao tác."
    :breadcrumb="['Trang chủ', 'Quản lý thi', 'Theo dõi kỳ thi']"
  >
    <div v-if="loading" class="dashboard-card crud-empty">Đang tải danh sách kỳ thi...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <div class="tracking-layout">
        <!-- Left: exam list -->
        <aside class="exam-sidebar">
          <div class="dashboard-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 16px; border-bottom: 1px solid var(--line);">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div>
                  <p class="section-kicker">Kỳ thi độc lập</p>
                  <h3 style="margin: 4px 0 0;">Chọn kỳ thi</h3>
                </div>
                <button class="crud-primary-btn" type="button" style="flex-shrink: 0;" @click="exportPDF">
                  <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle; margin-right: 2px;">picture_as_pdf</span>
                  PDF
                </button>
              </div>
              <input
                v-model="search"
                type="text"
                placeholder="Tìm kiếm..."
                class="crud-search"
                style="width: 100%;"
              >
            </div>
            <div style="max-height: 600px; overflow-y: auto;">
              <div v-if="filteredExams.length === 0" class="crud-empty" style="padding: 2rem;">
                Không có kỳ thi nào.
              </div>
              <button
                v-for="exam in filteredExams"
                :key="exam.id"
                type="button"
                class="exam-list-item"
                :class="{ 'is-selected': String(selectedExamId) === String(exam.id) }"
                @click="selectExam(String(exam.id))"
              >
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                  <strong style="font-size: 0.875rem; text-align: left;">{{ exam.title }}</strong>
                  <span
                    class="crud-badge"
                    :class="exam.status === 'published' ? 'role-instructor' : ''"
                    style="flex-shrink: 0;"
                  >
                    {{ exam.status === 'published' ? 'Hoạt động' : exam.status }}
                  </span>
                </div>
                <p style="font-size: 0.75rem; color: var(--muted); margin-top: 4px; text-align: left;">
                  {{ exam.enrollments_count || 0 }} thí sinh đăng ký
                </p>
              </button>
            </div>
          </div>
        </aside>

        <!-- Right: monitor panel -->
        <div class="monitor-panel">
          <!-- No exam selected -->
          <div v-if="!selectedExamId" class="dashboard-card crud-empty" style="padding: 4rem; text-align: center;">
            <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.2; display: block; margin-bottom: 16px;">radar</span>
            <p>Chọn một kỳ thi từ danh sách bên trái để xem trạng thái thí sinh.</p>
          </div>

          <!-- Loading -->
          <div v-else-if="monitorLoading" class="dashboard-card crud-empty">Đang tải dữ liệu giám sát...</div>

          <!-- Error -->
          <div v-else-if="monitorError" class="crud-alert is-error">{{ monitorError }}</div>

          <!-- Monitor data -->
          <template v-else-if="monitorData">
            <!-- Summary + full monitor link -->
            <div class="dashboard-card" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
              <div>
                <p class="section-kicker">Đang xem</p>
                <h3 style="margin: 4px 0 0;">{{ selectedExam?.title }}</h3>
              </div>
              <NuxtLink
                :to="`/admin/exam-monitor?exam=${selectedExamId}`"
                class="crud-primary-btn"
                style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;"
              >
                <span class="material-symbols-outlined" style="font-size: 18px;">open_in_new</span>
                Mở trang giám sát đầy đủ
              </NuxtLink>
            </div>

            <!-- Stats -->
            <section style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px;">
              <div class="dashboard-card" style="padding: 14px; text-align: center;">
                <div style="font-size: 1.75rem; font-weight: 800;">{{ monitorData.summary?.total || 0 }}</div>
                <div style="font-size: 0.75rem; color: var(--muted);">Tổng</div>
              </div>
              <div class="dashboard-card" style="padding: 14px; text-align: center; border-left: 3px solid var(--green);">
                <div style="font-size: 1.75rem; font-weight: 800; color: var(--green);">{{ monitorData.summary?.in_progress || 0 }}</div>
                <div style="font-size: 0.75rem; color: var(--muted);">Đang thi</div>
              </div>
              <div class="dashboard-card" style="padding: 14px; text-align: center; border-left: 3px solid #f59e0b;">
                <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b;">{{ monitorData.summary?.paused || 0 }}</div>
                <div style="font-size: 0.75rem; color: var(--muted);">Tạm dừng</div>
              </div>
              <div class="dashboard-card" style="padding: 14px; text-align: center; border-left: 3px solid #3b82f6;">
                <div style="font-size: 1.75rem; font-weight: 800; color: #3b82f6;">{{ monitorData.summary?.submitted || 0 }}</div>
                <div style="font-size: 0.75rem; color: var(--muted);">Đã nộp</div>
              </div>
              <div class="dashboard-card" style="padding: 14px; text-align: center; border-left: 3px solid #ef4444;">
                <div style="font-size: 1.75rem; font-weight: 800; color: #ef4444;">{{ monitorData.summary?.force_stopped || 0 }}</div>
                <div style="font-size: 0.75rem; color: var(--muted);">Bị dừng</div>
              </div>
            </section>

            <!-- Attempts table (read-only) -->
            <section class="dashboard-card crud-panel">
              <div class="crud-toolbar">
                <div>
                  <h3 style="margin: 0;">Danh sách thí sinh</h3>
                  <p style="font-size: 0.8rem; color: var(--muted); margin: 4px 0 0;">Chỉ xem · Dùng trang giám sát để thao tác</p>
                </div>
                <div class="crud-toolbar-right">
                  <button class="crud-export-btn" type="button" :disabled="!monitorData.attempts?.length" @click="exportCSV">
                    <span class="material-symbols-outlined">download</span>
                    Xuất Excel
                  </button>
                </div>
              </div>
              <div class="crud-table-wrap">
                <table class="crud-table">
                  <thead>
                    <tr>
                      <th>Tên thí sinh</th>
                      <th>Trạng thái bài thi</th>
                      <th>Thời gian còn lại</th>
                      <th>Số vi phạm</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!monitorData.attempts?.length">
                      <td colspan="4" class="crud-empty">Chưa có thí sinh nào.</td>
                    </tr>
                    <tr v-for="a in (monitorData.attempts || [])" :key="a.id" :style="{ background: statusBg[a.status] || 'transparent' }">
                      <td>
                        <strong>{{ a.user?.name || '—' }}</strong>
                        <p style="font-size: 0.75rem; color: var(--muted);">{{ a.user?.email }}</p>
                      </td>
                      <td><span style="font-weight: 600;">{{ statusLabel[a.status] || a.status }}</span></td>
                      <td style="font-family: monospace; font-weight: 700;" :style="{ color: (a.remaining_time || 0) < 300 ? '#ef4444' : 'inherit' }">
                        {{ a.remaining_time !== null ? formatTime(a.remaining_time) : '∞' }}
                      </td>
                      <td>
                        <span :style="{ color: a.violations_count > 0 ? '#ef4444' : 'var(--green)', fontWeight: '700' }">
                          {{ a.violations_count > 0 ? `⚠ ${a.violations_count}` : '0' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>
        </div>
      </div>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.tracking-layout {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 24px;
  align-items: start;
}
.exam-sidebar { position: sticky; top: 80px; }
.exam-list-item {
  display: block;
  width: 100%;
  padding: 14px 16px;
  border: none;
  background: transparent;
  border-bottom: 1px solid var(--line);
  cursor: pointer;
  transition: background 0.15s;
}
.exam-list-item:hover { background: rgba(var(--green-rgb), 0.05); }
.exam-list-item.is-selected { background: rgba(var(--green-rgb), 0.1); border-left: 3px solid var(--green); }
.exam-list-item:last-child { border-bottom: none; }
@media (max-width: 900px) {
  .tracking-layout { grid-template-columns: 1fr; }
  .exam-sidebar { position: static; }
}
</style>
