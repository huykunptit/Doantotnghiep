<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const props = defineProps<{ courseId: number, lessonId: number }>()
const emit = defineEmits<{ completed: [] }>()
const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const submitting = ref(false)
const quizId = ref<number | null>(null)
const attemptId = ref<number | null>(null)
const questions = ref<any[]>([])
const answers = reactive<Record<number, number[] | string>>({})
const result = ref<{ score?: number, passed?: boolean } | null>(null)

async function load() {
  loading.value = true
  result.value = null
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

async function submit() {
  if (!quizId.value || !attemptId.value) return
  submitting.value = true
  try {
    const payload: Record<string, unknown> = {}
    for (const [qid, val] of Object.entries(answers)) payload[qid] = val
    const res = await useApi<any>(`/courses/${props.courseId}/lessons/${props.lessonId}/quiz/${quizId.value}/submit`, {
      method: 'POST',
      body: { attempt_id: attemptId.value, answers: payload },
    })
    result.value = { score: res.score ?? res.attempt?.score, passed: res.passed ?? res.attempt?.passed }
    emit('completed')
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.learn.quizSubmitError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    submitting.value = false
  }
}

watch(() => [props.courseId, props.lessonId], load, { immediate: true })
</script>

<template>
  <div class="quiz">
    <div v-if="loading" class="empty">…</div>
    <template v-else-if="result">
      <div class="result" :class="{ pass: result.passed }">
        <strong>{{ t('student.learn.quizScore', { score: result.score ?? '—' }) }}</strong>
        <span>{{ result.passed ? t('student.learn.quizPass') : t('student.learn.quizFail') }}</span>
      </div>
    </template>
    <template v-else>
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
      <Button :label="t('student.learn.quizSubmit')" icon="pi pi-check" :loading="submitting" :disabled="!questions.length" @click="submit" />
    </template>
  </div>
</template>

<style scoped>
.quiz { display: grid; gap: 14px; }
.q { padding: 14px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle); }
.q-content { font-weight: 650; margin-bottom: 10px; }
.choices { display: grid; gap: 8px; }
.choice { display: flex; gap: 10px; align-items: flex-start; font-weight: 500; cursor: pointer; }
.result { padding: 16px; border-radius: 12px; background: #fee2e2; color: #b91c1c; display: grid; gap: 4px; }
.result.pass { background: #dcfce7; color: #15803d; }
.w-full { width: 100%; }
.empty { color: var(--text-muted); }
</style>
