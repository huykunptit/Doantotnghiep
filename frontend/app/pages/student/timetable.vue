<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface ScheduleItem {
  id: number
  weekday: number
  start_time: string
  end_time: string
  room?: string | null
  course?: { id: number, title: string } | null
  lecturer?: { id: number, name: string } | null
}

interface ExamItem {
  id: number
  title: string
  starts_at?: string | null
  ends_at?: string | null
  duration?: number | null
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const schedules = ref<ScheduleItem[]>([])
const exams = ref<ExamItem[]>([])
const currentTerm = ref<{ name?: string } | null>(null)

const weekdayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

const byDay = computed(() => {
  const map: Record<number, ScheduleItem[]> = {}
  for (let d = 1; d <= 7; d++) map[d] = []
  for (const s of schedules.value) {
    if (map[s.weekday]) map[s.weekday].push(s)
  }
  return map
})

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ schedules: ScheduleItem[], exams: ExamItem[], current_term: any }>('/me/timetable')
    schedules.value = res.schedules || []
    exams.value = res.exams || []
    currentTerm.value = res.current_term || null
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.timetable.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function fmtDateTime(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.timetable.title') }}</h1>
        <p>{{ t('student.timetable.subtitle') }}<template v-if="currentTerm?.name"> · {{ currentTerm.name }}</template></p>
      </div>
    </header>

    <div v-if="loading" class="empty">…</div>
    <template v-else>
      <section class="week">
        <div v-for="(key, idx) in weekdayKeys" :key="key" class="day">
          <div class="day-head">{{ t('student.timetable.weekdays.' + key) }}</div>
          <div v-if="!byDay[idx + 1].length" class="day-empty">—</div>
          <div v-for="s in byDay[idx + 1]" :key="s.id" class="slot">
            <strong>{{ s.start_time }}–{{ s.end_time }}</strong>
            <span class="course">{{ s.course?.title || '—' }}</span>
            <small v-if="s.room" class="muted"><i class="pi pi-map-marker" /> {{ s.room }}</small>
            <small v-if="s.lecturer" class="muted"><i class="pi pi-user" /> {{ s.lecturer.name }}</small>
          </div>
        </div>
      </section>

      <section v-if="exams.length" class="exams">
        <h2>{{ t('student.timetable.upcomingExams') }}</h2>
        <div v-for="ex in exams" :key="ex.id" class="exam-row">
          <i class="pi pi-file-edit" />
          <strong>{{ ex.title }}</strong>
          <span class="muted">{{ fmtDateTime(ex.starts_at) }}</span>
          <span v-if="ex.duration" class="muted">· {{ ex.duration }}'</span>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
.day { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: color-mix(in srgb, var(--surface) 92%, transparent); min-height: 120px; }
.day-head { padding: 8px; text-align: center; font-weight: 700; background: color-mix(in srgb, var(--brand) 8%, var(--surface)); border-bottom: 1px solid var(--border); }
.day-empty { padding: 16px; text-align: center; color: var(--text-muted); }
.slot { padding: 10px; border-bottom: 1px dashed var(--border); display: flex; flex-direction: column; gap: 2px; }
.slot:last-child { border-bottom: none; }
.slot strong { color: var(--brand); font-size: .85rem; }
.course { font-weight: 600; font-size: .88rem; }
.muted { color: var(--text-muted); font-size: .78rem; }
.exams { border: 1px solid var(--border); border-radius: 14px; padding: 16px; }
.exams h2 { margin: 0 0 10px; font-size: 1.05rem; }
.exam-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--border); }
.exam-row:last-child { border-bottom: none; }
.exam-row strong { font-weight: 600; }
@media (max-width: 900px) { .week { grid-template-columns: 1fr; } }
</style>
