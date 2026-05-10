<template>
  <NuxtLayout name="default">
    <div class="account-shell">
      <input ref="fileInput" type="file" class="hidden" accept=".pdf,.doc,.docx" @change="handleFileUpload">

      <section class="account-wrap">
        <header class="account-hero">
          <div>
            <p class="account-eyebrow">AI Career Advisor</p>
            <h1>Định hướng sự nghiệp</h1>
            <p class="account-lead">
              Phân tích CV, gợi ý kỹ năng cần bổ sung và xây dựng lộ trình học tập theo mục tiêu nghề nghiệp của bạn.
            </p>
          </div>
          <button
            v-if="cvData"
            class="account-cta"
            type="button"
            :disabled="uploading"
            @click="openFilePicker"
          >
            <span class="material-symbols-outlined">refresh</span>
            <span>{{ uploading ? 'Đang xử lý...' : 'Cập nhật CV' }}</span>
          </button>
        </header>

        <section v-if="initialLoading" class="account-card career-status">
          <div class="career-spinner" />
          <p>Đang tải dữ liệu định hướng sự nghiệp...</p>
        </section>

        <section v-else-if="!cvData" class="account-card career-dropzone">
          <div class="career-dropzone-icon">
            <span class="material-symbols-outlined">cloud_upload</span>
          </div>
          <strong>{{ uploading ? 'Đang phân tích hồ sơ...' : 'Tải CV để bắt đầu' }}</strong>
          <p>Hỗ trợ PDF, DOC, DOCX. Hệ thống sẽ tự động trích xuất kỹ năng và chuẩn bị khuyến nghị cá nhân hóa.</p>
          <button class="account-cta" type="button" :disabled="uploading" @click="openFilePicker">
            <span class="material-symbols-outlined">upload_file</span>
            <span>{{ uploading ? 'Đang tải...' : 'Chọn tệp CV' }}</span>
          </button>
        </section>

        <section v-else class="account-layout">
          <aside class="account-sidebar">
            <div class="account-card career-cv-card">
              <p class="account-section-label">CV hiện tại</p>
              <div class="career-cv-file">
                <div class="career-cv-icon">
                  <span class="material-symbols-outlined">description</span>
                </div>
                <div class="career-cv-meta">
                  <strong :title="cvData.file_name">{{ cvData.file_name }}</strong>
                  <span class="account-meta">Cập nhật: {{ formatDate(cvData.created_at) }}</span>
                </div>
              </div>
              <div v-if="cvData.skills?.length" class="career-skills">
                <span v-for="skill in cvData.skills" :key="skill" class="account-chip">{{ skill }}</span>
              </div>
            </div>

            <div class="account-card career-target">
              <p class="account-section-label">Mục tiêu nghề nghiệp</p>
              <input
                v-model="targetJob"
                type="text"
                class="career-input"
                placeholder="Ví dụ: Backend Developer, Tech Lead..."
                @keyup.enter="getRecommendations"
              >
              <button
                class="account-cta is-primary"
                type="button"
                :disabled="loadingRecommendations || !targetJob.trim()"
                @click="getRecommendations"
              >
                <span v-if="loadingRecommendations" class="career-spinner is-sm" />
                <span class="material-symbols-outlined" v-else>auto_awesome</span>
                <span>{{ loadingRecommendations ? 'Đang phân tích...' : 'Phân tích với AI' }}</span>
              </button>
            </div>
          </aside>

          <main class="account-main">
            <template v-if="analysis">
              <section class="account-card career-overview">
                <div class="career-overview-content">
                  <p class="account-section-label">Đánh giá tổng quan</p>
                  <h2>Mức độ phù hợp với <span class="career-target-tag">{{ analysis.target_job || targetJob || 'mục tiêu' }}</span></h2>
                  <p class="career-overview-text">{{ expertAnalysis.overview || analysis.ai_summary || 'AI đã hoàn tất phân tích. Xem chi tiết bên dưới để biết điểm mạnh, khoảng trống và lộ trình học tập đề xuất.' }}</p>
                  <span class="account-chip">AI Recommendation</span>
                </div>
                <div class="career-ring" :style="{ '--progress': `${matchScore}%` }">
                  <div class="career-ring-inner">
                    <strong>{{ matchScore }}%</strong>
                    <span>Phù hợp</span>
                  </div>
                </div>
              </section>

              <section class="account-summary career-summary">
                <article>
                  <p class="account-section-label">Điểm phù hợp</p>
                  <strong>{{ matchScore }}%</strong>
                  <span>So với mục tiêu</span>
                </article>
                <article>
                  <p class="account-section-label">Điểm mạnh</p>
                  <strong>{{ expertAnalysis.strengths.length }}</strong>
                  <span>AI nhận diện</span>
                </article>
                <article>
                  <p class="account-section-label">Cần cải thiện</p>
                  <strong>{{ expertAnalysis.weaknesses.length }}</strong>
                  <span>Điểm yếu</span>
                </article>
                <article>
                  <p class="account-section-label">Skill gaps</p>
                  <strong>{{ analysis.skill_gaps?.length || 0 }}</strong>
                  <span>Kỹ năng cần bổ sung</span>
                </article>
              </section>

              <section class="account-card">
                <div class="account-main-head">
                  <div>
                    <p class="account-section-label">Bản đồ kỹ năng</p>
                    <h2>Mức bao phủ kỹ năng & sẵn sàng</h2>
                    <p>Tổng hợp chỉ số sẵn sàng và mức bao phủ trên các nhóm kỹ năng cốt lõi.</p>
                  </div>
                </div>
                <div class="career-skill-grid">
                  <div v-for="item in skillCoverage" :key="item.label" class="career-skill-row">
                    <div class="career-skill-head">
                      <strong>{{ item.label }}</strong>
                      <span>{{ item.value }}%</span>
                    </div>
                    <div class="progress-track">
                      <span class="progress-fill" :style="{ width: `${item.value}%` }" />
                    </div>
                  </div>
                </div>
              </section>

              <section class="account-grid two-col">
                <article class="account-card">
                  <p class="account-section-label">Điểm mạnh</p>
                  <ul v-if="expertAnalysis.strengths.length" class="career-list is-positive">
                    <li v-for="item in expertAnalysis.strengths" :key="item">
                      <span class="material-symbols-outlined">check_circle</span>
                      <span>{{ item }}</span>
                    </li>
                  </ul>
                  <p v-else class="account-meta">AI chưa phát hiện điểm mạnh nổi bật từ CV.</p>
                </article>

                <article class="account-card">
                  <p class="account-section-label">Cần cải thiện</p>
                  <ul v-if="expertAnalysis.weaknesses.length" class="career-list is-warning">
                    <li v-for="item in expertAnalysis.weaknesses" :key="item">
                      <span class="material-symbols-outlined">priority_high</span>
                      <span>{{ item }}</span>
                    </li>
                  </ul>
                  <p v-else class="account-meta">Không có điểm yếu rõ rệt — tiếp tục duy trì.</p>

                  <div v-if="analysis.skill_gaps?.length" class="career-gaps">
                    <p class="account-section-label">Skill gaps</p>
                    <div class="career-skills">
                      <span v-for="gap in analysis.skill_gaps" :key="gap" class="account-badge is-danger">{{ gap }}</span>
                    </div>
                  </div>
                </article>
              </section>

              <section class="account-card">
                <div class="account-main-head">
                  <div>
                    <p class="account-section-label">Lộ trình học tập</p>
                    <h2>Khóa học đề xuất</h2>
                    <p>Khóa học được chọn dựa trên khoảng trống kỹ năng và mục tiêu nghề nghiệp của bạn.</p>
                  </div>
                </div>
                <div v-if="analysis.suggested_courses_data?.length" class="account-grid two-col career-courses">
                  <NuxtLink
                    v-for="course in analysis.suggested_courses_data"
                    :key="course.id"
                    :to="`/courses/${course.id}`"
                    class="career-course-card"
                  >
                    <div class="career-course-thumb">
                      <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                      <span v-else class="material-symbols-outlined">menu_book</span>
                    </div>
                    <div class="career-course-body">
                      <strong>{{ course.title }}</strong>
                      <p class="account-meta">{{ course.instructor?.name || 'PTIT LMS' }}</p>
                      <p class="career-course-reason">{{ course.recommendation_reason || 'Khóa học phù hợp để lấp khoảng trống kỹ năng hiện tại.' }}</p>
                    </div>
                  </NuxtLink>
                </div>
                <div v-else class="career-status">
                  <span class="material-symbols-outlined">school</span>
                  <strong>Chưa có khuyến nghị khóa học</strong>
                  <p>Hãy thử cập nhật mục tiêu nghề nghiệp để hệ thống gợi ý chính xác hơn.</p>
                </div>
              </section>
            </template>

            <section v-else class="account-card career-status">
              <span class="material-symbols-outlined">target</span>
              <strong>Nhập mục tiêu của bạn</strong>
              <p>Ví dụ: Frontend Developer, Data Analyst hoặc Project Manager để AI bắt đầu tư vấn.</p>
            </section>
          </main>
        </section>
      </section>
    </div>
  </NuxtLayout>
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
const fileInput = ref<HTMLInputElement | null>(null)

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

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return
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
.account-cta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.account-cta:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.account-cta.is-primary {
  background: var(--green);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 12px 24px -16px rgba(var(--green-rgb), 0.7);
}
.account-cta.is-primary:disabled {
  box-shadow: none;
}

.career-status {
  display: grid;
  place-items: center;
  gap: 10px;
  text-align: center;
  padding: 36px 24px;
}
.career-status .material-symbols-outlined {
  font-size: 36px;
  color: var(--green-deep);
  background: rgba(var(--green-rgb), 0.1);
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: grid;
  place-items: center;
}
.career-status strong { font-size: 1.15rem; }
.career-status p { color: var(--muted); margin: 0; max-width: 420px; }

.career-dropzone {
  display: grid;
  place-items: center;
  gap: 12px;
  text-align: center;
  padding: 48px 28px;
  border: 1px dashed rgba(var(--green-rgb), 0.32);
}
.career-dropzone-icon {
  width: 72px;
  height: 72px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.1);
  display: grid;
  place-items: center;
  color: var(--green-deep);
}
.career-dropzone-icon .material-symbols-outlined { font-size: 36px; }
.career-dropzone strong { font-size: 1.2rem; }
.career-dropzone p { color: var(--muted); max-width: 460px; margin: 0 0 8px; }

.career-cv-card { display: grid; gap: 14px; }
.career-cv-file {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 18px;
  background: rgba(17, 17, 17, 0.04);
}
.career-cv-icon {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  flex-shrink: 0;
}
.career-cv-meta {
  min-width: 0;
  display: grid;
  gap: 2px;
}
.career-cv-meta strong {
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.career-skills { display: flex; flex-wrap: wrap; gap: 8px; }

.career-target { display: grid; gap: 12px; }
.career-input {
  width: 100%;
  min-height: 48px;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 0 14px;
  background: #fff;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}
.career-input:focus {
  outline: none;
  border-color: rgba(var(--green-rgb), 0.5);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.12);
}

.career-overview {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 28px;
}
.career-overview-content { display: grid; gap: 10px; }
.career-overview-content h2 { margin: 0; }
.career-overview-content .account-chip { justify-self: start; margin-top: 4px; }
.career-overview-text { color: var(--muted); margin: 0; }
.career-target-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 10px;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  font-weight: 700;
}

.career-ring {
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
.career-ring-inner {
  width: 78%;
  height: 78%;
  border-radius: 50%;
  background: #fff;
  display: grid;
  place-items: center;
  text-align: center;
  box-shadow: inset 0 0 0 1px rgba(17, 17, 17, 0.04);
}
.career-ring-inner strong {
  display: block;
  font-size: 1.8rem;
  line-height: 1;
  font-weight: 800;
}
.career-ring-inner span {
  display: block;
  margin-top: 4px;
  color: var(--muted);
  font-size: 0.8rem;
}

.career-summary article { display: grid; gap: 4px; }
.career-summary article strong { font-size: 1.6rem; }
.career-summary article span { color: var(--muted); font-size: 0.82rem; }

.career-skill-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px 24px;
  margin-top: 4px;
}
.career-skill-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.career-skill-head strong { font-weight: 600; }
.career-skill-head span { color: var(--muted); font-size: 0.85rem; font-weight: 700; }

.career-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 10px;
}
.career-list li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  color: var(--text);
  line-height: 1.55;
}
.career-list .material-symbols-outlined {
  font-size: 20px;
  margin-top: 1px;
  flex-shrink: 0;
}
.career-list.is-positive .material-symbols-outlined { color: var(--green-deep); }
.career-list.is-warning .material-symbols-outlined { color: #9a6117; }

.career-gaps { margin-top: 16px; display: grid; gap: 8px; }

.career-courses { gap: 14px; }
.career-course-card {
  display: flex;
  gap: 14px;
  padding: 14px;
  border-radius: 18px;
  background: rgba(17, 17, 17, 0.03);
  border: 1px solid transparent;
  transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease;
}
.career-course-card:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--green-rgb), 0.18);
  background: rgba(var(--green-rgb), 0.06);
}
.career-course-thumb {
  width: 96px;
  height: 96px;
  border-radius: 14px;
  flex-shrink: 0;
  overflow: hidden;
  background: rgba(var(--green-rgb), 0.1);
  display: grid;
  place-items: center;
  color: var(--green-deep);
}
.career-course-thumb img { width: 100%; height: 100%; object-fit: cover; }
.career-course-thumb .material-symbols-outlined { font-size: 32px; }
.career-course-body { display: grid; gap: 4px; min-width: 0; }
.career-course-body strong {
  font-size: 1rem;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}
.career-course-reason {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.86rem;
  line-height: 1.5;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.career-spinner {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 3px solid rgba(var(--green-rgb), 0.18);
  border-top-color: var(--green-deep);
  animation: career-spin 0.8s linear infinite;
}
.career-spinner.is-sm {
  width: 14px;
  height: 14px;
  border-width: 2px;
}
@keyframes career-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 1100px) {
  .career-overview { grid-template-columns: 1fr; text-align: left; }
  .career-ring { justify-self: center; }
  .career-skill-grid { grid-template-columns: 1fr; }
}

@media (max-width: 720px) {
  .career-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .career-ring { width: 144px; height: 144px; }
  .career-ring-inner strong { font-size: 1.5rem; }
  .career-course-card { flex-direction: column; }
  .career-course-thumb { width: 100%; height: 140px; }
}
</style>
