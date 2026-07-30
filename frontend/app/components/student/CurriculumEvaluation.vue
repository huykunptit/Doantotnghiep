<script setup lang="ts">
interface Evaluation {
  has_curriculum: boolean
  message?: string
  ready_for_career_advice?: boolean
  narrative?: string
  summary?: {
    level?: string
    completion_ratio?: number
    credit_ratio?: number
    overall_gpa?: number | null
    required_courses_completed?: number
    required_courses_total?: number
    credits_earned?: number
    credits_required?: number
  }
}

const { t } = useI18n()
const loading = ref(true)
const data = ref<Evaluation | null>(null)

const pct = computed(() => Math.round((data.value?.summary?.completion_ratio || 0) * 100))
const levelLabel = computed(() => {
  const gpa = data.value?.summary?.overall_gpa
  if (gpa == null || Number.isNaN(gpa)) return ''
  const key = gpa >= 3.6 ? 'excellent'
    : gpa >= 3.2 ? 'very_good'
      : gpa >= 2.5 ? 'good'
        : gpa >= 2.0 ? 'average'
          : gpa >= 1.0 ? 'weak'
            : null
  return key ? t(`student.evaluation.levels.${key}`) : ''
})
const levelKey = computed(() => {
  const gpa = data.value?.summary?.overall_gpa
  if (gpa == null || Number.isNaN(gpa)) return ''
  if (gpa >= 3.6) return 'excellent'
  if (gpa >= 3.2) return 'very_good'
  if (gpa >= 2.5) return 'good'
  if (gpa >= 2.0) return 'average'
  if (gpa >= 1.0) return 'weak'
  return ''
})

async function load() {
  loading.value = true
  try {
    data.value = await useApi<Evaluation>('/me/curriculum-evaluation')
  }
  catch {
    data.value = null
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="eval">
    <header class="head">
      <div>
        <span class="eyebrow">{{ t('student.evaluation.eyebrow') }}</span>
        <h2>{{ t('student.evaluation.title') }}</h2>
      </div>
      <span v-if="levelLabel" class="level" :data-level="levelKey">{{ levelLabel }}</span>
    </header>

    <div v-if="loading" class="muted">…</div>
    <div v-else-if="!data?.has_curriculum" class="muted">{{ data?.message || t('student.evaluation.noCurriculum') }}</div>
    <template v-else>
      <div class="stats">
        <div>
          <span>{{ t('student.evaluation.completion') }}</span>
          <strong>{{ pct }}%</strong>
        </div>
        <div>
          <span>{{ t('student.evaluation.gpa') }}</span>
          <strong>{{ data.summary?.overall_gpa != null ? data.summary.overall_gpa.toFixed(2) : '—' }}</strong>
        </div>
        <div>
          <span>{{ t('student.evaluation.credits') }}</span>
          <strong>{{ data.summary?.credits_earned || 0 }}/{{ data.summary?.credits_required || 0 }}</strong>
        </div>
      </div>

      <p class="narrative">{{ data.narrative }}</p>

      <div v-if="data.ready_for_career_advice" class="ready">
        <div>
          <strong>{{ t('student.evaluation.readyTitle') }}</strong>
          <p>{{ t('student.evaluation.readyBody') }}</p>
        </div>
        <Button :label="t('student.evaluation.viewPaths')" icon="pi pi-map" @click="navigateTo('/paths')" />
      </div>
    </template>
  </section>
</template>

<style scoped>
.eval {
  border: 1px solid var(--border); border-radius: 14px; padding: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; }
.eyebrow { display: block; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.head h2 { margin: 4px 0 0; font-size: 1.15rem; }
.level {
  padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 750; text-transform: uppercase;
  background: var(--brand-soft); color: var(--brand);
}
.level[data-level='excellent'] { background: color-mix(in srgb, #16a34a 18%, transparent); color: #166534; }
.level[data-level='very_good'] { background: color-mix(in srgb, #22c55e 16%, transparent); color: #15803d; }
.level[data-level='good'] { background: var(--brand-soft); color: var(--brand); }
.level[data-level='average'] { background: color-mix(in srgb, #eab308 18%, transparent); color: #a16207; }
.level[data-level='weak'] { background: color-mix(in srgb, #ef4444 16%, transparent); color: #b91c1c; }
.level[data-level='early'] { background: var(--surface-subtle); color: var(--text-muted); }
.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.stats div {
  padding: 10px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface-subtle);
}
.stats span { display: block; color: var(--text-muted); font-size: .75rem; font-weight: 650; }
.stats strong { font-family: var(--font-display); font-size: 1.35rem; }
.narrative { margin: 0 0 12px; font-weight: 550; line-height: 1.5; }
.ready {
  display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap;
  padding: 12px; border-radius: 12px; background: var(--brand-soft);
}
.ready p { margin: 4px 0 0; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.muted { color: var(--text-muted); }
@media (max-width: 700px) { .stats { grid-template-columns: 1fr; } }
</style>
