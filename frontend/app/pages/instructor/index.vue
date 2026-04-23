<template>
  <NuxtLayout name="instructor">
    <div class="space-y-10">

      <!-- Bento Grid Dashboard Layout -->
      <div class="grid grid-cols-12 gap-6">
        
        <!-- High Level Stats Cluster -->
        <div class="col-span-12 lg:col-span-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
          
          <!-- Stat: Tổng khóa học -->
          <div class="bg-surface-lowest p-6 rounded-2xl shadow-sm border border-surface-dim flex flex-col justify-between h-48 hover:-translate-y-1 hover:shadow-ambient transition-all duration-300">
            <div class="flex justify-between items-start">
              <div class="p-3 bg-secondary/10 rounded-xl">
                <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">view_cozy</span>
              </div>
              <span class="text-[10px] font-bold text-secondary bg-secondary/15 px-2 py-1 rounded uppercase tracking-wider">Hệ thống</span>
            </div>
            <div>
              <p class="text-4xl font-bold font-headline text-on-surface">{{ stats.total_courses || 0 }}</p>
              <p class="text-sm font-medium text-on-surface-variant mt-1">Tổng khóa học</p>
            </div>
          </div>
          
          <!-- Stat: Tổng học viên -->
          <div class="bg-surface-lowest p-6 rounded-2xl shadow-sm border border-surface-dim flex flex-col justify-between h-48 hover:-translate-y-1 hover:shadow-ambient transition-all duration-300">
            <div class="flex justify-between items-start">
              <div class="p-3 bg-primary/10 rounded-xl">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">groups</span>
              </div>
              <span class="text-[10px] font-bold text-primary bg-primary-fixed px-2 py-1 rounded uppercase tracking-wider">Toàn bộ</span>
            </div>
            <div>
              <p class="text-4xl font-bold font-headline text-on-surface">{{ stats.total_students || 0 }}</p>
              <p class="text-sm font-medium text-on-surface-variant mt-1">Học viên Đăng ký</p>
            </div>
          </div>
          
          <!-- Stat: Doanh thu -->
          <div class="bg-surface-lowest p-6 rounded-2xl shadow-sm border border-surface-dim flex flex-col justify-between h-48 hover:-translate-y-1 hover:shadow-ambient transition-all duration-300">
            <div class="flex justify-between items-start">
              <div class="p-3 bg-secondary/10 rounded-xl">
                <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">payments</span>
              </div>
              <span class="text-[10px] font-bold text-secondary bg-secondary/10 px-2 py-1 rounded uppercase tracking-wider">Tổng Thu</span>
            </div>
            <div>
              <p class="text-3xl font-bold font-headline text-on-surface truncate cursor-help" :title="formatPrice(stats.total_revenue || 0)">{{ compactPrice(stats.total_revenue || 0) }}</p>
              <p class="text-sm font-medium text-on-surface-variant mt-1">Doanh thu Net</p>
            </div>
          </div>
          
          <!-- Main Analytics Chart Placeholder -->
          <div class="col-span-1 sm:col-span-3 bg-surface-lowest rounded-2xl border border-surface-dim p-8 shadow-sm h-80 relative overflow-hidden flex flex-col justify-between">
            <div class="flex justify-between items-center mb-8 relative z-10">
              <h3 class="text-xl font-bold font-headline text-on-surface">Tăng trưởng Doanh thu & Tương tác</h3>
              <div class="flex gap-2">
                <button class="px-4 py-2 bg-surface-low text-xs font-bold rounded-lg uppercase tracking-wider">30 Ngày Qua</button>
              </div>
            </div>
            
            <div v-if="loading" class="flex-1 flex items-center justify-center">
               <span class="material-symbols-outlined animate-spin text-outline opacity-50 text-4xl">refresh</span>
            </div>
            <!-- Stylized editorial "chart" using CSS Bar Graph -->
            <div v-else class="absolute bottom-0 left-0 w-full h-56 flex items-end justify-between px-8 pb-8 gap-4 opacity-70">
              <div class="w-full bg-primary/10 rounded-t-lg transition-all h-[40%] hover:bg-primary/20 cursor-crosshair"></div>
              <div class="w-full bg-primary/10 rounded-t-lg transition-all h-[55%] hover:bg-primary/20 cursor-crosshair"></div>
              <div class="w-full bg-primary/10 rounded-t-lg transition-all h-[45%] hover:bg-primary/20 cursor-crosshair"></div>
              <div class="w-full bg-primary/10 rounded-t-lg transition-all h-[70%] hover:bg-primary/20 cursor-crosshair"></div>
              <div class="w-full bg-primary/10 rounded-t-lg transition-all h-[60%] hover:bg-primary/20 cursor-crosshair"></div>
              <div class="w-full cta-gradient rounded-t-lg transition-all h-[85%] shadow-lg cursor-crosshair relative">
                 <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-surface-highest text-[10px] font-bold px-2 py-1 rounded shadow-sm opacity-0 hover:opacity-100">$2.1k</div>
              </div>
              <div class="w-full bg-primary/10 rounded-t-lg transition-all h-[65%] hover:bg-primary/20 cursor-crosshair"></div>
            </div>
          </div>
        </div>
        
        <!-- Quick Links & Actions Column -->
        <div class="col-span-12 lg:col-span-4 flex flex-col gap-6">
          <div class="bg-surface-low rounded-2xl p-6 h-fit border border-surface-dim">
            <h3 class="text-lg font-bold font-headline text-on-surface mb-6">Quản trị Nhanh</h3>
            <div class="space-y-3">
              <NuxtLink to="/courses/create" class="w-full flex items-center justify-between p-4 bg-surface-lowest rounded-xl hover:shadow-ambient transition-all group border border-surface-dim/10">
                <div class="flex items-center gap-4">
                  <span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg" style="font-variation-settings: 'FILL' 1;">add_circle</span>
                  <span class="text-sm font-semibold">Tạo Khóa Học Mới</span>
                </div>
                <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span>
              </NuxtLink>
              
              <NuxtLink to="/instructor/question-bank" class="w-full flex items-center justify-between p-4 bg-surface-lowest rounded-xl hover:shadow-ambient transition-all group border border-surface-dim/10">
                <div class="flex items-center gap-4">
                  <span class="material-symbols-outlined text-secondary bg-secondary/10 p-2 rounded-lg" style="font-variation-settings: 'FILL' 1;">database</span>
                  <span class="text-sm font-semibold">Ngân Hàng Câu Hỏi</span>
                </div>
                <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span>
              </NuxtLink>
              
              <NuxtLink to="/instructor/students" class="w-full flex items-center justify-between p-4 bg-surface-lowest rounded-xl hover:shadow-ambient transition-all group border border-surface-dim/10">
                <div class="flex items-center gap-4">
                  <span class="material-symbols-outlined text-secondary bg-secondary/10 p-2 rounded-lg" style="font-variation-settings: 'FILL' 1;">group</span>
                  <span class="text-sm font-semibold">Phân Tích Học Viên</span>
                </div>
                <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span>
              </NuxtLink>
            </div>
          </div>
          
          <!-- Recent Activity Feed (Mocked visually but with true style) -->
          <div class="bg-surface-lowest border border-surface-dim rounded-2xl p-6 shadow-sm flex-1 flex flex-col">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-bold font-headline text-on-surface">Hoạt Động Mới Nhất</h3>
              <NuxtLink to="/instructor/courses" class="text-[10px] font-bold text-outline uppercase tracking-widest transition-colors hover:text-primary">
                Xem tất cả
              </NuxtLink>
            </div>
            
            <div v-if="loading" class="flex-1 flex items-center justify-center text-outline">Đang kết nối...</div>
            <div v-else class="space-y-6 flex-1">
               <div class="flex gap-4 items-start">
                <div class="w-10 h-10 rounded-full bg-secondary/15 flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-secondary text-sm">auto_awesome</span>
                </div>
                <div>
                  <p class="text-sm text-on-surface">Bảng điều khiển <strong>Stitch Nexus DS2</strong> đã được kích hoạt thành công.</p>
                  <span class="text-xs text-on-surface-variant font-medium">Bây giờ</span>
                </div>
              </div>
              <div class="flex gap-4 items-start">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-primary text-sm">school</span>
                </div>
                <div>
                  <p class="text-sm text-on-surface">Hệ thống đang phục vụ hàng chục học viên mới thông qua nền tảng hiện đại.</p>
                  <span class="text-xs text-on-surface-variant font-medium">Hôm nay</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Active Courses Editorial Grid -->
      <div class="mt-4 pt-10 border-t border-surface-dim/30">
        <div class="flex justify-between items-end mb-8">
          <div>
            <h3 class="text-2xl font-bold font-headline text-on-surface">Khóa Học Đang Quản Lý</h3>
            <p class="text-on-surface-variant text-sm mt-1">Theo dõi tiến độ duyệt và doanh thu của danh sách khóa học.</p>
          </div>
          <NuxtLink to="/instructor/courses" class="bg-surface-high text-on-surface px-6 py-2.5 rounded-lg font-bold text-xs uppercase tracking-widest hover:bg-surface-highest transition-colors hidden sm:block">
            Xem Toàn Bộ
          </NuxtLink>
        </div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div v-for="i in 3" :key="i" class="h-64 bg-surface-high animate-pulse rounded-2xl"></div>
        </div>
        
        <UiEmptyState v-else-if="courses.length === 0" title="Chưa có khóa học nào" description="Bạn vẫn chưa xuất bản khóa học nào lên nền tảng." class="border border-surface-dim py-16">
          <template #icon><span class="material-symbols-outlined text-4xl">view_list</span></template>
          <UiButton to="/courses/create" class="mt-4">Tạo khóa học ngay</UiButton>
        </UiEmptyState>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
          <!-- Course Cards Limit 3 -->
          <div v-for="course in courses.slice(0,3)" :key="course.id" class="bg-surface-lowest rounded-2xl overflow-hidden shadow-sm hover:shadow-ambient border border-surface-dim transition-all duration-300 group flex flex-col">
            <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="relative h-48 overflow-hidden block">
              <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
              <div v-else class="w-full h-full bg-surface-high flex items-center justify-center group-hover:scale-105 transition-transform duration-500 text-3xl">📘</div>
              
              <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
              
              <!-- Badge -->
              <div class="absolute top-4 right-4">
                 <StatusBadge :value="statusLabel(course.status)" />
              </div>
              
              <div class="absolute bottom-4 left-6 pr-6">
                <h4 class="text-white font-headline font-bold text-lg leading-tight line-clamp-2 drop-shadow-sm">{{ course.title }}</h4>
              </div>
            </NuxtLink>
            
            <div class="p-6 flex flex-col flex-1">
              <div class="flex justify-between text-sm mb-6 pb-4 border-b border-surface-dim/30">
                <span class="text-on-surface-variant flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">groups</span> <strong>{{ course.enrollments_count || 0 }}</strong> học viên</span>
                <span class="text-on-surface-variant flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">library_books</span> <strong>{{ course.lessons_count || 0 }}</strong> bài học</span>
              </div>
              
              <p v-if="course.status === 'rejected' && course.reject_reason" class="text-xs bg-error-container text-error px-3 py-2 rounded-lg font-medium mb-4">
                <strong>Từ chối:</strong> {{ course.reject_reason }}
              </p>

              <div class="mt-auto space-y-3">
                <div class="flex gap-2">
                  <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="flex-1 text-center py-2.5 bg-surface-lowest border border-outline-variant/40 text-on-surface font-semibold text-xs uppercase tracking-widest rounded-lg hover:bg-surface-low transition-colors">
                     Curriculum
                  </NuxtLink>
                  <NuxtLink :to="`/courses/${course.id}`" class="flex-1 text-center py-2.5 bg-primary/10 border border-primary/20 text-primary font-semibold text-xs uppercase tracking-widest rounded-lg hover:bg-primary/20 transition-colors">
                     Xem trước
                  </NuxtLink>
                  <NuxtLink :to="`/courses/${course.id}/edit`" class="flex-1 text-center py-2.5 bg-surface-lowest border border-outline-variant/40 text-on-surface font-semibold text-xs uppercase tracking-widest rounded-lg hover:bg-surface-low transition-colors">
                     Chỉnh sửa
                  </NuxtLink>
                </div>
                <button v-if="course.status === 'draft' || course.status === 'rejected'" @click="publishCourse(course)" class="w-full py-2.5 cta-gradient text-white font-bold text-xs uppercase tracking-widest rounded-lg hover:shadow-md transition-shadow">
                  Gửi Duyệt Ngay
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'instructor' })
const auth = useAuthStore()
const courseStore = useCourseStore()
const courses = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(true)

const statusLabel = (status: string) => {
  const map: Record<string,string> = { published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }
  return map[status] || status
}

const formatPrice = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const compactPrice = (value: number) => {
  if (value >= 1000000) return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
  if (value >= 1000) return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'K'
  return value.toString()
}

async function publishCourse(course: any) { 
  try { 
    await courseStore.publishCourse(course.id)
    course.status = 'pending_review' 
  } catch (e: any) { 
    alert(e?.data?.message || 'Có lỗi khi gửi duyệt khóa học.') 
  } 
}

onMounted(async () => { 
  loading.value = true
  try { 
    const [myCourses, statsData] = await Promise.all([
      courseStore.fetchMyCourses(), 
      $fetch<any>('/api/instructor/stats', { headers: { 'Authorization': `Bearer ${auth.token}` } }).catch(() => ({}))
      // Mock logic in case API missing: fallback to simple counts
    ])
    courses.value = myCourses || []
    stats.value = statsData && Object.keys(statsData).length > 0 ? statsData : {
      total_courses: courses.value.length,
      total_students: courses.value.reduce((acc, c) => acc + (c.enrollments_count || 0), 0),
      total_revenue: 0 
    }
  } finally { 
    loading.value = false 
  } 
})
</script>

<style scoped>
html { scroll-behavior: smooth; }
</style>
