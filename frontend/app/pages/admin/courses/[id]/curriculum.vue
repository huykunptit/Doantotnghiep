<template>
  <div>
    <NuxtLayout name="admin">
      <div class="flex items-center justify-between mb-6">
        <div>
          <NuxtLink :to="`/admin/courses/${courseId}`" class="text-sm text-gray-500">← Quay lại duyệt khóa học</NuxtLink>
          <h1 class="text-2xl font-bold text-gray-900 mt-1">Curriculum (Admin)</h1>
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

      <div v-if="showLessonModal" class="modal-overlay" @click.self="showLessonModal = false">
        <div class="modal">
          <h3 class="text-lg font-bold mb-4">{{ editingLesson ? 'Sửa bài học' : 'Thêm bài học' }}</h3>
          <div class="space-y-3">
            <input v-model="lessonForm.title" class="input" placeholder="Tiêu đề bài học" />
            <textarea v-model="lessonForm.description" class="input" rows="3" placeholder="Mô tả"></textarea>
            <label class="text-sm text-gray-700">
              <input v-model="lessonForm.is_preview" type="checkbox" class="mr-2" />
              Cho phép xem thử
            </label>
            <div class="flex justify-end gap-2">
              <button class="btn-secondary" @click="showLessonModal = false">Hủy</button>
              <button class="btn-primary" @click="saveLesson">Lưu</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showUploadModal" class="modal-overlay" @click.self="showUploadModal = false">
        <div class="modal max-w-2xl">
          <h3 class="text-lg font-bold mb-4">Upload video</h3>
          <VideoUploader
            v-if="uploadingLesson"
            :course-id="courseId"
            :lesson-id="uploadingLesson.id"
            :existing-video-url="uploadingLesson.video_url"
            @uploaded="onUploaded"
          />
          <div class="mt-4 flex justify-end">
            <button class="btn-secondary" @click="showUploadModal = false">Đóng</button>
          </div>
        </div>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
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

