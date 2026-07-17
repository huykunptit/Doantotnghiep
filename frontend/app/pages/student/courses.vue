<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const enrollments = ref<any[]>([])
const search = ref('')
const filterStatus = ref<'all' | 'in_progress' | 'completed'>('all')

onMounted(async () => {
  try {
    const data = await useApi<any[]>('/user/enrollments', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    enrollments.value = data || []
  } finally {
    loading.value = false
  }
})

const filtered = computed(() => {
  let list = enrollments.value
  if (filterStatus.value === 'in_progress') list = list.filter(e => e.progress < 100)
  if (filterStatus.value === 'completed') list = list.filter(e => e.progress >= 100)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(e => e.course?.title?.toLowerCase().includes(q))
  }
  return list
})

const stats = computed(() => ({
  total: enrollments.value.length,
  inProgress: enrollments.value.filter(e => e.progress < 100).length,
  completed: enrollments.value.filter(e => e.progress >= 100).length,
}))
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học tập</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Khóa học của tôi</h1>
      </div>
      <NuxtLink to="/courses" class="inline-flex items-center gap-1 text-sm font-bold text-[#1d9e75] hover:text-[#17876a] transition-colors">
        Khám phá thêm →
      </NuxtLink>
    </div>

    <!-- Stats -->
    <div class="flex flex-wrap gap-3">
      <button 
        class="flex items-center gap-3 px-5 py-3 rounded-2xl border text-left transition-all"
        :class="filterStatus === 'all' ? 'bg-[rgba(29,158,117,0.08)] border-[rgba(29,158,117,0.2)] text-[#085041]' : 'bg-white border-[var(--line)] text-[var(--text)] hover:bg-[var(--surface)]'"
        @click="filterStatus = 'all'"
      >
        <span class="text-xl font-extrabold">{{ stats.total }}</span>
        <span class="text-xs font-bold text-[var(--muted)] uppercase tracking-wider">Tất cả</span>
      </button>
      <button 
        class="flex items-center gap-3 px-5 py-3 rounded-2xl border text-left transition-all"
        :class="filterStatus === 'in_progress' ? 'bg-[rgba(29,158,117,0.08)] border-[rgba(29,158,117,0.2)] text-[#085041]' : 'bg-white border-[var(--line)] text-[var(--text)] hover:bg-[var(--surface)]'"
        @click="filterStatus = 'in_progress'"
      >
        <span class="text-xl font-extrabold">{{ stats.inProgress }}</span>
        <span class="text-xs font-bold text-[var(--muted)] uppercase tracking-wider">Đang học</span>
      </button>
      <button 
        class="flex items-center gap-3 px-5 py-3 rounded-2xl border text-left transition-all"
        :class="filterStatus === 'completed' ? 'bg-[rgba(29,158,117,0.08)] border-[rgba(29,158,117,0.2)] text-[#085041]' : 'bg-white border-[var(--line)] text-[var(--text)] hover:bg-[var(--surface)]'"
        @click="filterStatus = 'completed'"
      >
        <span class="text-xl font-extrabold">{{ stats.completed }}</span>
        <span class="text-xs font-bold text-[var(--muted)] uppercase tracking-wider">Hoàn thành</span>
      </button>
    </div>

    <!-- Search -->
    <div class="relative w-full max-w-md">
      <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--muted)] text-lg">search</span>
      <input 
        v-model="search" 
        class="w-full h-10 pl-10 pr-4 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] transition-colors" 
        type="text" 
        placeholder="Tìm khóa học..."
      />
    </div>

    <!-- Course grid -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="i in 6" :key="i" class="h-80 rounded-2xl bg-[var(--surface-strong)] border border-[var(--line)] animate-pulse" />
    </div>
    <div v-else-if="filtered.length === 0" class="flex flex-col items-center gap-4 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">auto_stories</span>
      <p class="text-sm font-semibold text-[var(--muted)]">Không tìm thấy khóa học nào.</p>
      <NuxtLink to="/courses" class="text-xs font-bold text-[#1d9e75] hover:underline">Khám phá khóa học mới</NuxtLink>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <NuxtLink
        v-for="e in filtered"
        :key="e.id"
        :to="`/learn/${e.course?.id}`"
        class="flex flex-col bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm hover:-translate-y-1 hover:shadow-md hover:border-[rgba(29,158,117,0.3)] transition-all duration-300"
      >
        <div class="relative aspect-video overflow-hidden bg-[rgba(29,158,117,0.08)]">
          <img :src="e.course?.thumbnail || 'https://placehold.co/600x400/e1f5ee/085041?text=Course'" :alt="e.course?.title" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105" />
          <div class="absolute bottom-0 inset-x-0 p-3 bg-gradient-to-t from-black/60 to-transparent flex items-center gap-2">
            <div class="flex-1 h-1.5 bg-white/30 rounded-full overflow-hidden">
              <div class="h-full bg-[#1d9e75] rounded-full transition-all" :class="{ '!bg-emerald-400': e.progress >= 100 }" :style="{ width: `${e.progress}%` }" />
            </div>
            <span class="text-[10px] font-extrabold text-white">{{ Math.round(e.progress) }}%</span>
          </div>
        </div>
        <div class="p-4 flex flex-col flex-1">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[#1d9e75] mb-1.5">{{ e.course?.category?.name || 'Khóa học' }}</span>
          <h3 class="text-sm font-bold text-[var(--text)] line-clamp-2 leading-snug mb-1">{{ e.course?.title }}</h3>
          <p v-if="e.course?.instructor?.name" class="text-xs text-[var(--muted)] mb-4 font-semibold">{{ e.course.instructor.name }}</p>
          <div class="flex items-center justify-between mt-auto pt-3 border-t border-[var(--line)]">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="e.progress >= 100 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'">
              {{ e.progress >= 100 ? 'Hoàn thành' : 'Đang học' }}
            </span>
            <span class="text-xs font-bold text-[#1d9e75] inline-flex items-center gap-0.5">
              {{ e.progress > 0 ? (e.progress >= 100 ? 'Xem lại' : 'Tiếp tục') : 'Bắt đầu' }} &rarr;
            </span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
