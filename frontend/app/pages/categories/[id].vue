<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useCourseStore } from '~/stores/course'

const route = useRoute()
const courseStore = useCourseStore()

const categoryId = route.params.id as string
const category = ref<any>(null)
const courses = ref<any[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [categoryData, data] = await Promise.all([
      useApi<any>(`/categories/${categoryId}`).catch(() => null),
      courseStore.fetchCourses({ category: categoryId, per_page: 20 })
    ])
    category.value = categoryData
    courses.value = data.data
  } finally {
    loading.value = false
  }
})

const breadcrumbs = computed(() => [
  { label: 'Tất cả chuyên mục', to: '/categories' },
  { label: category.value?.name || 'Khám phá', to: null }
])

const childCategories = computed(() => category.value?.children || [])
</script>

<template>
  <main class="min-h-screen bg-surface">
    <!-- Category Hero -->
    <section class="bg-surface-lowest pt-12 pb-24 px-4 md:px-8 relative overflow-hidden">
      <!-- Background pattern -->
      <div class="absolute inset-0 opacity-[0.03] pointer-events-none">
        <div class="grid grid-cols-6 gap-8 p-12 h-full w-full">
          <div v-for="i in 12" :key="i" class="aspect-square bg-primary rounded-full"></div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto relative z-10">
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-sm font-semibold text-outline-variant mb-8">
          <NuxtLink to="/categories" class="hover:text-primary transition-colors">Tất cả chuyên mục</NuxtLink>
          <span class="material-symbols-outlined text-sm">chevron_right</span>
          <span class="text-on-surface-variant">{{ category?.name || 'Đang tải...' }}</span>
        </nav>

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-12">
          <div class="max-w-3xl">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-headline font-bold text-on-surface tracking-tight">{{ category?.name || 'Khám phá Chuyên mục' }}</h1>
            <p class="mt-6 text-lg md:text-xl text-on-surface-variant leading-relaxed">
              {{ category?.description || 'Tìm hiểu những kiến thức chuyên sâu và thực tiễn để nâng cao kỹ năng của bạn trong lĩnh vực này.' }}
            </p>
          </div>
          
          <!-- Stats badge -->
          <div class="bg-primary/5 border border-primary/20 rounded-3xl p-6 lg:p-8 flex items-baseline gap-4 shadow-sm">
            <span class="text-5xl font-bold font-headline text-primary">{{ courses.length }}</span>
            <span class="text-sm font-bold text-primary-dark uppercase tracking-widest leading-none">Khóa học<br>Sẵn có</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Course List Section -->
    <section class="py-24 px-4 md:px-8 rounded-t-[3rem] -mt-12 relative z-20 bg-surface">
      <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12">
          <h2 class="text-2xl font-headline font-bold text-on-surface">Kết quả tìm kiếm</h2>
          <div class="h-1 flex-1 mx-8 bg-surface-dim/20 rounded-full"></div>
          <span class="text-sm font-bold text-outline uppercase tracking-widest">Sắp xếp: Mới nhất</span>
        </div>

        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
          <div v-for="i in 6" :key="i" class="h-96 bg-surface-lowest rounded-2xl animate-pulse"></div>
        </div>

        <div v-else-if="childCategories.length > 0" class="space-y-8">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <NuxtLink
              v-for="child in childCategories"
              :key="child.id"
              :to="`/categories/${child.id}`"
              class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-6 shadow-sm transition hover:-translate-y-1 hover:border-primary/30 hover:shadow-ambient"
            >
              <p class="text-lg font-bold text-on-surface">{{ child.name }}</p>
              <p class="mt-2 text-sm text-on-surface-variant">{{ child.courses_count || 0 }} khóa học</p>
            </NuxtLink>
          </div>
        </div>

        <div v-else-if="courses.length === 0" class="py-24 text-center bg-surface-lowest rounded-[3rem] border border-surface-dim">
          <UiEmptyState 
            title="Chưa tìm thấy khóa học" 
            description="Chuyên mục này hiện chưa có khóa học nào hoạt động. Bạn có thể quay lại sau hoặc khám phá các chuyên mục khác."
          />
          <NuxtLink to="/categories" class="mt-8 inline-block">
            <UiButton variant="ghost">Khám phá chuyên mục khác</UiButton>
          </NuxtLink>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
          <CourseCard 
            v-for="course in courses" 
            :key="course.id" 
            :course="course" 
          />
        </div>
      </div>
    </section>
  </main>
</template>
