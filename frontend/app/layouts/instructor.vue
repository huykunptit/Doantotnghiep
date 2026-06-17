<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LogOut, Menu, X } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const { siteName, siteLogo } = useSiteSettings()

if (!auth.isReady) auth.initFromStorage()
if (auth.token && !auth.user) await auth.fetchMe()
if (!auth.isLoggedIn || !auth.user) await navigateTo('/login', { replace: true })

const user = computed(() => auth.user)

const searchPlaceholder = computed(() => {
  if (route.path.includes('/curriculum')) return 'Tìm chương, bài học...'
  if (route.path.includes('/question-bank')) return 'Tìm câu hỏi...'
  return 'Tìm khóa học, học viên...'
})

watch(() => route.fullPath, () => { sidebarOpen.value = false })

const navGroups = [
  {
    label: 'Tổng quan',
    items: [{ to: '/instructor', label: 'Bảng điều khiển', icon: 'layout-dashboard' }],
  },
  {
    label: 'Khóa học',
    items: [
      { to: '/instructor/courses', label: 'Khóa học của tôi', icon: 'book-open' },
      { to: '/courses/create', label: 'Tạo khóa học mới', icon: 'plus-circle' },
    ],
  },
  {
    label: 'Khảo thí',
    items: [
      { to: '/instructor/question-bank', label: 'Ngân hàng câu hỏi', icon: 'database' },
      { to: '/instructor/exams', label: 'Đợt thi', icon: 'clipboard-list' },
    ],
  },
  {
    label: 'Học vụ',
    items: [
      { to: '/instructor/sections', label: 'Lớp học phần & điểm', icon: 'graduation-cap' },
    ],
  },
  {
    label: 'Kinh doanh',
    items: [
      { to: '/instructor/students', label: 'Học viên', icon: 'users' },
      { to: '/instructor/revenue', label: 'Doanh thu', icon: 'trending-up' },
    ],
  },
]

function isActive(path: string) {
  return path === '/instructor' ? route.path === '/instructor' : route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}

const userInitials = computed(() => {
  const name = user.value?.name || 'GV'
  return name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
})
</script>

<template>
  <div class="ins-shell">
    <!-- Sidebar -->
    <aside class="ins-sidebar" :class="{ 'is-open': sidebarOpen }" aria-label="Instructor navigation">
      <!-- Brand -->
      <div class="ins-brand">
        <NuxtLink to="/instructor" class="ins-brand-link">
          <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="ins-logo">
          <div v-else class="ins-logo-mark">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22C12 22 22 12 22 2" /><path d="M12 12C12 8 16 4 22 2" /><path d="M12 12C8 12 4 16 2 22" />
            </svg>
          </div>
          <div class="ins-brand-text">
            <span class="ins-brand-name">{{ siteName }}</span>
            <span class="ins-brand-role">Giảng viên</span>
          </div>
        </NuxtLink>
        <button type="button" class="ins-close-btn" aria-label="Đóng menu" @click="sidebarOpen = false">
          <X :size="18" :stroke-width="2" />
        </button>
      </div>

      <!-- Nav -->
      <nav class="ins-nav" aria-label="Điều hướng chính">
        <div v-for="group in navGroups" :key="group.label" class="ins-nav-group">
          <p class="ins-nav-label">{{ group.label }}</p>
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="ins-nav-item"
            :class="{ 'is-active': isActive(item.to) }"
          >
            <SylvaIcon :name="item.icon" :size="16" :stroke-width="1.75" class="ins-nav-icon" />
            <span>{{ item.label }}</span>
          </NuxtLink>
        </div>
      </nav>

      <!-- Footer -->
      <div class="ins-sidebar-foot">
        <div class="ins-user-chip">
          <div class="ins-avatar">{{ userInitials }}</div>
          <div class="ins-user-info">
            <span class="ins-user-name">{{ user?.name || 'Giảng viên' }}</span>
            <span class="ins-user-role">Instructor</span>
          </div>
        </div>
        <button type="button" class="ins-logout-btn" aria-label="Đăng xuất" @click="logout">
          <LogOut :size="15" :stroke-width="2" />
        </button>
      </div>
    </aside>

    <!-- Overlay -->
    <Transition name="fade">
      <div v-if="sidebarOpen" class="ins-overlay" aria-hidden="true" @click="sidebarOpen = false" />
    </Transition>

    <!-- Main -->
    <div class="ins-main">
      <AdminTopbar
        :search-placeholder="searchPlaceholder"
        :user-name="user?.name || 'Giảng viên'"
        :user-avatar="user?.avatar"
        user-role="Instructor"
        dashboard-path="/instructor"
        settings-path="/instructor"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />

      <div class="ins-content">
        <slot />
      </div>

      <AdminFooter />
    </div>
  </div>
</template>

<style scoped>
/* ── Shell ── */
.ins-shell {
  display: flex;
  min-height: 100vh;
  background: var(--bg);
}

/* ── Sidebar ── */
.ins-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: 240px;
  display: flex;
  flex-direction: column;
  background: var(--surface-strong, #fff);
  border-right: 1px solid var(--line);
  z-index: 200;
  overflow: hidden;
  transition: transform 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Brand ── */
.ins-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  height: 64px;
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
}

.ins-brand-link {
  display: flex; align-items: center; gap: 10px;
  text-decoration: none; flex: 1; min-width: 0;
}

.ins-logo { height: 28px; width: auto; flex-shrink: 0; }

.ins-logo-mark {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 7px;
  background: var(--green); color: #fff; flex-shrink: 0;
}

.ins-brand-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }

.ins-brand-name {
  font-family: 'Outfit', sans-serif;
  font-size: 0.875rem; font-weight: 700;
  letter-spacing: -0.02em; color: var(--text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.ins-brand-role {
  font-size: 0.6875rem; font-weight: 600;
  color: var(--green); text-transform: uppercase; letter-spacing: 0.08em;
}

.ins-close-btn {
  display: none; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: var(--muted); cursor: pointer; border-radius: 6px;
  transition: color 150ms, background 150ms;
}
.ins-close-btn:hover { color: var(--text); background: var(--surface); }

/* ── Nav ── */
.ins-nav {
  flex: 1; overflow-y: auto; padding: 12px 10px;
  display: flex; flex-direction: column; gap: 4px;
}

.ins-nav::-webkit-scrollbar { width: 4px; }
.ins-nav::-webkit-scrollbar-track { background: transparent; }
.ins-nav::-webkit-scrollbar-thumb { background: var(--line); border-radius: 4px; }

.ins-nav-group { display: flex; flex-direction: column; gap: 2px; margin-bottom: 12px; }

.ins-nav-label {
  padding: 0 8px 4px;
  font-size: 0.6875rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: var(--muted);
}

.ins-nav-item {
  position: relative;
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 7px;
  text-decoration: none; font-size: 0.84rem; font-weight: 500;
  color: var(--muted); transition: background 150ms, color 150ms;
}

.ins-nav-item:hover { background: var(--surface); color: var(--text); }

.ins-nav-item.is-active {
  background: var(--green-soft); color: var(--green-deep); font-weight: 600;
}

.ins-nav-item.is-active::before {
  content: '';
  position: absolute; left: 0; top: 20%; bottom: 20%;
  width: 3px; border-radius: 0 3px 3px 0;
  background: var(--green);
}

.ins-nav-icon { flex-shrink: 0; }

/* ── Sidebar footer ── */
.ins-sidebar-foot {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-top: 1px solid var(--line);
  flex-shrink: 0;
}

.ins-user-chip { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }

.ins-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--green-soft); color: var(--green-deep);
  font-size: 0.72rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.ins-user-info { display: flex; flex-direction: column; gap: 1px; min-width: 0; }

.ins-user-name {
  font-size: 0.8125rem; font-weight: 600; color: var(--text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.ins-user-role { font-size: 0.6875rem; color: var(--muted); }

.ins-logout-btn {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: var(--muted); cursor: pointer; border-radius: 6px;
  flex-shrink: 0; transition: color 150ms, background 150ms;
}
.ins-logout-btn:hover { color: var(--danger); background: var(--danger-soft); }

/* ── Main ── */
.ins-main {
  margin-left: 240px;
  display: flex; flex-direction: column;
  min-height: 100vh; flex: 1; min-width: 0;
}

.ins-content { padding: 24px; flex: 1; }

/* ── Overlay ── */
.ins-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(10, 26, 20, 0.5);
  z-index: 199;
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 1080px) {
  .ins-sidebar { transform: translateX(-100%); }
  .ins-sidebar.is-open { transform: translateX(0); }
  .ins-close-btn { display: flex; }
  .ins-main { margin-left: 0; }
  .ins-overlay { display: block; }
}

/* ── Dark mode ── */
[data-theme="dark"] .ins-sidebar { background: #0F2219; border-color: rgba(255,255,255,0.07); }
[data-theme="dark"] .ins-brand { border-color: rgba(255,255,255,0.07); }
[data-theme="dark"] .ins-sidebar-foot { border-color: rgba(255,255,255,0.07); }
</style>
