<template>
  <div class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Tất cả khóa học</h1>
        <div class="flex flex-col sm:flex-row gap-3">
          <!-- Search -->
          <div class="relative flex-1 max-w-md">
            <input
              v-model="filters.search"
              type="text"
              placeholder="Tìm kiếm khóa học..."
              class="input pl-9"
              @keyup.enter="applyFilters"
            />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <!-- Category filter -->
          <select v-model="filters.category" class="input w-auto min-w-[180px]" @change="applyFilters">
            <option value="">Tất cả danh mục</option>
            <template v-for="cat in categories" :key="cat.id">
              <option :value="cat.id">{{ cat.name }}</option>
              <option v-for="child in cat.children" :key="child.id" :value="child.id">&nbsp;&nbsp;{{ child.name }}</option>
            </template>
          </select>
          <!-- Sort -->
          <select v-model="filters.sort" class="input w-auto min-w-[160px]" @change="applyFilters">
            <option value="newest">Mới nhất</option>
            <option value="price_asc">Giá tăng dần</option>
            <option value="price_desc">Giá giảm dần</option>
            <option value="popular">Nhiều học viên</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Course Grid -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div v-if="loading" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div v-for="i in 8" :key="i" class="card animate-pulse">
          <div class="h-44 bg-gray-200 rounded-t-xl"></div>
          <div class="p-4 space-y-3">
            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
            <div class="h-4 bg-gray-200 rounded w-1/3"></div>
          </div>
        </div>
      </div>

      <div v-else-if="courses.length === 0" class="text-center py-20">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 20a8 8 0 100-16 8 8 0 000 16z" /></svg>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Không tìm thấy khóa học</h3>
        <p class="text-gray-500 text-sm">Thử thay đổi bộ lọc hoặc tìm kiếm từ khóa khác.</p>
      </div>

      <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <NuxtLink
          v-for="course in courses"
          :key="course.id"
          :to="`/courses/${course.id}`"
          class="card overflow-hidden group"
        >
          <div class="relative h-44 bg-gray-100 overflow-hidden">
            <img
              v-if="course.thumbnail"
              :src="course.thumbnail"
              :alt="course.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </div>
            <div v-if="course.price === 0" class="absolute top-3 left-3 badge-success">Miễn phí</div>
          </div>
          <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mb-2 group-hover:text-primary transition-colors">{{ course.title }}</h3>
            <p class="text-xs text-gray-500 mb-3">{{ course.instructor?.name }}</p>
            <div class="flex items-center justify-between">
              <span class="text-sm font-bold" :class="course.price > 0 ? 'text-primary' : 'text-primary'">
                {{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}
              </span>
              <div class="flex items-center gap-2 text-xs text-gray-400">
                <span>{{ course.lessons_count || 0 }} bài</span>
                <span>{{ course.enrollments_count || 0 }} học viên</span>
              </div>
            </div>
          </div>
        </NuxtLink>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center gap-2 mt-10">
        <button
          v-for="page in totalPages"
          :key="page"
          @click="goToPage(page)"
          class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
          :class="currentPage === page ? 'bg-primary text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'"
        >
          {{ page }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const router = useRouter()

const courses = ref<any[]>([])
const categories = ref<any[]>([])
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)

const filters = reactive({
  search: (route.query.search as string) || '',
  category: (route.query.category as string) || '',
  sort: 'newest',
})

function formatPrice(price: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

async function fetchCourses(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams()
    if (filters.search) query.set('search', filters.search)
    if (filters.category) query.set('category', filters.category)
    query.set('page', String(page))
    query.set('per_page', '12')

    const data = await useApi<any>(`/courses?${query}`)
    courses.value = data.data || []
    currentPage.value = data.current_page || 1
    totalPages.value = data.last_page || 1
  } catch {
    courses.value = []
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  const query: Record<string, string> = {}
  if (filters.search) query.search = filters.search
  if (filters.category) query.category = filters.category
  router.push({ query })
  fetchCourses(1)
}

function goToPage(page: number) {
  currentPage.value = page
  fetchCourses(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(async () => {
  const [catData] = await Promise.all([
    useApi<any[]>('/categories').catch(() => []),
    fetchCourses(),
  ])
  categories.value = catData
})

watch(() => route.query, () => {
  filters.search = (route.query.search as string) || ''
  filters.category = (route.query.category as string) || ''
  fetchCourses(1)
})
</script>
