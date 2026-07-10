<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import RichTextEditor from '~/components/dashboard/RichTextEditor.vue'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin' })

const route = useRoute()
const router = useRouter()
const courseId = route.query.courseId as string
const bankId = route.query.bankId as string
const questionId = route.query.questionId as string

const user = useAuthUserCookie(); const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface BankDetail { id: number; name: string; groups?: { id: number; name: string }[] }
interface AnswerItem { content: string; is_correct: boolean; sub_content?: string; sort_order?: number }
interface AttachmentItem { id: number; original_name: string; file_path: string; file_size?: string; mime_type?: string; type?: string; url?: string }
interface QuestionDetail {
  id: number; code?: string; content: string; type: string
  difficulty?: number | null; default_score?: number
  explanation?: string | null; feedback?: string | null; general_feedback?: string | null
  question_group_id?: number | null
  answers?: { id: number; content: string; is_correct: boolean; sub_content?: string | null }[]
  attachments?: AttachmentItem[]
}
interface QuestionForm {
  code: string; content: string; type: string; difficulty: number
  default_score: number; explanation: string; feedback: string
  general_feedback: string; question_group_id: number | null
  answers: AnswerItem[]; correct_answer: boolean
}

const bank = ref<BankDetail | null>(null)
const loading = ref(false)
const saving = ref(false)
const toast = useToast()
const existingAttachments = ref<AttachmentItem[]>([])
const pendingFiles = ref<File[]>([])
const uploadingFiles = ref(false)

const questionTypes = [
  { value: 'single_choice', label: 'Trắc nghiệm 1 đáp án', icon: 'radio_button_checked' },
  { value: 'multiple_choice', label: 'Trắc nghiệm nhiều đáp án', icon: 'check_box' },
  { value: 'true_false', label: 'Đúng/Sai', icon: 'toggle_on' },
  { value: 'short_answer', label: 'Trả lời ngắn', icon: 'short_text' },
  { value: 'numerical', label: 'Số', icon: 'numbers' },
  { value: 'essay', label: 'Tự luận', icon: 'article' },
  { value: 'matching', label: 'Ghép đôi', icon: 'compare_arrows' },
  { value: 'ordering', label: 'Sắp xếp', icon: 'swap_vert' },
]

const difficultyLevels = [
  { value: 1, label: 'Nhận biết', color: 'var(--green)' },
  { value: 2, label: 'Thông hiểu', color: 'var(--green)' },
  { value: 3, label: 'Vận dụng', color: '#ff9800' },
  { value: 4, label: 'Vận dụng cao', color: '#f44336' },
  { value: 5, label: 'Sáng tạo', color: '#9c27b0' },
]

const form = reactive<QuestionForm>({
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
  correct_answer: true,
})

const needsAnswerOptions = computed(() => !['essay'].includes(form.type))
const isTrueFalse = computed(() => form.type === 'true_false')
const availableGroups = computed(() => {
  if (!bank.value) return []
  return [
    ...(bank.value.groups || []).map(g => ({ id: g.id, name: g.name })),
    { id: null as number | null, name: 'Chưa phân nhóm' },
  ]
})

function onTypeChange() {
  if (form.type === 'true_false') {
    form.correct_answer = true
    form.answers = []
  } else if (form.type === 'single_choice' || form.type === 'multiple_choice') {
    form.answers = [{ content: '', is_correct: true }, { content: '', is_correct: false }]
  } else if (form.type === 'short_answer' || form.type === 'numerical') {
    form.answers = [{ content: '', is_correct: true }]
  } else if (form.type === 'matching') {
    form.answers = [{ content: '', is_correct: true, sub_content: '' }]
  } else {
    form.answers = []
  }
}

function setCorrectSingleChoice(selectedIndex: number) {
  form.answers.forEach((ans, idx) => {
    ans.is_correct = idx === selectedIndex
  })
}

function addAnswer() {
  if (form.type === 'matching') {
    form.answers.push({ content: '', is_correct: true, sub_content: '' })
  } else {
    form.answers.push({ content: '', is_correct: false })
  }
}

function removeAnswer(index: number) {
  if (form.answers.length > 1) {
    form.answers.splice(index, 1)
  }
}

function onFilesSelected(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) {
    pendingFiles.value = [...pendingFiles.value, ...Array.from(input.files)]
    input.value = ''
  }
}
function removePendingFile(index: number) { pendingFiles.value.splice(index, 1) }
function formatSize(bytes: number) {
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB'
  return (bytes / 1024).toFixed(1) + ' KB'
}
function attachmentIcon(mime?: string) {
  if (!mime) return '📄'
  if (mime.startsWith('image/')) return '🖼️'
  if (mime.startsWith('audio/')) return '🎵'
  return '📄'
}
async function deleteAttachment(att: AttachmentItem) {
  try {
    await useApi(`/courses/${courseId}/question-banks/${bankId}/questions/${questionId}/attachments/${att.id}`, { method: 'DELETE', headers: authHeaders() })
    existingAttachments.value = existingAttachments.value.filter(a => a.id !== att.id)
  } catch { toast.error('Không thể xóa file đính kèm.') }
}

async function fetchData() {
  if (!courseId || !bankId || !questionId) return
  loading.value = true
  try {
    bank.value = await useApi<BankDetail>(`/courses/${courseId}/question-banks/${bankId}`, { headers: authHeaders() })
    const bankDetail = await useApi<any>(`/courses/${courseId}/question-banks/${bankId}`, { headers: authHeaders() })
    const allQuestions = [
      ...(bankDetail.questions || []),
      ...((bankDetail.groups || []).flatMap((g: any) => g.questions || [])),
    ]
    const question: QuestionDetail | undefined = allQuestions.find((q: any) => String(q.id) === String(questionId))

    if (question) {
      form.code = question.code || ''
      form.content = question.content
      form.type = question.type
      form.difficulty = question.difficulty || 1
      form.default_score = question.default_score || 1
      form.explanation = question.explanation || ''
      form.feedback = question.feedback || ''
      form.general_feedback = question.general_feedback || ''
      form.question_group_id = question.question_group_id || null
      existingAttachments.value = question.attachments || []
      if (question.type === 'true_false') {
        form.correct_answer = question.answers?.find(a => a.is_correct)?.content === 'Đúng'
        form.answers = []
      } else {
        form.answers = (question.answers || []).map(a => ({
          content: a.content,
          is_correct: a.is_correct,
          sub_content: a.sub_content || undefined,
        }))
        if (form.answers.length === 0 && needsAnswerOptions.value) {
          form.answers = [{ content: '', is_correct: true }, { content: '', is_correct: false }]
        }
      }
    } else {
      toast.error('Không tìm thấy câu hỏi trong ngân hàng này.')
    }
  } catch {
    toast.error('Không thể tải dữ liệu câu hỏi.')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!courseId || !bankId || !questionId || !form.content.trim()) {
    toast.error('Vui lòng nhập nội dung câu hỏi.')
    return
  }
  saving.value = true
  try {
    const body: any = { ...form }
    if (isTrueFalse.value) {
      body.answers = [
        { content: 'Đúng', is_correct: form.correct_answer === true },
        { content: 'Sai', is_correct: form.correct_answer !== true },
      ]
    }
    delete body.correct_answer

    await useApi(`/courses/${courseId}/question-banks/${bankId}/questions/${questionId}`, {
      method: 'PUT',
      headers: authHeaders(),
      body,
    })
    // Upload pending files
    if (pendingFiles.value.length > 0) {
      uploadingFiles.value = true
      for (const file of pendingFiles.value) {
        const fd = new FormData()
        fd.append('file', file)
        await useApi(`/courses/${courseId}/question-banks/${bankId}/questions/${questionId}/attachments`, {
          method: 'POST', headers: authHeaders(), body: fd,
        })
      }
      uploadingFiles.value = false
    }
    toast.success('Đã cập nhật câu hỏi thành công!')
    setTimeout(() => {
      router.push(`/admin/question-bank?courseId=${courseId}&bankId=${bankId}`)
    }, 800)
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể cập nhật câu hỏi.')
  } finally {
    saving.value = false
    uploadingFiles.value = false
  }
}

function goBack() {
  router.push(`/admin/question-bank?courseId=${courseId}&bankId=${bankId}`)
}

onMounted(fetchData)
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý thi', 'Ngân hàng câu hỏi', 'Chỉnh sửa câu hỏi']"
    description="Chỉnh sửa nội dung, đáp án, phản hồi và giải thích cho câu hỏi."
    title="Chỉnh sửa câu hỏi"
  >
    <div class="qf-topbar">
      <button class="crud-secondary-btn" type="button" @click="goBack">← Quay lại</button>
      <div class="qf-topbar-right">
        <span v-if="bank" class="qf-bank-tag">{{ bank.name }}</span>
        <span v-if="form.code" class="qf-code-tag">{{ form.code }}</span>
      </div>
    </div>

    <div v-if="loading" style="padding: 40px; text-align: center; color: var(--muted);">Đang tải dữ liệu câu hỏi...</div>

    <template v-else>


      <div class="qf-grid">
        <!-- LEFT: Main content -->
        <div class="qf-main">
          <section class="dashboard-card qf-section">
            <h4 class="qf-section-title">Nội dung câu hỏi</h4>
            <div class="crud-field">
              <span>Nội dung <span class="qf-required">*</span></span>
              <RichTextEditor v-model="form.content" placeholder="Nhập nội dung câu hỏi..." enable-images upload-folder="courses" />
            </div>
          </section>

          <!-- Answer options -->
          <section v-if="isTrueFalse" class="dashboard-card qf-section">
            <h4 class="qf-section-title">Đáp án Đúng / Sai</h4>
            <div class="qf-tf-options">
              <label class="qf-tf-option" :class="{ 'is-selected': form.correct_answer === true }">
                <input v-model="form.correct_answer" type="radio" :value="true">
                <span class="qf-tf-icon qf-tf-icon--true material-symbols-outlined" style="font-size: 20px;">check</span>
                <strong>Đúng</strong>
              </label>
              <label class="qf-tf-option" :class="{ 'is-selected': form.correct_answer === false }">
                <input v-model="form.correct_answer" type="radio" :value="false">
                <span class="qf-tf-icon qf-tf-icon--false material-symbols-outlined" style="font-size: 20px;">close</span>
                <strong>Sai</strong>
              </label>
            </div>
          </section>

          <section v-else-if="needsAnswerOptions" class="dashboard-card qf-section">
            <h4 class="qf-section-title">
              Đáp án
              <span v-if="form.type === 'matching'" class="qf-section-hint">(Nội dung trái ↔ Nội dung phải)</span>
            </h4>
            <ul class="qf-answers-list">
              <li v-for="(answer, index) in form.answers" :key="index" class="qf-answer-row">
                <span class="qf-answer-index">{{ String.fromCharCode(65 + index) }}</span>
                <div class="qf-answer-fields">
                  <input v-model="answer.content" type="text" class="crud-input" :placeholder="form.type === 'numerical' ? 'Giá trị (VD: 3.14)' : 'Nội dung đáp án...'">
                  <input v-if="form.type === 'matching'" v-model="answer.sub_content" type="text" class="crud-input" placeholder="Nội dung ghép...">
                </div>
                <label v-if="form.type === 'single_choice'" class="qf-answer-correct">
                  <input type="radio" name="correct_answer_radio" :checked="answer.is_correct" @change="setCorrectSingleChoice(index)" class="crud-radio"> Đúng
                </label>
                <label v-else-if="form.type !== 'matching' && form.type !== 'ordering'" class="qf-answer-correct">
                  <input v-model="answer.is_correct" type="checkbox" class="crud-checkbox"> Đúng
                </label>
                <button type="button" class="qf-answer-remove" :disabled="form.answers.length <= 1" @click="removeAnswer(index)">✕</button>
              </li>
            </ul>
            <button type="button" class="crud-btn-outline qf-add-answer" @click="addAnswer">+ Thêm đáp án</button>
          </section>

          <!-- Feedback section -->
          <section class="dashboard-card qf-section">
            <h4 class="qf-section-title">Phản hồi & Giải thích</h4>
            <div class="qf-feedback-grid">
              <div class="crud-field">
                <span>Phản hồi (hiện sau khi nộp)</span>
                <RichTextEditor v-model="form.feedback" placeholder="Phản hồi cho câu hỏi này..." enable-images upload-folder="courses" />
              </div>
              <div class="crud-field">
                <span>Giải thích đáp án</span>
                <RichTextEditor v-model="form.explanation" placeholder="Giải thích tại sao đáp án đúng..." enable-images upload-folder="courses" />
              </div>
            </div>
            <div class="crud-field" style="margin-top: 16px;">
              <span>Phản hồi chung</span>
              <RichTextEditor v-model="form.general_feedback" placeholder="Nhận xét tổng quan về câu hỏi..." enable-images upload-folder="courses" />
            </div>
          </section>

          <!-- Attachments section -->
          <section class="dashboard-card qf-section">
            <h4 class="qf-section-title">File đính kèm</h4>
            <ul v-if="existingAttachments.length > 0" class="qf-attachment-list" style="margin-bottom: 14px;">
              <li v-for="att in existingAttachments" :key="att.id" class="qf-attachment-item">
                <span class="qf-attachment-icon material-symbols-outlined">{{ att.mime_type?.startsWith('image/') ? 'image' : att.mime_type?.startsWith('audio/') ? 'audiotrack' : 'description' }}</span>
                <div class="qf-attachment-info">
                  <strong>{{ att.original_name }}</strong>
                  <span>{{ att.file_size || '—' }}</span>
                </div>
                <button type="button" class="qf-answer-remove" @click="deleteAttachment(att)">✕</button>
              </li>
            </ul>
            <label class="upload-dropzone upload-dropzone-compact">
              <input class="upload-dropzone-input" type="file" multiple accept="image/*,audio/*,.pdf,.doc,.docx,.zip" @change="onFilesSelected">
              <span class="upload-dropzone-icon material-symbols-outlined">upload_file</span>
              <strong>Tải thêm file đính kèm</strong>
              <span>Hỗ trợ hình ảnh, audio, PDF, Word, ZIP — tối đa 10MB mỗi file</span>
            </label>
            <ul v-if="pendingFiles.length > 0" class="qf-attachment-list">
              <li v-for="(file, index) in pendingFiles" :key="'p'+index" class="qf-attachment-item">
                <span class="qf-attachment-icon material-symbols-outlined">{{ file.type.startsWith('image/') ? 'image' : file.type.startsWith('audio/') ? 'audiotrack' : 'description' }}</span>
                <div class="qf-attachment-info">
                  <strong>{{ file.name }}</strong>
                  <span>{{ formatSize(file.size) }} · <em>Chưa tải lên</em></span>
                </div>
                <button type="button" class="qf-answer-remove" @click="removePendingFile(index)">✕</button>
              </li>
            </ul>
          </section>
        </div>

        <!-- RIGHT: Sidebar -->
        <aside class="qf-sidebar">
          <section class="dashboard-card qf-section">
            <h4 class="qf-section-title">Cấu hình</h4>
            <div class="crud-field">
              <span>Mã câu hỏi</span>
              <input v-model="form.code" type="text" class="crud-input" placeholder="Tự sinh nếu để trống">
            </div>
            <div class="crud-field">
              <span>Loại câu hỏi</span>
              <ul class="qf-type-list">
                <li v-for="qt in questionTypes" :key="qt.value" :class="['qf-type-item', { 'is-active': form.type === qt.value }]" @click="form.type = qt.value; onTypeChange()">
                  <span class="material-symbols-outlined qf-type-icon">{{ qt.icon }}</span>
                  <span class="qf-type-label">{{ qt.label }}</span>
                </li>
              </ul>
            </div>
            <div class="crud-field">
              <span>Mức độ</span>
              <ul class="qf-difficulty-list">
                <li v-for="d in difficultyLevels" :key="d.value" :class="['qf-difficulty-item', { 'is-active': form.difficulty === d.value }]" :style="{ '--active-color': d.color }" @click="form.difficulty = d.value">
                  {{ d.label }}
                </li>
              </ul>
            </div>
            <div class="crud-field">
              <span>Điểm mặc định</span>
              <input v-model.number="form.default_score" type="number" min="0" step="0.5" class="crud-input">
            </div>
            <div class="crud-field">
              <span>Nhóm câu hỏi</span>
              <select v-model="form.question_group_id" class="crud-input">
                <option :value="null">Chưa phân nhóm</option>
                <option v-for="group in availableGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
              </select>
            </div>
          </section>

          <div class="qf-actions">
            <button class="crud-primary-btn qf-save-btn" type="button" :disabled="saving" @click="save">
              {{ saving ? 'Đang lưu...' : 'Cập nhật câu hỏi' }}
            </button>
            <button class="crud-secondary-btn" type="button" @click="goBack">Hủy bỏ</button>
          </div>
        </aside>
      </div>
    </template>
  </AdminWorkspaceShell>
</template>

<style scoped>
.qf-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.qf-topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.qf-bank-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep, var(--green));
  border-radius: 20px;
  font-size: 0.88rem;
  font-weight: 600;
}

.qf-code-tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  background: var(--surface-high, #f0f0f0);
  border-radius: 6px;
  font-family: monospace;
  font-size: 0.82rem;
  color: var(--muted);
}

.qf-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
  align-items: start;
}

@media (max-width: 960px) {
  .qf-grid {
    grid-template-columns: 1fr;
  }
}

.qf-main {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.qf-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: sticky;
  top: 80px;
}

.qf-section {
  padding: 24px;
}

.qf-section-title {
  margin: 0 0 20px;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.qf-section-hint {
  font-weight: 400;
  color: var(--muted);
  font-size: 0.85rem;
}

.qf-required {
  color: #ef4444;
}

/* True/False options */
.qf-tf-options {
  display: flex;
  gap: 16px;
}

.qf-tf-option {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 18px 20px;
  border: 2px solid var(--border, #e5e7eb);
  border-radius: 16px;
  cursor: pointer;
  transition: all 200ms ease;
  background: var(--surface, #fff);
}

.qf-tf-option:hover {
  border-color: var(--green-deep, var(--green));
}

.qf-tf-option.is-selected {
  border-color: var(--green-deep, var(--green));
  background: rgba(var(--green-rgb), 0.04);
}

.qf-tf-option input[type="radio"] {
  display: none;
}

.qf-tf-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  font-size: 1.1rem;
  font-weight: 700;
}

.qf-tf-icon--true {
  background: rgba(76, 175, 80, 0.12);
  color: var(--green);
}

.qf-tf-icon--false {
  background: rgba(244, 67, 54, 0.12);
  color: #f44336;
}

/* Answers */
.qf-answers-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.qf-answer-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: var(--surface-high, #f9fafb);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 14px;
  transition: border-color 200ms ease;
}

.qf-answer-row:hover {
  border-color: rgba(17, 17, 17, 0.2);
}

.qf-answer-index {
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: var(--green-deep, var(--green));
  color: #fff;
  font-weight: 700;
  font-size: 0.82rem;
  flex-shrink: 0;
}

.qf-answer-fields {
  flex: 1;
  display: flex;
  gap: 8px;
}

.qf-answer-fields .crud-input {
  flex: 1;
}

.qf-answer-correct {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.82rem;
  white-space: nowrap;
  color: var(--muted);
}

.qf-answer-remove {
  padding: 4px 8px;
  border: none;
  background: rgba(244, 67, 54, 0.08);
  color: #f44336;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: background 180ms ease;
}

.qf-answer-remove:hover:not(:disabled) {
  background: rgba(244, 67, 54, 0.18);
}

.qf-answer-remove:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.qf-add-answer {
  margin-top: 12px;
}

/* Feedback grid */
.qf-feedback-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 768px) {
  .qf-feedback-grid {
    grid-template-columns: 1fr;
  }
}

/* Difficulty indicator */
.qf-difficulty-indicator {
  display: inline-block;
  margin-top: 6px;
  padding: 3px 10px;
  border-radius: 6px;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 600;
  text-align: center;
}

/* Actions */
.qf-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.qf-save-btn {
  width: 100%;
  padding: 14px 20px;
  font-size: 1rem;
  font-weight: 700;
}

.qf-save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.upload-dropzone {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 10px;
  padding: 28px 20px;
  border: 2px dashed rgba(249, 115, 22, 0.85);
  border-radius: 24px;
  background: rgba(255, 247, 237, 0.75);
  text-align: center;
  cursor: pointer;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}
.upload-dropzone:hover {
  transform: translateY(-1px);
  border-color: rgba(234, 88, 12, 0.95);
  box-shadow: 0 20px 40px -28px rgba(249, 115, 22, 0.45);
}
.upload-dropzone-compact {
  justify-items: start;
  text-align: left;
  padding: 20px 18px;
  border-radius: 20px;
}
.upload-dropzone-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.upload-dropzone-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 999px;
  background: rgba(249, 115, 22, 0.12);
  color: #ea580c;
  font-size: 1.6rem;
}
.upload-dropzone strong {
  font-size: 1.02rem;
  color: var(--text);
}
.upload-dropzone span:last-child {
  color: var(--muted);
  font-size: 0.95rem;
  line-height: 1.5;
}
.qf-attachment-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 14px;
}
.qf-attachment-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: var(--surface-high, #f9fafb);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 12px;
}
.qf-attachment-icon {
  font-size: 1.3rem;
  flex-shrink: 0;
}
.qf-attachment-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.qf-attachment-info strong {
  font-size: 0.88rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.qf-attachment-info span {
  font-size: 0.78rem;
  color: var(--muted);
}
/* Custom Question Type Selection List */
.qf-type-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 6px;
}

.qf-type-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 600;
  transition: all 0.15s ease;
  background: #fff;
}

.qf-type-item:hover {
  background: rgba(16, 185, 129, 0.02);
  border-color: rgba(16, 185, 129, 0.2);
}

.qf-type-item.is-active {
  background: rgba(16, 185, 129, 0.08);
  border-color: rgba(16, 185, 129, 0.25);
  color: var(--green-deep);
}

.qf-type-icon {
  font-size: 16px;
  color: var(--muted);
}

.qf-type-item.is-active .qf-type-icon {
  color: var(--green-deep);
}

/* Difficulty Selector segmented control */
.qf-difficulty-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.qf-difficulty-item {
  flex: 1;
  min-width: 60px;
  text-align: center;
  padding: 5px 2px;
  font-size: 0.72rem;
  font-weight: 700;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  background: #fff;
  white-space: nowrap;
}

.qf-difficulty-item:hover {
  background: rgba(15, 23, 42, 0.02);
}

.qf-difficulty-item.is-active {
  background: var(--active-color);
  border-color: var(--active-color);
  color: #fff;
}

/* List element tweaks */
.qf-answers-list {
  list-style: none;
  padding: 0;
}

.qf-attachment-list {
  list-style: none;
  padding: 0;
}

.qf-attachment-icon {
  color: var(--green-deep);
  display: flex;
  align-items: center;
  justify-content: center;
}

[data-theme="dark"] .qf-type-item,
[data-theme="dark"] .qf-difficulty-item {
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(255, 255, 255, 0.08);
  color: var(--text);
}

[data-theme="dark"] .qf-type-item:hover {
  background: rgba(16, 185, 129, 0.08);
  border-color: rgba(16, 185, 129, 0.3);
}

[data-theme="dark"] .qf-type-item.is-active {
  background: rgba(16, 185, 129, 0.15);
  border-color: rgba(16, 185, 129, 0.25);
  color: #6ee7b7;
}

[data-theme="dark"] .qf-type-item.is-active .qf-type-icon {
  color: #6ee7b7;
}

[data-theme="dark"] .qf-difficulty-item:hover {
  background: rgba(255, 255, 255, 0.05);
}

[data-theme="dark"] .qf-difficulty-item.is-active {
  background: var(--active-color);
  border-color: var(--active-color);
  color: #fff;
}
</style>
