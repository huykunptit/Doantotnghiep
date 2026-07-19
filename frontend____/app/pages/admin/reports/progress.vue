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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Báo cáo</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Tiến độ học tập</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Theo dõi lộ trình đăng ký và mức độ phổ biến của từng khóa học trong hệ thống.</p>
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

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-12 text-center text-sm" style="color:var(--muted)">Đang tải dữ liệu...</div>
    <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(29,158,117,0.06);border-color:rgba(29,158,117,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:#1d9e75">Tổng học viên</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ stats.students_count || totalEnrollments }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Đăng ký trong hệ thống</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(59,130,246,0.06);border-color:rgba(59,130,246,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-blue-500">Khóa học hoạt động</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ publishedCourses.length }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Đang mở đăng ký</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2)">
          <p class="text-xs font-bold uppercase tracking-wider text-amber-500">TB đăng ký / khóa</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ avgEnrollment }}</strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Học viên mỗi khóa</span>
        </div>
        <div class="rounded-2xl p-5 flex flex-col gap-2 border border-[var(--line)]" style="background:var(--surface)">
          <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--muted)">Tổng bài học</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">
            {{ publishedCourses.reduce((s, c) => s + (c.lessons_count || 0), 0) }}
          </strong>
          <span class="text-xs font-medium" style="color:var(--muted)">Bài học đã xuất bản</span>
        </div>
      </div>

      <!-- Filter toolbar -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap gap-3 items-center px-5 py-4">
          <input
            v-model="search"
            type="text"
            placeholder="Tìm tên khóa học..."
            class="h-9 px-4 rounded-xl text-sm border border-[var(--line)] bg-transparent focus:outline-none focus:border-[#1d9e75] transition-colors flex-1 min-w-[180px] max-w-xs"
            style="color:var(--text)"
          >
          <select
            v-model="sortBy"
            class="h-9 px-3 rounded-xl text-sm border border-[var(--line)] bg-transparent focus:outline-none focus:border-[#1d9e75] transition-colors"
            style="color:var(--text)"
          >
            <option value="enrollments">Sắp xếp: Lượt đăng ký</option>
            <option value="lessons">Sắp xếp: Số bài học</option>
            <option value="title">Sắp xếp: Tên khóa học</option>
          </select>
          <span class="text-sm ml-auto" style="color:var(--muted)">{{ filteredAndSorted.length }} kết quả</span>
        </div>
      </section>

      <!-- Course progress list -->
      <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
        <div class="px-5 py-4 border-b border-[var(--line)]">
          <h3 class="text-base font-semibold" style="color:var(--text)">Khóa học đã xuất bản</h3>
        </div>

        <div v-if="filteredAndSorted.length === 0" class="py-12 text-center text-sm" style="color:var(--muted)">Không có khóa học nào.</div>

        <div v-else class="divide-y divide-[var(--line)]">
          <div
            v-for="(course, idx) in filteredAndSorted"
            :key="course.id"
            class="grid items-center gap-4 px-5 py-4"
            style="grid-template-columns: 48px 1fr 1fr 100px"
          >
            <!-- Rank -->
            <div class="text-center">
              <span
                class="font-extrabold"
                :class="idx < 3 ? 'text-base' : 'text-sm'"
                :style="idx < 3 ? 'color:#085041' : 'color:var(--muted)'"
              >#{{ idx + 1 }}</span>
            </div>
            <!-- Info -->
            <div class="min-w-0 flex items-center gap-3">
              <img
                v-if="course.thumbnail"
                :src="course.thumbnail"
                :alt="course.title"
                class="w-10 h-8 object-cover rounded-md shrink-0"
              >
              <div class="min-w-0">
                <p class="font-semibold text-sm truncate" style="color:var(--text)">{{ course.title }}</p>
                <p class="text-xs mt-0.5" style="color:var(--muted)">
                  {{ course.category?.name || 'Chưa phân loại' }} · {{ course.lessons_count || 0 }} bài học
                </p>
              </div>
            </div>
            <!-- Progress bar -->
            <div class="min-w-0">
              <div class="flex justify-between text-xs mb-1">
                <span style="color:var(--muted)">Đăng ký</span>
                <strong style="color:var(--text)">{{ course.enrollments_count || 0 }} HV</strong>
              </div>
              <div class="h-2 rounded-full overflow-hidden" style="background:rgba(17,17,17,.07)">
                <div
                  class="h-full rounded-full transition-all duration-700"
                  style="background:#1d9e75"
                  :style="{ width: `${((course.enrollments_count || 0) / maxEnrollment) * 100}%` }"
                />
              </div>
            </div>
            <!-- Price -->
            <div class="text-right">
              <span
                v-if="!course.price || course.price === 0"
                class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold bg-green-50 text-green-700"
              >Miễn phí</span>
              <span v-else class="text-sm font-bold" style="color:var(--text)">
                {{ new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(course.price) }}
              </span>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
@media (max-width: 900px) {
  .grid[style*="grid-template-columns: 48px 1fr 1fr 100px"] {
    grid-template-columns: 40px 1fr !important;
  }
  .grid[style*="grid-template-columns: 48px 1fr 1fr 100px"] > div:nth-child(3),
  .grid[style*="grid-template-columns: 48px 1fr 1fr 100px"] > div:nth-child(4) {
    display: none;
  }
}
</style>
