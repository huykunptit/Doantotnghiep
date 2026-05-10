<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'default' })

interface TranscriptResponse {
  student: any
  overall_gpa: number | null
  terms: Array<{
    term: { id: number; name: string; code: string }
    gpa: number | null
    credits: number
    courses: Array<{
      enrollment_id: number
      course: { id: number; title: string; course_mode: string; credit_value: number | null }
      class_section?: { code: string; lecturer?: { name: string } }
      enrollment_source: string
      final_score: number | null
      entries: Array<{ component: string; weight: number; max_score: number; score: number | null }>
    }>
  }>
}

const token = useAuthTokenCookie()
const data = ref<TranscriptResponse | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi('/me/transcript', {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải bảng điểm.'
  } finally {
    loading.value = false
  }
}

const totalCredits = computed(() => data.value?.terms.reduce((sum, t) => sum + (t.credits || 0), 0) || 0)
const totalCourses = computed(() => data.value?.terms.reduce((sum, t) => sum + t.courses.length, 0) || 0)

function fmtScore(s: number | null) {
  if (s === null || s === undefined) return '—'
  return s.toFixed(2)
}

onMounted(load)
</script>

<template>
  <main class="container mx-auto max-w-6xl space-y-6 p-6">
    <header class="space-y-1">
      <p class="text-sm font-semibold text-on-surface-variant">Sinh viên</p>
      <h1 class="text-2xl font-bold">Bảng điểm tổng hợp</h1>
      <p v-if="data?.student" class="text-sm text-muted">
        {{ data.student.name }} · MSSV <code>{{ data.student.student_code || '--' }}</code>
      </p>
    </header>

    <div v-if="error" class="crud-alert is-error">{{ error }}</div>

    <div class="grid gap-4 md:grid-cols-3">
      <article class="dashboard-card stat-card">
        <p class="stat-label">GPA tổng</p>
        <strong class="stat-value">{{ data?.overall_gpa !== null && data?.overall_gpa !== undefined ? fmtScore(data.overall_gpa) : '—' }}</strong>
      </article>
      <article class="dashboard-card stat-card">
        <p class="stat-label">Tín chỉ tích luỹ</p>
        <strong class="stat-value">{{ totalCredits }}</strong>
      </article>
      <article class="dashboard-card stat-card">
        <p class="stat-label">Học phần đã ghi danh</p>
        <strong class="stat-value">{{ totalCourses }}</strong>
      </article>
    </div>

    <div v-if="loading" class="dashboard-card crud-empty">Đang tải bảng điểm...</div>
    <div v-else-if="!data?.terms.length" class="dashboard-card crud-empty">Chưa có bảng điểm.</div>

    <section
      v-for="term in data?.terms || []"
      :key="term.term?.id"
      class="dashboard-card"
    >
      <header class="term-head">
        <div>
          <p class="section-kicker">Kỳ {{ term.term?.code }}</p>
          <h3>{{ term.term?.name }}</h3>
        </div>
        <div class="term-stats">
          <span><strong>GPA:</strong> {{ fmtScore(term.gpa) }}</span>
          <span><strong>Tín chỉ:</strong> {{ term.credits }}</span>
        </div>
      </header>

      <table class="crud-table">
        <thead>
          <tr>
            <th>Học phần</th>
            <th>Lớp</th>
            <th>Loại</th>
            <th class="text-center">Tín chỉ</th>
            <th class="text-center">Điểm tổng kết (10)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="course in term.courses" :key="course.enrollment_id">
            <td>
              <strong>{{ course.course.title }}</strong>
              <p v-if="course.entries.length" class="text-xs text-muted">
                <span v-for="e in course.entries" :key="e.component" class="entry-pill">
                  {{ e.component }}: {{ fmtScore(e.score) }}/{{ e.max_score }}
                </span>
              </p>
            </td>
            <td><code>{{ course.class_section?.code || '--' }}</code></td>
            <td>
              <span v-if="course.course.course_mode === 'core'" class="chip-core">Chính quy</span>
              <span v-else class="chip-ext">Mở rộng</span>
            </td>
            <td class="text-center">{{ course.course.credit_value || '--' }}</td>
            <td class="text-center">
              <strong :class="course.final_score !== null && course.final_score >= 5 ? 'final-pass' : 'final-fail'">
                {{ fmtScore(course.final_score) }}
              </strong>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </main>
</template>

<style scoped>
.stat-card { padding: 18px 20px; }
.stat-label { color: var(--muted); font-size: 0.85rem; margin: 0; }
.stat-value { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; }

.term-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}
.term-head h3 { margin: 4px 0 0; font-size: 1.1rem; }
.term-stats { display: flex; gap: 16px; color: var(--muted); font-size: 0.92rem; }

.chip-core, .chip-ext {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}
.chip-core { background: rgba(var(--green-rgb), 0.12); color: var(--green-deep); }
.chip-ext { background: rgba(217, 119, 6, 0.12); color: #b45309; }

.entry-pill {
  display: inline-block;
  margin-right: 6px;
  padding: 1px 6px;
  background: rgba(17, 17, 17, 0.05);
  border-radius: 6px;
  font-size: 0.72rem;
}

.final-pass { color: var(--green-deep); font-size: 1.05rem; }
.final-fail { color: #b91c1c; font-size: 1.05rem; }

.text-muted { color: var(--muted); }
.text-center { text-align: center; }
</style>
