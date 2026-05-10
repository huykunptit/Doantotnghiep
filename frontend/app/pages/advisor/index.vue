<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'default' })

interface AdviseeRow {
  student: {
    id: number
    name: string
    email: string
    student_code: string | null
    cohort?: { name: string; code: string }
    major?: { name: string; code: string }
  }
  enrollments: number
  graded: number
  avg_score: number | null
  is_at_risk: boolean
}
interface AdviseesResponse {
  advisor_id: number
  current_term: any
  advisees: AdviseeRow[]
  totals: { count: number; at_risk: number; avg_gpa: number | null }
}

const token = useAuthTokenCookie()
const data = ref<AdviseesResponse | null>(null)
const loading = ref(true)
const error = ref('')
const showOnlyRisk = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi('/advisor/advisees', {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải danh sách advisees.'
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  if (!data.value) return []
  return showOnlyRisk.value ? data.value.advisees.filter((r) => r.is_at_risk) : data.value.advisees
})

function fmtScore(s: number | null) {
  if (s === null || s === undefined) return '—'
  return s.toFixed(2)
}

onMounted(load)
</script>

<template>
  <main class="container mx-auto max-w-6xl space-y-6 p-6">
    <header class="space-y-1">
      <p class="text-sm font-semibold text-on-surface-variant">Cố vấn học tập</p>
      <h1 class="text-2xl font-bold">Sinh viên tôi phụ trách</h1>
      <p v-if="data?.current_term" class="text-sm text-muted">
        Kỳ hiện hành: <strong>{{ data.current_term.name }}</strong>
      </p>
    </header>

    <div v-if="error" class="crud-alert is-error">{{ error }}</div>

    <div class="grid gap-4 md:grid-cols-3">
      <article class="dashboard-card stat-card">
        <p class="stat-label">Sinh viên phụ trách</p>
        <strong class="stat-value">{{ data?.totals.count || 0 }}</strong>
      </article>
      <article class="dashboard-card stat-card stat-risk">
        <p class="stat-label">Cảnh báo rớt môn</p>
        <strong class="stat-value">{{ data?.totals.at_risk || 0 }}</strong>
      </article>
      <article class="dashboard-card stat-card">
        <p class="stat-label">GPA trung bình nhóm</p>
        <strong class="stat-value">{{ fmtScore(data?.totals.avg_gpa ?? null) }}</strong>
      </article>
    </div>

    <section class="dashboard-card">
      <header class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Danh sách sinh viên</h2>
        <label class="advisor-toggle">
          <input v-model="showOnlyRisk" type="checkbox">
          Chỉ hiện sinh viên cảnh báo
        </label>
      </header>

      <table class="crud-table">
        <thead>
          <tr>
            <th>STT</th>
            <th>Mã SV</th>
            <th>Họ tên</th>
            <th>Khóa/Lớp</th>
            <th class="text-center">Đang ghi danh</th>
            <th class="text-center">Đã chấm</th>
            <th class="text-center">GPA kỳ</th>
            <th>Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="crud-empty">Đang tải...</td>
          </tr>
          <tr v-else-if="!filtered.length">
            <td colspan="8" class="crud-empty">
              {{ showOnlyRisk ? 'Không có sinh viên nào trong diện cảnh báo.' : 'Chưa có sinh viên được gán cố vấn.' }}
            </td>
          </tr>
          <tr v-for="(row, i) in filtered" :key="row.student.id">
            <td>{{ i + 1 }}</td>
            <td><code>{{ row.student.student_code || '--' }}</code></td>
            <td>
              <strong>{{ row.student.name }}</strong>
              <p class="text-xs text-muted">{{ row.student.email }}</p>
            </td>
            <td>{{ row.student.cohort?.code || '--' }}</td>
            <td class="text-center">{{ row.enrollments }}</td>
            <td class="text-center">{{ row.graded }}</td>
            <td class="text-center">
              <strong :class="row.avg_score !== null && row.avg_score < 5 ? 'final-fail' : 'final-pass'">
                {{ fmtScore(row.avg_score) }}
              </strong>
            </td>
            <td>
              <span v-if="row.is_at_risk" class="chip-risk">Cần can thiệp</span>
              <span v-else class="chip-ok">Ổn định</span>
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
.stat-risk .stat-value { color: #b91c1c; }

.advisor-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--muted);
}

.chip-ok, .chip-risk {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}
.chip-ok { background: rgba(var(--green-rgb), 0.12); color: var(--green-deep); }
.chip-risk { background: rgba(220, 38, 38, 0.12); color: #b91c1c; }

.final-pass { color: var(--green-deep); }
.final-fail { color: #b91c1c; }

.text-center { text-align: center; }
.text-muted { color: var(--muted); }
</style>
