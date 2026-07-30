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
  content?: string | null
  cover_image_url?: string | null
  published_at?: string | null
  author?: { name?: string } | null
}

const route = useRoute()
const { t, locale } = useI18n()
const loading = ref(true)
const error = ref('')
const post = ref<NewsItem | null>(null)

function formatDate(value?: string | null) {
  if (!value) return ''
  return new Date(value).toLocaleString(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    post.value = await useApi<NewsItem>(`/news/${route.params.slug}`)
  }
  catch {
    post.value = null
    error.value = t('student.news.loadError')
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.slug, load)
</script>

<template>
  <div class="page-stack">
    <NuxtLink to="/news" class="back">← {{ t('student.news.back') }}</NuxtLink>
    <div v-if="loading" class="empty">…</div>
    <div v-else-if="error" class="empty">{{ error }}</div>
    <article v-else-if="post" class="article">
      <div
        v-if="post.cover_image_url"
        class="cover"
        :style="{ backgroundImage: `url(${post.cover_image_url})` }"
      />
      <header>
        <small>{{ formatDate(post.published_at) }} · {{ post.author?.name || 'Eript' }}</small>
        <h1>{{ post.title }}</h1>
        <p v-if="post.excerpt" class="excerpt">{{ post.excerpt }}</p>
      </header>
      <div class="content" v-html="post.content || ''" />
    </article>
  </div>
</template>

<style scoped>
.back { color: var(--brand); text-decoration: none; font-weight: 700; font-size: .9rem; }
.article { margin-top: 12px; display: grid; gap: 14px; }
.cover { height: 220px; border-radius: 16px; background-size: cover; background-position: center; }
header small { color: var(--text-muted); }
header h1 { margin: 6px 0; }
.excerpt { color: var(--text-muted); font-size: 1.05rem; }
.content :deep(p) { line-height: 1.65; }
.content :deep(ul) { padding-left: 1.2rem; }
.empty { color: var(--text-muted); padding: 24px 0; }
</style>
