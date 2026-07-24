<template>
  <div class="cp-page">

    <!-- ── Hero ── -->
    <section class="cp-hero">
      <div class="cp-blob cp-blob--a" aria-hidden="true" />
      <div class="cp-blob cp-blob--b" aria-hidden="true" />
      <div class="cp-blob cp-blob--c" aria-hidden="true" />
      <div class="cp-hero-inner">
        <div class="cp-eyebrow">
          <span class="cp-eyebrow-dot" />
          {{ categoriesWithCounts.length }} danh mục · {{ allCourses.length }}+ khóa học
        </div>
        <h1 class="cp-hero-h1">
          Nâng tầm kỹ năng,<br>
          <em class="cp-hero-em">Bứt phá sự nghiệp</em>
        </h1>
        <p class="cp-hero-sub">
          Học cùng chuyên gia thực tế, hoàn thành dự án và nhận chứng chỉ được công nhận.
        </p>
        <form class="cp-search-box" @submit.prevent="submitSearch">
          <span class="material-symbols-outlined cp-search-ico">search</span>
          <input
            v-model="filters.search"
            type="text"
            class="cp-search-field"
            placeholder="Tìm khóa học, kỹ năng, chuyên đề..."
          >
          <button v-if="filters.search" type="button" class="cp-search-x" @click="filters.search = ''">
            <span class="material-symbols-outlined">close</span>
          </button>
          <button type="submit" class="cp-search-go">
            Tìm kiếm
            <span class="material-symbols-outlined">arrow_forward</span>
          </button>
        </form>
        <div class="cp-hero-stats">
          <div class="cp-hero-stat">
            <span class="cp-stat-n">{{ allCourses.length }}</span>
            <span class="cp-stat-l">Khóa học</span>
          </div>
          <div class="cp-hero-sep" />
          <div class="cp-hero-stat">
            <span class="cp-stat-n">{{ categoriesWithCounts.length }}</span>
            <span class="cp-stat-l">Danh mục</span>
          </div>
          <div class="cp-hero-sep" />
          <div class="cp-hero-stat">
            <span class="cp-stat-n">{{ freeCoursesCount }}</span>
            <span class="cp-stat-l">Miễn phí</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Category rail ── -->
    <div class="cp-cat-rail">
      <button
        v-for="item in categoryChips"
        :key="item.id"
        type="button"
        :class="['cp-cat-pill', { 'is-on': filters.category === String(item.id) }]"
        @click="selectCategory(item.id)"
      >
        {{ item.name }}
        <span class="cp-cat-n">{{ item.count }}</span>
      </button>
    </div>

    <!-- ── Shell ── -->
    <div class="cp-shell">

      <!-- Mobile overlay -->
      <Transition name="cp-fade">
        <div v-if="sidebarOpen" class="cp-overlay" @click="sidebarOpen = false" />
      </Transition>

      <!-- Sidebar -->
      <aside :class="['cp-sidebar', { 'is-open': sidebarOpen }]">
        <div class="cp-sb-inner">

          <!-- Categories -->
          <div class="cp-sb-block">
            <p class="cp-sb-label">Danh mục</p>
            <nav class="cp-cat-nav">
              <button
                type="button"
                :class="['cp-cat-row', { 'is-on': filters.category === '' }]"
                @click="selectCategory('')"
              >
                <span class="cp-cat-row-name">Tất cả khóa học</span>
                <span class="cp-cat-row-n">{{ allCourses.length }}</span>
              </button>
              <button
                v-for="cat in categoriesWithCounts"
                :key="cat.id"
                type="button"
                :class="['cp-cat-row', { 'is-on': filters.category === String(cat.id) }]"
                @click="selectCategory(cat.id)"
              >
                <span class="cp-cat-row-name">{{ cat.name }}</span>
                <span class="cp-cat-row-n">{{ cat.total_courses }}</span>
              </button>
            </nav>
          </div>

          <!-- Filters -->
          <div class="cp-sb-block">
            <div class="cp-sb-block-head">
              <p class="cp-sb-label">Bộ lọc</p>
              <button type="button" class="cp-clear-btn" @click="resetFilters">Xóa lọc</button>
            </div>
            <div class="cp-filters">
              <label class="cp-filter">
                <span class="cp-filter-lbl">Học phí</span>
                <select v-model="filters.price" class="cp-filter-sel">
                  <option value="">Tất cả</option>
                  <option value="free">Miễn phí</option>
                  <option value="paid">Trả phí</option>
                </select>
              </label>
              <label class="cp-filter">
                <span class="cp-filter-lbl">Sắp xếp theo</span>
                <select v-model="filters.sort" class="cp-filter-sel">
                  <option value="newest">Mới nhất</option>
                  <option value="popular">Nhiều học viên</option>
                  <option value="price_asc">Giá tăng dần</option>
                  <option value="price_desc">Giá giảm dần</option>
                  <option value="rating">Đánh giá cao</option>
                </select>
              </label>
            </div>
          </div>

          <!-- Stats -->
          <div class="cp-sb-stats">
            <div class="cp-sb-stat-item">
              <strong>{{ allCourses.length }}</strong>
              <span>Khóa học</span>
            </div>
            <div class="cp-sb-stat-item">
              <strong>{{ categoriesWithCounts.length }}</strong>
              <span>Danh mục</span>
            </div>
            <div class="cp-sb-stat-item">
              <strong>{{ freeCoursesCount }}</strong>
              <span>Miễn phí</span>
            </div>
          </div>

        </div>
      </aside>

      <!-- Main -->
      <main class="cp-main">

        <!-- Toolbar -->
        <div class="cp-bar">
          <div class="cp-bar-left">
            <button type="button" class="cp-filter-toggle" @click="sidebarOpen = !sidebarOpen">
              <span class="material-symbols-outlined">tune</span>
              Lọc
            </button>
            <div class="cp-bar-heading">
              <h2 class="cp-bar-title">{{ heroTitle }}</h2>
              <p class="cp-bar-count">{{ filteredCourses.length }} kết quả</p>
            </div>
          </div>
          <div class="cp-bar-right">
            <div v-if="activeFilterTags.length" class="cp-active-tags">
              <span v-for="tag in activeFilterTags" :key="tag.label" class="cp-active-tag">
                {{ tag.label }}
                <button type="button" class="cp-tag-x" @click="tag.clear()">
                  <span class="material-symbols-outlined">close</span>
                </button>
              </span>
            </div>
            <div class="cp-view-tgl">
              <button
                type="button"
                :class="['cp-vbtn', { on: viewMode === 'grid' }]"
                title="Dạng lưới"
                @click="viewMode = 'grid'"
              >
                <span class="material-symbols-outlined">grid_view</span>
              </button>
              <button
                type="button"
                :class="['cp-vbtn', { on: viewMode === 'topic' }]"
                title="Theo chủ đề"
                @click="viewMode = 'topic'"
              >
                <span class="material-symbols-outlined">category</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="cp-grid">
          <div v-for="i in 9" :key="i" class="cp-skel" />
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredCourses.length === 0" class="cp-empty">
          <div class="cp-empty-icon-wrap">
            <span class="material-symbols-outlined">search_off</span>
          </div>
          <h3>Không tìm thấy khóa học</h3>
          <p>Thử đổi từ khóa hoặc xóa bộ lọc để xem thêm.</p>
          <button type="button" class="cp-empty-btn" @click="resetFilters">Xem tất cả khóa học</button>
        </div>

        <!-- Grid view -->
        <template v-else-if="viewMode === 'grid'">
          <div class="cp-grid">
            <CourseCard v-for="course in paginatedCourses" :key="course.id" :course="course" />
          </div>
          <div v-if="totalPages > 1" class="cp-pager">
            <button
              type="button"
              class="cp-pg-btn"
              :disabled="currentPage === 1"
              @click="goToPage(currentPage - 1)"
            >
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <button
              v-for="page in paginationItems"
              :key="page"
              type="button"
              :class="['cp-pg-btn', { on: currentPage === page }]"
              @click="goToPage(page)"
            >{{ page }}</button>
            <button
              type="button"
              class="cp-pg-btn"
              :disabled="currentPage === totalPages"
              @click="goToPage(currentPage + 1)"
            >
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>
        </template>

        <!-- Topic grouped view -->
        <template v-else>
          <div v-if="groupedCourses.length === 0" class="cp-empty">
            <div class="cp-empty-icon-wrap">
              <span class="material-symbols-outlined">search_off</span>
            </div>
            <h3>Không có khóa học nào</h3>
          </div>
          <div v-for="group in groupedCourses" :key="group.categoryId" class="cp-tgroup">
            <div class="cp-tgroup-head">
              <div class="cp-tgroup-left">
                <span class="cp-tdot" />
                <h2 class="cp-ttitle">{{ group.categoryName }}</h2>
                <span class="cp-tcnt">{{ group.courses.length }} khóa học</span>
              </div>
              <button
                v-if="group.courses.length > 3"
                type="button"
                class="cp-tsee-all"
                @click="selectCategory(group.categoryId); viewMode = 'grid'"
              >
                Xem tất cả
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </div>
            <div class="cp-tgrid">
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
/* ── Page ── */
.cp-page {
  min-height: 100vh;
  background: var(--surface-strong, #f6f8f3);
}

/* ── Hero ── */
.cp-hero {
  position: relative;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 50%, #163d2a 100%);
  padding: 68px 24px 84px;
  overflow: hidden;
  text-align: center;
}

.cp-blob {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.cp-blob--a {
  width: 700px; height: 700px;
  top: -280px; right: -120px;
  background: radial-gradient(ellipse, rgba(29, 158, 117, 0.2) 0%, transparent 65%);
}
.cp-blob--b {
  width: 450px; height: 450px;
  bottom: -180px; left: -60px;
  background: radial-gradient(ellipse, rgba(29, 158, 117, 0.14) 0%, transparent 65%);
}
.cp-blob--c {
  width: 260px; height: 260px;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  background: radial-gradient(ellipse, rgba(29, 158, 117, 0.06) 0%, transparent 70%);
}

.cp-hero-inner {
  position: relative;
  z-index: 1;
  max-width: 700px;
  margin: 0 auto;
}

.cp-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: rgba(29, 158, 117, 0.95);
  background: rgba(29, 158, 117, 0.1);
  border: 1px solid rgba(29, 158, 117, 0.25);
  padding: 5px 16px 5px 12px;
  border-radius: 999px;
  margin-bottom: 24px;
}
.cp-eyebrow-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--green);
  animation: pulse-dot 2s ease-in-out infinite;
  flex-shrink: 0;
}
@keyframes pulse-dot {
  0%, 100% { box-shadow: 0 0 0 0 rgba(29, 158, 117, 0.4); }
  50% { box-shadow: 0 0 0 5px rgba(29, 158, 117, 0); }
}

.cp-hero-h1 {
  font-size: clamp(2.1rem, 5vw, 3.5rem);
  font-weight: 900;
  letter-spacing: -0.055em;
  line-height: 1.08;
  color: #fff;
  margin: 0 0 18px;
}
.cp-hero-em {
  font-style: normal;
  background: linear-gradient(90deg, #1D9E75 0%, #34d39b 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.cp-hero-sub {
  font-size: 1.05rem;
  line-height: 1.75;
  color: rgba(255, 255, 255, 0.5);
  margin: 0 auto 36px;
  max-width: 520px;
}

/* Search box */
.cp-search-box {
  display: flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.07);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;
  padding: 7px 7px 7px 22px;
  max-width: 600px;
  margin: 0 auto 36px;
  transition: border-color 200ms ease, background 200ms ease;
}
.cp-search-box:focus-within {
  border-color: rgba(29, 158, 117, 0.5);
  background: rgba(255, 255, 255, 0.1);
}
.cp-search-ico {
  font-size: 20px;
  color: rgba(255, 255, 255, 0.38);
  flex-shrink: 0;
  margin-right: 10px;
}
.cp-search-field {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 0.95rem;
  color: #fff;
}
.cp-search-field::placeholder { color: rgba(255, 255, 255, 0.32); }
.cp-search-x {
  display: flex;
  align-items: center;
  background: transparent;
  border: none;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.38);
  padding: 0 8px;
  flex-shrink: 0;
}
.cp-search-x .material-symbols-outlined { font-size: 16px; }
.cp-search-go {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 44px;
  padding: 0 22px;
  border-radius: 999px;
  border: none;
  background: var(--green);
  color: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  flex-shrink: 0;
  transition: filter 180ms ease, transform 180ms ease, box-shadow 180ms ease;
  box-shadow: 0 4px 16px rgba(29, 158, 117, 0.4);
}
.cp-search-go:hover {
  filter: brightness(1.08);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(29, 158, 117, 0.5);
}
.cp-search-go .material-symbols-outlined { font-size: 17px; }

/* Hero stats */
.cp-hero-stats {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 32px;
  flex-wrap: wrap;
}
.cp-hero-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
}
.cp-stat-n {
  font-size: 1.8rem;
  font-weight: 900;
  letter-spacing: -0.06em;
  color: #fff;
  line-height: 1;
}
.cp-stat-l {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: rgba(255, 255, 255, 0.38);
}
.cp-hero-sep {
  width: 1px;
  height: 38px;
  background: rgba(255, 255, 255, 0.1);
}

/* ── Category rail ── */
.cp-cat-rail {
  background: rgba(255, 255, 255, 0.95);
  border-bottom: 1px solid var(--line);
  padding: 12px 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  position: sticky;
  top: 0;
  z-index: 10;
  backdrop-filter: blur(12px);
}
.cp-cat-rail::-webkit-scrollbar { display: none; }
[data-theme="dark"] .cp-cat-rail {
  background: rgba(12, 29, 21, 0.95);
  border-bottom-color: rgba(255, 255, 255, 0.07);
}

.cp-cat-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1.5px solid var(--line);
  background: transparent;
  color: var(--muted);
  font-size: 0.83rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 160ms ease;
}
.cp-cat-pill:hover {
  border-color: rgba(var(--green-rgb), 0.35);
  color: var(--green-deep);
  background: rgba(var(--green-rgb), 0.05);
}
.cp-cat-pill.is-on {
  background: var(--green);
  border-color: transparent;
  color: #fff;
  box-shadow: 0 4px 12px rgba(var(--green-rgb), 0.3);
}
.cp-cat-n {
  font-size: 0.67rem;
  font-weight: 800;
  opacity: 0.72;
}
.cp-cat-pill.is-on .cp-cat-n { opacity: 0.9; }

/* ── Shell ── */
.cp-shell {
  display: grid;
  grid-template-columns: 264px minmax(0, 1fr);
  min-height: calc(100vh - 200px);
  align-items: start;
  position: relative;
}

/* Mobile overlay */
.cp-overlay {
  position: fixed;
  inset: 0;
  z-index: 39;
  background: rgba(5, 20, 10, 0.4);
  backdrop-filter: blur(2px);
}
.cp-fade-enter-active, .cp-fade-leave-active { transition: opacity 200ms ease; }
.cp-fade-enter-from, .cp-fade-leave-to { opacity: 0; }

/* ── Sidebar ── */
.cp-sidebar {
  height: calc(100vh - 91px);
  overflow-y: auto;
  overflow-x: hidden;
  border-right: 1px solid var(--line);
  background: var(--surface-strong, #f6f8f3);
  position: sticky;
  top: 57px;
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--green-rgb), 0.18) transparent;
}
[data-theme="dark"] .cp-sidebar {
  background: #0c1d15;
  border-right-color: rgba(255, 255, 255, 0.06);
}

.cp-sb-inner {
  padding: 20px 14px;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.cp-sb-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.cp-sb-block-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cp-sb-label {
  margin: 0;
  font-size: 0.63rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: var(--muted);
  opacity: 0.65;
}

.cp-clear-btn {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--green-deep);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: opacity 150ms;
}
.cp-clear-btn:hover { opacity: 0.6; }

/* Category nav */
.cp-cat-nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cp-cat-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  height: 36px;
  padding: 0 10px;
  border-radius: 10px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  color: var(--muted);
  transition: background 130ms ease, color 130ms ease;
  position: relative;
}
.cp-cat-row:hover {
  background: rgba(var(--green-rgb), 0.07);
  color: var(--text);
}
.cp-cat-row.is-on {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  font-weight: 700;
}
.cp-cat-row.is-on::before {
  content: '';
  position: absolute;
  left: 0; top: 7px; bottom: 7px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--green);
}
[data-theme="dark"] .cp-cat-row.is-on { background: rgba(var(--green-rgb), 0.14); color: #4ade80; }
.cp-cat-row-name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}
.cp-cat-row-n {
  flex-shrink: 0;
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--muted);
  background: rgba(17, 17, 17, 0.06);
  padding: 2px 7px;
  border-radius: 999px;
}
.cp-cat-row.is-on .cp-cat-row-n {
  color: var(--green-deep);
  background: rgba(var(--green-rgb), 0.12);
}
[data-theme="dark"] .cp-cat-row-n { background: rgba(255, 255, 255, 0.07); }

/* Filters */
.cp-filters { display: flex; flex-direction: column; gap: 12px; }
.cp-filter { display: flex; flex-direction: column; gap: 5px; }
.cp-filter-lbl {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text);
}
.cp-filter-sel {
  min-height: 42px;
  border: 1.5px solid var(--line);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.8);
  padding: 0 12px;
  font-size: 0.875rem;
  color: var(--text);
  outline: none;
  cursor: pointer;
  transition: border-color 150ms;
}
.cp-filter-sel:focus { border-color: rgba(var(--green-rgb), 0.45); }
[data-theme="dark"] .cp-filter-sel {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.1);
  color: #e2e8e4;
}

/* Sidebar stats */
.cp-sb-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  border: 1.5px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.7);
}
[data-theme="dark"] .cp-sb-stats {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
}
.cp-sb-stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 14px 6px;
  border-right: 1px solid var(--line);
}
.cp-sb-stat-item:last-child { border-right: none; }
[data-theme="dark"] .cp-sb-stat-item { border-right-color: rgba(255, 255, 255, 0.07); }
.cp-sb-stat-item strong {
  font-size: 1.4rem;
  font-weight: 900;
  letter-spacing: -0.05em;
  color: var(--text);
  line-height: 1;
}
.cp-sb-stat-item span {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--muted);
}

/* ── Main ── */
.cp-main {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 20px 22px 48px;
  min-width: 0;
}

/* Toolbar */
.cp-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.8);
  border-radius: 20px;
  padding: 14px 18px;
  box-shadow: 0 4px 20px -8px rgba(17, 17, 17, 0.07);
}
[data-theme="dark"] .cp-bar {
  background: rgba(15, 30, 20, 0.9);
  border-color: rgba(255, 255, 255, 0.06);
}

.cp-bar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.cp-filter-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 38px;
  padding: 0 14px;
  border-radius: 12px;
  border: 1.5px solid var(--line);
  background: transparent;
  color: var(--muted);
  font-size: 0.83rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms;
}
.cp-filter-toggle:hover {
  border-color: rgba(var(--green-rgb), 0.35);
  color: var(--green-deep);
  background: rgba(var(--green-rgb), 0.05);
}
.cp-filter-toggle .material-symbols-outlined { font-size: 18px; }

.cp-bar-heading { display: flex; flex-direction: column; gap: 1px; }
.cp-bar-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--text);
}
.cp-bar-count {
  margin: 0;
  font-size: 0.77rem;
  color: var(--muted);
}

.cp-bar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.cp-active-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.cp-active-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 30px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid rgba(var(--green-rgb), 0.22);
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep);
  font-size: 0.8rem;
  font-weight: 700;
}
.cp-tag-x {
  display: flex;
  align-items: center;
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--green-deep);
  padding: 0;
}
.cp-tag-x .material-symbols-outlined { font-size: 13px; }

.cp-view-tgl {
  display: flex;
  gap: 3px;
  background: rgba(17, 17, 17, 0.05);
  border-radius: 12px;
  padding: 3px;
}
[data-theme="dark"] .cp-view-tgl { background: rgba(255, 255, 255, 0.06); }
.cp-vbtn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px; height: 36px;
  border-radius: 9px;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: background 140ms, color 140ms;
}
.cp-vbtn:hover { color: var(--text); }
.cp-vbtn.on {
  background: rgba(255, 255, 255, 0.95);
  color: var(--green-deep);
  box-shadow: 0 2px 8px rgba(17, 17, 17, 0.1);
}
[data-theme="dark"] .cp-vbtn.on { background: rgba(255, 255, 255, 0.1); color: #4ade80; }
.cp-vbtn .material-symbols-outlined { font-size: 19px; }

/* ── Grid ── */
.cp-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

/* Skeleton */
.cp-skel {
  height: 340px;
  border-radius: 22px;
  background: linear-gradient(90deg, rgba(17,17,17,0.05) 25%, rgba(17,17,17,0.09) 50%, rgba(17,17,17,0.05) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Empty state */
.cp-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  min-height: 360px;
  background: rgba(255, 255, 255, 0.9);
  border: 1.5px solid var(--line);
  border-radius: 24px;
  text-align: center;
  padding: 48px;
}
[data-theme="dark"] .cp-empty { background: rgba(255, 255, 255, 0.04); }
.cp-empty-icon-wrap {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: rgba(var(--green-rgb), 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
}
.cp-empty-icon-wrap .material-symbols-outlined {
  font-size: 36px;
  color: rgba(var(--green-rgb), 0.45);
}
.cp-empty h3 { margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text); }
.cp-empty p { margin: 0; font-size: 0.9rem; color: var(--muted); max-width: 320px; }
.cp-empty-btn {
  height: 44px;
  padding: 0 24px;
  border-radius: 999px;
  border: none;
  background: var(--green);
  color: #fff;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  margin-top: 4px;
  box-shadow: 0 6px 18px rgba(var(--green-rgb), 0.32);
  transition: filter 150ms, transform 150ms;
}
.cp-empty-btn:hover { filter: brightness(1.06); transform: translateY(-1px); }

/* ── Pagination ── */
.cp-pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding-top: 8px;
}
.cp-pg-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 42px;
  height: 42px;
  padding: 0 10px;
  border-radius: 12px;
  border: 1.5px solid var(--line);
  background: rgba(255, 255, 255, 0.9);
  color: var(--muted);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 160ms ease;
}
.cp-pg-btn:hover:not(:disabled) {
  border-color: rgba(var(--green-rgb), 0.35);
  color: var(--green-deep);
  transform: translateY(-1px);
}
.cp-pg-btn.on {
  background: var(--green);
  border-color: transparent;
  color: #fff;
  box-shadow: 0 4px 14px rgba(var(--green-rgb), 0.35);
}
.cp-pg-btn:disabled { opacity: 0.32; cursor: not-allowed; }
.cp-pg-btn .material-symbols-outlined { font-size: 20px; }
[data-theme="dark"] .cp-pg-btn { background: rgba(255, 255, 255, 0.05); }

/* ── Topic groups ── */
.cp-tgroup { display: flex; flex-direction: column; gap: 16px; }

.cp-tgroup-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}
.cp-tgroup-left { display: flex; align-items: center; gap: 10px; }
.cp-tdot {
  width: 10px; height: 10px;
  border-radius: 50%;
  background: var(--green);
  box-shadow: 0 0 0 4px rgba(var(--green-rgb), 0.14);
  flex-shrink: 0;
}
.cp-ttitle {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: var(--text);
}
.cp-tcnt {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--muted);
  background: rgba(17, 17, 17, 0.06);
  border-radius: 999px;
  padding: 3px 10px;
}
[data-theme="dark"] .cp-tcnt { background: rgba(255, 255, 255, 0.07); }
.cp-tsee-all {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid rgba(var(--green-rgb), 0.22);
  background: rgba(var(--green-rgb), 0.06);
  color: var(--green-deep);
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 160ms ease;
}
.cp-tsee-all:hover {
  background: rgba(var(--green-rgb), 0.12);
  transform: translateY(-1px);
}
.cp-tsee-all .material-symbols-outlined {
  font-size: 16px;
  transition: transform 160ms ease;
}
.cp-tsee-all:hover .material-symbols-outlined { transform: translateX(3px); }

.cp-tgrid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

/* ── Responsive ── */
@media (max-width: 1280px) {
  .cp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .cp-tgrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 1080px) {
  .cp-shell { grid-template-columns: 1fr; }
  .cp-sidebar {
    position: fixed;
    inset: 0;
    z-index: 40;
    height: 100%;
    width: min(300px, 85vw);
    transform: translateX(-110%);
    transition: transform 240ms cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 4px 0 30px rgba(5, 20, 10, 0.18);
  }
  .cp-sidebar.is-open { transform: translateX(0); }
}

@media (max-width: 760px) {
  .cp-hero { padding: 48px 18px 64px; }
  .cp-search-go .material-symbols-outlined { display: none; }
  .cp-search-go { padding: 0 16px; gap: 0; }
  .cp-main { padding: 14px 14px 32px; }
  .cp-grid { grid-template-columns: 1fr; }
  .cp-tgrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 480px) {
  .cp-tgrid { grid-template-columns: 1fr; }
  .cp-hero-h1 { font-size: 1.9rem; }
}
</style>
