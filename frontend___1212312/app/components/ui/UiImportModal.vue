<script setup lang="ts">
import { ref } from 'vue'
import Dialog from 'primevue/dialog'

defineOptions({ name: 'UiImportModal' })

interface ValidationError {
  row?: number
  message: string
}

const props = withDefaults(defineProps<{
  open: boolean
  title?: string
  templateUrl?: string
  templateText?: string
  loading?: boolean
  errors?: ValidationError[]
  successCount?: number
}>(), {
  title: 'Nhập dữ liệu',
  templateUrl: '',
  templateText: 'Tải file mẫu (CSV)',
  loading: false,
  errors: () => [],
  successCount: 0,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  'import': [file: File]
  'close': []
}>()

const dragActive = ref(false)
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

function triggerFileInput() {
  fileInput.value?.click()
}

function handleDragOver(e: DragEvent) {
  e.preventDefault()
  dragActive.value = true
}

function handleDragLeave() {
  dragActive.value = false
}

function handleDrop(e: DragEvent) {
  e.preventDefault()
  dragActive.value = false
  if (e.dataTransfer?.files.length) {
    validateAndSetFile(e.dataTransfer.files[0])
  }
}

function handleFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files?.length) {
    validateAndSetFile(target.files[0])
  }
}

function validateAndSetFile(file: File) {
  const ext = file.name.split('.').pop()?.toLowerCase()
  if (ext === 'csv' || ext === 'xlsx' || ext === 'xls') {
    selectedFile.value = file
  } else {
    alert('Vui lòng chỉ tải lên file định dạng CSV hoặc Excel (.xlsx, .xls)')
  }
}

function removeFile() {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function handleConfirm() {
  if (!selectedFile.value) return
  emit('import', selectedFile.value)
}

function handleClose() {
  removeFile()
  emit('update:open', false)
  emit('close')
}
</script>

<template>
  <Dialog
    :visible="open"
    :modal="true"
    :dismissable-mask="!loading"
    :closable="false"
    :style="{ width: '32rem', maxWidth: '95vw' }"
    @update:visible="handleClose"
  >
    <template #default>
      <div class="import-modal-container">
        
        <!-- Header -->
        <div class="modal-header">
          <div>
            <h3 class="text-lg font-bold text-[var(--color-text)]">{{ title }}</h3>
            <p class="text-xs text-[var(--color-text-muted)] mt-0.5">Tải lên file dữ liệu để nhập nhanh vào hệ thống.</p>
          </div>
          <button 
            type="button" 
            class="modal-close-btn" 
            :disabled="loading" 
            @click="handleClose"
          >
            <i class="pi pi-times" />
          </button>
        </div>

        <!-- Body -->
        <div class="modal-body">
          
          <!-- Download Template Section -->
          <div v-if="templateUrl" class="template-section">
            <div class="flex items-center justify-between p-3 rounded-xl bg-[var(--color-primary-soft)] border border-[rgba(29,158,117,0.15)]">
              <div class="flex items-center gap-2">
                <i class="pi pi-file-excel text-[var(--color-primary)] text-lg" />
                <span class="text-xs font-semibold text-[var(--color-text-secondary)]">Sử dụng đúng định dạng file mẫu</span>
              </div>
              <a 
                :href="templateUrl" 
                download
                class="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--color-primary)] hover:underline"
              >
                <i class="pi pi-download" style="font-size: 0.75rem" />
                {{ templateText }}
              </a>
            </div>
          </div>

          <!-- Drag and Drop Area -->
          <div 
            v-if="!selectedFile"
            :class="['dropzone', dragActive && 'is-active']"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="triggerFileInput"
          >
            <input 
              ref="fileInput" 
              type="file" 
              class="hidden" 
              accept=".csv, .xlsx, .xls"
              @change="handleFileChange"
            >
            <div class="dropzone-inner">
              <div class="dropzone-icon">
                <i class="pi pi-cloud-upload" />
              </div>
              <strong class="text-sm text-[var(--color-text)]">Kéo thả file vào đây</strong>
              <p class="text-xs text-[var(--color-text-muted)] mt-1">hoặc click để chọn file từ máy tính</p>
              <span class="text-[10px] text-[var(--color-text-muted)] mt-3">Hỗ trợ file: .csv, .xlsx, .xls (Tối đa 10MB)</span>
            </div>
          </div>

          <!-- File Info when selected -->
          <div v-else class="selected-file-card">
            <div class="file-details">
              <i class="pi pi-file text-2xl text-[var(--color-primary)]" />
              <div class="min-w-0 flex-1">
                <h4 class="text-xs font-bold text-[var(--color-text)] truncate">{{ selectedFile.name }}</h4>
                <p class="text-[10px] text-[var(--color-text-muted)] mt-0.5">{{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB</p>
              </div>
              <button 
                type="button" 
                class="file-remove-btn" 
                :disabled="loading"
                @click="removeFile"
              >
                <i class="pi pi-trash" />
              </button>
            </div>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="processing-state mt-4">
            <div class="flex items-center gap-3 p-4 rounded-xl border border-[var(--color-line)] bg-[var(--color-surface)]">
              <i class="pi pi-spinner pi-spin text-xl text-[var(--color-primary)]" />
              <div class="flex-1">
                <h4 class="text-xs font-bold text-[var(--color-text)]">Đang xử lý file...</h4>
                <p class="text-[10px] text-[var(--color-text-muted)] mt-0.5">Hệ thống đang tải lên và kiểm tra tính hợp lệ của dữ liệu.</p>
              </div>
            </div>
          </div>

          <!-- Success Results -->
          <div v-if="successCount > 0 && !loading" class="result-success-state mt-4">
            <div class="flex items-center gap-3 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800">
              <i class="pi pi-check-circle text-xl shrink-0" />
              <div>
                <h4 class="text-xs font-bold">Nhập dữ liệu thành công!</h4>
                <p class="text-[10px] opacity-90 mt-0.5">Đã nhập thành công {{ successCount }} dòng dữ liệu hợp lệ.</p>
              </div>
            </div>
          </div>

          <!-- Validation Errors -->
          <div v-if="errors.length > 0 && !loading" class="result-error-state mt-4">
            <div class="error-header">
              <i class="pi pi-exclamation-triangle" />
              <span class="text-xs font-bold">Phát hiện {{ errors.length }} dòng bị lỗi</span>
            </div>
            <div class="errors-list">
              <div 
                v-for="(err, idx) in errors" 
                :key="idx" 
                class="error-item"
              >
                <span v-if="err.row" class="error-row">Dòng {{ err.row }}:</span>
                <span class="error-msg">{{ err.message }}</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button 
            type="button" 
            class="btn-secondary" 
            :disabled="loading" 
            @click="handleClose"
          >
            Hủy bỏ
          </button>
          <button 
            type="button" 
            class="btn-primary" 
            :disabled="!selectedFile || loading"
            @click="handleConfirm"
          >
            <i v-if="loading" class="pi pi-spinner pi-spin mr-1" />
            Xác nhận Nhập
          </button>
        </div>

      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.import-modal-container {
  display: flex;
  flex-direction: column;
  background: var(--color-surface-strong, #fff);
  border-radius: 12px;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--color-line-soft);
}

.modal-close-btn {
  background: transparent;
  border: none;
  color: var(--color-text-muted, #8a8a80);
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 150ms;
}

.modal-close-btn:hover:not(:disabled) {
  background: var(--color-surface);
  color: var(--color-text);
}

.modal-close-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.modal-body {
  padding: 20px 24px;
}

.template-section {
  margin-bottom: 16px;
}

.dropzone {
  border: 2px dashed var(--color-line);
  border-radius: 14px;
  padding: 32px 20px;
  cursor: pointer;
  background: var(--color-surface, #fbf7ef);
  transition: all 200ms ease;
}

.dropzone:hover,
.dropzone.is-active {
  border-color: var(--color-primary);
  background: var(--color-primary-soft);
}

.dropzone-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.dropzone-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: var(--color-surface-strong, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-primary);
  margin-bottom: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.02);
  font-size: 1.25rem;
}

.selected-file-card {
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--color-line);
  background: var(--color-surface-strong);
}

.file-details {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-remove-btn {
  background: transparent;
  border: none;
  color: var(--color-danger);
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 150ms;
}

.file-remove-btn:hover:not(:disabled) {
  background: var(--color-danger-soft);
}

.file-remove-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Validation Errors styling */
.result-error-state {
  border: 1px solid var(--color-danger-soft);
  background: rgba(239, 68, 68, 0.02);
  border-radius: 12px;
  padding: 16px;
  color: var(--color-danger);
}

.error-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.errors-list {
  max-height: 120px;
  overflow-y: auto;
  font-size: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.error-item {
  display: flex;
  gap: 6px;
  line-height: 1.4;
}

.error-row {
  font-weight: 700;
  flex-shrink: 0;
}

.error-msg {
  color: var(--color-text-secondary);
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px 20px;
  border-top: 1px solid var(--color-line-soft);
}

/* Button overrides to ensure matching look */
.btn-primary,
.btn-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 38px;
  padding: 0 16px;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 150ms;
}

.btn-primary {
  background: var(--color-primary);
  color: #fff;
}
.btn-primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}
.btn-primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-secondary {
  background: transparent;
  border-color: var(--color-line);
  color: var(--color-text-secondary);
}
.btn-secondary:hover:not(:disabled) {
  background: var(--color-surface);
  color: var(--color-text);
}
.btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Dark mode overrides */
:global([data-theme="dark"]) .import-modal-container {
  background: #111a17;
}

:global([data-theme="dark"]) .dropzone {
  background: rgba(255,255,255,0.02);
  border-color: rgba(255,255,255,0.08);
}

:global([data-theme="dark"]) .selected-file-card {
  background: rgba(255,255,255,0.03);
  border-color: rgba(255,255,255,0.08);
}

:global([data-theme="dark"]) .dropzone-icon {
  background: rgba(255,255,255,0.05);
}
</style>
