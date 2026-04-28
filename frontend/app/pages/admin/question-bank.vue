<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý ngân hàng câu hỏi...' })
interface CourseItem { id: number; title: string; thumbnail?: string | null; category?: { name: string } | null }
interface BankItem { id: number; name: string; description?: string | null; questions_count?: number; groups_count?: number }
interface QuestionItem { id: number; code?: string; content: string; type: string; difficulty?: number | null; default_score?: number; feedback?: string; answers?: { id: number; content: string; is_correct: boolean }[] }
interface BankDetail extends BankItem { questions?: QuestionItem[]; groups?: { id: number; name: string; questions?: QuestionItem[] }[] }
interface AnswerItem { content: string; is_correct: boolean; sub_content?: string; sort_order?: number }
interface QuestionForm { code?: string; content: string; type: string; difficulty: number; default_score: number; explanation?: string; feedback?: string; general_feedback?: string; question_group_id?: number | null; answers: AnswerItem[]; correct_answer?: boolean }
const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const courses = ref<CourseItem[]>([]); const banks = ref<BankItem[]>([]); const selectedCourseId = ref<number | null>(null)
const bankName = ref(''); const bankDescription = ref(''); const loadingCourses = ref(false); const loadingBanks = ref(false)
const detailOpen = ref(false); const confirmOpen = ref(false); const selectedBank = ref<BankItem | null>(null); const bankDetail = ref<BankDetail | null>(null)
const errorMessage = ref(''); const successMessage = ref('')
const questionModalOpen = ref(false); const editingQuestion = ref<QuestionItem | null>(null)

const questionTypes = [
  { value: 'single_choice', label: 'Trắc nghiệm 1 đáp án', icon: '○' },
  { value: 'multiple_choice', label: 'Trắc nghiệm nhiều đáp án', icon: '☑' },
  { value: 'true_false', label: 'Đúng/Sai', icon: '✓✗' },
  { value: 'short_answer', label: 'Trả lời ngắn', icon: '✎' },
  { value: 'numerical', label: 'Số', icon: '#' },
  { value: 'essay', label: 'Tự luận', icon: '📝' },
  { value: 'matching', label: 'Ghép đôi', icon: '↔' },
  { value: 'ordering', label: 'Sắp xếp', icon: '↕' },
]

const difficultyLevels = [
  { value: 1, label: 'Nhận biết', color: '#4caf50' },
  { value: 2, label: 'Thông hiểu', color: '#2196f3' },
  { value: 3, label: 'Vận dụng', color: '#ff9800' },
  { value: 4, label: 'Vận dụng cao', color: '#f44336' },
  { value: 5, label: 'Sáng tạo', color: '#9c27b0' },
]

const questionForm = reactive<QuestionForm>({
  code: '',
  content: '',
  type: 'single_choice',
  difficulty: 1,
  default_score: 1,
  explanation: '',
  feedback: '',
  general_feedback: '',
  question_group_id: null,
  answers: [{ content: '', is_correct: true }, { content: '', is_correct: false }],
  correct_answer: true, // For true_false type
})
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const selectedCourse = computed(() => courses.value.find(item => item.id === selectedCourseId.value))
const detailRows = computed(() => [
  ...(bankDetail.value?.questions || []).map(q => ({ group: 'Chưa phân nhóm', ...q })),
  ...((bankDetail.value?.groups || []).flatMap(group => (group.questions || []).map(q => ({ group: group.name, ...q })))),
])
const availableGroups = computed(() => {
  if (!bankDetail.value) return []
  return [
    ...(bankDetail.value.groups || []).map(g => ({ id: g.id, name: g.name })),
    { id: null, name: 'Chưa phân nhóm' }
  ]
})

const typeLabel = (type: string) => questionTypes.find(t => t.value === type)?.label || type
const difficultyLabel = (level: number) => difficultyLevels.find(d => d.value === level)?.label || `${level}`
const difficultyColor = (level: number) => difficultyLevels.find(d => d.value === level)?.color || '#999'

// Whether the current question type needs answer options
const needsAnswerOptions = computed(() => !['essay'].includes(questionForm.type))
const isTrueFalse = computed(() => questionForm.type === 'true_false')

async function fetchCourses() {
  loadingCourses.value = true
  try {
    const response = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    courses.value = response.data; if (!selectedCourseId.value && response.data.length) { selectedCourseId.value = response.data[0].id; await fetchBanks() }
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải danh sách khóa học.' } finally { loadingCourses.value = false }
}
async function fetchBanks() {
  if (!selectedCourseId.value) return
  loadingBanks.value = true
  try {
    const response = await useApi<{ banks: BankItem[] }>(`/courses/${selectedCourseId.value}/question-banks`, { headers: authHeaders() })
    banks.value = response.banks || []
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải ngân hàng câu hỏi.' } finally { loadingBanks.value = false }
}
async function createBank() {
  if (!selectedCourseId.value || !bankName.value.trim()) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/question-banks`, { method: 'POST', headers: authHeaders(), body: { name: bankName.value.trim(), description: bankDescription.value || null } })
    bankName.value = ''; bankDescription.value = ''; successMessage.value = 'Đã tạo ngân hàng câu hỏi.'; await fetchBanks()
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tạo ngân hàng câu hỏi.' }
}
async function openDetail(bank: BankItem) {
  if (!selectedCourseId.value) return
  selectedBank.value = bank; detailOpen.value = true
  bankDetail.value = await useApi<BankDetail>(`/courses/${selectedCourseId.value}/question-banks/${bank.id}`, { headers: authHeaders() })
}
async function deleteBank() {
  if (!selectedCourseId.value || !selectedBank.value) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/question-banks/${selectedBank.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa ngân hàng câu hỏi.'; confirmOpen.value = false; detailOpen.value = false; selectedBank.value = null; await fetchBanks()
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể xóa ngân hàng câu hỏi.' }
}
function openQuestionModal(question?: QuestionItem) {
  editingQuestion.value = question || null
  if (question) {
    questionForm.code = question.code || ''
    questionForm.content = question.content
    questionForm.type = question.type
    questionForm.difficulty = question.difficulty || 1
    questionForm.default_score = question.default_score || 1
    questionForm.explanation = ''
    questionForm.feedback = question.feedback || ''
    questionForm.general_feedback = ''
    questionForm.question_group_id = null
    if (question.type === 'true_false') {
      questionForm.correct_answer = question.answers?.find(a => a.is_correct)?.content === 'Đúng'
      questionForm.answers = []
    } else {
      questionForm.answers = question.answers || [{ content: '', is_correct: true }, { content: '', is_correct: false }]
    }
  } else {
    resetQuestionForm()
  }
  questionModalOpen.value = true
}
function resetQuestionForm() {
  questionForm.code = ''
  questionForm.content = ''
  questionForm.type = 'single_choice'
  questionForm.difficulty = 1
  questionForm.default_score = 1
  questionForm.explanation = ''
  questionForm.feedback = ''
  questionForm.general_feedback = ''
  questionForm.question_group_id = null
  questionForm.correct_answer = true
  questionForm.answers = [{ content: '', is_correct: true }, { content: '', is_correct: false }]
}

function onTypeChange() {
  if (questionForm.type === 'true_false') {
    questionForm.correct_answer = true
    questionForm.answers = []
  } else if (questionForm.type === 'single_choice' || questionForm.type === 'multiple_choice') {
    questionForm.answers = [{ content: '', is_correct: true }, { content: '', is_correct: false }]
  } else if (questionForm.type === 'short_answer' || questionForm.type === 'numerical') {
    questionForm.answers = [{ content: '', is_correct: true }]
  } else if (questionForm.type === 'matching') {
    questionForm.answers = [{ content: '', is_correct: true, sub_content: '' }]
  } else {
    questionForm.answers = []
  }
}

async function saveQuestion() {
  if (!selectedCourseId.value || !selectedBank.value) return
  try {
    const url = editingQuestion.value
      ? `/courses/${selectedCourseId.value}/question-banks/${selectedBank.value.id}/questions/${editingQuestion.value.id}`
      : `/courses/${selectedCourseId.value}/question-banks/${selectedBank.value.id}/questions`
    const method = editingQuestion.value ? 'PUT' : 'POST'

    // Build body
    const body: any = { ...questionForm }
    if (isTrueFalse.value) {
      body.answers = [
        { content: 'Đúng', is_correct: questionForm.correct_answer === true },
        { content: 'Sai', is_correct: questionForm.correct_answer !== true },
      ]
    }
    delete body.correct_answer

    await useApi(url, { method, headers: authHeaders(), body })
    successMessage.value = editingQuestion.value ? 'Đã cập nhật câu hỏi.' : 'Đã tạo câu hỏi mới.'
    questionModalOpen.value = false
    await openDetail(selectedBank.value)
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể lưu câu hỏi.' }
}
function addAnswer() {
  if (questionForm.type === 'matching') {
    questionForm.answers.push({ content: '', is_correct: true, sub_content: '' })
  } else {
    questionForm.answers.push({ content: '', is_correct: false })
  }
}
function removeAnswer(index: number) {
  if (questionForm.answers.length > 1) {
    questionForm.answers.splice(index, 1)
  }
}
async function deleteQuestion(question: QuestionItem) {
  if (!selectedCourseId.value || !selectedBank.value) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/question-banks/${selectedBank.value.id}/questions/${question.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa câu hỏi.'
    await openDetail(selectedBank.value)
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể xóa câu hỏi.' }
}
function onCourseChange() {
  fetchBanks()
}
onMounted(fetchCourses)
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý thi', 'Ngân hàng câu hỏi']" description="Quản lý ngân hàng câu hỏi theo khóa học. Chọn khóa học từ dropdown phía trên, sau đó xem và quản lý danh sách ngân hàng câu hỏi bên dưới." title="Quản lý ngân hàng câu hỏi">
    <!-- Filter bar -->
    <section class="dashboard-card crud-panel" style="position: relative; z-index: 20;">
      <div class="crud-toolbar">
        <div class="crud-toolbar-main">
          <label class="crud-filter-group">
            <span class="crud-filter-label">Khóa học</span>
            <SearchableCourseSelect v-model="selectedCourseId" :courses="courses" :loading="loadingCourses" @change="onCourseChange" />
          </label>
        </div>
      </div>
    </section>

    <!-- Create form + table -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Ngân hàng câu hỏi</p>
          <h3>{{ selectedCourse?.title || 'Chưa chọn khóa học' }}</h3>
        </div>
      </div>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>
      <div class="crud-form-grid">
        <label class="crud-field"><span>Tên ngân hàng câu hỏi</span><input v-model="bankName" type="text" placeholder="Ví dụ: Bộ câu hỏi giữa kỳ"></label>
        <div class="crud-field"><span>Mô tả ngắn</span><RichTextEditor v-model="bankDescription" placeholder="Mô tả phạm vi câu hỏi" enable-images upload-folder="courses" /></div>
      </div>
      <div class="crud-inline-actions crud-modal-foot"><button class="crud-primary-btn" type="button" @click="createBank">Tạo mới</button></div>
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead><tr><th>Tên ngân hàng</th><th>Mô tả</th><th>Số câu hỏi</th><th>Số nhóm</th><th>Thao tác</th></tr></thead>
          <tbody>
            <tr v-if="loadingBanks"><td colspan="5" class="crud-empty">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="banks.length === 0"><td colspan="5" class="crud-empty">Khóa học này chưa có ngân hàng câu hỏi.</td></tr>
            <tr v-for="bank in banks" :key="bank.id">
              <td><strong>{{ bank.name }}</strong></td>
              <td><RichTextContent :content="bank.description" compact empty-text="Chưa có mô tả." /></td>
              <td>{{ bank.questions_count || 0 }}</td>
              <td>{{ bank.groups_count || 0 }}</td>
              <td>
                <div class="crud-actions">
                  <button class="action-btn is-view" type="button" @click="openDetail(bank)">Xem chi tiết</button>
                  <button class="action-btn is-delete" type="button" @click="selectedBank = bank; confirmOpen = true">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Bank Detail Modal -->
    <Teleport to="body">
      <div v-if="detailOpen" class="crud-modal-backdrop" @click.self="detailOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Chi tiết ngân hàng câu hỏi</p>
              <h3>{{ bankDetail?.name || selectedBank?.name }}</h3>
              <RichTextContent :content="bankDetail?.description" empty-text="Danh sách câu hỏi theo nhóm trong ngân hàng đã chọn." />
            </div>
            <div class="flex gap-2">
              <button class="crud-primary-btn" type="button" @click="openQuestionModal()">+ Thêm câu hỏi</button>
              <button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button>
            </div>
          </div>
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead><tr><th>Mã</th><th>Nhóm</th><th>Nội dung</th><th>Loại</th><th>Độ khó</th><th>Điểm</th><th>Đáp án đúng</th><th>Thao tác</th></tr></thead>
              <tbody>
                <tr v-if="detailRows.length === 0"><td colspan="8" class="crud-empty">Ngân hàng này chưa có câu hỏi.</td></tr>
                <tr v-for="row in detailRows" :key="row.id">
                  <td><code style="font-size: 0.75rem; background: var(--surface-high, #f0f0f0); padding: 2px 6px; border-radius: 4px;">{{ row.code || '—' }}</code></td>
                  <td>{{ row.group }}</td>
                  <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ row.content }}</td>
                  <td><span class="crud-badge">{{ typeLabel(row.type) }}</span></td>
                  <td><span :style="{ color: difficultyColor(row.difficulty || 1), fontWeight: 600 }">{{ difficultyLabel(row.difficulty || 1) }}</span></td>
                  <td>{{ row.default_score ?? 1 }}</td>
                  <td>{{ row.answers?.find(answer => answer.is_correct)?.content || '--' }}</td>
                  <td>
                    <div class="crud-actions">
                      <button class="action-btn is-edit" type="button" @click="openQuestionModal(row)">Sửa</button>
                      <button class="action-btn is-delete" type="button" @click="deleteQuestion(row)">Xóa</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Question Modal -->
    <Teleport to="body">
      <div v-if="questionModalOpen" class="crud-modal-backdrop" @click.self="questionModalOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Câu hỏi</p>
              <h3>{{ editingQuestion ? 'Chỉnh sửa câu hỏi' : 'Thêm câu hỏi mới' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="questionModalOpen = false">✕</button>
          </div>
          <div class="crud-modal-body" style="max-height: 70vh; overflow-y: auto;">
            <div class="crud-form-grid">
              <!-- Row 1: Code + Type -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="crud-field">
                  <label>Mã câu hỏi (tự sinh nếu để trống)</label>
                  <input v-model="questionForm.code" type="text" placeholder="VD: Q-CS101-001" class="crud-input">
                </div>
                <div class="crud-field">
                  <label>Loại câu hỏi</label>
                  <select v-model="questionForm.type" class="crud-input" @change="onTypeChange">
                    <option v-for="qt in questionTypes" :key="qt.value" :value="qt.value">{{ qt.icon }} {{ qt.label }}</option>
                  </select>
                </div>
              </div>

              <!-- Row 2: Content -->
              <div class="crud-field">
                <label>Nội dung câu hỏi</label>
                <textarea v-model="questionForm.content" rows="4" placeholder="Nhập nội dung câu hỏi..." class="crud-input"></textarea>
              </div>

              <!-- Row 3: Difficulty + Score + Group -->
              <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="crud-field">
                  <label>Mức độ</label>
                  <select v-model.number="questionForm.difficulty" class="crud-input">
                    <option v-for="d in difficultyLevels" :key="d.value" :value="d.value">{{ d.label }}</option>
                  </select>
                </div>
                <div class="crud-field">
                  <label>Điểm mặc định</label>
                  <input v-model.number="questionForm.default_score" type="number" min="0" step="0.5" class="crud-input">
                </div>
                <div class="crud-field">
                  <label>Nhóm câu hỏi</label>
                  <select v-model="questionForm.question_group_id" class="crud-input">
                    <option :value="null">Chưa phân nhóm</option>
                    <option v-for="group in availableGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
                  </select>
                </div>
              </div>

              <!-- True/False specific -->
              <div v-if="isTrueFalse" class="crud-field">
                <label>Đáp án đúng</label>
                <div style="display: flex; gap: 1.5rem; padding: 0.5rem 0;">
                  <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input v-model="questionForm.correct_answer" type="radio" :value="true"> <strong style="color: #4caf50;">Đúng</strong>
                  </label>
                  <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input v-model="questionForm.correct_answer" type="radio" :value="false"> <strong style="color: #f44336;">Sai</strong>
                  </label>
                </div>
              </div>

              <!-- Answer options for non-essay, non-true_false -->
              <div class="crud-field" v-if="needsAnswerOptions && !isTrueFalse">
                <label>Đáp án <span v-if="questionForm.type === 'matching'" style="font-weight: normal; color: #666;">(Nội dung trái ↔ Nội dung phải)</span></label>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                  <div v-for="(answer, index) in questionForm.answers" :key="index" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input v-model="answer.content" type="text" :placeholder="questionForm.type === 'numerical' ? 'Giá trị đúng (VD: 3.14)' : 'Nội dung đáp án...'" class="crud-input" style="flex: 1;">
                    <input v-if="questionForm.type === 'matching'" v-model="answer.sub_content" type="text" placeholder="Nội dung ghép..." class="crud-input" style="flex: 1;">
                    <label v-if="questionForm.type !== 'matching'" style="display: flex; align-items: center; gap: 4px; white-space: nowrap; font-size: 0.8rem;">
                      <input v-model="answer.is_correct" type="checkbox" class="crud-checkbox"> Đúng
                    </label>
                    <button type="button" class="crud-btn-secondary" :disabled="questionForm.answers.length <= 1" @click="removeAnswer(index)" style="padding: 4px 8px; font-size: 0.75rem;">✕</button>
                  </div>
                  <button type="button" class="crud-btn-outline" @click="addAnswer">+ Thêm đáp án</button>
                </div>
              </div>

              <!-- Feedback fields -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="crud-field">
                  <label>Phản hồi (hiện sau khi nộp)</label>
                  <textarea v-model="questionForm.feedback" rows="2" placeholder="Phản hồi cho câu hỏi này..." class="crud-input"></textarea>
                </div>
                <div class="crud-field">
                  <label>Giải thích đáp án</label>
                  <textarea v-model="questionForm.explanation" rows="2" placeholder="Giải thích tại sao đáp án đúng..." class="crud-input"></textarea>
                </div>
              </div>
              <div class="crud-field">
                <label>Phản hồi chung</label>
                <textarea v-model="questionForm.general_feedback" rows="2" placeholder="Nhận xét tổng quan..." class="crud-input"></textarea>
              </div>
            </div>

            <div class="crud-modal-foot" style="margin-top: 1rem;">
              <button type="button" class="crud-btn-secondary" @click="questionModalOpen = false">Hủy</button>
              <button type="button" class="crud-primary-btn" @click="saveQuestion">{{ editingQuestion ? 'Cập nhật' : 'Tạo mới' }}</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
    <CrudConfirmModal :open="confirmOpen" title="Xóa ngân hàng câu hỏi" :description="`Bạn có chắc chắn muốn xóa ${selectedBank?.name || 'mục này'}? Thao tác này không thể hoàn tác.`" confirm-text="Xóa ngân hàng" tone="danger" @close="confirmOpen = false" @confirm="deleteBank" />
  </AdminWorkspaceShell>
</template>
