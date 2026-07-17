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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Báo cáo</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Báo cáo kỳ thi</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Tổng hợp đề thi, tỷ lệ đăng ký và thống kê kết quả của các kỳ thi độc lập.</p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button
          type="button"
          class="inline-flex items-center gap-2 h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
          style="color:var(--muted)"
          @click="exportCSV"
        >
          <i class="pi pi-download" />
          Xuất Excel
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 h-9 px-5 rounded-xl text-sm font-semibold text-white transition-colors"
          style="background:#1d9e75"
          @click="exportPDF"
        >
          <i class="pi pi-file" />
          Xuất PDF
        </button>
      </div>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tải dữ liệu kỳ thi...</div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(59,130,246,0.06);border-color:rgba(59,130,246,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Tổng đề thi</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ stats.total }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Đề thi độc lập</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(29,158,117,0.06);border-color:rgba(29,158,117,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:#1d9e75">Đang hoạt động</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ stats.published }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Đề đã xuất bản</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Bản nháp</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ stats.draft }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Chưa xuất bản</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border border-[var(--line)]" style="background:var(--surface)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--muted)">Tổng lượt đăng ký</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ stats.totalEnrolled }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Thí sinh đăng ký</span>
        </div>
      </div>

      <!-- Table -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-[var(--line)]">
          <form class="flex flex-wrap items-center gap-2" @submit.prevent>
            <input
              v-model="search"
              type="text"
              placeholder="Tìm tên đề thi..."
              class="h-9 px-4 rounded-xl text-sm border border-[var(--line)] bg-transparent focus:outline-none focus:border-[#1d9e75] transition-colors w-52"
              style="color:var(--text)"
            >
            <select
              v-model="statusFilter"
              class="h-9 px-3 rounded-xl text-sm border border-[var(--line)] bg-transparent focus:outline-none focus:border-[#1d9e75] transition-colors"
              style="color:var(--text)"
            >
              <option value="">Tất cả trạng thái</option>
              <option value="published">Đã xuất bản</option>
              <option value="draft">Bản nháp</option>
              <option value="scheduled">Đã lên lịch</option>
              <option value="closed">Đã đóng</option>
            </select>
          </form>
          <NuxtLink
            to="/admin/quiz"
            class="inline-flex items-center h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
            style="color:var(--muted);text-decoration:none"
          >
            Quản lý đề thi →
          </NuxtLink>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[var(--line)]" style="background:var(--surface)">
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Tên đề thi</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Trạng thái</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Thời gian (phút)</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Điểm đạt</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Số thí sinh</th>
                <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Ngày tạo</th>
                <th class="text-right px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredExams.length === 0">
                <td colspan="7" class="py-12 text-center text-sm" style="color:var(--muted)">Không có đề thi nào.</td>
              </tr>
              <tr
                v-for="exam in filteredExams"
                :key="exam.id"
                class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
              >
                <td class="px-5 py-3">
                  <p class="font-semibold" style="color:var(--text)">{{ exam.title }}</p>
                  <p class="text-xs mt-0.5 truncate max-w-[28ch]" style="color:var(--muted)">{{ exam.description?.slice(0, 60) }}{{ exam.description?.length > 60 ? '...' : '' }}</p>
                </td>
                <td class="px-5 py-3">
                  <span
                    class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                    :class="{
                      'bg-green-50 text-green-700': exam.status === 'published',
                      'bg-blue-50 text-blue-700': exam.status === 'scheduled',
                      'bg-red-50 text-red-600': exam.status === 'closed',
                    }"
                    :style="!['published','scheduled','closed'].includes(exam.status) ? 'background:rgba(17,17,17,.06);color:var(--muted)' : ''"
                  >
                    {{ statusLabel[exam.status] || exam.status }}
                  </span>
                </td>
                <td class="px-5 py-3 text-sm" style="color:var(--text)">{{ exam.duration || exam.quiz?.time_limit || '—' }}</td>
                <td class="px-5 py-3 text-sm" style="color:var(--text)">{{ exam.pass_score ?? exam.quiz?.pass_score ?? '—' }}</td>
                <td class="px-5 py-3 text-sm font-semibold" style="color:var(--text)">{{ exam.enrollments_count ?? '—' }}</td>
                <td class="px-5 py-3 text-sm" style="color:var(--muted)">{{ formatDate(exam.created_at) }}</td>
                <td class="px-5 py-3 text-right">
                  <NuxtLink
                    :to="`/admin/exam-monitor?exam=${exam.id}`"
                    class="inline-flex items-center gap-1.5 h-7 px-3 rounded-lg text-xs font-semibold border border-[var(--line)] hover:bg-[var(--surface)] transition-colors"
                    style="color:var(--text);text-decoration:none"
                  >
                    Giám sát
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
