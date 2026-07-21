<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()

// ── Data ─────────────────────────────────────────────
const loading = ref(true)
const enrollments = ref<any[]>([])
const exams = ref<any[]>([])
const certificates = ref<any[]>([])
const transcript = ref<any>(null)
const notifications = ref<any[]>([])
const announcements = ref<any[]>([])
const dashboardData = ref<any>(null)
const studentTasks = ref<any[]>([])

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0, r1, r2, r3, r4, r5, r6, r7] = await Promise.allSettled([
    useApi<any[]>('/user/enrollments', { headers: h }),
    useApi<any>('/exams/standalone?status=published&per_page=50', { headers: h }),
    useApi<any[]>('/user/my-certificates', { headers: h }),
    useApi<any>('/me/transcript', { headers: h }),
    useApi<any>('/user/notifications?per_page=8', { headers: h }),
    useApi<any>('/posts?type=announcement&per_page=3', { headers: h }),
    useApi<any>('/me/dashboard', { headers: h }),
    useApi<any>('/me/tasks', { headers: h }),
  ])
  if (r0.status === 'fulfilled') enrollments.value = r0.value || []
  if (r1.status === 'fulfilled') { const d = r1.value; exams.value = Array.isArray(d) ? d : (d?.data || []) }
  if (r2.status === 'fulfilled') { const d = r2.value; certificates.value = Array.isArray(d) ? d : (d?.data || []) }
  if (r3.status === 'fulfilled') transcript.value = r3.value
  if (r4.status === 'fulfilled') { const d = r4.value; notifications.value = Array.isArray(d) ? d : (d?.data || []) }
  if (r5.status === 'fulfilled') { const d = r5.value; announcements.value = Array.isArray(d) ? d : (d?.data || []) }
  if (r6.status === 'fulfilled') dashboardData.value = r6.value
  if (r7.status === 'fulfilled') studentTasks.value = r7.value || []
  loading.value = false
})

// ── KPI ──────────────────────────────────────────────
const totalEnrolled = computed(() => enrollments.value.length)
const inProgressCount = computed(() => enrollments.value.filter(e => (e.progress ?? 0) > 0 && (e.progress ?? 0) < 100).length)
const gpa = computed(() => {
  const raw = transcript.value?.gpa ?? transcript.value?.cumulative_gpa
  return raw ? Number(raw).toFixed(2) : '—'
})
const certCount = computed(() => certificates.value.length)

const allPendingTasks = computed(() => {
  const list: any[] = []
  studentTasks.value.forEach((c: any) => {
    (c.tasks || []).forEach((t: any) => {
      if (!t.is_completed) {
        list.push({
          ...t,
          course_title: c.course_title
        })
      }
    })
  })
  return list.sort((a, b) => new Date(a.deadline).getTime() - new Date(b.deadline).getTime())
})

function isTaskUrgent(deadline: string) {
  if (!deadline) return false
  const t = new Date(deadline).getTime() - Date.now()
  return t > 0 && t < 86400000 * 2
}

// ── Hero Slider ───────────────────────────────────────
const SLIDES = [
  {
    img: 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1200&q=80',
    title: 'Hành trình học tập của bạn',
    desc: 'Mỗi ngày một bước nhỏ — tương lai lớn hơn bạn tưởng.',
    cta: 'Khóa học của tôi',
    to: '/student/courses',
  },
  {
    img: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=1200&q=80',
    title: 'Học cùng cộng đồng',
    desc: 'Kết nối, trao đổi và phát triển cùng hàng nghìn học viên.',
    cta: 'Lộ trình học',
    to: '/student/learning-path',
  },
  {
    img: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=1200&q=80',
    title: 'Chinh phục mọi kỳ thi',
    desc: 'Theo dõi lịch thi, ôn tập và đạt kết quả tốt nhất.',
    cta: 'Xem lịch thi',
    to: '/student/exams',
  },
]

const slideIndex = ref(0)
const sliderPaused = ref(false)
let sliderTimer: ReturnType<typeof setInterval> | null = null

function startSlider() {
  sliderTimer = setInterval(() => {
    if (!sliderPaused.value) nextSlide()
  }, 5000)
}

function nextSlide() {
  slideIndex.value = (slideIndex.value + 1) % SLIDES.length
}
function prevSlide() {
  slideIndex.value = (slideIndex.value - 1 + SLIDES.length) % SLIDES.length
}
function goSlide(i: number) {
  slideIndex.value = i
  sliderPaused.value = true
  setTimeout(() => (sliderPaused.value = false), 8000)
}

onMounted(() => startSlider())
onUnmounted(() => { if (sliderTimer) clearInterval(sliderTimer) })

// ── Courses ───────────────────────────────────────────
const courseTab = ref('all')
const courseSearch = ref('')
const coursePage = ref(1)
const COURSE_PER_PAGE = 4

const filteredCourses = computed(() => {
  let list = enrollments.value
  if (courseTab.value === 'active') list = list.filter(e => (e.progress ?? 0) > 0 && (e.progress ?? 0) < 100)
  else if (courseTab.value === 'done') list = list.filter(e => (e.progress ?? 0) >= 100)
  else if (courseTab.value === 'new') list = list.filter(e => (e.progress ?? 0) === 0)
  if (courseSearch.value) {
    const q = courseSearch.value.toLowerCase()
    list = list.filter(e => (e.course?.title || e.title || '').toLowerCase().includes(q))
  }
  return list
})

const totalCoursePages = computed(() => Math.max(1, Math.ceil(filteredCourses.value.length / COURSE_PER_PAGE)))
const pagedCourses = computed(() => {
  const s = (coursePage.value - 1) * COURSE_PER_PAGE
  return filteredCourses.value.slice(s, s + COURSE_PER_PAGE)
})

function setCourseTab(t: string) { courseTab.value = t; coursePage.value = 1 }

// ── Exams ────────────────────────────────────────────
const examTab = ref('upcoming')

const filteredExams = computed(() => {
  const now = Date.now()
  if (examTab.value === 'upcoming') return exams.value.filter(e => new Date(e.start_time || e.date).getTime() > now)
  if (examTab.value === 'done') return exams.value.filter(e => new Date(e.end_time || e.start_time || e.date).getTime() < now)
  return exams.value
})

function examCountdown(e: any) {
  const t = new Date(e.start_time || e.date).getTime() - Date.now()
  if (t <= 0) return 'Đã diễn ra'
  const d = Math.floor(t / 86400000)
  if (d > 0) return `${d} ngày nữa`
  const h = Math.floor((t % 86400000) / 3600000)
  const m = Math.floor((t % 3600000) / 60000)
  return `${h}h ${m}m`
}

function examIsUrgent(e: any) {
  const t = new Date(e.start_time || e.date).getTime() - Date.now()
  return t > 0 && t < 86400000
}

// ── Calendar ─────────────────────────────────────────
const today = new Date()
const calYear = ref(today.getFullYear())
const calMonth = ref(today.getMonth())

const MONTHS_VI = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6',
                   'Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12']
const DAYS_VI = ['CN','T2','T3','T4','T5','T6','T7']

const calLabel = computed(() => `${MONTHS_VI[calMonth.value]} · ${calYear.value}`)

function prevMonth() {
  if (calMonth.value === 0) { calYear.value--; calMonth.value = 11 } else calMonth.value--
}
function nextMonth() {
  if (calMonth.value === 11) { calYear.value++; calMonth.value = 0 } else calMonth.value++
}

const calendarDays = computed(() => {
  const first = new Date(calYear.value, calMonth.value, 1).getDay()
  const total = new Date(calYear.value, calMonth.value + 1, 0).getDate()
  const days: Array<{ day: number; isToday: boolean; hasEvent: boolean; inMonth: boolean }> = []
  for (let i = 0; i < first; i++) days.push({ day: 0, isToday: false, hasEvent: false, inMonth: false })
  for (let d = 1; d <= total; d++) {
    const isToday = d === today.getDate() && calMonth.value === today.getMonth() && calYear.value === today.getFullYear()
    const dateStr = `${calYear.value}-${String(calMonth.value + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`
    const hasEvent = exams.value.some(e => (e.start_time || e.date || '').startsWith(dateStr))
    days.push({ day: d, isToday, hasEvent, inMonth: true })
  }
  return days
})

const agendaItems = computed(() => {
  const now = Date.now()
  return exams.value
    .filter(e => new Date(e.start_time || e.date).getTime() > now)
    .sort((a, b) => new Date(a.start_time || a.date).getTime() - new Date(b.start_time || b.date).getTime())
    .slice(0, 4)
})

const recentNotifs = computed(() => {
  const base = announcements.value.length > 0 ? announcements.value : notifications.value
  return base.slice(0, 4)
})

const user = computed(() => auth.user)
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Chào buổi sáng'
  if (h < 18) return 'Chào buổi chiều'
  return 'Chào buổi tối'
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 py-2 flex flex-col gap-6">
    <!-- Main grid -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 items-start">
      <!-- Main column -->
      <div class="flex flex-col gap-6 min-w-0">
        <!-- Hero Slider -->
        <div
          class="relative rounded-2xl overflow-hidden h-[180px] bg-slate-950 group"
          @mouseenter="sliderPaused = true"
          @mouseleave="sliderPaused = false"
        >
          <div class="relative w-full h-full">
            <div
              v-for="(slide, i) in SLIDES"
              v-show="slideIndex === i"
              :key="i"
              class="absolute inset-0 transition-opacity duration-700"
            >
              <img :src="slide.img" :alt="slide.title" class="w-full h-full object-cover object-center opacity-40" loading="lazy">
              <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent"></div>
              <div class="absolute inset-0 flex flex-col justify-center px-8 md:px-12 py-4">
                <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest mb-1.5">{{ greeting }}, {{ user?.name || 'Học viên' }} 👋</p>
                <h1 class="text-lg md:text-xl font-bold text-white mb-1.5 leading-snug">{{ slide.title }}</h1>
                <p class="text-xs text-slate-300 max-w-sm mb-4 font-medium leading-relaxed">{{ slide.desc }}</p>
                <NuxtLink :to="slide.to" class="h-8 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#157959] text-white text-xs font-bold flex items-center justify-center transition-colors self-start">{{ slide.cta }}</NuxtLink>
              </div>
            </div>
          </div>

          <!-- Arrows -->
          <button class="absolute left-4 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full border border-white/20 bg-black/30 hover:bg-black/50 text-white flex items-center justify-center transition-colors backdrop-blur-sm opacity-0 group-hover:opacity-100" @click="prevSlide">
            <span class="material-symbols-outlined text-base">chevron_left</span>
          </button>
          <button class="absolute right-4 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full border border-white/20 bg-black/30 hover:bg-black/50 text-white flex items-center justify-center transition-colors backdrop-blur-sm opacity-0 group-hover:opacity-100" @click="nextSlide">
            <span class="material-symbols-outlined text-base">chevron_right</span>
          </button>

          <!-- Dots -->
          <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
            <button
              v-for="(_, i) in SLIDES"
              :key="i"
              class="h-1.5 rounded-full transition-all duration-350"
              :class="slideIndex === i ? 'w-5 bg-white' : 'w-1.5 bg-white/40'"
              @click="goSlide(i)"
            ></button>
          </div>
        </div>

        <!-- My Courses -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Học tập</p>
              <h2 class="text-sm font-bold text-[var(--text)]">Khóa học của tôi</h2>
            </div>
            <NuxtLink to="/student/courses" class="text-xs font-bold text-[#1d9e75] hover:underline">Xem tất cả</NuxtLink>
          </div>

          <!-- Tabs + Search -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-b border-[var(--line)] py-3">
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="t in [{k:'all',l:'Tất cả'},{k:'active',l:'Đang học'},{k:'new',l:'Chưa bắt đầu'},{k:'done',l:'Hoàn thành'}]"
                :key="t.k"
                class="h-7 px-3 rounded-lg text-xs font-bold transition-colors"
                :class="courseTab === t.k ? 'bg-emerald-50 text-[#085041] border border-emerald-100' : 'text-[var(--muted)] hover:bg-[var(--surface)]'"
                @click="setCourseTab(t.k)"
              >{{ t.l }}</button>
            </div>
            <div class="relative w-full sm:w-56">
              <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm leading-none">search</span>
              <input
                v-model="courseSearch"
                type="search"
                placeholder="Tìm khóa học..."
                class="w-full h-8 pl-8 pr-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]"
                @input="coursePage = 1"
              >
            </div>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="flex flex-col gap-2.5 animate-pulse">
            <div v-for="i in 3" :key="i" class="h-16 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl" />
          </div>

          <!-- Course list -->
          <div v-else-if="pagedCourses.length" class="flex flex-col gap-2.5">
            <NuxtLink
              v-for="e in pagedCourses"
              :key="e.id"
              :to="`/student/courses/${e.course?.id || e.course_id || e.id}`"
              class="flex items-center gap-3.5 p-3 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] transition-colors text-left"
            >
              <div class="w-11 h-9 rounded-lg bg-[var(--surface)] flex items-center justify-center overflow-hidden flex-shrink-0 border border-[var(--line)]">
                <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" :alt="e.course?.title" loading="lazy" class="w-full h-full object-cover">
                <span v-else class="material-symbols-outlined text-base text-[var(--muted)]">auto_stories</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-[var(--text)] truncate leading-relaxed">{{ e.course?.title || e.title || 'Khóa học' }}</p>
                <p class="text-[10px] text-[var(--muted)] font-semibold mt-0.5 truncate">{{ e.course?.instructor?.name || e.instructor || '' }}</p>
              </div>
              <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-extrabold text-[var(--text)]">{{ Math.round(e.progress || 0) }}%</span>
                  <div class="w-14 h-1.5 bg-slate-100 border border-slate-200/50 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full" :style="{ width: `${e.progress || 0}%` }"></div>
                  </div>
                </div>
                <span
                  class="px-2 py-0.5 rounded text-[8px] font-bold border"
                  :class="(e.progress || 0) >= 100 ? 'bg-sky-50 text-sky-700 border-sky-100' : (e.progress || 0) > 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-100'"
                >
                  {{ (e.progress || 0) >= 100 ? 'Hoàn thành' : (e.progress || 0) > 0 ? 'Đang học' : 'Chưa bắt đầu' }}
                </span>
              </div>
            </NuxtLink>
          </div>

          <div v-else class="flex flex-col items-center gap-2.5 text-center py-10">
            <span class="material-symbols-outlined text-3xl text-[var(--muted)] opacity-60">auto_stories</span>
            <p class="text-xs font-semibold text-[var(--muted)]">Chưa có khóa học nào.</p>
            <NuxtLink to="/student/recommendations" class="h-8 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#157959] text-white text-xs font-bold flex items-center transition-colors mt-1">Khám phá khóa học</NuxtLink>
          </div>

          <!-- Pagination -->
          <div v-if="totalCoursePages > 1" class="flex items-center justify-center gap-1 pt-3 border-t border-[var(--line)]">
            <button :disabled="coursePage === 1" class="w-8 h-8 rounded-lg border border-[var(--line)] hover:bg-[var(--surface)] text-xs font-bold text-[var(--muted)] disabled:opacity-40 flex items-center justify-center" @click="coursePage--">
              <span class="material-symbols-outlined text-sm leading-none">chevron_left</span>
            </button>
            <button
              v-for="p in totalCoursePages"
              :key="p"
              class="w-8 h-8 rounded-lg border text-xs font-bold transition-all"
              :class="coursePage === p ? 'bg-[#1d9e75] border-transparent text-white' : 'border-[var(--line)] hover:bg-[var(--surface)] text-[var(--muted)]'"
              @click="coursePage = p"
            >{{ p }}</button>
            <button :disabled="coursePage === totalCoursePages" class="w-8 h-8 rounded-lg border border-[var(--line)] hover:bg-[var(--surface)] text-xs font-bold text-[var(--muted)] disabled:opacity-40 flex items-center justify-center" @click="coursePage++">
              <span class="material-symbols-outlined text-sm leading-none">chevron_right</span>
            </button>
          </div>
        </div>

        <!-- My Exams -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Khảo thí</p>
              <h2 class="text-sm font-bold text-[var(--text)]">Kỳ thi của tôi</h2>
            </div>
            <NuxtLink to="/student/exams" class="text-xs font-bold text-[#1d9e75] hover:underline">Xem tất cả</NuxtLink>
          </div>

          <!-- Tabs -->
          <div class="flex gap-1.5 border-b border-[var(--line)] pb-3">
            <button
              v-for="t in [{k:'upcoming',l:'Sắp diễn ra'},{k:'all',l:'Tất cả'},{k:'done',l:'Đã xong'}]"
              :key="t.k"
              class="h-7 px-3 rounded-lg text-xs font-bold transition-colors"
              :class="examTab === t.k ? 'bg-emerald-50 text-[#085041] border border-emerald-100' : 'text-[var(--muted)] hover:bg-[var(--surface)]'"
              @click="examTab = t.k"
            >{{ t.l }}</button>
          </div>

          <div v-if="loading" class="flex flex-col gap-2.5 animate-pulse">
            <div v-for="i in 3" :key="i" class="h-14 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl" />
          </div>

          <div v-else-if="filteredExams.length" class="flex flex-col gap-2">
            <div v-for="e in filteredExams.slice(0, 4)" :key="e.id" class="flex items-center gap-3.5 p-3 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] transition-colors">
              <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="examIsUrgent(e) ? 'bg-rose-500 animate-pulse' : 'bg-amber-400'"></span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-[var(--text)] truncate leading-relaxed">{{ e.title || e.name }}</p>
                <p class="text-[10px] text-[var(--muted)] font-semibold mt-0.5 truncate">{{ e.course?.title || '' }}</p>
              </div>
              <span class="text-[10px] font-extrabold flex-shrink-0" :class="examIsUrgent(e) ? 'text-rose-600' : 'text-emerald-600'">
                {{ examCountdown(e) }}
              </span>
              <NuxtLink to="/student/exams" class="h-7 px-3 rounded-lg border border-[var(--line)] hover:bg-[var(--surface)] text-[10px] font-extrabold text-[var(--muted)] transition-colors flex items-center justify-center">Chi tiết</NuxtLink>
            </div>
          </div>

          <div v-else class="flex flex-col items-center gap-2 text-center py-10">
            <span class="material-symbols-outlined text-3xl text-[var(--muted)] opacity-60">assignment</span>
            <p class="text-xs font-semibold text-[var(--muted)]">Không có kỳ thi nào.</p>
          </div>
        </div>
      </div>

      <!-- Right panel -->
      <aside class="flex flex-col gap-6">
        <!-- Student Profile Card (GPA & Achievements) -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-extrabold text-base overflow-hidden border border-emerald-100">
              <img v-if="dashboardData?.student?.avatar" :src="dashboardData.student.avatar" alt="Avatar" class="w-full h-full object-cover">
              <span v-else>{{ dashboardData?.student?.name ? dashboardData.student.name.slice(0, 2).toUpperCase() : 'SV' }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-xs font-bold text-[var(--text)] truncate leading-snug">{{ dashboardData?.student?.name || 'Học viên' }}</h3>
              <p class="text-[9px] text-[var(--muted)] font-bold uppercase tracking-wider mt-0.5">MSSV: {{ dashboardData?.student?.student_code || '—' }}</p>
            </div>
          </div>

          <div class="flex flex-col gap-2.5 pt-3 border-t border-[var(--line)] text-xs text-[var(--text)]">
            <div class="flex justify-between items-center gap-2">
              <span class="text-[var(--muted)] font-semibold">Lớp hành chính:</span>
              <span class="font-bold truncate max-w-[60%]">{{ dashboardData?.student?.administrative_class?.name || dashboardData?.student?.administrative_class?.code || 'Chưa xếp lớp' }}</span>
            </div>
            <div class="flex justify-between items-center gap-2">
              <span class="text-[var(--muted)] font-semibold">Khoá học:</span>
              <span class="font-bold truncate max-w-[60%]">{{ dashboardData?.student?.cohort?.name || dashboardData?.student?.cohort?.code || '—' }}</span>
            </div>
            <div class="flex justify-between items-center gap-2">
              <span class="text-[var(--muted)] font-semibold">Ngành học:</span>
              <span class="font-bold truncate max-w-[60%]">{{ dashboardData?.student?.major?.name || '—' }}</span>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2 bg-[var(--surface)] p-3 rounded-xl border border-[var(--line)] text-center mt-1">
            <div class="flex flex-col">
              <span class="text-sm font-extrabold text-amber-500 leading-snug">{{ gpa }}</span>
              <span class="text-[8px] text-[var(--muted)] font-bold uppercase tracking-wider">GPA</span>
            </div>
            <div class="flex flex-col border-l border-r border-[var(--line)]">
              <span class="text-sm font-extrabold text-purple-600 leading-snug">{{ certCount }}</span>
              <span class="text-[8px] text-[var(--muted)] font-bold uppercase tracking-wider">C.Chỉ</span>
            </div>
            <div class="flex flex-col">
              <span class="text-sm font-extrabold text-emerald-600 leading-snug">{{ enrollments.filter(e => (e.progress || 0) >= 100).length }}</span>
              <span class="text-[8px] text-[var(--muted)] font-bold uppercase tracking-wider">Xong</span>
            </div>
          </div>
        </div>

        <!-- Administrative Class Advisor -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4.5" v-if="dashboardData?.student?.administrative_class">
          <div class="flex flex-col">
            <p class="text-[9px] font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Lớp hành chính</p>
            <h3 class="text-xs font-bold text-[var(--text)]">{{ dashboardData.student.administrative_class.name || dashboardData.student.administrative_class.code }}</h3>
          </div>
          
          <div class="flex items-center gap-2.5 bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100" v-if="dashboardData.student.administrative_class.advisor">
            <span class="material-symbols-outlined text-emerald-700 text-lg leading-none flex-shrink-0">co_present</span>
            <div class="min-w-0">
              <p class="text-[10px] font-bold text-[#085041] truncate leading-tight">GVCN: {{ dashboardData.student.administrative_class.advisor.name }}</p>
              <p class="text-[9px] text-[#0d7c65] font-semibold truncate leading-normal mt-0.5">{{ dashboardData.student.administrative_class.advisor.email }}</p>
            </div>
          </div>

          <div class="flex flex-col gap-2.5">
            <p class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Thành viên lớp ({{ dashboardData.student.administrative_class.students?.length || 0 }})</p>
            <div class="max-h-48 overflow-y-auto flex flex-col gap-2 pr-1 scrollbar-thin">
              <div v-for="member in dashboardData.student.administrative_class.students" :key="member.id" class="flex items-center gap-2.5 p-1.5 rounded-lg border transition-colors" :class="member.id === dashboardData.student.id ? 'bg-emerald-50/40 border-emerald-100' : 'border-transparent bg-slate-50/50'">
                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center font-bold text-[9px] overflow-hidden flex-shrink-0">
                  <img v-if="member.avatar" :src="member.avatar" alt="Avatar" class="w-full h-full object-cover">
                  <span v-else class="text-slate-500">{{ member.name.slice(0, 2).toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[10px] font-bold text-[var(--text)] truncate leading-tight">{{ member.name }} <span v-if="member.id === dashboardData.student.id" class="text-[8px] font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded ml-1">(Bạn)</span></p>
                  <p class="text-[8px] text-[var(--muted)] font-semibold mt-0.5">{{ member.student_code }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calendar Widget -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-xs font-bold text-[var(--text)]">Lịch tháng</h3>
            <div class="flex items-center gap-1">
              <button class="w-5 h-5 rounded hover:bg-[var(--surface)] flex items-center justify-center text-[var(--muted)]" aria-label="Tháng trước" @click="prevMonth">
                <span class="material-symbols-outlined text-sm leading-none">chevron_left</span>
              </button>
              <span class="text-[10px] font-extrabold text-[var(--text)] whitespace-nowrap">{{ calLabel }}</span>
              <button class="w-5 h-5 rounded hover:bg-[var(--surface)] flex items-center justify-center text-[var(--muted)]" aria-label="Tháng sau" @click="nextMonth">
                <span class="material-symbols-outlined text-sm leading-none">chevron_right</span>
              </button>
            </div>
          </div>

          <div class="grid grid-cols-7 gap-1">
            <div v-for="d in DAYS_VI" :key="d" class="text-center text-[8px] font-bold text-[var(--muted)] py-1 uppercase">{{ d }}</div>
            <div
              v-for="(cell, idx) in calendarDays"
              :key="idx"
              class="relative flex flex-col items-center justify-center aspect-square rounded-md text-[10px] font-bold"
              :class="[
                !cell.inMonth ? 'text-transparent' : 'text-[var(--text)]',
                cell.isToday ? 'bg-emerald-600 text-white shadow-sm' : cell.hasEvent ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'hover:bg-[var(--surface)]'
              ]"
            >
              <span v-if="cell.inMonth">{{ cell.day }}</span>
              <span v-if="cell.hasEvent && cell.inMonth" class="absolute bottom-1 w-1 h-1 rounded-full" :class="cell.isToday ? 'bg-white' : 'bg-emerald-500'"></span>
            </div>
          </div>

          <!-- Agenda -->
          <div v-if="agendaItems.length" class="flex flex-col gap-2.5 pt-3 border-t border-[var(--line)]">
            <p class="text-[9px] font-bold uppercase tracking-wider text-[var(--muted)]">Lịch trình sắp tới</p>
            <div class="flex flex-col gap-1.5">
              <div v-for="item in agendaItems" :key="item.id" class="flex items-center gap-2 p-2 bg-slate-50/50 hover:bg-[var(--surface)] border border-[var(--line)] rounded-xl transition-colors text-left">
                <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                <div class="flex-1 min-w-0">
                  <p class="text-[10px] font-bold text-[var(--text)] truncate leading-tight">{{ item.title || item.name }}</p>
                  <p class="text-[8px] text-[var(--muted)] font-semibold mt-0.5">{{ new Date(item.start_time || item.date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }) }}</p>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-[10px] text-[var(--muted)] font-medium text-center py-2">Không có sự kiện sắp tới.</p>
          <NuxtLink to="/student/calendar" class="text-center pt-3 border-t border-[var(--line)] text-[10px] font-bold text-[#1d9e75] hover:underline">Xem lịch đầy đủ →</NuxtLink>
        </div>

        <!-- Tasks Card -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Công việc</p>
              <h3 class="text-xs font-bold text-[var(--text)]">Nhiệm vụ cần làm</h3>
            </div>
            <span class="material-symbols-outlined text-emerald-600 text-lg">playlist_add_check</span>
          </div>

          <div class="flex flex-col gap-2" v-if="allPendingTasks.length">
            <div v-for="task in allPendingTasks.slice(0, 4)" :key="task.id" class="flex items-start gap-2.5 p-2 rounded-xl bg-slate-50/50 border border-[var(--line)]">
              <span class="material-symbols-outlined text-slate-400 text-sm mt-0.5 flex-shrink-0">radio_button_unchecked</span>
              <div class="flex-1 min-w-0 text-left">
                <p class="text-[10px] font-bold text-[var(--text)] truncate leading-tight">{{ task.title }}</p>
                <div class="flex flex-wrap items-center gap-1.5 mt-1 text-[8px] font-semibold text-[var(--muted)]">
                  <span class="truncate max-w-[120px]">{{ task.course_title }}</span>
                  <span v-if="task.deadline" class="px-1 py-0.5 rounded flex-shrink-0" :class="isTaskUrgent(task.deadline) ? 'bg-red-50 text-red-700 font-extrabold' : 'bg-slate-100 text-slate-600'">
                    Hạn: {{ new Date(task.deadline).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-[10px] text-[var(--muted)] font-medium text-center py-4">Không có nhiệm vụ chưa hoàn thành.</p>
          <NuxtLink to="/student/tasks" class="text-center pt-3 border-t border-[var(--line)] text-[10px] font-bold text-[#1d9e75] hover:underline">Xem tất cả nhiệm vụ →</NuxtLink>
        </div>

        <!-- Leaderboard -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Bảng xếp hạng</p>
              <h3 class="text-xs font-bold text-[var(--text)]">Thành tích lớp</h3>
            </div>
            <span class="material-symbols-outlined text-amber-500 text-lg">emoji_events</span>
          </div>

          <div class="flex flex-col gap-1.5">
            <div v-for="(item, index) in [
              { name: 'Nguyễn Hoàng Đức', gpa: '3.92' },
              { name: 'Trần Thị Mai', gpa: '3.85' },
              { name: 'Lê Huy Quốc', gpa: '3.78' },
              { name: 'Phạm Minh Chính', gpa: '3.65' }
            ]" :key="index" class="flex items-center gap-2.5 p-2 rounded-xl border border-[var(--line)] text-left" :class="index === 0 ? 'bg-amber-500/5' : index === 1 ? 'bg-slate-500/5' : index === 2 ? 'bg-amber-700/5' : 'bg-white'">
              <div class="w-5 h-5 rounded-lg flex items-center justify-center flex-shrink-0">
                <span v-if="index === 0" class="material-symbols-outlined text-amber-500 text-base leading-none">workspace_premium</span>
                <span v-else-if="index === 1" class="material-symbols-outlined text-slate-400 text-base leading-none">workspace_premium</span>
                <span v-else-if="index === 2" class="material-symbols-outlined text-amber-700 text-base leading-none">workspace_premium</span>
                <span v-else class="text-[10px] font-extrabold text-[var(--muted)]">{{ index + 1 }}</span>
              </div>
              <span class="text-[10px] font-bold text-[var(--text)] flex-1 truncate">{{ item.name }}</span>
              <span class="text-[10px] font-extrabold text-[#1d9e75] flex-shrink-0">{{ item.gpa }} GPA</span>
            </div>
          </div>
        </div>

        <!-- Notifications Widget -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div class="flex items-center justify-between gap-4">
            <h3 class="text-xs font-bold text-[var(--text)]">Thông báo</h3>
            <NuxtLink to="/student/notifications" class="text-xs font-bold text-[#1d9e75] hover:underline">Tất cả</NuxtLink>
          </div>

          <div v-if="loading" class="flex flex-col gap-2 animate-pulse">
            <span v-for="i in 3" :key="i" class="h-10 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl" />
          </div>

          <div v-else-if="recentNotifs.length" class="flex flex-col gap-2">
            <div v-for="n in recentNotifs" :key="n.id" class="flex items-start gap-2.5 p-2 rounded-xl bg-slate-50/50 hover:bg-[var(--surface)] border border-[var(--line)] transition-colors">
              <span class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" :class="!n.read_at ? 'bg-emerald-500' : 'bg-slate-300'"></span>
              <div class="flex-1 min-w-0 text-left">
                <p class="text-[10px] font-bold text-[var(--text)] truncate leading-tight">{{ n.title || n.data?.title || 'Thông báo' }}</p>
                <p class="text-[8px] text-[var(--muted)] font-semibold mt-0.5">{{ n.created_at ? new Date(n.created_at).toLocaleDateString('vi-VN') : '' }}</p>
              </div>
            </div>
          </div>
          <p v-else class="text-[10px] text-[var(--muted)] font-medium text-center py-4">Không có thông báo mới.</p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
/* Minimal custom overrides for layout, keeping it clean */
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: var(--line);
  border-radius: 4px;
}
</style>
