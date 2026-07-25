<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

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
const attemptId = ref<number | null>(null)
const remainingTime = ref<number | null>(null)
const status = ref('in_progress')
const questions = ref<QuestionItem[]>([])
const answers = ref<Record<string, any>>({})
const currentIndex = ref(0)
const focusLoss = ref(0)
const MAX_FOCUS_LOSS = 3
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

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
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
    if (proctoring.value) bindProctor()
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

function onVisibility() {
  if (document.hidden && status.value === 'in_progress') {
    focusLoss.value++
    toast.add({
      severity: 'warn',
      summary: t('exam.focusWarn'),
      detail: t('exam.focusCount', { n: focusLoss.value, max: MAX_FOCUS_LOSS }),
      life: 4000,
    })
    if (focusLoss.value >= MAX_FOCUS_LOSS) submitExam(true)
  }
}

function bindProctor() {
  if (!import.meta.client) return
  document.addEventListener('visibilitychange', onVisibility)
}

function unbindProctor() {
  if (!import.meta.client) return
  document.removeEventListener('visibilitychange', onVisibility)
}

onMounted(loadExam)
onBeforeUnmount(() => {
  if (timerInterval) clearInterval(timerInterval)
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  unbindProctor()
})
</script>

<template>
  <div class="exam-shell">
    <ConfirmDialog />
    <Toast />

    <header class="exam-bar">
      <div class="left">
        <strong>{{ examTitle || t('exam.title') }}</strong>
        <small v-if="autoSaveStatus">{{ autoSaveStatus }}</small>
      </div>
      <div class="right">
        <span class="progress">{{ answeredCount }}/{{ questions.length }}</span>
        <span class="timer" :class="{ urgent: timerUrgent }"><i class="pi pi-clock" /> {{ timerDisplay }}</span>
        <Button :label="t('exam.submit')" icon="pi pi-send" :loading="submitting" :disabled="loading || !!error" @click="askSubmit" />
      </div>
    </header>

    <div v-if="loading" class="center">{{ t('exam.loading') }}</div>
    <div v-else-if="error" class="center error-box">
      <p>{{ error }}</p>
      <Button :label="t('exam.back')" severity="secondary" @click="navigateTo('/student/exams')" />
    </div>

    <div v-else class="exam-body">
      <aside class="nav">
        <button
          v-for="(q, i) in questions"
          :key="q.id"
          type="button"
          class="nav-btn"
          :class="{
            active: i === currentIndex,
            done: answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== '',
          }"
          @click="currentIndex = i"
        >
          {{ i + 1 }}
        </button>
      </aside>

      <main v-if="current" class="question">
        <div class="q-meta">
          <Tag :value="typeLabel(current.type)" severity="secondary" />
          <span>{{ t('exam.questionN', { n: currentIndex + 1 }) }}</span>
        </div>
        <h2>{{ stripHtml(current.content) }}</h2>

        <div v-if="current.type === 'multiple_choice'" class="opts">
          <label v-for="opt in current.answers || []" :key="opt.id" class="opt">
            <Checkbox
              :model-value="(answers[current.id] || []).includes(opt.id)"
              :binary="true"
              @update:model-value="toggleMulti(current.id, opt.id)"
            />
            <span>{{ stripHtml(opt.content) }}</span>
          </label>
        </div>

        <div v-else-if="['single_choice', 'true_false'].includes(current.type)" class="opts">
          <label v-for="opt in current.answers || []" :key="opt.id" class="opt">
            <RadioButton
              :model-value="answers[current.id]"
              :input-id="`a-${opt.id}`"
              :value="opt.id"
              @update:model-value="selectAnswer(current.id, $event)"
            />
            <label :for="`a-${opt.id}`">{{ stripHtml(opt.content) }}</label>
          </label>
        </div>

        <div v-else class="text-ans">
          <Textarea
            :model-value="answers[current.id] || ''"
            rows="5"
            auto-resize
            class="w-full"
            :placeholder="t('exam.writeAnswer')"
            @update:model-value="selectAnswer(current.id, $event)"
          />
        </div>

        <div class="q-actions">
          <Button :label="t('exam.prev')" icon="pi pi-arrow-left" severity="secondary" outlined :disabled="currentIndex === 0" @click="currentIndex--" />
          <Button
            v-if="currentIndex < questions.length - 1"
            :label="t('exam.next')"
            icon="pi pi-arrow-right"
            icon-pos="right"
            @click="currentIndex++"
          />
          <Button v-else :label="t('exam.submit')" icon="pi pi-send" :loading="submitting" @click="askSubmit" />
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
.exam-shell {
  min-height: 100vh;
  background: linear-gradient(160deg, #0f766e12, #0b122014 40%, #fff 100%);
  color: var(--text, #10221f);
}
.exam-bar {
  display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center;
  padding: 12px 18px; border-bottom: 1px solid var(--border, #d7e2df);
  background: color-mix(in srgb, var(--surface, #fff) 92%, transparent);
  position: sticky; top: 0; z-index: 10;
}
.left { display: grid; gap: 2px; }
.left strong { font-size: 1.05rem; }
.left small { color: var(--text-muted, #5b6f6b); }
.right { display: flex; align-items: center; gap: 12px; }
.progress { font-weight: 700; }
.timer { font-weight: 800; font-variant-numeric: tabular-nums; display: inline-flex; gap: 6px; align-items: center; }
.timer.urgent { color: #dc2626; }
.center { min-height: 60vh; display: grid; place-content: center; gap: 12px; text-align: center; }
.error-box { color: #b91c1c; }
.exam-body {
  display: grid; grid-template-columns: 88px 1fr; gap: 16px;
  max-width: 1100px; margin: 0 auto; padding: 16px;
}
.nav { display: flex; flex-direction: column; gap: 6px; }
.nav-btn {
  width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border, #d7e2df);
  background: #fff; cursor: pointer; font-weight: 700;
}
.nav-btn.active { border-color: #0f766e; background: #0f766e; color: #fff; }
.nav-btn.done:not(.active) { background: #d1fae5; border-color: #6ee7b7; }
.question {
  border: 1px solid var(--border, #d7e2df); border-radius: 16px; padding: 20px;
  background: color-mix(in srgb, #fff 94%, transparent);
}
.q-meta { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; color: var(--text-muted, #5b6f6b); font-size: .85rem; }
.question h2 { margin: 0 0 16px; font-size: 1.15rem; line-height: 1.5; }
.opts { display: grid; gap: 8px; }
.opt {
  display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px;
  border: 1px solid var(--border, #d7e2df); border-radius: 12px; cursor: pointer;
}
.opt:hover { border-color: #0f766e; }
.text-ans { margin-top: 4px; }
.w-full { width: 100%; }
.q-actions { display: flex; justify-content: space-between; gap: 10px; margin-top: 20px; }
@media (max-width: 720px) {
  .exam-body { grid-template-columns: 1fr; }
  .nav { flex-direction: row; flex-wrap: wrap; }
}
</style>
