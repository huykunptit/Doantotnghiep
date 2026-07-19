<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khóa học &bull; Học viên</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Học viên khóa học</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Theo dõi danh sách học viên, tiến độ hoàn thành và thời điểm đăng ký.</p>
      </div>
      <div class="flex items-center gap-2">
        <NuxtLink to="/instructor/courses" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <i class="pi pi-arrow-left text-xs" />
          <span>Quay lại</span>
        </NuxtLink>
      </div>
    </div>

    <!-- Summary cards (shown after load) -->
    <div v-if="!loading && students.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
          <span class="material-symbols-outlined text-xl">people</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng học viên</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ total }}</strong>
          <span class="text-[10px] text-[var(--muted)]">ghi danh</span>
        </div>
      </div>
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
          <span class="material-symbols-outlined text-xl">check_circle</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Đã hoàn thành</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ completedCount }}</strong>
          <span class="text-[10px] text-[var(--muted)]">học viên</span>
        </div>
      </div>
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600">
          <span class="material-symbols-outlined text-xl">trending_up</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tiến độ trung bình</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ avgProgress }}%</strong>
          <span class="text-[10px] text-[var(--muted)]">hoàn thành</span>
        </div>
      </div>
    </div>

    <!-- Content -->
    <section class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <form class="flex flex-1 items-center gap-3 w-full sm:max-w-md" @submit.prevent="doSearch">
          <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[var(--muted)]">search</span>
            <input
              v-model="search"
              type="text"
              placeholder="Tìm theo tên hoặc email..."
              class="w-full h-9 pl-9 pr-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75]"
              @keyup.enter="doSearch"
            />
          </div>
          <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors">
            Tìm kiếm
          </button>
        </form>
      </div>

      <!-- Skeleton / Loading -->
      <div v-if="loading" class="text-center py-12 text-xs text-[var(--muted)]">Đang tải...</div>

      <!-- Empty -->
      <div v-else-if="students.length === 0" class="text-center py-12 flex flex-col items-center gap-2 text-xs text-[var(--muted)]">
        <span class="material-symbols-outlined text-4xl opacity-20">person_off</span>
        <div>
          <strong class="text-xs font-bold text-[var(--text)]">Chưa có học viên</strong>
          <p class="text-[10px] text-[var(--muted)] mt-1">Danh sách học viên sẽ xuất hiện khi có người đăng ký khóa học.</p>
        </div>
      </div>

      <!-- List -->
      <div v-else class="flex flex-col gap-3">
        <div v-for="item in students" :key="item.id" class="border border-[var(--line)] bg-[var(--surface-strong)] rounded-2xl p-4 flex flex-col lg:flex-row justify-between lg:items-center gap-4 hover:shadow-md transition-shadow">
          <!-- Avatar + name -->
          <div class="flex items-center gap-3 min-w-[200px]">
            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-emerald-50 text-[#1d9e75] font-extrabold text-xs">
              {{ item.user?.name?.slice(0, 2).toUpperCase() || 'HV' }}
            </div>
            <div class="flex flex-col">
              <strong class="text-xs font-bold text-[var(--text)]">{{ item.user?.name || '—' }}</strong>
              <span class="text-[10px] text-[var(--muted)] mt-0.5">{{ item.user?.email || '—' }}</span>
            </div>
          </div>

          <!-- Progress bar -->
          <div class="flex-1 flex flex-col gap-1.5 min-w-[150px]">
            <div class="flex justify-between text-[10px] font-bold">
              <span class="text-[var(--muted)]">Tiến độ</span>
              <span :class="item.progress_percent >= 100 ? 'text-emerald-600' : 'text-sky-600'">
                {{ item.progress_percent || 0 }}%
              </span>
            </div>
            <div class="h-1.5 rounded-full bg-[var(--surface)] border border-[var(--line)] overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="item.progress_percent >= 100 ? 'bg-emerald-500' : 'bg-sky-500'"
                :style="{ width: `${item.progress_percent || 0}%` }"
              />
            </div>
          </div>

          <!-- Stats & Date -->
          <div class="grid grid-cols-3 gap-4 lg:w-[280px] text-center">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-[var(--muted)]">Bài học</p>
              <strong class="text-xs font-bold text-[var(--text)] mt-1 block">{{ item.completed_lessons }}/{{ item.total_lessons }}</strong>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-[var(--muted)]">Trạng thái</p>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold border mt-1" :class="item.progress_percent >= 100 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-sky-50 text-sky-600 border-sky-100'">
                {{ item.progress_percent >= 100 ? 'Hoàn thành' : 'Đang học' }}
              </span>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-[var(--muted)]">Đăng ký ngày</p>
              <span class="text-[10px] text-[var(--muted)] mt-1 block font-semibold">{{ item.enrolled_at ? formatDate(item.enrolled_at) : '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="px-5 py-4 border-t border-[var(--line)] flex justify-between items-center text-xs text-[var(--muted)]">
        <p>Trang <strong>{{ currentPage }}</strong> / {{ lastPage }} · {{ total }} học viên</p>
        <div class="flex gap-2">
          <button type="button" class="h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-semibold text-[var(--text)] transition-colors disabled:opacity-40" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">
            ← Trước
          </button>
          <button type="button" class="h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-[10px] font-semibold text-[var(--text)] transition-colors disabled:opacity-40" :disabled="currentPage >= lastPage" @click="changePage(currentPage + 1)">
            Sau →
          </button>
        </div>
      </div>
    </section>
  </div>
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

<style scoped>
/* Scoped styles kept minimal */
</style>
