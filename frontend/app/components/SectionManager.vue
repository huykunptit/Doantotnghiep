<template>
  <div class="section-manager">
    <!-- Header -->
    <div class="header">
      <h3 class="title">Course Curriculum</h3>
      <button @click="showAddSection = true" class="btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Section
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Loading curriculum...</p>
    </div>

    <!-- Sections List -->
    <div v-else class="sections-list">
      <div
        v-for="(section, index) in sections"
        :key="section.id"
        class="section-card"
      >
        <!-- Section Header -->
        <div class="section-header">
          <div class="section-info">
            <span class="section-number">Section {{ index + 1 }}</span>
            <h4 class="section-title">{{ section.title }}</h4>
            <p v-if="section.description" class="section-description">{{ section.description }}</p>
            <div class="section-meta">
              <span>{{ section.lessons?.length || 0 }} lessons</span>
              <span v-if="section.total_duration">{{ formatDuration(section.total_duration) }}</span>
            </div>
          </div>

          <div class="section-actions">
            <button @click="editSection(section)" class="btn-icon" title="Edit">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button @click="deleteSection(section.id)" class="btn-icon text-red-600" title="Delete">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Lessons in Section -->
        <div v-if="section.lessons && section.lessons.length > 0" class="lessons-list">
          <div
            v-for="lesson in section.lessons"
            :key="lesson.id"
            class="lesson-item"
          >
            <div class="lesson-info">
              <!-- Video icon -->
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5 text-gray-400">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="lesson-title">{{ lesson.title }}</span>
              <span v-if="lesson.is_preview" class="preview-badge">Preview</span>
              <span v-if="lesson.duration" class="lesson-duration">{{ formatDuration(lesson.duration) }}</span>
              <!-- Video status badge -->
              <span v-if="lesson.video_status === 'ready'" class="video-badge video-badge--ready">✓ Video</span>
              <span v-else-if="lesson.video_status === 'processing'" class="video-badge video-badge--processing">⏳ Xử lý</span>
              <span v-else-if="lesson.video_status === 'failed'" class="video-badge video-badge--failed">✗ Lỗi</span>
              <span v-else-if="lesson.video_url" class="video-badge video-badge--ready">✓ Video</span>
              <span v-else class="video-badge video-badge--pending">Chưa có video</span>
            </div>
            <div class="lesson-actions">
              <button @click="$emit('edit-lesson', lesson)" class="btn-icon-sm" title="Chỉnh sửa">Edit</button>
              <button @click="$emit('upload-video', lesson)" class="btn-icon-sm btn-upload" title="Upload video">
                📹 Upload
              </button>
              <button @click="$emit('delete-lesson', lesson)" class="btn-icon-sm btn-danger" title="Xóa bài học">Delete</button>
            </div>
          </div>
        </div>

        <!-- Add Lesson Button -->
        <button @click="$emit('add-lesson', section)" class="btn-add-lesson">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Lesson
        </button>
      </div>

      <!-- Empty State -->
      <div v-if="sections.length === 0" class="empty-state">
        <p>No sections yet. Create your first section to start building your course!</p>
      </div>
    </div>

    <!-- Add/Edit Section Modal -->
    <div v-if="showAddSection || editingSection" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <h3 class="modal-title">{{ editingSection ? 'Edit Section' : 'Add New Section' }}</h3>
        <form @submit.prevent="saveSection">
          <div class="form-group">
            <label>Title *</label>
            <input v-model="sectionForm.title" type="text" required class="input">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="sectionForm.description" rows="3" class="input"></textarea>
          </div>
          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn-secondary">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const props = defineProps<{
  courseId: number
}>()

const emit = defineEmits<{
  'add-lesson': [section: any]
  'edit-lesson': [lesson: any]
  'upload-video': [lesson: any]
  'delete-lesson': [lesson: any]
}>()

const sections = ref<any[]>([])
const loading = ref(false)
const showAddSection = ref(false)
const editingSection = ref<any>(null)
const saving = ref(false)

const sectionForm = ref({
  title: '',
  description: ''
})

onMounted(() => {
  loadSections()
})

const loadSections = async () => {
  loading.value = true
  const auth = useAuthStore()
  try {
    const response = await useApi<{ data: any[] }>(`/courses/${props.courseId}/sections`, {
      token: auth.token,
    })
    sections.value = response.data || []
  } catch (error) {
    console.error('Failed to load sections:', error)
  } finally {
    loading.value = false
  }
}

const saveSection = async () => {
  saving.value = true
  const auth = useAuthStore()
  try {
    if (editingSection.value) {
      await useApi(`/sections/${editingSection.value.id}`, {
        method: 'PUT',
        body: sectionForm.value,
        token: auth.token,
      })
    } else {
      await useApi(`/courses/${props.courseId}/sections`, {
        method: 'POST',
        body: sectionForm.value,
        token: auth.token,
      })
    }
    
    closeModal()
    loadSections()
  } catch (error) {
    console.error('Failed to save section:', error)
  } finally {
    saving.value = false
  }
}

const editSection = (section: any) => {
  editingSection.value = section
  sectionForm.value = {
    title: section.title,
    description: section.description || ''
  }
}

const deleteSection = async (sectionId: number) => {
  if (!confirm('Are you sure you want to delete this section?')) return
  const auth = useAuthStore()

  try {
    await useApi(`/sections/${sectionId}`, { method: 'DELETE', token: auth.token })
    await loadSections()
  } catch (error: any) {
    alert(error?.data?.message || 'Failed to delete section')
  }
}

const closeModal = () => {
  showAddSection.value = false
  editingSection.value = null
  sectionForm.value = { title: '', description: '' }
}

const formatDuration = (seconds: number) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

defineExpose({ loadSections })
</script>

<style scoped>
.section-manager {
  width: 100%;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #16a34a;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover {
  background: #15803d;
}

.loading {
  text-align: center;
  padding: 48px;
  color: #6b7280;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-top-color: #16a34a;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.sections-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.section-info {
  flex: 1;
}

.section-number {
  display: inline-block;
  padding: 4px 12px;
  background: #16a34a;
  color: white;
  font-size: 12px;
  font-weight: 600;
  border-radius: 12px;
  margin-bottom: 8px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
}

.section-description {
  font-size: 14px;
  color: #6b7280;
  margin-top: 4px;
}

.section-meta {
  display: flex;
  gap: 16px;
  margin-top: 8px;
  font-size: 13px;
  color: #6b7280;
}

.section-actions {
  display: flex;
  gap: 8px;
}

.btn-icon {
  padding: 8px;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  color: #6b7280;
}

.btn-icon:hover {
  background: #f3f4f6;
  border-color: #16a34a;
  color: #16a34a;
}

.lessons-list {
  padding: 12px 20px;
}

.lesson-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-radius: 8px;
  transition: background 0.2s;
}

.lesson-item:hover {
  background: #f9fafb;
}

.lesson-info {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.lesson-title {
  font-size: 14px;
  color: #111827;
  flex: 1;
}

.lesson-duration {
  font-size: 13px;
  color: #6b7280;
}

.video-badge {
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 4px;
}

.video-badge--ready {
  background: #dcfce7;
  color: #166534;
}

.video-badge--processing {
  background: #fef9c3;
  color: #854d0e;
}

.video-badge--failed {
  background: #fee2e2;
  color: #991b1b;
}

.video-badge--pending {
  background: #f3f4f6;
  color: #6b7280;
}

.preview-badge {
  padding: 2px 8px;
  background: #e0f2fe;
  color: #0369a1;
  font-size: 11px;
  font-weight: 600;
  border-radius: 4px;
}

.lesson-actions {
  display: flex;
  gap: 8px;
}

.btn-icon-sm {
  padding: 6px 12px;
  font-size: 13px;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-icon-sm:hover {
  background: #16a34a;
  color: white;
  border-color: #16a34a;
}

.btn-icon-sm.btn-upload:hover {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.btn-icon-sm.btn-danger:hover {
  background: #dc2626;
  color: white;
  border-color: #dc2626;
}

.btn-add-lesson {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px;
  background: white;
  border: 2px dashed #d1d5db;
  border-radius: 0 0 12px 12px;
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-lesson:hover {
  background: #f9fafb;
  border-color: #16a34a;
  color: #16a34a;
}

.empty-state {
  text-align: center;
  padding: 48px;
  color: #6b7280;
  background: #f9fafb;
  border-radius: 12px;
  border: 2px dashed #d1d5db;
}

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

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
}

.btn-secondary {
  padding: 10px 20px;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: #f3f4f6;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
