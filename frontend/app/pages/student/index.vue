<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
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
  
  try {
    const res = await useApi<any[]>('/user/enrollments', {
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    enrollments.value = res || []
  } catch (e) {
    console.error('Failed to fetch enrollments:', e)
  } finally {
    loading.value = false
  }
})

const filteredCourses = computed(() => {
  if (filter.value === 'all') return enrollments.value
  if (filter.value === 'completed') return enrollments.value.filter(e => e.progress >= 100)
  return enrollments.value.filter(e => e.progress < 100)
})

const totalCourses = computed(() => enrollments.value.length)
const completedCourses = computed(() => enrollments.value.filter(e => e.progress >= 100).length)
const inProgressCourses = computed(() => totalCourses.value - completedCourses.value)

const mockEvents = [
  { id: 1, title: 'Học nhóm Nuxt.js', time: '08:30', date: 'Hôm nay', type: 'lesson', course: 'Nuxt.js Masterclass', location: 'Phòng học 302' },
  { id: 2, title: 'Kiểm tra giữa kỳ', time: '14:00', date: 'Hôm nay', type: 'exam', course: 'Cơ sở dữ liệu', location: 'Trực tuyến' },
  { id: 3, title: 'Hạn chót nộp đồ án', time: '23:59', date: 'Ngày mai', type: 'deadline', course: 'Lập trình Web' },
] as any[]
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
            <span class="material-symbols-outlined">school</span>
            <div>
              <div class="sp-stat-value">{{ totalCourses }}</div>
              <div class="sp-stat-label">Khóa học đã đăng ký</div>
            </div>
          </div>
          <div class="sp-stat-card">
            <span class="material-symbols-outlined" style="color: var(--green)">pending_actions</span>
            <div>
              <div class="sp-stat-value">{{ inProgressCourses }}</div>
              <div class="sp-stat-label">Đang học</div>
            </div>
          </div>
          <div class="sp-stat-card">
            <span class="material-symbols-outlined" style="color: var(--green)">verified</span>
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
        <DashboardSchedule :events="mockEvents" title="Lịch học của tôi" style="margin-bottom: 2.5rem;" />

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
          <div class="sp-empty-icon"><span class="material-symbols-outlined">menu_book</span></div>
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
  background: var(--surface, #f8fafc);
  display: flex;
  flex-direction: column;
}

/* ── Hero Section ── */
.sp-hero {
  background: var(--green);
  color: #fff;
  padding: 3rem 1.5rem;
  position: relative;
  overflow: hidden;
}
.sp-hero::before { content: none; }
.sp-hero-inner {
  max-width: 1200px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}
.sp-hero-title {
  font-size: 2rem;
  font-weight: 800;
  margin: 0 0 0.5rem;
  line-height: 1.2;
}
.sp-hero-subtitle {
  font-size: 1rem;
  color: rgba(255,255,255,0.8);
  margin-bottom: 2rem;
}

/* ── Stats ── */
.sp-stats-row {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}
.sp-stat-card {
  background: rgba(255,255,255,0.1);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 16px;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  min-width: 220px;
  flex: 1;
}
.sp-stat-card .material-symbols-outlined {
  font-size: 32px;
  color: #fff;
  opacity: 0.9;
}
.sp-stat-value {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 4px;
}
.sp-stat-label {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(255,255,255,0.7);
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
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--on-surface, #0f172a);
  margin: 0;
}
.sp-filters {
  display: flex;
  background: var(--surface-low, #f1f5f9);
  padding: 4px;
  border-radius: 12px;
  gap: 4px;
}
.sp-filter-btn {
  padding: 8px 16px;
  border: none;
  background: transparent;
  color: var(--on-surface-variant, #475569);
  font-size: 0.85rem;
  font-weight: 700;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.sp-filter-btn:hover {
  color: var(--on-surface, #0f172a);
}
.sp-filter-btn.active {
  background: var(--surface-lowest, #fff);
  color: var(--primary, var(--green));
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
  color: var(--outline, #64748b);
  font-weight: 600;
  gap: 1rem;
}
.sp-spinner {
  width: 40px; height: 40px;
  border: 3px solid var(--surface-dim, #e5e7eb);
  border-top-color: var(--primary, var(--green));
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
  background: var(--surface-lowest, #fff);
  border: 1px dashed var(--surface-dim, #cbd5e1);
  border-radius: 24px;
  text-align: center;
}
.sp-empty-icon {
  width: 80px; height: 80px;
  background: var(--surface-low, #f1f5f9);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 1.5rem;
  color: var(--outline, #94a3b8);
}
.sp-empty-icon .material-symbols-outlined { font-size: 40px; }
.sp-empty h3 {
  font-size: 1.25rem; font-weight: 800; color: var(--on-surface); margin: 0 0 0.5rem;
}
.sp-empty p {
  color: var(--on-surface-variant); font-size: 0.95rem; margin: 0; max-width: 400px;
}
.sp-btn-primary {
  display: inline-block;
  padding: 12px 24px;
  background: var(--primary, var(--green));
  color: #fff;
  border-radius: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: opacity 0.2s;
  margin-top: 1.5rem;
}
.sp-btn-primary:hover { opacity: 0.9; }

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
