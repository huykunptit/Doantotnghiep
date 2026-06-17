<template>
  <section class="space-y-8">
    <!-- Header -->
    <AppPageHeader eyebrow="Instructor" title="Học viên khóa học" description="Theo dõi danh sách học viên, tiến độ hoàn thành và thời điểm đăng ký.">
      <template #actions>
        <UiButton to="/instructor/courses" variant="secondary">Quay lại</UiButton>
      </template>
    </AppPageHeader>

    <!-- Summary cards (shown after load) -->
    <div v-if="!loading && students.length > 0" class="grid gap-4 sm:grid-cols-3">
      <UiCard>
        <p class="text-xs font-semibold uppercase tracking-wide text-outline">Tổng học viên</p>
        <p class="mt-2 text-2xl font-bold text-on-surface">{{ total }}</p>
      </UiCard>
      <UiCard>
        <p class="text-xs font-semibold uppercase tracking-wide text-outline">Đã hoàn thành</p>
        <p class="mt-2 text-2xl font-bold text-green-600">{{ completedCount }}</p>
      </UiCard>
      <UiCard>
        <p class="text-xs font-semibold uppercase tracking-wide text-outline">Tiến độ TB</p>
        <p class="mt-2 text-2xl font-bold text-primary">{{ avgProgress }}%</p>
      </UiCard>
    </div>

    <!-- Search -->
    <div class="flex gap-3">
      <input
        v-model="search"
        type="text"
        placeholder="Tìm theo tên hoặc email..."
        class="h-11 flex-1 rounded-xl border border-surface-dim/60 bg-surface-lowest px-4 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
        @keyup.enter="doSearch"
      >
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark transition-all"
        @click="doSearch"
      >
        Tìm kiếm
      </button>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-24 rounded-3xl border border-surface-dim bg-surface-lowest animate-pulse" />
    </div>

    <!-- Empty -->
    <UiEmptyState
      v-else-if="students.length === 0"
      title="Chưa có học viên"
      description="Danh sách học viên sẽ xuất hiện khi có người đăng ký khóa học."
    />

    <!-- List -->
    <div v-else class="space-y-3">
      <UiCard v-for="item in students" :key="item.id" class="hover:shadow-md transition-shadow">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-6">
          <!-- Avatar + name -->
          <div class="flex items-center gap-3 lg:w-56 lg:shrink-0">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
              {{ item.user?.name?.slice(0, 2).toUpperCase() || 'HV' }}
            </div>
            <div class="min-w-0">
              <p class="truncate font-semibold text-on-surface">{{ item.user?.name || '—' }}</p>
              <p class="truncate text-xs text-on-surface-variant">{{ item.user?.email || '—' }}</p>
            </div>
          </div>

          <!-- Progress bar -->
          <div class="flex-1">
            <div class="mb-1 flex items-center justify-between text-xs">
              <span class="font-semibold text-on-surface-variant">Tiến độ</span>
              <span class="font-bold" :class="item.progress_percent >= 100 ? 'text-green-600' : 'text-primary'">
                {{ item.progress_percent || 0 }}%
              </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-surface-dim">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="item.progress_percent >= 100 ? 'bg-green-500' : 'bg-primary'"
                :style="{ width: `${item.progress_percent || 0}%` }"
              />
            </div>
          </div>

          <!-- Stats -->
          <div class="grid grid-cols-3 gap-3 lg:w-64 lg:shrink-0">
            <div class="text-center">
              <p class="text-xs font-semibold uppercase tracking-wide text-outline">Bài học</p>
              <p class="mt-1 font-bold text-on-surface">{{ item.completed_lessons }}/{{ item.total_lessons }}</p>
            </div>
            <div class="text-center">
              <p class="text-xs font-semibold uppercase tracking-wide text-outline">Đăng ký</p>
              <p class="mt-1 text-sm text-on-surface-variant">{{ formatDate(item.enrolled_at) }}</p>
            </div>
            <div class="text-center">
              <p class="text-xs font-semibold uppercase tracking-wide text-outline">Trạng thái</p>
              <span
                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-bold"
                :class="item.progress_percent >= 100 ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
              >
                {{ item.progress_percent >= 100 ? 'Hoàn thành' : 'Đang học' }}
              </span>
            </div>
          </div>
        </div>
      </UiCard>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-between rounded-2xl border border-surface-dim/60 bg-surface-lowest px-5 py-3">
      <p class="text-sm text-on-surface-variant">
        Trang <strong>{{ currentPage }}</strong> / {{ lastPage }} &nbsp;·&nbsp; {{ total }} học viên
      </p>
      <div class="flex gap-2">
        <button
          type="button"
          class="rounded-lg border border-surface-dim/60 bg-surface-lowest px-4 py-1.5 text-sm font-semibold disabled:opacity-40 hover:bg-surface-low transition-all"
          :disabled="currentPage <= 1"
          @click="changePage(currentPage - 1)"
        >
          ← Trước
        </button>
        <button
          type="button"
          class="rounded-lg border border-surface-dim/60 bg-surface-lowest px-4 py-1.5 text-sm font-semibold disabled:opacity-40 hover:bg-surface-low transition-all"
          :disabled="currentPage >= lastPage"
          @click="changePage(currentPage + 1)"
        >
          Sau →
        </button>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
// @ts-nocheck
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const loading = ref(true)
const search = ref('')
const students = ref<any[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 10

const formatDate = (v: string) => new Date(v).toLocaleDateString('vi-VN')

const completedCount = computed(() =>
  students.value.filter(s => (s.progress_percent || 0) >= 100).length
)

const avgProgress = computed(() => {
  if (!students.value.length) return 0
  const sum = students.value.reduce((acc, s) => acc + (s.progress_percent || 0), 0)
  return Math.round(sum / students.value.length)
})

async function loadData(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage) })
    if (search.value.trim()) query.set('search', search.value.trim())
    const res = await useApi<any>(`/instructor/courses/${courseId}/students?${query}`, { token: auth.token })
    students.value = res.data || []
    currentPage.value = res.current_page || page
    lastPage.value = res.last_page || 1
    total.value = res.total || students.value.length
  }
  finally {
    loading.value = false
  }
}

function doSearch() {
  currentPage.value = 1
  loadData(1)
}

function changePage(page: number) {
  loadData(page)
}

onMounted(() => loadData())
</script>
