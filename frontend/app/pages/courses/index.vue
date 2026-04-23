<template>
  <section class="bg-surface">
    <div class="border-b border-surface-dim/50 bg-surface-lowest">
      <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-8 gap-y-3 px-4 py-3 text-sm font-semibold text-on-surface-variant sm:px-6 lg:px-8">
        <NuxtLink
          v-for="segment in audienceSegments"
          :key="segment"
          to="/courses"
          class="transition-colors hover:text-primary"
        >
          {{ segment }}
        </NuxtLink>
      </div>
    </div>

    <div class="border-b border-surface-dim/50 bg-surface-lowest">
      <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-center gap-4">
            <NuxtLink to="/" class="font-headline text-4xl font-extrabold tracking-[-0.06em] text-primary">
              EduPress
            </NuxtLink>
            <div class="hidden items-center gap-2 rounded-full border border-surface-dim/50 bg-surface px-4 py-2 text-sm font-semibold text-on-surface-variant md:flex">
              <span>Khám phá</span>
              <span class="material-symbols-outlined text-[18px]">expand_more</span>
            </div>
          </div>

          <form class="flex w-full max-w-3xl items-center gap-3 rounded-full border border-surface-dim/60 bg-surface px-3 py-3 shadow-sm" @submit.prevent="submitSearch">
            <input
              v-model="filters.search"
              type="text"
              placeholder="Tìm khóa học, kỹ năng, chuyên đề..."
              class="min-w-0 flex-1 bg-transparent px-4 text-base text-on-surface outline-none placeholder:text-outline"
            >
            <button type="submit" class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/20 transition-transform hover:scale-105">
              <span class="material-symbols-outlined text-[22px]">search</span>
            </button>
          </form>

          <div class="hidden items-center gap-5 lg:flex">
            <NuxtLink to="/login" class="text-sm font-semibold text-primary hover:opacity-80">Đăng nhập</NuxtLink>
            <NuxtLink to="/register" class="rounded-2xl border border-primary px-5 py-3 text-sm font-bold text-primary transition-colors hover:bg-primary hover:text-white">
              Tham gia miễn phí
            </NuxtLink>
          </div>
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            v-for="item in categoryChips"
            :key="item.id"
            type="button"
            class="rounded-full border px-4 py-2 text-sm font-semibold transition-all"
            :class="filters.category === String(item.id) ? 'border-primary bg-primary text-white shadow-lg shadow-primary/15' : 'border-surface-dim/50 bg-surface text-on-surface hover:border-primary/40 hover:text-primary'"
            @click="selectCategory(item.id)"
          >
            {{ item.name }}
            <span class="ml-2 text-xs opacity-70">{{ item.count }}</span>
          </button>
        </div>
      </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <div class="grid gap-8 xl:grid-cols-[290px_minmax(0,1fr)]">
        <aside class="space-y-6">
          <section class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-6 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Danh mục nổi bật</p>
            <div class="mt-5 space-y-3">
              <button
                type="button"
                class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left transition-all"
                :class="filters.category === '' ? 'border-primary bg-primary/5 text-primary' : 'border-surface-dim/40 bg-surface hover:border-primary/30'"
                @click="selectCategory('')"
              >
                <span class="font-semibold">Tất cả danh mục</span>
                <span class="text-xs font-bold uppercase tracking-[0.2em]">{{ allCourses.length }}</span>
              </button>

              <button
                v-for="category in categoriesWithCounts"
                :key="category.id"
                type="button"
                class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left transition-all"
                :class="filters.category === String(category.id) ? 'border-primary bg-primary/5 text-primary' : 'border-surface-dim/40 bg-surface hover:border-primary/30'"
                @click="selectCategory(category.id)"
              >
                <div>
                  <p class="font-semibold">{{ category.name }}</p>
                  <p v-if="category.children?.length" class="mt-1 text-xs text-on-surface-variant">
                    {{ category.children.length }} nhánh con
                  </p>
                </div>
                <span class="text-xs font-bold uppercase tracking-[0.2em]">{{ category.total_courses }}</span>
              </button>
            </div>
          </section>

          <section class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-6 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Bộ lọc</p>
                <h3 class="mt-2 font-headline text-2xl font-bold text-on-surface">Tùy chọn hiển thị</h3>
              </div>
              <button type="button" class="text-sm font-semibold text-primary hover:opacity-80" @click="resetFilters">
                Xóa lọc
              </button>
            </div>

            <div class="mt-6 space-y-5">
              <label class="block space-y-2">
                <span class="text-sm font-semibold text-on-surface-variant">Chủ đề</span>
                <select v-model="filters.category" class="w-full rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3 text-sm text-on-surface outline-none transition-all focus:border-primary">
                  <option value="">Tất cả danh mục</option>
                  <template v-for="category in categories" :key="category.id">
                    <option :value="String(category.id)">{{ category.name }}</option>
                    <option v-for="child in category.children || []" :key="child.id" :value="String(child.id)">
                      - {{ child.name }}
                    </option>
                  </template>
                </select>
              </label>

              <label class="block space-y-2">
                <span class="text-sm font-semibold text-on-surface-variant">Loại học phí</span>
                <select v-model="filters.price" class="w-full rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3 text-sm text-on-surface outline-none transition-all focus:border-primary">
                  <option value="">Tất cả</option>
                  <option value="free">Miễn phí</option>
                  <option value="paid">Trả phí</option>
                </select>
              </label>

              <label class="block space-y-2">
                <span class="text-sm font-semibold text-on-surface-variant">Sắp xếp</span>
                <select v-model="filters.sort" class="w-full rounded-2xl border border-surface-dim/40 bg-surface px-4 py-3 text-sm text-on-surface outline-none transition-all focus:border-primary">
                  <option value="newest">Mới nhất</option>
                  <option value="popular">Nhiều học viên nhất</option>
                  <option value="price_asc">Giá thấp đến cao</option>
                  <option value="price_desc">Giá cao đến thấp</option>
                  <option value="rating">Đánh giá cao</option>
                </select>
              </label>
            </div>
          </section>
        </aside>

        <div class="space-y-8">
          <div class="rounded-[2.25rem] border border-surface-dim bg-surface-lowest p-6 shadow-sm lg:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
              <div class="max-w-3xl">
                <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-outline">Khám phá khóa học</p>
                <h1 class="mt-3 font-headline text-4xl font-bold tracking-[-0.04em] text-on-surface">
                  {{ heroTitle }}
                </h1>
                <p class="mt-3 text-sm leading-7 text-on-surface-variant">
                  Duyệt toàn bộ danh mục, tìm kỹ năng đang cần và chọn khóa học phù hợp với mục tiêu học tập hoặc chuyển đổi nghề nghiệp của bạn.
                </p>
              </div>

              <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[360px]">
                <div class="rounded-2xl bg-surface p-4">
                  <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline">Tổng khóa học</p>
                  <p class="mt-3 text-3xl font-headline font-bold text-on-surface">{{ filteredCourses.length }}</p>
                </div>
                <div class="rounded-2xl bg-surface p-4">
                  <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline">Danh mục</p>
                  <p class="mt-3 text-3xl font-headline font-bold text-on-surface">{{ categoryChips.length - 1 }}</p>
                </div>
                <div class="rounded-2xl bg-surface p-4">
                  <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-outline">Khóa miễn phí</p>
                  <p class="mt-3 text-3xl font-headline font-bold text-on-surface">{{ freeCoursesCount }}</p>
                </div>
              </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <div
                v-for="tag in activeFilterTags"
                :key="tag.label"
                class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-4 py-2 text-sm font-semibold text-primary"
              >
                <span>{{ tag.label }}</span>
                <button type="button" class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/10" @click="tag.clear()">
                  <span class="material-symbols-outlined text-[14px]">close</span>
                </button>
              </div>
            </div>
          </div>

          <div v-if="loading" class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="item in 9" :key="item" class="h-[25rem] rounded-[1.75rem] bg-surface-high animate-pulse" />
          </div>

          <UiEmptyState
            v-else-if="paginatedCourses.length === 0"
            title="Không tìm thấy khóa học phù hợp"
            description="Thử đổi từ khóa, xóa bộ lọc hoặc quay về tất cả danh mục để xem thêm khóa học."
          >
            <UiButton @click="resetFilters">Xem toàn bộ khóa học</UiButton>
          </UiEmptyState>

          <template v-else>
            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
              <CourseCard v-for="course in paginatedCourses" :key="course.id" :course="course" />
            </div>

            <div v-if="totalPages > 1" class="flex flex-wrap items-center justify-center gap-2 pt-2">
              <button
                type="button"
                class="flex h-11 min-w-11 items-center justify-center rounded-full px-4 text-sm font-semibold transition-all"
                :class="currentPage === 1 ? 'cursor-not-allowed bg-surface text-outline' : 'bg-surface-high text-on-surface hover:bg-surface-highest'"
                :disabled="currentPage === 1"
                @click="goToPage(currentPage - 1)"
              >
                Trước
              </button>
              <button
                v-for="page in paginationItems"
                :key="page"
                type="button"
                class="flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold transition-all"
                :class="currentPage === page ? 'cta-gradient text-white shadow-lg shadow-primary/20' : 'bg-surface-high text-on-surface-variant hover:bg-surface-highest hover:text-on-surface'"
                @click="goToPage(page)"
              >
                {{ page }}
              </button>
              <button
                type="button"
                class="flex h-11 min-w-11 items-center justify-center rounded-full px-4 text-sm font-semibold transition-all"
                :class="currentPage === totalPages ? 'cursor-not-allowed bg-surface text-outline' : 'bg-surface-high text-on-surface hover:bg-surface-highest'"
                :disabled="currentPage === totalPages"
                @click="goToPage(currentPage + 1)"
              >
                Sau
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CourseCard from '~/components/course/CourseCard.vue'
import UiButton from '~/components/ui/UiButton.vue'
import UiEmptyState from '~/components/ui/UiEmptyState.vue'
import { useApi } from '~/composables/useApi'

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

const audienceSegments = ['Dành cho Cá nhân', 'Dành cho Doanh nghiệp', 'Dành cho Trường đại học', 'Dành cho Chính phủ']

const route = useRoute()
const router = useRouter()
const allCourses = ref<any[]>([])
const categories = ref<CourseCategory[]>([])
const loading = ref(true)
const currentPage = ref(Number(route.query.page || 1))
const perPage = 9

const filters = reactive<FilterState>({
  search: (route.query.search as string) || '',
  category: (route.query.category as string) || '',
  sort: ((route.query.sort as FilterState['sort']) || 'newest'),
  price: ((route.query.price as FilterState['price']) || ''),
})

function normalizeCategoryName(course: any) {
  return course.category?.name || course.category || ''
}

function includesCategory(category: CourseCategory, targetId: number): boolean {
  if (category.id === targetId) return true
  return (category.children || []).some((child) => includesCategory(child, targetId))
}

function countAllCourses(category: CourseCategory): number {
  return Number(category.courses_count || 0) + (category.children || []).reduce((sum, child) => sum + countAllCourses(child), 0)
}

const categoriesWithCounts = computed(() =>
  categories.value.map((category) => ({
    ...category,
    total_courses: countAllCourses(category),
  })),
)

const categoryLookup = computed(() => {
  const map = new Map<string, string>()
  categories.value.forEach((category) => {
    map.set(String(category.id), category.name)
    ;(category.children || []).forEach((child) => map.set(String(child.id), `${category.name} / ${child.name}`))
  })
  return map
})

const categoryChips = computed(() => [
  { id: '', name: 'Tất cả', count: allCourses.value.length },
  ...categoriesWithCounts.value.map((category) => ({
    id: category.id,
    name: category.name,
    count: category.total_courses,
  })),
])

const filteredCourses = computed(() => {
  let items = [...allCourses.value]
  const keyword = filters.search.trim().toLowerCase()
  const categoryId = Number(filters.category)

  if (keyword) {
    items = items.filter((course) => {
      const haystack = [
        course.title,
        course.description,
        normalizeCategoryName(course),
        course.instructor?.name,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return haystack.includes(keyword)
    })
  }

  if (filters.category) {
    items = items.filter((course) => {
      const courseCategoryId = Number(course.category?.id || course.category_id || 0)
      if (!courseCategoryId) return false
      if (courseCategoryId === categoryId) return true
      return categories.value.some((category) => category.id === categoryId && includesCategory(category, courseCategoryId))
    })
  }

  if (filters.price === 'free') {
    items = items.filter((course) => Number(course.price || 0) === 0)
  }

  if (filters.price === 'paid') {
    items = items.filter((course) => Number(course.price || 0) > 0)
  }

  items.sort((a, b) => {
    if (filters.sort === 'price_asc') return Number(a.price || 0) - Number(b.price || 0)
    if (filters.sort === 'price_desc') return Number(b.price || 0) - Number(a.price || 0)
    if (filters.sort === 'popular') return Number(b.enrollments_count || 0) - Number(a.enrollments_count || 0)
    if (filters.sort === 'rating') return Number(b.reviews_avg_rating || b.avg_rating || 0) - Number(a.reviews_avg_rating || a.avg_rating || 0)
    return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime()
  })

  return items
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredCourses.value.length / perPage)))

const paginatedCourses = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredCourses.value.slice(start, start + perPage)
})

const freeCoursesCount = computed(() => allCourses.value.filter((course) => Number(course.price || 0) === 0).length)

const heroTitle = computed(() => {
  if (filters.search.trim()) return `Kết quả cho "${filters.search.trim()}"`
  if (filters.category) return `Khóa học trong ${categoryLookup.value.get(filters.category) || 'danh mục đã chọn'}`
  return 'Khám phá toàn bộ danh mục và khóa học'
})

const activeFilterTags = computed(() => {
  const tags: Array<{ label: string; clear: () => void }> = []

  if (filters.search) {
    tags.push({ label: `Từ khóa: ${filters.search}`, clear: () => { filters.search = '' } })
  }

  if (filters.category) {
    tags.push({
      label: `Danh mục: ${categoryLookup.value.get(filters.category) || filters.category}`,
      clear: () => { filters.category = '' },
    })
  }

  if (filters.price) {
    tags.push({
      label: filters.price === 'free' ? 'Miễn phí' : 'Trả phí',
      clear: () => { filters.price = '' },
    })
  }

  return tags
})

const paginationItems = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, start + 4)
  for (let page = start; page <= end; page += 1) pages.push(page)
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
  } finally {
    loading.value = false
  }
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

function submitSearch() {
  currentPage.value = 1
}

function selectCategory(categoryId: number | string) {
  filters.category = String(categoryId)
  currentPage.value = 1
}

function resetFilters() {
  filters.search = ''
  filters.category = ''
  filters.sort = 'newest'
  filters.price = ''
  currentPage.value = 1
}

function goToPage(page: number) {
  currentPage.value = page
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(
  () => ({ ...filters }),
  () => {
    currentPage.value = 1
    syncRoute()
  },
  { deep: true },
)

watch(currentPage, syncRoute)

watch(totalPages, (value) => {
  if (currentPage.value > value) currentPage.value = value
})

watch(
  () => route.query,
  (query) => {
    filters.search = (query.search as string) || ''
    filters.category = (query.category as string) || ''
    filters.sort = ((query.sort as FilterState['sort']) || 'newest')
    filters.price = ((query.price as FilterState['price']) || '')
    currentPage.value = Math.max(1, Number(query.page || 1))
  },
)

onMounted(fetchData)
</script>
