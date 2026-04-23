<template>
  <NuxtLayout name="admin">
    <div class="space-y-8">
      <AppPageHeader eyebrow="Admin" title="Curriculum khóa học" description="Xem và rà soát cấu trúc section, lesson và tài nguyên của khóa học.">
        <template #actions>
          <NuxtLink :to="`/admin/courses/${courseId}`"><UiButton variant="secondary">Quay lại chi tiết</UiButton></NuxtLink>
        </template>
      </AppPageHeader>

      <UiCard>
        <div class="mb-6 flex items-center justify-between">
          <div>
            <p class="text-sm text-on-surface-variant">Mã khóa học #{{ courseId }}</p>
            <h2 class="text-xl font-semibold text-on-surface">Quản lý nội dung / review curriculum</h2>
          </div>
        </div>

        <SectionManager
          ref="sectionManagerRef"
          :course-id="courseId"
          @add-lesson="handleAddLesson"
          @edit-lesson="handleEditLesson"
          @upload-video="handleUploadVideo"
          @delete-lesson="handleDeleteLesson"
        />
      </UiCard>

      <Teleport to="body">
        <div v-if="showLessonModal" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/40 p-4" @click.self="showLessonModal = false">
          <div class="w-full max-w-xl rounded-3xl bg-surface-lowest p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-on-surface">{{ editingLesson ? 'Sửa bài học' : 'Thêm bài học' }}</h3>
              <button class="text-outline" @click="showLessonModal = false">✕</button>
            </div>
            <div class="space-y-4">
              <UiInput v-model="lessonForm.title" label="Tiêu đề bài học" placeholder="Nhập tiêu đề bài học" />
              <UiTextarea v-model="lessonForm.description" label="Mô tả" :rows="4" placeholder="Nhập mô tả..." />
              <label class="flex items-center gap-3 rounded-2xl border border-surface-dim px-4 py-3 text-sm text-on-surface-variant">
                <input v-model="lessonForm.is_preview" type="checkbox">
                Cho phép xem thử
              </label>
              <div class="flex justify-end gap-3">
                <UiButton variant="ghost" @click="showLessonModal = false">Hủy</UiButton>
                <UiButton @click="saveLesson">Lưu</UiButton>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="showUploadModal" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/40 p-4" @click.self="showUploadModal = false">
          <div class="w-full max-w-3xl rounded-3xl bg-surface-lowest p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-on-surface">Upload video</h3>
              <button class="text-outline" @click="showUploadModal = false">✕</button>
            </div>
            <VideoUploader v-if="uploadingLesson" :course-id="courseId" :lesson-id="uploadingLesson.id" :existing-video-url="uploadingLesson.video_url" @uploaded="onUploaded" />
          </div>
        </div>
      </Teleport>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const sectionManagerRef = ref<any>(null)
const showLessonModal = ref(false)
const showUploadModal = ref(false)
const editingLesson = ref<any>(null)
const uploadingLesson = ref<any>(null)
const lessonForm = ref({
  title: '',
  description: '',
  section_id: null as number | null,
  is_preview: false,
})

function handleAddLesson(section: any) {
  editingLesson.value = null
  lessonForm.value = { title: '', description: '', section_id: section.id, is_preview: false }
  showLessonModal.value = true
}

function handleEditLesson(lesson: any) {
  editingLesson.value = lesson
  lessonForm.value = {
    title: lesson.title || '',
    description: lesson.description || '',
    section_id: lesson.section_id || null,
    is_preview: !!lesson.is_preview,
  }
  showLessonModal.value = true
}

async function saveLesson() {
  if (!lessonForm.value.title.trim()) return
  if (editingLesson.value) {
    await useApi(`/courses/${courseId}/lessons/${editingLesson.value.id}`, {
      method: 'PUT',
      body: lessonForm.value,
      token: auth.token,
    })
  } else {
    await useApi(`/courses/${courseId}/lessons`, {
      method: 'POST',
      body: { ...lessonForm.value, order: 0 },
      token: auth.token,
    })
  }
  showLessonModal.value = false
  await sectionManagerRef.value?.loadSections?.()
}

function handleUploadVideo(lesson: any) {
  uploadingLesson.value = lesson
  showUploadModal.value = true
}

async function onUploaded() {
  showUploadModal.value = false
  await sectionManagerRef.value?.loadSections?.()
}

async function handleDeleteLesson(lesson: any) {
  if (!confirm(`Xóa bài học "${lesson.title}"?`)) return
  await useApi(`/courses/${courseId}/lessons/${lesson.id}`, {
    method: 'DELETE',
    token: auth.token,
  })
  await sectionManagerRef.value?.loadSections?.()
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.modal { background: #fff; border-radius: 12px; padding: 20px; width: min(90vw, 640px); }
</style>

