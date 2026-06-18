<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Trophy, Frown, CheckCircle2, XCircle, Clock, Hash, ChevronRight } from 'lucide-vue-next'

definePageMeta({ layout: 'default', middleware: 'auth' })

const route = useRoute()
const attemptId = route.params.attemptId as string
const examId = route.query.exam as string
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const result = ref<any>(null)

async function fetchResult() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>(`/exams/${examId}/results/${attemptId}`, { headers: authHeaders() })
    result.value = res
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải kết quả thi.'
  }
  finally { loading.value = false }
}

const isPassed = computed(() => {
  if (!result.value) return false
  const score = result.value.score ?? result.value.attempt?.score ?? 0
  const passScore = result.value.exam?.pass_score ?? result.value.pass_score ?? 80
  return score >= passScore
})

const scorePercent = computed(() => Math.round(Number(result.value?.score ?? result.value?.attempt?.score ?? 0)))
const passScore = computed(() => result.value?.exam?.pass_score ?? result.value?.pass_score ?? 80)
const examTitle = computed(() => result.value?.exam?.title || result.value?.attempt?.exam_title || 'Kỳ thi')
const answers = computed(() => result.value?.answers || result.value?.attempt?.answers || [])
const correctCount = computed(() => answers.value.filter((a: any) => a.is_correct).length)
const incorrectCount = computed(() => answers.value.filter((a: any) => !a.is_correct).length)
const timeSpent = computed(() => result.value?.attempt?.time_spent ?? result.value?.time_spent ?? 0)

const circumference = 2 * Math.PI * 50
const strokeDash = computed(() => `${(scorePercent.value / 100) * circumference} ${circumference}`)

function formatTime(seconds: number) {
  if (!seconds) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

onMounted(fetchResult)
</script>

<template>
  <div class="result-page">

    <!-- Loading -->
    <div v-if="loading" class="dashboard-card crud-empty" style="margin: 40px auto; max-width: 600px;">
      <span class="material-symbols-outlined" style="font-size: 36px; opacity: 0.3;">hourglass_empty</span>
      <p>Đang tải kết quả thi...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="dashboard-card crud-empty" style="margin: 40px auto; max-width: 600px;">
      <span class="material-symbols-outlined" style="font-size: 36px; color: var(--danger);">error_outline</span>
      <p style="color: var(--danger);">{{ error }}</p>
      <NuxtLink to="/my-courses" class="crud-secondary-btn" style="margin-top: 8px;">
        Về khoá học của tôi
      </NuxtLink>
    </div>

    <template v-else-if="result">
      <!-- Result hero card -->
      <div class="result-hero dashboard-card" :class="isPassed ? 'hero-pass' : 'hero-fail'">
        <div class="hero-icon-wrap" :class="isPassed ? 'icon-pass' : 'icon-fail'">
          <Trophy v-if="isPassed" :size="36" :stroke-width="1.75" />
          <Frown v-else :size="36" :stroke-width="1.75" />
        </div>

        <div class="hero-copy">
          <p class="section-kicker" :style="{ color: isPassed ? 'var(--green)' : 'var(--danger)' }">
            {{ isPassed ? 'Đạt yêu cầu' : 'Chưa đạt' }}
          </p>
          <h1>{{ isPassed ? 'Chúc mừng bạn đã vượt qua!' : 'Hãy ôn luyện và thử lại' }}</h1>
          <p class="hero-exam-name">{{ examTitle }}</p>
        </div>

        <!-- Score ring -->
        <div class="score-ring-wrap">
          <svg viewBox="0 0 120 120" class="score-svg">
            <circle cx="60" cy="60" r="50" class="ring-bg" />
            <circle
              cx="60" cy="60" r="50"
              class="ring-fill"
              :class="isPassed ? 'ring-pass' : 'ring-fail'"
              :stroke-dasharray="strokeDash"
              stroke-dashoffset="0"
              transform="rotate(-90 60 60)"
            />
          </svg>
          <div class="score-inner">
            <span class="score-num" :class="isPassed ? 'score-pass' : 'score-fail'">{{ scorePercent }}</span>
            <span class="score-pct">%</span>
            <span class="score-label">Điểm đạt: {{ passScore }}%</span>
          </div>
        </div>
      </div>

      <!-- Stats row -->
      <div class="stats-grid">
        <div class="stat-tile dashboard-card">
          <div class="stat-icon-wrap" style="background: rgba(34,197,94,0.1); color: #22c55e;">
            <CheckCircle2 :size="22" :stroke-width="1.75" />
          </div>
          <div class="stat-body">
            <p class="stat-label">Câu đúng</p>
            <strong class="stat-value">{{ correctCount }}</strong>
          </div>
        </div>
        <div class="stat-tile dashboard-card">
          <div class="stat-icon-wrap" style="background: rgba(239,68,68,0.1); color: #ef4444;">
            <XCircle :size="22" :stroke-width="1.75" />
          </div>
          <div class="stat-body">
            <p class="stat-label">Câu sai</p>
            <strong class="stat-value">{{ incorrectCount }}</strong>
          </div>
        </div>
        <div class="stat-tile dashboard-card">
          <div class="stat-icon-wrap" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
            <Clock :size="22" :stroke-width="1.75" />
          </div>
          <div class="stat-body">
            <p class="stat-label">Thời gian</p>
            <strong class="stat-value">{{ formatTime(timeSpent) }}</strong>
          </div>
        </div>
        <div class="stat-tile dashboard-card">
          <div class="stat-icon-wrap" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
            <Hash :size="22" :stroke-width="1.75" />
          </div>
          <div class="stat-body">
            <p class="stat-label">Tổng câu</p>
            <strong class="stat-value">{{ answers.length }}</strong>
          </div>
        </div>
      </div>

      <!-- Answer review -->
      <div v-if="answers.length > 0" class="dashboard-card crud-panel">
        <div class="crud-toolbar" style="margin-bottom: 20px;">
          <div>
            <p class="section-kicker">Chi tiết bài thi</p>
            <h3>Đánh giá từng câu hỏi</h3>
          </div>
          <span class="crud-badge">{{ correctCount }}/{{ answers.length }} câu đúng</span>
        </div>

        <div class="review-list">
          <div
            v-for="(ans, i) in answers"
            :key="i"
            class="answer-item"
            :class="ans.is_correct ? 'ans-correct' : 'ans-wrong'"
          >
            <div class="ans-header">
              <span class="ans-num">Câu {{ i + 1 }}</span>
              <span class="ans-verdict" :class="ans.is_correct ? 'verdict-pass' : 'verdict-fail'">
                <CheckCircle2 v-if="ans.is_correct" :size="14" :stroke-width="2" />
                <XCircle v-else :size="14" :stroke-width="2" />
                {{ ans.is_correct ? 'Đúng' : 'Sai' }}
              </span>
            </div>
            <p class="ans-question">{{ ans.question_content || ans.question?.content }}</p>
            <div class="ans-detail">
              <span class="ans-detail-label">Đáp án của bạn:</span>
              <span :class="ans.is_correct ? 'ans-text-correct' : 'ans-text-wrong'">
                {{ ans.selected_answer || ans.user_answer || '(Không trả lời)' }}
              </span>
            </div>
            <div v-if="!ans.is_correct" class="ans-detail">
              <span class="ans-detail-label">Đáp án đúng:</span>
              <span class="ans-text-correct">{{ ans.correct_answer }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="result-actions">
        <NuxtLink :to="`/exam/${examId}`" class="crud-primary-btn">
          <ChevronRight :size="16" :stroke-width="2" />
          Thi lại
        </NuxtLink>
        <NuxtLink to="/my-courses" class="crud-secondary-btn">
          Về khoá học của tôi
        </NuxtLink>
      </div>
    </template>
  </div>
</template>

<style scoped>
.result-page {
  max-width: 820px;
  margin: 40px auto;
  padding: 0 24px 60px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Hero */
.result-hero {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 20px;
  padding: 32px;
  border-width: 2px;
}

.hero-pass { border-color: rgba(34,197,94,0.3); background: linear-gradient(135deg, rgba(240,253,244,0.8), rgba(220,252,231,0.4)); }
.hero-fail { border-color: rgba(239,68,68,0.25); background: linear-gradient(135deg, rgba(255,241,242,0.8), rgba(254,228,230,0.4)); }

.hero-icon-wrap {
  width: 64px; height: 64px;
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.icon-pass { background: rgba(34,197,94,0.12); color: #16a34a; }
.icon-fail { background: rgba(239,68,68,0.1); color: #dc2626; }

.hero-copy h1 { margin: 4px 0 6px; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.03em; }
.hero-exam-name { margin: 0; font-size: 0.875rem; color: var(--muted); }

/* Score ring */
.score-ring-wrap {
  position: relative;
  width: 110px; height: 110px;
  flex-shrink: 0;
}
.score-svg { width: 100%; height: 100%; }
.ring-bg { fill: none; stroke: rgba(17,17,17,0.06); stroke-width: 10; }
.ring-fill { fill: none; stroke-width: 10; stroke-linecap: round; transition: stroke-dasharray 1s ease; }
.ring-pass { stroke: #22c55e; }
.ring-fail { stroke: #ef4444; }
.score-inner {
  position: absolute; inset: 0;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 1px;
}
.score-num { font-size: 1.75rem; font-weight: 900; line-height: 1; }
.score-pass { color: #16a34a; }
.score-fail { color: #dc2626; }
.score-pct { font-size: 0.75rem; color: var(--muted); }
.score-label { font-size: 0.6rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.stat-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
}

.stat-icon-wrap {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.stat-label { margin: 0; font-size: 0.78rem; color: var(--muted); }
.stat-value { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.04em; }

/* Review list */
.review-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.answer-item {
  border-radius: 12px;
  padding: 16px;
  border: 1px solid transparent;
}
.ans-correct { background: rgba(240,253,244,0.8); border-color: rgba(134,239,172,0.6); }
.ans-wrong { background: rgba(255,241,242,0.8); border-color: rgba(252,165,165,0.5); }

.ans-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.ans-num { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); }
.ans-verdict {
  display: flex; align-items: center; gap: 4px;
  font-size: 0.78rem; font-weight: 700;
}
.verdict-pass { color: #16a34a; }
.verdict-fail { color: #dc2626; }

.ans-question {
  font-size: 0.875rem; font-weight: 600;
  color: var(--text);
  margin: 0 0 8px;
  line-height: 1.5;
}

.ans-detail {
  display: flex; align-items: baseline; gap: 8px;
  font-size: 0.8rem;
  margin-top: 4px;
}
.ans-detail-label { color: var(--muted); flex-shrink: 0; }
.ans-text-correct { color: #16a34a; font-weight: 700; }
.ans-text-wrong { color: #dc2626; font-weight: 700; }

/* Actions */
.result-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* Dark mode */
[data-theme="dark"] .hero-pass { background: rgba(34,197,94,0.06); }
[data-theme="dark"] .hero-fail { background: rgba(239,68,68,0.06); }
[data-theme="dark"] .ans-correct { background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.2); }
[data-theme="dark"] .ans-wrong { background: rgba(239,68,68,0.07); border-color: rgba(239,68,68,0.18); }
[data-theme="dark"] .stat-tile { background: rgba(255,255,255,0.04); }

@media (max-width: 700px) {
  .result-hero { grid-template-columns: 1fr; text-align: center; }
  .hero-icon-wrap { margin: 0 auto; }
  .score-ring-wrap { margin: 0 auto; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
  .result-page { padding: 0 16px 40px; margin-top: 24px; }
}
</style>
