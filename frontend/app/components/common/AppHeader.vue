<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  Sun, Moon, Menu, X, ChevronDown,
  User, ReceiptText, GraduationCap, ShieldCheck, LogOut,
  BookOpen, Compass, Bell,
} from 'lucide-vue-next'
import NotificationBell from '~/components/NotificationBell.vue'
import { publicNavigation } from '~/constants/navigation'
import { useAuthStore } from '~/stores/auth'
import { useDarkMode } from '~/composables/useDarkMode'

const auth = useAuthStore()
const router = useRouter()
const { siteLogo, siteName, siteTagline } = useSiteSettings()
const { isDark, toggle: toggleDark, init: initDark } = useDarkMode()

const showMenu = ref(false)
const showMobileMenu = ref(false)
const menuRef = ref<HTMLElement | null>(null)
const scrolled = ref(false)

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

function handleScroll() {
  scrolled.value = window.scrollY > 8
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.addEventListener('scroll', handleScroll, { passive: true })
  initDark()
})
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <header class="cd-header" :class="{ 'is-scrolled': scrolled }">
    <div class="cd-header-inner">

      <!-- Brand -->
      <NuxtLink to="/" class="cd-brand">
        <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="cd-brand-logo">
        <div v-else class="cd-brand-icon">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 22C12 22 22 12 22 2" />
            <path d="M12 12C12 8 16 4 22 2" />
            <path d="M12 12C8 12 4 16 2 22" />
          </svg>
        </div>
        <div class="cd-brand-text">
          <span class="cd-brand-title">{{ siteName }}</span>
          <span class="cd-brand-slogan">{{ siteTagline || 'Nuôi dưỡng tri thức' }}</span>
        </div>
      </NuxtLink>

      <!-- Desktop nav -->
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

      <!-- Actions -->
      <div class="cd-actions">
        <button
          type="button"
          class="cd-icon-btn"
          :title="isDark ? 'Chuyển sang sáng' : 'Chuyển sang tối'"
          @click="toggleDark"
        >
          <Sun v-if="isDark" :size="18" :stroke-width="1.75" />
          <Moon v-else :size="18" :stroke-width="1.75" />
        </button>

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
                  <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="cd-user-info">
                  <span class="cd-user-name">{{ auth.user?.name }}</span>
                  <span class="cd-user-role">Sylva Member</span>
                </div>
                <ChevronDown :size="16" :stroke-width="2" class="cd-chevron" :class="{ 'is-open': showMenu }" />
              </button>

              <Transition name="dropdown">
                <div v-if="showMenu" class="cd-dropdown">
                  <div class="cd-dropdown-header">
                    <div class="cd-dropdown-avatar">
                      <img v-if="auth.user?.avatar" :src="auth.user.avatar" alt="">
                      <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div>
                      <p class="cd-dropdown-name">{{ auth.user?.name }}</p>
                      <p class="cd-dropdown-email">{{ auth.user?.email }}</p>
                    </div>
                  </div>
                  <div class="cd-dropdown-divider" />
                  <div class="cd-dropdown-body">
                    <NuxtLink to="/profile" class="cd-dropdown-item" @click="showMenu = false">
                      <User :size="16" :stroke-width="1.75" /> Hồ sơ cá nhân
                    </NuxtLink>
                    <NuxtLink to="/orders" class="cd-dropdown-item" @click="showMenu = false">
                      <ReceiptText :size="16" :stroke-width="1.75" /> Đơn hàng
                    </NuxtLink>
                    <NuxtLink v-if="isInstructor" to="/instructor" class="cd-dropdown-item cd-dropdown-item--primary" @click="showMenu = false">
                      <GraduationCap :size="16" :stroke-width="1.75" /> Khu vực giảng viên
                    </NuxtLink>
                    <NuxtLink v-if="isAdmin" to="/admin" class="cd-dropdown-item cd-dropdown-item--accent" @click="showMenu = false">
                      <ShieldCheck :size="16" :stroke-width="1.75" /> Quản trị hệ thống
                    </NuxtLink>
                    <div class="cd-dropdown-divider" />
                    <button class="cd-dropdown-item cd-dropdown-item--danger" @click="handleLogout">
                      <LogOut :size="16" :stroke-width="1.75" /> Đăng xuất
                    </button>
                  </div>
                </div>
              </Transition>
            </div>
          </template>
        </ClientOnly>

        <!-- Mobile hamburger -->
        <button class="cd-mobile-toggle" @click="showMobileMenu = !showMobileMenu" :aria-label="showMobileMenu ? 'Đóng menu' : 'Mở menu'">
          <X v-if="showMobileMenu" :size="20" :stroke-width="2" />
          <Menu v-else :size="20" :stroke-width="2" />
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <Transition name="mobile-menu">
      <div v-if="showMobileMenu" class="cd-mobile-menu">
        <div class="cd-mobile-header">
          <div class="cd-mobile-brand">
            <BookOpen :size="18" :stroke-width="1.75" />
            <span>{{ siteName }}</span>
          </div>
          <p class="cd-mobile-desc">Học tập thích nghi · Tri thức bền vững</p>
        </div>

        <nav class="cd-mobile-nav">
          <NuxtLink
            v-for="item in publicNavigation"
            :key="item.to"
            :to="item.to"
            class="cd-mobile-link"
            @click="showMobileMenu = false"
          >
            {{ item.label }}
          </NuxtLink>
          <NuxtLink v-if="auth.isLoggedIn" to="/student" class="cd-mobile-link" @click="showMobileMenu = false">
            Khóa học của tôi
          </NuxtLink>
          <NuxtLink v-if="auth.isLoggedIn" to="/profile" class="cd-mobile-link" @click="showMobileMenu = false">
            Hồ sơ cá nhân
          </NuxtLink>
        </nav>

        <div class="cd-mobile-foot">
          <template v-if="!auth.isLoggedIn">
            <NuxtLink to="/login" class="cd-mobile-btn cd-mobile-btn--ghost" @click="showMobileMenu = false">
              Đăng nhập
            </NuxtLink>
            <NuxtLink to="/register" class="cd-mobile-btn cd-mobile-btn--primary" @click="showMobileMenu = false">
              Bắt đầu ngay
            </NuxtLink>
          </template>
          <button v-else class="cd-mobile-btn cd-mobile-btn--ghost" @click="handleLogout">
            <LogOut :size="16" :stroke-width="1.75" /> Đăng xuất
          </button>
        </div>
      </div>
    </Transition>
  </header>
</template>

<style scoped>
/* ── Header shell ── */
.cd-header {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  background: rgba(248, 250, 249, 0.88);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid transparent;
  transition: border-color 200ms ease, box-shadow 200ms ease, background 200ms ease;
}

.cd-header.is-scrolled {
  border-bottom-color: var(--line);
  box-shadow: 0 1px 12px rgba(31, 49, 43, 0.06);
}

[data-theme="dark"] .cd-header {
  background: rgba(10, 26, 20, 0.88);
}
[data-theme="dark"] .cd-header.is-scrolled {
  box-shadow: 0 1px 12px rgba(0, 0, 0, 0.3);
}

.cd-header-inner {
  display: flex;
  align-items: center;
  gap: 16px;
  height: 64px;
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

/* ── Brand ── */
.cd-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  flex-shrink: 0;
  transition: opacity 150ms;
}
.cd-brand:hover { opacity: 0.85; }

.cd-brand-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 12px;
  background: var(--green);
  color: #fff;
  flex-shrink: 0;
}

.cd-brand-logo {
  height: 38px;
  border-radius: 10px;
  object-fit: contain;
  flex-shrink: 0;
}

.cd-brand-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.cd-brand-title {
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text);
  white-space: nowrap;
}

.cd-brand-slogan {
  font-size: 0.6rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: var(--muted);
  white-space: nowrap;
}

/* ── Desktop Navigation ── */
.cd-nav {
  display: none;
  align-items: center;
  gap: 2px;
  margin-left: auto;
}

@media (min-width: 768px) {
  .cd-nav { display: flex; }
}

.cd-nav-link {
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  transition: background 150ms, color 150ms;
}

.cd-nav-link:hover {
  background: rgba(var(--primary-rgb), 0.08);
  color: var(--text);
}

.cd-nav-link.is-active {
  background: rgba(var(--primary-rgb), 0.1);
  color: var(--green);
  font-weight: 600;
}

.cd-nav-login {
  display: none;
}
@media (min-width: 640px) {
  .cd-nav-login { display: block; }
}

/* ── Actions ── */
.cd-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}

@media (min-width: 768px) {
  .cd-actions { margin-left: 16px; }
}

.cd-icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: background 150ms, color 150ms, border-color 150ms;
  flex-shrink: 0;
}

.cd-icon-btn:hover {
  background: rgba(var(--primary-rgb), 0.08);
  color: var(--green);
  border-color: rgba(var(--primary-rgb), 0.2);
}

.cd-btn-primary {
  display: inline-flex;
  align-items: center;
  height: 38px;
  padding: 0 18px;
  border-radius: 8px;
  background: var(--green);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: background 150ms, transform 150ms;
}

.cd-btn-primary:hover {
  background: var(--green-deep);
  transform: translateY(-1px);
}

/* ── User Menu ── */
.cd-user-menu {
  position: relative;
  display: none;
}

@media (min-width: 640px) {
  .cd-user-menu { display: block; }
}

.cd-user-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 40px;
  padding: 4px 10px 4px 4px;
  border-radius: 999px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  cursor: pointer;
  transition: border-color 150ms, background 150ms;
}

.cd-user-btn:hover {
  border-color: rgba(var(--primary-rgb), 0.3);
  background: rgba(var(--primary-rgb), 0.04);
}

.cd-user-avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--green-soft);
  color: var(--green-deep);
  font-size: 0.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cd-user-avatar img {
  width: 100%; height: 100%;
  object-fit: cover;
}

.cd-user-info {
  display: none;
  flex-direction: column;
  gap: 1px;
  text-align: left;
}

@media (min-width: 1024px) {
  .cd-user-info { display: flex; }
}

.cd-user-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text);
  max-width: 120px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cd-user-role {
  font-size: 0.6875rem;
  color: var(--muted);
}

.cd-chevron {
  color: var(--muted);
  transition: transform 200ms ease;
  flex-shrink: 0;
}

.cd-chevron.is-open {
  transform: rotate(180deg);
}

/* ── Dropdown ── */
.cd-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  width: 272px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 8px;
  box-shadow: 0 8px 32px rgba(31, 49, 43, 0.12);
  z-index: 200;
}

[data-theme="dark"] .cd-dropdown {
  background: #142D1F;
  border-color: var(--line);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.cd-dropdown-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 10px;
  background: var(--green-soft);
}

.cd-dropdown-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--green);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cd-dropdown-avatar img {
  width: 100%; height: 100%;
  object-fit: cover;
}

.cd-dropdown-name {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--text);
}

.cd-dropdown-email {
  margin: 2px 0 0;
  font-size: 0.75rem;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cd-dropdown-divider {
  height: 1px;
  background: var(--line);
  margin: 6px 4px;
}

.cd-dropdown-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cd-dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: none;
  background: transparent;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  cursor: pointer;
  text-align: left;
  width: 100%;
  transition: background 150ms, color 150ms;
}

.cd-dropdown-item:hover {
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--text);
}

.cd-dropdown-item--primary { color: var(--green); }
.cd-dropdown-item--primary:hover {
  background: rgba(var(--primary-rgb), 0.08);
  color: var(--green-deep);
}

.cd-dropdown-item--accent { color: var(--secondary); }
.cd-dropdown-item--accent:hover {
  background: var(--secondary-soft);
  color: var(--secondary);
}

.cd-dropdown-item--danger { color: var(--danger); }
.cd-dropdown-item--danger:hover {
  background: var(--danger-soft);
  color: var(--danger);
}

/* ── Mobile toggle ── */
.cd-mobile-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px; height: 40px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  cursor: pointer;
  transition: background 150ms;
  flex-shrink: 0;
}

.cd-mobile-toggle:hover {
  background: rgba(var(--primary-rgb), 0.06);
}

@media (min-width: 768px) {
  .cd-mobile-toggle { display: none; }
}

/* ── Mobile menu ── */
.cd-mobile-menu {
  border-top: 1px solid var(--line);
  background: var(--surface-strong);
  padding: 16px 24px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

@media (min-width: 768px) {
  .cd-mobile-menu { display: none !important; }
}

.cd-mobile-header {
  padding: 16px;
  border-radius: 12px;
  background: var(--green-soft);
}

.cd-mobile-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 0.9rem;
  color: var(--green-deep);
  margin-bottom: 4px;
}

.cd-mobile-desc {
  margin: 0;
  font-size: 0.75rem;
  color: var(--muted);
}

.cd-mobile-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cd-mobile-link {
  display: block;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  transition: background 150ms, color 150ms;
}

.cd-mobile-link:hover {
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--text);
}

.cd-mobile-foot {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  padding-top: 8px;
  border-top: 1px solid var(--line);
}

.cd-mobile-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex: 1;
  height: 42px;
  padding: 0 16px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: background 150ms, transform 150ms;
}

.cd-mobile-btn--primary {
  background: var(--green);
  color: #fff;
}

.cd-mobile-btn--primary:hover {
  background: var(--green-deep);
}

.cd-mobile-btn--ghost {
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--text);
  border: 1px solid var(--line);
}

.cd-mobile-btn--ghost:hover {
  background: rgba(var(--primary-rgb), 0.1);
}

/* ── Transitions ── */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 150ms ease, transform 150ms ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.98);
}

.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition: opacity 200ms ease, transform 200ms ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
