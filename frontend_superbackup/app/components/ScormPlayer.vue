<template>
  <div class="scorm-player-container">
    <div v-if="loading" class="player-loading">
      <div class="spinner"></div>
      <p>Đang tải giáo trình tương tác...</p>
    </div>

    <!-- File not found or no package -->
    <div v-else-if="notFound" class="player-empty">
      <span class="material-symbols-outlined">folder_off</span>
      <p>Chưa có gói SCORM cho bài học này.</p>
      <span class="player-empty-hint">Giảng viên cần upload file .zip SCORM trong phần chỉnh sửa bài học.</span>
    </div>

    <template v-else>
      <iframe
        ref="scormIframe"
        :src="resolvedUrl"
        class="scorm-iframe"
        frameborder="0"
        allowfullscreen
        @load="onIframeLoad"
      />
    </template>

    <div v-if="error" class="player-error">
      <span class="material-symbols-outlined">error</span>
      <p>{{ error }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useRuntimeConfig } from '#app'

const props = defineProps<{
  courseId: string | number
  lessonId: string | number
  packageData: any
}>()

const emit = defineEmits<{
  completed: [payload: { version: string; passed?: boolean }]
}>()

const auth = useAuthStore()
const config = useRuntimeConfig()
const loading = ref(true)
const notFound = ref(false)
const error = ref<string | null>(null)
const scormIframe = ref<HTMLIFrameElement | null>(null)
const authHeaders = () => auth.token ? { Authorization: `Bearer ${auth.token}` } : undefined

// Resolve to browser-accessible URL.
// Backend returns: /minio/<bucket>/<key>, https://..., or /storage/...
const resolvedUrl = computed(() => {
  const url = props.packageData?.entry_url
  if (!url) return ''
  if (url.startsWith('http') || url.startsWith('/minio/') || url.startsWith('/storage/')) return url
  const base = (config.public.apiBase as string).replace('/api', '')
  return `${base}/storage/${url.replace(/^\//, '')}`
})

function onIframeLoad() {
  loading.value = false
}

// Probe the entry URL with a HEAD request to detect 404/missing files before
// showing the iframe. This avoids the "loading forever" state when seeded or
// deleted packages point to non-existent files.
async function probeUrl(url: string): Promise<boolean> {
  try {
    const res = await fetch(url, { method: 'HEAD', signal: AbortSignal.timeout(5000) })
    return res.ok
  } catch {
    return false
  }
}

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

onMounted(async () => {
  mountForVersion()

  if (!resolvedUrl.value) {
    loading.value = false
    notFound.value = true
    return
  }

  // Probe before rendering the iframe so we never get stuck on the spinner.
  const exists = await probeUrl(resolvedUrl.value)
  if (!exists) {
    loading.value = false
    notFound.value = true
    return
  }

  // File confirmed to exist — iframe will handle loading from here.
  // onIframeLoad will set loading = false once the iframe fires its load event.
  // Safety timeout in case the load event never fires (e.g. CSP/sandbox issue).
  setTimeout(() => { loading.value = false }, 10000)
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
.player-error,
.player-empty {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #fff;
  background: #111827;
  text-align: center;
  padding: 24px;
}

.player-error .material-symbols-outlined,
.player-empty .material-symbols-outlined {
  font-size: 42px;
  color: #f87171;
}

.player-empty .material-symbols-outlined {
  color: #6b7280;
}

.player-empty p {
  font-size: 1rem;
  font-weight: 600;
  color: #d1d5db;
  margin: 0;
}

.player-empty-hint {
  font-size: 0.82rem;
  color: #6b7280;
  max-width: 340px;
  line-height: 1.5;
}

.retry-btn {
  margin-top: 8px;
  padding: 8px 20px;
  background: var(--green, #1d9e75);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
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
