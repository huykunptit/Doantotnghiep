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
  <div class="exam-container">
    <!-- Header bar -->
    <header class="exam-header">
      <div class="exam-header-left">
        <h1>{{ examData?.title || 'Bài thi' }}</h1>
        <span class="exam-info">{{ answeredCount }}/{{ totalQuestions }} câu đã trả lời</span>
      </div>
      <div class="exam-header-right">
        <span v-if="autoSaveStatus" class="auto-save-badge">{{ autoSaveStatus }}</span>
        <div class="exam-timer" :class="{ urgent: timerUrgent }">⏱ {{ timerDisplay }}</div>
        <button class="exam-submit-btn" :disabled="submitting" @click="confirmSubmitOpen = true">Nộp bài</button>
      </div>
    </header>

    <!-- Paused overlay -->
    <div v-if="status === 'paused'" class="exam-overlay">
      <div class="exam-overlay-content">
        <div style="font-size: 3rem;">⏸</div>
        <h2>Bài thi đang bị tạm dừng</h2>
        <p>Giám thị đã tạm dừng bài thi của bạn. Vui lòng chờ.</p>
        <p style="font-size: 0.85rem; color: #999;">Hệ thống sẽ tự động tiếp tục khi giám thị cho phép.</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="exam-loading">Đang tải bài thi...</div>

    <!-- Error -->
    <div v-else-if="error && !showResult" class="exam-error">
      <h2>⚠️ Lỗi</h2>
      <p>{{ error }}</p>
      <NuxtLink to="/dashboard" class="exam-submit-btn" style="text-decoration: none; display: inline-block; margin-top: 1rem;">Về trang chủ</NuxtLink>
    </div>

    <!-- Result -->
    <div v-else-if="showResult" class="exam-result">
      <div class="exam-result-card">
        <div style="font-size: 3rem;">{{ result?.passed ? '🎉' : '📝' }}</div>
        <h2>{{ result?.passed ? 'Chúc mừng, bạn đã đạt!' : 'Chưa đạt điểm tối thiểu' }}</h2>
        <div v-if="result?.score !== undefined" class="exam-score" :class="{ passed: result?.passed }">{{ result.score }}%</div>
        <p>{{ result?.message }}</p>
        <NuxtLink to="/dashboard" class="exam-submit-btn" style="text-decoration: none; display: inline-block; margin-top: 1.5rem;">Về trang chủ</NuxtLink>
      </div>
    </div>

    <!-- Exam content -->
    <template v-else-if="questions.length > 0 && status === 'in_progress'">
      <div class="exam-body">
        <!-- Question navigator -->
        <aside class="exam-sidebar">
          <h3>Câu hỏi</h3>
          <div class="question-nav">
            <button v-for="(q, idx) in questions" :key="q.id"
              :class="['q-nav-btn', { active: idx === currentIndex, answered: answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== '' }]"
              @click="currentIndex = idx">
              {{ idx + 1 }}
            </button>
          </div>
        </aside>

        <!-- Question panel -->
        <main class="exam-main" v-if="currentQuestion">
          <div class="question-header">
            <span class="question-number">Câu {{ currentIndex + 1 }}/{{ totalQuestions }}</span>
            <span class="question-type">{{ currentQuestion.type }}</span>
          </div>
          <div class="question-content">{{ currentQuestion.content }}</div>

          <!-- Single choice / True-false -->
          <div v-if="['single_choice', 'true_false'].includes(currentQuestion.type)" class="answer-list">
            <label v-for="ans in currentQuestion.answers" :key="ans.id"
              :class="['answer-option', { selected: answers[currentQuestion.id] === ans.id }]"
              @click="selectAnswer(currentQuestion.id, ans.id)">
              <input type="radio" :name="'q-' + currentQuestion.id" :value="ans.id" :checked="answers[currentQuestion.id] === ans.id">
              <span>{{ ans.content }}</span>
            </label>
          </div>

          <!-- Multiple choice -->
          <div v-else-if="currentQuestion.type === 'multiple_choice'" class="answer-list">
            <label v-for="ans in currentQuestion.answers" :key="ans.id"
              :class="['answer-option', { selected: (answers[currentQuestion.id] || []).includes(ans.id) }]"
              @click="toggleMultipleChoice(currentQuestion.id, ans.id)">
              <input type="checkbox" :checked="(answers[currentQuestion.id] || []).includes(ans.id)">
              <span>{{ ans.content }}</span>
            </label>
          </div>

          <!-- Short answer / Numerical -->
          <div v-else-if="['short_answer', 'numerical'].includes(currentQuestion.type)" class="answer-input">
            <input :type="currentQuestion.type === 'numerical' ? 'number' : 'text'"
              :value="answers[currentQuestion.id] || ''"
              @input="selectAnswer(currentQuestion.id, ($event.target as HTMLInputElement).value)"
              :placeholder="currentQuestion.type === 'numerical' ? 'Nhập số...' : 'Nhập câu trả lời...'"
              class="exam-text-input">
          </div>

          <!-- Essay -->
          <div v-else-if="currentQuestion.type === 'essay'" class="answer-input">
            <textarea :value="answers[currentQuestion.id] || ''"
              @input="selectAnswer(currentQuestion.id, ($event.target as HTMLTextAreaElement).value)"
              placeholder="Nhập bài tự luận..." rows="8" class="exam-text-input"></textarea>
          </div>

          <!-- Navigation -->
          <div class="question-nav-buttons">
            <button :disabled="currentIndex === 0" class="nav-btn" @click="currentIndex--">← Câu trước</button>
            <button :disabled="currentIndex >= totalQuestions - 1" class="nav-btn" @click="currentIndex++">Câu sau →</button>
          </div>
        </main>
      </div>
    </template>

    <!-- Confirm submit modal -->
    <Teleport to="body">
      <div v-if="confirmSubmitOpen" class="exam-overlay" @click.self="confirmSubmitOpen = false">
        <div class="exam-overlay-content" style="max-width: 420px;">
          <h2>Xác nhận nộp bài</h2>
          <p>Bạn đã trả lời <strong>{{ answeredCount }}/{{ totalQuestions }}</strong> câu hỏi.</p>
          <p v-if="answeredCount < totalQuestions" style="color: #f44336;">⚠ Còn {{ totalQuestions - answeredCount }} câu chưa trả lời!</p>
          <div style="display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: center;">
            <button class="nav-btn" @click="confirmSubmitOpen = false">Quay lại</button>
            <button class="exam-submit-btn" :disabled="submitting" @click="submitExam">{{ submitting ? 'Đang nộp...' : 'Xác nhận nộp' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.exam-container { min-height: 100vh; background: #f5f5f5; font-family: 'Inter', system-ui, sans-serif; }
.exam-header { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.5rem; background: #1a1a2e; color: #fff; position: sticky; top: 0; z-index: 100; }
.exam-header-left h1 { font-size: 1rem; margin: 0; }
.exam-info { font-size: 0.8rem; opacity: 0.7; }
.exam-header-right { display: flex; align-items: center; gap: 1rem; }
.auto-save-badge { font-size: 0.75rem; background: rgba(255,255,255,0.15); padding: 4px 10px; border-radius: 12px; }
.exam-timer { font-family: 'Courier New', monospace; font-size: 1.2rem; font-weight: 800; padding: 6px 16px; background: rgba(255,255,255,0.1); border-radius: 8px; }
.exam-timer.urgent { color: #ff4444; background: rgba(255,68,68,0.15); animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
.exam-submit-btn { padding: 8px 20px; background: #4caf50; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.9rem; }
.exam-submit-btn:hover { background: #43a047; }
.exam-submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.exam-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 200; }
.exam-overlay-content { background: #fff; border-radius: 16px; padding: 3rem; text-align: center; max-width: 480px; }
.exam-overlay-content h2 { margin: 1rem 0 0.5rem; }

.exam-loading, .exam-error { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; font-size: 1.1rem; }
.exam-result { display: flex; align-items: center; justify-content: center; min-height: 80vh; }
.exam-result-card { background: #fff; border-radius: 16px; padding: 3rem; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; }
.exam-score { font-size: 3rem; font-weight: 900; color: #f44336; margin: 1rem 0; }
.exam-score.passed { color: #4caf50; }

.exam-body { display: flex; min-height: calc(100vh - 56px); }
.exam-sidebar { width: 200px; background: #fff; padding: 1.5rem; border-right: 1px solid #e0e0e0; }
.exam-sidebar h3 { font-size: 0.85rem; margin: 0 0 1rem; color: #666; text-transform: uppercase; letter-spacing: 0.05em; }
.question-nav { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.q-nav-btn { width: 40px; height: 40px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 700; font-size: 0.85rem; transition: all 0.15s; }
.q-nav-btn.active { border-color: #1976d2; background: #e3f2fd; color: #1976d2; }
.q-nav-btn.answered { background: #e8f5e9; border-color: #4caf50; color: #2e7d32; }
.q-nav-btn.active.answered { border-color: #1976d2; }

.exam-main { flex: 1; padding: 2rem 3rem; max-width: 800px; }
.question-header { display: flex; justify-content: space-between; margin-bottom: 1rem; }
.question-number { font-weight: 700; color: #1976d2; }
.question-type { font-size: 0.8rem; background: #e3f2fd; color: #1565c0; padding: 4px 10px; border-radius: 12px; }
.question-content { font-size: 1.1rem; line-height: 1.6; margin-bottom: 1.5rem; padding: 1.5rem; background: #fff; border-radius: 12px; border: 1px solid #e0e0e0; }

.answer-list { display: flex; flex-direction: column; gap: 0.5rem; }
.answer-option { display: flex; align-items: center; gap: 0.75rem; padding: 1rem 1.25rem; background: #fff; border: 2px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.15s; }
.answer-option:hover { border-color: #90caf9; background: #f5f9ff; }
.answer-option.selected { border-color: #1976d2; background: #e3f2fd; }
.answer-option input { accent-color: #1976d2; }

.answer-input { margin-bottom: 1.5rem; }
.exam-text-input { width: 100%; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; font-family: inherit; resize: vertical; }
.exam-text-input:focus { outline: none; border-color: #1976d2; }

.question-nav-buttons { display: flex; justify-content: space-between; margin-top: 2rem; }
.nav-btn { padding: 10px 24px; background: #fff; border: 2px solid #e0e0e0; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.15s; }
.nav-btn:hover:not(:disabled) { border-color: #1976d2; color: #1976d2; }
.nav-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
