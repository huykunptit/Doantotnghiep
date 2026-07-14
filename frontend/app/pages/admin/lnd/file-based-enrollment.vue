<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from '~/composables/useToast'
// Icons removed - using PrimeIcons
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'instructor'],
  adminSearchPlaceholder: 'Quản trị ghi danh...',
})

type Id = number

interface TermItem {
  id: Id
  name: string
  code: string
  is_current?: boolean
}

interface PreviewRow {
  row_number: number
  student_code: string
  student_name: string | null
  course_code: string
  course_title: string | null
  status: 'valid' | 'invalid'
  message: string
}

interface ImportPreviewResponse {
  import_token: string
  total_rows: number
  valid_rows: number
  invalid_rows: number
  preview_data: PreviewRow[]
}

const token = useAuthTokenCookie()
const toast = useToast()

function headers() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

const terms = ref<TermItem[]>([])
const selectedTermId = ref<Id | ''>('')
const loading = ref(false)
const processing = ref(false)

// Wizard Steps: 1 = upload/select, 2 = validate/preview, 3 = complete
const currentStep = ref<1 | 2 | 3>(1)

const fileInputRef = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)

const previewResult = ref<ImportPreviewResponse | null>(null)
const importedCount = ref(0)

onMounted(async () => {
  await loadTerms()
})

async function loadTerms() {
  loading.value = true
  try {
    const res = await useApi<{ data: TermItem[] }>('/admin/academic/terms?per_page=100', { headers: headers() })
    terms.value = res.data
    const current = terms.value.find(t => t.is_current) || terms.value[0]
    if (current) selectedTermId.value = current.id
  } catch (e) {
    toast.error('Không thể tải học kỳ.')
  } finally {
    loading.value = false
  }
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0]
  }
}

function triggerFileSelect() {
  fileInputRef.value?.click()
}

// Download Sample Template CSV
function downloadTemplate() {
  const csvContent = "Mã Sinh Viên,Mã Môn Học / Tên Môn Học (hoặc ID)\nB20DCCN001,INT1306\nB20DCCN002,15\nB20DCCN003,Cấu trúc dữ liệu\n"
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', 'mau_ghi_danh_sinh_vien.csv')
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// Step 1: Upload and Preview
async function uploadAndValidate() {
  if (!selectedFile.value) {
    toast.error('Vui lòng chọn tệp CSV để nhập.')
    return
  }

  loading.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const res = await useApi<ImportPreviewResponse>('/admin/academic/enrollments/import-preview', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`
      },
      body: formData
    })

    previewResult.value = res
    currentStep.value = 2
    toast.success('Kiểm tra tệp hoàn tất. Hãy xác nhận kết quả xem trước.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Có lỗi xảy ra khi phân tích tệp. Hãy chắc chắn định dạng CSV hợp lệ.')
  } finally {
    loading.value = false
  }
}

// Step 2: Confirm and Save
async function executeImport() {
  if (!previewResult.value || !previewResult.value.import_token) return
  
  processing.value = true
  try {
    const res = await useApi<{ created: number }>('/admin/academic/enrollments/import-execute', {
      method: 'POST',
      headers: headers(),
      body: {
        import_token: previewResult.value.import_token,
        term_id: selectedTermId.value || null
      }
    })

    importedCount.value = res.created
    currentStep.value = 3
    toast.success(`Ghi danh thành công cho ${res.created} học viên!`)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể thực hiện ghi danh.')
  } finally {
    processing.value = false
  }
}

function resetWizard() {
  selectedFile.value = null
  previewResult.value = null
  currentStep.value = 1
}
</script>

<template>
  <AdminWorkspaceShell
    title="Ghi Danh Học Viên Bằng Tệp"
    description="Nhập hàng loạt sinh viên vào các lớp học phần/môn học từ bảng tính CSV/Excel"
    :breadcrumb="['Trang chủ', 'Đào tạo', 'Ghi Danh Bằng Tệp']"
  >

    <!-- Stepper Navigation -->
    <div class="import-stepper dashboard-card">
      <div class="step-item" :class="{ 'is-active': currentStep === 1, 'is-done': currentStep > 1 }">
        <span class="step-num">1</span>
        <span class="step-label">Tải tệp & Lọc kỳ</span>
      </div>
      <i class="pi pi-chevron-right" style="font-size:1.0rem" />
      <div class="step-item" :class="{ 'is-active': currentStep === 2, 'is-done': currentStep > 2 }">
        <span class="step-num">2</span>
        <span class="step-label">Xem trước & Kiểm tra</span>
      </div>
      <i class="pi pi-chevron-right" style="font-size:1.0rem" />
      <div class="step-item" :class="{ 'is-active': currentStep === 3 }">
        <span class="step-num">3</span>
        <span class="step-label">Hoàn tất</span>
      </div>
    </div>

    <!-- Main Workspace Content -->
    <div class="import-workspace">
      <!-- STEP 1: UPLOAD -->
      <div v-if="currentStep === 1" class="step-content upload-step">
        <div class="grid-2-columns">
          <!-- Drop Area Card -->
          <div class="dashboard-card drop-card">
            <h4 class="card-title"><UploadCloud :size="18" /> Tải lên tài liệu</h4>
            <div 
              class="upload-dropzone"
              @click="triggerFileSelect"
            >
              <i class="pi pi-file-excel" style="font-size:3.0rem" />
              <div v-if="!selectedFile" class="dropzone-text">
                <strong>Kéo thả file CSV vào đây</strong>
                <span>hoặc click để duyệt tệp tin</span>
              </div>
              <div v-else class="dropzone-file">
                <strong>{{ selectedFile.name }}</strong>
                <span>{{ (selectedFile.size / 1024).toFixed(1) }} KB</span>
              </div>
              <input 
                type="file" 
                ref="fileInputRef" 
                class="hidden-input" 
                accept=".csv"
                @change="handleFileChange" 
              />
            </div>

            <div class="template-download">
              <p>Chưa có file mẫu? Tải file mẫu CSV cấu trúc chuẩn của hệ thống:</p>
              <button class="add-btn-small" @click="downloadTemplate">
                <i class="pi pi-download" style="font-size:0.875rem" /> Tải tệp mẫu (.csv)
              </button>
            </div>
          </div>

          <!-- Configuration sidebar -->
          <div class="dashboard-card config-card">
            <h4 class="card-title"><i class="pi pi-file-excel" style="font-size:1.125rem" /> Cấu hình học kỳ</h4>
            
            <label class="crud-field">
              <span>Học kỳ áp dụng</span>
              <select v-model="selectedTermId">
                <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
              </select>
            </label>

            <button 
              class="crud-primary-btn w-full mt-24"
              :disabled="loading || !selectedFile"
              @click="uploadAndValidate"
            >
              {{ loading ? 'Đang phân tích...' : 'Tiếp tục & Kiểm tra lỗi' }}
            </button>
          </div>
        </div>
      </div>

      <!-- STEP 2: VALIDATE AND PREVIEW -->
      <div v-if="currentStep === 2 && previewResult" class="step-content preview-step">
        <!-- Summary Stats Card -->
        <div class="dashboard-card preview-summary-card">
          <div class="stats-grid">
            <div class="stat-box">
              <span class="label">Tổng số dòng đọc được</span>
              <strong class="value">{{ previewResult.total_rows }} dòng</strong>
            </div>
            <div class="stat-box">
              <span class="label text-success">Số dòng hợp lệ</span>
              <strong class="value text-success">{{ previewResult.valid_rows }} dòng</strong>
            </div>
            <div class="stat-box">
              <span class="label text-danger">Số dòng có lỗi</span>
              <strong class="value text-danger">{{ previewResult.invalid_rows }} dòng</strong>
            </div>
          </div>

          <div class="preview-actions">
            <button class="crud-secondary-btn" @click="resetWizard">
              <i class="pi pi-arrow-left" style="font-size:0.875rem" /> Chọn tệp khác
            </button>
            <button 
              class="crud-primary-btn" 
              :disabled="previewResult.valid_rows === 0 || processing"
              @click="executeImport"
            >
              <i class="pi pi-play" style="font-size:0.875rem" /> Thực thi ghi danh ({{ previewResult.valid_rows }} dòng)
            </button>
          </div>
        </div>

        <!-- Preview Grid Table Card -->
        <div class="dashboard-card preview-table-card">
          <h4 class="card-title">Kết quả phân tích chi tiết</h4>
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th style="width: 70px">Dòng</th>
                  <th style="width: 140px">Mã sinh viên</th>
                  <th>Tên học viên</th>
                  <th style="width: 140px">Mã môn/Khóa</th>
                  <th>Tên môn học/Khóa học</th>
                  <th>Trạng thái dữ liệu</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in previewResult.preview_data" :key="row.row_number">
                  <td>{{ row.row_number }}</td>
                  <td><strong>{{ row.student_code }}</strong></td>
                  <td>{{ row.student_name || '—' }}</td>
                  <td><strong>{{ row.course_code }}</strong></td>
                  <td>{{ row.course_title || '—' }}</td>
                  <td>
                    <div class="status-cell">
                      <span 
                        class="status-dot" 
                        :class="row.status === 'valid' ? 'is-valid' : 'is-invalid'"
                      ></span>
                      <span class="status-msg">{{ row.message }}</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- STEP 3: COMPLETED SUCCESS SCREEN -->
      <div v-if="currentStep === 3" class="step-content success-step">
        <div class="dashboard-card success-card">
          <i class="pi pi-check-circle" style="font-size:4.0rem" />
          <h3>Ghi Danh Hoàn Tất!</h3>
          <p>Hệ thống đã ghi danh thành công cho <strong>{{ importedCount }}</strong> sinh viên vào các học phần được cấu hình.</p>
          <button class="crud-primary-btn" @click="resetWizard">
            Quay lại nhập tệp mới
          </button>
        </div>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.lnd-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.import-stepper {
  display: flex;
  justify-content: space-around;
  align-items: center;
  padding: 16px 24px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(0, 0, 0, 0.05);
  margin-top: 20px;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 10px;
  opacity: 0.5;
  transition: opacity 160ms ease;
}

.step-item.is-active {
  opacity: 1;
  font-weight: 700;
}

.step-item.is-done {
  opacity: 0.8;
  color: var(--green-deep, #047857);
}

.step-num {
  width: 24px;
  height: 24px;
  border-radius: 99px;
  background: #eee;
  color: #555;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: 700;
}

.is-active .step-num {
  background: var(--green-deep, #047857);
  color: #fff;
}

.is-done .step-num {
  background: rgba(16, 185, 129, 0.15);
  color: var(--green-deep, #047857);
}

.step-label {
  font-size: 0.88rem;
}

.step-separator {
  color: #ccc;
}

.import-workspace {
  margin-top: 20px;
}

.grid-2-columns {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 20px;
}

@media (max-width: 900px) {
  .grid-2-columns {
    grid-template-columns: 1fr;
  }
}

.drop-card, .config-card, .preview-summary-card, .preview-table-card, .success-card {
  padding: 20px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  margin-bottom: 16px;
}

.upload-dropzone {
  border: 2px dashed rgba(0, 0, 0, 0.12);
  border-radius: 14px;
  padding: 48px 24px;
  text-align: center;
  cursor: pointer;
  background: #fafafa;
  transition: all 160ms ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.upload-dropzone:hover {
  background: rgba(var(--green-rgb, 16, 185, 129), 0.03);
  border-color: rgba(var(--green-rgb, 16, 185, 129), 0.25);
}

.upload-icon {
  color: #aaa;
  transition: color 160ms ease;
}

.upload-dropzone:hover .upload-icon {
  color: var(--green-deep, #047857);
}

.dropzone-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dropzone-text strong {
  font-size: 0.95rem;
  color: #333;
}

.dropzone-text span {
  font-size: 0.8rem;
  color: #888;
}

.dropzone-file {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.dropzone-file strong {
  color: var(--green-deep, #047857);
  font-size: 1rem;
}

.dropzone-file span {
  font-size: 0.8rem;
  color: #666;
  font-weight: 600;
}

.hidden-input {
  display: none;
}

.template-download {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px dashed rgba(0, 0, 0, 0.08);
  font-size: 0.82rem;
  color: #666;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.add-btn-small {
  background: rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(0, 0, 0, 0.08);
  color: #333;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  align-self: flex-start;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 120ms ease;
}

.add-btn-small:hover {
  background: #333;
  color: #fff;
}

.w-full {
  width: 100%;
}

.mt-24 {
  margin-top: 24px;
}

/* Step 2 preview styles */
.preview-summary-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.stats-grid {
  display: flex;
  gap: 24px;
}

.stat-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-box .label {
  font-size: 0.78rem;
  color: #888;
}

.stat-box .value {
  font-size: 1.2rem;
  font-weight: 800;
}

.preview-actions {
  display: flex;
  gap: 10px;
}

.preview-table-card {
  margin-top: 20px;
}

.status-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 99px;
  flex-shrink: 0;
}

.status-dot.is-valid {
  background: #10b981;
}

.status-dot.is-invalid {
  background: #ef4444;
}

.status-msg {
  font-size: 0.8rem;
  color: #555;
}

/* Success step */
.success-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 60px 20px;
  gap: 16px;
}

.success-card h3 {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 8px 0 0;
}

.success-card p {
  color: #666;
  max-width: 320px;
  line-height: 1.4;
  margin-bottom: 8px;
}
</style>
