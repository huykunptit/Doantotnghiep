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

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const schedules = ref<ScheduleItem[]>([])
const currentTerm = ref<{ name?: string } | null>(null)

/** 0 = tuần hiện tại; âm = tuần trước; dương = tuần sau */
const weekOffset = ref(0)

const weekdayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const

function startOfWeek(date: Date) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  const day = d.getDay() // 0 CN … 6 T7
  const diff = day === 0 ? -6 : 1 - day
  d.setDate(d.getDate() + diff)
  return d
}

function addDays(date: Date, days: number) {
  const d = new Date(date)
  d.setDate(d.getDate() + days)
  return d
}

/** ISO week number (tuần bắt đầu Thứ 2). */
function isoWeekNumber(date: Date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
  const dayNum = d.getUTCDay() || 7
  d.setUTCDate(d.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
  return Math.ceil((((d.getTime() - yearStart.getTime()) / 86400000) + 1) / 7)
}

function pad2(n: number) {
  return String(n).padStart(2, '0')
}

function fmtDM(date: Date) {
  return `${pad2(date.getDate())}/${pad2(date.getMonth() + 1)}`
}

function fmtDMY(date: Date) {
  return `${pad2(date.getDate())}/${pad2(date.getMonth() + 1)}/${date.getFullYear()}`
}

const weekStart = computed(() => {
  const base = startOfWeek(new Date())
  return addDays(base, weekOffset.value * 7)
})

const weekEnd = computed(() => addDays(weekStart.value, 6))

const weekNumber = computed(() => isoWeekNumber(weekStart.value))

const weekLabel = computed(() =>
  t('student.timetable.weekRange', {
    n: weekNumber.value,
    from: fmtDMY(weekStart.value),
    to: fmtDMY(weekEnd.value),
  }),
)

const dayDates = computed(() =>
  weekdayKeys.map((_, idx) => addDays(weekStart.value, idx)),
)

const byDay = computed(() => {
  const map: Record<number, ScheduleItem[]> = {}
  for (let d = 1; d <= 7; d++) map[d] = []
  for (const s of schedules.value) {
    const day = Number(s.weekday)
    if (!map[day]) continue
    map[day].push(s)
  }
  for (const day of Object.keys(map)) {
    map[Number(day)].sort((a, b) => a.start_time.localeCompare(b.start_time))
  }
  return map
})

function prevWeek() {
  weekOffset.value -= 1
}

function nextWeek() {
  weekOffset.value += 1
}

function goThisWeek() {
  weekOffset.value = 0
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ schedules: ScheduleItem[], current_term: any }>('/me/timetable')
    schedules.value = res.schedules || []
    currentTerm.value = res.current_term || null
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.timetable.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <p v-if="currentTerm?.name" class="term-note">{{ currentTerm.name }}</p>

    <div v-if="loading" class="empty">…</div>
    <template v-else>
      <div class="week-nav">
        <button
          type="button"
          class="nav-btn"
          :aria-label="t('student.timetable.prevWeek')"
          @click="prevWeek"
        >
          <i class="pi pi-chevron-left" />
        </button>

        <div class="week-box" :title="weekLabel">
          <span>{{ weekLabel }}</span>
        </div>

        <button
          type="button"
          class="nav-btn"
          :aria-label="t('student.timetable.nextWeek')"
          @click="nextWeek"
        >
          <i class="pi pi-chevron-right" />
        </button>

        <button
          v-if="weekOffset !== 0"
          type="button"
          class="today-btn"
          @click="goThisWeek"
        >
          {{ t('student.timetable.thisWeek') }}
        </button>
      </div>

      <section class="week">
        <div v-for="(key, idx) in weekdayKeys" :key="key" class="day">
          <div class="day-head">
            <span>{{ t('student.timetable.weekdays.' + key) }}</span>
            <small>({{ fmtDM(dayDates[idx]) }})</small>
          </div>
          <div v-if="!byDay[idx + 1].length" class="day-empty">—</div>
          <div v-for="s in byDay[idx + 1]" :key="s.id" class="slot">
            <strong>{{ s.start_time }}–{{ s.end_time }}</strong>
            <span class="course">{{ s.course?.title || '—' }}</span>
            <small v-if="s.room" class="muted"><i class="pi pi-map-marker" /> {{ s.room }}</small>
            <small v-if="s.lecturer" class="muted"><i class="pi pi-user" /> {{ s.lecturer.name }}</small>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }
.term-note { margin: 0; color: var(--text-muted); font-weight: 600; font-size: .88rem; }

.week-nav {
  display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
}
.nav-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: 10px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--brand); cursor: pointer;
}
.nav-btn:hover {
  border-color: color-mix(in srgb, var(--brand) 40%, var(--border));
  background: color-mix(in srgb, var(--brand) 8%, var(--surface));
}
.week-box {
  flex: 1; min-width: min(280px, 100%);
  padding: 9px 14px; border: 1px solid var(--border); border-radius: 10px;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  font-weight: 700; font-size: .92rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.today-btn {
  border: 1px solid color-mix(in srgb, var(--brand) 35%, var(--border));
  background: color-mix(in srgb, var(--brand) 8%, transparent);
  color: var(--brand); border-radius: 10px; padding: 8px 12px;
  font-weight: 700; font-size: .85rem; cursor: pointer;
}
.today-btn:hover { background: color-mix(in srgb, var(--brand) 14%, transparent); }

.week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
.day { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: color-mix(in srgb, var(--surface) 92%, transparent); min-height: 120px; }
.day-head {
  padding: 8px; text-align: center; font-weight: 700;
  background: color-mix(in srgb, var(--brand) 8%, var(--surface));
  border-bottom: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 2px; line-height: 1.2;
}
.day-head small { color: var(--text-muted); font-weight: 600; font-size: .75rem; }
.day-empty { padding: 16px; text-align: center; color: var(--text-muted); }
.slot { padding: 10px; border-bottom: 1px dashed var(--border); display: flex; flex-direction: column; gap: 2px; }
.slot:last-child { border-bottom: none; }
.slot strong { color: var(--brand); font-size: .85rem; }
.course { font-weight: 600; font-size: .88rem; }
.muted { color: var(--text-muted); font-size: .78rem; }
@media (max-width: 900px) { .week { grid-template-columns: 1fr; } }
</style>
