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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Báo cáo</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Báo cáo theo khóa học</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Phân bổ danh mục, trạng thái và hiệu quả đào tạo của toàn bộ hệ thống khóa học.</p>
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
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div
          v-for="metric in performanceMetrics"
          :key="metric.label"
          class="rounded-2xl p-5 flex flex-col gap-2 border"
          :class="{
            'bg-[rgba(59,130,246,0.06)]': metric.color === 'tone-blue',
            'bg-[rgba(29,158,117,0.06)]': metric.color === 'tone-green',
            'bg-[rgba(245,158,11,0.06)]': metric.color === 'tone-amber',
          }"
          :style="{
            borderColor: metric.color === 'tone-blue' ? 'rgba(59,130,246,0.2)' : metric.color === 'tone-green' ? 'rgba(29,158,117,0.2)' : 'rgba(245,158,11,0.2)'
          }"
        >
          <p
            class="text-xs font-bold uppercase tracking-wider"
            :class="{
              'text-blue-500': metric.color === 'tone-blue',
              'text-amber-500': metric.color === 'tone-amber',
            }"
            :style="metric.color === 'tone-green' ? 'color:#1d9e75' : ''"
          >{{ metric.label }}</p>
          <strong class="text-3xl font-extrabold tracking-tight" style="color:var(--text)">{{ metric.value }}</strong>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-[1fr_300px] gap-5">
        <!-- Left col -->
        <div class="flex flex-col gap-5">
          <!-- Category distribution -->
          <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <div class="px-6 pt-5 pb-4 border-b border-[var(--line)]">
              <h3 class="text-base font-semibold" style="color:var(--text)">Phân bổ theo danh mục</h3>
              <p class="text-xs mt-0.5" style="color:var(--muted)">Số lượng khóa học theo từng lĩnh vực đào tạo.</p>
            </div>
            <div class="px-6 py-5">
              <div v-if="categoryDistribution.length === 0" class="py-8 text-center text-sm" style="color:var(--muted)">Chưa có dữ liệu.</div>
              <div v-else class="flex flex-col gap-5">
                <div v-for="cat in categoryDistribution" :key="cat.name" class="flex flex-col gap-2">
                  <div class="flex justify-between items-center text-sm">
                    <strong class="font-semibold" style="color:var(--text)">{{ cat.name }}</strong>
                    <span class="text-xs" style="color:var(--muted)">{{ cat.count }} khóa ({{ cat.percentage }}%)</span>
                  </div>
                  <div class="h-2.5 rounded-full overflow-hidden" style="background:rgba(29,158,117,.07)">
                    <div class="h-full rounded-full transition-all duration-700" style="background:#1d9e75" :style="{ width: `${cat.percentage}%` }" />
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Top by enrollment -->
          <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <div class="px-6 pt-5 pb-4 border-b border-[var(--line)]">
              <h3 class="text-base font-semibold" style="color:var(--text)">Khóa học nhiều học viên nhất</h3>
            </div>
            <div class="px-6 py-5">
              <div v-if="topByEnrollment.length === 0" class="py-8 text-center text-sm" style="color:var(--muted)">Chưa có dữ liệu.</div>
              <div v-else class="flex flex-col gap-4">
                <div v-for="course in topByEnrollment" :key="course.id" class="flex flex-col gap-1.5">
                  <div class="flex justify-between text-sm">
                    <span class="font-semibold truncate max-w-[24ch]" style="color:var(--text)">{{ course.title }}</span>
                    <span class="text-xs ml-2 shrink-0" style="color:var(--muted)">{{ course.enrollments_count || 0 }} HV</span>
                  </div>
                  <div class="h-1.5 rounded-full overflow-hidden" style="background:rgba(17,17,17,.05)">
                    <div class="h-full rounded-full transition-all duration-700" style="background:#1d9e75" :style="{ width: `${((course.enrollments_count || 0) / maxEnrollment) * 100}%` }" />
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- Right col -->
        <div class="flex flex-col gap-5">
          <!-- Status breakdown -->
          <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
            <div class="px-5 pt-4 pb-3 border-b border-[var(--line)]">
              <h3 class="text-base font-semibold" style="color:var(--text)">Theo trạng thái</h3>
            </div>
            <div class="px-5 py-4 flex flex-col gap-3.5">
              <div v-for="s in statusDistribution" :key="s.status" class="flex justify-between items-center">
                <span class="text-sm font-semibold" style="color:var(--text)">{{ s.label }}</span>
                <span
                  class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                  :class="{
                    'bg-green-50 text-green-700': s.status === 'published',
                    'bg-red-50 text-red-600': s.status === 'rejected',
                  }"
                  :style="!['published','rejected'].includes(s.status) ? 'background:rgba(17,17,17,.06);color:var(--muted)' : ''"
                >
                  {{ s.count }}
                </span>
              </div>
            </div>
          </section>

          <!-- Free vs paid -->
          <section class="rounded-2xl p-5 border" style="background:rgba(29,158,117,0.06);border:1px dashed rgba(29,158,117,0.35)">
            <h4 class="text-sm font-semibold" style="color:var(--text)">Miễn phí vs Trả phí</h4>
            <div class="grid grid-cols-2 gap-4 mt-4">
              <div class="text-center">
                <strong class="text-3xl font-extrabold" style="color:#085041">
                  {{ courses.filter(c => !c.price || c.price === 0).length }}
                </strong>
                <p class="text-xs mt-1" style="color:var(--muted)">Miễn phí</p>
              </div>
              <div class="text-center">
                <strong class="text-3xl font-extrabold" style="color:#1d9e75">
                  {{ courses.filter(c => c.price > 0).length }}
                </strong>
                <p class="text-xs mt-1" style="color:var(--muted)">Trả phí</p>
              </div>
            </div>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>
