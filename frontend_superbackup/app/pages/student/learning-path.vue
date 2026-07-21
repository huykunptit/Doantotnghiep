<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const learningPathData = ref<any>(null)
const expandedTerms = ref<number[]>([1, 2]) // Default expand Term 1 & 2

onMounted(async () => {
  try {
    const res = await useApi<any>('/me/learning-path', {
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    learningPathData.value = res
    
    // Automatically expand the terms that contain active courses
    if (res.has_curriculum && res.terms) {
      const activeTerms: number[] = []
      res.terms.forEach((t: any) => {
        const hasActive = t.courses.some((c: any) => c.status === 'learning')
        if (hasActive) {
          activeTerms.push(t.term_number)
        }
      })
      if (activeTerms.length > 0) {
        expandedTerms.value = activeTerms
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const overallCreditsProgress = computed(() => {
  if (!learningPathData.value || !learningPathData.value.total_credits_required) return 0
  const req = learningPathData.value.total_credits_required
  const earned = learningPathData.value.total_credits_earned
  return Math.round((earned / req) * 100)
})

function toggleTerm(termNum: number) {
  const index = expandedTerms.value.indexOf(termNum)
  if (index > -1) {
    expandedTerms.value.splice(index, 1)
  } else {
    expandedTerms.value.push(termNum)
  }
}

function getTermCompletedCount(courses: any[]) {
  return courses.filter(c => c.status === 'completed').length
}

function getStatusLabel(status: string) {
  if (status === 'completed') return 'Đã hoàn thành'
  if (status === 'learning') return 'Đang học'
  return 'Chưa đăng ký'
}

function getStatusClass(status: string) {
  if (status === 'completed') return 'border-emerald-200 bg-emerald-50/20'
  if (status === 'learning') return 'border-sky-200 bg-sky-50/20'
  return 'border-[var(--line)] bg-white'
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Đào tạo chính quy</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Chương Trình Đào Tạo</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5" v-if="learningPathData?.has_curriculum">
          Lộ trình học tập chi tiết của bạn thuộc lớp hành chính khoa đào tạo
        </p>
        <p class="text-sm text-[var(--muted)] mt-0.5" v-else>
          Theo dõi tiến độ học tập và tích lũy tín chỉ trong lộ trình
        </p>
      </div>
      <NuxtLink to="/student/recommendations" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-all">
        <span class="material-symbols-outlined text-sm">sparkles</span> Gợi ý học phần
      </NuxtLink>
    </div>

    <!-- Loading Shimmer -->
    <div v-if="loading" class="flex flex-col gap-4 animate-pulse">
      <div class="h-32 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl"></div>
      <div v-for="i in 3" :key="i" class="h-16 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl"></div>
    </div>

    <!-- Empty/No Curriculum Error -->
    <div v-else-if="!learningPathData?.has_curriculum" class="flex flex-col items-center gap-4 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm px-6">
      <span class="material-symbols-outlined text-5xl text-[var(--muted)] opacity-60">clone</span>
      <h3 class="text-base font-bold text-[var(--text)]">Chưa gán Chương trình đào tạo</h3>
      <p class="text-xs text-[var(--muted)] max-w-md">{{ learningPathData?.message || 'Tài khoản của bạn chưa được gán lộ trình hoặc lớp học hành chính.' }}</p>
      <NuxtLink to="/student/courses" class="inline-flex items-center h-9 px-5 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors mt-2">Khám phá các khóa học</NuxtLink>
    </div>

    <div v-else class="flex flex-col gap-6">
      <!-- Overall progress card -->
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
          <div class="flex flex-col">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Chương trình học</span>
            <strong class="text-lg font-extrabold text-[#1d9e75] mt-1">{{ learningPathData.curriculum_name }}</strong>
            <span class="text-xs text-[var(--muted)] mt-1 font-semibold">Mã CTĐT: {{ learningPathData.curriculum_code }}</span>
          </div>
          <div class="flex flex-col gap-2">
            <div class="flex justify-between items-center text-xs font-semibold text-[var(--text)]">
              <span>Tiến độ tích lũy tín chỉ</span>
              <strong>{{ learningPathData.total_credits_earned }} / {{ learningPathData.total_credits_required }} Tín chỉ</strong>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden border border-[var(--line)]">
              <div class="h-full bg-gradient-to-r from-emerald-500 to-[#1d9e75] rounded-full transition-all" :style="{ width: `${overallCreditsProgress}%` }"></div>
            </div>
            <span class="text-[10px] font-bold text-[#1d9e75]">{{ overallCreditsProgress }}% Hoàn tất chương trình</span>
          </div>
        </div>
      </div>

      <!-- Semesters Accordion -->
      <div class="flex flex-col gap-4">
        <div 
          v-for="term in learningPathData.terms" 
          :key="term.term_number" 
          class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm transition-all"
        >
          <!-- Accordion Header -->
          <div class="px-5 py-4 bg-[var(--surface)] hover:bg-[var(--surface-strong)] flex justify-between items-center cursor-pointer transition-colors" @click="toggleTerm(term.term_number)">
            <div class="flex items-center gap-3 flex-wrap">
              <div class="bg-slate-200 text-slate-800 px-3 py-1 rounded-full text-xs font-bold">Học kỳ {{ term.term_number }}</div>
              <div class="flex items-center gap-2 text-xs text-[var(--muted)] font-semibold">
                <span>Số tín chỉ: <strong>{{ term.credits }}</strong></span>
                <span class="text-slate-300">•</span>
                <span>
                  Đã đạt: <strong>{{ getTermCompletedCount(term.courses) }} / {{ term.courses.length }}</strong> môn học
                </span>
              </div>
            </div>
            <div class="text-[var(--muted)]">
              <span class="material-symbols-outlined text-lg leading-none align-middle">{{ expandedTerms.includes(term.term_number) ? 'expand_less' : 'expand_more' }}</span>
            </div>
          </div>

          <!-- Accordion Content -->
          <div v-show="expandedTerms.includes(term.term_number)" class="p-5 flex flex-col gap-3 border-t border-[var(--line)]">
            <div v-if="term.courses.length === 0" class="text-center py-6 text-xs text-[var(--muted)] font-semibold italic">
              Chưa có học phần nào được thiết lập cho học kỳ này.
            </div>
            
            <div 
              v-else 
              v-for="course in term.courses" 
              :key="course.id" 
              class="border rounded-2xl p-4 flex flex-col lg:flex-row justify-between lg:items-center gap-4 hover:shadow-sm transition-all"
              :class="getStatusClass(course.status)"
            >
              <!-- Course Status & Info -->
              <div class="flex items-center gap-3 flex-1">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" :class="course.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : course.status === 'learning' ? 'bg-sky-50 text-sky-600' : 'bg-slate-100 text-slate-400'">
                  <span class="material-symbols-outlined text-base">{{ course.status === 'completed' ? 'check_circle' : course.status === 'learning' ? 'schedule' : 'help' }}</span>
                </div>
                <div class="flex flex-col gap-1.5">
                  <NuxtLink :to="`/student/courses/${course.id}`" class="text-xs font-bold text-[var(--text)] hover:text-[#1d9e75] transition-colors">
                    {{ course.title }}
                  </NuxtLink>
                  <div class="flex items-center gap-2">
                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold border border-slate-200">{{ course.credits }} tín chỉ</span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border" :class="course.course_mode === 'core' ? 'bg-red-50 text-red-600 border-red-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'">
                      {{ course.course_mode === 'core' ? 'Bắt buộc' : 'Tự chọn' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Course Progress & Grade -->
              <div class="flex items-center gap-4 w-full lg:w-48" v-if="course.status !== 'not_started'">
                <div class="flex flex-col gap-1 flex-1">
                  <div class="h-1 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-sky-500 rounded-full" :class="{ '!bg-emerald-500': course.status === 'completed' }" :style="{ width: `${course.progress}%` }"></div>
                  </div>
                  <span class="text-[10px] font-semibold text-[var(--muted)]">Tiến độ: {{ Math.round(course.progress) }}%</span>
                </div>
                <div v-if="course.final_score !== null" class="px-2 py-1 bg-slate-100 rounded-lg border border-slate-200 text-[10px] text-[var(--text)]">
                  Điểm: <strong class="text-xs font-bold text-emerald-600">{{ course.final_score }}</strong>
                </div>
              </div>
              <div class="w-full lg:w-48 text-right text-[10px] font-semibold text-[var(--muted)] italic" v-else>
                Học phần chưa đăng ký học kỳ này
              </div>

              <!-- Course Row Actions -->
              <div class="flex items-center justify-between lg:justify-end gap-3 pt-3 lg:pt-0 border-t lg:border-t-0 border-[var(--line)]">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="course.status === 'completed' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : course.status === 'learning' ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-slate-50 text-slate-500 border-slate-200'">
                  {{ getStatusLabel(course.status) }}
                </span>
                
                <NuxtLink 
                  :to="`/student/courses/${course.id}`" 
                  class="h-7 px-3 rounded-lg text-[10px] font-bold inline-flex items-center justify-center transition-colors"
                  :class="course.status === 'completed' ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : course.status === 'learning' ? 'bg-sky-600 hover:bg-sky-700 text-white' : 'bg-white border border-[var(--line)] hover:bg-[var(--surface)] text-[var(--text)]'"
                >
                  {{ course.status === 'completed' ? 'Ôn tập bài' : course.status === 'learning' ? 'Học tiếp' : 'Xem thông tin' }}
                </NuxtLink>
              </div>
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
