<template>
  <div class="adv-page">
    <!-- HERO -->
    <header class="adv-hero">
      <div class="adv-hero-overlay" />
      <div class="adv-hero-inner">
        <div class="adv-badge">
          <span class="adv-badge-dot" />
          AI Learning Advisor
        </div>
        <h1 class="adv-hero-title">Cố vấn học tập AI</h1>
        <p class="adv-hero-lead">
          Phân tích bảng điểm học thuật, theo dõi tiến độ các môn học và đề xuất lộ trình ôn tập cá nhân hóa.
        </p>
      </div>
    </header>

    <!-- LOADING STATE -->
    <div v-if="loading" class="adv-loading-wrap">
      <div class="adv-spinner" />
      <p>Đang tải dữ liệu học tập...</p>
    </div>

    <!-- MAIN INTERFACE -->
    <div v-else class="adv-container">
      <!-- 1. Stats Row -->
      <div class="adv-stats-grid">
        <div class="adv-stat-card">
          <p class="adv-label">GPA (Thang 10)</p>
          <strong class="adv-stat-value text-green">{{ profile?.gpa?.toFixed(2) || '0.00' }}</strong>
          <span class="adv-muted">Trung bình tích lũy</span>
        </div>
        <div class="adv-stat-card">
          <p class="adv-label">GPA (Thang 4)</p>
          <strong class="adv-stat-value text-blue">{{ profile?.gpa_4?.toFixed(2) || '0.00' }}</strong>
          <span class="adv-muted">Thang điểm quốc tế</span>
        </div>
        <div class="adv-stat-card">
          <p class="adv-label">Tín chỉ tích lũy</p>
          <strong class="adv-stat-value">{{ profile?.total_credits_earned || 0 }}</strong>
          <span class="adv-muted">Đã hoàn thành</span>
        </div>
        <div class="adv-stat-card">
          <p class="adv-label">Môn học đã hoàn thành</p>
          <strong class="adv-stat-value">{{ profile?.total_completed_courses || 0 }}</strong>
          <span class="adv-muted">Môn học tích lũy</span>
        </div>
      </div>

      <div class="adv-layout">
        <!-- LEFT: Academic Status -->
        <div class="adv-column-left">
          <!-- Active e-Learning Courses -->
          <div class="adv-card">
            <h3 class="adv-card-title">
              <BookOpen :size="18" />
              <span>Tiến độ học phần đang học</span>
            </h3>
            <div v-if="profile?.enrolled_courses?.length" class="adv-course-list">
              <div v-for="c in profile.enrolled_courses" :key="c.course_id" class="adv-course-item">
                <div class="adv-course-meta">
                  <strong>{{ c.course_title }}</strong>
                  <span class="adv-muted" v-if="c.quiz_avg_score !== null">
                    Quiz: {{ c.quiz_avg_score }}đ
                  </span>
                </div>
                <div class="adv-progress-wrap">
                  <div class="adv-progress-bar">
                    <span class="adv-progress-fill" :style="{ width: `${c.progress_percent}%` }" />
                  </div>
                  <span class="adv-progress-num">{{ c.progress_percent }}%</span>
                </div>
              </div>
            </div>
            <div v-else class="adv-empty-state">
              <p class="adv-muted">Bạn chưa ghi danh học phần trực tuyến nào.</p>
            </div>
          </div>

          <!-- Academic Transcript -->
          <div class="adv-card">
            <h3 class="adv-card-title">
              <GraduationCap :size="18" />
              <span>Kết quả học thuật học trình</span>
            </h3>
            <div v-if="profile?.grade_transcript?.length" class="adv-transcript">
              <table class="adv-table">
                <thead>
                  <tr>
                    <th>Môn học</th>
                    <th class="text-center">Kỳ</th>
                    <th class="text-center">Điểm số</th>
                    <th class="text-center">Điểm chữ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="g in profile.grade_transcript" :key="g.course_id">
                    <td>{{ g.course_title }}</td>
                    <td class="text-center">{{ g.term_number || 1 }}</td>
                    <td class="text-center">
                      <strong :class="g.final_score >= 4.0 ? 'text-green' : 'text-red'">
                        {{ g.final_score.toFixed(1) }}
                      </strong>
                    </td>
                    <td class="text-center">
                      <span class="adv-grade-badge" :class="`grade-${g.grade_letter?.toLowerCase().replace('+', 'plus')}`">
                        {{ g.grade_letter }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="adv-empty-state">
              <p class="adv-muted">Chưa có kết quả học tập trong học trình.</p>
            </div>
          </div>

          <!-- Curriculum Gaps -->
          <div class="adv-card">
            <h3 class="adv-card-title">
              <AlertCircle :size="18" />
              <span>Môn học bắt buộc chưa hoàn thành</span>
            </h3>
            <div v-if="profile?.curriculum_gaps?.length" class="adv-gaps-list">
              <div v-for="gap in profile.curriculum_gaps" :key="gap.course_id" class="adv-gap-item">
                <span class="adv-gap-dot" />
                <div class="adv-gap-meta">
                  <strong>{{ gap.course_title }}</strong>
                  <span class="adv-muted">Tín chỉ: {{ gap.credits }} | Dự kiến học kỳ: {{ gap.term_number || 'Chưa rõ' }}</span>
                </div>
              </div>
            </div>
            <div v-else class="adv-empty-state">
              <p class="adv-muted">Tuyệt vời! Bạn đã hoàn thành toàn bộ môn bắt buộc trong chương trình đào tạo.</p>
            </div>
          </div>
        </div>

        <!-- RIGHT: AI Advisor Output -->
        <div class="adv-column-right">
          <div class="adv-card adv-ai-card">
            <div class="adv-ai-header">
              <div class="adv-ai-avatar">
                <Sparkles :size="20" color="#fff" />
              </div>
              <div>
                <h3 class="adv-ai-title">Cố vấn AI cá nhân hóa</h3>
                <span class="adv-ai-subtitle">Dựa trên dữ liệu học tập thực tế của bạn</span>
              </div>
            </div>

            <!-- Analysis Not Run Yet -->
            <div v-if="!advice" class="adv-ai-intro">
              <p>
                Trợ lý AI sẽ tiến hành phân tích GPA, tiến độ học tập, đối chiếu chương trình đào tạo để đưa ra kế hoạch ôn tập, khắc phục môn yếu và gợi ý môn học kỳ tiếp theo.
              </p>
              <button
                class="adv-btn-primary"
                type="button"
                :disabled="generating"
                @click="generateAdvice"
              >
                <span v-if="generating" class="adv-spinner-sm" />
                <Sparkles v-else :size="16" />
                <span>{{ generating ? 'AI đang phân tích...' : 'Bắt đầu tư vấn học tập' }}</span>
              </button>
            </div>

            <!-- Analysis Output -->
            <div v-else class="adv-ai-output space-y-6">
              <!-- Assessment -->
              <div class="adv-ai-section">
                <h4 class="adv-ai-section-title">Nhận xét tổng quan</h4>
                <p class="adv-ai-text">{{ advice.overall_assessment }}</p>
              </div>

              <!-- Strengths & Weaknesses -->
              <div class="adv-two-col">
                <div class="adv-col-box bg-green-soft">
                  <h5 class="adv-col-title text-green-dark">Điểm mạnh</h5>
                  <ul class="adv-ai-list">
                    <li v-for="s in advice.strengths" :key="s">{{ s }}</li>
                  </ul>
                </div>
                <div class="adv-col-box bg-red-soft">
                  <h5 class="adv-col-title text-red-dark">Điểm yếu</h5>
                  <ul class="adv-ai-list">
                    <li v-for="w in advice.weaknesses" :key="w">{{ w }}</li>
                  </ul>
                </div>
              </div>

              <!-- Recommended Study Plan -->
              <div class="adv-ai-section">
                <h4 class="adv-ai-section-title">Kế hoạch hành động chi tiết</h4>
                <div class="adv-plan-list">
                  <div v-for="(item, idx) in advice.study_plan" :key="idx" class="adv-plan-item">
                    <div class="adv-plan-meta">
                      <span class="adv-plan-badge" :class="`priority-${item.priority}`">{{ item.priority }}</span>
                      <strong>{{ item.title }}</strong>
                    </div>
                    <p class="adv-plan-desc">{{ item.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Suggestions for next semester -->
              <div class="adv-ai-section" v-if="advice.next_term_suggestions?.length">
                <h4 class="adv-ai-section-title">Gợi ý đăng ký kỳ tiếp theo</h4>
                <ul class="adv-ai-list-bullet">
                  <li v-for="s in advice.next_term_suggestions" :key="s">{{ s }}</li>
                </ul>
              </div>

              <!-- Skills to develop -->
              <div class="adv-ai-section" v-if="advice.skills_to_develop?.length">
                <h4 class="adv-ai-section-title">Kỹ năng cần bổ trợ</h4>
                <div class="adv-chips-row">
                  <span v-for="s in advice.skills_to_develop" :key="s" class="adv-chip">{{ s }}</span>
                </div>
              </div>

              <!-- Motivational Message -->
              <div class="adv-motivation">
                <p><em>"{{ advice.motivational_message }}"</em></p>
              </div>

              <!-- Re-run advice -->
              <button
                class="adv-btn-outline"
                type="button"
                :disabled="generating"
                @click="generateAdvice"
              >
                <span>Cập nhật tư vấn mới</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { BookOpen, GraduationCap, AlertCircle, Sparkles, TrendingUp } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const loading = ref(true)
const generating = ref(false)
const profile = ref<any>(null)
const advice = ref<any>(null)

const loadProfile = async () => {
  loading.value = true
  try {
    profile.value = await useApi<any>('/ai/learning/analysis', { token: auth.token })
  } catch (err) {
    console.error('Failed to load academic profile', err)
  } finally {
    loading.value = false
  }
}

const generateAdvice = async () => {
  if (generating.value) return
  generating.value = true
  try {
    const res = await useApi<any>('/ai/learning/advise', {
      method: 'POST',
      token: auth.token,
    })
    advice.value = res
  } catch (err) {
    alert('Không thể tạo cố vấn AI vào lúc này. Vui lòng thử lại sau.')
  } finally {
    generating.value = false
  }
}

onMounted(loadProfile)
</script>

<style scoped>
.adv-page {
  font-family: 'Be Vietnam Pro', sans-serif;
  background: #f9fafb;
  min-height: 100vh;
  --green: #1d9e75;
  --green-rgb: 29, 158, 117;
  --green-deep: #14785a;
  --green-soft: rgba(29, 158, 117, 0.08);
  --blue: #2563eb;
  --blue-soft: rgba(37, 99, 235, 0.08);
  --red: #ef4444;
  --red-soft: rgba(239, 68, 68, 0.08);
  --text: #1f2937;
  --muted: #6b7280;
  --line: #e5e7eb;
}

.adv-hero {
  position: relative;
  background: linear-gradient(135deg, #071e16 0%, #0d2e22 50%, #153c2e 100%);
  padding: 56px 0;
  overflow: hidden;
}

.adv-hero-overlay {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
  background-size: 32px 32px;
}

.adv-hero-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.adv-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 4px 12px;
  border-radius: 99px;
  background: rgba(var(--green-rgb), 0.15);
  border: 1px solid rgba(var(--green-rgb), 0.3);
  color: #fff;
  font-size: 0.78rem;
  font-weight: 600;
  margin-bottom: 12px;
}

.adv-badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--green);
}

.adv-hero-title {
  font-size: 2.25rem;
  font-weight: 850;
  color: #fff;
  margin: 0 0 10px;
}

.adv-hero-lead {
  color: rgba(255, 255, 255, 0.7);
  font-size: 1.05rem;
  margin: 0;
  max-width: 560px;
  line-height: 1.6;
}

/* Container */
.adv-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 24px 80px;
}

/* Stats */
.adv-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 32px;
}

.adv-stat-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.adv-stat-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--text);
}

.text-green { color: var(--green); }
.text-blue { color: var(--blue); }
.text-red { color: var(--red); }

/* Layout Grid */
.adv-layout {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 24px;
  align-items: start;
}

.adv-column-left,
.adv-column-right {
  display: grid;
  gap: 24px;
}

/* Cards */
.adv-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 28px;
}

.adv-card-title {
  font-size: 1.15rem;
  font-weight: 750;
  color: var(--text);
  margin: 0 0 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid var(--line);
  padding-bottom: 14px;
}

/* Course Progress */
.adv-course-list {
  display: grid;
  gap: 16px;
}

.adv-course-item {
  display: grid;
  gap: 8px;
}

.adv-course-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.95rem;
}

.adv-progress-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.adv-progress-bar {
  flex-grow: 1;
  height: 8px;
  background: #f3f4f6;
  border-radius: 99px;
  overflow: hidden;
}

.adv-progress-fill {
  display: block;
  height: 100%;
  background: var(--green);
  border-radius: 99px;
}

.adv-progress-num {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
  width: 36px;
  text-align: right;
}

/* Transcript Table */
.adv-transcript {
  overflow-x: auto;
}

.adv-table {
  width: 100%;
  border-collapse: collapse;
}

.adv-table th {
  text-align: left;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted);
  border-bottom: 2px solid var(--line);
  padding: 10px 12px;
}

.adv-table td {
  font-size: 0.92rem;
  color: var(--text);
  border-bottom: 1px solid var(--line);
  padding: 14px 12px;
}

.adv-grade-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 99px;
  font-size: 0.8rem;
  font-weight: 700;
}

.grade-a, .grade-aplus { background: #dcfce7; color: #15803d; }
.grade-b, .grade-bplus { background: #dbeafe; color: #1d4ed8; }
.grade-c, .grade-cplus { background: #fef9c3; color: #a16207; }
.grade-d, .grade-dplus { background: #fee2e2; color: #b91c1c; }
.grade-f { background: #ffd2d2; color: #9c0000; }

/* Gaps */
.adv-gaps-list {
  display: grid;
  gap: 14px;
}

.adv-gap-item {
  display: flex;
  gap: 12px;
  align-items: center;
}

.adv-gap-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--red);
  flex-shrink: 0;
}

.adv-gap-meta {
  display: flex;
  flex-direction: column;
}

/* AI card */
.adv-ai-card {
  border-color: rgba(var(--green-rgb), 0.25);
  box-shadow: 0 4px 20px -6px rgba(var(--green-rgb), 0.05);
}

.adv-ai-header {
  display: flex;
  align-items: center;
  gap: 14px;
  border-bottom: 1px solid var(--line);
  padding-bottom: 20px;
  margin-bottom: 20px;
}

.adv-ai-avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--green) 0%, var(--green-deep) 100%);
  display: grid;
  place-items: center;
}

.adv-ai-title {
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--text);
  margin: 0;
}

.adv-ai-subtitle {
  font-size: 0.84rem;
  color: var(--muted);
}

.adv-ai-intro {
  text-align: center;
  padding: 32px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.adv-ai-intro p {
  font-size: 0.95rem;
  color: var(--muted);
  line-height: 1.6;
  margin: 0;
}

.adv-btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 46px;
  padding: 0 24px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, var(--green) 0%, var(--green-deep) 100%);
  color: #fff;
  font-size: 0.92rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(var(--green-rgb), 0.3);
  transition: transform 150ms ease, opacity 150ms ease;
}

.adv-btn-primary:hover {
  transform: translateY(-1px);
  opacity: 0.95;
}

.adv-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.adv-btn-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 42px;
  padding: 0 20px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms ease;
  width: 100%;
}

.adv-btn-outline:hover {
  background: #f9fafb;
}

/* AI sections output */
.adv-ai-section {
  display: grid;
  gap: 8px;
}

.adv-ai-section-title {
  font-size: 0.95rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--muted);
  margin: 0;
}

.adv-ai-text {
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--text);
  margin: 0;
}

.adv-two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.adv-col-box {
  padding: 16px;
  border-radius: 12px;
}

.bg-green-soft { background: var(--green-soft); }
.bg-red-soft { background: var(--red-soft); }

.adv-col-title {
  font-size: 0.9rem;
  font-weight: 750;
  margin: 0 0 10px;
}

.text-green-dark { color: var(--green-deep); }
.text-red-dark { color: #991b1b; }

.adv-ai-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}

.adv-ai-list li {
  font-size: 0.88rem;
  line-height: 1.5;
  position: relative;
  padding-left: 14px;
}

.adv-ai-list li::before {
  content: '•';
  position: absolute;
  left: 0;
  color: inherit;
}

.adv-ai-list-bullet {
  list-style-type: decimal;
  padding-left: 18px;
  margin: 0;
  display: grid;
  gap: 8px;
}

.adv-ai-list-bullet li {
  font-size: 0.92rem;
  line-height: 1.6;
  color: var(--text);
}

/* Action plan list */
.adv-plan-list {
  display: grid;
  gap: 12px;
}

.adv-plan-item {
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 16px;
  background: #fafafa;
}

.adv-plan-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.adv-plan-badge {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 6px;
}

.priority-high { background: #fee2e2; color: #ef4444; }
.priority-medium { background: #fef3c7; color: #d97706; }
.priority-low { background: #d1fae5; color: #10b981; }

.adv-plan-desc {
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--muted);
  margin: 0;
}

/* Motivation */
.adv-motivation {
  background: rgba(var(--green-rgb), 0.03);
  border-left: 3px solid var(--green);
  padding: 16px;
  border-radius: 0 12px 12px 0;
  text-align: center;
}

.adv-motivation p {
  margin: 0;
  font-size: 0.95rem;
  color: var(--green-deep);
  font-weight: 500;
}

.adv-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.adv-chip {
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 0.84rem;
  color: var(--text);
  font-weight: 500;
}

.adv-empty-state {
  text-align: center;
  padding: 24px 0;
}

.adv-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(var(--green-rgb), 0.15);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 12px;
}

.adv-spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  display: inline-block;
}

.adv-loading-wrap {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  color: var(--muted);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 1024px) {
  .adv-layout {
    grid-template-columns: 1fr;
  }
  .adv-stats-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 640px) {
  .adv-stats-grid {
    grid-template-columns: 1fr;
  }
  .adv-two-col {
    grid-template-columns: 1fr;
  }
}
</style>
