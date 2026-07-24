<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

definePageMeta({ layout: 'student', middleware: 'auth' })

const route = useRoute()
const attemptId = route.params.attemptId as string
const examId = route.query.exam as string
const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const result = ref<any>(null)

async function fetchResult() {
  loading.value = true
  error.value = ''
  try {
    const res = await useApi<any>(`/exams/${examId}/results/${attemptId}`, { headers: authHeaders() })
    result.value = res
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải kết quả thi.'
  }
  finally { loading.value = false }
}

const isPassed = computed(() => {
  if (!result.value) return false
  const score = result.value.score ?? result.value.attempt?.score ?? 0
  const passScore = result.value.exam?.pass_score ?? result.value.pass_score ?? 80
  return score >= passScore
})

const scorePercent = computed(() => Math.round(Number(result.value?.score ?? result.value?.attempt?.score ?? 0)))
const passScore = computed(() => result.value?.exam?.pass_score ?? result.value?.pass_score ?? 80)
const examTitle = computed(() => result.value?.exam?.title || result.value?.attempt?.exam_title || 'Kỳ thi')
const answers = computed(() => result.value?.answers || result.value?.attempt?.answers || [])
const correctCount = computed(() => answers.value.filter((a: any) => a.is_correct).length)
const incorrectCount = computed(() => answers.value.filter((a: any) => !a.is_correct).length)
const timeSpent = computed(() => result.value?.attempt?.time_spent ?? result.value?.time_spent ?? 0)

const circumference = 2 * Math.PI * 46
const strokeDash = computed(() => `${(scorePercent.value / 100) * circumference} ${circumference}`)

function formatTime(seconds: number) {
  if (!seconds) return '0:00'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

onMounted(fetchResult)
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-2 flex flex-col gap-6">
    <!-- Loading -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm gap-3">
      <span class="material-symbols-outlined text-3xl animate-spin text-[var(--muted)]">progress_activity</span>
      <p class="text-xs font-semibold text-[var(--muted)]">Đang tải kết quả thi...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex flex-col items-center justify-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm text-center px-6 gap-3">
      <span class="material-symbols-outlined text-4xl text-rose-500">error</span>
      <h3 class="text-base font-bold text-[var(--text)]">Không thể tải kết quả</h3>
      <p class="text-xs text-[var(--muted)] max-w-sm">{{ error }}</p>
      <NuxtLink to="/student/exams" class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-bold text-[var(--text)] flex items-center transition-colors mt-2">
        Quay lại Kỳ thi của tôi
      </NuxtLink>
    </div>

    <template v-else-if="result">
      <!-- Result hero card -->
      <div 
        class="bg-white border rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6"
        :class="isPassed ? 'border-emerald-200 bg-emerald-50/10' : 'border-rose-200 bg-rose-50/10'"
      >
        <div class="flex items-center gap-4 flex-wrap text-center md:text-left justify-center md:justify-start">
          <div 
            class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0"
            :class="isPassed ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100'"
          >
            <span class="material-symbols-outlined text-2xl">{{ isPassed ? 'emoji_events' : 'sentiment_very_dissatisfied' }}</span>
          </div>

          <div class="flex flex-col gap-1">
            <p 
              class="text-[10px] font-bold uppercase tracking-widest"
              :class="isPassed ? 'text-emerald-700' : 'text-rose-700'"
            >
              {{ isPassed ? 'Đạt yêu cầu' : 'Chưa đạt' }}
            </p>
            <h1 class="text-lg font-bold text-[var(--text)] leading-snug">{{ isPassed ? 'Chúc mừng bạn đã vượt qua!' : 'Hãy ôn luyện và thử lại' }}</h1>
            <p class="text-xs text-[var(--muted)] font-semibold">{{ examTitle }}</p>
          </div>
        </div>

        <!-- Score ring -->
        <div class="relative w-24 h-24 flex-shrink-0">
          <svg viewBox="0 0 100 100" class="w-full h-full">
            <circle cx="50" cy="50" r="46" fill="none" class="stroke-[var(--line)] stroke-[6]" />
            <circle
              cx="50" cy="50" r="46"
              fill="none"
              class="stroke-[6] transition-all duration-1000 ease-out"
              :class="isPassed ? 'stroke-emerald-500' : 'stroke-rose-500'"
              :stroke-dasharray="strokeDash"
              stroke-dashoffset="0"
              stroke-linecap="round"
              transform="rotate(-90 50 50)"
            />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center leading-none text-center">
            <span class="text-2xl font-black" :class="isPassed ? 'text-emerald-600' : 'text-rose-600'">{{ scorePercent }}%</span>
            <span class="text-[8px] text-[var(--muted)] font-bold mt-1 uppercase tracking-wider">Đạt: {{ passScore }}%</span>
          </div>
        </div>
      </div>

      <!-- Stats row -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Stat Item 1 -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 border border-emerald-100">
            <span class="material-symbols-outlined text-sm font-bold">check_circle</span>
          </div>
          <div class="flex flex-col">
            <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Câu đúng</span>
            <strong class="text-base font-extrabold text-[var(--text)] leading-snug mt-0.5">{{ correctCount }}</strong>
          </div>
        </div>
        <!-- Stat Item 2 -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0 border border-rose-100">
            <span class="material-symbols-outlined text-sm font-bold">cancel</span>
          </div>
          <div class="flex flex-col">
            <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Câu sai</span>
            <strong class="text-base font-extrabold text-[var(--text)] leading-snug mt-0.5">{{ incorrectCount }}</strong>
          </div>
        </div>
        <!-- Stat Item 3 -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0 border border-sky-100">
            <span class="material-symbols-outlined text-sm font-bold">schedule</span>
          </div>
          <div class="flex flex-col">
            <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Thời gian</span>
            <strong class="text-base font-extrabold text-[var(--text)] leading-snug mt-0.5">{{ formatTime(timeSpent) }}</strong>
          </div>
        </div>
        <!-- Stat Item 4 -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 border border-amber-100">
            <span class="material-symbols-outlined text-sm font-bold">tag</span>
          </div>
          <div class="flex flex-col">
            <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Tổng câu</span>
            <strong class="text-base font-extrabold text-[var(--text)] leading-snug mt-0.5">{{ answers.length }}</strong>
          </div>
        </div>
      </div>

      <!-- Answer review -->
      <div v-if="answers.length > 0" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4 border-b border-[var(--line)] pb-4">
          <div>
            <p class="text-[9px] font-bold uppercase tracking-widest text-[var(--muted)] mb-0.5">Chi tiết bài thi</p>
            <h3 class="text-sm font-bold text-[var(--text)]">Đánh giá từng câu hỏi</h3>
          </div>
          <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
            {{ correctCount }}/{{ answers.length }} câu đúng
          </span>
        </div>

        <div class="flex flex-col gap-3">
          <div
            v-for="(ans, i) in answers"
            :key="i"
            class="border rounded-2xl p-4 flex flex-col gap-3"
            :class="ans.is_correct ? 'bg-emerald-50/10 border-emerald-200/50' : 'bg-rose-50/10 border-rose-200/50'"
          >
            <div class="flex items-center justify-between gap-4">
              <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted)]">Câu {{ i + 1 }}</span>
              <span 
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold border"
                :class="ans.is_correct ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100'"
              >
                <span class="material-symbols-outlined text-[10px] font-extrabold leading-none">{{ ans.is_correct ? 'check' : 'close' }}</span>
                {{ ans.is_correct ? 'Đúng' : 'Sai' }}
              </span>
            </div>

            <p class="text-xs font-bold text-[var(--text)] leading-relaxed">{{ ans.question_content || ans.question?.content }}</p>
            
            <div class="flex flex-col gap-1.5 pt-2 border-t border-[var(--line)]/50 text-[11px]">
              <div class="flex items-start gap-1">
                <span class="text-[var(--muted)] font-semibold flex-shrink-0">Đáp án của bạn:</span>
                <span :class="ans.is_correct ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold'">
                  {{ ans.selected_answer || ans.user_answer || '(Không trả lời)' }}
                </span>
              </div>
              <div v-if="!ans.is_correct" class="flex items-start gap-1">
                <span class="text-[var(--muted)] font-semibold flex-shrink-0">Đáp án đúng:</span>
                <span class="text-emerald-600 font-bold">{{ ans.correct_answer }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-center gap-3">
        <NuxtLink :to="`/exam/${examId}`" class="h-9 px-5 rounded-xl bg-[#1d9e75] hover:bg-[#17876a] text-white text-xs font-bold flex items-center gap-1.5 transition-colors shadow-sm">
          <span class="material-symbols-outlined text-sm font-bold">replay</span> Thi lại
        </NuxtLink>
        <NuxtLink to="/student/courses" class="h-9 px-5 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-bold text-[var(--muted)] hover:text-[var(--text)] flex items-center transition-colors">
          Về khóa học của tôi
        </NuxtLink>
      </div>
    </template>
  </div>
</template>
