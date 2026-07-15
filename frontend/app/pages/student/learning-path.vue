<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const learningPathData = ref<any>(null)
const expandedTerms = ref<number[]>([1, 2]) // Default expand Term 1 & 2

onMounted(async () => {
  try {
    const res = await useApi<any>('/me/learning-path', {
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    learningPathData.value = res
    
    // Automatically expand the terms that contain active courses
    if (res.has_curriculum && res.terms) {
      const activeTerms: number[] = []
      res.terms.forEach((t: any) => {
        const hasActive = t.courses.some((c: any) => c.status === 'learning')
        if (hasActive) {
          activeTerms.push(t.term_number)
        }
      })
      if (activeTerms.length > 0) {
        expandedTerms.value = activeTerms
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const overallCreditsProgress = computed(() => {
  if (!learningPathData.value || !learningPathData.value.total_credits_required) return 0
  const req = learningPathData.value.total_credits_required
  const earned = learningPathData.value.total_credits_earned
  return Math.round((earned / req) * 100)
})

function toggleTerm(termNum: number) {
  const index = expandedTerms.value.indexOf(termNum)
  if (index > -1) {
    expandedTerms.value.splice(index, 1)
  } else {
    expandedTerms.value.push(termNum)
  }
}

function getTermCompletedCount(courses: any[]) {
  return courses.filter(c => c.status === 'completed').length
}

function getStatusLabel(status: string) {
  if (status === 'completed') return 'Đã hoàn thành'
  if (status === 'learning') return 'Đang học'
  return 'Chưa đăng ký'
}

function getStatusClass(status: string) {
  if (status === 'completed') return 'is-completed'
  if (status === 'learning') return 'is-learning'
  return 'is-not-started'
}
</script>

<template>
  <div class="lp-container">
    <!-- Header -->
    <div class="lp-header-section">
      <div>
        <p class="section-kicker">Đào tạo chính quy</p>
        <h1 class="lp-page-title">Chương Trình Đào Tạo</h1>
        <p class="lp-page-sub" v-if="learningPathData?.has_curriculum">
          Lộ trình học tập chi tiết của bạn thuộc lớp hành chính khoa đào tạo
        </p>
        <p class="lp-page-sub" v-else>
          Theo dõi tiến độ học tập và tích lũy tín chỉ trong lộ trình
        </p>
      </div>
      <NuxtLink to="/student/recommendations" class="lp-recommend-btn">
        <i class="pi pi-sparkles" style="font-size:0.875rem" /> Gợi ý học phần
      </NuxtLink>
    </div>

    <!-- Loading Shimmer -->
    <div v-if="loading" class="lp-loading-wrapper">
      <div class="dashboard-card shimmer-banner"></div>
      <div v-for="i in 3" :key="i" class="shimmer-accordion-item"></div>
    </div>

    <!-- Empty/No Curriculum Error -->
    <div v-else-if="!learningPathData?.has_curriculum" class="lp-empty-card dashboard-card">
      <i class="pi pi-clone" style="font-size:3.0rem" />
      <h3>Chưa gán Chương trình đào tạo</h3>
      <p>{{ learningPathData?.message || 'Tài khoản của bạn chưa được gán lộ trình hoặc lớp học hành chính.' }}</p>
      <NuxtLink to="/student/courses" class="primary-action-btn">Khám phá các khóa học</NuxtLink>
    </div>

    <div v-else class="lp-content-layout">
      <!-- Overall progress card -->
      <div class="dashboard-card lp-progress-banner">
        <div class="progress-info">
          <div class="info-block">
            <span class="info-label">Chương trình học</span>
            <strong class="info-value text-primary">{{ learningPathData.curriculum_name }}</strong>
            <span class="info-code">Mã CTĐT: {{ learningPathData.curriculum_code }}</span>
          </div>
          <div class="progress-bar-container">
            <div class="progress-stat">
              <span>Tiến độ tích lũy tín chỉ</span>
              <strong>{{ learningPathData.total_credits_earned }} / {{ learningPathData.total_credits_required }} Tín chỉ</strong>
            </div>
            <div class="progress-track-bg">
              <div class="progress-fill-bar" :style="{ width: `${overallCreditsProgress}%` }"></div>
            </div>
            <span class="percentage-badge">{{ overallCreditsProgress }}% Hoàn tất chương trình</span>
          </div>
        </div>
      </div>

      <!-- Semesters Accordion -->
      <div class="lp-semesters-wrapper">
        <div 
          v-for="term in learningPathData.terms" 
          :key="term.term_number" 
          class="term-accordion-item dashboard-card"
          :class="{ 'is-open': expandedTerms.includes(term.term_number) }"
        >
          <!-- Accordion Header -->
          <div class="term-header" @click="toggleTerm(term.term_number)">
            <div class="term-header-left">
              <div class="term-num-badge">Học kỳ {{ term.term_number }}</div>
              <div class="term-header-summary">
                <span class="term-credits-stat">Số tín chỉ: <strong>{{ term.credits }}</strong></span>
                <span class="dot-separator">•</span>
                <span class="term-completed-stat">
                  Đã đạt: <strong>{{ getTermCompletedCount(term.courses) }} / {{ term.courses.length }}</strong> môn học
                </span>
              </div>
            </div>
            <div class="term-header-right">
              <i v-if="!expandedTerms.includes(term.term_number)" class="pi pi-chevron-down" style="font-size:1.25rem" />
              <i v-else class="pi pi-chevron-up" style="font-size:1.25rem" />
            </div>
          </div>

          <!-- Accordion Content -->
          <div v-show="expandedTerms.includes(term.term_number)" class="term-courses-list">
            <div v-if="term.courses.length === 0" class="course-row-empty">
              Chưa có học phần nào được thiết lập cho học kỳ này.
            </div>
            
            <div 
              v-else 
              v-for="course in term.courses" 
              :key="course.id" 
              class="course-row-item"
              :class="getStatusClass(course.status)"
            >
              <!-- Course Thumb / Info -->
              <div class="course-row-meta">
                <div class="course-status-icon">
                  <i v-if="course.status === 'completed'" class="pi pi-check-circle completed-check" style="font-size:1.125rem" />
                  <i v-else-if="course.status === 'learning'" class="pi pi-clock learning-clock" style="font-size:1.125rem" />
                  <i v-else class="pi pi-question-circle notstarted-help" style="font-size:1.125rem" />
                </div>
                <div class="course-title-block">
                  <NuxtLink :to="`/student/courses/${course.id}`" class="course-link-title">
                    {{ course.title }}
                  </NuxtLink>
                  <div class="course-badges">
                    <span class="badge credit-badge">{{ course.credits }} tín chỉ</span>
                    <span class="badge mode-badge" :class="course.course_mode">
                      {{ course.course_mode === 'core' ? 'Bắt buộc' : 'Tự chọn' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Course Progress & Grade -->
              <div class="course-row-progress" v-if="course.status !== 'not_started'">
                <div class="progress-mini-bar">
                  <div class="progress-fill" :style="{ width: `${course.progress}%` }"></div>
                </div>
                <span class="progress-pct-text">Tiến độ: {{ Math.round(course.progress) }}%</span>
                <span class="grade-badge" v-if="course.final_score !== null">
                  Điểm: <strong>{{ course.final_score }}</strong>
                </span>
              </div>
              <div class="course-row-progress not-enrolled" v-else>
                <span class="not-enrolled-text">Môn học chưa đăng ký học kỳ này</span>
              </div>

              <!-- Course Row Actions -->
              <div class="course-row-action">
                <span class="status-tag" :class="course.status">
                  {{ getStatusLabel(course.status) }}
                </span>
                
                <NuxtLink 
                  :to="`/student/courses/${course.id}`" 
                  class="action-button-link"
                  :class="course.status"
                >
                  {{ course.status === 'completed' ? 'Ôn tập bài' : course.status === 'learning' ? 'Học tiếp' : 'Xem thông tin' }}
                </NuxtLink>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.lp-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.lp-header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
}

.section-kicker {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--green-deep, #047857);
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}

.lp-page-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 6px 0;
}

.lp-page-sub {
  font-size: 0.88rem;
  color: #64748b;
  margin: 0;
}

.lp-recommend-btn {
  background: #ecfdf5;
  color: #047857;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  transition: all 160ms ease;
}

.lp-recommend-btn:hover {
  background: #d1fae5;
  transform: translateY(-1px);
}

/* Loading Shimmer */
.lp-loading-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shimmer-banner {
  height: 120px;
  border-radius: 16px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmerAnim 1.5s infinite;
}

.shimmer-accordion-item {
  height: 60px;
  border-radius: 12px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmerAnim 1.5s infinite;
}

@keyframes shimmerAnim {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Empty State */
.lp-empty-card {
  padding: 48px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.empty-icon {
  color: #94a3b8;
}

.lp-empty-card h3 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  color: #334155;
}

.lp-empty-card p {
  color: #64748b;
  font-size: 0.9rem;
  max-width: 420px;
  margin: 0;
}

.primary-action-btn {
  background: var(--green-deep, #047857);
  color: #fff;
  padding: 10px 20px;
  border-radius: 10px;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 600;
  transition: background 150ms;
}

.primary-action-btn:hover {
  background: #065f46;
}

/* Progress Banner */
.lp-progress-banner {
  padding: 24px;
}

.progress-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: center;
}

@media (max-width: 768px) {
  .progress-info {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}

.info-block {
  display: flex;
  flex-direction: column;
}

.info-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-value {
  font-size: 1.25rem;
  font-weight: 800;
  color: #1e293b;
  margin: 4px 0;
}

.info-code {
  font-size: 0.8rem;
  color: #64748b;
}

.progress-bar-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.progress-stat {
  display: flex;
  justify-content: space-between;
  font-size: 0.82rem;
  font-weight: 600;
  color: #475569;
}

.progress-track-bg {
  height: 8px;
  background: #e2e8f0;
  border-radius: 99px;
  overflow: hidden;
}

.progress-fill-bar {
  height: 100%;
  background: linear-gradient(90deg, #10b981, #059669);
  border-radius: 99px;
  transition: width 500ms ease;
}

.percentage-badge {
  font-size: 0.72rem;
  font-weight: 700;
  color: #059669;
}

/* Semesters Accordions */
.lp-semesters-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.term-accordion-item {
  border: 1px solid rgba(0, 0, 0, 0.04);
  overflow: hidden;
  transition: all 200ms ease;
}

.term-accordion-item.is-open {
  box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}

.term-header {
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  background: #f8fafc;
  transition: background 150ms;
}

.term-header:hover {
  background: #f1f5f9;
}

.term-header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.term-num-badge {
  background: #e2e8f0;
  color: #334155;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 800;
}

.term-header-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  color: #64748b;
}

.dot-separator {
  color: #cbd5e1;
}

.term-header-right {
  color: #64748b;
}

.term-courses-list {
  padding: 10px 20px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  border-top: 1px solid rgba(0,0,0,0.03);
}

.course-row-empty {
  padding: 16px;
  text-align: center;
  color: #94a3b8;
  font-style: italic;
  font-size: 0.85rem;
}

/* Course Rows */
.course-row-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e2e8f0;
  gap: 16px;
  transition: all 150ms;
}

.course-row-item:hover {
  border-color: #cbd5e1;
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

@media (max-width: 960px) {
  .course-row-item {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
}

.course-row-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.course-status-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.completed-check {
  color: #10b981;
}

.learning-clock {
  color: #3b82f6;
}

.notstarted-help {
  color: #94a3b8;
}

.course-title-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.course-link-title {
  font-weight: 700;
  font-size: 0.92rem;
  color: #334155;
  text-decoration: none;
  transition: color 150ms;
}

.course-link-title:hover {
  color: var(--green-deep, #047857);
}

.course-badges {
  display: flex;
  gap: 6px;
  align-items: center;
}

.badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
}

.credit-badge {
  background: #f1f5f9;
  color: #475569;
}

.mode-badge.core {
  background: #fef2f2;
  color: #b91c1c;
}

.mode-badge.elective {
  background: #f0fdf4;
  color: #15803d;
}

/* Progress area in course row */
.course-row-progress {
  display: flex;
  flex-direction: column;
  width: 180px;
  gap: 4px;
}

@media (max-width: 960px) {
  .course-row-progress {
    width: 100%;
  }
}

.progress-mini-bar {
  height: 4px;
  background: #e2e8f0;
  border-radius: 99px;
  overflow: hidden;
}

.progress-mini-bar .progress-fill {
  height: 100%;
  background: #3b82f6;
  border-radius: 99px;
}

.course-row-item.is-completed .progress-mini-bar .progress-fill {
  background: #10b981;
}

.progress-pct-text {
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
}

.grade-badge {
  font-size: 0.72rem;
  color: #0f172a;
}

.grade-badge strong {
  color: #10b981;
  font-size: 0.8rem;
}

.not-enrolled-text {
  font-size: 0.78rem;
  color: #94a3b8;
  font-style: italic;
}

/* Row Actions */
.course-row-action {
  display: flex;
  align-items: center;
  gap: 16px;
}

@media (max-width: 960px) {
  .course-row-action {
    justify-content: space-between;
  }
}

.status-tag {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 20px;
  border: 1px solid #cbd5e1;
  color: #64748b;
  background: #f8fafc;
}

.status-tag.completed {
  background: #ecfdf5;
  color: #047857;
  border-color: #a7f3d0;
}

.status-tag.learning {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

.action-button-link {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 6px 14px;
  border-radius: 8px;
  text-decoration: none;
  transition: all 150ms;
  text-align: center;
  white-space: nowrap;
}

.action-button-link.completed {
  background: #f1f5f9;
  color: #475569;
}

.action-button-link.completed:hover {
  background: #e2e8f0;
}

.action-button-link.learning {
  background: #3b82f6;
  color: #fff;
}

.action-button-link.learning:hover {
  background: #2563eb;
}

.action-button-link.not_started {
  background: #f1f5f9;
  color: #0f172a;
  border: 1px solid #cbd5e1;
}

.action-button-link.not_started:hover {
  background: #e2e8f0;
}

/* Dark mode adjustment override */
[data-theme="dark"] .term-header {
  background: #1e293b;
}
[data-theme="dark"] .course-row-item {
  background: #0f172a;
  border-color: #334155;
}
[data-theme="dark"] .lp-page-title {
  color: #f1f5f9;
}
</style>
