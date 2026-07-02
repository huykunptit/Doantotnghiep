<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from '~/composables/useToast'
import { 
  Users, 
  AlertTriangle, 
  TrendingUp, 
  Layers, 
  Search, 
  Award, 
  CheckCircle2, 
  BarChart3,
  HelpCircle,
  Building,
  GraduationCap
} from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor']
})

type Id = number

interface CohortItem {
  id: Id
  name: string
  code: string
}

interface AdministrativeClassItem {
  id: Id
  code: string
  name: string
  cohort_id: Id
}

const token = useAuthTokenCookie()
const toast = useToast()

function headers() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

const loading = ref(false)
const reportLoading = ref(false)

// Selections
const cohorts = ref<CohortItem[]>([])
const adminClasses = ref<AdministrativeClassItem[]>([])
const selectedCohortId = ref<Id | ''>('')
const selectedClassId = ref<Id | ''>('')

// Report Data
const reportData = ref<any>(null)
const studentSearchQuery = ref('')

onMounted(async () => {
  loading.value = true
  try {
    const res = await useApi<{ data: CohortItem[] }>('/admin/academic/cohorts?per_page=100', { headers: headers() })
    cohorts.value = res.data
    if (cohorts.value.length > 0) {
      selectedCohortId.value = cohorts.value[0].id
      await loadAdminClasses()
    }
  } catch (e) {
    toast.error('Không thể tải danh sách khóa học.')
  } finally {
    loading.value = false
  }
})

watch(selectedCohortId, async () => {
  await loadAdminClasses()
})

watch(selectedClassId, async () => {
  await loadProgressReport()
})

async function loadAdminClasses() {
  if (!selectedCohortId.value) {
    adminClasses.value = []
    selectedClassId.value = ''
    reportData.value = null
    return
  }
  loading.value = true
  try {
    const res = await useApi<{ data: AdministrativeClassItem[] }>(
      `/admin/academic/administrative-classes?cohort_id=${selectedCohortId.value}&per_page=100`,
      { headers: headers() }
    )
    adminClasses.value = res.data
    if (adminClasses.value.length > 0) {
      selectedClassId.value = adminClasses.value[0].id
      await loadProgressReport()
    } else {
      selectedClassId.value = ''
      reportData.value = null
    }
  } catch (e) {
    toast.error('Không thể tải danh sách lớp học.')
  } finally {
    loading.value = false
  }
}

async function loadProgressReport() {
  if (!selectedClassId.value) {
    reportData.value = null
    return
  }
  reportLoading.value = true
  try {
    const res = await useApi<any>(
      `/admin/academic/lnd/reports/class-progress?administrative_class_id=${selectedClassId.value}`,
      { headers: headers() }
    )
    reportData.value = res
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra khi tải báo cáo tiến độ.')
    reportData.value = null
  } finally {
    reportLoading.value = false
  }
}

const filteredStudents = computed(() => {
  if (!reportData.value || !reportData.value.students) return []
  let list = reportData.value.students
  if (studentSearchQuery.value.trim()) {
    const q = studentSearchQuery.value.toLowerCase().trim()
    list = list.filter((s: any) => 
      s.name.toLowerCase().includes(q) || 
      s.student_code.toLowerCase().includes(q)
    )
  }
  return list
})
</script>

<template>
  <AdminWorkspaceShell
    title="Báo Cáo & Phân Tích L&D"
    description="Giám sát tiến độ học tập, tích lũy tín chỉ và cảnh báo học vụ theo lộ trình đào tạo"
    :breadcrumb="['Trang chủ', 'Đào tạo', 'Báo Cáo']"
  >

    <!-- Filters -->
    <div class="filter-card dashboard-card">
      <div class="filter-grid">
        <label class="crud-field">
          <span>Khóa đào tạo (Cohort)</span>
          <select v-model="selectedCohortId" :disabled="loading">
            <option v-for="c in cohorts" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>

        <label class="crud-field">
          <span>Lớp hành chính</span>
          <select v-model="selectedClassId" :disabled="loading || !selectedCohortId">
            <option value="">-- Chọn lớp hành chính --</option>
            <option v-for="c in adminClasses" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
          </select>
        </label>
      </div>
    </div>

    <!-- Main Reports Panel -->
    <div v-if="reportLoading" class="report-loading-box">
      <div class="shimmer-block banner"></div>
      <div class="shimmer-grid">
        <div v-for="i in 3" :key="i" class="shimmer-block kpi"></div>
      </div>
    </div>

    <div v-else-if="!selectedClassId" class="report-empty-box dashboard-card">
      <Building :size="40" class="text-muted" />
      <p>Vui lòng chọn lớp hành chính để xem báo cáo tiến trình đào tạo.</p>
    </div>

    <div v-else-if="reportData && !reportData.has_curriculum" class="report-empty-box dashboard-card">
      <AlertTriangle :size="40" class="text-warning" />
      <p>{{ reportData.message }}</p>
      <span class="text-small text-muted mt-5">Hãy gán lộ trình đào tạo cho lớp này trong mục Quản lý lớp học.</span>
    </div>

    <div v-else-if="reportData" class="report-layout-grid">
      <!-- KPI Stats -->
      <div class="kpi-row-grid">
        <!-- KPI 1 -->
        <div class="dashboard-card kpi-card-box">
          <div class="kpi-card-left">
            <span class="kpi-label">Sĩ số sinh viên</span>
            <strong class="kpi-number">{{ reportData.stats.total_students }}</strong>
            <span class="kpi-subtext">Học viên chính quy</span>
          </div>
          <Users :size="40" class="kpi-icon text-primary" />
        </div>

        <!-- KPI 2 -->
        <div class="dashboard-card kpi-card-box">
          <div class="kpi-card-left">
            <span class="kpi-label">Cần cảnh báo tiến độ</span>
            <strong class="kpi-number text-danger">{{ reportData.stats.at_risk_students }}</strong>
            <span class="kpi-subtext">Học viên chậm tiến độ &lt; 50%</span>
          </div>
          <AlertTriangle :size="40" class="kpi-icon text-danger" />
        </div>

        <!-- KPI 3 -->
        <div class="dashboard-card kpi-card-box">
          <div class="kpi-card-left">
            <span class="kpi-label">Tỷ lệ hoàn thành lớp</span>
            <strong class="kpi-number text-success">{{ reportData.stats.average_completion_rate }}%</strong>
            <span class="kpi-subtext">Đã hoàn thành các học phần</span>
          </div>
          <TrendingUp :size="40" class="kpi-icon text-success" />
        </div>
      </div>

      <!-- Class Info Banner -->
      <div class="dashboard-card class-info-banner">
        <div class="banner-summary">
          <div class="banner-item">
            <span class="b-lbl">Tên lộ trình gán lớp:</span>
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

      <!-- 2 Columns: Left is Student progress list, Right is Course-by-course metrics -->
      <div class="columns-wrap">
        <!-- Left: Student List -->
        <div class="dashboard-card column-item">
          <div class="column-header">
            <h3 class="column-title"><GraduationCap :size="18" /> Tiến độ của từng học viên</h3>
            <div class="search-wrap">
              <Search :size="14" class="search-icon" />
              <input type="text" v-model="studentSearchQuery" placeholder="Mã SV hoặc tên..." />
            </div>
          </div>

          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th>Mã SV</th>
                  <th>Họ và tên</th>
                  <th style="width: 140px;">Học phần đã đạt</th>
                  <th>Tiến độ trung bình</th>
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

        <!-- Right: Course Metrics -->
        <div class="dashboard-card column-item">
          <div class="column-header">
            <h3 class="column-title"><BarChart3 :size="18" /> Thống kê theo học phần</h3>
          </div>

          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th style="width: 80px;">Học kỳ</th>
                  <th>Tên học phần/Môn học</th>
                  <th style="width: 120px;">Đã ghi danh</th>
                  <th style="width: 120px;">Đã hoàn thành</th>
                  <th style="width: 120px;">Tỷ lệ đạt</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="reportData.courses.length === 0">
                  <td colspan="5" class="crud-empty">Lộ trình chưa được thiết lập môn học.</td>
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
                  <td>
                    <strong>{{ course.enrolled_students }}</strong> / {{ course.total_students }} SV
                  </td>
                  <td>
                    <strong class="text-success">{{ course.completed_students }}</strong> / {{ course.total_students }} SV
                  </td>
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
  </AdminWorkspaceShell>
</template>

<style scoped>
.lnd-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.filter-card {
  padding: 16px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  margin-bottom: 20px;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 640px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }
}

/* Loading state */
.report-loading-box {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shimmer-block {
  border-radius: 14px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmerAnim 1.5s infinite;
}

.shimmer-block.banner {
  height: 80px;
}

.shimmer-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.shimmer-block.kpi {
  height: 100px;
}

@keyframes shimmerAnim {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Empty State */
.report-empty-box {
  padding: 48px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.report-empty-box p {
  color: #475569;
  font-size: 0.95rem;
  font-weight: 600;
  margin: 0;
}

/* KPI Cards */
.report-layout-grid {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.kpi-row-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 768px) {
  .kpi-row-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card-box {
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-card-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kpi-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.kpi-number {
  font-size: 1.8rem;
  font-weight: 800;
  color: #1e293b;
  line-height: 1;
}

.kpi-subtext {
  font-size: 0.72rem;
  color: #64748b;
}

.kpi-icon {
  opacity: 0.8;
}

/* Class Info Banner */
.class-info-banner {
  padding: 16px 20px;
  background: #f8fafc;
}

.banner-summary {
  display: flex;
  gap: 32px;
  flex-wrap: wrap;
}

.banner-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.banner-item .b-lbl {
  font-size: 0.7rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
}

.banner-item .b-val {
  font-size: 0.9rem;
  font-weight: 700;
  color: #334155;
}

/* Columns wrap */
.columns-wrap {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 20px;
}

@media (max-width: 1024px) {
  .columns-wrap {
    grid-template-columns: 1fr;
  }
}

.column-item {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.column-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.search-wrap {
  position: relative;
  width: 180px;
}

.search-wrap .search-icon {
  position: absolute;
  left: 8px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-wrap input {
  width: 100%;
  padding: 5px 8px 5px 26px;
  font-size: 0.78rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  outline: none;
  background: #f8fafc;
}

.search-wrap input:focus {
  border-color: var(--green-deep, #047857);
  background: #fff;
}

/* Table detail stylings */
.student-code {
  font-family: monospace;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.78rem;
  color: #475569;
}

.credits-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.credits-box strong {
  color: var(--green-deep, #047857);
}

.credits-box small {
  font-size: 0.68rem;
}

.progress-bar-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.progress-track {
  flex: 1;
  height: 6px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #10b981;
  border-radius: 4px;
}

.progress-text {
  font-size: 0.72rem;
  font-weight: 700;
  color: #475569;
  width: 32px;
  text-align: right;
}

.alert-status-badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #f0fdf4;
  color: #15803d;
}

.alert-status-badge.is-danger {
  background: #fef2f2;
  color: #b91c1c;
  animation: pulseRisk 2s infinite;
}

@keyframes pulseRisk {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

.term-badge {
  background: #e2e8f0;
  color: #475569;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
}

.course-name-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.req-badge {
  font-size: 0.62rem;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 3px;
  align-self: flex-start;
  text-transform: uppercase;
}

.req-badge.required {
  background: #fef2f2;
  color: #b91c1c;
}

.req-badge.elective {
  background: #f0fdf4;
  color: #15803d;
}

.course-completion-badge {
  display: inline-block;
  padding: 2px 8px;
  font-weight: 700;
  font-size: 0.75rem;
  border-radius: 12px;
  background: #f1f5f9;
  color: #475569;
  text-align: center;
}

.course-completion-badge.high {
  background: #ecfdf5;
  color: #047857;
}

.mt-5 {
  margin-top: 5px;
}
</style>
