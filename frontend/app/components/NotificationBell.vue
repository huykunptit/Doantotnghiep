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
  <div class="cd-bell-wrap">
    <button @click="toggleDropdown" class="cd-bell-btn">
      <span class="material-symbols-outlined cd-bell-icon">notifications</span>
      <span v-if="unreadCount > 0" class="cd-bell-badge">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <Transition name="cd-fade-slide">
      <div v-if="open" class="cd-notif-dropdown" @click.stop>
        <!-- Header -->
        <div class="cd-notif-header">
          <h4 class="cd-notif-title">Thông báo</h4>
          <button v-if="unreadCount > 0" @click="markAllRead" class="cd-notif-mark-read">
            Đánh dấu tất cả đã đọc
          </button>
        </div>

        <!-- List -->
        <div class="cd-notif-list">
          <div v-if="loading" class="cd-notif-empty">Đang tải...</div>
          <div v-else-if="notifications.length === 0" class="cd-notif-empty">
            <span class="material-symbols-outlined">notifications_off</span>
            <p>Chưa có thông báo nào</p>
          </div>
          <template v-else>
            <NuxtLink
              v-for="notif in notifications"
              :key="notif.id"
              :to="notif.link || '#'"
              @click="open = false"
              class="cd-notif-item"
              :class="{ 'is-unread': !notif.read_at }"
            >
              <div class="cd-notif-icon-wrap" :class="{ 'is-unread-icon': !notif.read_at }">
                <span class="material-symbols-outlined">{{ typeIcon(notif.type) }}</span>
              </div>
              <div class="cd-notif-content">
                <p class="cd-notif-msg-title">{{ notif.title }}</p>
                <p class="cd-notif-msg-desc">{{ notif.message }}</p>
                <p class="cd-notif-time">{{ formatTime(notif.created_at) }}</p>
              </div>
              <div v-if="!notif.read_at" class="cd-notif-dot"></div>
            </NuxtLink>
          </template>
        </div>
      </div>
    </Transition>

    <!-- Click outside to close -->
    <div v-if="open" class="cd-notif-overlay" @click="open = false"></div>
  </div>
</template>

<style scoped>
.cd-bell-wrap { position: relative; }

.cd-bell-btn {
  position: relative;
  width: 40px; height: 40px;
  border-radius: 50%;
  background: #fff;
  border: 1px solid rgba(47, 122, 69, 0.1);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.cd-bell-btn:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.cd-bell-icon { font-size: 20px; color: var(--outline, #64748b); transition: color 0.2s; }
.cd-bell-btn:hover .cd-bell-icon { color: var(--primary, #2f7a45); }

.cd-bell-badge {
  position: absolute; top: -2px; right: -2px;
  display: flex; align-items: center; justify-content: center;
  width: 20px; height: 20px;
  border-radius: 50%;
  background: #ef4444; color: #fff;
  font-size: 10px; font-weight: 700;
  box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

.cd-notif-dropdown {
  position: absolute; right: 0; top: 48px; z-index: 50;
  width: 320px;
  background: #fff;
  border-radius: 24px;
  border: 1px solid rgba(47, 122, 69, 0.1);
  box-shadow: 0 12px 40px rgba(0,0,0,0.12);
  overflow: hidden;
}
@media (min-width: 640px) { .cd-notif-dropdown { width: 384px; } }

.cd-notif-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.08);
  background: var(--surface-low, #f1f5f9);
}
.cd-notif-title { margin: 0; font-size: 0.875rem; font-weight: 700; color: var(--on-surface, #0f172a); }
.cd-notif-mark-read {
  background: transparent; border: none; padding: 0;
  font-size: 0.75rem; font-weight: 600; color: var(--primary, #2f7a45);
  cursor: pointer;
}
.cd-notif-mark-read:hover { text-decoration: underline; }

.cd-notif-list { max-height: 320px; overflow-y: auto; }

.cd-notif-empty {
  padding: 24px; text-align: center; color: var(--on-surface-variant, #475569); font-size: 0.875rem;
}
.cd-notif-empty .material-symbols-outlined { font-size: 32px; color: var(--outline, #64748b); margin-bottom: 8px; display: block; }
.cd-notif-empty p { margin: 0; }

.cd-notif-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.05);
  text-decoration: none; transition: background 0.2s;
}
.cd-notif-item:last-child { border-bottom: none; }
.cd-notif-item:hover { background: var(--surface-low, #f1f5f9); }
.cd-notif-item.is-unread { background: rgba(47, 122, 69, 0.04); }

.cd-notif-icon-wrap {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; margin-top: 2px;
  background: var(--surface-high, #e2e8f0); color: var(--outline, #64748b);
}
.cd-notif-icon-wrap.is-unread-icon { background: rgba(47, 122, 69, 0.1); color: var(--primary, #2f7a45); }
.cd-notif-icon-wrap .material-symbols-outlined { font-size: 16px; }

.cd-notif-content { min-width: 0; flex: 1; }
.cd-notif-msg-title {
  margin: 0; font-size: 0.875rem; font-weight: 600; color: var(--on-surface, #0f172a);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cd-notif-msg-desc {
  margin: 2px 0 0; font-size: 0.75rem; color: var(--on-surface-variant, #475569);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.cd-notif-time { margin: 4px 0 0; font-size: 0.625rem; color: var(--outline, #64748b); }

.cd-notif-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary, #2f7a45); margin-top: 8px; flex-shrink: 0; }

.cd-notif-overlay { position: fixed; inset: 0; z-index: 40; }

/* Transitions */
.cd-fade-slide-enter-active { transition: all 0.2s ease-out; }
.cd-fade-slide-leave-active { transition: all 0.15s ease-in; }
.cd-fade-slide-enter-from, .cd-fade-slide-leave-to { opacity: 0; transform: translateY(4px) scale(0.95); }
</style>
