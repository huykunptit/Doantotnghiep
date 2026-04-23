<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const open = ref(false)
const loading = ref(false)
const notifications = ref<any[]>([])
const unreadCount = ref(0)

async function fetchUnreadCount() {
  if (!auth.token) return
  try {
    const data = await $fetch<{ count: number }>('/api/notifications/unread-count', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    unreadCount.value = data.count
  } catch {}
}

async function fetchNotifications() {
  if (!auth.token) return
  loading.value = true
  try {
    const data = await $fetch<{ data: any[] }>('/api/notifications?per_page=10', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    notifications.value = data.data || []
  } catch {}
  loading.value = false
}

async function markAllRead() {
  if (!auth.token) return
  try {
    await $fetch('/api/notifications/read-all', {
      method: 'PUT',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    unreadCount.value = 0
    notifications.value.forEach(n => n.read_at = new Date().toISOString())
  } catch {}
}

function toggleDropdown() {
  open.value = !open.value
  if (open.value) fetchNotifications()
}

function formatTime(date: string) {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = Math.floor((now.getTime() - d.getTime()) / 1000)
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  return d.toLocaleDateString('vi-VN')
}

const typeIcon = (type: string) => {
  const map: Record<string, string> = {
    enrollment: 'school',
    order: 'receipt_long',
    course_approved: 'check_circle',
    course_rejected: 'cancel',
    review: 'star',
    system: 'info',
  }
  return map[type] || 'notifications'
}

onMounted(fetchUnreadCount)

// Poll every 60s
if (import.meta.client) {
  setInterval(fetchUnreadCount, 60000)
}
</script>

<template>
  <div class="relative">
    <button @click="toggleDropdown" class="relative w-10 h-10 rounded-full bg-surface-lowest border border-surface-dim hover:shadow-ambient flex items-center justify-center transition-all group">
      <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors text-[20px]">notifications</span>
      <span v-if="unreadCount > 0" class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-error text-white text-[10px] font-bold shadow-sm">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div v-if="open" class="absolute right-0 top-12 z-50 w-80 sm:w-96 rounded-2xl bg-surface-lowest border border-surface-dim shadow-ambient overflow-hidden" @click.stop>
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-surface-dim bg-surface-low">
          <h4 class="text-sm font-bold text-on-surface">Thông báo</h4>
          <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs font-semibold text-primary hover:underline">
            Đánh dấu tất cả đã đọc
          </button>
        </div>

        <!-- List -->
        <div class="max-h-80 overflow-y-auto">
          <div v-if="loading" class="p-6 text-center text-sm text-on-surface-variant">Đang tải...</div>
          <div v-else-if="notifications.length === 0" class="p-6 text-center">
            <span class="material-symbols-outlined text-3xl text-outline mb-2">notifications_off</span>
            <p class="text-sm text-on-surface-variant">Chưa có thông báo nào</p>
          </div>
          <template v-else>
            <NuxtLink
              v-for="notif in notifications"
              :key="notif.id"
              :to="notif.link || '#'"
              @click="open = false"
              class="flex items-start gap-3 px-4 py-3 hover:bg-surface-low transition-colors border-b border-surface-dim/30 last:border-0"
              :class="!notif.read_at ? 'bg-primary/5' : ''"
            >
              <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5" :class="!notif.read_at ? 'bg-primary/10 text-primary' : 'bg-surface-high text-outline'">
                <span class="material-symbols-outlined text-[16px]">{{ typeIcon(notif.type) }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-on-surface line-clamp-1">{{ notif.title }}</p>
                <p class="text-xs text-on-surface-variant line-clamp-2 mt-0.5">{{ notif.message }}</p>
                <p class="text-[10px] text-outline mt-1">{{ formatTime(notif.created_at) }}</p>
              </div>
              <div v-if="!notif.read_at" class="w-2 h-2 rounded-full bg-primary mt-2 shrink-0"></div>
            </NuxtLink>
          </template>
        </div>
      </div>
    </Transition>

    <!-- Click outside to close -->
    <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>
  </div>
</template>
