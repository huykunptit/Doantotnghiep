<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import type { GazeOffset } from '~/composables/useFaceApi'
import { resolveMediaUrl } from '~/utils/media-url'

definePageMeta({ layout: false, middleware: ['auth'] })

interface AnswerOpt { id: number, content: string }
interface QuestionItem {
  id: number
  content: string
  type: string
  answers?: AnswerOpt[]
}

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()
const examId = computed(() => String(route.params.examId))

const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const examTitle = ref('')
const proctoring = ref(false)
const preChecking = ref(true)
const preCheckError = ref('')
const requiresFaceCheck = ref(false)
const hasFaceUrl = ref(false)
const canEnrollFace = ref(false)
const facePhotoUrl = ref<string | null>(null)
const faceVerified = ref(false)
const gazeBaseline = ref<GazeOffset | null>(null)
const { loadModels: loadFaceModels, detectFacesWithLandmarks, estimateGazeOffset } = useFaceApi()
const { detectPhone } = usePhoneDetector()
const monitorVideoRef = ref<HTMLVideoElement | null>(null)
const monitorActive = ref(false)
let monitorStream: MediaStream | null = null
let monitorVideo: HTMLVideoElement | null = null
let monitorCanvas: HTMLCanvasElement | null = null
let monitorInterval: ReturnType<typeof setInterval> | null = null
let phoneCheckInterval: ReturnType<typeof setInterval> | null = null
const attemptId = ref<number | null>(null)
const remainingTime = ref<number | null>(null)
const status = ref('in_progress')
const questions = ref<QuestionItem[]>([])
const answers = ref<Record<string, any>>({})
const currentIndex = ref(0)
const bookmarks = ref<Record<number, boolean>>({})
const focusLoss = ref(0)
const MAX_FOCUS_LOSS = 3
const FOCUS_LOSS_COOLDOWN_MS = 1500
const focusBannerVisible = ref(false)
let lastFocusLogAt = 0
let pendingFocusBanner = false
const autoSaveStatus = ref('')

let timerInterval: ReturnType<typeof setInterval> | null = null
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null

const current = computed(() => questions.value[currentIndex.value] || null)
const answeredCount = computed(() =>
  Object.keys(answers.value).filter((k) => {
    const v = answers.value[k]
    return v !== null && v !== undefined && v !== '' && !(Array.isArray(v) && !v.length)
  }).length,
)

const timerDisplay = computed(() => {
  if (remainingTime.value === null) return '∞'
  const m = Math.floor(remainingTime.value / 60)
  const s = remainingTime.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const timerUrgent = computed(() => remainingTime.value !== null && remainingTime.value < 300)
const unansweredCount = computed(() => Math.max(0, questions.value.length - answeredCount.value))
const bookmarkedCount = computed(() => Object.values(bookmarks.value).filter(Boolean).length)
const isCurrentBookmarked = computed(() => {
  const id = current.value?.id
  return id ? !!bookmarks.value[id] : false
})

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

function isQuestionAnswered(qid: number) {
  const v = answers.value[qid]
  return v !== null && v !== undefined && v !== '' && !(Array.isArray(v) && !v.length)
}

function toggleBookmark(qid?: number) {
  const id = qid ?? current.value?.id
  if (!id) return
  bookmarks.value = { ...bookmarks.value, [id]: !bookmarks.value[id] }
}

function typeLabel(type: string) {
  const map: Record<string, string> = {
    single_choice: t('exam.type.single'),
    multiple_choice: t('exam.type.multi'),
    true_false: t('exam.type.tf'),
    essay: t('exam.type.essay'),
    short_answer: t('exam.type.short'),
    numerical: t('exam.type.num'),
  }
  return map[type] || type
}

async function runPreCheck() {
  preChecking.value = true
  preCheckError.value = ''
  try {
    const data = await useApi<{
      exam: { title: string, proctoring_enabled?: boolean }
      requires_face_check: boolean
      has_face_url: boolean
      face_photo_usable?: boolean
      can_enroll_face?: boolean
      face_photo_url: string | null
    }>(`/exams/${examId.value}/pre-check`)

    hasFaceUrl.value = !!data.has_face_url
    canEnrollFace.value = data.can_enroll_face ?? !data.face_photo_usable
    facePhotoUrl.value = resolveMediaUrl(data.face_photo_url) || data.face_photo_url
    requiresFaceCheck.value = !!data.requires_face_check

    if (requiresFaceCheck.value) {
      examTitle.value = data.exam?.title || ''
      preChecking.value = false
      return
    }

    await loadExam()
  }
  catch (e: any) {
    preCheckError.value = e?.data?.message || t('exam.faceCheck.preCheckError')
  }
  finally {
    preChecking.value = false
  }
}

function onFaceVerified(baseline: GazeOffset | null) {
  faceVerified.value = true
  gazeBaseline.value = baseline
  requiresFaceCheck.value = false
  loadExam()
}

async function loadExam() {
  loading.value = true
  error.value = ''
  try {
    const data = await useApi<{
      exam: { title: string, proctoring_enabled?: boolean }
      questions: QuestionItem[]
      attempt_id: number
      remaining_time: number | null
      status: string
      saved_answers?: Record<string, any>
    }>(`/exams/${examId.value}/start`)
    examTitle.value = data.exam?.title || ''
    proctoring.value = !!data.exam?.proctoring_enabled
    questions.value = data.questions || []
    attemptId.value = data.attempt_id
    remainingTime.value = data.remaining_time
    status.value = data.status || 'in_progress'
    if (data.saved_answers) answers.value = { ...data.saved_answers }
    startTimer()
    bindProctor()
    if (proctoring.value) {
      startFaceMonitor()
      startPhoneMonitor()
    }
  }
  catch (e: any) {
    error.value = e?.data?.message || t('exam.loadError')
  }
  finally {
    loading.value = false
  }
}

function startTimer() {
  if (remainingTime.value === null) return
  timerInterval = setInterval(() => {
    if (status.value === 'paused') return
    if (remainingTime.value !== null && remainingTime.value > 0) {
      remainingTime.value--
    }
    else if (remainingTime.value !== null && remainingTime.value <= 0) {
      submitExam(true)
    }
  }, 1000)
}

function selectAnswer(qid: number, value: any) {
  answers.value[qid] = value
  debouncedAutoSave()
}

function toggleMulti(qid: number, answerId: number) {
  const currentVal = answers.value[qid] || []
  const arr = Array.isArray(currentVal) ? [...currentVal] : []
  const idx = arr.indexOf(answerId)
  if (idx > -1) arr.splice(idx, 1)
  else arr.push(answerId)
  answers.value[qid] = arr
  debouncedAutoSave()
}

function debouncedAutoSave() {
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(autoSave, 4000)
}

async function autoSave() {
  if (!attemptId.value || status.value !== 'in_progress') return
  autoSaveStatus.value = t('exam.saving')
  try {
    await useApi(`/attempts/${attemptId.value}/auto-save`, {
      method: 'POST',
      body: { answers: answers.value },
    })
    autoSaveStatus.value = t('exam.saved')
    setTimeout(() => { autoSaveStatus.value = '' }, 2000)
  }
  catch {
    autoSaveStatus.value = t('exam.saveFail')
  }
}

function askSubmit() {
  confirm.require({
    message: t('exam.confirmSubmit', { answered: answeredCount.value, total: questions.value.length }),
    header: t('exam.submitTitle'),
    icon: 'pi pi-send',
    accept: () => submitExam(false),
  })
}

async function submitExam(auto = false) {
  if (!attemptId.value || submitting.value) return
  submitting.value = true
  if (timerInterval) clearInterval(timerInterval)
  unbindProctor()
  stopFaceMonitor()
  stopPhoneMonitor()
  try {
    const res = await useApi<{ attempt_id?: number, score?: number }>(`/exams/${examId.value}/submit`, {
      method: 'POST',
      body: { attempt_id: attemptId.value, answers: answers.value },
    })
    toast.add({
      severity: 'success',
      summary: auto ? t('exam.timeUp') : t('exam.submitOk'),
      detail: res.score !== undefined ? `${t('exam.score')}: ${res.score}` : undefined,
      life: 3500,
    })
    await navigateTo(`/exam/result/${res.attempt_id || attemptId.value}?exam=${examId.value}`)
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('exam.submitFail'), detail: e?.data?.message, life: 4000 })
    submitting.value = false
  }
}

/** Grabs the current frame of the background monitor camera as a JPEG data URL — used as violation evidence. */
function captureMonitorSnapshot(): string | null {
  if (!monitorVideo || !monitorVideo.videoWidth) return null
  if (!monitorCanvas) monitorCanvas = document.createElement('canvas')
  monitorCanvas.width = monitorVideo.videoWidth
  monitorCanvas.height = monitorVideo.videoHeight
  const ctx = monitorCanvas.getContext('2d')
  if (!ctx) return null
  ctx.drawImage(monitorVideo, 0, 0, monitorCanvas.width, monitorCanvas.height)
  return monitorCanvas.toDataURL('image/jpeg', 0.7)
}

async function logViolation(type: string, severity: 'warning' | 'critical', metadata: Record<string, unknown>) {
  if (!attemptId.value) return
  // Snapshot evidence only for critical violations — keeps storage bounded.
  const image = severity === 'critical' ? captureMonitorSnapshot() : null
  try {
    await useApi(`/attempts/${attemptId.value}/violations`, {
      method: 'POST',
      body: { type, severity, metadata, ...(image ? { image } : {}) },
    })
  }
  catch { /* ignore offline */ }
}

function onVisibility() {
  if (status.value !== 'in_progress') return

  if (document.hidden) {
    const now = Date.now()
    if (now - lastFocusLogAt < FOCUS_LOSS_COOLDOWN_MS) return
    lastFocusLogAt = now

    focusLoss.value++
    const critical = focusLoss.value >= MAX_FOCUS_LOSS
    logViolation('focus_lost', critical ? 'critical' : 'warning', { count: focusLoss.value, max: MAX_FOCUS_LOSS })
    pendingFocusBanner = true
    if (critical) submitExam(true)
    return
  }

  if (pendingFocusBanner) {
    pendingFocusBanner = false
    focusBannerVisible.value = true
  }
}

function dismissFocusBanner() {
  focusBannerVisible.value = false
}

function bindProctor() {
  if (!import.meta.client) return
  document.addEventListener('visibilitychange', onVisibility)
}

function unbindProctor() {
  if (!import.meta.client) return
  document.removeEventListener('visibilitychange', onVisibility)
}

// ── Continuous webcam monitoring (behavior-based cheating detection) ────────
// Runs independently of the pre-exam face-verification camera (that stream is
// torn down once verified). Periodically detects faces + landmarks in frame
// client-side (face-api.js) — one detector call per tick serves both the
// face count (no_face/multiple_faces) and, for a single face, a head-pose
// check against the baseline captured at verification (looking_away).
// Violations report through the same endpoint the pre-exam check and
// focus-loss use.
const MONITOR_INTERVAL_MS = 8000
const MONITOR_COOLDOWN_MS = 30000
const MONITOR_STREAK_TO_FLAG = 2
const LOOK_AWAY_STREAK_TO_FLAG = 4
const LOOK_AWAY_COOLDOWN_MS = 45000
const GAZE_YAW_THRESHOLD = 0.35
const GAZE_PITCH_THRESHOLD = 0.3
let noFaceStreak = 0
let multiFaceStreak = 0
let lookAwayStreak = 0
let lastNoFaceLogAt = 0
let lastMultiFaceLogAt = 0
let lastLookAwayLogAt = 0

async function startFaceMonitor() {
  if (!import.meta.client || monitorInterval) return
  try {
    await loadFaceModels()
    monitorStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 480 }, height: { ideal: 360 } },
      audio: false,
    })
    await nextTick()
    monitorVideo = monitorVideoRef.value
    if (!monitorVideo) return
    monitorVideo.srcObject = monitorStream
    monitorVideo.muted = true
    monitorVideo.playsInline = true
    await monitorVideo.play().catch(() => {})
    monitorActive.value = true
  }
  catch {
    // No camera available for continuous monitoring — proctoring still
    // relies on focus-loss detection; don't block the exam over this.
    return
  }

  monitorInterval = setInterval(async () => {
    if (status.value !== 'in_progress' || !monitorVideo) return
    let faces: Awaited<ReturnType<typeof detectFacesWithLandmarks>> = []
    try {
      faces = await detectFacesWithLandmarks(monitorVideo)
    }
    catch {
      return
    }

    const now = Date.now()
    const count = faces.length

    if (count === 0) {
      noFaceStreak++
      multiFaceStreak = 0
      lookAwayStreak = 0
      if (noFaceStreak >= MONITOR_STREAK_TO_FLAG && now - lastNoFaceLogAt > MONITOR_COOLDOWN_MS) {
        lastNoFaceLogAt = now
        logViolation('no_face', 'warning', { streak: noFaceStreak })
        toast.add({ severity: 'warn', summary: t('exam.faceCheck.noFaceDuringExam'), life: 4000 })
      }
      return
    }

    if (count > 1) {
      multiFaceStreak++
      noFaceStreak = 0
      lookAwayStreak = 0
      if (multiFaceStreak >= MONITOR_STREAK_TO_FLAG && now - lastMultiFaceLogAt > MONITOR_COOLDOWN_MS) {
        lastMultiFaceLogAt = now
        logViolation('multiple_faces', 'critical', { count, streak: multiFaceStreak })
        toast.add({ severity: 'error', summary: t('exam.faceCheck.multipleFacesDuringExam'), life: 4000 })
      }
      return
    }

    noFaceStreak = 0
    multiFaceStreak = 0

    if (!gazeBaseline.value) return
    const primary = faces.reduce((a, b) => (a.detection.box.area >= b.detection.box.area ? a : b))
    const offset = estimateGazeOffset(primary.landmarks)
    if (!offset) return

    const yawDelta = Math.abs(offset.yaw - gazeBaseline.value.yaw)
    const pitchDelta = Math.abs(offset.pitch - gazeBaseline.value.pitch)
    if (yawDelta > GAZE_YAW_THRESHOLD || pitchDelta > GAZE_PITCH_THRESHOLD) {
      lookAwayStreak++
      if (lookAwayStreak >= LOOK_AWAY_STREAK_TO_FLAG && now - lastLookAwayLogAt > LOOK_AWAY_COOLDOWN_MS) {
        lastLookAwayLogAt = now
        logViolation('looking_away', 'warning', { yaw_delta: yawDelta, pitch_delta: pitchDelta, streak: lookAwayStreak })
        toast.add({ severity: 'warn', summary: t('exam.faceCheck.lookingAwayDuringExam'), life: 4000 })
      }
    }
    else {
      lookAwayStreak = 0
    }
  }, MONITOR_INTERVAL_MS)
}

function stopFaceMonitor() {
  if (monitorInterval) {
    clearInterval(monitorInterval)
    monitorInterval = null
  }
  monitorVideo?.pause()
  if (monitorVideoRef.value) monitorVideoRef.value.srcObject = null
  monitorVideo = null
  monitorStream?.getTracks().forEach(track => track.stop())
  monitorStream = null
  monitorActive.value = false
}

// ── Phone detection (separate, thinner pipeline) ────────────────────────────
// Reuses the same background monitor camera stream (started by
// startFaceMonitor) but runs on its own tick, offset from the face-monitor
// tick, so the two heavier client-side inferences don't land in the same
// frame and stall a slow machine.
const PHONE_CHECK_INTERVAL_MS = 6000
const PHONE_STREAK_TO_FLAG = 2
const PHONE_COOLDOWN_MS = 30000
let phoneStreak = 0
let lastPhoneLogAt = 0

function startPhoneMonitor() {
  if (!import.meta.client || phoneCheckInterval) return

  phoneCheckInterval = setInterval(async () => {
    if (status.value !== 'in_progress' || !monitorVideo) return
    let result: { found: boolean, score: number }
    try {
      result = await detectPhone(monitorVideo)
    }
    catch {
      // Detector failed to load or run (e.g. underpowered device) — skip
      // phone checks silently, don't block the exam over it.
      return
    }

    const now = Date.now()
    if (result.found) {
      phoneStreak++
      if (phoneStreak >= PHONE_STREAK_TO_FLAG && now - lastPhoneLogAt > PHONE_COOLDOWN_MS) {
        lastPhoneLogAt = now
        logViolation('phone_detected', 'critical', { score: result.score, streak: phoneStreak })
        toast.add({ severity: 'error', summary: t('exam.faceCheck.phoneDetectedDuringExam'), life: 4000 })
      }
    }
    else {
      phoneStreak = 0
    }
  }, PHONE_CHECK_INTERVAL_MS)
}

function stopPhoneMonitor() {
  if (phoneCheckInterval) {
    clearInterval(phoneCheckInterval)
    phoneCheckInterval = null
  }
}

onMounted(runPreCheck)
onBeforeUnmount(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  unbindProctor()
  stopFaceMonitor()
  stopPhoneMonitor()
})
</script>

<template>
  <div class="exam-shell">
    <ConfirmDialog />
    <Toast />

    <div class="monitor-widget" :class="{ show: monitorActive }">
      <video ref="monitorVideoRef" autoplay playsinline muted class="monitor-video" />
      <div class="monitor-badge"><span class="monitor-dot" />{{ t('exam.proctoringActive') }}</div>
    </div>

    <div
      v-if="focusBannerVisible && focusLoss"
      class="focus-banner"
      :class="{ critical: focusLoss >= MAX_FOCUS_LOSS }"
    >
      <i class="pi pi-exclamation-triangle" />
      <span>
        {{ focusLoss >= MAX_FOCUS_LOSS
          ? t('exam.focusBannerCritical', { max: MAX_FOCUS_LOSS })
          : t('exam.focusBanner', { n: focusLoss, max: MAX_FOCUS_LOSS }) }}
      </span>
      <button type="button" class="focus-dismiss" :aria-label="t('common.close')" @click="dismissFocusBanner">
        <i class="pi pi-times" />
      </button>
    </div>

    <div v-if="preChecking" class="center">{{ t('exam.faceCheck.checkingAccess') }}</div>
    <div v-else-if="preCheckError" class="center error-box">
      <p>{{ preCheckError }}</p>
      <Button :label="t('exam.backExams')" severity="secondary" @click="navigateTo('/student/exams')" />
    </div>

    <div v-else-if="requiresFaceCheck && !faceVerified" class="center face-gate">
      <div class="face-gate-card">
        <ExamFaceVerification
          :exam-id="examId"
          :has-face-url="hasFaceUrl"
          :face-photo-url="facePhotoUrl"
          :can-enroll-face="canEnrollFace"
          @verified="onFaceVerified"
        />
      </div>
    </div>

    <div v-else-if="loading" class="center">{{ t('exam.loading') }}</div>
    <div v-else-if="error" class="center error-box">
      <p>{{ error }}</p>
      <Button :label="t('exam.back')" severity="secondary" @click="navigateTo('/student/exams')" />
    </div>

    <div v-else class="exam-workspace">
      <header class="exam-topbar">
        <div class="exam-topbar__title">
          <p class="exam-kicker">{{ t('exam.workspaceKicker') }}</p>
          <h1>{{ examTitle || t('exam.title') }}</h1>
          <div class="exam-meta">
            <span>{{ t('exam.answeredMeta', { answered: answeredCount, total: questions.length }) }}</span>
            <span>{{ t('exam.unansweredMeta', { n: unansweredCount }) }}</span>
            <span v-if="bookmarkedCount > 0">{{ t('exam.bookmarkedMeta', { n: bookmarkedCount }) }}</span>
            <span v-if="autoSaveStatus" class="autosave">{{ autoSaveStatus }}</span>
          </div>
          <div v-if="focusLoss" class="exam-warning">
            <i class="pi pi-exclamation-triangle" />
            {{ t('exam.focusCount', { n: focusLoss, max: MAX_FOCUS_LOSS }) }}
          </div>
        </div>
        <div class="exam-topbar__actions">
          <span v-if="proctoring" class="proctor-badge" :title="t('exam.proctoringActive')">
            <i class="pi pi-video" /> {{ t('exam.proctoringActive') }}
          </span>
          <div class="exam-timer" :class="{ urgent: timerUrgent }">
            <i class="pi pi-clock" />
            <strong>{{ timerDisplay }}</strong>
          </div>
          <button type="button" class="exam-submit-btn" :disabled="loading || submitting || !!error" @click="askSubmit">
            {{ submitting ? '…' : t('exam.submit') }}
          </button>
        </div>
      </header>

      <div class="exam-layout">
        <aside class="exam-sidebar">
          <div class="exam-sidebar__card">
            <h3>{{ t('exam.navTitle') }}</h3>
            <p>{{ t('exam.navHint') }}</p>
            <div class="question-nav">
              <button
                v-for="(q, i) in questions"
                :key="q.id"
                type="button"
                class="q-nav-btn"
                :class="{
                  active: i === currentIndex,
                  answered: isQuestionAnswered(q.id),
                  bookmarked: !!bookmarks[q.id],
                }"
                :title="bookmarks[q.id] ? t('exam.bookmarked') : ''"
                @click="currentIndex = i"
              >
                <span>{{ i + 1 }}</span>
                <i v-if="bookmarks[q.id]" class="pi pi-bookmark-fill q-nav-flag" aria-hidden="true" />
              </button>
            </div>
            <div class="question-nav-legend">
              <span><i class="legend-dot legend-answered" /> {{ t('exam.legendAnswered') }}</span>
              <span><i class="legend-dot legend-bookmark" /> {{ t('exam.legendBookmark') }}</span>
            </div>
          </div>
        </aside>

        <main v-if="current" class="exam-main">
          <div class="question-panel">
            <div class="question-panel__header">
              <div>
                <p class="exam-kicker">{{ t('exam.questionOf', { n: currentIndex + 1, total: questions.length }) }}</p>
                <h2>{{ examTitle || t('exam.title') }}</h2>
              </div>
              <div class="question-panel__head-actions">
                <button
                  type="button"
                  class="bookmark-btn"
                  :class="{ 'is-active': isCurrentBookmarked }"
                  @click="toggleBookmark()"
                >
                  <i :class="isCurrentBookmarked ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'" />
                  <span>{{ isCurrentBookmarked ? t('exam.bookmarked') : t('exam.bookmark') }}</span>
                </button>
                <span class="question-type">{{ typeLabel(current.type) }}</span>
              </div>
            </div>

            <div class="question-content">{{ stripHtml(current.content) }}</div>

            <div v-if="current.type === 'multiple_choice'" class="answer-list">
              <label
                v-for="opt in current.answers || []"
                :key="opt.id"
                class="answer-option"
                :class="{ selected: (answers[current.id] || []).includes(opt.id) }"
              >
                <Checkbox
                  :model-value="(answers[current.id] || []).includes(opt.id)"
                  :binary="true"
                  @update:model-value="toggleMulti(current.id, opt.id)"
                />
                <span>{{ stripHtml(opt.content) }}</span>
              </label>
            </div>

            <div v-else-if="['single_choice', 'true_false'].includes(current.type)" class="answer-list">
              <label
                v-for="opt in current.answers || []"
                :key="opt.id"
                class="answer-option"
                :class="{ selected: answers[current.id] === opt.id }"
              >
                <RadioButton
                  :model-value="answers[current.id]"
                  :input-id="`a-${opt.id}`"
                  :value="opt.id"
                  @update:model-value="selectAnswer(current.id, $event)"
                />
                <label :for="`a-${opt.id}`">{{ stripHtml(opt.content) }}</label>
              </label>
            </div>

            <div v-else class="answer-input">
              <Textarea
                :model-value="answers[current.id] || ''"
                rows="5"
                auto-resize
                class="exam-text-input"
                :placeholder="t('exam.writeAnswer')"
                @update:model-value="selectAnswer(current.id, $event)"
              />
            </div>

            <div class="question-nav-buttons">
              <button type="button" class="nav-btn" :disabled="currentIndex === 0" @click="currentIndex--">
                ← {{ t('exam.prev') }}
              </button>
              <button
                v-if="currentIndex < questions.length - 1"
                type="button"
                class="nav-btn nav-btn--primary"
                @click="currentIndex++"
              >
                {{ t('exam.next') }} →
              </button>
              <button
                v-else
                type="button"
                class="exam-submit-btn"
                :disabled="submitting"
                @click="askSubmit"
              >
                {{ t('exam.submit') }}
              </button>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
.exam-shell {
  --green: #0f766e;
  --green-deep: #0d9488;
  --green-rgb: 15, 118, 110;
  min-height: 100vh;
  background: #f8fbff;
  color: #0f172a;
}
.exam-topbar {
  display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(var(--green-rgb), 0.12);
  background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(18px);
  position: sticky; top: 0; z-index: 10;
}
.exam-topbar__title h1 { margin: 0; font-size: 1.35rem; }
.exam-kicker {
  margin: 0 0 0.35rem; color: var(--green); font-size: 0.76rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.12em;
}
.exam-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.45rem; color: #64748b; font-size: 0.92rem; }
.exam-meta .autosave { color: var(--green); font-weight: 600; }
.exam-warning {
  display: flex; align-items: center; gap: 0.35rem; margin-top: 0.5rem;
  color: #dc2626; font-size: 0.8rem; font-weight: 700;
}
.exam-topbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.exam-timer {
  display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem;
  border-radius: 16px; background: #14213d; color: #fff;
  box-shadow: 0 10px 24px rgba(20, 33, 61, 0.16); font-variant-numeric: tabular-nums;
}
.exam-timer.urgent { background: #d71920; animation: pulse 1s infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(0.98); } }
.exam-submit-btn, .nav-btn { border: none; cursor: pointer; transition: 0.2s ease; font: inherit; }
.exam-submit-btn {
  padding: 0.9rem 1.2rem; border-radius: 14px; background: var(--green); color: #fff;
  font-weight: 800; box-shadow: 0 10px 24px rgba(var(--green-rgb), 0.22);
}
.exam-submit-btn:hover:not(:disabled), .nav-btn--primary:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.03); }
.exam-submit-btn:disabled, .nav-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.exam-layout {
  display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 1.5rem;
  padding: 1.5rem; max-width: 1280px; margin: 0 auto;
}
.exam-sidebar__card, .question-panel {
  border: 1px solid rgba(148, 163, 184, 0.18); border-radius: 24px;
  background: rgba(255, 255, 255, 0.92); box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);
}
.exam-sidebar__card { position: sticky; top: 1.5rem; padding: 1.25rem; align-self: start; }
.exam-sidebar__card h3 { margin: 0 0 0.45rem; }
.exam-sidebar__card p { margin: 0 0 1rem; color: #64748b; font-size: 0.92rem; line-height: 1.6; }

.question-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; }
.q-nav-btn {
  position: relative; min-height: 44px; border: 1px solid #dbe6f5; border-radius: 14px;
  background: #fff; color: #475569; font-weight: 800; cursor: pointer;
}
.q-nav-btn.active { background: rgba(var(--green-rgb), 0.05); color: var(--green); border-color: #90caf9; }
.q-nav-btn.answered { background: #ecfdf3; color: var(--green-deep); border-color: #86efac; }
.q-nav-btn.active.answered { box-shadow: inset 0 0 0 1px var(--green); }
.q-nav-btn.bookmarked { border-color: #f59e0b; box-shadow: inset 0 0 0 1px #fbbf24; }
.q-nav-flag {
  position: absolute; top: -6px; right: -6px; display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%; background: #f59e0b; color: #fff; font-size: 10px;
}

.question-nav-legend {
  display: flex; flex-wrap: wrap; gap: 0.85rem; margin-top: 0.85rem; padding-top: 0.85rem;
  border-top: 1px dashed #dbe6f5; color: #64748b; font-size: 0.78rem;
}
.question-nav-legend span { display: inline-flex; align-items: center; gap: 0.4rem; }
.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; }
.legend-answered { background: #ecfdf3; border: 1px solid #86efac; }
.legend-bookmark { background: #fff; border: 1px solid #f59e0b; box-shadow: inset 0 0 0 1px #fbbf24; }

.question-panel { padding: 1.5rem; }
.question-panel__header, .question-nav-buttons { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.question-panel__header h2 { margin: 0; font-size: 1.15rem; }
.question-panel__head-actions { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
.bookmark-btn {
  display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 0.85rem;
  border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #64748b;
  font-weight: 700; font-size: 0.85rem; cursor: pointer;
}
.bookmark-btn.is-active { background: #fffbeb; border-color: #f59e0b; color: #b45309; }
.question-type {
  padding: 0.45rem 0.9rem; border-radius: 999px; background: rgba(var(--green-rgb), 0.05);
  color: #1558b0; font-size: 0.82rem; font-weight: 700;
}
.question-content {
  margin: 1.5rem 0; padding: 1.25rem; border-radius: 20px; background: #f8fbff;
  border: 1px solid #dbe6f5; font-size: 1.05rem; line-height: 1.75; white-space: pre-wrap;
}
.answer-list { display: grid; gap: 0.85rem; }
.answer-option {
  display: flex; align-items: flex-start; gap: 0.8rem; padding: 1rem 1.1rem;
  border: 1px solid #dbe6f5; border-radius: 18px; background: #fff; cursor: pointer; transition: 0.2s ease;
}
.answer-option:hover { border-color: #90caf9; background: #f8fbff; }
.answer-option.selected {
  border-color: var(--green); background: rgba(var(--green-rgb), 0.05);
  box-shadow: inset 0 0 0 1px rgba(var(--green-rgb), 0.12);
}
.answer-input { margin-top: 0.25rem; }
.exam-text-input {
  width: 100%; min-height: 56px; padding: 1rem 1.1rem; border: 1px solid #cbd5e1;
  border-radius: 18px; background: #fff; font: inherit; resize: vertical;
}
.question-nav-buttons { margin-top: 1.5rem; }
.nav-btn {
  padding: 0.9rem 1.2rem; border-radius: 14px; background: #fff; color: #334155;
  border: 1px solid #dbe6f5; font-weight: 700;
}
.nav-btn:hover:not(:disabled) { border-color: #90caf9; color: var(--green); }
.nav-btn--primary { background: #14213d; color: #fff; border-color: #14213d; }

.proctor-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700;
  background: color-mix(in srgb, #dc2626 12%, transparent); color: #b91c1c;
}
.focus-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 18px; font-size: .88rem; font-weight: 600;
  background: #fef3c7; color: #92400e; border-bottom: 1px solid #fde68a;
}
.focus-banner.critical { background: #fee2e2; color: #991b1b; border-bottom-color: #fecaca; }
.focus-banner span { flex: 1; }
.focus-dismiss {
  border: 0; background: transparent; color: inherit; cursor: pointer;
  width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center;
}
.focus-dismiss:hover { background: color-mix(in srgb, currentColor 12%, transparent); }
.monitor-widget {
  position: fixed; right: 18px; bottom: 18px; z-index: 40;
  width: 160px; border-radius: 14px; overflow: hidden;
  background: #0b1220; box-shadow: 0 8px 24px -8px rgba(0, 0, 0, .35);
  border: 1px solid rgba(255, 255, 255, .12);
  opacity: 0; transform: translateY(10px); pointer-events: none;
  transition: opacity .2s ease, transform .2s ease;
}
.monitor-widget.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
.monitor-video { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; transform: scaleX(-1); }
.monitor-badge {
  position: absolute; left: 8px; bottom: 8px;
  display: inline-flex; align-items: center; gap: 6px;
  padding: 3px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700;
  background: rgba(0, 0, 0, .55); color: #fff;
}
.monitor-dot { width: 7px; height: 7px; border-radius: 50%; background: #ef4444; animation: monitor-pulse 1.6s ease-in-out infinite; }
@keyframes monitor-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
.center { min-height: 60vh; display: grid; place-content: center; gap: 12px; text-align: center; }
.error-box { color: #b91c1c; }
.face-gate { padding: 16px; }
.face-gate-card {
  width: min(560px, 92vw); text-align: left; padding: 24px;
  border: 1px solid #d7e2df; border-radius: 18px; background: rgba(255, 255, 255, 0.96);
}
@media (max-width: 900px) {
  .exam-layout { grid-template-columns: 1fr; }
  .exam-sidebar__card { position: static; }
  .question-nav { grid-template-columns: repeat(6, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .monitor-widget { width: 108px; right: 10px; bottom: 10px; }
  .question-nav { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
</style>
