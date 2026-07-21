<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const exams = ref<any[]>([])
const searchQuery = ref('')
const activeTab = ref<'all' | 'upcoming' | 'live' | 'done'>('all')

onMounted(async () => {
  try {
    const res = await useApi<{ exams: any[] }>('/me/exams', {
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    exams.value = res.exams || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

function formatDate(rawStr?: string) {
  if (!rawStr) return 'Tự do'
  return new Date(rawStr).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Client-side filtering
const filteredExams = computed(() => {
  let list = exams.value

  // Tab filter
  if (activeTab.value !== 'all') {
    list = list.filter(e => e.status === activeTab.value)
  }

  // Search query filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(e => 
      e.title.toLowerCase().includes(q) || 
      (e.description && e.description.toLowerCase().includes(q))
    )
  }

  return list
})

const tabCounts = computed(() => {
  return {
    all: exams.value.length,
    upcoming: exams.value.filter(e => e.status === 'upcoming').length,
    live: exams.value.filter(e => e.status === 'active').length,
    done: exams.value.filter(e => e.status === 'completed' || e.status === 'closed').length
  }
})
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khảo thí & Đánh giá</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Kỳ Thi Của Tôi</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Danh sách các đợt thi học kỳ, thi thử và kiểm tra chất lượng</p>
      </div>

      <!-- Search Box -->
      <div class="relative w-full md:w-80">
        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--muted)] text-lg">search</span>
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Tìm kiếm kỳ thi..." 
          class="w-full h-10 pl-10 pr-4 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] transition-colors" 
        />
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-[var(--line)] pb-px">
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'all' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'all'"
      >
        Tất cả
        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-bold border border-slate-200">{{ tabCounts.all }}</span>
      </button>
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'upcoming' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'upcoming'"
      >
        Sắp diễn ra
        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-bold border border-slate-200">{{ tabCounts.upcoming }}</span>
      </button>
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'live' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'live'"
      >
        Đang diễn ra
        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-red-500 text-white font-bold">{{ tabCounts.live }}</span>
      </button>
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'done' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'done'"
      >
        Đã hoàn thành / Kết thúc
        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-bold border border-slate-200">{{ tabCounts.done }}</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="h-64 rounded-2xl bg-[var(--surface-strong)] border border-[var(--line)] animate-pulse"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredExams.length === 0" class="flex flex-col items-center gap-4 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">list</span>
      <h3 class="text-base font-bold text-[var(--text)]">Không tìm thấy kỳ thi nào</h3>
      <p class="text-xs text-[var(--muted)]">Không có kỳ thi nào khớp với bộ lọc hiện tại của bạn.</p>
    </div>

    <!-- Exams Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div 
        v-for="exam in filteredExams" 
        :key="exam.id" 
        class="flex flex-col bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow relative"
      >
        <!-- Status indicator bar -->
        <div class="h-1 w-full" :class="exam.status === 'active' ? 'bg-red-500' : exam.status === 'upcoming' ? 'bg-sky-500' : 'bg-slate-400'"></div>

        <div class="p-5 flex flex-col flex-1 gap-4">
          <!-- Top row (Type & Proctoring) -->
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border border-slate-200 bg-slate-50 text-slate-600">
              {{ exam.type === 'course_final' ? 'Thi học phần' : 'Thi độc lập' }}
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold border border-red-100 bg-red-50 text-red-600" v-if="exam.proctoring_enabled">
              <span class="material-symbols-outlined text-xs">lock</span> Giám sát AI
            </span>
          </div>

          <!-- Title & Description -->
          <div>
            <h3 class="text-sm font-bold text-[var(--text)] line-clamp-1 leading-snug">{{ exam.title }}</h3>
            <p class="text-xs text-[var(--muted)] line-clamp-2 mt-1 leading-relaxed" v-if="exam.description">{{ exam.description }}</p>
          </div>

          <!-- Exam Details Grid -->
          <div class="flex flex-col gap-2.5 text-xs font-semibold text-[var(--muted)] border-t border-[var(--line)] pt-4">
            <div class="flex gap-2">
              <span class="material-symbols-outlined text-base">calendar_today</span>
              <div class="flex flex-col gap-0.5">
                <span class="text-[9px] uppercase tracking-wider">Thời gian thi:</span>
                <span class="text-[var(--text)]">{{ formatDate(exam.starts_at) }} &mdash; {{ formatDate(exam.ends_at) }}</span>
              </div>
            </div>
            
            <div class="flex gap-2">
              <span class="material-symbols-outlined text-base">schedule</span>
              <div class="flex flex-col gap-0.5">
                <span class="text-[9px] uppercase tracking-wider">Thời gian làm bài:</span>
                <span class="text-[var(--text)]">{{ exam.duration }} phút</span>
              </div>
            </div>

            <div class="flex gap-2">
              <span class="material-symbols-outlined text-base">verified</span>
              <div class="flex flex-col gap-0.5">
                <span class="text-[9px] uppercase tracking-wider">Điểm điều kiện đạt:</span>
                <span class="text-[var(--text)]">{{ exam.pass_score }}/10 điểm</span>
              </div>
            </div>
          </div>

          <!-- Bottom block: Attempt results or actions -->
          <div class="mt-auto border-t border-[var(--line)] pt-4">
            <!-- Completed state result -->
            <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 border border-[var(--line)]" v-if="exam.status === 'completed'">
              <div class="flex items-center gap-1.5 text-emerald-600 font-bold text-xs">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span>Đã nộp bài</span>
              </div>
              <div class="text-xs text-[var(--text)] font-bold">
                Điểm: <span class="text-emerald-600 font-extrabold text-sm">{{ exam.highest_score ?? '—' }}</span>/10
              </div>
            </div>

            <!-- Active / Take exam actions -->
            <div class="flex flex-col gap-2" v-else-if="exam.status === 'active' || exam.is_open">
              <div class="flex justify-between items-center text-xs font-bold text-[var(--muted)] mb-1">
                <span>Số lượt thi đã dùng:</span>
                <span class="text-[var(--text)]">{{ exam.attempts_count }} / {{ exam.max_attempts ?? 'Không giới hạn' }}</span>
              </div>
              <NuxtLink 
                :to="`/exam/${exam.id}`" 
                class="w-full h-9 rounded-xl font-semibold text-white bg-red-600 hover:bg-red-700 flex items-center justify-center text-xs gap-1.5 transition-colors"
              >
                <span class="material-symbols-outlined text-sm">play_circle</span>
                Vào thi ngay
              </NuxtLink>
            </div>

            <!-- Closed state -->
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--muted)] py-2" v-else-if="exam.status === 'closed'">
              <span>Trạng thái:</span>
              <span class="font-bold text-red-600">Đã đóng đợt thi</span>
            </div>

            <!-- Upcoming state -->
            <div class="flex items-center justify-between text-xs font-semibold text-[var(--muted)] py-2" v-else>
              <span>Thời gian mở thi:</span>
              <span class="font-bold text-sky-600">{{ exam.starts_at ? new Date(exam.starts_at).toLocaleDateString('vi-VN') : 'Tự do' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
