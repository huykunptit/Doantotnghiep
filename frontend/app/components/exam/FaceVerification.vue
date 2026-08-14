<script setup lang="ts">
import type { GazeOffset } from '~/composables/useFaceApi'
import { resolveMediaUrl } from '~/utils/media-url'

const props = defineProps<{
  examId: string | number
  hasFaceUrl: boolean
  facePhotoUrl: string | null
  canEnrollFace?: boolean
}>()

const emit = defineEmits<{
  verified: [gazeBaseline: GazeOffset | null]
}>()

const { t } = useI18n()
const toast = useToast()
const {
  loadModels,
  descriptorFromImageUrl,
  descriptorFromVideo,
  detectFacesWithLandmarks,
  estimateGazeOffset,
  similarityFromDescriptors,
} = useFaceApi()

const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const stream = ref<MediaStream | null>(null)
const modelsReady = ref(false)
const verifying = ref(false)
const verified = ref(false)
const cameraError = ref('')
const statusMessage = ref('')
const enrollMode = ref(!!props.canEnrollFace)

let referenceDescriptor: Float32Array | null = null

function stopCamera() {
  stream.value?.getTracks().forEach(track => track.stop())
  stream.value = null
}

async function startCamera() {
  cameraError.value = ''
  statusMessage.value = ''

  if (!navigator.mediaDevices?.getUserMedia) {
    cameraError.value = t('exam.faceCheck.unsupported')
    return
  }

  stopCamera()
  try {
    const mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
      audio: false,
    })
    stream.value = mediaStream
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream
      await videoRef.value.play().catch(() => {})
    }
  }
  catch {
    cameraError.value = t('exam.faceCheck.cameraDenied')
  }
}

/** Loads face-api.js models + optional reference descriptor from the enrolled profile photo. */
async function prepareModels() {
  try {
    await loadModels()
    modelsReady.value = true

    const photo = resolveMediaUrl(props.facePhotoUrl) || props.facePhotoUrl
    if (!photo || props.canEnrollFace) {
      enrollMode.value = true
      return
    }

    referenceDescriptor = await descriptorFromImageUrl(photo)
    if (!referenceDescriptor) {
      enrollMode.value = true
      statusMessage.value = t('exam.faceCheck.enrollHint')
    }
  }
  catch {
    cameraError.value = t('exam.faceCheck.modelLoadError')
  }
}

function captureFrame(): string | null {
  const video = videoRef.value
  const canvas = canvasRef.value
  if (!video || !canvas || !video.videoWidth || !video.videoHeight) return null

  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')
  if (!ctx) return null

  ctx.save()
  ctx.translate(canvas.width, 0)
  ctx.scale(-1, 1)
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)
  ctx.restore()

  return canvas.toDataURL('image/jpeg', 0.9)
}

async function verifyFace() {
  if (verifying.value || verified.value) return

  if (!stream.value || !videoRef.value?.videoWidth) {
    await startCamera()
  }
  if (cameraError.value) return

  if (!modelsReady.value) await prepareModels()
  if (!modelsReady.value) return

  const image = captureFrame()
  if (!image) {
    cameraError.value = t('exam.faceCheck.captureFailed')
    return
  }

  verifying.value = true
  statusMessage.value = ''
  try {
    const liveDescriptor = videoRef.value ? await descriptorFromVideo(videoRef.value) : null
    if (!liveDescriptor) {
      statusMessage.value = t('exam.faceCheck.noFaceInFrame')
      toast.add({ severity: 'warn', summary: t('exam.faceCheck.failed'), detail: statusMessage.value, life: 4000 })
      return
    }

    const shouldEnroll = enrollMode.value || !referenceDescriptor
    const score = shouldEnroll
      ? 1
      : similarityFromDescriptors(referenceDescriptor!, liveDescriptor)

    const res = await useApi<{ ok: boolean, message?: string, enrolled?: boolean }>(`/exams/${props.examId}/verify-face`, {
      method: 'POST',
      body: { image, score, enroll: shouldEnroll },
    })

    if (!res.ok) {
      statusMessage.value = res.message || t('exam.faceCheck.failed')
      toast.add({ severity: 'error', summary: t('exam.faceCheck.failed'), detail: statusMessage.value, life: 4000 })
      return
    }

    verified.value = true
    statusMessage.value = res.enrolled
      ? t('exam.faceCheck.enrolled')
      : t('exam.faceCheck.verified')

    let gazeBaseline: GazeOffset | null = null
    try {
      if (videoRef.value) {
        const faces = await detectFacesWithLandmarks(videoRef.value)
        if (faces[0]) gazeBaseline = estimateGazeOffset(faces[0].landmarks)
      }
    }
    catch { /* baseline is best-effort */ }

    stopCamera()
    emit('verified', gazeBaseline)
  }
  catch (error: any) {
    statusMessage.value = error?.data?.message || t('exam.faceCheck.failed')
    toast.add({ severity: 'error', summary: t('exam.faceCheck.failed'), detail: statusMessage.value, life: 4000 })
  }
  finally {
    verifying.value = false
  }
}

onMounted(() => {
  startCamera()
  prepareModels()
})
onBeforeUnmount(stopCamera)
</script>

<template>
  <div class="face-check">
    <div class="head">
      <div>
        <strong>{{ t('exam.faceCheck.title') }}</strong>
        <p>{{ enrollMode ? t('exam.faceCheck.enrollDescription') : t('exam.faceCheck.description') }}</p>
      </div>
      <Tag
        v-if="verified || cameraError || verifying"
        :value="verified ? t('exam.faceCheck.verified') : cameraError ? t('exam.faceCheck.failed') : verifying ? t('exam.faceCheck.verifying') : ''"
        :severity="verified ? 'success' : cameraError ? 'danger' : 'info'"
      />
    </div>

    <div class="cam-shell">
      <video ref="videoRef" autoplay playsinline muted class="cam" />
      <div v-if="verified" class="cam-overlay success"><i class="pi pi-check-circle" /></div>
      <div v-if="!modelsReady && !cameraError" class="cam-overlay loading"><i class="pi pi-spin pi-spinner" /></div>
    </div>

    <p v-if="cameraError" class="msg error">{{ cameraError }}</p>
    <p v-else-if="statusMessage" class="msg">{{ statusMessage }}</p>

    <div class="actions">
      <Button
        :label="t('exam.faceCheck.restartCamera')"
        icon="pi pi-refresh"
        severity="secondary"
        outlined
        :disabled="verifying || verified"
        @click="startCamera"
      />
      <Button
        v-if="!verified"
        :label="enrollMode ? t('exam.faceCheck.enrollNow') : t('exam.faceCheck.verifyNow')"
        icon="pi pi-camera"
        :loading="verifying"
        :disabled="!modelsReady"
        @click="verifyFace"
      />
    </div>

    <canvas ref="canvasRef" class="hidden" />
  </div>
</template>

<style scoped>
.face-check { display: grid; gap: 14px; }
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap; }
.head strong { font-size: 1.05rem; }
.head p { margin: 4px 0 0; color: var(--text-muted, #5b6f6b); font-size: .88rem; max-width: 46ch; }
.cam-shell {
  position: relative; overflow: hidden; border-radius: 16px; background: #0b1220;
  aspect-ratio: 4 / 3; max-width: 420px; margin: 0 auto;
}
.cam { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
.cam-overlay {
  position: absolute; inset: 0; display: grid; place-items: center; font-size: 3rem;
}
.cam-overlay.success { background: rgba(16, 185, 129, .25); color: #10b981; }
.cam-overlay.loading { background: rgba(0, 0, 0, .35); color: #fff; font-size: 2rem; }
.msg { margin: 0; text-align: center; font-size: .88rem; color: var(--text-muted, #5b6f6b); }
.msg.error { color: #dc2626; }
.actions { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
.hidden { display: none; }
</style>
