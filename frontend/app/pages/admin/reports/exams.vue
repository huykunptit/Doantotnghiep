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
  <AdminWorkspaceShell
    title="Báo cáo kỳ thi"
    description="Tổng hợp đề thi, tỷ lệ đăng ký và thống kê kết quả của các kỳ thi độc lập."
    :breadcrumb="['Trang chủ', 'Báo cáo', 'Báo cáo kỳ thi']"
  >
    <div v-if="loading" class="dashboard-card crud-empty">Đang tải dữ liệu kỳ thi...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article class="dashboard-card mini-card tone-blue">
          <p class="mini-title">Tổng đề thi</p>
          <div class="mini-head"><strong>{{ stats.total }}</strong><span>Đề thi độc lập</span></div>
        </article>
        <article class="dashboard-card mini-card tone-green">
          <p class="mini-title">Đang hoạt động</p>
          <div class="mini-head"><strong>{{ stats.published }}</strong><span>Đề đã xuất bản</span></div>
        </article>
        <article class="dashboard-card mini-card tone-amber">
          <p class="mini-title">Bản nháp</p>
          <div class="mini-head"><strong>{{ stats.draft }}</strong><span>Chưa xuất bản</span></div>
        </article>
        <article class="dashboard-card mini-card">
          <p class="mini-title">Tổng lượt đăng ký</p>
          <div class="mini-head"><strong>{{ stats.totalEnrolled }}</strong><span>Thí sinh đăng ký</span></div>
        </article>
      </section>

      <!-- Table -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <form class="crud-toolbar-main" @submit.prevent>
            <input
              v-model="search"
              class="crud-search"
              type="text"
              placeholder="Tìm tên đề thi..."
            >
            <select v-model="statusFilter" class="crud-select">
              <option value="">Tất cả trạng thái</option>
              <option value="published">Đã xuất bản</option>
              <option value="draft">Bản nháp</option>
              <option value="scheduled">Đã lên lịch</option>
              <option value="closed">Đã đóng</option>
            </select>
          </form>
          <div class="crud-toolbar-right">
            <button class="crud-export-btn" type="button" @click="exportCSV">
              <span class="material-symbols-outlined">download</span>
              Xuất Excel
            </button>
            <button class="crud-primary-btn" type="button" @click="exportPDF" style="display: inline-flex; align-items: center; gap: 6px;">
              <span class="material-symbols-outlined">picture_as_pdf</span>
              Xuất PDF
            </button>
            <NuxtLink to="/admin/quiz" class="crud-secondary-btn" style="display: inline-flex; align-items: center; justify-content: center; min-height: 48px;">Quản lý đề thi →</NuxtLink>
          </div>
        </div>

        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Tên đề thi</th>
                <th>Trạng thái</th>
                <th>Thời gian (phút)</th>
                <th>Điểm đạt</th>
                <th>Số thí sinh</th>
                <th>Ngày tạo</th>
                <th style="text-align: right;">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredExams.length === 0">
                <td colspan="7" class="crud-empty">Không có đề thi nào.</td>
              </tr>
              <tr v-for="exam in filteredExams" :key="exam.id">
                <td>
                  <strong>{{ exam.title }}</strong>
                  <p style="font-size: 0.8rem; color: var(--muted); margin-top: 2px;">{{ exam.description?.slice(0, 60) }}{{ exam.description?.length > 60 ? '...' : '' }}</p>
                </td>
                <td>
                  <span class="crud-badge" :class="statusClass[exam.status] || ''">
                    {{ statusLabel[exam.status] || exam.status }}
                  </span>
                </td>
                <td>{{ exam.duration || exam.quiz?.time_limit || '—' }}</td>
                <td>{{ exam.pass_score ?? exam.quiz?.pass_score ?? '—' }}</td>
                <td>{{ exam.enrollments_count ?? '—' }}</td>
                <td>{{ formatDate(exam.created_at) }}</td>
                <td style="text-align: right;">
                  <NuxtLink
                    :to="`/admin/exam-monitor?exam=${exam.id}`"
                    class="action-btn is-view"
                    style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-size: 0.8rem;"
                  >
                    <span class="material-symbols-outlined" style="font-size: 14px;">visibility</span>
                    Giám sát
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </AdminWorkspaceShell>
</template>
