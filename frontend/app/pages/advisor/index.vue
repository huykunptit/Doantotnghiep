<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import DataTableFooter from '~/components/common/DataTableFooter.vue'

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
const advisorPage = ref(1)
const advisorPerPage = ref(10)

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

const allFiltered = computed(() => {
  if (!data.value) return []
  return showOnlyRisk.value ? data.value.advisees.filter((r) => r.is_at_risk) : data.value.advisees
})
const advisorLastPage = computed(() => Math.max(1, Math.ceil(allFiltered.value.length / advisorPerPage.value)))
const filtered = computed(() => {
  const start = (advisorPage.value - 1) * advisorPerPage.value
  return allFiltered.value.slice(start, start + advisorPerPage.value)
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
      <p class="text-sm font-semibold text-muted">Cố vấn học tập</p>
      <h1 class="font-headline text-3xl font-extrabold tracking-[-0.03em] text-text">Sinh viên tôi phụ trách</h1>
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
            <th>Mã sinh viên</th>
            <th>Họ và tên</th>
            <th>Khóa/Lớp</th>
            <th class="text-center">Đang ghi danh</th>
            <th class="text-center">Đã chấm điểm</th>
            <th class="text-center">GPA kỳ học</th>
            <th>Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="crud-empty">Đang tải...</td>
          </tr>
          <tr v-else-if="!allFiltered.length">
            <td colspan="8" class="crud-empty">
              {{ showOnlyRisk ? 'Không có sinh viên nào trong diện cảnh báo.' : 'Chưa có sinh viên được gán cố vấn.' }}
            </td>
          </tr>
          <tr v-for="(row, i) in filtered" :key="row.student.id">
            <td>{{ (advisorPage - 1) * advisorPerPage + i + 1 }}</td>
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

      <DataTableFooter
        :current="advisorPage"
        :last="advisorLastPage"
        :total="allFiltered.length"
        :per-page="advisorPerPage"
        @page="advisorPage = $event"
        @update:per-page="advisorPerPage = $event; advisorPage = 1"
      />
    </section>
  </main>
</template>

<style scoped>
.stat-card { 
  padding: 24px; 
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(31, 49, 43, 0.04);
}
.stat-label { 
  color: var(--muted); 
  font-size: 0.85rem; 
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0 0 8px; 
}
.stat-value { 
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.85rem; 
  font-weight: 800; 
  letter-spacing: -0.02em; 
  color: var(--text);
}
.stat-risk .stat-value { color: var(--danger); }

.advisor-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  user-select: none;
}
.advisor-toggle input {
  accent-color: var(--green);
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.chip-ok, .chip-risk {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}
.chip-ok { background: var(--green-soft); color: var(--green); }
.chip-risk { background: var(--danger-soft); color: var(--danger); }

.final-pass { color: var(--green); font-weight: 700; }
.final-fail { color: var(--danger); font-weight: 700; }

.text-center { text-align: center; }
.text-muted { color: var(--muted); }
</style>
