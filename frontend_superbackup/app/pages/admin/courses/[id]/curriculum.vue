<script setup lang="ts">
import { onMounted, ref } from 'vue'
import CurriculumStudio from '~/components/course/CurriculumStudio.vue'
import VideoUploader from '~/components/VideoUploader.vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)
const course = ref<any>(null)
const studioRef = ref<any>(null)
const showUploadModal = ref(false)
const uploadingLesson = ref<any>(null)

const loadCourse = async () => {
  try {
    course.value = await $fetch(`/api/courses/${courseId}`, {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
  } catch {
    course.value = null
  }
}

onMounted(loadCourse)

function handleUploadTrigger(lesson: any) {
  uploadingLesson.value = lesson
  showUploadModal.value = true
}

async function handleVideoUploaded() {
  showUploadModal.value = false
  uploadingLesson.value = null
  await studioRef.value?.loadSections?.()
}

function closeUploadModal() {
  showUploadModal.value = false
  uploadingLesson.value = null
}

function previewCourse() {
  window.open(`/courses/${courseId}`, '_blank')
}
</script>

<template>
  <div class="flex flex-col gap-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div><p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Curriculum</p><h1 class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-0">{{ course?.title || 'Curriculum khoá học' }}</h1><p class="mt-1 text-sm text-surface-500">Rà soát cấu trúc chương, bài học và tài nguyên.</p></div>
      <div class="flex gap-2"><Button label="Quay lại chi tiết" icon="pi pi-arrow-left" severity="secondary" outlined @click="navigateTo(`/admin/courses/${courseId}`)" /><Button label="Xem trước" icon="pi pi-eye" severity="secondary" @click="previewCourse" /></div>
    </header>
    <Card><template #content>
        <CurriculumStudio
          ref="studioRef"
          :course-id="courseId"
          @upload-video="handleUploadTrigger"
        />
    </template></Card>
    <Dialog v-model:visible="showUploadModal" modal header="Tải lên bài giảng" class="w-[min(38rem,calc(100vw-2rem))]" @hide="closeUploadModal">
      <p class="mb-4 text-sm text-surface-500">{{ uploadingLesson?.title }}</p>
      <VideoUploader v-if="uploadingLesson" :course-id="courseId" :lesson-id="uploadingLesson.id" :existing-video-url="uploadingLesson.video_url" @uploaded="handleVideoUploaded" />
    </Dialog>
  </div>
</template>

<style scoped>
.curriculum-workspace-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 24px;
  align-items: start;
}

.studio-topbar-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  border-radius: 10px;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
  text-decoration: none;
  border: none;
}

.studio-topbar-btn.is-secondary {
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--text-secondary);
}

.studio-topbar-btn.is-secondary:hover {
  background: var(--surface);
  color: var(--text);
}

.studio-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.studio-modal-card.is-uploader {
  width: 100%;
  max-width: 600px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  animation: modal-enter 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modal-enter {
  from { transform: scale(0.95); opacity: 0; }
  to   { transform: scale(1);    opacity: 1; }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 24px;
  border-bottom: 1px solid var(--line);
}

.modal-subtitle-tag {
  font-size: 0.68rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--green);
  display: block;
  margin-bottom: 4px;
}

.modal-title-text {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 850;
  color: var(--text);
  letter-spacing: -0.02em;
}

.uploader-lesson-meta {
  font-size: 0.76rem;
  color: var(--muted);
  display: block;
  margin-top: 4px;
  font-weight: 600;
}

.modal-close-x-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
  transition: color 150ms;
}

.modal-close-x-btn:hover { color: var(--text); }

.uploader-modal-body { padding: 24px; }

.fade-enter-active,
.fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
