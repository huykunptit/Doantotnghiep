<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface ExamRow {
  id: number
  title: string
  description?: string | null
  type: string
  status: string
  is_open: boolean
  duration?: number | null
  pass_score?: number | null
  starts_at?: string | null
  ends_at?: string | null
  room?: string | null
  attempts_count: number
  best_score?: number | null
  attempt_id?: number | null
  quiz_id?: number | null
  proctoring_enabled?: boolean
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const exams = ref<ExamRow[]>([])

// Chưa thi lên trước, đã thi/đóng đẩy xuống dưới; trong mỗi nhóm sắp theo thời gian bắt đầu.
const STATUS_RANK: Record<string, number> = { active: 0, scheduled: 1, closed: 2, completed: 3 }
const sortedExams = computed(() => [...exams.value].sort((a, b) => {
  const rankDiff = (STATUS_RANK[a.status] ?? 1) - (STATUS_RANK[b.status] ?? 1)
  if (rankDiff !== 0) return rankDiff
  return new Date(a.starts_at || 0).getTime() - new Date(b.starts_at || 0).getTime()
}))

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ exams: ExamRow[] }>('/me/exams')
    exams.value = res.exams || []
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.exams.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function fmt(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
}

function statusInfo(row: ExamRow) {
  switch (row.status) {
    case 'active': return { severity: 'success', label: t('student.exams.status.active') }
    case 'scheduled': return { severity: 'info', label: t('student.exams.status.scheduled') }
    case 'closed': return { severity: 'secondary', label: t('student.exams.status.closed') }
    case 'completed': return { severity: 'contrast', label: t('student.exams.status.completed') }
    default: return { severity: 'secondary', label: row.status }
  }
}

onMounted(load)
</script>

<template>
  <div class="page">

    <div v-if="loading" class="empty">…</div>
    <CommonEmptyState v-else-if="!exams.length" :description="t('student.exams.empty')" />
    <div v-else class="list">
      <article v-for="ex in sortedExams" :key="ex.id" class="card">
        <div class="main">
          <div class="title-row">
            <strong>{{ ex.title }}</strong>
            <Tag :severity="statusInfo(ex).severity" :value="statusInfo(ex).label" />
            <Tag v-if="ex.proctoring_enabled" :value="t('student.exams.faceCheck')" severity="warn" />
          </div>
          <div v-if="ex.description" class="desc rich" v-html="ex.description" />
          <div class="meta">
            <span><i class="pi pi-calendar" /> {{ t('student.exams.startsAt') }}: {{ fmt(ex.starts_at) }}</span>
            <span><i class="pi pi-clock" /> {{ t('student.exams.endsAt') }}: {{ fmt(ex.ends_at) }}</span>
            <span v-if="ex.duration"><i class="pi pi-hourglass" /> {{ ex.duration }}'</span>
            <span v-if="ex.room"><i class="pi pi-map-marker" /> {{ ex.room }}</span>
            <span v-if="ex.attempts_count"><i class="pi pi-check-circle" /> {{ t('student.exams.attempts', { n: ex.attempts_count }) }}</span>
            <span v-if="ex.best_score !== null && ex.best_score !== undefined" class="score"><i class="pi pi-star" /> {{ ex.best_score }}</span>
          </div>
        </div>
        <div class="actions">
          <Button
            v-if="ex.is_open"
            :label="t('student.exams.enter')"
            icon="pi pi-play"
            @click="navigateTo(`/exam/${ex.id}`)"
          />
          <Button
            v-else-if="ex.attempt_id"
            :label="t('student.exams.viewResult')"
            icon="pi pi-eye"
            severity="secondary"
            outlined
            @click="navigateTo(`/exam/result/${ex.attempt_id}?exam=${ex.id}`)"
          />
          <span v-else class="muted">{{ statusInfo(ex).label }}</span>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.list { display: grid; gap: 10px; }
.card {
  display: grid; grid-template-columns: 1fr auto; gap: 14px; align-items: center;
  padding: 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.title-row { display: flex; align-items: center; gap: 10px; }
.title-row strong { font-size: 1.05rem; }
.desc { margin: 6px 0; color: var(--text-muted); font-size: .88rem; }
.meta { display: flex; flex-wrap: wrap; gap: 10px 22px; color: var(--text-muted); font-size: .82rem; }
.meta span { display: inline-flex; align-items: center; gap: 7px; }
.meta .score { color: var(--brand); font-weight: 700; }
.muted { color: var(--text-muted); }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
@media (max-width: 700px) { .card { grid-template-columns: 1fr; } }
</style>
