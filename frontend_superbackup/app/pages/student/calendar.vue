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
  <div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Lịch học</p>
        <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Lịch & Sự kiện</h1>
      </div>
      <div class="flex items-center gap-2">
        <button class="px-3.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors" @click="goToday">Hôm nay</button>
        <div class="flex border border-slate-200 rounded-lg overflow-hidden">
          <button :class="['flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-colors', view === 'month' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-slate-500 hover:bg-slate-50']" @click="view='month'">
            <span class="material-symbols-outlined text-sm">calendar_month</span> Tháng
          </button>
          <button :class="['flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold transition-colors', view === 'agenda' ? 'bg-emerald-50 text-emerald-700' : 'bg-white text-slate-500 hover:bg-slate-50']" @click="view='agenda'">
            <span class="material-symbols-outlined text-sm">list</span> Danh sách
          </button>
        </div>
      </div>
    </div>

    <div v-if="view==='month'" class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5 items-start">
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <button class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 flex items-center justify-center hover:bg-slate-50 hover:text-slate-900 transition-colors" @click="prevMonth" aria-label="Tháng trước">
            <span class="material-symbols-outlined text-base">chevron_left</span>
          </button>
          <h2 class="text-sm font-bold text-slate-900">{{ calLabel }}</h2>
          <button class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 flex items-center justify-center hover:bg-slate-50 hover:text-slate-900 transition-colors" @click="nextMonth" aria-label="Tháng sau">
            <span class="material-symbols-outlined text-base">chevron_right</span>
          </button>
        </div>

        <div v-if="loading" class="grid grid-cols-7 gap-1">
          <span v-for="i in 35" :key="i" class="h-20 bg-slate-100 animate-pulse rounded-lg"></span>
        </div>

        <div v-else class="grid grid-cols-7 gap-1">
          <div v-for="d in DAYS_VI" :key="d" class="text-center text-[10px] font-bold text-slate-400 uppercase py-1">{{ d }}</div>
          <div
            v-for="(cell, idx) in calendarDays"
            :key="idx"
            class="min-h-[84px] rounded-lg border border-transparent p-1 cursor-pointer transition-colors flex flex-col gap-1"
            :class="{
              'bg-slate-50': !cell.inMonth,
              'bg-emerald-50 border-emerald-500': cell.isToday || (selectedDate === cell.dateStr && cell.inMonth),
              'hover:bg-slate-50 border-slate-100': cell.inMonth && !cell.isToday && selectedDate !== cell.dateStr,
              'cursor-default': !cell.inMonth
            }"
            @click="cell.inMonth && (selectedDate = selectedDate === cell.dateStr ? null : cell.dateStr)"
          >
            <span v-if="cell.inMonth" class="text-xs font-bold" :class="cell.isToday ? 'text-emerald-700' : 'text-slate-900'">{{ cell.day }}</span>
            <div v-if="cell.events.length" class="flex flex-col gap-0.5">
              <span v-for="(ev, ei) in cell.events.slice(0,2)" :key="ei" class="text-[9px] font-bold bg-emerald-500 text-white px-1 py-0.5 rounded truncate">
                {{ ev.title?.slice(0,12) || 'Kỳ thi' }}
              </span>
              <span v-if="cell.events.length > 2" class="text-[9px] text-slate-400 pl-0.5 font-bold">+{{ cell.events.length - 2 }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-4">
        <div v-if="selectedDate && selectedEvents.length" class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
          <h3 class="text-xs font-bold text-slate-900 mb-3 border-b border-slate-100 pb-2">
            {{ new Date(selectedDate + 'T00:00:00').toLocaleDateString('vi-VN', {weekday:'long',day:'2-digit',month:'long',year:'numeric'}) }}
          </h3>
          <div class="flex flex-col gap-3">
            <div v-for="ev in selectedEvents" :key="ev.id" class="flex gap-3">
              <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-base">assignment</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-slate-900 truncate">{{ ev.title || ev.name }}</p>
                <p class="text-[10px] text-slate-500 truncate">{{ ev.course?.title || 'Kỳ thi độc lập' }}</p>
                <p class="text-[10px] text-emerald-600 font-bold mt-0.5">{{ formatDateTime(ev.start_time || ev.date) }}</p>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="selectedDate" class="bg-white border border-slate-200 rounded-2xl p-6 text-center flex flex-col items-center gap-2 text-slate-400">
          <span class="material-symbols-outlined text-3xl">calendar_today</span>
          <p class="text-xs font-medium">Không có sự kiện trong ngày này.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
          <h3 class="text-xs font-bold text-slate-900 mb-3">Sắp tới</h3>
          <div v-if="loading" class="flex flex-col gap-2">
            <span v-for="i in 5" :key="i" class="h-12 bg-slate-100 animate-pulse rounded-lg"></span>
          </div>
          <div v-else-if="agendaExams.length" class="flex flex-col gap-2">
            <div v-for="ev in agendaExams.slice(0,6)" :key="ev.id" class="flex items-center gap-3 p-2 rounded-lg border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50 transition-all cursor-pointer"
              @click="selectedDate = eventDateStr(ev); calYear = new Date(ev.start_time||ev.date).getFullYear(); calMonth = new Date(ev.start_time||ev.date).getMonth()">
              <div class="w-9 text-center bg-emerald-50 rounded-lg py-1 flex-shrink-0">
                <span class="block text-sm font-extrabold text-emerald-700 leading-none">{{ new Date(ev.start_time||ev.date).getDate() }}</span>
                <span class="block text-[8px] font-bold text-emerald-600 uppercase">{{ MONTHS_VI[new Date(ev.start_time||ev.date).getMonth()].slice(6) }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-slate-900 truncate">{{ ev.title || ev.name }}</p>
                <p class="text-[10px] text-slate-500">{{ new Date(ev.start_time||ev.date).toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'}) }}</p>
              </div>
            </div>
          </div>
          <p v-else class="text-xs text-slate-400 text-center py-4">Không có sự kiện sắp tới.</p>
        </div>
      </div>
    </div>

    <div v-else class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
      <div v-if="loading" class="flex flex-col gap-3">
        <span v-for="i in 8" :key="i" class="h-16 bg-slate-100 animate-pulse rounded-lg"></span>
      </div>
      <div v-else-if="agendaExams.length" class="flex flex-col gap-4">
        <div v-for="ev in agendaExams" :key="ev.id" class="flex items-start gap-4 pb-4 border-b border-slate-100 last:border-0 last:pb-0">
          <div class="w-12 text-center flex-shrink-0 pt-1">
            <span class="block text-xl font-extrabold text-slate-900 leading-none">{{ new Date(ev.start_time||ev.date).getDate() }}</span>
            <span class="block text-[10px] font-bold text-slate-400 uppercase mt-1">{{ MONTHS_VI[new Date(ev.start_time||ev.date).getMonth()] }}</span>
          </div>
          <div class="w-px self-stretch bg-slate-200"></div>
          <div class="flex items-start gap-3 flex-1">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-base">assignment</span>
            </div>
            <div>
              <p class="text-sm font-bold text-slate-900 leading-snug">{{ ev.title || ev.name }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5">{{ ev.course?.title || 'Kỳ thi độc lập' }} &bull; {{ formatDateTime(ev.start_time || ev.date) }}</p>
              <p v-if="ev.duration_minutes" class="text-[10px] font-bold text-emerald-600 mt-1">{{ ev.duration_minutes }} phút</p>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="flex flex-col items-center justify-center py-16 text-slate-400 gap-3">
        <span class="material-symbols-outlined text-4xl">calendar_today</span>
        <p class="text-sm font-medium">Không có sự kiện sắp tới.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
