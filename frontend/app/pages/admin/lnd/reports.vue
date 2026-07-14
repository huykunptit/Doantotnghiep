<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
// Icons removed - using PrimeIcons
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import UiAreaChart from '~/components/dashboard/charts/UiAreaChart.vue'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'
import UiDonut from '~/components/dashboard/charts/UiDonut.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor']
})

type Id = number

interface CohortItem { id: Id; name: string; code: string }
interface AdministrativeClassItem { id: Id; code: string; name: string; cohort_id: Id }

const token = useAuthTokenCookie()
const toast = useToast()
function headers() { return token.value ? { Authorization: `Bearer ${token.value}` } : {} }

// ── Tab state ────────────────────────────────────────────────────────────────
const activeTab = ref<'overview' | 'class' | 'atrisk' | 'completion'>('overview')

// ── Shared filter data ───────────────────────────────────────────────────────
const loading = ref(false)
const cohorts = ref<CohortItem[]>([])
const adminClasses = ref<AdministrativeClassItem[]>([])
const selectedCohortId = ref<Id | ''>('')
const selectedClassId = ref<Id | ''>('')

// ── Class progress report ────────────────────────────────────────────────────
const reportLoading = ref(false)
const reportData = ref<any>(null)
const studentSearchQuery = ref('')

// ── Overview analytics ───────────────────────────────────────────────────────
const overviewLoading = ref(false)
const overviewData = ref<any>(null)

// ── Cohort enrollment chart ──────────────────────────────────────────────────
const cohortLoading = ref(false)
const cohortData = ref<any[]>([])

// ── At-risk ──────────────────────────────────────────────────────────────────
const atRiskLoading = ref(false)
const atRiskData = ref<any[]>([])
const atRiskSearch = ref('')

// ── Completion rates ─────────────────────────────────────────────────────────
const completionLoading = ref(false)
const completionData = ref<any>(null)

onMounted(async () => {
  loading.value = true
  try {
    const res = await useApi<{ data: CohortItem[] }>('/admin/academic/cohorts?per_page=100', { headers: headers() })
    cohorts.value = res.data
    if (cohorts.value.length > 0) {
      selectedCohortId.value = cohorts.value[0].id
      await loadAdminClasses()
    }
  } catch {
    toast.error('Không thể tải danh sách khóa học.')
  } finally {
    loading.value = false
  }
  await loadOverview()
  await loadCohortData()
  await loadCompletionData()
  await loadAtRisk()
})

watch(selectedCohortId, async () => { await loadAdminClasses() })
watch(selectedClassId, async () => { if (activeTab.value === 'class') await loadProgressReport() })
watch(activeTab, async (tab) => {
  if (tab === 'class' && selectedClassId.value && !reportData.value) await loadProgressReport()
  if (tab === 'atrisk' && !atRiskData.value.length) await loadAtRisk()
})

async function loadAdminClasses() {
  if (!selectedCohortId.value) { adminClasses.value = []; selectedClassId.value = ''; reportData.value = null; return }
  loading.value = true
  try {
    const res = await useApi<{ data: AdministrativeClassItem[] }>(
      `/admin/academic/administrative-classes?cohort_id=${selectedCohortId.value}&per_page=100`,
      { headers: headers() }
    )
    adminClasses.value = res.data
    if (adminClasses.value.length > 0) {
      selectedClassId.value = adminClasses.value[0].id
      if (activeTab.value === 'class') await loadProgressReport()
    } else {
      selectedClassId.value = ''
      reportData.value = null
    }
  } catch { toast.error('Không thể tải danh sách lớp học.') }
  finally { loading.value = false }
}

async function loadProgressReport() {
  if (!selectedClassId.value) { reportData.value = null; return }
  reportLoading.value = true
  try {
    const res = await useApi<any>(`/admin/academic/lnd/reports/class-progress?administrative_class_id=${selectedClassId.value}`, { headers: headers() })
    reportData.value = res
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra khi tải báo cáo.')
    reportData.value = null
  } finally { reportLoading.value = false }
}

async function loadOverview() {
  overviewLoading.value = true
  try {
    overviewData.value = await useApi<any>('/admin/academic/lnd/analytics/overview', { headers: headers() })
  } catch { toast.error('Không thể tải dữ liệu tổng quan.') }
  finally { overviewLoading.value = false }
}

async function loadCohortData() {
  cohortLoading.value = true
  try {
    const res = await useApi<{ data: any[] }>('/admin/academic/lnd/analytics/enrollment-by-cohort', { headers: headers() })
    cohortData.value = res.data
  } catch { }
  finally { cohortLoading.value = false }
}

async function loadAtRisk() {
  atRiskLoading.value = true
  try {
    const params = new URLSearchParams()
    if (selectedCohortId.value) params.set('cohort_id', String(selectedCohortId.value))
    if (selectedClassId.value) params.set('administrative_class_id', String(selectedClassId.value))
    const res = await useApi<any>(`/admin/academic/lnd/analytics/at-risk?${params}`, { headers: headers() })
    atRiskData.value = res.students
  } catch { toast.error('Không thể tải dữ liệu cảnh báo.') }
  finally { atRiskLoading.value = false }
}

async function loadCompletionData() {
  completionLoading.value = true
  try {
    completionData.value = await useApi<any>('/admin/academic/lnd/analytics/completion-rate', { headers: headers() })
  } catch { }
  finally { completionLoading.value = false }
}

// ── Computed chart data ──────────────────────────────────────────────────────
const trendLabels = computed(() =>
  (overviewData.value?.enrollment_trend ?? []).map((d: any) => {
    const parts = d.date.split('-')
    return `${parts[2]}/${parts[1]}`
  })
)
const trendValues = computed(() =>
  (overviewData.value?.enrollment_trend ?? []).map((d: any) => d.count)
)

const cohortBarLabels = computed(() => cohortData.value.map((c: any) => c.code || c.name))
const cohortEnrollValues = computed(() => cohortData.value.map((c: any) => Number(c.enrollment_count)))

const completionTermLabels = computed(() =>
  (completionData.value?.by_term ?? []).map((t: any) => t.term_name)
)
const completionTermValues = computed(() =>
  (completionData.value?.by_term ?? []).map((t: any) => t.completion_rate)
)

const completionProgramSegments = computed(() =>
  (completionData.value?.by_program ?? []).map((p: any) => ({
    label: p.program_name,
    value: Number(p.enrolled_count),
  }))
)

const topCourseLabels = computed(() =>
  (overviewData.value?.top_courses ?? []).map((c: any) => c.title.slice(0, 22) + (c.title.length > 22 ? '…' : ''))
)
const topCourseValues = computed(() =>
  (overviewData.value?.top_courses ?? []).map((c: any) => Number(c.enrolled))
)

const filteredAtRisk = computed(() => {
  if (!atRiskSearch.value.trim()) return atRiskData.value
  const q = atRiskSearch.value.toLowerCase()
  return atRiskData.value.filter((s: any) =>
    s.name?.toLowerCase().includes(q) || s.student_code?.toLowerCase().includes(q)
  )
})

const filteredStudents = computed(() => {
  if (!reportData.value?.students) return []
  const q = studentSearchQuery.value.toLowerCase().trim()
  if (!q) return reportData.value.students
  return reportData.value.students.filter((s: any) =>
    s.name.toLowerCase().includes(q) || s.student_code.toLowerCase().includes(q)
  )
})

function daysLabel(days: number | null) {
  if (days === null) return 'Chưa hoạt động'
  if (days === 0) return 'Hôm nay'
  return `${days} ngày trước`
}

function riskLevel(days: number | null) {
  if (days === null || days > 21) return 'high'
  if (days > 14) return 'medium'
  return 'low'
}
</script>

<template>
  <AdminWorkspaceShell
    title="Báo Cáo & Phân Tích L&D"
    description="Giám sát tiến độ học tập, tích lũy tín chỉ và cảnh báo học vụ theo lộ trình đào tạo"
    :breadcrumb="['Trang chủ', 'Đào tạo', 'Báo Cáo']"
  >
    <div class="page-body">
    <!-- Filter strip -->
    <div class="filter-card dashboard-card">
      <div class="filter-grid">
        <label class="crud-field">
          <span>Khóa đào tạo</span>
          <select v-model="selectedCohortId" :disabled="loading">
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="crud-field">
          <span>Lớp hành chính</span>
          <select v-model="selectedClassId" :disabled="loading || !selectedCohortId">
            <option value="">-- Chọn lớp --</option>
            <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
          </select>
        </label>
      </div>
    </div>

    <!-- Tab nav -->
    <div class="tab-nav">
      <button class="tab-btn" :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">
        <Activity :size="15" /> Tổng quan
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'class' }" @click="activeTab = 'class'">
        <i class="pi pi-graduation-cap" style="font-size:0.9375rem" /> Tiến độ lớp
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'atrisk' }" @click="activeTab = 'atrisk'">
        <AlertTriangle :size="15" />
        Cảnh báo sớm
        <span v-if="atRiskData.length" class="tab-badge danger">{{ atRiskData.length }}</span>
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'completion' }" @click="activeTab = 'completion'">
        <i class="pi pi-verified" style="font-size:0.9375rem" /> Tỷ lệ hoàn thành
      </button>
    </div>

    <!-- ── TAB: OVERVIEW ── -->
    <div v-if="activeTab === 'overview'" class="tab-content">
      <div v-if="overviewLoading" class="shimmer-grid-4">
        <div v-for="i in 4" :key="i" class="shimmer-block kpi" />
      </div>
      <template v-else-if="overviewData">
        <!-- KPI row -->
        <div class="kpi-row-grid">
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Tổng ghi danh</span>
              <strong class="kpi-number">{{ overviewData.totals.enrollments.toLocaleString('vi-VN') }}</strong>
              <span class="kpi-subtext">Tất cả khóa học</span>
            </div>
            <i class="pi pi-book" style="font-size:2.5rem" />
          </div>
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Bài học hoàn thành</span>
              <strong class="kpi-number text-success">{{ overviewData.totals.completed_lessons.toLocaleString('vi-VN') }}</strong>
              <span class="kpi-subtext">lesson_progress.completed</span>
            </div>
            <i class="pi pi-check-circle" style="font-size:2.5rem" />
          </div>
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Tổng sinh viên</span>
              <strong class="kpi-number">{{ overviewData.totals.total_students.toLocaleString('vi-VN') }}</strong>
              <span class="kpi-subtext">Tất cả hệ thống</span>
            </div>
            <i class="pi pi-users" style="font-size:2.5rem" />
          </div>
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Nguy cơ bỏ học</span>
              <strong class="kpi-number text-danger">{{ overviewData.totals.at_risk_students.toLocaleString('vi-VN') }}</strong>
              <span class="kpi-subtext">Không hoạt động 14 ngày</span>
            </div>
            <AlertTriangle :size="40" class="kpi-icon text-danger" />
          </div>
        </div>

        <!-- Trend + Top courses -->
        <div class="charts-row-2">
          <div class="dashboard-card chart-card">
            <div class="chart-card-header">
              <i class="pi pi-arrow-up" style="font-size:1.0rem" />
              <h3>Ghi danh 30 ngày qua</h3>
            </div>
            <UiAreaChart :series="[{ name: 'Ghi danh', values: trendValues }]" :labels="trendLabels" :height="200" />
          </div>
          <div class="dashboard-card chart-card">
            <div class="chart-card-header">
              <BarChart3 :size="16" class="text-primary" />
              <h3>Top 10 khóa học nhiều ghi danh</h3>
            </div>
            <UiBarChart :values="topCourseValues" :labels="topCourseLabels" :height="200" />
          </div>
        </div>

        <!-- Cohort enrollment bar -->
        <div class="dashboard-card chart-card">
          <div class="chart-card-header">
            <i class="pi pi-clone" style="font-size:1.0rem" />
            <h3>Ghi danh theo khóa đào tạo</h3>
          </div>
          <div v-if="cohortLoading" class="shimmer-block" style="height:200px; border-radius:8px;" />
          <UiBarChart v-else :values="cohortEnrollValues" :labels="cohortBarLabels" color="#0F6E8C" :height="200" />
        </div>
      </template>
    </div>

    <!-- ── TAB: CLASS PROGRESS ── -->
    <div v-if="activeTab === 'class'" class="tab-content">
      <div v-if="reportLoading" class="report-loading-box">
        <div class="shimmer-block banner" />
        <div class="shimmer-grid">
          <div v-for="i in 3" :key="i" class="shimmer-block kpi" />
        </div>
      </div>
      <div v-else-if="!selectedClassId" class="report-empty-box dashboard-card">
        <i class="pi pi-building" style="font-size:2.5rem" />
        <p>Vui lòng chọn lớp hành chính để xem báo cáo tiến trình đào tạo.</p>
      </div>
      <div v-else-if="reportData && !reportData.has_curriculum" class="report-empty-box dashboard-card">
        <AlertTriangle :size="40" class="text-warning" />
        <p>{{ reportData.message }}</p>
        <span class="text-small text-muted mt-5">Hãy gán lộ trình đào tạo cho lớp này trong mục Quản lý lớp học.</span>
      </div>
      <div v-else-if="reportData" class="report-layout-grid">
        <div class="kpi-row-grid">
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Sĩ số sinh viên</span>
              <strong class="kpi-number">{{ reportData.stats.total_students }}</strong>
              <span class="kpi-subtext">Học viên chính quy</span>
            </div>
            <i class="pi pi-users" style="font-size:2.5rem" />
          </div>
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Cần cảnh báo tiến độ</span>
              <strong class="kpi-number text-danger">{{ reportData.stats.at_risk_students }}</strong>
              <span class="kpi-subtext">Hoàn thành &lt; 50%</span>
            </div>
            <AlertTriangle :size="40" class="kpi-icon text-danger" />
          </div>
          <div class="dashboard-card kpi-card-box">
            <div class="kpi-card-left">
              <span class="kpi-label">Tỷ lệ hoàn thành lớp</span>
              <strong class="kpi-number text-success">{{ reportData.stats.average_completion_rate }}%</strong>
              <span class="kpi-subtext">Trung bình toàn lớp</span>
            </div>
            <i class="pi pi-arrow-up" style="font-size:2.5rem" />
          </div>
        </div>

        <div class="dashboard-card class-info-banner">
          <div class="banner-summary">
            <div class="banner-item">
              <span class="b-lbl">Lộ trình đào tạo:</span>
              <strong class="b-val text-primary">{{ reportData.class_info.curriculum_name }}</strong>
            </div>
            <div class="banner-item">
              <span class="b-lbl">Tổng số học phần:</span>
              <strong class="b-val">{{ reportData.class_info.total_courses }} môn học</strong>
            </div>
            <div class="banner-item">
              <span class="b-lbl">Tổng số tín chỉ:</span>
              <strong class="b-val">{{ reportData.class_info.total_credits_required }} tín chỉ</strong>
            </div>
          </div>
        </div>

        <!-- Course completion bar chart -->
        <div class="dashboard-card chart-card">
          <div class="chart-card-header">
            <BarChart3 :size="16" class="text-primary" />
            <h3>Tỷ lệ hoàn thành từng học phần</h3>
          </div>
          <UiBarChart
            :values="reportData.courses.map((c: any) => c.total_students > 0 ? Math.round((c.completed_students / c.total_students) * 100) : 0)"
            :labels="reportData.courses.map((c: any) => c.title.slice(0, 18) + (c.title.length > 18 ? '…' : ''))"
            color="#1D9E75"
            :height="220"
            :format-value="(n: number) => n + '%'"
          />
        </div>

        <div class="columns-wrap">
          <div class="dashboard-card column-item">
            <div class="column-header">
              <h3 class="column-title"><i class="pi pi-graduation-cap" style="font-size:1.125rem" /> Tiến độ từng học viên</h3>
              <div class="search-wrap">
                <i class="pi pi-search" style="font-size:0.875rem" />
                <input type="text" v-model="studentSearchQuery" placeholder="Mã SV hoặc tên..." />
              </div>
            </div>
            <div class="crud-table-wrap">
              <table class="crud-table">
                <thead>
                  <tr>
                    <th>Mã SV</th>
                    <th>Họ và tên</th>
                    <th style="width:140px;">Học phần đã đạt</th>
                    <th>Tiến độ</th>
                    <th>Cảnh báo</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="filteredStudents.length === 0">
                    <td colspan="5" class="crud-empty">Không tìm thấy sinh viên nào.</td>
                  </tr>
                  <tr v-else v-for="student in filteredStudents" :key="student.id">
                    <td><span class="student-code">{{ student.student_code }}</span></td>
                    <td><strong>{{ student.name }}</strong></td>
                    <td>
                      <div class="credits-box">
                        <span><strong>{{ student.completed_courses_count }}</strong>/{{ reportData.class_info.total_courses }} môn</span>
                        <small class="text-muted">({{ student.credits_earned }} TC)</small>
                      </div>
                    </td>
                    <td>
                      <div class="progress-bar-wrap">
                        <div class="progress-track">
                          <div class="progress-fill" :style="{ width: `${student.average_progress}%` }"></div>
                        </div>
                        <span class="progress-text">{{ student.average_progress }}%</span>
                      </div>
                    </td>
                    <td>
                      <span class="alert-status-badge" :class="{ 'is-danger': student.is_at_risk }">
                        {{ student.is_at_risk ? 'Cảnh báo' : 'Bình thường' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="dashboard-card column-item">
            <div class="column-header">
              <h3 class="column-title"><BarChart3 :size="18" /> Thống kê theo học phần</h3>
            </div>
            <div class="crud-table-wrap">
              <table class="crud-table">
                <thead>
                  <tr>
                    <th style="width:80px;">Học kỳ</th>
                    <th>Tên học phần</th>
                    <th style="width:120px;">Đã ghi danh</th>
                    <th style="width:120px;">Hoàn thành</th>
                    <th style="width:90px;">Tỷ lệ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="reportData.courses.length === 0">
                    <td colspan="5" class="crud-empty">Lộ trình chưa có môn học.</td>
                  </tr>
                  <tr v-else v-for="course in reportData.courses" :key="course.course_id">
                    <td><span class="term-badge">Kỳ {{ course.term_number }}</span></td>
                    <td>
                      <div class="course-name-block">
                        <strong>{{ course.title }}</strong>
                        <span class="req-badge" :class="course.is_required ? 'required' : 'elective'">
                          {{ course.is_required ? 'Bắt buộc' : 'Tự chọn' }}
                        </span>
                      </div>
                    </td>
                    <td><strong>{{ course.enrolled_students }}</strong> / {{ course.total_students }}</td>
                    <td><strong class="text-success">{{ course.completed_students }}</strong> / {{ course.total_students }}</td>
                    <td>
                      <div class="course-completion-badge" :class="{ 'high': (course.completed_students / Math.max(1, course.total_students)) >= 0.8 }">
                        {{ Math.round((course.completed_students / Math.max(1, course.total_students)) * 100) }}%
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div v-else-if="!reportLoading && selectedClassId" class="report-empty-box dashboard-card">
        <i class="pi pi-building" style="font-size:2.5rem" />
        <p>Nhấn vào tab này để tải báo cáo lớp.</p>
        <button class="crud-primary-btn" @click="loadProgressReport">Tải báo cáo</button>
      </div>
    </div>

    <!-- ── TAB: AT-RISK ── -->
    <div v-if="activeTab === 'atrisk'" class="tab-content">
      <div class="dashboard-card alert-banner">
        <AlertTriangle :size="20" class="text-danger" />
        <div>
          <strong>Cảnh báo sớm học viên nguy cơ bỏ học</strong>
          <p>Danh sách sinh viên có ghi danh nhưng không hoàn thành bài học nào trong 30 ngày qua.</p>
        </div>
        <button class="crud-secondary-btn ml-auto" @click="loadAtRisk" :disabled="atRiskLoading">
          <RefreshCcw :size="14" /> Làm mới
        </button>
      </div>

      <div v-if="atRiskLoading" class="shimmer-block" style="height:300px; border-radius:14px;" />
      <div v-else class="dashboard-card">
        <div class="column-header" style="padding: 16px 20px;">
          <h3 class="column-title"><AlertTriangle :size="16" class="text-danger" /> {{ atRiskData.length }} học viên cần chú ý</h3>
          <div class="search-wrap" style="width:220px;">
            <i class="pi pi-search" style="font-size:0.875rem" />
            <input type="text" v-model="atRiskSearch" placeholder="Tìm theo tên, mã SV..." />
          </div>
        </div>
        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Mã SV</th>
                <th>Họ và tên</th>
                <th>Email</th>
                <th>Lớp hành chính</th>
                <th>Khóa</th>
                <th>Số khóa ghi danh</th>
                <th>Hoạt động cuối</th>
                <th>Mức độ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredAtRisk.length === 0">
                <td colspan="8" class="crud-empty">
                  <i class="pi pi-check-circle" style="font-size:1.25rem" /> Không có học viên nào cần cảnh báo.
                </td>
              </tr>
              <tr v-else v-for="s in filteredAtRisk" :key="s.id" :class="{ 'row-danger': riskLevel(s.days_inactive) === 'high', 'row-warn': riskLevel(s.days_inactive) === 'medium' }">
                <td><span class="student-code">{{ s.student_code }}</span></td>
                <td><strong>{{ s.name }}</strong></td>
                <td class="text-muted" style="font-size: 0.78rem;">{{ s.email }}</td>
                <td>{{ s.admin_class ?? '—' }}</td>
                <td>{{ s.cohort ?? '—' }}</td>
                <td><strong>{{ s.enrollment_count }}</strong></td>
                <td>
                  <span class="last-activity" :class="riskLevel(s.days_inactive)">
                    <i class="pi pi-clock" style="font-size:0.75rem" /> {{ daysLabel(s.days_inactive) }}
                  </span>
                </td>
                <td>
                  <span class="risk-badge" :class="riskLevel(s.days_inactive)">
                    {{ riskLevel(s.days_inactive) === 'high' ? 'Nghiêm trọng' : riskLevel(s.days_inactive) === 'medium' ? 'Cần theo dõi' : 'Chú ý' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── TAB: COMPLETION RATES ── -->
    <div v-if="activeTab === 'completion'" class="tab-content">
      <div v-if="completionLoading" class="shimmer-grid">
        <div v-for="i in 2" :key="i" class="shimmer-block kpi" style="height:300px;" />
      </div>
      <template v-else-if="completionData">
        <div class="charts-row-2">
          <div class="dashboard-card chart-card">
            <div class="chart-card-header">
              <BarChart3 :size="16" class="text-primary" />
              <h3>Tỷ lệ hoàn thành theo học kỳ (%)</h3>
            </div>
            <div v-if="completionTermLabels.length === 0" class="report-empty-box" style="padding: 40px;">
              <p class="text-muted">Chưa có dữ liệu theo học kỳ.</p>
            </div>
            <UiBarChart v-else :values="completionTermValues" :labels="completionTermLabels" color="#1D9E75" :height="220" :format-value="(n: number) => n + '%'" />
          </div>

          <div class="dashboard-card chart-card">
            <div class="chart-card-header">
              <i class="pi pi-clone" style="font-size:1.0rem" />
              <h3>Phân bổ ghi danh theo ngành</h3>
            </div>
            <div v-if="completionProgramSegments.length === 0" class="report-empty-box" style="padding: 40px;">
              <p class="text-muted">Chưa có dữ liệu theo ngành.</p>
            </div>
            <UiDonut v-else :segments="completionProgramSegments" :size="180" center-label="Tổng GD" />
          </div>
        </div>

        <!-- Term table detail -->
        <div class="dashboard-card">
          <div class="column-header" style="padding: 16px 20px;">
            <h3 class="column-title"><i class="pi pi-verified" style="font-size:1.0rem" /> Chi tiết theo học kỳ</h3>
          </div>
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th>Học kỳ</th>
                  <th>Số SV ghi danh</th>
                  <th>Số SV hoàn thành</th>
                  <th>Tỷ lệ hoàn thành</th>
                  <th>Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!completionData.by_term?.length">
                  <td colspan="5" class="crud-empty">Chưa có dữ liệu.</td>
                </tr>
                <tr v-else v-for="t in completionData.by_term" :key="t.id">
                  <td><span class="term-badge">{{ t.term_name }}</span></td>
                  <td><strong>{{ t.enrolled_count }}</strong></td>
                  <td><strong class="text-success">{{ t.completed_count }}</strong></td>
                  <td>
                    <div class="progress-bar-wrap">
                      <div class="progress-track">
                        <div class="progress-fill" :style="{ width: `${t.completion_rate}%`, background: t.completion_rate >= 70 ? '#10b981' : t.completion_rate >= 40 ? '#f59e0b' : '#ef4444' }"></div>
                      </div>
                      <span class="progress-text">{{ t.completion_rate }}%</span>
                    </div>
                  </td>
                  <td>
                    <span class="course-completion-badge" :class="{ 'high': t.completion_rate >= 70, 'warn': t.completion_rate >= 40 && t.completion_rate < 70, 'low': t.completion_rate < 40 }">
                      {{ t.completion_rate >= 70 ? 'Tốt' : t.completion_rate >= 40 ? 'Trung bình' : 'Thấp' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div><!-- /tab completion -->
    </div><!-- /page-body -->

  </AdminWorkspaceShell>
</template>

<style scoped>
/* ── Page body — one slot in crud-page grid, owns internal spacing ── */
.page-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Filter ── */
.filter-card { padding: 14px 16px; }
.filter-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
@media (max-width: 640px) { .filter-grid { grid-template-columns: 1fr; } }

/* ── Tab nav ── */
.tab-nav {
  display: flex;
  gap: 4px;
  border-bottom: 2px solid #e2e8f0;
  flex-wrap: wrap;
  /* no margin — crud-page gap handles spacing */
}
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  font-size: 0.82rem;
  font-weight: 600;
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  border-radius: 6px 6px 0 0;
  transition: color 0.15s, border-color 0.15s;
}
.tab-btn:hover { color: #1e293b; background: #f8fafc; }
.tab-btn.active { color: #047857; border-bottom-color: #047857; background: #f0fdf4; }
.tab-badge {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 999px;
}
.tab-badge.danger { background: #fef2f2; color: #b91c1c; }

/* ── Shimmer ── */
.shimmer-block {
  border-radius: 14px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmerAnim 1.5s infinite;
}
.shimmer-block.banner { height: 80px; }
.shimmer-block.kpi { height: 100px; }
.shimmer-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.shimmer-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
@media (max-width: 900px) { .shimmer-grid-4 { grid-template-columns: repeat(2, 1fr); } }
@keyframes shimmerAnim { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ── KPI cards ── */
.kpi-row-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
@media (max-width: 1024px) { .kpi-row-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .kpi-row-grid { grid-template-columns: 1fr; } }

.kpi-card-box { padding: 18px; display: flex; justify-content: space-between; align-items: center; }
.kpi-card-left { display: flex; flex-direction: column; gap: 3px; }
.kpi-label { font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-number { font-size: 1.7rem; font-weight: 800; color: #1e293b; line-height: 1; }
.kpi-subtext { font-size: 0.7rem; color: #64748b; }
.kpi-icon { opacity: 0.75; }

/* ── Tab content wrappers — use flex column with consistent gap ── */
.tab-content { display: flex; flex-direction: column; gap: 14px; }

/* ── Charts ── */
.charts-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 900px) { .charts-row-2 { grid-template-columns: 1fr; } }
.chart-card { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
.chart-card-header { display: flex; align-items: center; gap: 8px; }
.chart-card-header h3 { margin: 0; font-size: 0.88rem; font-weight: 700; color: #1e293b; }

/* ── Empty state ── */
.report-empty-box { padding: 40px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; }
.report-empty-box p { color: #475569; font-size: 0.9rem; font-weight: 600; margin: 0; }
.report-loading-box { display: flex; flex-direction: column; gap: 14px; }

/* ── Class info banner ── */
.report-layout-grid { display: flex; flex-direction: column; gap: 14px; }
.class-info-banner { padding: 14px 18px; background: #f8fafc; }
.banner-summary { display: flex; gap: 28px; flex-wrap: wrap; }
.banner-item { display: flex; flex-direction: column; gap: 2px; }
.banner-item .b-lbl { font-size: 0.68rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; }
.banner-item .b-val { font-size: 0.88rem; font-weight: 700; color: #334155; }

/* ── Columns wrap ── */
.columns-wrap { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 14px; }
@media (max-width: 1024px) { .columns-wrap { grid-template-columns: 1fr; } }
.column-item { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }
.column-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.column-title { font-size: 0.9rem; font-weight: 700; color: #1e293b; display: inline-flex; align-items: center; gap: 8px; margin: 0; }

/* ── Search ── */
.search-wrap { position: relative; width: 180px; }
.search-wrap .search-icon { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-wrap input { width: 100%; padding: 5px 8px 5px 26px; font-size: 0.78rem; border-radius: 6px; border: 1px solid #cbd5e1; outline: none; background: #f8fafc; }
.search-wrap input:focus { border-color: var(--green-deep, #047857); background: #fff; }

/* ── Table elements ── */
.student-code { font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.78rem; color: #475569; }
.credits-box { display: flex; flex-direction: column; gap: 2px; }
.credits-box strong { color: var(--green-deep, #047857); }
.credits-box small { font-size: 0.68rem; }
.progress-bar-wrap { display: flex; align-items: center; gap: 8px; width: 100%; }
.progress-track { flex: 1; height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; background: #10b981; border-radius: 4px; transition: width 0.4s; }
.progress-text { font-size: 0.72rem; font-weight: 700; color: #475569; width: 36px; text-align: right; }
.alert-status-badge { font-size: 0.68rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: #f0fdf4; color: #15803d; }
.alert-status-badge.is-danger { background: #fef2f2; color: #b91c1c; animation: pulseRisk 2s infinite; }
@keyframes pulseRisk { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
.term-badge { background: #e2e8f0; color: #475569; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; }
.course-name-block { display: flex; flex-direction: column; gap: 4px; }
.req-badge { font-size: 0.62rem; font-weight: 700; padding: 1px 4px; border-radius: 3px; align-self: flex-start; text-transform: uppercase; }
.req-badge.required { background: #fef2f2; color: #b91c1c; }
.req-badge.elective { background: #f0fdf4; color: #15803d; }
.course-completion-badge { display: inline-block; padding: 2px 8px; font-weight: 700; font-size: 0.75rem; border-radius: 12px; background: #f1f5f9; color: #475569; text-align: center; }
.course-completion-badge.high { background: #ecfdf5; color: #047857; }
.course-completion-badge.warn { background: #fffbeb; color: #b45309; }
.course-completion-badge.low { background: #fef2f2; color: #b91c1c; }

/* ── At-risk ── */
.alert-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 14px;
}
.alert-banner strong { color: #9a3412; font-size: 0.88rem; display: block; margin-bottom: 2px; }
.alert-banner p { margin: 0; font-size: 0.76rem; color: #c2410c; }
.ml-auto { margin-left: auto; }
.row-danger td { background: #fff5f5; }
.row-warn td { background: #fffbeb; }
.last-activity { display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
.last-activity.high { background: #fef2f2; color: #b91c1c; }
.last-activity.medium { background: #fffbeb; color: #b45309; }
.last-activity.low { background: #f0fdf4; color: #15803d; }
.risk-badge { font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
.risk-badge.high { background: #fef2f2; color: #b91c1c; }
.risk-badge.medium { background: #fffbeb; color: #b45309; }
.risk-badge.low { background: #f0fdf4; color: #15803d; }

.mt-5 { margin-top: 5px; }
</style>
