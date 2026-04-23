<template>
  <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 min-h-[80vh]">
    <!-- Header Section -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-12">
      <div class="max-w-2xl">
        <h2 class="text-4xl md:text-[3.5rem] font-bold leading-tight font-headline tracking-tight text-on-surface">Khóa học của tôi</h2>
        <p class="text-on-surface-variant text-lg mt-4 font-body leading-relaxed">
          Theo dõi tiến độ và quay lại bài học đang học dở bất cứ lúc nào.
        </p>
      </div>
      <div class="flex gap-4 mb-2 shrink-0">
        <NuxtLink to="/courses" class="bg-surface-high text-on-surface hover:text-primary px-6 py-3 rounded-xl font-semibold text-sm transition-all hover:bg-surface-highest">
          Khám phá thêm
        </NuxtLink>
      </div>
    </header>

    <div v-if="loading" class="mt-8 grid gap-8 grid-cols-12">
      <div class="col-span-12 lg:col-span-8 h-[300px] rounded-[1.5rem] bg-surface-high animate-pulse" />
      <div class="col-span-12 lg:col-span-4 h-[300px] rounded-[1.5rem] bg-surface-high animate-pulse" />
    </div>

    <UiEmptyState v-else-if="enrollments.length === 0" class="mt-8 py-20" title="Chưa có khóa học nào" description="Bạn chưa đăng ký khóa học nào. Hãy khám phá ngay để không bỏ lỡ kiến thức!">
      <template #icon>
        <span class="material-symbols-outlined text-4xl">school</span>
      </template>
      <NuxtLink to="/courses">
        <UiButton class="mt-4">Khám phá khóa học</UiButton>
      </NuxtLink>
    </UiEmptyState>

    <!-- Bento Grid Layout -->
    <div v-else class="grid grid-cols-12 gap-8 mt-8">
      
      <!-- LẦN HỌC GẦN NHẤT (Latest Enrollment) -->
      <NuxtLink :to="`/learn/${activeEnrollment.course_id}`" class="col-span-12 lg:col-span-8 group block">
        <div class="bg-surface-lowest rounded-xl p-6 md:p-8 h-full transition-all duration-500 hover:-translate-y-1 hover:shadow-ambient flex flex-col md:flex-row gap-6 md:gap-8 overflow-hidden border border-surface-dim">
          <div class="w-full md:w-1/2 aspect-video md:aspect-square rounded-lg overflow-hidden shrink-0 relative bg-surface-high">
            <img v-if="activeEnrollment.course?.thumbnail" :src="activeEnrollment.course.thumbnail" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
            <div v-else class="flex h-full items-center justify-center text-4xl text-outline">📘</div>
            <div class="absolute inset-0 bg-black/10 group-hover:bg-black/0 transition-colors"></div>
          </div>
          <div class="flex flex-col justify-between py-2 flex-1">
            <div>
              <div class="flex items-center gap-2 mb-4">
                <span class="bg-secondary/10 text-secondary text-[10px] font-bold tracking-widest uppercase px-3 py-1 rounded-full">Đang học</span>
                <span class="text-on-surface-variant text-xs font-medium">{{ formatDate(activeEnrollment.enrolled_at) }}</span>
              </div>
              <h3 class="text-2xl font-bold font-headline mb-3 text-on-surface group-hover:text-primary transition-colors line-clamp-2">
                {{ activeEnrollment.course?.title }}
              </h3>
              <p class="text-on-surface-variant text-sm leading-relaxed mb-6 line-clamp-3">
                Tiếp tục bài học của bạn và hoàn thành khối lượng kiến thức của khóa học này.
              </p>
            </div>
            <div class="space-y-6">
              <div class="space-y-2">
                <div class="flex justify-between items-end text-xs font-semibold">
                  <span class="text-on-surface">Tiến độ: {{ Math.round(activeEnrollment.progress || 0) }}%</span>
                </div>
                <div class="w-full h-1.5 bg-surface-high rounded-full overflow-hidden">
                  <div class="h-full progress-gradient rounded-full box-border" :style="{ width: `${activeEnrollment.progress || 0}%` }"></div>
                </div>
              </div>
              <button class="cta-gradient text-white px-6 py-3.5 rounded-xl font-bold text-sm flex items-center justify-center gap-3 w-full md:w-fit hover:shadow-lg transition-shadow">
                Tiếp tục Học
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
              </button>
            </div>
          </div>
        </div>
      </NuxtLink>

      <!-- Stats/Summary Card -->
      <div class="col-span-12 lg:col-span-4 space-y-8">
        <div class="bg-surface-low rounded-xl p-8 flex flex-col justify-between h-full border border-surface-dim">
          <h4 class="text-sm font-bold text-on-surface uppercase tracking-widest mb-8">Thống kê học tập</h4>
          <div class="space-y-8">
            <div class="flex items-center gap-6">
              <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined">schedule</span>
              </div>
              <div>
                <p class="text-2xl font-bold font-headline">{{ enrollments.length }}</p>
                <p class="text-xs text-on-surface-variant">Khóa học đã đăng ký</p>
              </div>
            </div>
            <div class="flex items-center gap-6">
              <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center text-secondary">
                <span class="material-symbols-outlined">verified</span>
              </div>
              <div>
                <p class="text-2xl font-bold font-headline">0</p>
                <p class="text-xs text-on-surface-variant">Chứng chỉ đạt được</p>
              </div>
            </div>
          </div>
          <div class="mt-auto pt-8 border-t border-surface-dim/30">
            <p class="text-sm italic text-on-surface-variant">"Đầu tư vào tri thức luôn mang lại lãi suất cao nhất."</p>
          </div>
        </div>
      </div>

      <!-- Secondary Course Cards (Các khóa còn lại) -->
      <div v-for="e in secondaryEnrollments" :key="e.id" class="col-span-12 md:col-span-6 lg:col-span-4 group flex flex-col">
        <NuxtLink :to="`/learn/${e.course_id}`" class="bg-surface-lowest rounded-xl p-6 h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-ambient border border-surface-dim flex flex-col">
          <div class="aspect-[16/10] rounded-lg overflow-hidden mb-5 relative bg-surface-high">
            <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
            <div class="absolute inset-0 bg-black/10 group-hover:bg-black/0 transition-colors"></div>
            <div v-if="e.progress >= 100" class="absolute right-3 top-3 rounded-full bg-secondary px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-white shadow-sm">Hoàn thành</div>
          </div>
          
          <h4 class="text-lg font-bold font-headline mb-2 text-on-surface group-hover:text-primary transition-colors line-clamp-2">{{ e.course?.title }}</h4>
          <p class="text-on-surface-variant text-sm mb-6 flex-grow line-clamp-2">Đăng ký ngày: {{ formatDate(e.enrolled_at) }}</p>
          
          <div class="space-y-4">
            <div class="w-full h-1 bg-surface-high rounded-full overflow-hidden">
              <div class="h-full progress-gradient rounded-full box-border" :style="{ width: `${e.progress || 0}%` }"></div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-on-surface-variant">{{ Math.round(e.progress || 0) }}% COMPLETE</span>
              <button class="text-primary font-bold text-sm flex items-center gap-1 group-hover:underline">
                {{ e.progress >= 100 ? 'Xem lại' : 'Tiếp tục' }} <span class="material-symbols-outlined text-[16px]">{{ e.progress >= 100 ? 'done_all' : 'play_arrow' }}</span>
              </button>
            </div>
          </div>
        </NuxtLink>
      </div>

    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'auth' })
const courseStore = useCourseStore()
const loading = ref(true)
const enrollments = ref<any[]>([])

const formatDate = (date: string) => {
  if(!date) return '';
  return new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const activeEnrollment = computed(() => {
  if (enrollments.value.length === 0) return null;
  // Giả sử lấy khóa học đang học dở dang nhất hoặc đăng ký gần nhất 
  // (hiện tại lấy phần tử đầu tiên)
  return enrollments.value[0];
})

const secondaryEnrollments = computed(() => {
  if (enrollments.value.length <= 1) return [];
  return enrollments.value.slice(1);
})

onMounted(async () => { 
  loading.value = true; 
  enrollments.value = await courseStore.fetchEnrollments(); 
  loading.value = false;
})
</script>
