<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên</p>
        <h2>Doanh thu</h2>
        <p>Theo dõi doanh thu, số đơn hàng và hiệu suất kinh doanh theo từng khóa học.</p>
      </div>
      <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Xem khóa học</NuxtLink>
    </header>

    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent>
          <input v-model="search" class="crud-search" type="text" placeholder="Tìm khóa học...">
        </form>
      </div>
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Khóa học</th>
              <th>Học viên</th>
              <th>Bài học</th>
              <th>Giá</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="crud-empty">Đang tải...</td>
            </tr>
            <tr v-else-if="filteredCourses.length === 0">
              <td colspan="7" class="crud-empty">Chưa có khóa học.</td>
            </tr>
            <tr v-for="(course, idx) in filteredCourses" :key="course.id">
              <td>{{ idx + 1 }}</td>
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                    <span v-else>📘</span>
                  </div>
                  <div><strong>{{ course.title }}</strong></div>
                </div>
              </td>
              <td>{{ course.enrollments_count || 0 }}</td>
              <td>{{ course.lessons_count || 0 }}</td>
              <td>{{ formatPrice(course.price) }}</td>
              <td><span class="crud-badge" :class="statusClass(course.status)">{{ statusLabel(course.status) }}</span></td>
              <td>
                <div class="crud-actions">
                  <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="action-btn is-view">Chi tiết</NuxtLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])
const search = ref('')

const formatPrice = (price: number) => price <= 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)

const statusLabel = (status: string) => {
  const map: Record<string, string> = { published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }
  return map[status] || status
}

const statusClass = (s: string) => ({ published: 'role-instructor', pending_review: 'role-student', draft: 'role-admin', rejected: 'role-admin' }[s] || 'role-admin')

const filteredCourses = computed(() => {
  if (!search.value.trim()) return courses.value
  const q = search.value.toLowerCase()
  return courses.value.filter(c => c.title?.toLowerCase().includes(q))
})

onMounted(async () => {
  try {
    courses.value = await courseStore.fetchMyCourses()
  } finally {
    loading.value = false
  }
})
</script>
