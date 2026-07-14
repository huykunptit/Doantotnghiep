<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LogOut, X } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import AdminTopbar from '~/components/dashboard/AdminTopbar.vue'
import AdminFooter from '~/components/dashboard/AdminFooter.vue'
import AppToast from '~/components/AppToast.vue'

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
      { to: '/student', label: 'Dashboard', icon: 'layout-dashboard' },
      { to: '/student/calendar', label: 'Lịch học', icon: 'calendar' },
    ],
  },
  {
    label: 'Học tập',
    items: [
      { to: '/student/courses', label: 'Khóa học của tôi', icon: 'book-open' },
      { to: '/student/learning-path', label: 'Lộ trình học', icon: 'map' },
      { to: '/student/exams', label: 'Kỳ thi', icon: 'clipboard-list' },
      { to: '/student/tasks', label: 'Nhiệm vụ', icon: 'check-square' },
    ],
  },
  {
    label: 'Học vụ',
    items: [
      { to: '/student/transcript', label: 'Bảng điểm / GPA', icon: 'graduation-cap' },
      { to: '/student/certificates', label: 'Chứng chỉ', icon: 'award' },
      { to: '/student/tuition', label: 'Học phí', icon: 'credit-card' },
      { to: '/student/achievements', label: 'Thành tích', icon: 'trophy' },
    ],
  },
  {
    label: 'Cộng đồng',
    items: [
      { to: '/student/forum', label: 'Diễn đàn', icon: 'message-circle' },
      { to: '/student/surveys', label: 'Khảo sát', icon: 'file-text' },
      { to: '/student/helpdesk', label: 'Helpdesk', icon: 'headphones' },
    ],
  },
  {
    label: 'Khám phá',
    items: [
      { to: '/student/recommendations', label: 'Gợi ý khóa học', icon: 'sparkles' },
      { to: '/student/library', label: 'Thư viện tài liệu', icon: 'library' },
    ],
  },
  {
    label: 'Kênh hỗ trợ',
    items: [
      { to: '/student/notifications', label: 'Thông báo', icon: 'bell' },
      { to: '/student/ai-chat', label: 'Chat với AI', icon: 'bot' },
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

const isChatPage = computed(() => route.path === '/student/ai-chat')
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
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22C12 22 22 12 22 2" /><path d="M12 12C12 8 16 4 22 2" /><path d="M12 12C8 12 4 16 2 22" />
            </svg>
          </div>
          <div class="sv-brand-text">
            <span class="sv-brand-name">{{ siteName }}</span>
            <span class="sv-brand-role">Học viên</span>
          </div>
        </NuxtLink>
        <button type="button" class="sv-close-btn" aria-label="Đóng menu" @click="sidebarOpen = false">
          <X :size="18" :stroke-width="2" />
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
            <SylvaIcon :name="item.icon" :size="16" :stroke-width="1.75" class="sv-nav-icon" />
            <span>{{ item.label }}</span>
          </NuxtLink>
        </div>
      </nav>

      <!-- User footer -->
      <div class="sv-sidebar-foot">
        <div class="sv-user-chip">
          <div class="sv-avatar">
            <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
            <span v-else>{{ userInitials }}</span>
          </div>
          <div class="sv-user-info">
            <span class="sv-user-name">{{ user?.name || 'Học viên' }}</span>
            <span class="sv-user-role">Student</span>
          </div>
        </div>
        <button type="button" class="sv-logout-btn" aria-label="Đăng xuất" @click="logout">
          <LogOut :size="15" :stroke-width="2" />
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
        :search-placeholder="'Tìm khóa học, tài liệu...'"
        :user-name="user?.name || 'Học viên'"
        :user-avatar="user?.avatar"
        user-role="Student"
        dashboard-path="/student"
        settings-path="/student"
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
      />
      <div class="sv-content" :class="{ 'is-chat-page': isChatPage }">
        <slot />
      </div>
      <AdminFooter />
    </div>
  </div>
  <AppToast />
</template>

<style scoped>
.sv-shell {
  display: flex;
  min-height: 100vh;
  background: var(--bg, #EFF2F0);
}

/* ── Sidebar ── */
.sv-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: 240px;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #064e3b 0%, #052e22 100%);
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
  padding: 20px 16px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
}
.sv-brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  min-width: 0;
}
.sv-logo { height: 28px; width: auto; object-fit: contain; }
.sv-logo-mark {
  width: 28px; height: 28px;
  border-radius: 8px;
  background: linear-gradient(135deg, var(--green, #0f6e8c), var(--green-mid, #1d9e75));
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  flex-shrink: 0;
}
.sv-brand-text { display: flex; flex-direction: column; min-width: 0; }
.sv-brand-name {
  font-size: 0.88rem; font-weight: 700;
  color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sv-brand-role {
  font-size: 0.68rem; font-weight: 600;
  color: #5DCAA5; text-transform: uppercase; letter-spacing: 0.08em;
}
.sv-close-btn {
  display: none;
  align-items: center; justify-content: center;
  width: 28px; height: 28px;
  border-radius: 8px; border: none;
  background: transparent; color: rgba(255, 255, 255, 0.5); cursor: pointer;
}
.sv-close-btn:hover { background: rgba(255,255,255,0.05); color: #ffffff; }

/* ── Nav ── */
.sv-nav {
  flex: 1;
  overflow-y: auto;
  padding: 12px 10px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.08) transparent;
}
.sv-nav::-webkit-scrollbar { width: 4px; }
.sv-nav::-webkit-scrollbar-track { background: transparent; }
.sv-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.08); border-radius: 4px; }
.sv-nav-group { margin-bottom: 20px; }
.sv-nav-label {
  font-size: 0.82rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em;
  color: #ffffff;
 padding: 0 8px;
  margin: 0 0 6px;
}
.sv-nav-item {
  display: flex; align-items: center; gap: 9px;
  padding: 8px 10px;
  border-radius: 10px;
  font-size: 0.84rem; font-weight: 600;
  color: rgba(240, 250, 247, 0.65);
  text-decoration: none;
  transition: background 150ms, color 150ms;
  margin-bottom: 2px;
}
.sv-nav-item:hover { background: rgba(255, 255, 255, 0.04); color: #ffffff; }
.sv-nav-item:hover .sv-nav-icon { color: #5DCAA5; opacity: 1; }
.sv-nav-item.is-active {
  background: linear-gradient(135deg, #0F6E8C 0%, #0a4f64 100%);
  color: #ffffff;
  border-left: 3px solid #5DCAA5;
  padding-left: 7px;
}
.sv-nav-icon { flex-shrink: 0; opacity: 0.75; color: inherit; }
.sv-nav-item.is-active .sv-nav-icon { opacity: 1; color: #5DCAA5; }

/* ── Sidebar footer ── */
.sv-sidebar-foot {
  padding: 12px 14px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  display: flex; align-items: center; gap: 10px;
  flex-shrink: 0;
  background: rgba(0, 0, 0, 0.15);
}
.sv-user-chip {
  display: flex; align-items: center; gap: 9px;
  flex: 1; min-width: 0;
}
.sv-avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--green, #0F6E8C), var(--green-mid, #1D9E75));
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem; font-weight: 700; color: #fff;
  flex-shrink: 0; overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.15);
}
.sv-user-info { display: flex; flex-direction: column; min-width: 0; }
.sv-user-name {
  font-size: 0.8rem; font-weight: 700;
  color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sv-user-role { font-size: 0.68rem; color: rgba(240, 250, 247, 0.45); }
.sv-logout-btn {
  display: flex; align-items: center; justify-content: center;
  width: 30px; height: 30px;
  border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1);
  background: transparent; color: rgba(240, 250, 247, 0.5); cursor: pointer;
  transition: all 150ms; flex-shrink: 0;
}
.sv-logout-btn:hover { background: rgba(239, 68, 68, 0.15); color: #F87171; border-color: rgba(239, 68, 68, 0.2); }

/* ── Overlay ── */
.sv-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 199;
  background: rgba(0,0,0,0.4);
}

/* ── Main ── */
.sv-main {
  flex: 1;
  margin-left: 240px;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: var(--surface-strong, #fff);
}
.sv-content {
  flex: 1;
  padding: 28px 32px 40px;
  background: #ffffff !important;
}
.sv-content.is-chat-page {
  padding: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
[data-theme="dark"] .sv-content {
  background: var(--surface, #161920) !important;
}

/* ── Dark mode ── */
[data-theme="dark"] .sv-main { background: var(--surface, #161920); }
[data-theme="dark"] .sv-sidebar {
  background: linear-gradient(180deg, #031c15 0%, #03130e 100%);
  border-color: rgba(255, 255, 255, 0.04);
}
[data-theme="dark"] .sv-brand { border-color: rgba(255, 255, 255, 0.04); }
[data-theme="dark"] .sv-nav-item:hover { background: rgba(255, 255, 255, 0.04); }
[data-theme="dark"] .sv-nav-item.is-active { background: linear-gradient(135deg, #0F6E8C 0%, #0a4f64 100%); color: #ffffff; border-left-color: #5DCAA5; }
[data-theme="dark"] .sv-sidebar-foot { border-color: rgba(255, 255, 255, 0.04); }
[data-theme="dark"] .sv-logout-btn { border-color: rgba(255, 255, 255, 0.08); }

/* ── Responsive ── */
@media (max-width: 768px) {
  .sv-sidebar {
    transform: translateX(-100%);
  }
  .sv-sidebar.is-open {
    transform: translateX(0);
    box-shadow: 4px 0 24px rgba(0,0,0,0.15);
  }
  .sv-close-btn { display: flex; }
  .sv-overlay { display: block; }
  .sv-main { margin-left: 0; }
  .sv-content { padding: 20px 16px 32px; }
}

/* Fade transition */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
