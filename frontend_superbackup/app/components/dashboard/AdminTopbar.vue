<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useDarkMode } from '~/composables/useDarkMode'
import { useAdminMenuConfig } from '~/composables/useAdminMenuConfig'
import { searchMenuItems } from '~/composables/useSearchUtils'
import type { FlattenedMenuItem } from '~/composables/useSearchUtils'

const emit = defineEmits<{ toggleSidebar: [] }>()

defineProps<{
  searchPlaceholder: string
  userName: string
  userRole: string
  userAvatar?: string | null
  dashboardPath?: string
  settingsPath?: string
  floating?: boolean
}>()

const auth = useAuthStore()
const { isDark, toggle: toggleDark, init: initDark } = useDarkMode()

// ── Search State ──
const searchQuery = ref('')
const { menuItems } = useAdminMenuConfig()

const searchResults = computed<FlattenedMenuItem[]>(() => {
  return searchMenuItems(searchQuery.value, menuItems.value)
})

function handleSearchSelect(item: FlattenedMenuItem) {
  searchQuery.value = ''
  navigateTo(item.to)
}

function handleEscapeKey(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    searchQuery.value = ''
    closeAll()
  }
}

// ── Language Selector State ──
const langOpen = ref(false)
const currentLang = ref('vi') // default
const languages = [
  { code: 'vi', label: 'Tiếng Việt', flag: '🇻🇳' },
  { code: 'en', label: 'English', flag: '🇺🇸' },
]

const activeLanguage = computed(() => {
  return languages.find((l) => l.code === currentLang.value) || languages[0]
})

function toggleLang() {
  langOpen.value = !langOpen.value
  notifOpen.value = false
  userOpen.value = false
}

function selectLanguage(code: string) {
  currentLang.value = code
  langOpen.value = false
  if (import.meta.client) {
    localStorage.setItem('sylva-locale', code)
  }
}

// ── Notifications State ──
const notifOpen = ref(false)
const notifLoading = ref(false)
const notifications = ref<any[]>([])
const unreadCount = ref(0)
const userOpen = ref(false)
let unreadPoll: ReturnType<typeof setInterval> | undefined

const authHeaders = () => ({ Authorization: `Bearer ${auth.token}` })

async function fetchUnreadCount() {
  if (!auth.token) return
  try {
    const data = await useApi<{ count: number }>('/notifications/unread-count', { headers: authHeaders() })
    unreadCount.value = (data as any).count ?? 0
  } catch {}
}

async function openNotif() {
  notifOpen.value = !notifOpen.value
  userOpen.value = false
  langOpen.value = false
  if (!notifOpen.value) return
  notifLoading.value = true
  try {
    const data = await useApi<{ data: any[] }>('/notifications?per_page=12', { headers: authHeaders() })
    notifications.value = (data as any).data || []
  } catch {}
  notifLoading.value = false
}

async function markAllRead() {
  if (!auth.token) return
  try {
    await useApi('/notifications/read-all', { method: 'PUT', headers: authHeaders() })
    unreadCount.value = 0
    notifications.value.forEach((n) => (n.read_at = new Date().toISOString()))
  } catch {}
}

const notifMeta: Record<string, { icon: string; color: string; bg: string }> = {
  enrollment:      { icon: 'graduation-cap',  color: '#1D9E75', bg: 'rgba(29,158,117,0.1)' },
  order:           { icon: 'receipt',    color: '#7C3AED', bg: 'rgba(124,58,237,0.1)' },
  course_approved: { icon: 'check-circle', color: '#1D9E75', bg: 'rgba(29,158,117,0.1)' },
  course_rejected: { icon: 'times-circle',        color: '#E24B4A', bg: 'rgba(226,75,74,0.1)' },
  review:          { icon: 'star',           color: '#F59E0B', bg: 'rgba(245,158,11,0.1)' },
  system:          { icon: 'bolt',            color: '#3B82F6', bg: 'rgba(59,130,246,0.1)' },
}

function notifIconMeta(type: string) {
  return notifMeta[type] || { icon: 'bell', color: '#4A6059', bg: 'rgba(74,96,89,0.1)' }
}

function relativeTime(date: string) {
  if (!date) return ''
  const diff = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  return new Date(date).toLocaleDateString('vi-VN')
}

function closeAll() {
  notifOpen.value = false
  userOpen.value = false
  langOpen.value = false
}

function openUser() {
  userOpen.value = !userOpen.value
  notifOpen.value = false
  langOpen.value = false
}

// ── Role Switch items ──
const rolesList = computed(() => auth.user?.roles || [])
const canSwitchToStudent = computed(() => rolesList.value.includes('student') || rolesList.value.includes('instructor'))
const canSwitchToInstructor = computed(() => rolesList.value.includes('instructor'))

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
  document.addEventListener('keydown', handleEscapeKey)
  if (import.meta.client) {
    unreadPoll = setInterval(fetchUnreadCount, 60_000)
    const savedLocale = localStorage.getItem('sylva-locale')
    if (savedLocale) currentLang.value = savedLocale
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscapeKey)
  if (unreadPoll) clearInterval(unreadPoll)
})
</script>

<template>
  <header class="tb" :class="{ 'tb--floating': floating }">
    <!-- Left -->
    <div class="tb-left">
      <button type="button" class="tb-icon-btn" aria-label="Mở menu điều hướng" @click="emit('toggleSidebar')">
        <i class="pi pi-bars" style="font-size:1.1875rem" />
      </button>
    </div>

    <!-- Search container (scoped popup) -->
    <div class="tb-search-container">
      <label class="tb-search">
        <i class="pi pi-search" style="font-size:0.875rem" aria-hidden="true" />
        <input
          v-model="searchQuery"
          type="search"
          :placeholder="searchPlaceholder"
          class="tb-search-input"
          aria-label="Tìm kiếm trong hệ thống"
        >
        <kbd class="tb-search-kbd" aria-label="Phím tắt Command K">⌘K</kbd>
      </label>

      <!-- Search results -->
      <Transition name="pop">
        <div v-if="searchQuery" class="tb-search-results">
          <div v-if="searchResults.length > 0" class="tb-search-results-list">
            <div
              v-for="item in searchResults"
              :key="item.to"
              class="tb-search-result-item"
              @click="handleSearchSelect(item)"
            >
              <i :class="[item.icon, 'tb-search-result-icon']" />
              <div class="tb-search-result-text">
                <span class="tb-search-result-label">{{ item.label }}</span>
                <span class="tb-search-result-path">{{ item.path }}</span>
              </div>
            </div>
          </div>
          <div v-else class="tb-search-results-empty">
            <i class="pi pi-search-minus text-xl mb-1 block" />
            Không tìm thấy trang nào trùng khớp
          </div>
        </div>
      </Transition>
    </div>

    <!-- Right -->
    <div class="tb-right">
      <!-- Language switcher -->
      <div class="tb-popover">
        <button
          type="button"
          class="tb-icon-btn"
          :class="{ 'is-active': langOpen }"
          aria-label="Chọn ngôn ngữ"
          @click="toggleLang"
        >
          <span class="text-sm font-semibold">{{ activeLanguage.flag }}</span>
        </button>

        <Transition name="pop">
          <div v-if="langOpen" class="tb-panel tb-lang-panel" @click.stop>
            <div class="tb-menu">
              <button
                v-for="lang in languages"
                :key="lang.code"
                type="button"
                class="tb-menu-item"
                :class="{ 'is-active-lang': currentLang === lang.code }"
                @click="selectLanguage(lang.code)"
              >
                <span class="mr-2">{{ lang.flag }}</span>
                <span>{{ lang.label }}</span>
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Dark mode -->
      <button type="button" class="tb-icon-btn" :aria-label="isDark ? 'Chuyển sang chế độ sáng' : 'Chuyển sang chế độ tối'" @click="toggleDark">
        <Transition name="mode" mode="out-in">
          <i v-if="isDark" class="pi pi-sun" style="font-size:1.062rem" aria-hidden="true" />
          <i v-else class="pi pi-moon" style="font-size:1.062rem" aria-hidden="true" />
        </Transition>
      </button>

      <!-- Notifications -->
      <div class="tb-popover">
        <button type="button" class="tb-icon-btn" :class="{ 'is-active': notifOpen }" :aria-label="unreadCount > 0 ? `Thông báo (${unreadCount} chưa đọc)` : 'Thông báo'" :aria-expanded="notifOpen" @click="openNotif">
          <i class="pi pi-bell" style="font-size:1.0625rem" aria-hidden="true" />
          <span v-if="unreadCount > 0" class="tb-badge" aria-hidden="true">
            {{ unreadCount > 9 ? '9+' : unreadCount }}
          </span>
        </button>

        <Transition name="pop">
          <div v-if="notifOpen" class="tb-panel tb-notif-panel" @click.stop>
            <!-- Header -->
            <div class="tb-panel-head">
              <div class="tb-panel-head-left">
                <span class="tb-panel-title">Thông báo</span>
                <span v-if="unreadCount > 0" class="tb-unread-chip">{{ unreadCount }} chưa đọc</span>
              </div>
              <button v-if="unreadCount > 0" type="button" class="tb-mark-read" @click="markAllRead">
                Đánh dấu đã đọc
              </button>
            </div>

            <div class="tb-notif-body">
              <div v-if="notifLoading" class="tb-notif-empty">
                <i class="pi pi-spinner" style="font-size:1.375rem" />
                <p>Đang tải...</p>
              </div>
              <div v-else-if="notifications.length === 0" class="tb-notif-empty">
                <i class="pi pi-bell-slash" style="font-size:1.875rem" />
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
                  <div
                    class="tb-notif-icon"
                    :style="{ background: notifIconMeta(n.type).bg, color: notifIconMeta(n.type).color }"
                  >
                    <i :class="`pi pi-${notifIconMeta(n.type).icon}`" style="font-size:0.875rem" />
                  </div>
                  <div class="tb-notif-content">
                    <p class="tb-notif-title">{{ n.title }}</p>
                    <p class="tb-notif-msg">{{ n.message }}</p>
                    <p class="tb-notif-time">{{ relativeTime(n.created_at) }}</p>
                  </div>
                  <div v-if="!n.read_at" class="tb-unread-dot" aria-hidden="true" />
                </NuxtLink>
              </template>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Divider -->
      <div class="tb-divider-v" />

      <!-- User menu -->
      <div class="tb-popover">
        <button type="button" class="tb-user-chip" :class="{ 'is-open': userOpen }" @click="openUser">
          <div class="tb-avatar">
            <img v-if="userAvatar" :src="userAvatar" :alt="userName" class="tb-avatar-img">
            <span v-else>{{ userName.slice(0, 2).toUpperCase() }}</span>
          </div>
          <div class="tb-user-info">
            <strong class="tb-user-name">{{ userName }}</strong>
            <span class="tb-user-role">{{ userRole }}</span>
          </div>
          <i class="pi pi-chevron-down" style="font-size:0.8125rem" />
        </button>

        <Transition name="pop">
          <div v-if="userOpen" class="tb-panel tb-user-panel" @click.stop>
            <!-- Profile head -->
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

            <div class="tb-sep" />

            <div class="tb-menu">
              <!-- Switch to Student portal -->
              <NuxtLink v-if="canSwitchToStudent" to="/student" class="tb-menu-item" @click="userOpen = false">
                <i class="pi pi-graduation-cap text-emerald-600" style="font-size:0.875rem" />
                <span>Giao diện học viên</span>
              </NuxtLink>

              <!-- Switch to Instructor portal -->
              <NuxtLink v-if="canSwitchToInstructor" to="/instructor" class="tb-menu-item" @click="userOpen = false">
                <i class="pi pi-briefcase text-sky-600" style="font-size:0.875rem" />
                <span>Giao diện giảng viên</span>
              </NuxtLink>

              <div v-if="canSwitchToStudent || canSwitchToInstructor" class="tb-sep" />

              <NuxtLink :to="settingsPath || '/admin/settings'" class="tb-menu-item" @click="userOpen = false">
                <i class="pi pi-cog" style="font-size:0.875rem" />
                Tài khoản & cài đặt
              </NuxtLink>
              <NuxtLink :to="dashboardPath || '/admin'" class="tb-menu-item" @click="userOpen = false">
                <i class="pi pi-th-large" style="font-size:0.875rem" />
                Bảng điều khiển
              </NuxtLink>
              
              <div class="tb-sep" />
              <button type="button" class="tb-menu-item tb-menu-danger" @click="handleLogout">
                <i class="pi pi-sign-out" style="font-size:0.875rem" />
                Đăng xuất
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Backdrop -->
    <div v-if="notifOpen || userOpen || langOpen || searchQuery" class="tb-backdrop" @click="closeAll" />
  </header>
</template>

<style scoped>
/* ── Shell ── */
.tb {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 10px;
  height: 60px;
  padding: 0 20px;
  background: var(--surface-strong);
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
}

.tb--floating {
  top: 8px;
  height: 56px;
  margin: 8px 8px 4px;
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(var(--color-text-rgb), 0.06);
}

/* ── Left ── */
.tb-left { flex-shrink: 0; }

/* ── Search Container & Results ── */
.tb-search-container {
  position: relative;
  flex: 1;
  max-width: 420px;
}

.tb-search {
  display: flex;
  align-items: center;
  gap: 9px;
  height: 38px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: var(--surface);
  transition: border-color 150ms, box-shadow 150ms, background 150ms;
  cursor: text;
}

.tb-search:focus-within {
  border-color: rgba(var(--green-rgb), 0.45);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.08);
  background: var(--surface-strong);
}

.tb-search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
  font-size: 0.84rem;
  color: var(--text);
  min-width: 0;
}

.tb-search-input::placeholder { color: var(--muted); opacity: 0.7; }

.tb-search-kbd {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 6px;
  border-radius: 5px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  font-size: 0.68rem;
  font-weight: 600;
  color: var(--muted);
  font-family: inherit;
}

.tb-search-results {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  max-height: 320px;
  overflow-y: auto;
  z-index: 100;
}

.tb-search-results-list {
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.tb-search-result-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 150ms;
}

.tb-search-result-item:hover {
  background: var(--sidebar-hover-bg);
}

.tb-search-result-icon {
  font-size: 1rem;
  color: var(--color-primary);
}

.tb-search-result-text {
  display: flex;
  flex-direction: column;
}

.tb-search-result-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text);
}

.tb-search-result-path {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.tb-search-results-empty {
  padding: 24px;
  text-align: center;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

/* ── Language Panel ── */
.tb-lang-panel {
  width: 160px;
}

.tb-menu-item.is-active-lang {
  background: rgba(var(--green-rgb), 0.08);
  color: var(--color-primary);
  font-weight: 600;
}

/* ── Right ── */
.tb-right {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  margin-left: auto;
}

/* ── Icon button ── */
.tb-icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px; height: 36px;
  border-radius: 9px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: background 120ms, color 120ms, border-color 120ms;
}

.tb-icon-btn:hover {
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green);
  border-color: rgba(var(--green-rgb), 0.12);
}

.tb-icon-btn.is-active {
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green);
  border-color: rgba(var(--green-rgb), 0.15);
}

/* ── Badge ── */
.tb-badge {
  position: absolute;
  top: -4px; right: -4px;
  min-width: 16px; height: 16px;
  border-radius: 999px;
  background: var(--danger);
  color: #fff;
  font-size: 0.6rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 2px solid var(--surface-strong);
  animation: badge-pop 250ms cubic-bezier(0.34,1.56,0.64,1);
}

@keyframes badge-pop {
  from { transform: scale(0); }
  to   { transform: scale(1); }
}

/* ── Dividers ── */
.tb-divider-v {
  width: 1px;
  height: 22px;
  background: var(--line);
  margin: 0 4px;
  flex-shrink: 0;
}

/* ── User chip ── */
.tb-user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 3px 10px 3px 3px;
  border-radius: 999px;
  border: 1px solid var(--line);
  background: transparent;
  cursor: pointer;
  transition: background 120ms, border-color 120ms;
}

.tb-user-chip:hover, .tb-user-chip.is-open {
  background: rgba(var(--green-rgb), 0.05);
  border-color: rgba(var(--green-rgb), 0.2);
}

.tb-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--green-soft) 0%, rgba(var(--green-rgb),0.18) 100%);
  color: var(--green-deep);
  font-weight: 800;
  font-size: 0.72rem;
  flex-shrink: 0;
  overflow: hidden;
  letter-spacing: 0.04em;
}

.tb-avatar--lg {
  width: 40px; height: 40px;
  font-size: 0.84rem;
}

.tb-avatar-img { width: 100%; height: 100%; object-fit: cover; }

.tb-user-info {
  display: flex;
  flex-direction: column;
  gap: 0;
  text-align: left;
}

.tb-user-name { margin: 0; font-size: 0.8rem; font-weight: 700; color: var(--text); line-height: 1.25; }
.tb-user-role { font-size: 0.66rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }

/* ── Backdrop ── */
.tb-backdrop {
  position: fixed;
  inset: 0;
  z-index: 49;
}

/* ── Popover / Panel shared ── */
.tb-popover { position: relative; }

.tb-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  z-index: 200;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 16px;
  box-shadow:
    0 4px 6px -1px rgba(0,0,0,0.06),
    0 16px 40px -8px rgba(31,49,43,0.14),
    0 0 0 1px rgba(var(--green-rgb),0.04);
  overflow: hidden;
}

/* ── Notification panel ── */
.tb-notif-panel { width: 348px; }

.tb-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--line);
}

.tb-panel-head-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tb-panel-title {
  font-size: 0.875rem;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.01em;
}

.tb-unread-chip {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green);
}

.tb-mark-read {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--green);
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
  padding: 36px 20px;
  color: var(--muted);
  font-size: 0.82rem;
  text-align: center;
}

.tb-notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  text-decoration: none;
  color: inherit;
  transition: background 120ms;
  border-bottom: 1px solid rgba(var(--green-rgb), 0.04);
}
.tb-notif-item:last-child { border-bottom: none; }
.tb-notif-item:hover { background: rgba(var(--green-rgb), 0.04); }
.tb-notif-item.is-unread { background: rgba(var(--green-rgb), 0.025); }

.tb-notif-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 8px;
  flex-shrink: 0;
}

.tb-notif-content { flex: 1; min-width: 0; }
.tb-notif-title { margin: 0; font-size: 0.8125rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tb-notif-msg   { margin: 2px 0 0; font-size: 0.75rem; color: var(--muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; }
.tb-notif-time  { margin: 4px 0 0; font-size: 0.67rem; color: var(--muted); opacity: 0.65; }

.tb-unread-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--green);
  flex-shrink: 0;
  margin-top: 5px;
}

/* ── User panel ── */
.tb-user-panel { width: 230px; }

.tb-user-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 14px 12px;
  background: linear-gradient(135deg, rgba(var(--green-rgb),0.06) 0%, transparent 100%);
  border-bottom: 1px solid var(--line);
}
.tb-panel-name { margin: 0; font-size: 0.875rem; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.tb-panel-role { margin: 2px 0 0; font-size: 0.67rem; color: var(--muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.06em; }

.tb-sep { height: 1px; background: var(--line); margin: 4px 10px; }
.tb-menu { padding: 6px; display: flex; flex-direction: column; gap: 1px; }

.tb-menu-item {
  display: flex;
  align-items: center;
  gap: 9px;
  height: 36px;
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
  transition: background 120ms, color 120ms;
  text-align: left;
  width: 100%;
}
.tb-menu-item:hover { background: rgba(var(--green-rgb), 0.07); color: var(--text); }
.tb-menu-danger:hover { background: var(--danger-soft); color: var(--danger); }

/* ── Transitions ── */
.pop-enter-active, .pop-leave-active { transition: opacity 150ms ease, transform 150ms ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: translateY(-8px) scale(0.97); }

.mode-enter-active, .mode-leave-active { transition: opacity 150ms, transform 150ms; }
.mode-enter-from, .mode-leave-to { opacity: 0; transform: rotate(20deg) scale(0.7); }

/* ── Responsive ── */
@media (max-width: 1080px) {
  .tb-search-container { max-width: none; }
}
@media (max-width: 640px) {
  .tb { padding: 0 12px; gap: 6px; }
  .tb--floating { margin-inline: 8px; }
  .tb-user-info { display: none; }
  .tb-search-kbd { display: none; }
  .tb-notif-panel { width: calc(100vw - 24px); right: -12px; }
}
</style>
