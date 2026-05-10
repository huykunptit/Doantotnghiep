<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import NotificationBell from '~/components/NotificationBell.vue'
import { publicNavigation } from '~/constants/navigation'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { siteLogo, siteName, siteTagline } = useSiteSettings()

const showMenu = ref(false)
const showMobileMenu = ref(false)
const menuRef = ref<HTMLElement | null>(null)

const isAdmin = computed(() => auth.user?.roles?.includes('admin'))
const isInstructor = computed(() => auth.user?.roles?.includes('instructor') || isAdmin.value)

async function handleLogout() {
  showMenu.value = false
  showMobileMenu.value = false
  await auth.logout()
  router.push('/')
}

function handleClickOutside(e: Event) {
  if (menuRef.value && !menuRef.value.contains(e.target as Node)) showMenu.value = false
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
  <header class="cd-header">
    <div class="cd-header-inner">
      <NuxtLink to="/" class="cd-brand">
        <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="cd-brand-logo">
        <div v-else class="cd-brand-icon" />
        <div class="cd-brand-text">
          <p class="cd-brand-title">{{ siteName }}</p>
          <p class="cd-brand-slogan">{{ siteTagline || 'Học, học nữa, học mãi' }}</p>
        </div>
      </NuxtLink>

      <nav class="cd-nav">
        <NuxtLink
          v-for="item in publicNavigation"
          :key="item.to"
          :to="item.to"
          class="cd-nav-link"
          active-class="is-active"
        >
          {{ item.label }}
        </NuxtLink>
        <NuxtLink
          v-if="auth.isLoggedIn"
          to="/student"
          class="cd-nav-link"
          active-class="is-active"
        >
          Khóa học của tôi
        </NuxtLink>
      </nav>

      <div class="cd-actions">
        <ClientOnly>
          <template v-if="!auth.isLoggedIn">
            <NuxtLink to="/login" class="cd-nav-link cd-nav-login">Đăng nhập</NuxtLink>
            <NuxtLink to="/register" class="cd-btn-primary">Bắt đầu ngay</NuxtLink>
          </template>

          <template v-else>
            <NotificationBell />
            <div ref="menuRef" class="cd-user-menu">
              <button class="cd-user-btn" @click="showMenu = !showMenu">
                <div class="cd-user-avatar">
                  <img v-if="auth.user?.avatar" :src="auth.user.avatar" alt="Avatar">
                  <span v-else>{{ auth.user?.name?.charAt(0) }}</span>
                </div>
                <div class="cd-user-info">
                  <p class="cd-user-name">{{ auth.user?.name }}</p>
                  <p class="cd-user-role">PTIT Member</p>
                </div>
                <span class="material-symbols-outlined">expand_more</span>
              </button>

              <div v-if="showMenu" class="cd-dropdown">
                <div class="cd-dropdown-header">
                  <p class="cd-dropdown-name">{{ auth.user?.name }}</p>
                  <p class="cd-dropdown-email">{{ auth.user?.email }}</p>
                </div>
                <div class="cd-dropdown-body">
                  <NuxtLink to="/profile" class="cd-dropdown-item" @click="showMenu = false">
                    <span class="material-symbols-outlined">person</span> Hồ sơ cá nhân
                  </NuxtLink>
                  <NuxtLink to="/orders" class="cd-dropdown-item" @click="showMenu = false">
                    <span class="material-symbols-outlined">receipt_long</span> Đơn hàng
                  </NuxtLink>
                  <NuxtLink v-if="isInstructor" to="/instructor" class="cd-dropdown-item cd-dropdown-item--primary" @click="showMenu = false">
                    <span class="material-symbols-outlined">school</span> Khu vực giảng viên
                  </NuxtLink>
                  <NuxtLink v-if="isAdmin" to="/admin" class="cd-dropdown-item cd-dropdown-item--danger" @click="showMenu = false">
                    <span class="material-symbols-outlined">admin_panel_settings</span> Quản trị hệ thống
                  </NuxtLink>
                  <button class="cd-dropdown-item cd-dropdown-item--error" @click="handleLogout">
                    <span class="material-symbols-outlined">logout</span> Đăng xuất
                  </button>
                </div>
              </div>
            </div>
          </template>
        </ClientOnly>

        <button class="cd-mobile-toggle" @click="showMobileMenu = !showMobileMenu">
          <span class="material-symbols-outlined">{{ showMobileMenu ? 'close' : 'menu' }}</span>
        </button>
      </div>
    </div>

    <div v-if="showMobileMenu" class="cd-mobile-menu">
      <div class="cd-mobile-header">
        <p class="cd-mobile-title">{{ siteName }}</p>
        <p class="cd-mobile-desc">Truy cập nhanh khóa học, lộ trình nghề nghiệp và tài khoản của bạn trên mọi thiết bị.</p>
      </div>
      <nav class="cd-mobile-nav">
        <NuxtLink v-for="item in publicNavigation" :key="item.to" :to="item.to" class="cd-mobile-link" @click="showMobileMenu = false">
          {{ item.label }}
        </NuxtLink>
        <NuxtLink v-if="auth.isLoggedIn" to="/student" class="cd-mobile-link" @click="showMobileMenu = false">
          Khóa học của tôi
        </NuxtLink>
        <NuxtLink v-if="auth.isLoggedIn" to="/profile" class="cd-mobile-link" @click="showMobileMenu = false">
          Hồ sơ cá nhân
        </NuxtLink>
        <NuxtLink v-if="!auth.isLoggedIn" to="/login" class="cd-mobile-link cd-mobile-link--bordered" @click="showMobileMenu = false">
          Đăng nhập
        </NuxtLink>
        <NuxtLink v-if="!auth.isLoggedIn" to="/register" class="cd-mobile-link cd-mobile-link--primary" @click="showMobileMenu = false">
          Tạo tài khoản mới
        </NuxtLink>
        <button v-if="auth.isLoggedIn" class="cd-mobile-link cd-mobile-link--primary" @click="handleLogout">
          Đăng xuất
        </button>
      </nav>
    </div>
  </header>
</template>

<style scoped>
.cd-header {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 50;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(var(--green-rgb), 0.1);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.cd-header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 80px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
  gap: 1rem;
}

@media (min-width: 640px) {
  .cd-header-inner { padding: 0 1.5rem; }
}

/* Brand */
.cd-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  min-width: 0;
  transition: opacity 0.2s;
}
.cd-brand:hover { opacity: 0.9; }

.cd-brand-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px; height: 48px;
  border-radius: 16px;
  background: var(--green);
  color: #fff;
  font-size: 1.25rem;
  font-weight: 900;
  flex-shrink: 0;
  box-shadow: 0 8px 20px rgba(var(--green-rgb), 0.2);
}

.cd-brand-logo {
  max-width: 100%;
  height: 48px;
  border-radius: 16px;
  object-fit: cover;
  flex-shrink: 0;
  box-shadow: 0 8px 20px rgba(var(--green-rgb), 0.2);
}

.cd-brand-text { min-width: 0; }
.cd-brand-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: var(--on-surface, #0f172a);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cd-brand-slogan {
  margin: 0;
  font-size: 0.6875rem;
  text-transform: uppercase;
  letter-spacing: 0.24em;
  color: var(--outline, #64748b);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Navigation */
.cd-nav {
  display: none;
  align-items: center;
  gap: 8px;
  padding: 4px;
  background: rgba(248, 250, 252, 0.8);
  border: 1px solid rgba(var(--green-rgb), 0.1);
  border-radius: 999px;
}
@media (min-width: 768px) {
  .cd-nav { display: flex; }
}

.cd-nav-link {
  padding: 8px 16px;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--on-surface-variant, #475569);
  text-decoration: none;
  transition: all 0.2s;
}
.cd-nav-link:hover {
  background: rgba(var(--green-rgb), 0.1);
  color: var(--primary, var(--green));
}
.cd-nav-link.is-active {
  background: var(--green);
  color: #fff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.cd-nav-login { display: none; }
@media (min-width: 640px) { .cd-nav-login { display: block; } }

/* Actions */
.cd-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.cd-btn-primary {
  padding: 10px 20px;
  border-radius: 999px;
  background: var(--green);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 4px 12px rgba(var(--green-rgb), 0.2);
  transition: opacity 0.2s;
}
.cd-btn-primary:hover { opacity: 0.9; }

/* User Menu */
.cd-user-menu { position: relative; display: none; }
@media (min-width: 640px) { .cd-user-menu { display: block; } }

.cd-user-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 8px;
  border-radius: 999px;
  border: 1px solid rgba(var(--green-rgb), 0.1);
  background: var(--surface, #f8fafc);
  cursor: pointer;
  transition: all 0.2s;
}
.cd-user-btn:hover {
  border-color: rgba(var(--green-rgb), 0.3);
  background: var(--surface-high, #e2e8f0);
}

.cd-user-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  overflow: hidden;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--primary, var(--green));
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.cd-user-avatar img { width: 100%; height: 100%; object-fit: cover; }

.cd-user-info { display: none; text-align: left; }
@media (min-width: 1024px) { .cd-user-info { display: block; } }
.cd-user-name {
  margin: 0; font-size: 0.875rem; font-weight: 600;
  color: var(--on-surface, #0f172a);
  max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cd-user-role { margin: 0; font-size: 0.75rem; color: var(--outline, #64748b); }

.cd-user-btn .material-symbols-outlined { font-size: 20px; color: var(--outline, #64748b); }

/* Dropdown */
.cd-dropdown {
  position: absolute; right: 0; top: calc(100% + 8px);
  width: 288px;
  background: #fff;
  border: 1px solid rgba(var(--green-rgb), 0.1);
  border-radius: 24px;
  padding: 12px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.12);
}
.cd-dropdown-header {
  padding: 12px 16px;
  border-radius: 16px;
  background: rgba(var(--green-rgb), 0.1);
  margin-bottom: 12px;
}
.cd-dropdown-name { margin: 0; font-size: 0.875rem; font-weight: 700; color: var(--on-surface, #0f172a); }
.cd-dropdown-email { margin: 4px 0 0; font-size: 0.75rem; color: var(--outline, #64748b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.cd-dropdown-body { display: flex; flex-direction: column; gap: 4px; }
.cd-dropdown-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px;
  border-radius: 16px;
  border: none; background: transparent;
  font-size: 0.875rem; font-weight: 500;
  color: var(--on-surface-variant, #475569);
  text-decoration: none; cursor: pointer; text-align: left;
  transition: all 0.2s;
}
.cd-dropdown-item:hover { background: var(--surface, #f8fafc); color: var(--on-surface, #0f172a); }
.cd-dropdown-item .material-symbols-outlined { font-size: 20px; }

.cd-dropdown-item--primary { color: var(--primary, var(--green)); }
.cd-dropdown-item--primary:hover { background: rgba(var(--green-rgb), 0.1); color: var(--primary, var(--green)); }
.cd-dropdown-item--danger { color: #d71920; }
.cd-dropdown-item--danger:hover { background: rgba(215, 25, 32, 0.1); color: #d71920; }
.cd-dropdown-item--error { color: #ef4444; }
.cd-dropdown-item--error:hover { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

/* Mobile Menu */
.cd-mobile-toggle {
  display: flex; align-items: center; justify-content: center;
  width: 44px; height: 44px;
  border-radius: 50%;
  border: 1px solid rgba(var(--green-rgb), 0.1);
  background: var(--surface, #f8fafc);
  color: var(--on-surface, #0f172a);
  cursor: pointer;
}
@media (min-width: 768px) { .cd-mobile-toggle { display: none; } }

.cd-mobile-menu {
  padding: 16px;
  background: #fff;
  border-top: 1px solid rgba(var(--green-rgb), 0.1);
}
@media (min-width: 768px) { .cd-mobile-menu { display: none; } }

.cd-mobile-header {
  padding: 16px; margin-bottom: 16px;
  border-radius: 24px;
  background: rgba(var(--green-rgb), 0.1);
}
.cd-mobile-title { margin: 0; font-size: 0.875rem; font-weight: 700; color: var(--on-surface, #0f172a); }
.cd-mobile-desc { margin: 4px 0 0; font-size: 0.75rem; line-height: 1.5; color: var(--on-surface-variant, #475569); }

.cd-mobile-nav { display: flex; flex-direction: column; gap: 8px; }
.cd-mobile-link {
  display: block; padding: 12px 16px;
  border-radius: 16px;
  font-size: 0.875rem; font-weight: 600;
  color: var(--on-surface-variant, #475569);
  text-decoration: none; border: none; background: transparent; cursor: pointer; text-align: left;
  transition: all 0.2s;
}
.cd-mobile-link:hover { background: var(--surface, #f8fafc); color: var(--primary, var(--green)); }
.cd-mobile-link--bordered { border: 1px solid rgba(var(--green-rgb), 0.1); }
.cd-mobile-link--primary {
  background: var(--green);
  color: #fff; text-align: center;
}
.cd-mobile-link--primary:hover { opacity: 0.9; color: #fff; }
</style>
