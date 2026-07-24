<script setup lang="ts">
interface RecItem {
  course: {
    id: number
    title: string
    slug?: string
    price?: number
    thumbnail?: string | null
    course_mode?: string
    instructor?: { id: number, name: string } | null
    category?: { name: string } | null
  }
  score: number
  matched_skills?: string[]
  reasons?: string[]
}

const props = withDefaults(defineProps<{ limit?: number, compact?: boolean }>(), {
  limit: 6,
  compact: false,
})

const { t, locale } = useI18n()
const items = ref<RecItem[]>([])
const loading = ref(true)
const error = ref('')

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
  error.value = ''
  try {
    const response = await useApi<{ recommendations: RecItem[] }>('/me/recommendations/extensions')
    items.value = (response.recommendations || []).slice(0, props.limit)
  }
  catch (e: any) {
    error.value = e?.data?.message || t('student.ai.recError')
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
        <h2>{{ t('student.ai.recTitle') }}</h2>
        <p>{{ t('student.ai.recSubtitle') }}</p>
      </div>
      <NuxtLink to="/courses" class="more">{{ t('student.ai.recMore') }}</NuxtLink>
    </header>

    <p v-if="error" class="msg error">{{ error }}</p>
    <div v-else-if="loading" class="skeleton">
      <div v-for="i in 3" :key="i" class="sk" />
    </div>
    <p v-else-if="!items.length" class="msg">{{ t('student.ai.recEmpty') }}</p>
    <div v-else class="grid">
      <NuxtLink
        v-for="item in items"
        :key="item.course.id"
        :to="`/courses/${item.course.slug || item.course.id}`"
        class="card"
      >
        <div class="thumb">
          <img v-if="item.course.thumbnail" :src="item.course.thumbnail" :alt="item.course.title">
          <i v-else class="pi pi-graduation-cap" />
        </div>
        <div class="body">
          <span class="cat">{{ item.course.category?.name || t('student.ai.recFallbackCat') }}</span>
          <strong>{{ item.course.title }}</strong>
          <span class="inst">{{ item.course.instructor?.name || '' }}</span>
          <div v-if="item.matched_skills?.length" class="skills">
            <span v-for="skill in item.matched_skills.slice(0, 3)" :key="skill">{{ skill }}</span>
          </div>
          <p v-if="item.reasons?.length" class="reason">{{ item.reasons[0] }}</p>
          <div class="foot">
            <em>{{ formatPrice(item.course.price) }}</em>
            <span :title="t('student.ai.recScore', { n: item.score })">
              <i class="pi pi-sparkles" /> {{ item.score }}
            </span>
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
  backdrop-filter: blur(8px);
}
.rec-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.eyebrow {
  display: block;
  margin-bottom: 4px;
  color: var(--brand);
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
}
.rec-head h2 { margin: 0 0 4px; font-size: 1.15rem; }
.rec-head p { margin: 0; color: var(--text-muted); font-weight: 500; font-size: .9rem; }
.more { color: var(--brand); font-weight: 700; text-decoration: none; white-space: nowrap; }
.more:hover { text-decoration: underline; }
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}
.compact .grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
.card {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  background: var(--surface-subtle);
  color: inherit;
  text-decoration: none;
  transition: border-color .15s ease;
}
.card:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.thumb {
  aspect-ratio: 16/10;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--brand-soft) 70%, var(--surface));
  color: var(--brand);
  font-size: 1.6rem;
}
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.body { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px 12px; min-width: 0; }
.cat { color: var(--text-muted); font-size: .75rem; font-weight: 650; text-transform: uppercase; letter-spacing: .04em; }
.body strong { font-size: .95rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.inst { color: var(--text-muted); font-size: .82rem; font-weight: 500; }
.skills { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
.skills span {
  padding: 2px 7px;
  border-radius: 999px;
  background: var(--brand-soft);
  color: var(--brand);
  font-size: .72rem;
  font-weight: 650;
}
.reason {
  margin: 2px 0 0;
  color: var(--text-muted);
  font-size: .78rem;
  font-weight: 500;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 6px;
  gap: 8px;
}
.foot em { font-style: normal; font-weight: 750; color: var(--brand); }
.foot span { color: var(--text-muted); font-size: .8rem; font-weight: 650; }
.msg { color: var(--text-muted); margin: 0; font-weight: 500; }
.msg.error { color: var(--p-red-500, #c0392b); }
.skeleton { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.sk { height: 160px; border-radius: 12px; background: color-mix(in srgb, var(--border) 55%, transparent); animation: pulse 1.2s ease infinite; }
@keyframes pulse { 50% { opacity: .55; } }
@media (max-width: 700px) {
  .skeleton { grid-template-columns: 1fr; }
}
</style>
