<script setup lang="ts">
import { LESSON_TYPE_LABELS } from '~/constants/lesson-types'

const props = defineProps<{
  lesson: any
  courseId: number
  index: number
  isFirst: boolean
  isLast: boolean
}>()

const emit = defineEmits<{
  edit: [lesson: any]
  delete: [lesson: any]
  uploadVideo: [lesson: any]
  moveUp: [lesson: any]
  moveDown: [lesson: any]
  viewSubmissions: [lesson: any]
}>()

function formatDuration(seconds: number) {
  if (!seconds) return '--'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

function getLessonIcon(lesson: any) {
  const type = lesson.type || 'video'
  const map: Record<string, string> = {
    video: 'video',
    document: 'file',
    assignment: 'list',
    virtual_class: 'video',
    scorm: 'box',
    h5p: 'desktop',
    quiz: 'question-circle',
  }
  return map[type] || 'file'
}

function lessonChip(lesson: any) {
  return LESSON_TYPE_LABELS[(lesson.type || 'video') as keyof typeof LESSON_TYPE_LABELS] || lesson.type || 'Bài học'
}

function lessonSummary(lesson: any) {
  if (lesson.type === 'video') return lesson.video_url || lesson.video_status === 'ready' ? 'Video bài giảng đã sẵn sàng' : 'Chưa có nguồn video tải lên'
  if (lesson.type === 'document') return lesson.attachments?.length ? `${lesson.attachments.length} tài liệu đính kèm` : 'Không có tài liệu đính kèm'
  if (lesson.type === 'assignment') return lesson.assignment?.instructions ? 'Đã cấu hình bài tập thực hành' : 'Chưa cấu hình yêu cầu bài tập'
  if (lesson.type === 'virtual_class') return lesson.virtual_class?.join_url ? 'Đã liên kết phòng trực tuyến' : 'Chưa gắn link họp trực tuyến'
  if (lesson.type === 'scorm') return lesson.scorm_package?.entry_url ? 'Đã tích hợp gói SCORM' : 'Chưa tải lên gói SCORM'
  if (lesson.type === 'h5p') return lesson.scorm_package?.entry_url ? 'Đã tích hợp nội dung H5P' : 'Chưa liên kết nội dung H5P'
  if (lesson.type === 'quiz') return 'Câu hỏi kiểm tra trắc nghiệm đánh giá'
  return 'Chưa cấu hình nội dung chi tiết'
}

function typeIconStyle(lesson: any) {
  const type = lesson.type || 'video'
  const map: Record<string, string> = {
    assignment: 'background: rgba(245, 158, 11, 0.08); color: #D97706; border-color: rgba(245, 158, 11, 0.15);',
    virtual_class: 'background: rgba(14, 165, 233, 0.08); color: #0EA5E9; border-color: rgba(14, 165, 233, 0.15);',
    document: 'background: rgba(139, 92, 246, 0.08); color: #8B5CF6; border-color: rgba(139, 92, 246, 0.15);',
    scorm: 'background: rgba(234, 88, 12, 0.08); color: #EA580C; border-color: rgba(234, 88, 12, 0.15);',
    h5p: 'background: rgba(236, 72, 153, 0.08); color: #EC4899; border-color: rgba(236, 72, 153, 0.15);',
    quiz: 'background: rgba(139, 92, 246, 0.08); color: #8B5CF6; border-color: rgba(139, 92, 246, 0.15);',
  }
  if (type === 'video') {
    return lesson.video_url || lesson.video_status === 'ready'
      ? 'background: rgba(16, 185, 129, 0.08); color: #10B981; border-color: rgba(16, 185, 129, 0.15);'
      : 'background: rgba(107, 114, 128, 0.08); color: #6B7280; border-color: rgba(107, 114, 128, 0.15);'
  }
  return map[type] || 'background: rgba(16, 185, 129, 0.08); color: #10B981; border-color: rgba(16, 185, 129, 0.15);'
}
</script>

<template>
  <div class="studio-lesson-row">
    <!-- Reorder handle controls -->
    <div class="reorder-controls">
      <button class="order-arrow-btn" :disabled="isFirst" @click="emit('moveUp', lesson)" title="Di chuyển lên">
        <i class="pi pi-chevron-up" style="font-size:0.8125rem" />
      </button>
      <button class="order-arrow-btn" :disabled="isLast" @click="emit('moveDown', lesson)" title="Di chuyển xuống">
        <i class="pi pi-chevron-down" style="font-size:0.8125rem" />
      </button>
    </div>

    <!-- Visual Type Icon -->
    <div class="lesson-type-icon-box" :style="typeIconStyle(lesson)">
      <i :class="`pi pi-${getLessonIcon(lesson)}`" style="font-size:1.125rem" />
    </div>

    <!-- Main Content Info -->
    <div class="lesson-main-info">
      <div class="lesson-title-badges">
        <strong class="lesson-title">{{ lesson.title }}</strong>
        <span v-if="lesson.is_preview" class="preview-badge">PREVIEW</span>
        <span class="type-badge">{{ lessonChip(lesson) }}</span>
      </div>
      <p class="lesson-meta-desc">{{ lessonSummary(lesson) }}</p>
      
      <div class="lesson-meta-footer">
        <span class="meta-tag">
          <i class="pi pi-clock" style="font-size:0.75rem" />
          {{ formatDuration(lesson.duration) }}
        </span>
        <span v-if="lesson.video_status === 'processing'" class="meta-tag is-processing">
          <i class="pi pi-refresh" style="font-size:0.75rem" />
          Đang xử lý video...
        </span>
        <span v-else-if="lesson.video_status === 'ready' || lesson.video_url" class="meta-tag is-success">
          <i class="pi pi-check-circle" style="font-size:0.75rem" />
          Sẵn sàng
        </span>
      </div>
    </div>

    <!-- Control Actions Panel -->
    <div class="lesson-actions-panel">
      <!-- Edit button -->
      <button class="control-btn btn-edit" type="button" @click="emit('edit', lesson)" title="Chỉnh sửa bài giảng">
        <i class="pi pi-pencil" style="font-size:0.8125rem" />
        <span>Sửa</span>
      </button>

      <!-- Video upload trigger (only for video lesson type) -->
      <button 
        v-if="lesson.type === 'video'" 
        class="control-btn btn-video" 
        type="button" 
        @click="emit('uploadVideo', lesson)"
        title="Tải lên video bài giảng"
      >
        <i class="pi pi-cloud-upload" style="font-size:0.8125rem" />
        <span>Video</span>
      </button>

      <!-- Submissions manager (only for assignments) -->
      <button 
        v-if="lesson.type === 'assignment'" 
        class="control-btn btn-submissions" 
        type="button" 
        @click="emit('viewSubmissions', lesson)"
        title="Xem danh sách học viên nộp bài"
      >
        <i class="pi pi-list" style="font-size:0.8125rem" />
        <span>Bài nộp</span>
      </button>

      <!-- Quiz manager linkage -->
      <NuxtLink 
        :to="`/instructor/courses/${courseId}/lessons/${lesson.id}/quiz`" 
        class="control-btn btn-quiz" 
        title="Thiết lập Quiz trắc nghiệm"
      >
        <i class="pi pi-question-circle" style="font-size:0.8125rem" />
        <span>Quiz</span>
      </NuxtLink>

      <!-- Delete button -->
      <button class="control-btn btn-delete" type="button" @click="emit('delete', lesson)" title="Xóa bài học">
        <i class="pi pi-trash" style="font-size:0.8125rem" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.studio-lesson-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  border-radius: 14px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  transition: all 200ms ease;
}

.studio-lesson-row:hover {
  border-color: var(--green);
  box-shadow: var(--shadow-sm);
  transform: translateX(2px);
}

/* Reorder controls */
.reorder-controls {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex-shrink: 0;
}

.order-arrow-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: all 150ms;
}

.order-arrow-btn:hover:not(:disabled) {
  background: var(--green-soft);
  color: var(--green);
  border-color: var(--green);
}

.order-arrow-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

/* Visual Type Icon */
.lesson-type-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  border: 1px solid transparent;
  flex-shrink: 0;
}

/* Info styling */
.lesson-main-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.lesson-title-badges {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.lesson-title {
  font-size: 0.9rem;
  font-weight: 750;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 320px;
}

.preview-badge {
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  padding: 2px 6px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.08);
  color: #10B981;
  border: 1px solid rgba(16, 185, 129, 0.15);
}

.type-badge {
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 2px 6px;
  border-radius: 6px;
  background: var(--surface);
  border: 1px solid var(--line);
  color: var(--muted);
}

.lesson-meta-desc {
  margin: 0;
  font-size: 0.76rem;
  color: var(--muted);
  font-weight: 500;
}

.lesson-meta-footer {
  display: flex;
  align-items: center;
  gap: 12px;
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  color: var(--muted);
  font-weight: 600;
}

.meta-tag svg {
  color: var(--muted);
}

.meta-tag.is-processing {
  color: #D97706;
}

.meta-tag.is-success {
  color: #10B981;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Actions Panel */
.lesson-actions-panel {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  margin-left: auto;
}

.control-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  height: 28px;
  padding: 0 10px;
  border-radius: 8px;
  font-size: 0.74rem;
  font-weight: 700;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 150ms;
  text-decoration: none;
}

.control-btn:hover {
  background: var(--surface-strong);
  color: var(--text);
  border-color: var(--muted);
}

.btn-edit:hover {
  color: #0EA5E9;
  background: rgba(14, 165, 233, 0.05);
  border-color: rgba(14, 165, 233, 0.25);
}

.btn-video:hover {
  color: #10B981;
  background: rgba(16, 185, 129, 0.05);
  border-color: rgba(16, 185, 129, 0.25);
}

.btn-submissions:hover {
  color: #D97706;
  background: rgba(217, 119, 6, 0.05);
  border-color: rgba(217, 119, 6, 0.25);
}

.btn-quiz:hover {
  color: #8B5CF6;
  background: rgba(139, 92, 246, 0.05);
  border-color: rgba(139, 92, 246, 0.25);
}

.btn-delete {
  padding: 0 8px;
}

.btn-delete:hover {
  color: #EF4444;
  background: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.25);
}
</style>
