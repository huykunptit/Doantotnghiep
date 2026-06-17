<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Bell, BellOff, GraduationCap, ReceiptText, CircleCheckBig, XCircle, Star, Info } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const open = ref(false)
const loading = ref(false)
const notifications = ref<any[]>([])
const unreadCount = ref(0)

const typeIconMap: Record<string, any> = {
  enrollment: GraduationCap,
  order: ReceiptText,
  course_approved: CircleCheckBig,
  course_rejected: XCircle,
  review: Star,
  system: Info,
}

function getTypeIcon(type: string) {
  return typeIconMap[type] || Bell
}

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
    notifications.value.forEach(n => (n.read_at = new Date().toISOString()))
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

onMounted(fetchUnreadCount)

if (import.meta.client) {
  setInterval(fetchUnreadCount, 60000)
}
</script>

<template>
  <div class="nb-wrap">
    <button class="nb-btn" :aria-label="`Thông báo${unreadCount > 0 ? ` (${unreadCount} chưa đọc)` : ''}`" @click="toggleDropdown">
      <Bell :size="18" :stroke-width="1.75" />
      <span v-if="unreadCount > 0" class="nb-badge">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown -->
    <Transition name="nb-pop">
      <div v-if="open" class="nb-dropdown" @click.stop>
        <div class="nb-header">
          <h4 class="nb-title">Thông báo</h4>
          <button v-if="unreadCount > 0" class="nb-mark-read" @click="markAllRead">
            Đánh dấu tất cả đã đọc
          </button>
        </div>

        <div class="nb-list">
          <div v-if="loading" class="nb-empty">Đang tải...</div>
          <div v-else-if="notifications.length === 0" class="nb-empty">
            <BellOff :size="28" :stroke-width="1.5" class="nb-empty-icon" />
            <p>Chưa có thông báo nào</p>
          </div>
          <template v-else>
            <NuxtLink
              v-for="notif in notifications"
              :key="notif.id"
              :to="notif.link || '#'"
              class="nb-item"
              :class="{ 'is-unread': !notif.read_at }"
              @click="open = false"
            >
              <div class="nb-icon-wrap" :class="{ 'is-unread-icon': !notif.read_at }">
                <component :is="getTypeIcon(notif.type)" :size="15" :stroke-width="1.75" />
              </div>
              <div class="nb-content">
                <p class="nb-msg-title">{{ notif.title }}</p>
                <p class="nb-msg-desc">{{ notif.message }}</p>
                <p class="nb-time">{{ formatTime(notif.created_at) }}</p>
              </div>
              <div v-if="!notif.read_at" class="nb-dot" />
            </NuxtLink>
          </template>
        </div>
      </div>
    </Transition>

    <div v-if="open" class="nb-overlay" @click="open = false" />
  </div>
</template>

<style scoped>
.nb-wrap { position: relative; }

.nb-btn {
  position: relative;
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--line);
  background: var(--surface-strong, #fff); color: var(--muted);
  cursor: pointer; transition: color 150ms, background 150ms, border-color 150ms;
}
.nb-btn:hover { color: var(--text); background: var(--surface); border-color: rgba(var(--primary-rgb), 0.2); }

.nb-badge {
  position: absolute; top: -4px; right: -4px;
  display: flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; padding: 0 4px; border-radius: 999px;
  background: #ef4444; color: #fff; font-size: 0.625rem; font-weight: 700;
  box-shadow: 0 0 0 2px var(--surface-strong, #fff);
}

/* ── Dropdown ── */
.nb-dropdown {
  position: absolute; right: 0; top: calc(100% + 8px); z-index: 300;
  width: 340px; max-width: calc(100vw - 32px);
  background: var(--surface-strong, #fff);
  border-radius: 12px; border: 1px solid var(--line);
  box-shadow: 0 12px 40px -12px rgba(31, 49, 43, 0.18);
  overflow: hidden;
}

.nb-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-bottom: 1px solid var(--line);
  background: var(--surface);
}
.nb-title { margin: 0; font-size: 0.875rem; font-weight: 700; color: var(--text); }
.nb-mark-read {
  background: transparent; border: none; padding: 0;
  font-size: 0.75rem; font-weight: 600; color: var(--green); cursor: pointer;
}
.nb-mark-read:hover { text-decoration: underline; }

.nb-list { max-height: 340px; overflow-y: auto; }
.nb-list::-webkit-scrollbar { width: 4px; }
.nb-list::-webkit-scrollbar-thumb { background: var(--line); border-radius: 4px; }

.nb-empty {
  padding: 28px 16px; text-align: center;
  color: var(--muted); font-size: 0.875rem;
}
.nb-empty-icon { margin: 0 auto 10px; display: block; color: var(--muted); }
.nb-empty p { margin: 0; }

.nb-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 14px; border-bottom: 1px solid var(--line);
  text-decoration: none; transition: background 150ms;
}
.nb-item:last-child { border-bottom: none; }
.nb-item:hover { background: var(--surface); }
.nb-item.is-unread { background: rgba(var(--primary-rgb), 0.03); }

.nb-icon-wrap {
  display: flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0; margin-top: 1px;
  background: var(--surface); color: var(--muted);
}
.nb-icon-wrap.is-unread-icon { background: var(--green-soft); color: var(--green); }

.nb-content { min-width: 0; flex: 1; }
.nb-msg-title {
  margin: 0; font-size: 0.84rem; font-weight: 600; color: var(--text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.nb-msg-desc {
  margin: 2px 0 0; font-size: 0.75rem; color: var(--muted);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.nb-time { margin: 4px 0 0; font-size: 0.6875rem; color: var(--muted); opacity: 0.7; }

.nb-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--green); margin-top: 6px; flex-shrink: 0;
}

.nb-overlay { position: fixed; inset: 0; z-index: 299; }

/* ── Transition ── */
.nb-pop-enter-active { transition: opacity 180ms ease, transform 180ms ease; }
.nb-pop-leave-active { transition: opacity 130ms ease, transform 130ms ease; }
.nb-pop-enter-from, .nb-pop-leave-to { opacity: 0; transform: translateY(-6px) scale(0.97); }

[data-theme="dark"] .nb-dropdown { background: #0F2219; border-color: rgba(255,255,255,0.08); }
[data-theme="dark"] .nb-header { background: rgba(255,255,255,0.04); }
</style>
