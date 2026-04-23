<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'

const courseStore = useCourseStore()
const categories = ref<any[]>([])
const loading = ref(true)

const categoryIcons: Record<string, string> = { 
  code: 'terminal', 
  paintbrush: 'architecture', 
  briefcase: 'monitoring', 
  globe: 'public', 
  camera: 'photo_camera', 
  music: 'music_note',
  default: 'auto_graph'
}

onMounted(async () => {
  try {
    categories.value = await courseStore.fetchCategories()
  } finally {
    loading.value = false
  }
})

const countAllCourses = (category: any): number => {
  return Number(category?.courses_count || 0) + (category?.children || []).reduce((sum: number, child: any) => sum + countAllCourses(child), 0)
}

const categoriesWithCounts = computed(() =>
  categories.value.map((category) => ({
    ...category,
    total_courses: countAllCourses(category),
  })),
)
</script>

<template>
  <main class="min-h-screen bg-surface py-12 px-4 md:px-8">
    <div class="max-w-7xl mx-auto">
      <AppPageHeader 
        eyebrow="Tất cả Chuyên mục" 
        title="Lựa chọn Lộ trình của Bạn" 
        description="Khám phá kho tàng tri thức được phân loại khoa học để giúp bạn dễ dàng tìm thấy điểm xuất phát cho hành trình thăng tiến nghề nghiệp."
      />

      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-16">
        <div v-for="i in 8" :key="i" class="h-48 bg-surface-high animate-pulse rounded-2xl"></div>
      </div>
      
      <div v-else-if="categories.length === 0" class="mt-16 text-center py-24 bg-surface-lowest rounded-[2.5rem] border border-surface-dim shadow-sm">
        <span class="material-symbols-outlined text-6xl text-outline mb-4">folder_off</span>
        <p class="text-xl font-headline font-bold text-on-surface">Chưa có danh mục nào</p>
        <p class="text-on-surface-variant mt-2">Dữ liệu đang được đội ngũ EduPress cập nhật.</p>
      </div>

      <div v-else class="space-y-8 mt-16">
        <section
          v-for="(cat, index) in categoriesWithCounts"
          :key="cat.id"
          class="rounded-[2rem] border border-surface-dim bg-surface-lowest p-8 shadow-sm"
          :style="`animation-delay: ${index * 0.05}s`"
        >
          <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <NuxtLink :to="`/categories/${cat.id}`" class="group block max-w-2xl">
              <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mb-6 text-primary group-hover:bg-primary group-hover:text-white transition-colors duration-500 shadow-sm">
                <span class="material-symbols-outlined text-3xl">{{ categoryIcons[cat.icon] || categoryIcons.default }}</span>
              </div>
              <h3 class="text-2xl font-headline font-bold mb-3 text-on-surface group-hover:text-primary transition-colors">{{ cat.name }}</h3>
              <p class="text-on-surface-variant text-sm font-medium flex items-center gap-2">
                {{ cat.total_courses || 0 }} khóa học trong toàn nhánh
                <span class="material-symbols-outlined text-xs group-hover:translate-x-1 transition-transform">arrow_forward</span>
              </p>
            </NuxtLink>

            <div v-if="cat.children?.length" class="grid gap-3 sm:grid-cols-2 lg:min-w-[28rem]">
              <NuxtLink
                v-for="child in cat.children"
                :key="child.id"
                :to="`/categories/${child.id}`"
                class="rounded-2xl border border-surface-dim bg-surface-low px-5 py-4 text-sm transition hover:border-primary/30 hover:bg-white"
              >
                <p class="font-semibold text-on-surface">{{ child.name }}</p>
                <p class="mt-1 text-xs text-on-surface-variant">{{ child.courses_count || 0 }} khóa học</p>
              </NuxtLink>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>
