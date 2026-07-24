<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const certs = ref<any[]>([])

onMounted(async () => {
  try {
    const data = await useApi<any>('/user/my-certificates', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    certs.value = Array.isArray(data) ? data : (data?.data || [])
  } finally {
    loading.value = false
  }
})

function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học vụ</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Chứng chỉ của tôi</h1>
      </div>
      <span class="text-xs font-bold text-[var(--muted)]" v-if="!loading">{{ certs.length }} chứng chỉ</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="h-64 rounded-2xl bg-[var(--surface-strong)] border border-[var(--line)] animate-pulse" />
    </div>

    <!-- Empty State -->
    <div v-else-if="certs.length === 0" class="flex flex-col items-center gap-4 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">verified</span>
      <h3 class="text-base font-bold text-[var(--text)]">Chưa có chứng chỉ nào</h3>
      <p class="text-xs text-[var(--muted)]">Hoàn thành các khóa học để nhận chứng chỉ.</p>
      <NuxtLink to="/student/courses" class="text-xs font-bold text-[#1d9e75] hover:underline">Xem khóa học của tôi</NuxtLink>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="cert in certs" :key="cert.id" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all flex flex-col">
        <!-- Certificate visual -->
        <div class="relative bg-gradient-to-br from-[#063d31] via-[#085041] to-[#0d7a60] p-6 text-white overflow-hidden">
          <div class="relative z-10 flex items-center gap-4">
            <span class="text-3xl flex-shrink-0">🎓</span>
            <div class="flex flex-col min-w-0">
              <span class="text-[8px] font-bold tracking-widest text-emerald-300 uppercase">CHỨNG CHỈ</span>
              <h3 class="text-xs font-extrabold line-clamp-2 leading-snug mt-0.5">{{ cert.course?.title || cert.certificate?.name || 'Chứng chỉ' }}</h3>
            </div>
          </div>
          <!-- Skew ribbon decoration -->
          <div class="absolute top-0 right-[-20px] bottom-0 w-24 bg-white/5 skew-x-[-12deg]" />
        </div>

        <!-- Info -->
        <div class="p-5 flex-1 flex flex-col gap-3">
          <p class="text-xs font-bold text-[var(--text)] line-clamp-2 leading-relaxed">{{ cert.course?.title || cert.certificate?.name || 'Chứng chỉ' }}</p>
          
          <div class="flex flex-col gap-1 text-[11px] font-semibold text-[var(--muted)]">
            <div class="flex justify-between">
              <span>Cấp ngày:</span>
              <span class="text-[var(--text)]">{{ formatDate(cert.issued_at) }}</span>
            </div>
            <div v-if="cert.expires_at" class="flex justify-between">
              <span>Hết hạn:</span>
              <span class="text-[var(--text)]">{{ formatDate(cert.expires_at) }}</span>
            </div>
            <div v-if="cert.serial_number || cert.credential_id" class="flex justify-between font-mono text-[9px] mt-1 border-t border-[var(--line)] pt-1">
              <span>ID:</span>
              <span>{{ cert.serial_number || cert.credential_id }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="px-5 pb-5 flex gap-2">
          <a v-if="cert.pdf_url || cert.download_url" :href="cert.pdf_url || cert.download_url" target="_blank" rel="noopener" class="h-9 flex-1 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-colors">
            <span class="material-symbols-outlined text-sm">download</span> Tải xuống PDF
          </a>
          <NuxtLink v-if="cert.course?.id" :to="`/learn/${cert.course.id}`" class="h-9 px-4 rounded-xl border border-[var(--line)] hover:bg-[var(--surface)] text-xs font-bold text-[var(--muted)] hover:text-[var(--text)] flex items-center justify-center transition-colors">
            Xem khóa học
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
