<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'default' })

interface Course {
  id: number
  slug?: string
  title: string
  description?: string
  thumbnail?: string | null
  price?: number
  level?: string | null
  enrollments_count?: number
  reviews_count?: number
  reviews_avg_rating?: number | string | null
  instructor?: { id: number, name: string } | null
  category?: { id: number, name: string, slug?: string } | null
  course_mode?: string | null
}
interface CategoryNode {
  id: number
  name: string
  slug?: string
  courses_count?: number
  children?: CategoryNode[]
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()
const toast = useToast()
const { t, locale } = useI18n()

const search = ref(String(route.query.search || ''))
const categoryId = ref(route.query.category ? Number(route.query.category) : null)
const level = ref(String(route.query.level || ''))
const pricing = ref(String(route.query.pricing || ''))
const sort = ref(String(route.query.sort || 'newest'))
const page = ref(Number(route.query.page || 1))
const perPage = 12

const loading = ref(false)
const filtersOpen = ref(false)
const courses = ref<Course[]>([])
const total = ref(0)
const lastPage = ref(1)
const categories = ref<CategoryNode[]>([])

let searchTimer: ReturnType<typeof setTimeout> | null = null

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

const levelOptions = computed(() => [
  { label: t('admin.builder.levels.beginner'), value: 'beginner' },
  { label: t('admin.builder.levels.intermediate'), value: 'intermediate' },
  { label: t('admin.builder.levels.advanced'), value: 'advanced' },
])

const sortOptions = computed(() => [
  { label: t('student.catalog.sortNewest'), value: 'newest' },
  { label: t('student.catalog.sortPopular'), value: 'popular' },
  { label: t('student.catalog.sortRating'), value: 'rating' },
  { label: t('student.catalog.sortPriceAsc'), value: 'price_asc' },
  { label: t('student.catalog.sortPriceDesc'), value: 'price_desc' },
])

const activeFilterCount = computed(() => {
  let n = 0
  if (categoryId.value) n++
  if (level.value) n++
  if (pricing.value) n++
  return n
})

const selectedCategoryName = computed(() => {
  if (!categoryId.value) return null
  for (const top of categories.value) {
    if (top.id === categoryId.value) return top.name
    for (const child of top.children || []) {
      if (child.id === categoryId.value) return child.name
    }
  }
  return null
})

function levelLabel(value?: string | null) {
  if (!value) return ''
  const key = `admin.builder.levels.${value}`
  const translated = t(key)
  return translated === key ? value : translated
}

function formatRating(value?: number | string | null) {
  const rating = Number(value || 0)
  return rating > 0 ? rating.toFixed(1) : null
}

function formatPrice(price?: number) {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

function canAddCourse(course: Course) {
  return (course.price || 0) > 0 && course.course_mode !== 'core'
}

function addCatalogCourse(course: Course, event: Event) {
  event.preventDefault()
  event.stopPropagation()
  if (!canAddCourse(course)) return
  const added = cart.add({
    id: course.id,
    title: course.title,
    price: course.price || 0,
    thumbnail: course.thumbnail,
    slug: course.slug,
  })
  toast.add({
    severity: added ? 'success' : 'info',
    summary: added ? t('student.cart.added') : t('student.cart.already'),
    life: 2000,
  })
}

function syncQuery() {
  router.replace({
    query: {
      ...(search.value ? { search: search.value } : {}),
      ...(categoryId.value ? { category: categoryId.value } : {}),
      ...(level.value ? { level: level.value } : {}),
      ...(pricing.value ? { pricing: pricing.value } : {}),
      ...(sort.value !== 'newest' ? { sort: sort.value } : {}),
      ...(page.value > 1 ? { page: page.value } : {}),
    },
  })
}

async function loadCategories() {
  try {
    const res = await useApi<CategoryNode[]>('/categories', { token: null })
    categories.value = Array.isArray(res) ? res : []
  }
  catch {
    categories.value = []
  }
}

async function loadCourses() {
  loading.value = true
  try {
    const response = await useApi<{ data?: Course[], total?: number, last_page?: number }>('/courses', {
      query: {
        per_page: perPage,
        page: page.value,
        status: 'published',
        search: search.value || undefined,
        category: categoryId.value || undefined,
        level: level.value || undefined,
        pricing: pricing.value || undefined,
        sort: sort.value,
      },
      token: null,
    })
    courses.value = response.data || []
    total.value = response.total || courses.value.length
    lastPage.value = response.last_page || 1
  }
  catch {
    courses.value = []
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
  loadCourses()
}

function selectCategory(id: number | null) {
  categoryId.value = categoryId.value === id ? null : id
  applyFilters()
}

function resetFilters() {
  search.value = ''
  categoryId.value = null
  level.value = ''
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
  loadCourses()
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  syncQuery()
  loadCourses()
  if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(async () => {
  cart.hydrate()
  await Promise.all([loadCategories(), loadCourses()])
})
</script>

<template>
  <div class="courses-page">
    <header class="page-heading">
      <div>
        <h1>{{ t('student.catalog.title') }}</h1>
        <p>{{ t('student.catalog.subtitle') }}</p>
      </div>
    </header>

    <div class="toolbar">
      <IconField class="search-field">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('student.catalog.search')" fluid @input="onSearchInput" />
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
        :label="t('student.catalog.filters')"
        @click="filtersOpen = true"
      >
        <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" class="filters-badge" />
      </Button>
    </div>

    <div class="layout">
      <aside class="sidebar">
        <div class="filter-block">
          <div class="filter-block-head">
            <strong>{{ t('student.catalog.categoryLabel') }}</strong>
          </div>
          <ul class="category-list">
            <li>
              <button
                type="button"
                class="category-row"
                :class="{ active: !categoryId }"
                @click="selectCategory(null)"
              >
                <span>{{ t('common.all') }}</span>
              </button>
            </li>
            <li v-for="top in categories" :key="top.id">
              <button
                type="button"
                class="category-row top"
                :class="{ active: categoryId === top.id }"
                @click="selectCategory(top.id)"
              >
                <span>{{ top.name }}</span>
                <small>{{ top.courses_count || 0 }}</small>
              </button>
              <ul v-if="top.children?.length" class="category-children">
                <li v-for="child in top.children" :key="child.id">
                  <button
                    type="button"
                    class="category-row child"
                    :class="{ active: categoryId === child.id }"
                    @click="selectCategory(child.id)"
                  >
                    <span>{{ child.name }}</span>
                    <small>{{ child.courses_count || 0 }}</small>
                  </button>
                </li>
              </ul>
            </li>
          </ul>
        </div>

        <div class="filter-block">
          <div class="filter-block-head"><strong>{{ t('student.catalog.levelLabel') }}</strong></div>
          <div class="chip-group">
            <button
              type="button"
              class="chip"
              :class="{ active: !level }"
              @click="level = ''; applyFilters()"
            >{{ t('common.all') }}</button>
            <button
              v-for="opt in levelOptions"
              :key="opt.value"
              type="button"
              class="chip"
              :class="{ active: level === opt.value }"
              @click="level = opt.value; applyFilters()"
            >{{ opt.label }}</button>
          </div>
        </div>

        <div class="filter-block">
          <div class="filter-block-head"><strong>{{ t('student.catalog.priceLabel') }}</strong></div>
          <div class="chip-group">
            <button type="button" class="chip" :class="{ active: !pricing }" @click="pricing = ''; applyFilters()">{{ t('common.all') }}</button>
            <button type="button" class="chip" :class="{ active: pricing === 'free' }" @click="pricing = 'free'; applyFilters()">{{ t('student.catalog.free') }}</button>
            <button type="button" class="chip" :class="{ active: pricing === 'paid' }" @click="pricing = 'paid'; applyFilters()">{{ t('student.catalog.paid') }}</button>
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
        <div class="content-head">
          <p class="result-count">
            <strong>{{ total }}</strong> {{ t('student.catalog.result', { n: total }).replace(/^\d+\s*/, '') }}
            <template v-if="selectedCategoryName"> · {{ selectedCategoryName }}</template>
          </p>
        </div>

        <StudentCourseRecommendations v-if="auth.isAuthenticated && page === 1 && !activeFilterCount && !search" :limit="4" compact :show-more="false" class="rec-block" />

        <div v-if="loading" class="course-grid">
          <div v-for="i in perPage" :key="i" class="skeleton-card" />
        </div>
        <div v-else-if="!courses.length" class="empty-note">
          <CommonEmptyState :description="t('student.catalog.empty')" />
        </div>
        <div v-else class="course-grid">
          <article v-for="course in courses" :key="course.id" class="course-item">
            <NuxtLink :to="`/courses/${course.slug || course.id}`" class="course-link">
              <div class="course-media" :style="course.thumbnail ? { backgroundImage: `url(${course.thumbnail})` } : undefined">
                <span v-if="!course.thumbnail"><i class="pi pi-book" /></span>
                <span v-if="course.level" class="level-badge">{{ levelLabel(course.level) }}</span>
              </div>
              <div class="course-body">
                <small>{{ course.category?.name || t('student.catalog.title') }}</small>
                <strong>{{ course.title }}</strong>
                <p>{{ course.instructor?.name || t('student.catalog.instructor') }}</p>
                <div class="course-stats">
                  <span v-if="formatRating(course.reviews_avg_rating)" class="rating">
                    <i class="pi pi-star-fill" /> {{ formatRating(course.reviews_avg_rating) }}
                    <small v-if="course.reviews_count">({{ course.reviews_count }})</small>
                  </span>
                  <span>{{ t('student.catalog.learners', { n: course.enrollments_count || 0 }) }}</span>
                </div>
                <div class="course-meta">
                  <span class="price" :class="{ free: !course.price }">{{ formatPrice(course.price) }}</span>
                  <button
                    v-if="canAddCourse(course)"
                    type="button"
                    class="cart-add"
                    :title="t('student.catalog.addToCart')"
                    @click="addCatalogCourse(course, $event)"
                  >
                    <i :class="cart.has(course.id) ? 'pi pi-check' : 'pi pi-shopping-cart'" />
                  </button>
                </div>
              </div>
            </NuxtLink>
          </article>
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

    <Drawer v-model:visible="filtersOpen" position="left" :header="t('student.catalog.filters')" class="filters-drawer">
      <div class="filter-block">
        <div class="filter-block-head"><strong>{{ t('student.catalog.categoryLabel') }}</strong></div>
        <ul class="category-list">
          <li>
            <button type="button" class="category-row" :class="{ active: !categoryId }" @click="selectCategory(null)">
              <span>{{ t('common.all') }}</span>
            </button>
          </li>
          <li v-for="top in categories" :key="`d-${top.id}`">
            <button type="button" class="category-row top" :class="{ active: categoryId === top.id }" @click="selectCategory(top.id)">
              <span>{{ top.name }}</span>
              <small>{{ top.courses_count || 0 }}</small>
            </button>
            <ul v-if="top.children?.length" class="category-children">
              <li v-for="child in top.children" :key="`d-${child.id}`">
                <button type="button" class="category-row child" :class="{ active: categoryId === child.id }" @click="selectCategory(child.id)">
                  <span>{{ child.name }}</span>
                  <small>{{ child.courses_count || 0 }}</small>
                </button>
              </li>
            </ul>
          </li>
        </ul>
      </div>
      <div class="filter-block">
        <div class="filter-block-head"><strong>{{ t('student.catalog.levelLabel') }}</strong></div>
        <div class="chip-group">
          <button type="button" class="chip" :class="{ active: !level }" @click="level = ''; applyFilters()">{{ t('common.all') }}</button>
          <button v-for="opt in levelOptions" :key="opt.value" type="button" class="chip" :class="{ active: level === opt.value }" @click="level = opt.value; applyFilters()">{{ opt.label }}</button>
        </div>
      </div>
      <div class="filter-block">
        <div class="filter-block-head"><strong>{{ t('student.catalog.priceLabel') }}</strong></div>
        <div class="chip-group">
          <button type="button" class="chip" :class="{ active: !pricing }" @click="pricing = ''; applyFilters()">{{ t('common.all') }}</button>
          <button type="button" class="chip" :class="{ active: pricing === 'free' }" @click="pricing = 'free'; applyFilters()">{{ t('student.catalog.free') }}</button>
          <button type="button" class="chip" :class="{ active: pricing === 'paid' }" @click="pricing = 'paid'; applyFilters()">{{ t('student.catalog.paid') }}</button>
        </div>
      </div>
      <Button v-if="activeFilterCount" :label="t('common.reset')" severity="secondary" outlined size="small" icon="pi pi-times" class="w-full" @click="resetFilters" />
    </Drawer>
  </div>
</template>

<style scoped>
.courses-page {
  width: min(1280px, calc(100% - 32px));
  margin: 0 auto;
  padding: 36px 0 64px;
}
.page-heading h1 { margin: 0 0 6px; font-size: clamp(1.6rem, 3vw, 2rem); }
.page-heading p { margin: 0; color: var(--text-muted); font-weight: 500; }
.rec-block { margin: 4px 0 22px; }

.toolbar {
  display: flex;
  gap: 10px;
  margin: 18px 0;
  flex-wrap: wrap;
}
.search-field { flex: 1; min-width: 200px; }
.sort-select { min-width: 190px; }
.filters-toggle { display: none; position: relative; }
.filters-badge { position: absolute; top: -8px; right: -8px; }

.layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; align-items: start; }

.sidebar {
  position: sticky;
  top: 84px;
  display: grid;
  gap: 20px;
  padding-bottom: 12px;
}
.filter-block { display: grid; gap: 8px; }
.filter-block-head { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); }
.category-list, .category-children { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
.category-children { padding-left: 12px; margin-top: 2px; }
.category-row {
  display: flex; justify-content: space-between; align-items: center; gap: 8px;
  width: 100%; padding: 7px 10px; border: 0; border-radius: 8px; background: transparent;
  color: var(--text); font: inherit; font-size: .88rem; text-align: left; cursor: pointer;
  transition: background-color .12s ease, color .12s ease;
}
.category-row.top { font-weight: 650; }
.category-row.child { font-size: .84rem; color: var(--text-muted); }
.category-row:hover { background: var(--surface-subtle); }
.category-row.active { background: color-mix(in srgb, var(--brand) 14%, transparent); color: var(--brand); font-weight: 700; }
.category-row small { color: var(--text-muted); font-weight: 500; }
.category-row.active small { color: inherit; }

.chip-group { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
  padding: 6px 12px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface);
  color: var(--text); font: inherit; font-size: .82rem; font-weight: 600; cursor: pointer;
  transition: border-color .12s ease, color .12s ease, background-color .12s ease;
}
.chip:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.chip.active { background: var(--brand); border-color: var(--brand); color: #fff; }

.content-head { margin-bottom: 12px; }
.result-count { margin: 0; color: var(--text-muted); font-weight: 500; }

.course-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
}
.course-item {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  color: var(--text);
  transition: border-color .15s ease, transform .15s ease;
}
.course-link { display: block; color: inherit; text-decoration: none; }
.course-item:hover {
  border-color: color-mix(in srgb, var(--brand) 40%, var(--border));
  transform: translateY(-2px);
}
.course-media {
  position: relative;
  display: grid;
  place-items: center;
  height: 150px;
  background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 25%, #134e4a), #0f766e);
  background-size: cover;
  background-position: center;
  color: white;
  font-size: 1.8rem;
}
.level-badge {
  position: absolute; left: 10px; top: 10px; padding: 3px 9px; border-radius: 999px;
  background: rgba(0,0,0,.5); color: #fff; font-size: .7rem; font-weight: 700;
}
.course-body { padding: 12px 14px 16px; display: grid; gap: 4px; }
.course-body small { color: var(--text-muted); font-size: .75rem; font-weight: 650; text-transform: uppercase; }
.course-body p { margin: 0; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.course-stats { display: flex; align-items: center; gap: 10px; margin-top: 6px; font-size: .82rem; color: var(--text-muted); font-weight: 600; }
.rating { display: inline-flex; align-items: center; gap: 4px; color: #b45309; }
.rating .pi-star-fill { font-size: .78rem; }
.rating small { color: var(--text-muted); font-weight: 500; }
.course-meta { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-top: 6px; }
.price { font-size: .95rem; font-weight: 750; }
.price.free { color: #16a34a; }
.cart-add {
  width: 32px; height: 32px; border-radius: 999px; border: 1px solid var(--border);
  background: var(--surface); color: var(--brand); cursor: pointer; display: grid; place-items: center;
}
.cart-add:hover { background: var(--brand-soft); }

.skeleton-card {
  height: 260px; border-radius: 16px; border: 1px solid var(--border);
  background: linear-gradient(90deg, var(--surface-subtle) 25%, var(--surface-hover, #eef2f7) 37%, var(--surface-subtle) 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
}
@keyframes shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }

.paginator { margin-top: 22px; justify-content: center; background: transparent; }
.empty-note { padding: 20px 0; }
.filters-drawer :deep(.p-drawer-content) { display: grid; gap: 20px; }
.w-full { width: 100%; }

@media (max-width: 900px) {
  .layout { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .filters-toggle { display: inline-flex; }
}
</style>
