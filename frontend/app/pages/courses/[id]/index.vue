<template>
  <main class="min-h-screen bg-surface">
    <!-- Course Hero / Introduction -->
    <section class="bg-surface-lowest pt-12 pb-24 border-b border-surface-dim relative overflow-hidden">
      <!-- Decorative background -->
      <div class="absolute right-0 top-0 w-1/2 h-full bg-primary/5 -skew-x-12 translate-x-1/2 pointer-events-none"></div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <nav class="flex items-center gap-2 text-sm font-semibold text-outline-variant mb-8 overflow-x-auto whitespace-nowrap">
          <NuxtLink to="/courses" class="hover:text-primary transition-colors">Tất cả khóa học</NuxtLink>
          <span class="material-symbols-outlined text-sm">chevron_right</span>
          <NuxtLink :to="`/categories/${course?.category_id || course?.category?.id}`" class="hover:text-primary transition-colors">{{ course?.category?.name || 'Chuyên mục' }}</NuxtLink>
          <span class="material-symbols-outlined text-sm">chevron_right</span>
          <span class="text-on-surface-variant truncate">{{ course?.title || 'Đang tải...' }}</span>
        </nav>

        <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-12 gap-12">
          <div class="lg:col-span-8 space-y-6">
            <div class="h-12 bg-surface-high animate-pulse rounded-xl w-3/4"></div>
            <div class="h-24 bg-surface-high animate-pulse rounded-2xl w-full"></div>
            <div class="h-6 bg-surface-high animate-pulse rounded-lg w-1/2"></div>
          </div>
        </div>

        <div v-else-if="course" class="grid grid-cols-1 lg:grid-cols-12 gap-12">
          <div class="lg:col-span-8 space-y-8">
            <div class="space-y-6">
              <div class="flex flex-wrap gap-3">
                <StatusBadge :value="course.status || 'published'" />
                <UiBadge v-if="course.category">{{ typeof course.category === 'object' ? course.category.name : course.category }}</UiBadge>
              </div>
              <h1 class="text-4xl md:text-5xl font-headline font-bold text-on-surface tracking-tight leading-tight">{{ course.title }}</h1>
              <p class="text-lg text-on-surface-variant leading-relaxed max-w-3xl">{{ course.description }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-8 text-sm font-bold text-on-surface-variant">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                  <span class="material-symbols-outlined text-[20px]">person_check</span>
                </div>
                <div>
                  <p class="text-[10px] text-outline uppercase tracking-widest leading-none mb-1">Giảng viên</p>
                  <p class="text-on-surface">{{ course.instructor?.name || 'EduPress EduPress' }}</p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center text-secondary">
                  <span class="material-symbols-outlined text-[20px]">play_circle</span>
                </div>
                <div>
                  <p class="text-[10px] text-outline uppercase tracking-widest leading-none mb-1">Nội dung</p>
                  <p class="text-on-surface">{{ lessons.length }} bài giảng</p>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-600">
                  <span class="material-symbols-outlined text-[20px]">group</span>
                </div>
                <div>
                  <p class="text-[10px] text-outline uppercase tracking-widest leading-none mb-1">Số lượng</p>
                  <p class="text-on-surface">{{ course.enrollments_count || 0 }} học viên</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Content Grid -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 relative z-10">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        
        <!-- Left Column: Content -->
        <div class="lg:col-span-8 space-y-12">
          
          <!-- Curriculum -->
          <section id="curriculum">
            <div class="flex items-center justify-between mb-8">
              <h2 class="text-2xl font-headline font-bold text-on-surface flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">menu_book</span> Giáo trình Khóa học
              </h2>
              <span class="text-sm font-bold text-outline-variant tracking-widest uppercase">{{ totalDuration }}</span>
            </div>

            <div v-if="loading" class="space-y-4">
              <div v-for="i in 5" :key="i" class="h-20 bg-surface-lowest rounded-2xl animate-pulse"></div>
            </div>

            <div v-else class="space-y-3">
              <div 
                v-for="(lesson, index) in lessons" 
                :key="lesson.id" 
                class="flex items-center gap-6 rounded-[1.25rem] bg-surface-lowest border border-surface-dim p-5 group hover:border-primary/30 transition-all duration-300"
              >
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-surface-low text-base font-bold text-on-surface-variant group-hover:bg-primary group-hover:text-white transition-all">
                  {{ index + 1 }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-bold text-on-surface text-lg mb-1">{{ lesson.title }}</p>
                  <div class="flex items-center gap-4 text-xs font-semibold text-outline tracking-wider uppercase">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> {{ formatDuration(lesson.duration || 0) }}</span>
                    <span v-if="lesson.is_preview" class="text-emerald-600 flex items-center gap-1">
                      <span class="material-symbols-outlined text-[14px]">visibility</span> Xem thử miễn phí
                    </span>
                  </div>
                </div>
                
                <div v-if="lesson.is_preview" class="hidden sm:block">
                  <button
                    class="px-4 py-2 rounded-lg bg-primary/10 text-primary font-bold text-xs hover:bg-primary hover:text-white transition-all"
                    @click="goToPreviewLesson(lesson.id)"
                  >
                    Xem trước
                  </button>
                </div>
                <div v-else class="h-8 w-8 rounded-full flex items-center justify-center text-outline">
                  <span class="material-symbols-outlined text-[20px]">lock_outline</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Reviews -->
          <section id="reviews">
            <div class="flex items-center justify-between mb-8">
              <h2 class="text-2xl font-headline font-bold text-on-surface flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-500">grade</span> Đánh giá từ Học viên
              </h2>
              <div v-if="course?.avg_rating" class="flex items-baseline gap-1">
                <span class="text-3xl font-bold text-on-surface">{{ course.avg_rating }}</span>
                <span class="text-sm font-bold text-outline-variant">/ 5</span>
              </div>
            </div>
            <div v-if="isEnrolled && !course?.has_reviewed" class="mb-12">
              <ReviewForm :course-id="courseId" @success="onReviewSuccess" />
            </div>

            <div v-if="reviews.length === 0" class="py-12 bg-surface-lowest rounded-[2.5rem] border border-dashed border-slate-300 text-center">
              <span class="material-symbols-outlined text-4xl text-outline-variant mb-3">chat_bubble_outline</span>
              <p class="font-bold text-on-surface">Chưa có đánh giá nào</p>
              <p class="text-sm text-outline">Hãy là người đầu tiên để lại cảm nghĩ sau khi hoàn thành khóa học nhé.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div v-for="review in reviews" :key="review.id" class="p-6 rounded-[2rem] bg-surface-lowest border border-surface-dim shadow-sm">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center gap-3 text-on-surface font-bold">
                    <div class="w-8 h-8 rounded-full bg-surface-low flex items-center justify-center text-xs">
                       {{ review.user?.name?.charAt(0) || 'U' }}
                    </div>
                    <span>{{ review.user?.name || 'Học viên EduPress' }}</span>
                  </div>
                  <div class="flex items-center gap-1 text-amber-500">
                    <span class="material-symbols-outlined text-[16px]">grade</span>
                    <span class="font-bold text-sm">{{ review.rating }}</span>
                  </div>
                </div>
                <p class="text-sm text-on-surface-variant leading-relaxed">{{ review.comment || 'Tuyệt vời!' }}</p>
              </div>
            </div>
          </section>
        </div>

        <!-- Right Column: Buy Box -->
        <aside v-if="course" class="lg:col-span-4 relative">
          <div class="lg:sticky lg:top-24 space-y-6 z-20">
            <div class="bg-surface-lowest rounded-[2.5rem] p-6 shadow-ambient border border-surface-dim">
              <!-- Thumbnail Wrapper -->
              <div class="aspect-video rounded-3xl overflow-hidden bg-surface-low border border-surface-dim mb-8 relative">
                <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="h-full w-full object-cover">
                <div v-else class="flex h-full items-center justify-center text-6xl text-outline/20">📘</div>
              </div>

              <!-- Content and CTA -->
              <div class="space-y-6 px-2 relative">
                <div class="flex items-baseline gap-2">
                  <p class="text-4xl font-headline font-extrabold text-on-surface">
                    {{ course?.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}
                  </p>
                  <p v-if="course?.price > 0" class="text-base text-outline line-through font-medium">{{ formatPrice(course.price * 1.5) }}</p>
                </div>

                <div class="space-y-3">
                  <NuxtLink
                    v-if="isEnrolled"
                    :to="`/learn/${course.id}`"
                    class="flex items-center justify-center gap-2 w-full min-h-[58px] px-6 py-3 rounded-2xl text-base font-extrabold text-white bg-gradient-to-r from-primary to-indigo-600 shadow-xl shadow-primary/30 hover:shadow-2xl hover:shadow-primary/50 hover:scale-[1.02] active:scale-[0.98] transition-all duration-300 cursor-pointer select-none"
                  >
                    <span class="material-symbols-outlined text-[20px]">school</span>
                    Tiếp tục học ngay
                  </NuxtLink>

                  <template v-else-if="auth.isLoggedIn">
                    <button
                      type="button"
                      :disabled="enrolling"
                      class="flex items-center justify-center gap-2 w-full min-h-[58px] px-6 py-3 rounded-2xl text-base font-extrabold text-white bg-gradient-to-r from-primary to-indigo-600 shadow-xl shadow-primary/30 hover:shadow-2xl hover:shadow-primary/50 hover:scale-[1.02] active:scale-[0.98] transition-all duration-300 cursor-pointer select-none disabled:opacity-60 disabled:cursor-not-allowed"
                      @click="handleEnrollment"
                    >
                      <span v-if="enrolling" class="material-symbols-outlined animate-spin text-[20px]">progress_activity</span>
                      <span v-else class="material-symbols-outlined text-[20px]">payments</span>
                      {{ course?.price > 0 ? 'Thanh toán ngay' : 'Đăng ký miễn phí' }}
                    </button>

                    <NuxtLink
                      v-if="previewLessonId"
                      :to="`/learn/${course.id}/${previewLessonId}`"
                      class="flex items-center justify-center gap-2 w-full min-h-[52px] px-6 py-3 rounded-2xl text-sm font-bold text-primary bg-white border-2 border-primary hover:bg-primary hover:text-white hover:shadow-lg hover:shadow-primary/20 hover:scale-[1.01] active:scale-[0.99] transition-all duration-200 cursor-pointer select-none"
                    >
                      <span class="material-symbols-outlined text-[20px]">visibility</span>
                      Xem thử miễn phí
                    </NuxtLink>

                    <div class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-50 border border-emerald-100 px-4 py-3">
                      <span class="material-symbols-outlined text-emerald-500 text-[18px]">verified_user</span>
                      <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">
                        Cam kết hoàn trả trong 30 ngày
                      </p>
                    </div>
                  </template>

                  <template v-else>
                    <NuxtLink
                      to="/login"
                      class="flex items-center justify-center gap-2 w-full min-h-[58px] px-6 py-3 rounded-2xl text-base font-extrabold text-white bg-gradient-to-r from-primary to-indigo-600 shadow-xl shadow-primary/30 hover:shadow-2xl hover:shadow-primary/50 hover:scale-[1.02] active:scale-[0.98] transition-all duration-300 cursor-pointer select-none"
                    >
                      <span class="material-symbols-outlined text-[20px]">login</span>
                      Đăng nhập để học
                    </NuxtLink>
                    <NuxtLink
                      v-if="previewLessonId"
                      :to="`/learn/${course.id}/${previewLessonId}`"
                      class="flex items-center justify-center gap-2 w-full min-h-[52px] px-6 py-3 rounded-2xl text-sm font-bold text-primary bg-white border-2 border-primary hover:bg-primary hover:text-white hover:shadow-lg hover:scale-[1.01] transition-all duration-200 cursor-pointer select-none"
                    >
                      <span class="material-symbols-outlined text-[20px]">visibility</span>
                      Xem thử miễn phí
                    </NuxtLink>
                  </template>
                </div>

                <div class="pt-6 border-t border-slate-100 space-y-4 text-sm font-semibold text-on-surface-variant">
                  <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">assignment_turned_in</span>
                    <span>Học và Thực hành Thực tế</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">workspace_premium</span>
                    <span>Chứng nhận Hoàn thành</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">all_inclusive</span>
                    <span>Truy cập Trọn đời</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Promotion Card -->
            <div class="bg-gradient-to-br from-primary to-indigo-700 rounded-[2rem] p-8 text-white shadow-lg relative overflow-hidden group">
               <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
               <p class="text-[11px] font-bold uppercase tracking-widest opacity-80 mb-2">Quà tặng đặc biệt</p>
               <h4 class="text-xl font-bold mb-4">Nhận trọn bộ kho học liệu Premium</h4>
               <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold text-white hover:bg-white/20 transition-colors cursor-pointer">Tìm hiểu thêm</button>
            </div>
          </div>
        </aside>

      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()

const course = ref<any>(null)
const lessons = ref<any[]>([])
const reviews = ref<any[]>([])
const loading = ref(true)
const enrolling = ref(false)

const courseId = Number(route.params.id)
const isEnrolled = computed(() => course.value?.is_enrolled || courseStore.isEnrolled(courseId))
const firstLessonId = computed(() => lessons.value[0]?.id || 0)
const previewLessonId = computed(() => lessons.value.find((lesson: any) => lesson.is_preview)?.id || 0)

const totalDuration = computed(() => {
  const secs = lessons.value.reduce((sum: number, l: any) => sum + (l.duration || 0), 0)
  const hours = Math.floor(secs / 3600)
  const mins = Math.floor((secs % 3600) / 60)
  return hours > 0 ? `${hours}h ${mins}m tổng thời lượng` : `${mins} phút tổng thời lượng`
})

const formatPrice = (price: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0)
const formatDuration = (secs: number) => {
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

async function handleEnrollment() {
  if (enrolling.value) return
  enrolling.value = true
  try {
    const res = await courseStore.createOrder(courseId)
    if (res.payment_url) {
      window.location.href = res.payment_url
    } else if (res.enrolled) {
      // Immediate enrollment for free courses
      await courseStore.fetchEnrollments()
      router.push(`/learn/${courseId}`)
    } else {
      router.push(`/checkout/${courseId}`)
    }
  } catch (e) {
    console.error('Enrollment error:', e)
  } finally {
    enrolling.value = false
  }
}

function goToPreviewLesson(lessonId?: number) {
  if (!lessonId) return
  router.push(`/learn/${courseId}/${lessonId}`)
}

function onReviewSuccess(newReview: any) {
  reviews.value.unshift(newReview)
  if (course.value) {
    course.value.has_reviewed = true
  }
}

onMounted(async () => {
  try {
    const [c, l] = await Promise.all([
      courseStore.fetchCourse(courseId), 
      courseStore.fetchLessons(courseId).catch(() => [])
    ])
    course.value = c
    lessons.value = l || []
    reviews.value = c?.latest_reviews || []
    if (auth.isLoggedIn) await courseStore.fetchEnrollments().catch(() => {})
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
html { scroll-behavior: smooth; }
</style>
