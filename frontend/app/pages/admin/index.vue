<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import UiStatCard from '~/components/dashboard/charts/UiStatCard.vue'
import UiAreaChart from '~/components/dashboard/charts/UiAreaChart.vue'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'
import UiDonut from '~/components/dashboard/charts/UiDonut.vue'
import DashboardSchedule from '~/components/dashboard/DashboardSchedule.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
  adminSearchPlaceholder: 'Tìm người dùng, khóa học, giao dịch...',
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

const formatVnd = (n: number) => {
  if (n >= 1_000_000_000) return `${(n / 1_000_000_000).toFixed(1)}B`
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(0)}K`
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
    published: { label: 'Đã xuất bản', color: 'var(--green)' },
    pending_review: { label: 'Chờ duyệt', color: '#d97706' },
    draft: { label: 'Bản nháp', color: '#64748b' },
    rejected: { label: 'Bị từ chối', color: '#dc2626' },
    archived: { label: 'Lưu trữ', color: '#94a3b8' },
  }
  return Object.entries(map)
    .map(([key, value]) => ({
      label: colorMap[key]?.label || key,
      value: Number(value),
      color: colorMap[key]?.color || 'var(--green)',
    }))
    .filter((s) => s.value > 0)
})

const totalCoursesFromStatus = computed(() =>
  courseStatusSegments.value.reduce((sum, s) => sum + s.value, 0),
)

const engagement = computed(() => stats.value.engagement ?? {})

const loadStats = async () => {
  loading.value = true
  error.value = ''
  try {
    stats.value = await useApi<StatsResponse>('/admin/stats', {
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
    const res = await useApi<any>('/exams/standalone?per_page=20&status=published', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    upcomingExams.value = Array.isArray(res) ? res : (res?.data || [])
  }
  catch {}
})
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Bảng điều khiển']"
    description="Tổng quan toàn hệ thống — doanh thu, người dùng, khóa học và mức độ tương tác trong 6 tháng gần nhất."
    title="Tổng quan hệ thống"
  >
    <section class="dashboard-pro space-y-6">
      <div v-if="error" class="rounded-2xl border border-error/30 bg-error/10 p-4 text-sm text-error">
        {{ error }}
        <button class="ml-2 font-bold underline" @click="loadStats">Thử lại</button>
      </div>

      <!-- KPI grid -->
      <div class="kpi-grid">
        <UiStatCard
          label="Doanh thu (6 tháng)"
          :value="formatVndFull(stats.total_revenue || 0)"
          :delta="revenueDelta"
          delta-label="so với tháng trước"
          icon="payments"
          icon-bg="rgba(var(--green-rgb),0.1)"
          icon-color="var(--green)"
          :sparkline="revenueValues"
          spark-color="var(--green)"
          :loading="loading"
        />
        <UiStatCard
          label="Người dùng"
          :value="(stats.total_users || 0).toLocaleString('vi-VN')"
          :delta="userDelta"
          delta-label="so với tháng trước"
          icon="group"
          icon-bg="rgba(var(--green-rgb),0.1)"
          icon-color="var(--green)"
          :sparkline="userValues"
          spark-color="var(--green)"
          :loading="loading"
        />
        <UiStatCard
          label="Khóa học"
          :value="(stats.total_courses || 0).toLocaleString('vi-VN')"
          :delta-label="`${stats.courses_by_status?.published || 0} đang xuất bản`"
          :delta="null"
          icon="school"
          icon-bg="rgba(var(--green-rgb),0.1)"
          icon-color="var(--green)"
          :loading="loading"
        />
        <UiStatCard
          label="Đơn hàng"
          :value="(stats.total_orders || 0).toLocaleString('vi-VN')"
          :delta-label="`${stats.total_students || 0} học viên đang học`"
          :delta="null"
          icon="receipt_long"
          icon-bg="rgba(217,119,6,0.1)"
          icon-color="#d97706"
          :loading="loading"
        />
      </div>

      <!-- Schedule (7 cols) + course status donut (5 cols) -->
      <div class="grid-12">
        <div class="span-lg-7">
          <DashboardSchedule :events="scheduleEvents" title="Kỳ thi sắp diễn ra" />
        </div>

        <div class="dashboard-card chart-card span-lg-5">
          <header class="chart-card-head">
            <div>
              <p class="chart-card-kicker">Khóa học</p>
              <h3 class="chart-card-title">Phân bố trạng thái</h3>
            </div>
            <NuxtLink to="/admin/courses" class="chart-card-link">Quản lý →</NuxtLink>
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
          <div v-else class="empty-block">Chưa có khóa học nào.</div>
        </div>

        <!-- Revenue (8 cols) + new users (4 cols) -->
        <div class="dashboard-card chart-card span-lg-8">
          <header class="chart-card-head">
            <div>
              <p class="chart-card-kicker">Doanh thu</p>
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
          <div v-else class="empty-block">Chưa có dữ liệu doanh thu trong 6 tháng qua.</div>
        </div>

        <div class="dashboard-card chart-card span-lg-4">
          <header class="chart-card-head">
            <div>
              <p class="chart-card-kicker">Người dùng mới</p>
              <h3 class="chart-card-title">Đăng ký theo tháng</h3>
            </div>
            <span class="chart-card-tag">Số lượng</span>
          </header>
          <div v-if="loading" class="h-44 rounded-xl bg-surface-high animate-pulse" />
          <UiBarChart
            v-else-if="userPoints.length"
            :values="userValues"
            :labels="userPoints.map((p) => p.label)"
            :height="180"
            color="var(--green)"
          />
          <div v-else class="empty-block">Chưa có người dùng mới trong 6 tháng qua.</div>
        </div>

        <!-- Top courses (full width) -->
        <div class="dashboard-card chart-card span-lg-12">
          <header class="chart-card-head">
            <div>
              <p class="chart-card-kicker">Top khóa học</p>
              <h3 class="chart-card-title">Theo lượt ghi danh</h3>
            </div>
            <NuxtLink to="/admin/manage-courses" class="chart-card-link">Tất cả →</NuxtLink>
          </header>
          <div v-if="loading" class="space-y-2">
            <div v-for="i in 5" :key="i" class="h-10 rounded-lg bg-surface-high animate-pulse" />
          </div>
          <ol v-else-if="stats.top_courses?.length" class="leaderboard">
            <li v-for="(course, i) in stats.top_courses" :key="course.id" class="leaderboard-item">
              <span class="leaderboard-rank">{{ i + 1 }}</span>
              <NuxtLink :to="`/admin/manage-courses/${course.id}`" class="leaderboard-title">
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

        <!-- Engagement: full width row, 3 horizontal tiles -->
        <div class="dashboard-card chart-card">
          <header class="chart-card-head">
            <div>
              <p class="chart-card-kicker">Tương tác học tập</p>
              <h3 class="chart-card-title">Mức độ hoạt động</h3>
            </div>
          </header>
          <div v-if="loading" class="engagement-grid">
            <div v-for="i in 3" :key="i" class="h-20 rounded-xl bg-surface-high animate-pulse" />
          </div>
          <div v-else class="engagement-grid">
            <div class="engagement-tile">
              <span class="material-symbols-outlined engagement-icon" style="color:var(--green)">grade</span>
              <div>
                <p class="engagement-value">{{ Math.round((engagement.avg_quiz_score || 0) * 10) / 10 }}</p>
                <p class="engagement-label">Điểm quiz trung bình</p>
              </div>
            </div>
            <div class="engagement-tile">
              <span class="material-symbols-outlined engagement-icon" style="color:var(--green)">task_alt</span>
              <div>
                <p class="engagement-value">{{ (engagement.total_completions || 0).toLocaleString('vi-VN') }}</p>
                <p class="engagement-label">Bài học đã hoàn thành</p>
              </div>
            </div>
            <div class="engagement-tile">
              <span class="material-symbols-outlined engagement-icon" style="color:#d97706">bolt</span>
              <div>
                <p class="engagement-value">{{ (engagement.active_students_this_week || 0).toLocaleString('vi-VN') }}</p>
                <p class="engagement-label">Học viên hoạt động (7 ngày)</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AdminWorkspaceShell>
</template>

<style scoped>
.dashboard-pro { padding-bottom: 24px; }

.kpi-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

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
  background: var(--surface-lowest);
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 16px 20px;
  min-width: 0;
  box-shadow: 0 8px 30px rgba(31, 49, 43, 0.03);
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
  font-size: 1.05rem;
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
  background: var(--bg);
  transition: background 0.2s, transform 0.2s;
}
.leaderboard-item:hover { 
  background: var(--green-soft); 
  transform: translateX(2px);
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

/* Engagement */
.engagement-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}
.engagement-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  border-radius: 14px;
  background: var(--surface);
  border: 1px solid var(--line);
}
.engagement-icon { font-size: 28px; }
.engagement-value {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--text);
  font-variant-numeric: tabular-nums;
}
.engagement-label {
  margin: 2px 0 0;
  font-size: 0.82rem;
  color: var(--muted);
}
</style>
