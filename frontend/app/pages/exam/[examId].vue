<script setup lang="ts">
definePageMeta({ layout: false }) // Fullscreen exam mode

const route = useRoute()
const token = useAuthTokenCookie()
const user = useAuthUserCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const examId = route.params.examId as string
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const examData = ref<any>(null)
const questions = ref<any[]>([])
const attemptId = ref<number | null>(null)
const remainingTime = ref<number | null>(null)
const currentIndex = ref(0)
const answers = ref<Record<string, any>>({})
const status = ref('in_progress')
const error = ref('')
const autoSaveStatus = ref('')
const submitting = ref(false)
const showResult = ref(false)
const result = ref<any>(null)
const confirmSubmitOpen = ref(false)

// Timer
const timerInterval = ref<any>(null)
const timerDisplay = computed(() => {
  if (remainingTime.value === null) return '∞'
  const m = Math.floor(remainingTime.value / 60)
  const s = remainingTime.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})
const timerUrgent = computed(() => remainingTime.value !== null && remainingTime.value < 300)
const currentQuestion = computed(() => questions.value[currentIndex.value] || null)
const answeredCount = computed(() => Object.keys(answers.value).filter(k => answers.value[k] !== null && answers.value[k] !== undefined && answers.value[k] !== '').length)
const totalQuestions = computed(() => questions.value.length)

async function loadExam() {
  try {
    const data = await useApi<any>(`/exams/${examId}/start`, { headers: authHeaders() })
    examData.value = data.exam
    questions.value = data.questions || []
    attemptId.value = data.attempt_id
    remainingTime.value = data.remaining_time
    status.value = data.status || 'in_progress'
    // Restore saved answers
    if (data.saved_answers) { answers.value = { ...data.saved_answers } }
    startTimer()
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải bài thi.'
  } finally { loading.value = false }
}

function startTimer() {
  if (remainingTime.value === null) return
  timerInterval.value = setInterval(() => {
    if (status.value === 'paused') return
    if (remainingTime.value !== null && remainingTime.value > 0) {
      remainingTime.value--
    } else if (remainingTime.value !== null && remainingTime.value <= 0) {
      handleTimeUp()
    }
  }, 1000)
}

async function handleTimeUp() {
  clearInterval(timerInterval.value)
  await submitExam()
}

function selectAnswer(questionId: number, value: any) {
  answers.value[questionId] = value
  debouncedAutoSave()
}

function toggleMultipleChoice(questionId: number, answerId: number) {
  const current = answers.value[questionId] || []
  const arr = Array.isArray(current) ? [...current] : []
  const idx = arr.indexOf(answerId)
  if (idx > -1) arr.splice(idx, 1); else arr.push(answerId)
  answers.value[questionId] = arr
  debouncedAutoSave()
}

// Auto-save debounce
let autoSaveTimer: any = null
function debouncedAutoSave() {
  clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(autoSave, 5000) // 5s debounce
}

async function autoSave() {
  if (!attemptId.value || status.value !== 'in_progress') return
  autoSaveStatus.value = 'Đang lưu...'
  try {
    const res = await useApi<any>(`/attempts/${attemptId.value}/auto-save`, {
      method: 'POST', headers: authHeaders(), body: { answers: answers.value }
    })
    autoSaveStatus.value = 'Đã lưu tự động'
    if (res.status === 'paused') { status.value = 'paused' }
    if (res.remaining_time !== undefined) { remainingTime.value = res.remaining_time }
    setTimeout(() => { autoSaveStatus.value = '' }, 3000)
  } catch (e: any) {
    if (e?.data?.status === 'paused') { status.value = 'paused' }
    autoSaveStatus.value = 'Lỗi lưu tự động'
  }
}

// Poll status every 10s
const statusPollInterval = ref<any>(null)
async function pollStatus() {
  if (!attemptId.value) return
  try {
    const res = await useApi<any>(`/attempts/${attemptId.value}/status`, { headers: authHeaders() })
    status.value = res.status
    if (res.remaining_time !== null && res.remaining_time !== undefined) { remainingTime.value = res.remaining_time }
    if (res.time_expired) { handleTimeUp() }
  } catch {}
}

async function submitExam() {
  if (!attemptId.value || submitting.value) return
  submitting.value = true; confirmSubmitOpen.value = false
  try {
    result.value = await useApi<any>(`/exams/${examId}/submit`, {
      method: 'POST', headers: authHeaders(),
      body: { attempt_id: attemptId.value, answers: answers.value }
    })
    showResult.value = true
    status.value = 'submitted'
    clearInterval(timerInterval.value)
    clearInterval(statusPollInterval.value)
  } catch (e: any) { error.value = e?.data?.message || 'Lỗi nộp bài.' }
  finally { submitting.value = false }
}

onMounted(async () => {
  await loadExam()
  statusPollInterval.value = setInterval(pollStatus, 10000)
})
onUnmounted(() => {
  clearInterval(timerInterval.value)
  clearInterval(statusPollInterval.value)
  clearTimeout(autoSaveTimer)
})
</script>

<template>
  <div class="exam-shell">
    <header class="exam-topbar">
      <div class="exam-topbar__title">
        <p class="exam-kicker">PTIT Exam Workspace</p>
        <h1>{{ examData?.title || 'Bài thi' }}</h1>
        <div class="exam-meta">
          <span>{{ answeredCount }}/{{ totalQuestions }} câu đã trả lời</span>
          <span>{{ totalQuestions - answeredCount }} câu chưa trả lời</span>
        </div>
      </div>

      <div class="exam-topbar__actions">
        <span v-if="autoSaveStatus" class="exam-chip">{{ autoSaveStatus }}</span>
        <div class="exam-timer" :class="{ urgent: timerUrgent }">
          <span class="material-symbols-outlined">timer</span>
          <strong>{{ timerDisplay }}</strong>
        </div>
        <button class="exam-submit-btn" :disabled="submitting" @click="confirmSubmitOpen = true">Nộp bài</button>
      </div>
    </header>

    <div v-if="status === 'paused'" class="exam-overlay">
      <div class="exam-overlay-card">
        <div class="exam-overlay-icon">⏸</div>
        <h2>Bài thi đang tạm dừng</h2>
        <p>Giám thị đã tạm dừng bài thi của bạn. Vui lòng chờ đến khi hệ thống cho phép tiếp tục.</p>
      </div>
    </div>

    <div v-if="loading" class="exam-state-card">Đang tải bài thi...</div>

    <div v-else-if="error && !showResult" class="exam-state-card is-error">
      <h2>Không thể tải bài thi</h2>
      <p>{{ error }}</p>
      <NuxtLink to="/dashboard" class="exam-submit-btn exam-link-btn">Về trang chủ</NuxtLink>
    </div>

    <div v-else-if="showResult" class="exam-result-wrap">
      <div class="exam-result-card">
        <div class="exam-result-icon">{{ result?.passed ? '🎉' : '📝' }}</div>
        <p class="exam-kicker">Kết quả bài thi</p>
        <h2>{{ result?.passed ? 'Chúc mừng, bạn đã đạt!' : 'Bạn chưa đạt điểm tối thiểu' }}</h2>
        <div v-if="result?.score !== undefined" class="exam-score" :class="{ passed: result?.passed }">{{ result.score }}%</div>
        <p>{{ result?.message }}</p>
        <NuxtLink to="/dashboard" class="exam-submit-btn exam-link-btn">Về trang chủ</NuxtLink>
      </div>
    </div>

    <template v-else-if="questions.length > 0 && status === 'in_progress'">
      <div class="exam-layout">
        <aside class="exam-sidebar">
          <div class="exam-sidebar__card">
            <h3>Điều hướng câu hỏi</h3>
            <p>Chọn nhanh câu hỏi cần làm hoặc kiểm tra lại.</p>
            <div class="question-nav">
              <button
                v-for="(q, idx) in questions"
                :key="q.id"
                :class="['q-nav-btn', { active: idx === currentIndex, answered: answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== '' }]"
                @click="currentIndex = idx"
              >
                {{ idx + 1 }}
              </button>
            </div>
          </div>
        </aside>

        <main v-if="currentQuestion" class="exam-main">
          <div class="question-panel">
            <div class="question-panel__header">
              <div>
                <p class="exam-kicker">Câu hỏi {{ currentIndex + 1 }}/{{ totalQuestions }}</p>
                <h2>{{ examData?.title || 'Bài thi' }}</h2>
              </div>
              <span class="question-type">{{ currentQuestion.type }}</span>
            </div>

            <div class="question-content">{{ currentQuestion.content }}</div>

            <div v-if="['single_choice', 'true_false'].includes(currentQuestion.type)" class="answer-list">
              <label
                v-for="ans in currentQuestion.answers"
                :key="ans.id"
                :class="['answer-option', { selected: answers[currentQuestion.id] === ans.id }]"
                @click="selectAnswer(currentQuestion.id, ans.id)"
              >
                <input type="radio" :name="'q-' + currentQuestion.id" :value="ans.id" :checked="answers[currentQuestion.id] === ans.id">
                <span>{{ ans.content }}</span>
              </label>
            </div>

            <div v-else-if="currentQuestion.type === 'multiple_choice'" class="answer-list">
              <label
                v-for="ans in currentQuestion.answers"
                :key="ans.id"
                :class="['answer-option', { selected: (answers[currentQuestion.id] || []).includes(ans.id) }]"
                @click="toggleMultipleChoice(currentQuestion.id, ans.id)"
              >
                <input type="checkbox" :checked="(answers[currentQuestion.id] || []).includes(ans.id)">
                <span>{{ ans.content }}</span>
              </label>
            </div>

            <div v-else-if="['short_answer', 'numerical'].includes(currentQuestion.type)" class="answer-input">
              <input
                :type="currentQuestion.type === 'numerical' ? 'number' : 'text'"
                :value="answers[currentQuestion.id] || ''"
                :placeholder="currentQuestion.type === 'numerical' ? 'Nhập số...' : 'Nhập câu trả lời...'"
                class="exam-text-input"
                @input="selectAnswer(currentQuestion.id, ($event.target as HTMLInputElement).value)"
              >
            </div>

            <div v-else-if="currentQuestion.type === 'essay'" class="answer-input">
              <textarea
                :value="answers[currentQuestion.id] || ''"
                rows="8"
                class="exam-text-input"
                placeholder="Nhập bài tự luận..."
                @input="selectAnswer(currentQuestion.id, ($event.target as HTMLTextAreaElement).value)"
              />
            </div>

            <div class="question-nav-buttons">
              <button :disabled="currentIndex === 0" class="nav-btn" @click="currentIndex--">← Câu trước</button>
              <button :disabled="currentIndex >= totalQuestions - 1" class="nav-btn nav-btn--primary" @click="currentIndex++">Câu sau →</button>
            </div>
          </div>
        </main>
      </div>

      <Teleport to="body">
        <div v-if="confirmSubmitOpen" class="exam-overlay" @click.self="confirmSubmitOpen = false">
          <div class="exam-overlay-card exam-submit-modal">
            <h2>Xác nhận nộp bài</h2>
            <p>Bạn đã hoàn thành <strong>{{ answeredCount }}/{{ totalQuestions }}</strong> câu hỏi.</p>
            <p v-if="answeredCount < totalQuestions" class="exam-warning">Còn {{ totalQuestions - answeredCount }} câu chưa trả lời.</p>
            <div class="question-nav-buttons question-nav-buttons--modal">
              <button class="nav-btn" @click="confirmSubmitOpen = false">Quay lại</button>
              <button class="exam-submit-btn" :disabled="submitting" @click="submitExam">{{ submitting ? 'Đang nộp...' : 'Xác nhận nộp' }}</button>
            </div>
          </div>
        </div>
      </Teleport>
    </template>
  </div>
</template>

<style scoped>
.exam-shell { min-height: 100vh; background: #f8fbff; font-family: 'Inter', system-ui, sans-serif; color: #14213d; }
.exam-topbar { position: sticky; top: 0; z-index: 40; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(25, 118, 210, 0.12); background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(18px); }
.exam-topbar__title h1, .exam-result-card h2, .exam-overlay-card h2 { margin: 0; }
.exam-kicker { margin: 0 0 0.35rem; color: #1976d2; font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
.exam-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.45rem; color: #64748b; font-size: 0.92rem; }
.exam-topbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.exam-chip, .question-type { border-radius: 999px; background: #e8f1ff; color: #1558b0; }
.exam-chip { padding: 0.55rem 0.85rem; font-size: 0.82rem; font-weight: 700; }
.exam-timer { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 16px; background: #14213d; color: #fff; box-shadow: 0 10px 24px rgba(20, 33, 61, 0.16); }
.exam-timer.urgent { background: #d71920; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(0.98); } }
.exam-submit-btn, .nav-btn { border: none; cursor: pointer; transition: 0.2s ease; }
.exam-submit-btn { padding: 0.9rem 1.2rem; border-radius: 14px; background: #2f7a45; color: #fff; font-weight: 800; box-shadow: 0 10px 24px rgba(47, 122, 69, 0.22); }
.exam-submit-btn:hover, .nav-btn--primary:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.03); }
.exam-submit-btn:disabled, .nav-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.exam-link-btn { display: inline-flex; justify-content: center; text-decoration: none; }
.exam-layout { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 1.5rem; padding: 1.5rem; }
.exam-sidebar__card, .question-panel, .exam-result-card, .exam-overlay-card, .exam-state-card { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 24px; background: rgba(255, 255, 255, 0.92); box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08); }
.exam-sidebar__card { position: sticky; top: 6.5rem; padding: 1.25rem; }
.exam-sidebar__card h3 { margin: 0 0 0.45rem; }
.exam-sidebar__card p { margin: 0 0 1rem; color: #64748b; font-size: 0.92rem; line-height: 1.6; }
.question-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; }
.q-nav-btn { min-height: 44px; border: 1px solid #dbe6f5; border-radius: 14px; background: #fff; color: #475569; font-weight: 800; }
.q-nav-btn.active { background: #e8f1ff; color: #1976d2; border-color: #90caf9; }
.q-nav-btn.answered { background: #ecfdf3; color: #15803d; border-color: #86efac; }
.q-nav-btn.active.answered { box-shadow: inset 0 0 0 1px #1976d2; }
.exam-main { min-width: 0; }
.question-panel { padding: 1.5rem; }
.question-panel__header, .question-nav-buttons { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.question-type { padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 700; text-transform: capitalize; }
.question-content { margin: 1.5rem 0; padding: 1.25rem; border-radius: 20px; background: #f8fbff; border: 1px solid #dbe6f5; font-size: 1.05rem; line-height: 1.75; white-space: pre-wrap; }
.answer-list { display: grid; gap: 0.85rem; }
.answer-option { display: flex; align-items: flex-start; gap: 0.8rem; padding: 1rem 1.1rem; border: 1px solid #dbe6f5; border-radius: 18px; background: #fff; cursor: pointer; transition: 0.2s ease; }
.answer-option:hover { border-color: #90caf9; background: #f8fbff; }
.answer-option.selected { border-color: #1976d2; background: #e8f1ff; box-shadow: inset 0 0 0 1px rgba(25, 118, 210, 0.12); }
.answer-option input { margin-top: 0.2rem; accent-color: #1976d2; }
.answer-input { margin-top: 1rem; }
.exam-text-input { width: 100%; min-height: 56px; padding: 1rem 1.1rem; border: 1px solid #cbd5e1; border-radius: 18px; background: #fff; font: inherit; resize: vertical; }
.exam-text-input:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.12); }
.question-nav-buttons { margin-top: 1.5rem; }
.nav-btn { padding: 0.9rem 1.2rem; border-radius: 14px; background: #fff; color: #334155; border: 1px solid #dbe6f5; font-weight: 700; }
.nav-btn:hover:not(:disabled) { border-color: #90caf9; color: #1976d2; }
.nav-btn--primary { background: #14213d; color: #fff; border-color: #14213d; }
.exam-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, 0.6); z-index: 60; }
.exam-overlay-card { width: min(100%, 520px); padding: 2rem; text-align: center; }
.exam-overlay-icon, .exam-result-icon { font-size: 3rem; margin-bottom: 0.5rem; }
.exam-submit-modal { text-align: left; }
.question-nav-buttons--modal { justify-content: flex-end; margin-top: 1.25rem; }
.exam-warning { color: #d71920; font-weight: 700; }
.exam-state-card, .exam-result-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 88px); padding: 1.5rem; }
.exam-state-card { flex-direction: column; gap: 0.75rem; text-align: center; margin: 1.5rem; padding: 2rem; }
.exam-state-card.is-error { color: #991b1b; }
.exam-result-card { width: min(100%, 560px); padding: 2rem; text-align: center; }
.exam-score { margin: 1rem 0; font-size: 3.2rem; font-weight: 900; color: #d71920; }
.exam-score.passed { color: #15803d; }
@media (max-width: 1024px) {
  .exam-layout { grid-template-columns: 1fr; }
  .exam-sidebar__card { position: static; }
}
@media (max-width: 640px) {
  .exam-topbar, .exam-layout { padding: 1rem; }
  .exam-topbar__actions, .question-panel__header, .question-nav-buttons, .question-nav-buttons--modal { flex-direction: column; align-items: stretch; }
  .question-nav { grid-template-columns: repeat(5, minmax(0, 1fr)); }
  .question-panel, .exam-sidebar__card, .exam-result-card, .exam-overlay-card { padding: 1.1rem; }
}
</style>
