<script setup lang="ts">
import { computed } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({
  layout: 'admin'
})

const user = useAuthUserCookie()

const breadcrumbs = [
  { label: 'Trang chủ' }
]

const adminName = computed(() => user.value?.name || 'Administrator')

const kpiCards = [
  {
    label: 'Tổng khóa học',
    value: '128',
    sub: '+12 khóa học trong tháng',
    icon: 'pi pi-book',
    tone: 'blue',
    to: '/admin/courses'
  },
  {
    label: 'Học viên hoạt động',
    value: '1,020',
    sub: '82% đang học tập',
    icon: 'pi pi-users',
    tone: 'green',
    to: '/admin/users'
  },
  {
    label: 'Giảng viên',
    value: '180',
    sub: '24 giảng viên online',
    icon: 'pi pi-user',
    tone: 'violet',
    to: '/admin/users?role=instructor'
  },
  {
    label: 'Tỷ lệ hoàn thành',
    value: '74.8%',
    sub: '+5.2% so với kỳ trước',
    icon: 'pi pi-chart-line',
    tone: 'amber',
    to: '/admin/reports/progress'
  },
  {
    label: 'Ghi danh mới',
    value: '342',
    sub: '64 ghi danh tuần này',
    icon: 'pi pi-user-plus',
    tone: 'rose',
    to: '/admin/lnd/file-based-enrollment'
  },
  {
    label: 'Doanh thu tháng',
    value: '45,2M ₫',
    sub: '18 đơn hàng cần soát xét',
    icon: 'pi pi-wallet',
    tone: 'cyan',
    to: '/admin/orders'
  }
]

const quickActions = [
  { label: 'Thêm học viên', desc: 'Tạo hoặc import tài khoản', to: '/admin/users?action=create', icon: 'pi pi-user-plus', tone: 'green' },
  { label: 'Tạo khóa học', desc: 'Thiết lập nội dung đào tạo', to: '/admin/courses?action=create', icon: 'pi pi-book', tone: 'blue' },
  { label: 'Mở lớp học phần', desc: 'Phân lớp và lịch học', to: '/admin/lnd/classes?action=create', icon: 'pi pi-building', tone: 'amber' },
  { label: 'Cấu hình hệ thống', desc: 'Logo, email, quyền truy cập', to: '/admin/settings', icon: 'pi pi-cog', tone: 'violet' }
]

const pendingTasks = [
  { label: 'Khóa học chờ kiểm duyệt', value: 12, icon: 'pi pi-verified', tone: 'blue', to: '/admin/manage-courses' },
  { label: 'Yêu cầu rút tiền', value: 8, icon: 'pi pi-credit-card', tone: 'amber', to: '/admin/payouts' },
  { label: 'Báo lỗi chưa xử lý', value: 19, icon: 'pi pi-exclamation-triangle', tone: 'rose', to: '/admin/reports/errors' },
  { label: 'Bài thi cần giám sát', value: 6, icon: 'pi pi-pencil', tone: 'violet', to: '/admin/exam-monitor' }
]

const activities = [
  { title: 'Nguyễn Văn An hoàn thành khóa “Kỹ năng số cơ bản”', time: '5 phút trước', icon: 'pi pi-check-circle', tone: 'green' },
  { title: 'Giảng viên Trần Minh tạo mới ngân hàng câu hỏi', time: '18 phút trước', icon: 'pi pi-file-edit', tone: 'blue' },
  { title: 'Hệ thống ghi nhận 3 lần đăng nhập thất bại', time: '42 phút trước', icon: 'pi pi-shield', tone: 'amber' },
  { title: 'Khóa “Lập trình Web nâng cao” được gửi kiểm duyệt', time: '1 giờ trước', icon: 'pi pi-send', tone: 'violet' }
]

const schedule = [
  { time: '08:00', title: 'Lớp PTIT-LMS-01', meta: 'Phòng online • 126 học viên' },
  { time: '10:30', title: 'Kiểm duyệt khóa học mới', meta: '3 khóa đang chờ duyệt' },
  { time: '14:00', title: 'Báo cáo tiến độ học tập', meta: 'Khoa CNTT • Học kỳ 2025' }
]

const trafficBars = [58, 72, 46, 83, 64, 91, 76, 88, 69, 95, 82, 73]

function toneClasses(tone: string) {
  const map: Record<string, { icon: string; bg: string; border: string; text: string; soft: string }> = {
    green: {
      icon: 'text-emerald-600 bg-emerald-50 border-emerald-100',
      bg: 'bg-emerald-50',
      border: 'border-emerald-100',
      text: 'text-emerald-700',
      soft: 'from-emerald-500 to-teal-500'
    },
    blue: {
      icon: 'text-blue-600 bg-blue-50 border-blue-100',
      bg: 'bg-blue-50',
      border: 'border-blue-100',
      text: 'text-blue-700',
      soft: 'from-blue-500 to-sky-500'
    },
    amber: {
      icon: 'text-amber-600 bg-amber-50 border-amber-100',
      bg: 'bg-amber-50',
      border: 'border-amber-100',
      text: 'text-amber-700',
      soft: 'from-amber-500 to-orange-500'
    },
    violet: {
      icon: 'text-violet-600 bg-violet-50 border-violet-100',
      bg: 'bg-violet-50',
      border: 'border-violet-100',
      text: 'text-violet-700',
      soft: 'from-violet-500 to-fuchsia-500'
    },
    rose: {
      icon: 'text-rose-600 bg-rose-50 border-rose-100',
      bg: 'bg-rose-50',
      border: 'border-rose-100',
      text: 'text-rose-700',
      soft: 'from-rose-500 to-red-500'
    },
    cyan: {
      icon: 'text-cyan-600 bg-cyan-50 border-cyan-100',
      bg: 'bg-cyan-50',
      border: 'border-cyan-100',
      text: 'text-cyan-700',
      soft: 'from-cyan-500 to-blue-500'
    }
  }

  return map[tone] || map.green
}
</script>

<template>
  <AdminWorkspaceShell
    title="Bảng điều khiển"
    :subtitle="`Chào mừng ${adminName} quay trở lại hệ thống quản trị Eript LMS.`"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-6">
      <!-- Overview Hero -->
      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="grid grid-cols-1 xl:grid-cols-[1.35fr_0.65fr]">
          <div class="relative p-6 md:p-7">
            <div class="absolute right-0 top-0 h-40 w-40 rounded-full bg-[rgba(29,158,117,0.08)] blur-2xl" />
            <div class="relative flex flex-col gap-5">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" />
                    Hệ thống đang vận hành ổn định
                  </div>
                  <h2 class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">
                    Tổng quan hoạt động đào tạo hôm nay
                  </h2>
                  <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Theo dõi nhanh người dùng, khóa học, lớp học phần, khảo thí và các tác vụ quản trị cần xử lý trong ngày.
                  </p>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                  <NuxtLink to="/admin/reports/progress" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    <i class="pi pi-chart-bar text-slate-400" />
                    Xem báo cáo
                  </NuxtLink>
                  <NuxtLink to="/admin/settings" class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-sm font-semibold text-white transition hover:bg-[#178563]">
                    <i class="pi pi-sliders-h" />
                    Thiết lập
                  </NuxtLink>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <p class="text-xs font-semibold text-slate-400">Phiên học hôm nay</p>
                  <p class="mt-1 text-xl font-bold text-slate-900">36</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <p class="text-xs font-semibold text-slate-400">Người dùng online</p>
                  <p class="mt-1 text-xl font-bold text-slate-900">248</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <p class="text-xs font-semibold text-slate-400">Tỷ lệ lỗi hệ thống</p>
                  <p class="mt-1 text-xl font-bold text-emerald-600">0.12%</p>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-slate-100 bg-slate-50 p-6 xl:border-l xl:border-t-0">
            <div class="mb-4 flex items-center justify-between">
              <div>
                <h3 class="text-base font-semibold text-slate-800">Lịch vận hành</h3>
                <p class="text-xs text-slate-400">Các mốc quan trọng trong ngày</p>
              </div>
              <i class="pi pi-calendar rounded-xl border border-slate-200 bg-white p-2 text-slate-400" />
            </div>

            <div class="space-y-3">
              <div v-for="item in schedule" :key="`${item.time}-${item.title}`" class="flex gap-3 rounded-2xl border border-slate-100 bg-white p-3">
                <div class="w-14 shrink-0 rounded-xl bg-emerald-50 py-2 text-center text-xs font-bold text-emerald-700">
                  {{ item.time }}
                </div>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-slate-800">{{ item.title }}</p>
                  <p class="mt-0.5 text-xs text-slate-400">{{ item.meta }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- KPI cards -->
      <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <NuxtLink
          v-for="item in kpiCards"
          :key="item.label"
          :to="item.to"
          class="group rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="truncate text-xs font-semibold text-slate-500">{{ item.label }}</p>
              <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ item.value }}</p>
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border" :class="toneClasses(item.tone).icon">
              <i :class="[item.icon, 'text-base']" />
            </div>
          </div>
          <div class="mt-4 flex items-center justify-between gap-3">
            <p class="truncate text-xs text-slate-400">{{ item.sub }}</p>
            <i class="pi pi-arrow-right text-xs text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500" />
          </div>
        </NuxtLink>
      </section>

      <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Chart -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-2">
          <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600">
                <i class="pi pi-chart-line" />
              </div>
              <div>
                <h3 class="text-base font-semibold text-slate-800">Hoạt động đăng nhập</h3>
                <p class="text-xs text-slate-400">12 tháng gần nhất</p>
              </div>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-500">
              <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#1d9e75]" /> Học viên</span>
              <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-blue-500" /> Giảng viên</span>
            </div>
          </div>

          <div class="flex h-72 items-end gap-3 rounded-2xl border border-slate-100 bg-gradient-to-b from-slate-50 to-white p-4">
            <div v-for="(bar, index) in trafficBars" :key="index" class="flex flex-1 flex-col items-center gap-2">
              <div class="flex h-56 w-full max-w-9 items-end gap-1">
                <div class="w-1/2 rounded-t-lg bg-[#1d9e75]" :style="{ height: `${bar}%` }" />
                <div class="w-1/2 rounded-t-lg bg-blue-400" :style="{ height: `${Math.max(24, bar - 18)}%` }" />
              </div>
              <span class="text-[10px] font-semibold text-slate-400">T{{ index + 1 }}</span>
            </div>
          </div>
        </div>

        <!-- Pending tasks -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
          <div class="mb-5 flex items-center justify-between">
            <div>
              <h3 class="text-base font-semibold text-slate-800">Cần xử lý</h3>
              <p class="text-xs text-slate-400">Các tác vụ ưu tiên</p>
            </div>
            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-600">45 việc</span>
          </div>

          <div class="space-y-3">
            <NuxtLink
              v-for="task in pendingTasks"
              :key="task.label"
              :to="task.to"
              class="group flex items-center gap-3 rounded-2xl border border-slate-100 p-3 transition hover:bg-slate-50"
            >
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border" :class="toneClasses(task.tone).icon">
                <i :class="task.icon" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-700">{{ task.label }}</p>
                <p class="text-xs text-slate-400">Nhấn để xem chi tiết</p>
              </div>
              <strong class="text-lg font-bold text-slate-900">{{ task.value }}</strong>
            </NuxtLink>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Quick actions -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 xl:col-span-2">
          <div class="mb-5 flex items-center justify-between">
            <div>
              <h3 class="text-base font-semibold text-slate-800">Lối tắt nhanh</h3>
              <p class="text-xs text-slate-400">Truy cập nhanh các chức năng thường dùng</p>
            </div>
            <i class="pi pi-bolt rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-400" />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <NuxtLink
              v-for="action in quickActions"
              :key="action.label"
              :to="action.to"
              class="group rounded-2xl border border-slate-100 bg-slate-50 p-4 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-sm"
            >
              <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl border" :class="toneClasses(action.tone).icon">
                <i :class="[action.icon, 'text-lg']" />
              </div>
              <p class="font-semibold text-slate-800">{{ action.label }}</p>
              <p class="mt-1 text-xs leading-5 text-slate-400">{{ action.desc }}</p>
              <div class="mt-4 inline-flex items-center gap-2 text-xs font-bold" :class="toneClasses(action.tone).text">
                Thực hiện
                <i class="pi pi-arrow-right text-[10px] transition group-hover:translate-x-0.5" />
              </div>
            </NuxtLink>
          </div>
        </div>

        <!-- Activity -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
          <div class="mb-5 flex items-center justify-between">
            <div>
              <h3 class="text-base font-semibold text-slate-800">Hoạt động gần đây</h3>
              <p class="text-xs text-slate-400">Realtime activity feed</p>
            </div>
            <NuxtLink to="/admin/reports/activity" class="text-xs font-semibold text-[#1d9e75] hover:underline">Xem tất cả</NuxtLink>
          </div>

          <div class="space-y-4">
            <div v-for="activity in activities" :key="activity.title" class="flex gap-3">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border" :class="toneClasses(activity.tone).icon">
                <i :class="[activity.icon, 'text-sm']" />
              </div>
              <div class="min-w-0 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0">
                <p class="text-sm font-medium leading-5 text-slate-700">{{ activity.title }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ activity.time }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AdminWorkspaceShell>
</template>
