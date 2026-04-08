<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Khóa học của tôi</h1>
        <p class="text-sm text-gray-500 mt-1">Theo dõi tiến độ học tập của bạn</p>
      </div>
      <NuxtLink to="/courses" class="btn-secondary text-sm">Khám phá thêm</NuxtLink>
    </div>

    <div v-if="loading" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <div v-for="i in 4" :key="i" class="card animate-pulse">
        <div class="h-40 bg-gray-200 rounded-t-2xl"></div>
        <div class="p-4 space-y-3">
          <div class="h-4 bg-gray-200 rounded w-3/4"></div>
          <div class="h-2 bg-gray-200 rounded w-full"></div>
          <div class="h-3 bg-gray-200 rounded w-1/3"></div>
        </div>
      </div>
    </div>

    <div v-else-if="enrollments.length === 0" class="card p-16 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Chưa có khóa học nào</h3>
      <p class="text-sm text-gray-500 mb-4">Bạn chưa đăng ký khóa học nào. Hãy khám phá ngay!</p>
      <NuxtLink to="/courses" class="btn-primary">Khám phá khóa học</NuxtLink>
    </div>

    <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <NuxtLink
        v-for="e in enrollments"
        :key="e.id"
        :to="`/learn/${e.course_id}/${e.course?.id || e.course_id}`"
        class="card overflow-hidden group"
      >
        <div class="relative h-40 bg-gray-100 overflow-hidden">
          <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" :alt="e.course?.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
          <div v-else class="w-full h-full flex items-center justify-center bg-primary-light">
            <span class="text-4xl font-bold text-primary/30">{{ e.course?.title?.charAt(0) }}</span>
          </div>
          <div v-if="e.progress >= 100" class="absolute top-3 right-3 bg-primary text-white text-xs font-bold px-2 py-1 rounded-lg">✓ Hoàn thành</div>
        </div>
        <div class="p-4">
          <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mb-3 group-hover:text-primary transition-colors">{{ e.course?.title }}</h3>
          <div class="flex items-center gap-2 mb-2">
            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" :class="e.progress >= 100 ? 'bg-primary' : 'bg-primary/70'" :style="{ width: e.progress + '%' }"></div>
            </div>
            <span class="text-xs font-bold" :class="e.progress >= 100 ? 'text-primary' : 'text-gray-500'">{{ Math.round(e.progress) }}%</span>
          </div>
          <p class="text-xs text-gray-400">Đăng ký: {{ formatDate(e.enrolled_at) }}</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const courseStore = useCourseStore()
const loading = ref(true)
const enrollments = ref(courseStore.enrollments)

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('vi-VN')
}

onMounted(async () => {
  loading.value = true
  enrollments.value = await courseStore.fetchEnrollments()
  loading.value = false
})
</script>
