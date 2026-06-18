<script setup lang="ts">
import { ref } from 'vue'
import { ArrowLeft } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

const route = useRoute()
const courseId = Number(route.params.id)
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

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
  saving.value = true
  try {
    if (editingLesson.value) {
      await useApi(`/courses/${courseId}/lessons/${editingLesson.value.id}`, {
        method: 'PUT',
        body: lessonForm.value,
        headers: authHeaders(),
      })
    } else {
      await useApi(`/courses/${courseId}/lessons`, {
        method: 'POST',
        body: { ...lessonForm.value, order: 0 },
        headers: authHeaders(),
      })
    }
    showLessonModal.value = false
    await sectionManagerRef.value?.loadSections?.()
  } catch {}
  finally { saving.value = false }
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
    headers: authHeaders(),
  })
  await sectionManagerRef.value?.loadSections?.()
}
</script>

<template>
  <AdminWorkspaceShell
    title="Curriculum khoá học"
    description="Xem và rà soát cấu trúc section, bài học và tài nguyên của khoá học."
    :breadcrumb="['Trang chủ', 'Khoá học', 'Chi tiết', 'Curriculum']"
  >
    <template #actions>
      <NuxtLink :to="`/admin/courses/${courseId}`" class="crud-secondary-btn">
        <ArrowLeft :size="15" :stroke-width="2" />
        Quay lại chi tiết
      </NuxtLink>
    </template>

    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar" style="margin-bottom: 20px;">
        <div>
          <p class="section-kicker">Mã khoá học #{{ courseId }}</p>
          <h3>Quản lý nội dung / review curriculum</h3>
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
    </section>

    <!-- Lesson create/edit modal -->
    <Teleport to="body">
      <div v-if="showLessonModal" class="modal-overlay" @click.self="showLessonModal = false">
        <div class="modal-box dashboard-card">
          <div class="modal-head">
            <h3>{{ editingLesson ? 'Sửa bài học' : 'Thêm bài học' }}</h3>
            <button type="button" class="crud-secondary-btn modal-close-btn" @click="showLessonModal = false">✕</button>
          </div>

          <div class="crud-form-grid">
            <label class="crud-field crud-field-full">
              <span>Tiêu đề bài học <span style="color:#ef4444">*</span></span>
              <input v-model="lessonForm.title" type="text" placeholder="Nhập tiêu đề bài học">
            </label>
            <label class="crud-field crud-field-full">
              <span>Mô tả</span>
              <textarea v-model="lessonForm.description" rows="4" placeholder="Nhập mô tả..."></textarea>
            </label>
            <label class="crud-field crud-field-full checkbox-field">
              <input v-model="lessonForm.is_preview" type="checkbox">
              <span>Cho phép xem thử (preview)</span>
            </label>
          </div>

          <div class="modal-actions">
            <button class="crud-primary-btn" type="button" :disabled="saving || !lessonForm.title.trim()" @click="saveLesson">
              {{ saving ? 'Đang lưu...' : (editingLesson ? 'Cập nhật' : 'Thêm bài học') }}
            </button>
            <button class="crud-secondary-btn" type="button" @click="showLessonModal = false">Huỷ</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Video upload modal -->
    <Teleport to="body">
      <div v-if="showUploadModal" class="modal-overlay" @click.self="showUploadModal = false">
        <div class="modal-box modal-box--wide dashboard-card">
          <div class="modal-head">
            <h3>Upload video bài học</h3>
            <button type="button" class="crud-secondary-btn modal-close-btn" @click="showUploadModal = false">✕</button>
          </div>
          <VideoUploader
            v-if="uploadingLesson"
            :course-id="courseId"
            :lesson-id="uploadingLesson.id"
            :existing-video-url="uploadingLesson.video_url"
            @uploaded="onUploaded"
          />
        </div>
      </div>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}

.modal-box {
  width: 100%;
  max-width: 540px;
  padding: 28px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-box--wide { max-width: 860px; }

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.modal-head h3 { margin: 0; font-size: 1.1rem; font-weight: 700; }

.modal-close-btn {
  padding: 6px 12px;
  font-size: 0.8rem;
  height: auto;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.checkbox-field {
  flex-direction: row !important;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.checkbox-field input[type="checkbox"] {
  width: 16px; height: 16px;
  accent-color: var(--green);
  flex-shrink: 0;
}
</style>
