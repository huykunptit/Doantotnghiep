<template>
  <NuxtLayout name="instructor">
    <section class="space-y-8">
      <AppPageHeader eyebrow="Instructor" title="Quản lý học viên" description="Xem danh sách học viên, tiến độ và mức độ hoàn thành theo từng khóa học." />

      <UiCard>
        <!-- Search -->
        <div class="mb-6">
          <div class="relative max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
            <input v-model="search" type="text" placeholder="Tìm khóa học..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-dim bg-surface-lowest text-sm placeholder-outline focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
          <div v-for="i in 5" :key="i" class="h-14 rounded-xl bg-surface-high animate-pulse" />
        </div>

        <UiEmptyState v-else-if="filteredCourses.length === 0" title="Chưa có khóa học" description="Bạn cần có khóa học trước khi theo dõi học viên." />

        <!-- Table -->
        <div v-else class="overflow-x-auto rounded-xl border border-surface-dim">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-surface-low text-left text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                <th class="px-5 py-3.5">#</th>
                <th class="px-5 py-3.5">Khóa học</th>
                <th class="px-5 py-3.5 text-center">Số học viên</th>
                <th class="px-5 py-3.5 text-center">Bài học</th>
                <th class="px-5 py-3.5 text-center">Trạng thái</th>
                <th class="px-5 py-3.5 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-dim/50">
              <tr v-for="(course, idx) in filteredCourses" :key="course.id" class="hover:bg-surface-low/50 transition-colors">
                <td class="px-5 py-4 text-on-surface-variant font-medium">{{ idx + 1 }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="h-10 w-14 shrink-0 rounded-lg overflow-hidden bg-surface-high">
                      <img v-if="course.thumbnail" :src="course.thumbnail" class="h-full w-full object-cover">
                      <div v-else class="flex h-full items-center justify-center text-sm">📘</div>
                    </div>
                    <span class="font-semibold text-on-surface line-clamp-1">{{ course.title }}</span>
                  </div>
                </td>
                <td class="px-5 py-4 text-center">
                  <span class="inline-flex items-center gap-1 font-semibold text-on-surface">
                    <span class="material-symbols-outlined text-[16px] text-primary">group</span>
                    {{ course.enrollments_count || 0 }}
                  </span>
                </td>
                <td class="px-5 py-4 text-center text-on-surface-variant">{{ course.lessons_count || 0 }}</td>
                <td class="px-5 py-4 text-center"><StatusBadge :value="statusLabel(course.status)" /></td>
                <td class="px-5 py-4 text-right">
                  <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">visibility</span> Chi tiết
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </UiCard>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const courseStore = useCourseStore()
const loading = ref(true)
const courses = ref<any[]>([])
const search = ref('')

const statusLabel = (status: string) => {
  const map: Record<string, string> = { published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }
  return map[status] || status
}

const filteredCourses = computed(() => {
  if (!search.value.trim()) return courses.value
  const q = search.value.toLowerCase()
  return courses.value.filter(c => c.title?.toLowerCase().includes(q))
})

onMounted(async () => {
  try {
    courses.value = await courseStore.fetchMyCourses()
  } finally {
    loading.value = false
  }
})
</script>
