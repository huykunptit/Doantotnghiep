<template>
  <div>
    <NuxtLayout name="instructor">
    <div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Giảng viên</h1>
        <p class="text-sm text-gray-500 mt-1">Tổng quan khóa học và thống kê</p>
      </div>
      <NuxtLink to="/courses/create" class="btn-primary flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        Tạo khóa học mới
      </NuxtLink>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="card p-5">
        <p class="text-sm text-gray-500">Tổng khóa học</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.total_courses || 0 }}</p>
      </div>
      <div class="card p-5">
        <p class="text-sm text-gray-500">Tổng học viên</p>
        <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.total_students || 0 }}</p>
      </div>
      <div class="card p-5">
        <p class="text-sm text-gray-500">Doanh thu</p>
        <p class="text-2xl font-bold text-primary mt-1">{{ formatPrice(stats.total_revenue || 0) }}</p>
      </div>
    </div>

    <!-- Course List -->
    <div class="space-y-3">
      <div v-if="loading" v-for="i in 3" :key="i" class="card p-5 animate-pulse flex gap-4">
        <div class="w-24 h-16 bg-gray-200 rounded-lg flex-shrink-0"></div>
        <div class="flex-1 space-y-2"><div class="h-4 bg-gray-200 rounded w-1/2"></div><div class="h-3 bg-gray-200 rounded w-1/3"></div></div>
      </div>

      <div v-if="!loading && courses.length === 0" class="card p-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
        <h3 class="text-base font-semibold text-gray-900 mb-1">Chưa có khóa học nào</h3>
        <p class="text-sm text-gray-500 mb-4">Bắt đầu tạo khóa học đầu tiên của bạn!</p>
        <NuxtLink to="/courses/create" class="btn-primary">Tạo khóa học</NuxtLink>
      </div>

      <div v-for="course in courses" :key="course.id" class="card p-5 flex flex-col sm:flex-row gap-4 items-start">
        <div class="w-full sm:w-32 h-20 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden">
          <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-start gap-3">
            <div class="flex-1">
              <h3 class="text-sm font-semibold text-gray-900">{{ course.title }}</h3>
              <p class="text-xs text-gray-500 mt-1">{{ course.lessons_count || 0 }} bài học · {{ course.enrollments_count || 0 }} học viên</p>
            </div>
            <span class="badge flex-shrink-0" :class="statusBadge(course.status)">{{ statusLabel(course.status) }}</span>
          </div>
          <div v-if="course.status === 'rejected' && course.reject_reason" class="mt-2 text-xs text-red-600 bg-red-50 rounded-lg p-2">
            Từ chối: {{ course.reject_reason }}
          </div>
          <div class="flex gap-2 mt-3 flex-wrap">
            <NuxtLink :to="`/courses/${course.id}/edit`" class="btn-ghost text-xs px-3 py-1.5">Chỉnh sửa</NuxtLink>
            <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="btn-ghost text-xs px-3 py-1.5">Curriculum</NuxtLink>
            <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="btn-ghost text-xs px-3 py-1.5">Học viên</NuxtLink>
            <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="btn-ghost text-xs px-3 py-1.5">Doanh thu</NuxtLink>
            <button
              v-if="course.status === 'draft' || course.status === 'rejected'"
              @click="publishCourse(course)"
              class="btn-secondary text-xs px-3 py-1.5"
            >
              Gửi duyệt
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const auth = useAuthStore()
const courseStore = useCourseStore()

const courses = ref<any[]>([])
const stats = ref<any>({})
const loading = ref(true)

function statusBadge(s: string): string {
  const m: Record<string, string> = { published: 'bg-primary-light text-primary-dark', draft: 'bg-gray-100 text-gray-600', pending_review: 'bg-amber-100 text-amber-700', rejected: 'bg-red-100 text-red-700' }
  return m[s] || 'bg-gray-100 text-gray-600'
}
function statusLabel(s: string): string {
  const m: Record<string, string> = { published: 'Đã xuất bản', draft: 'Nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }
  return m[s] || s
}
function formatPrice(v: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v)
}

async function publishCourse(course: any) {
  try {
    await courseStore.publishCourse(course.id)
    course.status = 'pending_review'
  } catch (e: any) {
    alert(e?.data?.message || 'Thất bại')
  }
}

onMounted(async () => {
  try {
    const [myCourses, statsData] = await Promise.all([
      courseStore.fetchMyCourses(),
      useApi<any>('/instructor/stats', { token: auth.token }).catch(() => ({})),
    ])
    courses.value = myCourses || []
    stats.value = statsData
  } finally {
    loading.value = false
  }
})
</script>
