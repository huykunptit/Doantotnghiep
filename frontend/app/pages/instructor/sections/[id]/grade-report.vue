<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ &bull; Báo cáo điểm</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Báo cáo điểm & GPA</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">{{ data?.class_section ? `${data.class_section.course?.title} — ${data.class_section.term?.name}` : '' }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" type="button" @click="showComponentsEditor = !showComponentsEditor">
          <span class="material-symbols-outlined text-sm">tune</span>
          Cấu trúc điểm
        </button>
        <NuxtLink :to="`/instructor/sections/${sectionId}/grades`" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors">
          <span class="material-symbols-outlined text-sm">grading</span>
          Nhập điểm
        </NuxtLink>
      </div>
    </div>

    <div v-if="loading" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm text-center py-12 text-xs text-[var(--muted)]">
      Đang tải...
    </div>
    <div v-else-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ error }}</div>

    <template v-else-if="data">
      <!-- KPI -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
            <span class="material-symbols-outlined text-lg">people</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng SV</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.total }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
            <span class="material-symbols-outlined text-lg">check_circle</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Đạt</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.passed }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-red-50 text-red-600">
            <span class="material-symbols-outlined text-lg">cancel</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Không đạt</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.failed }}</strong>
            <span class="text-[9px] text-[var(--muted)]">sinh viên</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
            <span class="material-symbols-outlined text-lg">avg_pace</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Điểm TB</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.avg_score?.toFixed(1) ?? '—' }}</strong>
            <span class="text-[9px] text-[var(--muted)]">thang 10</span>
          </div>
        </div>
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-violet-50 text-violet-600">
            <span class="material-symbols-outlined text-lg">school</span>
          </div>
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">GPA lớp</p>
            <strong class="text-base font-extrabold text-[var(--text)] block mt-0.5">{{ data.summary.class_gpa?.toFixed(2) ?? '—' }}</strong>
            <span class="text-[9px] text-[var(--muted)]">thang 4</span>
          </div>
        </div>
      </div>

      <!-- Grade Components Editor -->
      <div v-if="showComponentsEditor" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
        <div class="flex justify-between items-center border-b border-[var(--line)] pb-3">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Cấu hình</p>
            <h3 class="text-sm font-bold text-[var(--text)] mt-0.5">Cấu trúc đầu điểm</h3>
          </div>
          <button
            v-if="components.length === 0"
            class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors"
            type="button"
            @click="applyPreset"
          >
            <span class="material-symbols-outlined text-sm">auto_fix_high</span>
            Áp dụng mẫu mặc định
          </button>
        </div>
        <div class="grid grid-cols-[1fr_140px_140px_40px] gap-2 text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] px-1">
          <span>Tên đầu điểm</span>
          <span>Trọng số (%)</span>
          <span>Điểm tối đa</span>
          <span></span>
        </div>
        <div v-for="(c, i) in components" :key="i" class="grid grid-cols-[1fr_140px_140px_40px] gap-2 items-center">
          <input v-model="c.name" placeholder="VD: Cuối kỳ" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
          <input v-model.number="c.weight" type="number" min="0" max="100" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
          <input v-model.number="c.max_score" type="number" min="1" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-[var(--surface)] text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
          <button class="w-8 h-8 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 flex items-center justify-center transition-colors" type="button" @click="removeComponent(i)">
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>
        <div class="flex justify-between items-center border-t border-[var(--line)] pt-3">
          <button class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" type="button" @click="addComponent">
            <span class="material-symbols-outlined text-sm">add</span> Thêm đầu điểm
          </button>
          <span class="text-xs text-[var(--muted)] font-bold">Tổng: {{ components.reduce((s, c) => s + Number(c.weight), 0) }}%</span>
        </div>
        <div v-if="componentError" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs font-semibold">{{ componentError }}</div>
        <div class="flex justify-end gap-2 border-t border-[var(--line)] pt-3">
          <button class="h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" type="button" @click="showComponentsEditor = false">Huỷ</button>
          <button class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors" :disabled="savingComponents" type="button" @click="saveComponents">
            <span class="material-symbols-outlined text-sm">save</span>
            {{ savingComponents ? 'Đang lưu...' : 'Lưu cấu trúc' }}
          </button>
        </div>
      </div>

      <!-- No components warning -->
      <div v-if="data.components.length === 0" class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-xs font-semibold">
        Chưa có cấu trúc đầu điểm.
        <button class="ml-1 font-bold underline text-[#b45309]" @click="showComponentsEditor = true">Thiết lập ngay</button>
        hoặc
        <button class="ml-1 font-bold underline text-[#b45309]" @click="applyPreset">áp dụng mẫu mặc định</button>
        (Chuyên cần 10%, Giữa kỳ 20%, Kiểm tra/BTL 20%, Cuối kỳ 50%).
      </div>

      <!-- Grade table -->
      <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex flex-col">
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Bảng điểm</p>
          <h3 class="text-xs font-bold text-[var(--text)] mt-0.5">Chi tiết điểm sinh viên</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
                <th class="px-5 py-3">Sinh viên</th>
                <th class="px-5 py-3">MSSV</th>
                <th
                  v-for="c in data.components"
                  :key="c.id"
                  class="px-5 py-3 text-center"
                >
                  {{ c.name }}
                  <div class="text-[10px] font-normal text-[var(--muted)] mt-0.5">({{ c.weight }}%)</div>
                </th>
                <th class="px-5 py-3 text-center">Điểm TK</th>
                <th class="px-5 py-3 text-center">Xếp loại</th>
                <th class="px-5 py-3 text-center">GPA</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in data.students" :key="s.enrollment_id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
                <td class="px-5 py-4"><strong class="text-xs font-bold text-[var(--text)]">{{ s.student.name }}</strong></td>
                <td class="px-5 py-4 text-xs text-[var(--muted)] font-semibold">{{ s.student.student_code ?? '—' }}</td>
                <td
                  v-for="entry in s.entries"
                  :key="entry.component_id"
                  class="px-5 py-4 text-center text-xs font-semibold text-[var(--text)]"
                >
                  {{ entry.score !== null ? Number(entry.score).toFixed(1) : '—' }}
                </td>
                <td class="px-5 py-4 text-center text-xs font-bold text-[var(--text)]">{{ s.final_score !== null ? Number(s.final_score).toFixed(2) : '—' }}</td>
                <td class="px-5 py-4 text-center">
                  <span class="text-xs font-bold" :class="letterClass(s.letter_grade)">{{ s.letter_grade ?? '—' }}</span>
                </td>
                <td class="px-5 py-4 text-center text-xs text-[var(--muted)] font-semibold">{{ s.gpa4?.toFixed(1) ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
