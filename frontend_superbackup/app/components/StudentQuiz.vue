<template>
  <div class="student-quiz-container">
    <div v-if="loading" class="quiz-loading">
      <div class="premium-loader"></div>
      <p>Đang chuẩn bị đề thi...</p>
    </div>

    <div v-else-if="!quiz" class="quiz-empty-state">
      <div class="empty-icon shadow-premium">
        <i class="fas fa-file-signature"></i>
      </div>
      <p>Hệ thống chưa tải được bài thi.</p>
    </div>

    <div v-else-if="result" class="exam-result-layout" :class="{ passed: result?.passed }">
      <!-- Left: Result Card -->
      <div class="exam-result-card">
        <p class="exam-kicker">Kết quả bài thi</p>
        <h1 class="exam-result-heading">
          {{ result?.passed ? 'Chúc mừng, bạn đã đạt!' : 'Bạn chưa đạt điểm tối thiểu' }}
        </h1>

        <div v-if="result?.score !== undefined" class="exam-score-container">
          <div class="exam-score-circle" :class="{ passed: result?.passed }">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="circle-bg" cx="60" cy="60" r="52" />
              <circle
                class="circle-fill"
                cx="60"
                cy="60"
                r="52"
                :stroke-dasharray="326.7"
                :stroke-dashoffset="326.7 - (Math.min(Math.max(Number(result.score) || 0, 0), 100) / 100) * 326.7"
              />
            </svg>
            <div class="score-text" :class="{ passed: result?.passed }">{{ result.score }}<span>%</span></div>
          </div>
        </div>

        <div class="result-info-grid">
          <div class="result-info-item">
            <div class="info-icon" :class="{ passed: result?.passed }">
              <span class="material-symbols-outlined">check_circle</span>
            </div>
            <div class="info-content">
              <div class="info-value">{{ answeredCount }}/{{ questions.length }}</div>
              <div class="info-label">Câu đã trả lời</div>
            </div>
          </div>
          <div class="result-info-item">
            <div class="info-icon">
              <span class="material-symbols-outlined">school</span>
            </div>
            <div class="info-content">
              <div class="info-value">{{ quiz?.pass_score || 80 }}%</div>
              <div class="info-label">Điểm cần đạt</div>
            </div>
          </div>
        </div>

        <button @click="resetQuiz" class="result-action-btn">
          <span class="material-symbols-outlined">replay</span>
          Làm lại bài thi
        </button>
      </div>

      <!-- Right: Attempts History Sidebar -->
      <div class="exam-result-sidebar">
        <div class="sidebar-header">
          <h3>Lượt làm bài</h3>
          <p class="sidebar-subtitle">Xem lại các lượt làm trước đây</p>
        </div>

        <div v-if="showReviewDetails && reviewAttempt" class="review-detail-view">
          <button class="review-back-btn" @click="showReviewDetails = false">
            <span class="material-symbols-outlined">arrow_back</span>
            Quay lại danh sách
          </button>

          <div class="review-attempt-info">
            <div class="review-info-row">
              <span>Điểm:</span>
              <strong :class="{ 'text-green': reviewAttempt.score >= (quiz?.pass_score || 80) }">
                {{ reviewAttempt.score }}%
              </strong>
            </div>
            <div class="review-info-row">
              <span>Ngày làm:</span>
              <span>{{ new Date(reviewAttempt.created_at).toLocaleDateString('vi-VN') }}</span>
            </div>
            <div class="review-info-row">
              <span>Trạng thái:</span>
              <span :class="{ 'text-green': reviewAttempt.score >= (quiz?.pass_score || 80), 'text-red': reviewAttempt.score < (quiz?.pass_score || 80) }">
                {{ reviewAttempt.score >= (quiz?.pass_score || 80) ? 'Đạt' : 'Chưa đạt' }}
              </span>
            </div>
          </div>

          <div class="review-content-toggle">
            <label class="toggle-option">
              <input type="checkbox" v-model="reviewAnswers" true-value="show-user" false-value="">
              <span>Xem bài làm của tôi</span>
            </label>
            <label class="toggle-option">
              <input type="checkbox" v-model="reviewAnswers" true-value="show-correct" false-value="">
              <span>Xem đáp án</span>
            </label>
          </div>

          <div class="review-questions-list">
            <div v-for="(q, idx) in questions" :key="q.id" class="review-question-item">
              <div class="review-q-header">
                <span class="q-number">{{ idx + 1 }}</span>
                <span class="q-title">{{ q.content.substring(0, 60) }}...</span>
                <span :class="['q-result', { correct: reviewAttempt.correct_answers?.includes(q.id) }]">
                  {{ reviewAttempt.correct_answers?.includes(q.id) ? '✓' : '✗' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="attempts-list">
          <div class="attempt-item" :class="{ current: !result?.attempt_id }">
            <div class="attempt-header">
              <span class="attempt-badge">Lần làm {{ 1 }}</span>
              <span class="attempt-score" :class="{ passed: result?.score >= (quiz?.pass_score || 80) }">
                {{ result?.score }}%
              </span>
            </div>
            <div class="attempt-meta">
              <span>Hôm nay - {{ new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}</span>
              <span :class="{ 'text-green': result?.score >= (quiz?.pass_score || 80) }">
                {{ result?.score >= (quiz?.pass_score || 80) ? '✓ Đạt' : '✗ Chưa đạt' }}
              </span>
            </div>
            <button class="attempt-review-btn" @click="openReview">
              <span class="material-symbols-outlined">visibility</span>
              Xem chi tiết
            </button>
          </div>

          <!-- Previous attempts (if any) -->
          <!-- This would populate from API if quiz allows multiple attempts -->
          <p class="no-more-attempts" style="text-align: center; color: #64748b; font-size: 0.9rem; margin-top: 1rem;">
            Đây là lần làm đầu tiên
          </p>
        </div>
      </div>
    </div>

    <div v-else class="exam-shell-inline shadow-premium">
      <!-- Exam Header -->
      <header class="exam-topbar">
        <div class="exam-topbar__title">
          <p class="exam-kicker">PTIT Exam Workspace</p>
          <h1>{{ quiz.title }}</h1>
          <div class="exam-meta">
            <span>{{ answeredCount }}/{{ questions.length }} câu đã trả lời</span>
            <span>{{ questions.length - answeredCount }} câu chưa trả lời</span>
            <span v-if="bookmarkedCount > 0">★ {{ bookmarkedCount }} câu đã đánh dấu</span>
          </div>
          <div v-if="warnings > 0" class="mt-2 text-xs font-bold text-red-500">
            <i class="fas fa-exclamation-triangle"></i> Cảnh báo giám sát: Rời khỏi màn hình {{ warnings }} lần
          </div>
        </div>

        <div class="exam-topbar__actions">
          <div v-if="quiz.time_limit" class="exam-timer" :class="{ urgent: timeRemaining < 300 && timeRemaining > 0 }">
            <span class="material-symbols-outlined">timer</span>
            <strong>{{ formattedTime }}</strong>
          </div>
          <button class="exam-submit-btn" :disabled="submitting" @click="confirmSubmitOpen = true">Nộp bài</button>
        </div>
      </header>

      <div class="exam-layout">
        <aside class="exam-sidebar">
          <div class="exam-sidebar__card">
            <h3>Điều hướng câu hỏi</h3>
            <p>Chọn nhanh câu hỏi cần làm hoặc kiểm tra lại.</p>
            <div class="question-nav">
              <button
                v-for="(q, idx) in questions"
                :key="q.id"
                :class="['q-nav-btn', {
                  active: idx === currentIndex,
                  answered: isQuestionAnswered(q.id),
                  bookmarked: !!bookmarks[q.id],
                }]"
                :title="bookmarks[q.id] ? 'Đã đánh dấu' : ''"
                @click="currentIndex = idx"
              >
                <span>{{ idx + 1 }}</span>
                <span v-if="bookmarks[q.id]" class="material-symbols-outlined q-nav-flag" aria-hidden="true">bookmark</span>
              </button>
            </div>
            <div class="question-nav-legend">
              <span><i class="legend-dot legend-answered"></i> Đã trả lời</span>
              <span><i class="legend-dot legend-bookmark"></i> Đã đánh dấu</span>
            </div>
          </div>
        </aside>

        <main v-if="currentQuestion" class="exam-main">
          <div class="question-panel">
            <div class="question-panel__header">
              <div>
                <p class="exam-kicker">Câu hỏi {{ currentIndex + 1 }}/{{ questions.length }}</p>
                <h2>{{ quiz?.title || 'Bài thi' }}</h2>
              </div>
              <div class="question-panel__head-actions">
                <button
                  type="button"
                  :class="['bookmark-btn', { 'is-active': isCurrentBookmarked }]"
                  :title="isCurrentBookmarked ? 'Bỏ đánh dấu câu này' : 'Đánh dấu câu này để xem lại sau'"
                  @click="toggleBookmark()"
                >
                  <span class="material-symbols-outlined bookmark-icon">{{ isCurrentBookmarked ? 'bookmark' : 'bookmark_border' }}</span>
                  <span>{{ isCurrentBookmarked ? 'Đã đánh dấu' : 'Đánh dấu' }}</span>
                </button>
                <span class="question-type">{{ getTypeText(currentQuestion.type) }}</span>
              </div>
            </div>

            <div class="question-content">{{ currentQuestion.content }}</div>

            <!-- Single Choice & True/False -->
            <div v-if="['single_choice', 'true_false'].includes(currentQuestion.type)" class="answer-list">
              <label
                v-for="ans in currentQuestion.answers"
                :key="ans.id"
                :class="['answer-option', { selected: userAnswers[currentQuestion.id] === ans.id }]"
                @click.prevent="selectAnswer(currentQuestion.id, ans.id)"
              >
                <input type="radio" :name="'q-' + currentQuestion.id" :value="ans.id" :checked="userAnswers[currentQuestion.id] === ans.id">
                <span>{{ ans.content }}</span>
              </label>
            </div>

            <!-- Multiple Choice -->
            <div v-else-if="currentQuestion.type === 'multiple_choice'" class="answer-list">
              <label
                v-for="ans in currentQuestion.answers"
                :key="ans.id"
                :class="['answer-option', { selected: (userAnswers[currentQuestion.id] || []).includes(ans.id) }]"
                @click.prevent="toggleMultipleChoice(currentQuestion.id, ans.id)"
              >
                <input type="checkbox" :checked="(userAnswers[currentQuestion.id] || []).includes(ans.id)">
                <span>{{ ans.content }}</span>
              </label>
            </div>

            <!-- Short Answer & Numerical -->
            <div v-else-if="['short_answer', 'numerical'].includes(currentQuestion.type)" class="answer-input">
              <input
                :type="currentQuestion.type === 'numerical' ? 'number' : 'text'"
                :value="userAnswers[currentQuestion.id] || ''"
                :placeholder="currentQuestion.type === 'numerical' ? 'Nhập số...' : 'Nhập câu trả lời...'"
                class="exam-text-input"
                @input="selectAnswer(currentQuestion.id, ($event.target as HTMLInputElement).value)"
              >
            </div>

            <!-- Essay -->
            <div v-else-if="currentQuestion.type === 'essay'" class="answer-input">
              <textarea
                :value="userAnswers[currentQuestion.id] || ''"
                rows="8"
                class="exam-text-input"
                placeholder="Nhập bài tự luận..."
                @input="selectAnswer(currentQuestion.id, ($event.target as HTMLTextAreaElement).value)"
              />
            </div>
            
            <!-- Ordering -->
            <div v-else-if="currentQuestion.type === 'ordering'" class="ordering-wrap">
               <p class="exam-kicker mb-3">Sắp xếp theo thứ tự đúng:</p>
               <div class="answer-list">
                 <div v-for="(ans, sIdx) in (userAnswers[currentQuestion.id] || currentQuestion.answers)" :key="ans.id" class="answer-option sort-item">
                   <div class="flex items-center gap-3">
                     <strong style="color:var(--green)">{{ sIdx + 1 }}.</strong>
                     <span>{{ ans.content }}</span>
                   </div>
                   <div class="flex flex-col gap-1">
                     <button type="button" @click="moveOrder(currentQuestion.id, sIdx, -1)" :disabled="sIdx === 0" class="sort-btn">
                       <span class="material-symbols-outlined">expand_less</span>
                     </button>
                     <button type="button" @click="moveOrder(currentQuestion.id, sIdx, 1)" :disabled="sIdx === (userAnswers[currentQuestion.id] || currentQuestion.answers).length - 1" class="sort-btn">
                       <span class="material-symbols-outlined">expand_more</span>
                     </button>
                   </div>
                 </div>
               </div>
            </div>

            <div class="question-nav-buttons">
              <button :disabled="currentIndex === 0" class="nav-btn" @click="currentIndex--">← Câu trước</button>
              <button :disabled="currentIndex >= questions.length - 1" class="nav-btn nav-btn--primary" @click="currentIndex++">Câu sau →</button>
            </div>
          </div>
        </main>
      </div>

      <Teleport to="body">
        <div v-if="confirmSubmitOpen" class="exam-overlay" @click.self="confirmSubmitOpen = false">
          <div class="exam-overlay-card exam-submit-modal">
            <h2>Xác nhận nộp bài</h2>
            <p>Bạn đã hoàn thành <strong>{{ answeredCount }}/{{ questions.length }}</strong> câu hỏi.</p>
            <p v-if="answeredCount < questions.length" class="exam-warning">Còn {{ questions.length - answeredCount }} câu chưa trả lời.</p>
            <div class="question-nav-buttons question-nav-buttons--modal">
              <button class="nav-btn" @click="confirmSubmitOpen = false">Quay lại</button>
              <button class="exam-submit-btn" :disabled="submitting" @click="submitQuiz(false)">{{ submitting ? 'Đang nộp...' : 'Xác nhận nộp' }}</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useToast } from '~/composables/useToast'

const toast = useToast()
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

const props = defineProps<{
  courseId: number
  lessonId: number
}>()

const emit = defineEmits<{
  completed: [payload: { passed: boolean; score?: number }]
}>()

const auth = useAuthStore()
const loading = ref(true)
const submitting = ref(false)
const quiz = ref<any>(null)
const questions = ref<any[]>([])
const attemptId = ref<number | null>(null)
const userAnswers = ref<Record<string, any>>({})
const result = ref<any>(null)
const confirmSubmitOpen = ref(false)

const currentIndex = ref(0)
const bookmarks = ref<Record<string, boolean>>({})

const timeRemaining = ref(0)
const timerInterval = ref<any>(null)
const warnings = ref(0)

// Review mode state
const reviewMode = ref(false)
const reviewAttempt = ref<any>(null)
const reviewAnswers = ref<Record<string, any>>({})
const showReviewDetails = ref(false)

const currentQuestion = computed(() => questions.value[currentIndex.value] || null)

const formattedTime = computed(() => {
  if (timeRemaining.value <= 0) return '00:00'
  const m = Math.floor(timeRemaining.value / 60)
  const s = timeRemaining.value % 60
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
})

const authHeaders = () => auth.token ? { Authorization: `Bearer ${auth.token}` } : undefined

function getTypeText(type: string) {
  const map: any = {
    single_choice: 'Trắc nghiệm',
    multiple_choice: 'Nhiều lựa chọn',
    true_false: 'Đúng/Sai',
    essay: 'Tự luận',
    ordering: 'Sắp xếp',
    matching: 'Nối cặp',
    short_answer: 'Trả lời ngắn',
    numerical: 'Điền số'
  }
  return map[type] || 'Câu hỏi'
}

function isQuestionAnswered(qId: number) {
  const val = userAnswers.value[qId]
  if (Array.isArray(val)) return val.length > 0
  return val !== null && val !== undefined && val !== ''
}

const answeredCount = computed(() => {
  return questions.value.filter(q => isQuestionAnswered(q.id)).length
})

const bookmarkedCount = computed(() => Object.values(bookmarks.value).filter(Boolean).length)
const isCurrentBookmarked = computed(() => !!(currentQuestion.value && bookmarks.value[currentQuestion.value.id]))

function toggleBookmark() {
  if (!currentQuestion.value) return
  const id = currentQuestion.value.id
  bookmarks.value = { ...bookmarks.value, [id]: !bookmarks.value[id] }
}

function selectAnswer(questionId: number, value: any) {
  userAnswers.value[questionId] = value
}

function toggleMultipleChoice(questionId: number, answerId: number) {
  const current = userAnswers.value[questionId] || []
  const arr = Array.isArray(current) ? [...current] : []
  const idx = arr.indexOf(answerId)
  if (idx > -1) arr.splice(idx, 1)
  else arr.push(answerId)
  userAnswers.value[questionId] = arr
}

function moveOrder(qId: number, index: number, direction: number) {
  const arr = [...(userAnswers.value[qId] || [])]
  if (index + direction < 0 || index + direction >= arr.length) return
  
  const temp = arr[index]
  arr[index] = arr[index + direction]
  arr[index + direction] = temp
  
  userAnswers.value[qId] = arr
}

function startTimer() {
  if (timerInterval.value) clearInterval(timerInterval.value)
  if (!quiz.value?.time_limit) return
  
  timeRemaining.value = quiz.value.time_limit * 60
  
  timerInterval.value = setInterval(() => {
    timeRemaining.value--
    if (timeRemaining.value <= 0) {
      clearInterval(timerInterval.value)
      submitQuiz(true) // auto submit
    }
  }, 1000)
}

function stopTimer() {
  if (timerInterval.value) clearInterval(timerInterval.value)
}

function openReview() {
  reviewAttempt.value = result.value
  reviewAnswers.value = {}
  showReviewDetails.value = true
}

function handleVisibilityChange() {
  if (document.hidden && !result.value && quiz.value) {
    warnings.value++
    // toast.error(`CẢNH BÁO GIAN LẬN (${warnings.value}): Bạn vừa chuyển tab hoặc rời khỏi cửa sổ làm bài!`)
  }
}

async function loadQuiz() {
  loading.value = true
  result.value = null
  userAnswers.value = {}
  bookmarks.value = {}
  currentIndex.value = 0
  
  try {
    const res = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/quiz`, { headers: authHeaders() })
    quiz.value = res.quiz
    questions.value = res.questions || []
    attemptId.value = res.attempt_id

    // Check if q.answers exists, fallback if empty
    questions.value.forEach((q: any) => {
      q.answers = q.answers || [] // safeguard
      if (q.type === 'multiple_choice') userAnswers.value[q.id] = []
      else if (q.type === 'ordering') userAnswers.value[q.id] = [...q.answers]
      else userAnswers.value[q.id] = null
    })

    warnings.value = 0
    startTimer()
    document.addEventListener('visibilitychange', handleVisibilityChange)
  } catch (e: any) {
    quiz.value = null
  } finally {
    loading.value = false
  }
}

async function submitQuiz(auto = false) {
  if (!auto && answeredCount.value < questions.value.length && !confirmSubmitOpen.value) {
    confirmSubmitOpen.value = true
    return
  }
  confirmSubmitOpen.value = false

  stopTimer()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  submitting.value = true
  try {
    const res = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/quiz/${quiz.value.id}/submit`, {
      method: 'POST',
      body: {
        attempt_id: attemptId.value,
        answers: userAnswers.value,
      },
      headers: authHeaders(),
    })
    result.value = res.attempt
    if (res.attempt) {
      emit('completed', {
        passed: Boolean(res.attempt.passed),
        score: res.attempt.score,
      })
    }
  } catch (e) {
    toast.error('Không thể nộp bài, vui lòng thử lại sau.')
  } finally {
    submitting.value = false
  }
}

function resetQuiz() {
  stopTimer()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  result.value = null
  showReviewDetails.value = false
  reviewMode.value = false
  loadQuiz()
}

onBeforeUnmount(() => {
  stopTimer()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})

onMounted(loadQuiz)
watch(() => props.lessonId, loadQuiz)
</script>

<style scoped>
.student-quiz-container { min-height: 400px; font-family: 'Be Vietnam Pro', system-ui, sans-serif; color: #14213d; position: relative; }
.quiz-loading, .quiz-empty-state { padding: 4rem 1rem; text-align: center; color: #64748b; }
.premium-loader { width: 40px; height: 40px; border: 4px solid #f1f5f9; border-top-color: var(--green); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-icon { width: 64px; height: 64px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 1.5rem; color: #cbd5e1; }

.shadow-premium { box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }

.exam-shell-inline { 
  background: #f8fbff; 
  border-radius: 24px; 
  overflow: hidden; 
  border: 1px solid rgba(var(--green-rgb), 0.12);
  display: flex;
  flex-direction: column;
}

.exam-topbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid rgba(var(--green-rgb), 0.12); background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(18px); }
.exam-topbar__title h1 { margin: 0; font-size: 1.5rem; }
.exam-kicker { margin: 0 0 0.35rem; color: var(--green); font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
.exam-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.45rem; color: #64748b; font-size: 0.92rem; }
.exam-topbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }

.exam-timer { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 16px; background: #14213d; color: #fff; box-shadow: 0 10px 24px rgba(20, 33, 61, 0.16); }
.exam-timer.urgent { background: #d71920; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(0.98); } }

.exam-submit-btn, .nav-btn { border: none; cursor: pointer; transition: 0.2s ease; }
.exam-submit-btn { padding: 0.9rem 1.2rem; border-radius: 14px; background: var(--green); color: #fff; font-weight: 800; box-shadow: 0 10px 24px rgba(var(--green-rgb), 0.22); }
.exam-submit-btn:hover, .nav-btn--primary:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.03); }
.exam-submit-btn:disabled, .nav-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.exam-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 1.5rem; padding: 1.5rem; }
.exam-sidebar__card, .question-panel { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 24px; background: rgba(255, 255, 255, 0.92); box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04); }
.exam-sidebar__card { position: sticky; top: 1.5rem; padding: 1.25rem; align-self: start; }
.exam-sidebar__card h3 { margin: 0 0 0.45rem; }
.exam-sidebar__card p { margin: 0 0 1rem; color: #64748b; font-size: 0.92rem; line-height: 1.6; }

.question-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; }
.q-nav-btn { position: relative; min-height: 44px; border: 1px solid #dbe6f5; border-radius: 14px; background: #fff; color: #475569; font-weight: 800; cursor: pointer; }
.q-nav-btn.active { background: rgba(var(--green-rgb), 0.05); color: var(--green); border-color: #90caf9; }
.q-nav-btn.answered { background: #ecfdf3; color: var(--green-deep); border-color: #86efac; }
.q-nav-btn.active.answered { box-shadow: inset 0 0 0 1px var(--green); }
.q-nav-btn.bookmarked { border-color: #f59e0b; box-shadow: inset 0 0 0 1px #fbbf24; }
.q-nav-flag { position: absolute; top: -6px; right: -6px; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: #f59e0b; color: #fff; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4); font-size: 14px; line-height: 1; }

.question-nav-legend { display: flex; flex-wrap: wrap; gap: 0.85rem; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed #dbe6f5; color: #64748b; font-size: 0.78rem; }
.question-nav-legend span { display: inline-flex; align-items: center; gap: 0.4rem; }
.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; }
.legend-answered { background: #ecfdf3; border: 1px solid #86efac; }
.legend-bookmark { background: #fff; border: 1px solid #f59e0b; box-shadow: inset 0 0 0 1px #fbbf24; }

.question-panel { padding: 1.5rem; }
.question-panel__header, .question-nav-buttons { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.question-panel__header h2 { margin: 0; font-size: 1.25rem; }
.question-panel__head-actions { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }

.bookmark-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #64748b; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: 0.15s ease; }
.bookmark-btn:hover { border-color: #f59e0b; color: #b45309; background: #fffbeb; }
.bookmark-btn.is-active { background: #fffbeb; border-color: #f59e0b; color: #b45309; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.18); }
.bookmark-icon { font-size: 18px !important; line-height: 1; }
.question-type { padding: 0.45rem 0.9rem; border-radius: 999px; background: rgba(var(--green-rgb), 0.05); color: #1558b0; font-size: 0.82rem; font-weight: 700; }

.question-content { margin: 1.5rem 0; padding: 1.25rem; border-radius: 20px; background: #f8fbff; border: 1px solid #dbe6f5; font-size: 1.05rem; line-height: 1.75; white-space: pre-wrap; }
.answer-list { display: grid; gap: 0.85rem; }
.answer-option { display: flex; align-items: flex-start; gap: 0.8rem; padding: 1rem 1.1rem; border: 1px solid #dbe6f5; border-radius: 18px; background: #fff; cursor: pointer; transition: 0.2s ease; }
.answer-option:hover { border-color: #90caf9; background: #f8fbff; }
.answer-option.selected { border-color: var(--green); background: rgba(var(--green-rgb), 0.05); box-shadow: inset 0 0 0 1px rgba(var(--green-rgb), 0.12); }
.answer-option input { margin-top: 0.2rem; accent-color: var(--green); }

.answer-input { margin-top: 1rem; }
.exam-text-input { width: 100%; min-height: 56px; padding: 1rem 1.1rem; border: 1px solid #cbd5e1; border-radius: 18px; background: #fff; font: inherit; resize: vertical; }
.exam-text-input:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 4px rgba(var(--green-rgb), 0.12); }

.sort-item { justify-content: space-between; align-items: center; }
.sort-btn { padding: 4px; border-radius: 4px; background: #f1f5f9; color: #64748b; border: none; cursor: pointer; transition: 0.2s; }
.sort-btn:hover:not(:disabled) { background: #e2e8f0; color: #0f172a; }
.sort-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.question-nav-buttons { margin-top: 1.5rem; }
.nav-btn { padding: 0.9rem 1.2rem; border-radius: 14px; background: #fff; color: #334155; border: 1px solid #dbe6f5; font-weight: 700; }
.nav-btn:hover:not(:disabled) { border-color: #90caf9; color: var(--green); }
.nav-btn--primary { background: #14213d; color: #fff; border-color: #14213d; }

.exam-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, 0.6); z-index: 60; }
.exam-overlay-card { width: min(100%, 520px); padding: 2rem; background: #fff; border-radius: 24px; text-align: center;}
.exam-submit-modal { text-align: left; }
.question-nav-buttons--modal { justify-content: flex-end; margin-top: 1.25rem; display: flex; gap: 1rem; }
.exam-warning { color: #d71920; font-weight: 700; }

.exam-result-fullscreen { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1.5rem; background: radial-gradient(circle at top, #fff5f5 0%, #fef2f2 35%, #fee2e2 100%); z-index: 50; overflow: auto; border-radius: 16px; }
.exam-result-fullscreen.passed { background: radial-gradient(circle at top, #f0fdf4 0%, #dcfce7 35%, #bbf7d0 100%); }
.exam-result-content { width: min(100%, 640px); text-align: center; display: flex; flex-direction: column; align-items: center; gap: 1rem; }
.exam-result-icon-wrap { width: 96px; height: 96px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; box-shadow: 0 14px 40px rgba(215, 25, 32, 0.18); margin-bottom: 0.5rem; }
.exam-result-icon-wrap .material-symbols-outlined { font-size: 56px; color: #d71920; }
.exam-result-icon-wrap.passed { box-shadow: 0 14px 40px rgba(21, 128, 61, 0.22); }
.exam-result-icon-wrap.passed .material-symbols-outlined { color: var(--green-deep); }
.exam-result-title { margin: 0; font-size: 2rem; line-height: 1.2; }
.exam-score-wrap { margin: 0.5rem 0 0.25rem; }
.exam-score-ring { position: relative; width: 200px; height: 200px; }
.exam-score-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.exam-score-ring .ring-bg { fill: none; stroke: rgba(215, 25, 32, 0.12); stroke-width: 12; }
.exam-score-ring .ring-fg { fill: none; stroke: #d71920; stroke-width: 12; stroke-linecap: round; transition: stroke-dashoffset 1.2s ease-out; }
.exam-score-ring.passed .ring-bg { stroke: rgba(21, 128, 61, 0.15); }
.exam-score-ring.passed .ring-fg { stroke: var(--green-deep); }
.exam-score { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; margin: 0; font-size: 3.2rem; font-weight: 900; color: #d71920; letter-spacing: -0.02em; }
.exam-score span { font-size: 1.2rem; font-weight: 700; margin-left: 2px; }
.exam-score.passed { color: var(--green-deep); }
.exam-result-stats { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.85rem; margin: 1rem 0 0.5rem; }
.exam-result-stat { display: flex; align-items: center; gap: 0.65rem; padding: 0.85rem 1.1rem; border-radius: 16px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(15, 23, 42, 0.08); text-align: left; min-width: 180px; }
.exam-result-stat .material-symbols-outlined { font-size: 28px; color: var(--green); }
.exam-result-stat strong { display: block; font-size: 1.1rem; }
.exam-result-stat span { font-size: 0.8rem; color: #64748b; }
.exam-link-btn { text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }

/* New 2-column result layout */
.exam-result-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; padding: 2rem; align-items: start; height: 100%; overflow: auto; background: linear-gradient(135deg, #fff5f5 0%, #fef9f9 100%); }
.exam-result-layout.passed { background: linear-gradient(135deg, #f0fdf4 0%, #f9ffef 100%); }

.exam-result-card { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.5rem; padding: 2.5rem; background: #fff; border-radius: 28px; box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08); }

.exam-result-heading { margin: 0; font-size: 1.75rem; font-weight: 900; line-height: 1.3; color: #0f172a; }
.exam-result-layout.passed .exam-result-heading { color: var(--green-deep); }

.exam-score-container { width: 100%; display: flex; justify-content: center; }
.exam-score-circle { position: relative; width: 240px; height: 240px; display: flex; align-items: center; justify-content: center; }
.exam-score-circle svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.exam-score-circle .circle-bg { fill: none; stroke: rgba(215, 25, 32, 0.15); stroke-width: 14; }
.exam-score-circle .circle-fill { fill: none; stroke: #d71920; stroke-width: 14; stroke-linecap: round; transition: stroke-dashoffset 1.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.exam-score-circle.passed .circle-bg { stroke: rgba(21, 128, 61, 0.18); }
.exam-score-circle.passed .circle-fill { stroke: var(--green-deep); }

.score-text { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 4.2rem; font-weight: 900; color: #d71920; letter-spacing: -0.03em; margin: 0; }
.score-text span { font-size: 1.4rem; font-weight: 700; margin-left: 4px; }
.score-text.passed { color: var(--green-deep); }

.result-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; width: 100%; margin: 0.5rem 0; }
.result-info-item { display: flex; align-items: flex-start; gap: 0.85rem; padding: 1rem; border-radius: 16px; background: #f8f9fa; }
.info-icon { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; background: rgba(215, 25, 32, 0.1); flex-shrink: 0; }
.info-icon.passed { background: rgba(21, 128, 61, 0.12); }
.info-icon .material-symbols-outlined { font-size: 24px; color: #d71920; }
.info-icon.passed .material-symbols-outlined { color: var(--green-deep); }
.info-content { text-align: left; }
.info-value { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; }
.info-label { font-size: 0.8rem; color: #64748b; margin: 0.2rem 0 0; }

.result-action-btn { display: inline-flex; align-items: center; gap: 0.6rem; padding: 1rem 1.8rem; border: none; border-radius: 14px; background: var(--green); color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.2s ease; box-shadow: 0 8px 20px rgba(var(--green-rgb), 0.25); }
.result-action-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(var(--green-rgb), 0.35); }
.result-action-btn:active { transform: translateY(0); }

.exam-result-sidebar { display: flex; flex-direction: column; gap: 1.25rem; max-height: 600px; overflow-y: auto; }
.sidebar-header { padding: 1.5rem; background: #fff; border-radius: 20px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06); }
.sidebar-header h3 { margin: 0 0 0.5rem; font-size: 1.1rem; color: #0f172a; }
.sidebar-subtitle { margin: 0; font-size: 0.9rem; color: #64748b; line-height: 1.5; }
.sidebar-content { padding: 1.5rem; background: #fff; border-radius: 20px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06); flex: 1; overflow-y: auto; }

/* Attempts list styles */
.attempts-list { display: flex; flex-direction: column; gap: 0.75rem; padding: 0 0.25rem; }
.attempt-item { padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #fff; cursor: pointer; transition: all 0.2s ease; }
.attempt-item:hover { border-color: var(--green); box-shadow: 0 4px 12px rgba(var(--green-rgb), 0.12); }
.attempt-item.current { border-color: var(--green); background: rgba(var(--green-rgb), 0.02); }

.attempt-header { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.5rem; }
.attempt-badge { font-size: 0.85rem; font-weight: 700; color: #64748b; }
.attempt-score { font-size: 1.3rem; font-weight: 900; color: #d71920; }
.attempt-score.passed { color: var(--green-deep); }

.attempt-meta { display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b; margin-bottom: 0.75rem; gap: 0.5rem; }
.attempt-meta .text-green { color: var(--green-deep); font-weight: 700; }

.attempt-review-btn { display: flex; align-items: center; gap: 0.4rem; width: 100%; padding: 0.65rem; border: 1px solid #dbe6f5; border-radius: 10px; background: #f8fbff; color: #1558b0; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: 0.2s ease; }
.attempt-review-btn:hover { background: #dbeafe; border-color: #60a5fa; }

.no-more-attempts { margin: 0; }

/* Review detail view */
.review-detail-view { display: flex; flex-direction: column; gap: 1rem; }

.review-back-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.6rem 0.9rem; border: none; background: transparent; color: var(--green); font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: 0.2s ease; margin-bottom: 0.5rem; }
.review-back-btn:hover { transform: translateX(-2px); }

.review-attempt-info { padding: 1rem; background: #f8fbff; border-radius: 12px; border-left: 4px solid var(--green); }
.review-info-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9rem; }
.review-info-row span:first-child { color: #64748b; font-weight: 600; }
.review-info-row strong { font-weight: 800; }
.review-info-row .text-green { color: var(--green-deep); }
.review-info-row .text-red { color: #d71920; }

.review-content-toggle { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem 0; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
.toggle-option { display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; color: #475569; cursor: pointer; }
.toggle-option input { accent-color: var(--green); cursor: pointer; }

.review-questions-list { display: flex; flex-direction: column; gap: 0.65rem; max-height: 400px; overflow-y: auto; }
.review-question-item { padding: 0.85rem; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #dbe6f5; }
.review-q-header { display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; }
.q-number { min-width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(var(--green-rgb), 0.1); color: var(--green); font-weight: 700; }
.q-title { flex: 1; color: #475569; font-weight: 600; }
.q-result { font-weight: 800; color: #d71920; }
.q-result.correct { color: var(--green-deep); }

@media (max-width: 1024px) {
  .exam-layout { grid-template-columns: 1fr; }
  .exam-sidebar__card { position: static; }
  .exam-result-layout { grid-template-columns: 1fr; max-height: none; }
  .exam-result-sidebar { max-height: none; }
}

/* Dark mode fix with :global() */
:global([data-theme="dark"]) .exam-shell-inline { background: var(--surface); border-color: rgba(255, 255, 255, 0.1); }
:global([data-theme="dark"]) .exam-topbar { background: rgba(15, 34, 25, 0.92); border-color: rgba(255, 255, 255, 0.12); }
:global([data-theme="dark"]) .exam-topbar__title h1 { color: var(--text); }
:global([data-theme="dark"]) .exam-sidebar__card, :global([data-theme="dark"]) .question-panel, :global([data-theme="dark"]) .exam-overlay-card { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
:global([data-theme="dark"]) .exam-sidebar__card h3 { color: var(--text); }
:global([data-theme="dark"]) .q-nav-btn { background: rgba(255, 255, 255, 0.05); color: var(--text); border-color: rgba(255, 255, 255, 0.1); }
:global([data-theme="dark"]) .question-content { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); color: var(--text); }
:global([data-theme="dark"]) .answer-option { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
:global([data-theme="dark"]) .answer-option:hover { background: rgba(255, 255, 255, 0.08); }
:global([data-theme="dark"]) .exam-text-input { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
:global([data-theme="dark"]) .nav-btn { background: rgba(255, 255, 255, 0.05); color: var(--text); border-color: rgba(255, 255, 255, 0.1); }
:global([data-theme="dark"]) .q-nav-btn.answered { background: rgba(var(--green-rgb), 0.15); color: var(--green); border-color: rgba(var(--green-rgb), 0.3); }
:global([data-theme="dark"]) .legend-answered { background: rgba(var(--green-rgb), 0.12); border-color: rgba(var(--green-rgb), 0.3); }
:global([data-theme="dark"]) .legend-bookmark { background: rgba(245, 158, 11, 0.1); }
:global([data-theme="dark"]) .bookmark-btn { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--muted); }
:global([data-theme="dark"]) .bookmark-btn.is-active { background: rgba(245, 158, 11, 0.12); border-color: #f59e0b; color: #fbbf24; }
:global([data-theme="dark"]) .exam-sidebar__card p { color: var(--muted); }
:global([data-theme="dark"]) .question-nav-legend { color: var(--muted); border-top-color: rgba(255, 255, 255, 0.08); }
:global([data-theme="dark"]) .exam-result-fullscreen { background: var(--bg); }
:global([data-theme="dark"]) .exam-result-icon-wrap { background: var(--surface-strong); }
:global([data-theme="dark"]) .exam-result-stat { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
:global([data-theme="dark"]) .exam-result-title { color: var(--text); }
</style>
