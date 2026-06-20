<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { BookOpen, Clock, CircleCheckBig } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import CourseProgressCard from '~/components/student/CourseProgressCard.vue'
import DashboardSchedule from '~/components/dashboard/DashboardSchedule.vue'

definePageMeta({ layout: 'default' })

const auth = useAuthStore()
const router = useRouter()
const loading = ref(true)
const enrollments = ref<any[]>([])
const filter = ref('all') // 'all', 'in_progress', 'completed'

onMounted(async () => {
  if (!auth.isLoggedIn) {
    router.push('/login?redirect=/student')
    return
  }

  const headers = { Authorization: `Bearer ${auth.token}` }
  try {
    const [enrollRes, examRes] = await Promise.allSettled([
      useApi<any[]>('/user/enrollments', { headers }),
      useApi<any>('/exams/standalone?per_page=20&status=published', { headers }),
    ])
    if (enrollRes.status === 'fulfilled') enrollments.value = enrollRes.value || []
    if (examRes.status === 'fulfilled') {
      const d = examRes.value
      upcomingExams.value = Array.isArray(d) ? d : (d?.data || [])
    }
  }
  catch (e) { console.error('Failed to fetch student data', e) }
  finally { loading.value = false }
})

const filteredCourses = computed(() => {
  if (filter.value === 'all') return enrollments.value
  if (filter.value === 'completed') return enrollments.value.filter(e => e.progress >= 100)
  return enrollments.value.filter(e => e.progress < 100)
})

const totalCourses = computed(() => enrollments.value.length)
const completedCourses = computed(() => enrollments.value.filter(e => e.progress >= 100).length)
const inProgressCourses = computed(() => totalCourses.value - completedCourses.value)

const upcomingExams = ref<any[]>([])

const scheduleEvents = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)

  return upcomingExams.value
    .filter((exam) => {
      const start = exam.scheduled_start ? new Date(exam.scheduled_start) : null
      return start && start >= today
    })
    .slice(0, 5)
    .map((exam): { id: number; title: string; time: string; date: string; type: 'exam'; course: string; location: string } => {
      const start = new Date(exam.scheduled_start)
      const examDate = new Date(start)
      examDate.setHours(0, 0, 0, 0)
      const isToday = examDate.getTime() === today.getTime()
      const isTomorrow = examDate.getTime() === tomorrow.getTime()
      return {
        id: exam.id,
        title: exam.title,
        time: start.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        date: isToday ? 'Hôm nay' : isTomorrow ? 'Ngày mai' : start.toLocaleDateString('vi-VN', { day: 'numeric', month: 'short' }),
        type: 'exam',
        course: exam.course?.title || '',
        location: 'Trực tuyến',
      }
    })
})
</script>

<template>
  <div class="student-portal">

    <!-- ═══════════════════════════════════ HERO ═══════════════════════════════════ -->
    <section class="sp-hero">
      <!-- CSS grid texture overlay -->
      <div class="sp-hero-grid" aria-hidden="true" />

      <!-- Radial glow blobs -->
      <div class="sp-glow sp-glow--1" aria-hidden="true" />
      <div class="sp-glow sp-glow--2" aria-hidden="true" />
      <div class="sp-glow sp-glow--3" aria-hidden="true" />

      <div class="sp-hero-inner">
        <!-- Badge pill -->
        <div class="sp-badge">
          <span class="sp-badge-dot" />
          Cổng học viên
        </div>

        <!-- Heading -->
        <h1 class="sp-hero-title">
          Chào mừng,&nbsp;<em class="hero-em">{{ auth.user?.name || 'Học viên' }}</em>!
        </h1>

        <!-- Subtitle -->
        <p class="sp-hero-subtitle">
          Tiếp tục hành trình học tập của bạn — mỗi ngày một chút tiến bộ sẽ tạo nên sự khác biệt.
        </p>

        <!-- Glassmorphism stat cards -->
        <div class="sp-stats-row">
          <div class="sp-stat-card">
            <div class="sp-stat-icon-wrap">
              <BookOpen :size="22" :stroke-width="1.8" />
            </div>
            <div class="sp-stat-body">
              <span class="sp-stat-value">{{ totalCourses }}</span>
              <span class="sp-stat-label">Khóa học đăng ký</span>
            </div>
          </div>

          <div class="sp-stat-card">
            <div class="sp-stat-icon-wrap">
              <Clock :size="22" :stroke-width="1.8" />
            </div>
            <div class="sp-stat-body">
              <span class="sp-stat-value">{{ inProgressCourses }}</span>
              <span class="sp-stat-label">Đang học</span>
            </div>
          </div>

          <div class="sp-stat-card">
            <div class="sp-stat-icon-wrap">
              <CircleCheckBig :size="22" :stroke-width="1.8" />
            </div>
            <div class="sp-stat-body">
              <span class="sp-stat-value">{{ completedCourses }}</span>
              <span class="sp-stat-label">Đã hoàn thành</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════════ MAIN ═══════════════════════════════════ -->
    <main class="sp-main">
      <div class="sp-container">

        <!-- Schedule widget -->
        <DashboardSchedule :events="scheduleEvents" title="Kỳ thi sắp tới" class="sp-schedule" />

        <!-- Section header -->
        <div class="sp-section-header">
          <h2 class="sp-section-title">Khóa học của tôi</h2>

          <div class="sp-filter-group" role="group" aria-label="Bộ lọc khóa học">
            <button
              class="sp-filter-pill"
              :class="{ 'is-active': filter === 'all' }"
              @click="filter = 'all'"
            >
              Tất cả
            </button>
            <button
              class="sp-filter-pill"
              :class="{ 'is-active': filter === 'in_progress' }"
              @click="filter = 'in_progress'"
            >
              Đang học
            </button>
            <button
              class="sp-filter-pill"
              :class="{ 'is-active': filter === 'completed' }"
              @click="filter = 'completed'"
            >
              Hoàn thành
            </button>
          </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="sp-loading" aria-live="polite">
          <span class="sp-spinner" />
          <p>Đang tải danh sách khóa học…</p>
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredCourses.length === 0" class="sp-empty">
          <div class="sp-empty-icon-wrap">
            <BookOpen :size="36" :stroke-width="1.4" />
          </div>
          <h3 class="sp-empty-title">Không tìm thấy khóa học nào</h3>
          <p class="sp-empty-desc" v-if="filter === 'all'">
            Bạn chưa đăng ký khóa học nào. Hãy khám phá các khóa học thú vị nhé!
          </p>
          <p class="sp-empty-desc" v-else>
            Không có khóa học nào khớp với bộ lọc hiện tại.
          </p>
          <NuxtLink v-if="filter === 'all'" to="/courses" class="sp-cta-btn">
            Khám phá ngay
          </NuxtLink>
        </div>

        <!-- Course grid -->
        <div v-else class="sp-grid">
          <CourseProgressCard
            v-for="enrollment in filteredCourses"
            :key="enrollment.id"
            :course="enrollment.course"
            :progress="enrollment.progress"
          />
        </div>

      </div>
    </main>
  </div>
</template>

<style scoped>
/* ─────────────────────────────────────────────────
   CSS variables (scoped defaults + dark overrides)
───────────────────────────────────────────────── */
.student-portal {
  --green: #1D9E75;
  --green-rgb: 29, 158, 117;
  --green-deep: #145c46;
  --green-soft: rgba(29, 158, 117, 0.15);
  --text: #111827;
  --muted: #6b7280;
  --surface-strong: #ffffff;
  --line: #e5e7eb;

  min-height: calc(100vh - 80px);
  background: var(--bg, #f9fafb);
  display: flex;
  flex-direction: column;
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
}


/* ─────────────────────────────────────────────────
   HERO
───────────────────────────────────────────────── */
.sp-hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 50%, #163d2a 100%);
  padding: 5rem 1.5rem 4.5rem;
  border-bottom: 1px solid rgba(29, 158, 117, 0.18);
  color: #fff;
}

/* CSS grid texture */
.sp-hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(29, 158, 117, 0.07) 1px, transparent 1px),
    linear-gradient(90deg, rgba(29, 158, 117, 0.07) 1px, transparent 1px);
  background-size: 48px 48px;
  pointer-events: none;
}

/* Radial glow blobs */
.sp-glow {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(80px);
}
.sp-glow--1 {
  width: 600px;
  height: 600px;
  top: -200px;
  right: -120px;
  background: radial-gradient(circle, rgba(29, 158, 117, 0.22) 0%, transparent 70%);
}
.sp-glow--2 {
  width: 400px;
  height: 400px;
  bottom: -150px;
  left: 5%;
  background: radial-gradient(circle, rgba(29, 158, 117, 0.14) 0%, transparent 70%);
}
.sp-glow--3 {
  width: 300px;
  height: 300px;
  top: 40%;
  left: 45%;
  background: radial-gradient(circle, rgba(29, 158, 117, 0.10) 0%, transparent 70%);
}

.sp-hero-inner {
  position: relative;
  z-index: 1;
  max-width: 1280px;
  margin: 0 auto;
}

/* Badge pill */
.sp-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(29, 158, 117, 0.18);
  border: 1px solid rgba(29, 158, 117, 0.38);
  color: #6ee7c0;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  padding: 6px 14px;
  border-radius: 999px;
  margin-bottom: 1.5rem;
  backdrop-filter: blur(6px);
}
.sp-badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #1D9E75;
  animation: pulse-dot 2s ease-in-out infinite;
  flex-shrink: 0;
}
@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(29, 158, 117, 0.6); }
  50%       { opacity: 0.8; transform: scale(1.15); box-shadow: 0 0 0 6px rgba(29, 158, 117, 0); }
}

/* Heading */
.sp-hero-title {
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 800;
  line-height: 1.2;
  margin: 0 0 1rem;
  color: #fff;
  letter-spacing: -0.02em;
}

/* Gradient name */
.hero-em {
  font-style: normal;
  background: linear-gradient(90deg, #5de8b7 0%, #1D9E75 60%, #a3f0d7 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Subtitle */
.sp-hero-subtitle {
  font-size: 1.05rem;
  color: rgba(220, 245, 235, 0.78);
  line-height: 1.65;
  max-width: 560px;
  margin: 0 0 2.75rem;
}

/* ── Stat cards shelf ── */
.sp-stats-row {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.sp-stat-card {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.10);
  border-radius: 16px;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  flex: 1;
  min-width: 200px;
  transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1),
              background 0.2s ease,
              border-color 0.2s ease;
  cursor: default;
}
.sp-stat-card:hover {
  transform: translateY(-5px);
  background: rgba(255, 255, 255, 0.10);
  border-color: rgba(29, 158, 117, 0.35);
}

.sp-stat-icon-wrap {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(29, 158, 117, 0.20);
  border: 1px solid rgba(29, 158, 117, 0.30);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #5de8b7;
}

.sp-stat-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sp-stat-value {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
  color: #fff;
  letter-spacing: -0.03em;
}

.sp-stat-label {
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: rgba(255, 255, 255, 0.60);
}

/* ─────────────────────────────────────────────────
   MAIN CONTENT
───────────────────────────────────────────────── */
.sp-main {
  flex: 1;
  padding: 40px 24px 64px;
}

.sp-container {
  max-width: 1280px;
  margin: 0 auto;
}

.sp-schedule {
  margin-bottom: 2.5rem;
}

/* ── Section header ── */
.sp-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.75rem;
  flex-wrap: wrap;
}

.sp-section-title {
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
  font-size: 1.55rem;
  font-weight: 800;
  color: var(--text);
  margin: 0;
  letter-spacing: -0.02em;
}

/* ── Filter pills ── */
.sp-filter-group {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 4px;
}

.sp-filter-pill {
  padding: 7px 16px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-size: 0.84rem;
  font-weight: 700;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
  white-space: nowrap;
}
.sp-filter-pill:hover:not(.is-active) {
  color: var(--text);
  background: rgba(29, 158, 117, 0.08);
}
.sp-filter-pill.is-active {
  background: var(--green);
  color: #fff;
  box-shadow: 0 2px 10px rgba(29, 158, 117, 0.35);
}

/* ── Course grid ── */
.sp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

/* ── Loading state ── */
.sp-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 5rem 0;
  color: var(--muted);
  font-size: 0.95rem;
  font-weight: 600;
}

.sp-spinner {
  display: block;
  width: 42px;
  height: 42px;
  border: 3px solid var(--line);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: sp-spin 0.85s linear infinite;
}

@keyframes sp-spin {
  to { transform: rotate(360deg); }
}

/* ── Empty state ── */
.sp-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 5rem 2rem;
  background: var(--surface-strong);
  border: 1.5px dashed var(--line);
  border-radius: 24px;
}

.sp-empty-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--green-soft, rgba(29, 158, 117, 0.12));
  border: 1px solid rgba(29, 158, 117, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--green);
  margin-bottom: 1.5rem;
}

.sp-empty-title {
  font-family: 'Be Vietnam Pro', system-ui, sans-serif;
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--text);
  margin: 0 0 0.6rem;
}

.sp-empty-desc {
  color: var(--muted);
  font-size: 0.95rem;
  line-height: 1.65;
  max-width: 400px;
  margin: 0;
}

.sp-cta-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 1.75rem;
  padding: 12px 28px;
  background: var(--green);
  color: #fff;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  box-shadow: 0 4px 14px rgba(29, 158, 117, 0.30);
  transition: opacity 0.2s, transform 0.2s;
}
.sp-cta-btn:hover {
  opacity: 0.88;
  transform: translateY(-2px);
}

/* ─────────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .sp-stats-row {
    gap: 0.85rem;
  }
}

@media (max-width: 768px) {
  .sp-hero {
    padding: 3.5rem 1.25rem 3rem;
  }
  .sp-hero-title {
    font-size: 1.75rem;
  }
  .sp-hero-subtitle {
    font-size: 0.95rem;
    margin-bottom: 2rem;
  }
  .sp-stats-row {
    flex-direction: column;
  }
  .sp-stat-card {
    min-width: unset;
    width: 100%;
  }
  .sp-main {
    padding: 28px 16px 48px;
  }
  .sp-section-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .sp-filter-group {
    width: 100%;
  }
  .sp-filter-pill {
    flex: 1;
    text-align: center;
    padding: 8px 6px;
  }
  .sp-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .sp-hero {
    padding: 2.5rem 1rem 2.5rem;
  }
  .sp-hero-title {
    font-size: 1.45rem;
  }
  .sp-badge {
    font-size: 0.72rem;
  }
  .sp-stat-value {
    font-size: 1.65rem;
  }
}

/* ─────────────────────────────────────────────────
   DARK MODE
───────────────────────────────────────────────── */
[data-theme="dark"] .sp-section-title {
  color: var(--text);
}

[data-theme="dark"] .sp-filter-group {
  background: var(--surface);
  border-color: var(--line);
}

[data-theme="dark"] .sp-filter-pill {
  color: var(--muted);
}

[data-theme="dark"] .sp-filter-pill:hover:not(.is-active) {
  color: var(--text);
  background: rgba(var(--green-rgb), 0.12);
}

[data-theme="dark"] .sp-empty {
  background: var(--surface);
  border-color: var(--line);
}

[data-theme="dark"] .sp-empty-title {
  color: var(--text);
}

[data-theme="dark"] .sp-empty-desc {
  color: var(--muted);
}

[data-theme="dark"] .sp-loading {
  color: var(--muted);
}

[data-theme="dark"] .sp-spinner {
  border-color: var(--line);
  border-top-color: var(--green);
}
</style>
