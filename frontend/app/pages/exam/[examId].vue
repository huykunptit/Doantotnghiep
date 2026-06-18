<script setup lang="ts">
definePageMeta({ layout: false }) // Fullscreen exam mode

const route = useRoute()
const token = useAuthTokenCookie()
const user = useAuthUserCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const examId = route.params.examId as string
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const bookmarkStorageKey = computed(() => `exam-bookmarks:${examId}`)

interface ProctorMessage { id: number; type: string; title: string; message: string; created_at: string }

const loading = ref(true)
const examData = ref<any>(null)
const questions = ref<any[]>([])
const attemptId = ref<number | null>(null)
const remainingTime = ref<number | null>(null)
const currentIndex = ref(0)
const answers = ref<Record<string, any>>({})
const bookmarks = ref<Record<string, boolean>>({})
const status = ref('in_progress')
const error = ref('')
const autoSaveStatus = ref('')
const submitting = ref(false)
const showResult = ref(false)
const result = ref<any>(null)
const confirmSubmitOpen = ref(false)
const proctorAlert = ref<ProctorMessage | null>(null)
const lastMessageAt = ref<string | null>(null)

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
const bookmarkedCount = computed(() => Object.values(bookmarks.value).filter(Boolean).length)
const isCurrentBookmarked = computed(() => !!(currentQuestion.value && bookmarks.value[currentQuestion.value.id]))

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

function loadBookmarks() {
  if (typeof window === 'undefined') return
  try {
    const raw = window.localStorage.getItem(bookmarkStorageKey.value)
    bookmarks.value = raw ? JSON.parse(raw) : {}
  } catch { bookmarks.value = {} }
}

function persistBookmarks() {
  if (typeof window === 'undefined') return
  try { window.localStorage.setItem(bookmarkStorageKey.value, JSON.stringify(bookmarks.value)) } catch {}
}

function toggleBookmark(questionId?: number) {
  const id = questionId ?? currentQuestion.value?.id
  if (id === undefined || id === null) return
  bookmarks.value = { ...bookmarks.value, [id]: !bookmarks.value[id] }
  if (!bookmarks.value[id]) delete bookmarks.value[id]
  persistBookmarks()
}

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
    const query = lastMessageAt.value ? `?since=${encodeURIComponent(lastMessageAt.value)}` : ''
    const res = await useApi<any>(`/attempts/${attemptId.value}/status${query}`, { headers: authHeaders() })
    status.value = res.status
    if (res.remaining_time !== null && res.remaining_time !== undefined) { remainingTime.value = res.remaining_time }
    if (res.time_expired) { handleTimeUp() }
    if (Array.isArray(res.messages) && res.messages.length > 0) {
      const newest = res.messages[0]
      proctorAlert.value = newest
      lastMessageAt.value = newest.created_at
    }
  } catch {}
}

function dismissProctorAlert() { proctorAlert.value = null }

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
  loadBookmarks()
  lastMessageAt.value = new Date().toISOString()
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
          <span v-if="bookmarkedCount > 0">★ {{ bookmarkedCount }} câu đã đánh dấu</span>
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
        <span class="material-symbols-outlined exam-overlay-icon">pause_circle</span>
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

    <div v-else-if="showResult" class="exam-result-fullscreen" :class="{ passed: result?.passed }">
      <div class="exam-result-content">
        <div class="exam-result-icon-wrap" :class="{ passed: result?.passed }">
          <span class="material-symbols-outlined">{{ result?.passed ? 'workspace_premium' : 'task_alt' }}</span>
        </div>
        <p class="exam-kicker">Kết quả bài thi</p>
        <h1 class="exam-result-title">{{ result?.passed ? 'Chúc mừng, bạn đã đạt!' : 'Bạn chưa đạt điểm tối thiểu' }}</h1>
        <div v-if="result?.score !== undefined" class="exam-score-wrap">
          <div class="exam-score-ring" :class="{ passed: result?.passed }">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="ring-bg" cx="60" cy="60" r="52" />
              <circle
                class="ring-fg"
                cx="60"
                cy="60"
                r="52"
                :stroke-dasharray="326.7"
                :stroke-dashoffset="326.7 - (Math.min(Math.max(Number(result.score) || 0, 0), 100) / 100) * 326.7"
              />
            </svg>
            <div class="exam-score" :class="{ passed: result?.passed }">{{ result.score }}<span>%</span></div>
          </div>
        </div>
        <p v-if="result?.message" class="exam-result-message">{{ result.message }}</p>
        <div class="exam-result-stats">
          <div class="exam-result-stat">
            <span class="material-symbols-outlined">check_circle</span>
            <div>
              <strong>{{ answeredCount }}/{{ totalQuestions }}</strong>
              <span>Câu đã trả lời</span>
            </div>
          </div>
          <div class="exam-result-stat">
            <span class="material-symbols-outlined">bookmark</span>
            <div>
              <strong>{{ bookmarkedCount }}</strong>
              <span>Câu đã đánh dấu</span>
            </div>
          </div>
        </div>
        <NuxtLink to="/dashboard" class="exam-submit-btn exam-link-btn">
          <span class="material-symbols-outlined">home</span>
          Về trang chủ
        </NuxtLink>
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
                :class="['q-nav-btn', {
                  active: idx === currentIndex,
                  answered: answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== '',
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
                <p class="exam-kicker">Câu hỏi {{ currentIndex + 1 }}/{{ totalQuestions }}</p>
                <h2>{{ examData?.title || 'Bài thi' }}</h2>
              </div>
              <div class="question-panel__head-actions">
                <button
                  type="button"
                  :class="['bookmark-btn', { 'is-active': isCurrentBookmarked }]"
                  :aria-pressed="isCurrentBookmarked"
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
        <div v-if="proctorAlert" class="exam-overlay" @click.self="dismissProctorAlert">
          <div class="exam-overlay-card proctor-alert-card" :class="{ 'is-critical': proctorAlert.type === 'exam_force_stopped' || proctorAlert.title?.includes('nghiêm trọng') }">
            <span class="material-symbols-outlined exam-overlay-icon">{{ proctorAlert.type === 'exam_force_stopped' ? 'gpp_bad' : 'campaign' }}</span>
            <p class="exam-kicker">Thông báo từ giám thị</p>
            <h2>{{ proctorAlert.title }}</h2>
            <p class="proctor-alert-message">{{ proctorAlert.message }}</p>
            <div class="question-nav-buttons question-nav-buttons--modal">
              <button class="exam-submit-btn" @click="dismissProctorAlert">Đã hiểu</button>
            </div>
          </div>
        </div>
      </Teleport>

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
.exam-shell { min-height: 100vh; background: #f8fbff; font-family: 'Be Vietnam Pro', system-ui, sans-serif; color: #14213d; }
.exam-topbar { position: sticky; top: 0; z-index: 40; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(var(--green-rgb), 0.12); background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(18px); }
.exam-topbar__title h1, .exam-result-card h2, .exam-overlay-card h2 { margin: 0; }
.exam-kicker { margin: 0 0 0.35rem; color: var(--green); font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
.exam-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.45rem; color: #64748b; font-size: 0.92rem; }
.exam-topbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.exam-chip, .question-type { border-radius: 999px; background: rgba(var(--green-rgb), 0.05); color: #1558b0; }
.exam-chip { padding: 0.55rem 0.85rem; font-size: 0.82rem; font-weight: 700; }
.exam-timer { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 16px; background: #14213d; color: #fff; box-shadow: 0 10px 24px rgba(20, 33, 61, 0.16); }
.exam-timer.urgent { background: #d71920; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(0.98); } }
.exam-submit-btn, .nav-btn { border: none; cursor: pointer; transition: 0.2s ease; }
.exam-submit-btn { padding: 0.9rem 1.2rem; border-radius: 14px; background: var(--green); color: #fff; font-weight: 800; box-shadow: 0 10px 24px rgba(var(--green-rgb), 0.22); }
.exam-submit-btn:hover, .nav-btn--primary:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.03); }
.exam-submit-btn:disabled, .nav-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.exam-link-btn { display: inline-flex; justify-content: center; text-decoration: none; }
.exam-layout { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 1.5rem; padding: 1.5rem; }
.exam-sidebar { position: sticky; top: 6.5rem; display: flex; flex-direction: column; gap: 1.5rem; height: calc(100vh - 4rem); }
.exam-sidebar__card, .question-panel, .exam-result-card, .exam-overlay-card, .exam-state-card { border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 24px; background: rgba(255, 255, 255, 0.92); box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08); }
.exam-sidebar__card { display: flex; flex-direction: column; max-height: 70%; padding: 1.5rem; }
.exam-sidebar__card h3 { margin: 0 0 0.45rem; flex-shrink: 0; }
.exam-sidebar__card p { margin: 0 0 1rem; color: #64748b; font-size: 0.92rem; line-height: 1.6; flex-shrink: 0; }
.question-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; overflow-y: auto; padding-right: 0.25rem; flex: 1; margin-bottom: 0.85rem; }
.question-nav::-webkit-scrollbar { width: 4px; }
.question-nav::-webkit-scrollbar-track { background: transparent; }
.question-nav::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.q-nav-btn { position: relative; min-height: 44px; border: 1px solid #dbe6f5; border-radius: 14px; background: #fff; color: #475569; font-weight: 800; cursor: pointer; }
.q-nav-btn.active { background: rgba(var(--green-rgb), 0.05); color: var(--green); border-color: #90caf9; }
.q-nav-btn.answered { background: #ecfdf3; color: var(--green-deep); border-color: #86efac; }
.q-nav-btn.active.answered { box-shadow: inset 0 0 0 1px var(--green); }
.q-nav-btn.bookmarked { border-color: #f59e0b; box-shadow: inset 0 0 0 1px #fbbf24; }
.q-nav-flag { position: absolute; top: -6px; right: -6px; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: #f59e0b; color: #fff; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4); font-variation-settings: 'FILL' 1, 'wght' 600; font-size: 14px !important; line-height: 1; }
.question-nav-legend { display: flex; flex-wrap: wrap; gap: 0.85rem; padding-top: 0.85rem; border-top: 1px dashed #dbe6f5; color: #64748b; font-size: 0.78rem; flex-shrink: 0; }
.question-nav-legend span { display: inline-flex; align-items: center; gap: 0.4rem; }
.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; }
.legend-answered { background: #ecfdf3; border: 1px solid #86efac; }
.legend-bookmark { background: #fff; border: 1px solid #f59e0b; box-shadow: inset 0 0 0 1px #fbbf24; }
.question-panel__head-actions { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
.bookmark-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 0.85rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #64748b; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: 0.15s ease; }
.bookmark-btn:hover { border-color: #f59e0b; color: #b45309; background: #fffbeb; }
.bookmark-btn.is-active { background: #fffbeb; border-color: #f59e0b; color: #b45309; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.18); }
.bookmark-icon { font-size: 18px !important; line-height: 1; font-variation-settings: 'FILL' 0, 'wght' 500; }
.bookmark-btn.is-active .bookmark-icon { font-variation-settings: 'FILL' 1, 'wght' 600; }
.proctor-alert-card { border: 2px solid #f59e0b; }
.proctor-alert-card.is-critical { border-color: #d71920; }
.proctor-alert-card .exam-overlay-icon { color: #d97706; }
.proctor-alert-card.is-critical .exam-overlay-icon { color: #d71920; }
.proctor-alert-message { white-space: pre-wrap; line-height: 1.6; color: #14213d; }
.exam-main { min-width: 0; }
.question-panel { padding: 1.5rem; }
.question-panel__header, .question-nav-buttons { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.question-type { padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 700; text-transform: capitalize; }
.question-content { margin: 1.5rem 0; padding: 1.25rem; border-radius: 20px; background: #f8fbff; border: 1px solid #dbe6f5; font-size: 1.05rem; line-height: 1.75; white-space: pre-wrap; }
.answer-list { display: grid; gap: 0.85rem; }
.answer-option { display: flex; align-items: flex-start; gap: 0.8rem; padding: 1rem 1.1rem; border: 1px solid #dbe6f5; border-radius: 18px; background: #fff; cursor: pointer; transition: 0.2s ease; }
.answer-option:hover { border-color: #90caf9; background: #f8fbff; }
.answer-option.selected { border-color: var(--green); background: rgba(var(--green-rgb), 0.05); box-shadow: inset 0 0 0 1px rgba(var(--green-rgb), 0.12); }
.answer-option input { margin-top: 0.2rem; accent-color: var(--green); }
.answer-input { margin-top: 1rem; }
.exam-text-input { width: 100%; min-height: 56px; padding: 1rem 1.1rem; border: 1px solid #cbd5e1; border-radius: 18px; background: #fff; font: inherit; resize: vertical; }
.exam-text-input:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 4px rgba(var(--green-rgb), 0.12); }
.question-nav-buttons { margin-top: 1.5rem; }
.nav-btn { padding: 0.9rem 1.2rem; border-radius: 14px; background: #fff; color: #334155; border: 1px solid #dbe6f5; font-weight: 700; }
.nav-btn:hover:not(:disabled) { border-color: #90caf9; color: var(--green); }
.nav-btn--primary { background: #14213d; color: #fff; border-color: #14213d; }
.exam-overlay { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 23, 42, 0.6); z-index: 60; }
.exam-overlay-card { width: min(100%, 520px); padding: 2rem; text-align: center; }
.exam-overlay-icon { font-size: 3rem; margin-bottom: 0.5rem; display: inline-block; }
.exam-overlay-icon.material-symbols-outlined { font-size: 56px; font-variation-settings: 'FILL' 1, 'wght' 500; color: #d97706; }
.proctor-alert-card .exam-overlay-icon.material-symbols-outlined { color: #d97706; }
.proctor-alert-card.is-critical .exam-overlay-icon.material-symbols-outlined { color: #d71920; }
.exam-submit-modal { text-align: left; }
.question-nav-buttons--modal { justify-content: flex-end; margin-top: 1.25rem; }
.exam-warning { color: #d71920; font-weight: 700; }
.exam-state-card { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 88px); padding: 1.5rem; flex-direction: column; gap: 0.75rem; text-align: center; margin: 1.5rem; padding: 2rem; }
.exam-state-card.is-error { color: #991b1b; }

.exam-result-fullscreen {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.5rem;
  background: radial-gradient(circle at top, #fff5f5 0%, #fef2f2 35%, #fee2e2 100%);
  z-index: 50;
  overflow: auto;
}
.exam-result-fullscreen.passed {
  background: radial-gradient(circle at top, #f0fdf4 0%, #dcfce7 35%, #bbf7d0 100%);
}
.exam-result-content {
  width: min(100%, 640px);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
.exam-result-icon-wrap {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  box-shadow: 0 14px 40px rgba(215, 25, 32, 0.18);
  margin-bottom: 0.5rem;
}
.exam-result-icon-wrap .material-symbols-outlined {
  font-size: 56px;
  color: #d71920;
  font-variation-settings: 'FILL' 1, 'wght' 500;
}
.exam-result-icon-wrap.passed {
  box-shadow: 0 14px 40px rgba(21, 128, 61, 0.22);
}
.exam-result-icon-wrap.passed .material-symbols-outlined { color: var(--green-deep); }
.exam-result-title { margin: 0; font-size: 2rem; line-height: 1.2; }
.exam-score-wrap { margin: 0.5rem 0 0.25rem; }
.exam-score-ring { position: relative; width: 200px; height: 200px; }
.exam-score-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.exam-score-ring .ring-bg { fill: none; stroke: rgba(215, 25, 32, 0.12); stroke-width: 12; }
.exam-score-ring .ring-fg {
  fill: none;
  stroke: #d71920;
  stroke-width: 12;
  stroke-linecap: round;
  transition: stroke-dashoffset 1.2s ease-out;
}
.exam-score-ring.passed .ring-bg { stroke: rgba(21, 128, 61, 0.15); }
.exam-score-ring.passed .ring-fg { stroke: var(--green-deep); }
.exam-score {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  font-size: 3.2rem;
  font-weight: 900;
  color: #d71920;
  letter-spacing: -0.02em;
}
.exam-score span { font-size: 1.2rem; font-weight: 700; margin-left: 2px; }
.exam-score.passed { color: var(--green-deep); }
.exam-result-message {
  margin: 0;
  color: #475569;
  line-height: 1.65;
  max-width: 480px;
}
.exam-result-stats {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.85rem;
  margin: 1rem 0 0.5rem;
}
.exam-result-stat {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.85rem 1.1rem;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(15, 23, 42, 0.08);
  text-align: left;
  min-width: 180px;
}
.exam-result-stat .material-symbols-outlined { font-size: 28px; color: var(--green); }
.exam-result-stat strong { display: block; font-size: 1.1rem; }
.exam-result-stat span { font-size: 0.8rem; color: #64748b; }
.exam-result-fullscreen .exam-submit-btn {
  margin-top: 0.5rem;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.exam-result-fullscreen .exam-submit-btn .material-symbols-outlined { font-size: 20px; }
@media (max-width: 1024px) {
  .exam-layout { grid-template-columns: 1fr; }
  .exam-sidebar { position: static; height: auto; }
  .exam-sidebar__card { max-height: none; }
}
@media (max-width: 640px) {
  .exam-topbar, .exam-layout { padding: 1rem; }
  .exam-topbar__actions, .question-panel__header, .question-nav-buttons, .question-nav-buttons--modal { flex-direction: column; align-items: stretch; }
  .question-nav { grid-template-columns: repeat(5, minmax(0, 1fr)); }
  .question-panel, .exam-sidebar__card, .exam-result-card, .exam-overlay-card { padding: 1.1rem; }
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .exam-shell { background: var(--bg); color: var(--text); }
[data-theme="dark"] .exam-topbar { background: rgba(15, 34, 25, 0.92); border-color: rgba(255, 255, 255, 0.12); }
[data-theme="dark"] .exam-topbar__title h1, [data-theme="dark"] .exam-result-card h2, [data-theme="dark"] .exam-overlay-card h2 { color: var(--text); }
[data-theme="dark"] .exam-sidebar__card, [data-theme="dark"] .question-panel, [data-theme="dark"] .exam-result-card, [data-theme="dark"] .exam-overlay-card, [data-theme="dark"] .exam-state-card { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .exam-sidebar__card h3, [data-theme="dark"] .exam-state-card h2, [data-theme="dark"] .proctor-alert-message { color: var(--text); }
[data-theme="dark"] .q-nav-btn { background: rgba(255, 255, 255, 0.05); color: var(--text); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .question-content { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); color: var(--text); }
[data-theme="dark"] .answer-option { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .answer-option:hover { background: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .exam-text-input { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .nav-btn { background: rgba(255, 255, 255, 0.05); color: var(--text); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .exam-result-fullscreen { background: var(--bg); }
[data-theme="dark"] .exam-result-icon-wrap { background: var(--surface-strong); }
[data-theme="dark"] .exam-result-stat { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .exam-result-title { color: var(--text); }
[data-theme="dark"] .exam-result-message { color: var(--muted); }
[data-theme="dark"] .q-nav-btn.answered { background: rgba(var(--green-rgb), 0.15); color: var(--green); border-color: rgba(var(--green-rgb), 0.3); }
[data-theme="dark"] .legend-answered { background: rgba(var(--green-rgb), 0.12); border-color: rgba(var(--green-rgb), 0.3); }
[data-theme="dark"] .legend-bookmark { background: rgba(245, 158, 11, 0.1); }
[data-theme="dark"] .bookmark-btn { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--muted); }
[data-theme="dark"] .bookmark-btn.is-active { background: rgba(245, 158, 11, 0.12); border-color: #f59e0b; color: #fbbf24; }
[data-theme="dark"] .exam-sidebar__card p { color: var(--muted); }
[data-theme="dark"] .question-nav-legend { color: var(--muted); border-top-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .question-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); }
</style>
