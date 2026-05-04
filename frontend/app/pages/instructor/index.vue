<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import UiStatCard from '~/components/dashboard/charts/UiStatCard.vue'
import UiAreaChart from '~/components/dashboard/charts/UiAreaChart.vue'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'
import UiDonut from '~/components/dashboard/charts/UiDonut.vue'

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

const formatVnd = (n: number) => {
  if (n >= 1_000_000_000) return `${(n / 1_000_000_000).toFixed(1)}B`
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(0)}K`
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
    published: { label: 'Đã xuất bản', color: '#16a34a' },
    pending_review: { label: 'Chờ duyệt', color: '#d97706' },
    draft: { label: 'Bản nháp', color: '#64748b' },
    rejected: { label: 'Bị từ chối', color: '#dc2626' },
    archived: { label: 'Lưu trữ', color: '#94a3b8' },
  }
  return Object.entries(map)
    .map(([key, value]) => ({
      label: colorMap[key]?.label || key,
      value: Number(value),
      color: colorMap[key]?.color || '#2f7a45',
    }))
    .filter((s) => s.value > 0)
})

const totalCoursesFromStatus = computed(() =>
  courseStatusSegments.value.reduce((sum, s) => sum + s.value, 0),
)

const loadStats = async () => {
  loading.value = true
  error.value = ''
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

onMounted(loadStats)
</script>

<template>
  <section class="dashboard-pro space-y-6">
    <!-- Header -->
    <header class="instructor-header">
      <div>
        <p class="header-kicker">Khu vực giảng viên</p>
        <h1 class="header-title">Tổng quan giảng dạy</h1>
        <p class="header-desc">
          Theo dõi doanh thu, học viên và hiệu suất khóa học của bạn trong 6 tháng gần nhất.
        </p>
      </div>
      <div class="header-actions">
        <NuxtLink to="/instructor/question-bank" class="btn-ghost">
          <span class="material-symbols-outlined">database</span>
          Ngân hàng câu hỏi
        </NuxtLink>
        <NuxtLink to="/instructor/courses" class="btn-primary">
          <span class="material-symbols-outlined">add_circle</span>
          Tạo khóa học
        </NuxtLink>
      </div>
    </header>

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
        icon-bg="rgba(22,163,74,0.1)"
        icon-color="#16a34a"
        :sparkline="revenueValues"
        spark-color="#16a34a"
        :loading="loading"
      />
      <UiStatCard
        label="Học viên"
        :value="(stats.total_students || 0).toLocaleString('vi-VN')"
        :delta="studentDelta"
        delta-label="so với tháng trước"
        icon="group"
        icon-bg="rgba(25,118,210,0.1)"
        icon-color="#1976d2"
        :sparkline="studentValues"
        spark-color="#1976d2"
        :loading="loading"
      />
      <UiStatCard
        label="Khóa học"
        :value="(stats.total_courses || 0).toLocaleString('vi-VN')"
        :delta="null"
        :delta-label="`${stats.courses_by_status?.published || 0} đang xuất bản`"
        icon="school"
        icon-bg="rgba(47,122,69,0.1)"
        icon-color="#2f7a45"
        :loading="loading"
      />
      <UiStatCard
        label="Chờ duyệt"
        :value="stats.courses_by_status?.pending_review || 0"
        :delta="null"
        :delta-label="(stats.courses_by_status?.draft || 0) + ' bản nháp'"
        icon="pending"
        icon-bg="rgba(217,119,6,0.1)"
        icon-color="#d97706"
        :loading="loading"
      />
    </div>

    <!-- Charts grid -->
    <div class="grid-12">
      <!-- Revenue (8 cols) + Donut (4 cols) -->
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
          :series="[{ name: 'Doanh thu', values: revenueValues, color: '#16a34a' }]"
          :labels="monthLabels"
          :height="200"
          :format-y="formatVnd"
        />
        <div v-else class="empty-block">Chưa có doanh thu trong 6 tháng qua.</div>
      </div>

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

      <!-- Bar chart (5 cols) + Top courses (7 cols) -->
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
          color="#1976d2"
        />
        <div v-else class="empty-block">Chưa có học viên ghi danh trong 6 tháng qua.</div>
      </div>

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

      <!-- Quick links full width -->
      <div class="dashboard-card chart-card">
        <header class="chart-card-head">
          <div>
            <p class="chart-card-kicker">Truy cập nhanh</p>
            <h3 class="chart-card-title">Công cụ giảng viên</h3>
          </div>
        </header>
        <div class="quick-grid">
          <NuxtLink to="/instructor/courses" class="quick-tile">
            <span class="material-symbols-outlined">auto_stories</span>
            <p>Khóa học</p>
          </NuxtLink>
          <NuxtLink to="/instructor/students" class="quick-tile">
            <span class="material-symbols-outlined">group</span>
            <p>Học viên</p>
          </NuxtLink>
          <NuxtLink to="/instructor/revenue" class="quick-tile">
            <span class="material-symbols-outlined">monitoring</span>
            <p>Doanh thu</p>
          </NuxtLink>
          <NuxtLink to="/instructor/exams" class="quick-tile">
            <span class="material-symbols-outlined">quiz</span>
            <p>Đợt thi</p>
          </NuxtLink>
          <NuxtLink to="/instructor/question-bank" class="quick-tile">
            <span class="material-symbols-outlined">database</span>
            <p>Ngân hàng câu hỏi</p>
          </NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dashboard-pro { padding-bottom: 24px; }

.instructor-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  padding: 24px 26px;
  background: var(--surface-lowest, #fff);
  border: 1px solid var(--surface-dim, #e5e7eb);
  border-radius: 24px;
}
.header-kicker {
  margin: 0 0 4px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--on-surface-variant, #5f675f);
}
.header-title {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--on-surface, #111);
}
.header-desc {
  margin: 8px 0 0;
  font-size: 0.9rem;
  color: var(--on-surface-variant, #5f675f);
  max-width: 480px;
}
.header-actions { display: flex; flex-wrap: wrap; gap: 10px; }

.btn-primary, .btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 16px;
  border-radius: 12px;
  font-size: 0.86rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.15s ease;
}
.btn-primary { background: #2f7a45; color: #fff; box-shadow: 0 6px 14px rgba(47, 122, 69, 0.25); }
.btn-primary:hover { transform: translateY(-1px); filter: brightness(1.05); }
.btn-ghost {
  background: rgba(17, 17, 17, 0.04);
  color: var(--on-surface, #111);
  border: 1px solid rgba(17, 17, 17, 0.08);
}
.btn-ghost:hover { background: rgba(47, 122, 69, 0.08); border-color: rgba(47, 122, 69, 0.3); color: #2f7a45; }
.btn-primary .material-symbols-outlined,
.btn-ghost .material-symbols-outlined { font-size: 18px; }

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
  background: var(--surface-lowest, #fff);
  border: 1px solid var(--surface-dim, #e5e7eb);
  border-radius: 18px;
  padding: 18px 20px;
  min-width: 0;
}
.chart-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}
.chart-card-kicker {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--on-surface-variant, #5f675f);
}
.chart-card-title {
  margin: 4px 0 0;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--on-surface, #111);
}
.chart-card-tag {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(47, 122, 69, 0.1);
  color: #2f7a45;
}
.chart-card-link {
  font-size: 0.78rem;
  font-weight: 700;
  color: #2f7a45;
  text-decoration: none;
}
.chart-card-link:hover { text-decoration: underline; }

.empty-block {
  display: grid;
  place-items: center;
  min-height: 180px;
  border: 1px dashed rgba(17, 17, 17, 0.12);
  border-radius: 16px;
  font-size: 0.86rem;
  color: var(--on-surface-variant);
  text-align: center;
  padding: 24px;
}

/* Leaderboard */
.leaderboard { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.leaderboard-item {
  display: grid;
  grid-template-columns: 28px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(17, 17, 17, 0.02);
  transition: background 0.15s;
}
.leaderboard-item:hover { background: rgba(47, 122, 69, 0.06); }
.leaderboard-rank {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: rgba(47, 122, 69, 0.1);
  color: #2f7a45;
  font-weight: 800;
  font-size: 0.8rem;
}
.leaderboard-title {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--on-surface);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.leaderboard-title:hover { color: #2f7a45; }
.leaderboard-value {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.84rem;
  font-weight: 700;
  color: var(--on-surface-variant);
  font-variant-numeric: tabular-nums;
}
.leaderboard-value .material-symbols-outlined { font-size: 16px; }

/* Quick grid */
.quick-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}
.quick-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 18px 12px;
  border-radius: 16px;
  background: rgba(17, 17, 17, 0.02);
  border: 1px solid transparent;
  text-decoration: none;
  color: var(--on-surface);
  transition: all 0.18s ease;
}
.quick-tile:hover {
  border-color: rgba(47, 122, 69, 0.3);
  background: rgba(47, 122, 69, 0.06);
  transform: translateY(-2px);
}
.quick-tile .material-symbols-outlined {
  font-size: 32px;
  color: #2f7a45;
}
.quick-tile p {
  margin: 0;
  font-size: 0.86rem;
  font-weight: 700;
}
</style>
