<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import RichTextContent from '~/components/dashboard/RichTextContent.vue'

definePageMeta({ layout: 'admin' })

const route = useRoute()
const bankId = route.params.id as string
const courseId = route.query.courseId as string

const user = useAuthUserCookie(); const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface QuestionItem { id: number; code?: string; content: string; type: string; difficulty?: number | null; default_score?: number; answers?: { id: number; content: string; is_correct: boolean }[] }
interface BankDetail { id: number; name: string; description?: string | null; questions?: QuestionItem[]; groups?: { id: number; name: string; questions?: QuestionItem[] }[] }

const bank = ref<BankDetail | null>(null)
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const confirmOpen = ref(false)
const deletingQuestion = ref<QuestionItem | null>(null)

const questionTypes = [
  { value: 'single_choice', label: 'Trắc nghiệm 1 đáp án' },
  { value: 'multiple_choice', label: 'Nhiều đáp án' },
  { value: 'true_false', label: 'Đúng/Sai' },
  { value: 'short_answer', label: 'Trả lời ngắn' },
  { value: 'numerical', label: 'Số' },
  { value: 'essay', label: 'Tự luận' },
  { value: 'matching', label: 'Ghép đôi' },
  { value: 'ordering', label: 'Sắp xếp' },
]

const difficultyLevels = [
  { value: 1, label: 'Nhận biết', color: '#4caf50' },
  { value: 2, label: 'Thông hiểu', color: '#2196f3' },
  { value: 3, label: 'Vận dụng', color: '#ff9800' },
  { value: 4, label: 'Vận dụng cao', color: '#f44336' },
  { value: 5, label: 'Sáng tạo', color: '#9c27b0' },
]

const typeLabel = (type: string) => questionTypes.find(t => t.value === type)?.label || type
const difficultyLabel = (level: number) => difficultyLevels.find(d => d.value === level)?.label || `${level}`
const difficultyColor = (level: number) => difficultyLevels.find(d => d.value === level)?.color || '#999'

const allRows = computed(() => [
  ...(bank.value?.questions || []).map(q => ({ group: 'Chưa phân nhóm', ...q })),
  ...((bank.value?.groups || []).flatMap(g => (g.questions || []).map(q => ({ group: g.name, ...q })))),
])

async function fetchBank() {
  if (!courseId || !bankId) return
  loading.value = true
  try {
    bank.value = await useApi<BankDetail>(`/courses/${courseId}/question-banks/${bankId}`, { headers: authHeaders() })
  } catch {
    errorMessage.value = 'Không thể tải thông tin ngân hàng câu hỏi.'
  } finally {
    loading.value = false
  }
}

function goToCreate() {
  navigateTo(`/admin/question-bank/create?courseId=${courseId}&bankId=${bankId}`)
}

function goToEdit(question: QuestionItem) {
  navigateTo(`/admin/question-bank/edit?courseId=${courseId}&bankId=${bankId}&questionId=${question.id}`)
}

async function deleteQuestion() {
  if (!deletingQuestion.value) return
  try {
    await useApi(`/courses/${courseId}/question-banks/${bankId}/questions/${deletingQuestion.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa câu hỏi.'
    confirmOpen.value = false
    deletingQuestion.value = null
    await fetchBank()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa câu hỏi.'
  }
}

function goBack() {
  navigateTo(`/admin/question-bank`)
}

onMounted(fetchBank)
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý thi', 'Ngân hàng câu hỏi', bank?.name || 'Chi tiết']"
    :description="bank?.description ? '' : 'Danh sách câu hỏi trong ngân hàng.'"
    :title="bank?.name || 'Chi tiết ngân hàng câu hỏi'"
  >
    <div class="qb-topbar">
      <button class="crud-secondary-btn" type="button" @click="goBack">← Quay lại</button>
      <button class="crud-primary-btn" type="button" @click="goToCreate">+ Thêm câu hỏi</button>
    </div>

    <div v-if="bank?.description" class="qb-description dashboard-card">
      <RichTextContent :content="bank.description" />
    </div>

    <div v-if="loading" style="padding: 40px; text-align: center; color: var(--muted);">Đang tải dữ liệu...</div>

    <template v-else>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <section class="dashboard-card crud-panel">
        <div class="crud-toolbar">
          <div>
            <p class="section-kicker">Tổng cộng {{ allRows.length }} câu hỏi</p>
          </div>
        </div>
        <div class="crud-table-wrap">
          <table class="crud-table">
            <thead>
              <tr>
                <th>Mã</th>
                <th>Nhóm</th>
                <th>Nội dung</th>
                <th>Loại</th>
                <th>Độ khó</th>
                <th>Điểm</th>
                <th>Đáp án đúng</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="allRows.length === 0"><td colspan="8" class="crud-empty">Ngân hàng này chưa có câu hỏi.</td></tr>
              <tr v-for="row in allRows" :key="row.id">
                <td><code class="qb-code">{{ row.code || '—' }}</code></td>
                <td>{{ row.group }}</td>
                <td class="qb-content-cell"><RichTextContent :content="row.content" compact empty-text="—" /></td>
                <td><span class="crud-badge">{{ typeLabel(row.type) }}</span></td>
                <td><span :style="{ color: difficultyColor(row.difficulty || 1), fontWeight: 600 }">{{ difficultyLabel(row.difficulty || 1) }}</span></td>
                <td>{{ row.default_score ?? 1 }}</td>
                <td>{{ row.answers?.find(a => a.is_correct)?.content || '--' }}</td>
                <td>
                  <div class="crud-actions">
                    <button class="action-btn is-edit" type="button" @click="goToEdit(row)">Sửa</button>
                    <button class="action-btn is-delete" type="button" @click="deletingQuestion = row; confirmOpen = true">Xóa</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <CrudConfirmModal
      :open="confirmOpen"
      title="Xóa câu hỏi"
      :description="`Bạn có chắc chắn muốn xóa câu hỏi '${deletingQuestion?.code || ''}' ? Thao tác này không thể hoàn tác.`"
      confirm-text="Xóa câu hỏi"
      tone="danger"
      @close="confirmOpen = false"
      @confirm="deleteQuestion"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.qb-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 12px;
  flex-wrap: wrap;
}

.qb-description {
  padding: 16px 20px;
  margin-bottom: 20px;
}

.qb-code {
  font-size: 0.75rem;
  background: var(--surface-high, #f0f0f0);
  padding: 2px 6px;
  border-radius: 4px;
}

.qb-content-cell {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
