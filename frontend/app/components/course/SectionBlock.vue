<script setup lang="ts">
// Icons removed - using PrimeIcons
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
  viewSubmissions: [lesson: any]
}>()

function formatDuration(seconds: number) {
  if (!seconds) return '0 phút'
  const mins = Math.floor(seconds / 60)
  return `${mins} phút`
}
</script>

<template>
  <div class="studio-section-card">
    <!-- Section Header Panel -->
    <div class="section-header-panel">
      <!-- Drag & Order arrows -->
      <div class="section-order-arrows">
        <button
          class="order-arrow-btn"
          :disabled="isFirst"
          @click="emit('moveSectionUp', section)"
          title="Di chuyển chương lên"
        >
          <i class="pi pi-chevron-up" style="font-size:0.8125rem" />
        </button>
        <button
          class="order-arrow-btn"
          :disabled="isLast"
          @click="emit('moveSectionDown', section)"
          title="Di chuyển chương xuống"
        >
          <i class="pi pi-chevron-down" style="font-size:0.8125rem" />
        </button>
      </div>

      <!-- Section Numeric Indicator -->
      <div class="section-index-badge">Chương {{ index + 1 }}</div>

      <!-- Section Details -->
      <div class="section-details-info">
        <h4 class="section-title-text">{{ section.title }}</h4>
        <p v-if="section.description" class="section-desc-text">{{ section.description }}</p>
        
        <div class="section-summary-meta">
          <span class="meta-badge">
            <i class="pi pi-book" style="font-size:0.75rem" />
            {{ section.lessons?.length || 0 }} bài học
          </span>
          <span class="meta-badge">
            <i class="pi pi-clock" style="font-size:0.75rem" />
            {{ formatDuration(section.total_duration || 0) }}
          </span>
        </div>
      </div>

      <!-- Section Operations -->
      <div class="section-ops-buttons">
        <button class="op-btn is-edit" type="button" @click="emit('editSection', section)">
          <i class="pi pi-pencil" style="font-size:0.75rem" />
          <span>Sửa tiêu đề</span>
        </button>
        <button class="op-btn is-delete" type="button" @click="emit('deleteSection', section.id)">
          <i class="pi pi-trash" style="font-size:0.8125rem" />
        </button>
      </div>
    </div>

    <!-- Lessons Nested List Area -->
    <div class="lessons-nested-container">
      <div class="lessons-list" v-if="section.lessons && section.lessons.length > 0">
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
          @view-submissions="emit('viewSubmissions', $event)"
        />
      </div>

      <!-- Button: Add new lesson to this section -->
      <button class="add-lesson-dashed-btn" type="button" @click="emit('addLesson', section)">
        <i class="pi pi-plus" style="font-size:1.0rem" />
        <span>Thêm bài giảng vào Chương {{ index + 1 }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.studio-section-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  transition: all 250ms ease;
}

.studio-section-card:hover {
  box-shadow: var(--shadow-md);
  border-color: rgba(29, 158, 11, 0.25);
}

/* Header styling */
.section-header-panel {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px 24px;
  background: linear-gradient(to bottom, var(--surface-strong), var(--surface));
  border-bottom: 1px dashed var(--line);
}

.section-order-arrows {
  display: flex;
  flex-direction: column;
  gap: 3px;
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
  opacity: 0.35;
  cursor: not-allowed;
}

.section-index-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  background: var(--green-soft);
  color: var(--green);
  font-size: 0.72rem;
  font-weight: 800;
  border-radius: 8px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
  margin-top: 4px;
}

.section-details-info {
  flex: 1;
  min-width: 0;
}

.section-title-text {
  margin: 0;
  font-size: 1.02rem;
  font-weight: 850;
  color: var(--text);
  letter-spacing: -0.01em;
}

.section-desc-text {
  margin: 4px 0 0;
  font-size: 0.82rem;
  color: var(--muted);
  font-weight: 500;
}

.section-summary-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 8px;
}

.meta-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.74rem;
  color: var(--text-secondary);
  font-weight: 600;
}

.meta-badge svg {
  color: var(--muted);
}

/* Operations panel */
.section-ops-buttons {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.op-btn {
  display: inline-flex;
  align-items: center;
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
}

.op-btn:hover {
  background: var(--surface-strong);
  color: var(--text);
}

.op-btn.is-edit:hover {
  color: var(--green);
  border-color: rgba(29, 158, 117, 0.35);
  background: var(--green-soft);
}

.op-btn.is-delete:hover {
  color: #EF4444;
  border-color: rgba(239, 68, 68, 0.25);
  background: rgba(239, 68, 68, 0.05);
}

/* Nest block */
.lessons-nested-container {
  padding: 20px;
  background: var(--surface);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.lessons-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.add-lesson-dashed-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px;
  border-radius: 12px;
  border: 1.5px dashed var(--line);
  background: transparent;
  color: var(--muted);
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 200ms ease;
}

.add-lesson-dashed-btn:hover {
  border-color: var(--green);
  color: var(--green);
  background: var(--green-soft);
}
</style>
