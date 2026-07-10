<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import {
  Menu, Search, Bell, BellOff, Sun, Moon, ChevronDown,
  Settings, LayoutDashboard, LogOut, Loader,
  GraduationCap, ReceiptText, CircleCheckBig, XCircle, Star, Info, Zap,
  Coins, Flame, Trophy, BookOpen, Medal, ShoppingBag, ClipboardList, CalendarCheck, ChevronRight, X,
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
const userOpen = ref(false)

// ── Points / Quests panel ────────────────────────────────────────────────────
const questOpen = ref(false)
const questLoading = ref(false)
const questData = ref<any>(null)

const questIconMap: Record<string, any> = {
  'calendar-check': CalendarCheck,
  'flame': Flame,
  'trophy': Trophy,
  'book-open-check': BookOpen,
  'graduation-cap': GraduationCap,
  'medal': Medal,
  'shopping-bag': ShoppingBag,
  'clipboard-list': ClipboardList,
  'star': Star,
}
function questIcon(key: string) { return questIconMap[key] || Star }

async function openQuestPanel() {
  questOpen.value = !questOpen.value
  notifOpen.value = false
  userOpen.value = false
  if (!questOpen.value || questData.value) return
  questLoading.value = true
  try {
    questData.value = await useApi<any>('/points/quests', { headers: authHeaders() })
  } catch {}
  questLoading.value = false
}

const questCatLabel: Record<string, string> = {
  daily: 'Hàng ngày', milestone: 'Cột mốc', learning: 'Học tập', engagement: 'Tương tác',
}


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

const notifMeta: Record<string, { icon: any; color: string; bg: string }> = {
  enrollment:      { icon: GraduationCap,  color: '#1D9E75', bg: 'rgba(29,158,117,0.1)' },
  order:           { icon: ReceiptText,    color: '#7C3AED', bg: 'rgba(124,58,237,0.1)' },
  course_approved: { icon: CircleCheckBig, color: '#1D9E75', bg: 'rgba(29,158,117,0.1)' },
  course_rejected: { icon: XCircle,        color: '#E24B4A', bg: 'rgba(226,75,74,0.1)' },
  review:          { icon: Star,           color: '#F59E0B', bg: 'rgba(245,158,11,0.1)' },
  system:          { icon: Zap,            color: '#3B82F6', bg: 'rgba(59,130,246,0.1)' },
}

function notifIconMeta(type: string) {
  return notifMeta[type] || { icon: Bell, color: '#4A6059', bg: 'rgba(74,96,89,0.1)' }
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
  questOpen.value = false
}

function openUser() {
  userOpen.value = !userOpen.value
  notifOpen.value = false
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
  if (import.meta.client) setInterval(fetchUnreadCount, 60_000)
})

onUnmounted(() => document.removeEventListener('keydown', handleKey))
</script>

<template>
  <header class="tb">
    <!-- Left -->
    <div class="tb-left">
      <button type="button" class="tb-icon-btn" aria-label="Mở sidebar" @click="emit('toggleSidebar')">
        <Menu :size="19" :stroke-width="1.75" />
      </button>
    </div>

    <!-- Search -->
    <label class="tb-search">
      <Search :size="14" :stroke-width="2" class="tb-search-icon" />
      <input type="search" :placeholder="searchPlaceholder" class="tb-search-input" aria-label="Tìm kiếm">
      <kbd class="tb-search-kbd">⌘K</kbd>
    </label>

    <!-- Right -->
    <div class="tb-right">
      <!-- Dark mode -->
      <button type="button" class="tb-icon-btn" :title="isDark ? 'Chế độ sáng' : 'Chế độ tối'" @click="toggleDark">
        <Transition name="mode" mode="out-in">
          <Sun v-if="isDark" :key="'sun'" :size="17" :stroke-width="2" />
          <Moon v-else :key="'moon'" :size="17" :stroke-width="2" />
        </Transition>
      </button>

      <!-- Quest / Points -->
      <div class="tb-popover">
        <button
          type="button"
          class="tb-icon-btn tb-quest-btn"
          :class="{ 'is-active': questOpen }"
          title="Nhiệm vụ tích điểm"
          @click="openQuestPanel"
        >
          <Coins :size="17" :stroke-width="2" />
          <span v-if="questData?.balance" class="tb-quest-pts">{{ questData.balance > 9999 ? '9999+' : questData.balance }}</span>
        </button>

        <Transition name="pop">
          <div v-if="questOpen" class="tb-panel tb-quest-panel" @click.stop>
            <div class="tb-panel-head">
              <div class="tb-panel-head-left">
                <Coins :size="15" style="color:#f59e0b" />
                <span class="tb-panel-title">Điểm & Nhiệm vụ</span>
              </div>
              <button class="tb-close-panel" @click="questOpen = false"><X :size="14" /></button>
            </div>

            <!-- Balance bar -->
            <div v-if="questData" class="tb-quest-balance">
              <div class="tb-qbal-item">
                <Coins :size="14" style="color:#f59e0b" />
                <span><strong>{{ questData.balance.toLocaleString('vi-VN') }}</strong> điểm</span>
              </div>
              <div class="tb-qbal-item">
                <Flame :size="14" style="color:#ea580c" />
                <span>Streak <strong>{{ questData.streak_days }}</strong></span>
              </div>
            </div>

            <div class="tb-quest-body">
              <div v-if="questLoading" class="tb-notif-empty">
                <Loader :size="20" class="tb-spin" />
                <p>Đang tải...</p>
              </div>
              <template v-else-if="questData">
                <template v-for="cat in ['daily','milestone','learning','engagement']" :key="cat">
                  <template v-if="questData.quests.filter((q:any)=>q.category===cat).length">
                    <p class="tb-quest-cat-label">{{ questCatLabel[cat] }}</p>
                    <div
                      v-for="q in questData.quests.filter((q:any)=>q.category===cat)"
                      :key="q.key"
                      class="tb-quest-row"
                      :class="{ 'is-done': q.done_today }"
                    >
                      <div class="tb-quest-ico" :class="`qcat-${q.category}`">
                        <component :is="questIcon(q.icon)" :size="13" />
                      </div>
                      <div class="tb-quest-info">
                        <p class="tb-quest-name">{{ q.title }}</p>
                        <div v-if="q.progress !== undefined" class="tb-qprog">
                          <div class="tb-qprog-track"><div class="tb-qprog-fill" :style="{ width: `${Math.round((q.progress/q.target)*100)}%` }"/></div>
                          <span>{{ q.progress }}/{{ q.target }}</span>
                        </div>
                      </div>
                      <span class="tb-quest-pts">+{{ q.points }}</span>
                      <span v-if="q.done_today" class="tb-quest-check">✓</span>
                    </div>
                  </template>
                </template>
              </template>
            </div>

            <div class="tb-quest-foot">
              <NuxtLink to="/student/points" class="tb-quest-shop-link" @click="questOpen = false">
                Xem shop đổi quà <ChevronRight :size="13" />
              </NuxtLink>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Notifications -->
      <div class="tb-popover">
        <button type="button" class="tb-icon-btn" :class="{ 'is-active': notifOpen }" aria-label="Thông báo" @click="openNotif">
          <Bell :size="17" :stroke-width="2" />
          <span v-if="unreadCount > 0" class="tb-badge">
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
                <Loader :size="22" :stroke-width="1.75" class="tb-spin" />
                <p>Đang tải...</p>
              </div>
              <div v-else-if="notifications.length === 0" class="tb-notif-empty">
                <BellOff :size="30" :stroke-width="1.5" style="opacity:.3;" />
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
                    <component :is="notifIconMeta(n.type).icon" :size="14" :stroke-width="2" />
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
          <ChevronDown :size="13" :stroke-width="2.5" class="tb-chevron" :class="{ 'is-open': userOpen }" />
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
              <NuxtLink :to="settingsPath || '/admin/settings'" class="tb-menu-item" @click="userOpen = false">
                <Settings :size="14" :stroke-width="1.75" />
                Tài khoản & cài đặt
              </NuxtLink>
              <NuxtLink :to="dashboardPath || '/admin'" class="tb-menu-item" @click="userOpen = false">
                <LayoutDashboard :size="14" :stroke-width="1.75" />
                Bảng điều khiển
              </NuxtLink>
              <div class="tb-sep" />
              <button type="button" class="tb-menu-item tb-menu-danger" @click="handleLogout">
                <LogOut :size="14" :stroke-width="1.75" />
                Đăng xuất
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Backdrop -->
    <div v-if="notifOpen || userOpen" class="tb-backdrop" @click="closeAll" />
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
  background: rgba(240, 250, 247, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
}

[data-theme="dark"] .tb {
  background: rgba(9, 26, 21, 0.85);
  border-bottom-color: rgba(255,255,255,0.07);
}

/* ── Left ── */
.tb-left { flex-shrink: 0; }

/* ── Search ── */
.tb-search {
  display: flex;
  align-items: center;
  gap: 9px;
  flex: 1;
  max-width: 420px;
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

.tb-search-icon { color: var(--muted); flex-shrink: 0; }

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

.tb-chevron {
  color: var(--muted);
  transition: transform 200ms ease;
  flex-shrink: 0;
}
.tb-chevron.is-open { transform: rotate(180deg); }

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

[data-theme="dark"] .tb-panel {
  background: #0f1f17;
  border-color: rgba(255,255,255,0.08);
  box-shadow: 0 16px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04);
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

[data-theme="dark"] .tb-panel-head { border-bottom-color: rgba(255,255,255,0.07); }

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

.tb-spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

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
[data-theme="dark"] .tb-user-head { border-bottom-color: rgba(255,255,255,0.07); }

.tb-panel-name { margin: 0; font-size: 0.875rem; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.tb-panel-role { margin: 2px 0 0; font-size: 0.67rem; color: var(--muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.06em; }

.tb-sep { height: 1px; background: var(--line); margin: 4px 10px; }
[data-theme="dark"] .tb-sep { background: rgba(255,255,255,0.07); }

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

/* ── Quest button ── */
.tb-quest-btn { position: relative; }
.tb-quest-pts {
  position: absolute;
  bottom: -5px; right: -6px;
  min-width: 18px; height: 14px;
  border-radius: 999px;
  background: #f59e0b;
  color: #fff;
  font-size: 0.56rem;
  font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  padding: 0 3px;
  border: 2px solid var(--surface-strong);
}

/* ── Quest panel ── */
.tb-quest-panel { width: 320px; max-height: 480px; display: flex; flex-direction: column; }
.tb-close-panel {
  width: 24px; height: 24px; border-radius: 6px; border: none;
  background: transparent; color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.tb-close-panel:hover { background: var(--surface); }

.tb-quest-balance {
  display: flex; gap: 0;
  border-bottom: 1px solid var(--line);
}
.tb-qbal-item {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
  padding: 8px; font-size: 0.75rem; color: var(--text);
  border-right: 1px solid var(--line);
}
.tb-qbal-item:last-child { border-right: none; }
.tb-qbal-item strong { font-weight: 800; }

.tb-quest-body { flex: 1; overflow-y: auto; padding: 8px 12px; max-height: 320px; }
.tb-quest-cat-label {
  font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  color: var(--muted); margin: 8px 0 4px;
}
.tb-quest-row {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 8px; border-radius: 8px; margin-bottom: 3px;
  border: 1px solid var(--line); background: var(--surface);
}
.tb-quest-row.is-done { background: #f0fdf4; border-color: #bbf7d0; opacity: 0.8; }

.tb-quest-ico {
  width: 26px; height: 26px; border-radius: 7px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.qcat-daily { background: #dbeafe; color: #2563eb; }
.qcat-milestone { background: #fef3c7; color: #d97706; }
.qcat-learning { background: #d1fae5; color: #059669; }
.qcat-engagement { background: #ede9fe; color: #7c3aed; }

.tb-quest-info { flex: 1; min-width: 0; }
.tb-quest-name { margin: 0; font-size: 0.76rem; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.tb-qprog { display: flex; align-items: center; gap: 4px; margin-top: 3px; }
.tb-qprog-track { flex: 1; height: 3px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.tb-qprog-fill { height: 100%; background: #f59e0b; }
.tb-qprog span { font-size: 0.6rem; color: var(--muted); white-space: nowrap; }

.tb-quest-pts {
  font-size: 0.72rem; font-weight: 800; color: #f59e0b;
  background: #fffbeb; padding: 1px 6px; border-radius: 999px; border: 1px solid #fde68a;
  flex-shrink: 0;
}
.tb-quest-check { font-size: 0.7rem; font-weight: 800; color: #16a34a; flex-shrink: 0; }

.tb-quest-foot {
  padding: 10px 12px;
  border-top: 1px solid var(--line);
}
.tb-quest-shop-link {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  font-size: 0.8rem; font-weight: 700;
  padding: 8px; border-radius: 8px;
  background: linear-gradient(135deg, #0F6E8C, #1D9E75);
  color: #fff; text-decoration: none;
  transition: opacity 150ms;
}
.tb-quest-shop-link:hover { opacity: 0.9; }

/* ── Responsive ── */
@media (max-width: 1080px) { .tb-search { max-width: none; } }
@media (max-width: 640px) {
  .tb { padding: 0 12px; gap: 6px; }
  .tb-user-info { display: none; }
  .tb-search-kbd { display: none; }
  .tb-notif-panel { width: calc(100vw - 24px); right: -12px; }
  .tb-quest-panel { width: calc(100vw - 24px); right: -12px; }
}
</style>
