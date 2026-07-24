<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import RichTextContent from '~/components/dashboard/RichTextContent.vue'
import { useToast } from '~/composables/useToast'

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
const toast = useToast()
const confirmOpen = ref(false)
const deletingQuestion = ref<QuestionItem | null>(null)
const search = ref('')
const typeFilter = ref('')
const expandedRows = ref({})

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
  { value: 1, label: 'Nhận biết', color: 'var(--green)' },
  { value: 2, label: 'Thông hiểu', color: 'var(--green)' },
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
const filteredRows = computed(() => {
  const query = search.value.trim().toLowerCase()
  return allRows.value.filter(row =>
    (!typeFilter.value || row.type === typeFilter.value)
    && (!query || row.code?.toLowerCase().includes(query) || row.content.toLowerCase().includes(query)),
  )
})

async function fetchBank() {
  if (!courseId || !bankId) return
  loading.value = true
  try {
    bank.value = await useApi<BankDetail>(`/courses/${courseId}/question-banks/${bankId}`, { headers: authHeaders() })
  } catch {
    toast.error('Không thể tải thông tin ngân hàng câu hỏi.')
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
    toast.success('Đã xóa câu hỏi.')
    confirmOpen.value = false
    deletingQuestion.value = null
    await fetchBank()
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể xóa câu hỏi.')
  }
}

function goBack() {
  navigateTo(`/admin/question-bank`)
}

onMounted(fetchBank)
</script>

<template>
  <div class="assessment-page">
    <Toast />
    <div class="page-heading">
      <div><span>Khảo thí</span><h1>{{ bank?.name || 'Chi tiết ngân hàng câu hỏi' }}</h1><p>{{ allRows.length }} câu hỏi trong ngân hàng</p></div>
      <div class="heading-actions"><Button label="Quay lại" icon="pi pi-arrow-left" severity="secondary" outlined @click="goBack" /><Button label="Thêm câu hỏi" icon="pi pi-plus" @click="goToCreate" /></div>
    </div>

    <Card v-if="bank?.description"><template #title>Mô tả</template><template #content><RichTextContent :content="bank.description" /></template></Card>
    <Card>
      <template #title>Danh sách câu hỏi</template>
      <template #content>
        <div class="filter-grid">
          <label class="field"><span>Tìm kiếm</span><InputText v-model="search" placeholder="Mã hoặc nội dung câu hỏi..." /></label>
          <label class="field"><span>Loại câu hỏi</span><Select v-model="typeFilter" :options="[{ value: '', label: 'Tất cả' }, ...questionTypes]" option-label="label" option-value="value" /></label>
        </div>
        <DataTable v-model:expanded-rows="expandedRows" :value="filteredRows" :loading="loading" data-key="id" paginator :rows="15" :rows-per-page-options="[15, 30, 50]" striped-rows responsive-layout="scroll" class="question-table">
          <template #empty>Ngân hàng này chưa có câu hỏi.</template>
          <Column expander style="width:3rem" />
          <Column field="code" header="Mã" sortable><template #body="{ data }"><code>{{ data.code || '—' }}</code></template></Column>
          <Column field="content" header="Nội dung"><template #body="{ data }"><div class="content-cell"><RichTextContent :content="data.content" compact empty-text="—" /></div></template></Column>
          <Column field="type" header="Loại" sortable><template #body="{ data }"><Tag :value="typeLabel(data.type)" severity="info" /></template></Column>
          <Column field="difficulty" header="Độ khó" sortable><template #body="{ data }"><Tag :value="difficultyLabel(data.difficulty || 1)" :style="{ color: difficultyColor(data.difficulty || 1) }" /></template></Column>
          <Column header="Thao tác" frozen align-frozen="right"><template #body="{ data }"><div class="row-actions"><Button icon="pi pi-pencil" size="small" text aria-label="Sửa" @click="goToEdit(data)" /><Button icon="pi pi-trash" size="small" text severity="danger" aria-label="Xóa" @click="deletingQuestion = data; confirmOpen = true" /></div></template></Column>
          <template #expansion="{ data }"><div class="expansion-grid"><div><b>Nhóm</b><span>{{ data.group }}</span></div><div><b>Điểm</b><span>{{ data.default_score ?? 1 }}</span></div><div><b>Đáp án đúng</b><span>{{ data.answers?.find(a => a.is_correct)?.content || '—' }}</span></div><div class="full"><b>Nội dung đầy đủ</b><RichTextContent :content="data.content" /></div></div></template>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="confirmOpen" modal header="Xóa câu hỏi" :style="{ width: 'min(30rem, 95vw)' }">
      <p>Bạn có chắc muốn xóa câu hỏi <strong>{{ deletingQuestion?.code || '' }}</strong>? Thao tác này không thể hoàn tác.</p>
      <template #footer><Button label="Hủy" severity="secondary" text @click="confirmOpen = false" /><Button label="Xóa câu hỏi" icon="pi pi-trash" severity="danger" @click="deleteQuestion" /></template>
    </Dialog>
  </div>
</template>

<style scoped>
.assessment-page{display:grid;gap:1.25rem}.page-heading{display:flex;justify-content:space-between;gap:1rem;align-items:flex-end;flex-wrap:wrap}.page-heading>div:first-child>span{color:var(--p-text-muted-color);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.page-heading h1{margin:.2rem 0;font-size:1.55rem}.page-heading p{margin:0;color:var(--p-text-muted-color)}.heading-actions,.row-actions{display:flex;gap:.5rem}.filter-grid{display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem}.field{display:grid;gap:.45rem;font-size:.82rem;font-weight:600}.field :deep(.p-inputtext),.field :deep(.p-select){width:100%}.content-cell{max-width:30rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.expansion-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;padding:1rem}.expansion-grid>div{display:grid;gap:.25rem}.expansion-grid b{font-size:.75rem;color:var(--p-text-muted-color)}.expansion-grid .full{grid-column:1/-1}.assessment-page :deep(.p-card){border:1px solid var(--p-content-border-color);box-shadow:none}@media(max-width:700px){.filter-grid,.expansion-grid{grid-template-columns:1fr}.expansion-grid .full{grid-column:auto}}
</style>
