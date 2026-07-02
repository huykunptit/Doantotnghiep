<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const exams = ref<any[]>([])
const view = ref<'month' | 'agenda'>('month')
const selectedDate = ref<string | null>(null)

const today = new Date()
const calYear = ref(today.getFullYear())
const calMonth = ref(today.getMonth())

const MONTHS_VI = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6',
                   'Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12']
const DAYS_VI = ['CN','T2','T3','T4','T5','T6','T7']

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0] = await Promise.allSettled([
    useApi<any>('/exams/standalone?per_page=200', { headers: h }),
  ])
  if (r0.status === 'fulfilled') {
    const d = r0.value
    exams.value = Array.isArray(d) ? d : (d?.data || [])
  }
  loading.value = false
})

const calLabel = computed(() => `${MONTHS_VI[calMonth.value]} ${calYear.value}`)

function prevMonth() {
  if (calMonth.value === 0) { calYear.value--; calMonth.value = 11 } else calMonth.value--
  selectedDate.value = null
}
function nextMonth() {
  if (calMonth.value === 11) { calYear.value++; calMonth.value = 0 } else calMonth.value++
  selectedDate.value = null
}
function goToday() {
  calYear.value = today.getFullYear()
  calMonth.value = today.getMonth()
  selectedDate.value = todayStr.value
}

const todayStr = computed(() => `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`)

function eventDateStr(e: any) {
  const d = new Date(e.start_time || e.date)
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

const calendarDays = computed(() => {
  const first = new Date(calYear.value, calMonth.value, 1).getDay()
  const total = new Date(calYear.value, calMonth.value + 1, 0).getDate()
  const days: any[] = []
  for (let i = 0; i < first; i++) days.push({ day: 0, dateStr: '', isToday: false, events: [], inMonth: false })
  for (let d = 1; d <= total; d++) {
    const dateStr = `${calYear.value}-${String(calMonth.value+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`
    const isToday = dateStr === todayStr.value
    const events = exams.value.filter(e => eventDateStr(e) === dateStr)
    days.push({ day: d, dateStr, isToday, events, inMonth: true })
  }
  return days
})

const selectedEvents = computed(() => {
  if (!selectedDate.value) return []
  return exams.value.filter(e => eventDateStr(e) === selectedDate.value)
})

const agendaExams = computed(() => {
  const now = Date.now()
  return exams.value
    .filter(e => new Date(e.start_time || e.date).getTime() >= now)
    .sort((a, b) => new Date(a.start_time || a.date).getTime() - new Date(b.start_time || b.date).getTime())
})

function formatDateTime(raw: string) {
  if (!raw) return '—'
  return new Date(raw).toLocaleString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' })
}

function formatDateShort(raw: string) {
  if (!raw) return '—'
  const d = new Date(raw)
  return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}`
}
</script>

<template>
  <div class="cl-page">
    <!-- Header -->
    <div class="cl-header">
      <div>
        <p class="section-kicker">Lịch học</p>
        <h1 class="cl-title">Lịch & Sự kiện</h1>
      </div>
      <div class="cl-header-actions">
        <button class="cl-today-btn" @click="goToday">Hôm nay</button>
        <div class="cl-view-toggle">
          <button :class="['cl-vt-btn', {active: view==='month'}]" @click="view='month'">
            <SylvaIcon name="calendar" :size="14" /> Tháng
          </button>
          <button :class="['cl-vt-btn', {active: view==='agenda'}]" @click="view='agenda'">
            <SylvaIcon name="list" :size="14" /> Danh sách
          </button>
        </div>
      </div>
    </div>

    <!-- Month view -->
    <div v-if="view==='month'" class="cl-month-layout">
      <!-- Calendar -->
      <div class="dashboard-card cl-cal-wrap">
        <!-- Nav bar -->
        <div class="cl-nav">
          <button class="cl-nav-btn" @click="prevMonth" aria-label="Tháng trước">
            <SylvaIcon name="chevron-left" :size="16" />
          </button>
          <h2 class="cl-nav-label">{{ calLabel }}</h2>
          <button class="cl-nav-btn" @click="nextMonth" aria-label="Tháng sau">
            <SylvaIcon name="chevron-right" :size="16" />
          </button>
        </div>

        <!-- Shimmer -->
        <div v-if="loading" class="cl-skeleton">
          <span v-for="i in 35" :key="i" class="sd-shimmer cl-sk-cell"></span>
        </div>

        <!-- Calendar grid -->
        <div v-else class="cl-grid">
          <div v-for="d in DAYS_VI" :key="d" class="cl-dow">{{ d }}</div>
          <div
            v-for="(cell, idx) in calendarDays"
            :key="idx"
            class="cl-cell"
            :class="{
              empty: !cell.inMonth,
              today: cell.isToday,
              selected: selectedDate === cell.dateStr && cell.inMonth,
              'has-event': cell.events.length > 0
            }"
            @click="cell.inMonth && (selectedDate = selectedDate === cell.dateStr ? null : cell.dateStr)"
          >
            <span v-if="cell.inMonth" class="cl-day-num">{{ cell.day }}</span>
            <div v-if="cell.events.length" class="cl-event-pills">
              <span v-for="(ev, ei) in cell.events.slice(0,2)" :key="ei" class="cl-pill">
                {{ ev.title?.slice(0,12) || 'Kỳ thi' }}
              </span>
              <span v-if="cell.events.length > 2" class="cl-pill cl-pill-more">+{{ cell.events.length - 2 }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Detail panel -->
      <div class="cl-detail-panel">
        <div v-if="selectedDate && selectedEvents.length" class="dashboard-card cl-detail-card">
          <h3 class="cl-detail-date">
            {{ new Date(selectedDate + 'T00:00:00').toLocaleDateString('vi-VN', {weekday:'long',day:'2-digit',month:'long',year:'numeric'}) }}
          </h3>
          <div class="cl-detail-list">
            <div v-for="ev in selectedEvents" :key="ev.id" class="cl-detail-item">
              <div class="cl-detail-icon">
                <SylvaIcon name="clipboard-list" :size="16" />
              </div>
              <div class="cl-detail-info">
                <p class="cl-detail-name">{{ ev.title || ev.name }}</p>
                <p class="cl-detail-course">{{ ev.course?.title || 'Kỳ thi độc lập' }}</p>
                <p class="cl-detail-time">{{ formatDateTime(ev.start_time || ev.date) }}</p>
                <p v-if="ev.duration_minutes" class="cl-detail-dur">
                  <SylvaIcon name="clock" :size="11" /> {{ ev.duration_minutes }} phút
                </p>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="selectedDate" class="dashboard-card cl-detail-card cl-no-event">
          <SylvaIcon name="calendar" :size="32" />
          <p>Không có sự kiện trong ngày này.</p>
        </div>

        <!-- Upcoming sidebar -->
        <div class="dashboard-card cl-upcoming">
          <h3 class="cl-upcoming-title">Sắp tới</h3>
          <div v-if="loading" class="cl-upcoming-list">
            <span v-for="i in 5" :key="i" class="sd-shimmer" style="height:52px;border-radius:10px;display:block"></span>
          </div>
          <div v-else-if="agendaExams.length" class="cl-upcoming-list">
            <div v-for="ev in agendaExams.slice(0,6)" :key="ev.id" class="cl-upcoming-item"
              @click="selectedDate = eventDateStr(ev); calYear = new Date(ev.start_time||ev.date).getFullYear(); calMonth = new Date(ev.start_time||ev.date).getMonth()">
              <div class="cl-up-date">
                <span class="cl-up-day">{{ new Date(ev.start_time||ev.date).getDate() }}</span>
                <span class="cl-up-month">{{ MONTHS_VI[new Date(ev.start_time||ev.date).getMonth()].slice(2) }}</span>
              </div>
              <div class="cl-up-info">
                <p class="cl-up-name">{{ ev.title || ev.name }}</p>
                <p class="cl-up-time">{{ new Date(ev.start_time||ev.date).toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'}) }}</p>
              </div>
            </div>
          </div>
          <p v-else class="cl-no-upcoming">Không có sự kiện sắp tới.</p>
        </div>
      </div>
    </div>

    <!-- Agenda view -->
    <div v-else class="dashboard-card cl-agenda-wrap">
      <div v-if="loading">
        <span v-for="i in 8" :key="i" class="sd-shimmer" style="height:60px;border-radius:10px;display:block;margin-bottom:10px"></span>
      </div>
      <div v-else-if="agendaExams.length" class="cl-agenda-list">
        <div v-for="ev in agendaExams" :key="ev.id" class="cl-agenda-row">
          <div class="cl-ag-date-col">
            <span class="cl-ag-day">{{ new Date(ev.start_time||ev.date).getDate() }}</span>
            <span class="cl-ag-month">{{ MONTHS_VI[new Date(ev.start_time||ev.date).getMonth()] }}</span>
          </div>
          <div class="cl-ag-line"></div>
          <div class="cl-ag-content">
            <div class="cl-ag-icon"><SylvaIcon name="clipboard-list" :size="15" /></div>
            <div class="cl-ag-info">
              <p class="cl-ag-name">{{ ev.title || ev.name }}</p>
              <p class="cl-ag-sub">{{ ev.course?.title || 'Kỳ thi độc lập' }} · {{ formatDateTime(ev.start_time || ev.date) }}</p>
              <p v-if="ev.duration_minutes" class="cl-ag-dur">{{ ev.duration_minutes }} phút</p>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="sd-empty">
        <SylvaIcon name="calendar" :size="40" />
        <p>Không có sự kiện sắp tới.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cl-page { display: flex; flex-direction: column; gap: 20px; }
.cl-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.cl-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin: 4px 0 0; }
.cl-header-actions { display: flex; align-items: center; gap: 10px; }
.cl-today-btn {
  padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--line); background: var(--surface-strong);
  color: var(--text); font-size: 0.82rem; font-weight: 600; cursor: pointer;
  transition: background 150ms;
}
.cl-today-btn:hover { background: var(--bg); }
.cl-view-toggle { display: flex; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
.cl-vt-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 12px; border: none;
  background: transparent; color: var(--muted);
  font-size: 0.8rem; font-weight: 600; cursor: pointer;
  transition: background 150ms, color 150ms;
}
.cl-vt-btn.active { background: var(--green-soft); color: var(--green-deep); }

/* Month layout */
.cl-month-layout { display: grid; grid-template-columns: 1fr 300px; gap: 20px; align-items: start; }

.cl-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.cl-nav-btn {
  width: 32px; height: 32px; border-radius: 8px;
  border: 1px solid var(--line); background: transparent;
  color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background 150ms;
}
.cl-nav-btn:hover { background: var(--bg); color: var(--text); }
.cl-nav-label { font-size: 1rem; font-weight: 700; color: var(--text); }

/* Calendar grid */
.cl-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.cl-dow {
  text-align: center; font-size: 0.68rem; font-weight: 700;
  color: var(--muted); padding: 6px 0; text-transform: uppercase;
}
.cl-cell {
  min-height: 84px; border-radius: 8px;
  border: 1px solid transparent;
  padding: 4px 5px;
  cursor: pointer;
  transition: background 120ms, border-color 120ms;
  display: flex; flex-direction: column;
}
.cl-cell:hover:not(.empty) { background: var(--bg); border-color: var(--line); }
.cl-cell.today { background: var(--green-soft); border-color: var(--green); }
.cl-cell.selected { background: var(--green-soft); border-color: var(--green); }
.cl-cell.has-event:not(.today):not(.selected) { background: rgba(16,185,129,0.04); }
.cl-cell.empty { cursor: default; }
.cl-day-num {
  font-size: 0.82rem; font-weight: 700; color: var(--text);
  display: block; margin-bottom: 3px;
}
.cl-cell.today .cl-day-num {
  background: var(--green); color: #fff;
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem;
}
.cl-event-pills { display: flex; flex-direction: column; gap: 2px; }
.cl-pill {
  font-size: 0.82rem; font-weight: 600;
  background: var(--green); color: #fff;
  padding: 1px 5px; border-radius: 4px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cl-pill-more { background: var(--muted); }

/* Skeleton */
.cl-skeleton { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.cl-sk-cell { height: 84px; display: block; border-radius: 8px; }

/* Detail panel */
.cl-detail-panel { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 80px; }
.cl-detail-card { padding: 16px; }
.cl-detail-date { font-size: 0.88rem; font-weight: 700; color: var(--text); margin: 0 0 14px; }
.cl-detail-list { display: flex; flex-direction: column; gap: 12px; }
.cl-detail-item { display: flex; gap: 10px; }
.cl-detail-icon {
  width: 32px; height: 32px; border-radius: 8px;
  background: var(--green-soft); color: var(--green-deep);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cl-detail-info { flex: 1; }
.cl-detail-name { font-size: 0.86rem; font-weight: 700; color: var(--text); margin: 0 0 2px; }
.cl-detail-course { font-size: 0.74rem; color: var(--muted); margin: 0 0 4px; }
.cl-detail-time { font-size: 0.76rem; color: var(--green); font-weight: 600; margin: 0 0 3px; }
.cl-detail-dur { font-size: 0.72rem; color: var(--muted); display: flex; align-items: center; gap: 4px; margin: 0; }
.cl-no-event { padding: 24px; display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--muted); }
.cl-no-event p { font-size: 0.84rem; }

/* Upcoming */
.cl-upcoming { padding: 16px; }
.cl-upcoming-title { font-size: 0.88rem; font-weight: 700; color: var(--text); margin: 0 0 12px; }
.cl-upcoming-list { display: flex; flex-direction: column; gap: 8px; }
.cl-upcoming-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px; border-radius: 10px;
  border: 1px solid var(--line);
  cursor: pointer; transition: background 150ms;
}
.cl-upcoming-item:hover { background: var(--green-soft); border-color: transparent; }
.cl-up-date {
  width: 36px; flex-shrink: 0; text-align: center;
  background: var(--green-soft); border-radius: 8px; padding: 4px 0;
}
.cl-up-day { display: block; font-size: 1rem; font-weight: 800; color: var(--green-deep); line-height: 1; }
.cl-up-month { display: block; font-size: 0.82rem; font-weight: 600; color: var(--green); text-transform: uppercase; }
.cl-up-info { flex: 1; min-width: 0; }
.cl-up-name { font-size: 0.8rem; font-weight: 600; color: var(--text); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cl-up-time { font-size: 0.72rem; color: var(--muted); }
.cl-no-upcoming { font-size: 0.82rem; color: var(--muted); text-align: center; padding: 12px 0; }

/* Agenda view */
.cl-agenda-wrap { padding: 20px; }
.cl-agenda-list { display: flex; flex-direction: column; gap: 0; }
.cl-agenda-row { display: flex; align-items: flex-start; gap: 0; padding: 12px 0; border-bottom: 1px solid var(--line); }
.cl-agenda-row:last-child { border-bottom: none; }
.cl-ag-date-col { width: 60px; flex-shrink: 0; text-align: center; padding-top: 2px; }
.cl-ag-day { display: block; font-size: 1.4rem; font-weight: 800; color: var(--text); line-height: 1; }
.cl-ag-month { display: block; font-size: 0.68rem; color: var(--muted); font-weight: 600; }
.cl-ag-line { width: 1px; background: var(--line); margin: 0 16px; align-self: stretch; }
.cl-ag-content { display: flex; gap: 10px; flex: 1; align-items: flex-start; }
.cl-ag-icon {
  width: 30px; height: 30px; border-radius: 8px;
  background: var(--green-soft); color: var(--green-deep);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cl-ag-info { flex: 1; }
.cl-ag-name { font-size: 0.9rem; font-weight: 700; color: var(--text); margin: 0 0 3px; }
.cl-ag-sub { font-size: 0.78rem; color: var(--muted); margin: 0 0 2px; }
.cl-ag-dur { font-size: 0.74rem; color: var(--green); font-weight: 600; margin: 0; }

.sd-shimmer { background: linear-gradient(90deg, var(--line) 25%, var(--bg) 50%, var(--line) 75%); background-size: 200% 100%; animation: sd-shimmer 1.5s infinite; border-radius: 6px; display: inline-block; }
@keyframes sd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.sd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: var(--muted); gap: 10px; }
.sd-empty p { font-size: 0.9rem; }

[data-theme="dark"] .cl-cell.today { background: rgba(52,211,153,0.18); }
[data-theme="dark"] .cl-cell.selected { background: rgba(52,211,153,0.12); }
[data-theme="dark"] .cl-vt-btn.active { background: rgba(52,211,153,0.15); color: #6ee7b7; }
[data-theme="dark"] .cl-up-date { background: rgba(52,211,153,0.12); }
[data-theme="dark"] .cl-up-day { color: #6ee7b7; }

@media (max-width: 900px) {
  .cl-month-layout { grid-template-columns: 1fr; }
  .cl-detail-panel { position: static; }
}
@media (max-width: 640px) {
  .cl-header { flex-direction: column; align-items: flex-start; }
  .cl-cell { min-height: 52px; }
  .cl-ag-date-col { width: 44px; }
}
</style>
