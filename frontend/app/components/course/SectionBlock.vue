<script setup lang="ts">
import LessonBlock from './LessonBlock.vue'

const props = defineProps<{
  section: any
  courseId: number
  index: number
  isFirst: boolean
  isLast: boolean
}>()

const emit = defineEmits<{
  editSection: [section: any]
  deleteSection: [id: number]
  moveSectionUp: [section: any]
  moveSectionDown: [section: any]
  addLesson: [section: any]
  editLesson: [lesson: any]
  deleteLesson: [lesson: any]
  uploadVideo: [lesson: any]
  moveLessonUp: [section: any, lesson: any]
  moveLessonDown: [section: any, lesson: any]
}>()

function formatDuration(seconds: number) {
  if (!seconds) return '0 phút'
  const mins = Math.floor(seconds / 60)
  return `${mins} phút`
}
</script>

<template>
  <div class="dashboard-card" style="padding:20px;">
    <!-- Section Header -->
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding-bottom:16px; border-bottom:1px solid rgba(17,17,17,0.07); margin-bottom:16px;">
      <div style="display:flex; align-items:center; gap:12px; min-width:0; flex:1;">
        <!-- Order Buttons -->
        <div style="display:flex; flex-direction:column; gap:3px; flex-shrink:0;">
          <button
            class="curriculum-order-btn"
            :disabled="isFirst"
            @click="emit('moveSectionUp', section)"
          >
            <span class="material-symbols-outlined" style="font-size:16px;">expand_less</span>
          </button>
          <button
            class="curriculum-order-btn"
            :disabled="isLast"
            @click="emit('moveSectionDown', section)"
          >
            <span class="material-symbols-outlined" style="font-size:16px;">expand_more</span>
          </button>
        </div>

        <!-- Section Number -->
        <div class="curriculum-section-num">{{ index + 1 }}</div>

        <!-- Title & Meta -->
        <div style="min-width:0; flex:1;">
          <strong style="display:block; font-size:1rem; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            {{ section.title }}
          </strong>
          <p v-if="section.description" style="margin:2px 0 0; font-size:0.8rem; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            {{ section.description }}
          </p>
          <div style="display:flex; align-items:center; gap:14px; margin-top:4px;">
            <span style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); display:flex; align-items:center; gap:4px;">
              <span class="material-symbols-outlined" style="font-size:14px;">menu_book</span>
              {{ section.lessons?.length || 0 }} bài học
            </span>
            <span style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); display:flex; align-items:center; gap:4px;">
              <span class="material-symbols-outlined" style="font-size:14px;">schedule</span>
              {{ formatDuration(section.total_duration || 0) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="crud-actions" style="flex-shrink:0;">
        <button class="action-btn is-edit" type="button" @click="emit('editSection', section)">
          <span class="material-symbols-outlined" style="font-size:16px;margin-right:4px;">edit</span>
          Sửa tên
        </button>
        <button class="action-btn is-delete" type="button" @click="emit('deleteSection', section.id)">
          <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
        </button>
      </div>
    </div>

    <!-- Lessons List -->
    <div class="curriculum-lessons-list">
      <LessonBlock
        v-for="(lesson, lIdx) in section.lessons"
        :key="lesson.id"
        :lesson="lesson"
        :course-id="courseId"
        :index="lIdx"
        :is-first="lIdx === 0"
        :is-last="lIdx === (section.lessons?.length || 0) - 1"
        @edit="emit('editLesson', $event)"
        @delete="emit('deleteLesson', $event)"
        @upload-video="emit('uploadVideo', $event)"
        @move-up="emit('moveLessonUp', section, $event)"
        @move-down="emit('moveLessonDown', section, $event)"
      />

      <!-- Add Lesson -->
      <button class="curriculum-add-lesson" type="button" @click="emit('addLesson', section)">
        <span class="material-symbols-outlined" style="font-size:22px;">add_circle</span>
        Thêm bài học vào chương này
      </button>
    </div>
  </div>
</template>
