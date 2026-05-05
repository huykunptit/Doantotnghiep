<template>
  <div class="video-uploader">
    <div v-if="!uploading && !videoUrl" class="upload-zone">
      <input
        ref="fileInput"
        type="file"
        accept="video/mp4,video/mov,video/avi,video/webm"
        @change="handleFileSelect"
        class="hidden"
      />
      
      <div 
        class="border-2 border-dashed border-outline-variant/50 rounded-2xl p-12 text-center cursor-pointer transition-all duration-300"
        @click="$refs.fileInput.click()"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="handleDrop"
        :class="dragOver ? 'border-primary bg-primary/5' : 'bg-surface-lowest hover:border-primary/50 hover:bg-surface-low'"
      >
        <div class="flex justify-center mb-4 text-outline group-hover:text-primary transition-colors">
          <span class="material-symbols-outlined text-5xl">cloud_upload</span>
        </div>
        <p class="text-lg font-bold font-headline text-on-surface">Kéo thả Video vào đây hoặc Click để trình duyệt</p>
        <p class="text-xs font-bold text-outline-variant mt-2 uppercase tracking-widest">Hỗ trợ: MP4, MOV, AVI, WEBM (Tối Đa 1GB)</p>
      </div>
    </div>

    <!-- Progress -->
    <div v-if="uploading" class="bg-surface-lowest border border-surface-dim rounded-2xl p-6 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <span class="text-sm font-bold text-on-surface truncate pr-4">{{ selectedFile?.name }}</span>
        <span class="text-sm font-bold text-primary bg-primary/10 px-2 py-1 rounded">{{ uploadProgress }}%</span>
      </div>
      <div class="w-full h-3 bg-surface-high rounded-full overflow-hidden">
        <div class="h-full progress-gradient transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
      </div>
      <p class="text-[10px] font-bold text-outline uppercase tracking-widest mt-3 flex items-center justify-center gap-2">
         <span class="material-symbols-outlined text-[14px] animate-spin">refresh</span>
         Đang xử lý tải lên... Vui lòng không đóng cửa sổ
      </p>
    </div>

    <!-- Success -->
    <div v-if="videoUrl && !uploading" class="bg-secondary/10 border border-secondary/20 rounded-2xl p-8 text-center text-secondary">
      <div class="flex justify-center mb-3">
        <span class="material-symbols-outlined text-4xl text-secondary" style="font-variation-settings: 'FILL' 1;">check_circle</span>
      </div>
      <p class="text-sm font-bold uppercase tracking-widest">Tải lên Giáo trình Thành công!</p>
      <button @click="replaceVideo" class="mt-6 px-6 py-2 bg-surface-lowest border border-secondary/20 text-secondary font-bold text-sm hover:bg-secondary-50 rounded-lg transition-colors">Thay Video Khác</button>
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-error-container/30 border border-error/20 rounded-2xl p-6 text-center text-error mt-4">
      <p class="text-sm font-medium">{{ error }}</p>
      <button @click="resetUpload" class="mt-4 px-6 py-2 bg-error text-white font-bold text-sm hover:bg-error/90 rounded-lg transition-colors shadow-sm">Thử Lại Ngay</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

const props = defineProps<{
  courseId: number
  lessonId: number
  existingVideoUrl?: string | null
}>()

const emit = defineEmits<{
  uploaded: [data: any]
  error: [message: string]
}>()

const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const uploading = ref(false)
const uploadProgress = ref(0)
const videoUrl = ref(props.existingVideoUrl || null)
const dragOver = ref(false)
const error = ref('')

const runtimeConfig = useRuntimeConfig()

const MAX_SIZE = 1000 * 1024 * 1024 // 1GB now allowed

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) validateAndUpload(file)
}

const handleDrop = (event: DragEvent) => {
  dragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) validateAndUpload(file)
}

const validateAndUpload = (file: File) => {
  error.value = ''

  // Validate file type
  const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm']
  if (!allowedTypes.includes(file.type)) {
    error.value = 'Định dạng Video không hợp lệ! Vui lòng dùng MP4, MOV, AVI, hoặc WEBM.'
    return
  }

  // Validate file size
  if (file.size > MAX_SIZE) {
    error.value = 'Dung lượng Video vượt quá 1GB. Vui lòng nén file trước khi upload.'
    return
  }

  selectedFile.value = file
  uploadVideo()
}

const uploadVideo = async () => {
  if (!selectedFile.value) return

  uploading.value = true
  uploadProgress.value = 0
  error.value = ''
  const auth = useAuthStore()

  const formData = new FormData()
  formData.append('video', selectedFile.value)

  const base = (runtimeConfig.public.apiBase as string).replace(/\/$/, '')

  try {
    const response = await new Promise<any>((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('POST', `${base}/courses/${props.courseId}/lessons/${props.lessonId}/upload-video`, true)
      if (auth.token) {
        xhr.setRequestHeader('Authorization', `Bearer ${auth.token}`)
      }
      xhr.setRequestHeader('Accept', 'application/json')

      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          uploadProgress.value = Math.min(100, Math.round((event.loaded / event.total) * 100))
        }
      }

      xhr.onreadystatechange = () => {
        if (xhr.readyState !== XMLHttpRequest.DONE) return

        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            resolve(JSON.parse(xhr.responseText))
          } catch (parseError) {
            reject(new Error('Phản hồi upload không hợp lệ.'))
          }
        } else {
          const responseBody = xhr.responseText || xhr.statusText
          reject(new Error(responseBody))
        }
      }

      xhr.onerror = () => reject(new Error('Lỗi mạng khi tải lên video.'))
      xhr.send(formData)
    })

    if (response.lesson) {
      uploadProgress.value = 100
      setTimeout(() => {
        videoUrl.value = response.lesson.video_url
        emit('uploaded', response.lesson)
      }, 300)
    }
  } catch (err: any) {
    let message = 'Băng thông gặp sự cố. Quá trình tải lên thất bại.'
    if (err?.message) {
      try {
        const parsed = JSON.parse(err.message)
        message = parsed.message || message
      } catch {
        message = err.message
      }
    }
    error.value = message
    emit('error', error.value)
  } finally {
    uploading.value = false
  }
}

const replaceVideo = () => {
  videoUrl.value = null
  selectedFile.value = null
  error.value = ''
  if(fileInput.value) fileInput.value.value = ''
}

const resetUpload = () => {
  error.value = ''
  uploading.value = false
  selectedFile.value = null
  uploadProgress.value = 0
}
</script>

<style scoped>
.hidden {
  display: none;
}
</style>
