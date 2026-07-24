<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const transcript = ref<any>(null)

onMounted(async () => {
  try {
    transcript.value = await useApi<any>('/me/transcript', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
  } finally {
    loading.value = false
  }
})

const gpa = computed(() => transcript.value?.overall_gpa ?? null)
const gpa4 = computed(() => transcript.value?.overall_gpa_4 ?? null)
const totalCredits = computed(() => transcript.value?.total_credits ?? 0)
const semesters = computed(() => transcript.value?.semesters ?? [])

function gpaTextColor(g: number | null) {
  if (g === null) return 'text-slate-500'
  if (g >= 3.6) return 'text-violet-600'
  if (g >= 3.2) return 'text-[#1d9e75]'
  if (g >= 2.5) return 'text-sky-600'
  if (g >= 2.0) return 'text-amber-600'
  return 'text-red-600'
}
function gpaLabel(g: number | null) {
  if (g === null) return '—'
  if (g >= 3.6) return 'Xuất sắc'
  if (g >= 3.2) return 'Giỏi'
  if (g >= 2.5) return 'Khá'
  if (g >= 2.0) return 'Trung bình'
  return 'Yếu'
}
function gradeClass(score: number | null) {
  if (score === null) return 'bg-slate-100 text-slate-600 border-slate-200'
  if (score >= 8.5) return 'bg-emerald-50 text-emerald-600 border-emerald-100'
  if (score >= 7.0) return 'bg-sky-50 text-sky-600 border-sky-100'
  if (score >= 5.5) return 'bg-amber-50 text-amber-600 border-amber-100'
  return 'bg-red-50 text-red-600 border-red-100'
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ</p>
      <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Bảng điểm & GPA</h1>
    </div>

    <!-- Summary strip -->
    <div class="bg-white border border-[var(--line)] rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-center gap-6">
      <div class="flex items-center gap-4 flex-shrink-0">
        <div class="text-5xl font-black tracking-tight leading-none" :class="gpaTextColor(gpa4 ?? gpa)">
          <span v-if="loading">—</span>
          <span v-else>{{ (gpa4 ?? gpa) !== null ? (gpa4 ?? gpa).toFixed(2) : '—' }}</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-base font-extrabold" :class="gpaTextColor(gpa4 ?? gpa)">{{ gpaLabel(gpa4 ?? gpa) }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold">Điểm trung bình tích lũy (thang 4)</span>
        </div>
      </div>
      <div class="flex-1 grid grid-cols-3 gap-6 md:border-l border-[var(--line)] md:pl-6 pt-6 md:pt-0 border-t md:border-t-0 w-full">
        <div class="flex flex-col gap-0.5">
          <span class="text-lg font-extrabold text-[var(--text)]">{{ loading ? '—' : totalCredits }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Tín chỉ tích lũy</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-lg font-extrabold text-[var(--text)]">{{ loading ? '—' : semesters.length }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Học kỳ</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-lg font-extrabold text-[var(--text)]">
            {{ loading ? '—' : (gpa !== null ? gpa.toFixed(2) : '—') }}
          </span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Điểm TB (thang 10)</span>
        </div>
      </div>
    </div>

    <!-- Semester tables -->
    <div v-if="loading" class="flex flex-col gap-6 animate-pulse">
      <div v-for="i in 2" :key="i" class="h-64 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl" />
    </div>
    <div v-else-if="semesters.length === 0" class="flex flex-col items-center gap-4 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">graduation_cap</span>
      <p class="text-sm font-semibold text-[var(--muted)]">Chưa có dữ liệu bảng điểm.</p>
    </div>
    <div v-else class="flex flex-col gap-6">
      <div v-for="sem in semesters" :key="sem.id ?? sem.name" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-5 py-4 bg-[var(--surface)] border-b border-[var(--line)] flex justify-between items-center flex-wrap gap-3">
          <div class="flex items-center gap-2 text-slate-800">
            <span class="material-symbols-outlined text-base">arrow_upward</span>
            <h2 class="text-xs font-bold text-[var(--text)]">{{ sem.name }}</h2>
          </div>
          <div class="flex items-center gap-4 font-semibold text-xs text-[var(--muted)]">
            <span class="font-bold" :class="gpaTextColor(sem.gpa)">
              GPA: {{ sem.gpa !== null ? sem.gpa.toFixed(2) : '—' }}
            </span>
            <span>{{ sem.total_credits ?? 0 }} tín chỉ</span>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
                <th class="px-5 py-3">Môn học / Khóa học</th>
                <th class="px-5 py-3 text-center">Tín chỉ</th>
                <th class="px-5 py-3 text-center">Điểm GK</th>
                <th class="px-5 py-3 text-center">Điểm CK</th>
                <th class="px-5 py-3 text-center">Điểm TK</th>
                <th class="px-5 py-3 text-center">Điểm chữ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in sem.grades ?? []" :key="row.id ?? row.course_id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
                <td class="px-5 py-4"><strong class="text-xs font-bold text-[var(--text)]">{{ row.course?.title ?? row.subject_name ?? '—' }}</strong></td>
                <td class="px-5 py-4 text-center text-xs text-[var(--muted)] font-semibold">{{ row.credits ?? '—' }}</td>
                <td class="px-5 py-4 text-center text-xs text-[var(--muted)] font-semibold">{{ row.midterm_score ?? '—' }}</td>
                <td class="px-5 py-4 text-center text-xs text-[var(--muted)] font-semibold">{{ row.final_score ?? '—' }}</td>
                <td class="px-5 py-4 text-center">
                  <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-bold border" :class="gradeClass(row.total_score)">
                    {{ row.total_score !== null ? row.total_score : '—' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-center">
                  <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-bold border" :class="gradeClass(row.total_score)">{{ row.letter_grade ?? '—' }}</span>
                </td>
              </tr>
              <tr v-if="!sem.grades?.length">
                <td colspan="6" class="px-5 py-8 text-center text-xs text-[var(--muted)] italic">Chưa có điểm học kỳ này.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
