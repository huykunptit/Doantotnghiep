<template>
  <div class="student-quiz-container">
    <div v-if="loading" class="quiz-loading">
      <div class="premium-loader"></div>
      <p>Đang chuẩn bị bài tập cho bạn...</p>
    </div>

    <div v-else-if="!quiz" class="quiz-empty-state">
      <div class="empty-icon shadow-premium">
        <i class="fas fa-file-signature"></i>
      </div>
      <p>Không có bài tập cho bài học này.</p>
    </div>

    <div v-else class="quiz-box shadow-premium">
      <!-- Quiz Header -->
      <div class="quiz-header">
        <div class="header-main">
          <h3>{{ quiz.title }}</h3>
          <p v-if="quiz.description">{{ quiz.description }}</p>
          <div v-if="warnings > 0" class="mt-2 text-xs font-bold text-red-500">
            <i class="fas fa-exclamation-triangle"></i> Cảnh báo gian lận: Rời khỏi màn hình {{ warnings }} lần
          </div>
        </div>
        <div class="header-stats">
          <div class="stat-item"><i class="fas fa-list-ul"></i> {{ questions?.length }} câu</div>
          <div class="stat-item"><i class="fas fa-bullseye"></i> Đạt {{ quiz.pass_score }}%</div>
          <div v-if="quiz.time_limit" class="stat-item timer" :class="{ 'text-red-500': timeRemaining < 60 && timeRemaining > 0 }">
            <i class="fas fa-clock"></i> {{ formattedTime }}
          </div>
        </div>
      </div>

      <!-- Result View -->
      <div v-if="result" class="quiz-result-card animate-scale-up">
        <div class="result-icon" :class="result.passed ? 'success' : 'fail'">
          <i :class="result.passed ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
        </div>
        <h4>{{ result.passed ? 'Bạn đã vượt qua!' : 'Chưa đạt yêu cầu' }}</h4>
        <div class="score-display">
          <span class="label">Điểm số:</span>
          <span class="value">{{ result.score }}%</span>
        </div>
        <button @click="resetQuiz" class="btn-retry">Làm lại bài tập</button>
      </div>

      <!-- Questions List -->
      <div v-else class="quiz-content">
        <div class="questions-flow">
          <div v-for="(q, idx) in questions" :key="q.id" class="question-card shadow-soft">
            <div class="q-header">
              <span class="q-number">Câu {{ idx + 1 }}</span>
              <span class="q-type">{{ getTypeText(q.type) }}</span>
            </div>
            
            <h4 class="q-title">{{ q.content }}</h4>

            <!-- Choice Types (Single/Multiple) -->
            <div v-if="q.type === 'single_choice' || q.type === 'multiple_choice'" class="choices-list">
              <label 
                v-for="ans in q.answers" 
                :key="ans.id" 
                class="choice-item"
                :class="{ selected: isSelected(q, ans.id) }"
              >
                <input 
                  :type="q.type === 'single_choice' ? 'radio' : 'checkbox'" 
                  :name="`q_${q.id}`"
                  :value="ans.id"
                  @change="toggleChoice(q, ans.id)"
                  class="hidden-input"
                >
                <div class="choice-indicator"></div>
                <div class="choice-text">{{ ans.content }}</div>
              </label>
            </div>

            <!-- Essay Type -->
            <div v-else-if="q.type === 'essay' || q.type === 'short_answer'" class="essay-input">
              <textarea 
                v-model="userAnswers[q.id]" 
                placeholder="Nhập câu trả lời của bạn tại đây..."
                rows="4"
              ></textarea>
            </div>

            <!-- Ordering Type -->
            <div v-else-if="q.type === 'ordering'" class="ordering-wrap">
               <p class="instr mb-3 text-sm text-gray-500">Sắp xếp theo đúng thứ tự (Dùng nút lên/xuống):</p>
               <div class="sortable-list space-y-2">
                 <div v-for="(ans, sIdx) in (userAnswers[q.id] || q.answers)" :key="ans.id" class="sort-item flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg p-3">
                   <div class="flex items-center gap-3">
                     <span class="font-bold text-gray-400">{{ sIdx + 1 }}.</span>
                     <span class="text-gray-700 font-medium">{{ ans.content }}</span>
                   </div>
                   <div class="flex flex-col">
                     <button type="button" @click="moveOrder(q.id, sIdx, -1)" :disabled="sIdx === 0" class="text-gray-400 hover:text-blue-500 disabled:opacity-30">
                       <i class="fas fa-chevron-up"></i>
                     </button>
                     <button type="button" @click="moveOrder(q.id, sIdx, 1)" :disabled="sIdx === (userAnswers[q.id] || q.answers).length - 1" class="text-gray-400 hover:text-blue-500 disabled:opacity-30">
                       <i class="fas fa-chevron-down"></i>
                     </button>
                   </div>
                 </div>
               </div>
            </div>
          </div>
        </div>

        <div class="quiz-footer">
          <p class="completion-track">Bạn đã trả lời {{ answeredCount }} / {{ questions.length }} câu</p>
          <button @click="submitQuiz" class="btn-submit" :disabled="submitting">
            {{ submitting ? 'Đang nộp bài...' : 'Hoàn thành & Nộp bài' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
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

const timeRemaining = ref(0)
const timerInterval = ref<any>(null)
const warnings = ref(0)

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
    essay: 'Tự luận',
    ordering: 'Sắp xếp',
    matching: 'Nối cặp',
    short_answer: 'Trả lời ngắn'
  }
  return map[type] || 'Câu hỏi'
}

function isSelected(q: any, ansId: number) {
  const val = userAnswers.value[q.id]
  if (q.type === 'multiple_choice') return Array.isArray(val) && val.includes(ansId)
  return val === ansId
}

function toggleChoice(q: any, ansId: number) {
  if (q.type === 'single_choice') {
    userAnswers.value[q.id] = ansId
  } else if (q.type === 'multiple_choice') {
    if (!Array.isArray(userAnswers.value[q.id])) userAnswers.value[q.id] = []
    const idx = userAnswers.value[q.id].indexOf(ansId)
    if (idx > -1) userAnswers.value[q.id].splice(idx, 1)
    else userAnswers.value[q.id].push(ansId)
  }
}

const answeredCount = computed(() => {
  return Object.values(userAnswers.value).filter(v => {
    if (Array.isArray(v)) return v.length > 0
    return v !== null && v !== '' && v !== undefined
  }).length
})

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

function handleVisibilityChange() {
  if (document.hidden && !result.value && quiz.value) {
    warnings.value++
    alert(`CẢNH BÁO GIAN LẬN (${warnings.value}): Bạn vừa chuyển tab hoặc rời khỏi cửa sổ làm bài!`)
  }
}

async function loadQuiz() {
  loading.value = true
  result.value = null
  userAnswers.value = {}
  try {
    const res = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/quiz`, { headers: authHeaders() })
    quiz.value = res.quiz
    questions.value = res.questions
    attemptId.value = res.attempt_id

    questions.value.forEach((q: any) => {
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
  if (!auto && answeredCount.value < questions.value.length) {
    if (!confirm('Bạn chưa trả lời hết các câu hỏi. Vẫn muốn nộp bài?')) return
  }

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
    alert('Không thể nộp bài, vui lòng thử lại sau.')
  } finally {
    submitting.value = false
  }
}

function resetQuiz() {
  stopTimer()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
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
.student-quiz-container { min-height: 200px; }

/* States */
.quiz-loading { padding: 4rem 1rem; text-align: center; color: #64748b; }
.premium-loader { width: 40px; height: 40px; border: 4px solid #f1f5f9; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }

.quiz-empty-state { padding: 4rem 1rem; text-align: center; color: #94a3b8; }
.empty-icon { width: 64px; height: 64px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 1.5rem; color: #cbd5e1; }

/* Box */
.quiz-box { background: white; border-radius: 16px; overflow: hidden; }

/* Header */
.quiz-header { background: #f8fafc; padding: 2rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-start; gap: 2rem; }
.header-main h3 { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; }
.header-main p { font-size: 0.875rem; color: #64748b; }
.header-stats { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.stat-item { padding: 0.25rem 0.75rem; border-radius: 99px; background: white; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 0.5rem; }

/* Questions Content */
.quiz-content { padding: 2rem; }
.questions-flow { display: flex; flex-direction: column; gap: 2.5rem; }

.question-card { padding: 1.5rem; border-radius: 12px; border: 1px solid #f1f5f9; background: #fff; }
.q-header { display: flex; justify-content: space-between; margin-bottom: 1rem; }
.q-number { font-size: 0.75rem; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.05em; }
.q-type { font-size: 0.7rem; color: #94a3b8; background: #f8fafc; padding: 2px 8px; border-radius: 4px; }
.q-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; line-height: 1.5; }

/* Choices */
.choice-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 10px; border: 2px solid #f1f5f9; cursor: pointer; transition: all 0.2s; position: relative; }
.choice-item:hover { border-color: #e2e8f0; background: #f8fafc; }
.choice-item.selected { border-color: #3b82f6; background: #eff6ff; }
.hidden-input { position: absolute; opacity: 0; }

.choice-indicator { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 50%; position: relative; flex-shrink: 0; }
.choice-item.selected .choice-indicator { border-color: #3b82f6; background: #3b82f6; }
.choice-item.selected .choice-indicator::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 8px; height: 8px; background: white; border-radius: 50%; }

.choice-text { font-size: 0.95rem; color: #475569; font-weight: 500; }
.choice-item.selected .choice-text { color: #1e293b; font-weight: 600; }

/* Essay */
.essay-input textarea { width: 100%; border-radius: 12px; border: 2px solid #f1f5f9; padding: 1rem; font-size: 0.95rem; color: #1e293b; transition: all 0.2s; }
.essay-input textarea:focus { border-color: #3b82f6; outline: none; background: #fafafa; }

/* Ordering */
.sort-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.9rem; color: #475569; cursor: move; }
.sort-item i { color: #cbd5e1; }
.hint-dev { font-size: 0.75rem; color: #94a3b8; font-style: italic; margin-top: 1rem; }

/* Footer */
.quiz-footer { margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.completion-track { font-size: 0.875rem; font-weight: 700; color: #64748b; }
.btn-submit { padding: 0.75rem 2rem; background: #111827; color: white; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s; }
.btn-submit:hover:not(:disabled) { background: #1e293b; transform: translateY(-1px); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

/* Result Card */
.quiz-result-card { padding: 3rem; text-align: center; }
.result-icon { font-size: 4rem; margin-bottom: 1.5rem; }
.result-icon.success { color: #10b981; }
.result-icon.fail { color: #ef4444; }
.quiz-result-card h4 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; }
.score-display { margin-bottom: 2rem; }
.score-display .label { font-size: 1.125rem; color: #64748b; margin-right: 0.5rem; }
.score-display .value { font-size: 2.5rem; font-weight: 900; color: #1e293b; }
.btn-retry { padding: 0.75rem 1.5rem; background: #f1f5f9; color: #475569; border-radius: 10px; font-weight: 700; border: none; cursor: pointer; }
.btn-retry:hover { background: #e2e8f0; }

.animate-scale-up { animation: scaleUp 0.3s ease-out; }
@keyframes scaleUp { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.shadow-premium { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02); }
.shadow-soft { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
</style>
