<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Quản lý khóa học</h1>
        <p class="text-sm text-gray-500 mt-1">Danh sách khóa học của giảng viên</p>
      </div>
      <NuxtLink to="/courses/create" class="btn-primary">Tạo khóa học</NuxtLink>
    </div>

    <div v-if="loading" class="card p-6 text-sm text-gray-500">Đang tải...</div>
    <div v-else-if="courses.length === 0" class="card p-8 text-sm text-gray-500 text-center">
      Chưa có khóa học nào.
    </div>
    <div v-else class="space-y-3">
      <div v-for="course in courses" :key="course.id" class="card p-5 flex flex-col md:flex-row gap-4 md:items-center">
        <div class="w-full md:w-40 h-24 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
          <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-900">{{ course.title }}</p>
          <p class="text-xs text-gray-500 mt-1">
            {{ course.lessons_count || 0 }} bài học · {{ course.enrollments_count || 0 }} học viên
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <NuxtLink :to="`/courses/${course.id}/edit`" class="btn-ghost text-xs px-3 py-1.5">Sửa</NuxtLink>
          <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="btn-ghost text-xs px-3 py-1.5">Curriculum</NuxtLink>
          <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="btn-ghost text-xs px-3 py-1.5">Học viên</NuxtLink>
          <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="btn-ghost text-xs px-3 py-1.5">Doanh thu</NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])

onMounted(async () => {
  try {
    courses.value = await courseStore.fetchMyCourses()
  } finally {
    loading.value = false
  }
})
</script>

