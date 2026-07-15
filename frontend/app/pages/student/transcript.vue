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

function gpaColor(g: number | null) {
  if (g === null) return '#6b7280'
  if (g >= 3.6) return '#7c3aed'
  if (g >= 3.2) return '#1D9E75'
  if (g >= 2.5) return '#2563eb'
  if (g >= 2.0) return '#d97706'
  return '#dc2626'
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
  if (score === null) return ''
  if (score >= 8.5) return 'grade-a'
  if (score >= 7.0) return 'grade-b'
  if (score >= 5.5) return 'grade-c'
  return 'grade-d'
}
</script>

<template>
  <div class="tr-page">
    <div class="tr-header">
      <p class="tr-kicker">Học vụ</p>
      <h1 class="tr-title">Bảng điểm & GPA</h1>
    </div>

    <!-- Summary strip -->
    <div class="tr-summary">
      <div class="tr-gpa-hero">
        <div class="tr-gpa-score" :style="{ color: gpaColor(gpa4 ?? gpa) }">
          <span v-if="loading">—</span>
          <span v-else>{{ (gpa4 ?? gpa) !== null ? (gpa4 ?? gpa).toFixed(2) : '—' }}</span>
        </div>
        <div class="tr-gpa-meta">
          <span class="tr-gpa-label" :style="{ color: gpaColor(gpa4 ?? gpa) }">{{ gpaLabel(gpa4 ?? gpa) }}</span>
          <span class="tr-gpa-sub">Điểm trung bình tích lũy (thang 4)</span>
        </div>
      </div>
      <div class="tr-stat-cards">
        <div class="tr-stat">
          <span class="tr-stat-val" :class="loading ? 'tr-skeleton-inline' : ''">{{ loading ? '' : totalCredits }}</span>
          <span class="tr-stat-lbl">Tín chỉ tích lũy</span>
        </div>
        <div class="tr-stat">
          <span class="tr-stat-val">{{ loading ? '' : semesters.length }}</span>
          <span class="tr-stat-lbl">Học kỳ</span>
        </div>
        <div class="tr-stat">
          <span class="tr-stat-val">
            {{ loading ? '' : (gpa !== null ? gpa.toFixed(2) : '—') }}
          </span>
          <span class="tr-stat-lbl">Điểm TB (thang 10)</span>
        </div>
      </div>
    </div>

    <!-- Semester tables -->
    <div v-if="loading" class="tr-skeletons">
      <div v-for="i in 2" :key="i" class="tr-skeleton-block" />
    </div>
    <div v-else-if="semesters.length === 0" class="tr-empty">
      <i class="pi pi-graduation-cap" style="font-size:2.75rem" />
      <p>Chưa có dữ liệu bảng điểm.</p>
    </div>
    <div v-else class="tr-semesters">
      <div v-for="sem in semesters" :key="sem.id ?? sem.name" class="tr-semester">
        <div class="tr-sem-head">
          <div class="tr-sem-title-wrap">
            <i class="pi pi-arrow-up" style="font-size:1.0rem" />
            <h2 class="tr-sem-title">{{ sem.name }}</h2>
          </div>
          <div class="tr-sem-meta">
            <span class="tr-sem-gpa-badge" :style="{ color: gpaColor(sem.gpa) }">
              GPA: {{ sem.gpa !== null ? sem.gpa.toFixed(2) : '—' }}
            </span>
            <span class="tr-sem-credits">{{ sem.total_credits ?? 0 }} tín chỉ</span>
          </div>
        </div>
        <div class="tr-table-wrap">
          <table class="tr-table">
            <thead>
              <tr>
                <th>Môn học / Khóa học</th>
                <th>Tín chỉ</th>
                <th>Điểm GK</th>
                <th>Điểm CK</th>
                <th>Điểm TK</th>
                <th>Điểm chữ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in sem.grades ?? []" :key="row.id ?? row.course_id">
                <td class="tr-td-name">{{ row.course?.title ?? row.subject_name ?? '—' }}</td>
                <td class="tr-td-center">{{ row.credits ?? '—' }}</td>
                <td class="tr-td-center">{{ row.midterm_score ?? '—' }}</td>
                <td class="tr-td-center">{{ row.final_score ?? '—' }}</td>
                <td class="tr-td-center">
                  <span class="tr-score" :class="gradeClass(row.total_score)">
                    {{ row.total_score !== null ? row.total_score : '—' }}
                  </span>
                </td>
                <td class="tr-td-center">
                  <span class="tr-letter" :class="gradeClass(row.total_score)">{{ row.letter_grade ?? '—' }}</span>
                </td>
              </tr>
              <tr v-if="!sem.grades?.length">
                <td colspan="6" class="tr-td-empty">Chưa có điểm học kỳ này.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tr-page { max-width: 1100px; margin: 0 auto; }
.tr-header { margin-bottom: 24px; }
.tr-kicker { margin: 0 0 4px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
.tr-title { margin: 0; font-size: 1.7rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }

/* Summary */
.tr-summary {
  display: flex; align-items: center; gap: 32px;
  background: var(--surface-strong); border: 1px solid var(--line);
  border-radius: 20px; padding: 24px 28px; margin-bottom: 28px;
}
.tr-gpa-hero { display: flex; align-items: center; gap: 16px; flex-shrink: 0; }
.tr-gpa-score { font-size: 4rem; font-weight: 900; letter-spacing: -0.06em; line-height: 1; }
.tr-gpa-meta { display: flex; flex-direction: column; gap: 4px; }
.tr-gpa-label { font-size: 1.1rem; font-weight: 800; }
.tr-gpa-sub { font-size: 0.75rem; color: var(--muted); }
.tr-stat-cards { display: flex; gap: 32px; border-left: 1px solid var(--line); padding-left: 32px; margin-left: 8px; }
.tr-stat { display: flex; flex-direction: column; gap: 2px; }
.tr-stat-val { font-size: 1.6rem; font-weight: 800; color: var(--text); letter-spacing: -0.03em; line-height: 1; }
.tr-stat-lbl { font-size: 0.72rem; font-weight: 600; color: var(--muted); }
.tr-skeleton-inline { display: block; width: 48px; height: 28px; border-radius: 6px; background: var(--line); animation: shimmer 1.4s ease-in-out infinite; }

/* Semesters */
.tr-semesters { display: flex; flex-direction: column; gap: 24px; }
.tr-semester {
  background: var(--surface-strong); border: 1px solid var(--line);
  border-radius: 16px; overflow: hidden;
}
.tr-sem-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--line);
  background: var(--bg);
}
.tr-sem-title-wrap { display: flex; align-items: center; gap: 8px; color: var(--muted); }
.tr-sem-title { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--text); }
.tr-sem-meta { display: flex; align-items: center; gap: 16px; }
.tr-sem-gpa-badge { font-size: 0.875rem; font-weight: 800; }
.tr-sem-credits { font-size: 0.78rem; color: var(--muted); font-weight: 600; }

/* Table */
.tr-table-wrap { overflow-x: auto; }
.tr-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.tr-table th {
  padding: 10px 16px; text-align: left;
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--muted); border-bottom: 1px solid var(--line);
  white-space: nowrap;
}
.tr-table td { padding: 12px 16px; border-bottom: 1px solid var(--line); }
.tr-table tr:last-child td { border-bottom: none; }
.tr-table tr:hover td { background: var(--bg); }
.tr-td-name { font-weight: 600; color: var(--text); }
.tr-td-center { text-align: center; color: var(--muted); }
.tr-td-empty { text-align: center; color: var(--muted); padding: 20px; font-size: 0.84rem; }

.tr-score, .tr-letter {
  display: inline-block; padding: 2px 10px; border-radius: 6px;
  font-weight: 800; font-size: 0.84rem;
}
.grade-a { background: rgba(29,158,117,0.1); color: var(--green-deep); }
.grade-b { background: rgba(37,99,235,0.08); color: #1d4ed8; }
.grade-c { background: rgba(234,179,8,0.1); color: #b45309; }
.grade-d { background: rgba(220,38,38,0.08); color: #b91c1c; }

.tr-skeletons { display: flex; flex-direction: column; gap: 20px; }
.tr-skeleton-block {
  height: 200px; border-radius: 16px;
  background: linear-gradient(90deg, var(--line) 25%, rgba(221,229,225,0.5) 50%, var(--line) 75%);
  background-size: 200% 100%; animation: shimmer 1.4s ease-in-out infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

.tr-empty {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 60px; color: var(--muted); text-align: center;
}
.tr-empty p { margin: 0; font-size: 0.9rem; }

[data-theme="dark"] .tr-summary,
[data-theme="dark"] .tr-semester { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07); }
[data-theme="dark"] .tr-sem-head { background: rgba(255,255,255,0.02); }
[data-theme="dark"] .tr-table tr:hover td { background: rgba(255,255,255,0.03); }

@media (max-width: 640px) {
  .tr-summary { flex-direction: column; align-items: flex-start; gap: 20px; }
  .tr-stat-cards { border-left: none; padding-left: 0; margin-left: 0; border-top: 1px solid var(--line); padding-top: 20px; }
}
</style>
