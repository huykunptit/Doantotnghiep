<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'
import DataTableFooter from '~/components/common/DataTableFooter.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])
const search = ref('')
const selectedStatus = ref('all')
const instrPage = ref(1)
const instrPerPage = ref(10)

const statusOptions = [
  { value: 'all', label: 'Tất cả trạng thái' },
  { value: 'published', label: 'Đã xuất bản' },
  { value: 'pending_review', label: 'Chờ duyệt' },
  { value: 'draft', label: 'Bản nháp' },
  { value: 'rejected', label: 'Bị từ chối' },
]

const allFilteredCourses = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  return courses.value.filter((course) => {
    const matchesStatus = selectedStatus.value === 'all' || course.status === selectedStatus.value
    const haystack = `${course.title || ''} ${course.description || ''}`.toLowerCase()
    const matchesKeyword = keyword === '' || haystack.includes(keyword)
    return matchesStatus && matchesKeyword
  })
})
const instrLastPage = computed(() => Math.max(1, Math.ceil(allFilteredCourses.value.length / instrPerPage.value)))
const filteredCourses = computed(() => {
  const start = (instrPage.value - 1) * instrPerPage.value
  return allFilteredCourses.value.slice(start, start + instrPerPage.value)
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
    published: 'is-published',
    pending_review: 'is-pending',
    draft: 'is-draft',
    rejected: 'is-rejected',
  }
  return map[status] || 'is-draft'
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
      </div>
      <div style="display:flex; align-items:center; gap:10px; flex-shrink:0; flex-wrap:wrap;">
        <NuxtLink to="/instructor/question-bank" class="crud-secondary-btn">
          <span class="material-symbols-outlined" style="font-size:18px; margin-right:6px;">database</span>
          Ngân hàng câu hỏi
        </NuxtLink>
        <NuxtLink to="/courses/create" class="crud-primary-btn">
          <span class="material-symbols-outlined" style="font-size:18px; margin-right:6px;">add_circle</span>
          Tạo khóa học
        </NuxtLink>
      </div>
    </header>

    <!-- Stats Dashboard Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon icon-primary">
          <span class="material-symbols-outlined">school</span>
        </div>
        <div class="stat-info">
          <h4>Tổng khóa học</h4>
          <p>{{ courses.length }}</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon icon-secondary">
          <span class="material-symbols-outlined">menu_book</span>
        </div>
        <div class="stat-info">
          <h4>Tổng bài giảng</h4>
          <p>{{ totalLessons }}</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon icon-accent">
          <span class="material-symbols-outlined">group</span>
        </div>
        <div class="stat-info">
          <h4>Tổng học viên</h4>
          <p>{{ totalEnrollments }}</p>
        </div>
      </div>
    </div>

    <!-- Course Table -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent>
          <div class="search-input-wrap">
            <span class="material-symbols-outlined search-icon">search</span>
            <input v-model="search" class="crud-search" type="text" placeholder="Tìm tên khóa học, mô tả...">
          </div>
          <select v-model="selectedStatus" class="crud-select">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </form>
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Khóa học</th>
              <th>Số học viên</th>
              <th>Số bài học</th>
              <th>Học phí</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="6" class="crud-empty">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="allFilteredCourses.length === 0"><td colspan="6" class="crud-empty">Không tìm thấy khóa học phù hợp.</td></tr>
            <tr v-for="course in filteredCourses" :key="course.id">
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                    <span v-else class="material-symbols-outlined text-thumb">book</span>
                  </div>
                  <div class="course-info">
                    <strong>{{ course.title }}</strong>
                    <p v-if="course.status === 'rejected' && course.reject_reason" class="reject-reason">
                      Từ chối: {{ course.reject_reason }}
                    </p>
                    <p v-else class="category-name">
                      {{ course.category?.name || 'Chưa có danh mục' }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="stat-col">{{ course.enrollments_count || 0 }}</td>
              <td class="stat-col">{{ course.lessons_count || 0 }}</td>
              <td class="price-col">{{ formatPrice(course.price || 0) }}</td>
              <td>
                <span class="status-badge" :class="statusClass(course.status)">
                  {{ statusLabel(course.status) }}
                </span>
              </td>
              <td>
                <div class="crud-actions">
                  <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="action-btn is-curriculum">
                    <span class="material-symbols-outlined" style="font-size:16px; margin-right:4px;">view_list</span>
                    Giáo trình
                  </NuxtLink>
                  <NuxtLink :to="`/courses/${course.id}`" class="action-btn is-icon" title="Xem trang khóa học">
                    <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
                  </NuxtLink>
                  <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="action-btn is-icon" title="Học viên">
                    <span class="material-symbols-outlined" style="font-size:18px;">group</span>
                  </NuxtLink>
                  <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="action-btn is-icon" title="Doanh thu">
                    <span class="material-symbols-outlined" style="font-size:18px;">payments</span>
                  </NuxtLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DataTableFooter
        :current="instrPage"
        :last="instrLastPage"
        :total="allFilteredCourses.length"
        :per-page="instrPerPage"
        @page="instrPage = $event"
        @update:per-page="instrPerPage = $event; instrPage = 1"
      />
    </section>
  </section>
</template>

<style scoped>
/* Stats dashboard cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--color-neutral-0, #fff);
  padding: 24px;
  border-radius: 20px;
  border: 1px solid rgba(var(--green-rgb, 17, 51, 17), 0.05);
  box-shadow: 0 4px 20px rgba(var(--green-rgb, 17, 51, 17), 0.02);
  display: flex;
  align-items: center;
  gap: 18px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(var(--green-rgb, 17, 51, 17), 0.06);
}
.stat-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.stat-icon span {
  font-size: 24px;
}
.icon-primary {
  background: #e1f5ee; /* primary-50 */
  color: #1d9e75; /* primary-400 */
}
.icon-secondary {
  background: #e6f1fb; /* secondary-50 */
  color: #378add; /* secondary-400 */
}
.icon-accent {
  background: #faece7; /* accent-50 */
  color: #d85a30; /* accent-400 */
}
.stat-info h4 {
  margin: 0;
  font-size: 0.85rem;
  color: var(--color-neutral-600, #4a6059);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.stat-info p {
  margin: 4px 0 0;
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--color-neutral-800, #1f312b);
  font-family: 'Outfit', sans-serif;
}

/* Card-like Table Layout */
.crud-table {
  border-collapse: separate;
  border-spacing: 0 10px;
  background: transparent;
  width: 100%;
}
.crud-table th {
  background: transparent;
  border: none;
  font-weight: 700;
  color: var(--color-neutral-600, #4a6059);
  font-size: 0.82rem;
  padding: 10px 16px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.crud-table tbody tr {
  background: var(--color-neutral-0, #ffffff);
  box-shadow: 0 2px 8px rgba(17, 51, 17, 0.02);
  border-radius: 16px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.crud-table tbody tr:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(17, 51, 17, 0.06);
}
.crud-table td {
  border: none;
  padding: 16px;
  color: var(--color-neutral-800, #1f312b);
  font-size: 0.9rem;
}
.crud-table td:first-child {
  border-top-left-radius: 16px;
  border-bottom-left-radius: 16px;
}
.crud-table td:last-child {
  border-top-right-radius: 16px;
  border-bottom-right-radius: 16px;
}

/* Search input with inner icon */
.search-input-wrap {
  position: relative;
  flex: 1;
  max-width: 320px;
}
.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 20px;
  color: var(--color-neutral-400, #8fa89e);
  pointer-events: none;
}
.crud-search {
  padding-left: 40px !important;
  width: 100%;
}

/* Thumbnail and text styling */
.crud-course {
  display: flex;
  align-items: center;
  gap: 16px;
}
.crud-course-thumb {
  width: 72px;
  height: 48px;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  background: #f0f4f2;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.crud-course-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.text-thumb {
  font-size: 22px;
  color: #1d9e75;
}
.course-info strong {
  font-size: 0.95rem;
  color: var(--color-neutral-800, #1f312b);
  display: block;
  font-family: 'Outfit', sans-serif;
}
.reject-reason {
  color: #e24b4a;
  font-size: 0.75rem;
  margin: 4px 0 0;
  font-weight: 500;
}
.category-name {
  color: var(--color-neutral-600, #4a6059);
  font-size: 0.78rem;
  margin: 4px 0 0;
}

.stat-col {
  font-weight: 700;
  text-align: center;
}
.price-col {
  font-weight: 700;
  color: #1d9e75;
}

/* Redesigned Status Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.is-published {
  background: #e1f5ee;
  color: #1d9e75;
}
.is-pending {
  background: #e6f1fb;
  color: #378add;
}
.is-draft {
  background: #f0f4f2;
  color: #8fa89e;
}
.is-rejected {
  background: #faece7;
  color: #d85a30;
}

/* Actions styling */
.crud-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.action-btn {
  height: 38px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-weight: 600;
  font-size: 0.85rem;
}
.is-curriculum {
  background: #e1f5ee;
  color: #1d9e75;
  padding: 0 14px;
  border: 1px solid rgba(29, 158, 117, 0.15);
}
.is-curriculum:hover {
  background: #1d9e75;
  color: #fff;
  border-color: transparent;
}
.is-icon {
  width: 38px;
  background: #f0f4f2;
  color: var(--color-neutral-600, #4a6059);
  border: 1px solid rgba(17, 51, 17, 0.05);
}
.is-icon:hover {
  background: #dde5e1;
  color: var(--color-neutral-800, #1f312b);
}

[data-theme="dark"] .stat-card {
  background: var(--color-neutral-100, #142d1f);
  border-color: rgba(255, 255, 255, 0.05);
}
[data-theme="dark"] .crud-table tbody tr {
  background: var(--color-neutral-100, #142d1f);
}
[data-theme="dark"] .is-icon {
  background: rgba(255, 255, 255, 0.03);
  color: var(--color-neutral-400);
}
[data-theme="dark"] .is-icon:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}
[data-theme="dark"] .is-draft {
  background: rgba(255, 255, 255, 0.05);
  color: var(--color-neutral-400);
}
</style>
