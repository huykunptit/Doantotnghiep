<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])
const search = ref('')
const selectedStatus = ref('all')

const statusOptions = [
  { value: 'all', label: 'Tất cả' },
  { value: 'published', label: 'Đã xuất bản' },
  { value: 'pending_review', label: 'Chờ duyệt' },
  { value: 'draft', label: 'Bản nháp' },
  { value: 'rejected', label: 'Bị từ chối' },
]

const filteredCourses = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  return courses.value.filter((course) => {
    const matchesStatus = selectedStatus.value === 'all' || course.status === selectedStatus.value
    const haystack = `${course.title || ''} ${course.description || ''}`.toLowerCase()
    const matchesKeyword = keyword === '' || haystack.includes(keyword)
    return matchesStatus && matchesKeyword
  })
})

const totalLessons = computed(() => courses.value.reduce((sum, c) => sum + Number(c.lessons_count || 0), 0))
const totalEnrollments = computed(() => courses.value.reduce((sum, c) => sum + Number(c.enrollments_count || 0), 0))

const statusLabel = (status: string) => {
  const map: Record<string, string> = {
    published: 'Đã xuất bản',
    draft: 'Bản nháp',
    pending_review: 'Chờ duyệt',
    rejected: 'Bị từ chối',
  }
  return map[status] || status
}

const statusClass = (status: string) => {
  const map: Record<string, string> = {
    published: 'role-instructor',
    pending_review: 'role-student',
    draft: 'role-admin',
    rejected: 'role-admin',
  }
  return map[status] || 'role-admin'
}

const formatPrice = (value: number) => {
  if (!value) return 'Miễn phí'
  return `${new Intl.NumberFormat('vi-VN').format(value)} đ`
}

onMounted(async () => {
  try {
    courses.value = await courseStore.fetchMyCourses()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="crud-page">
    <!-- Page Header -->
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên / Studio giảng dạy</p>
        <h2>Khóa học của tôi</h2>
        <p>Theo dõi trạng thái, cập nhật curriculum và di chuyển nhanh đến học viên hoặc doanh thu.</p>
        <div style="display:flex; align-items:center; gap:18px; margin-top:10px;">
          <span style="font-size:0.8rem; font-weight:700; color:var(--muted);">
            {{ courses.length }} khóa học · {{ totalLessons }} bài giảng · {{ totalEnrollments }} học viên
          </span>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:10px; flex-shrink:0; flex-wrap:wrap;">
        <NuxtLink to="/instructor/question-bank" class="crud-secondary-btn">
          <span class="material-symbols-outlined" style="font-size:16px; margin-right:6px;">database</span>
          Ngân hàng câu hỏi
        </NuxtLink>
        <NuxtLink to="/courses/create" class="crud-primary-btn">
          <span class="material-symbols-outlined" style="font-size:16px; margin-right:6px;">add_circle</span>
          Tạo khóa học
        </NuxtLink>
      </div>
    </header>

    <!-- Course Table -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent>
          <input v-model="search" class="crud-search" type="text" placeholder="Tìm tên khóa học, mô tả...">
          <select v-model="selectedStatus" class="crud-select">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </form>
        <NuxtLink to="/courses/create" class="crud-primary-btn">Tạo khóa học</NuxtLink>
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Khóa học</th>
              <th>Học viên</th>
              <th>Bài học</th>
              <th>Giá</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="6" class="crud-empty">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="filteredCourses.length === 0"><td colspan="6" class="crud-empty">Không tìm thấy khóa học phù hợp.</td></tr>
            <tr v-for="course in filteredCourses" :key="course.id">
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                    <span v-else>📘</span>
                  </div>
                  <div>
                    <strong>{{ course.title }}</strong>
                    <p v-if="course.status === 'rejected' && course.reject_reason" style="color:var(--danger); font-size:0.75rem; margin:2px 0 0;">
                      Từ chối: {{ course.reject_reason }}
                    </p>
                    <p v-else style="color:var(--muted); font-size:0.78rem; margin:2px 0 0;">
                      {{ course.category?.name || 'Chưa có danh mục' }}
                    </p>
                  </div>
                </div>
              </td>
              <td>{{ course.enrollments_count || 0 }}</td>
              <td>{{ course.lessons_count || 0 }}</td>
              <td>{{ formatPrice(course.price || 0) }}</td>
              <td><span class="crud-badge" :class="statusClass(course.status)">{{ statusLabel(course.status) }}</span></td>
              <td>
                <div class="crud-actions">
                  <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="action-btn is-edit">
                    <span class="material-symbols-outlined" style="font-size:14px; margin-right:4px;">view_list</span>
                    Curriculum
                  </NuxtLink>
                  <NuxtLink :to="`/courses/${course.id}`" class="action-btn is-view" title="Xem trang khóa học">
                    <span class="material-symbols-outlined" style="font-size:14px;">visibility</span>
                  </NuxtLink>
                  <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="action-btn is-view" title="Học viên">
                    <span class="material-symbols-outlined" style="font-size:14px;">group</span>
                  </NuxtLink>
                  <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="action-btn is-view" title="Doanh thu">
                    <span class="material-symbols-outlined" style="font-size:14px;">payments</span>
                  </NuxtLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>
