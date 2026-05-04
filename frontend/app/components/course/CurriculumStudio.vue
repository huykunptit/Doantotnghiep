<script setup lang="ts">
import { ref, onMounted } from 'vue'
import SectionBlock from './SectionBlock.vue'
import LessonFormModal from './LessonFormModal.vue'
import { useAuthStore } from '~/stores/auth'

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
    alert('Không thể lưu chương học.')
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
    alert('Lỗi khi xóa chương.')
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
      await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/upload-video`, {
        method: 'POST',
        body: videoPayload,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
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
      if (formData.scorm_file) payload.append('scorm_file', formData.scorm_file)
      await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/scorm-package`, {
        method: 'POST',
        body: payload,
        headers: { Authorization: `Bearer ${auth.token}` },
      })
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
    alert('Không thể lưu bài học.')
  } finally {
    saving.value = false
  }
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
    alert('Lỗi khi xóa bài học.')
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
  <section class="dashboard-card crud-panel" style="gap:0; padding:0; overflow:hidden;">
    <!-- Studio Toolbar -->
    <div class="crud-toolbar" style="border-bottom:1px solid rgba(17,17,17,0.07); padding:18px 24px;">
      <div>
        <p class="section-kicker" style="margin:0 0 2px;">Cấu trúc Giáo trình</p>
        <p style="margin:0; font-size:0.85rem; color:var(--muted);">Quản lý chương học và bài giảng của khóa học.</p>
      </div>
      <button class="crud-primary-btn" style="display:flex;align-items:center;gap:8px;" @click="handleAddSection">
        <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>
        Tạo Chương mới
      </button>
    </div>

    <!-- Content Area -->
    <div style="padding:20px 24px; display:grid; gap:16px;">
      <!-- Loading -->
      <template v-if="loading">
        <div v-for="i in 2" :key="i" style="height:180px; border-radius:16px; background:rgba(17,17,17,0.05); animation:pulse 1.5s ease-in-out infinite;"></div>
      </template>

      <!-- Empty State -->
      <div v-else-if="sections.length === 0" style="padding:60px 24px; text-align:center;">
        <div style="width:64px;height:64px;border-radius:20px;background:rgba(17,17,17,0.05);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--muted);">
          <span class="material-symbols-outlined" style="font-size:32px;">inventory_2</span>
        </div>
        <h3 style="margin:0 0 8px; font-size:1.1rem; font-weight:800;">Giáo trình đang trống</h3>
        <p style="margin:0 0 20px; color:var(--muted); font-size:0.9rem; max-width:380px; margin-left:auto; margin-right:auto;">Hãy bắt đầu bằng việc tạo chương học đầu tiên để hướng dẫn học viên qua lộ trình của bạn.</p>
        <button class="crud-primary-btn" @click="handleAddSection">Bắt đầu ngay</button>
      </div>

      <!-- Sections List -->
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
        />
      </template>
    </div>
  </section>

  <!-- Section Modal -->
  <Teleport to="body">
    <div v-if="showSectionModal" class="crud-modal-backdrop" @click.self="showSectionModal = false">
      <div class="crud-modal">
        <div class="crud-modal-head">
          <div>
            <p class="section-kicker">{{ editingSection ? 'Chỉnh sửa' : 'Tạo mới' }}</p>
            <h3>{{ editingSection ? 'Sửa tên Chương' : 'Chương học mới' }}</h3>
          </div>
          <button class="topbar-ghost" type="button" @click="showSectionModal = false">✕</button>
        </div>

        <form @submit.prevent="saveSection">
          <div class="crud-form-grid">
            <div class="crud-field crud-field-full">
              <span>Tiêu đề Chương <span style="color:#ae3d37;">*</span></span>
              <input v-model="sectionForm.title" type="text" placeholder="VD: Chương 1: Kiến thức nền tảng" required>
            </div>
            <div class="crud-field crud-field-full">
              <span>Mô tả ngắn <span style="color:var(--muted); font-weight:400;">(Tùy chọn)</span></span>
              <textarea v-model="sectionForm.description" rows="3" class="crud-textarea" placeholder="Mô tả nội dung tổng quan của chương học này..."></textarea>
            </div>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="showSectionModal = false">Hủy bỏ</button>
            <button class="crud-primary-btn" type="submit" :disabled="saving">
              {{ saving ? 'Đang lưu...' : (editingSection ? 'Cập nhật' : 'Tạo chương') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- Lesson Modal -->
  <LessonFormModal
    :show="showLessonModal"
    :lesson="editingLesson"
    :saving="saving"
    @close="showLessonModal = false"
    @save="saveLesson"
  />
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>
