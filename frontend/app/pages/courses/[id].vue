<template>
  <div class="bg-gray-50 min-h-screen">
    <!-- Hero -->
    <div class="bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div v-if="loading" class="animate-pulse space-y-4">
          <div class="h-6 bg-gray-200 rounded w-1/2"></div>
          <div class="h-4 bg-gray-200 rounded w-3/4"></div>
          <div class="h-4 bg-gray-200 rounded w-1/4"></div>
        </div>
        <div v-else-if="course" class="grid lg:grid-cols-3 gap-8">
          <div class="lg:col-span-2">
            <div class="flex items-center gap-2 mb-3">
              <span class="badge-success" v-if="course.status === 'published'">Đang mở</span>
              <span class="badge-gray" v-else>{{ course.status }}</span>
              <span v-if="course.category" class="badge-gray">{{ course.category?.name || course.category }}</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ course.title }}</h1>
            <p class="text-gray-600 mb-6">{{ course.description }}</p>

            <div class="flex items-center gap-4 text-sm text-gray-500">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-light flex items-center justify-center">
                  <span class="text-xs font-semibold text-primary">{{ course.instructor?.name?.charAt(0) }}</span>
                </div>
                <span class="font-medium text-gray-900">{{ course.instructor?.name }}</span>
              </div>
              <span>{{ course.lessons_count || lessons.length }} bài học</span>
              <span>{{ course.enrollments_count || 0 }} học viên</span>
            </div>
          </div>

          <!-- Sidebar Card -->
          <div class="card p-6 h-fit lg:sticky lg:top-24">
            <div class="relative h-44 bg-gray-100 rounded-lg overflow-hidden mb-4">
              <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
            </div>
            <p class="text-3xl font-bold text-gray-900 mb-4">
              {{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}
            </p>
            <NuxtLink
              v-if="isEnrolled"
              :to="`/learn/${course.id}/${firstLessonId}`"
              class="btn-primary w-full mb-3"
            >
              Tiếp tục học
            </NuxtLink>
            <NuxtLink
              v-else-if="auth.isLoggedIn"
              :to="`/checkout/${course.id}`"
              class="btn-primary w-full mb-3"
            >
              {{ course.price > 0 ? 'Mua khóa học' : 'Đăng ký học miễn phí' }}
            </NuxtLink>
            <NuxtLink v-else to="/login" class="btn-primary w-full mb-3">Đăng nhập để mua</NuxtLink>

            <ul class="space-y-2 text-sm text-gray-600">
              <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ totalDuration }} tổng thời lượng
              </li>
              <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Học trọn đời, không giới hạn
              </li>
              <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                Học trên mọi thiết bị
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Lessons List + Reviews -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      <div class="lg:w-2/3 space-y-12">
        <!-- Lessons -->
        <div>
          <h2 class="text-xl font-bold text-gray-900 mb-6">Nội dung khóa học ({{ lessons.length }} bài)</h2>
          <div class="space-y-2">
            <div
              v-for="(lesson, idx) in lessons"
              :key="lesson.id"
              class="card p-4 flex items-center gap-4"
            >
              <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-semibold"
                :class="lesson.is_preview ? 'bg-primary-light text-primary' : 'bg-gray-100 text-gray-500'">
                {{ idx + 1 }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ lesson.title }}</p>
                <p class="text-xs text-gray-500">{{ formatDuration(lesson.duration) }}</p>
              </div>
              <span v-if="lesson.is_preview" class="badge-success text-xs">Xem thử</span>
              <svg v-else class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </div>
          </div>
        </div>

        <!-- Reviews Section -->
        <div>
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="text-xl font-bold text-gray-900">Đánh giá từ học viên</h2>
              <div v-if="course?.avg_rating > 0" class="flex items-center gap-2 mt-1">
                <div class="flex items-center gap-0.5">
                  <svg v-for="s in 5" :key="s" class="w-4 h-4" :class="s <= Math.round(course.avg_rating) ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                </div>
                <span class="text-sm font-bold text-gray-900">{{ course.avg_rating }}</span>
                <span class="text-xs text-gray-500">({{ course.reviews_count || 0 }} đánh giá)</span>
              </div>
            </div>
          </div>

          <!-- Write review form -->
          <div v-if="isEnrolled && !hasReviewed" class="card p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Viết đánh giá của bạn</h3>
            <div class="flex gap-1 mb-3">
              <button v-for="s in 5" :key="s" @click="reviewForm.rating = s" class="focus:outline-none">
                <svg class="w-7 h-7 transition-colors" :class="s <= reviewForm.rating ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
              </button>
            </div>
            <textarea v-model="reviewForm.comment" rows="3" class="input mb-3" placeholder="Chia sẻ trải nghiệm của bạn..."></textarea>
            <div class="flex items-center gap-3">
              <button @click="submitReview" :disabled="reviewSubmitting || reviewForm.rating === 0" class="btn-primary text-sm">
                {{ reviewSubmitting ? 'Đang gửi...' : 'Gửi đánh giá' }}
              </button>
              <span v-if="reviewError" class="text-xs text-red-600">{{ reviewError }}</span>
              <span v-if="reviewSuccess" class="text-xs text-primary font-semibold">{{ reviewSuccess }}</span>
            </div>
          </div>

          <!-- Reviews list -->
          <div v-if="reviews.length > 0" class="space-y-4">
            <div v-for="review in reviews" :key="review.id" class="card p-5">
              <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-primary-light flex items-center justify-center overflow-hidden">
                  <img v-if="review.user?.avatar" :src="review.user.avatar" class="w-full h-full object-cover" />
                  <span v-else class="text-xs font-bold text-primary">{{ review.user?.name?.charAt(0) }}</span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ review.user?.name }}</p>
                  <div class="flex items-center gap-1">
                    <svg v-for="s in 5" :key="s" class="w-3.5 h-3.5" :class="s <= review.rating ? 'text-amber-400' : 'text-gray-200'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                  </div>
                </div>
              </div>
              <p v-if="review.comment" class="text-sm text-gray-600 leading-relaxed">{{ review.comment }}</p>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 text-center py-8">Chưa có đánh giá nào.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const auth = useAuthStore()
const courseStore = useCourseStore()

const course = ref<any>(null)
const lessons = ref<any[]>([])
const reviews = ref<any[]>([])
const loading = ref(true)
const hasReviewed = ref(false)
const reviewSubmitting = ref(false)
const reviewError = ref('')
const reviewSuccess = ref('')
const reviewForm = reactive({ rating: 0, comment: '' })

const courseId = Number(route.params.id)
const isEnrolled = computed(() => course.value?.is_enrolled || courseStore.isEnrolled(courseId))
const firstLessonId = computed(() => lessons.value[0]?.id || 0)

const totalDuration = computed(() => {
  const secs = lessons.value.reduce((sum: number, l: any) => sum + (l.duration || 0), 0)
  const hours = Math.floor(secs / 3600)
  const mins = Math.floor((secs % 3600) / 60)
  if (hours > 0) return `${hours}h ${mins}m`
  return `${mins} phút`
})

function formatPrice(price: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

function formatDuration(secs: number): string {
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

async function submitReview() {
  if (reviewForm.rating === 0) return
  reviewSubmitting.value = true
  reviewError.value = ''
  reviewSuccess.value = ''
  try {
    const data = await useApi<{ review: any }>(`/courses/${courseId}/reviews`, {
      method: 'POST',
      body: { rating: reviewForm.rating, comment: reviewForm.comment || null },
      token: auth.token,
    })
    reviews.value.unshift(data.review)
    hasReviewed.value = true
    reviewSuccess.value = 'Cảm ơn bạn đã đánh giá!'
  } catch (e: any) {
    reviewError.value = e?.data?.message || 'Không thể gửi đánh giá.'
  } finally {
    reviewSubmitting.value = false
  }
}

onMounted(async () => {
  try {
    const [c, l] = await Promise.all([
      courseStore.fetchCourse(courseId),
      useApi<any[]>(`/courses/${courseId}/lessons`).catch(() => []),
    ])
    course.value = c
    lessons.value = l || []
    reviews.value = c?.latest_reviews || []

    // Check if current user already reviewed
    if (auth.isLoggedIn && auth.user) {
      hasReviewed.value = reviews.value.some((r: any) => r.user_id === auth.user?.id)
      await courseStore.fetchEnrollments().catch(() => {})
    }
  } finally {
    loading.value = false
  }
})
</script>
