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

watch(() => route.fullPath, () => { sidebarOpen.value = false })

const navGroups = [
  {
    label: 'Tổng quan',
    items: [
      { to: '/student', label: 'Dashboard', piIcon: 'th-large' },
      { to: '/student/calendar', label: 'Lịch học', piIcon: 'calendar' },
    ],
  },
  {
    label: 'Học tập',
    items: [
      { to: '/student/courses', label: 'Khóa học của tôi', piIcon: 'book' },
      { to: '/student/learning-path', label: 'Lộ trình học', piIcon: 'map' },
      { to: '/student/exams', label: 'Kỳ thi', piIcon: 'clipboard' },
      { to: '/student/tasks', label: 'Nhiệm vụ', piIcon: 'check-square' },
    ],
  },
  {
    label: 'Học vụ',
    items: [
      { to: '/student/transcript', label: 'Bảng điểm / GPA', piIcon: 'graduation-cap' },
      { to: '/student/certificates', label: 'Chứng chỉ', piIcon: 'verified' },
      { to: '/student/tuition', label: 'Học phí', piIcon: 'credit-card' },
      { to: '/student/achievements', label: 'Thành tích', piIcon: 'trophy' },
      { to: '/student/points', label: 'Điểm & Phần thưởng', piIcon: 'star' },
    ],
  },
  {
    label: 'Cộng đồng',
    items: [
      { to: '/student/forum', label: 'Diễn đàn', piIcon: 'comments' },
      { to: '/student/surveys', label: 'Khảo sát', piIcon: 'file-edit' },
      { to: '/student/helpdesk', label: 'Helpdesk', piIcon: 'headphones' },
    ],
  },
  {
    label: 'Khám phá',
    items: [
      { to: '/student/recommendations', label: 'Gợi ý khóa học', piIcon: 'sparkles' },
      { to: '/student/library', label: 'Thư viện tài liệu', piIcon: 'book-open' },
    ],
  },
  {
    label: 'Kênh hỗ trợ',
    items: [
      { to: '/student/notifications', label: 'Thông báo', piIcon: 'bell' },
      { to: '/student/ai-chat', label: 'Chat với AI', piIcon: 'comment' },
    ],
  },
]

function isActive(path: string) {
  return path === '/student' ? route.path === '/student' : route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}

const userInitials = computed(() => {
  const name = user.value?.name || 'SV'
  return name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
})
</script>

<template>
  <div class="sv-shell">
    <!-- Sidebar -->
    <aside class="sv-sidebar" :class="{ 'is-open': sidebarOpen }" aria-label="Student navigation">
      <!-- Brand -->
      <div class="sv-brand">
        <NuxtLink to="/student" class="sv-brand-link">
          <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="sv-logo">
          <div v-else class="sv-logo-mark">
            <i class="pi pi-book" style="font-size:1rem" />
          </div>
          <div class="sv-brand-text">
            <span class="sv-brand-name">{{ siteName }}</span>
            <span class="sv-brand-role">Học viên</span>
          </div>
        </NuxtLink>
        <button type="button" class="sv-close-btn" aria-label="Đóng menu" @click="sidebarOpen = false">
          <i class="pi pi-times" style="font-size:0.875rem" />
        </button>
      </div>

      <!-- Nav -->
      <nav class="sv-nav" aria-label="Điều hướng học viên">
        <div v-for="group in navGroups" :key="group.label" class="sv-nav-group">
          <p class="sv-nav-label">{{ group.label }}</p>
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="sv-nav-item"
            :class="{ 'is-active': isActive(item.to) }"
          >
            <i :class="`pi pi-${item.piIcon}`" class="sv-nav-icon" />
            <span>{{ item.label }}</span>
          </NuxtLink>
        </div>
      </nav>

      <!-- User footer -->
      <div class="sv-sidebar-foot">
        <div class="sv-user-chip">
          <div class="sv-avatar">
            <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" class="sv-avatar-img">
            <span v-else>{{ userInitials }}</span>
          </div>
          <div class="sv-user-info">
            <span class="sv-user-name">{{ user?.name || 'Học viên' }}</span>
            <span class="sv-user-role">Student</span>
          </div>
        </div>
        <button type="button" class="sv-logout-btn" aria-label="Đăng xuất" @click="logout">
          <i class="pi pi-sign-out" style="font-size:0.875rem" />
        </button>
      </div>
    </aside>

    <!-- Mobile overlay -->
    <Transition name="fade">
      <div v-if="sidebarOpen" class="sv-overlay" aria-hidden="true" @click="sidebarOpen = false" />
    </Transition>

    <!-- Main -->
    <div class="sv-main">
      <AdminTopbar
        search-placeholder="Tìm khóa học, tài liệu..."
        :user-name="user?.name || 'Học viên'"
        :user-avatar="user?.avatar"
        user-role="Student"
        dashboard-path="/student"
        settings-path="/student"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />
      <div class="sv-content">
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
.sv-shell {
  display: flex;
  min-height: 100vh;
  background-color: var(--page-bg, #f6f6f6);
}

/* ── Sidebar ── */
.sv-sidebar {
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
.sv-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  height: 64px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
}
.sv-brand-link {
  display: flex; align-items: center; gap: 10px;
  text-decoration: none; flex: 1; min-width: 0;
}
.sv-logo { height: 28px; width: auto; object-fit: contain; flex-shrink: 0; }
.sv-logo-mark {
  width: 28px; height: 28px; border-radius: 8px;
  background: var(--theme-primary, #0ea5e9);
  display: flex; align-items: center; justify-content: center;
  color: #fff; flex-shrink: 0;
}
.sv-brand-text { display: flex; flex-direction: column; min-width: 0; }
.sv-brand-name {
  font-size: 0.875rem; font-weight: 700;
  color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sv-brand-role {
  font-size: 0.6875rem; font-weight: 600;
  color: var(--theme-primary-light, #bae6fd);
  text-transform: uppercase; letter-spacing: 0.08em;
}
.sv-close-btn {
  display: none; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: none; background: transparent;
  color: rgba(255, 255, 255, 0.5); cursor: pointer; border-radius: 6px;
}
.sv-close-btn:hover { background: rgba(255,255,255,0.05); color: #ffffff; }

/* ── Nav ── */
.sv-nav {
  flex: 1; overflow-y: auto; padding: 12px 10px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}
.sv-nav::-webkit-scrollbar { width: 4px; }
.sv-nav::-webkit-scrollbar-track { background: transparent; }
.sv-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.08); border-radius: 4px; }
.sv-nav-group { margin-bottom: 20px; }
.sv-nav-label {
  font-size: 0.6875rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.35);
  padding: 0 8px; margin: 0 0 6px;
}
.sv-nav-item {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 10px; border-radius: 7px;
  font-size: 0.84rem; font-weight: 500;
  color: rgba(255, 255, 255, 0.6);
  text-decoration: none; transition: background 150ms, color 150ms;
  margin-bottom: 2px;
}
.sv-nav-item:hover { background: rgba(255, 255, 255, 0.05); color: #ffffff; }
.sv-nav-item.is-active {
  background: rgba(var(--theme-primary-rgb, 14, 165, 233), 0.18);
  color: #ffffff; font-weight: 600;
  border-left: 3px solid var(--theme-primary, #0ea5e9);
  padding-left: 7px;
}
.sv-nav-icon { flex-shrink: 0; font-size: 0.875rem; color: inherit; opacity: 0.7; }
.sv-nav-item.is-active .sv-nav-icon { opacity: 1; color: var(--theme-primary-light, #bae6fd); }

/* ── Sidebar footer ── */
.sv-sidebar-foot {
  padding: 12px 14px; border-top: 1px solid rgba(255, 255, 255, 0.06);
  display: flex; align-items: center; gap: 10px;
  flex-shrink: 0; background: rgba(0, 0, 0, 0.2);
}
.sv-user-chip { display: flex; align-items: center; gap: 9px; flex: 1; min-width: 0; }
.sv-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--theme-primary, #0ea5e9);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem; font-weight: 700; color: #fff;
  flex-shrink: 0; overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.15);
}
.sv-avatar-img { width: 100%; height: 100%; object-fit: cover; }
.sv-user-info { display: flex; flex-direction: column; min-width: 0; }
.sv-user-name {
  font-size: 0.8125rem; font-weight: 600; color: #ffffff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sv-user-role { font-size: 0.6875rem; color: rgba(255, 255, 255, 0.4); }
.sv-logout-btn {
  display: flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  background: transparent; color: rgba(255, 255, 255, 0.5); cursor: pointer;
  transition: all 150ms; flex-shrink: 0;
}
.sv-logout-btn:hover { background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.2); }

/* ── Overlay ── */
.sv-overlay {
  display: none; position: fixed; inset: 0; z-index: 199;
  background: rgba(0, 0, 0, 0.45);
}

/* ── Main ── */
.sv-main {
  flex: 1; margin-left: 240px;
  display: flex; flex-direction: column; min-width: 0;
  background: #ffffff;
}
.sv-content { flex: 1; padding: 24px 28px 40px; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .sv-sidebar { transform: translateX(-100%); }
  .sv-sidebar.is-open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,0.2); }
  .sv-close-btn { display: flex; }
  .sv-overlay { display: block; }
  .sv-main { margin-left: 0; }
  .sv-content { padding: 16px 16px 32px; }
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Dark mode ── */
:global(.dark) .sv-main { background: #1a1a1e; }
:global(.dark) .sv-shell { background-color: #111113; }
:global(.dark) .sv-sidebar {
  background: linear-gradient(180deg, #0d1b2e 0%, #060e1a 100%);
  border-color: rgba(255, 255, 255, 0.04);
}
</style>
