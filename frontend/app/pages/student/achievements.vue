<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const certificates = ref<any[]>([])
const transcript = ref<any>(null)
const enrollments = ref<any[]>([])

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0, r1, r2] = await Promise.allSettled([
    useApi<any[]>('/user/my-certificates', { headers: h }),
    useApi<any>('/me/transcript', { headers: h }),
    useApi<any[]>('/user/enrollments', { headers: h }),
  ])
  if (r0.status === 'fulfilled') { const d = r0.value; certificates.value = Array.isArray(d) ? d : (d?.data || []) }
  if (r1.status === 'fulfilled') transcript.value = r1.value
  if (r2.status === 'fulfilled') enrollments.value = r2.value || []
  loading.value = false
})

const gpa = computed(() => {
  const raw = transcript.value?.gpa ?? transcript.value?.cumulative_gpa
  return raw ? Number(raw).toFixed(2) : null
})

const gpaLabel = computed(() => {
  const v = parseFloat(gpa.value || '0')
  if (v >= 3.6) return 'Xuất sắc'
  if (v >= 3.2) return 'Giỏi'
  if (v >= 2.5) return 'Khá'
  if (v >= 2.0) return 'Trung bình'
  return '—'
})

const gpaGradientClass = computed(() => {
  const v = parseFloat(gpa.value || '0')
  if (v >= 3.6) return 'from-violet-600 via-purple-600 to-indigo-700'
  if (v >= 3.2) return 'from-emerald-600 via-[#1d9e75] to-teal-700'
  if (v >= 2.5) return 'from-sky-600 via-blue-600 to-indigo-700'
  return 'from-red-600 via-rose-600 to-red-700'
})

const totalCredits = computed(() => transcript.value?.total_credits ?? transcript.value?.credits ?? 0)
const completedCourses = computed(() => enrollments.value.filter(e => (e.progress ?? 0) >= 100).length)
const totalExams = computed(() => transcript.value?.total_exams ?? 0)

function formatDate(d?: string) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ</p>
      <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Thành tích của tôi</h1>
    </div>

    <!-- Hero GPA card -->
    <div class="rounded-3xl overflow-hidden relative bg-gradient-to-br shadow-md" :class="gpaGradientClass">
      <div class="relative z-10 flex flex-col md:flex-row items-center gap-8 p-8 md:p-10 text-white">
        <div class="flex flex-col items-center gap-1.5 min-w-[120px] text-center">
          <div v-if="loading" class="w-16 h-12 bg-white/20 animate-pulse rounded-xl" />
          <template v-else>
            <span class="text-5xl font-black tracking-tight leading-none">{{ gpa || '—' }}</span>
            <span class="text-[9px] font-bold text-white/70 uppercase tracking-widest mt-1">GPA tích lũy</span>
          </template>
        </div>
        <div class="hidden md:block w-px self-stretch bg-white/20"></div>
        <div class="flex flex-wrap gap-x-8 gap-y-4 justify-center md:justify-start">
          <div class="flex flex-col gap-0.5">
            <span class="text-base font-extrabold">{{ loading ? '—' : gpaLabel }}</span>
            <span class="text-[10px] text-white/60 font-semibold uppercase tracking-wider">Xếp loại</span>
          </div>
          <div class="flex flex-col gap-0.5">
            <span class="text-base font-extrabold">{{ loading ? '—' : totalCredits }}</span>
            <span class="text-[10px] text-white/60 font-semibold uppercase tracking-wider">Tín chỉ tích lũy</span>
          </div>
          <div class="flex flex-col gap-0.5">
            <span class="text-base font-extrabold">{{ loading ? '—' : completedCourses }}</span>
            <span class="text-[10px] text-white/60 font-semibold uppercase tracking-wider">Khóa hoàn thành</span>
          </div>
          <div class="flex flex-col gap-0.5">
            <span class="text-base font-extrabold">{{ loading ? '—' : certificates.length }}</span>
            <span class="text-[10px] text-white/60 font-semibold uppercase tracking-wider">Chứng chỉ</span>
          </div>
        </div>
      </div>
      <!-- Decorative background circles -->
      <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
        <div class="absolute w-48 h-48 rounded-full bg-white/5 -top-16 -right-10" />
        <div class="absolute w-32 h-32 rounded-full bg-white/5 -bottom-10 right-20" />
      </div>
    </div>

    <!-- Certificates -->
    <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
      <div class="flex justify-between items-center pb-2 border-b border-[var(--line)]">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Chứng chỉ</p>
          <h2 class="text-sm font-bold text-[var(--text)] mt-0.5">Chứng chỉ đã đạt được</h2>
        </div>
        <NuxtLink to="/student/certificates" class="text-xs font-bold text-[#1d9e75] hover:underline">Xem tất cả</NuxtLink>
      </div>

      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-44 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl animate-pulse" />
      </div>

      <div v-else-if="certificates.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="cert in certificates.slice(0, 4)" :key="cert.id" class="bg-white border border-[var(--line)] rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col">
          <div class="p-4 bg-gradient-to-br from-[#064e3b] to-[#065f46] text-white flex justify-between items-center">
            <span class="material-symbols-outlined text-xl text-white/70">reward</span>
            <span class="px-2 py-0.5 rounded-full text-[8px] font-bold bg-white/20 text-white">Đã cấp</span>
          </div>
          <div class="p-4 flex-1 flex flex-col gap-1.5">
            <p class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider truncate">{{ cert.course?.title || cert.course_title || 'Khóa học' }}</p>
            <p class="text-xs font-bold text-[var(--text)] line-clamp-1 leading-snug">{{ cert.title || cert.name || 'Chứng chỉ hoàn thành' }}</p>
            <p class="text-[10px] text-[var(--muted)] mt-auto">Cấp ngày {{ formatDate(cert.issued_at || cert.created_at) }}</p>
          </div>
          <div class="px-4 pb-4 mt-auto">
            <a v-if="cert.pdf_url || cert.certificate_url" :href="cert.pdf_url || cert.certificate_url" target="_blank" class="h-8 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white font-bold text-xs flex items-center justify-center gap-1 transition-colors">
              <span class="material-symbols-outlined text-sm">download</span> Tải PDF
            </a>
            <span v-else class="text-[10px] text-[var(--muted)] font-mono block text-right">#{{ cert.certificate_number || cert.id }}</span>
          </div>
        </div>
      </div>

      <div v-else class="flex flex-col items-center gap-3 text-center py-10">
        <span class="material-symbols-outlined text-3xl text-[var(--muted)] opacity-60">reward</span>
        <p class="text-xs font-semibold text-[var(--muted)]">Chưa có chứng chỉ nào.</p>
        <NuxtLink to="/student/courses" class="h-8 px-4 rounded-xl bg-[#1d9e75] hover:bg-[#157959] text-white text-xs font-bold flex items-center transition-colors mt-2">Bắt đầu học ngay</NuxtLink>
      </div>
    </div>

    <!-- Stats strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#1d9e75] flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">book</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : enrollments.length }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Đăng ký</span>
        </div>
      </div>
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">check_circle</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : completedCourses }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Hoàn thành</span>
        </div>
      </div>
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">assignment</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : totalExams }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Kỳ thi đã làm</span>
        </div>
      </div>
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-xl">school</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-xl font-extrabold text-[var(--text)] leading-none">{{ loading ? '…' : totalCredits }}</span>
          <span class="text-[10px] text-[var(--muted)] font-semibold uppercase tracking-wider">Tín chỉ</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
