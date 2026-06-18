<template>
  <div class="scorm-player-container">
    <div v-if="loading" class="player-loading">
      <div class="spinner"></div>
      <p>Đang tải giáo trình tương tác...</p>
    </div>

    <iframe
      v-if="packageData?.entry_url"
      ref="scormIframe"
      :src="packageData.entry_url"
      class="scorm-iframe"
      frameborder="0"
      allowfullscreen
    ></iframe>

    <div v-if="error" class="player-error">
      <span class="material-symbols-outlined">error</span>
      <p>{{ error }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

const props = defineProps<{
  courseId: string | number
  lessonId: string | number
  packageData: any
}>()

const emit = defineEmits<{
  completed: [payload: { version: string; passed?: boolean }]
}>()

const auth = useAuthStore()
const loading = ref(true)
const error = ref<string | null>(null)
const scormIframe = ref<HTMLIFrameElement | null>(null)
const authHeaders = () => auth.token ? { Authorization: `Bearer ${auth.token}` } : undefined

// SCORM 1.2 and 2004 use different data keys. We keep one bag and split when
// reporting to the backend so each version sees its own keys.
const dataModel = reactive<Record<string, string>>({})

const isScorm2004 = () => {
  const v = String(props.packageData?.version || '1.2')
  return v.includes('2004') || v.startsWith('3') || v.startsWith('4')
}

function normalizeStatus(): { status: string | null; completion: string | null; success: string | null; score: number | null } {
  if (isScorm2004()) {
    return {
      status: null,
      completion: dataModel['cmi.completion_status'] || null,
      success: dataModel['cmi.success_status'] || null,
      score: parseFloat(dataModel['cmi.score.raw'] || '') || null,
    }
  }
  return {
    status: dataModel['cmi.core.lesson_status'] || 'incomplete',
    completion: null,
    success: null,
    score: parseFloat(dataModel['cmi.core.score.raw'] || '') || null,
  }
}

let commitTimer: number | null = null

async function trackProgress() {
  try {
    const norm = normalizeStatus()
    const version = isScorm2004() ? '2004' : '1.2'
    await useApi(`/courses/${props.courseId}/lessons/${props.lessonId}/scorm/track`, {
      method: 'POST',
      body: {
        version,
        status: norm.status,
        completion_status: norm.completion,
        success_status: norm.success,
        score: norm.score,
        lesson_location: dataModel[isScorm2004() ? 'cmi.location' : 'cmi.core.lesson_location'] || null,
        suspend_data: dataModel['cmi.suspend_data'] || null,
      },
      headers: authHeaders(),
    })

    // Emit when content reports completion so parent can refresh progress UI.
    const isDone = version === '2004'
      ? norm.completion === 'completed' || norm.success === 'passed'
      : ['completed', 'passed'].includes(norm.status || '')
    if (isDone) {
      emit('completed', { version, passed: norm.status === 'passed' || norm.success === 'passed' })
    }
  } catch (err) {
    // eslint-disable-next-line no-console
    console.error('Failed to track SCORM progress', err)
  }
}

// Coalesce rapid SetValue+Commit chains so we don't hammer the API.
function scheduleCommit() {
  if (commitTimer) clearTimeout(commitTimer)
  commitTimer = window.setTimeout(trackProgress, 800)
}

function mountScorm12Adapter() {
  ;(window as any).API = {
    LMSInitialize: () => 'true',
    LMSFinish: () => { trackProgress(); return 'true' },
    LMSGetValue: (key: string) => dataModel[key] ?? '',
    LMSSetValue: (key: string, val: string) => { dataModel[key] = val; return 'true' },
    LMSCommit: () => { scheduleCommit(); return 'true' },
    LMSGetLastError: () => '0',
    LMSGetErrorString: () => 'No error',
    LMSGetDiagnostic: () => 'No diagnostic',
  }
}

function mountScorm2004Adapter() {
  ;(window as any).API_1484_11 = {
    Initialize: () => 'true',
    Terminate: () => { trackProgress(); return 'true' },
    GetValue: (key: string) => dataModel[key] ?? '',
    SetValue: (key: string, val: string) => { dataModel[key] = val; return 'true' },
    Commit: () => { scheduleCommit(); return 'true' },
    GetLastError: () => '0',
    GetErrorString: () => 'No error',
    GetDiagnostic: () => 'No diagnostic',
  }
}

function unmountAdapters() {
  delete (window as any).API
  delete (window as any).API_1484_11
}

function mountForVersion() {
  unmountAdapters()
  if (isScorm2004()) {
    mountScorm2004Adapter()
  } else {
    mountScorm12Adapter()
  }
}

onMounted(() => {
  // Mount BEFORE the iframe finishes loading — SCORM content's first thing
  // upon load is API discovery on window.parent.
  mountForVersion()
  setTimeout(() => { loading.value = false }, 600)
})

watch(() => props.packageData?.version, mountForVersion)

onBeforeUnmount(() => {
  if (commitTimer) clearTimeout(commitTimer)
  unmountAdapters()
})
</script>

<style scoped>
.scorm-player-container {
  width: 100%;
  height: 100%;
  min-height: 480px;
  background: #000;
  overflow: hidden;
  position: relative;
}

.scorm-iframe {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
  background: #fff;
}

.player-loading,
.player-error {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #fff;
  background: #111827;
}

.player-error .material-symbols-outlined {
  font-size: 36px;
  color: #f87171;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .scorm-iframe { background: var(--surface); }
</style>
