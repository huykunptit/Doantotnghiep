<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import RichTextContent from '~/components/dashboard/RichTextContent.vue'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý ngân hàng câu hỏi...' })
interface CourseItem { id: number; title: string; thumbnail?: string | null; category?: { name: string } | null }
interface BankItem { id: number; name: string; description?: string | null; questions_count?: number; groups_count?: number }

const user = useAuthUserCookie(); const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const courses = ref<CourseItem[]>([])
const banks = ref<BankItem[]>([])
const selectedCourseId = ref<number | null>(null)
const loadingCourses = ref(false)
const loadingBanks = ref(false)
const toast = useToast()
// Create bank modal
const createOpen = ref(false)
const bankName = ref('')
const bankDescription = ref('')
const search = ref('')

// Delete bank
const confirmOpen = ref(false)
const selectedBank = ref<BankItem | null>(null)

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const selectedCourse = computed(() => courses.value.find(item => item.id === selectedCourseId.value))
const filteredBanks = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return banks.value
  return banks.value.filter(bank =>
    bank.name.toLowerCase().includes(query)
    || (bank.description || '').toLowerCase().includes(query),
  )
})

async function fetchCourses() {
  loadingCourses.value = true
  try {
    const response = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100', { headers: authHeaders() })
    courses.value = response.data
    if (!selectedCourseId.value && response.data.length) {
      selectedCourseId.value = response.data[0].id
      await fetchBanks()
    }
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tải danh sách khóa học.')
  } finally {
    loadingCourses.value = false
  }
}

async function fetchBanks() {
  if (!selectedCourseId.value) return
  loadingBanks.value = true
  try {
    const response = await useApi<{ banks: BankItem[] }>(`/courses/${selectedCourseId.value}/question-banks`, { headers: authHeaders() })
    banks.value = response.banks || []
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tải ngân hàng câu hỏi.')
  } finally {
    loadingBanks.value = false
  }
}

async function createBank() {
  if (!selectedCourseId.value || !bankName.value.trim()) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/question-banks`, {
      method: 'POST', headers: authHeaders(),
      body: { name: bankName.value.trim(), description: bankDescription.value || null },
    })
    bankName.value = ''
    bankDescription.value = ''
    createOpen.value = false
    toast.success('Đã tạo ngân hàng câu hỏi.')
    await fetchBanks()
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tạo ngân hàng câu hỏi.')
  }
}

async function deleteBank() {
  if (!selectedCourseId.value || !selectedBank.value) return
  try {
    await useApi(`/courses/${selectedCourseId.value}/question-banks/${selectedBank.value.id}`, { method: 'DELETE', headers: authHeaders() })
    toast.success('Đã xóa ngân hàng câu hỏi.')
    confirmOpen.value = false
    selectedBank.value = null
    await fetchBanks()
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể xóa ngân hàng câu hỏi.')
  }
}

function viewBank(bank: BankItem) {
  if (!selectedCourseId.value) return
  navigateTo(`/admin/question-bank/${bank.id}?courseId=${selectedCourseId.value}`)
}

const { exportToCSV } = useExport()

function exportData() {
  const cols = [
    { key: 'id', label: 'ID Ngân hàng' },
    { key: 'name', label: 'Tên ngân hàng câu hỏi' },
    { key: 'description', label: 'Mô tả', format: (val: any) => String(val || '--') },
    { key: 'questions_count', label: 'Số câu hỏi', format: (val: any) => String(val || 0) },
    { key: 'groups_count', label: 'Số nhóm', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(banks.value, cols, `ngan_hang_cau_hoi_khoa_${selectedCourseId.value || 'temp'}`)
}

function onCourseChange() {
  fetchBanks()
}

onMounted(fetchCourses)
</script>

<template>
  <div class="assessment-page">
    <Toast />
    <div class="page-heading">
      <div><span>Khảo thí</span><h1>Quản lý ngân hàng câu hỏi</h1><p>Quản lý ngân hàng câu hỏi theo khóa học.</p></div>
      <div class="heading-actions">
        <Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined @click="exportData" />
        <Button label="Thêm ngân hàng" icon="pi pi-plus" :disabled="!selectedCourseId" @click="createOpen = true" />
      </div>
    </div>

    <Card>
      <template #title>Bộ lọc</template>
      <template #content>
        <div class="filter-grid">
          <label class="field"><span>Khóa học</span><Select v-model="selectedCourseId" :options="courses" option-label="title" option-value="id" filter :loading="loadingCourses" placeholder="Chọn khóa học" @change="onCourseChange" /></label>
          <label class="field"><span>Tìm ngân hàng</span><InputText v-model="search" placeholder="Tên hoặc mô tả..." /></label>
        </div>
      </template>
    </Card>

    <Card>
      <template #title>{{ selectedCourse?.title || 'Chưa chọn khóa học' }}</template>
      <template #subtitle>{{ filteredBanks.length }} ngân hàng câu hỏi</template>
      <template #content>
        <DataTable :value="filteredBanks" :loading="loadingBanks" paginator :rows="10" :rows-per-page-options="[10, 20, 50]" striped-rows responsive-layout="scroll" data-key="id">
          <template #empty>Khóa học này chưa có ngân hàng câu hỏi.</template>
          <Column field="name" header="Tên ngân hàng" sortable><template #body="{ data }"><strong>{{ data.name }}</strong></template></Column>
          <Column header="Mô tả"><template #body="{ data }"><div class="description-cell"><RichTextContent :content="data.description" compact empty-text="Chưa có mô tả." /></div></template></Column>
          <Column field="questions_count" header="Câu hỏi" sortable><template #body="{ data }"><Tag :value="String(data.questions_count || 0)" severity="info" /></template></Column>
          <Column field="groups_count" header="Nhóm" sortable><template #body="{ data }">{{ data.groups_count || 0 }}</template></Column>
          <Column header="Thao tác" frozen align-frozen="right">
            <template #body="{ data }"><div class="row-actions">
              <Button icon="pi pi-eye" label="Chi tiết" size="small" text @click="viewBank(data)" />
              <Button icon="pi pi-trash" severity="danger" size="small" text aria-label="Xóa" @click="selectedBank = data; confirmOpen = true" />
            </div></template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="createOpen" modal header="Tạo ngân hàng câu hỏi" :style="{ width: 'min(34rem, 95vw)' }">
      <div class="dialog-form">
        <label class="field"><span>Tên ngân hàng <b>*</b></span><InputText v-model="bankName" autofocus placeholder="Ví dụ: Bộ câu hỏi giữa kỳ" /></label>
        <label class="field"><span>Mô tả ngắn</span><Textarea v-model="bankDescription" rows="4" auto-resize placeholder="Mô tả phạm vi câu hỏi..." /></label>
      </div>
      <template #footer><Button label="Hủy" severity="secondary" text @click="createOpen = false" /><Button label="Tạo mới" icon="pi pi-check" :disabled="!bankName.trim()" @click="createBank" /></template>
    </Dialog>

    <Dialog v-model:visible="confirmOpen" modal header="Xóa ngân hàng câu hỏi" :style="{ width: 'min(30rem, 95vw)' }">
      <p>Bạn có chắc muốn xóa <strong>{{ selectedBank?.name }}</strong>? Thao tác này không thể hoàn tác.</p>
      <template #footer><Button label="Hủy" severity="secondary" text @click="confirmOpen = false" /><Button label="Xóa ngân hàng" icon="pi pi-trash" severity="danger" @click="deleteBank" /></template>
    </Dialog>
  </div>
</template>

<style scoped>
.assessment-page{display:grid;gap:1.25rem}.page-heading{display:flex;justify-content:space-between;gap:1rem;align-items:flex-end;flex-wrap:wrap}.page-heading span{color:var(--p-text-muted-color);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em}.page-heading h1{margin:.2rem 0;font-size:1.55rem}.page-heading p{margin:0;color:var(--p-text-muted-color);font-size:.9rem}.heading-actions,.row-actions{display:flex;gap:.5rem;flex-wrap:wrap}.filter-grid{display:grid;grid-template-columns:minmax(16rem,2fr) minmax(14rem,1fr);gap:1rem}.field{display:grid;gap:.45rem;font-size:.82rem;font-weight:600}.field b{color:var(--p-red-500)}.field :deep(.p-inputtext),.field :deep(.p-textarea){width:100%}.dialog-form{display:grid;gap:1rem}.description-cell{max-width:32rem}.assessment-page :deep(.p-card){border:1px solid var(--p-content-border-color);box-shadow:none}@media(max-width:700px){.filter-grid{grid-template-columns:1fr}.page-heading{align-items:flex-start}}
</style>
