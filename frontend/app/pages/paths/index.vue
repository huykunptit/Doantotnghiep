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
  user_career_paths_count?: number
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { t, locale } = useI18n()

const search = ref(String(route.query.search || ''))
const targetRole = ref(String(route.query.target_role || ''))
const pricing = ref(String(route.query.pricing || ''))
const sort = ref(String(route.query.sort || 'newest'))
const page = ref(Number(route.query.page || 1))
const perPage = 12

const loading = ref(false)
const filtersOpen = ref(false)
const paths = ref<PathCard[]>([])
const total = ref(0)
const lastPage = ref(1)
const targetRoles = ref<string[]>([])

let searchTimer: ReturnType<typeof setTimeout> | null = null

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const showPersonalized = computed(() => auth.isAuthenticated)

const sortOptions = computed(() => [
  { label: t('student.paths.sortNewest'), value: 'newest' },
  { label: t('student.paths.sortPopular'), value: 'popular' },
  { label: t('student.paths.sortPriceAsc'), value: 'price_asc' },
  { label: t('student.paths.sortPriceDesc'), value: 'price_desc' },
])

const activeFilterCount = computed(() => {
  let n = 0
  if (targetRole.value) n++
  if (pricing.value) n++
  return n
})

function roleLabel(role: string) {
  return role.replace(/[_-]+/g, ' ')
}

function formatPrice(price?: number) {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

function syncQuery() {
  router.replace({
    query: {
      ...(search.value ? { search: search.value } : {}),
      ...(targetRole.value ? { target_role: targetRole.value } : {}),
      ...(pricing.value ? { pricing: pricing.value } : {}),
      ...(sort.value !== 'newest' ? { sort: sort.value } : {}),
      ...(page.value > 1 ? { page: page.value } : {}),
    },
  })
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data?: PathCard[], total?: number, last_page?: number, target_roles?: string[] }>('/career-paths', {
      query: {
        per_page: perPage,
        page: page.value,
        search: search.value || undefined,
        target_role: targetRole.value || undefined,
        pricing: pricing.value || undefined,
        sort: sort.value,
      },
      token: null,
    })
    paths.value = res.data || []
    total.value = res.total || paths.value.length
    lastPage.value = res.last_page || 1
    if (res.target_roles) targetRoles.value = res.target_roles
  }
  catch {
    paths.value = []
    total.value = 0
    lastPage.value = 1
  }
  finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  filtersOpen.value = false
  syncQuery()
  load()
}

function selectRole(role: string | null) {
  targetRole.value = targetRole.value === role ? '' : (role || '')
  applyFilters()
}

function resetFilters() {
  search.value = ''
  targetRole.value = ''
  pricing.value = ''
  sort.value = 'newest'
  applyFilters()
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

function onSort() {
  page.value = 1
  syncQuery()
  load()
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  syncQuery()
  load()
  if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
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

    <div class="toolbar">
      <IconField class="search-field">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('student.paths.search')" fluid @input="onSearchInput" />
      </IconField>
      <Select
        v-model="sort"
        class="sort-select"
        :options="sortOptions"
        option-label="label"
        option-value="value"
        @update:model-value="onSort"
      />
      <Button
        class="filters-toggle"
        severity="secondary"
        outlined
        icon="pi pi-sliders-h"
        :label="t('student.paths.filters')"
        @click="filtersOpen = true"
      >
        <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" class="filters-badge" />
      </Button>
    </div>

    <div class="layout">
      <aside class="sidebar">
        <div class="filter-block">
          <div class="filter-block-head"><strong>{{ t('student.paths.priceLabel') }}</strong></div>
          <div class="chip-group">
            <button type="button" class="chip" :class="{ active: !pricing }" @click="pricing = ''; applyFilters()">{{ t('common.all') }}</button>
            <button type="button" class="chip" :class="{ active: pricing === 'free' }" @click="pricing = 'free'; applyFilters()">{{ t('student.catalog.free') }}</button>
            <button type="button" class="chip" :class="{ active: pricing === 'paid' }" @click="pricing = 'paid'; applyFilters()">{{ t('student.catalog.paid') }}</button>
          </div>
        </div>

        <div v-if="targetRoles.length" class="filter-block">
          <div class="filter-block-head"><strong>{{ t('student.paths.roleLabel') }}</strong></div>
          <div class="chip-group vertical">
            <button type="button" class="chip" :class="{ active: !targetRole }" @click="selectRole(null)">{{ t('common.all') }}</button>
            <button
              v-for="role in targetRoles"
              :key="role"
              type="button"
              class="chip"
              :class="{ active: targetRole === role }"
              @click="selectRole(role)"
            >{{ roleLabel(role) }}</button>
          </div>
        </div>

        <Button
          v-if="activeFilterCount"
          :label="t('common.reset')"
          severity="secondary"
          text
          size="small"
          icon="pi pi-times"
          @click="resetFilters"
        />
      </aside>

      <main class="content">
        <p class="result">{{ t('student.paths.result', { n: total }) }}</p>

        <StudentPathRecommendations
          v-if="showPersonalized && page === 1 && !activeFilterCount && !search"
          class="path-recs"
          :limit="4"
          compact
          :show-more="false"
        />

        <div v-if="loading" class="grid">
          <div v-for="i in perPage" :key="i" class="skeleton-card" />
        </div>
        <CommonEmptyState v-else-if="!paths.length" :description="t('student.paths.empty')" />
        <div v-else class="grid">
          <NuxtLink
            v-for="path in paths"
            :key="path.id"
            :to="`/paths/${path.slug}`"
            class="card"
          >
            <div class="cover" :style="path.cover_url ? { backgroundImage: `url(${path.cover_url})` } : undefined">
              <span v-if="path.target_role" class="role">{{ roleLabel(path.target_role) }}</span>
            </div>
            <div class="body">
              <strong>{{ path.title }}</strong>
              <p>{{ path.description || t('student.paths.noDesc') }}</p>
              <div class="stats">
                <span>{{ t('student.paths.courses', { n: path.path_courses_count || 0 }) }}</span>
                <span>{{ t('student.catalog.learners', { n: path.user_career_paths_count || 0 }) }}</span>
              </div>
              <div class="meta">
                <span class="price" :class="{ free: !path.price }">{{ formatPrice(path.price) }}</span>
              </div>
            </div>
          </NuxtLink>
        </div>

        <Paginator
          v-if="!loading && lastPage > 1"
          class="paginator"
          :rows="perPage"
          :total-records="total"
          :first="(page - 1) * perPage"
          template="PrevPageLink PageLinks NextPageLink"
          @page="onPage"
        />
      </main>
    </div>

    <Drawer v-model:visible="filtersOpen" position="left" :header="t('student.paths.filters')" class="filters-drawer">
      <div class="filter-block">
        <div class="filter-block-head"><strong>{{ t('student.paths.priceLabel') }}</strong></div>
        <div class="chip-group">
          <button type="button" class="chip" :class="{ active: !pricing }" @click="pricing = ''; applyFilters()">{{ t('common.all') }}</button>
          <button type="button" class="chip" :class="{ active: pricing === 'free' }" @click="pricing = 'free'; applyFilters()">{{ t('student.catalog.free') }}</button>
          <button type="button" class="chip" :class="{ active: pricing === 'paid' }" @click="pricing = 'paid'; applyFilters()">{{ t('student.catalog.paid') }}</button>
        </div>
      </div>
      <div v-if="targetRoles.length" class="filter-block">
        <div class="filter-block-head"><strong>{{ t('student.paths.roleLabel') }}</strong></div>
        <div class="chip-group vertical">
          <button type="button" class="chip" :class="{ active: !targetRole }" @click="selectRole(null)">{{ t('common.all') }}</button>
          <button
            v-for="role in targetRoles"
            :key="`d-${role}`"
            type="button"
            class="chip"
            :class="{ active: targetRole === role }"
            @click="selectRole(role)"
          >{{ roleLabel(role) }}</button>
        </div>
      </div>
      <Button v-if="activeFilterCount" :label="t('common.reset')" severity="secondary" outlined size="small" icon="pi pi-times" class="w-full" @click="resetFilters" />
    </Drawer>
  </div>
</template>

<style scoped>
.paths-page {
  width: min(1280px, calc(100% - 32px));
  margin: 0 auto;
  padding: 36px 0 64px;
}
.page-heading h1 { margin: 0 0 6px; font-size: clamp(1.6rem, 3vw, 2rem); }
.page-heading p { margin: 0; color: var(--text-muted); font-weight: 500; }

.toolbar { display: flex; gap: 10px; margin: 18px 0; flex-wrap: wrap; }
.search-field { flex: 1; min-width: 200px; }
.sort-select { min-width: 190px; }
.filters-toggle { display: none; position: relative; }
.filters-badge { position: absolute; top: -8px; right: -8px; }

.layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; align-items: start; }
.sidebar { position: sticky; top: 84px; display: grid; gap: 20px; padding-bottom: 12px; }
.filter-block { display: grid; gap: 8px; }
.filter-block-head { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); }
.chip-group { display: flex; flex-wrap: wrap; gap: 6px; }
.chip-group.vertical { flex-direction: column; align-items: flex-start; }
.chip {
  padding: 6px 12px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface);
  color: var(--text); font: inherit; font-size: .82rem; font-weight: 600; cursor: pointer;
  transition: border-color .12s ease, color .12s ease, background-color .12s ease; text-transform: capitalize;
}
.chip-group.vertical .chip { width: 100%; text-align: left; border-radius: 8px; }
.chip:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.chip.active { background: var(--brand); border-color: var(--brand); color: #fff; }

.result { margin: 0 0 12px; color: var(--text-muted); font-weight: 500; }
.path-recs { margin: 0 0 22px; }
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
}
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
  background: rgba(0,0,0,.45); color: #fff; font-size: .75rem; font-weight: 700; text-transform: capitalize;
}
.body { padding: 12px 14px 16px; display: grid; gap: 8px; flex: 1; }
.body p {
  margin: 0; color: var(--text-muted); font-size: .9rem; font-weight: 500;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.stats { display: flex; gap: 10px; font-size: .82rem; color: var(--text-muted); font-weight: 600; }
.meta { display: flex; justify-content: flex-end; align-items: center; font-weight: 650; margin-top: auto; }
.price { font-size: .95rem; font-weight: 750; }
.price.free { color: #16a34a; }

.skeleton-card {
  height: 240px; border-radius: 16px; border: 1px solid var(--border);
  background: linear-gradient(90deg, var(--surface-subtle) 25%, var(--surface-hover, #eef2f7) 37%, var(--surface-subtle) 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
}
@keyframes shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

.paginator { margin-top: 22px; justify-content: center; background: transparent; }
.filters-drawer :deep(.p-drawer-content) { display: grid; gap: 20px; }
.w-full { width: 100%; }

@media (max-width: 900px) {
  .layout { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .filters-toggle { display: inline-flex; }
}
</style>
