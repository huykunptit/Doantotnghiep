<template>
  <div class="video-player-wrapper">
    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p class="text-sm text-on-surface-variant mt-4">Loading video...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-12 h-12 text-error">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <p class="text-error font-semibold mt-4">{{ error }}</p>
      <button @click="retryLoad" class="btn-retry">Retry</button>
    </div>

    <!-- Video Player -->
    <div v-else-if="isIframeSource && iframeUrl" class="player-container iframe-container">
      <iframe
        :id="ytContainerId"
        class="iframe-element"
        :src="iframeUrl"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen
        referrerpolicy="strict-origin-when-cross-origin"
      />
    </div>
    <div v-else-if="videoUrl" class="player-container">
      <video
        ref="videoElement"
        class="video-element"
        controls
        controlsList="nodownload"
        @timeupdate="handleTimeUpdate"
        @ended="handleVideoEnd"
        @play="handlePlay"
        @pause="handlePause"
      >
        <source :src="videoUrl" type="video/mp4">
        Your browser does not support the video tag.
      </video>

      <!-- URL Expiry Warning -->
      <div v-if="showExpiryWarning" class="expiry-warning">
        <p class="text-sm">Video URL expiring soon. Refreshing...</p>
      </div>
    </div>

    <!-- No Video -->
    <div v-else class="no-video-state">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-12 h-12 text-outline">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
      </svg>
      <p class="text-on-surface-variant mt-4">No video available</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

const props = defineProps<{
  courseId: number
  lessonId: number
  autoplay?: boolean
  /** URL bài học (YouTube/Drive) — phát ngay, không phụ thuộc signed URL. */
  src?: string | null
}>()

const emit = defineEmits<{
  progress: [data: { watched_seconds: number; completed: boolean }]
  ended: []
}>()

const auth = useAuthStore()
const isIframeSource = computed(() => ['youtube', 'gdrive', 'onedrive'].includes(detectProvider(videoUrl.value)))
const isYouTube = computed(() => detectProvider(videoUrl.value) === 'youtube')
const iframeUrl = computed(() => normalizeIframeUrl(videoUrl.value))
const ytContainerId = computed(() => `yt-player-${props.lessonId}`)

function extractYouTubeId(url: string): string {
  if (!url) return ''
  try {
    const parsed = new URL(url)
    if (parsed.hostname.includes('youtu.be')) {
      return parsed.pathname.split('/').filter(Boolean)[0] || ''
    }
    const fromQuery = parsed.searchParams.get('v')
    if (fromQuery) return fromQuery
    const parts = parsed.pathname.split('/').filter(Boolean)
    for (let i = 0; i < parts.length - 1; i++) {
      if (['embed', 'shorts', 'live', 'v'].includes(parts[i])) return parts[i + 1]
    }
  }
  catch {
    /* fall through */
  }
  const short = url.match(/youtu\.be\/([^?&/]+)/)
  const long = url.match(/[?&]v=([^?&/]+)/)
  const path = url.match(/youtube\.com\/(?:embed|shorts|live|v)\/([^?&/]+)/)
  return short?.[1] || long?.[1] || path?.[1] || ''
}

function detectProvider(url: string) {
  const normalized = (url || '').toLowerCase()
  if (normalized.includes('youtube.com') || normalized.includes('youtu.be')) return 'youtube'
  if (normalized.includes('drive.google.com')) return 'gdrive'
  if (normalized.includes('1drv.ms') || normalized.includes('onedrive.live.com')) return 'onedrive'
  return 'file'
}

function normalizeIframeUrl(url: string) {
  if (!url) return ''
  const provider = detectProvider(url)
  if (provider === 'youtube') {
    const id = extractYouTubeId(url)
    if (!id) return ''
    const params = new URLSearchParams({
      enablejsapi: '1',
      rel: '0',
      modestbranding: '1',
      playsinline: '1',
    })
    if (import.meta.client && window.location.origin) {
      params.set('origin', window.location.origin)
    }
    return `https://www.youtube.com/embed/${id}?${params.toString()}`
  }
  if (provider === 'gdrive') {
    const fileMatch = url.match(/\/file\/d\/([^/]+)/)
    const openMatch = url.match(/[?&]id=([^?&/]+)/)
    const id = fileMatch?.[1] || openMatch?.[1]
    return id ? `https://drive.google.com/file/d/${id}/preview` : url
  }
  if (provider === 'onedrive') {
    if (url.includes('embed=1')) return url
    return url.includes('?') ? `${url}&embed=1` : `${url}?embed=1`
  }
  return ''
}

const videoElement = ref<HTMLVideoElement>()
const videoUrl = ref('')
const loading = ref(false)
const error = ref('')
const showExpiryWarning = ref(false)
const expiresAt = ref<Date | null>(null)
const currentTime = ref(0)
const lastProgressUpdate = ref(0)

let refreshTimer: ReturnType<typeof setTimeout> | null = null
let progressTimer: ReturnType<typeof setInterval> | null = null

// ── YouTube IFrame API ──────────────────────────────────────────────────────
declare global {
  interface Window {
    YT: any
    onYouTubeIframeAPIReady: () => void
  }
}

let ytPlayer: any = null
let ytProgressTimer: ReturnType<typeof setInterval> | null = null
let ytApiReady = false

function loadYouTubeApi(): Promise<void> {
  return new Promise(resolve => {
    if (ytApiReady || window.YT?.Player) {
      ytApiReady = true
      resolve()
      return
    }
    const prev = window.onYouTubeIframeAPIReady
    window.onYouTubeIframeAPIReady = () => {
      ytApiReady = true
      prev?.()
      resolve()
    }
    if (!document.getElementById('yt-iframe-api')) {
      const tag = document.createElement('script')
      tag.id = 'yt-iframe-api'
      tag.src = 'https://www.youtube.com/iframe_api'
      document.head.appendChild(tag)
    }
  })
}

async function initYouTubePlayer() {
  if (!isYouTube.value || !iframeUrl.value) return
  if (ytProgressTimer) {
    clearInterval(ytProgressTimer)
    ytProgressTimer = null
  }
  ytPlayer = null
  await loadYouTubeApi()
  await nextTick()

  const el = document.getElementById(ytContainerId.value)
  if (!el || !window.YT?.Player) return

  try {
    ytPlayer = new window.YT.Player(el, {
      events: {
        onReady: (event: any) => {
          if (props.autoplay) event.target?.playVideo?.()
        },
        onStateChange: onYtStateChange,
      },
    })
  }
  catch {
    // iframe vẫn phát được dù IFrame API không gắn được
  }
}

function onYtStateChange(event: any) {
  const YT_PLAYING = 1
  const YT_PAUSED  = 2
  const YT_ENDED   = 0

  if (event.data === YT_PLAYING) {
    // Gửi progress mỗi 15 giây khi đang xem
    if (ytProgressTimer) clearInterval(ytProgressTimer)
    ytProgressTimer = setInterval(() => {
      const watched = Math.floor(ytPlayer?.getCurrentTime?.() ?? 0)
      sendYtProgress(watched, false)
    }, 15000)
  }

  if (event.data === YT_PAUSED) {
    if (ytProgressTimer) { clearInterval(ytProgressTimer); ytProgressTimer = null }
    const watched = Math.floor(ytPlayer?.getCurrentTime?.() ?? 0)
    sendYtProgress(watched, false)
  }

  if (event.data === YT_ENDED) {
    if (ytProgressTimer) { clearInterval(ytProgressTimer); ytProgressTimer = null }
    const duration = Math.floor(ytPlayer?.getDuration?.() ?? 0)
    sendYtProgress(duration, true)
    emit('ended')
  }
}

async function sendYtProgress(watched_seconds: number, completed: boolean) {
  try {
    await useApi(`/courses/${props.courseId}/lessons/${props.lessonId}/progress`, {
      method: 'PUT',
      body: { watched_seconds, completed },
      token: auth.token,
    })
    emit('progress', { watched_seconds, completed })
  } catch {
    // silent
  }
}

function destroyYouTubePlayer() {
  if (ytProgressTimer) {
    clearInterval(ytProgressTimer)
    ytProgressTimer = null
  }
  // Không gọi destroy() — nó gỡ iframe Vue đang giữ (mất referrerpolicy → YouTube 153).
  try {
    ytPlayer?.stopVideo?.()
  }
  catch {
    /* ignore */
  }
  ytPlayer = null
}
// ───────────────────────────────────────────────────────────────────────────

onMounted(() => {
  loadVideo()
})

onUnmounted(() => {
  cleanup()
})

watch(() => props.lessonId, () => {
  cleanup()
  videoUrl.value = ''
  error.value = ''
  loadVideo()
})

function applyPlaybackUrl(url: string, expiresAtIso?: string) {
  videoUrl.value = url
  const provider = detectProvider(url)
  if (provider === 'youtube' && !extractYouTubeId(url)) {
    error.value = 'Link YouTube không hợp lệ. Dùng youtube.com/watch?v=... hoặc youtu.be/...'
    return false
  }
  if (expiresAtIso && provider === 'file') {
    expiresAt.value = new Date(expiresAtIso)
    scheduleRefresh()
  }
  return true
}

const loadVideo = async () => {
  loading.value = true
  error.value = ''
  videoUrl.value = ''
  expiresAt.value = null

  const immediate = (props.src || '').trim()
  if (immediate && detectProvider(immediate) !== 'file') {
    const ok = applyPlaybackUrl(immediate)
    loading.value = false
    if (ok && detectProvider(immediate) === 'youtube') {
      await initYouTubePlayer()
    }
    return
  }

  try {
    const response = await useApi<{ url: string; expires_at: string; expires_in: number }>(
      `/courses/${props.courseId}/lessons/${props.lessonId}/video-url`,
      { token: auth.token },
    )

    const ok = applyPlaybackUrl(response.url, response.expires_at)
    loading.value = false
    if (!ok) return

    if (detectProvider(response.url) === 'youtube') {
      await initYouTubePlayer()
    }
    else if (props.autoplay) {
      await nextTick()
      videoElement.value?.play()
    }
  }
  catch (err: any) {
    error.value = err?.data?.message || 'Không thể tải video. Vui lòng thử lại.'
  }
  finally {
    loading.value = false
  }
}

const scheduleRefresh = () => {
  if (refreshTimer) clearTimeout(refreshTimer)

  if (!expiresAt.value) return

  const now = new Date()
  const timeUntilExpiry = expiresAt.value.getTime() - now.getTime()
  const refreshTime = Math.max(0, timeUntilExpiry - 2 * 60 * 1000)

  refreshTimer = setTimeout(() => {
    showExpiryWarning.value = true
    refreshVideoUrl()
  }, refreshTime)
}

const refreshVideoUrl = async () => {
  const currentTimeSnapshot = videoElement.value?.currentTime || 0

  try {
    const response = await useApi<{ url: string; expires_at: string; expires_in: number }>(
      `/courses/${props.courseId}/lessons/${props.lessonId}/video-url`,
      { token: auth.token }
    )

    videoUrl.value = response.url
    expiresAt.value = new Date(response.expires_at)

    // Khôi phục vị trí phát
    await nextTick()
    if (videoElement.value) {
      videoElement.value.currentTime = currentTimeSnapshot
    }

    showExpiryWarning.value = false
    scheduleRefresh()

  } catch (err) {
    error.value = 'Phiên xem hết hạn. Vui lòng tải lại trang.'
  }
}

const handleTimeUpdate = () => {
  if (!videoElement.value) return

  currentTime.value = Math.floor(videoElement.value.currentTime)

  // Gửi progress mỗi 10 giây
  if (currentTime.value - lastProgressUpdate.value >= 10) {
    sendProgressUpdate(false)
    lastProgressUpdate.value = currentTime.value
  }
}

const handleVideoEnd = () => {
  sendProgressUpdate(true)
  emit('ended')
}

const handlePlay = () => {
  // Gửi progress định kỳ 30 giây
  progressTimer = setInterval(() => {
    if (videoElement.value && !videoElement.value.paused) {
      sendProgressUpdate(false)
    }
  }, 30000)
}

const handlePause = () => {
  sendProgressUpdate(false)
  if (progressTimer) {
    clearInterval(progressTimer)
    progressTimer = null
  }
}

const sendProgressUpdate = async (completed: boolean) => {
  if (!videoElement.value) return

  const watched_seconds = Math.floor(videoElement.value.currentTime)

  try {
    await useApi(`/courses/${props.courseId}/lessons/${props.lessonId}/progress`, {
      method: 'PUT',
      body: { watched_seconds, completed },
      token: auth.token,
    })

    emit('progress', { watched_seconds, completed })
  } catch (err) {
    // Silent fail — không làm gián đoạn video
    console.error('Failed to update progress:', err)
  }
}

const retryLoad = () => {
  error.value = ''
  loadVideo()
}

const cleanup = () => {
  if (refreshTimer) {
    clearTimeout(refreshTimer)
    refreshTimer = null
  }
  if (progressTimer) {
    clearInterval(progressTimer)
    progressTimer = null
  }
  destroyYouTubePlayer()
}
</script>

<style scoped>
.video-player-wrapper {
  width: 100%;
  height: 100%;
  background: #000;
  overflow: hidden;
  position: relative;
}

.loading-state,
.error-state,
.no-video-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  background: #1a1a1a;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #333;
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.btn-retry {
  margin-top: 16px;
  padding: 10px 24px;
  background: var(--green);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.iframe-container {
  height: 100%;
}

.iframe-element {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
  background: #000;
}

.btn-retry:hover {
  background: var(--green-deep);
}

.player-container {
  position: relative;
  width: 100%;
  height: 100%;
}

.video-element {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: contain;
  background: #000;
}

.expiry-warning {
  position: absolute;
  top: 16px;
  right: 16px;
  background: rgba(0, 0, 0, 0.8);
  padding: 8px 16px;
  border-radius: 6px;
  color: #fbbf24;
  font-size: 12px;
  font-weight: 500;
}
</style>

