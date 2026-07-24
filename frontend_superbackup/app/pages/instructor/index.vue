<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import UiAreaChart from '~/components/dashboard/charts/UiAreaChart.vue'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'
import UiDonut from '~/components/dashboard/charts/UiDonut.vue'
import DashboardSchedule from '~/components/dashboard/DashboardSchedule.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface MonthPoint { month: string; label: string; value: number }
interface TopCourse { id: number; title: string; enrollments_count: number; price?: number }
interface InstructorStats {
  total_courses?: number
  total_students?: number
  total_revenue?: number
  courses_by_status?: Record<string, number>
  revenue_by_month?: MonthPoint[]
  students_by_month?: MonthPoint[]
  top_courses?: TopCourse[]
}

const auth = useAuthStore()
const loading = ref(true)
const stats = ref<InstructorStats>({})
const error = ref('')
const now = ref(new Date())

const greeting = computed(() => {
  const greetingHour = now.value.getHours()
  return greetingHour < 12 ? 'Chào buổi sáng' : greetingHour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'
})
const todayLabel = computed(() =>
  now.value.toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

const formatVnd = (n: number) => {
  if (n >= 1_000_000_000) return `${(n / 1_000_000_000).toFixed(1)} tỷ`
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)} tr`
  if (n >= 1_000) return `${(n / 1_000).toFixed(0)}k`
  return n.toLocaleString('vi-VN')
}
const formatVndFull = (n: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n || 0)

const revenuePoints = computed<MonthPoint[]>(() => stats.value.revenue_by_month ?? [])
const studentPoints = computed<MonthPoint[]>(() => stats.value.students_by_month ?? [])
const monthLabels = computed(() => revenuePoints.value.map((p) => p.label))
const revenueValues = computed(() => revenuePoints.value.map((p) => p.value))
const studentValues = computed(() => studentPoints.value.map((p) => p.value))

const computeDelta = (values: number[]): number | null => {
  if (values.length < 2) return null
  const last = values[values.length - 1] ?? 0
  const prev = values[values.length - 2] ?? 0
  if (prev === 0) return last > 0 ? 100 : 0
  return Math.round(((last - prev) / prev) * 100)
}

const revenueDelta = computed(() => computeDelta(revenueValues.value))
const studentDelta = computed(() => computeDelta(studentValues.value))

const courseStatusSegments = computed(() => {
  const map = stats.value.courses_by_status ?? {}
  const colorMap: Record<string, { label: string; color: string }> = {
    published: { label: 'Đã xuất bản', color: '#10B981' },
    pending_review: { label: 'Chờ duyệt', color: '#F59E0B' },
    draft: { label: 'Bản nháp', color: '#6B7280' },
    rejected: { label: 'Bị từ chối', color: '#EF4444' },
    archived: { label: 'Lưu trữ', color: '#374151' },
  }
  return Object.entries(map)
    .map(([key, value]) => ({
      label: colorMap[key]?.label || key,
      value: Number(value),
      color: colorMap[key]?.color || '#10B981',
    }))
    .filter((s) => s.value > 0)
})

const totalCoursesFromStatus = computed(() =>
  courseStatusSegments.value.reduce((sum, s) => sum + s.value, 0),
)

const loadStats = async () => {
  loading.value = true
  error.value = ''
  now.value = new Date()
  try {
    stats.value = await useApi<InstructorStats>('/instructor/stats', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu thống kê.'
  } finally {
    loading.value = false
  }
}

const upcomingExams = ref<any[]>([])

const scheduleEvents = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)
  return upcomingExams.value
    .filter(e => e.scheduled_start && new Date(e.scheduled_start) >= today)
    .slice(0, 5)
    .map((e) => {
      const start = new Date(e.scheduled_start)
      const d = new Date(start); d.setHours(0, 0, 0, 0)
      const label = d.getTime() === today.getTime() ? 'Hôm nay' : d.getTime() === tomorrow.getTime() ? 'Ngày mai' : start.toLocaleDateString('vi-VN', { day: 'numeric', month: 'short' })
      return {
        id: e.id,
        title: e.title,
        time: start.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        date: label,
        type: 'exam',
        course: e.course?.title || 'Kỳ thi độc lập',
        location: 'Trực tuyến',
      }
    })
})

onMounted(async () => {
  await loadStats()
  try {
    const res = await useApi<any>('/instructor/exams?per_page=20&status=published', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    upcomingExams.value = Array.isArray(res) ? res : (res?.data || [])
  }
  catch {}
})

const quickActions = [
  { label: 'Tạo khóa học mới', icon: 'plus', to: '/courses/create' },
  { label: 'Ngân hàng câu hỏi', icon: 'database', to: '/instructor/question-bank' },
  { label: 'Quản lý học viên', icon: 'users', to: '/instructor/students' },
  { label: 'Doanh thu giảng dạy', icon: 'credit-card', to: '/instructor/revenue' },
  { label: 'Đợt thi & Giám sát', icon: 'clone', to: '/instructor/exams' }
]

function sparklineLine(values: number[], w: number, h: number): string {
  if (!values.length) return ''
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1
  const pad = 2
  return values.map((v, i) => {
    const x = (i / (values.length - 1)) * w
    const y = h - pad - ((v - min) / range) * (h - pad * 2)
    return `${i === 0 ? 'M' : 'L'} ${x} ${y}`
  }).join(' ')
}
function sparklinePath(values: number[], w: number, h: number): string {
  if (!values.length) return ''
  const line = sparklineLine(values, w, h)
  return `${line} L ${w} ${h} L 0 ${h} Z`
}
</script>

<template>
  <div class="flex flex-col gap-6 pb-8 min-h-screen text-[var(--text)]">
    
    <!-- ══ HEADER HUB ══ -->
    <header class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 p-6 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl shadow-sm backdrop-blur-md">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Trung Tâm Giảng Dạy</h1>
        <p class="text-xs text-[var(--muted)] mt-1 font-medium">
          {{ greeting }}, <strong class="text-[var(--text)] font-semibold">{{ auth.user?.name || 'Giảng viên' }}</strong> &bull; {{ todayLabel }}
        </p>
      </div>
      <div class="flex items-center gap-3">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-bold uppercase tracking-wider">
          <i class="pi pi-chart-line animate-pulse" />
          <span>Hệ thống bình thường</span>
        </div>
        <button 
          class="inline-flex items-center gap-2 h-9 px-4 rounded-xl border border-[var(--line)] bg-[var(--surface-strong)] hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] hover:text-[#1d9e75] hover:border-[#1d9e75] transition-all cursor-pointer shadow-sm disabled:opacity-50" 
          :disabled="loading" 
          title="Đồng bộ dữ liệu" 
          @click="loadStats"
        >
          <i class="pi pi-refresh" :class="{ 'animate-spin': loading }" />
          <span>Đồng bộ</span>
        </button>
      </div>
    </header>

    <!-- ══ QUICK RUNWAY ══ -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
      <NuxtLink
        v-for="action in quickActions"
        :key="action.to"
        :to="action.to"
        class="flex items-center justify-between p-4 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl text-[var(--text-secondary)] shadow-sm hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-600 group transition-all duration-200"
      >
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-emerald-50 text-[#1d9e75] group-hover:bg-[#1d9e75] group-hover:text-white transition-colors duration-200">
            <i :class="`pi pi-${action.icon}`" class="text-sm" />
          </div>
          <span class="text-xs font-bold text-[var(--text)] group-hover:text-[#1d9e75] transition-colors">{{ action.label }}</span>
        </div>
        <i class="pi pi-chevron-right text-[10px] text-[var(--muted)] group-hover:translate-x-1 group-hover:text-[#1d9e75] transition-all" />
      </NuxtLink>
    </div>

    <!-- ══ ERROR STATUS ══ -->
    <div v-if="error" class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl shadow-sm text-sm">
      <i class="pi pi-exclamation-triangle text-base" />
      <span class="flex-1 font-medium">{{ error }}</span>
      <button class="px-3 py-1 rounded-lg bg-white border border-red-200 text-xs font-semibold hover:bg-red-50 transition-colors" @click="loadStats">Thử lại</button>
    </div>

    <!-- ══ METRICS WORKSPACE ══ -->
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <!-- CARD 1: REVENUE -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col justify-between">
        <div class="flex justify-between items-start mb-3">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Doanh thu (6 tháng)</span>
          <span v-if="revenueDelta !== null" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full" :class="revenueDelta >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
            <i :class="`pi pi-arrow-${revenueDelta >= 0 ? 'up' : 'down'}`" class="text-[8px]" />
            {{ Math.abs(revenueDelta) }}%
          </span>
        </div>
        <div>
          <div v-if="loading" class="h-8 w-32 bg-[var(--line)] rounded animate-pulse" />
          <h2 v-else class="text-xl font-extrabold text-[var(--text)] tracking-tight">{{ formatVndFull(stats.total_revenue || 0) }}</h2>
          
          <div class="h-8 mt-3" v-if="!loading && revenueValues.length">
            <svg width="100%" height="32" viewBox="0 0 100 32" preserveAspectRatio="none">
              <defs>
                <linearGradient id="glow-rev-instr" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="var(--green)" stop-opacity="0.3"/>
                  <stop offset="100%" stop-color="var(--green)" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <path :d="sparklinePath(revenueValues, 100, 32)" fill="url(#glow-rev-instr)" />
              <path :d="sparklineLine(revenueValues, 100, 32)" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
        <p class="text-[10px] text-[var(--muted)] mt-3 pt-3 border-t border-[var(--line)]">Doanh số khóa học đã phân chia hoa hồng</p>
      </div>

      <!-- CARD 2: COURSES SPLIT -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col justify-between">
        <div class="flex justify-between items-start mb-3">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng số khóa học</span>
          <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600">
            <i class="pi pi-clone text-[8px]" /> Active
          </span>
        </div>
        <div>
          <div v-if="loading" class="h-8 w-32 bg-[var(--line)] rounded animate-pulse" />
          <div v-else class="flex items-center justify-between">
            <div class="flex flex-col">
              <div class="flex items-center gap-1.5">
                <i class="pi pi-book text-sky-500 text-sm" />
                <span class="text-lg font-extrabold text-[var(--text)] tracking-tight">{{ stats.courses_by_status?.published || 0 }}</span>
              </div>
              <span class="text-[9px] text-[var(--muted)] mt-0.5">Đang xuất bản</span>
            </div>
            <div class="h-8 w-px bg-[var(--line)]"></div>
            <div class="flex flex-col items-end">
              <div class="flex items-center gap-1.5">
                <span class="text-lg font-extrabold text-[var(--text)] tracking-tight">{{ (stats.courses_by_status?.pending_review || 0) + (stats.courses_by_status?.draft || 0) }}</span>
                <i class="pi pi-clone text-indigo-500 text-sm" />
              </div>
              <span class="text-[9px] text-[var(--muted)] mt-0.5">Chờ duyệt / Nháp</span>
            </div>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-[var(--line)]">
          <NuxtLink to="/instructor/courses" class="inline-flex items-center gap-1 text-[10px] font-bold text-[#1d9e75] hover:underline">
            <span>Quản lý kho bài giảng</span>
            <i class="pi pi-arrow-right text-[8px]" />
          </NuxtLink>
        </div>
      </div>

      <!-- CARD 3: ENGAGEMENT -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col justify-between">
        <div class="flex justify-between items-start mb-3">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Hiệu suất học tập</span>
          <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-50 text-violet-600">
            <i class="pi pi-verified text-[8px]" /> Đạt yêu cầu
          </span>
        </div>
        <div>
          <div v-if="loading" class="h-8 w-32 bg-[var(--line)] rounded animate-pulse" />
          <div v-else class="flex items-center justify-between">
            <div class="flex flex-col">
              <span class="text-lg font-extrabold text-[var(--text)] tracking-tight">82%</span>
              <span class="text-[9px] text-[var(--muted)] mt-0.5">Hoàn thành bài tập</span>
            </div>
            <div class="w-9 h-9">
              <svg width="36" height="36" viewBox="0 0 36 36">
                <circle cx="18" cy="18" r="16" fill="none" stroke="var(--line)" stroke-width="3"/>
                <circle cx="18" cy="18" r="16" fill="none" stroke="#8B5CF6" stroke-width="3" 
                  stroke-dasharray="100" stroke-dashoffset="18"
                  stroke-linecap="round" transform="rotate(-90 18 18)"/>
              </svg>
            </div>
          </div>
        </div>
        <p class="text-[10px] text-[var(--muted)] mt-3 pt-3 border-t border-[var(--line)]">Đánh giá chung qua tiến trình học tập</p>
      </div>

      <!-- CARD 4: STUDENTS TOTAL -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col justify-between">
        <div class="flex justify-between items-start mb-3">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng lượng học viên</span>
          <span v-if="studentDelta !== null" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">
            <i class="pi pi-users text-[8px]" /> +{{ Math.abs(studentDelta) }}%
          </span>
        </div>
        <div>
          <div v-if="loading" class="h-8 w-32 bg-[var(--line)] rounded animate-pulse" />
          <div v-else class="flex items-center justify-between">
            <h2 class="text-xl font-extrabold text-[var(--text)] tracking-tight">{{ (stats.total_students || 0).toLocaleString('vi-VN') }}</h2>
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 text-[8px] font-bold uppercase tracking-wide">Ghi danh</span>
          </div>
          
          <div class="flex h-1.5 bg-[var(--line)] rounded-full overflow-hidden mt-3" v-if="!loading">
            <div 
              class="h-full bg-emerald-500 transition-all duration-500" 
              :style="`width: ${((stats.courses_by_status?.published || 0) / (totalCoursesFromStatus || 1)) * 100}%`"
              title="Xuất bản"
            />
            <div 
              class="h-full bg-emerald-200 transition-all duration-500" 
              :style="`width: ${(((stats.courses_by_status?.pending_review || 0) + (stats.courses_by_status?.draft || 0)) / (totalCoursesFromStatus || 1)) * 100}%`"
              title="Khác"
            />
          </div>
        </div>
        <div class="flex justify-between text-[9px] text-[var(--muted)] mt-3 pt-3 border-t border-[var(--line)]">
          <span>{{ stats.courses_by_status?.published || 0 }} Đã xuất bản</span>
          <span>{{ (stats.courses_by_status?.pending_review || 0) + (stats.courses_by_status?.draft || 0) }} Khác</span>
        </div>
      </div>
    </section>

    <!-- ══ CHARTS & SCHEDULE WORKSPACE ══ -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-2">
      
      <!-- Schedule -->
      <div class="lg:col-span-12">
        <DashboardSchedule :events="scheduleEvents" title="Kỳ thi sắp diễn ra" />
      </div>

      <!-- Revenue Chart -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-6 shadow-sm flex flex-col justify-between lg:col-span-8">
        <header class="flex justify-between items-start mb-5">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Doanh thu giảng dạy</p>
            <h3 class="text-sm font-bold text-[var(--text)] mt-1">Xu hướng 6 tháng gần nhất</h3>
          </div>
          <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">VND</span>
        </header>
        <div v-if="loading" class="h-44 rounded-xl bg-[var(--surface)] animate-pulse" />
        <UiAreaChart
          v-else-if="revenuePoints.length"
          :series="[{ name: 'Doanh thu', values: revenueValues, color: 'var(--green)' }]"
          :labels="monthLabels"
          :height="200"
          :format-y="formatVnd"
        />
        <div v-else class="flex items-center justify-center min-h-[180px] border border-dashed border-[var(--line)] rounded-2xl text-xs text-[var(--muted)] bg-[var(--surface)]">Chưa có doanh thu trong 6 tháng qua.</div>
      </div>

      <!-- Donut Status Distribution -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-6 shadow-sm flex flex-col justify-between lg:col-span-4">
        <header class="flex justify-between items-start mb-5">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Khóa học</p>
            <h3 class="text-sm font-bold text-[var(--text)] mt-1">Phân bố trạng thái</h3>
          </div>
          <NuxtLink to="/instructor/courses" class="text-xs font-bold text-[#1d9e75] hover:underline">Quản lý →</NuxtLink>
        </header>
        <div v-if="loading" class="h-44 rounded-xl bg-[var(--surface)] animate-pulse" />
        <div class="flex justify-center" v-else-if="courseStatusSegments.length">
          <UiDonut
            :segments="courseStatusSegments"
            :size="150"
            :thickness="24"
            center-label="Tổng"
            :center-value="totalCoursesFromStatus"
          />
        </div>
        <div v-else class="flex items-center justify-center min-h-[180px] border border-dashed border-[var(--line)] rounded-2xl text-xs text-[var(--muted)] bg-[var(--surface)]">Bạn chưa có khóa học nào.</div>
      </div>

      <!-- Monthly Enrollment Bar Chart -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-6 shadow-sm flex flex-col justify-between lg:col-span-5">
        <header class="flex justify-between items-start mb-5">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Học viên mới</p>
            <h3 class="text-sm font-bold text-[var(--text)] mt-1">Ghi danh theo tháng</h3>
          </div>
          <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">Số lượng</span>
        </header>
        <div v-if="loading" class="h-44 rounded-xl bg-[var(--surface)] animate-pulse" />
        <UiBarChart
          v-else-if="studentPoints.length"
          :values="studentValues"
          :labels="studentPoints.map((p) => p.label)"
          :height="180"
          color="var(--green)"
        />
        <div v-else class="flex items-center justify-center min-h-[180px] border border-dashed border-[var(--line)] rounded-2xl text-xs text-[var(--muted)] bg-[var(--surface)]">Chưa có học viên ghi danh trong 6 tháng qua.</div>
      </div>

      <!-- Top Courses Leaderboard -->
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-6 shadow-sm flex flex-col justify-between lg:col-span-7">
        <header class="flex justify-between items-start mb-5">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Top khóa học của bạn</p>
            <h3 class="text-sm font-bold text-[var(--text)] mt-1">Theo lượt ghi danh</h3>
          </div>
          <NuxtLink to="/instructor/courses" class="text-xs font-bold text-[#1d9e75] hover:underline">Tất cả →</NuxtLink>
        </header>
        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="h-10 rounded-xl bg-[var(--surface)] animate-pulse" />
        </div>
        <ol v-else-if="stats.top_courses?.length" class="flex flex-col gap-2">
          <li v-for="(course, i) in stats.top_courses" :key="course.id" class="grid grid-cols-[28px_1fr_auto] items-center gap-3 p-3 bg-[var(--surface)] border border-[var(--line)] rounded-xl hover:border-emerald-500 hover:translate-x-0.5 transition-all duration-200">
            <span class="w-7 h-7 rounded-lg flex items-center justify-center bg-emerald-50 text-[#1d9e75] font-extrabold text-xs">{{ i + 1 }}</span>
            <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="text-xs font-semibold text-[var(--text)] hover:text-[#1d9e75] truncate">
              {{ course.title }}
            </NuxtLink>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-[var(--muted)] tabular-nums">
              <i class="pi pi-user text-[10px]" />
              {{ course.enrollments_count }}
            </span>
          </li>
        </ol>
        <div v-else class="flex items-center justify-center min-h-[180px] border border-dashed border-[var(--line)] rounded-2xl text-xs text-[var(--muted)] bg-[var(--surface)]">Chưa có khóa học nào có lượt ghi danh.</div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
