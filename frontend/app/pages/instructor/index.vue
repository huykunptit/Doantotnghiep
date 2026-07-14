<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import UiAreaChart from '~/components/dashboard/charts/UiAreaChart.vue'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'
import UiDonut from '~/components/dashboard/charts/UiDonut.vue'
import DashboardSchedule from '~/components/dashboard/DashboardSchedule.vue'
// Icons removed - using PrimeIcons

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
  { label: 'Tạo khóa học mới', icon: Plus, to: '/courses/create' },
  { label: 'Ngân hàng câu hỏi', icon: Database, to: '/instructor/question-bank' },
  { label: 'Quản lý học viên', icon: Users, to: '/instructor/students' },
  { label: 'Doanh thu giảng dạy', icon: CreditCard, to: '/instructor/revenue' },
  { label: 'Đợt thi & Giám sát', icon: Layers, to: '/instructor/exams' }
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
  <div class="dash-container">
    
    <!-- ══ HEADER HUB ══ -->
    <header class="dash-header">
      <div class="header-main-info">
        <h1 class="header-title">Trung Tâm Giảng Dạy</h1>
        <p class="header-subtitle">
          {{ greeting }}, <strong>{{ auth.user?.name || 'Giảng viên' }}</strong> &bull; {{ todayLabel }}
        </p>
      </div>
      <div class="header-action-meta">
        <div class="status-badge">
          <Activity class="icon-pulse" :size="16" />
          <span>Hệ thống bình thường</span>
        </div>
        <button class="action-btn-refresh" :disabled="loading" title="Đồng bộ dữ liệu" @click="loadStats">
          <RefreshCw :class="{ 'spin-anim': loading }" :size="16" />
          <span>Đồng bộ</span>
        </button>
      </div>
    </header>

    <!-- ══ QUICK RUNWAY ══ -->
    <div class="action-grid">
      <NuxtLink
        v-for="action in quickActions"
        :key="action.to"
        :to="action.to"
        class="action-card"
      >
        <div class="action-icon-wrap">
          <component :is="action.icon" :size="18" />
        </div>
        <span class="action-label">{{ action.label }}</span>
        <ChevronRight class="action-arrow" :size="16" />
      </NuxtLink>
    </div>

    <!-- ══ ERROR STATUS ══ -->
    <div v-if="error" class="error-banner">
      <AlertTriangle :size="20" />
      <span class="error-msg">{{ error }}</span>
      <button class="btn-retry" @click="loadStats">Thử lại</button>
    </div>

    <!-- ══ METRICS WORKSPACE ══ -->
    <section class="metrics-grid">
      
      <!-- CARD 1: REVENUE -->
      <div class="metric-block is-revenue">
        <div class="metric-header">
          <span class="metric-title">Doanh thu (6 tháng)</span>
          <span v-if="revenueDelta !== null" class="metric-delta" :class="revenueDelta >= 0 ? 'is-positive' : 'is-negative'">
            <component :is="revenueDelta >= 0 ? TrendingUp : TrendingDown" :size="12" />
            {{ Math.abs(revenueDelta) }}% tháng trước
          </span>
        </div>
        
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <h2 v-else class="metric-value">{{ formatVndFull(stats.total_revenue || 0) }}</h2>
          
          <div class="metric-sparkline" v-if="!loading && revenueValues.length">
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
        <div class="metric-footer">
          <span class="footer-note">Doanh số khóa học đã phân chia hoa hồng</span>
        </div>
      </div>

      <!-- CARD 2: COURSES SPLIT -->
      <div class="metric-block is-classes">
        <div class="metric-header">
          <span class="metric-title">Tổng số khóa học</span>
          <span class="metric-delta is-info">
            <i class="pi pi-clone" style="font-size:0.75rem" /> Active Curriculum
          </span>
        </div>
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <div v-else class="classes-split-row">
            <div class="class-split-col">
              <div class="split-num-wrap">
                <BookOpen class="text-sky" :size="18" />
                <span class="split-value">{{ stats.courses_by_status?.published || 0 }}</span>
              </div>
              <span class="split-lbl">Đang xuất bản</span>
            </div>
            <div class="split-divider"></div>
            <div class="class-split-col">
              <div class="split-num-wrap">
                <Layers class="text-indigo" :size="18" />
                <span class="split-value">{{ (stats.courses_by_status?.pending_review || 0) + (stats.courses_by_status?.draft || 0) }}</span>
              </div>
              <span class="split-lbl">Chờ duyệt / Nháp</span>
            </div>
          </div>
        </div>
        <div class="metric-footer">
          <NuxtLink to="/instructor/courses" class="footer-link-action">
            <span>Quản lý kho bài giảng</span>
            <i class="pi pi-arrow-right" style="font-size:0.75rem" />
          </NuxtLink>
        </div>
      </div>

      <!-- CARD 3: ENGAGEMENT -->
      <div class="metric-block is-completion">
        <div class="metric-header">
          <span class="metric-title">Hiệu suất học tập</span>
          <span class="metric-delta is-success-alt">
            <i class="pi pi-verified" style="font-size:0.75rem" /> Đạt yêu cầu
          </span>
        </div>
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <template v-else>
            <div class="completion-hero-row">
              <div class="score-display">
                <span class="score-value">82%</span>
                <span class="score-max">Hoàn thành</span>
              </div>
              <div class="progress-ring-mini">
                <svg width="36" height="36" viewBox="0 0 36 36">
                  <circle cx="18" cy="18" r="16" fill="none" stroke="var(--line)" stroke-width="3"/>
                  <circle cx="18" cy="18" r="16" fill="none" stroke="#8B5CF6" stroke-width="3" 
                    stroke-dasharray="100" stroke-dashoffset="18"
                    stroke-linecap="round" transform="rotate(-90 18 18)"/>
                </svg>
              </div>
            </div>
            <div class="metric-indicators">
              <span class="indicator-tag">
                <CheckCircle class="text-green" :size="12" />
                Đang theo sát lộ trình bài học
              </span>
            </div>
          </template>
        </div>
        <div class="metric-footer">
          <span class="footer-note">Đánh giá chung qua tiến trình học tập</span>
        </div>
      </div>

      <!-- CARD 4: STUDENTS TOTAL -->
      <div class="metric-block is-users">
        <div class="metric-header">
          <span class="metric-title">Tổng lượng học viên</span>
          <span v-if="studentDelta !== null" class="metric-delta is-positive">
            <i class="pi pi-users" style="font-size:0.75rem" /> +{{ Math.abs(studentDelta) }}% tháng này
          </span>
        </div>
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <div v-else class="users-total-wrap">
            <h2 class="metric-value">{{ (stats.total_students || 0).toLocaleString('vi-VN') }}</h2>
            <div class="live-counter-badge">
              <span class="ping-dot"></span>
              <span>Ghi danh</span>
            </div>
          </div>
          
          <div class="ratio-progress-bar" v-if="!loading">
            <div 
              class="bar-fill is-student" 
              :style="`width: ${((stats.courses_by_status?.published || 0) / (totalCoursesFromStatus || 1)) * 100}%`"
              title="Xuất bản"
            />
            <div 
              class="bar-fill is-instructor" 
              :style="`width: ${(((stats.courses_by_status?.pending_review || 0) + (stats.courses_by_status?.draft || 0)) / (totalCoursesFromStatus || 1)) * 100}%`"
              title="Khác"
            />
          </div>
        </div>
        <div class="metric-footer text-split">
          <span>{{ stats.courses_by_status?.published || 0 }} Đã xuất bản</span>
          <span>{{ (stats.courses_by_status?.pending_review || 0) + (stats.courses_by_status?.draft || 0) }} Khác</span>
        </div>
      </div>

    </section>

    <!-- ══ CHARTS & SCHEDULE WORKSPACE ══ -->
    <div class="grid-12 mt-6">
      
      <!-- Schedule -->
      <div class="span-lg-12">
        <DashboardSchedule :events="scheduleEvents" title="Kỳ thi sắp diễn ra" />
      </div>

      <!-- Revenue Chart -->
      <div class="dashboard-card chart-card span-lg-8">
        <header class="chart-card-head">
          <div>
            <p class="chart-card-kicker">Doanh thu giảng dạy</p>
            <h3 class="chart-card-title">Xu hướng 6 tháng gần nhất</h3>
          </div>
          <span class="chart-card-tag">VND</span>
        </header>
        <div v-if="loading" class="h-44 rounded-xl bg-surface-high animate-pulse" />
        <UiAreaChart
          v-else-if="revenuePoints.length"
          :series="[{ name: 'Doanh thu', values: revenueValues, color: 'var(--green)' }]"
          :labels="monthLabels"
          :height="200"
          :format-y="formatVnd"
        />
        <div v-else class="empty-block">Chưa có doanh thu trong 6 tháng qua.</div>
      </div>

      <!-- Donut Status Distribution -->
      <div class="dashboard-card chart-card span-lg-4">
        <header class="chart-card-head">
          <div>
            <p class="chart-card-kicker">Khóa học</p>
            <h3 class="chart-card-title">Phân bố trạng thái</h3>
          </div>
          <NuxtLink to="/instructor/courses" class="chart-card-link">Quản lý →</NuxtLink>
        </header>
        <div v-if="loading" class="h-44 rounded-xl bg-surface-high animate-pulse" />
        <UiDonut
          v-else-if="courseStatusSegments.length"
          :segments="courseStatusSegments"
          :size="150"
          :thickness="24"
          center-label="Tổng"
          :center-value="totalCoursesFromStatus"
        />
        <div v-else class="empty-block">Bạn chưa có khóa học nào.</div>
      </div>

      <!-- Monthly Enrollment Bar Chart -->
      <div class="dashboard-card chart-card span-lg-5">
        <header class="chart-card-head">
          <div>
            <p class="chart-card-kicker">Học viên mới</p>
            <h3 class="chart-card-title">Ghi danh theo tháng</h3>
          </div>
          <span class="chart-card-tag">Số lượng</span>
        </header>
        <div v-if="loading" class="h-44 rounded-xl bg-surface-high animate-pulse" />
        <UiBarChart
          v-else-if="studentPoints.length"
          :values="studentValues"
          :labels="studentPoints.map((p) => p.label)"
          :height="180"
          color="var(--green)"
        />
        <div v-else class="empty-block">Chưa có học viên ghi danh trong 6 tháng qua.</div>
      </div>

      <!-- Top Courses Leaderboard -->
      <div class="dashboard-card chart-card span-lg-7">
        <header class="chart-card-head">
          <div>
            <p class="chart-card-kicker">Top khóa học của bạn</p>
            <h3 class="chart-card-title">Theo lượt ghi danh</h3>
          </div>
          <NuxtLink to="/instructor/courses" class="chart-card-link">Tất cả →</NuxtLink>
        </header>
        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="h-10 rounded-lg bg-surface-high animate-pulse" />
        </div>
        <ol v-else-if="stats.top_courses?.length" class="leaderboard">
          <li v-for="(course, i) in stats.top_courses" :key="course.id" class="leaderboard-item">
            <span class="leaderboard-rank">{{ i + 1 }}</span>
            <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="leaderboard-title">
              {{ course.title }}
            </NuxtLink>
            <span class="leaderboard-value">
              <span class="material-symbols-outlined">person</span>
              {{ course.enrollments_count }}
            </span>
          </li>
        </ol>
        <div v-else class="empty-block">Chưa có khóa học nào có lượt ghi danh.</div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* ── General Scrollbars ── */
.dash-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
  min-height: 100vh;
  color: var(--text);
  padding-bottom: 32px;
}

/* ── Header Hub ── */
.dash-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  padding: 24px;
  background: linear-gradient(135deg, var(--surface-strong), rgba(var(--surface-strong-rgb), 0.7));
  border: 1px solid var(--line);
  border-radius: 16px;
  box-shadow: var(--shadow-sm);
  backdrop-filter: blur(8px);
}

.header-title {
  margin: 0 0 6px;
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.03em;
}

.header-subtitle {
  margin: 0;
  font-size: 0.88rem;
  color: var(--muted);
  font-weight: 500;
}

.header-action-meta {
  display: flex;
  align-items: center;
  gap: 16px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 99px;
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #10B981;
  font-size: 0.8rem;
  font-weight: 700;
}

.icon-pulse {
  animation: pulse-ring 2s infinite ease-in-out;
}

@keyframes pulse-ring {
  0% { transform: scale(0.95); opacity: 0.5; }
  50% { transform: scale(1.05); opacity: 1; }
  100% { transform: scale(0.95); opacity: 0.5; }
}

.action-btn-refresh {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--text);
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 200ms ease;
  box-shadow: var(--shadow-sm);
}

.action-btn-refresh:hover:not(:disabled) {
  background: var(--surface);
  border-color: #10B981;
  color: #10B981;
  transform: translateY(-1px);
}

.spin-anim {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── Runway Quick Actions ── */
.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}

.action-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 14px;
  text-decoration: none;
  color: var(--text-secondary);
  transition: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: var(--shadow-sm);
}

.action-card:hover {
  transform: translateY(-3px);
  border-color: #10B981;
  box-shadow: var(--shadow);
  background: linear-gradient(to bottom, var(--surface-strong), rgba(16, 185, 129, 0.02));
}

.action-card:hover .action-label {
  color: #10B981;
}

.action-card:hover .action-arrow {
  transform: translateX(4px);
  color: #10B981;
}

.action-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.06);
  color: #10B981;
  transition: all 200ms;
}

.action-card:hover .action-icon-wrap {
  background: #10B981;
  color: #ffffff;
}

.action-label {
  flex: 1;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--text);
  transition: color 200ms;
}

.action-arrow {
  color: var(--muted);
  transition: transform 200ms, color 200ms;
}

/* ── Error Banner ── */
.error-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 14px;
  color: #EF4444;
  font-size: 0.88rem;
}

.error-msg {
  flex: 1;
  font-weight: 600;
}

.btn-retry {
  padding: 6px 14px;
  border: 1px solid rgba(239, 68, 68, 0.2);
  background: rgba(239, 68, 68, 0.1);
  color: #EF4444;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 150ms;
}

.btn-retry:hover {
  background: #EF4444;
  color: #fff;
}

/* ── Metrics Cards Grid ── */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 20px;
}

.metric-block {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 24px;
  min-height: 170px;
  box-shadow: var(--shadow-sm);
  transition: border-color 250ms, box-shadow 250ms, transform 250ms;
}

.metric-block:hover {
  box-shadow: var(--shadow);
  transform: translateY(-2px);
}

.metric-block.is-revenue:hover { border-color: rgba(16, 185, 129, 0.3); }
.metric-block.is-classes:hover { border-color: rgba(14, 165, 233, 0.3); }
.metric-block.is-completion:hover { border-color: rgba(139, 92, 246, 0.3); }
.metric-block.is-users:hover { border-color: rgba(245, 158, 11, 0.3); }

.metric-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.metric-title {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.metric-delta {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 0.74rem;
  font-weight: 700;
}

.metric-delta.is-positive { background: rgba(16, 185, 129, 0.08); color: #10B981; }
.metric-delta.is-negative { background: rgba(239, 68, 68, 0.08); color: #EF4444; }
.metric-delta.is-info { background: rgba(14, 165, 233, 0.08); color: #0EA5E9; }
.metric-delta.is-success-alt { background: rgba(139, 92, 246, 0.08); color: #8B5CF6; }

.metric-content {
  margin: 16px 0;
  position: relative;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.metric-value {
  margin: 0;
  font-size: 2rem;
  font-weight: 850;
  letter-spacing: -0.03em;
  color: var(--text);
  font-variant-numeric: tabular-nums;
  line-height: 1.1;
}

.metric-sparkline {
  margin-top: 10px;
  height: 36px;
}

.metric-footer {
  font-size: 0.78rem;
  color: var(--muted);
  font-weight: 500;
  border-top: 1px solid var(--line);
  padding-top: 12px;
  margin-top: auto;
}

.footer-note {
  opacity: 0.8;
}

.footer-link-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  color: #0EA5E9;
  font-weight: 700;
  transition: opacity 150ms;
}

.footer-link-action:hover {
  opacity: 0.8;
}

.text-split {
  display: flex;
  justify-content: space-between;
  font-weight: 600;
  color: var(--text-secondary);
}

/* ── Class Card Split Layout ── */
.classes-split-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.class-split-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.split-num-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.split-value {
  font-size: 1.6rem;
  font-weight: 850;
  color: var(--text);
}

.split-lbl {
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--muted);
}

.split-divider {
  width: 1px;
  height: 36px;
  background: var(--line);
  flex-shrink: 0;
}

.text-sky { color: #0EA5E9; }
.text-indigo { color: #8B5CF6; }

/* ── Completion Score Layout ── */
.completion-hero-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.score-display {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.score-value {
  font-size: 2.2rem;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.04em;
}

.score-max {
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--muted);
}

.progress-ring-mini {
  display: flex;
  align-items: center;
  justify-content: center;
}

.metric-indicators {
  margin-top: 10px;
}

.indicator-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 8px;
  background: var(--surface);
  border: 1px solid var(--line);
  color: var(--text-secondary);
  font-size: 0.74rem;
  font-weight: 600;
}

.text-green { color: #10B981; }
.text-orange { color: #F59E0B; }
.text-violet { color: #8B5CF6; }

/* ── Users Card Live Stats ── */
.users-total-wrap {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.live-counter-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(29, 158, 117, 0.08);
  border: 1px solid rgba(29, 158, 117, 0.2);
  color: #10B981;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.74rem;
  font-weight: 700;
}

.ping-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #10B981;
  animation: live-ping 1.4s infinite ease-in-out;
}

@keyframes live-ping {
  0% { transform: scale(0.8); opacity: 0.5; }
  50% { transform: scale(1.3); opacity: 1; }
  100% { transform: scale(0.8); opacity: 0.5; }
}

.ratio-progress-bar {
  display: flex;
  height: 7px;
  background: var(--line);
  border-radius: 99px;
  overflow: hidden;
  margin-top: 14px;
}

.bar-fill {
  height: 100%;
  transition: width 0.6s ease;
}

.bar-fill.is-student { background: #10B981; }
.bar-fill.is-instructor { background: rgba(29, 158, 117, 0.4); }

/* Skeletons */
.skeleton-h3 {
  height: 28px;
  border-radius: 6px;
  background: var(--line);
  width: 70%;
  animation: pulse 1.4s infinite ease-in-out;
}

@keyframes pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 0.3; }
}

/* ── Charts & Grid Layout ── */
.grid-12 {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 16px;
}
.grid-12 > * { grid-column: span 12; min-width: 0; }
@media (min-width: 1024px) {
  .grid-12 > .span-lg-4 { grid-column: span 4; }
  .grid-12 > .span-lg-5 { grid-column: span 5; }
  .grid-12 > .span-lg-7 { grid-column: span 7; }
  .grid-12 > .span-lg-8 { grid-column: span 8; }
}

.chart-card {
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 24px;
  min-width: 0;
  box-shadow: var(--shadow-sm);
  transition: border-color 200ms, box-shadow 200ms;
}

.chart-card:hover {
  border-color: rgba(var(--text-rgb), 0.1);
}

.chart-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.chart-card-kicker {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--muted);
}

.chart-card-title {
  font-family: 'Be Vietnam Pro', sans-serif;
  margin: 4px 0 0;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text);
}

.chart-card-tag {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--green-soft);
  color: var(--green);
}

.chart-card-link {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--green);
  text-decoration: none;
}
.chart-card-link:hover { text-decoration: underline; }

.empty-block {
  display: grid;
  place-items: center;
  min-height: 180px;
  border: 1px dashed var(--line);
  border-radius: 18px;
  font-size: 0.86rem;
  color: var(--muted);
  text-align: center;
  padding: 24px;
  background: var(--bg);
}

/* Leaderboard */
.leaderboard { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.leaderboard-item {
  display: grid;
  grid-template-columns: 28px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--surface);
  border: 1px solid var(--line);
  transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
}
.leaderboard-item:hover { 
  border-color: var(--green);
  transform: translateX(3px);
}
.leaderboard-rank {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--green-soft);
  color: var(--green);
  font-weight: 800;
  font-size: 0.8rem;
}
.leaderboard-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.leaderboard-title:hover { color: var(--green); }
.leaderboard-value {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.84rem;
  font-weight: 700;
  color: var(--muted);
  font-variant-numeric: tabular-nums;
}
.leaderboard-value .material-symbols-outlined { font-size: 16px; }
</style>
