<script setup lang="ts">
definePageMeta({
  layout: 'student',
  middleware: ['auth'],
})

interface NewsItem {
  id: number
  title: string
  slug: string
  excerpt?: string | null
  cover_image_url?: string | null
  published_at?: string | null
  author?: { name?: string } | null
}

const { t, locale } = useI18n()
const loading = ref(true)
const items = ref<NewsItem[]>([])

function formatDate(value?: string | null) {
  if (!value) return ''
  return new Date(value).toLocaleDateString(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    dateStyle: 'medium',
  })
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data?: NewsItem[] }>('/news', { query: { per_page: 20 } })
    items.value = res.data || []
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
  <div class="page-stack">
    <header class="head">
      <h1>{{ t('student.news.title') }}</h1>
      <p>{{ t('student.news.subtitle') }}</p>
    </header>

    <div v-if="loading" class="empty">…</div>
    <CommonEmptyState v-else-if="!items.length" :description="t('student.news.empty')" />
    <div v-else class="grid">
      <NuxtLink
        v-for="item in items"
        :key="item.id"
        :to="`/news/${item.slug}`"
        class="card"
      >
        <div
          class="cover"
          :style="item.cover_image_url ? { backgroundImage: `url(${item.cover_image_url})` } : undefined"
        />
        <div class="body">
          <small>{{ formatDate(item.published_at) }}</small>
          <strong>{{ item.title }}</strong>
          <p>{{ item.excerpt }}</p>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.head { margin-bottom: 16px; }
.head h1 { margin: 0 0 4px; }
.head p { margin: 0; color: var(--text-muted); }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.card {
  display: grid; border: 1px solid var(--border); border-radius: 14px; overflow: hidden;
  text-decoration: none; color: inherit; background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.cover { height: 120px; background: linear-gradient(135deg, #0B5A54, #0f766e); background-size: cover; background-position: center; }
.body { padding: 12px; display: grid; gap: 6px; }
.body small { color: var(--text-muted); font-size: .75rem; }
.body p { margin: 0; color: var(--text-muted); font-size: .88rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.empty { color: var(--text-muted); padding: 24px 0; }
</style>
