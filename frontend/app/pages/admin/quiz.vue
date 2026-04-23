<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý quiz / đề thi...' })
interface CourseItem { id: number; title: string; category?: { name: string } | null }
interface ExamItem { id: number; title: string; description?: string | null; duration?: number | null; pass_score?: number | null; status?: string | null }
interface ExamDetail extends ExamItem { quiz?: { questions?: { id: number; content: string; type: string; answers?: { id: number; content: string; is_correct: boolean }[] }[] } | null }
const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const courses = ref<CourseItem[]>([]); const exams = ref<ExamItem[]>([]); const selectedCourseId = ref<number | null>(null)
const title = ref(''); const description = ref(''); const duration = ref(30); const passScore = ref(70)
const loadingCourses = ref(false); const loadingExams = ref(false); const detailOpen = ref(false); const confirmOpen = ref(false)
const selectedExam = ref<ExamItem | null>(null); const examDetail = ref<ExamDetail | null>(null); const errorMessage = ref(''); const successMessage = ref('')
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const selectedCourse = computed(() => courses.value.find(item => item.id === selectedCourseId.value))
async function fetchCourses() {
  loadingCourses.value = true
  try {
    const response = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    const items = response.data || []
    courses.value = items
    if (!selectedCourseId.value && items.length > 0) {
      selectedCourseId.value = items[0]!.id
      await fetchExams()
    }
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải danh sách khóa học.' } finally { loadingCourses.value = false }
}
async function fetchExams() {
  if (!selectedCourseId.value) return
  loadingExams.value = true
  try {
    exams.value = await useApi<ExamItem[]>(`/courses/${selectedCourseId.value}/exams`, { headers: authHeaders() })
  } catch (error: any) { 
    errorMessage.value = error?.data?.message || 'Không thể tải danh sách quiz / đề thi.' 
  } finally { loadingExams.value = false }
}
async function createExam() {
  if (!selectedCourseId.value || !title.value.trim()) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/exams`, { method: 'POST', headers: authHeaders(), body: { title: title.value.trim(), description: description.value || null, duration: duration.value, pass_score: passScore.value } })
    title.value = ''; description.value = ''; duration.value = 30; passScore.value = 70; successMessage.value = 'Đã tạo quiz / đề thi mới.'; await fetchExams()
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tạo quiz / đề thi.' }
}
async function openDetail(exam: ExamItem) {
  if (!selectedCourseId.value) return
  selectedExam.value = exam; detailOpen.value = true
  examDetail.value = await useApi<ExamDetail>(`/courses/${selectedCourseId.value}/exams/${exam.id}`, { headers: authHeaders() })
}
async function deleteExam() {
  if (!selectedCourseId.value || !selectedExam.value) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/exams/${selectedExam.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa quiz / đề thi.'; confirmOpen.value = false; detailOpen.value = false; selectedExam.value = null; await fetchExams()
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể xóa quiz / đề thi.' }
}
function onCourseChange() {
  fetchExams()
}
onMounted(fetchCourses)
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý thi', 'Quiz / Đề thi']" description="Quản lý đề thi theo từng khóa học. Chọn khóa học từ dropdown phía trên, sau đó xem và quản lý danh sách đề thi bên dưới." title="Quản lý quiz / đề thi">
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
          <p class="section-kicker">Quiz / Đề thi</p>
          <h3>{{ selectedCourse?.title || 'Chưa chọn khóa học' }}</h3>
        </div>
      </div>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>
      <div class="crud-form-grid">
        <label class="crud-field"><span>Tên đề thi</span><input v-model="title" type="text" placeholder="Ví dụ: Đề thi giữa kỳ"></label>
        <div class="crud-field"><span>Mô tả</span><RichTextEditor v-model="description" placeholder="Mô tả phạm vi kiến thức" enable-images upload-folder="courses" /></div>
        <label class="crud-field"><span>Thời lượng (phút)</span><input v-model="duration" type="number" min="1"></label>
        <label class="crud-field"><span>Điểm đạt</span><input v-model="passScore" type="number" min="1" max="100"></label>
      </div>
      <div class="crud-inline-actions crud-modal-foot"><button class="crud-primary-btn" type="button" @click="createExam">Tạo đề thi</button></div>
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead><tr><th>Tên đề thi</th><th>Thời lượng</th><th>Điểm đạt</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
          <tbody>
            <tr v-if="loadingExams"><td colspan="5" class="crud-empty">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="exams.length === 0"><td colspan="5" class="crud-empty">Khóa học này chưa có đề thi nào.</td></tr>
            <tr v-for="exam in exams" :key="exam.id">
              <td><strong>{{ exam.title }}</strong><RichTextContent :content="exam.description" compact empty-text="Chưa có mô tả." /></td>
              <td>{{ exam.duration || 0 }} phút</td>
              <td>{{ exam.pass_score || 0 }}</td>
              <td><span class="crud-badge role-admin">{{ exam.status || 'draft' }}</span></td>
              <td>
                <div class="crud-actions">
                  <button class="action-btn is-view" type="button" @click="openDetail(exam)">Xem chi tiết</button>
                  <button class="action-btn is-delete" type="button" @click="selectedExam = exam; confirmOpen = true">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Teleport to="body"><div v-if="detailOpen" class="crud-modal-backdrop" @click.self="detailOpen = false"><div class="crud-modal crud-modal-wide"><div class="crud-modal-head"><div><p class="section-kicker">Chi tiết đề thi</p><h3>{{ examDetail?.title || selectedExam?.title }}</h3><RichTextContent :content="examDetail?.description" empty-text="Danh sách câu hỏi nằm trong quiz của đề thi." /></div><button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button></div><div class="crud-table-wrap"><table class="crud-table"><thead><tr><th>Nội dung câu hỏi</th><th>Loại</th><th>Số đáp án</th><th>Đáp án đúng</th></tr></thead><tbody><tr v-if="!(examDetail?.quiz?.questions || []).length"><td colspan="4" class="crud-empty">Đề thi này chưa có câu hỏi.</td></tr><tr v-for="question in examDetail?.quiz?.questions || []" :key="question.id"><td>{{ question.content }}</td><td>{{ question.type }}</td><td>{{ question.answers?.length || 0 }}</td><td>{{ question.answers?.find(answer => answer.is_correct)?.content || '--' }}</td></tr></tbody></table></div></div></div></Teleport>
    <CrudConfirmModal :open="confirmOpen" title="Xóa đề thi" :description="`Bạn có chắc chắn muốn xóa ${selectedExam?.title || 'đề thi này'}? Thao tác này không thể hoàn tác.`" confirm-text="Xóa đề thi" tone="danger" @close="confirmOpen = false" @confirm="deleteExam" />
  </AdminWorkspaceShell>
</template>
