<template>
  <NuxtLayout name="admin">
    <div class="space-y-8">
      
      <!-- Header -->
      <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div class="space-y-1">
          <p class="text-on-surface-variant text-xs font-bold uppercase tracking-widest">Administrative Overlook</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface">Global Performance</h2>
        </div>
        <div class="flex items-center gap-4">
          <div class="hidden md:flex items-center bg-surface-low px-4 py-2 rounded-lg gap-2 border border-surface-dim/30">
            <span class="material-symbols-outlined text-outline text-[18px]">calendar_today</span>
            <span class="text-sm font-bold text-on-surface">Last 30 Days</span>
            <span class="material-symbols-outlined text-outline text-[18px]">expand_more</span>
          </div>
          <button @click="exportDashboard" class="cta-gradient text-white px-5 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 shadow-md hover:shadow-lg transition-all active:scale-95">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Xuất Báo Cáo
          </button>
        </div>
      </header>

      <!-- Bento Grid Metrics -->
      <section class="grid grid-cols-12 gap-6 mb-10">
        
        <!-- Total Revenue -->
        <div class="col-span-12 md:col-span-4 bg-surface-lowest p-8 rounded-[1.25rem] flex flex-col justify-between min-h-[220px] transition-all duration-300 hover:-translate-y-1 hover:shadow-ambient border border-surface-dim">
          <div class="flex justify-between items-start">
            <div class="p-3 bg-secondary/10 rounded-xl">
              <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">payments</span>
            </div>
            <span class="bg-secondary/10 text-secondary text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1 border border-secondary/20">
              <span class="material-symbols-outlined text-[14px]">trending_up</span> +12.4%
            </span>
          </div>
          <div class="mt-8">
            <p class="text-on-surface-variant text-sm font-bold mb-1">Tổng Doanh Thu</p>
            <h3 class="text-4xl font-bold font-headline tracking-tighter text-on-surface truncate" :title="formatMoney(stats.total_revenue || 0)">{{ compactPrice(stats.total_revenue || 0) }}</h3>
          </div>
        </div>

        <!-- Total Users -->
        <div class="col-span-12 md:col-span-4 bg-surface-lowest p-8 rounded-[1.25rem] flex flex-col justify-between min-h-[220px] transition-all duration-300 hover:-translate-y-1 hover:shadow-ambient border border-surface-dim">
          <div class="flex justify-between items-start">
            <div class="p-3 bg-secondary/10 rounded-xl">
               <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">group</span>
            </div>
            <span class="bg-secondary/10 text-secondary text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1 border border-secondary/20">
              <span class="material-symbols-outlined text-[14px]">trending_up</span> +5.2%
            </span>
          </div>
          <div class="mt-8">
             <p class="text-on-surface-variant text-sm font-bold mb-1">Tổng Thành Viên</p>
             <h3 class="text-4xl font-bold font-headline tracking-tighter text-on-surface">{{ stats.total_users || 0 }}</h3>
          </div>
        </div>

        <!-- System Health -->
        <div class="col-span-12 md:col-span-4 bg-surface-low p-8 rounded-[1.25rem] flex flex-col justify-between min-h-[220px] border border-surface-dim/30">
          <div>
            <div class="flex items-center gap-2 mb-6">
              <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
              </span>
              <h4 class="text-sm font-bold uppercase tracking-widest text-on-surface">Khỏe Mạnh (System Health)</h4>
            </div>
            
            <div class="space-y-4 flex-1">
               <div class="flex justify-between items-center text-sm">
                  <span class="text-on-surface-variant font-medium">Băng thông Server</span>
                  <span class="font-bold text-on-surface">24%</span>
               </div>
               <div class="h-1.5 w-full bg-surface-high rounded-full overflow-hidden">
                  <div class="h-full cta-gradient w-[24%]"></div>
               </div>
            </div>
          </div>
          <div class="pt-4 flex items-center gap-2 text-on-surface-variant text-xs font-bold border-t border-surface-dim/30 mt-6">
              <span class="material-symbols-outlined text-[16px] text-secondary">check_circle</span>
              Dịch vụ hoạt động bình thường
          </div>
        </div>
      </section>

      <!-- Growth & Alerts Row -->
      <div class="grid grid-cols-12 gap-6">
        
        <!-- Growth Chart Placeholder -->
        <div class="col-span-12 md:col-span-8 bg-surface-lowest rounded-[1.25rem] overflow-hidden flex flex-col border border-surface-dim shadow-sm">
          <div class="p-8 border-b border-surface-dim/30 flex justify-between items-center bg-surface-lowest">
            <div>
              <h4 class="text-xl font-bold font-headline text-on-surface">Biểu đồ Trưởng Khóa Học</h4>
              <p class="text-sm text-on-surface-variant font-medium mt-1">Xu hướng theo các tháng gần đây</p>
            </div>
            <div class="hidden sm:flex gap-2">
              <button class="px-4 py-1.5 text-xs font-bold rounded-lg bg-surface-low text-on-surface-variant hover:bg-surface-high transition-colors">Ngày</button>
              <button class="px-4 py-1.5 text-xs font-bold rounded-lg bg-primary text-white shadow-md">Tháng</button>
            </div>
          </div>
          
          <div v-if="revenueSeries.length === 0" class="p-10 text-center flex-1 flex flex-col justify-center items-center opacity-50">
             <span class="material-symbols-outlined text-5xl mb-2">bar_chart</span>
             <p class="font-medium text-sm">Chưa có dữ liệu giao dịch</p>
          </div>
          <!-- Visualized Bar Chart (Dynamic) -->
          <div v-else class="p-8 flex-1 flex items-end gap-4 min-h-[300px] h-full relative">
            <div v-for="(item, i) in revenueSeries.slice(-6)" :key="item.month" 
                 class="flex-1 rounded-t-lg group transition-all cursor-crosshair relative flex flex-col justify-end"
                 :class="i === revenueSeries.slice(-6).length - 1 ? 'cta-gradient shadow-lg' : 'bg-surface-high hover:bg-primary/20'"
                 :style="{ height: `${Math.max(10, calcBarHeight(item.value, maxRevenue))}%` }">
              <div class="absolute -top-10 left-1/2 -translate-x-1/2 bg-on-surface text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity z-10 whitespace-nowrap">
                {{ formatMoney(item.value) }}
              </div>
              <span class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] font-bold text-outline uppercase">{{ item.label }}</span>
            </div>
          </div>
        </div>

        <!-- Quick Access Sidebar -->
        <div class="col-span-12 md:col-span-4 space-y-6 flex flex-col">
          
          <!-- Management Modules -->
          <div class="bg-surface-lowest p-6 rounded-[1.25rem] border border-surface-dim shadow-sm flex-1">
            <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-4">Hoạt động nhanh</h4>
            <div class="grid grid-cols-2 gap-3">
              <NuxtLink to="/admin/users" class="flex flex-col items-center justify-center p-4 bg-surface-low rounded-xl hover:bg-primary/10 hover:border-primary/30 border border-transparent transition-all text-on-surface group">
                <span class="material-symbols-outlined mb-2 text-primary text-[24px] group-hover:scale-110 transition-transform" style="font-variation-settings: 'FILL' 1;">manage_accounts</span>
                <span class="text-[11px] font-bold uppercase tracking-widest">Tài Khoản</span>
              </NuxtLink>
              <NuxtLink to="/admin/courses" class="flex flex-col items-center justify-center p-4 bg-surface-low rounded-xl hover:bg-secondary/10 hover:border-secondary/30 border border-transparent transition-all text-on-surface group">
                <span class="material-symbols-outlined mb-2 text-secondary text-[24px] group-hover:scale-110 transition-transform" style="font-variation-settings: 'FILL' 1;">fact_check</span>
                <span class="text-[11px] font-bold uppercase tracking-widest">Duyệt Khóa</span>
              </NuxtLink>
              <NuxtLink to="/admin/categories" class="flex flex-col items-center justify-center p-4 bg-surface-low rounded-xl border border-transparent hover:border-surface-dim transition-all text-on-surface group">
                <span class="material-symbols-outlined mb-2 text-outline group-hover:text-on-surface text-[24px] group-hover:scale-110 transition-all">category</span>
                <span class="text-[11px] font-bold uppercase tracking-widest">Danh mục</span>
              </NuxtLink>
              <NuxtLink to="/admin/orders" class="flex flex-col items-center justify-center p-4 bg-surface-low rounded-xl border border-transparent hover:border-surface-dim transition-all text-on-surface group">
                <span class="material-symbols-outlined mb-2 text-outline group-hover:text-on-surface text-[24px] group-hover:scale-110 transition-all">receipt_long</span>
                <span class="text-[11px] font-bold uppercase tracking-widest">Đơn hàng</span>
              </NuxtLink>
              <NuxtLink to="/admin/ai" class="flex flex-col items-center justify-center p-4 bg-tertiary/10 border border-tertiary/20 rounded-xl hover:bg-tertiary/20 transition-all text-on-surface group">
                <span class="material-symbols-outlined mb-2 text-tertiary text-[24px] group-hover:scale-110 transition-transform" style="font-variation-settings: 'FILL' 1;">psychology</span>
                <span class="text-[11px] font-bold uppercase tracking-widest text-tertiary">Quản trị AI</span>
              </NuxtLink>
            </div>
          </div>

          <!-- Pending Tasks -->
          <div class="bg-surface-lowest p-6 rounded-[1.25rem] border border-surface-dim shadow-sm flex-1">
             <div class="flex justify-between items-center mb-5">
               <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Khoá đang chờ duyệt</h4>
               <span class="bg-amber-500/20 text-amber-600 border border-amber-500/20 text-[10px] font-bold px-2 py-0.5 rounded">{{ pendingCourses.length }}</span>
             </div>
             
             <div v-if="pendingCourses.length === 0" class="text-center py-6 text-outline">
                <span class="material-symbols-outlined text-3xl mb-2">done_all</span>
                <p class="text-xs font-bold">Không có việc tồn đọng</p>
             </div>
             <div v-else class="space-y-4">
                <NuxtLink v-for="course in pendingCourses" :key="course.id" :to="`/admin/courses/${course.id}`" class="flex gap-4 p-3 bg-surface-low rounded-xl hover:bg-surface-high transition-colors border border-surface-dim/10">
                   <div class="w-1.5 rounded-full bg-amber-500 shrink-0"></div>
                   <div class="flex-1 min-w-0">
                      <p class="text-sm font-bold text-on-surface truncate">{{ course.title }}</p>
                      <p class="text-[10px] uppercase font-bold text-outline-variant mt-1">{{ course.instructor?.name || 'Giảng viên' }}</p>
                   </div>
                </NuxtLink>
             </div>
          </div>

        </div>
      </div>

    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const stats = ref<any>({})
const pendingCourses = ref<any[]>([])
const { exportToCSV } = useExport()

const exportDashboard = () => {
   exportToCSV(
    [
      { metric: 'Tổng doanh thu', value: stats.value.total_revenue || 0 },
      { metric: 'Tổng người dùng', value: stats.value.total_users || 0 },
      { metric: 'Tổng khóa học', value: stats.value.total_courses || 0 },
      { metric: 'Tổng đơn hàng', value: stats.value.total_orders || 0 },
      { metric: 'Học viên', value: stats.value.total_students || 0 },
      { metric: 'Giảng viên', value: stats.value.total_instructors || 0 },
    ],
    [
      { key: 'metric', label: 'Chỉ số' },
      { key: 'value', label: 'Giá trị' },
    ],
    'admin_dashboard',
  )
}

const revenueSeries = computed(() => (stats.value.revenue_by_month || []) as Array<{ month: string; label: string; value: number }>)
const maxRevenue = computed(() => Math.max(...revenueSeries.value.map((item) => item.value), 1))

const formatMoney = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
const compactPrice = (value: number) => {
  if (value >= 1000000) return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
  if (value >= 1000) return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'K'
  return value.toString()
}

const calcBarHeight = (value: number, maxValue: number) => Math.max(0, Math.round(100 * (maxValue > 0 ? value / maxValue : 0)))

onMounted(async () => {
  try {
    const s = await $fetch<any>('/api/admin/stats', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => null)
    if(s) stats.value = s
    
    const coursesRes = await $fetch<any>('/api/admin/courses?status=pending_review&per_page=3', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [] }))
    pendingCourses.value = coursesRes.data || []
  } catch {}
})
</script>
