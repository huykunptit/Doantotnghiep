<template>
  <div class="section-manager w-full">
    <!-- Header Controls -->
    <div class="flex justify-between items-center mb-8 pb-4 border-b border-surface-dim/30">
      <h3 class="title text-xl font-bold font-headline text-on-surface">Cấu trúc Giáo trình</h3>
      <button @click="showAddSection = true" class="flex items-center gap-2 px-5 py-2.5 bg-surface-low text-primary text-sm font-bold rounded-lg hover:bg-surface-high transition-colors">
        <span class="material-symbols-outlined text-[18px]">add_circle</span>
        Chương mới
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-16 text-outline">
      <span class="material-symbols-outlined animate-spin text-4xl mb-4">settings</span>
      <p class="text-sm font-medium">Đang tải biểu đồ giáo trình...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="sections.length === 0" class="py-16 text-center bg-surface-low rounded-2xl border-2 border-dashed border-surface-dim mt-4">
      <span class="material-symbols-outlined text-5xl text-outline mb-4">account_tree</span>
      <p class="text-on-surface-variant font-medium">Chưa có nội dung nào. Cùng xây dựng những chương học đầu tiên nhé!</p>
      <button @click="showAddSection = true" class="mt-6 px-6 py-2 cta-gradient text-white font-bold text-sm rounded-lg shadow-md hover:shadow-lg transition-all">
        Bắt đầu tạo Chương
      </button>
    </div>

    <!-- Sections List -->
    <div v-else class="space-y-8">
      <div v-for="(section, index) in sections" :key="section.id" class="group/section">
        
        <!-- Section Header Bar -->
        <div class="flex items-center gap-4 p-4 mb-4 bg-surface-low rounded-xl group-hover/section:bg-surface-high transition-all border border-surface-dim/0 group-hover/section:border-surface-dim">
          <span class="material-symbols-outlined text-outline cursor-grab active:cursor-grabbing hover:text-primary transition-colors">drag_indicator</span>
          <div class="flex-1 min-w-0">
            <span class="text-[10px] font-bold text-primary uppercase tracking-widest bg-primary/10 px-2 py-0.5 rounded">Chương {{ index + 1 }}</span>
            <h4 class="font-headline font-bold text-lg text-on-surface mt-1 truncate">{{ section.title }}</h4>
            <p v-if="section.description" class="text-xs text-on-surface-variant truncate">{{ section.description }}</p>
            <div class="flex items-center gap-4 mt-2 text-xs font-medium text-outline">
              <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">format_list_bulleted</span> {{ section.lessons?.length || 0 }} Bài học</span>
              <span v-if="section.total_duration" class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span> {{ formatDuration(section.total_duration) }}</span>
            </div>
          </div>
          <div class="flex items-center gap-2 opacity-0 group-hover/section:opacity-100 transition-opacity">
            <button @click="editSection(section)" class="p-2 hover:bg-surface-lowest rounded-lg text-outline transition-colors shadow-sm" title="Sửa tên chương">
              <span class="material-symbols-outlined text-[18px]">edit</span>
            </button>
            <button @click="deleteSection(section.id)" class="p-2 hover:bg-error-container rounded-lg text-error transition-colors shadow-sm" title="Xóa chương này">
              <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>
        </div>

        <!-- Lessons in Section -->
        <div class="pl-8 md:pl-12 space-y-3 relative before:absolute before:left-6 before:top-0 before:bottom-0 before:w-[2px] before:bg-surface-high">
          
          <div v-for="lesson in section.lessons" :key="lesson.id" class="bg-surface-lowest p-4 rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col md:flex-row md:items-center gap-4 border border-surface-dim/10 hover:border-primary/30 group/lesson relative z-10">
            <div class="flex items-center gap-4 w-full md:w-auto flex-1">
              <span class="material-symbols-outlined text-outline-variant cursor-grab active:cursor-grabbing hover:text-primary transition-colors">menu</span>
              
              <!-- Video / Media Type Icon -->
              <div class="w-10 h-10 shrink-0 rounded-lg bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[20px]" style="font-variation-settings: 'FILL' 1;">{{ lesson.video_url || lesson.video_status === 'ready' ? 'play_circle' : 'article' }}</span>
              </div>
              
              <div class="flex-1 min-w-0">
                <p class="font-bold text-sm text-on-surface truncate">{{ lesson.title }}</p>
                <div class="flex items-center gap-3 mt-1">
                   <span v-if="lesson.is_preview" class="text-[9px] bg-secondary-container/30 text-secondary px-1.5 py-0.5 rounded font-bold uppercase tracking-widest">Preview</span>
                   <span v-if="lesson.duration" class="text-xs text-on-surface-variant font-medium">{{ formatDuration(lesson.duration) }}</span>
                </div>
              </div>
            </div>
            
            <!-- Video Status -->
            <div class="flex items-center shrink-0">
               <span v-if="lesson.video_status === 'ready' || lesson.video_url" class="text-[10px] bg-secondary/10 text-secondary px-2 py-1 rounded font-bold uppercase tracking-widest flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">check_circle</span> Video</span>
               <span v-else-if="lesson.video_status === 'processing'" class="text-[10px] bg-amber-500/10 text-amber-600 px-2 py-1 rounded font-bold uppercase tracking-widest flex items-center gap-1"><span class="material-symbols-outlined text-[14px] animate-spin">refresh</span> Xử lý</span>
               <span v-else-if="lesson.video_status === 'failed'" class="text-[10px] bg-error/10 text-error px-2 py-1 rounded font-bold uppercase tracking-widest flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">error</span> Lỗi</span>
               <span v-else class="text-[10px] bg-surface-high text-outline px-2 py-1 rounded font-bold uppercase tracking-widest flex items-center gap-1 hidden sm:flex"><span class="material-symbols-outlined text-[14px]">hourglass_empty</span> Trống</span>
            </div>

            <!-- Actions Panel -->
            <div class="flex items-center justify-end gap-2 w-full md:w-auto pt-4 md:pt-0 border-t md:border-t-0 border-surface-dim opacity-100 md:opacity-0 md:group-hover/lesson:opacity-100 transition-opacity">
              <button @click="$emit('edit-lesson', lesson)" class="px-2.5 py-1.5 text-[11px] font-bold text-on-surface-variant bg-surface-low hover:bg-surface-high hover:text-primary rounded shadow-sm transition-all flex items-center gap-1" title="Chỉnh sửa thông tin">
                <span class="material-symbols-outlined text-[14px]">edit</span>
              </button>
              <button @click="$emit('upload-video', lesson)" class="px-3 py-1.5 text-[11px] font-bold text-white cta-gradient rounded shadow-sm hover:shadow-md transition-all flex items-center gap-1" title="Tải Video Giáo trình">
                <span class="material-symbols-outlined text-[14px]">video_library</span> Video
              </button>
              <NuxtLink :to="`/instructor/courses/${courseId}/lessons/${lesson.id}/quiz`" class="px-2.5 py-1.5 text-[11px] font-bold text-on-surface-variant bg-surface-low hover:bg-surface-high hover:text-primary rounded shadow-sm transition-all flex items-center gap-1" title="Quản lý Quiz (Bài Test)">
                <span class="material-symbols-outlined text-[14px]">quiz</span>
              </NuxtLink>
              <NuxtLink :to="`/instructor/courses/${courseId}/lessons/${lesson.id}/attachments`" class="px-2.5 py-1.5 text-[11px] font-bold text-on-surface-variant bg-surface-low hover:bg-surface-high hover:text-primary rounded shadow-sm transition-all flex items-center gap-1" title="Thêm tài liệu (PDF, File đính kèm)">
                <span class="material-symbols-outlined text-[14px]">attach_file</span>
              </NuxtLink>
              <button @click="$emit('delete-lesson', lesson)" class="px-2.5 py-1.5 text-[11px] font-bold text-error bg-error-container/20 hover:bg-error-container hover:text-error rounded shadow-sm transition-all flex items-center gap-1 group-hover/lesson:opacity-100" title="Xóa bỏ">
                <span class="material-symbols-outlined text-[14px]">delete</span>
              </button>
            </div>
          </div>

          <!-- Add Content Area (Drop zone mock/Add button) -->
          <div class="border-2 border-dashed border-surface-dim/50 rounded-xl py-4 flex items-center justify-center text-outline hover:border-primary/50 hover:bg-primary/5 hover:text-primary transition-colors cursor-pointer group/add relative z-10" @click="$emit('add-lesson', section)">
            <span class="text-xs font-bold uppercase tracking-widest flex items-center gap-2">
              <span class="material-symbols-outlined text-[18px]">add</span>
              Thêm bài học vào chương này
            </span>
          </div>

        </div>
      </div>
    </div>

    <!-- Add/Edit Section Modal Overlays (Using Teleport in case but here it's mounted inside) -->
    <div v-if="showAddSection || editingSection" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @click.self="closeModal">
      <div class="w-full max-w-lg rounded-[2rem] bg-surface-lowest p-8 shadow-ambient modal-bounce border border-surface-dim">
        <div class="mb-6 flex items-center justify-between border-b border-surface-dim/30 pb-4">
           <h3 class="font-headline text-xl font-bold text-on-surface flex items-center gap-2">
              <span class="material-symbols-outlined text-primary">{{ editingSection ? 'edit' : 'view_cozy' }}</span>
              {{ editingSection ? 'Chỉnh sửa Tên Chương' : 'Tạo Chương Mới' }}
           </h3>
           <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="closeModal">
              <span class="material-symbols-outlined text-[20px]">close</span>
           </button>
        </div>

        <form @submit.prevent="saveSection" class="space-y-5">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Tiêu đề (Tên chương) <span class="text-error">*</span></label>
            <input v-model="sectionForm.title" type="text" required class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="VD: Chương 1: Nhập môn kiến trúc tĩnh">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Mô tả ngắn gọn (Tùy chọn)</label>
            <textarea v-model="sectionForm.description" rows="3" class="w-full flex-1 rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="Tóm tắt về mục đích của chương này..."></textarea>
          </div>
          
          <div class="sticky bottom-0 -mx-8 -mb-8 mt-6 flex flex-col gap-3 border-t border-surface-dim/30 bg-surface-lowest/95 px-8 py-5 backdrop-blur sm:flex-row sm:justify-end">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-error/30 bg-error/10 px-6 py-3 text-sm font-bold text-error hover:bg-error hover:text-white hover:shadow-lg transition-all cursor-pointer"
              @click="closeModal"
            >
              <span class="material-symbols-outlined text-[18px]">close</span>
              Hủy bỏ
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              <span v-else class="material-symbols-outlined text-[18px]">task_alt</span>
              {{ editingSection ? 'Lưu thay đổi' : 'Lưu chương' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

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
    const response = await $fetch<{ data: any[] }>(`/api/courses/${props.courseId}/sections`, {
      headers: { Authorization: `Bearer ${auth.token}` }
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
  if (!confirm('Bạn có chắc chắn muốn xóa chương này cùng tất cả bài học bên trong?')) return
  const auth = useAuthStore()
  try {
    await $fetch(`/api/sections/${sectionId}`, { 
      method: 'DELETE', 
      headers: { Authorization: `Bearer ${auth.token}` } 
    })
    await loadSections()
  } catch (error: any) {
    alert(error?.data?.message || 'Có lỗi khi xóa chương này.')
  }
}

const closeModal = () => {
  showAddSection.value = false
  editingSection.value = null
  sectionForm.value = { title: '', description: '' }
}

const formatDuration = (seconds: number) => {
  if (!seconds) return ''
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

defineExpose({ loadSections })
</script>

<style scoped>
.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
