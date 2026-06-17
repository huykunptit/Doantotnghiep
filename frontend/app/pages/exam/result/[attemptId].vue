<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

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

const scorePercent = computed(() => {
  const score = result.value?.score ?? result.value?.attempt?.score ?? 0
  return Math.round(Number(score))
})

const answers = computed(() => result.value?.answers || result.value?.attempt?.answers || [])
const correctCount = computed(() => answers.value.filter((a: any) => a.is_correct).length)
const incorrectCount = computed(() => answers.value.filter((a: any) => !a.is_correct).length)

function formatTime(seconds: number) {
  if (!seconds) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

onMounted(fetchResult)
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-10">
    <!-- Loading -->
    <div v-if="loading" class="flex justify-center p-12">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rounded-3xl border border-rose-200 bg-rose-50 p-8 text-center text-rose-700">
      <span class="material-symbols-outlined mb-2 block text-4xl">error_outline</span>
      <p>{{ error }}</p>
      <NuxtLink to="/my-courses" class="mt-4 inline-block text-sm font-semibold text-primary hover:underline">
        Về khoá học của tôi
      </NuxtLink>
    </div>

    <template v-else-if="result">
      <!-- Result card -->
      <div
        class="result-card"
        :class="isPassed ? 'is-passed' : 'is-failed'"
      >
        <div class="result-icon">
          <span class="material-symbols-outlined">
            {{ isPassed ? 'emoji_events' : 'sentiment_dissatisfied' }}
          </span>
        </div>
        <h1 class="result-title">
          {{ isPassed ? 'Chúc mừng! Bạn đã đạt yêu cầu' : 'Chưa đạt — Hãy thử lại' }}
        </h1>
        <p class="result-exam-name">{{ result.exam?.title || result.attempt?.exam_title || 'Kỳ thi' }}</p>

        <!-- Score ring -->
        <div class="score-ring-wrap">
          <svg class="score-ring" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="50" class="ring-bg" />
            <circle
              cx="60" cy="60" r="50"
              class="ring-fill"
              :class="isPassed ? 'ring-pass' : 'ring-fail'"
              :stroke-dasharray="`${scorePercent * 3.14} 314`"
              stroke-dashoffset="0"
              transform="rotate(-90 60 60)"
            />
          </svg>
          <div class="score-text">
            <span class="score-num">{{ scorePercent }}</span>
            <span class="score-unit">%</span>
          </div>
        </div>

        <p class="pass-threshold">Điểm đạt: {{ result.exam?.pass_score ?? result.pass_score ?? 80 }}%</p>
      </div>

      <!-- Stats row -->
      <div class="stats-row">
        <div class="stat-card">
          <span class="material-symbols-outlined stat-icon" style="color: #22c55e;">check_circle</span>
          <div class="stat-label">Câu đúng</div>
          <div class="stat-value">{{ correctCount }}</div>
        </div>
        <div class="stat-card">
          <span class="material-symbols-outlined stat-icon" style="color: #ef4444;">cancel</span>
          <div class="stat-label">Câu sai</div>
          <div class="stat-value">{{ incorrectCount }}</div>
        </div>
        <div class="stat-card">
          <span class="material-symbols-outlined stat-icon" style="color: #3b82f6;">timer</span>
          <div class="stat-label">Thời gian</div>
          <div class="stat-value">{{ formatTime(result.attempt?.time_spent ?? result.time_spent) }}</div>
        </div>
        <div class="stat-card">
          <span class="material-symbols-outlined stat-icon" style="color: #f59e0b;">quiz</span>
          <div class="stat-label">Tổng câu</div>
          <div class="stat-value">{{ answers.length }}</div>
        </div>
      </div>

      <!-- Answer review -->
      <div v-if="answers.length > 0" class="review-section">
        <h2 class="review-title">Chi tiết từng câu hỏi</h2>
        <div class="review-list">
          <div
            v-for="(ans, i) in answers"
            :key="i"
            class="answer-item"
            :class="ans.is_correct ? 'is-correct' : 'is-wrong'"
          >
            <div class="answer-header">
              <span class="q-num">Câu {{ i + 1 }}</span>
              <span class="q-status">
                <span class="material-symbols-outlined">{{ ans.is_correct ? 'check_circle' : 'cancel' }}</span>
                {{ ans.is_correct ? 'Đúng' : 'Sai' }}
              </span>
            </div>
            <p class="q-content">{{ ans.question_content || ans.question?.content }}</p>
            <div class="ans-row">
              <span class="ans-label">Đáp án của bạn:</span>
              <span :class="ans.is_correct ? 'ans-correct' : 'ans-wrong'">
                {{ ans.selected_answer || ans.user_answer || '(Không trả lời)' }}
              </span>
            </div>
            <div v-if="!ans.is_correct" class="ans-row">
              <span class="ans-label">Đáp án đúng:</span>
              <span class="ans-correct">{{ ans.correct_answer }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="actions-row">
        <NuxtLink :to="`/exam/${examId}`" class="btn-retry">Thi lại</NuxtLink>
        <NuxtLink to="/my-courses" class="btn-courses">Về khoá học</NuxtLink>
      </div>
    </template>
  </div>
</template>

<style scoped>
.result-card {
  border-radius: 28px;
  padding: 40px 24px 32px;
  text-align: center;
  margin-bottom: 20px;
}
.result-card.is-passed {
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 2px solid #86efac;
}
.result-card.is-failed {
  background: linear-gradient(135deg, #fff1f2, #ffe4e6);
  border: 2px solid #fca5a5;
}
.result-icon .material-symbols-outlined { font-size: 48px; }
.is-passed .result-icon .material-symbols-outlined { color: #16a34a; }
.is-failed .result-icon .material-symbols-outlined { color: #dc2626; }
.result-title { font-size: 1.5rem; font-weight: 800; margin: 12px 0 6px; }
.is-passed .result-title { color: #14532d; }
.is-failed .result-title { color: #7f1d1d; }
.result-exam-name { font-size: 0.875rem; color: #6b7280; margin-bottom: 24px; }

/* Score ring */
.score-ring-wrap { position: relative; width: 120px; height: 120px; margin: 0 auto 12px; }
.score-ring { width: 100%; height: 100%; }
.ring-bg { fill: none; stroke: rgba(17,17,17,0.08); stroke-width: 10; }
.ring-fill { fill: none; stroke-width: 10; stroke-linecap: round; transition: stroke-dasharray 1s ease; }
.ring-pass { stroke: #22c55e; }
.ring-fail { stroke: #ef4444; }
.score-text {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center; flex-direction: column;
}
.score-num { font-size: 2rem; font-weight: 900; line-height: 1; }
.score-unit { font-size: 0.75rem; color: #6b7280; }
.pass-threshold { font-size: 0.8rem; color: #6b7280; }

/* Stats */
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}
@media (max-width: 600px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
.stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 16px;
  text-align: center;
}
.stat-icon { font-size: 24px; }
.stat-label { font-size: 0.75rem; color: #6b7280; margin-top: 6px; }
.stat-value { font-size: 1.5rem; font-weight: 800; color: #111827; }

/* Answer review */
.review-section { margin-bottom: 28px; }
.review-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; }
.review-list { display: flex; flex-direction: column; gap: 12px; }
.answer-item {
  border-radius: 16px;
  padding: 16px;
  border: 1px solid transparent;
}
.answer-item.is-correct { background: #f0fdf4; border-color: #86efac; }
.answer-item.is-wrong { background: #fff1f2; border-color: #fca5a5; }
.answer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.q-num { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; }
.q-status { display: flex; align-items: center; gap: 4px; font-size: 0.8rem; font-weight: 700; }
.is-correct .q-status { color: #16a34a; }
.is-wrong .q-status { color: #dc2626; }
.q-status .material-symbols-outlined { font-size: 16px; }
.q-content { font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 10px; line-height: 1.5; }
.ans-row { display: flex; align-items: baseline; gap: 8px; margin-top: 4px; font-size: 0.8rem; }
.ans-label { color: #6b7280; flex-shrink: 0; }
.ans-correct { color: #16a34a; font-weight: 700; }
.ans-wrong { color: #dc2626; font-weight: 700; }

/* Actions */
.actions-row { display: flex; gap: 12px; justify-content: center; }
.btn-retry, .btn-courses {
  padding: 12px 28px;
  border-radius: 14px;
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s;
}
.btn-retry { background: var(--primary, #4f46e5); color: #fff; }
.btn-retry:hover { opacity: 0.9; }
.btn-courses { background: #f1f5f9; color: #374151; }
.btn-courses:hover { background: #e2e8f0; }
</style>
