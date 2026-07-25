<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const attemptId = computed(() => String(route.params.attemptId))
const examId = computed(() => String(route.query.exam || ''))

const loading = ref(true)
const error = ref('')
const result = ref<{
  attempt_id?: number
  score?: number | null
  passed?: boolean | null
  status?: string
  student_answers?: Record<string, any>
} | null>(null)

async function load() {
  loading.value = true
  error.value = ''
  if (!examId.value) {
    error.value = t('exam.resultMissingExam')
    loading.value = false
    return
  }
  try {
    result.value = await useApi(`/exams/${examId.value}/results/${attemptId.value}`)
  }
  catch (e: any) {
    error.value = e?.data?.message || t('exam.resultError')
    toast.add({ severity: 'error', summary: t('exam.resultError'), detail: error.value, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('exam.resultTitle') }}</h1>
        <p>{{ t('exam.resultSubtitle') }}</p>
      </div>
      <Button :label="t('exam.backExams')" icon="pi pi-arrow-left" severity="secondary" outlined @click="navigateTo('/student/exams')" />
    </header>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="error" class="empty error">{{ error }}</div>
    <section v-else-if="result" class="card" :class="{ pass: result.passed, fail: result.passed === false }">
      <Tag
        v-if="result.passed !== null && result.passed !== undefined"
        :severity="result.passed ? 'success' : 'danger'"
        :value="result.passed ? t('student.transcript.pass') : t('student.transcript.fail')"
      />
      <div class="score-block">
        <span>{{ t('exam.score') }}</span>
        <strong>{{ result.score ?? '—' }}</strong>
      </div>
      <p class="muted">{{ t('exam.attemptStatus') }}: {{ result.status || 'submitted' }}</p>
      <div class="actions">
        <Button :label="t('student.menu.transcript')" icon="pi pi-list-check" @click="navigateTo('/student/transcript')" />
        <Button :label="t('exam.backExams')" severity="secondary" outlined @click="navigateTo('/student/exams')" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; max-width: 720px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.card {
  padding: 24px; border-radius: 16px; border: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  display: grid; gap: 12px; justify-items: start;
}
.card.pass { border-color: #6ee7b7; background: color-mix(in srgb, #d1fae5 35%, var(--surface)); }
.card.fail { border-color: #fca5a5; background: color-mix(in srgb, #fee2e2 35%, var(--surface)); }
.score-block span { display: block; color: var(--text-muted); font-size: .85rem; font-weight: 600; }
.score-block strong { font-size: 2.4rem; line-height: 1; }
.muted { margin: 0; color: var(--text-muted); }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
.error { color: #b91c1c; }
</style>
