<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const props = defineProps<{ courseId: number, lessonId: number }>()
const emit = defineEmits<{ completed: [] }>()
const { t } = useI18n()
const toast = useToast()

const MAX_FOCUS_LOSS = 3

const loading = ref(true)
const submitting = ref(false)
const quizId = ref<number | null>(null)
const attemptId = ref<number | null>(null)
const questions = ref<any[]>([])
const answers = reactive<Record<number, number[] | string>>({})
const result = ref<{ score?: number, passed?: boolean } | null>(null)
const review = ref<any[]>([])
const focusLoss = ref(0)
const autoSubmitted = ref(false)
let lastFocusLogAt = 0

async function load() {
  loading.value = true
  result.value = null
  review.value = []
  focusLoss.value = 0
  autoSubmitted.value = false
  try {
    const res = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/quiz`)
    quizId.value = res.quiz?.id || res.id || null
    attemptId.value = res.attempt_id || null
    questions.value = res.questions || res.quiz?.questions || []
    for (const q of questions.value) {
      answers[q.id] = q.type === 'short_answer' ? '' : []
    }
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.learn.quizLoadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function toggleChoice(qid: number, aid: number, single: boolean) {
  if (single) {
    answers[qid] = [aid]
    return
  }
  const cur = Array.isArray(answers[qid]) ? [...answers[qid] as number[]] : []
  const idx = cur.indexOf(aid)
  if (idx >= 0) cur.splice(idx, 1)
  else cur.push(aid)
  answers[qid] = cur
}

async function logFocusLoss() {
  if (!attemptId.value || result.value) return
  const now = Date.now()
  if (now - lastFocusLogAt < 1200) return
  lastFocusLogAt = now
  focusLoss.value += 1
  const remaining = Math.max(0, MAX_FOCUS_LOSS - focusLoss.value)
  const critical = focusLoss.value >= MAX_FOCUS_LOSS

  try {
    await useApi(`/attempts/${attemptId.value}/violations`, {
      method: 'POST',
      body: {
        type: 'focus_lost',
        severity: critical ? 'critical' : 'warning',
        metadata: { count: focusLoss.value, max: MAX_FOCUS_LOSS },
      },
    })
  }
  catch { /* ignore offline */ }

  toast.add({
    severity: critical ? 'error' : 'warn',
    summary: t('student.learn.focusWarnTitle'),
    detail: critical
      ? t('student.learn.focusWarnCritical', { n: focusLoss.value })
      : t('student.learn.focusWarn', { n: focusLoss.value, left: remaining }),
    life: 4000,
  })

  if (critical && !autoSubmitted.value) {
    autoSubmitted.value = true
    await submit(true)
  }
}

function onVisibility() {
  if (document.hidden) logFocusLoss()
}

async function enterFullscreen() {
  try {
    if (!document.fullscreenElement) {
      await document.documentElement.requestFullscreen?.()
    }
  }
  catch { /* browser may block */ }
}

async function submit(fromAuto = false) {
  if (!quizId.value || !attemptId.value || submitting.value || result.value) return
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {}
    for (const [qid, val] of Object.entries(answers)) payload[qid] = val
    const res = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/quiz/${quizId.value}/submit`, {
      method: 'POST',
      body: { attempt_id: attemptId.value, answers: payload },
    })
    result.value = { score: res.score ?? res.attempt?.score, passed: res.passed ?? res.attempt?.passed }
    review.value = res.review || []
    if (fromAuto) {
      toast.add({ severity: 'error', summary: t('student.learn.focusAutoSubmit'), life: 4500 })
    }
    emit('completed')
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.learn.quizSubmitError'), detail: error?.data?.message, life: 3500 })
    if (fromAuto) autoSubmitted.value = false
  }
  finally {
    submitting.value = false
  }
}

function choiceClass(q: any, a: any) {
  if (!result.value) return ''
  const submitted = answers[q.id]
  const picked = Array.isArray(submitted) ? submitted.includes(a.id) : false
  if (a.is_correct) return 'correct'
  if (picked) return 'wrong'
  return ''
}

watch(() => [props.courseId, props.lessonId], load, { immediate: true })

onMounted(() => {
  document.addEventListener('visibilitychange', onVisibility)
})

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisibility)
})
</script>

<template>
  <div class="quiz">
    <div v-if="loading" class="empty">…</div>
    <template v-else-if="result">
      <div class="result" :class="{ pass: result.passed }">
        <strong>{{ t('student.learn.quizScore', { score: result.score ?? '—' }) }}</strong>
        <span>{{ result.passed ? t('student.learn.quizPass') : t('student.learn.quizFail') }}</span>
        <span v-if="focusLoss" class="meta">{{ t('student.learn.focusCount', { n: focusLoss }) }}</span>
      </div>

      <section v-if="review.length" class="review">
        <h3>{{ t('student.learn.quizReview') }}</h3>
        <article v-for="(q, idx) in review" :key="q.id" class="q" :class="{ ok: q.is_correct, bad: !q.is_correct }">
          <div class="q-head">
            <span>{{ t('student.learn.quizQ', { n: idx + 1 }) }}</span>
            <Tag :value="q.is_correct ? t('student.learn.quizPass') : t('student.learn.quizFail')" :severity="q.is_correct ? 'success' : 'danger'" />
          </div>
          <div class="q-content" v-html="q.content" />
          <div class="choices">
            <div
              v-for="a in (q.answers || [])"
              :key="a.id"
              class="choice static"
              :class="choiceClass(q, a)"
            >
              <span v-html="a.content" />
            </div>
          </div>
        </article>
      </section>
    </template>
    <template v-else>
      <div class="proctor-bar">
        <span>{{ t('student.learn.focusPolicy', { n: MAX_FOCUS_LOSS }) }}</span>
        <Button :label="t('student.learn.enterFs')" icon="pi pi-window-maximize" size="small" text @click="enterFullscreen" />
      </div>
      <div v-for="q in questions" :key="q.id" class="q">
        <div class="q-content" v-html="q.content" />
        <div v-if="q.type === 'short_answer'" class="choices">
          <InputText v-model="(answers[q.id] as string)" class="w-full" />
        </div>
        <div v-else class="choices">
          <label v-for="a in (q.answers || [])" :key="a.id" class="choice">
            <input
              :type="q.type === 'multiple_choice' ? 'checkbox' : 'radio'"
              :name="`q-${q.id}`"
              :checked="Array.isArray(answers[q.id]) && (answers[q.id] as number[]).includes(a.id)"
              @change="toggleChoice(q.id, a.id, q.type !== 'multiple_choice')"
            >
            <span v-html="a.content" />
          </label>
        </div>
      </div>
      <Button :label="t('student.learn.quizSubmit')" icon="pi pi-check" :loading="submitting" :disabled="!questions.length" @click="submit(false)" />
    </template>
  </div>
</template>

<style scoped>
.quiz { display: grid; gap: 14px; }
.proctor-bar {
  display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap;
  padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border);
  background: color-mix(in srgb, var(--brand) 8%, var(--surface)); font-weight: 600; font-size: .9rem;
}
.q { padding: 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle); }
.q.ok { border-color: #86efac; }
.q.bad { border-color: #fca5a5; }
.q-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.q-content { font-weight: 650; margin-bottom: 10px; }
.choices { display: grid; gap: 8px; }
.choice { display: flex; gap: 10px; align-items: flex-start; font-weight: 500; cursor: pointer; }
.choice.static { cursor: default; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); }
.choice.correct { border-color: #86efac; background: #f0fdf4; }
.choice.wrong { border-color: #fca5a5; background: #fef2f2; }
.result { padding: 16px; border-radius: 12px; background: #fee2e2; color: #b91c1c; display: grid; gap: 4px; }
.result.pass { background: #dcfce7; color: #15803d; }
.result .meta { font-size: .85rem; opacity: .85; }
.review h3 { margin: 0 0 4px; }
.w-full { width: 100%; }
.empty { color: var(--text-muted); }
</style>
