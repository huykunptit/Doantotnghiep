<template>
  <div class="assignment-view">
    <!-- Timeline of milestones -->
    <section v-if="hasMilestones" class="assignment-timeline-card">
      <div class="assignment-timeline-row">
        <div
          v-for="m in milestones"
          :key="m.key"
          :class="['assignment-milestone', `assignment-milestone--${m.tone}`, { 'is-current': m.isCurrent }]"
        >
          <div class="assignment-milestone-icon">
            <span class="material-symbols-outlined">{{ m.icon }}</span>
          </div>
          <div class="assignment-milestone-body">
            <p class="assignment-milestone-label">{{ m.label }}</p>
            <p class="assignment-milestone-value">{{ m.value || 'Chưa đặt' }}</p>
            <p class="assignment-milestone-status">{{ m.status }}</p>
          </div>
        </div>
      </div>
      <p v-if="countdown" class="assignment-countdown">
        <span class="material-symbols-outlined">timer</span>
        {{ countdown }}
      </p>
    </section>

    <div class="assignment-grid">
      <!-- LEFT: instructions -->
      <section class="assignment-card">
        <header class="assignment-section-head">
          <span class="material-symbols-outlined">description</span>
          <h3>Hướng dẫn bài tập</h3>
        </header>
        <div class="assignment-instructions" v-html="data.instructions || '<em>Giảng viên chưa thêm hướng dẫn.</em>'"></div>
        <div class="assignment-meta">
          <span class="assignment-meta-tag">
            <span class="material-symbols-outlined">folder_zip</span>
            {{ data.allowed_extensions || 'Mọi định dạng' }}
          </span>
          <span class="assignment-meta-tag">
            <span class="material-symbols-outlined">database</span>
            Tối đa {{ formatSizeKb(data.max_file_size) }}
          </span>
        </div>
      </section>

      <!-- RIGHT: submission -->
      <section class="assignment-card">
        <header class="assignment-section-head">
          <span class="material-symbols-outlined">cloud_upload</span>
          <h3>Nộp bài của bạn</h3>
        </header>

        <!-- Already submitted -->
        <div v-if="existingSubmission" class="assignment-submitted">
          <div class="assignment-submitted-head">
            <span class="material-symbols-outlined">task_alt</span>
            <div>
              <p class="assignment-submitted-title">Đã nộp bài</p>
              <p class="assignment-submitted-meta">{{ formatDateTime(existingSubmission.submitted_at) }}</p>
            </div>
          </div>
          <a :href="existingSubmission.file_url" target="_blank" class="assignment-file-pill">
            <span class="material-symbols-outlined">attach_file</span>
            Mở bài đã nộp
          </a>
          <div v-if="existingSubmission.grade != null" class="assignment-grade">
            <p class="assignment-grade-value">{{ existingSubmission.grade }} <small>/ 10</small></p>
            <p v-if="existingSubmission.feedback" class="assignment-feedback">
              <strong>Phản hồi:</strong> {{ existingSubmission.feedback }}
            </p>
          </div>
          <button v-else-if="canSubmitNow" type="button" class="assignment-link-ghost" @click="resetForm">
            Nộp lại bài khác
          </button>
        </div>

        <!-- Submission window not yet open -->
        <div v-else-if="submissionState === 'before'" class="assignment-locked assignment-locked--soon">
          <span class="material-symbols-outlined">schedule</span>
          <p>Chưa đến giờ nộp bài.</p>
          <small v-if="data.submission_open_at">Bắt đầu lúc {{ formatDateTime(data.submission_open_at) }}</small>
        </div>

        <!-- Deadline passed -->
        <div v-else-if="submissionState === 'closed'" class="assignment-locked assignment-locked--closed">
          <span class="material-symbols-outlined">lock</span>
          <p>Đã quá hạn nộp bài.</p>
          <small v-if="data.due_at">Đóng lúc {{ formatDateTime(data.due_at) }}</small>
        </div>

        <!-- Active submission form -->
        <div v-else class="assignment-form">
          <div
            class="assignment-dropzone"
            :class="{ 'is-dragging': isDragging, 'has-file': !!selectedFile }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="triggerSelect"
          >
            <input ref="fileInput" type="file" :accept="acceptString" class="assignment-file-input" @change="onFileSelected" />

            <div v-if="!selectedFile" class="assignment-dropzone-empty">
              <span class="material-symbols-outlined">cloud_upload</span>
              <p><strong>Kéo thả file</strong> hoặc <span class="link">chọn từ máy</span></p>
              <small>{{ data.allowed_extensions || 'mọi định dạng' }} · Tối đa {{ formatSizeKb(data.max_file_size) }}</small>
            </div>

            <div v-else class="assignment-dropzone-file">
              <span class="material-symbols-outlined assignment-file-icon">{{ extensionIcon(selectedFile.name) }}</span>
              <div class="assignment-file-info">
                <p class="assignment-file-name">{{ selectedFile.name }}</p>
                <p class="assignment-file-size">{{ formatSize(selectedFile.size) }}</p>
              </div>
              <button type="button" class="assignment-file-remove" aria-label="Gỡ bỏ" @click.stop="selectedFile = null">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
          </div>

          <p v-if="sizeWarning" class="assignment-error">
            <span class="material-symbols-outlined">error</span>
            {{ sizeWarning }}
          </p>

          <label class="assignment-note">
            <span>Ghi chú cho giảng viên (tùy chọn)</span>
            <textarea v-model="submissionNote" rows="3" placeholder="Vd: Em đã hoàn thành các yêu cầu, riêng phần X em làm theo cách..."></textarea>
          </label>

          <button
            type="button"
            class="assignment-submit-btn"
            :disabled="!canConfirm"
            @click="submitAssignment"
          >
            <span class="material-symbols-outlined" :class="{ 'is-spinning': isSubmitting }">
              {{ isSubmitting ? 'progress_activity' : 'send' }}
            </span>
            {{ isSubmitting ? 'Đang nộp bài...' : 'Xác nhận nộp bài' }}
          </button>
        </div>
      </section>
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

const emit = defineEmits<{
  submitted: [submission: any]
}>()

const auth = useAuthStore()
const selectedFile = ref<File | null>(null)
const isDragging = ref(false)
const isSubmitting = ref(false)
const submissionNote = ref('')
const existingSubmission = ref<any>(props.data?.my_submission || null)
const fileInput = ref<HTMLInputElement | null>(null)
const now = ref(new Date())

if (import.meta.client) {
  // Refresh "now" each minute so countdowns + state transitions update.
  setInterval(() => { now.value = new Date() }, 60_000)
}

const authHeaders = () => auth.token ? { Authorization: `Bearer ${auth.token}` } : undefined

const acceptString = computed(() => {
  if (!props.data?.allowed_extensions) return ''
  return props.data.allowed_extensions.split(',').map((ext: string) => `.${ext.trim()}`).join(',')
})

const maxBytes = computed(() => (props.data?.max_file_size || 10240) * 1024)

const sizeWarning = computed(() => {
  if (!selectedFile.value) return ''
  if (selectedFile.value.size > maxBytes.value) {
    return `File quá lớn (${formatSize(selectedFile.value.size)}). Giới hạn ${formatSizeKb(props.data?.max_file_size)}.`
  }
  const ext = selectedFile.value.name.split('.').pop()?.toLowerCase()
  const allowed = (props.data?.allowed_extensions || '').split(',').map((s: string) => s.trim().toLowerCase()).filter(Boolean)
  if (allowed.length && ext && !allowed.includes(ext)) {
    return `Định dạng .${ext} không được chấp nhận. Cho phép: ${allowed.join(', ')}.`
  }
  return ''
})

const submissionState = computed<'before' | 'open' | 'closed'>(() => {
  const open = props.data?.submission_open_at ? new Date(props.data.submission_open_at) : null
  const due = props.data?.due_at ? new Date(props.data.due_at) : null
  if (open && now.value < open) return 'before'
  if (due && now.value > due) return 'closed'
  return 'open'
})

const canSubmitNow = computed(() => submissionState.value === 'open')
const canConfirm = computed(() => canSubmitNow.value && !!selectedFile.value && !sizeWarning.value && !isSubmitting.value)

const hasMilestones = computed(() => Boolean(props.data?.available_from || props.data?.submission_open_at || props.data?.due_at))

const milestones = computed(() => {
  const open = props.data?.submission_open_at ? new Date(props.data.submission_open_at) : null
  const due = props.data?.due_at ? new Date(props.data.due_at) : null
  const items = [
    {
      key: 'available_from',
      label: 'Ngày nhận bài',
      icon: 'event_available',
      tone: 'open',
      value: props.data?.available_from ? formatDateTime(props.data.available_from) : '',
      raw: props.data?.available_from ? new Date(props.data.available_from) : null,
    },
    {
      key: 'submission_open_at',
      label: 'Ngày bắt đầu nộp',
      icon: 'task_alt',
      tone: 'submit',
      value: props.data?.submission_open_at ? formatDateTime(props.data.submission_open_at) : '',
      raw: open,
    },
    {
      key: 'due_at',
      label: 'Hạn nộp (ngày đóng)',
      icon: 'lock_clock',
      tone: 'close',
      value: props.data?.due_at ? formatDateTime(props.data.due_at) : '',
      raw: due,
    },
  ] as const

  return items.map(m => {
    let status = 'Chưa cấu hình'
    let isCurrent = false
    if (m.raw) {
      const passed = now.value >= m.raw
      if (m.key === 'due_at') {
        status = passed ? 'Đã đóng' : 'Còn ' + relativeToNow(m.raw)
        isCurrent = !passed && submissionState.value === 'open'
      } else if (m.key === 'submission_open_at') {
        status = passed ? 'Đã mở' : 'Mở sau ' + relativeToNow(m.raw)
        isCurrent = passed && submissionState.value === 'open'
      } else {
        status = passed ? 'Đã hiển thị' : 'Sẽ hiển thị sau ' + relativeToNow(m.raw)
        isCurrent = passed && submissionState.value === 'before'
      }
    }
    return { ...m, status, isCurrent }
  })
})

const countdown = computed(() => {
  const due = props.data?.due_at ? new Date(props.data.due_at) : null
  const open = props.data?.submission_open_at ? new Date(props.data.submission_open_at) : null
  if (submissionState.value === 'before' && open) {
    return `Bắt đầu cho phép nộp sau ${relativeToNow(open)}`
  }
  if (submissionState.value === 'open' && due) {
    return `Còn ${relativeToNow(due)} đến hạn nộp`
  }
  if (submissionState.value === 'closed' && due) {
    return `Đã đóng cách đây ${relativeToNow(due)}`
  }
  return ''
})

function formatDateTime(dateStr: string | Date) {
  return new Date(dateStr).toLocaleString('vi-VN', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatSize(bytes: number) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

function formatSizeKb(kb: number | null | undefined) {
  if (!kb) return '—'
  if (kb < 1024) return kb + ' KB'
  return (kb / 1024).toFixed(1) + ' MB'
}

function relativeToNow(target: Date) {
  const diff = Math.abs(target.getTime() - now.value.getTime())
  const minutes = Math.round(diff / 60000)
  if (minutes < 60) return `${minutes} phút`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} giờ`
  const days = Math.round(hours / 24)
  if (days < 30) return `${days} ngày`
  const months = Math.round(days / 30)
  return `${months} tháng`
}

function extensionIcon(filename: string) {
  const ext = filename.split('.').pop()?.toLowerCase()
  if (!ext) return 'draft'
  if (['pdf'].includes(ext)) return 'picture_as_pdf'
  if (['doc', 'docx', 'rtf'].includes(ext)) return 'description'
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'table_chart'
  if (['ppt', 'pptx'].includes(ext)) return 'co_present'
  if (['zip', 'rar', '7z'].includes(ext)) return 'folder_zip'
  if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return 'image'
  if (['mp4', 'mov', 'webm', 'mkv'].includes(ext)) return 'movie'
  if (['mp3', 'wav', 'm4a'].includes(ext)) return 'audio_file'
  return 'draft'
}

function triggerSelect() {
  if (!canSubmitNow.value) return
  fileInput.value?.click()
}

function onFileSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) selectedFile.value = file
}

function onDrop(e: DragEvent) {
  isDragging.value = false
  if (!canSubmitNow.value) return
  const file = e.dataTransfer?.files[0]
  if (file) selectedFile.value = file
}

function resetForm() {
  existingSubmission.value = null
  selectedFile.value = null
  submissionNote.value = ''
}

async function submitAssignment() {
  if (!canConfirm.value || !selectedFile.value) return
  isSubmitting.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    if (submissionNote.value) formData.append('student_note', submissionNote.value)

    const response = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/assignment/submit`, {
      method: 'POST',
      body: formData,
      headers: authHeaders(),
    })

    existingSubmission.value = response.submission
    if (response.submission) emit('submitted', response.submission)
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
  background: #f8fafc;
  color: #0f172a;
}

/* ───── Timeline ───── */
.assignment-timeline-card {
  background: #fff;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  padding: 18px 20px;
  margin-bottom: 18px;
}

.assignment-timeline-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.assignment-milestone {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  background: #f9fafb;
  border: 1px solid #eef2f7;
  position: relative;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.assignment-milestone.is-current {
  background: #fff;
  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
  border-color: currentColor;
}
.assignment-milestone--open   { color: #16a34a; }
.assignment-milestone--submit { color: #2563eb; }
.assignment-milestone--close  { color: #dc2626; }

.assignment-milestone-icon {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, currentColor 12%, white);
}
.assignment-milestone-icon .material-symbols-outlined {
  font-size: 20px;
  color: currentColor;
}
.assignment-milestone-body { min-width: 0; flex: 1; }
.assignment-milestone-label {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.assignment-milestone-value {
  margin: 4px 0 2px;
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
}
.assignment-milestone-status {
  margin: 0;
  font-size: 0.78rem;
  color: currentColor;
  font-weight: 600;
}

.assignment-countdown {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 14px 0 0;
  font-size: 0.85rem;
  font-weight: 700;
  color: #b45309;
  background: #fef3c7;
  padding: 8px 14px;
  border-radius: 999px;
}
.assignment-countdown .material-symbols-outlined { font-size: 16px; }

/* ───── Layout ───── */
.assignment-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}
@media (max-width: 900px) {
  .assignment-grid { grid-template-columns: 1fr; }
  .assignment-timeline-row { grid-template-columns: 1fr; }
}

.assignment-card {
  background: #fff;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  padding: 22px;
}

.assignment-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.assignment-section-head .material-symbols-outlined {
  color: #2563eb;
  font-size: 22px;
}
.assignment-section-head h3 {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
  color: #0f172a;
}

.assignment-instructions {
  font-size: 0.95rem;
  line-height: 1.7;
  color: #334155;
}
.assignment-instructions :deep(p) { margin: 0 0 8px; }

.assignment-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
}
.assignment-meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  font-size: 0.78rem;
  font-weight: 600;
  color: #1e293b;
}
.assignment-meta-tag .material-symbols-outlined { font-size: 14px; color: #64748b; }

/* ───── Dropzone ───── */
.assignment-dropzone {
  position: relative;
  border: 2px dashed #cbd5e1;
  border-radius: 16px;
  background: #f5f7fa;
  padding: 28px;
  cursor: pointer;
  transition: all 0.15s;
}
.assignment-dropzone:hover,
.assignment-dropzone.is-dragging {
  border-color: #2563eb;
  background: #e6efff;
}
.assignment-dropzone.has-file {
  border-style: solid;
  background: #fff;
  border-color: #16a34a;
}

.assignment-file-input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.assignment-dropzone-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  pointer-events: none;
}
.assignment-dropzone-empty .material-symbols-outlined {
  font-size: 40px;
  color: #94a3b8;
}
.assignment-dropzone-empty p {
  margin: 0;
  font-size: 0.95rem;
  color: #334155;
}
.assignment-dropzone-empty .link {
  color: #2563eb;
  font-weight: 700;
  text-decoration: underline;
}
.assignment-dropzone-empty small {
  color: #94a3b8;
  font-size: 0.78rem;
}

.assignment-dropzone-file {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 4px;
}
.assignment-file-icon {
  font-size: 32px;
  color: #16a34a;
  background: #dcfce7;
  border-radius: 10px;
  padding: 8px;
}
.assignment-file-info { min-width: 0; flex: 1; }
.assignment-file-name {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
  word-break: break-all;
}
.assignment-file-size {
  margin: 2px 0 0;
  font-size: 0.78rem;
  color: #64748b;
}
.assignment-file-remove {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: #fee2e2;
  color: #dc2626;
  cursor: pointer;
  display: grid;
  place-items: center;
  z-index: 1;
}
.assignment-file-remove:hover { background: #fecaca; }
.assignment-file-remove .material-symbols-outlined { font-size: 18px; }

.assignment-error {
  margin: 12px 0 0;
  padding: 10px 14px;
  border-radius: 10px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.assignment-error .material-symbols-outlined { font-size: 16px; }

/* ───── Note + submit ───── */
.assignment-note {
  display: block;
  margin-top: 16px;
}
.assignment-note span {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 6px;
}
.assignment-note textarea {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
  outline: none;
  font: inherit;
  font-size: 0.9rem;
  resize: vertical;
  min-height: 80px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.assignment-note textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.assignment-submit-btn {
  margin-top: 16px;
  width: 100%;
  padding: 13px;
  border: none;
  border-radius: 12px;
  background: #16a34a;
  color: #fff;
  font-weight: 800;
  font-size: 0.95rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 8px 22px rgba(22, 163, 74, 0.3);
  transition: filter 0.15s, transform 0.15s;
}
.assignment-submit-btn:hover:not(:disabled) {
  filter: brightness(1.05);
  transform: translateY(-1px);
}
.assignment-submit-btn:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
  box-shadow: none;
  color: #fff;
}
.assignment-submit-btn .material-symbols-outlined { font-size: 18px; }
.assignment-submit-btn .material-symbols-outlined.is-spinning {
  animation: assignment-spin 1.2s linear infinite;
}
@keyframes assignment-spin {
  to { transform: rotate(360deg); }
}

/* ───── Already submitted ───── */
.assignment-submitted {
  background: #ecfaef;
  border: 1px solid #bbf7d0;
  border-radius: 16px;
  padding: 20px;
}
.assignment-submitted-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
}
.assignment-submitted-head .material-symbols-outlined {
  font-size: 28px;
  color: #16a34a;
  font-variation-settings: 'FILL' 1;
}
.assignment-submitted-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  color: #14532d;
}
.assignment-submitted-meta {
  margin: 2px 0 0;
  font-size: 0.82rem;
  color: #166534;
}

.assignment-file-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 999px;
  background: #fff;
  color: #15803d;
  font-weight: 700;
  font-size: 0.85rem;
  text-decoration: none;
  border: 1px solid #bbf7d0;
}
.assignment-file-pill:hover { background: #f0fdf4; }
.assignment-file-pill .material-symbols-outlined { font-size: 16px; }

.assignment-grade {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #bbf7d0;
}
.assignment-grade-value {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #15803d;
}
.assignment-grade-value small {
  font-size: 0.9rem;
  color: #166534;
  font-weight: 600;
}
.assignment-feedback {
  margin: 8px 0 0;
  font-size: 0.9rem;
  color: #14532d;
  line-height: 1.6;
}

.assignment-link-ghost {
  margin-top: 12px;
  background: transparent;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  padding: 8px 14px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}
.assignment-link-ghost:hover { background: #f1f5f9; }

/* ───── Locked states ───── */
.assignment-locked {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 8px;
  padding: 32px 20px;
  border-radius: 16px;
  border: 1px dashed #e2e8f0;
}
.assignment-locked .material-symbols-outlined {
  font-size: 36px;
}
.assignment-locked p {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
}
.assignment-locked small {
  font-size: 0.82rem;
  color: #64748b;
}
.assignment-locked--soon {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}
.assignment-locked--closed {
  background: #fef2f2;
  border-color: #fecaca;
  color: #b91c1c;
}
</style>
