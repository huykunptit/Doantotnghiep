<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

definePageMeta({ middleware: 'instructor', layout: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const courseId = Number(route.params.id)
const lessonId = Number(route.params.lessonId)
const loading = ref(true)
const saving = ref(false)
const success = ref(false)
const lesson = ref<any>(null)
const banks = ref<any[]>([])
const bankQuestions = ref<any[]>([])
const selectedBankId = ref<number | null>(null)
const bankSearch = ref('')

const quiz = reactive({
  title: 'Bài tập trắc nghiệm',
  description: '',
  time_limit: null as number | null,
  pass_score: 80,
})
const selectedQuestions = ref<any[]>([])

const selectedQuestionIds = computed(() =>
  selectedQuestions.value.filter(q => !!q.id).map(q => q.id)
)

const filteredBankQuestions = computed(() => {
  if (!bankSearch.value.trim()) return bankQuestions.value
  const q = bankSearch.value.toLowerCase()
  return bankQuestions.value.filter(bq => bq.content?.toLowerCase().includes(q))
})

function localKey() {
  return Math.random().toString(36).slice(2)
}

async function loadBank() {
  if (!selectedBankId.value) {
    bankQuestions.value = []
    return
  }
  const bank = await useApi<any>(`/courses/${courseId}/question-banks/${selectedBankId.value}`, { headers: authHeaders() })
  bankQuestions.value = bank.questions || []
}

function addInlineQuestion() {
  selectedQuestions.value.push({
    localKey: localKey(),
    id: null,
    content: '',
    type: 'single_choice',
    answers: [
      { content: '', is_correct: true },
      { content: '', is_correct: false },
      { content: '', is_correct: false },
      { content: '', is_correct: false },
    ],
  })
}

function removeQuestion(index: number) {
  selectedQuestions.value.splice(index, 1)
}

function toggleQuestion(question: any) {
  const exists = selectedQuestions.value.find(item => item.id === question.id)
  if (exists) {
    selectedQuestions.value = selectedQuestions.value.filter(item => item.id !== question.id)
  }
  else {
    selectedQuestions.value = [...selectedQuestions.value, { ...question, localKey: localKey() }]
  }
}

function isSelected(questionId: number) {
  return selectedQuestionIds.value.includes(questionId)
}

async function loadData() {
  loading.value = true
  try {
    const [lessonRes, bankRes] = await Promise.all([
      useApi<any>(`/courses/${courseId}/lessons/${lessonId}`, { headers: authHeaders() }),
      useApi<any>(`/courses/${courseId}/question-banks`, { headers: authHeaders() }),
    ])
    lesson.value = lessonRes
    banks.value = bankRes.banks || []

    try {
      const res = await useApi<any>(`/courses/${courseId}/lessons/${lessonId}/quiz`, { headers: authHeaders() })
      quiz.title = res.quiz?.title || 'Bài tập trắc nghiệm'
      quiz.description = res.quiz?.description || ''
      quiz.time_limit = res.quiz?.time_limit || null
      quiz.pass_score = res.quiz?.pass_score || 80
      selectedQuestions.value = (res.questions || []).map((q: any) => ({ ...q, localKey: localKey() }))
    }
    catch { selectedQuestions.value = [] }
  }
  finally { loading.value = false }
}

async function saveQuiz() {
  if (!quiz.title.trim()) return
  saving.value = true
  success.value = false
  try {
    await useApi(`/courses/${courseId}/lessons/${lessonId}/quiz`, {
      method: 'POST',
      headers: authHeaders(),
      body: {
        ...quiz,
        question_ids: selectedQuestionIds.value,
        questions: selectedQuestions.value
          .filter(q => !q.id)
          .map(q => ({
            content: q.content,
            type: q.type || 'single_choice',
            answers: q.answers,
          })),
      },
    })
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  }
  finally { saving.value = false }
}

onMounted(loadData)
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="section-kicker" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 4px;">
          ← Quay lại Curriculum
        </NuxtLink>
        <h2>Quản lý Quiz bài học</h2>
        <p v-if="lesson">Bài học: <strong>{{ lesson.title }}</strong></p>
      </div>
      <button class="crud-primary-btn" :disabled="saving" type="button" @click="saveQuiz">
        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">save</span>
        {{ saving ? 'Đang lưu...' : 'Lưu Quiz' }}
      </button>
    </header>

    <div v-if="success" class="crud-alert is-success" style="margin-bottom: 16px;">Quiz đã được lưu thành công!</div>

    <div v-if="loading" class="dashboard-card crud-empty" style="height: 300px;">Đang tải dữ liệu quiz...</div>

    <div v-else class="quiz-layout">
      <!-- Left: quiz config + selected questions -->
      <section class="dashboard-card crud-panel">
        <!-- Config -->
        <div class="card-head" style="margin-bottom: 20px;">
          <div>
            <p class="section-kicker">Cấu hình</p>
            <h3>Thông tin quiz</h3>
          </div>
        </div>
        <div class="crud-form-grid">
          <label class="crud-field crud-field-full">
            <span>Tên quiz</span>
            <input v-model="quiz.title" type="text" placeholder="Tên quiz">
          </label>
          <label class="crud-field crud-field-full">
            <span>Mô tả / hướng dẫn</span>
            <textarea v-model="quiz.description" rows="3" placeholder="Hướng dẫn làm bài..." />
          </label>
          <label class="crud-field">
            <span>Thời gian (phút)</span>
            <input v-model.number="quiz.time_limit" type="number" min="0" placeholder="Không giới hạn">
          </label>
          <label class="crud-field">
            <span>Điểm đạt (%)</span>
            <input v-model.number="quiz.pass_score" type="number" min="0" max="100">
          </label>
        </div>

        <!-- Selected questions -->
        <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--line);">
          <div class="crud-toolbar" style="margin-bottom: 16px;">
            <div>
              <p class="section-kicker">Danh sách câu hỏi</p>
              <h3>Câu hỏi đã gắn ({{ selectedQuestions.length }})</h3>
            </div>
            <button type="button" class="crud-secondary-btn" @click="addInlineQuestion">
              <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">add</span>
              Soạn nhanh
            </button>
          </div>

          <div v-if="selectedQuestions.length === 0" class="crud-empty">
            Chưa có câu hỏi nào. Chọn từ ngân hàng hoặc soạn nhanh.
          </div>

          <div v-else class="questions-list">
            <div v-for="(question, index) in selectedQuestions" :key="question.localKey" class="question-item">
              <div class="question-head">
                <span class="crud-badge" :class="question.id ? 'role-instructor' : 'role-student'">
                  {{ question.id ? 'Ngân hàng' : 'Soạn nhanh' }}
                </span>
                <button type="button" class="q-remove-btn" @click="removeQuestion(index)">
                  <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                </button>
              </div>

              <textarea
                v-if="!question.id"
                v-model="question.content"
                rows="2"
                class="q-content-input"
                placeholder="Nội dung câu hỏi..."
              />
              <p v-else class="q-content-text">{{ question.content }}</p>

              <p v-if="question.id" class="q-meta">
                {{ question.group?.name || 'Không nhóm' }} · {{ question.answers?.length || 0 }} đáp án
              </p>

              <!-- Inline answers (for quick-compose questions) -->
              <div v-if="!question.id" class="answers-list">
                <div v-for="(answer, ai) in question.answers" :key="`${question.localKey}-${ai}`" class="answer-row">
                  <label class="answer-correct-toggle" :title="answer.is_correct ? 'Đáp án đúng' : 'Đánh dấu đúng'">
                    <input v-model="answer.is_correct" type="checkbox" style="display: none;">
                    <span
                      class="material-symbols-outlined"
                      :style="{
                        fontSize: '18px',
                        color: answer.is_correct ? 'var(--green)' : 'rgba(17,17,17,0.2)',
                        fontVariationSettings: answer.is_correct ? `'FILL' 1` : `'FILL' 0`,
                        cursor: 'pointer',
                      }"
                    >check_circle</span>
                  </label>
                  <input v-model="answer.content" type="text" class="answer-input" :placeholder="`Đáp án ${ai + 1}`">
                  <button v-if="question.answers.length > 2" type="button" class="answer-remove" @click="question.answers.splice(ai, 1)">×</button>
                </div>
                <button
                  v-if="question.answers.length < 6"
                  type="button"
                  style="font-size: 0.78rem; color: var(--green); font-weight: 700; background: none; border: none; cursor: pointer; padding: 4px 0;"
                  @click="question.answers.push({ content: '', is_correct: false })"
                >
                  + Thêm đáp án
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Right: question bank picker -->
      <aside class="quiz-bank-sidebar">
        <div class="dashboard-card" style="padding: 0; overflow: hidden; position: sticky; top: 20px;">
          <div style="padding: 16px; border-bottom: 1px solid var(--line);">
            <p class="section-kicker">Gắn câu hỏi</p>
            <h3 style="margin: 4px 0 12px;">Ngân hàng câu hỏi</h3>
            <select v-model.number="selectedBankId" class="crud-select" style="width: 100%; margin-bottom: 10px;" @change="loadBank">
              <option :value="null">— Chọn ngân hàng —</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
            </select>
            <input
              v-model="bankSearch"
              type="text"
              class="crud-search"
              style="width: 100%;"
              placeholder="Tìm câu hỏi..."
            >
          </div>

          <div style="max-height: 500px; overflow-y: auto; padding: 12px;">
            <div v-if="!selectedBankId" class="crud-empty" style="padding: 2rem 0;">
              Chọn ngân hàng để xem câu hỏi
            </div>
            <div v-else-if="filteredBankQuestions.length === 0" class="crud-empty" style="padding: 2rem 0;">
              Không có câu hỏi phù hợp.
            </div>
            <label
              v-for="bq in filteredBankQuestions"
              :key="bq.id"
              class="bank-q-row"
              :class="{ 'is-selected': isSelected(bq.id) }"
            >
              <input
                type="checkbox"
                :checked="isSelected(bq.id)"
                style="flex-shrink: 0;"
                @change="toggleQuestion(bq)"
              >
              <div style="min-width: 0;">
                <p class="bank-q-content">{{ bq.content }}</p>
                <p class="bank-q-meta">{{ bq.group?.name || 'Không nhóm' }} · {{ bq.answers?.length || 0 }} đáp án</p>
              </div>
            </label>
          </div>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.quiz-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 1000px) {
  .quiz-layout { grid-template-columns: 1fr; }
}

.questions-list { display: flex; flex-direction: column; gap: 12px; }
.question-item {
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: var(--surface);
}
.question-head { display: flex; justify-content: space-between; align-items: center; }
.q-remove-btn {
  width: 30px; height: 30px; border-radius: 50%;
  border: none; background: rgba(239,68,68,.08);
  color: #ef4444; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.q-remove-btn:hover { background: rgba(239,68,68,.15); }
.q-content-text { font-size: 0.875rem; font-weight: 600; line-height: 1.6; margin: 0; }
.q-content-input {
  width: 100%; border: 1px solid var(--line); border-radius: 8px;
  padding: 8px 12px; font: inherit; font-size: 0.875rem;
  resize: vertical; outline: none;
}
.q-content-input:focus { border-color: var(--green); }
.q-meta { font-size: 0.72rem; color: var(--muted); margin: 0; }

.answers-list { display: flex; flex-direction: column; gap: 6px; padding-left: 4px; }
.answer-row { display: flex; align-items: center; gap: 8px; }
.answer-correct-toggle { flex-shrink: 0; display: flex; }
.answer-input {
  flex: 1; border: 1px solid var(--line); border-radius: 8px;
  padding: 6px 10px; font: inherit; font-size: 0.8rem; outline: none;
}
.answer-input:focus { border-color: var(--green); }
.answer-remove {
  width: 22px; height: 22px; border: none; border-radius: 50%;
  background: rgba(239,68,68,.08); color: #ef4444; cursor: pointer;
  font-size: 14px; display: flex; align-items: center; justify-content: center;
}

.bank-q-row {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px; border-radius: 10px; cursor: pointer;
  transition: background 0.15s; margin-bottom: 6px;
  border: 1px solid transparent;
}
.bank-q-row:hover { background: rgba(var(--green-rgb), 0.04); }
.bank-q-row.is-selected { background: rgba(var(--green-rgb), 0.08); border-color: rgba(var(--green-rgb), 0.3); }
.bank-q-content { font-size: 0.8rem; font-weight: 600; margin: 0; line-height: 1.5;
  overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.bank-q-meta { font-size: 0.7rem; color: var(--muted); margin: 3px 0 0; }
</style>
