<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, FileText } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const courses = ref<any[]>([])
const stats = ref<any>({})

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

const performanceMetrics = computed(() => [
  {
    label: 'Tổng khóa học',
    value: stats.value.courses_count || courses.value.length,
    icon: 'graduation-cap',
    color: 'tone-blue',
  },
  {
    label: 'Đã xuất bản',
    value: courses.value.filter(c => c.status === 'published').length,
    icon: 'circle-check-big',
    color: 'tone-green',
  },
  {
    label: 'Tổng đăng ký',
    value: stats.value.students_count || courses.value.reduce((s, c) => s + (c.enrollments_count || 0), 0),
    icon: 'users',
    color: 'tone-amber',
  },
])

const statusDistribution = computed(() => {
  const map: Record<string, number> = {}
  courses.value.forEach(c => {
    map[c.status] = (map[c.status] || 0) + 1
  })
  return Object.entries(map).map(([status, count]) => ({
    status,
    count,
    label: { published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[status] || status,
  })).sort((a, b) => b.count - a.count)
})

const categoryDistribution = computed(() => {
  const map: Record<string, number> = {}
  courses.value.forEach(c => {
    const cat = c.category?.name || 'Chưa phân loại'
    map[cat] = (map[cat] || 0) + 1
  })
  const total = courses.value.length || 1
  return Object.entries(map)
    .sort(([, a], [, b]) => b - a)
    .slice(0, 6)
    .map(([name, count]) => ({ name, count, percentage: Math.round((count / total) * 100) }))
})

const topByEnrollment = computed(() =>
  [...courses.value]
    .sort((a, b) => (b.enrollments_count || 0) - (a.enrollments_count || 0))
    .slice(0, 8)
)

const maxEnrollment = computed(() =>
  Math.max(...topByEnrollment.value.map(c => c.enrollments_count || 0), 1)
)

const { exportToPDF, exportToCSV } = useExport()

function exportPDF() {
  const headers = ['Tên khóa học', 'Danh mục', 'Trạng thái', 'Lượt đăng ký', 'Giảng viên']
  const rows = courses.value.slice(0, 200).map(c => [
    c.title || '—',
    c.category?.name || '—',
    { published: 'Xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[c.status] || c.status,
    c.enrollments_count || 0,
    c.user?.name || '—',
  ])
  exportToPDF('Báo cáo Khóa học', `Tổng ${courses.value.length} khóa học`, headers, rows, 'bao-cao-khoa-hoc')
}

function exportCSV() {
  const cols = [
    { key: 'id', label: 'ID Khóa học' },
    { key: 'title', label: 'Tên khóa học' },
    { key: 'category', label: 'Danh mục', format: (_: any, row: any) => row.category?.name || 'Chưa phân loại' },
    { key: 'status', label: 'Trạng thái', format: (val: any) => ({ published: 'Xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Từ chối' }[val] || val) },
    { key: 'enrollments_count', label: 'Lượt đăng ký', format: (val: any) => String(val || 0) },
    { key: 'lessons_count', label: 'Bài học', format: (val: any) => String(val || 0) },
    { key: 'price', label: 'Giá', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(courses.value, cols, 'bao-cao-khoa-hoc')
}

onMounted(fetchData)
</script>

<template>
  <AdminWorkspaceShell
    title="Báo cáo theo khóa học"
    description="Phân bổ danh mục, trạng thái và hiệu quả đào tạo của toàn bộ hệ thống khóa học."
    :breadcrumb="['Trang chủ', 'Báo cáo', 'Báo cáo khóa học']"
  >
    <div v-if="loading" class="dashboard-card crud-empty">Đang tải dữ liệu...</div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <!-- Export toolbar -->
      <section class="dashboard-card" style="margin-bottom: 24px; padding: 0; border: none; background: transparent; box-shadow: none;">
        <div class="crud-toolbar">
          <div class="crud-toolbar-main">
            <p class="section-kicker" style="margin: 0;">Xuất dữ liệu báo cáo</p>
          </div>
          <div class="crud-toolbar-right">
            <button class="crud-export-btn" type="button" @click="exportCSV">
              <Download :size="18" :stroke-width="1.75" />
              Xuất Excel
            </button>
            <button class="crud-primary-btn" type="button" @click="exportPDF" style="display: inline-flex; align-items: center; gap: 6px;">
              <FileText :size="18" :stroke-width="1.75" />
              Xuất PDF
            </button>
          </div>
        </div>
      </section>
      <!-- KPI -->
      <section class="dashboard-grid" style="margin-bottom: 24px;">
        <article v-for="metric in performanceMetrics" :key="metric.label" class="dashboard-card mini-card" :class="metric.color">
          <p class="mini-title">{{ metric.label }}</p>
          <div class="mini-head">
            <strong>{{ metric.value }}</strong>
            <SylvaIcon :name="metric.icon" :size="20" style="opacity: 0.5;" />
          </div>
        </article>
      </section>

      <div class="report-layout">
        <!-- Left col -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
          <!-- Category distribution -->
          <section class="dashboard-card">
            <div class="card-head" style="margin-bottom: 24px;">
              <h3>Phân bổ theo danh mục</h3>
              <p>Số lượng khóa học theo từng lĩnh vực đào tạo.</p>
            </div>
            <div v-if="categoryDistribution.length === 0" class="crud-empty">Chưa có dữ liệu.</div>
            <div v-else class="category-bars">
              <div v-for="cat in categoryDistribution" :key="cat.name" class="category-item">
                <div class="cat-label">
                  <strong>{{ cat.name }}</strong>
                  <span>{{ cat.count }} khóa ({{ cat.percentage }}%)</span>
                </div>
                <div class="cat-track">
                  <div class="cat-fill" :style="{ width: `${cat.percentage}%` }" />
                </div>
              </div>
            </div>
          </section>

          <!-- Top by enrollment -->
          <section class="dashboard-card">
            <div class="card-head" style="margin-bottom: 24px;">
              <h3>Khóa học nhiều học viên nhất</h3>
            </div>
            <div v-if="topByEnrollment.length === 0" class="crud-empty">Chưa có dữ liệu.</div>
            <div v-else style="display: grid; gap: 14px;">
              <div v-for="course in topByEnrollment" :key="course.id" style="display: grid; gap: 6px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                  <span style="font-weight: 600; max-width: 24ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ course.title }}</span>
                  <span style="color: var(--muted); white-space: nowrap; margin-left: 8px;">{{ course.enrollments_count || 0 }} HV</span>
                </div>
                <div style="height: 6px; background: rgba(17,17,17,.05); border-radius: 999px; overflow: hidden;">
                  <div style="height: 100%; background: var(--green); border-radius: 999px; transition: width 0.8s ease;" :style="{ width: `${((course.enrollments_count || 0) / maxEnrollment) * 100}%` }" />
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- Right col -->
        <aside class="report-side">
          <!-- Status breakdown -->
          <section class="dashboard-card">
            <div class="card-head" style="margin-bottom: 20px;">
              <h3>Theo trạng thái</h3>
            </div>
            <div style="display: grid; gap: 14px;">
              <div v-for="s in statusDistribution" :key="s.status" style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.875rem; font-weight: 600;">{{ s.label }}</span>
                <span
                  class="crud-badge"
                  :class="{
                    'role-instructor': s.status === 'published',
                    'role-admin': s.status === 'rejected',
                  }"
                >
                  {{ s.count }}
                </span>
              </div>
            </div>
          </section>

          <!-- Free vs paid -->
          <section class="dashboard-card stat-highlight">
            <h4>Miễn phí vs Trả phí</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
              <div style="text-align: center;">
                <strong style="font-size: 1.75rem; color: var(--green-deep);">
                  {{ courses.filter(c => !c.price || c.price === 0).length }}
                </strong>
                <p style="font-size: 0.8rem; color: var(--muted); margin-top: 4px;">Miễn phí</p>
              </div>
              <div style="text-align: center;">
                <strong style="font-size: 1.75rem; color: var(--green);">
                  {{ courses.filter(c => c.price > 0).length }}
                </strong>
                <p style="font-size: 0.8rem; color: var(--muted); margin-top: 4px;">Trả phí</p>
              </div>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.report-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 24px;
}
.report-side { display: flex; flex-direction: column; gap: 24px; }
.category-bars { display: grid; gap: 20px; }
.category-item { display: grid; gap: 8px; }
.cat-label { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
.cat-label span { color: var(--muted); font-size: 0.8rem; }
.cat-track { height: 10px; background: rgba(var(--green-rgb),.07); border-radius: 999px; overflow: hidden; }
.cat-fill { height: 100%; background: var(--green); border-radius: 999px; transition: width 1s ease-out; }
.stat-highlight { background: var(--green-soft); border: 1px dashed var(--green); }
.stat-highlight h4 { margin: 0; font-size: 0.95rem; }
@media (max-width: 1100px) { .report-layout { grid-template-columns: 1fr; } }
</style>
