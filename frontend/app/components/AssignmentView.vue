<template>
  <div class="assignment-view">
    <div class="main-card shadow-premium">
      <!-- Section: Instructions -->
      <div class="section instructions">
        <div class="section-title">
          <i class="fas fa-file-invoice"></i>
          <h3>Hướng dẫn bài tập</h3>
        </div>
        <div class="content markdown-body" v-html="data.instructions"></div>
        <div class="meta-v2">
          <span class="meta-tag blue">
            <i class="fas fa-file-archive"></i>
            Hỗ trợ: {{ data.allowed_extensions }}
          </span>
          <span class="meta-tag orange" v-if="data.due_at">
            <i class="fas fa-clock"></i>
            Hạn chót: {{ formatDate(data.due_at) }}
          </span>
        </div>
      </div>

      <!-- Section: Submission -->
      <div class="section submission mt-8">
        <div class="section-title">
          <i class="fas fa-cloud-upload-alt"></i>
          <h3>Nộp bài của bạn</h3>
        </div>

        <div v-if="existingSubmission" class="existing-submission card-soft">
          <div class="sub-info">
            <p><strong>Bạn đã nộp bài vào lúc:</strong> {{ formatDateTime(existingSubmission.submitted_at) }}</p>
            <a :href="existingSubmission.file_url" target="_blank" class="file-link">
              <i class="fas fa-paperclip"></i> Xem lại bài đã nộp
            </a>
          </div>
          <div v-if="existingSubmission.grade" class="grade-report">
            <p class="grade-val">Điểm: {{ existingSubmission.grade }} / 10</p>
            <p v-if="existingSubmission.feedback" class="feedback-text">
              <strong>Phản hồi:</strong> {{ existingSubmission.feedback }}
            </p>
          </div>
          <button v-else @click="resetForm" class="btn-outline-red">Nộp lại bài khác</button>
        </div>

        <div v-else class="upload-form">
          <div 
            class="drop-zone"
            :class="{ dragging: isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="triggerSelect"
          >
            <input 
              type="file" 
              ref="fileInput" 
              class="hidden" 
              @change="onFileSelected"
              :accept="acceptString"
            />
            <div v-if="!selectedFile" class="prompt">
              <i class="fas fa-cloud-upload-alt big-icon"></i>
              <p>Kéo thả file vào đây hoặc click để chọn file</p>
              <small>Tối đa {{ data.max_file_size / 1024 }}MB</small>
            </div>
            <div v-else class="selected-file">
              <i class="fas fa-file-alt"></i>
              <span>{{ selectedFile.name }} ({{ formatSize(selectedFile.size) }})</span>
              <button @click.stop="selectedFile = null" class="btn-clear text-error">Gỡ bỏ</button>
            </div>
          </div>

          <div class="mt-4">
            <label class="block text-sm font-semibold mb-2">Ghi chú (tùy chọn)</label>
            <textarea 
              v-model="submissionNote" 
              class="input-premium" 
              placeholder="Nhập ghi chú cho giảng viên..."
              rows="3"
            ></textarea>
          </div>

          <button 
            @click="submitAssignment" 
            class="btn-gradient-blue mt-6"
            :disabled="!selectedFile || isSubmitting"
          >
            {{ isSubmitting ? 'Đang nộp bài...' : 'Xác nhận nộp bài' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
const props = defineProps<{
  data: any
  courseId: number
  lessonId: number
}>()

const auth = useAuthStore()
const selectedFile = ref<File | null>(null)
const isDragging = ref(false)
const isSubmitting = ref(false)
const submissionNote = ref('')
const existingSubmission = ref<any>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const acceptString = computed(() => {
  return props.data.allowed_extensions.split(',').map((ext: string) => `.${ext.trim()}`).join(',')
})

function formatDateTime(dateStr: string) {
  return new Date(dateStr).toLocaleString('vi-VN')
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('vi-VN')
}

function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

function triggerSelect() {
  fileInput.value?.click()
}

function onFileSelected(e: any) {
  const file = e.target.files[0]
  if (file) selectedFile.value = file
}

function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files[0]
  if (file) selectedFile.value = file
}

function resetForm() {
  existingSubmission.value = null
  selectedFile.value = null
  submissionNote.value = ''
}

async function submitAssignment() {
  if (!selectedFile.value) return
  isSubmitting.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    if (submissionNote.value) formData.append('student_note', submissionNote.value)

    const response = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/assignment/submit`, {
      method: 'POST',
      body: formData,
      token: auth.token,
    })

    existingSubmission.value = response.submission
  } catch {
    alert('Nộp bài thất bại. Vui lòng thử lại.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.assignment-view {
  padding: 24px;
  background: var(--bg-soft, #f8fafc);
}

.main-card {
  background: #fff;
  border-radius: 20px;
  padding: 32px;
  border: 1px solid #e2e8f0;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  color: #1e293b;
}

.section-title i {
  font-size: 1.25rem;
  color: #3b82f6;
}

.section-title h3 {
  font-size: 1.125rem;
  font-weight: 700;
  margin: 0;
}

.instructions .content {
  line-height: 1.7;
  color: #475569;
  font-size: 1rem;
}

.meta-v2 {
  display: flex;
  gap: 12px;
  margin-top: 20px;
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
}

.meta-tag.blue { background: #eff6ff; color: #1d4ed8; }
.meta-tag.orange { background: #fff7ed; color: #c2410c; }

.drop-zone {
  border: 2px dashed #cbd5e1;
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #f8fafc;
}

.drop-zone:hover, .drop-zone.dragging {
  border-color: #3b82f6;
  background: #eff6ff;
}

.big-icon {
  font-size: 3rem;
  color: #94a3b8;
  margin-bottom: 16px;
}

.btn-gradient-blue {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 0.2s;
}

.btn-gradient-blue:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);
}

.btn-gradient-blue:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.input-premium {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  outline: none;
  transition: border-color 0.2s;
}

.input-premium:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.existing-submission {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 24px;
  border-radius: 16px;
}

.sub-info p {
  color: #166534;
  margin-bottom: 8px;
}

.file-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #15803d;
  font-weight: 600;
  text-decoration: underline;
}

.grade-report {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #bbf7d0;
}

.grade-val {
  font-size: 1.25rem;
  font-weight: 800;
  color: #15803d;
}

.feedback-text {
  margin-top: 8px;
  font-size: 0.95rem;
  color: #14532d;
}
</style>
