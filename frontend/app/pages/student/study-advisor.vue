<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface CourseRec {
  id: number
  title: string
  price?: number
  thumbnail?: string | null
  reason?: string
}

interface ApiCourseRec {
  id?: number
  title?: string
  price?: number
  thumbnail?: string | null
  reason?: string
  course?: {
    id: number
    title: string
    price?: number
    thumbnail?: string | null
  }
  reasons?: string[]
}

interface EvalSummary {
  has_curriculum?: boolean
  message?: string
  narrative?: string
  summary?: {
    level?: string
    completion_ratio?: number
    overall_gpa?: number | null
    ready_for_career_advice?: boolean
  }
  strengths?: Array<{ title: string, final_score: number }>
  weaknesses?: Array<{ title: string, final_score: number }>
  suggested_courses?: ApiCourseRec[]
  suggested_paths?: Array<{ id: number, title: string }>
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const tip = ref('')
const tipLoading = ref(false)
const evaluation = ref<EvalSummary | null>(null)
const recCourses = ref<CourseRec[]>([])

/** Phân loại theo thang GPA 4.0 (PTIT-style). */
function classifyGpa(gpa?: number | null) {
  if (gpa == null || Number.isNaN(gpa)) return 'none'
  if (gpa >= 3.6) return 'excellent'
  if (gpa >= 3.2) return 'very_good'
  if (gpa >= 2.5) return 'good'
  if (gpa >= 2.0) return 'average'
  if (gpa >= 1.0) return 'weak'
  return 'none'
}

const gpaClassLabel = computed(() => {
  const key = classifyGpa(evaluation.value?.summary?.overall_gpa)
  return t(`student.studyAdvisor.gpaClass.${key}`)
})

function normalizeCourseRec(item: ApiCourseRec): CourseRec | null {
  const course = item.course
  const id = course?.id ?? item.id
  const title = course?.title ?? item.title
  if (!id || !title) return null
  return {
    id,
    title,
    price: course?.price ?? item.price,
    thumbnail: course?.thumbnail ?? item.thumbnail,
    reason: item.reasons?.[0] || item.reason,
  }
}

async function load() {
  loading.value = true
  try {
    const evalRes = await useApi<EvalSummary>('/me/curriculum-evaluation')
    evaluation.value = evalRes
    recCourses.value = (evalRes.suggested_courses || [])
      .map(normalizeCourseRec)
      .filter((c): c is CourseRec => !!c)
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('student.studyAdvisor.loadError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function askTip() {
  tipLoading.value = true
  try {
    const res = await useApi<{ tip?: string, message?: string, reply?: string }>('/ai/tutoring', {
      method: 'POST',
      body: {
        context: 'study_roadmap',
        prompt: evaluation.value?.narrative || 'Gợi ý lộ trình học cải thiện dựa trên kết quả học tập.',
      },
    })
    tip.value = res.tip || res.message || res.reply || t('student.studyAdvisor.tipFallback')
  }
  catch {
    tip.value = evaluation.value?.narrative || t('student.studyAdvisor.tipFallback')
  }
  finally {
    tipLoading.value = false
  }
}

onMounted(async () => {
  await load()
  await askTip()
})
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.studyAdvisor.title') }}</h1>
        <p>{{ t('student.studyAdvisor.subtitle') }}</p>
      </div>
      <Button :label="t('career.title')" icon="pi pi-briefcase" severity="secondary" outlined @click="navigateTo('/career')" />
    </header>

    <div v-if="loading" class="empty">…</div>
    <template v-else>
      <section class="panel">
        <h2>{{ t('student.studyAdvisor.aiTip') }}</h2>
        <p>{{ tip || '…' }}</p>
        <Button :label="t('student.studyAdvisor.refreshTip')" icon="pi pi-refresh" size="small" text :loading="tipLoading" @click="askTip" />
      </section>

      <section v-if="evaluation?.has_curriculum" class="panel">
        <h2>{{ t('student.studyAdvisor.progress') }}</h2>
        <p>{{ evaluation.narrative }}</p>
        <div class="stats">
          <div><span>{{ t('student.studyAdvisor.completion') }}</span><strong>{{ Math.round((evaluation.summary?.completion_ratio || 0) * 100) }}%</strong></div>
          <div><span>GPA</span><strong>{{ evaluation.summary?.overall_gpa ?? '—' }}</strong></div>
          <div><span>{{ t('student.studyAdvisor.level') }}</span><strong>{{ gpaClassLabel }}</strong></div>
        </div>
        <div v-if="evaluation.weaknesses?.length" class="list">
          <h3>{{ t('student.studyAdvisor.weak') }}</h3>
          <ul>
            <li v-for="(w, i) in evaluation.weaknesses" :key="i">{{ w.title }} ({{ w.final_score }})</li>
          </ul>
        </div>
      </section>
      <section v-else class="panel">
        <p>{{ evaluation?.message || t('student.studyAdvisor.noCurriculum') }}</p>
      </section>

      <section class="panel">
        <h2>{{ t('student.studyAdvisor.courses') }}</h2>
        <p class="muted">{{ t('student.studyAdvisor.coursesHint') }}</p>
        <div v-if="!recCourses.length" class="empty">{{ t('student.studyAdvisor.noCourses') }}</div>
        <div v-else class="courses">
          <button v-for="c in recCourses" :key="c.id" type="button" class="course" @click="navigateTo(`/courses/${c.id}`)">
            <strong>{{ c.title }}</strong>
            <span>{{ c.reason || t('student.studyAdvisor.buyHint') }}</span>
          </button>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.panel { border: 1px solid var(--border); border-radius: 16px; padding: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 12px; }
.stats div { padding: 12px; border-radius: 12px; border: 1px solid var(--border); }
.stats span { display: block; color: var(--text-muted); font-size: .78rem; font-weight: 600; }
.stats strong { font-size: 1.3rem; }
.list h3 { margin: 14px 0 6px; font-size: .95rem; }
.muted { color: var(--text-muted); }
.courses { display: grid; gap: 8px; margin-top: 10px; }
.course {
  display: grid; gap: 2px; text-align: left; padding: 12px 14px; border-radius: 12px;
  border: 1px solid var(--border); background: transparent; cursor: pointer; font: inherit; color: inherit;
}
.course span { color: var(--text-muted); font-size: .85rem; }
.empty { padding: 24px; text-align: center; color: var(--text-muted); }
@media (max-width: 700px) { .stats { grid-template-columns: 1fr; } }
</style>
