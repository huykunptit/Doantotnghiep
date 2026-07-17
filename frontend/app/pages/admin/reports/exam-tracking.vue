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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div>
      <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Khảo thí</p>
      <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Theo dõi kỳ thi</h1>
      <p class="text-sm mt-0.5" style="color:var(--muted)">Chọn một kỳ thi để xem trạng thái thí sinh theo thời gian thực.</p>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tải danh sách kỳ thi...</div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <div class="grid gap-5" style="grid-template-columns: 300px 1fr; align-items: start;">
        <!-- Left: exam list -->
        <aside class="sticky top-20">
          <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <div class="px-4 py-4 border-b border-[var(--line)]">
              <div class="flex justify-between items-start mb-3">
                <div>
                  <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-0.5" style="color:var(--muted)">Kỳ thi độc lập</p>
                  <h3 class="text-base font-semibold" style="color:var(--text)">Chọn kỳ thi</h3>
                </div>
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold text-white shrink-0 transition-colors"
                  style="background:#1d9e75"
                  @click="exportPDF"
                >
                  <i class="pi pi-file text-xs" />
                  PDF
                </button>
              </div>
              <input
                v-model="search"
                type="text"
                placeholder="Tìm kiếm..."
                class="w-full h-9 px-3 rounded-xl text-sm border border-[var(--line)] bg-transparent focus:outline-none focus:border-[#1d9e75] transition-colors"
                style="color:var(--text)"
              >
            </div>
            <div class="max-h-[600px] overflow-y-auto">
              <div v-if="filteredExams.length === 0" class="py-8 text-center text-sm" style="color:var(--muted)">
                Không có kỳ thi nào.
              </div>
              <button
                v-for="exam in filteredExams"
                :key="exam.id"
                type="button"
                class="block w-full px-4 py-3.5 border-b border-[var(--line)] text-left hover:bg-[var(--surface)] transition-colors last:border-0"
                :class="String(selectedExamId) === String(exam.id) ? 'bg-[rgba(29,158,117,0.08)] border-l-[3px] border-l-[#1d9e75] !pl-[13px]' : ''"
                @click="selectExam(String(exam.id))"
              >
                <div class="flex justify-between items-start gap-2">
                  <strong class="text-sm font-semibold" style="color:var(--text)">{{ exam.title }}</strong>
                  <span
                    class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold shrink-0"
                    :class="exam.status === 'published' ? 'bg-green-50 text-green-700' : ''"
                    :style="exam.status !== 'published' ? 'background:rgba(17,17,17,.06);color:var(--muted)' : ''"
                  >
                    {{ exam.status === 'published' ? 'Hoạt động' : exam.status }}
                  </span>
                </div>
                <p class="text-xs mt-1" style="color:var(--muted)">{{ exam.enrollments_count || 0 }} thí sinh đăng ký</p>
              </button>
            </div>
          </div>
        </aside>

        <!-- Right: monitor panel -->
        <div class="flex flex-col gap-4">
          <!-- No exam selected -->
          <div v-if="!selectedExamId" class="bg-white border border-[var(--line)] rounded-2xl py-16 text-center shadow-sm">
            <i class="pi pi-eye-slash text-4xl mb-4 block opacity-20" />
            <p class="text-sm" style="color:var(--muted)">Chọn một kỳ thi từ danh sách bên trái để xem trạng thái thí sinh.</p>
          </div>

          <!-- Loading -->
          <div v-else-if="monitorLoading" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tải dữ liệu giám sát...</div>

          <!-- Error -->
          <div v-else-if="monitorError" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ monitorError }}</div>

          <!-- Monitor data -->
          <template v-else-if="monitorData">
            <!-- Header + link -->
            <div class="bg-white border border-[var(--line)] rounded-2xl px-5 py-4 shadow-sm flex items-center justify-between flex-wrap gap-3">
              <div>
                <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-0.5" style="color:var(--muted)">Đang xem</p>
                <h3 class="text-base font-semibold" style="color:var(--text)">{{ selectedExam?.title }}</h3>
              </div>
              <NuxtLink
                :to="`/admin/exam-monitor?exam=${selectedExamId}`"
                class="inline-flex items-center gap-2 h-9 px-5 rounded-xl text-sm font-semibold text-white transition-colors"
                style="background:#1d9e75;text-decoration:none"
              >
                <i class="pi pi-external-link text-xs" />
                Mở trang giám sát đầy đủ
              </NuxtLink>
            </div>

            <!-- Stats row -->
            <div class="grid gap-3" style="grid-template-columns: repeat(5, 1fr)">
              <div class="bg-white border border-[var(--line)] rounded-2xl py-3 px-2 text-center shadow-sm">
                <div class="text-2xl font-extrabold" style="color:var(--text)">{{ monitorData.summary?.total || 0 }}</div>
                <div class="text-xs mt-0.5" style="color:var(--muted)">Tổng</div>
              </div>
              <div class="bg-white rounded-2xl py-3 px-2 text-center shadow-sm border-l-[3px]" style="border-color:#1d9e75;border-top:1px solid var(--line);border-right:1px solid var(--line);border-bottom:1px solid var(--line)">
                <div class="text-2xl font-extrabold" style="color:#1d9e75">{{ monitorData.summary?.in_progress || 0 }}</div>
                <div class="text-xs mt-0.5" style="color:var(--muted)">Đang thi</div>
              </div>
              <div class="bg-white rounded-2xl py-3 px-2 text-center shadow-sm border-l-[3px]" style="border-color:#f59e0b;border-top:1px solid var(--line);border-right:1px solid var(--line);border-bottom:1px solid var(--line)">
                <div class="text-2xl font-extrabold text-amber-500">{{ monitorData.summary?.paused || 0 }}</div>
                <div class="text-xs mt-0.5" style="color:var(--muted)">Tạm dừng</div>
              </div>
              <div class="bg-white rounded-2xl py-3 px-2 text-center shadow-sm border-l-[3px]" style="border-color:#3b82f6;border-top:1px solid var(--line);border-right:1px solid var(--line);border-bottom:1px solid var(--line)">
                <div class="text-2xl font-extrabold text-blue-500">{{ monitorData.summary?.submitted || 0 }}</div>
                <div class="text-xs mt-0.5" style="color:var(--muted)">Đã nộp</div>
              </div>
              <div class="bg-white rounded-2xl py-3 px-2 text-center shadow-sm border-l-[3px]" style="border-color:#ef4444;border-top:1px solid var(--line);border-right:1px solid var(--line);border-bottom:1px solid var(--line)">
                <div class="text-2xl font-extrabold text-red-500">{{ monitorData.summary?.force_stopped || 0 }}</div>
                <div class="text-xs mt-0.5" style="color:var(--muted)">Bị dừng</div>
              </div>
            </div>

            <!-- Attempts table -->
            <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
              <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--line)]">
                <div>
                  <h3 class="text-base font-semibold" style="color:var(--text)">Danh sách thí sinh</h3>
                  <p class="text-xs mt-0.5" style="color:var(--muted)">Chỉ xem · Dùng trang giám sát để thao tác</p>
                </div>
                <button
                  type="button"
                  :disabled="!monitorData.attempts?.length"
                  class="inline-flex items-center gap-2 h-9 px-4 rounded-xl text-sm font-semibold border border-[var(--line)] hover:bg-[var(--surface)] disabled:opacity-40 transition-colors"
                  style="color:var(--muted)"
                  @click="exportCSV"
                >
                  <i class="pi pi-download" />
                  Xuất Excel
                </button>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-[var(--line)]" style="background:var(--surface)">
                      <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Tên thí sinh</th>
                      <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Trạng thái</th>
                      <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Thời gian còn lại</th>
                      <th class="text-left px-5 py-3 text-[0.72rem] font-bold uppercase tracking-wide" style="color:var(--muted)">Số vi phạm</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!monitorData.attempts?.length">
                      <td colspan="4" class="py-12 text-center text-sm" style="color:var(--muted)">Chưa có thí sinh nào.</td>
                    </tr>
                    <tr
                      v-for="a in (monitorData.attempts || [])"
                      :key="a.id"
                      class="border-b border-[var(--line)] transition-colors"
                      :style="{ background: statusBg[a.status] || 'transparent' }"
                    >
                      <td class="px-5 py-3">
                        <p class="font-semibold" style="color:var(--text)">{{ a.user?.name || '—' }}</p>
                        <p class="text-xs" style="color:var(--muted)">{{ a.user?.email }}</p>
                      </td>
                      <td class="px-5 py-3 font-semibold text-sm" style="color:var(--text)">{{ statusLabel[a.status] || a.status }}</td>
                      <td
                        class="px-5 py-3 font-mono font-bold text-sm"
                        :style="{ color: (a.remaining_time || 0) < 300 ? '#ef4444' : 'var(--text)' }"
                      >
                        {{ a.remaining_time !== null ? formatTime(a.remaining_time) : '∞' }}
                      </td>
                      <td class="px-5 py-3 font-bold text-sm" :style="{ color: a.violations_count > 0 ? '#ef4444' : '#1d9e75' }">
                        {{ a.violations_count > 0 ? `⚠ ${a.violations_count}` : '0' }}
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
  </div>
</template>

<style scoped>
@media (max-width: 900px) {
  .grid[style*="grid-template-columns: 300px 1fr"] {
    grid-template-columns: 1fr !important;
  }
  aside.sticky { position: static !important; }
}
</style>
