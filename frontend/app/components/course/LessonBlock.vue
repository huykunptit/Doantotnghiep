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
}>()

function formatDuration(seconds: number) {
  if (!seconds) return '--:--'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

function lessonIcon(lesson: any) {
  const type = lesson.type || 'video'
  const map: Record<string, string> = {
    video: lesson.video_url || lesson.video_status === 'ready' ? 'movie' : 'video_library',
    document: 'description',
    assignment: 'assignment',
    virtual_class: 'video_camera_front',
    scorm: 'subscriptions',
    h5p: 'extension',
    quiz: 'quiz',
  }
  return map[type] || 'article'
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
</script>

<template>
  <div 
    class="lesson-block group bg-surface-lowest p-5 rounded-[1.5rem] border border-surface-dim shadow-sm hover:border-primary/40 hover:shadow-md transition-all flex flex-col md:flex-row md:items-center gap-6"
  >
    <!-- Left: Drag/Order Handle Style & Icon -->
    <div class="flex items-center gap-4 flex-1">
      <div class="flex flex-col gap-1 shrink-0">
        <button 
          @click="emit('moveUp', lesson)" 
          :disabled="isFirst"
          class="p-1 hover:bg-surface-low rounded text-outline-variant disabled:opacity-20 hover:text-primary transition-colors"
        >
          <span class="material-symbols-outlined text-[18px]">keyboard_arrow_up</span>
        </button>
        <button 
          @click="emit('moveDown', lesson)" 
          :disabled="isLast"
          class="p-1 hover:bg-surface-low rounded text-outline-variant disabled:opacity-20 hover:text-primary transition-colors"
        >
          <span class="material-symbols-outlined text-[18px]">keyboard_arrow_down</span>
        </button>
      </div>

      <div 
        class="w-12 h-12 shrink-0 rounded-2xl flex items-center justify-center transition-colors shadow-sm"
        :class="lesson.type === 'assignment' ? 'bg-amber-50 text-amber-600' : lesson.type === 'virtual_class' ? 'bg-sky-50 text-sky-600' : lesson.type === 'document' ? 'bg-violet-50 text-violet-600' : lesson.type === 'scorm' || lesson.type === 'h5p' ? 'bg-orange-50 text-orange-600' : lesson.video_url || lesson.video_status === 'ready' ? 'bg-emerald-50 text-emerald-600' : 'bg-primary/5 text-primary'"
      >
        <span class="material-symbols-outlined text-[24px]">
          {{ lessonIcon(lesson) }}
        </span>
      </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 mb-1">
            <p class="font-bold text-on-surface truncate pr-4 text-base tracking-tight">{{ lesson.title }}</p>
            <UiBadge v-if="lesson.is_preview" variant="success" size="sm">PREVIEW</UiBadge>
            <UiBadge variant="default" size="sm">{{ lessonChip(lesson) }}</UiBadge>
          </div>
          <p class="mb-2 text-sm text-on-surface-variant">{{ lessonSummary(lesson) }}</p>
          <div class="flex items-center gap-4 text-[11px] font-bold text-outline-variant uppercase tracking-widest">
           <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> {{ formatDuration(lesson.duration) }}</span>
           <span v-if="lesson.video_status === 'processing'" class="text-amber-600 flex items-center gap-1">
             <span class="material-symbols-outlined text-[14px] animate-spin">sync</span> ĐANG XỬ LÝ VIDEO
           </span>
           <span v-else-if="lesson.video_status === 'ready' || lesson.video_url" class="text-emerald-600 flex items-center gap-1">
             <span class="material-symbols-outlined text-[14px]">check_circle</span> VIDEO SẴN SÀNG
           </span>
        </div>
      </div>
    </div>

    <!-- Right: Action Bar -->
    <div class="flex items-center justify-between md:justify-end gap-3 pt-4 md:pt-0 border-t md:border-t-0 border-surface-dim">
      <div class="flex items-center gap-2">
        <button 
          @click="emit('edit', lesson)" 
          class="p-2.5 bg-surface-low text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-xl transition-all"
          title="Chỉnh sửa nội dung"
        >
          <span class="material-symbols-outlined text-[20px]">edit_square</span>
        </button>
        <button 
          @click="emit('uploadVideo', lesson)" 
          v-if="lesson.type === 'video'"
          class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl shadow-sm hover:shadow-md hover:bg-primary-dark transition-all"
        >
          <span class="material-symbols-outlined text-[18px]">cloud_upload</span>
          VIDEO
        </button>
      </div>

      <div class="h-8 w-[1px] bg-surface-dim/30 mx-1 hidden md:block"></div>

      <div class="flex items-center gap-2">
        <NuxtLink 
          :to="`/instructor/courses/${courseId}/lessons/${lesson.id}/quiz`"
          class="p-2.5 bg-surface-low text-on-surface-variant hover:text-secondary hover:bg-secondary/5 rounded-xl transition-all"
          title="Bài kiểm tra (Quiz)"
        >
          <span class="material-symbols-outlined text-[20px]">quiz</span>
        </NuxtLink>
        <NuxtLink 
          :to="`/instructor/courses/${courseId}/lessons/${lesson.id}/attachments`"
          class="p-2.5 bg-surface-low text-on-surface-variant hover:text-secondary hover:bg-secondary/5 rounded-xl transition-all"
          :title="lesson.type === 'document' ? 'Quản lý file đính kèm' : 'Tài liệu đính kèm'"
        >
          <span class="material-symbols-outlined text-[20px]">attachment</span>
        </NuxtLink>
        <button 
          @click="emit('delete', lesson)" 
          class="p-2.5 bg-surface-low text-outline-variant hover:text-error hover:bg-error/5 rounded-xl transition-all"
          title="Xóa bài học"
        >
          <span class="material-symbols-outlined text-[20px]">delete</span>
        </button>
      </div>
    </div>
  </div>
</template>
