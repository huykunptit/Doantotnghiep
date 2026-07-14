<script setup lang="ts">
import { ref, onMounted } from 'vue'
// Icons removed - using PrimeIcons
import SectionBlock from './SectionBlock.vue'
import LessonFormModal from './LessonFormModal.vue'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

const toast = useToast()

const props = defineProps<{
  courseId: number
}>()

const emit = defineEmits<{
  uploadVideo: [lesson: any]
}>()

const sections = ref<any[]>([])
const loading = ref(true)
const auth = useAuthStore()

const showSectionModal = ref(false)
const showLessonModal = ref(false)
const editingSection = ref<any>(null)
const editingLesson = ref<any>(null)
const currentSectionForLesson = ref<any>(null)
const sectionForm = ref({ title: '', description: '' })
const saving = ref(false)
const uploadProgress = ref(0)   // 0-100, only active during file uploads
const uploadLabel = ref('')     // e.g. "Đang tải SCORM..." or "Đang tải video..."

// Submissions
const showSubmissionsModal = ref(false)
const submissionsLesson = ref<any>(null)
const submissions = ref<any[]>([])
const loadingSubmissions = ref(false)

async function openSubmissions(lesson: any) {
  submissionsLesson.value = lesson
  submissions.value = []
  showSubmissionsModal.value = true
  loadingSubmissions.value = true
  try {
    const res = await $fetch<any[]>(`/api/courses/${props.courseId}/lessons/${lesson.id}/assignment/submissions`, {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    submissions.value = Array.isArray(res) ? res : (res as any)?.data || []
  } catch {
    toast.error('Không thể tải danh sách bài nộp.')
  } finally {
    loadingSubmissions.value = false
  }
}

onMounted(loadSections)

async function loadSections() {
  loading.value = true
  try {
    const res = await $fetch<{ data: any[] }>(`/api/courses/${props.courseId}/sections`, {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    sections.value = res.data || []
  } catch (error) {
    console.error('Failed to load sections:', error)
  } finally {
    loading.value = false
  }
}

function handleAddSection() {
  editingSection.value = null
  sectionForm.value = { title: '', description: '' }
  showSectionModal.value = true
}

function handleEditSection(section: any) {
  editingSection.value = section
  sectionForm.value = { title: section.title, description: section.description || '' }
  showSectionModal.value = true
}

async function saveSection() {
  saving.value = true
  try {
    if (editingSection.value) {
      await $fetch(`/api/sections/${editingSection.value.id}`, {
        method: 'PUT',
        body: sectionForm.value,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
    } else {
      await $fetch(`/api/courses/${props.courseId}/sections`, {
        method: 'POST',
        body: sectionForm.value,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
    }
    showSectionModal.value = false
    await loadSections()
  } catch {
    toast.error('Không thể lưu chương học.')
  } finally {
    saving.value = false
  }
}

async function deleteSection(id: number) {
  if (!confirm('Xóa chương này sẽ xóa toàn bộ bài học bên trong. Bạn chắc chắn chứ?')) return
  try {
    await $fetch(`/api/sections/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    await loadSections()
  } catch {
    toast.error('Lỗi khi xóa chương.')
  }
}

function handleAddLesson(section: any) {
  currentSectionForLesson.value = section
  editingLesson.value = null
  showLessonModal.value = true
}

function handleEditLesson(lesson: any) {
  editingLesson.value = lesson
  showLessonModal.value = true
}

async function saveLesson(formData: any) {
  saving.value = true
  uploadProgress.value = 0
  uploadLabel.value = ''
  try {
    const basePayload = {
      title: formData.title,
      description: formData.description,
      type: formData.type,
      is_preview: formData.is_preview,
      duration: Number(formData.duration || 0),
      video_url: formData.type === 'video' ? (formData.video_url || null) : null,
    }

    let lessonId = editingLesson.value?.id

    if (editingLesson.value) {
      await $fetch(`/api/courses/${props.courseId}/lessons/${editingLesson.value.id}`, {
        method: 'PUT',
        body: basePayload,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
    } else {
      const response = await $fetch<any>(`/api/courses/${props.courseId}/lessons`, {
        method: 'POST',
        body: { ...basePayload, section_id: currentSectionForLesson.value.id, order: 0 },
        headers: { Authorization: `Bearer ${auth.token}` },
      })
      lessonId = response?.lesson?.id
    }

    if (!lessonId) throw new Error('Missing lesson id')

    if (formData.type === 'video' && formData.video_file) {
      const videoPayload = new FormData()
      videoPayload.append('video', formData.video_file)
      uploadLabel.value = 'Đang tải video lên...'
      await uploadWithProgress(
        `/api/courses/${props.courseId}/lessons/${lessonId}/upload-video`,
        videoPayload,
      )
      uploadProgress.value = 0
    }

    if (formData.type === 'assignment') {
      await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/assignment`, {
        method: 'POST',
        body: formData.assignment,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
    }

    if (formData.type === 'virtual_class') {
      await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/virtual-class`, {
        method: 'POST',
        body: formData.virtual_class,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
    }

    if (formData.type === 'scorm' || formData.type === 'h5p') {
      const payload = new FormData()
      payload.append('type', formData.type)
      if (formData.scorm_package?.entry_url) payload.append('entry_url', formData.scorm_package.entry_url)
      if (formData.scorm_package?.title) payload.append('title', formData.scorm_package.title)
      if (formData.scorm_package?.identifier) payload.append('identifier', formData.scorm_package.identifier)
      if (formData.scorm_package?.version) payload.append('version', formData.scorm_package.version)
      if (formData.scorm_file) {
        payload.append('scorm_file', formData.scorm_file)
        uploadLabel.value = 'Đang tải gói SCORM lên...'
        await uploadWithProgress(
          `/api/courses/${props.courseId}/lessons/${lessonId}/scorm-package`,
          payload,
        )
        uploadProgress.value = 0
      } else {
        await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/scorm-package`, {
          method: 'POST',
          body: payload,
          headers: { Authorization: `Bearer ${auth.token}` },
        })
      }
    }

    if (Array.isArray(formData.attachments) && formData.attachments.length > 0) {
      await Promise.all(formData.attachments.map((file: File) => {
        const payload = new FormData()
        payload.append('file', file)
        return $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/attachments`, {
          method: 'POST',
          body: payload,
          headers: { Authorization: `Bearer ${auth.token}` },
        })
      }))
    }

    showLessonModal.value = false
    await loadSections()
  } catch {
    toast.error('Không thể lưu bài học.')
  } finally {
    saving.value = false
    uploadProgress.value = 0
    uploadLabel.value = ''
  }
}

function uploadWithProgress(url: string, body: FormData): Promise<void> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', url)
    xhr.setRequestHeader('Authorization', `Bearer ${auth.token}`)
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        uploadProgress.value = Math.round((e.loaded / e.total) * 100)
      }
    })
    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        uploadProgress.value = 100
        resolve()
      } else {
        reject(new Error(`Upload failed: ${xhr.status}`))
      }
    })
    xhr.addEventListener('error', () => reject(new Error('Network error during upload')))
    xhr.send(body)
  })
}

async function deleteLesson(lesson: any) {
  if (!confirm(`Xóa bài học "${lesson.title}"?`)) return
  try {
    await $fetch(`/api/courses/${props.courseId}/lessons/${lesson.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    await loadSections()
  } catch {
    toast.error('Lỗi khi xóa bài học.')
  }
}

async function moveSection(section: any, direction: 'up' | 'down') {
  const index = sections.value.findIndex(s => s.id === section.id)
  if (direction === 'up' && index === 0) return
  if (direction === 'down' && index === sections.value.length - 1) return
  const neighbor = direction === 'up' ? sections.value[index - 1] : sections.value[index + 1]
  try {
    await Promise.all([
      $fetch(`/api/sections/${section.id}`, { method: 'PUT', body: { order: neighbor.order || 0 }, headers: { Authorization: `Bearer ${auth.token}` } }),
      $fetch(`/api/sections/${neighbor.id}`, { method: 'PUT', body: { order: section.order || 0 }, headers: { Authorization: `Bearer ${auth.token}` } }),
    ])
    await loadSections()
  } catch {
    console.error('Reorder failed')
  }
}

async function moveLesson(section: any, lesson: any, direction: 'up' | 'down') {
  const lessons = section.lessons || []
  const index = lessons.findIndex((l: any) => l.id === lesson.id)
  if (direction === 'up' && index === 0) return
  if (direction === 'down' && index === lessons.length - 1) return
  const neighbor = direction === 'up' ? lessons[index - 1] : lessons[index + 1]
  try {
    await Promise.all([
      $fetch(`/api/courses/${props.courseId}/lessons/${lesson.id}`, { method: 'PUT', body: { order: neighbor.order || 0 }, headers: { Authorization: `Bearer ${auth.token}` } }),
      $fetch(`/api/courses/${props.courseId}/lessons/${neighbor.id}`, { method: 'PUT', body: { order: lesson.order || 0 }, headers: { Authorization: `Bearer ${auth.token}` } }),
    ])
    await loadSections()
  } catch {
    console.error('Lesson reorder failed')
  }
}

defineExpose({ loadSections })
</script>

<template>
  <section class="studio-curriculum-panel">
    <!-- Studio Toolbar Header -->
    <div class="studio-panel-toolbar">
      <div class="toolbar-left">
        <span class="toolbar-kicker">Cấu trúc giáo trình</span>
        <h3 class="toolbar-title">Chương trình đào tạo</h3>
      </div>
      
      <button class="add-section-action-btn" type="button" @click="handleAddSection">
        <i class="pi pi-folder-plus" style="font-size:1.0rem" />
        <span>Tạo Chương mới</span>
      </button>
    </div>

    <!-- Content Area sections list -->
    <div class="studio-panel-content">
      <!-- Loading Skeleton -->
      <template v-if="loading">
        <div v-for="i in 3" :key="i" class="skeleton-section-loader"></div>
      </template>

      <!-- Empty State Illustration -->
      <div v-else-if="sections.length === 0" class="curriculum-empty-state">
        <div class="empty-state-icon-box">
          <i class="pi pi-clone" style="font-size:2.0rem" />
        </div>
        <h3 class="empty-state-title">Chưa có nội dung giáo trình</h3>
        <p class="empty-state-desc">Hãy thiết lập chương học đầu tiên và tải lên các bài giảng để bắt đầu truyền tải tri thức cho học viên.</p>
        <button class="empty-state-btn" type="button" @click="handleAddSection">
          <i class="pi pi-folder-plus" style="font-size:0.9375rem" />
          <span>Tạo Chương học đầu tiên</span>
        </button>
      </div>

      <!-- Recursive Chapters List -->
      <template v-else>
        <SectionBlock
          v-for="(section, index) in sections"
          :key="section.id"
          :section="section"
          :course-id="courseId"
          :index="index"
          :is-first="index === 0"
          :is-last="index === sections.length - 1"
          @edit-section="handleEditSection"
          @delete-section="deleteSection"
          @move-section-up="moveSection($event, 'up')"
          @move-section-down="moveSection($event, 'down')"
          @add-lesson="handleAddLesson"
          @edit-lesson="handleEditLesson"
          @delete-lesson="deleteLesson"
          @upload-video="emit('uploadVideo', $event)"
          @move-lesson-up="moveLesson"
          @move-lesson-down="moveLesson"
          @view-submissions="openSubmissions"
        />
      </template>
    </div>
  </section>

  <!-- Section Dialog Modal (Portal to Body) -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="showSectionModal" class="studio-modal-backdrop" @click.self="showSectionModal = false">
        <div class="studio-modal-card">
          <div class="modal-header">
            <div>
              <span class="modal-subtitle-tag">{{ editingSection ? 'Chỉnh sửa chương học' : 'Tạo mới chương học' }}</span>
              <h3 class="modal-title-text">{{ editingSection ? 'Cập nhật tiêu đề chương' : 'Chương học giảng dạy mới' }}</h3>
            </div>
            <button class="modal-close-x-btn" type="button" @click="showSectionModal = false">✕</button>
          </div>

          <form @submit.prevent="saveSection" class="modal-form-wrapper">
            <div class="modal-form-body">
              <div class="form-field-group">
                <label class="custom-label">
                  <span>Tiêu đề Chương học</span>
                  <span class="required-indicator">*</span>
                </label>
                <input 
                  v-model="sectionForm.title" 
                  type="text" 
                  class="custom-input"
                  placeholder="Ví dụ: Chương 1: Giới thiệu và thiết lập môi trường phát triển" 
                  required
                />
              </div>
              
              <div class="form-field-group">
                <label class="custom-label">Mô tả ngắn khái quát (Không bắt buộc)</label>
                <textarea 
                  v-model="sectionForm.description" 
                  rows="3" 
                  class="custom-textarea" 
                  placeholder="Mô tả tóm tắt nội dung trọng tâm của chương học này..."
                ></textarea>
              </div>
            </div>

            <div class="modal-footer-ops">
              <button class="modal-secondary-action" type="button" @click="showSectionModal = false">Hủy bỏ</button>
              <button class="modal-primary-action" type="submit" :disabled="saving">
                {{ saving ? 'Đang lưu...' : (editingSection ? 'Cập nhật Chương' : 'Tạo Chương học') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Lesson Builder Dialog Modal Component -->
  <LessonFormModal
    :show="showLessonModal"
    :lesson="editingLesson"
    :saving="saving"
    :upload-progress="uploadProgress"
    :upload-label="uploadLabel"
    @close="showLessonModal = false"
    @save="saveLesson"
  />

  <!-- Upload progress overlay -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="uploadProgress > 0 && uploadProgress < 100" class="upload-overlay">
        <div class="upload-card">
          <div class="upload-card-icon">
            <span class="material-symbols-outlined">upload_file</span>
          </div>
          <p class="upload-card-label">{{ uploadLabel }}</p>
          <div class="upload-progress-track">
            <div class="upload-progress-fill" :style="{ width: uploadProgress + '%' }" />
          </div>
          <p class="upload-card-pct">{{ uploadProgress }}%</p>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Homework Submissions List Dialog Modal -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="showSubmissionsModal" class="studio-modal-backdrop" @click.self="showSubmissionsModal = false">
        <div class="studio-modal-card is-wide">
          <div class="modal-header">
            <div>
              <span class="modal-subtitle-tag">Yêu cầu bài tập về nhà</span>
              <h3 class="modal-title-text">{{ submissionsLesson?.title }}</h3>
            </div>
            <button class="modal-close-x-btn" type="button" @click="showSubmissionsModal = false">✕</button>
          </div>

          <div class="submissions-list-body">
            <!-- Loading indicator -->
            <div v-if="loadingSubmissions" class="submissions-loading-box">
              <RefreshCw class="spin-icon" :size="24" />
              <p>Đang tải bài nộp của học viên...</p>
            </div>

            <!-- Empty status -->
            <div v-else-if="submissions.length === 0" class="submissions-empty-box">
              <i class="pi pi-inbox" style="font-size:2.25rem" />
              <p>Chưa có học viên nào nộp bài giải cho yêu cầu này.</p>
            </div>

            <!-- Submissions Grid Table -->
            <div v-else class="submissions-table-wrap">
              <table class="submissions-table">
                <thead>
                  <tr>
                    <th>Học viên</th>
                    <th>Thời gian nộp bài</th>
                    <th>Bài nộp đính kèm</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sub in submissions" :key="sub.id">
                    <td>
                      <div class="student-profile-info">
                        <div class="student-avatar-letter">
                          {{ (sub.user?.name || sub.student?.name || '?').slice(0, 2).toUpperCase() }}
                        </div>
                        <div>
                          <div class="student-name">{{ sub.user?.name || sub.student?.name || 'Chưa rõ tên' }}</div>
                          <div class="student-email">{{ sub.user?.email || sub.student?.email || '' }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="submitted-time-cell">
                      {{ sub.submitted_at ? new Date(sub.submitted_at).toLocaleString('vi-VN') : '—' }}
                    </td>
                    <td>
                      <a
                        v-if="sub.file_url || sub.attachment_url"
                        :href="sub.file_url || sub.attachment_url"
                        target="_blank"
                        rel="noopener"
                        class="download-file-btn"
                      >
                        <i class="pi pi-download" style="font-size:0.875rem" />
                        <span>Tải bài nộp</span>
                      </a>
                      <span v-else class="no-file-text">Không đính kèm file</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer-ops">
            <button class="modal-secondary-action" type="button" @click="showSubmissionsModal = false">Đóng hộp thoại</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.studio-curriculum-panel {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

/* Toolbar header style */
.studio-panel-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 24px 32px;
  background: linear-gradient(135deg, var(--surface-strong), rgba(var(--surface-strong-rgb), 0.7));
  border-bottom: 1px solid var(--line);
}

.toolbar-left {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toolbar-kicker {
  font-size: 0.7rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--green);
}

.toolbar-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 850;
  letter-spacing: -0.02em;
  color: var(--text);
}

.add-section-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
  border: none;
  background: var(--green);
  color: #fff;
  font-size: 0.84rem;
  font-weight: 750;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(29, 158, 117, 0.2);
  transition: all 200ms;
}

.add-section-action-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(29, 158, 117, 0.3);
  background: var(--green-deep);
}

/* Content list */
.studio-panel-content {
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Empty state design */
.curriculum-empty-state {
  text-align: center;
  padding: 80px 24px;
}

.empty-state-icon-box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  color: var(--muted);
  margin-bottom: 20px;
}

.empty-state-title {
  margin: 0 0 8px;
  font-size: 1.15rem;
  font-weight: 850;
  color: var(--text);
}

.empty-state-desc {
  margin: 0 0 24px;
  font-size: 0.86rem;
  color: var(--muted);
  max-width: 420px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.6;
}

.empty-state-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 12px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--text-secondary);
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
}

.empty-state-btn:hover {
  background: var(--surface);
  color: var(--text);
  border-color: var(--green);
}

/* Loading skeletal loaders */
.skeleton-section-loader {
  height: 180px;
  border-radius: 16px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* ── MODALS STYLING ── */
.studio-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.studio-modal-card {
  width: 100%;
  max-width: 500px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
  animation: modal-enter 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.studio-modal-card.is-wide {
  max-width: 720px;
}

@keyframes modal-enter {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
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

.modal-close-x-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
  transition: color 150ms;
}

.modal-close-x-btn:hover {
  color: var(--text);
}

.modal-form-wrapper {
  display: flex;
  flex-direction: column;
}

.modal-form-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-field-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.custom-label {
  font-size: 0.84rem;
  font-weight: 700;
  color: var(--text-secondary);
}

.required-indicator {
  color: #EF4444;
  margin-left: 4px;
}

.custom-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--line);
  background: var(--surface);
  color: var(--text);
  outline: none;
  font-size: 0.88rem;
  transition: all 0.2s ease;
}

.custom-input:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.08);
}

.custom-textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--line);
  background: var(--surface);
  color: var(--text);
  outline: none;
  font-size: 0.88rem;
  transition: all 0.2s ease;
  resize: vertical;
}

.custom-textarea:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.08);
}

.modal-footer-ops {
  padding: 16px 24px;
  border-top: 1px solid var(--line);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.modal-secondary-action {
  display: inline-flex;
  align-items: center;
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
}

.modal-secondary-action:hover {
  background: var(--surface);
  color: var(--text);
}

.modal-primary-action {
  display: inline-flex;
  align-items: center;
  padding: 10px 20px;
  border-radius: 10px;
  border: none;
  background: var(--green);
  color: #fff;
  font-size: 0.84rem;
  font-weight: 750;
  cursor: pointer;
  transition: background 150ms;
}

.modal-primary-action:hover:not(:disabled) {
  background: var(--green-deep);
}

.modal-primary-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ── SUBMISSIONS MODAL DETAILS ── */
.submissions-list-body {
  padding: 24px;
  flex: 1;
  overflow-y: auto;
  max-height: 55vh;
}

.submissions-loading-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 0;
  color: var(--muted);
  gap: 12px;
}

.spin-icon {
  animation: spin 1s linear infinite;
}

.submissions-empty-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  color: var(--muted);
  gap: 12px;
}

.submissions-table-wrap {
  border: 1px solid var(--line);
  border-radius: 14px;
  overflow: hidden;
}

.submissions-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.86rem;
  text-align: left;
}

.submissions-table th {
  background: var(--surface-strong);
  padding: 12px 16px;
  font-weight: 750;
  color: var(--text-secondary);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  border-bottom: 1px solid var(--line);
}

.submissions-table td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--line);
  color: var(--text);
  vertical-align: middle;
}

.submissions-table tr:last-child td {
  border-bottom: none;
}

.student-profile-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.student-avatar-letter {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--green-soft);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.75rem;
}

.student-name {
  font-weight: 750;
  color: var(--text);
}

.student-email {
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 500;
}

.submitted-time-cell {
  color: var(--text-secondary);
  font-weight: 500;
}

.download-file-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid rgba(29, 158, 117, 0.2);
  background: var(--green-soft);
  color: var(--green);
  font-size: 0.76rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 150ms;
}

.download-file-btn:hover {
  background: var(--green);
  color: #fff;
  border-color: var(--green);
}

.no-file-text {
  font-size: 0.76rem;
  color: var(--muted);
  font-weight: 600;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Upload progress overlay ── */
.upload-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-card {
  background: #fff;
  border-radius: 20px;
  padding: 32px 40px;
  min-width: 320px;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.22);
}

.upload-card-icon .material-symbols-outlined {
  font-size: 44px;
  color: var(--green);
  animation: bounce-upload 1s ease-in-out infinite alternate;
}

@keyframes bounce-upload {
  from { transform: translateY(0); }
  to   { transform: translateY(-6px); }
}

.upload-card-label {
  font-size: 0.95rem;
  font-weight: 700;
  color: #111;
  margin: 0;
  text-align: center;
}

.upload-progress-track {
  width: 100%;
  height: 8px;
  background: rgba(29, 158, 117, 0.15);
  border-radius: 999px;
  overflow: hidden;
}

.upload-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--green) 0%, #34d39b 100%);
  border-radius: 999px;
  transition: width 0.2s ease;
}

.upload-card-pct {
  font-size: 1.5rem;
  font-weight: 900;
  color: var(--green);
  margin: 0;
  letter-spacing: -0.04em;
}
</style>
