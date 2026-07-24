<script setup lang="ts">
import { computed, ref } from 'vue'

interface ScheduleEvent {
  id: number
  title: string
  time: string
  date: string
  type: 'lesson' | 'exam' | 'deadline' | 'meeting' | 'online' | 'offline' | 'live' | 'quiz_online' | 'quiz_offline'
  course?: string
  location?: string
}

const props = defineProps<{
  events: ScheduleEvent[]
  title?: string
}>()

const activeView = ref<'list' | 'calendar'>('list')
const hoveredDay = ref<number | null>(null)
const hoveredMonth = ref<boolean>(true)
const daysShort = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']
const currentDay = ref(new Date().getDay() === 0 ? 6 : new Date().getDay() - 1)

// Calendar Logic
const currentDate = ref(new Date())
const monthNames = [
  'THÁNG 1', 'THÁNG 2', 'THÁNG 3', 'THÁNG 4', 'THÁNG 5', 'THÁNG 6',
  'THÁNG 7', 'THÁNG 8', 'THÁNG 9', 'THÁNG 10', 'THÁNG 11', 'THÁNG 12'
]

const currentMonthLabel = computed(() => {
  return `${monthNames[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`
})

const calendarDays = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  
  const firstDayOfMonth = new Date(year, month, 1).getDay()
  const adjustedFirstDay = firstDayOfMonth === 0 ? 6 : firstDayOfMonth - 1
  
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const daysInPrevMonth = new Date(year, month, 0).getDate()
  
  const days = []
  
  // Prev month padding
  for (let i = adjustedFirstDay - 1; i >= 0; i--) {
    days.push({ day: daysInPrevMonth - i, currentMonth: false })
  }
  
  // Current month
  for (let i = 1; i <= daysInMonth; i++) {
    days.push({ day: i, currentMonth: true })
  }
  
  // Next month padding
  const totalSlots = 42 // 6 rows
  const remaining = totalSlots - days.length
  for (let i = 1; i <= remaining; i++) {
    days.push({ day: i, currentMonth: false })
  }
  
  return days
})

function prevMonth() {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
}

function nextMonth() {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
}

function getEventColor(type: string) {
  switch (type) {
    case 'online': return '#6de33d'
    case 'offline': return '#f5b91a'
    case 'live': return '#f56161'
    case 'quiz_online': return '#5c90f2'
    case 'quiz_offline': return '#1a237e'
    case 'exam': return '#dc2626'
    case 'lesson': return 'var(--green)'
    case 'deadline': return '#d97706'
    case 'meeting': return '#2563eb'
    default: return 'var(--muted)'
  }
}

// Mock dots for calendar view demo
function getDotsForDay(day: number, currentMonth: boolean) {
  if (!currentMonth) return []
  if (day === 4) return ['quiz_online', 'quiz_online']
  if (day === 7) return ['online', 'offline', 'quiz_online']
  if (day === 8) return ['online']
  if (day === 9) return ['quiz_online']
  if (day === 29) return ['quiz_online']
  return []
}

function getBgForDay(day: number, currentMonth: boolean) {
  if (!currentMonth) return ''
  if (day === 8) return '#6de33d'
  if (day === 9) return '#5c90f2'
  if (day === 29) return '#5c90f2'
  if (day === 7) return '#71717a' // Dark grey for active selection in image
  return ''
}

function getEventsForDay(day: number) {
  // Mock data matching the dots
  if (day === 4) return [
    { title: 'Quiz Online: Vue.js', time: '10:00', type: 'quiz_online' },
    { title: 'Quiz Online: CSS Grid', time: '14:00', type: 'quiz_online' }
  ]
  if (day === 7) return [
    { title: 'Lớp học Online: Nuxt 3', time: '08:00', type: 'online' },
    { title: 'Lớp học Offline: Figma', time: '13:00', type: 'offline' },
    { title: 'Kiểm tra trắc nghiệm', time: '16:00', type: 'quiz_online' }
  ]
  if (day === 8) return [{ title: 'Lớp học Online: JS', time: '09:00', type: 'online' }]
  if (day === 9) return [{ title: 'Quiz Online: HTML', time: '15:00', type: 'quiz_online' }]
  if (day === 29) return [{ title: 'Quiz Online: Finale', time: '20:00', type: 'quiz_online' }]
  return []
}
</script>

<template>
  <div class="schedule-card dashboard-card">
    <header class="schedule-header">
      <div class="header-left">
        <p class="schedule-kicker">Lịch trình</p>
        <h3 class="schedule-title">{{ title || 'LEARNING SCHEDULE' }}</h3>
      </div>
      
      <div class="view-switcher">
        <button
          class="view-btn"
          :class="{ active: activeView === 'list' }"
          @click="activeView = 'list'"
        >
          <i class="pi pi-list" style="font-size:1.25rem" />
        </button>
        <button
          class="view-btn"
          :class="{ active: activeView === 'calendar' }"
          @click="activeView = 'calendar'"
        >
          <i class="pi pi-calendar" style="font-size:1.25rem" />
        </button>
      </div>
    </header>

    <!-- ── List View ── -->
    <div v-if="activeView === 'list'" class="list-view-content">
      <div class="schedule-date-nav">
        <button 
          v-for="(day, index) in daysShort" 
          :key="day"
          class="day-btn"
          :class="{ active: currentDay === index }"
          @click="currentDay = index"
        >
          {{ day }}
        </button>
      </div>

      <div class="schedule-list">
        <div v-if="events.length === 0" class="schedule-empty">
          <i class="pi pi-calendar" style="font-size:1.5rem" />
          <p>Không có hoạt động nào trong ngày này.</p>
        </div>
        
        <div v-for="event in events" :key="event.id" class="schedule-item">
          <div class="event-time">
            <strong>{{ event.time }}</strong>
            <span>{{ event.date }}</span>
          </div>
          <div class="event-line" :style="{ backgroundColor: getEventColor(event.type) }"></div>
          <div class="event-details">
            <h4>{{ event.title }}</h4>
            <p v-if="event.course">{{ event.course }}</p>
            <div class="event-meta">
              <span class="event-tag" :style="{ backgroundColor: getEventColor(event.type) + '15', color: getEventColor(event.type) }">
                {{ event.type }}
              </span>
              <span v-if="event.location" class="event-loc">
                <i class="pi pi-map-marker" style="font-size:1.0rem" />
                {{ event.location }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Calendar View ── -->
    <div v-else class="calendar-view-content">
      <div class="calendar-legend">
        <div class="legend-group">
          <strong>Course:</strong>
          <span class="legend-item"><i style="background: #6de33d"></i> Online</span>
          <span class="legend-item"><i style="background: #f5b91a"></i> Offline</span>
          <span class="legend-item"><i style="background: #f56161"></i> Live Class</span>
        </div>
        <div class="legend-group">
          <strong>Quiz:</strong>
          <span class="legend-item"><i style="background: #5c90f2"></i> Online</span>
          <span class="legend-item"><i style="background: #1a237e"></i> Offline</span>
        </div>
      </div>

      <div class="calendar-nav">
        <button class="nav-btn" @click="prevMonth">
          <i class="pi pi-chevron-left" style="font-size:1.125rem" />
        </button>
        <span class="month-label">{{ currentMonthLabel }}</span>
        <button class="nav-btn" @click="nextMonth">
          <i class="pi pi-chevron-right" style="font-size:1.125rem" />
        </button>
      </div>

      <div class="calendar-grid">
        <div v-for="day in daysShort" :key="day" class="weekday-label">{{ day }}</div>
        
        <div 
          v-for="(date, idx) in calendarDays" 
          :key="idx" 
          class="calendar-day"
          :class="{ 'not-current': !date.currentMonth, 'has-bg': getBgForDay(date.day, date.currentMonth) }"
          @mouseenter="hoveredDay = date.day; hoveredMonth = date.currentMonth"
          @mouseleave="hoveredDay = null"
        >
          <div class="day-dots">
            <span 
              v-for="(dot, dIdx) in getDotsForDay(date.day, date.currentMonth)" 
              :key="dIdx" 
              class="dot"
              :style="{ backgroundColor: getEventColor(dot) }"
            ></span>
          </div>
          <div 
            class="day-number" 
            :style="{ 
              backgroundColor: getBgForDay(date.day, date.currentMonth),
              color: getBgForDay(date.day, date.currentMonth) ? 'white' : '',
              border: date.day === 4 && date.currentMonth ? '1px solid #71717a' : ''
            }"
          >
            {{ date.day < 10 ? '0' + date.day : date.day }}
          </div>

          <!-- Tooltip -->
          <div v-if="hoveredDay === date.day && hoveredMonth === date.currentMonth && getDotsForDay(date.day, date.currentMonth).length > 0" class="calendar-tooltip">
            <div class="tooltip-header">Lịch ngày {{ date.day }} tháng {{ currentDate.getMonth() + 1 }}</div>
            <div v-for="(event, eIdx) in getEventsForDay(date.day)" :key="eIdx" class="tooltip-event">
              <div class="t-event-dot" :style="{ backgroundColor: getEventColor(event.type) }"></div>
              <div class="t-event-info">
                <strong>{{ event.title }}</strong>
                <span>{{ event.time }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <footer class="schedule-footer">
      <button class="view-all-btn">Xem chi tiết toàn bộ →</button>
    </footer>
  </div>
</template>

<style scoped>
.schedule-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: auto;
}

.schedule-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.schedule-kicker {
  margin: 0;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--muted);
}

.schedule-title {
  margin: 2px 0 0;
  font-size: 1.1rem;
  font-weight: 900;
  letter-spacing: -0.02em;
}

.view-switcher {
  display: flex;
  background: rgba(17, 17, 17, 0.04);
  padding: 3px;
  border-radius: 10px;
  gap: 2px;
}

.view-btn {
  width: 32px;
  height: 32px;
  border-radius: 7px;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  transition: all 0.2s;
}


.view-btn.active {
  background: white;
  color: var(--green);
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

/* ── List View ── */
.schedule-date-nav {
  display: flex;
  gap: 6px;
  margin-bottom: 16px;
}

.day-btn {
  flex: 1;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: rgba(17, 17, 17, 0.02);
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s;
}

.day-btn.active {
  background: var(--green);
  color: white;
  box-shadow: 0 3px 8px rgba(var(--green-rgb), 0.15);
}

.schedule-list {
  display: grid;
  gap: 12px;
}

.schedule-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.event-time {
  min-width: 50px;
  text-align: right;
}

.event-time strong {
  display: block;
  font-size: 0.85rem;
  font-weight: 800;
}

.event-time span {
  font-size: 0.6rem;
  color: var(--muted);
}

.event-line {
  width: 3px;
  height: 38px;
  border-radius: 99px;
  margin-top: 3px;
}

.event-details h4 {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 700;
}

.event-details p {
  margin: 1px 0 4px;
  font-size: 0.75rem;
  color: var(--muted);
}

.event-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.event-tag {
  font-size: 0.55rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 1px 6px;
  border-radius: 4px;
}

/* ── Calendar View ── */
.calendar-legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  margin-bottom: 16px;
  font-size: 0.72rem;
}

.legend-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.legend-group strong {
  font-weight: 800;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  color: #374151;
}

.legend-item i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.calendar-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-bottom: 16px;
}

.month-label {
  font-size: 0.95rem;
  font-weight: 900;
  color: var(--green-deep);
  min-width: 120px;
  text-align: center;
}

.nav-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}


.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
  max-width: 350px;
  margin: 0 auto;
}

.weekday-label {
  text-align: center;
  font-size: 0.7rem;
  font-weight: 700;
  padding-bottom: 6px;
  color: #64748b;
}

.calendar-day {
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: pointer;
}

.calendar-day.not-current {
  opacity: 0.15;
}

.day-dots {
  position: absolute;
  top: 1px;
  display: flex;
  gap: 1px;
}

.dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
}

.day-number {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 0.75rem;
  font-weight: 700;
  transition: all 0.2s;
}

.calendar-day:hover .day-number:not(.has-bg) {
  background: rgba(17, 17, 17, 0.04);
}

.calendar-tooltip {
  position: absolute;
  bottom: 110%;
  left: 50%;
  transform: translateX(-50%);
  width: 180px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.12);
  border: 1px solid rgba(17, 17, 17, 0.05);
  z-index: 100;
  padding: 10px;
  pointer-events: none;
  animation: fadeIn 0.2s ease-out;
}

.calendar-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 6px solid transparent;
  border-top-color: white;
}

.tooltip-header {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--muted);
  margin-bottom: 8px;
  text-transform: uppercase;
  border-bottom: 1px solid rgba(17, 17, 17, 0.05);
  padding-bottom: 4px;
}

.tooltip-event {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 6px;
}

.tooltip-event:last-child {
  margin-bottom: 0;
}

.t-event-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.t-event-info {
  display: flex;
  flex-direction: column;
}

.t-event-info strong {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text);
  line-height: 1.2;
}

.t-event-info span {
  font-size: 0.65rem;
  color: var(--muted);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateX(-50%) translateY(5px); }
  to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.schedule-footer {
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid rgba(17, 17, 17, 0.04);
}

.view-all-btn {
  background: transparent;
  border: none;
  color: var(--green);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .dashboard-schedule-widget { background: var(--surface); color: var(--text); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .widget-header h3 { color: var(--text); }
[data-theme="dark"] .today-highlight { background: rgba(255, 255, 255, 0.04); }
[data-theme="dark"] .td-date strong { color: var(--text); }
[data-theme="dark"] .date-badge { background: rgba(255, 255, 255, 0.05); color: var(--text); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .event-item { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .event-item:hover { background: rgba(255, 255, 255, 0.05); }
[data-theme="dark"] .event-main strong { color: var(--text); }
[data-theme="dark"] .mini-calendar-wrap { background: rgba(255, 255, 255, 0.02); }
[data-theme="dark"] .calendar-tooltip { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
[data-theme="dark"] .calendar-tooltip::after { border-top-color: var(--surface-strong); }
[data-theme="dark"] .calendar-day:hover .day-number:not(.has-bg) { background: rgba(255, 255, 255, 0.08); }
</style>
