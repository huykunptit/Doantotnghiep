<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const courses = ref<any[]>([])
const stats = ref<any>({})
const search = ref('')
const sortBy = ref<'enrollments' | 'lessons' | 'title'>('enrollments')

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [coursesRes, statsRes] = await Promise.all([
      useApi<any>('/admin/courses?per_page=200', { headers: authHeaders() }),
      useApi<any>('/admin/stats', { headers: authHeaders() }),
    ])
    courses.value = coursesRes.data || []
    stats.value = statsRes || {}
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu.'
  }
  finally {
    loading.value = false
  }
}

const publishedCourses = computed(() =>
  courses.value.filter(c => c.status === 'published')
)

const filteredAndSorted = computed(() => {
  let list = publishedCourses.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(c => c.title?.toLowerCase().includes(q))
  }
  return [...list].sort((a, b) => {
    if (sortBy.value === 'enrollments') return (b.enrollments_count || 0) - (a.enrollments_count || 0)
    if (sortBy.value === 'lessons') return (b.lessons_count || 0) - (a.lessons_count || 0)
    return (a.title || '').localeCompare(b.title || '')
  })
})

const totalEnrollments = computed(() =>
  publishedCourses.value.reduce((s, c) => s + (c.enrollments_count || 0), 0)
)

const maxEnrollment = computed(() =>
  Math.max(...publishedCourses.value.map(c => c.enrollments_count || 0), 1)
)

const avgEnrollment = computed(() =>
  publishedCourses.value.length
    ? Math.round(totalEnrollments.value / publishedCourses.value.length)
    : 0
)

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Tên khóa học', 'Danh mục', 'Số học viên', 'Số bài học']
  const rows = filteredAndSorted.value.slice(0, 200).map(c => [
    c.title || '—',
    c.category?.name || '—',
    c.enrollments_count || 0,
    c.lessons_count || 0,
  ])
  exportToPDF('Tiến độ học tập', `${publishedCourses.value.length} khóa học đang hoạt động`, headers, rows, 'tien-do-hoc-tap')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Khóa học' },
    { key: 'title', label: 'Tên khóa học' },
    { key: 'category', label: 'Danh mục', format: (_: any, row: any) => row.category?.name || 'Chưa phân loại' },
    { key: 'enrollments_count', label: 'Số học viên', format: (val: any) => String(val || 0) },
    { key: 'lessons_count', label: 'Số bài học', format: (val: any) => String(val || 0) },
    { key: 'price', label: 'Giá tiền', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(filteredAndSorted.value, cols, 'tien-do-hoc-tap')
}

onMounted(fetchData)
</script>

<template>
  <AdminWorkspaceShell
    title="Tiến độ học tập"
    description="Theo dõi lộ trình đăng ký và mức độ phổ biến của từng khóa học trong hệ thống."
    :breadcrumb="['Trang chủ', 'Báo cáo', 'Tiến độ học tập']"
  >
    <div v-if="loading" class="dashboard-card crud-empty">Đang tải dữ liệu...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- KPI -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article class="dashboard-card mini-card tone-green">
          <p class="mini-title">Tổng học viên</p>
          <div class="mini-head"><strong>{{ stats.students_count || totalEnrollments }}</strong><span>Đăng ký trong hệ thống</span></div>
        </article>
        <article class="dashboard-card mini-card tone-blue">
          <p class="mini-title">Khóa học hoạt động</p>
          <div class="mini-head"><strong>{{ publishedCourses.length }}</strong><span>Đang mở đăng ký</span></div>
        </article>
        <article class="dashboard-card mini-card tone-amber">
          <p class="mini-title">TB đăng ký / khóa</p>
          <div class="mini-head"><strong>{{ avgEnrollment }}</strong><span>Học viên mỗi khóa</span></div>
        </article>
        <article class="dashboard-card mini-card">
          <p class="mini-title">Tổng bài học</p>
          <div class="mini-head">
            <strong>{{ publishedCourses.reduce((s, c) => s + (c.lessons_count || 0), 0) }}</strong>
            <span>Bài học đã xuất bản</span>
          </div>
        </article>
      </section>

      <!-- Filters -->
      <!-- Filters -->
      <section class="dashboard-card" style="margin-bottom: 24px; padding: 0; border: none; background: transparent; box-shadow: none;">
        <div class="crud-toolbar">
          <div class="crud-toolbar-main">
            <input
              v-model="search"
              type="text"
              placeholder="Tìm tên khóa học..."
              class="crud-search"
            >
            <select v-model="sortBy" class="crud-select">
              <option value="enrollments">Sắp xếp: Lượt đăng ký</option>
              <option value="lessons">Sắp xếp: Số bài học</option>
              <option value="title">Sắp xếp: Tên khóa học</option>
            </select>
          </div>
          <div class="crud-toolbar-right">
            <button class="crud-export-btn" type="button" @click="exportCSV">
              <span class="material-symbols-outlined">download</span>
              Xuất Excel
            </button>
            <button class="crud-primary-btn" type="button" @click="exportPDF" style="display: inline-flex; align-items: center; gap: 6px;">
              <span class="material-symbols-outlined">picture_as_pdf</span>
              Xuất PDF
            </button>
          </div>
        </div>
      </section>

      <!-- Course progress table -->
      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Khóa học đã xuất bản</p>
            <h3>{{ filteredAndSorted.length }} kết quả</h3>
          </div>
        </div>

        <div v-if="filteredAndSorted.length === 0" class="crud-empty">Không có khóa học nào.</div>

        <div v-else class="progress-list">
          <div v-for="(course, idx) in filteredAndSorted" :key="course.id" class="progress-row">
            <div class="progress-rank">
              <span :class="idx < 3 ? 'rank-top' : 'rank-normal'">#{{ idx + 1 }}</span>
            </div>
            <div class="progress-info">
              <div style="display: flex; align-items: center; gap: 10px;">
                <img
                  v-if="course.thumbnail"
                  :src="course.thumbnail"
                  :alt="course.title"
                  style="width: 40px; height: 32px; object-fit: cover; border-radius: 6px; flex-shrink: 0;"
                >
                <div>
                  <strong style="font-size: 0.9rem;">{{ course.title }}</strong>
                  <p style="font-size: 0.75rem; color: var(--muted); margin-top: 2px;">
                    {{ course.category?.name || 'Chưa phân loại' }} &nbsp;·&nbsp; {{ course.lessons_count || 0 }} bài học
                  </p>
                </div>
              </div>
            </div>
            <div class="progress-bar-col">
              <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 4px;">
                <span style="color: var(--muted);">Đăng ký</span>
                <strong>{{ course.enrollments_count || 0 }} HV</strong>
              </div>
              <div style="height: 8px; background: rgba(17,17,17,.07); border-radius: 999px; overflow: hidden;">
                <div
                  style="height: 100%; border-radius: 999px; background: var(--green); transition: width 0.6s ease;"
                  :style="{ width: `${((course.enrollments_count || 0) / maxEnrollment) * 100}%` }"
                />
              </div>
            </div>
            <div class="progress-price">
              <span v-if="!course.price || course.price === 0" class="crud-badge role-instructor">Miễn phí</span>
              <span v-else style="font-size: 0.85rem; font-weight: 700;">
                {{ new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(course.price) }}
              </span>
            </div>
          </div>
        </div>
      </section>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.progress-list { display: flex; flex-direction: column; }
.progress-row {
  display: grid;
  grid-template-columns: 48px 1fr 1fr 100px;
  align-items: center;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid var(--line);
}
.progress-row:last-child { border-bottom: none; }
.progress-rank { text-align: center; }
.rank-top { font-size: 1rem; font-weight: 800; color: var(--green-deep); }
.rank-normal { font-size: 0.85rem; font-weight: 700; color: var(--muted); }
.progress-info { min-width: 0; }
.progress-bar-col { min-width: 120px; }
.progress-price { text-align: right; }
@media (max-width: 900px) {
  .progress-row { grid-template-columns: 40px 1fr; }
  .progress-bar-col, .progress-price { display: none; }
}
</style>
