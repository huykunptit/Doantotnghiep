<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface SectionRow {
  section: {
    id: number
    code: string
    name: string | null
    status: string
    course?: { id: number; title: string; course_mode: string }
    term?: { id: number; name: string; code: string }
    cohort?: { id: number; name: string; code: string }
  }
  enrollments: number
  graded: number
  pending: number
}

const token = useAuthTokenCookie()
const data = ref<{
  instructor: any
  current_term: any
  sections: SectionRow[]
  totals: { sections: number; students: number; pending_grading: number }
} | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi('/instructor/dashboard', {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dashboard giảng viên.'
  } finally {
    loading.value = false
  }
}

const totals = computed(() => data.value?.totals || { sections: 0, students: 0, pending_grading: 0 })
const term = computed(() => data.value?.current_term)

onMounted(load)
</script>

<template>
  <section class="space-y-6 p-6">
    <header class="space-y-1">
      <p class="text-sm font-semibold text-on-surface-variant">Học vụ · Giảng viên</p>
      <h1 class="text-2xl font-bold">Lớp học phần & điểm</h1>
      <p v-if="term" class="text-sm text-muted">Kỳ hiện hành: <strong>{{ term.name }}</strong> ({{ term.code }})</p>
    </header>

    <div class="grid gap-4 md:grid-cols-3">
      <article class="dashboard-card stat-card">
        <p class="stat-label">Lớp đang phụ trách</p>
        <strong class="stat-value">{{ totals.sections }}</strong>
      </article>
      <article class="dashboard-card stat-card">
        <p class="stat-label">Tổng sinh viên</p>
        <strong class="stat-value">{{ totals.students }}</strong>
      </article>
      <article class="dashboard-card stat-card stat-pending">
        <p class="stat-label">Chờ chấm điểm</p>
        <strong class="stat-value">{{ totals.pending_grading }}</strong>
      </article>
    </div>

    <section class="dashboard-card">
      <header class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Danh sách lớp học phần</h2>
        <button class="crud-secondary-btn" type="button" :disabled="loading" @click="load">
          <span class="material-symbols-outlined">refresh</span>
          <span>{{ loading ? 'Đang tải...' : 'Làm mới' }}</span>
        </button>
      </header>

      <div v-if="error" class="crud-alert is-error">{{ error }}</div>

      <table class="crud-table">
        <thead>
          <tr>
            <th>Mã lớp</th>
            <th>Học phần</th>
            <th>Khóa</th>
            <th class="text-center">SV</th>
            <th class="text-center">Đã chấm</th>
            <th class="text-center">Chờ chấm</th>
            <th class="text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="crud-empty">Đang tải...</td>
          </tr>
          <tr v-else-if="!data?.sections.length">
            <td colspan="7" class="crud-empty">Bạn chưa được phân lớp nào trong kỳ này.</td>
          </tr>
          <tr v-for="row in data?.sections || []" :key="row.section.id">
            <td><code>{{ row.section.code }}</code></td>
            <td>
              <strong>{{ row.section.course?.title || '--' }}</strong>
              <p class="text-xs text-muted">{{ row.section.term?.name }} · {{ row.section.cohort?.code }}</p>
            </td>
            <td>{{ row.section.cohort?.name || '--' }}</td>
            <td class="text-center">{{ row.enrollments }}</td>
            <td class="text-center">{{ row.graded }}</td>
            <td class="text-center">
              <span :class="row.pending > 0 ? 'badge-warning' : 'badge-ok'">{{ row.pending }}</span>
            </td>
            <td class="text-right">
              <NuxtLink :to="`/instructor/sections/${row.section.id}/grades`" class="crud-primary-btn-sm">
                <span class="material-symbols-outlined">grading</span>
                <span>Vào sổ điểm</span>
              </NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

<style scoped>
.stat-card { padding: 18px 20px; }
.stat-label { color: var(--muted); font-size: 0.85rem; margin: 0; }
.stat-value { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; }
.stat-pending .stat-value { color: #d97706; }

.crud-primary-btn-sm {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
  transition: background-color 140ms ease;
}
.crud-primary-btn-sm:hover { background: rgba(var(--green-rgb), 0.22); }
.crud-primary-btn-sm .material-symbols-outlined { font-size: 18px; }

.badge-ok { color: var(--green-deep); font-weight: 700; }
.badge-warning {
  background: rgba(217, 119, 6, 0.12);
  color: #b45309;
  padding: 2px 10px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.85rem;
}

.text-right { text-align: right; }
.text-center { text-align: center; }
.text-muted { color: var(--muted); }
</style>
