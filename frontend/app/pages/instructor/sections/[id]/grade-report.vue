<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface GradeComponent {
  id: number
  name: string
  weight: number
  max_score: number
  position: number
}
interface StudentGrade {
  enrollment_id: number
  student: { id: number; name: string; email: string; student_code: string | null }
  entries: Array<{
    component_id: number
    component_name: string
    weight: number
    max_score: number
    score: number | null
  }>
  final_score: number | null
  letter_grade: string | null
  gpa4: number | null
}
interface ReportResponse {
  class_section: any
  components: GradeComponent[]
  students: StudentGrade[]
  summary: {
    total: number
    passed: number
    failed: number
    avg_score: number | null
    class_gpa: number | null
  }
}

const route = useRoute()
const sectionId = Number(route.params.id)
const token = useAuthTokenCookie()
const headers = { Authorization: `Bearer ${token.value}` }

const data = ref<ReportResponse | null>(null)
const loading = ref(true)
const error = ref('')

// Grade components editor
const showComponentsEditor = ref(false)
const components = ref<Array<{ id?: number; name: string; weight: number; max_score: number; position: number }>>([])
const savingComponents = ref(false)
const componentError = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi<ReportResponse>(
      `/instructor/sections/${sectionId}/grade-report`, { headers }
    )
    components.value = data.value.components.map(c => ({ ...c }))
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải báo cáo điểm.'
  } finally {
    loading.value = false
  }
}

async function applyPreset() {
  const courseId = data.value?.class_section?.course_id
  if (!courseId) return
  try {
    await useApi(`/instructor/courses/${courseId}/grade-components/preset`, { method: 'POST', headers })
    await load()
    showComponentsEditor.value = true
  } catch (e: any) {
    componentError.value = e?.data?.message || 'Lỗi áp dụng mẫu.'
  }
}

function addComponent() {
  components.value.push({ name: '', weight: 0, max_score: 10, position: components.value.length + 1 })
}
function removeComponent(idx: number) {
  components.value.splice(idx, 1)
}

async function saveComponents() {
  const courseId = data.value?.class_section?.course_id
  if (!courseId) return
  const total = components.value.reduce((s, c) => s + Number(c.weight), 0)
  if (Math.abs(total - 100) > 0.01) {
    componentError.value = `Tổng trọng số phải bằng 100 (hiện tại: ${total})`
    return
  }
  savingComponents.value = true
  componentError.value = ''
  try {
    await useApi(`/instructor/courses/${courseId}/grade-components`, {
      method: 'PUT', headers,
      body: { components: components.value },
    })
    showComponentsEditor.value = false
    await load()
  } catch (e: any) {
    componentError.value = e?.data?.message || 'Lỗi lưu cấu trúc điểm.'
  } finally {
    savingComponents.value = false
  }
}

function letterClass(letter: string | null) {
  if (!letter) return 'text-gray-400'
  if (['A+', 'A'].includes(letter)) return 'text-green-600 font-semibold'
  if (['B+', 'B'].includes(letter)) return 'text-blue-600 font-semibold'
  if (['C+', 'C'].includes(letter)) return 'text-yellow-600 font-semibold'
  if (['D+', 'D'].includes(letter)) return 'text-orange-600 font-semibold'
  return 'text-red-600 font-semibold'
}

onMounted(load)
</script>

<template>
  <InstructorWorkspaceShell
    title="Báo cáo điểm & GPA"
    :description="data?.class_section ? `${data.class_section.course?.title} — ${data.class_section.term?.name}` : ''"
    :breadcrumb="['Trang chủ', 'Học vụ', 'Lớp học phần', 'Báo cáo điểm']"
  >
    <template #actions>
      <button class="crud-secondary-btn" type="button" @click="showComponentsEditor = !showComponentsEditor">
        <span class="material-symbols-outlined">tune</span>
        Cấu trúc điểm
      </button>
      <NuxtLink :to="`/instructor/sections/${sectionId}/grades`" class="crud-primary-btn">
        <span class="material-symbols-outlined">grading</span>
        Nhập điểm
      </NuxtLink>
    </template>

    <div v-if="loading" class="dashboard-card crud-panel">
      <div class="crud-empty" style="padding:3rem;">Đang tải...</div>
    </div>
    <div v-else-if="error" class="crud-alert is-error">{{ error }}</div>

    <template v-else-if="data">
      <!-- KPI -->
      <div class="ds-stats mb-0">
        <div class="ds-stat ds-stat--blue">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">people</span></div>
          <p class="ds-stat-label">Tổng SV</p>
          <strong class="ds-stat-value">{{ data.summary.total }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
        <div class="ds-stat ds-stat--green">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">check_circle</span></div>
          <p class="ds-stat-label">Đạt</p>
          <strong class="ds-stat-value">{{ data.summary.passed }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
        <div class="ds-stat ds-stat--red">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">cancel</span></div>
          <p class="ds-stat-label">Không đạt</p>
          <strong class="ds-stat-value">{{ data.summary.failed }}</strong>
          <span class="ds-stat-sub">sinh viên</span>
        </div>
        <div class="ds-stat ds-stat--blue">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">avg_pace</span></div>
          <p class="ds-stat-label">Điểm TB</p>
          <strong class="ds-stat-value">{{ data.summary.avg_score?.toFixed(1) ?? '—' }}</strong>
          <span class="ds-stat-sub">thang 10</span>
        </div>
        <div class="ds-stat ds-stat--violet">
          <div class="ds-stat-icon"><span class="material-symbols-outlined">school</span></div>
          <p class="ds-stat-label">GPA lớp</p>
          <strong class="ds-stat-value">{{ data.summary.class_gpa?.toFixed(2) ?? '—' }}</strong>
          <span class="ds-stat-sub">thang 4</span>
        </div>
      </div>

      <!-- Grade Components Editor -->
      <div v-if="showComponentsEditor" class="dashboard-card">
        <div class="crud-toolbar mb-4">
          <div>
            <p class="section-kicker">Cấu hình</p>
            <h3 class="ds-section-title">Cấu trúc đầu điểm</h3>
          </div>
          <button
            v-if="components.length === 0"
            class="crud-secondary-btn"
            type="button"
            @click="applyPreset"
          >
            <span class="material-symbols-outlined">auto_fix_high</span>
            Áp dụng mẫu mặc định
          </button>
        </div>
        <div class="component-grid-head">
          <span>Tên đầu điểm</span>
          <span>Trọng số (%)</span>
          <span>Điểm tối đa</span>
          <span></span>
        </div>
        <div v-for="(c, i) in components" :key="i" class="component-grid-row">
          <input v-model="c.name" placeholder="VD: Cuối kỳ" class="form-input" />
          <input v-model.number="c.weight" type="number" min="0" max="100" class="form-input" />
          <input v-model.number="c.max_score" type="number" min="1" class="form-input" />
          <button class="ds-btn ds-btn--delete" type="button" @click="removeComponent(i)">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="component-footer">
          <button class="crud-secondary-btn" type="button" @click="addComponent">
            <span class="material-symbols-outlined">add</span> Thêm đầu điểm
          </button>
          <span class="text-muted text-sm">Tổng: {{ components.reduce((s, c) => s + Number(c.weight), 0) }}%</span>
        </div>
        <div v-if="componentError" class="crud-alert is-error mt-3">{{ componentError }}</div>
        <div class="component-actions">
          <button class="crud-secondary-btn" type="button" @click="showComponentsEditor = false">Huỷ</button>
          <button class="crud-primary-btn" :disabled="savingComponents" type="button" @click="saveComponents">
            <span class="material-symbols-outlined">save</span>
            {{ savingComponents ? 'Đang lưu...' : 'Lưu cấu trúc' }}
          </button>
        </div>
      </div>

      <!-- No components warning -->
      <div v-if="data.components.length === 0" class="crud-alert is-warning">
        Chưa có cấu trúc đầu điểm.
        <button class="ml-1 font-semibold underline" @click="showComponentsEditor = true">Thiết lập ngay</button>
        hoặc
        <button class="ml-1 font-semibold underline" @click="applyPreset">áp dụng mẫu mặc định</button>
        (Chuyên cần 10%, Giữa kỳ 20%, Kiểm tra/BTL 20%, Cuối kỳ 50%).
      </div>

      <!-- Grade table -->
      <div class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Bảng điểm</p>
            <h3 class="ds-section-title">Chi tiết điểm sinh viên</h3>
          </div>
        </div>
        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Sinh viên</th>
                <th>MSSV</th>
                <th
                  v-for="c in data.components"
                  :key="c.id"
                  class="text-center"
                >
                  {{ c.name }}
                  <div class="text-xs font-normal text-muted">({{ c.weight }}%)</div>
                </th>
                <th class="text-center">Điểm TK</th>
                <th class="text-center">Xếp loại</th>
                <th class="text-center">GPA</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in data.students" :key="s.enrollment_id">
                <td><strong>{{ s.student.name }}</strong></td>
                <td class="text-muted">{{ s.student.student_code ?? '—' }}</td>
                <td
                  v-for="entry in s.entries"
                  :key="entry.component_id"
                  class="text-center"
                >
                  {{ entry.score !== null ? Number(entry.score).toFixed(1) : '—' }}
                </td>
                <td class="text-center"><strong>{{ s.final_score !== null ? Number(s.final_score).toFixed(2) : '—' }}</strong></td>
                <td class="text-center">
                  <span :class="letterClass(s.letter_grade)">{{ s.letter_grade ?? '—' }}</span>
                </td>
                <td class="text-center text-muted">{{ s.gpa4?.toFixed(1) ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </InstructorWorkspaceShell>
</template>

<style scoped>
.text-center { text-align: center; }
.text-muted  { color: var(--muted); font-size: 0.85rem; }

.component-grid-head {
  display: grid;
  grid-template-columns: 1fr 140px 140px 40px;
  gap: 8px;
  padding: 0 4px 6px;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.component-grid-row {
  display: grid;
  grid-template-columns: 1fr 140px 140px 40px;
  gap: 8px;
  margin-bottom: 6px;
  align-items: center;
}
.form-input {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid var(--line);
  border-radius: 10px;
  font: inherit;
  font-size: 0.875rem;
  background: var(--bg);
  color: var(--text);
}
.form-input:focus {
  outline: none;
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.12);
}
.component-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10px;
}
.component-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

/* letter grade colors */
.text-green-600  { color: #16a34a; font-weight: 600; }
.text-blue-600   { color: #2563eb; font-weight: 600; }
.text-yellow-600 { color: #ca8a04; font-weight: 600; }
.text-orange-600 { color: #ea580c; font-weight: 600; }
.text-red-600    { color: #dc2626; font-weight: 600; }
.text-gray-400   { color: var(--muted); }
</style>
