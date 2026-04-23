<template>
  <div class="scorm-player-container">
    <div v-if="loading" class="player-loading">
      <div class="spinner"></div>
      <p>Đang tải giáo trình tương tác...</p>
    </div>
    
    <iframe
      v-if="packageData"
      ref="scormIframe"
      :src="packageData.entry_url"
      class="scorm-iframe"
      frameborder="0"
      allowfullscreen
    ></iframe>
    
    <div v-if="error" class="player-error">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
const props = defineProps<{
  courseId: string | number
  lessonId: string | number
  packageData: any
}>()

const loading = ref(true)
const error = ref<string | null>(null)
const scormIframe = ref<HTMLIFrameElement | null>(null)

// Local state for SCORM data
const scormData = reactive({
  'cmi.core.lesson_status': 'incomplete',
  'cmi.core.score.raw': '0',
  'cmi.core.lesson_location': '',
  'cmi.suspend_data': ''
})

const trackProgress = async () => {
  try {
    await $fetch(`/api/courses/${props.courseId}/lessons/${props.lessonId}/scorm/track`, {
      method: 'POST',
      body: {
        status: scormData['cmi.core.lesson_status'],
        score: parseFloat(scormData['cmi.core.score.raw']),
        lesson_location: scormData['cmi.core.lesson_location'],
        suspend_data: scormData['cmi.suspend_data']
      }
    })
  } catch (err) {
    console.error('Failed to track SCORM progress', err)
  }
}

onMounted(() => {
  // SCORM 1.2 API adapter
  (window as any).API = {
    LMSInitialize: () => { 
      console.log('SCORM: Initialized')
      return "true" 
    },
    LMSFinish: () => { 
      console.log('SCORM: Finished')
      trackProgress()
      return "true" 
    },
    LMSGetValue: (key: string) => { 
      return (scormData as any)[key] || "" 
    },
    LMSSetValue: (key: string, val: string) => { 
      (scormData as any)[key] = val
      return "true" 
    },
    LMSCommit: () => { 
      trackProgress()
      return "true" 
    },
    LMSGetLastError: () => "0",
    LMSGetErrorString: () => "No error",
    LMSGetDiagnostic: () => "Diagnostic info"
  }
  
  setTimeout(() => {
    loading.value = false
  }, 1000)
})
</script>

<style scoped>
.scorm-player-container {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #000;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
}

.scorm-iframe {
  width: 100%;
  height: 100%;
}

.player-loading, .player-error {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: #111827;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
