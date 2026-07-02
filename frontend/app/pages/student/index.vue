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
const COURSE_PER_PAGE = 6

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
  return base.slice(0, 5)
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
  <div class="sd-page">



    <!-- ── Main grid ── -->
    <div class="sd-grid">

      <!-- Main column -->
      <div class="sd-main-col">

        <!-- ── Hero Slider ── -->
        <div
          class="sd-slider"
          @mouseenter="sliderPaused = true"
          @mouseleave="sliderPaused = false"
        >
          <!-- Slides -->
          <transition-group name="sd-slide" tag="div" class="sd-slides-wrap">
            <div
              v-for="(slide, i) in SLIDES"
              v-show="slideIndex === i"
              :key="i"
              class="sd-slide"
            >
              <img :src="slide.img" :alt="slide.title" class="sd-slide-img" loading="lazy">
              <div class="sd-slide-overlay"></div>
              <div class="sd-slide-content">
                <p class="sd-slide-greeting">{{ greeting }}, {{ user?.name || 'Học viên' }} 👋</p>
                <h1 class="sd-slide-title">{{ slide.title }}</h1>
                <p class="sd-slide-desc">{{ slide.desc }}</p>
                <NuxtLink :to="slide.to" class="sd-slide-btn">{{ slide.cta }}</NuxtLink>
              </div>
            </div>
          </transition-group>

          <!-- Prev / Next arrows -->
          <button class="sd-sl-arrow sd-sl-prev" aria-label="Ảnh trước" @click="prevSlide">
            <i class="material-symbols-outlined">chevron_left</i>
          </button>
          <button class="sd-sl-arrow sd-sl-next" aria-label="Ảnh sau" @click="nextSlide">
            <i class="material-symbols-outlined">chevron_right</i>
          </button>

          <!-- Dots -->
          <div class="sd-sl-dots" role="tablist" aria-label="Chọn ảnh">
            <button
              v-for="(_, i) in SLIDES"
              :key="i"
              role="tab"
              class="sd-sl-dot"
              :class="{ active: slideIndex === i }"
              :aria-selected="slideIndex === i"
              :aria-label="`Ảnh ${i + 1}`"
              @click="goSlide(i)"
            ></button>
          </div>
        </div>

        <!-- My Courses -->
        <div class="sd-card">
          <div class="sd-card-hd">
            <div>
              <p class="sd-kicker">Học tập</p>
              <h2 class="sd-card-title">Khóa học của tôi</h2>
            </div>
            <NuxtLink to="/student/courses" class="sd-link-more">Xem tất cả</NuxtLink>
          </div>

          <!-- Tabs + Search -->
          <div class="sd-toolbar">
            <div class="sd-tabs" role="tablist">
              <button
                v-for="t in [{k:'all',l:'Tất cả'},{k:'active',l:'Đang học'},{k:'new',l:'Chưa bắt đầu'},{k:'done',l:'Hoàn thành'}]"
                :key="t.k"
                role="tab"
                class="sd-tab"
                :class="{ on: courseTab === t.k }"
                @click="setCourseTab(t.k)"
              >{{ t.l }}</button>
            </div>
            <div class="sd-search-wrap">
              <i class="material-symbols-outlined sd-search-ico">search</i>
              <input
                v-model="courseSearch"
                type="search"
                placeholder="Tìm khóa học..."
                class="sd-search"
                @input="coursePage = 1"
              >
            </div>
          </div>

          <!-- Loading skeleton -->
          <div v-if="loading" class="sd-course-list">
            <div v-for="i in 5" :key="i" class="sd-course-row sd-course-skel">
              <span class="sd-shimmer" style="width:44px;height:33px;border-radius:6px;flex-shrink:0"></span>
              <div style="flex:1;display:flex;flex-direction:column;gap:5px">
                <span class="sd-shimmer" style="height:12px;width:70%;border-radius:4px"></span>
                <span class="sd-shimmer" style="height:10px;width:40%;border-radius:4px"></span>
              </div>
              <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px">
                <span class="sd-shimmer" style="height:10px;width:32px;border-radius:4px"></span>
                <span class="sd-shimmer" style="height:3px;width:64px;border-radius:2px"></span>
              </div>
            </div>
          </div>

          <!-- Course list rows -->
          <div v-else-if="pagedCourses.length" class="sd-course-list">
            <NuxtLink
              v-for="e in pagedCourses"
              :key="e.id"
              :to="`/student/courses/${e.course?.id || e.course_id || e.id}`"
              class="sd-course-row"
            >
              <div class="sd-c-thumb">
                <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" :alt="e.course?.title" loading="lazy">
                <i v-else class="material-symbols-outlined sd-c-ico">auto_stories</i>
              </div>
              <div class="sd-c-info">
                <p class="sd-c-name">{{ e.course?.title || e.title || 'Khóa học' }}</p>
                <p class="sd-c-inst">{{ e.course?.instructor?.name || e.instructor || '' }}</p>
              </div>
              <div class="sd-c-right">
                <span class="sd-c-pct">{{ Math.round(e.progress || 0) }}%</span>
                <div class="sd-c-bar">
                  <div class="sd-c-fill" :style="{ width: `${e.progress || 0}%` }"></div>
                </div>
                <span
                  class="sd-badge"
                  :class="{
                    active: (e.progress || 0) > 0 && (e.progress || 0) < 100,
                    done: (e.progress || 0) >= 100
                  }"
                >
                  {{ (e.progress || 0) >= 100 ? 'Hoàn thành' : (e.progress || 0) > 0 ? 'Đang học' : 'Chưa bắt đầu' }}
                </span>
              </div>
            </NuxtLink>
          </div>

          <div v-else class="sd-empty">
            <i class="material-symbols-outlined" style="font-size:32px;opacity:.4">auto_stories</i>
            <p>Chưa có khóa học nào.</p>
            <NuxtLink to="/student/recommendations" class="sd-btn-sm" style="margin-top:6px">Khám phá khóa học</NuxtLink>
          </div>

          <!-- Pagination -->
          <div v-if="totalCoursePages > 1" class="sd-pagination">
            <button :disabled="coursePage === 1" class="sd-pg-btn" @click="coursePage--">‹</button>
            <button
              v-for="p in totalCoursePages"
              :key="p"
              class="sd-pg-btn"
              :class="{ on: coursePage === p }"
              @click="coursePage = p"
            >{{ p }}</button>
            <button :disabled="coursePage === totalCoursePages" class="sd-pg-btn" @click="coursePage++">›</button>
          </div>
        </div>

        <!-- My Exams -->
        <div class="sd-card">
          <div class="sd-card-hd">
            <div>
              <p class="sd-kicker">Khảo thí</p>
              <h2 class="sd-card-title">Kỳ thi của tôi</h2>
            </div>
            <NuxtLink to="/student/exams" class="sd-link-more">Xem tất cả</NuxtLink>
          </div>

          <!-- Tabs -->
          <div class="sd-toolbar">
            <div class="sd-tabs" role="tablist">
              <button
                v-for="t in [{k:'upcoming',l:'Sắp diễn ra'},{k:'all',l:'Tất cả'},{k:'done',l:'Đã xong'}]"
                :key="t.k"
                role="tab"
                class="sd-tab"
                :class="{ on: examTab === t.k }"
                @click="examTab = t.k"
              >{{ t.l }}</button>
            </div>
          </div>

          <div v-if="loading" style="display:flex;flex-direction:column;gap:8px;padding:14px 16px 16px">
            <span v-for="i in 3" :key="i" class="sd-shimmer" style="height:44px;border-radius:8px;display:block"></span>
          </div>

          <div v-else-if="filteredExams.length" class="sd-exam-list">
            <div v-for="e in filteredExams.slice(0, 5)" :key="e.id" class="sd-exam-row">
              <span class="sd-exam-dot" :class="{ urgent: examIsUrgent(e) }"></span>
              <div class="sd-exam-info">
                <p class="sd-exam-name">{{ e.title || e.name }}</p>
                <p class="sd-exam-sub">{{ e.course?.title || '' }}</p>
              </div>
              <span class="sd-exam-cd" :class="{ urgent: examIsUrgent(e) }">
                {{ examCountdown(e) }}
              </span>
              <NuxtLink to="/student/exams" class="sd-btn-xs">Chi tiết</NuxtLink>
            </div>
          </div>

          <div v-else class="sd-empty">
            <i class="material-symbols-outlined" style="font-size:28px;opacity:.4">assignment</i>
            <p>Không có kỳ thi nào.</p>
          </div>
        </div>

      </div><!-- /main-col -->

      <!-- ── Right panel ── -->
      <aside class="sd-right-panel">

        <!-- Student Profile Card (Merged Info & Achievements) -->
        <div class="sd-card sd-widget sd-student-profile-card">
          <!-- Student Info Header -->
          <div class="sd-student-header">
            <div class="sd-student-avatar">
              <img v-if="dashboardData?.student?.avatar" :src="dashboardData.student.avatar" alt="Avatar">
              <span v-else>{{ dashboardData?.student?.name ? dashboardData.student.name.slice(0, 2).toUpperCase() : 'SV' }}</span>
            </div>
            <div class="sd-student-meta">
              <h3 class="sd-student-name">{{ dashboardData?.student?.name || 'Học viên' }}</h3>
              <p class="sd-student-code">MSSV: {{ dashboardData?.student?.student_code || '—' }}</p>
            </div>
          </div>

          <!-- Academic Profile Details -->
          <div class="sd-student-details">
            <div class="sd-detail-item">
              <span class="sd-detail-label">Lớp hành chính:</span>
              <span class="sd-detail-val">{{ dashboardData?.student?.administrative_class?.name || dashboardData?.student?.administrative_class?.code || 'Chưa xếp lớp' }}</span>
            </div>
            <div class="sd-detail-item">
              <span class="sd-detail-label">Khoá (Cohort):</span>
              <span class="sd-detail-val">{{ dashboardData?.student?.cohort?.name || dashboardData?.student?.cohort?.code || '—' }}</span>
            </div>
            <div class="sd-detail-item">
              <span class="sd-detail-label">Ngành học:</span>
              <span class="sd-detail-val">{{ dashboardData?.student?.major?.name || '—' }}</span>
            </div>
            <div class="sd-detail-item">
              <span class="sd-detail-label">Hệ đào tạo:</span>
              <span class="sd-detail-val">{{ dashboardData?.student?.program?.name || '—' }}</span>
            </div>
          </div>

          <!-- Integrated Achievements Strip -->
          <div class="sd-profile-ach-strip">
            <div class="sd-ach-item">
              <span class="sd-ach-val sd-ach-amber">{{ gpa }}</span>
              <span class="sd-ach-lbl">GPA</span>
            </div>
            <div class="sd-ach-sep"></div>
            <div class="sd-ach-item">
              <span class="sd-ach-val sd-ach-violet">{{ certCount }}</span>
              <span class="sd-ach-lbl">Chứng chỉ</span>
            </div>
            <div class="sd-ach-sep"></div>
            <div class="sd-ach-item">
              <span class="sd-ach-val sd-ach-green">{{ enrollments.filter(e => (e.progress || 0) >= 100).length }}</span>
              <span class="sd-ach-lbl">Hoàn thành</span>
            </div>
          </div>
        </div>

        <!-- Administrative Class -->
        <div class="sd-card sd-widget sd-class-card" v-if="dashboardData?.student?.administrative_class">
          <div class="sd-widget-hd">
            <div>
              <p class="sd-kicker">Lớp hành chính</p>
              <h3 class="sd-widget-title">{{ dashboardData.student.administrative_class.name || dashboardData.student.administrative_class.code }}</h3>
            </div>
          </div>
          <div class="sd-class-advisor" v-if="dashboardData.student.administrative_class.advisor">
            <span class="material-symbols-outlined sd-advisor-icon">co_present</span>
            <div class="sd-advisor-info">
              <p class="sd-advisor-name">GVCN: {{ dashboardData.student.administrative_class.advisor.name }}</p>
              <p class="sd-advisor-email">{{ dashboardData.student.administrative_class.advisor.email }}</p>
            </div>
          </div>
          <div class="sd-class-members-list">
            <p class="sd-members-title">Danh sách lớp ({{ dashboardData.student.administrative_class.students?.length || 0 }} thành viên)</p>
            <div class="sd-members-scroll">
              <div v-for="member in dashboardData.student.administrative_class.students" :key="member.id" class="sd-member-row" :class="{ 'is-me': member.id === dashboardData.student.id }">
                <div class="sd-member-avatar">
                  <img v-if="member.avatar" :src="member.avatar" alt="Avatar">
                  <span v-else>{{ member.name.slice(0, 2).toUpperCase() }}</span>
                </div>
                <div class="sd-member-info">
                  <p class="sd-member-name">{{ member.name }} <span v-if="member.id === dashboardData.student.id" class="sd-me-tag">(Bạn)</span></p>
                  <p class="sd-member-code">{{ member.student_code }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calendar -->
        <div class="sd-card sd-widget">
          <div class="sd-widget-hd">
            <h3 class="sd-widget-title">Lịch</h3>
            <div class="sd-cal-nav">
              <button class="sd-cal-btn" aria-label="Tháng trước" @click="prevMonth">
                <i class="material-symbols-outlined" style="font-size:14px">chevron_left</i>
              </button>
              <span class="sd-cal-label">{{ calLabel }}</span>
              <button class="sd-cal-btn" aria-label="Tháng sau" @click="nextMonth">
                <i class="material-symbols-outlined" style="font-size:14px">chevron_right</i>
              </button>
            </div>
          </div>
          <div class="sd-calendar">
            <div v-for="d in DAYS_VI" :key="d" class="sd-cal-dow">{{ d }}</div>
            <div
              v-for="(cell, idx) in calendarDays"
              :key="idx"
              class="sd-cal-cell"
              :class="{ today: cell.isToday, evt: cell.hasEvent, empty: !cell.inMonth }"
            >
              <span v-if="cell.inMonth">{{ cell.day }}</span>
              <span v-if="cell.hasEvent && cell.inMonth" class="sd-cal-dot" aria-hidden="true"></span>
            </div>
          </div>

          <!-- Agenda -->
          <div v-if="agendaItems.length" class="sd-agenda">
            <p class="sd-agenda-lbl">Sắp tới</p>
            <div v-for="item in agendaItems" :key="item.id" class="sd-agenda-row">
              <span class="sd-agenda-dot"></span>
              <div class="sd-agenda-info">
                <p class="sd-agenda-name">{{ item.title || item.name }}</p>
                <p class="sd-agenda-date">{{ new Date(item.start_time || item.date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }) }}</p>
              </div>
            </div>
          </div>
          <p v-else class="sd-no-data">Không có sự kiện sắp tới.</p>
          <NuxtLink to="/student/calendar" class="sd-widget-footer">Xem lịch đầy đủ →</NuxtLink>
        </div>

        <!-- Tasks Card -->
        <div class="sd-card sd-widget sd-tasks-card">
          <div class="sd-widget-hd">
            <div>
              <p class="sd-kicker">Công việc</p>
              <h3 class="sd-widget-title">Nhiệm vụ cần làm</h3>
            </div>
            <span class="material-symbols-outlined sd-tasks-icon">playlist_add_check</span>
          </div>
          <div class="sd-tasks-list" v-if="allPendingTasks.length">
            <div v-for="task in allPendingTasks.slice(0, 5)" :key="task.id" class="sd-task-row">
              <span class="material-symbols-outlined sd-task-checkbox">radio_button_unchecked</span>
              <div class="sd-task-body">
                <p class="sd-task-title">{{ task.title }}</p>
                <p class="sd-task-desc">
                  <span>{{ task.course_title }}</span>
                  <span v-if="task.deadline" class="sd-task-due" :class="{ urgent: isTaskUrgent(task.deadline) }">
                    · Hạn: {{ new Date(task.deadline).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }) }}
                  </span>
                </p>
              </div>
            </div>
          </div>
          <p v-else class="sd-no-data">Không có nhiệm vụ chưa hoàn thành.</p>
          <NuxtLink to="/student/tasks" class="sd-widget-footer">Xem tất cả nhiệm vụ →</NuxtLink>
        </div>

        <!-- Leaderboard (BXH) -->
        <div class="sd-card sd-widget sd-leaderboard-card">
          <div class="sd-widget-hd">
            <div>
              <p class="sd-kicker">Bảng xếp hạng</p>
              <h3 class="sd-widget-title">Thành tích lớp</h3>
            </div>
            <span class="material-symbols-outlined sd-cup-icon">emoji_events</span>
          </div>
          <div class="sd-leaderboard-list">
            <div v-for="(item, index) in [
              { name: 'Nguyễn Hoàng Đức', gpa: '3.92', me: false },
              { name: 'Trần Thị Mai', gpa: '3.85', me: false },
              { name: 'Lê Huy Quốc', gpa: '3.78', me: false },
              { name: 'Phạm Minh Chính', gpa: '3.65', me: false },
              { name: 'Vương Đình Huệ', gpa: '3.58', me: false }
            ]" :key="index" class="sd-lb-row" :class="{ 'rank-1': index === 0, 'rank-2': index === 1, 'rank-3': index === 2 }">
              <div class="sd-lb-rank">
                <span v-if="index === 0" class="material-symbols-outlined rank-icon gold">workspace_premium</span>
                <span v-else-if="index === 1" class="material-symbols-outlined rank-icon silver">workspace_premium</span>
                <span v-else-if="index === 2" class="material-symbols-outlined rank-icon bronze">workspace_premium</span>
                <span v-else class="rank-num">{{ index + 1 }}</span>
              </div>
              <span class="sd-lb-name">{{ item.name }}</span>
              <span class="sd-lb-gpa">{{ item.gpa }} GPA</span>
            </div>
          </div>
        </div>



        <!-- Notifications -->
        <div class="sd-card sd-widget">
          <div class="sd-widget-hd">
            <h3 class="sd-widget-title">Thông báo</h3>
            <NuxtLink to="/student/notifications" class="sd-link-more">Tất cả</NuxtLink>
          </div>
          <div v-if="loading" style="display:flex;flex-direction:column;gap:6px;padding:4px 0 8px">
            <span v-for="i in 3" :key="i" class="sd-shimmer" style="height:36px;border-radius:8px;display:block"></span>
          </div>
          <div v-else-if="recentNotifs.length" class="sd-notif-list">
            <div v-for="n in recentNotifs" :key="n.id" class="sd-notif-item">
              <span class="sd-notif-dot" :class="{ unread: !n.read_at }"></span>
              <div class="sd-notif-body">
                <p class="sd-notif-title">{{ n.title || n.data?.title || 'Thông báo' }}</p>
                <p class="sd-notif-time">{{ n.created_at ? new Date(n.created_at).toLocaleDateString('vi-VN') : '' }}</p>
              </div>
            </div>
          </div>
          <p v-else class="sd-no-data">Không có thông báo mới.</p>
        </div>

      </aside>
    </div>
  </div>
</template>

<style scoped>
/* ── Page ── */
.sd-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Hero Slider ── */
.sd-slider {
  position: relative;
  border-radius: 14px;
  overflow: hidden;
  height: 140px;
  background: #0a0a0a;
}
.sd-slides-wrap {
  position: relative;
  width: 100%;
  height: 100%;
}
.sd-slide {
  position: absolute;
  inset: 0;
}
.sd-slide-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 30%;
  display: block;
}
.sd-slide-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(0,0,0,.65) 0%, rgba(0,0,0,.3) 60%, rgba(0,0,0,.1) 100%);
}
.sd-slide-content {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 16px 28px;
  z-index: 1;
  margin-left: 100px;
}
.sd-slide-greeting {
  font-size: 0.72rem;
  font-weight: 500;
  color: rgba(255,255,255,.7);
  margin: 0 0 2px;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.sd-slide-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #fff;
  margin: 0 0 4px;
  line-height: 1.25;
  max-width: 440px;
}
.sd-slide-desc {
  font-size: 0.78rem;
  color: rgba(255,255,255,.75);
  margin: 0 0 10px;
  max-width: 380px;
  line-height: 1.45;
}
.sd-slide-btn {
  display: inline-flex;
  align-items: center;
  padding: 5px 12px;
  border-radius: 6px;
  background: #fff;
  color: #065f46;
  font-size: 0.74rem;
  font-weight: 700;
  text-decoration: none;
  align-self: flex-start;
  transition: transform .12s, box-shadow .12s;
}
.sd-slide-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.2);
}

/* Arrows */
.sd-sl-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,.25);
  background: rgba(0,0,0,.35);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
  backdrop-filter: blur(4px);
}
.sd-sl-arrow:hover { background: rgba(0,0,0,.55); }
.sd-sl-arrow i { font-size: 18px; }
.sd-sl-prev { left: 12px; }
.sd-sl-next { right: 12px; }

/* Dots */
.sd-sl-dots {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 2;
  display: flex;
  gap: 6px;
}
.sd-sl-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  border: none;
  background: rgba(255,255,255,.4);
  cursor: pointer;
  transition: background .2s, width .2s;
  padding: 0;
}
.sd-sl-dot.active {
  background: #fff;
  width: 20px;
  border-radius: 3px;
}

/* Slide transition */
.sd-slide-enter-active,
.sd-slide-leave-active {
  transition: opacity .5s ease;
}
.sd-slide-enter-from,
.sd-slide-leave-to {
  opacity: 0;
}

/* ── KPI strip ── */
.sd-kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}
.sd-kpi-card {
  background: var(--surface, #fff);
  border: 0.5px solid var(--line, rgba(0,0,0,.08));
  border-radius: 12px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  position: relative;
  overflow: hidden;
}
.sd-kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  border-radius: 12px 12px 0 0;
}
.tone-green::before  { background: var(--green-mid, #1D9E75); }
.tone-blue::before   { background: #378add; }
.tone-amber::before  { background: var(--accent, #f59e0b); }
.tone-violet::before { background: var(--secondary, #6366F1); }

.sd-kpi-val {
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1;
  color: var(--text);
  display: block;
  margin-bottom: 1px;
  letter-spacing: -0.03em;
}
.tone-green .sd-kpi-val  { color: var(--green-mid, #1D9E75); }
.tone-blue .sd-kpi-val   { color: #378add; }
.tone-amber .sd-kpi-val  { color: var(--accent, #d97706); }
.tone-violet .sd-kpi-val { color: var(--secondary, #6366F1); }

.sd-kpi-lbl {
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 500;
}
.sd-kpi-link {
  display: block;
  margin-top: 6px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--green, #0F6E8C);
  text-decoration: none;
  opacity: .8;
}
.sd-kpi-link:hover { opacity: 1; text-decoration: underline; }

/* ── Main grid ── */
.sd-grid {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 20px;
  align-items: start;
}
.sd-main-col { display: flex; flex-direction: column; gap: 20px; }
.sd-right-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 80px;
}

/* ── Cards ── */
.sd-card {
  background: var(--surface, #fff);
  border: 1px solid rgba(0, 0, 0, 0.065);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.025), 0 2px 6px rgba(0, 0, 0, 0.015);
  transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}
.sd-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05), 0 4px 12px rgba(0, 0, 0, 0.03);
}
[data-theme="dark"] .sd-card {
  border-color: rgba(255, 255, 255, 0.05);
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.2), 0 2px 6px rgba(0, 0, 0, 0.15);
}
[data-theme="dark"] .sd-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35), 0 4px 12px rgba(0, 0, 0, 0.25);
}
.sd-card-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px 0;
}
.sd-kicker {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--muted);
  margin: 0 0 2px;
}
.sd-card-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
}
.sd-link-more {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--green, #059669);
  text-decoration: none;
  white-space: nowrap;
}
.sd-link-more:hover { text-decoration: underline; }

/* ── Toolbar ── */
.sd-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 16px 10px;
  border-bottom: 0.5px solid var(--line, rgba(0,0,0,.08));
  flex-wrap: wrap;
}
.sd-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.sd-tab {
  padding: 4px 10px;
  border-radius: 6px;
  border: 0.5px solid var(--line, rgba(0,0,0,.08));
  background: transparent;
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .12s, color .12s, border-color .12s;
}
.sd-tab:hover { background: var(--bg, #f4f3f0); color: var(--text); }
.sd-tab.on {
  background: #ecfdf5;
  color: #065f46;
  border-color: transparent;
}
.sd-search-wrap { position: relative; }
.sd-search-ico {
  position: absolute;
  left: 8px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 14px;
}
.sd-search {
  padding: 5px 10px 5px 28px;
  border: 0.5px solid var(--line, rgba(0,0,0,.12));
  border-radius: 7px;
  background: var(--bg, #f8f8f6);
  color: var(--text);
  font-size: 0.8rem;
  outline: none;
  width: 190px;
  transition: border-color .15s;
}
.sd-search:focus { border-color: #059669; }

/* ── Course list ── */
.sd-course-list { display: flex; flex-direction: column; }
.sd-course-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 0.5px solid var(--line, rgba(0,0,0,.06));
  text-decoration: none;
  color: inherit;
  transition: background .1s;
}
.sd-course-row:last-child { border-bottom: none; }
.sd-course-row:hover { background: var(--bg, #f8f8f6); }
.sd-course-skel { pointer-events: none; }
.sd-c-thumb {
  width: 44px;
  height: 33px;
  border-radius: 6px;
  background: #ecfdf5;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.sd-c-thumb img { width: 100%; height: 100%; object-fit: cover; }
.sd-c-ico { font-size: 16px; color: #059669; }
.sd-c-info { flex: 1; min-width: 0; }
.sd-c-name {
  font-size: 0.83rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-c-inst { font-size: 0.72rem; color: var(--muted); margin-top: 1px; }
.sd-c-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  flex-shrink: 0;
}
.sd-c-pct { font-size: 0.72rem; font-weight: 600; color: var(--muted); }
.sd-c-bar {
  width: 64px;
  height: 3px;
  background: var(--line, rgba(0,0,0,.1));
  border-radius: 2px;
  overflow: hidden;
}
.sd-c-fill { height: 100%; background: #059669; border-radius: 2px; transition: width .4s; }
.sd-badge {
  font-size: 0.67rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 20px;
  background: var(--bg, #f4f3f0);
  color: var(--muted);
  border: 0.5px solid var(--line, rgba(0,0,0,.08));
}
.sd-badge.active { background: #ecfdf5; color: #065f46; border-color: transparent; }
.sd-badge.done   { background: #eff6ff; color: #1d4ed8; border-color: transparent; }

/* ── Empty ── */
.sd-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 36px 16px;
  gap: 6px;
  color: var(--muted);
  text-align: center;
}
.sd-empty p { font-size: 0.85rem; }
.sd-btn-sm {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  border-radius: 8px;
  background: #059669;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
  text-decoration: none;
  transition: opacity .15s;
}
.sd-btn-sm:hover { opacity: .85; }

/* ── Pagination ── */
.sd-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
  padding: 12px 16px;
  border-top: 0.5px solid var(--line, rgba(0,0,0,.06));
}
.sd-pg-btn {
  min-width: 30px;
  height: 30px;
  padding: 0 5px;
  border: 0.5px solid var(--line, rgba(0,0,0,.1));
  border-radius: 6px;
  background: transparent;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .12s, color .12s;
}
.sd-pg-btn:hover:not(:disabled) { background: var(--bg, #f4f3f0); color: var(--text); }
.sd-pg-btn.on { background: #059669; color: #fff; border-color: #059669; }
.sd-pg-btn:disabled { opacity: .35; cursor: not-allowed; }

/* ── Exam list ── */
.sd-exam-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px 16px;
}
.sd-exam-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border: 0.5px solid var(--line, rgba(0,0,0,.08));
  border-radius: 8px;
  background: var(--surface-strong, #f8f8f6);
  transition: background .1s;
}
.sd-exam-row:hover { background: var(--bg, #f4f3f0); }
.sd-exam-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #d97706;
  flex-shrink: 0;
}
.sd-exam-dot.urgent { background: #ef4444; }
.sd-exam-info { flex: 1; min-width: 0; }
.sd-exam-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-exam-sub { font-size: 0.72rem; color: var(--muted); }
.sd-exam-cd {
  font-size: 0.74rem;
  font-weight: 600;
  color: #059669;
  white-space: nowrap;
}
.sd-exam-cd.urgent { color: #ef4444; }
.sd-btn-xs {
  padding: 3px 9px;
  border-radius: 6px;
  border: 0.5px solid var(--line, rgba(0,0,0,.12));
  background: transparent;
  color: var(--muted);
  font-size: 0.74rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: background .12s, color .12s;
}
.sd-btn-xs:hover { background: #ecfdf5; color: #065f46; border-color: transparent; }

/* ── Right panel widgets ── */
.sd-widget { padding: 14px; }
.sd-widget-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.sd-widget-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
}

/* Calendar */
.sd-cal-nav { display: flex; align-items: center; gap: 5px; }
.sd-cal-label { font-size: 0.75rem; font-weight: 600; color: var(--text); white-space: nowrap; }
.sd-cal-btn {
  width: 22px;
  height: 22px;
  border-radius: 5px;
  border: 0.5px solid var(--line, rgba(0,0,0,.12));
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .12s;
}
.sd-cal-btn:hover { background: var(--bg, #f4f3f0); }
.sd-calendar {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  margin-bottom: 2px;
}
.sd-cal-dow {
  text-align: center;
  font-size: 0.6rem;
  font-weight: 700;
  color: var(--muted);
  padding: 3px 0;
  text-transform: uppercase;
}
.sd-cal-cell {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border-radius: 5px;
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--text);
}
.sd-cal-cell.empty { color: transparent; }
.sd-cal-cell.today { background: #059669; color: #fff; font-weight: 700; }
.sd-cal-cell.evt:not(.today) { background: #ecfdf5; color: #065f46; }
.sd-cal-dot {
  position: absolute;
  bottom: 2px;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #059669;
}
.sd-cal-cell.today .sd-cal-dot { background: rgba(255,255,255,.8); }

.sd-agenda { border-top: 0.5px solid var(--line, rgba(0,0,0,.06)); padding-top: 10px; margin-top: 8px; }
.sd-agenda-lbl {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--muted);
  margin: 0 0 6px;
}
.sd-agenda-row {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  padding: 5px 0;
  border-bottom: 0.5px solid var(--line, rgba(0,0,0,.06));
}
.sd-agenda-row:last-child { border-bottom: none; }
.sd-agenda-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #d97706;
  margin-top: 4px;
  flex-shrink: 0;
}
.sd-agenda-info { flex: 1; min-width: 0; }
.sd-agenda-name {
  font-size: 0.77rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-agenda-date { font-size: 0.68rem; color: var(--muted); }
.sd-no-data { font-size: 0.78rem; color: var(--muted); text-align: center; padding: 6px 0; margin: 0; }
.sd-widget-footer {
  display: block;
  text-align: center;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 0.5px solid var(--line, rgba(0,0,0,.06));
  font-size: 0.75rem;
  font-weight: 600;
  color: #059669;
  text-decoration: none;
}
.sd-widget-footer:hover { text-decoration: underline; }

/* Achievements strip */
.sd-ach-strip {
  display: flex;
  align-items: stretch;
  background: var(--bg, #f8f8f6);
  border-radius: 8px;
  border: 0.5px solid var(--line, rgba(0,0,0,.08));
  overflow: hidden;
}
.sd-ach-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 6px;
  gap: 2px;
}
.sd-ach-val { font-size: 1.3rem; font-weight: 700; line-height: 1; }
.sd-ach-amber { color: #d97706; }
.sd-ach-violet { color: #7c3aed; }
.sd-ach-green  { color: #059669; }
.sd-ach-lbl { font-size: 0.65rem; color: var(--muted); font-weight: 600; text-align: center; text-transform: uppercase; letter-spacing: .04em; }
.sd-ach-sep { width: 0.5px; background: var(--line, rgba(0,0,0,.08)); align-self: stretch; }

/* Notifications */
.sd-notif-list { display: flex; flex-direction: column; gap: 4px; padding: 4px 0 6px; }
.sd-notif-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 7px 10px;
  border-radius: 7px;
  transition: background .1s;
}
.sd-notif-item:hover { background: var(--bg, #f4f3f0); }
.sd-notif-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--line, rgba(0,0,0,.15));
  margin-top: 4px;
  flex-shrink: 0;
}
.sd-notif-dot.unread { background: #059669; }
.sd-notif-body { flex: 1; min-width: 0; }
.sd-notif-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-notif-time { font-size: 0.68rem; color: var(--muted); }

/* ── Shimmer skeleton ── */
.sd-shimmer {
  background: linear-gradient(90deg, var(--line, rgba(0,0,0,.08)) 25%, var(--bg, #f4f3f0) 50%, var(--line, rgba(0,0,0,.08)) 75%);
  background-size: 200% 100%;
  animation: sd-shimmer 1.5s infinite;
  border-radius: 4px;
  display: inline-block;
}
@keyframes sd-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Dark mode ── */
[data-theme="dark"] .sd-tab.on             { background: rgba(52,211,153,.15); color: #6ee7b7; }
[data-theme="dark"] .sd-search             { background: var(--surface); border-color: var(--line); }
[data-theme="dark"] .sd-badge.active       { background: rgba(52,211,153,.15); color: #6ee7b7; }
[data-theme="dark"] .sd-badge.done         { background: rgba(96,165,250,.15); color: #93c5fd; }
[data-theme="dark"] .sd-kpi-card           { background: var(--surface); }
[data-theme="dark"] .tone-green .sd-kpi-val { color: #34d399; }
[data-theme="dark"] .tone-blue .sd-kpi-val  { color: #60a5fa; }
[data-theme="dark"] .tone-amber .sd-kpi-val { color: #fbbf24; }
[data-theme="dark"] .tone-violet .sd-kpi-val{ color: #a78bfa; }
[data-theme="dark"] .sd-exam-row          { background: var(--surface); }
[data-theme="dark"] .sd-cal-cell.evt:not(.today) { background: rgba(52,211,153,.12); color: #6ee7b7; }
[data-theme="dark"] .sd-ach-val.sd-ach-amber  { color: #fbbf24; }
[data-theme="dark"] .sd-ach-val.sd-ach-violet { color: #a78bfa; }
[data-theme="dark"] .sd-ach-val.sd-ach-green  { color: #34d399; }
[data-theme="dark"] .sd-exam-cd           { color: #34d399; }
[data-theme="dark"] .sd-kpi-link          { color: #34d399; }

/* ── KPI Strip adjustments ── */
.sd-kpi-card {
  padding: 8px 12px !important;
  gap: 1px !important;
}
.sd-kpi-val {
  font-size: 1.15rem !important;
}
.sd-kpi-lbl {
  font-size: 0.7rem !important;
}
.sd-kpi-link {
  margin-top: 4px !important;
  font-size: 0.68rem !important;
}

/* ── Dynamic Colors Sync ── */
.tone-green::before  { background: var(--green) !important; }
.tone-green .sd-kpi-val  { color: var(--green) !important; }

.tone-blue::before   { background: var(--green-mid) !important; }
.tone-blue .sd-kpi-val   { color: var(--green-mid) !important; }

.tone-amber::before  { background: var(--accent) !important; }
.tone-amber .sd-kpi-val  { color: var(--accent) !important; }

.tone-violet::before { background: var(--green-deep) !important; }
.tone-violet .sd-kpi-val { color: var(--green-deep) !important; }

/* ── Student Info Card ── */
.sd-student-info-card, .sd-student-profile-card {
  padding: 16px !important;
}
.sd-student-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}
.sd-student-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--green-soft);
  color: var(--green-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1.1rem;
  overflow: hidden;
  border: 1px solid rgba(var(--green-rgb), 0.15);
}
.sd-student-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sd-student-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.sd-student-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-student-code {
  font-size: 0.74rem;
  color: var(--muted);
  font-weight: 600;
  margin: 0;
}
.sd-student-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--line, rgba(0,0,0,.06));
}
.sd-detail-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.78rem;
  line-height: 1.4;
}
.sd-detail-label {
  color: var(--muted);
  font-weight: 500;
}
.sd-detail-val {
  color: var(--text);
  font-weight: 700;
  text-align: right;
  max-width: 60%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sd-profile-ach-strip {
  display: flex;
  align-items: stretch;
  background: rgba(0, 0, 0, 0.015);
  border-top: 1px dashed var(--line, rgba(0,0,0,.08));
  margin-top: 14px;
  padding-top: 8px;
  border-radius: 0 0 12px 12px;
}

/* ── Class Card ── */
.sd-class-card {
  padding: 16px !important;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.sd-class-advisor {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(var(--green-rgb), 0.04);
  padding: 10px 12px;
  border-radius: 10px;
  border: 0.5px solid rgba(var(--green-rgb), 0.1);
}
.sd-advisor-icon {
  color: var(--green);
  font-size: 20px;
}
.sd-advisor-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.sd-advisor-name {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
}
.sd-advisor-email {
  font-size: 0.7rem;
  color: var(--muted);
  margin: 1px 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-class-members-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sd-members-title {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0;
}
.sd-members-scroll {
  max-height: 240px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-right: 4px;
}
.sd-members-scroll::-webkit-scrollbar {
  width: 4px;
}
.sd-members-scroll::-webkit-scrollbar-thumb {
  background: rgba(0,0,0,0.08);
  border-radius: 4px;
}
.sd-member-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 8px;
  border-radius: 8px;
  background: rgba(0,0,0,0.01);
  border: 0.5px solid transparent;
}
.sd-member-row:hover {
  background: rgba(0,0,0,0.03);
}
.sd-member-row.is-me {
  background: rgba(var(--green-rgb), 0.06);
  border-color: rgba(var(--green-rgb), 0.15);
}
.sd-member-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--green-soft);
  color: var(--green-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.68rem;
  overflow: hidden;
}
.sd-member-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sd-member-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.sd-member-name {
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--text);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-me-tag {
  font-size: 0.62rem;
  font-weight: 700;
  color: var(--green);
  background: rgba(var(--green-rgb), 0.12);
  padding: 1px 4px;
  border-radius: 4px;
  margin-left: 2px;
}
.sd-member-code {
  font-size: 0.65rem;
  color: var(--muted);
  margin: 1px 0 0;
}
/* ── Tasks Card ── */
.sd-tasks-card {
  padding: 16px !important;
}
.sd-tasks-icon {
  color: var(--green);
  font-size: 24px;
}
.sd-tasks-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}
.sd-task-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.015);
  border: 0.5px solid transparent;
}
.sd-task-row:hover {
  background: rgba(0, 0, 0, 0.03);
}
.sd-task-checkbox {
  color: var(--muted);
  font-size: 18px;
  margin-top: 2px;
  cursor: pointer;
}
.sd-task-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}
.sd-task-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-task-desc {
  font-size: 0.68rem;
  color: var(--muted);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}
.sd-task-due {
  font-weight: 700;
  color: var(--muted);
}
.sd-task-due.urgent {
  color: #b91c1c;
  background: rgba(220, 38, 38, 0.08);
  padding: 0px 4px;
  border-radius: 4px;
}

/* ── Leaderboard Card ── */
.sd-leaderboard-card {
  padding: 16px !important;
}
.sd-cup-icon {
  color: var(--accent);
  font-size: 24px;
}
.sd-leaderboard-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}
.sd-lb-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border-radius: 8px;
  background: rgba(0,0,0,0.01);
  font-size: 0.78rem;
}
.sd-lb-row:hover {
  background: rgba(0,0,0,0.03);
}
.sd-lb-rank {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.rank-icon {
  font-size: 20px;
  font-weight: bold;
}
.rank-icon.gold { color: #f59e0b; }
.rank-icon.silver { color: #94a3b8; }
.rank-icon.bronze { color: #b45309; }
.rank-num {
  font-weight: 700;
  color: var(--muted);
}
.sd-lb-name {
  font-weight: 600;
  color: var(--text);
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sd-lb-gpa {
  font-weight: 700;
  color: var(--green);
}
.rank-1 { background: rgba(245, 158, 11, 0.05); }
.rank-2 { background: rgba(148, 163, 184, 0.05); }
.rank-3 { background: rgba(180, 83, 9, 0.05); }

/* ── Responsive ── */
@media (max-width: 1100px) {
  .sd-grid { grid-template-columns: 1fr; }
  .sd-right-panel {
    position: static;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  }
}
@media (max-width: 640px) {
  .sd-slider { height: 120px; }
  .sd-slide-content { padding: 12px 18px; }
  .sd-slide-title { font-size: 1rem; }
  .sd-slide-desc { display: none; }
  .sd-slide-btn { padding: 4px 10px; font-size: 0.7rem; }
  .sd-right-panel { grid-template-columns: 1fr; }
  .sd-toolbar { flex-direction: column; align-items: flex-start; }
  .sd-search { width: 100%; }
}
</style>
