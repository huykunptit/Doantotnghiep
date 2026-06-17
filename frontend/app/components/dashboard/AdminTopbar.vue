<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import {
  Menu, Search, Bell, BellOff, Sun, Moon, ChevronDown,
  Settings, LayoutDashboard, LogOut, Loader,
  GraduationCap, ReceiptText, CircleCheckBig, XCircle, Star, Info,
} from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useDarkMode } from '~/composables/useDarkMode'

const emit = defineEmits<{ toggleSidebar: [] }>()

defineProps<{
  searchPlaceholder: string
  userName: string
  userRole: string
  userAvatar?: string | null
  dashboardPath?: string
  settingsPath?: string
}>()

const auth = useAuthStore()
const { isDark, toggle: toggleDark, init: initDark } = useDarkMode()

const notifOpen = ref(false)
const notifLoading = ref(false)
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

async function openNotif() {
  notifOpen.value = !notifOpen.value
  if (!notifOpen.value) return
  notifLoading.value = true
  try {
    const data = await $fetch<{ data: any[] }>('/api/notifications?per_page=12', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    notifications.value = data.data || []
  } catch {}
  notifLoading.value = false
}

async function markAllRead() {
  if (!auth.token) return
  try {
    await $fetch('/api/notifications/read-all', {
      method: 'PUT',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    unreadCount.value = 0
    notifications.value.forEach((n) => (n.read_at = new Date().toISOString()))
  } catch {}
}

const notifIconMap: Record<string, any> = {
  enrollment: GraduationCap,
  order: ReceiptText,
  course_approved: CircleCheckBig,
  course_rejected: XCircle,
  review: Star,
  system: Info,
}

function notifIconComponent(type: string) {
  return notifIconMap[type] || Bell
}

function relativeTime(date: string) {
  if (!date) return ''
  const diff = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  return new Date(date).toLocaleDateString('vi-VN')
}

const userOpen = ref(false)

function closeAll() {
  notifOpen.value = false
  userOpen.value = false
}

function handleKey(e: KeyboardEvent) {
  if (e.key === 'Escape') closeAll()
}

async function handleLogout() {
  closeAll()
  const token = useAuthTokenCookie()
  try {
    if (token.value) {
      await useApi('/auth/logout', { method: 'POST', headers: { Authorization: `Bearer ${token.value}` } })
    }
  } catch {}
  finally {
    clearAuthSession()
    await navigateTo('/login')
  }
}

onMounted(() => {
  fetchUnreadCount()
  initDark()
  document.addEventListener('keydown', handleKey)
  if (import.meta.client) setInterval(fetchUnreadCount, 60000)
})

onUnmounted(() => document.removeEventListener('keydown', handleKey))
</script>

<template>
  <header class="tb">
    <!-- Left: sidebar toggle -->
    <div class="tb-left">
      <button type="button" class="tb-icon-btn" aria-label="Mở sidebar" @click="emit('toggleSidebar')">
        <Menu :size="20" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Center: search -->
    <label class="tb-search">
      <Search :size="16" :stroke-width="1.75" class="tb-search-icon" />
      <input type="search" :placeholder="searchPlaceholder" class="tb-search-input" aria-label="Tìm kiếm">
      <kbd class="tb-search-kbd">⌘K</kbd>
    </label>

    <!-- Right: actions -->
    <div class="tb-right">

      <!-- Dark mode -->
      <button type="button" class="tb-icon-btn" :title="isDark ? 'Sáng' : 'Tối'" @click="toggleDark">
        <Sun v-if="isDark" :size="18" :stroke-width="1.75" />
        <Moon v-else :size="18" :stroke-width="1.75" />
      </button>

      <!-- Notifications -->
      <div class="tb-popover-wrap">
        <button type="button" class="tb-icon-btn" aria-label="Thông báo" @click="openNotif">
          <Bell :size="18" :stroke-width="1.75" />
          <span v-if="unreadCount > 0" class="tb-badge" aria-label="`${unreadCount} chưa đọc`">
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </button>

        <Transition name="pop">
          <div v-if="notifOpen" class="tb-dropdown tb-notif-panel" @click.stop>
            <div class="tb-panel-head">
              <span class="tb-panel-title">Thông báo</span>
              <button v-if="unreadCount > 0" type="button" class="tb-mark-read" @click="markAllRead">
                Đánh dấu đã đọc
              </button>
            </div>

            <div class="tb-notif-body">
              <div v-if="notifLoading" class="tb-notif-empty">
                <Loader :size="24" :stroke-width="1.75" class="tb-spin" />
              </div>
              <div v-else-if="notifications.length === 0" class="tb-notif-empty">
                <BellOff :size="28" :stroke-width="1.5" />
                <p>Chưa có thông báo nào</p>
              </div>
              <template v-else>
                <NuxtLink
                  v-for="n in notifications"
                  :key="n.id"
                  :to="n.link || '#'"
                  class="tb-notif-item"
                  :class="{ 'is-unread': !n.read_at }"
                  @click="notifOpen = false"
                >
                  <div class="tb-notif-icon" :class="{ 'is-unread': !n.read_at }">
                    <component :is="notifIconComponent(n.type)" :size="15" :stroke-width="1.75" />
                  </div>
                  <div class="tb-notif-content">
                    <p class="tb-notif-title">{{ n.title }}</p>
                    <p class="tb-notif-msg">{{ n.message }}</p>
                    <p class="tb-notif-time">{{ relativeTime(n.created_at) }}</p>
                  </div>
                  <span v-if="!n.read_at" class="tb-unread-dot" aria-hidden="true" />
                </NuxtLink>
              </template>
            </div>
          </div>
        </Transition>
      </div>

      <!-- User menu -->
      <div class="tb-popover-wrap">
        <button type="button" class="tb-user-chip" @click="userOpen = !userOpen">
          <div class="tb-avatar">
            <img v-if="userAvatar" :src="userAvatar" :alt="userName" class="tb-avatar-img">
            <span v-else>{{ userName.slice(0, 2).toUpperCase() }}</span>
          </div>
          <div class="tb-user-info">
            <strong class="tb-user-name">{{ userName }}</strong>
            <span class="tb-user-role">{{ userRole }}</span>
          </div>
          <ChevronDown :size="14" :stroke-width="2" class="tb-chevron" :class="{ 'is-open': userOpen }" />
        </button>

        <Transition name="pop">
          <div v-if="userOpen" class="tb-dropdown tb-user-panel" @click.stop>
            <div class="tb-user-head">
              <div class="tb-avatar tb-avatar--lg">
                <img v-if="userAvatar" :src="userAvatar" :alt="userName" class="tb-avatar-img">
                <span v-else>{{ userName.slice(0, 2).toUpperCase() }}</span>
              </div>
              <div>
                <p class="tb-panel-name">{{ userName }}</p>
                <p class="tb-panel-role">{{ userRole }}</p>
              </div>
            </div>

            <div class="tb-divider" />

            <div class="tb-menu-items">
              <NuxtLink :to="settingsPath || '/admin/settings'" class="tb-menu-item" @click="userOpen = false">
                <Settings :size="15" :stroke-width="1.75" />
                Tài khoản & cài đặt
              </NuxtLink>
              <NuxtLink :to="dashboardPath || '/admin'" class="tb-menu-item" @click="userOpen = false">
                <LayoutDashboard :size="15" :stroke-width="1.75" />
                Bảng điều khiển
              </NuxtLink>
              <div class="tb-divider" />
              <button type="button" class="tb-menu-item tb-menu-item--danger" @click="handleLogout">
                <LogOut :size="15" :stroke-width="1.75" />
                Đăng xuất
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <div v-if="notifOpen || userOpen" class="tb-backdrop" @click="closeAll" />
  </header>
</template>

<style scoped>
/* ── Shell ── */
.tb {
  position: relative;
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 12px;
  height: 64px;
  padding: 0 20px;
  background: var(--surface-strong, #fff);
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
}

[data-theme="dark"] .tb {
  background: rgba(15, 34, 25, 0.95);
  border-bottom-color: var(--line);
}

/* ── Left ── */
.tb-left { flex-shrink: 0; }

/* ── Search ── */
.tb-search {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  max-width: 440px;
  height: 40px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface, rgba(240, 244, 242, 0.5));
  transition: border-color 150ms, box-shadow 150ms;
  cursor: text;
}

.tb-search:focus-within {
  border-color: rgba(var(--primary-rgb), 0.4);
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.08);
  background: var(--surface-strong, #fff);
}

.tb-search-icon {
  color: var(--muted);
  flex-shrink: 0;
}

.tb-search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 0.875rem;
  color: var(--text);
  min-width: 0;
}

.tb-search-input::placeholder { color: var(--muted); }

.tb-search-kbd {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  height: 22px;
  padding: 0 7px;
  border-radius: 6px;
  border: 1px solid var(--line);
  background: var(--surface-strong, #fff);
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--muted);
  font-family: inherit;
}

/* ── Right ── */
.tb-right {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  margin-left: auto;
}

/* ── Icon button ── */
.tb-icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: background 150ms, color 150ms;
}

.tb-icon-btn:hover {
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--green);
}

.tb-badge {
  position: absolute;
  top: -5px; right: -5px;
  min-width: 17px; height: 17px;
  border-radius: 999px;
  background: var(--danger);
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 2px solid var(--surface-strong, #fff);
}

/* ── User chip ── */
.tb-user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 4px 10px 4px 4px;
  border-radius: 999px;
  border: 1px solid var(--line);
  background: transparent;
  cursor: pointer;
  transition: background 150ms;
}

.tb-user-chip:hover {
  background: rgba(var(--primary-rgb), 0.04);
}

.tb-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--green-soft);
  color: var(--green-deep);
  font-weight: 700;
  font-size: 0.75rem;
  flex-shrink: 0;
  overflow: hidden;
}

.tb-avatar--lg {
  width: 42px; height: 42px;
  font-size: 0.875rem;
}

.tb-avatar-img { width: 100%; height: 100%; object-fit: cover; }

.tb-user-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  text-align: left;
}

.tb-user-name { margin: 0; font-size: 0.8125rem; font-weight: 600; color: var(--text); }
.tb-user-role { font-size: 0.68rem; color: var(--muted); }

.tb-chevron {
  color: var(--muted);
  transition: transform 180ms ease;
  flex-shrink: 0;
}

.tb-chevron.is-open { transform: rotate(180deg); }

/* ── Backdrop ── */
.tb-backdrop {
  position: fixed;
  inset: 0;
  z-index: 49;
}

/* ── Dropdown shared ── */
.tb-popover-wrap { position: relative; }

.tb-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 200;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: 0 8px 32px rgba(31, 49, 43, 0.12);
  overflow: hidden;
}

[data-theme="dark"] .tb-dropdown {
  background: #142D1F;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.tb-panel-head, .tb-user-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--line);
}

.tb-user-head {
  justify-content: flex-start;
  gap: 10px;
}

.tb-panel-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--text);
}

.tb-mark-read {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--green);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
}

.tb-mark-read:hover { opacity: 0.75; }

.tb-divider {
  height: 1px;
  background: var(--line);
  margin: 4px 12px;
}

/* ── Notifications ── */
.tb-notif-panel { width: 340px; }
.tb-notif-body { max-height: 320px; overflow-y: auto; }

.tb-notif-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 32px 20px;
  color: var(--muted);
  font-size: 0.84rem;
  text-align: center;
}

.tb-spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.tb-notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(var(--primary-rgb), 0.04);
  text-decoration: none;
  color: inherit;
  transition: background 150ms;
}

.tb-notif-item:hover { background: rgba(var(--primary-rgb), 0.04); }
.tb-notif-item.is-unread { background: rgba(var(--primary-rgb), 0.03); }
.tb-notif-item:last-child { border-bottom: none; }

.tb-notif-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border-radius: 8px;
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--muted);
  flex-shrink: 0;
}

.tb-notif-icon.is-unread {
  background: var(--green-soft);
  color: var(--green);
}

.tb-notif-content { flex: 1; min-width: 0; }

.tb-notif-title {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tb-notif-msg {
  margin: 3px 0 0;
  font-size: 0.75rem;
  color: var(--muted);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.5;
}

.tb-notif-time { margin: 4px 0 0; font-size: 0.68rem; color: var(--muted); opacity: 0.7; }

.tb-unread-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--green);
  flex-shrink: 0;
  margin-top: 4px;
}

/* ── User panel ── */
.tb-user-panel { width: 220px; }

.tb-panel-name { margin: 0; font-size: 0.875rem; font-weight: 700; color: var(--text); }
.tb-panel-role { margin: 2px 0 0; font-size: 0.7rem; color: var(--muted); }

.tb-menu-items { padding: 6px; display: flex; flex-direction: column; gap: 1px; }

.tb-menu-item {
  display: flex;
  align-items: center;
  gap: 9px;
  height: 38px;
  padding: 0 10px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-size: 0.8375rem;
  font-weight: 500;
  font-family: inherit;
  text-decoration: none;
  cursor: pointer;
  transition: background 150ms, color 150ms;
  text-align: left;
  width: 100%;
}

.tb-menu-item:hover {
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--text);
}

.tb-menu-item--danger:hover {
  background: var(--danger-soft);
  color: var(--danger);
}

/* ── Transition ── */
.pop-enter-active, .pop-leave-active {
  transition: opacity 150ms ease, transform 150ms ease;
}
.pop-enter-from, .pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
}

/* ── Responsive ── */
@media (max-width: 1080px) {
  .tb-search { max-width: none; }
}

@media (max-width: 640px) {
  .tb { padding: 0 14px; gap: 8px; }
  .tb-user-info { display: none; }
  .tb-search-kbd { display: none; }
  .tb-notif-panel { width: calc(100vw - 28px); right: -6px; }
}
</style>
