<script setup lang="ts">
interface PathRec {
  path: {
    id: number
    title: string
    slug: string
    description?: string | null
    target_role?: string | null
    price?: number
    cover_url?: string | null
    path_courses_count?: number
  }
  score: number
  reasons?: string[]
  matched_skills?: string[]
}

const props = withDefaults(defineProps<{
  limit?: number
  showMore?: boolean
  compact?: boolean
}>(), {
  limit: 4,
  showMore: true,
  compact: false,
})

const { t, locale } = useI18n()
const items = ref<PathRec[]>([])
const loading = ref(true)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
function formatPrice(n?: number) {
  if (!n) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(n)
}

async function load() {
  loading.value = true
  try {
    const response = await useApi<{ paths?: PathRec[] }>('/me/recommendations/extensions')
    items.value = (response.paths || []).slice(0, props.limit)
  }
  catch {
    items.value = []
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="rec" :class="{ compact }">
    <header class="rec-head">
      <div>
        <span class="eyebrow">{{ t('student.ai.recEyebrow') }}</span>
        <h2>{{ t('student.paths.recTitle') }}</h2>
        <p>{{ t('student.paths.recSubtitle') }}</p>
      </div>
      <NuxtLink v-if="showMore" to="/paths" class="more">{{ t('student.paths.recMore') }} <i class="pi pi-arrow-right" /></NuxtLink>
    </header>

    <div v-if="loading" class="skeleton">
      <div v-for="i in 3" :key="i" class="sk" />
    </div>
    <p v-else-if="!items.length" class="msg">{{ t('student.paths.recEmpty') }}</p>
    <div v-else class="grid">
      <NuxtLink
        v-for="item in items"
        :key="item.path.id"
        :to="`/paths/${item.path.slug}`"
        class="card"
      >
        <div
          class="cover"
          :style="item.path.cover_url ? { backgroundImage: `url(${item.path.cover_url})` } : undefined"
        >
          <span v-if="item.path.target_role">{{ item.path.target_role }}</span>
        </div>
        <div class="body">
          <strong>{{ item.path.title }}</strong>
          <p v-if="item.reasons?.length">{{ item.reasons[0] }}</p>
          <div class="foot">
            <em>{{ formatPrice(item.path.price) }}</em>
            <span>{{ t('student.paths.courses', { n: item.path.path_courses_count || 0 }) }}</span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </section>
</template>

<style scoped>
.rec {
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.rec-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; align-items: flex-end; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.rec-head h2 { margin: 0 0 4px; font-size: 1.15rem; }
.rec-head p { margin: 0; color: var(--text-muted); font-weight: 500; font-size: .9rem; }
.more { display: inline-flex; align-items: center; gap: 6px; color: var(--brand); font-weight: 700; text-decoration: none; }
.more i { font-size: .85em; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.compact .grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.card {
  min-width: 0;
  border: 1px solid var(--border); border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit;
  background: var(--surface-subtle);
}
.card:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.cover {
  height: 132px;
  overflow: hidden;
  background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 40%, #1e293b), #0f172a);
  background-size: cover;
  background-position: center;
  position: relative;
  flex-shrink: 0;
}
.cover span {
  position: absolute; left: 8px; bottom: 8px; padding: 2px 8px; border-radius: 999px;
  background: rgba(0,0,0,.45); color: #fff; font-size: .72rem; font-weight: 700;
}
.body { padding: 10px 12px 12px; display: grid; gap: 4px; min-width: 0; }
.body p { margin: 0; color: var(--text-muted); font-size: .82rem; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.foot { display: flex; justify-content: space-between; margin-top: 4px; font-weight: 700; font-size: .85rem; }
.foot em { font-style: normal; color: var(--brand); }
.msg { color: var(--text-muted); margin: 0; }
.skeleton { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.sk { height: 140px; border-radius: 12px; background: color-mix(in srgb, var(--border) 55%, transparent); animation: pulse 1.2s ease infinite; }
@keyframes pulse { 50% { opacity: .55; } }
@media (max-width: 960px) {
  .compact .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .compact .grid,
  .skeleton { grid-template-columns: 1fr; }
}
@media (max-width: 700px) { .skeleton { grid-template-columns: 1fr; } }
</style>
