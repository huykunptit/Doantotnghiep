<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'
import AppToast from '~/components/AppToast.vue'
import PointsQuestModal from '~/components/PointsQuestModal.vue'

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
    items: [{ to: '/instructor', label: 'Bảng điều khiển', piIcon: 'th-large' }],
  },
  {
    label: 'Khóa học',
    items: [
      { to: '/instructor/courses', label: 'Khóa học của tôi', piIcon: 'book' },
      { to: '/courses/create', label: 'Tạo khóa học mới', piIcon: 'plus-circle' },
    ],
  },
  {
    label: 'Khảo thí',
    items: [
      { to: '/instructor/question-bank', label: 'Ngân hàng câu hỏi', piIcon: 'database' },
      { to: '/instructor/exams', label: 'Đợt thi', piIcon: 'clipboard' },
    ],
  },
  {
    label: 'Học vụ',
    items: [
      { to: '/instructor/sections', label: 'Lớp học phần & điểm', piIcon: 'graduation-cap' },
    ],
  },
  {
    label: 'Quản trị L&D',
    items: [
      { to: '/admin/lnd/classes', label: 'Quản lý lớp học', piIcon: 'graduation-cap' },
      { to: '/admin/lnd/learning-paths', label: 'Quản lý lộ trình', piIcon: 'sitemap' },
      { to: '/admin/lnd/class-path-enrollment', label: 'Ghi danh lớp/lộ trình', piIcon: 'user-check' },
      { to: '/admin/lnd/class-path-enrollment?tab=enrollment-list', label: 'Danh sách ghi danh', piIcon: 'list' },
      { to: '/admin/lnd/class-path-enrollment?tab=exam-registrations', label: 'Danh sách đăng ký thi', piIcon: 'file' },
      { to: '/admin/lnd/file-based-enrollment', label: 'Ghi danh bằng tệp', piIcon: 'file-excel' },
      { to: '/admin/lnd/reports', label: 'Báo cáo tiến độ L&D', piIcon: 'chart-line' },
    ],
  },
  {
    label: 'Kinh doanh',
    items: [
      { to: '/instructor/students', label: 'Học viên', piIcon: 'users' },
      { to: '/instructor/revenue', label: 'Doanh thu', piIcon: 'chart-line' },
    ],
  },
]

function isActive(to: string) {
  const [toPath, toQueryStr] = to.split('?')
  
  if (toPath === '/instructor') {
    return route.path === '/instructor'
  }
  
  const pathMatches = route.path === toPath || route.path.startsWith(toPath + '/')
  
  if (!pathMatches) return false
  
  if (toQueryStr) {
    const params = new URLSearchParams(toQueryStr)
    for (const [key, val] of params.entries()) {
      if (route.query[key] !== val) return false
    }
    return true
  }
  
  if (route.path === toPath && Object.keys(route.query).length > 0) {
    return false
  }
  
  return true
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
            <i class="pi pi-graduation-cap" style="font-size:1rem" />
          </div>
          <div class="ins-brand-text">
            <span class="ins-brand-name">{{ siteName }}</span>
            <span class="ins-brand-role">Giảng viên</span>
          </div>
        </NuxtLink>
        <button type="button" class="ins-close-btn" aria-label="Đóng menu" @click="sidebarOpen = false">
          <i class="pi pi-times" style="font-size:0.875rem" />
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
            <i :class="`pi pi-${item.piIcon}`" class="ins-nav-icon" />
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
          <i class="pi pi-sign-out" style="font-size:0.875rem" />
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
  <AppToast />
  <PointsQuestModal />
</template>

<style scoped>
/* ── Shell ── */
.ins-shell {
  display: flex;
  min-height: 100vh;
  background-color: var(--page-bg, #f6f6f6);
}

/* ── Sidebar ── */
.ins-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: 240px;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
  border-right: 1px solid rgba(255, 255, 255, 0.05);
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
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
}
.ins-brand-link {
  display: flex; align-items: center; gap: 10px;
  text-decoration: none; flex: 1; min-width: 0;
}
.ins-logo { height: 28px; width: auto; flex-shrink: 0; object-fit: contain; }
.ins-logo-mark {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 7px;
  background: var(--theme-primary, #0ea5e9);
  color: #fff; flex-shrink: 0;
}
.ins-brand-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.ins-brand-name {
  font-size: 0.875rem; font-weight: 700;
  letter-spacing: -0.02em; color: #ffffff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ins-brand-role {
  font-size: 0.6875rem; font-weight: 600;
  color: var(--theme-primary-light, #bae6fd);
  text-transform: uppercase; letter-spacing: 0.08em;
}
.ins-close-btn {
  display: none; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: rgba(255, 255, 255, 0.5); cursor: pointer; border-radius: 6px;
  transition: color 150ms, background 150ms;
}
.ins-close-btn:hover { color: #ffffff; background: rgba(255, 255, 255, 0.05); }

/* ── Nav ── */
.ins-nav {
  flex: 1; overflow-y: auto; padding: 12px 10px;
  display: flex; flex-direction: column;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}
.ins-nav::-webkit-scrollbar { width: 4px; }
.ins-nav::-webkit-scrollbar-track { background: transparent; }
.ins-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.08); border-radius: 4px; }

.ins-nav-group { display: flex; flex-direction: column; gap: 2px; margin-bottom: 16px; }
.ins-nav-label {
  padding: 0 8px 4px;
  font-size: 0.6875rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.35);
}
.ins-nav-item {
  position: relative;
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: 7px;
  text-decoration: none; font-size: 0.84rem; font-weight: 500;
  color: rgba(255, 255, 255, 0.6);
  transition: background 150ms, color 150ms;
  margin-bottom: 2px;
}
.ins-nav-item:hover { background: rgba(255, 255, 255, 0.05); color: #ffffff; }
.ins-nav-item.is-active {
  background: rgba(var(--theme-primary-rgb, 14, 165, 233), 0.18);
  color: #ffffff; font-weight: 600;
  border-left: 3px solid var(--theme-primary, #0ea5e9);
  padding-left: 7px;
}
.ins-nav-icon { flex-shrink: 0; font-size: 0.875rem; color: inherit; opacity: 0.7; }
.ins-nav-item.is-active .ins-nav-icon { opacity: 1; color: var(--theme-primary-light, #bae6fd); }

/* ── Sidebar footer ── */
.ins-sidebar-foot {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-top: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0; background: rgba(0, 0, 0, 0.2);
}
.ins-user-chip { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.ins-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--theme-primary, #0ea5e9);
  color: #ffffff; font-size: 0.72rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; border: 1px solid rgba(255, 255, 255, 0.15);
}
.ins-user-info { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.ins-user-name {
  font-size: 0.8125rem; font-weight: 600; color: #ffffff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ins-user-role { font-size: 0.6875rem; color: rgba(255, 255, 255, 0.4); }
.ins-logout-btn {
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: rgba(255, 255, 255, 0.45); cursor: pointer; border-radius: 6px;
  flex-shrink: 0; transition: all 150ms;
}
.ins-logout-btn:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.15); }

/* ── Main ── */
.ins-main {
  margin-left: 240px;
  display: flex; flex-direction: column;
  min-height: 100vh; flex: 1; min-width: 0;
  background: #ffffff;
}
.ins-content { flex: 1; padding: 24px; min-width: 0; }

/* ── Overlay ── */
.ins-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 199;
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 1080px) {
  .ins-sidebar { transform: translateX(-100%); }
  .ins-sidebar.is-open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,0.3); }
  .ins-close-btn { display: flex; }
  .ins-main { margin-left: 0; }
  .ins-overlay { display: block; }
}
@media (max-width: 640px) {
  .ins-content { padding: 16px; }
}

/* ── Dark mode (.dark class) ── */
:global(.dark) .ins-main { background: #18181b; }
:global(.dark) .ins-sidebar { background: linear-gradient(180deg, #111113 0%, #09090b 100%); }
:global(.dark) .ins-brand { border-color: rgba(255, 255, 255, 0.04); }
:global(.dark) .ins-sidebar-foot { border-color: rgba(255, 255, 255, 0.04); }
</style>
