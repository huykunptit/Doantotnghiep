<script setup lang="ts">
definePageMeta({ layout: 'default' })

interface PathCard {
  id: number
  title: string
  slug: string
  description?: string | null
  target_role?: string | null
  price?: number
  cover_url?: string | null
  path_courses_count?: number
}

const route = useRoute()
const { t, locale } = useI18n()
const search = ref(String(route.query.search || ''))
const loading = ref(false)
const paths = ref<PathCard[]>([])
const total = ref(0)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data?: PathCard[], total?: number }>('/career-paths', {
      query: {
        per_page: 12,
        search: search.value || undefined,
      },
      token: null,
    })
    paths.value = res.data || []
    total.value = res.total || paths.value.length
  }
  catch {
    paths.value = []
    total.value = 0
  }
  finally {
    loading.value = false
  }
}

function formatPrice(price?: number) {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

onMounted(load)
</script>

<template>
  <div class="paths-page">
    <header class="page-heading">
      <div>
        <h1>{{ t('student.paths.title') }}</h1>
        <p>{{ t('student.paths.subtitle') }}</p>
      </div>
    </header>

    <form class="filter-bar" @submit.prevent="load">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('student.paths.search')" fluid />
      </IconField>
      <Button type="submit" :label="t('student.catalog.searchBtn')" :loading="loading" />
    </form>

    <p class="result">{{ t('student.paths.result', { n: total }) }}</p>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="!paths.length" class="empty">{{ t('student.paths.empty') }}</div>
    <div v-else class="grid">
      <NuxtLink
        v-for="path in paths"
        :key="path.id"
        :to="`/paths/${path.slug}`"
        class="card"
      >
        <div class="cover" :style="path.cover_url ? { backgroundImage: `url(${path.cover_url})` } : undefined">
          <span v-if="path.target_role" class="role">{{ path.target_role }}</span>
        </div>
        <div class="body">
          <strong>{{ path.title }}</strong>
          <p>{{ path.description || t('student.paths.noDesc') }}</p>
          <div class="meta">
            <span>{{ t('student.paths.courses', { n: path.path_courses_count || 0 }) }}</span>
            <span class="price">{{ formatPrice(path.price) }}</span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.paths-page { width: min(1100px, calc(100% - 32px)); margin: 0 auto 48px; padding-top: 28px; }
.page-heading h1 { margin: 0 0 6px; }
.page-heading p { margin: 0; color: var(--text-muted); font-weight: 500; }
.filter-bar { display: grid; grid-template-columns: 1fr auto; gap: 10px; margin: 18px 0 10px; }
.result { margin: 0 0 14px; color: var(--text-muted); font-weight: 600; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.card {
  display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 16px; overflow: hidden;
  background: color-mix(in srgb, var(--surface) 92%, transparent); text-decoration: none; color: inherit;
  transition: border-color .15s ease, transform .15s ease;
}
.card:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); transform: translateY(-2px); }
.cover {
  min-height: 120px; background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 35%, #1e293b), #0f172a);
  background-size: cover; background-position: center; position: relative;
}
.role {
  position: absolute; left: 10px; bottom: 10px; padding: 4px 8px; border-radius: 999px;
  background: rgba(0,0,0,.45); color: #fff; font-size: .75rem; font-weight: 700;
}
.body { padding: 14px; display: grid; gap: 8px; flex: 1; }
.body p {
  margin: 0; color: var(--text-muted); font-size: .9rem; font-weight: 500;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.meta { display: flex; justify-content: space-between; align-items: center; font-weight: 650; margin-top: auto; }
.price { color: var(--brand); }
.empty { color: var(--text-muted); padding: 28px 0; }
</style>
