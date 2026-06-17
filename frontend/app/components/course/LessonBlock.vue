<script setup lang="ts">
import { LESSON_TYPE_LABELS } from '~/constants/lesson-types'
import { ChevronUp, ChevronDown, Clock, RefreshCw, CircleCheckBig, Pencil, CloudUpload, FileQuestion, Trash2 } from 'lucide-vue-next'

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
}>()

function formatDuration(seconds: number) {
  if (!seconds) return '--'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

function lessonIcon(lesson: any) {
  const type = lesson.type || 'video'
  const map: Record<string, string> = {
    video: lesson.video_url || lesson.video_status === 'ready' ? 'play-circle' : 'video',
    document: 'file-text',
    assignment: 'clipboard-list',
    virtual_class: 'video',
    scorm: 'layers',
    h5p: 'puzzle',
    quiz: 'file-question',
  }
  return map[type] || 'file'
}

function lessonChip(lesson: any) {
  return LESSON_TYPE_LABELS[(lesson.type || 'video') as keyof typeof LESSON_TYPE_LABELS] || lesson.type || 'Bài học'
}

function lessonSummary(lesson: any) {
  if (lesson.type === 'video') return lesson.video_url ? 'Đã gắn video hoặc link video' : 'Chưa có nguồn video'
  if (lesson.type === 'document') return lesson.attachments?.length ? `${lesson.attachments.length} tài liệu đính kèm` : 'Chưa có tài liệu'
  if (lesson.type === 'assignment') return lesson.assignment?.instructions ? 'Đã cấu hình bài tập về nhà' : 'Chưa cấu hình bài tập'
  if (lesson.type === 'virtual_class') return lesson.virtual_class?.join_url ? 'Đã gắn link lớp trực tuyến' : 'Chưa gắn link họp'
  if (lesson.type === 'scorm') return lesson.scorm_package?.entry_url ? 'Đã tải package SCORM' : 'Chưa tải package SCORM'
  if (lesson.type === 'h5p') return lesson.scorm_package?.entry_url ? 'Đã gắn embed H5P' : 'Chưa gắn link H5P'
  if (lesson.type === 'quiz') return 'Quản lý câu hỏi ở trang quiz của bài học'
  return 'Chưa cấu hình nội dung'
}

function typeIconStyle(lesson: any) {
  const type = lesson.type || 'video'
  const map: Record<string, string> = {
    assignment: 'background:rgba(217,119,6,0.12);color:#b45309;',
    virtual_class: 'background:rgba(14,165,233,0.12);color:#0284c7;',
    document: 'background:rgba(124,58,237,0.12);color:#7c3aed;',
    scorm: 'background:rgba(234,88,12,0.12);color:#ea580c;',
    h5p: 'background:rgba(236,72,153,0.12);color:#db2777;',
    quiz: 'background:rgba(124,58,237,0.12);color:#7c3aed;',
  }
  if (type === 'video') {
    return lesson.video_url || lesson.video_status === 'ready'
      ? 'background:rgba(var(--green-rgb),0.12);color:var(--green-deep);'
      : 'background:rgba(var(--green-rgb),0.06);color:var(--green-deep);'
  }
  return map[type] || 'background:rgba(var(--green-rgb),0.06);color:var(--green-deep);'
}
</script>

<template>
  <div class="curriculum-lesson">
    <!-- Order buttons -->
    <div style="display:flex; flex-direction:column; gap:3px; flex-shrink:0;">
      <button class="curriculum-order-btn" :disabled="isFirst" @click="emit('moveUp', lesson)">
        <ChevronUp :size="14" :stroke-width="1.75" />
      </button>
      <button class="curriculum-order-btn" :disabled="isLast" @click="emit('moveDown', lesson)">
        <ChevronDown :size="14" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Type icon -->
    <div class="curriculum-type-icon" :style="typeIconStyle(lesson)">
      <SylvaIcon :name="lessonIcon(lesson)" :size="20" />
    </div>

    <!-- Info -->
    <div style="min-width:0; flex:1;">
      <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px; flex-wrap:wrap;">
        <strong style="font-size:0.9rem; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:280px;">
          {{ lesson.title }}
        </strong>
        <span
          v-if="lesson.is_preview"
          style="font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; padding:2px 6px; border-radius:6px; background:rgba(var(--green-rgb),0.12); color:var(--green-deep); flex-shrink:0;"
        >
          PREVIEW
        </span>
        <span style="font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; padding:2px 6px; border-radius:6px; background:rgba(17,17,17,0.06); color:var(--muted); flex-shrink:0;">
          {{ lessonChip(lesson) }}
        </span>
      </div>
      <p style="margin:0 0 4px; font-size:0.77rem; color:var(--muted);">{{ lessonSummary(lesson) }}</p>
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); display:flex; align-items:center; gap:3px;">
          <Clock :size="13" :stroke-width="1.75" />
          {{ formatDuration(lesson.duration) }}
        </span>
        <span
          v-if="lesson.video_status === 'processing'"
          style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#b45309; display:flex; align-items:center; gap:3px;"
        >
          <RefreshCw :size="13" :stroke-width="1.75" />
          Đang xử lý
        </span>
        <span
          v-else-if="lesson.video_status === 'ready' || lesson.video_url"
          style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--green-deep); display:flex; align-items:center; gap:3px;"
        >
          <CircleCheckBig :size="13" :stroke-width="1.75" />
          Sẵn sàng
        </span>
      </div>
    </div>

    <!-- Actions -->
    <div class="crud-actions" style="flex-shrink:0; margin-left:auto;">
      <button class="action-btn is-edit" type="button" @click="emit('edit', lesson)">
        <Pencil :size="15" :stroke-width="1.75" style="margin-right:3px;" />
        Sửa
      </button>
      <button
        v-if="lesson.type === 'video'"
        class="action-btn is-view"
        type="button"
        @click="emit('uploadVideo', lesson)"
      >
        <CloudUpload :size="15" :stroke-width="1.75" style="margin-right:3px;" />
        Video
      </button>
      <NuxtLink
        :to="`/instructor/courses/${courseId}/lessons/${lesson.id}/quiz`"
        class="action-btn is-view"
        title="Quản lý Quiz"
        style="display:inline-flex; align-items:center;"
      >
        <FileQuestion :size="15" :stroke-width="1.75" />
      </NuxtLink>
      <button class="action-btn is-delete" type="button" @click="emit('delete', lesson)">
        <Trash2 :size="15" :stroke-width="1.75" />
      </button>
    </div>
  </div>
</template>
