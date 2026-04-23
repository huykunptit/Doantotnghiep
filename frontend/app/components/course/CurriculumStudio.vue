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

// Modal States
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
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    sections.value = res.data || []
  } catch (error) {
    console.error('Failed to load sections:', error)
  } finally {
    loading.value = false
  }
}

// Section Actions
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
        headers: { Authorization: `Bearer ${auth.token}` }
      })
    } else {
      await $fetch(`/api/courses/${props.courseId}/sections`, {
        method: 'POST',
        body: sectionForm.value,
        headers: { Authorization: `Bearer ${auth.token}` }
      })
    }
    showSectionModal.value = false
    await loadSections()
  } catch (error) {
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
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    await loadSections()
  } catch (error) {
    alert('Lỗi khi xóa chương.')
  }
}

// Lesson Actions
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
        headers: { Authorization: `Bearer ${auth.token}` }
      })
    } else {
      const response = await $fetch<any>(`/api/courses/${props.courseId}/lessons`, {
        method: 'POST',
        body: { ...basePayload, section_id: currentSectionForLesson.value.id, order: 0 },
        headers: { Authorization: `Bearer ${auth.token}` }
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
        headers: { Authorization: `Bearer ${auth.token}` }
      })
    }

    if (formData.type === 'assignment') {
      await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/assignment`, {
        method: 'POST',
        body: formData.assignment,
        headers: { Authorization: `Bearer ${auth.token}` }
      })
    }

    if (formData.type === 'virtual_class') {
      await $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/virtual-class`, {
        method: 'POST',
        body: formData.virtual_class,
        headers: { Authorization: `Bearer ${auth.token}` }
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
        headers: { Authorization: `Bearer ${auth.token}` }
      })
    }

    if (Array.isArray(formData.attachments) && formData.attachments.length > 0) {
      await Promise.all(formData.attachments.map((file: File) => {
        const payload = new FormData()
        payload.append('file', file)
        return $fetch(`/api/courses/${props.courseId}/lessons/${lessonId}/attachments`, {
          method: 'POST',
          body: payload,
          headers: { Authorization: `Bearer ${auth.token}` }
        })
      }))
    }

    showLessonModal.value = false
    await loadSections()
  } catch (error) {
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
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    await loadSections()
  } catch (error) {
    alert('Lỗi khi xóa bài học.')
  }
}

// Reordering Logic
async function moveSection(section: any, direction: 'up' | 'down') {
  const index = sections.value.findIndex(s => s.id === section.id)
  if (direction === 'up' && index === 0) return
  if (direction === 'down' && index === sections.value.length - 1) return

  const neighbor = direction === 'up' ? sections.value[index - 1] : sections.value[index + 1]
  
  try {
    // Simple swap order simulation (if API supports order update)
    // Here we just notify the API of new order or swap locally for now
    const newOrderA = neighbor.order || 0
    const newOrderB = section.order || 0

    await Promise.all([
      $fetch(`/api/sections/${section.id}`, { method: 'PUT', body: { order: newOrderA }, headers: { Authorization: `Bearer ${auth.token}` } }),
      $fetch(`/api/sections/${neighbor.id}`, { method: 'PUT', body: { order: newOrderB }, headers: { Authorization: `Bearer ${auth.token}` } })
    ])
    await loadSections()
  } catch (error) {
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
    const newOrderA = neighbor.order || 0
    const newOrderB = lesson.order || 0

    await Promise.all([
      $fetch(`/api/courses/${props.courseId}/lessons/${lesson.id}`, { method: 'PUT', body: { order: newOrderA }, headers: { Authorization: `Bearer ${auth.token}` } }),
      $fetch(`/api/courses/${props.courseId}/lessons/${neighbor.id}`, { method: 'PUT', body: { order: newOrderB }, headers: { Authorization: `Bearer ${auth.token}` } })
    ])
    await loadSections()
  } catch (error) {
    console.error('Lesson reorder failed')
  }
}

defineExpose({ loadSections })
</script>

<template>
  <div class="curriculum-studio space-y-12">
    <!-- Studio Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-3xl font-headline font-bold text-on-surface">Cấu trúc Giáo trình</h2>
        <p class="text-on-surface-variant text-sm mt-1">Quản lý và sắp xếp các bài giảng của bạn theo cách chuyên nghiệp nhất.</p>
      </div>
      <button 
        @click="handleAddSection"
        class="flex items-center gap-3 px-6 py-3 bg-surface-low text-primary rounded-[1.25rem] font-bold shadow-sm hover:bg-surface-high transition-all border border-primary/10"
      >
        <span class="material-symbols-outlined">add_circle</span>
        Tạo Chương mới
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-8">
      <div v-for="i in 2" :key="i" class="h-64 bg-surface-low animate-pulse rounded-[2.5rem]"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="sections.length === 0" class="py-24 text-center bg-surface-lowest rounded-[3rem] border-2 border-dashed border-surface-dim/40">
      <div class="w-20 h-20 bg-surface-low rounded-3xl flex items-center justify-center mx-auto mb-6 text-outline">
        <span class="material-symbols-outlined text-4xl">inventory_2</span>
      </div>
      <h3 class="text-2xl font-headline font-bold text-on-surface">Giáo trình đang trống</h3>
      <p class="text-on-surface-variant mt-2 max-w-sm mx-auto">Hãy bắt đầu bằng việc tạo chương học đầu tiên để hướng dẫn học viên qua lộ trình của bạn.</p>
      <button @click="handleAddSection" class="mt-8 px-8 py-3 cta-gradient text-white font-bold rounded-xl shadow-lg hover:scale-105 transition-all">
        Bắt đầu ngay
      </button>
    </div>

    <!-- Sections List -->
    <div v-else class="space-y-8">
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
    </div>

    <!-- Section Modal -->
    <Teleport to="body">
      <div v-if="showSectionModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-md" @click.self="showSectionModal = false">
        <div class="w-full max-w-lg rounded-[2.5rem] bg-surface-lowest p-8 shadow-2xl modal-bounce border border-white/20">
          <div class="mb-8 flex items-center justify-between border-b border-surface-dim/30 pb-6">
            <h3 class="font-headline text-2xl font-bold text-on-surface flex items-center gap-3">
              <span class="material-symbols-outlined text-primary">{{ editingSection ? 'edit' : 'view_cozy' }}</span>
              {{ editingSection ? 'Sửa Chương' : 'Chương Mới' }}
            </h3>
            <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="showSectionModal = false">
              <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>
          
          <form class="space-y-6" @submit.prevent="saveSection">
            <UiInput v-model="sectionForm.title" label="Tiêu đề Chương" placeholder="VD: Chương 1: Kiến thức nền tảng" required />
            <div class="space-y-2">
              <label class="block font-bold text-sm text-on-surface ml-1">Mô tả ngắn (Tùy chọn)</label>
              <textarea v-model="sectionForm.description" rows="3" class="w-full rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-4 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all"></textarea>
            </div>
            
            <div class="sticky bottom-0 -mx-8 -mb-8 mt-8 flex flex-col gap-3 border-t border-surface-dim/30 bg-surface-lowest/95 px-8 py-5 backdrop-blur sm:flex-row sm:justify-end">
              <UiButton type="button" variant="secondary" size="lg" @click="showSectionModal = false">
                <span class="material-symbols-outlined text-[18px]">close</span>
                Hủy bỏ
              </UiButton>
              <UiButton type="submit" size="lg" :loading="saving">
                <span v-if="!saving" class="material-symbols-outlined text-[18px]">task_alt</span>
                Lưu chương
              </UiButton>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Unified Lesson Modal -->
    <LessonFormModal 
      :show="showLessonModal" 
      :lesson="editingLesson" 
      :saving="saving"
      @close="showLessonModal = false"
      @save="saveLesson"
    />
  </div>
</template>

<style scoped>
.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
