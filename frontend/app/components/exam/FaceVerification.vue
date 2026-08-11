<script setup lang="ts">
const props = defineProps<{
  examId: string | number
  hasFaceUrl: boolean
  facePhotoUrl: string | null
}>()

const emit = defineEmits<{
  verified: []
}>()

const { t } = useI18n()
const toast = useToast()
const { loadModels, descriptorFromImageUrl, descriptorFromVideo, similarityFromDescriptors } = useFaceApi()

const videoRef = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const stream = ref<MediaStream | null>(null)
const modelsReady = ref(false)
const verifying = ref(false)
const verified = ref(false)
const cameraError = ref('')
const statusMessage = ref('')

let referenceDescriptor: Float32Array | null = null

function stopCamera() {
  stream.value?.getTracks().forEach(track => track.stop())
  stream.value = null
}

async function startCamera() {
  if (!props.hasFaceUrl) return
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

/** Loads face-api.js models + computes the reference descriptor from the enrolled profile photo, once. */
async function prepareModels() {
  if (!props.hasFaceUrl || !props.facePhotoUrl) return
  try {
    await loadModels()
    referenceDescriptor = await descriptorFromImageUrl(props.facePhotoUrl)
    modelsReady.value = true
    if (!referenceDescriptor) {
      cameraError.value = t('exam.faceCheck.noReferenceFace')
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
  if (!referenceDescriptor) {
    cameraError.value = t('exam.faceCheck.noReferenceFace')
    return
  }

  const image = captureFrame()
  if (!image) {
    cameraError.value = t('exam.faceCheck.captureFailed')
    return
  }

  verifying.value = true
  statusMessage.value = ''
  try {
    // Real comparison happens here, in the browser: the live webcam frame's
    // face descriptor vs. the enrolled reference photo's descriptor. Only the
    // resulting similarity score (plus the captured image, for audit) goes to
    // the server, which independently thresholds it — see
    // ExamProctorController::verifyFace for why the server still decides
    // pass/fail rather than trusting a client-sent boolean.
    const liveDescriptor = videoRef.value ? await descriptorFromVideo(videoRef.value) : null
    if (!liveDescriptor) {
      statusMessage.value = t('exam.faceCheck.noFaceInFrame')
      toast.add({ severity: 'warn', summary: t('exam.faceCheck.failed'), detail: statusMessage.value, life: 4000 })
      return
    }

    const score = similarityFromDescriptors(referenceDescriptor, liveDescriptor)

    const res = await useApi<{ ok: boolean, message?: string }>(`/exams/${props.examId}/verify-face`, {
      method: 'POST',
      body: { image, score },
    })

    if (!res.ok) {
      statusMessage.value = res.message || t('exam.faceCheck.failed')
      toast.add({ severity: 'error', summary: t('exam.faceCheck.failed'), detail: statusMessage.value, life: 4000 })
      return
    }

    verified.value = true
    statusMessage.value = t('exam.faceCheck.verified')
    stopCamera()
    emit('verified')
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
    <div v-if="!hasFaceUrl" class="no-face">
      <i class="pi pi-exclamation-triangle" />
      <div>
        <strong>{{ t('exam.faceCheck.noFaceTitle') }}</strong>
        <p>{{ t('exam.faceCheck.noFaceDetail') }}</p>
      </div>
    </div>

    <template v-else>
      <div class="head">
        <div>
          <strong>{{ t('exam.faceCheck.title') }}</strong>
          <p>{{ t('exam.faceCheck.description') }}</p>
        </div>
        <Tag
          :value="verified ? t('exam.faceCheck.verified') : cameraError ? t('exam.faceCheck.failed') : verifying ? t('exam.faceCheck.verifying') : ''"
          :severity="verified ? 'success' : cameraError ? 'danger' : 'info'"
          v-if="verified || cameraError || verifying"
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
          :label="t('exam.faceCheck.verifyNow')"
          icon="pi pi-camera"
          :loading="verifying"
          :disabled="!modelsReady"
          @click="verifyFace"
        />
      </div>
    </template>

    <canvas ref="canvasRef" class="hidden" />
  </div>
</template>

<style scoped>
.face-check { display: grid; gap: 14px; }
.no-face {
  display: flex; gap: 12px; align-items: flex-start; padding: 16px;
  border: 1px solid #fde68a; border-radius: 14px; background: #fffbeb; color: #92400e;
}
.no-face i { font-size: 1.3rem; margin-top: 2px; }
.no-face strong { display: block; margin-bottom: 4px; }
.no-face p { margin: 0; font-size: .9rem; }
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
