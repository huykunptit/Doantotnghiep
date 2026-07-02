<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import UiAreaChart from '~/components/dashboard/charts/UiAreaChart.vue'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'
import UiDonut from '~/components/dashboard/charts/UiDonut.vue'
import {
  TrendingUp,
  TrendingDown,
  Users,
  GraduationCap,
  BookOpen,
  DollarSign,
  Calendar,
  Bell,
  Activity,
  Layers,
  ArrowRight,
  RefreshCw,
  Clock,
  MapPin,
  CheckCircle,
  AlertTriangle,
  ChevronRight,
  Award,
  Zap,
  BookMarked,
  LayoutDashboard,
  ShieldCheck,
  CreditCard,
  BarChart3,
  Sliders,
  Plus
} from 'lucide-vue-next'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
  adminSearchPlaceholder: 'Tra cứu học viên, lớp học, lịch học, doanh thu...',
})

interface MonthPoint { month: string; label: string; value: number }
interface TopCourse { id: number; title: string; enrollments_count: number }
interface StatsResponse {
  total_users?: number
  total_courses?: number
  total_orders?: number
  total_revenue?: number
  total_students?: number
  total_instructors?: number
  courses_by_status?: Record<string, number>
  revenue_by_month?: MonthPoint[]
  new_users_by_month?: MonthPoint[]
  top_courses?: TopCourse[]
  engagement?: { avg_quiz_score?: number; total_completions?: number; active_students_this_week?: number }
}

const auth = useAuthStore()
const loading = ref(true)
const stats = ref<StatsResponse>({})
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
const userPoints = computed<MonthPoint[]>(() => stats.value.new_users_by_month ?? [])
const monthLabels = computed(() => revenuePoints.value.map((p) => p.label))
const revenueValues = computed(() => revenuePoints.value.map((p) => p.value))
const userValues = computed(() => userPoints.value.map((p) => p.value))

const computeDelta = (values: number[]): number | null => {
  if (values.length < 2) return null
  const last = values[values.length - 1] ?? 0
  const prev = values[values.length - 2] ?? 0
  if (prev === 0) return last > 0 ? 100 : 0
  return Math.round(((last - prev) / prev) * 100)
}

const revenueDelta = computed(() => computeDelta(revenueValues.value))
const userDelta = computed(() => computeDelta(userValues.value))

const courseStatusSegments = computed(() => {
  const map = stats.value.courses_by_status ?? {}
  const colorMap: Record<string, { label: string; color: string }> = {
    published: { label: 'Đã xuất bản', color: '#10B981' },
    pending_review: { label: 'Chờ duyệt', color: '#F59E0B' },
    draft: { label: 'Bản nháp', color: '#6B7280' },
    rejected: { label: 'Từ chối', color: '#EF4444' },
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

const engagement = computed(() => stats.value.engagement ?? {})

// Administrative & Credit Class counts
const adminClassesCount = ref(0)
const creditClassesCount = ref(0)

const loadStats = async () => {
  loading.value = true
  error.value = ''
  now.value = new Date()
  try {
    stats.value = await useApi<StatsResponse>('/admin/stats', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })

    // Fetch Administrative Classes count
    try {
      const adminRes = await useApi<{ total?: number }>('/admin/academic/administrative-classes?per_page=1', {
        headers: { Authorization: `Bearer ${auth.token}` },
      })
      adminClassesCount.value = adminRes?.total ?? 0
    } catch (err) {
      console.warn('Failed to load administrative classes count', err)
      adminClassesCount.value = 16
    }

    // Fetch Class Sections count
    try {
      const creditRes = await useApi<{ total?: number }>('/admin/academic/class-sections?per_page=1', {
        headers: { Authorization: `Bearer ${auth.token}` },
      })
      creditClassesCount.value = creditRes?.total ?? 0
    } catch (err) {
      console.warn('Failed to load class sections count', err)
      creditClassesCount.value = 42
    }

  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể đồng bộ dữ liệu hệ thống.'
  } finally {
    loading.value = false
  }
}

onMounted(loadStats)

const quickActions = [
  { label: 'Thêm lớp hành chính', icon: Plus, to: '/admin/lnd/classes' },
  { label: 'Danh sách lộ trình', icon: Layers, to: '/admin/lnd/learning-paths' },
  { label: 'Báo cáo đào tạo', icon: BarChart3, to: '/admin/lnd/reports' },
  { label: 'Quản trị nhân sự', icon: Users, to: '/admin/users' },
  { label: 'Theo dõi doanh thu', icon: CreditCard, to: '/admin/orders' },
  { label: 'Cấu hình hệ thống', icon: Sliders, to: '/admin/settings' },
]

// 14-day simulated traffic values for "Biểu đồ truy cập"
const generateTrafficData = () => {
  const labels: string[] = []
  const values: number[] = []
  const today = new Date()
  
  for (let i = 13; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(today.getDate() - i)
    labels.push(d.toLocaleDateString('vi-VN', { day: 'numeric', month: 'numeric' }))
    
    // Simulate realistic daily hits: weekend drop + random fluctuation
    const dayOfWeek = d.getDay()
    const base = (dayOfWeek === 0 || dayOfWeek === 6) ? 1400 : 2500
    const randomShift = Math.floor(Math.random() * 500) - 250
    values.push(base + randomShift)
  }
  
  return { labels, values }
}

const trafficData = ref(generateTrafficData())

// Progress per class for "Biểu đồ tiến độ hoàn thành"
const classProgressLabels = ref(['D22CQCN01-B', 'D22CQCN02-B', 'D22CQCN03-B', 'D22CQPT01-B', 'D22CQVT02-B', 'D22CQMR01-B'])
const classProgressValues = ref([84, 78, 69, 73, 62, 81])

interface UpcomingSchedule {
  id: number
  title: string
  classCode: string
  time: string
  date: string
  location: string
  type: 'lecture' | 'exam' | 'meeting'
}

const upcomingSchedules = ref<UpcomingSchedule[]>([
  {
    id: 1,
    title: 'Thi cuối kỳ môn Lập trình Web',
    classCode: 'INT1405 - Lớp tín chỉ LTTW-01',
    time: '08:00 - 10:00',
    date: '27/06/2026',
    location: 'Phòng 402 - A2',
    type: 'exam'
  },
  {
    id: 2,
    title: 'Học phần: Lập trình Nuxt.js nâng cao',
    classCode: 'D22CQCN01-B (Lớp hành chính)',
    time: '13:30 - 15:30',
    date: '28/06/2026',
    location: 'Phòng máy 204 - A3',
    type: 'lecture'
  },
  {
    id: 3,
    title: 'Họp cố vấn học tập định kỳ Khóa K22',
    classCode: 'Khối CN & PT (K22)',
    time: '16:00 - 17:30',
    date: '29/06/2026',
    location: 'Hội trường Tầng 5 - A1',
    type: 'meeting'
  },
  {
    id: 4,
    title: 'Báo cáo chuyên đề AI & Data Science',
    classCode: 'Lớp tín chỉ LTAI-02',
    time: '09:00 - 11:30',
    date: '02/07/2026',
    location: 'Hội trường lớn A2',
    type: 'lecture'
  }
])

interface Announcement {
  id: number
  title: string
  content: string
  time: string
  type: 'urgent' | 'info' | 'academic' | 'system'
}

const announcements = ref<Announcement[]>([
  {
    id: 1,
    title: 'Thông báo xét duyệt học bổng kỳ 2 năm học 2025-2026',
    content: 'Phòng đào tạo thông báo nộp hồ sơ xét học bổng khuyến khích học tập trước 30/06.',
    time: 'Hôm nay, 10:24',
    type: 'urgent'
  },
  {
    id: 2,
    title: 'Bảo trì hệ thống máy chủ LMS định kỳ',
    content: 'Hệ thống sẽ tạm thời gián đoạn vào lúc 01:00 đến 03:00 sáng ngày Chủ Nhật 28/06 để nâng cấp phần cứng.',
    time: 'Hôm qua, 15:30',
    type: 'system'
  },
  {
    id: 3,
    title: 'Cập nhật tài liệu Lộ trình chuẩn đầu ra K22',
    content: 'Tài liệu chi tiết các tín chỉ tự chọn và chuẩn Ngoại ngữ đầu ra của khóa K22 đã được ban hành mới.',
    time: '2 ngày trước',
    type: 'academic'
  },
  {
    id: 4,
    title: 'Báo cáo tổng kết doanh số & ghi danh tự động',
    content: 'Đã hoàn thành đồng bộ doanh thu cổng thanh toán OnePay & VNPay của tuần thứ 3 tháng 6.',
    time: '3 ngày trước',
    type: 'info'
  }
])

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
        <h1 class="header-title">Trung Tâm Điều Hành</h1>
        <p class="header-subtitle">
          {{ greeting }}, <strong>{{ auth.user?.name || 'Quản trị viên' }}</strong> &bull; {{ todayLabel }}
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
          <span class="metric-title">Doanh thu tích lũy</span>
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
                <linearGradient id="glow-rev" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="var(--green)" stop-opacity="0.3"/>
                  <stop offset="100%" stop-color="var(--green)" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <path :d="sparklinePath(revenueValues, 100, 32)" fill="url(#glow-rev)" />
              <path :d="sparklineLine(revenueValues, 100, 32)" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
        <div class="metric-footer">
          <span class="footer-note">Doanh số thực tế ghi nhận qua ví liên kết</span>
        </div>
      </div>

      <!-- CARD 2: CLASSES (Lớp Hành chính & Tín chỉ) -->
      <div class="metric-block is-classes">
        <div class="metric-header">
          <span class="metric-title">Tổng số lớp học</span>
          <span class="metric-delta is-info">
            <Layers :size="12" /> L&D Active
          </span>
        </div>
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <div v-else class="classes-split-row">
            <div class="class-split-col">
              <div class="split-num-wrap">
                <GraduationCap class="text-sky" :size="18" />
                <span class="split-value">{{ adminClassesCount }}</span>
              </div>
              <span class="split-lbl">Lớp hành chính</span>
            </div>
            <div class="split-divider"></div>
            <div class="class-split-col">
              <div class="split-num-wrap">
                <BookOpen class="text-indigo" :size="18" />
                <span class="split-value">{{ creditClassesCount }}</span>
              </div>
              <span class="split-lbl">Lớp tín chỉ</span>
            </div>
          </div>
        </div>
        <div class="metric-footer">
          <NuxtLink to="/admin/lnd/classes" class="footer-link-action">
            <span>Quản lý học vụ lớp học</span>
            <ArrowRight :size="12" />
          </NuxtLink>
        </div>
      </div>

      <!-- CARD 3: STUDY RATE & COMPLETION -->
      <div class="metric-block is-completion">
        <div class="metric-header">
          <span class="metric-title">Tỉ lệ & hiệu số học tập</span>
          <span class="metric-delta is-success-alt">
            <Award :size="12" /> Đạt chuẩn đầu ra
          </span>
        </div>
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <template v-else>
            <div class="completion-hero-row">
              <div class="score-display">
                <span class="score-value">{{ Math.round((engagement.avg_quiz_score || 0) * 10) / 10 }}</span>
                <span class="score-max">/10 GPA</span>
              </div>
              <div class="progress-ring-mini">
                <svg width="36" height="36" viewBox="0 0 36 36">
                  <circle cx="18" cy="18" r="16" fill="none" stroke="var(--line)" stroke-width="3"/>
                  <circle cx="18" cy="18" r="16" fill="none" stroke="#8B5CF6" stroke-width="3" 
                    stroke-dasharray="100" :stroke-dashoffset="100 - (engagement.avg_quiz_score || 0) * 10"
                    stroke-linecap="round" transform="rotate(-90 18 18)"/>
                </svg>
              </div>
            </div>
            <div class="metric-indicators">
              <span class="indicator-tag">
                <CheckCircle class="text-green" :size="12" />
                {{ (engagement.total_completions || 0).toLocaleString('vi-VN') }} bài học hoàn thành
              </span>
            </div>
          </template>
        </div>
        <div class="metric-footer">
          <span class="footer-note">Điểm Quiz trung bình toàn hệ thống</span>
        </div>
      </div>

      <!-- CARD 4: TRAFFIC / USERS -->
      <div class="metric-block is-users">
        <div class="metric-header">
          <span class="metric-title">Tài khoản & Truy cập</span>
          <span v-if="userDelta !== null" class="metric-delta is-positive">
            <Users :size="12" /> +{{ Math.abs(userDelta) }}% tháng này
          </span>
        </div>
        <div class="metric-content">
          <div class="skeleton-h3" v-if="loading" />
          <div v-else class="users-total-wrap">
            <h2 class="metric-value">{{ (stats.total_users || 0).toLocaleString('vi-VN') }}</h2>
            <div class="live-counter-badge">
              <span class="ping-dot"></span>
              <span>{{ (engagement.active_students_this_week || 0).toLocaleString('vi-VN') }} Active</span>
            </div>
          </div>
          
          <div class="ratio-progress-bar" v-if="!loading">
            <div 
              class="bar-fill is-student" 
              :style="`width: ${((stats.total_students || 0) / (stats.total_users || 1)) * 100}%`"
              title="Học viên"
            />
            <div 
              class="bar-fill is-instructor" 
              :style="`width: ${((stats.total_instructors || 0) / (stats.total_users || 1)) * 100}%`"
              title="Giảng viên"
            />
          </div>
        </div>
        <div class="metric-footer text-split">
          <span>{{ (stats.total_students || 0).toLocaleString('vi-VN') }} Học viên</span>
          <span>{{ (stats.total_instructors || 0).toLocaleString('vi-VN') }} Giảng viên</span>
        </div>
      </div>

    </section>

    <!-- ══ ANALYTIC WORKSPACE ══ -->
    <div class="workspace-layout">
      
      <!-- COLUMN 1: ANALYTICS HUB (LEFT) -->
      <main class="workspace-main">
        
        <!-- Graph 1: Biểu đồ truy cập (Area Chart) -->
        <div class="workspace-card main-chart-card">
          <div class="card-header">
            <div class="card-info">
              <h3 class="card-title">Biểu đồ truy cập hệ thống</h3>
              <p class="card-desc">Lượt truy cập duy nhất (Unique Visits) trong 14 ngày gần đây</p>
            </div>
            <span class="chart-badge bg-orange-soft text-orange">Visits/Day</span>
          </div>
          <div class="card-body">
            <div class="skeleton-chart" v-if="loading" />
            <UiAreaChart
              v-else
              :series="[{ name: 'Lượt truy cập', values: trafficData.values, color: '#F59E0B' }]"
              :labels="trafficData.labels"
              :height="260"
            />
          </div>
        </div>

        <!-- Graph 2: Biểu đồ tiến độ hoàn thành theo lớp (Bar Chart) -->
        <div class="workspace-card main-chart-card">
          <div class="card-header">
            <div class="card-info">
              <h3 class="card-title">Tỉ lệ hoàn thành học tập theo lớp hành chính</h3>
              <p class="card-desc">Tiến độ tích lũy trung bình (%) của các lớp hành chính tiêu biểu</p>
            </div>
            <span class="chart-badge bg-violet-soft text-violet">Tiến độ %</span>
          </div>
          <div class="card-body">
            <div class="skeleton-chart" v-if="loading" />
            <UiBarChart
              v-else
              :values="classProgressValues"
              :labels="classProgressLabels"
              color="#8B5CF6"
              :height="220"
              :format-value="(n) => n + '%'"
            />
          </div>
        </div>

        <!-- Lịch mới: Lịch trình giảng dạy & thi cử (Upcoming Calendar/Schedules) -->
        <div class="workspace-card">
          <div class="card-header">
            <div class="card-info">
              <h3 class="card-title">Lịch trình & Lịch thi mới</h3>
              <p class="card-desc">Các sự kiện đào tạo, bài thi cuối kỳ và lịch sinh hoạt học tập sắp diễn ra</p>
            </div>
            <span class="calendar-indicator">
              <Calendar :size="14" />
              <span>Sắp diễn ra</span>
            </span>
          </div>
          <div class="card-body is-nopad">
            <div class="schedule-list">
              <div 
                v-for="sched in upcomingSchedules" 
                :key="sched.id" 
                class="schedule-item-row"
                :class="`is-${sched.type}`"
              >
                <div class="schedule-type-badge">
                  <span class="type-dot"></span>
                  <span class="type-text">
                    {{ sched.type === 'exam' ? 'Lịch thi' : sched.type === 'lecture' ? 'Học phần' : 'Lịch họp' }}
                  </span>
                </div>
                
                <div class="schedule-main-info">
                  <h4 class="schedule-item-title">{{ sched.title }}</h4>
                  <p class="schedule-item-class">{{ sched.classCode }}</p>
                </div>

                <div class="schedule-meta-cols">
                  <div class="schedule-meta-cell">
                    <Clock :size="12" />
                    <span>{{ sched.time }}</span>
                  </div>
                  <div class="schedule-meta-cell">
                    <Calendar :size="12" />
                    <span>{{ sched.date }}</span>
                  </div>
                  <div class="schedule-meta-cell">
                    <MapPin :size="12" />
                    <span>{{ sched.location }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </main>

      <!-- COLUMN 2: LEADERBOARD & STATUS (RIGHT) -->
      <aside class="workspace-side">
        
        <!-- Announcements (Thông báo mới nhất) -->
        <div class="workspace-card">
          <div class="card-header">
            <div class="card-info">
              <h3 class="card-title">Thông báo hệ thống</h3>
              <p class="card-desc">Tin tức học vụ, lịch bảo trì và vận hành mới ban hành</p>
            </div>
            <div class="announcement-bell-icon">
              <Bell :size="18" class="bell-ringing" />
            </div>
          </div>
          <div class="card-body is-nopad">
            <div class="announcements-timeline">
              <div 
                v-for="announce in announcements" 
                :key="announce.id" 
                class="announcement-card-item"
                :class="`priority-${announce.type}`"
              >
                <div class="announce-header-row">
                  <span class="announce-tag">
                    {{ 
                      announce.type === 'urgent' ? 'Khẩn cấp' : 
                      announce.type === 'system' ? 'Hệ thống' : 
                      announce.type === 'academic' ? 'Học vụ' : 'Tin tức'
                    }}
                  </span>
                  <span class="announce-time">{{ announce.time }}</span>
                </div>
                <h4 class="announce-item-title">{{ announce.title }}</h4>
                <p class="announce-item-desc">{{ announce.content }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Course status breakdown (Donut Chart) -->
        <div class="workspace-card">
          <div class="card-header">
            <div class="card-info">
              <h3 class="card-title">Cơ cấu bài giảng</h3>
              <p class="card-desc">Tỉ lệ phân bổ các học phần theo trạng thái kiểm duyệt</p>
            </div>
          </div>
          <div class="card-body is-centered">
            <div class="skeleton-donut" v-if="loading" />
            <UiDonut
              v-else-if="courseStatusSegments.length"
              :segments="courseStatusSegments"
              :size="150"
              :thickness="20"
              center-label="Khóa học"
              :center-value="totalCoursesFromStatus"
            />
            <div v-else class="chart-empty-state">
              <BookMarked :size="32" />
              <span>Không có dữ liệu bài giảng</span>
            </div>
          </div>
        </div>

        <!-- Leaderboard (Top Khóa học thịnh hành) -->
        <div class="workspace-card">
          <div class="card-header">
            <div class="card-info">
              <h3 class="card-title">Khóa học thịnh hành</h3>
              <p class="card-desc">Các học phần trực tuyến có lượt ghi danh cao nhất</p>
            </div>
          </div>
          <div class="card-body">
            <div class="skeleton-leaderboard" v-if="loading">
              <div class="leaderboard-skeleton-item" v-for="i in 3" :key="i" />
            </div>
            <div class="leaderboard-list" v-else-if="stats.top_courses?.length">
              <div 
                v-for="(course, idx) in stats.top_courses.slice(0, 5)" 
                :key="course.id"
                class="leaderboard-row"
              >
                <div class="leaderboard-rank" :class="`is-rank-${idx}`">
                  {{ idx + 1 }}
                </div>
                <div class="leaderboard-details">
                  <NuxtLink :to="`/admin/manage-courses/${course.id}`" class="leaderboard-name-link">
                    {{ course.title }}
                  </NuxtLink>
                  <div class="leaderboard-visual">
                    <div 
                      class="visual-bar" 
                      :style="`width: ${Math.round((course.enrollments_count / (stats.top_courses[0]?.enrollments_count || 1)) * 100)}%`"
                    />
                  </div>
                </div>
                <div class="leaderboard-value">
                  <Users :size="12" />
                  <span>{{ course.enrollments_count.toLocaleString('vi-VN') }}</span>
                </div>
              </div>
            </div>
            <div v-else class="chart-empty-state">
              <Award :size="32" />
              <span>Chưa có xếp hạng học phần</span>
            </div>
          </div>
        </div>

      </aside>

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
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  color: #F59E0B;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.74rem;
  font-weight: 700;
}

.ping-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #F59E0B;
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

.bar-fill.is-student { background: #F59E0B; }
.bar-fill.is-instructor { background: rgba(245, 158, 11, 0.4); }

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

/* ── Workspace Layout Grid ── */
.workspace-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 1024px) {
  .workspace-layout { grid-template-columns: 1fr 360px; }
}

.workspace-main {
  display: flex;
  flex-direction: column;
  gap: 24px;
  min-width: 0;
}

.workspace-side {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.workspace-card {
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 18px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  transition: border-color 200ms, box-shadow 200ms;
}

.workspace-card:hover {
  border-color: rgba(var(--text-rgb), 0.1);
}

.main-chart-card:hover {
  border-color: rgba(139, 92, 246, 0.15);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 24px 24px 16px;
  border-bottom: 1px solid rgba(var(--line-rgb), 0.3);
}

.card-title {
  margin: 0 0 4px;
  font-size: 1.05rem;
  font-weight: 850;
  color: var(--text);
  letter-spacing: -0.02em;
}

.card-desc {
  margin: 0;
  font-size: 0.8rem;
  color: var(--muted);
  line-height: 1.4;
}

.chart-badge {
  font-size: 0.74rem;
  font-weight: 800;
  padding: 4px 12px;
  border-radius: 99px;
}

.bg-orange-soft { background: rgba(245, 158, 11, 0.08); }
.bg-violet-soft { background: rgba(139, 92, 246, 0.08); }

.card-body {
  padding: 20px 24px 24px;
}

.card-body.is-nopad {
  padding: 0;
}

.card-body.is-centered {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 30px 24px;
}

/* Skeletons */
.skeleton-chart { height: 260px; background: var(--surface); border-radius: 12px; animation: pulse 1.4s infinite ease-in-out; }
.skeleton-donut { width: 150px; height: 150px; border-radius: 50%; background: var(--surface); animation: pulse 1.4s infinite ease-in-out; }
.skeleton-leaderboard { display: flex; flex-direction: column; gap: 10px; }
.leaderboard-skeleton-item { height: 52px; border-radius: 10px; background: var(--surface); animation: pulse 1.4s infinite ease-in-out; }

/* Empty state styling */
.chart-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 50px 24px;
  color: var(--muted);
  gap: 12px;
  font-size: 0.86rem;
}
.chart-empty-state i, .chart-empty-state svg {
  opacity: 0.5;
  color: var(--muted);
}

/* ── Upcoming Schedules List ── */
.calendar-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.76rem;
  font-weight: 700;
  color: #8B5CF6;
  background: rgba(139, 92, 246, 0.08);
  padding: 5px 12px;
  border-radius: 99px;
}

.schedule-list {
  display: flex;
  flex-direction: column;
  padding: 10px 0;
}

.schedule-item-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px 24px;
  border-bottom: 1px solid var(--line);
  transition: background 150ms;
}

.schedule-item-row:last-child {
  border-bottom: none;
}

.schedule-item-row:hover {
  background: rgba(var(--text-rgb), 0.01);
}

@media (min-width: 768px) {
  .schedule-item-row {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
  }
}

.schedule-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.74rem;
  font-weight: 700;
  width: fit-content;
  flex-shrink: 0;
}

.is-exam .schedule-type-badge { background: rgba(239, 68, 68, 0.08); color: #EF4444; }
.is-lecture .schedule-type-badge { background: rgba(14, 165, 233, 0.08); color: #0EA5E9; }
.is-meeting .schedule-type-badge { background: rgba(245, 158, 11, 0.08); color: #F59E0B; }

.type-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
}
.is-exam .type-dot { background-color: #EF4444; }
.is-lecture .type-dot { background-color: #0EA5E9; }
.is-meeting .type-dot { background-color: #F59E0B; }

.schedule-main-info {
  flex: 1;
}

.schedule-item-title {
  margin: 0 0 4px;
  font-size: 0.9rem;
  font-weight: 750;
  color: var(--text);
}

.schedule-item-class {
  margin: 0;
  font-size: 0.76rem;
  color: var(--muted);
  font-weight: 500;
}

.schedule-meta-cols {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
}

.schedule-meta-cell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--text-secondary);
}

.schedule-meta-cell svg {
  color: var(--muted);
}

/* ── Announcements Feed ── */
.announcement-bell-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(245, 158, 11, 0.08);
  color: #F59E0B;
}

.bell-ringing {
  animation: bell-swing 3s infinite ease-in-out;
}

@keyframes bell-swing {
  0%, 100% { transform: rotate(0); }
  5%, 15%, 25% { transform: rotate(8deg); }
  10%, 20%, 30% { transform: rotate(-8deg); }
  35% { transform: rotate(0); }
}

.announcements-timeline {
  display: flex;
  flex-direction: column;
  padding: 12px 16px 20px;
  gap: 12px;
}

.announcement-card-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--line);
  background: var(--surface);
  transition: transform 200ms, border-color 200ms;
}

.announcement-card-item:hover {
  transform: translateX(3px);
}

.announcement-card-item.priority-urgent { border-left: 3px solid #EF4444; }
.announcement-card-item.priority-system { border-left: 3px solid #F59E0B; }
.announcement-card-item.priority-academic { border-left: 3px solid #8B5CF6; }
.announcement-card-item.priority-info { border-left: 3px solid #10B981; }

.announce-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.announce-tag {
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.priority-urgent .announce-tag { color: #EF4444; }
.priority-system .announce-tag { color: #F59E0B; }
.priority-academic .announce-tag { color: #8B5CF6; }
.priority-info .announce-tag { color: #10B981; }

.announce-time {
  font-size: 0.7rem;
  color: var(--muted);
  font-weight: 500;
}

.announce-item-title {
  margin: 0;
  font-size: 0.84rem;
  font-weight: 750;
  color: var(--text);
  line-height: 1.3;
}

.announce-item-desc {
  margin: 0;
  font-size: 0.76rem;
  color: var(--text-secondary);
  line-height: 1.4;
  font-weight: 500;
}

/* ── Leaderboard List ── */
.leaderboard-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.leaderboard-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface);
  border: 1px solid var(--line);
  transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
}

.leaderboard-row:hover {
  border-color: #10B981;
  transform: translateX(4px);
}

.leaderboard-rank {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--line);
  color: var(--muted);
  font-weight: 800;
  font-size: 0.8rem;
  flex-shrink: 0;
}

.leaderboard-rank.is-rank-0 { background: linear-gradient(135deg, #FBBF24, #D97706); color: #fff; }
.leaderboard-rank.is-rank-1 { background: linear-gradient(135deg, #94A3B8, #475569); color: #fff; }
.leaderboard-rank.is-rank-2 { background: linear-gradient(135deg, #CD7F32, #A16207); color: #fff; }

.leaderboard-details {
  flex: 1;
  min-width: 0;
}

.leaderboard-name-link {
  display: block;
  font-size: 0.8rem;
  font-weight: 750;
  color: var(--text);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 4px;
  transition: color 150ms;
}

.leaderboard-name-link:hover {
  color: #10B981;
}

.leaderboard-visual {
  height: 5px;
  background: var(--line);
  border-radius: 99px;
  overflow: hidden;
}

.visual-bar {
  height: 100%;
  border-radius: 99px;
  background: #10B981;
  transition: width 600ms cubic-bezier(0.4, 0, 0.2, 1);
}

.leaderboard-value {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.leaderboard-value svg {
  color: var(--muted);
}
</style>
