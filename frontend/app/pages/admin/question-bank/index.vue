<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import RichTextContent from '~/components/dashboard/RichTextContent.vue'
import SearchableCourseSelect from '~/components/dashboard/SearchableCourseSelect.vue'
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

// Delete bank
const confirmOpen = ref(false)
const selectedBank = ref<BankItem | null>(null)

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const selectedCourse = computed(() => courses.value.find(item => item.id === selectedCourseId.value))

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
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý thi', 'Ngân hàng câu hỏi']"
    description="Quản lý ngân hàng câu hỏi theo khóa học."
    title="Quản lý ngân hàng câu hỏi"
  >
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

    <!-- Bank list -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Ngân hàng câu hỏi</p>
          <h3>{{ selectedCourse?.title || 'Chưa chọn khóa học' }}</h3>
        </div>
        <div class="crud-toolbar-right">
          <button class="crud-export-btn" type="button" @click="exportData">
            <span class="material-symbols-outlined">download</span>
            Xuất Excel
          </button>
          <button class="crud-primary-btn" type="button" @click="createOpen = true">+ Thêm mới</button>
        </div>
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Tên ngân hàng</th>
              <th>Mô tả</th>
              <th>Số câu hỏi</th>
              <th>Số nhóm</th>
              <th>Thao tác</th>
            </tr>
          </thead>
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
                  <button class="action-btn is-view" type="button" @click="viewBank(bank)">Xem chi tiết</button>
                  <button class="action-btn is-delete" type="button" @click="selectedBank = bank; confirmOpen = true">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Create bank modal -->
    <Teleport to="body">
      <div v-if="createOpen" class="crud-modal-backdrop" @click.self="createOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <h3>Tạo ngân hàng câu hỏi mới</h3>
            <button class="topbar-ghost" type="button" @click="createOpen = false">✕</button>
          </div>
          <div class="crud-modal-body">
            <label class="crud-field">
              <span>Tên ngân hàng câu hỏi <span style="color: #ef4444">*</span></span>
              <input v-model="bankName" type="text" placeholder="Ví dụ: Bộ câu hỏi giữa kỳ">
            </label>
            <label class="crud-field">
              <span>Mô tả ngắn</span>
              <textarea v-model="bankDescription" rows="3" placeholder="Mô tả phạm vi câu hỏi trong ngân hàng này..." />
            </label>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="createOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" :disabled="!bankName.trim()" @click="createBank">Tạo mới</button>
          </div>
        </div>
      </div>
    </Teleport>

    <CrudConfirmModal
      :open="confirmOpen"
      title="Xóa ngân hàng câu hỏi"
      :description="`Bạn có chắc chắn muốn xóa ${selectedBank?.name || 'mục này'}? Thao tác này không thể hoàn tác.`"
      confirm-text="Xóa ngân hàng"
      tone="danger"
      @close="confirmOpen = false"
      @confirm="deleteBank"
    />
  </AdminWorkspaceShell>
</template>
