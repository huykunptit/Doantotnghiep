<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'

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
const toast = useToast()
const loading = ref(true)
const stats = ref<StatsResponse>({})
const adminClassesCount = ref(0)
const creditClassesCount = ref(0)
const dailyEnrollments = ref<Array<{ date: string; label: string; value: number }>>([])
const classProgressData = ref<Array<{ label: string; value: number }>>([])
const upcomingSections = ref<any[]>([])
const recentNotifications = ref<any[]>([])

const greeting = computed(() => {
  const hour = new Date().getHours()
  return hour < 12 ? 'Chào buổi sáng' : hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'
})

const todayLabel = computed(() =>
  new Date().toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
)

const formatVnd = (value: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0)

const engagement = computed(() => stats.value.engagement ?? {})
const quickActions = [
  { label: 'Thêm lớp hành chính', icon: 'pi pi-plus', to: '/admin/lnd/classes' },
  { label: 'Quản trị nhân sự', icon: 'pi pi-users', to: '/admin/users' },
  { label: 'Theo dõi doanh thu', icon: 'pi pi-credit-card', to: '/admin/orders' },
  { label: 'Cấu hình hệ thống', icon: 'pi pi-cog', to: '/admin/settings' },
]

const metricCards = computed(() => [
  { label: 'Doanh thu tích lũy', value: formatVnd(stats.value.total_revenue || 0), icon: 'pi pi-wallet', severity: 'success' },
  { label: 'Tổng người dùng', value: (stats.value.total_users || 0).toLocaleString('vi-VN'), icon: 'pi pi-users', severity: 'info' },
  { label: 'Tổng khóa học', value: (stats.value.total_courses || 0).toLocaleString('vi-VN'), icon: 'pi pi-book', severity: 'warn' },
  { label: 'Đơn hàng', value: (stats.value.total_orders || 0).toLocaleString('vi-VN'), icon: 'pi pi-shopping-cart', severity: 'secondary' },
])

const revenueChartData = computed(() => ({
  labels: (stats.value.revenue_by_month || []).map(item => item.label),
  datasets: [{
    label: 'Doanh thu',
    data: (stats.value.revenue_by_month || []).map(item => item.value),
    borderColor: '#10b981',
    backgroundColor: 'rgba(16, 185, 129, .15)',
    fill: true,
    tension: .4,
  }],
}))

const enrollmentChartData = computed(() => ({
  labels: dailyEnrollments.value.map(item => item.label),
  datasets: [{
    label: 'Ghi danh',
    data: dailyEnrollments.value.map(item => item.value),
    borderColor: '#f59e0b',
    backgroundColor: 'rgba(245, 158, 11, .15)',
    fill: true,
    tension: .4,
  }],
}))

const progressChartData = computed(() => ({
  labels: classProgressData.value.map(item => item.label),
  datasets: [{
    label: 'Tiến độ (%)',
    data: classProgressData.value.map(item => item.value),
    backgroundColor: '#8b5cf6',
    borderRadius: 6,
  }],
}))

const courseStatusChartData = computed(() => {
  const labels: Record<string, string> = {
    published: 'Đã xuất bản',
    pending_review: 'Chờ duyệt',
    draft: 'Bản nháp',
    rejected: 'Từ chối',
    archived: 'Lưu trữ',
  }
  const entries = Object.entries(stats.value.courses_by_status || {}).filter(([, value]) => Number(value) > 0)
  return {
    labels: entries.map(([key]) => labels[key] || key),
    datasets: [{
      data: entries.map(([, value]) => Number(value)),
      backgroundColor: ['#10b981', '#f59e0b', '#64748b', '#ef4444', '#334155'],
    }],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { labels: { usePointStyle: true } } },
  scales: { y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, .15)' } }, x: { grid: { display: false } } },
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: { legend: { position: 'bottom', labels: { usePointStyle: true } } },
}

function timeAgo(value: string) {
  const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60000)
  if (minutes < 1) return 'Vừa xong'
  if (minutes < 60) return `${minutes} phút trước`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} giờ trước`
  return `${Math.floor(hours / 24)} ngày trước`
}

async function loadStats() {
  loading.value = true
  try {
    const [statsResponse, extraResponse] = await Promise.all([
      useApi<StatsResponse>('/admin/stats', { headers: { Authorization: `Bearer ${auth.token}` } }),
      useApi<any>('/admin/dashboard-extra', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => null),
    ])
    stats.value = statsResponse

    try {
      const [adminResponse, creditResponse] = await Promise.all([
        useApi<{ total?: number }>('/admin/academic/administrative-classes?per_page=1', {
          headers: { Authorization: `Bearer ${auth.token}` },
        }),
        useApi<{ total?: number }>('/admin/academic/class-sections?per_page=1', {
          headers: { Authorization: `Bearer ${auth.token}` },
        }),
      ])
      adminClassesCount.value = adminResponse?.total ?? 0
      creditClassesCount.value = creditResponse?.total ?? 0
    }
    catch {
      adminClassesCount.value = 0
      creditClassesCount.value = 0
    }

    if (extraResponse) {
      dailyEnrollments.value = extraResponse.daily_enrollments ?? []
      classProgressData.value = extraResponse.class_progress ?? []
      upcomingSections.value = extraResponse.upcoming_sections ?? []
      recentNotifications.value = extraResponse.notifications ?? []
    }
  }
  catch (error: any) {
    toast.error('Không thể đồng bộ dữ liệu', error?.data?.message || 'Vui lòng thử lại.')
  }
  finally {
    loading.value = false
  }
}

onMounted(loadStats)
</script>

<template>
  <div class="dashboard-page">
    <header class="page-header">
      <div>
        <h1>Bảng điều khiển</h1>
        <p>{{ greeting }}, <strong>{{ auth.user?.name || 'Quản trị viên' }}</strong> · {{ todayLabel }}</p>
      </div>
      <Button
        label="Đồng bộ"
        icon="pi pi-refresh"
        severity="secondary"
        outlined
        :loading="loading"
        @click="loadStats"
      />
    </header>

    <div class="quick-actions">
      <NuxtLink v-for="action in quickActions" :key="action.to" :to="action.to">
        <Button :label="action.label" :icon="action.icon" severity="secondary" text fluid />
      </NuxtLink>
    </div>

    <div class="metric-grid">
      <Card v-for="metric in metricCards" :key="metric.label">
        <template #content>
          <div class="metric">
            <div>
              <span>{{ metric.label }}</span>
              <Skeleton v-if="loading" width="8rem" height="2rem" />
              <strong v-else>{{ metric.value }}</strong>
            </div>
            <Tag :severity="metric.severity as any"><i :class="metric.icon" /></Tag>
          </div>
        </template>
      </Card>
    </div>

    <div class="detail-grid">
      <Card>
        <template #title>Học vụ & tương tác</template>
        <template #content>
          <div class="compact-stats">
            <div><span>Lớp hành chính</span><strong>{{ adminClassesCount }}</strong></div>
            <div><span>Lớp tín chỉ</span><strong>{{ creditClassesCount }}</strong></div>
            <div><span>GPA quiz trung bình</span><strong>{{ engagement.avg_quiz_score || 0 }}/10</strong></div>
            <div><span>Đang hoạt động</span><strong>{{ engagement.active_students_this_week || 0 }}</strong></div>
          </div>
        </template>
      </Card>
      <Card>
        <template #title>Thành phần người dùng</template>
        <template #content>
          <div class="compact-stats">
            <div><span>Sinh viên</span><strong>{{ stats.total_students || 0 }}</strong></div>
            <div><span>Giảng viên</span><strong>{{ stats.total_instructors || 0 }}</strong></div>
            <div><span>Bài học hoàn thành</span><strong>{{ engagement.total_completions || 0 }}</strong></div>
          </div>
        </template>
      </Card>
    </div>

    <div class="charts-grid">
      <Card>
        <template #title>Doanh thu theo tháng</template>
        <template #content>
          <Skeleton v-if="loading" height="18rem" />
          <Chart v-else type="line" :data="revenueChartData" :options="chartOptions" class="chart" />
        </template>
      </Card>
      <Card>
        <template #title>Ghi danh 14 ngày gần đây</template>
        <template #content>
          <Skeleton v-if="loading" height="18rem" />
          <Chart v-else type="line" :data="enrollmentChartData" :options="chartOptions" class="chart" />
        </template>
      </Card>
      <Card>
        <template #title>Tiến độ theo lớp</template>
        <template #content>
          <Skeleton v-if="loading" height="18rem" />
          <Chart v-else type="bar" :data="progressChartData" :options="chartOptions" class="chart" />
        </template>
      </Card>
      <Card>
        <template #title>Cơ cấu khóa học</template>
        <template #content>
          <Skeleton v-if="loading" height="18rem" />
          <Chart v-else type="doughnut" :data="courseStatusChartData" :options="doughnutOptions" class="chart" />
        </template>
      </Card>
    </div>

    <div class="feed-grid">
      <Card>
        <template #title>Lớp tín chỉ đang mở</template>
        <template #content>
          <DataTable :value="upcomingSections" :loading="loading" data-key="id" size="small">
            <template #empty>Không có lớp tín chỉ đang mở.</template>
            <Column field="code" header="Mã lớp" />
            <Column header="Học phần">
              <template #body="{ data }">{{ data.course?.title ?? data.name }}</template>
            </Column>
            <Column header="Sĩ số">
              <template #body="{ data }">{{ data.enrolled_count }}/{{ data.capacity }}</template>
            </Column>
            <Column header="Giảng viên">
              <template #body="{ data }">{{ data.lecturer?.name || '—' }}</template>
            </Column>
          </DataTable>
        </template>
      </Card>

      <Card>
        <template #title>Thông báo hệ thống</template>
        <template #content>
          <div v-if="!loading && !recentNotifications.length" class="empty">Chưa có thông báo.</div>
          <div v-else class="notification-list">
            <div v-for="notification in recentNotifications" :key="notification.id" class="notification">
              <div><strong>{{ notification.title }}</strong><small>{{ timeAgo(notification.created_at) }}</small></div>
              <p>{{ notification.message }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <Card>
      <template #title>Khóa học thịnh hành</template>
      <template #content>
        <DataTable :value="stats.top_courses || []" :loading="loading" data-key="id" size="small">
          <template #empty>Chưa có dữ liệu xếp hạng.</template>
          <Column header="#" style="width: 4rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Khóa học">
            <template #body="{ data }">
              <NuxtLink :to="`/admin/manage-courses/${data.id}`" class="course-link">{{ data.title }}</NuxtLink>
            </template>
          </Column>
          <Column field="enrollments_count" header="Lượt ghi danh" />
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.dashboard-page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.page-header h1 { margin: 0; color: var(--p-text-color); font-size: 1.75rem; }
.page-header p { margin: .35rem 0 0; color: var(--p-text-muted-color); }
.quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .5rem; }
.quick-actions a { text-decoration: none; }
.metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.metric { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.metric > div { display: flex; flex-direction: column; gap: .6rem; }
.metric span, .compact-stats span { color: var(--p-text-muted-color); font-size: .85rem; }
.metric strong { color: var(--p-text-color); font-size: 1.55rem; }
.detail-grid, .feed-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.compact-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; }
.compact-stats > div { display: flex; flex-direction: column; gap: .35rem; }
.compact-stats strong { color: var(--p-text-color); font-size: 1.25rem; }
.charts-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.chart { height: 18rem; }
.notification-list { display: flex; flex-direction: column; gap: .75rem; max-height: 18rem; overflow: auto; }
.notification { padding-bottom: .75rem; border-bottom: 1px solid var(--p-content-border-color); }
.notification > div { display: flex; justify-content: space-between; gap: 1rem; }
.notification small, .notification p, .empty { color: var(--p-text-muted-color); }
.notification p { margin: .35rem 0 0; font-size: .85rem; }
.course-link { color: var(--p-primary-color); text-decoration: none; font-weight: 600; }
@media (max-width: 1100px) { .metric-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 760px) {
  .metric-grid, .charts-grid, .detail-grid, .feed-grid { grid-template-columns: 1fr; }
}
</style>
