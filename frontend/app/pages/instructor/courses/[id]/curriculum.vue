<template>
  <div class="curriculum-page">
    <div class="container">
      <!-- Header -->
      <div class="page-header">
        <div>
          <nuxt-link :to="`/instructor/courses`" class="back-link">
            ← Back to Courses
          </nuxt-link>
          <h1 class="page-title">{{ course?.title }}</h1>
          <p class="page-subtitle">Manage your course curriculum</p>
        </div>
        <div class="header-actions">
          <button @click="previewCourse" class="btn-secondary">Preview</button>
          <button @click="publishCourse" class="btn-primary">Publish Course</button>
        </div>
      </div>

      <!-- Section Manager -->
      <SectionManager
        ref="sectionManagerRef"
        :course-id="courseId"
        @add-lesson="handleAddLesson"
        @edit-lesson="handleEditLesson"
        @upload-video="handleUploadVideo"
        @delete-lesson="handleDeleteLesson"
      />

      <!-- Add/Edit Lesson Modal -->
      <div v-if="showLessonModal" class="modal-overlay" @click.self="closeLessonModal">
        <div class="modal">
          <h3 class="modal-title">{{ editingLesson ? 'Edit Lesson' : 'Add New Lesson' }}</h3>
          <form @submit.prevent="saveLesson">
            <div class="form-group">
              <label>Lesson Title *</label>
              <input v-model="lessonForm.title" type="text" required class="input">
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea v-model="lessonForm.description" rows="3" class="input"></textarea>
            </div>
            <div class="form-group">
              <label>
                <input v-model="lessonForm.is_preview" type="checkbox" class="checkbox">
                Allow free preview
              </label>
            </div>
            <div class="modal-actions">
              <button type="button" @click="closeLessonModal" class="btn-secondary">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Lesson' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Upload Video Modal -->
      <div v-if="showUploadModal" class="modal-overlay" @click.self="closeUploadModal">
        <div class="modal modal-lg">
          <h3 class="modal-title">Upload Video - {{ uploadingLesson?.title }}</h3>
          <VideoUploader
            v-if="uploadingLesson"
            :course-id="courseId"
            :lesson-id="uploadingLesson.id"
            :existing-video-url="uploadingLesson.video_url"
            @uploaded="handleVideoUploaded"
            @error="handleUploadError"
          />
          <div class="modal-actions">
            <button @click="closeUploadModal" class="btn-secondary">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

definePageMeta({
  middleware: 'instructor',
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const course = ref<any>(null)
const sectionManagerRef = ref<any>(null)
const showLessonModal = ref(false)
const showUploadModal = ref(false)
const editingLesson = ref<any>(null)
const uploadingLesson = ref<any>(null)
const saving = ref(false)

const lessonForm = ref({
  title: '',
  description: '',
  section_id: null as number | null,
  is_preview: false
})

onMounted(() => {
  loadCourse()
})

const loadCourse = async () => {
  try {
    const response = await useApi(`/courses/${courseId}`, {
      token: auth.token,
    })
    course.value = response
  } catch (error) {
    console.error('Failed to load course:', error)
  }
}

const handleAddLesson = (section: any) => {
  editingLesson.value = null
  lessonForm.value = {
    title: '',
    description: '',
    section_id: section.id,
    is_preview: false
  }
  showLessonModal.value = true
}

const handleEditLesson = (lesson: any) => {
  editingLesson.value = lesson
  lessonForm.value = {
    title: lesson.title,
    description: lesson.description || '',
    section_id: lesson.section_id,
    is_preview: lesson.is_preview || false
  }
  showLessonModal.value = true
}

const saveLesson = async () => {
  saving.value = true
  try {
    if (editingLesson.value) {
      await useApi(`/courses/${courseId}/lessons/${editingLesson.value.id}`, {
        method: 'PUT',
        body: lessonForm.value,
        token: auth.token,
      })
    } else {
      await useApi(`/courses/${courseId}/lessons`, {
        method: 'POST',
        body: {
          ...lessonForm.value,
          order: 0 // Will be auto-calculated by backend
        },
        token: auth.token,
      })
    }

    closeLessonModal()
    await sectionManagerRef.value?.loadSections?.()
  } catch (error) {
    console.error('Failed to save lesson:', error)
    alert('Failed to save lesson')
  } finally {
    saving.value = false
  }
}

const closeLessonModal = () => {
  showLessonModal.value = false
  editingLesson.value = null
}

const handleUploadVideo = (lesson: any) => {
  uploadingLesson.value = lesson
  showUploadModal.value = true
}

const handleVideoUploaded = async () => {
  setTimeout(async () => {
    closeUploadModal()
    await sectionManagerRef.value?.loadSections?.()
  }, 800)
}

const handleUploadError = (error: string) => {
  console.error('Upload error:', error)
}

const closeUploadModal = () => {
  showUploadModal.value = false
  uploadingLesson.value = null
}

const handleDeleteLesson = async (lesson: any) => {
  if (!confirm(`Delete lesson "${lesson.title}"?`)) return
  try {
    await useApi(`/courses/${courseId}/lessons/${lesson.id}`, {
      method: 'DELETE',
      token: auth.token,
    })
    await sectionManagerRef.value?.loadSections?.()
  } catch (error: any) {
    alert(error?.data?.message || 'Failed to delete lesson')
  }
}

const previewCourse = () => {
  router.push(`/courses/${courseId}`)
}

const publishCourse = async () => {
  if (!confirm('Publish this course? It will be submitted for review.')) return
  
  try {
    await useApi(`/courses/${courseId}/publish`, { method: 'POST', token: auth.token })
    alert('Course submitted for review!')
    router.push('/instructor/courses')
  } catch (error: any) {
    alert(error?.data?.message || 'Failed to publish course')
  }
}
</script>

<style scoped>
.curriculum-page {
  min-height: 100vh;
  background: #f9fafb;
  padding: 32px 0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid #e5e7eb;
}

.back-link {
  display: inline-block;
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 12px;
  text-decoration: none;
  transition: color 0.2s;
}

.back-link:hover {
  color: #16a34a;
}

.page-title {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 4px;
}

.page-subtitle {
  color: #6b7280;
  font-size: 16px;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-primary, .btn-secondary {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-primary {
  background: #16a34a;
  color: white;
}

.btn-primary:hover {
  background: #15803d;
}

.btn-secondary {
  background: white;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-secondary:hover {
  background: #f3f4f6;
}

/* Modal styles matching SectionManager */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  width: 90%;
  max-width: 500px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  max-height: 90vh;
  overflow-y: auto;
}

.modal-lg {
  max-width: 700px;
}

.modal-title {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 24px;
  color: #111827;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.input:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}

.checkbox {
  margin-right: 8px;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
