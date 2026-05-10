<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import ExtensionRecommendations from '~/components/student/ExtensionRecommendations.vue'

definePageMeta({ layout: 'default' })

interface DashboardData {
  student: any
  current_term: any
  current_enrollments: any[]
  totals: { enrollments: number; in_progress: number; completed: number }
}

const token = useAuthTokenCookie()
const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi('/me/dashboard', {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dashboard.'
  } finally {
    loading.value = false
  }
}

const totals = computed(() => data.value?.totals || { enrollments: 0, in_progress: 0, completed: 0 })

onMounted(load)
</script>

<template>
  <main class="container mx-auto max-w-6xl space-y-6 p-6">
    <header class="space-y-1">
      <p class="text-sm font-semibold text-on-surface-variant">Sinh viên</p>
      <h1 class="text-2xl font-bold">Chào, {{ data?.student?.name || 'sinh viên' }}</h1>
      <p v-if="data?.current_term" class="text-sm text-muted">
        Kỳ hiện hành: <strong>{{ data.current_term.name }}</strong>
      </p>
    </header>

    <div v-if="error" class="crud-alert is-error">{{ error }}</div>

    <div class="grid gap-4 md:grid-cols-3">
      <article class="dashboard-card stat-card">
        <p class="stat-label">Tổng số lần ghi danh</p>
        <strong class="stat-value">{{ totals.enrollments }}</strong>
      </article>
      <article class="dashboard-card stat-card">
        <p class="stat-label">Đang học (kỳ này)</p>
        <strong class="stat-value">{{ totals.in_progress }}</strong>
      </article>
      <article class="dashboard-card stat-card">
        <p class="stat-label">Đã có điểm</p>
        <strong class="stat-value">{{ totals.completed }}</strong>
      </article>
    </div>

    <section class="dashboard-card">
      <header class="flex items-center justify-between mb-3">
        <div>
          <p class="section-kicker">Học phần kỳ hiện hành</p>
          <h2 class="text-lg font-semibold">Lớp đang học</h2>
        </div>
        <NuxtLink to="/me/transcript" class="rec-more">Bảng điểm đầy đủ →</NuxtLink>
      </header>

      <div v-if="loading" class="crud-empty">Đang tải...</div>
      <div v-else-if="!data?.current_enrollments.length" class="crud-empty">
        Chưa có lớp nào được ghi danh trong kỳ này.
      </div>
      <table v-else class="crud-table">
        <thead>
          <tr>
            <th>Học phần</th>
            <th>Mã lớp</th>
            <th>Giảng viên</th>
            <th>Loại</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in data?.current_enrollments || []" :key="e.id">
            <td><strong>{{ e.course?.title }}</strong></td>
            <td><code>{{ e.class_section?.code || '--' }}</code></td>
            <td>{{ e.class_section?.lecturer?.name || '--' }}</td>
            <td>
              <span v-if="e.course?.course_mode === 'core'" class="chip-core">Chính quy</span>
              <span v-else class="chip-ext">Mở rộng</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <ExtensionRecommendations :limit="6" />
  </main>
</template>

<style scoped>
.stat-card { padding: 18px 20px; }
.stat-label { color: var(--muted); font-size: 0.85rem; margin: 0; }
.stat-value { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; }

.chip-core, .chip-ext {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}
.chip-core { background: rgba(var(--green-rgb), 0.12); color: var(--green-deep); }
.chip-ext { background: rgba(217, 119, 6, 0.12); color: #b45309; }

.text-muted { color: var(--muted); }
.rec-more {
  font-weight: 600;
  color: var(--green-deep);
  text-decoration: none;
}
</style>
