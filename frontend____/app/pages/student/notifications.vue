<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const notifications = ref<any[]>([])
const marking = ref(false)

onMounted(load)

async function load() {
  loading.value = true
  try {
    const data = await useApi<any>('/user/notifications?per_page=50', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    notifications.value = data?.data || data || []
  } finally {
    loading.value = false
  }
}

async function markAllRead() {
  marking.value = true
  try {
    await useApi('/user/notifications/read-all', {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }))
  } finally {
    marking.value = false
  }
}

async function markRead(n: any) {
  if (n.read_at) return
  try {
    await useApi(`/user/notifications/${n.id}/read`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    n.read_at = new Date().toISOString()
  } catch {}
}

const unread = computed(() => notifications.value.filter(n => !n.read_at).length)

function formatDate(d: string) {
  if (!d) return ''
  const dt = new Date(d)
  const now = new Date()
  const diff = Math.floor((now.getTime() - dt.getTime()) / 60000)
  if (diff < 1) return 'Vừa xong'
  if (diff < 60) return `${diff} phút trước`
  if (diff < 1440) return `${Math.floor(diff / 60)} giờ trước`
  return dt.toLocaleDateString('vi-VN')
}

function typeIcon(type: string) {
  if (!type) return 'notifications'
  if (type.includes('course') || type.includes('Course')) return 'school'
  if (type.includes('exam') || type.includes('Exam')) return 'assignment'
  if (type.includes('grade') || type.includes('Grade')) return 'analytics'
  if (type.includes('cert') || type.includes('Cert')) return 'verified'
  if (type.includes('payment') || type.includes('Payment')) return 'payments'
  return 'notifications_active'
}

function typeIconClass(type: string) {
  if (!type) return 'bg-slate-50 text-slate-500 border-slate-100'
  if (type.includes('course') || type.includes('Course')) return 'bg-emerald-50 text-emerald-700 border-emerald-100'
  if (type.includes('exam') || type.includes('Exam')) return 'bg-sky-50 text-sky-700 border-sky-100'
  if (type.includes('grade') || type.includes('Grade')) return 'bg-amber-50 text-amber-700 border-amber-100'
  if (type.includes('cert') || type.includes('Cert')) return 'bg-purple-50 text-purple-700 border-purple-100'
  if (type.includes('payment') || type.includes('Payment')) return 'bg-rose-50 text-rose-700 border-rose-100'
  return 'bg-blue-50 text-blue-700 border-blue-100'
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-3xl mx-auto px-4 py-2">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Kênh hỗ trợ</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Thông báo</h1>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="unread > 0" class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">{{ unread }} chưa đọc</span>
        <button v-if="unread > 0" class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-bold text-[var(--text)] flex items-center gap-1.5 transition-colors" :disabled="marking" @click="markAllRead">
          <span class="material-symbols-outlined text-sm leading-none">done_all</span>
          {{ marking ? 'Đang xử lý...' : 'Đánh dấu đã đọc' }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-col gap-2.5 animate-pulse">
      <div v-for="i in 5" :key="i" class="h-16 bg-[var(--surface-strong)] border border-[var(--line)] rounded-xl" />
    </div>

    <!-- Empty State -->
    <div v-else-if="notifications.length === 0" class="flex flex-col items-center gap-3 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">notifications_off</span>
      <h3 class="text-base font-bold text-[var(--text)]">Không có thông báo nào</h3>
      <p class="text-xs text-[var(--muted)] max-w-[280px]">Các thông báo về khóa học, kỳ thi và điểm số sẽ xuất hiện ở đây.</p>
    </div>

    <!-- Notifications List -->
    <div v-else class="flex flex-col gap-2">
      <button
        v-for="n in notifications"
        :key="n.id"
        class="flex items-start gap-4 p-4 rounded-xl border w-full text-left transition-colors relative"
        :class="!n.read_at ? 'bg-emerald-50/20 border-emerald-200/50 hover:bg-emerald-50/30' : 'bg-white border-[var(--line)] hover:bg-[var(--surface)]'"
        @click="markRead(n)"
      >
        <div class="w-9 h-9 rounded-xl flex items-center justify-center border flex-shrink-0" :class="typeIconClass(n.type || n.data?.type)">
          <span class="material-symbols-outlined text-base">{{ typeIcon(n.type || n.data?.type) }}</span>
        </div>
        
        <div class="flex-1 min-w-0 pr-4">
          <p class="text-xs font-semibold text-[var(--text)] leading-snug" :class="{ 'font-bold text-slate-900': !n.read_at }">
            {{ n.data?.message || n.data?.title || n.data?.body || 'Thông báo mới' }}
          </p>
          <p v-if="n.data?.description || n.data?.body" class="text-[11px] text-[var(--muted)] mt-1.5 leading-relaxed line-clamp-2">
            {{ n.data.description || n.data.body }}
          </p>
          <span class="text-[9px] text-[var(--muted)] font-semibold mt-2 block">{{ formatDate(n.created_at) }}</span>
        </div>
        
        <div class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 self-center" v-if="!n.read_at" />
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
