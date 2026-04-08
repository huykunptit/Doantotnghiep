<template>
  <div>
    <NuxtLayout name="admin">
      <!-- Welcome bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4 animate-fade-in-up">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 drop-shadow-sm">Xin chào, {{ auth.user?.name?.split(' ').pop() }} <span class="origin-bottom-right inline-block hover:animate-bounce">👋</span></h2>
          <p class="text-sm text-gray-500 mt-1">Đây là tổng quan hệ thống EduPress</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="hidden sm:flex items-center gap-2 text-sm text-gray-600 bg-white px-4 py-2 rounded-xl border border-gray-100 shadow-sm">
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            {{ today }}
          </div>
          <button @click="exportDashboard" class="btn-export h-[38px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            Xuất báo cáo CSV
          </button>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8 animate-fade-in-up" style="animation-delay: 0.1s">
        <div v-for="(stat, idx) in statCards" :key="stat.label"
          class="stat-card">
          <div class="flex items-center justify-between mb-2">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="stat.bgColor">
              <span v-html="stat.icon" class="w-4 h-4" :class="stat.iconColor"></span>
            </div>
          </div>
          <p class="text-3xl font-extrabold text-gray-900 tracking-tight">{{ stat.value }}</p>
          <p class="text-xs font-medium text-gray-500 mt-1 uppercase tracking-wider">{{ stat.label }}</p>
        </div>
      </div>

      <!-- Charts row -->
      <div class="grid lg:grid-cols-2 gap-6 mb-8 animate-fade-in-up" style="animation-delay: 0.2s">
        <!-- Revenue chart -->
        <div class="card">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
              <h3 class="font-semibold text-gray-900 text-sm">Doanh thu 6 tháng</h3>
              <p class="text-xs text-gray-400 mt-0.5">Tổng: {{ formatMoney(totalRevenue6Months) }}</p>
            </div>
            <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
          </div>
          <div class="p-5">
            <div v-if="revenueSeries.length === 0" class="text-sm text-gray-400 text-center py-8">Chưa có dữ liệu</div>
            <div v-else>
              <div class="flex items-end gap-2 h-36">
                <div v-for="item in revenueSeries" :key="item.month" class="flex-1 flex flex-col items-center gap-1 group">
                  <div class="relative w-full flex items-end justify-center" style="height: 120px;">
                    <div class="w-full rounded-t-sm bg-gradient-to-t from-emerald-400 to-emerald-300 hover:to-emerald-400 transition-all cursor-pointer relative"
                      :style="{ height: `${calcBarHeight(item.value, maxRevenue)}px` }"
                      :title="formatMoney(item.value)">
                      <div class="absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] text-gray-600 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity bg-white border border-gray-200 rounded px-1 py-0.5 shadow-sm">
                        {{ shortMoney(item.value) }}
                      </div>
                    </div>
                  </div>
                  <span class="text-[10px] text-gray-400">{{ item.label }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- New users chart -->
        <div class="card">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
              <h3 class="font-semibold text-gray-900 text-sm">Người dùng mới 6 tháng</h3>
              <p class="text-xs text-gray-400 mt-0.5">Tổng: {{ totalNewUsers6Months }} người dùng mới</p>
            </div>
            <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V4H2v16h5m10 0v-5a3 3 0 10-6 0v5m6 0H7" /></svg>
            </div>
          </div>
          <div class="p-5">
            <div v-if="newUsersSeries.length === 0" class="text-sm text-gray-400 text-center py-8">Chưa có dữ liệu</div>
            <div v-else>
              <div class="flex items-end gap-2 h-36">
                <div v-for="item in newUsersSeries" :key="item.month" class="flex-1 flex flex-col items-center gap-1 group">
                  <div class="relative w-full flex items-end justify-center" style="height: 120px;">
                    <div class="w-full rounded-t-sm bg-gradient-to-t from-blue-400 to-blue-300 hover:to-blue-400 transition-all cursor-pointer relative"
                      :style="{ height: `${calcBarHeight(item.value, maxNewUsers)}px` }">
                      <div class="absolute -top-5 left-1/2 -translate-x-1/2 text-[9px] text-gray-600 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity bg-white border border-gray-200 rounded px-1 py-0.5 shadow-sm">
                        {{ item.value }}
                      </div>
                    </div>
                  </div>
                  <span class="text-[10px] text-gray-400">{{ item.label }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom row: pending courses + course status + quick actions -->
      <div class="grid lg:grid-cols-3 gap-6 animate-fade-in-up" style="animation-delay: 0.3s">
        <!-- Pending courses -->
        <div class="card-glass lg:col-span-1">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 text-sm">Khóa học chờ duyệt</h3>
            <NuxtLink to="/admin/courses?status=pending_review" class="text-xs text-primary hover:underline font-medium">Xem tất cả</NuxtLink>
          </div>
          <div v-if="pendingCourses.length === 0" class="p-6 text-center text-sm text-gray-400">Không có khóa học chờ duyệt</div>
          <div v-else class="divide-y divide-gray-100">
            <div v-for="c in pendingCourses.slice(0, 5)" :key="c.id" class="px-4 py-3 flex items-center gap-3 hover:bg-gray-50 transition-colors">
              <div class="w-9 h-6 rounded bg-gray-100 flex-shrink-0 overflow-hidden">
                <img v-if="c.thumbnail" :src="c.thumbnail" class="w-full h-full object-cover" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-gray-900 truncate">{{ c.title }}</p>
                <p class="text-[10px] text-gray-400">{{ c.instructor?.name }}</p>
              </div>
              <NuxtLink :to="`/admin/courses/${c.id}`" class="text-[10px] font-semibold text-primary hover:underline flex-shrink-0">Duyệt</NuxtLink>
            </div>
          </div>
        </div>

        <!-- Courses by status -->
        <div class="card">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900 text-sm">Phân bố khóa học</h3>
          </div>
          <div class="p-5 space-y-3">
            <div v-for="item in coursesByStatus" :key="item.status" class="flex items-center gap-3">
              <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="statusColor(item.status)"></span>
              <span class="text-sm text-gray-700 flex-1">{{ statusLabel(item.status) }}</span>
              <div class="flex items-center gap-2">
                <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :class="statusColor(item.status)"
                    :style="{ width: `${totalCourses > 0 ? (item.count / totalCourses) * 100 : 0}%` }"></div>
                </div>
                <span class="text-sm font-bold text-gray-900 w-6 text-right">{{ item.count }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="card-glass">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900 text-sm">Thao tác nhanh</h3>
          </div>
          <div class="p-4 space-y-2">
            <NuxtLink v-for="action in quickActions" :key="action.to" :to="action.to"
              class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-50 transition-colors group">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" :class="action.bg">
                <span v-html="action.icon" class="w-4 h-4" :class="action.color"></span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900">{{ action.label }}</p>
                <p class="text-xs text-gray-400">{{ action.desc }}</p>
              </div>
              <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-500 transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </NuxtLink>
          </div>
        </div>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const { exportToCSV } = useExport()

const stats = ref<any>({})
const pendingCourses = ref<any[]>([])
const coursesByStatus = ref<any[]>([])

const today = new Date().toLocaleDateString('vi-VN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

const totalCourses = computed(() => coursesByStatus.value.reduce((s, i) => s + Number(i.count), 0))

const statCards = computed(() => [
  {
    label: 'Tổng người dùng', value: stats.value.total_users || 0,
    bgColor: 'bg-blue-50', iconColor: 'text-blue-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
  },
  {
    label: 'Tổng khóa học', value: stats.value.total_courses || 0,
    bgColor: 'bg-primary-light', iconColor: 'text-primary',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>',
  },
  {
    label: 'Tổng đơn hàng', value: stats.value.total_orders || 0,
    bgColor: 'bg-amber-50', iconColor: 'text-amber-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>',
  },
  {
    label: 'Doanh thu', value: formatPrice(stats.value.total_revenue || 0),
    bgColor: 'bg-emerald-50', iconColor: 'text-emerald-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
  },
  {
    label: 'Học viên', value: stats.value.total_students || 0,
    bgColor: 'bg-indigo-50', iconColor: 'text-indigo-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>',
  },
  {
    label: 'Giảng viên', value: stats.value.total_instructors || 0,
    bgColor: 'bg-cyan-50', iconColor: 'text-cyan-700',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V4H2v16h5m10 0v-5a3 3 0 10-6 0v5m6 0H7" /></svg>',
  },
])

const quickActions = [
  {
    to: '/admin/users',
    label: 'Quản lý người dùng',
    desc: 'Tạo, sửa, xóa tài khoản',
    bg: 'bg-blue-50', color: 'text-blue-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
  },
  {
    to: '/admin/categories',
    label: 'Quản lý danh mục',
    desc: 'Thêm / sửa danh mục khóa học',
    bg: 'bg-primary-light', color: 'text-primary',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>',
  },
  {
    to: '/admin/orders',
    label: 'Xem đơn hàng',
    desc: 'Theo dõi giao dịch, doanh thu',
    bg: 'bg-amber-50', color: 'text-amber-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>',
  },
  {
    to: '/admin/reviews',
    label: 'Quản lý đánh giá',
    desc: 'Xem và kiểm duyệt reviews',
    bg: 'bg-rose-50', color: 'text-rose-600',
    icon: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>',
  },
]

function formatPrice(val: number): string {
  if (val >= 1_000_000) return `${(val / 1_000_000).toFixed(1)}M`
  if (val >= 1_000) return `${(val / 1_000).toFixed(0)}K`
  return String(val)
}

function formatMoney(val: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0)
}

function shortMoney(val: number): string {
  if (val >= 1_000_000) return `${(val / 1_000_000).toFixed(1)}M`
  if (val >= 1_000) return `${(val / 1_000).toFixed(0)}k`
  return String(val)
}

function statusColor(status: string): string {
  const map: Record<string, string> = {
    published: 'bg-primary', draft: 'bg-gray-400', pending_review: 'bg-amber-500', rejected: 'bg-red-500',
  }
  return map[status] || 'bg-gray-400'
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    published: 'Đã xuất bản', draft: 'Nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối',
  }
  return map[status] || status
}

const revenueSeries = computed(() => (stats.value.revenue_by_month || []) as Array<{ month: string; label: string; value: number }>)
const newUsersSeries = computed(() => (stats.value.new_users_by_month || []) as Array<{ month: string; label: string; value: number }>)
const maxRevenue = computed(() => Math.max(...revenueSeries.value.map((i) => i.value), 1))
const maxNewUsers = computed(() => Math.max(...newUsersSeries.value.map((i) => i.value), 1))
const totalRevenue6Months = computed(() => revenueSeries.value.reduce((sum, i) => sum + i.value, 0))
const totalNewUsers6Months = computed(() => newUsersSeries.value.reduce((sum, i) => sum + i.value, 0))

function calcBarHeight(value: number, maxValue: number): number {
  const ratio = maxValue > 0 ? value / maxValue : 0
  return Math.max(4, Math.round(110 * ratio))
}

function exportDashboard() {
  const data = [
    { metric: 'Tổng người dùng', value: stats.value.total_users || 0 },
    { metric: 'Tổng khóa học', value: stats.value.total_courses || 0 },
    { metric: 'Tổng đơn hàng', value: stats.value.total_orders || 0 },
    { metric: 'Doanh thu', value: stats.value.total_revenue || 0 },
    { metric: 'Học viên', value: stats.value.total_students || 0 },
    { metric: 'Giảng viên', value: stats.value.total_instructors || 0 },
  ]
  exportToCSV(data, [
    { key: 'metric', label: 'Chỉ số' },
    { key: 'value', label: 'Giá trị' }
  ], 'baocao_tongquan_edupress')
}

onMounted(async () => {
  try {
    const [s, courses] = await Promise.all([
      useApi<any>('/admin/stats', { token: auth.token }),
      useApi<any>('/admin/courses?status=pending_review&per_page=5', { token: auth.token }).catch(() => ({ data: [] })),
    ])
    stats.value = s
    pendingCourses.value = courses.data || []
    coursesByStatus.value = Object.entries(s.courses_by_status || {}).map(([status, count]) => ({ status, count }))
  } catch {}
})
</script>

