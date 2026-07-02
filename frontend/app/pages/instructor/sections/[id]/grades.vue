<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface GradeComponent {
  id: number
  name: string
  weight: number | string
  max_score: number | string
  position: number
}
interface StudentRow {
  enrollment_id: number
  student: { id: number; name: string; email: string; student_code: string | null }
  final_score: number | null
  entries: Array<{
    grade_component_id: number
    score: number | string | null
    note?: string | null
    graded_at?: string | null
  }>
}
interface GradebookResponse {
  class_section: any
  components: GradeComponent[]
  students: StudentRow[]
}

const route = useRoute()
const sectionId = Number(route.params.id)
const token = useAuthTokenCookie()

const data = ref<GradebookResponse | null>(null)
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const error = ref('')

// Map: enrollment_id_component_id -> string score (raw input)
const scoreMap = reactive<Record<string, string>>({})

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi<GradebookResponse>(`/instructor/sections/${sectionId}/grades`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    Object.keys(scoreMap).forEach((k) => delete scoreMap[k])
    data.value.students.forEach((stu) => {
      stu.entries.forEach((entry) => {
        scoreMap[`${stu.enrollment_id}-${entry.grade_component_id}`] = entry.score === null || entry.score === undefined ? '' : String(entry.score)
      })
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải bảng điểm.'
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!data.value) return
  saving.value = true
  message.value = ''
  error.value = ''
  try {
    const entries: Array<{ enrollment_id: number; grade_component_id: number; score: number | null }> = []
    data.value.students.forEach((stu) => {
      stu.entries.forEach((entry) => {
        const key = `${stu.enrollment_id}-${entry.grade_component_id}`
        const raw = scoreMap[key]
        const num = raw === '' ? null : Number(raw)
        entries.push({ enrollment_id: stu.enrollment_id, grade_component_id: entry.grade_component_id, score: num })
      })
    })

    const result = await useApi<{ message: string; written: number }>(`/instructor/sections/${sectionId}/grades`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: { entries },
    })
    message.value = `${result.message} (${result.written} bản ghi)`
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu điểm.'
  } finally {
    saving.value = false
  }
}

function previewFinal(stu: StudentRow): string {
  if (!data.value) return '--'
  let weightSum = 0
  let weighted = 0
  for (const c of data.value.components) {
    const raw = scoreMap[`${stu.enrollment_id}-${c.id}`]
    if (raw === '' || raw === undefined) continue
    const score = Number(raw)
    const max = Number(c.max_score) || 10
    const w = Number(c.weight) || 0
    weighted += (score / max) * 10 * w
    weightSum += w
  }
  return weightSum > 0 ? (weighted / weightSum).toFixed(2) : '--'
}

const sectionTitle = computed(() => {
  if (!data.value) return ''
  const cs = data.value.class_section
  return `${cs.code} — ${cs.course?.title}`
})

onMounted(load)
</script>

<template>
  <InstructorWorkspaceShell
    :title="sectionTitle || 'Đang tải...'"
    :description="data?.class_section ? `${data.class_section.term?.name} · Khóa ${data.class_section.cohort?.code}` : ''"
    :breadcrumb="['Trang chủ', 'Học vụ', 'Lớp học phần', 'Sổ điểm']"
  >
    <template #actions>
      <NuxtLink to="/instructor/sections" class="crud-secondary-btn">
        <span class="material-symbols-outlined">arrow_back</span>
        Danh sách lớp
      </NuxtLink>
      <button class="crud-primary-btn" :disabled="saving || loading" @click="save">
        <span class="material-symbols-outlined">save</span>
        {{ saving ? 'Đang lưu...' : 'Lưu điểm' }}
      </button>
    </template>

    <div v-if="error" class="crud-alert is-error">{{ error }}</div>
    <div v-if="message" class="crud-alert is-success">{{ message }}</div>

    <div class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Bảng điểm</p>
          <h3 class="ds-section-title">{{ data?.students.length || 0 }} sinh viên</h3>
        </div>
      </div>

      <div v-if="loading" class="crud-empty" style="padding:3rem;">Đang tải bảng điểm...</div>
      <div v-else-if="!data?.components.length" class="crud-empty" style="padding:3rem;">
        Học phần chưa có cấu trúc điểm. Liên hệ admin/khoa để khởi tạo grade_components.
      </div>
      <div v-else class="crud-table-wrap">
        <table class="crud-table gradebook-table">
          <thead>
            <tr>
              <th style="min-width:60px">STT</th>
              <th style="min-width:120px">Mã SV</th>
              <th style="min-width:220px">Họ tên</th>
              <th
                v-for="component in data.components"
                :key="component.id"
                class="text-center"
                style="min-width:120px"
              >
                {{ component.name }}
                <p class="text-xs font-normal text-muted">/{{ component.max_score }} ({{ component.weight }}%)</p>
              </th>
              <th class="text-center" style="min-width:110px">Tổng kết</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(stu, i) in data.students" :key="stu.enrollment_id">
              <td>{{ i + 1 }}</td>
              <td><code>{{ stu.student.student_code || '--' }}</code></td>
              <td>{{ stu.student.name }}</td>
              <td v-for="component in data.components" :key="component.id" class="text-center">
                <input
                  v-model="scoreMap[`${stu.enrollment_id}-${component.id}`]"
                  type="number"
                  step="0.1"
                  min="0"
                  :max="component.max_score"
                  class="grade-input"
                >
              </td>
              <td class="text-center">
                <strong :class="Number(previewFinal(stu)) >= 5 ? 'final-pass' : 'final-fail'">
                  {{ previewFinal(stu) }}
                </strong>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </InstructorWorkspaceShell>
</template>

<style scoped>
.gradebook-table { width: 100%; }
.gradebook-table th { font-size: 0.85rem; }
.grade-input {
  width: 80px;
  padding: 6px 8px;
  border: 1px solid rgba(17,17,17,0.12);
  border-radius: 8px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.grade-input:focus {
  outline: none;
  border-color: rgba(var(--green-rgb), 0.4);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.1);
}
.final-pass { color: var(--green-deep); font-size: 1.05rem; }
.final-fail { color: #b91c1c; font-size: 1.05rem; }
.text-center { text-align: center; }
.text-muted { color: var(--muted); }
</style>
