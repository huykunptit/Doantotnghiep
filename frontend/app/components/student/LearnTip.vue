<script setup lang="ts">
const props = defineProps<{
  courseId: number
  lessonId?: number
  lessonTitle?: string | null
  lessonType?: string | null
  progressPercent?: number
}>()

const { t } = useI18n()
const loading = ref(false)
const summary = ref('')
const tips = ref<string[]>([])
const review = ref<string[]>([])
const source = ref<'ai' | 'heuristic' | ''>('')

async function load() {
  if (!props.courseId) return
  loading.value = true
  try {
    const res = await useApi<{
      summary?: string
      study_tips?: string[]
      review_lessons?: string[]
      source?: 'ai' | 'heuristic'
    }>('/ai/tutoring', {
      method: 'POST',
      body: {
        course_id: props.courseId,
        lesson_id: props.lessonId,
        lesson_title: props.lessonTitle,
        lesson_type: props.lessonType,
        progress_percent: props.progressPercent ?? 0,
      },
    })
    summary.value = res.summary || ''
    tips.value = (res.study_tips || []).slice(0, 3)
    review.value = (res.review_lessons || []).slice(0, 2)
    source.value = res.source || 'heuristic'
  }
  catch {
    summary.value = t('student.ai.tipFallback')
    tips.value = [t('student.ai.tipFallbackDetail')]
    review.value = []
    source.value = 'heuristic'
  }
  finally {
    loading.value = false
  }
}

watch(
  () => [props.courseId, props.lessonId, props.progressPercent],
  load,
  { immediate: true },
)
</script>

<template>
  <aside class="tip">
    <header>
      <i class="pi pi-lightbulb" />
      <div>
        <strong>{{ t('student.ai.tipTitle') }}</strong>
        <span v-if="source">{{ source === 'ai' ? t('student.ai.tipSourceAi') : t('student.ai.tipSourceLocal') }}</span>
      </div>
    </header>
    <p v-if="loading" class="muted">…</p>
    <template v-else>
      <p v-if="summary" class="summary">{{ summary }}</p>
      <ul v-if="tips.length">
        <li v-for="(item, i) in tips" :key="i">{{ item }}</li>
      </ul>
      <div v-if="review.length" class="review">
        <em>{{ t('student.ai.tipReview') }}</em>
        <span v-for="(item, i) in review" :key="i">{{ item }}</span>
      </div>
    </template>
  </aside>
</template>

<style scoped>
.tip {
  margin-top: 16px;
  padding: 14px 16px;
  border: 1px solid color-mix(in srgb, var(--brand) 28%, var(--border));
  border-radius: 14px;
  background: color-mix(in srgb, var(--brand-soft) 45%, var(--surface));
}
header { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 8px; }
header i { color: var(--brand); font-size: 1.1rem; margin-top: 2px; }
header strong { display: block; font-size: .95rem; }
header span { color: var(--text-muted); font-size: .75rem; font-weight: 650; }
.summary { margin: 0 0 8px; font-weight: 550; line-height: 1.45; }
ul { margin: 0; padding-left: 18px; display: grid; gap: 4px; }
li { color: var(--text); font-weight: 500; line-height: 1.4; }
.review { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; align-items: center; }
.review em { font-style: normal; color: var(--text-muted); font-size: .8rem; font-weight: 650; }
.review span {
  padding: 3px 8px; border-radius: 999px; background: var(--surface);
  border: 1px solid var(--border); font-size: .78rem; font-weight: 650;
}
.muted { color: var(--text-muted); margin: 0; }
</style>
