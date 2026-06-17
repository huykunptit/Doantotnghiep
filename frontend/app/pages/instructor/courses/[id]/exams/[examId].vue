<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const route = useRoute()
const courseId = route.params.id as string
const examId = route.params.examId as string
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

const exam = ref<any>(null)
const banks = ref<any[]>([])
const bankQuestions = ref<any[]>([])
const selectedBankId = ref<number | null>(null)
const selectedQuestions = ref<any[]>([])
const selectedIds = computed(() => new Set(selectedQuestions.value.map(q => q.id)))
const bankSearch = ref('')

const quiz = reactive({
  title: '',
  description: '',
  time_limit: 60,
  pass_score: 80,
})

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [examRes, banksRes] = await Promise.all([
      useApi<any>(`/courses/${courseId}/exams/${examId}`, { headers: authHeaders() }),
      useApi<any>(`/courses/${courseId}/question-banks`, { headers: authHeaders() }),
    ])
    exam.value = examRes
    banks.value = banksRes.banks || banksRes || []
    quiz.title = examRes.title
    quiz.description = examRes.description || ''
    quiz.time_limit = examRes.duration || 60
    quiz.pass_score = examRes.pass_score || 80

    try {
      const quizRes = await useApi<any>(`/courses/${courseId}/exams/${examId}/quiz`, { headers: authHeaders() })
      if (quizRes.quiz) {
        quiz.title = quizRes.quiz.title || examRes.title
        quiz.description = quizRes.quiz.description || ''
        quiz.time_limit = quizRes.quiz.time_limit || examRes.duration || 60
        quiz.pass_score = quizRes.quiz.pass_score || examRes.pass_score || 80
        selectedQuestions.value = quizRes.questions || []
      }
    }
    catch {}
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu kỳ thi.'
  }
  finally { loading.value = false }
}

async function loadBank() {
  bankSearch.value = ''
  if (!selectedBankId.value) { bankQuestions.value = []; return }
  try {
    const res = await useApi<any>(`/courses/${courseId}/question-banks/${selectedBankId.value}`, { headers: authHeaders() })
    bankQuestions.value = res.questions || []
  }
  catch { bankQuestions.value = [] }
}

const filteredBankQuestions = computed(() => {
  if (!bankSearch.value.trim()) return bankQuestions.value
  const q = bankSearch.value.toLowerCase()
  return bankQuestions.value.filter(q2 => q2.content?.toLowerCase().includes(q))
})

function toggleQuestion(q: any) {
  if (selectedIds.value.has(q.id)) {
    selectedQuestions.value = selectedQuestions.value.filter(x => x.id !== q.id)
  }
  else {
    selectedQuestions.value = [...selectedQuestions.value, q]
  }
}

function removeQuestion(id: number) {
  selectedQuestions.value = selectedQuestions.value.filter(q => q.id !== id)
}

async function saveQuiz() {
  if (!quiz.title.trim()) { error.value = 'Tên quiz không được để trống.'; return }
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await useApi(`/courses/${courseId}/exams/${examId}/quiz`, {
      method: 'POST',
      headers: authHeaders(),
      body: {
        ...quiz,
        question_ids: selectedQuestions.value.map(q => q.id),
      },
    })
    success.value = 'Lưu quiz thành công!'
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu quiz.'
  }
  finally { saving.value = false }
}

onMounted(loadData)
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên / Kỳ thi</p>
        <h2>Quản lý câu hỏi</h2>
        <p style="margin-top: 4px;">
          Kỳ thi: <strong>{{ exam?.title || `#${examId}` }}</strong>
        </p>
      </div>
      <div style="display: flex; gap: 8px;">
        <NuxtLink :to="`/instructor/courses/${courseId}/exams`" class="crud-secondary-btn">← Quay lại</NuxtLink>
        <NuxtLink :to="`/exam/${examId}`" target="_blank" class="crud-secondary-btn">Thi thử</NuxtLink>
      </div>
    </header>

    <div v-if="loading" class="dashboard-card crud-empty">Đang tải dữ liệu kỳ thi...</div>
    <div v-else-if="error && !exam" class="crud-alert is-error">{{ error }}</div>

    <template v-else>
      <div v-if="success" class="crud-alert is-success" style="margin-bottom: 16px;">{{ success }}</div>
      <div v-if="error" class="crud-alert is-error" style="margin-bottom: 16px;">{{ error }}</div>

      <div class="quiz-layout">
        <!-- Left: quiz config + selected questions -->
        <div class="quiz-main">
          <!-- Quiz settings -->
          <section class="dashboard-card crud-panel" style="margin-bottom: 20px;">
            <div class="card-head" style="margin-bottom: 20px;">
              <h3>Cấu hình quiz</h3>
            </div>
            <div class="crud-form-grid">
              <label class="crud-field crud-field-full">
                <span>Tên quiz</span>
                <input v-model="quiz.title" type="text" placeholder="Tên bộ đề cho kỳ thi này">
              </label>
              <label class="crud-field crud-field-full">
                <span>Hướng dẫn thi</span>
                <textarea v-model="quiz.description" rows="3" placeholder="Mô tả, hướng dẫn cho thí sinh..." />
              </label>
              <label class="crud-field">
                <span>Thời gian làm bài (phút)</span>
                <input v-model.number="quiz.time_limit" type="number" min="1">
              </label>
              <label class="crud-field">
                <span>Điểm đạt (%)</span>
                <input v-model.number="quiz.pass_score" type="number" min="1" max="100">
              </label>
            </div>
            <button
              type="button"
              class="crud-primary-btn"
              style="margin-top: 20px;"
              :disabled="saving"
              @click="saveQuiz"
            >
              {{ saving ? 'Đang lưu...' : 'Lưu quiz' }}
            </button>
          </section>

          <!-- Selected questions -->
          <section class="dashboard-card crud-panel">
            <div class="crud-toolbar">
              <div>
                <p class="section-kicker">Đề thi</p>
                <h3>Câu hỏi đã chọn ({{ selectedQuestions.length }})</h3>
              </div>
            </div>

            <div v-if="selectedQuestions.length === 0" class="crud-empty">
              Chưa có câu hỏi nào. Chọn từ ngân hàng ở bên phải.
            </div>

            <div v-else class="crud-table-wrap">
              <table class="crud-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nội dung câu hỏi</th>
                    <th>Nhóm</th>
                    <th>Đáp án</th>
                    <th>Gỡ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(q, i) in selectedQuestions" :key="q.id">
                    <td>{{ i + 1 }}</td>
                    <td>
                      <strong style="font-size: 0.875rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        {{ q.content }}
                      </strong>
                    </td>
                    <td>
                      <span class="crud-badge">{{ q.group?.name || 'Chung' }}</span>
                    </td>
                    <td>{{ q.answers?.length || 0 }} đáp án</td>
                    <td>
                      <button type="button" class="action-btn is-danger" @click="removeQuestion(q.id)">Gỡ</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- Right: bank picker -->
        <aside class="quiz-bank-sidebar">
          <section class="dashboard-card crud-panel" style="padding: 0; overflow: hidden;">
            <div style="padding: 16px; border-bottom: 1px solid var(--line);">
              <p class="section-kicker">Ngân hàng câu hỏi</p>
              <h3 style="margin: 4px 0 12px;">Chọn câu hỏi</h3>
              <select v-model="selectedBankId" class="crud-select" style="width: 100%; margin-bottom: 10px;" @change="loadBank">
                <option :value="null">— Chọn ngân hàng —</option>
                <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
              </select>
              <input
                v-if="bankQuestions.length > 0"
                v-model="bankSearch"
                type="text"
                class="crud-search"
                style="width: 100%;"
                placeholder="Tìm câu hỏi..."
              >
            </div>

            <div style="max-height: 480px; overflow-y: auto;">
              <div v-if="!selectedBankId" class="crud-empty" style="padding: 2rem 1rem;">
                Chọn ngân hàng để xem câu hỏi.
              </div>
              <div v-else-if="filteredBankQuestions.length === 0" class="crud-empty" style="padding: 2rem 1rem;">
                Ngân hàng này chưa có câu hỏi.
              </div>
              <label
                v-for="q in filteredBankQuestions"
                :key="q.id"
                class="bank-question-item"
                :class="{ 'is-selected': selectedIds.has(q.id) }"
              >
                <input
                  type="checkbox"
                  :checked="selectedIds.has(q.id)"
                  @change="toggleQuestion(q)"
                >
                <div class="bank-q-body">
                  <p class="bank-q-text">{{ q.content }}</p>
                  <p class="bank-q-meta">
                    {{ q.group?.name || 'Chung' }} · {{ q.answers?.length || 0 }} đáp án
                  </p>
                </div>
              </label>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </section>
</template>

<style scoped>
.quiz-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .quiz-layout { grid-template-columns: 1fr; }
}
.quiz-bank-sidebar { position: sticky; top: 80px; }

.bank-question-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--line);
  cursor: pointer;
  transition: background 0.15s;
}
.bank-question-item:last-child { border-bottom: none; }
.bank-question-item:hover { background: rgba(var(--green-rgb), 0.04); }
.bank-question-item.is-selected { background: rgba(var(--green-rgb), 0.08); }
.bank-question-item input[type="checkbox"] { margin-top: 2px; flex-shrink: 0; }
.bank-q-body { min-width: 0; }
.bank-q-text { font-size: 0.8rem; font-weight: 600; color: var(--text); margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bank-q-meta { font-size: 0.7rem; color: var(--muted); margin-top: 2px; }
</style>
