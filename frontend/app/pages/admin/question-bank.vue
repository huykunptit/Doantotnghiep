<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học để quản lý ngân hàng câu hỏi...' })
interface CourseItem { id: number; title: string; thumbnail?: string | null; category?: { name: string } | null }
interface BankItem { id: number; name: string; description?: string | null; questions_count?: number; groups_count?: number }
interface QuestionItem { id: number; content: string; type: string; difficulty?: number | null; answers?: { id: number; content: string; is_correct: boolean }[] }
interface BankDetail extends BankItem { questions?: QuestionItem[]; groups?: { id: number; name: string; questions?: QuestionItem[] }[] }
const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const courses = ref<CourseItem[]>([]); const banks = ref<BankItem[]>([]); const selectedCourseId = ref<number | null>(null)
const bankName = ref(''); const bankDescription = ref(''); const loadingCourses = ref(false); const loadingBanks = ref(false)
const detailOpen = ref(false); const confirmOpen = ref(false); const selectedBank = ref<BankItem | null>(null); const bankDetail = ref<BankDetail | null>(null)
const errorMessage = ref(''); const successMessage = ref('')
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const selectedCourse = computed(() => courses.value.find(item => item.id === selectedCourseId.value))
const detailRows = computed(() => [
  ...(bankDetail.value?.questions || []).map(q => ({ group: 'Chưa phân nhóm', ...q })),
  ...((bankDetail.value?.groups || []).flatMap(group => (group.questions || []).map(q => ({ group: group.name, ...q })))),
])
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

    <Teleport to="body"><div v-if="detailOpen" class="crud-modal-backdrop" @click.self="detailOpen = false"><div class="crud-modal crud-modal-wide"><div class="crud-modal-head"><div><p class="section-kicker">Chi tiết ngân hàng câu hỏi</p><h3>{{ bankDetail?.name || selectedBank?.name }}</h3><RichTextContent :content="bankDetail?.description" empty-text="Danh sách câu hỏi theo nhóm trong ngân hàng đã chọn." /></div><button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button></div><div class="crud-table-wrap"><table class="crud-table"><thead><tr><th>Nhóm</th><th>Nội dung câu hỏi</th><th>Loại</th><th>Độ khó</th><th>Đáp án đúng</th></tr></thead><tbody><tr v-if="detailRows.length === 0"><td colspan="5" class="crud-empty">Ngân hàng này chưa có câu hỏi.</td></tr><tr v-for="row in detailRows" :key="row.id"><td>{{ row.group }}</td><td>{{ row.content }}</td><td>{{ row.type }}</td><td>{{ row.difficulty || 1 }}</td><td>{{ row.answers?.find(answer => answer.is_correct)?.content || '--' }}</td></tr></tbody></table></div></div></div></Teleport>
    <CrudConfirmModal :open="confirmOpen" title="Xóa ngân hàng câu hỏi" :description="`Bạn có chắc chắn muốn xóa ${selectedBank?.name || 'mục này'}? Thao tác này không thể hoàn tác.`" confirm-text="Xóa ngân hàng" tone="danger" @close="confirmOpen = false" @confirm="deleteBank" />
  </AdminWorkspaceShell>
</template>
