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
    .map((exam) => {
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
    <!-- Hero Section -->
    <section class="sp-hero">
      <div class="sp-hero-inner">
        <h1 class="sp-hero-title">Chào mừng trở lại, {{ auth.user?.name || 'Học viên' }}! 👋</h1>
        <p class="sp-hero-subtitle">Tiếp tục hành trình học tập của bạn. Mỗi ngày một chút tiến bộ!</p>
        
        <div class="sp-stats-row">
          <div class="sp-stat-card">
            <BookOpen :size="34" :stroke-width="1.5" style="color: #9FE1CB; opacity: 0.95;" />
            <div>
              <div class="sp-stat-value">{{ totalCourses }}</div>
              <div class="sp-stat-label">Khóa học đã đăng ký</div>
            </div>
          </div>
          <div class="sp-stat-card">
            <Clock :size="34" :stroke-width="1.5" style="color: #9FE1CB; opacity: 0.95;" />
            <div>
              <div class="sp-stat-value">{{ inProgressCourses }}</div>
              <div class="sp-stat-label">Đang học</div>
            </div>
          </div>
          <div class="sp-stat-card">
            <CircleCheckBig :size="34" :stroke-width="1.5" style="color: #9FE1CB; opacity: 0.95;" />
            <div>
              <div class="sp-stat-value">{{ completedCourses }}</div>
              <div class="sp-stat-label">Đã hoàn thành</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <main class="sp-main">
      <div class="sp-container">
        <!-- Learning Schedule -->
        <DashboardSchedule :events="scheduleEvents" title="Kỳ thi sắp tới" style="margin-bottom: 2.5rem;" />

        <div class="sp-header">
          <h2 class="sp-section-title">Khóa học của tôi</h2>
          <div class="sp-filters">
            <button class="sp-filter-btn" :class="{ active: filter === 'all' }" @click="filter = 'all'">Tất cả</button>
            <button class="sp-filter-btn" :class="{ active: filter === 'in_progress' }" @click="filter = 'in_progress'">Đang học</button>
            <button class="sp-filter-btn" :class="{ active: filter === 'completed' }" @click="filter = 'completed'">Hoàn thành</button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="sp-loading">
          <div class="sp-spinner"></div>
          <p>Đang tải danh sách khóa học...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredCourses.length === 0" class="sp-empty">
          <div class="sp-empty-icon"><BookOpen :size="40" :stroke-width="1.25" /></div>
          <h3>Không tìm thấy khóa học nào</h3>
          <p v-if="filter === 'all'">Bạn chưa đăng ký khóa học nào. Hãy khám phá các khóa học thú vị nhé!</p>
          <p v-else>Không có khóa học nào khớp với bộ lọc của bạn.</p>
          <NuxtLink v-if="filter === 'all'" to="/courses" class="sp-btn-primary mt-4">Khám phá ngay</NuxtLink>
        </div>

        <!-- Course Grid -->
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
.student-portal {
  min-height: calc(100vh - 80px);
  background: var(--bg);
  display: flex;
  flex-direction: column;
}

/* ── Hero Section ── */
.sp-hero {
  background: linear-gradient(135deg, #04342C 0%, #1D9E75 60%, #185FA5 100%);
  color: #fff;
  padding: 4rem 1.5rem;
  position: relative;
  overflow: hidden;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.sp-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 80% 20%, rgba(93, 202, 165, 0.15), transparent 50%);
  pointer-events: none;
}
.sp-hero-inner {
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}
.sp-hero-title {
  font-family: 'Outfit', sans-serif;
  font-size: 2.25rem;
  font-weight: 800;
  margin: 0 0 0.5rem;
  line-height: 1.2;
}
.sp-hero-subtitle {
  font-size: 1.05rem;
  color: rgba(240, 250, 245, 0.85);
  margin-bottom: 2.5rem;
}

/* ── Stats ── */
.sp-stats-row {
  display: flex;
  gap: 1.25rem;
  flex-wrap: wrap;
}
.sp-stat-card {
  background: rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  padding: 1.5rem 1.75rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  min-width: 220px;
  flex: 1;
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.2s, border-color 0.2s;
}
.sp-stat-card:hover {
  transform: translateY(-4px);
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.25);
}
.sp-stat-card svg {
  flex-shrink: 0;
}
.sp-stat-value {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 6px;
  color: #ffffff;
}
.sp-stat-label {
  font-size: 0.82rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(255, 255, 255, 0.75);
}

/* ── Main Content ── */
.sp-main {
  flex: 1;
  padding: 3rem 1.5rem;
}
.sp-container {
  max-width: 1200px;
  margin: 0 auto;
}
.sp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}
.sp-section-title {
  font-family: 'Outfit', sans-serif;
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--text);
  margin: 0;
}
.sp-filters {
  display: flex;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 4px;
  border-radius: 14px;
  gap: 4px;
}
.sp-filter-btn {
  padding: 8px 18px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}
.sp-filter-btn:hover {
  color: var(--text);
}
.sp-filter-btn.active {
  background: var(--surface-strong);
  color: var(--green);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

/* ── Grid ── */
.sp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 2rem;
}

/* ── Loading / Empty ── */
.sp-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 0;
  color: var(--muted);
  font-weight: 600;
  gap: 1rem;
}
.sp-spinner {
  width: 40px; height: 40px;
  border: 3px solid var(--line);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.sp-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 5rem 2rem;
  background: var(--surface-strong);
  border: 1px dashed var(--line);
  border-radius: 28px;
  text-align: center;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
}
.sp-empty-icon {
  width: 80px; height: 80px;
  background: var(--bg);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1.5rem;
  color: var(--green);
}
.sp-empty-icon svg { display: block; }
.sp-empty h3 {
  font-family: 'Outfit', sans-serif;
  font-size: 1.35rem; font-weight: 800; color: var(--text); margin: 0 0 0.5rem;
}
.sp-empty p {
  color: var(--muted); font-size: 0.95rem; margin: 0; max-width: 400px;
  line-height: 1.6;
}
.sp-btn-primary {
  display: inline-block;
  padding: 12px 26px;
  background: var(--green);
  color: #fff;
  border-radius: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: opacity 0.2s, transform 0.2s;
  margin-top: 1.5rem;
  box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}
.sp-btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .sp-hero { padding: 2rem 1rem; }
  .sp-hero-title { font-size: 1.5rem; }
  .sp-main { padding: 2rem 1rem; }
  .sp-header { flex-direction: column; align-items: flex-start; }
  .sp-filters { width: 100%; display: flex; }
  .sp-filter-btn { flex: 1; text-align: center; padding: 8px 4px; }
  .sp-grid { grid-template-columns: 1fr; }
}
</style>
