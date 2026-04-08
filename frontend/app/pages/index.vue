<template>
  <div>
    <!-- Hero -->
    <section class="bg-primary-50 py-16 lg:py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 leading-tight mb-6">
              Học bất kỳ điều gì,<br />
              <span class="text-primary">bất kỳ lúc nào</span>
            </h1>
            <p class="text-lg text-gray-600 mb-8 max-w-lg">
              Tham gia cùng hàng nghìn học viên và giảng viên. Khóa học chất lượng từ cơ bản đến nâng cao.
            </p>
            <div class="flex flex-wrap gap-3">
              <NuxtLink to="/courses" class="btn-primary text-base px-8 py-3">Khám phá khóa học</NuxtLink>
              <NuxtLink to="/register" class="btn-secondary text-base px-8 py-3">Đăng ký miễn phí</NuxtLink>
            </div>
          </div>
          <div class="hidden lg:flex justify-center">
            <div class="relative w-80 h-80">
              <div class="absolute inset-0 bg-primary/10 rounded-full"></div>
              <div class="absolute top-8 left-8 right-8 bottom-8 bg-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center">
                <svg class="w-16 h-16 text-primary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-semibold text-gray-900">Bắt đầu học ngay</p>
                <p class="text-xs text-gray-500 mt-1">Video chất lượng cao</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <section class="py-12 border-b border-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
          <div>
            <p class="text-3xl font-bold text-primary">1,000+</p>
            <p class="text-sm text-gray-500 mt-1">Học viên</p>
          </div>
          <div>
            <p class="text-3xl font-bold text-primary">200+</p>
            <p class="text-sm text-gray-500 mt-1">Khóa học</p>
          </div>
          <div>
            <p class="text-3xl font-bold text-primary">50+</p>
            <p class="text-sm text-gray-500 mt-1">Giảng viên</p>
          </div>
          <div>
            <p class="text-3xl font-bold text-primary">4.8</p>
            <p class="text-sm text-gray-500 mt-1">Đánh giá trung bình</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Categories -->
    <section class="py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-2xl font-bold text-gray-900 mb-3">Danh mục khóa học</h2>
          <p class="text-gray-500">Khám phá các lĩnh vực kiến thức đa dạng</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <NuxtLink
            v-for="cat in categories"
            :key="cat.id"
            :to="`/courses?category=${cat.id}`"
            class="card p-6 text-center hover:border-primary transition-colors group"
          >
            <div class="w-12 h-12 bg-primary-light rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-primary/20 transition-colors">
              <span class="text-2xl">{{ categoryIcons[cat.icon] || '📚' }}</span>
            </div>
            <h3 class="text-sm font-semibold text-gray-900">{{ cat.name }}</h3>
            <p class="text-xs text-gray-500 mt-1">{{ cat.children?.length || 0 }} lĩnh vực con</p>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- Featured Courses -->
    <section class="py-16 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">Khóa học nổi bật</h2>
            <p class="text-gray-500 text-sm mt-1">Được nhiều học viên lựa chọn</p>
          </div>
          <NuxtLink to="/courses" class="btn-secondary text-sm">Xem tất cả</NuxtLink>
        </div>
        <div v-if="coursesLoading" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 4" :key="i" class="card animate-pulse">
            <div class="h-40 bg-gray-200 rounded-t-xl"></div>
            <div class="p-4 space-y-3">
              <div class="h-4 bg-gray-200 rounded w-3/4"></div>
              <div class="h-3 bg-gray-200 rounded w-1/2"></div>
              <div class="h-4 bg-gray-200 rounded w-1/3"></div>
            </div>
          </div>
        </div>
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <NuxtLink
            v-for="course in featuredCourses"
            :key="course.id"
            :to="`/courses/${course.id}`"
            class="card overflow-hidden group"
          >
            <div class="relative h-40 bg-gray-100 overflow-hidden">
              <img
                v-if="course.thumbnail"
                :src="course.thumbnail"
                :alt="course.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              />
              <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
              </div>
            </div>
            <div class="p-4">
              <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mb-2">{{ course.title }}</h3>
              <p class="text-xs text-gray-500 mb-3">{{ course.instructor?.name }}</p>
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-primary">
                  {{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}
                </span>
                <span class="text-xs text-gray-400">{{ course.lessons_count || 0 }} bài học</span>
              </div>
            </div>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Bạn là chuyên gia? Hãy chia sẻ kiến thức!</h2>
        <p class="text-gray-500 mb-8 max-w-lg mx-auto">Trở thành giảng viên trên EduPress và tiếp cận hàng nghìn học viên.</p>
        <NuxtLink to="/register" class="btn-primary text-base px-8 py-3">Bắt đầu dạy ngay</NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
const courseStore = useCourseStore()

const categories = ref<any[]>([])
const featuredCourses = ref<any[]>([])
const coursesLoading = ref(true)

const categoryIcons: Record<string, string> = {
  code: '💻',
  paintbrush: '🎨',
  briefcase: '💼',
  globe: '🌍',
}

function formatPrice(price: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

onMounted(async () => {
  try {
    const [catData, courseData] = await Promise.all([
      useApi<any[]>('/categories').catch(() => []),
      courseStore.fetchCourses({ per_page: 8 }),
    ])
    categories.value = catData
    featuredCourses.value = courseData?.data || []
  } finally {
    coursesLoading.value = false
  }
})
</script>
