<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const courses = ref<any[]>([])
const categoryFilter = ref('all')
const freeFilter = ref<'all' | 'free' | 'paid'>('all')
const search = ref('')

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  try {
    const res = await useApi<any>('/me/recommendations/extensions', { headers: h })
    courses.value = Array.isArray(res) ? res : (res?.data || res?.courses || [])
  } catch {
    // fallback
  } finally {
    loading.value = false
  }
})

const categories = computed(() => {
  const cats = new Set(courses.value.map(c => c.category?.name || c.category || '').filter(Boolean))
  return ['all', ...Array.from(cats)]
})

const filtered = computed(() => {
  let list = courses.value
  if (categoryFilter.value !== 'all') list = list.filter(c => (c.category?.name || c.category) === categoryFilter.value)
  if (freeFilter.value === 'free') list = list.filter(c => !c.price || c.price === 0)
  if (freeFilter.value === 'paid') list = list.filter(c => c.price && c.price > 0)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(c => (c.title || '').toLowerCase().includes(q) || (c.instructor?.name || '').toLowerCase().includes(q))
  }
  return list
})

function formatPrice(p?: number) {
  if (!p || p === 0) return 'Miễn phí'
  return p.toLocaleString('vi-VN') + '₫'
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khám phá</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Gợi ý dành cho bạn</h1>
        <p class="text-xs text-[var(--muted)] mt-1">Các khóa học được AI chọn lọc dựa trên lộ trình học và kỹ năng của bạn.</p>
      </div>
      <div class="relative w-full md:w-80">
        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg leading-none">search</span>
        <input v-model="search" type="search" placeholder="Tìm khóa học..." class="w-full h-10 pl-10 pr-4 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-4 items-center">
      <div class="flex items-center gap-2">
        <span class="text-xs font-bold text-[var(--muted)]">Danh mục:</span>
        <div class="flex gap-1.5 border border-[var(--line)] bg-[var(--surface)] p-1 rounded-xl">
          <button 
            v-for="cat in categories" 
            :key="cat" 
            class="h-7 px-3 rounded-lg text-xs font-bold transition-all"
            :class="categoryFilter === cat ? 'bg-white text-[var(--text)] shadow-sm' : 'text-[var(--muted)] hover:text-[var(--text)]'"
            @click="categoryFilter = cat"
          >
            {{ cat === 'all' ? 'Tất cả' : cat }}
          </button>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs font-bold text-[var(--muted)]">Giá:</span>
        <div class="flex gap-1.5 border border-[var(--line)] bg-[var(--surface)] p-1 rounded-xl">
          <button 
            v-for="t in [{k:'all',l:'Tất cả'},{k:'free',l:'Miễn phí'},{k:'paid',l:'Có phí'}]"
            :key="t.k" 
            class="h-7 px-3 rounded-lg text-xs font-bold transition-all"
            :class="freeFilter === t.k ? 'bg-white text-[var(--text)] shadow-sm' : 'text-[var(--muted)] hover:text-[var(--text)]'"
            @click="freeFilter = t.k as any"
          >
            {{ t.l }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 animate-pulse">
      <div v-for="i in 4" :key="i" class="h-64 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl" />
    </div>

    <!-- Grid -->
    <div v-else-if="filtered.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div v-for="course in filtered" :key="course.id" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col">
        <div class="relative aspect-video bg-slate-50 flex items-center justify-center overflow-hidden flex-shrink-0">
          <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" loading="lazy" class="w-full h-full object-cover">
          <span v-else class="material-symbols-outlined text-3xl text-slate-300">book</span>
          
          <div v-if="course.match_score" class="absolute top-3 right-3 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-slate-900/80 text-amber-300 border border-slate-700 backdrop-blur-sm">
            <span class="material-symbols-outlined text-[10px] leading-none">auto_awesome</span> {{ Math.round(course.match_score * 100) }}% phù hợp
          </div>
        </div>
        
        <div class="p-4 flex-1 flex flex-col gap-2">
          <p v-if="course.category?.name || course.category" class="text-[9px] font-extrabold text-[var(--muted)] uppercase tracking-widest">{{ course.category?.name || course.category }}</p>
          <h3 class="text-xs font-bold text-[var(--text)] line-clamp-2 leading-relaxed">{{ course.title }}</h3>
          <p class="text-[10px] text-[var(--muted)] font-semibold">{{ course.instructor?.name || course.teacher_name || '' }}</p>

          <!-- Skill chips -->
          <div v-if="course.matched_skills?.length" class="flex flex-wrap gap-1 mt-1">
            <span v-for="s in course.matched_skills.slice(0, 3)" :key="s" class="px-1.5 py-0.5 rounded bg-sky-50 text-[9px] font-bold text-sky-700 border border-sky-100">{{ s }}</span>
          </div>

          <div class="flex items-center justify-between gap-3 pt-3 border-t border-[var(--line)] mt-auto">
            <span class="text-xs font-bold" :class="!course.price || course.price === 0 ? 'text-emerald-600' : 'text-[var(--text)]'">{{ formatPrice(course.price) }}</span>
            <NuxtLink :to="`/courses/${course.id}`" class="h-8 px-4 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-[#1d9e75] hover:text-white font-bold text-xs flex items-center justify-center transition-colors">Xem khóa học</NuxtLink>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="flex flex-col items-center gap-3 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">auto_awesome</span>
      <p class="text-xs font-semibold text-[var(--muted)]">Không tìm thấy gợi ý nào.</p>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
