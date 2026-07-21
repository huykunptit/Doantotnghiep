<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ &bull; Sổ điểm</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">{{ sectionTitle || 'Đang tải...' }}</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">{{ data?.class_section ? `${data.class_section.term?.name} · Khóa ${data.class_section.cohort?.code}` : '' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink to="/instructor/sections" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <i class="pi pi-arrow-left text-xs" />
          <span>Danh sách lớp</span>
        </NuxtLink>
        <button class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50" :disabled="saving || loading" @click="save">
          <span class="material-symbols-outlined text-sm">save</span>
          {{ saving ? 'Đang lưu...' : 'Lưu điểm' }}
        </button>
      </div>
    </div>

    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>
    <div v-if="message" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs font-semibold">{{ message }}</div>

    <!-- Content Card -->
    <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
      <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex flex-col">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Bảng điểm</p>
        <h3 class="text-xs font-bold text-[var(--text)] mt-0.5">{{ data?.students.length || 0 }} sinh viên</h3>
      </div>

      <div v-if="loading" class="text-center py-12 text-xs text-[var(--muted)]">Đang tải bảng điểm...</div>
      <div v-else-if="!data?.components.length" class="text-center py-12 text-xs text-[var(--muted)] px-5">
        Học phần chưa có cấu trúc điểm. Liên hệ admin/khoa để khởi tạo grade_components.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
              <th class="px-5 py-3 w-16">STT</th>
              <th class="px-5 py-3 min-w-[120px]">Mã SV</th>
              <th class="px-5 py-3 min-w-[220px]">Họ tên</th>
              <th
                v-for="component in data.components"
                :key="component.id"
                class="px-5 py-3 text-center min-w-[120px]"
              >
                {{ component.name }}
                <div class="text-[10px] font-normal text-[var(--muted)] mt-0.5">/{{ component.max_score }} ({{ component.weight }}%)</div>
              </th>
              <th class="px-5 py-3 text-center min-w-[110px]">Tổng kết</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(stu, i) in data.students" :key="stu.enrollment_id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-5 py-4 text-xs font-semibold text-[var(--muted)]">{{ i + 1 }}</td>
              <td class="px-5 py-4"><code class="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">{{ stu.student.student_code || '--' }}</code></td>
              <td class="px-5 py-4 text-xs font-bold text-[var(--text)]">{{ stu.student.name }}</td>
              <td v-for="component in data.components" :key="component.id" class="px-5 py-4 text-center">
                <input
                  v-model="scoreMap[`${stu.enrollment_id}-${component.id}`]"
                  type="number"
                  step="0.1"
                  min="0"
                  :max="component.max_score"
                  class="w-20 h-8 px-2 border border-[var(--line)] rounded-xl text-center text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] font-semibold"
                >
              </td>
              <td class="px-5 py-4 text-center">
                <strong class="text-xs font-bold" :class="Number(previewFinal(stu)) >= 5 ? 'text-emerald-600' : 'text-red-600'">
                  {{ previewFinal(stu) }}
                </strong>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
