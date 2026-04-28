<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý quiz / đề thi...' })

interface CourseItem { id: number; title: string; category?: { name: string } | null }
interface ExamItem { id: number; title: string; description?: string | null; type?: string; duration?: number | null; pass_score?: number | null; max_attempts?: number; status?: string | null; shuffle_questions?: boolean; shuffle_answers?: boolean; review_options?: any; starts_at?: string | null; ends_at?: string | null; exam_enrollments_count?: number }
interface ExamDetail extends ExamItem { quiz?: { questions?: { id: number; content: string; type: string; answers?: { id: number; content: string; is_correct: boolean }[] }[] } | null }

const user = useAuthUserCookie(); const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const activeTab = ref<'course' | 'standalone'>('course')
const courses = ref<CourseItem[]>([]); const exams = ref<ExamItem[]>([]); const standaloneExams = ref<ExamItem[]>([])
const selectedCourseId = ref<number | null>(null)
const loadingCourses = ref(false); const loadingExams = ref(false)
const detailOpen = ref(false); const confirmOpen = ref(false)
const selectedExam = ref<ExamItem | null>(null); const examDetail = ref<ExamDetail | null>(null)
const errorMessage = ref(''); const successMessage = ref('')
const quizModalOpen = ref(false); const quizTitle = ref(''); const quizDescription = ref(''); const quizTimeLimit = ref(30); const quizPassScore = ref(70)
const selectedQuestionIds = ref<number[]>([])
const banks = ref<any[]>([]); const loadingBanks = ref(false)

// Exam form
const examForm = reactive({
  title: '', description: '', type: 'course_final' as 'course_final' | 'standalone',
  duration: 30, pass_score: 70, max_attempts: 1,
  shuffle_questions: false, shuffle_answers: false,
  starts_at: '', ends_at: '',
  review_options: {
    after_submit: { attempt: true, correctness: true, marks: true, specific_feedback: false, general_feedback: false, right_answer: false, overall_feedback: true }
  }
})

const reviewLabels: Record<string, string> = {
  attempt: 'Bài làm', correctness: 'Nếu đúng', marks: 'Điểm',
  specific_feedback: 'Phản hồi chuyên biệt', general_feedback: 'Phản hồi chung',
  right_answer: 'Câu trả lời đúng', overall_feedback: 'Nhận xét chung'
}

const statusMap: Record<string, string> = { draft: 'Nháp', scheduled: 'Lên lịch', active: 'Đang thi', closed: 'Đã đóng', archived: 'Lưu trữ' }
const statusColor: Record<string, string> = { draft: '#999', scheduled: '#2196f3', active: '#4caf50', closed: '#f44336', archived: '#666' }

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const selectedCourse = computed(() => courses.value.find(item => item.id === selectedCourseId.value))
const currentExams = computed(() => activeTab.value === 'standalone' ? standaloneExams.value : exams.value)

function resetExamForm() {
  examForm.title = ''; examForm.description = ''; examForm.type = activeTab.value === 'standalone' ? 'standalone' : 'course_final'
  examForm.duration = 30; examForm.pass_score = 70; examForm.max_attempts = 1
  examForm.shuffle_questions = false; examForm.shuffle_answers = false
  examForm.starts_at = ''; examForm.ends_at = ''
  examForm.review_options = { after_submit: { attempt: true, correctness: true, marks: true, specific_feedback: false, general_feedback: false, right_answer: false, overall_feedback: true } }
}

async function fetchCourses() {
  loadingCourses.value = true
  try {
    const response = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    courses.value = response.data || []
    if (!selectedCourseId.value && courses.value.length > 0) { selectedCourseId.value = courses.value[0]!.id; await fetchExams() }
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải danh sách khóa học.' } finally { loadingCourses.value = false }
}

async function fetchExams() {
  if (!selectedCourseId.value) return
  loadingExams.value = true
  try {
    exams.value = await useApi<ExamItem[]>(`/courses/${selectedCourseId.value}/exams`, { headers: authHeaders() })
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải danh sách đề thi.' } finally { loadingExams.value = false }
}

async function fetchStandaloneExams() {
  loadingExams.value = true
  try {
    standaloneExams.value = await useApi<ExamItem[]>('/exams/standalone', { headers: authHeaders() })
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải kỳ thi độc lập.' } finally { loadingExams.value = false }
}

async function createExam() {
  if (!examForm.title.trim()) return
  try {
    const body: any = { ...examForm, starts_at: examForm.starts_at || null, ends_at: examForm.ends_at || null }
    if (activeTab.value === 'standalone') {
      body.type = 'standalone'
      await useApi('/exams/standalone', { method: 'POST', headers: authHeaders(), body })
      await fetchStandaloneExams()
    } else {
      if (!selectedCourseId.value) return
      body.type = 'course_final'
      await useApi(`/courses/${selectedCourseId.value}/exams`, { method: 'POST', headers: authHeaders(), body })
      await fetchExams()
    }
    resetExamForm(); successMessage.value = 'Đã tạo đề thi mới.'
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tạo đề thi.' }
}

async function openDetail(exam: ExamItem) {
  selectedExam.value = exam; detailOpen.value = true
  try {
    if (activeTab.value === 'standalone') {
      examDetail.value = await useApi<ExamDetail>(`/exams/${exam.id}/detail`, { headers: authHeaders() })
    } else if (selectedCourseId.value) {
      examDetail.value = await useApi<ExamDetail>(`/courses/${selectedCourseId.value}/exams/${exam.id}`, { headers: authHeaders() })
    }
  } catch { examDetail.value = exam as ExamDetail }
}

async function deleteExam() {
  if (!selectedExam.value) return
  try {
    if (activeTab.value === 'standalone') {
      await useApi(`/exams/${selectedExam.value.id}`, { method: 'DELETE', headers: authHeaders() })
      await fetchStandaloneExams()
    } else if (selectedCourseId.value) {
      await useApi(`/courses/${selectedCourseId.value}/exams/${selectedExam.value.id}`, { method: 'DELETE', headers: authHeaders() })
      await fetchExams()
    }
    successMessage.value = 'Đã xóa đề thi.'; confirmOpen.value = false; detailOpen.value = false; selectedExam.value = null
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể xóa đề thi.' }
}

async function openQuizSetup() {
  if (!selectedExam.value) return
  loadingBanks.value = true
  try {
    if (selectedCourseId.value) {
      const banksData = await useApi(`/courses/${selectedCourseId.value}/question-banks`, { headers: authHeaders() })
      banks.value = await Promise.all((banksData.banks || []).map(async (bank: any) => {
        try {
          const detail = await useApi(`/courses/${selectedCourseId.value}/question-banks/${bank.id}`, { headers: authHeaders() })
          return { ...bank, questions: detail.questions || [] }
        } catch { return bank }
      }))
    }

    // Load existing quiz setup
    try {
      let quizData: any = null
      if (activeTab.value === 'standalone') {
        // For standalone we may not have a course-bound quiz route
      } else if (selectedCourseId.value) {
        quizData = await useApi(`/courses/${selectedCourseId.value}/exams/${selectedExam.value.id}/quiz`, { headers: authHeaders() })
      }
      if (quizData?.quiz) {
        quizTitle.value = quizData.quiz.title || selectedExam.value.title
        quizDescription.value = quizData.quiz.description || ''
        quizTimeLimit.value = quizData.quiz.time_limit || selectedExam.value.duration || 30
        quizPassScore.value = quizData.quiz.pass_score || selectedExam.value.pass_score || 70
        selectedQuestionIds.value = quizData.quiz.questions?.map((q: any) => q.id) || []
      } else { throw new Error('no quiz') }
    } catch {
      quizTitle.value = selectedExam.value.title
      quizDescription.value = selectedExam.value.description || ''
      quizTimeLimit.value = selectedExam.value.duration || 30
      quizPassScore.value = selectedExam.value.pass_score || 70
      selectedQuestionIds.value = []
    }
    quizModalOpen.value = true
  } finally { loadingBanks.value = false }
}

async function saveQuizSetup() {
  if (!selectedCourseId.value || !selectedExam.value) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/exams/${selectedExam.value.id}/quiz`, {
      method: 'POST', headers: authHeaders(),
      body: { title: quizTitle.value, description: quizDescription.value, time_limit: quizTimeLimit.value, pass_score: quizPassScore.value, question_ids: selectedQuestionIds.value }
    })
    successMessage.value = 'Đã lưu setup quiz.'; quizModalOpen.value = false; await openDetail(selectedExam.value)
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể lưu setup quiz.' }
}

function toggleQuestionSelection(questionId: number) {
  const index = selectedQuestionIds.value.indexOf(questionId)
  if (index > -1) { selectedQuestionIds.value.splice(index, 1) } else { selectedQuestionIds.value.push(questionId) }
}

function onTabChange(tab: 'course' | 'standalone') {
  activeTab.value = tab; resetExamForm()
  if (tab === 'standalone') fetchStandaloneExams()
  else fetchExams()
}

function onCourseChange() { fetchExams() }
onMounted(fetchCourses)
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý thi', 'Quiz / Đề thi']" description="Quản lý đề thi theo từng khóa học hoặc kỳ thi độc lập." title="Quản lý quiz / đề thi">

    <!-- Tab switcher -->
    <section class="dashboard-card crud-panel">
      <div style="display: flex; gap: 0; border-bottom: 2px solid var(--border-color, #e0e0e0);">
        <button :class="['crud-tab-btn', { active: activeTab === 'course' }]" type="button" @click="onTabChange('course')">📘 Đề thi khóa học</button>
        <button :class="['crud-tab-btn', { active: activeTab === 'standalone' }]" type="button" @click="onTabChange('standalone')">🏆 Kỳ thi độc lập</button>
      </div>
    </section>

    <!-- Course selector (only for course tab) -->
    <section v-if="activeTab === 'course'" class="dashboard-card crud-panel" style="position: relative; z-index: 20;">
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
          <p class="section-kicker">{{ activeTab === 'standalone' ? 'Kỳ thi độc lập' : 'Đề thi khóa học' }}</p>
          <h3>{{ activeTab === 'standalone' ? 'Danh sách kỳ thi' : (selectedCourse?.title || 'Chưa chọn khóa học') }}</h3>
        </div>
      </div>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <div class="crud-form-grid">
        <label class="crud-field"><span>Tên đề thi</span><input v-model="examForm.title" type="text" placeholder="Ví dụ: Đề thi giữa kỳ"></label>
        <div class="crud-field"><span>Mô tả</span><RichTextEditor v-model="examForm.description" placeholder="Mô tả phạm vi kiến thức" enable-images upload-folder="courses" /></div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
          <label class="crud-field"><span>Thời lượng (phút)</span><input v-model.number="examForm.duration" type="number" min="1"></label>
          <label class="crud-field"><span>Điểm đạt (%)</span><input v-model.number="examForm.pass_score" type="number" min="0" max="100"></label>
          <label class="crud-field"><span>Số lần thi tối đa</span><input v-model.number="examForm.max_attempts" type="number" min="1" max="99"></label>
          <label class="crud-field"><span>Trạng thái</span>
            <select v-model="examForm.type" class="crud-input" disabled>
              <option value="course_final">Đề thi khóa học</option>
              <option value="standalone">Kỳ thi độc lập</option>
            </select>
          </label>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <label class="crud-field"><span>Ngày giờ bắt đầu</span><input v-model="examForm.starts_at" type="datetime-local"></label>
          <label class="crud-field"><span>Ngày giờ kết thúc</span><input v-model="examForm.ends_at" type="datetime-local"></label>
        </div>

        <div style="display: flex; gap: 2rem; padding: 0.5rem 0;">
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input v-model="examForm.shuffle_questions" type="checkbox"> Trộn câu hỏi
          </label>
          <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input v-model="examForm.shuffle_answers" type="checkbox"> Trộn đáp án
          </label>
        </div>

        <!-- Review options -->
        <div class="crud-field">
          <label style="font-weight: 700; margin-bottom: 0.5rem; display: block;">Các lựa chọn xem lại (sau khi thi xong)</label>
          <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
            <label v-for="(label, key) in reviewLabels" :key="key" style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.85rem;">
              <input v-model="examForm.review_options.after_submit[key]" type="checkbox"> {{ label }}
            </label>
          </div>
        </div>
      </div>
      <div class="crud-inline-actions crud-modal-foot"><button class="crud-primary-btn" type="button" @click="createExam">Tạo đề thi</button></div>

      <!-- Exam table -->
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead><tr><th>Tên đề thi</th><th>Thời lượng</th><th>Điểm đạt</th><th>Lượt thi</th><th>Lịch thi</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
          <tbody>
            <tr v-if="loadingExams"><td colspan="7" class="crud-empty">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="currentExams.length === 0"><td colspan="7" class="crud-empty">Chưa có đề thi nào.</td></tr>
            <tr v-for="exam in currentExams" :key="exam.id">
              <td><strong>{{ exam.title }}</strong></td>
              <td>{{ exam.duration || 0 }} phút</td>
              <td>{{ exam.pass_score || 0 }}%</td>
              <td>{{ exam.max_attempts || 1 }}</td>
              <td style="font-size: 0.8rem;">
                <template v-if="exam.starts_at">{{ new Date(exam.starts_at).toLocaleDateString('vi') }}</template>
                <template v-else>—</template>
              </td>
              <td><span class="crud-badge" :style="{ color: statusColor[exam.status || 'draft'] }">{{ statusMap[exam.status || 'draft'] || exam.status }}</span></td>
              <td>
                <div class="crud-actions">
                  <button class="action-btn is-view" type="button" @click="openDetail(exam)">Chi tiết</button>
                  <NuxtLink v-if="exam.id" :to="`/admin/exam-monitor?exam=${exam.id}`" class="action-btn is-edit">Giám sát</NuxtLink>
                  <button class="action-btn is-delete" type="button" @click="selectedExam = exam; confirmOpen = true">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Detail modal -->
    <Teleport to="body"><div v-if="detailOpen" class="crud-modal-backdrop" @click.self="detailOpen = false"><div class="crud-modal crud-modal-wide"><div class="crud-modal-head"><div><p class="section-kicker">Chi tiết đề thi</p><h3>{{ examDetail?.title || selectedExam?.title }}</h3><RichTextContent :content="examDetail?.description" empty-text="Danh sách câu hỏi nằm trong quiz của đề thi." /></div><div class="flex gap-2"><button v-if="activeTab === 'course'" class="crud-primary-btn" type="button" @click="openQuizSetup()">Setup Quiz</button><button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button></div></div><div class="crud-table-wrap"><table class="crud-table"><thead><tr><th>Nội dung câu hỏi</th><th>Loại</th><th>Số đáp án</th><th>Đáp án đúng</th></tr></thead><tbody><tr v-if="!(examDetail?.quiz?.questions || []).length"><td colspan="4" class="crud-empty">Đề thi này chưa có câu hỏi.</td></tr><tr v-for="question in examDetail?.quiz?.questions || []" :key="question.id"><td>{{ question.content }}</td><td>{{ question.type }}</td><td>{{ question.answers?.length || 0 }}</td><td>{{ question.answers?.find(answer => answer.is_correct)?.content || '--' }}</td></tr></tbody></table></div></div></div></Teleport>

    <!-- Quiz Setup Modal -->
    <Teleport to="body">
      <div v-if="quizModalOpen" class="crud-modal-backdrop" @click.self="quizModalOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div><p class="section-kicker">Setup Quiz</p><h3>{{ selectedExam?.title }}</h3></div>
            <button class="topbar-ghost" type="button" @click="quizModalOpen = false">✕</button>
          </div>
          <div class="crud-modal-body">
            <div class="crud-form-grid">
              <label class="crud-field"><span>Tên quiz</span><input v-model="quizTitle" type="text" class="crud-input"></label>
              <div class="crud-field"><span>Mô tả</span><textarea v-model="quizDescription" rows="3" class="crud-input"></textarea></div>
              <label class="crud-field"><span>Thời gian (phút)</span><input v-model.number="quizTimeLimit" type="number" class="crud-input"></label>
              <label class="crud-field"><span>Điểm đạt</span><input v-model.number="quizPassScore" type="number" min="0" max="100" class="crud-input"></label>
            </div>
            <div class="crud-field">
              <label>Chọn câu hỏi từ ngân hàng</label>
              <div v-if="loadingBanks" class="crud-empty">Đang tải...</div>
              <div v-else style="display: flex; flex-direction: column; gap: 1rem;">
                <div v-for="bank in banks" :key="bank.id" style="border: 1px solid var(--border-color, #e0e0e0); border-radius: 8px; padding: 1rem;">
                  <h4 style="font-weight: 600;">{{ bank.name }}</h4>
                  <div style="margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.25rem;">
                    <div v-for="question in bank.questions || []" :key="question.id" style="display: flex; align-items: center; gap: 0.5rem;">
                      <input :id="'q-' + question.id" type="checkbox" :checked="selectedQuestionIds.includes(question.id)" @change="toggleQuestionSelection(question.id)">
                      <label :for="'q-' + question.id" style="font-size: 0.85rem;">{{ question.content }}</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="crud-modal-foot">
            <button type="button" class="crud-btn-secondary" @click="quizModalOpen = false">Hủy</button>
            <button type="button" class="crud-primary-btn" @click="saveQuizSetup">Lưu</button>
          </div>
        </div>
      </div>
    </Teleport>
    <CrudConfirmModal :open="confirmOpen" title="Xóa đề thi" :description="`Bạn có chắc chắn muốn xóa ${selectedExam?.title || 'đề thi này'}? Thao tác này không thể hoàn tác.`" confirm-text="Xóa đề thi" tone="danger" @close="confirmOpen = false" @confirm="deleteExam" />
  </AdminWorkspaceShell>
</template>

<style scoped>
.crud-tab-btn {
  padding: 0.75rem 1.5rem; border: none; background: transparent; cursor: pointer;
  font-weight: 600; font-size: 0.9rem; color: var(--text-secondary, #666);
  border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s;
}
.crud-tab-btn.active { color: var(--primary, #1976d2); border-bottom-color: var(--primary, #1976d2); }
.crud-tab-btn:hover { color: var(--primary, #1976d2); }
</style>
