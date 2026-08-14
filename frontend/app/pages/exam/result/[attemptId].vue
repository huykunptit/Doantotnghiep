<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface AnswerOpt {
  id: number
  content: string
  is_correct?: boolean
}

interface ReviewQuestion {
  id: number
  content: string
  type: string
  is_correct?: boolean
  answers?: AnswerOpt[]
}

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
  exam_title?: string
  student_answers?: Record<string, any>
  questions?: ReviewQuestion[]
  overall_feedback?: string
} | null>(null)

const showMyAnswers = ref(true)
const showCorrectAnswers = ref(false)

const reviewQuestions = computed(() => result.value?.questions || [])
const hasReview = computed(() => reviewQuestions.value.length > 0)
const canShowCorrect = computed(() =>
  reviewQuestions.value.some(q => (q.answers || []).some(a => a.is_correct !== undefined)),
)
const correctCount = computed(() => reviewQuestions.value.filter(q => q.is_correct === true).length)

function stripHtml(html: string) {
  return (html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
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

function studentAnswerFor(qid: number) {
  const answers = result.value?.student_answers || {}
  return answers[qid] ?? answers[String(qid)]
}

function isSelected(qid: number, optId: number) {
  const ans = studentAnswerFor(qid)
  if (Array.isArray(ans)) return ans.map(Number).includes(Number(optId))
  return Number(ans) === Number(optId)
}

function textAnswer(qid: number) {
  const ans = studentAnswerFor(qid)
  if (ans == null || ans === '') return t('exam.reviewNoAnswer')
  if (Array.isArray(ans)) return ans.join(', ')
  return String(ans)
}

function optionClass(q: ReviewQuestion, opt: AnswerOpt) {
  const selected = isSelected(q.id, opt.id)
  const revealCorrect = showCorrectAnswers.value && canShowCorrect.value && opt.is_correct
  return {
    selected: showMyAnswers.value && selected,
    correct: revealCorrect,
    wrong: showMyAnswers.value && selected && q.is_correct === false && !opt.is_correct,
  }
}

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
    showCorrectAnswers.value = false
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
        <p>{{ result?.exam_title || t('exam.resultSubtitle') }}</p>
      </div>
      <Button :label="t('exam.backExams')" icon="pi pi-arrow-left" severity="secondary" outlined @click="navigateTo('/student/exams')" />
    </header>

    <div v-if="loading" class="empty">{{ t('exam.loading') }}</div>
    <div v-else-if="error" class="empty error">{{ error }}</div>

    <template v-else-if="result">
      <section class="result-hero" :class="{ pass: result.passed, fail: result.passed === false }">
        <div class="score-ring" :class="{ pass: result.passed }">
          <strong>{{ result.score ?? '—' }}</strong>
          <span v-if="result.score != null">%</span>
        </div>
        <div class="hero-copy">
          <Tag
            v-if="result.passed !== null && result.passed !== undefined"
            :severity="result.passed ? 'success' : 'danger'"
            :value="result.passed ? t('student.transcript.pass') : t('student.transcript.fail')"
          />
          <h2>
            {{ result.passed
              ? t('exam.reviewPassed')
              : result.passed === false
                ? t('exam.reviewFailed')
                : t('exam.resultTitle') }}
          </h2>
          <p v-if="result.overall_feedback" class="feedback">{{ result.overall_feedback }}</p>
          <div class="hero-meta">
            <span>{{ t('exam.attemptStatus') }}: {{ result.status || 'submitted' }}</span>
            <span v-if="hasReview && result.questions?.some(q => q.is_correct !== undefined)">
              {{ t('exam.reviewCorrectCount', { n: correctCount, total: reviewQuestions.length }) }}
            </span>
          </div>
          <div class="actions">
            <Button :label="t('student.menu.transcript')" icon="pi pi-list-check" @click="navigateTo('/student/transcript')" />
            <Button :label="t('exam.backExams')" severity="secondary" outlined @click="navigateTo('/student/exams')" />
          </div>
        </div>
      </section>

      <section v-if="hasReview" class="review-panel">
        <div class="review-head">
          <div>
            <h3>{{ t('exam.reviewTitle') }}</h3>
            <p>{{ t('exam.reviewHint') }}</p>
          </div>
          <div class="toggles">
            <label class="toggle">
              <Checkbox v-model="showMyAnswers" :binary="true" />
              <span>{{ t('exam.reviewShowMine') }}</span>
            </label>
            <label v-if="canShowCorrect" class="toggle">
              <Checkbox v-model="showCorrectAnswers" :binary="true" />
              <span>{{ t('exam.reviewShowCorrect') }}</span>
            </label>
          </div>
        </div>

        <article
          v-for="(q, idx) in reviewQuestions"
          :key="q.id"
          class="review-q"
          :class="{
            ok: q.is_correct === true,
            bad: q.is_correct === false,
          }"
        >
          <div class="review-q__head">
            <span class="q-num">{{ idx + 1 }}</span>
            <span class="q-type">{{ typeLabel(q.type) }}</span>
            <span v-if="q.is_correct === true" class="q-flag ok">{{ t('exam.reviewCorrect') }}</span>
            <span v-else-if="q.is_correct === false" class="q-flag bad">{{ t('exam.reviewWrong') }}</span>
          </div>
          <div class="q-content">{{ stripHtml(q.content) }}</div>

          <div v-if="(q.answers || []).length" class="opt-list">
            <div
              v-for="opt in q.answers"
              :key="opt.id"
              class="opt"
              :class="optionClass(q, opt)"
            >
              <span class="opt-mark">
                <i v-if="showMyAnswers && isSelected(q.id, opt.id)" class="pi pi-check" />
              </span>
              <span>{{ stripHtml(opt.content) }}</span>
              <span v-if="showCorrectAnswers && canShowCorrect && opt.is_correct" class="badge-correct">
                {{ t('exam.reviewAnswerKey') }}
              </span>
            </div>
          </div>
          <div v-else-if="showMyAnswers" class="text-ans">
            <span class="label">{{ t('exam.reviewYourAnswer') }}</span>
            <p>{{ textAnswer(q.id) }}</p>
          </div>
        </article>
      </section>

      <section v-else class="no-review card">
        <p>{{ t('exam.reviewUnavailable') }}</p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; max-width: 960px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand, #0f766e); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted, #64748b); font-weight: 500; }

.result-hero {
  display: grid; grid-template-columns: auto 1fr; gap: 1.25rem; align-items: center;
  padding: 1.5rem; border-radius: 24px; border: 1px solid #e2e8f0; background: #fff;
}
.result-hero.pass { border-color: #86efac; background: linear-gradient(135deg, #f0fdf4, #fff); }
.result-hero.fail { border-color: #fca5a5; background: linear-gradient(135deg, #fef2f2, #fff); }
.score-ring {
  width: 112px; height: 112px; border-radius: 50%; display: grid; place-content: center;
  border: 8px solid #fecaca; color: #dc2626; background: #fff;
}
.score-ring.pass { border-color: #86efac; color: #15803d; }
.score-ring strong { font-size: 2rem; line-height: 1; }
.score-ring span { font-size: .9rem; font-weight: 700; text-align: center; }
.hero-copy h2 { margin: 0.4rem 0; font-size: 1.35rem; }
.feedback { margin: 0; color: #475569; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem; color: #64748b; font-size: .9rem; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }

.review-panel {
  display: grid; gap: 1rem; padding: 1.25rem; border-radius: 24px;
  border: 1px solid #e2e8f0; background: #fff;
}
.review-head { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: flex-start; }
.review-head h3 { margin: 0 0 0.25rem; }
.review-head p { margin: 0; color: #64748b; font-size: .92rem; }
.toggles { display: flex; flex-wrap: wrap; gap: 0.85rem; }
.toggle { display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 600; color: #475569; cursor: pointer; }

.review-q {
  padding: 1rem 1.1rem; border-radius: 18px; border: 1px solid #e2e8f0; background: #f8fafc;
  border-left: 4px solid #cbd5e1;
}
.review-q.ok { border-left-color: #22c55e; background: #f0fdf4; }
.review-q.bad { border-left-color: #ef4444; background: #fef2f2; }
.review-q__head { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.65rem; }
.q-num {
  width: 28px; height: 28px; border-radius: 50%; display: grid; place-content: center;
  background: rgba(15, 118, 110, 0.12); color: #0f766e; font-weight: 800; font-size: .85rem;
}
.q-type { font-size: .78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
.q-flag { font-size: .78rem; font-weight: 800; padding: 0.2rem 0.55rem; border-radius: 999px; }
.q-flag.ok { background: #dcfce7; color: #15803d; }
.q-flag.bad { background: #fee2e2; color: #b91c1c; }
.q-content { font-size: 1.02rem; line-height: 1.65; margin-bottom: 0.85rem; font-weight: 600; }

.opt-list { display: grid; gap: 0.55rem; }
.opt {
  display: flex; align-items: flex-start; gap: 0.65rem; padding: 0.75rem 0.9rem;
  border-radius: 14px; border: 1px solid #e2e8f0; background: #fff;
}
.opt.selected { border-color: #0f766e; background: rgba(15, 118, 110, 0.06); }
.opt.correct { border-color: #22c55e; background: #ecfdf3; }
.opt.wrong { border-color: #f87171; background: #fef2f2; }
.opt-mark { width: 18px; color: #0f766e; flex-shrink: 0; }
.badge-correct {
  margin-left: auto; font-size: .72rem; font-weight: 800; color: #15803d;
  background: #dcfce7; padding: 0.15rem 0.45rem; border-radius: 999px; white-space: nowrap;
}
.text-ans .label { display: block; font-size: .8rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem; }
.text-ans p { margin: 0; white-space: pre-wrap; }

.no-review, .card { padding: 1.25rem; border-radius: 16px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; }
.empty { padding: 36px; text-align: center; color: #64748b; }
.error { color: #b91c1c; }

@media (max-width: 640px) {
  .result-hero { grid-template-columns: 1fr; justify-items: center; text-align: center; }
  .actions { justify-content: center; }
}
</style>
