<template>
  <InstructorWorkspaceShell
    title="Ngân hàng câu hỏi"
    description="Quản lý câu hỏi và bộ đề theo từng khóa học."
    :breadcrumb="['Trang chủ', 'Ngân hàng câu hỏi']"
  >
    <template #actions>
      <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Xem khóa học</NuxtLink>
    </template>

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
              <th>STT</th>
              <th>Khóa học</th>
              <th>Số bài học</th>
              <th>Số học viên</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="crud-empty">Đang tải...</td>
            </tr>
            <tr v-else-if="filteredCourses.length === 0">
              <td colspan="6" class="crud-empty">Chưa có khóa học.</td>
            </tr>
            <tr v-for="(course, idx) in filteredCourses" :key="course.id">
              <td>{{ (qbPage - 1) * qbPerPage + idx + 1 }}</td>
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                    <span v-else>📘</span>
                  </div>
                  <div><strong>{{ course.title }}</strong></div>
                </div>
              </td>
              <td>{{ course.lessons_count || 0 }}</td>
              <td>{{ course.enrollments_count || 0 }}</td>
              <td><span class="crud-badge" :class="statusClass(course.status)">{{ statusLabel(course.status) }}</span></td>
              <td>
                <div class="crud-actions">
                  <NuxtLink :to="`/instructor/courses/${course.id}/question-bank`" class="action-btn is-view">Chi tiết</NuxtLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DataTableFooter
        :current="qbPage"
        :last="qbLastPage"
        :total="allFilteredCourses.length"
        :per-page="qbPerPage"
        @page="qbPage = $event"
        @update:per-page="qbPerPage = $event; qbPage = 1"
      />
    </section>
  </InstructorWorkspaceShell>
</template>

<script setup lang="ts">
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])
const search = ref('')
const qbPage = ref(1)
const qbPerPage = ref(10)

const statusLabel = (status: string) => {
  const map: Record<string, string> = { published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }
  return map[status] || status
}

const statusClass = (s: string) => ({ published: 'role-instructor', pending_review: 'role-student', draft: 'role-admin', rejected: 'role-admin' }[s] || 'role-admin')

const allFilteredCourses = computed(() => {
  if (!search.value.trim()) return courses.value
  const q = search.value.toLowerCase()
  return courses.value.filter(c => c.title?.toLowerCase().includes(q))
})
const qbLastPage = computed(() => Math.max(1, Math.ceil(allFilteredCourses.value.length / qbPerPage.value)))
const filteredCourses = computed(() => {
  const start = (qbPage.value - 1) * qbPerPage.value
  return allFilteredCourses.value.slice(start, start + qbPerPage.value)
})

onMounted(async () => {
  try {
    courses.value = await courseStore.fetchMyCourses()
  } finally {
    loading.value = false
  }
})
</script>
