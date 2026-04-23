<template>
  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
      <div>
        <NuxtLink :to="`/instructor/courses/${courseId}/curriculum`" class="text-sm text-on-surface-variant hover:text-primary">
          ← Quay lại Curriculum
        </NuxtLink>
        <h1 class="text-2xl font-bold text-on-surface mt-2">Tài liệu đính kèm</h1>
        <p class="text-on-surface-variant text-sm mt-1">Bài học: {{ lesson?.title }}</p>
      </div>
    </div>

    <!-- Upload Area -->
    <div class="card p-6 mb-8 border-2 border-dashed border-surface-dim/30 bg-surface-low text-center relative hover:border-primary transition-colors">
      <input type="file" ref="fileInput" @change="handleFileChange" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" :disabled="uploading">
      
      <div v-if="uploading" class="py-8">
        <svg class="animate-spin h-8 w-8 text-primary mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <p class="text-on-surface-variant font-medium">Đang tải lên tài liệu...</p>
      </div>
      <div v-else class="py-8 pointer-events-none">
        <svg class="w-12 h-12 text-outline mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
        <p class="text-on-surface font-medium mb-1">Kéo thả file vào đây hoặc click để chọn</p>
        <p class="text-sm text-on-surface-variant">Hỗ trợ PDF, DOCX, ZIP, PPTX (Tối đa 50MB)</p>
      </div>
    </div>

    <div v-if="loading" class="card p-6 text-center text-on-surface-variant animate-pulse">
      Đang tải tài liệu...
    </div>
    
    <!-- Attachments List -->
    <div v-else class="card overflow-hidden">
      <div class="px-6 py-4 border-b border-surface-dim/10 bg-surface-low">
        <h2 class="text-lg font-semibold text-on-surface">Tài liệu đã tải lên ({{ attachments.length }})</h2>
      </div>

      <div v-if="attachments.length === 0" class="p-8 text-center text-on-surface-variant">
        Chưa có tài liệu đính kèm nào.
      </div>

      <ul v-else class="divide-y divide-surface-dim/10">
        <li v-for="file in attachments" :key="file.id" class="p-5 flex items-center justify-between hover:bg-surface-low transition-colors">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
            </div>
            <div>
              <p class="text-sm font-medium text-on-surface line-clamp-1 truncate" style="max-width: 400px;">{{ file.original_name }}</p>
              <div class="flex items-center gap-3 text-xs text-on-surface-variant mt-1">
                <span>{{ file.file_size }}</span>
                <span>{{ formatDate(file.created_at) }}</span>
              </div>
            </div>
          </div>
          
          <div class="flex items-center gap-2">
            <button @click="downloadFile(file)" class="btn-icon text-on-surface-variant hover:text-primary" title="Tải xuống">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            </button>
            <button @click="deleteFile(file)" class="btn-icon text-on-surface-variant hover:text-error" title="Xóa">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()

const courseId = Number(route.params.id)
const lessonId = Number(route.params.lessonId)

const loading = ref(true)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const lesson = ref<any>(null)
const attachments = ref<any[]>([])

function formatDate(val: string) {
  if (!val) return ''
  return new Date(val).toLocaleDateString('vi-VN')
}

onMounted(async () => {
  await loadData()
})

async function loadData() {
  loading.value = true
  try {
    lesson.value = await useApi(`/courses/${courseId}/lessons/${lessonId}`, { token: auth.token })
    const res = await useApi<{ attachments: any[] }>(`/courses/${courseId}/lessons/${lessonId}/attachments`, { token: auth.token })
    attachments.value = res.attachments || []
  } catch (e) {
    console.error('Failed to load attachments', e)
  } finally {
    loading.value = false
  }
}

async function handleFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return
  
  const file = target.files[0]
  if (file.size > 50 * 1024 * 1024) {
    alert('File vượt quá giới hạn 50MB')
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  uploading.value = true
  try {
    const config = useRuntimeConfig()
    const response = await fetch(`${config.public.apiBase}/courses/${courseId}/lessons/${lessonId}/attachments`, {
      method: 'POST',
      body: formData,
      headers: {
        'Authorization': `Bearer ${auth.token}`,
        'Accept': 'application/json'
      }
    })

    if (!response.ok) throw new Error('Upload failed')
    
    const data = await response.json()
    attachments.value.push(data.attachment)
    
  } catch (e: any) {
    alert('Có lỗi khi tải lên tài liệu.')
    console.error(e)
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function downloadFile(file: any) {
  try {
    const res = await useApi<{ url: string }>(`/courses/${courseId}/lessons/${lessonId}/attachments/${file.id}/download`, { token: auth.token })
    window.open(res.url, '_blank')
  } catch (e) {
    alert('Không thể tải file lúc này.')
  }
}

async function deleteFile(file: any) {
  if (!confirm(`Bạn có chắc chắn muốn xóa file "${file.original_name}"?`)) return

  try {
    await useApi(`/courses/${courseId}/lessons/${lessonId}/attachments/${file.id}`, { 
      method: 'DELETE',
      token: auth.token 
    })
    attachments.value = attachments.value.filter(a => a.id !== file.id)
  } catch (e) {
    alert('Không thể xóa file.')
  }
}
</script>

<style scoped>
.btn-icon {
  @apply p-2 rounded-lg transition-colors border-none bg-transparent;
}
.btn-icon:hover {
  @apply bg-gray-100;
}
</style>
