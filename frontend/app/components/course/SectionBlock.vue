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
  <div class="section-block bg-surface-low/30 rounded-[2.5rem] p-4 md:p-6 border border-surface-dim">
    <!-- Section Header -->
    <div class="flex flex-col md:flex-row md:items-center gap-6 mb-8 px-4">
      <!-- Ordering Controls -->
      <div class="flex items-center gap-2">
        <div class="flex flex-col gap-1">
          <button 
            @click="emit('moveSectionUp', section)" 
            :disabled="isFirst"
            class="w-8 h-8 flex items-center justify-center bg-surface-lowest rounded-xl border border-surface-dim/30 disabled:opacity-30 hover:text-primary transition-all"
          >
            <span class="material-symbols-outlined text-[20px]">expand_less</span>
          </button>
          <button 
            @click="emit('moveSectionDown', section)" 
            :disabled="isLast"
            class="w-8 h-8 flex items-center justify-center bg-surface-lowest rounded-xl border border-surface-dim/30 disabled:opacity-30 hover:text-primary transition-all"
          >
            <span class="material-symbols-outlined text-[20px]">expand_more</span>
          </button>
        </div>
        <div class="w-10 h-10 rounded-2xl bg-primary text-white flex items-center justify-center font-bold text-lg shadow-sm">
          {{ index + 1 }}
        </div>
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="font-headline text-2xl font-bold text-on-surface truncate">{{ section.title }}</h3>
        <p v-if="section.description" class="text-on-surface-variant text-sm mt-1 line-clamp-1 truncate">{{ section.description }}</p>
        <div class="flex items-center gap-4 mt-2 text-xs font-bold text-outline-variant uppercase tracking-widest">
           <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">menu_book</span> {{ section.lessons?.length || 0 }} bài học</span>
           <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">schedule</span> {{ formatDuration(section.total_duration || 0) }}</span>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button 
          @click="emit('editSection', section)" 
          class="flex items-center gap-2 px-4 py-2.5 bg-surface-lowest border border-surface-dim/30 rounded-xl text-sm font-bold text-on-surface-variant hover:text-primary hover:border-primary/30 transition-all"
        >
          <span class="material-symbols-outlined text-[18px]">edit</span>
          Sửa tên
        </button>
        <button 
          @click="emit('deleteSection', section.id)" 
          class="w-11 h-11 flex items-center justify-center bg-surface-lowest border border-surface-dim/30 rounded-xl text-outline-variant hover:text-error hover:border-error/30 transition-all"
        >
          <span class="material-symbols-outlined text-[20px]">delete</span>
        </button>
      </div>
    </div>

    <!-- Lessons List -->
    <div class="space-y-4 relative">
      <!-- Timeline connector -->
      <div class="absolute left-10 md:left-[52px] top-0 bottom-0 w-[2px] bg-primary/5 -z-0"></div>

      <div v-for="(lesson, lIdx) in section.lessons" :key="lesson.id" class="relative z-10 pl-4 md:pl-12">
        <LessonBlock 
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
      </div>

      <!-- Add Lesson Trigger -->
      <div class="pl-4 md:pl-12">
        <div 
          @click="emit('addLesson', section)"
          class="group flex items-center justify-center p-6 border-2 border-dashed border-primary/10 rounded-[1.5rem] bg-white/40 hover:bg-primary/5 hover:border-primary/40 cursor-pointer transition-all duration-300 relative z-10"
        >
          <div class="flex items-center gap-3 text-primary/60 group-hover:text-primary transition-colors">
            <span class="material-symbols-outlined text-4xl">add_circle</span>
            <span class="font-headline font-bold text-lg">Thêm bài học vào chương này</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
