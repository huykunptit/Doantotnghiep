<template>
  <div class="courses-shell">

    <!-- ── Sidebar ───────────────────────── -->
    <aside :class="['courses-sidebar', { 'is-open': sidebarOpen }]">
      <div class="sidebar-overlay" @click="sidebarOpen = false" />

      <div class="sidebar-inner">
        <!-- Categories -->
        <section class="sidebar-card">
          <p class="sidebar-kicker">Danh mục</p>

          <div class="cat-list">
            <button
              type="button"
              :class="['cat-item', { 'is-active': filters.category === '' }]"
              @click="selectCategory('')"
            >
              <span class="cat-name">Tất cả khóa học</span>
              <span class="cat-count">{{ allCourses.length }}</span>
            </button>

            <button
              v-for="cat in categoriesWithCounts"
              :key="cat.id"
              type="button"
              :class="['cat-item', { 'is-active': filters.category === String(cat.id) }]"
              @click="selectCategory(cat.id)"
            >
              <span class="cat-name">{{ cat.name }}</span>
              <span class="cat-count">{{ cat.total_courses }}</span>
            </button>
          </div>
        </section>

        <!-- Filters -->
        <section class="sidebar-card">
          <div class="sidebar-filter-head">
            <p class="sidebar-kicker">Bộ lọc</p>
            <button type="button" class="clear-btn" @click="resetFilters">Xóa lọc</button>
          </div>

          <div class="filter-fields">
            <label class="filter-field">
              <span class="filter-label">Học phí</span>
              <select v-model="filters.price" class="filter-select">
                <option value="">Tất cả</option>
                <option value="free">Miễn phí</option>
                <option value="paid">Trả phí</option>
              </select>
            </label>

            <label class="filter-field">
              <span class="filter-label">Sắp xếp theo</span>
              <select v-model="filters.sort" class="filter-select">
                <option value="newest">Mới nhất</option>
                <option value="popular">Nhiều học viên</option>
                <option value="price_asc">Giá thấp → cao</option>
                <option value="price_desc">Giá cao → thấp</option>
                <option value="rating">Đánh giá cao</option>
              </select>
            </label>
          </div>
        </section>

        <!-- Stats -->
        <section class="sidebar-card sidebar-stats">
          <div class="stat-row">
            <span class="stat-num">{{ allCourses.length }}</span>
            <span class="stat-lbl">Khóa học</span>
          </div>
          <div class="stat-row">
            <span class="stat-num">{{ categoriesWithCounts.length }}</span>
            <span class="stat-lbl">Danh mục</span>
          </div>
          <div class="stat-row">
            <span class="stat-num">{{ freeCoursesCount }}</span>
            <span class="stat-lbl">Miễn phí</span>
          </div>
        </section>
      </div>
    </aside>

    <!-- ── Main ──────────────────────────── -->
    <main class="courses-main">

      <!-- Search bar -->
      <div class="search-bar-wrap">
        <button type="button" class="sidebar-toggle" @click="sidebarOpen = !sidebarOpen">
          <span class="material-symbols-outlined">menu</span>
        </button>

        <form class="search-bar" @submit.prevent="submitSearch">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Tìm khóa học, kỹ năng, chuyên đề..."
            class="search-input"
          >
          <button v-if="filters.search" type="button" class="search-clear" @click="filters.search = ''">
            <span class="material-symbols-outlined">close</span>
          </button>
        </form>

        <!-- View toggle -->
        <div class="view-toggle">
          <button
            type="button"
            :class="['view-btn', { 'is-active': viewMode === 'grid' }]"
            title="Dạng lưới"
            @click="viewMode = 'grid'"
          >
            <span class="material-symbols-outlined">grid_view</span>
          </button>
          <button
            type="button"
            :class="['view-btn', { 'is-active': viewMode === 'topic' }]"
            title="Theo chủ đề"
            @click="viewMode = 'topic'"
          >
            <span class="material-symbols-outlined">category</span>
          </button>
        </div>
      </div>

      <!-- Hero strip -->
      <div class="hero-strip">
        <div class="hero-text">
          <h1 class="hero-title">{{ heroTitle }}</h1>
          <p class="hero-sub">{{ filteredCourses.length }} khóa học phù hợp</p>
        </div>

        <!-- Active filter tags -->
        <div v-if="activeFilterTags.length" class="filter-tags">
          <span
            v-for="tag in activeFilterTags"
            :key="tag.label"
            class="filter-tag"
          >
            {{ tag.label }}
            <button type="button" class="tag-close" @click="tag.clear()">
              <span class="material-symbols-outlined">close</span>
            </button>
          </span>
        </div>
      </div>

      <!-- Category chips (quick filter) -->
      <div class="chip-row">
        <button
          v-for="item in categoryChips"
          :key="item.id"
          type="button"
          :class="['chip', { 'is-active': filters.category === String(item.id) }]"
          @click="selectCategory(item.id)"
        >
          {{ item.name }}
          <span class="chip-count">{{ item.count }}</span>
        </button>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="course-grid">
        <div v-for="i in 9" :key="i" class="skeleton-card" />
      </div>

      <!-- Empty state -->
      <div v-else-if="filteredCourses.length === 0" class="empty-state">
        <span class="material-symbols-outlined empty-icon">search_off</span>
        <h3>Không tìm thấy khóa học</h3>
        <p>Thử đổi từ khóa hoặc xóa bộ lọc để xem thêm.</p>
        <button type="button" class="reset-btn" @click="resetFilters">Xem tất cả</button>
      </div>

      <!-- Grid view -->
      <template v-else-if="viewMode === 'grid'">
        <div class="course-grid">
          <CourseCard v-for="course in paginatedCourses" :key="course.id" :course="course" />
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="pagination">
          <button
            type="button"
            class="page-btn page-btn--prev"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <button
            v-for="page in paginationItems"
            :key="page"
            type="button"
            :class="['page-btn', { 'is-active': currentPage === page }]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button
            type="button"
            class="page-btn page-btn--next"
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </div>
      </template>

      <!-- Topic grouped view -->
      <template v-else>
        <div v-if="groupedCourses.length === 0" class="empty-state">
          <span class="material-symbols-outlined empty-icon">search_off</span>
          <h3>Không có khóa học nào</h3>
        </div>

        <div v-for="group in groupedCourses" :key="group.categoryId" class="topic-group">
          <div class="topic-head">
            <div class="topic-head-left">
              <span class="topic-dot" />
              <h2 class="topic-title">{{ group.categoryName }}</h2>
              <span class="topic-count">{{ group.courses.length }} khóa học</span>
            </div>
            <button
              v-if="group.courses.length > 3"
              type="button"
              class="topic-see-all"
              @click="selectCategory(group.categoryId); viewMode = 'grid'"
            >
              Xem tất cả
              <span class="material-symbols-outlined">arrow_forward</span>
            </button>
          </div>

          <div class="topic-grid">
            <CourseCard
              v-for="course in group.courses.slice(0, 4)"
              :key="course.id"
              :course="course"
            />
          </div>
        </div>
      </template>

    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CourseCard from '~/components/course/CourseCard.vue'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'default' })

type CourseCategory = {
  id: number
  name: string
  children?: CourseCategory[]
  courses_count?: number
}

type FilterState = {
  search: string
  category: string
  sort: 'newest' | 'popular' | 'price_asc' | 'price_desc' | 'rating'
  price: '' | 'free' | 'paid'
}

const route = useRoute()
const router = useRouter()
const allCourses = ref<any[]>([])
const categories = ref<CourseCategory[]>([])
const loading = ref(true)
const currentPage = ref(Number(route.query.page || 1))
const perPage = 9
const viewMode = ref<'grid' | 'topic'>('grid')
const sidebarOpen = ref(false)

const filters = reactive<FilterState>({
  search: (route.query.search as string) || '',
  category: (route.query.category as string) || '',
  sort: ((route.query.sort as FilterState['sort']) || 'newest'),
  price: ((route.query.price as FilterState['price']) || ''),
})

function includesCategory(category: CourseCategory, targetId: number): boolean {
  if (category.id === targetId) return true
  return (category.children || []).some((child) => includesCategory(child, targetId))
}

function countAllCourses(category: CourseCategory): number {
  return Number(category.courses_count || 0) + (category.children || []).reduce((sum, child) => sum + countAllCourses(child), 0)
}

const categoriesWithCounts = computed(() =>
  categories.value.map((cat) => ({ ...cat, total_courses: countAllCourses(cat) })),
)

const categoryLookup = computed(() => {
  const map = new Map<string, string>()
  categories.value.forEach((cat) => {
    map.set(String(cat.id), cat.name)
    ;(cat.children || []).forEach((child) => map.set(String(child.id), `${cat.name} / ${child.name}`))
  })
  return map
})

const categoryChips = computed(() => [
  { id: '', name: 'Tất cả', count: allCourses.value.length },
  ...categoriesWithCounts.value.map((cat) => ({ id: cat.id, name: cat.name, count: cat.total_courses })),
])

const filteredCourses = computed(() => {
  let items = [...allCourses.value]
  const keyword = filters.search.trim().toLowerCase()
  const categoryId = Number(filters.category)

  if (keyword) {
    items = items.filter((course) => {
      const haystack = [course.title, course.description, course.category?.name, course.instructor?.name]
        .filter(Boolean).join(' ').toLowerCase()
      return haystack.includes(keyword)
    })
  }

  if (filters.category) {
    items = items.filter((course) => {
      const id = Number(course.category?.id || course.category_id || 0)
      if (!id) return false
      if (id === categoryId) return true
      return categories.value.some((cat) => cat.id === categoryId && includesCategory(cat, id))
    })
  }

  if (filters.price === 'free') items = items.filter((c) => Number(c.price || 0) === 0)
  if (filters.price === 'paid') items = items.filter((c) => Number(c.price || 0) > 0)

  items.sort((a, b) => {
    if (filters.sort === 'price_asc') return Number(a.price || 0) - Number(b.price || 0)
    if (filters.sort === 'price_desc') return Number(b.price || 0) - Number(a.price || 0)
    if (filters.sort === 'popular') return Number(b.enrollments_count || 0) - Number(a.enrollments_count || 0)
    if (filters.sort === 'rating') return Number(b.reviews_avg_rating || 0) - Number(a.reviews_avg_rating || 0)
    return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime()
  })

  return items
})

const groupedCourses = computed(() => {
  const groups: Array<{ categoryId: string; categoryName: string; courses: any[] }> = []
  const seen = new Map<string, any[]>()

  for (const course of filteredCourses.value) {
    const catId = String(course.category?.id || course.category_id || '')
    const catName = course.category?.name || course.category || 'Khác'
    if (!catId) continue
    if (!seen.has(catId)) seen.set(catId, [])
    seen.get(catId)!.push(course)
  }

  seen.forEach((courses, catId) => {
    const catName = categoryLookup.value.get(catId) || courses[0]?.category?.name || 'Khác'
    groups.push({ categoryId: catId, categoryName: catName, courses })
  })

  return groups.sort((a, b) => b.courses.length - a.courses.length)
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCourses.value.length / perPage)))
const paginatedCourses = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredCourses.value.slice(start, start + perPage)
})
const freeCoursesCount = computed(() => allCourses.value.filter((c) => Number(c.price || 0) === 0).length)

const heroTitle = computed(() => {
  if (filters.search.trim()) return `Kết quả cho "${filters.search.trim()}"`
  if (filters.category) return categoryLookup.value.get(filters.category) || 'Danh mục đã chọn'
  return 'Khám phá toàn bộ khóa học'
})

const activeFilterTags = computed(() => {
  const tags: Array<{ label: string; clear: () => void }> = []
  if (filters.search) tags.push({ label: `"${filters.search}"`, clear: () => { filters.search = '' } })
  if (filters.category) tags.push({ label: categoryLookup.value.get(filters.category) || filters.category, clear: () => { filters.category = '' } })
  if (filters.price) tags.push({ label: filters.price === 'free' ? 'Miễn phí' : 'Trả phí', clear: () => { filters.price = '' } })
  return tags
})

const paginationItems = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, start + 4)
  for (let p = start; p <= end; p++) pages.push(p)
  return pages
})

async function fetchData() {
  loading.value = true
  try {
    const [categoryData, courseData] = await Promise.all([
      useApi<CourseCategory[]>('/categories').catch(() => []),
      useApi<any>('/courses?per_page=100').catch(() => ({ data: [] })),
    ])
    categories.value = categoryData
    allCourses.value = courseData.data || []
  }
  finally { loading.value = false }
}

function syncRoute() {
  const query: Record<string, string> = {}
  if (filters.search) query.search = filters.search
  if (filters.category) query.category = filters.category
  if (filters.sort !== 'newest') query.sort = filters.sort
  if (filters.price) query.price = filters.price
  if (currentPage.value > 1) query.page = String(currentPage.value)
  router.replace({ query })
}

function submitSearch() { currentPage.value = 1 }
function selectCategory(id: number | string) { filters.category = String(id); currentPage.value = 1 }
function resetFilters() { filters.search = ''; filters.category = ''; filters.sort = 'newest'; filters.price = ''; currentPage.value = 1 }
function goToPage(page: number) { currentPage.value = page; window.scrollTo({ top: 0, behavior: 'smooth' }) }

watch(() => ({ ...filters }), () => { currentPage.value = 1; syncRoute() }, { deep: true })
watch(currentPage, syncRoute)
watch(totalPages, (v) => { if (currentPage.value > v) currentPage.value = v })
watch(() => route.query, (q) => {
  filters.search = (q.search as string) || ''
  filters.category = (q.category as string) || ''
  filters.sort = ((q.sort as FilterState['sort']) || 'newest')
  filters.price = ((q.price as FilterState['price']) || '')
  currentPage.value = Math.max(1, Number(q.page || 1))
})

onMounted(fetchData)
</script>

<style scoped>
/* ── Shell ─────────────────────────────── */
.courses-shell {
  display: grid;
  grid-template-columns: 272px minmax(0, 1fr);
  gap: 20px;
  min-height: 100vh;
  padding: 20px;
  align-items: start;
}

/* ── Sidebar ───────────────────────────── */
.sidebar-inner {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 20px;
}

.sidebar-overlay { display: none; }

.sidebar-card {
  border: 1px solid rgba(255, 255, 255, 0.74);
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(16px);
  box-shadow: 0 24px 60px -30px rgba(17, 17, 17, 0.12);
  border-radius: 26px;
  padding: 20px;
}

.sidebar-kicker {
  margin: 0 0 14px;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: var(--green-deep, var(--green-deep));
}

/* Category list */
.cat-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cat-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid transparent;
  background: transparent;
  text-align: left;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 180ms ease, border-color 180ms ease, color 180ms ease, transform 180ms ease;
  color: var(--muted, #5f675f);
}
.cat-item:hover {
  background: rgba(var(--green-rgb), 0.06);
  color: var(--text, #111111);
  transform: translateX(2px);
}
.cat-item.is-active {
  background: rgba(var(--green-rgb), 0.1);
  border-color: rgba(var(--green-rgb), 0.22);
  color: var(--green-deep, var(--green-deep));
  font-weight: 700;
  transform: translateX(2px);
}

.cat-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cat-count {
  flex-shrink: 0;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: var(--muted, #5f675f);
}
.cat-item.is-active .cat-count { color: var(--green-deep, var(--green-deep)); }

/* Filter panel */
.sidebar-filter-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 16px;
}
.sidebar-filter-head .sidebar-kicker { margin: 0; }

.clear-btn {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--green-deep, var(--green-deep));
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: opacity 180ms ease;
}
.clear-btn:hover { opacity: 0.7; }

.filter-fields { display: flex; flex-direction: column; gap: 12px; }

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.filter-label {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text, #111111);
}
.filter-select {
  min-height: 46px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.88);
  padding: 0 14px;
  font-size: 0.88rem;
  color: var(--text, #111111);
  outline: none;
  cursor: pointer;
  transition: border-color 180ms ease, box-shadow 180ms ease;
}
.filter-select:focus {
  border-color: rgba(var(--green-rgb), 0.42);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.08);
}

/* Stats */
.sidebar-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
  padding: 0;
  overflow: hidden;
}
.stat-row {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px 8px;
  border-right: 1px solid rgba(17, 17, 17, 0.07);
}
.stat-row:last-child { border-right: none; }
.stat-num {
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.05em;
  color: var(--text, #111111);
  line-height: 1;
}
.stat-lbl {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--muted, #5f675f);
}

/* ── Main ──────────────────────────────── */
.courses-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* Search bar */
.search-bar-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid rgba(255, 255, 255, 0.74);
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(16px);
  border-radius: 26px;
  padding: 12px 16px;
  box-shadow: 0 24px 60px -30px rgba(17, 17, 17, 0.12);
}

.sidebar-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 14px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: rgba(17, 17, 17, 0.03);
  color: var(--muted, #5f675f);
  cursor: pointer;
  flex-shrink: 0;
}
.sidebar-toggle .material-symbols-outlined { font-size: 22px; }

.search-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-height: 48px;
  padding: 0 16px;
  border-radius: 16px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: rgba(17, 17, 17, 0.02);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}
.search-bar:focus-within {
  border-color: rgba(var(--green-rgb), 0.42);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.08);
}
.search-icon { font-size: 20px; color: var(--muted, #5f675f); flex-shrink: 0; }
.search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 0.95rem;
  color: var(--text, #111111);
}
.search-input::placeholder { color: var(--muted, #5f675f); }
.search-clear {
  display: flex;
  align-items: center;
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--muted, #5f675f);
  padding: 0;
  flex-shrink: 0;
}
.search-clear .material-symbols-outlined { font-size: 18px; }

.view-toggle {
  display: flex;
  gap: 4px;
  background: rgba(17, 17, 17, 0.04);
  border-radius: 14px;
  padding: 4px;
  flex-shrink: 0;
}
.view-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--muted, #5f675f);
  cursor: pointer;
  transition: background 180ms ease, color 180ms ease;
}
.view-btn:hover { color: var(--text, #111111); }
.view-btn.is-active {
  background: rgba(255, 255, 255, 0.9);
  color: var(--green-deep, var(--green-deep));
  box-shadow: 0 2px 8px rgba(17, 17, 17, 0.08);
}
.view-btn .material-symbols-outlined { font-size: 20px; }

/* Hero strip */
.hero-strip {
  border: 1px solid rgba(255, 255, 255, 0.74);
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(16px);
  border-radius: 26px;
  padding: 22px 26px;
  box-shadow: 0 24px 60px -30px rgba(17, 17, 17, 0.12);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}
.hero-title {
  margin: 0;
  font-size: 1.65rem;
  font-weight: 800;
  letter-spacing: -0.05em;
  color: var(--text, #111111);
}
.hero-sub {
  margin: 4px 0 0;
  font-size: 0.85rem;
  color: var(--muted, #5f675f);
}
.filter-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.filter-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid rgba(var(--green-rgb), 0.22);
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep, var(--green-deep));
  font-size: 0.82rem;
  font-weight: 700;
}
.tag-close {
  display: flex;
  align-items: center;
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--green-deep, var(--green-deep));
  padding: 0;
}
.tag-close .material-symbols-outlined { font-size: 14px; }

/* Category chips */
.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: rgba(255, 255, 255, 0.88);
  color: var(--muted, #5f675f);
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 180ms ease;
}
.chip:hover {
  border-color: rgba(var(--green-rgb), 0.3);
  color: var(--green-deep, var(--green-deep));
}
.chip.is-active {
  background: var(--green);
  border-color: transparent;
  color: #fff;
}
.chip-count {
  font-size: 0.72rem;
  font-weight: 800;
  opacity: 0.7;
}
.chip.is-active .chip-count { opacity: 0.85; }

/* ── Course grid ───────────────────────── */
.course-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

/* Skeleton */
.skeleton-card {
  height: 340px;
  border-radius: 26px;
  background: rgba(17,17,17,0.06);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  min-height: 320px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 26px;
  background: rgba(255, 255, 255, 0.9);
  text-align: center;
  padding: 40px;
}
.empty-icon {
  font-size: 56px;
  color: rgba(var(--green-rgb), 0.3);
}
.empty-state h3 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text, #111111); }
.empty-state p { margin: 0; color: var(--muted, #5f675f); font-size: 0.9rem; }
.reset-btn {
  margin-top: 8px;
  height: 42px;
  padding: 0 20px;
  border-radius: 999px;
  border: none;
  background: var(--green);
  color: #fff;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(var(--green-rgb), 0.3);
  transition: filter 180ms ease, transform 180ms ease;
}
.reset-btn:hover { filter: brightness(1.05); transform: translateY(-1px); }

/* ── Pagination ────────────────────────── */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding-top: 8px;
}
.page-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 42px;
  height: 42px;
  padding: 0 10px;
  border-radius: 14px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: rgba(255, 255, 255, 0.88);
  color: var(--muted, #5f675f);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 180ms ease;
}
.page-btn:hover:not(:disabled) {
  border-color: rgba(var(--green-rgb), 0.3);
  color: var(--green-deep, var(--green-deep));
  transform: translateY(-1px);
}
.page-btn.is-active {
  background: var(--green);
  border-color: transparent;
  color: #fff;
  box-shadow: 0 6px 16px rgba(var(--green-rgb), 0.3);
}
.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.page-btn .material-symbols-outlined { font-size: 20px; }

/* ── Topic groups ──────────────────────── */
.topic-group {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.topic-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}
.topic-head-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.topic-dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: var(--green);
  box-shadow: 0 0 0 4px rgba(var(--green-rgb), 0.12);
  flex-shrink: 0;
}
.topic-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: var(--text, #111111);
}
.topic-count {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--muted, #5f675f);
  background: rgba(17, 17, 17, 0.05);
  border-radius: 999px;
  padding: 3px 10px;
}

.topic-see-all {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid rgba(var(--green-rgb), 0.22);
  background: rgba(var(--green-rgb), 0.06);
  color: var(--green-deep, var(--green-deep));
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 180ms ease;
}
.topic-see-all:hover { background: rgba(var(--green-rgb), 0.12); transform: translateY(-1px); }
.topic-see-all .material-symbols-outlined { font-size: 16px; transition: transform 180ms ease; }
.topic-see-all:hover .material-symbols-outlined { transform: translateX(2px); }

.topic-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

/* ── Responsive ────────────────────────── */
@media (max-width: 1280px) {
  .course-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .topic-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 1080px) {
  .courses-shell { grid-template-columns: 1fr; }

  .courses-sidebar {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    pointer-events: none;
    opacity: 0;
    transition: opacity 200ms ease;
  }
  .courses-sidebar.is-open {
    pointer-events: all;
    opacity: 1;
  }
  .sidebar-overlay {
    display: block;
    position: absolute;
    inset: 0;
    background: rgba(17, 17, 17, 0.28);
  }
  .sidebar-inner {
    position: relative;
    z-index: 1;
    width: min(320px, 85vw);
    height: 100%;
    overflow-y: auto;
    padding: 20px 16px;
    background: #f6f8f3;
    border-right: 1px solid rgba(17, 17, 17, 0.08);
    box-shadow: 4px 0 24px rgba(17, 17, 17, 0.12);
  }
  .sidebar-toggle { display: flex; }
}

@media (max-width: 760px) {
  .courses-shell { padding: 12px; gap: 12px; }
  .course-grid { grid-template-columns: 1fr; }
  .topic-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .hero-strip { padding: 18px; }
  .hero-title { font-size: 1.35rem; }
}

@media (max-width: 480px) {
  .topic-grid { grid-template-columns: 1fr; }
}
</style>
