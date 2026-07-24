<template>
  <div class="car-page">
      <input ref="fileInput" type="file" class="car-file-hidden" accept=".pdf,.doc,.docx" @change="handleFileUpload">

      <!-- STATE 1: Loading -->
      <div v-if="initialLoading" class="car-loading-wrap">
        <div class="car-spinner-ring" />
        <p class="car-loading-text">Đang tải...</p>
      </div>

      <template v-else>
        <!-- HERO -->
        <header class="car-hero">
          <div class="car-hero-grid-overlay" />
          <div class="car-hero-glow car-hero-glow--left" />
          <div class="car-hero-glow car-hero-glow--right" />
          <div class="car-hero-inner">
            <div class="car-hero-left">
              <div class="car-badge">
                <span class="car-badge-dot" />
                AI Career Advisor
              </div>
              <h1 class="car-hero-title">Định hướng sự nghiệp</h1>
              <p class="car-hero-lead">
                Phân tích CV, gợi ý kỹ năng cần bổ sung và xây dựng lộ trình học tập theo mục tiêu nghề nghiệp của bạn.
              </p>
            </div>
            <div v-if="cvData" class="car-hero-right">
              <button
                class="car-btn-outline"
                type="button"
                :disabled="uploading"
                @click="openFilePicker"
              >
                <i class="pi pi-refresh" style="font-size:1.0rem" />
                <span>{{ uploading ? 'Đang xử lý...' : 'Cập nhật CV' }}</span>
              </button>
            </div>
          </div>
        </header>

        <!-- STATE 2: No CV — dropzone -->
        <div v-if="!cvData" class="car-upload-wrap">
          <div class="car-upload-grid">
            <!-- Dropzone -->
            <div
              class="car-dropzone"
              :class="{ 'car-dropzone--drag': isDragging, 'car-dropzone--busy': uploading }"
              role="button"
              tabindex="0"
              @click="!uploading && openFilePicker()"
              @keyup.enter="!uploading && openFilePicker()"
              @dragover.prevent="onDragOver"
              @dragleave.prevent="onDragLeave"
              @drop.prevent="onDrop"
            >
              <template v-if="uploading">
                <div class="car-upload-spinner"><span class="car-spinner-ring" /></div>
                <strong class="car-dropzone-title">Đang phân tích hồ sơ...</strong>
                <p class="car-dropzone-desc">AI đang đọc và trích xuất kỹ năng từ CV của bạn. Vui lòng đợi trong giây lát.</p>
              </template>
              <template v-else>
                <div class="car-icon-circle car-icon-circle--lg">
                  <i class="pi pi-cloud-upload" style="font-size:2.0rem" />
                </div>
                <strong class="car-dropzone-title">{{ isDragging ? 'Thả tệp để tải lên' : 'Kéo & thả CV vào đây' }}</strong>
                <p class="car-dropzone-desc">hoặc bấm để chọn tệp từ máy của bạn</p>
                <button class="car-btn-primary car-btn-primary--auto" type="button" @click.stop="openFilePicker">
                  <i class="pi pi-upload" style="font-size:1.0rem" />
                  <span>Chọn tệp CV</span>
                </button>
                <div class="car-format-row">
                  <span class="car-format-chip">PDF</span>
                  <span class="car-format-chip">DOC</span>
                  <span class="car-format-chip">DOCX</span>
                  <span class="car-format-hint">Tối đa 10MB</span>
                </div>
              </template>
            </div>

            <!-- What AI will do -->
            <aside class="car-upload-side">
              <p class="car-label">Sau khi tải lên</p>
              <h3 class="car-upload-side-title">AI sẽ giúp bạn</h3>
              <ul class="car-feature-list">
                <li v-for="feat in uploadFeatures" :key="feat.text">
                  <span class="car-feature-icon"><i :class="`pi pi-${feat.icon}`" style="font-size:1.125rem" /></span>
                  <span>{{ feat.text }}</span>
                </li>
              </ul>
              <div class="car-privacy-note">
                <i class="pi pi-shield" style="font-size:0.9375rem" />
                <span>CV của bạn được bảo mật và chỉ dùng để phân tích cá nhân hóa.</span>
              </div>
            </aside>
          </div>
        </div>

        <!-- STATE 3: Has CV -->
        <div v-else class="car-container">
          <div class="car-shell">
            <!-- SIDEBAR -->
            <aside class="car-sidebar">
              <!-- CV Card -->
              <div class="car-card car-cv-card">
                <p class="car-label">CV hiện tại</p>
                <div class="car-cv-file">
                  <div class="car-icon-circle">
                    <i class="pi pi-file" style="font-size:1.25rem" />
                  </div>
                  <div class="car-cv-meta">
                    <strong :title="cvData.file_name" class="car-cv-name">{{ cvData.file_name }}</strong>
                    <span class="car-muted">Cập nhật: {{ formatDate(cvData.created_at) }}</span>
                  </div>
                </div>
                <div v-if="cvData.skills?.length" class="car-chips">
                  <span v-for="skill in cvData.skills" :key="skill" class="car-skill-chip">{{ skill }}</span>
                </div>
              </div>

              <!-- Target Job Card -->
              <div class="car-card car-target-card">
                <p class="car-label">Mục tiêu nghề nghiệp</p>
                <input
                  v-model="targetJob"
                  type="text"
                  class="car-input"
                  placeholder="Backend Developer, Tech Lead..."
                  @keyup.enter="getRecommendations"
                >
                <button
                  class="car-btn-primary"
                  type="button"
                  :disabled="loadingRecommendations || !targetJob.trim()"
                  @click="getRecommendations"
                >
                  <span v-if="loadingRecommendations" class="car-spinner-sm" />
                  <i v-else class="pi pi-sparkles" style="font-size:1rem" />
                  <span>{{ loadingRecommendations ? 'Đang phân tích...' : 'Phân tích với AI' }}</span>
                </button>
              </div>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="car-main">
              <template v-if="analysis">
                <!-- 1. Overview Card -->
                <div class="car-card car-overview">
                  <div class="car-overview-content">
                    <p class="car-label">Đánh giá tổng quan</p>
                    <h2 class="car-overview-title">
                      Mức độ phù hợp với
                      <span class="car-target-tag">{{ analysis.target_job || targetJob || 'mục tiêu' }}</span>
                    </h2>
                    <p class="car-overview-text">
                      {{ expertAnalysis.overview || analysis.ai_summary || 'AI đã hoàn tất phân tích. Xem chi tiết bên dưới để biết điểm mạnh, khoảng trống và lộ trình học tập đề xuất.' }}
                    </p>
                    <span class="car-badge car-badge--sm">
                      <i class="pi pi-arrow-up" style="font-size:0.75rem" />
                      AI Recommendation
                    </span>
                  </div>
                  <div class="car-ring" :style="{ '--progress': `${matchScore}%` }">
                    <div class="car-ring-inner">
                      <strong>{{ matchScore }}%</strong>
                      <span>Phù hợp</span>
                    </div>
                  </div>
                </div>

                <!-- 2. Stats Strip -->
                <div class="car-stats">
                  <div class="car-stat-card">
                    <p class="car-label">Điểm phù hợp</p>
                    <strong class="car-stat-num">{{ matchScore }}%</strong>
                    <span class="car-muted">So với mục tiêu</span>
                  </div>
                  <div class="car-stat-card">
                    <p class="car-label">Điểm mạnh</p>
                    <strong class="car-stat-num">{{ expertAnalysis.strengths.length }}</strong>
                    <span class="car-muted">AI nhận diện</span>
                  </div>
                  <div class="car-stat-card">
                    <p class="car-label">Cần cải thiện</p>
                    <strong class="car-stat-num">{{ expertAnalysis.weaknesses.length }}</strong>
                    <span class="car-muted">Điểm yếu</span>
                  </div>
                  <div class="car-stat-card">
                    <p class="car-label">Skill gaps</p>
                    <strong class="car-stat-num">{{ analysis.skill_gaps?.length || 0 }}</strong>
                    <span class="car-muted">Kỹ năng cần bổ sung</span>
                  </div>
                </div>

                <!-- 3. Skill Coverage Card -->
                <div class="car-card">
                  <p class="car-label">Bản đồ kỹ năng</p>
                  <h2 class="car-section-title">Mức bao phủ kỹ năng & sẵn sàng</h2>
                  <p class="car-section-desc">Tổng hợp chỉ số sẵn sàng và mức bao phủ trên các nhóm kỹ năng cốt lõi.</p>
                  <div class="car-skill-grid">
                    <div v-for="item in skillCoverage" :key="item.label" class="car-skill-row">
                      <div class="car-skill-head">
                        <strong>{{ item.label }}</strong>
                        <span>{{ item.value }}%</span>
                      </div>
                      <div class="car-skill-track">
                        <span class="car-skill-fill" :style="{ width: `${item.value}%` }" />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 4. Strengths + Weaknesses -->
                <div class="car-two-col">
                  <div class="car-card">
                    <p class="car-label">Điểm mạnh</p>
                    <ul v-if="expertAnalysis.strengths.length" class="car-list car-list--positive">
                      <li v-for="item in expertAnalysis.strengths" :key="item">
                        <i class="pi pi-check-circle" style="font-size:1.125rem" />
                        <span>{{ item }}</span>
                      </li>
                    </ul>
                    <p v-else class="car-muted">AI chưa phát hiện điểm mạnh nổi bật từ CV.</p>
                  </div>

                  <div class="car-card">
                    <p class="car-label">Cần cải thiện</p>
                    <ul v-if="expertAnalysis.weaknesses.length" class="car-list car-list--warning">
                      <li v-for="item in expertAnalysis.weaknesses" :key="item">
                        <i class="pi pi-exclamation-circle" style="font-size:1.125rem" />
                        <span>{{ item }}</span>
                      </li>
                    </ul>
                    <p v-else class="car-muted">Không có điểm yếu rõ rệt — tiếp tục duy trì.</p>

                    <div v-if="analysis.skill_gaps?.length" class="car-gaps">
                      <p class="car-label">Skill gaps</p>
                      <div class="car-chips">
                        <span v-for="gap in analysis.skill_gaps" :key="gap" class="car-gap-chip">{{ gap }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 5. Suggested Courses -->
                <div class="car-card">
                  <p class="car-label">Lộ trình học tập</p>
                  <h2 class="car-section-title">Khóa học đề xuất</h2>
                  <p class="car-section-desc">Khóa học được chọn dựa trên khoảng trống kỹ năng và mục tiêu nghề nghiệp của bạn.</p>
                  <div v-if="analysis.suggested_courses_data?.length" class="car-course-grid">
                    <NuxtLink
                      v-for="course in analysis.suggested_courses_data"
                      :key="course.id"
                      :to="`/courses/${course.id}`"
                      class="car-course-card"
                    >
                      <div class="car-course-thumb">
                        <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                        <i v-else class="pi pi-book" style="font-size:2rem;color:var(--green-deep)" />
                      </div>
                      <div class="car-course-body">
                        <strong class="car-course-title">{{ course.title }}</strong>
                        <p class="car-muted">{{ course.instructor?.name || 'PTIT LMS' }}</p>
                        <p class="car-course-reason">{{ course.recommendation_reason || 'Khóa học phù hợp để lấp khoảng trống kỹ năng hiện tại.' }}</p>
                      </div>
                      <i class="pi pi-chevron-right" style="font-size:1.125rem" />
                    </NuxtLink>
                  </div>
                  <div v-else class="car-empty-state">
                    <div class="car-icon-circle car-icon-circle--lg">
                      <i class="pi pi-book" style="font-size:1.75rem" />
                    </div>
                    <strong>Chưa có khuyến nghị khóa học</strong>
                    <p class="car-muted">Hãy thử cập nhật mục tiêu nghề nghiệp để hệ thống gợi ý chính xác hơn.</p>
                  </div>
                </div>
              </template>

              <!-- No analysis yet -->
              <div v-else class="car-card car-empty-state">
                <div class="car-icon-circle car-icon-circle--lg">
                  <i class="pi pi-circle" style="font-size:1.75rem" />
                </div>
                <strong class="car-empty-title">Nhập mục tiêu của bạn</strong>
                <p class="car-muted">Ví dụ: Frontend Developer, Data Analyst hoặc Project Manager để AI bắt đầu tư vấn.</p>
              </div>
            </main>
          </div>
        </div>
      </template>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const cvData = ref<any>(null)
const analysis = ref<any>(null)
const targetJob = ref('')
const initialLoading = ref(true)
const uploading = ref(false)
const loadingRecommendations = ref(false)
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const uploadFeatures = [
  { icon: 'search', text: 'Trích xuất & phân tích kỹ năng từ CV' },
  { icon: 'circle', text: 'Đánh giá mức độ phù hợp với mục tiêu nghề nghiệp' },
  { icon: 'directions', text: 'Xác định khoảng trống kỹ năng cần bổ sung' },
  { icon: 'graduation-cap', text: 'Gợi ý lộ trình & khóa học cá nhân hóa' },
]

const matchScore = computed(() => {
  const raw = Number(analysis.value?.match_score || 0)
  return Math.max(0, Math.min(100, Math.round(raw)))
})

const expertAnalysis = computed(() => ({
  overview: analysis.value?.expert_analysis?.overview || '',
  strengths: analysis.value?.expert_analysis?.strengths || [],
  weaknesses: analysis.value?.expert_analysis?.weaknesses || [],
}))

const skillCoverage = computed(() => {
  const skills = cvData.value?.skills?.length || 0
  const score = matchScore.value
  const base = [
    { label: 'Kỹ thuật chuyên môn', baseValue: 64 },
    { label: 'Giải quyết vấn đề', baseValue: 60 },
    { label: 'Làm việc nhóm', baseValue: 70 },
    { label: 'Sẵn sàng thăng tiến', baseValue: Math.max(40, score) },
  ]
  return base.map((item, index) => {
    const boost = Math.min(skills * (index + 1), 14)
    return { label: item.label, value: Math.min(96, item.baseValue + boost) }
  })
})

const formatDate = (date: string) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const openFilePicker = () => {
  fileInput.value?.click()
}

const loadInitialData = async () => {
  try {
    const data = await useApi<any>('/career/advisor', { token: auth.token })
    cvData.value = data.cv
    if (data.recommendations?.length > 0) {
      analysis.value = data.recommendations[0]
      targetJob.value = data.recommendations[0]?.target_job || ''
    }
  } catch (err) {
    console.error('Failed to load career advisor data', err)
  } finally {
    initialLoading.value = false
  }
}

const ALLOWED_EXT = ['pdf', 'doc', 'docx']
const MAX_SIZE = 10 * 1024 * 1024

const uploadCv = async (file: 'file') => {
  if (uploading.value) return
  const ext = file.name.split('.').pop()?.toLowerCase() || ''
  if (!ALLOWED_EXT.includes(ext)) {
    alert('Định dạng không hỗ trợ. Vui lòng chọn tệp PDF, DOC hoặc DOCX.')
    return
  }
  if (file.size > MAX_SIZE) {
    alert('Tệp quá lớn. Kích thước tối đa là 10MB.')
    return
  }
  const formData = new FormData()
  formData.append('cv', file)
  uploading.value = true
  try {
    const res = await useApi<any>('/career/upload-cv', { method: 'POST', body: formData, token: auth.token })
    cvData.value = res.cv
    analysis.value = null
  } catch {
    alert('Không thể tải CV lên. Vui lòng thử lại.')
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) uploadCv(file)
}

const onDragOver = () => {
  if (!uploading.value) isDragging.value = true
}

const onDragLeave = () => {
  isDragging.value = false
}

const onDrop = (event: DragEvent) => {
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) uploadCv(file)
}

const getRecommendations = async () => {
  const job = targetJob.value.trim()
  if (!job || loadingRecommendations.value) return
  loadingRecommendations.value = true
  try {
    const res = await useApi<any>('/career/recommend', {
      method: 'POST',
      body: { job_title: job },
      token: auth.token,
    })
    analysis.value = res.recommendation
  } catch {
    alert('Có lỗi xảy ra trong quá trình phân tích AI.')
  } finally {
    loadingRecommendations.value = false
  }
}

onMounted(loadInitialData)
</script>

<style scoped>
/* ── Base ── */
.car-page {
  font-family: 'Be Vietnam Pro', sans-serif;
  min-height: 100vh;
  --green: #1d9e75;
  --green-rgb: 29, 158, 117;
  --green-deep: #14785a;
  --green-soft: rgba(29, 158, 117, 0.08);
  --text: #111;
  --muted: #6b7280;
  --surface-strong: #FFFFFF;
  --line: #e5e7eb;
}

.car-file-hidden {
  display: none;
}

/* ── Loading State ── */
.car-loading-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  min-height: 60vh;
}

.car-spinner-ring {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 4px solid rgba(var(--green-rgb), 0.15);
  border-top-color: var(--green);
  animation: car-spin 0.9s linear infinite;
}

.car-spinner-sm {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.35);
  border-top-color: #fff;
  animation: car-spin 0.8s linear infinite;
  flex-shrink: 0;
}

@keyframes car-spin {
  to { transform: rotate(360deg); }
}

.car-loading-text {
  color: var(--muted);
  font-size: 0.95rem;
}

/* ── Hero ── */
.car-hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 50%, #163d2a 100%);
  padding: 80px 0;
}

.car-hero-grid-overlay {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

.car-hero-glow {
  position: absolute;
  width: 480px;
  height: 480px;
  border-radius: 50%;
  filter: blur(120px);
  pointer-events: none;
  opacity: 0.25;
}

.car-hero-glow--left {
  background: radial-gradient(circle, rgba(var(--green-rgb), 0.6) 0%, transparent 70%);
  top: -120px;
  left: -80px;
}

.car-hero-glow--right {
  background: radial-gradient(circle, rgba(var(--green-rgb), 0.3) 0%, transparent 70%);
  bottom: -160px;
  right: -100px;
}

.car-hero-inner {
  position: relative;
  z-index: 1;
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.car-hero-left {
  display: grid;
  gap: 14px;
}

.car-hero-title {
  margin: 0;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 800;
  color: #fff;
  line-height: 1.15;
}

.car-hero-lead {
  margin: 0;
  color: rgba(255, 255, 255, 0.65);
  font-size: 1.05rem;
  max-width: 560px;
  line-height: 1.65;
}

/* ── Badge ── */
.car-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 14px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.18);
  border: 1px solid rgba(var(--green-rgb), 0.35);
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.4px;
  width: fit-content;
}

.car-badge--sm {
  background: rgba(var(--green-rgb), 0.12);
  border: 1px solid rgba(var(--green-rgb), 0.25);
  color: var(--green-deep);
  font-size: 0.74rem;
}

.car-badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--green);
  animation: car-pulse 2s ease-in-out infinite;
}

@keyframes car-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.75); }
}

/* ── Buttons ── */
.car-btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  padding: 0 24px;
  border-radius: 14px;
  border: none;
  background: linear-gradient(135deg, var(--green) 0%, var(--green-deep) 100%);
  color: #fff;
  font-size: 0.92rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-shadow: 0 8px 20px -8px rgba(var(--green-rgb), 0.6);
  transition: opacity 160ms ease, transform 160ms ease, box-shadow 160ms ease;
  width: 100%;
}

.car-btn-primary:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
  box-shadow: 0 12px 24px -8px rgba(var(--green-rgb), 0.65);
}

.car-btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

.car-btn-outline {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 42px;
  padding: 0 20px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.88rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background 160ms ease, border-color 160ms ease;
  backdrop-filter: blur(8px);
}

.car-btn-outline:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.14);
  border-color: rgba(255, 255, 255, 0.4);
}

.car-btn-outline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Upload / Dropzone ── */
.car-upload-wrap {
  max-width: 1080px;
  margin: 56px auto 72px;
  padding: 0 24px;
}

.car-upload-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 24px;
  align-items: stretch;
}

.car-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  text-align: center;
  padding: 56px 40px;
  background: #fff;
  border: 2px dashed rgba(var(--green-rgb), 0.3);
  border-radius: 20px;
  cursor: pointer;
  transition: border-color 180ms ease, background-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}

.car-dropzone:hover {
  border-color: rgba(var(--green-rgb), 0.55);
  background: rgba(var(--green-rgb), 0.03);
}

.car-dropzone:focus-visible {
  outline: none;
  border-color: var(--green);
  box-shadow: 0 0 0 4px rgba(var(--green-rgb), 0.15);
}

.car-dropzone--drag {
  border-color: var(--green);
  border-style: solid;
  background: rgba(var(--green-rgb), 0.06);
  transform: scale(1.005);
  box-shadow: 0 18px 40px -22px rgba(var(--green-rgb), 0.5);
}

.car-dropzone--busy {
  cursor: progress;
  border-style: solid;
  border-color: rgba(var(--green-rgb), 0.4);
}

.car-dropzone-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text);
}

.car-dropzone-desc {
  margin: 0;
  color: var(--muted);
  max-width: 420px;
  line-height: 1.6;
}

.car-upload-spinner {
  display: grid;
  place-items: center;
  width: 80px;
  height: 80px;
}

.car-btn-primary--auto {
  width: auto;
  margin-top: 6px;
}

/* ── Format chips ── */
.car-format-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 12px;
}

.car-format-chip {
  padding: 4px 12px;
  border-radius: 8px;
  background: rgba(17, 17, 17, 0.05);
  border: 1px solid var(--line);
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.4px;
}

.car-format-hint {
  color: var(--muted);
  font-size: 0.78rem;
}

/* ── Upload side panel ── */
.car-upload-side {
  background: linear-gradient(160deg, #0d2e1e 0%, #163d2a 100%);
  border-radius: 20px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  color: #fff;
}

.car-upload-side .car-label {
  color: rgba(255, 255, 255, 0.55);
}

.car-upload-side-title {
  margin: 0 0 12px;
  font-size: 1.2rem;
  font-weight: 700;
  color: #fff;
}

.car-feature-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 14px;
  flex: 1;
}

.car-feature-list li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 0.9rem;
  line-height: 1.5;
  color: rgba(255, 255, 255, 0.85);
}

.car-feature-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  border-radius: 10px;
  background: rgba(var(--green-rgb), 0.22);
  color: #fff;
}

.car-privacy-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 20px;
  padding-top: 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.8rem;
  line-height: 1.5;
}

.car-privacy-note svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: var(--green);
}

/* ── Icon Circles ── */
.car-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(var(--green-rgb), 0.12);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.car-icon-circle--lg {
  width: 80px;
  height: 80px;
}

/* ── Container & Shell ── */
.car-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 40px 32px 80px;
}

.car-shell {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 28px;
  align-items: start;
}

/* ── Sidebar ── */
.car-sidebar {
  position: sticky;
  top: 24px;
  display: grid;
  gap: 16px;
}

.car-cv-card {
  display: grid;
  gap: 14px;
}

.car-cv-file {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  background: rgba(17, 17, 17, 0.04);
}

.car-cv-meta {
  min-width: 0;
  display: grid;
  gap: 3px;
}

.car-cv-name {
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.9rem;
}

.car-target-card {
  display: grid;
  gap: 12px;
}

/* ── Input ── */
.car-input {
  width: 100%;
  min-height: 48px;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 0 14px;
  background: #fff;
  font-family: inherit;
  font-size: 0.92rem;
  color: var(--text);
  transition: border-color 160ms ease, box-shadow 160ms ease;
  box-sizing: border-box;
}

.car-input:focus {
  outline: none;
  border-color: rgba(var(--green-rgb), 0.5);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.12);
}

.car-input::placeholder {
  color: var(--muted);
}

/* ── Cards ── */
.car-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 28px;
}

/* ── Labels & Typography ── */
.car-label {
  margin: 0 0 4px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  color: var(--muted);
}

.car-muted {
  color: var(--muted);
  font-size: 0.86rem;
  margin: 0;
}

.car-section-title {
  margin: 6px 0 8px;
  font-size: 1.25rem;
  font-weight: 700;
}

.car-section-desc {
  margin: 0 0 20px;
  color: var(--muted);
  font-size: 0.9rem;
}

/* ── Overview Card ── */
.car-overview {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 28px;
}

.car-overview-content {
  display: grid;
  gap: 10px;
}

.car-overview-title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.4;
}

.car-overview-text {
  margin: 0;
  color: var(--muted);
  line-height: 1.65;
  font-size: 0.95rem;
}

.car-target-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 10px;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  font-weight: 700;
}

/* ── Progress Ring ── */
.car-ring {
  --progress: 0%;
  position: relative;
  width: 168px;
  height: 168px;
  border-radius: 50%;
  background: conic-gradient(var(--green) 0 var(--progress), rgba(17, 17, 17, 0.08) var(--progress) 100%);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.car-ring-inner {
  width: 78%;
  height: 78%;
  border-radius: 50%;
  background: #fff;
  display: grid;
  place-items: center;
  text-align: center;
  box-shadow: inset 0 0 0 1px rgba(17, 17, 17, 0.04);
}

.car-ring-inner strong {
  display: block;
  font-size: 1.8rem;
  line-height: 1;
  font-weight: 800;
  color: var(--text);
}

.car-ring-inner span {
  display: block;
  margin-top: 4px;
  color: var(--muted);
  font-size: 0.8rem;
}

/* ── Stats Strip ── */
.car-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.car-stat-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 24px 20px;
  display: grid;
  gap: 4px;
}

.car-stat-num {
  font-size: 1.75rem;
  font-weight: 800;
  color: var(--text);
  line-height: 1;
}

/* ── Skill Grid ── */
.car-skill-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px 28px;
}

.car-skill-row {
  display: grid;
  gap: 8px;
}

.car-skill-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.car-skill-head strong {
  font-weight: 600;
  font-size: 0.9rem;
}

.car-skill-head span {
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 700;
}

.car-skill-track {
  height: 8px;
  border-radius: 999px;
  background: rgba(17, 17, 17, 0.07);
  overflow: hidden;
}

.car-skill-fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, var(--green) 0%, var(--green-deep) 100%);
  transition: width 600ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Two Column ── */
.car-two-col {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

/* ── Lists ── */
.car-list {
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 12px;
}

.car-list li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  line-height: 1.55;
  font-size: 0.92rem;
}

.car-list-icon {
  margin-top: 1px;
  flex-shrink: 0;
}

.car-list-icon--green {
  color: var(--green-deep);
}

.car-list-icon--amber {
  color: #b45309;
}

/* ── Gaps ── */
.car-gaps {
  margin-top: 20px;
  display: grid;
  gap: 10px;
}

/* ── Chips ── */
.car-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.car-skill-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.1);
  border: 1px solid rgba(var(--green-rgb), 0.2);
  color: var(--green-deep);
  font-size: 0.78rem;
  font-weight: 600;
}

.car-gap-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background: rgba(220, 38, 38, 0.08);
  border: 1px solid rgba(220, 38, 38, 0.2);
  color: #b91c1c;
  font-size: 0.78rem;
  font-weight: 600;
}

/* ── Courses ── */
.car-course-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.car-course-card {
  display: flex;
  gap: 14px;
  padding: 16px;
  border-radius: 16px;
  background: rgba(17, 17, 17, 0.03);
  border: 1px solid transparent;
  text-decoration: none;
  color: inherit;
  transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease;
  align-items: flex-start;
}

.car-course-card:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--green-rgb), 0.25);
  background: rgba(var(--green-rgb), 0.05);
}

.car-course-thumb {
  width: 96px;
  height: 96px;
  border-radius: 12px;
  flex-shrink: 0;
  overflow: hidden;
  background: rgba(var(--green-rgb), 0.1);
  display: grid;
  place-items: center;
}

.car-course-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.car-course-body {
  display: grid;
  gap: 4px;
  min-width: 0;
  flex: 1;
}

.car-course-title {
  font-size: 0.95rem;
  font-weight: 700;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  color: var(--text);
}

.car-course-reason {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.84rem;
  line-height: 1.5;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.car-course-arrow {
  color: var(--muted);
  flex-shrink: 0;
  margin-top: 2px;
  transition: color 160ms ease;
}

.car-course-card:hover .car-course-arrow {
  color: var(--green-deep);
}

/* ── Empty State ── */
.car-empty-state {
  display: grid;
  place-items: center;
  gap: 12px;
  text-align: center;
  padding: 48px 24px;
}

.car-empty-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text);
}

/* ── Main Section Spacing ── */
.car-main {
  display: grid;
  gap: 20px;
}

/* ── Dark Mode ── */
[data-theme="dark"] .car-card,
[data-theme="dark"] .car-stat-card {
  background: var(--surface-strong);
  border-color: var(--line);
}

[data-theme="dark"] .car-ring-inner {
  background: var(--surface-strong);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
}

[data-theme="dark"] .car-ring {
  background: conic-gradient(var(--green) 0 var(--progress), rgba(255, 255, 255, 0.08) var(--progress) 100%);
}

[data-theme="dark"] .car-cv-file {
  background: rgba(255, 255, 255, 0.04);
}

[data-theme="dark"] .car-dropzone {
  background: var(--surface-strong);
  border-color: rgba(var(--green-rgb), 0.35);
}

[data-theme="dark"] .car-dropzone:hover {
  background: rgba(var(--green-rgb), 0.06);
}

[data-theme="dark"] .car-format-chip {
  background: rgba(255, 255, 255, 0.06);
  border-color: var(--line);
}

[data-theme="dark"] .car-input {
  background: rgba(255, 255, 255, 0.05);
  border-color: var(--line);
  color: var(--text);
}

[data-theme="dark"] .car-input::placeholder {
  color: var(--muted);
}

[data-theme="dark"] .car-skill-track {
  background: rgba(255, 255, 255, 0.07);
}

[data-theme="dark"] .car-course-card {
  background: rgba(255, 255, 255, 0.03);
}

[data-theme="dark"] .car-course-card:hover {
  background: rgba(var(--green-rgb), 0.08);
}

[data-theme="dark"] .car-list-icon--amber {
  color: #fbbf24;
}

[data-theme="dark"] .car-gap-chip {
  color: #f87171;
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.18);
}

/* ── Responsive ── */
@media (max-width: 1000px) {
  .car-shell {
    grid-template-columns: 1fr;
  }

  .car-sidebar {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    display: grid;
  }

  .car-overview {
    grid-template-columns: 1fr;
    text-align: left;
  }

  .car-ring {
    justify-self: center;
  }

  .car-skill-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .car-hero {
    padding: 56px 0;
  }

  .car-hero-inner {
    flex-direction: column;
    align-items: flex-start;
    padding: 0 20px;
  }

  .car-container {
    padding: 28px 20px 60px;
  }

  .car-upload-grid {
    grid-template-columns: 1fr;
  }

  .car-dropzone {
    padding: 44px 24px;
  }

  .car-sidebar {
    grid-template-columns: 1fr;
  }

  .car-two-col {
    grid-template-columns: 1fr;
  }

  .car-course-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .car-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .car-ring {
    width: 144px;
    height: 144px;
  }

  .car-ring-inner strong {
    font-size: 1.5rem;
  }

  .car-course-card {
    flex-direction: column;
  }

  .car-course-thumb {
    width: 100%;
    height: 140px;
  }
}
</style>
