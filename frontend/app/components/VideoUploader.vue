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
        class="drop-area"
        @click="$refs.fileInput.click()"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="handleDrop"
        :class="{ 'drag-over': dragOver }"
      >
        <div class="icon">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-12 h-12">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
        </div>
        <p class="text-lg font-semibold">Drop video here or click to upload</p>
        <p class="text-sm text-gray-500 mt-2">MP4, MOV, AVI, WEBM (Max 500MB)</p>
      </div>
    </div>

    <!-- Progress -->
    <div v-if="uploading" class="upload-progress">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium">{{ selectedFile?.name }}</span>
        <span class="text-sm text-gray-600">{{ uploadProgress }}%</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" :style="{ width: uploadProgress + '%' }"></div>
      </div>
      <p class="text-xs text-gray-500 mt-2">Uploading... Please wait</p>
    </div>

    <!-- Success -->
    <div v-if="videoUrl && !uploading" class="upload-success">
      <div class="success-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-8 h-8 text-green-600">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <p class="text-sm font-semibold text-green-700">Video uploaded successfully!</p>
      <button @click="replaceVideo" class="btn-replace">Replace Video</button>
    </div>

    <!-- Error -->
    <div v-if="error" class="error-message">
      <p class="text-sm text-red-600">{{ error }}</p>
      <button @click="resetUpload" class="btn-retry">Try Again</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

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

const MAX_SIZE = 500 * 1024 * 1024 // 500MB

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) validateAndUpload(file)
}

const handleDrop = (event: DragEvent) => {
  dragOver.value = false
  const file = event.dataTransfer?.files[0]
  if (file) validateAndUpload(file)
}

const validateAndUpload = (file: File) => {
  error.value = ''

  // Validate file type
  const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm']
  if (!allowedTypes.includes(file.type)) {
    error.value = 'Invalid file type. Please upload MP4, MOV, AVI, or WEBM.'
    return
  }

  // Validate file size
  if (file.size > MAX_SIZE) {
    error.value = 'File size exceeds 500MB. Please upload a smaller file.'
    return
  }

  selectedFile.value = file
  uploadVideo()
}

const uploadVideo = async () => {
  if (!selectedFile.value) return

  uploading.value = true
  uploadProgress.value = 15
  error.value = ''
  const auth = useAuthStore()

  const formData = new FormData()
  formData.append('video', selectedFile.value)

  const progressTimer = setInterval(() => {
    if (uploadProgress.value < 90) {
      uploadProgress.value += 8
    }
  }, 250)

  try {
    const response = await useApi<{ lesson?: any }>(`/courses/${props.courseId}/lessons/${props.lessonId}/upload-video`, {
      method: 'POST',
      body: formData,
      token: auth.token,
    })

    if (response.lesson) {
      uploadProgress.value = 100
      videoUrl.value = response.lesson.video_url
      emit('uploaded', response.lesson)
    }

  } catch (err: any) {
    error.value = err?.data?.message || 'Upload failed. Please try again.'
    emit('error', error.value)
  } finally {
    clearInterval(progressTimer)
    uploading.value = false
  }
}

const replaceVideo = () => {
  videoUrl.value = null
  selectedFile.value = null
  error.value = ''
  fileInput.value!.value = ''
}

const resetUpload = () => {
  error.value = ''
  uploading.value = false
  selectedFile.value = null
  uploadProgress.value = 0
}
</script>

<style scoped>
.video-uploader {
  width: 100%;
}

.drop-area {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 48px 24px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  background: #f9fafb;
}

.drop-area:hover,
.drop-area.drag-over {
  border-color: #16a34a;
  background: #dcfce7;
}

.icon {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
  color: #6b7280;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #16a34a, #22c55e);
  transition: width 0.3s ease;
}

.upload-success {
  text-align: center;
  padding: 32px;
  background: #f0fdf4;
  border-radius: 12px;
  border: 1px solid #bbf7d0;
}

.success-icon {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
}

.btn-replace {
  margin-top: 16px;
  padding: 8px 16px;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-replace:hover {
  background: #f9fafb;
  border-color: #16a34a;
}

.error-message {
  padding: 16px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  text-align: center;
}

.btn-retry {
  margin-top: 12px;
  padding: 8px 16px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
}

.btn-retry:hover {
  background: #b91c1c;
}

.hidden {
  display: none;
}
</style>
