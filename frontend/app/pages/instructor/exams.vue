<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Giảng viên &bull; Đánh giá</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Đợt thi & đánh giá</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý ngân hàng câu hỏi, đề kiểm tra và kỳ thi độc lập theo từng khóa học.</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink to="/instructor/courses" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <span>Xem khóa học</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Content Card -->
    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
      <!-- Toolbar -->
      <div class="flex flex-wrap gap-3 items-center px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)]">
        <form class="flex items-center gap-3 w-full sm:max-w-xs" @submit.prevent>
          <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[var(--muted)]">search</span>
            <input
              v-model="search"
              type="text"
              placeholder="Tìm khóa học..."
              class="w-full h-9 pl-9 pr-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75]"
            />
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
              <th class="px-5 py-3 w-12">#</th>
              <th class="px-5 py-3">Khóa học</th>
              <th class="px-5 py-3">Bài học</th>
              <th class="px-5 py-3">Học viên</th>
              <th class="px-5 py-3">Trạng thái</th>
              <th class="px-5 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="px-5 py-8 text-center text-xs text-[var(--muted)]">Đang tải...</td>
            </tr>
            <tr v-else-if="filteredCourses.length === 0">
              <td colspan="6" class="px-5 py-8 text-center text-xs text-[var(--muted)]">Chưa có khóa học.</td>
            </tr>
            <tr v-for="(course, idx) in filteredCourses" :key="course.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-5 py-4 text-xs font-semibold text-[var(--muted)]">{{ idx + 1 }}</td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl overflow-hidden bg-[var(--surface)] border border-[var(--line)] flex items-center justify-center flex-shrink-0">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="w-full h-full object-cover">
                    <span v-else class="text-sm">📘</span>
                  </div>
                  <strong class="text-xs font-bold text-[var(--text)]">{{ course.title }}</strong>
                </div>
              </td>
              <td class="px-5 py-4 text-xs text-[var(--text)] font-semibold">{{ course.lessons_count || 0 }}</td>
              <td class="px-5 py-4 text-xs text-[var(--text)] font-semibold">{{ course.enrollments_count || 0 }}</td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="{
                  'bg-emerald-50 text-emerald-600 border-emerald-100': course.status === 'published',
                  'bg-sky-50 text-sky-600 border-sky-100': course.status === 'pending_review',
                  'bg-[var(--surface)] text-[var(--muted)] border-[var(--line)]': course.status === 'draft',
                  'bg-red-50 text-red-500 border-red-100': course.status === 'rejected'
                }">
                  {{ statusLabel(course.status) }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <NuxtLink :to="`/instructor/courses/${course.id}/question-bank`" class="h-7 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] flex items-center justify-center transition-colors">
                    Câu hỏi
                  </NuxtLink>
                  <NuxtLink :to="`/instructor/courses/${course.id}/exams`" class="h-7 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-bold text-[var(--text)] flex items-center justify-center transition-colors">
                    Kỳ thi
                  </NuxtLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'instructor', middleware: 'instructor' })

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

