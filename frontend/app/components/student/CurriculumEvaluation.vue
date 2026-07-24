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
  target_roles?: string[]
  suggested_paths?: Array<{
    path: { id: number, title: string, slug: string, price?: number }
    reasons?: string[]
  }>
  suggested_courses?: Array<{
    course: { id: number, title: string, slug?: string, price?: number }
    reasons?: string[]
  }>
}

const { t } = useI18n()
const loading = ref(true)
const data = ref<Evaluation | null>(null)

const pct = computed(() => Math.round((data.value?.summary?.completion_ratio || 0) * 100))
const levelLabel = computed(() => {
  const level = data.value?.summary?.level
  if (!level) return ''
  return t(`student.evaluation.levels.${level}`)
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
        <p>{{ t('student.evaluation.subtitle') }}</p>
      </div>
      <span v-if="data?.summary?.level" class="level" :data-level="data.summary.level">{{ levelLabel }}</span>
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

      <div v-if="data.target_roles?.length" class="roles">
        <span v-for="role in data.target_roles.slice(0, 4)" :key="role">{{ role }}</span>
      </div>

      <div v-if="data.ready_for_career_advice" class="ready">
        <div>
          <strong>{{ t('student.evaluation.readyTitle') }}</strong>
          <p>{{ t('student.evaluation.readyBody') }}</p>
        </div>
        <Button :label="t('student.evaluation.viewPaths')" icon="pi pi-map" @click="navigateTo('/paths')" />
      </div>

      <div v-if="data.suggested_paths?.length" class="suggestions">
        <h3>{{ t('student.evaluation.suggestedPaths') }}</h3>
        <NuxtLink
          v-for="item in data.suggested_paths.slice(0, 3)"
          :key="item.path.id"
          :to="`/paths/${item.path.slug}`"
          class="sug"
        >
          <strong>{{ item.path.title }}</strong>
          <span>{{ item.reasons?.[0] }}</span>
        </NuxtLink>
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
.head h2 { margin: 4px 0; font-size: 1.15rem; }
.head p { margin: 0; color: var(--text-muted); font-weight: 500; font-size: .9rem; }
.level {
  padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 750; text-transform: uppercase;
  background: var(--brand-soft); color: var(--brand);
}
.level[data-level='excellent'] { background: color-mix(in srgb, #16a34a 18%, transparent); color: #166534; }
.level[data-level='early'] { background: var(--surface-subtle); color: var(--text-muted); }
.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.stats div {
  padding: 10px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface-subtle);
}
.stats span { display: block; color: var(--text-muted); font-size: .75rem; font-weight: 650; }
.stats strong { font-family: var(--font-display); font-size: 1.35rem; }
.narrative { margin: 0 0 12px; font-weight: 550; line-height: 1.5; }
.roles { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.roles span {
  padding: 3px 8px; border-radius: 999px; background: var(--brand-soft); color: var(--brand);
  font-size: .75rem; font-weight: 700;
}
.ready {
  display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap;
  padding: 12px; border-radius: 12px; background: var(--brand-soft); margin-bottom: 12px;
}
.ready p { margin: 4px 0 0; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.suggestions h3 { margin: 0 0 8px; font-size: .95rem; }
.sug {
  display: grid; gap: 2px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;
  text-decoration: none; color: inherit; margin-bottom: 8px; background: var(--surface-subtle);
}
.sug span { color: var(--text-muted); font-size: .84rem; font-weight: 500; }
.muted { color: var(--text-muted); }
@media (max-width: 700px) { .stats { grid-template-columns: 1fr; } }
</style>
