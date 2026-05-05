<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

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
const { siteName, siteLogo } = useSiteSettings()

/* ── Notifications ── */
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

function notifIcon(type: string) {
  const map: Record<string, string> = {
    enrollment: 'school', order: 'receipt_long',
    course_approved: 'check_circle', course_rejected: 'cancel',
    review: 'star', system: 'info',
  }
  return map[type] || 'notifications'
}

function relativeTime(date: string) {
  if (!date) return ''
  const diff = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  return new Date(date).toLocaleDateString('vi-VN')
}

/* ── User menu ── */
const userOpen = ref(false)

function closeAll() {
  notifOpen.value = false
  userOpen.value = false
}

function handleKey(e: KeyboardEvent) {
  if (e.key === 'Escape') closeAll()
}

/* ── Logout ── */
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
  document.addEventListener('keydown', handleKey)
  if (import.meta.client) {
    setInterval(fetchUnreadCount, 60000)
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKey)
})
</script>

<template>
  <header class="dashboard-topbar tb">
    <!-- Left: toggle + breadcrumb -->
    <div class="tb-left">
      <button type="button" class="tb-toggle" aria-label="Mở sidebar" @click="emit('toggleSidebar')">
        <span class="material-symbols-outlined">menu</span>
      </button>

      <div class="tb-brand-pill">
        <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="tb-brand-logo">
        <span v-else class="tb-brand-dot" />
        <span class="tb-brand-text">{{ siteName }}</span>
      </div>
    </div>

    <!-- Center: search -->
    <div class="tb-search">
      <span class="material-symbols-outlined tb-search-icon">search</span>
      <input type="text" :placeholder="searchPlaceholder" class="tb-search-input">
      <kbd class="tb-search-kbd">⌘K</kbd>
    </div>

    <!-- Right: actions + user -->
    <div class="tb-right">

      <!-- Notifications -->
      <div class="tb-popover-wrap">
        <button type="button" class="tb-icon-btn" @click="openNotif">
          <span class="material-symbols-outlined">notifications</span>
          <span v-if="unreadCount > 0" class="tb-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>

        <Transition name="pop">
          <div v-if="notifOpen" class="tb-dropdown tb-notif-panel" @click.stop>
            <div class="tb-notif-head">
              <span class="tb-panel-title">Thông báo</span>
              <button v-if="unreadCount > 0" type="button" class="tb-mark-read" @click="markAllRead">
                Đánh dấu đã đọc
              </button>
            </div>

            <div class="tb-notif-body">
              <div v-if="notifLoading" class="tb-notif-empty">
                <span class="material-symbols-outlined tb-spin">progress_activity</span>
              </div>
              <div v-else-if="notifications.length === 0" class="tb-notif-empty">
                <span class="material-symbols-outlined">notifications_off</span>
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
                    <span class="material-symbols-outlined">{{ notifIcon(n.type) }}</span>
                  </div>
                  <div class="tb-notif-content">
                    <p class="tb-notif-title">{{ n.title }}</p>
                    <p class="tb-notif-msg">{{ n.message }}</p>
                    <p class="tb-notif-time">{{ relativeTime(n.created_at) }}</p>
                  </div>
                  <span v-if="!n.read_at" class="tb-unread-dot" />
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
          <span class="material-symbols-outlined tb-chevron" :class="{ 'is-open': userOpen }">expand_more</span>
        </button>

        <Transition name="pop">
          <div v-if="userOpen" class="tb-dropdown tb-user-panel" @click.stop>
            <div class="tb-user-panel-head">
              <div class="tb-avatar tb-avatar--lg">
                <img v-if="userAvatar" :src="userAvatar" :alt="userName" class="tb-avatar-img">
                <span v-else>{{ userName.slice(0, 2).toUpperCase() }}</span>
              </div>
              <div>
                <p class="tb-panel-name">{{ userName }}</p>
                <p class="tb-panel-role">{{ userRole }}</p>
              </div>
            </div>

            <div class="tb-menu-items">
              <NuxtLink :to="settingsPath || '/admin/settings'" class="tb-menu-item" @click="userOpen = false">
                <span class="material-symbols-outlined">manage_accounts</span>
                Tài khoản & cài đặt
              </NuxtLink>
              <NuxtLink :to="dashboardPath || '/admin'" class="tb-menu-item" @click="userOpen = false">
                <span class="material-symbols-outlined">dashboard</span>
                Bảng điều khiển
              </NuxtLink>
              <div class="tb-menu-divider" />
              <button type="button" class="tb-menu-item tb-menu-item--danger" @click="handleLogout">
                <span class="material-symbols-outlined">logout</span>
                Đăng xuất
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Click outside overlay -->
    <div v-if="notifOpen || userOpen" class="tb-backdrop" @click="closeAll" />
  </header>
</template>

<style scoped>
/* ── Base ── */
.tb {
  position: relative;
  z-index: 200;
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 80px;
  padding: 14px 22px;
}

/* ── Left ── */
.tb-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.tb-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: transparent;
  color: var(--muted, #5f675f);
  cursor: pointer;
  transition: background 150ms ease;
}
.tb-toggle:hover { background: rgba(47, 122, 69, 0.06); }
.tb-toggle .material-symbols-outlined { font-size: 22px; }

.tb-brand-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(17, 17, 17, 0.07);
}
.tb-brand-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #2f7a45;
  box-shadow: 0 0 0 4px rgba(47, 122, 69, 0.12);
}
.tb-brand-logo {
  width: 22px;
  height: 22px;
  object-fit: contain;
  border-radius: 6px;
}
.tb-brand-text {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: rgba(17, 17, 17, 0.65);
}

/* ── Search ── */
.tb-search {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  max-width: 480px;
  min-height: 50px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1px solid rgba(17, 17, 17, 0.09);
  background: rgba(17, 17, 17, 0.03);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}
.tb-search:focus-within {
  border-color: rgba(47, 122, 69, 0.4);
  box-shadow: 0 0 0 3px rgba(47, 122, 69, 0.08);
  background: rgba(255, 255, 255, 0.9);
}
.tb-search-icon { font-size: 19px; color: var(--muted, #5f675f); flex-shrink: 0; }
.tb-search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 0.9rem;
  color: var(--text, #111111);
}
.tb-search-input::placeholder { color: var(--muted, #5f675f); }
.tb-search-kbd {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  height: 24px;
  padding: 0 8px;
  border-radius: 7px;
  border: 1px solid rgba(17, 17, 17, 0.12);
  background: rgba(255, 255, 255, 0.8);
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--muted, #5f675f);
  font-family: inherit;
}

/* ── Right ── */
.tb-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  margin-left: auto;
}

/* Icon buttons */
.tb-icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: rgba(255, 255, 255, 0.86);
  color: var(--muted, #5f675f);
  cursor: pointer;
  transition: background 150ms ease, color 150ms ease, transform 150ms ease;
}
.tb-icon-btn:hover { background: rgba(47, 122, 69, 0.08); color: var(--green-deep, #1f5d33); transform: translateY(-1px); }
.tb-icon-btn .material-symbols-outlined { font-size: 20px; }

.tb-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  background: #ae3d37;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  border: 2px solid #fff;
}

/* User chip */
.tb-user-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  border-radius: 999px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  background: rgba(255, 255, 255, 0.86);
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
}
.tb-user-chip:hover { background: rgba(47, 122, 69, 0.06); transform: translateY(-1px); }

.tb-avatar {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: #2f7a45;
  color: #fff;
  font-weight: 800;
  font-size: 0.82rem;
  flex-shrink: 0;
  overflow: hidden;
}
.tb-avatar--lg {
  width: 46px;
  height: 46px;
  border-radius: 16px;
  font-size: 1rem;
}
.tb-avatar-img { width: 100%; height: 100%; object-fit: cover; }

.tb-user-info { display: flex; flex-direction: column; gap: 1px; }
.tb-user-name { font-size: 0.85rem; font-weight: 700; color: var(--text, #111111); margin: 0; }
.tb-user-role { font-size: 0.72rem; color: var(--muted, #5f675f); }

.tb-chevron {
  font-size: 18px;
  color: var(--muted, #5f675f);
  transition: transform 200ms ease;
}
.tb-chevron.is-open { transform: rotate(180deg); }

/* ── Popover shared ── */
.tb-popover-wrap { position: relative; }

.tb-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  z-index: 100;
  border: 1px solid rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: 22px;
  box-shadow: 0 24px 60px -20px rgba(17, 17, 17, 0.2);
  overflow: hidden;
}

.tb-backdrop {
  position: fixed;
  inset: 0;
  z-index: 90;
}

/* ── Notification panel ── */
.tb-notif-panel { width: 360px; }

.tb-notif-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px 14px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.07);
}
.tb-panel-title {
  font-size: 0.9rem;
  font-weight: 800;
  color: var(--text, #111111);
}
.tb-mark-read {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--green-deep, #1f5d33);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: opacity 150ms;
}
.tb-mark-read:hover { opacity: 0.7; }

.tb-notif-body { max-height: 340px; overflow-y: auto; }

.tb-notif-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 32px 20px;
  color: var(--muted, #5f675f);
  font-size: 0.85rem;
}
.tb-notif-empty .material-symbols-outlined { font-size: 36px; color: rgba(17,17,17,0.2); }
.tb-spin { animation: tb-spin 1s linear infinite; }
@keyframes tb-spin { to { transform: rotate(360deg); } }

.tb-notif-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 13px 18px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.05);
  text-decoration: none;
  color: inherit;
  transition: background 150ms ease;
}
.tb-notif-item:hover { background: rgba(47, 122, 69, 0.04); }
.tb-notif-item.is-unread { background: rgba(47, 122, 69, 0.03); }
.tb-notif-item:last-child { border-bottom: none; }

.tb-notif-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 12px;
  background: rgba(17, 17, 17, 0.06);
  color: var(--muted, #5f675f);
  flex-shrink: 0;
}
.tb-notif-icon.is-unread {
  background: rgba(47, 122, 69, 0.1);
  color: var(--green-deep, #1f5d33);
}
.tb-notif-icon .material-symbols-outlined { font-size: 16px; }

.tb-notif-content { flex: 1; min-width: 0; }
.tb-notif-title {
  margin: 0;
  font-size: 0.84rem;
  font-weight: 700;
  color: var(--text, #111111);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tb-notif-msg {
  margin: 3px 0 0;
  font-size: 0.78rem;
  color: var(--muted, #5f675f);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.5;
}
.tb-notif-time { margin: 4px 0 0; font-size: 0.7rem; color: rgba(17,17,17,0.35); }

.tb-unread-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--green-deep, #1f5d33);
  flex-shrink: 0;
  margin-top: 6px;
}

/* ── User panel ── */
.tb-user-panel { width: 240px; }

.tb-user-panel-head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px 14px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.07);
}
.tb-panel-name { margin: 0; font-size: 0.88rem; font-weight: 800; color: var(--text, #111111); }
.tb-panel-role { margin: 2px 0 0; font-size: 0.72rem; color: var(--muted, #5f675f); }

.tb-menu-items { padding: 8px; display: flex; flex-direction: column; gap: 2px; }

.tb-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 42px;
  padding: 0 12px;
  border-radius: 12px;
  border: none;
  background: transparent;
  color: var(--muted, #5f675f);
  font-size: 0.86rem;
  font-weight: 600;
  font-family: inherit;
  text-decoration: none;
  cursor: pointer;
  transition: background 150ms ease, color 150ms ease;
  text-align: left;
  width: 100%;
}
.tb-menu-item:hover {
  background: rgba(47, 122, 69, 0.07);
  color: var(--text, #111111);
}
.tb-menu-item .material-symbols-outlined { font-size: 18px; }

.tb-menu-item--danger:hover {
  background: rgba(174, 61, 55, 0.08);
  color: #ae3d37;
}

.tb-menu-divider {
  height: 1px;
  background: rgba(17, 17, 17, 0.07);
  margin: 4px 0;
}

/* ── Transition ── */
.pop-enter-active, .pop-leave-active { transition: opacity 160ms ease, transform 160ms ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: translateY(6px) scale(0.97); }

/* ── Responsive ── */
@media (max-width: 1080px) {
  .tb-toggle { display: flex; }
  .tb-brand-pill { display: none; }
  .tb-search { max-width: none; }
}

@media (max-width: 640px) {
  .tb { padding: 12px 16px; gap: 10px; }
  .tb-user-info { display: none; }
  .tb-search-kbd { display: none; }
  .tb-notif-panel { width: calc(100vw - 32px); right: -8px; }
}
</style>
