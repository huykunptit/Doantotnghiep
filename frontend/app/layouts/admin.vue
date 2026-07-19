<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const sidebarExpanded = ref(true)
const mobileSidebarOpen = ref(false)
const activeSubmenu = ref<string | null>(null)
const hoveredMenu = ref<string | null>(null)

const user = useAuthUserCookie()

const adminName = computed(() => user.value?.name || 'Administrator')
const adminInitials = computed(() => {
  const name = adminName.value.trim()
  if (!name) return 'AD'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map(part => part[0]?.toUpperCase())
    .join('') || 'AD'
})
const adminRole = computed(() => {
  const role = user.value?.role || 'admin'
  if (role === 'admin') return 'Quản trị viên'
  if (role === 'academic_manager') return 'Quản lý học vụ'
  return 'Quản trị'
})

interface NavSubItem {
  label: string
  to: string
}

interface NavItem {
  key: string
  label: string
  icon: string
  to?: string
  items?: NavSubItem[]
}

const navigation: NavItem[] = [
  { key: 'dashboard', label: 'Dashboard', icon: 'pi pi-home', to: '/admin' },
  {
    key: 'users',
    label: 'Người dùng',
    icon: 'pi pi-users',
    items: [
      { label: 'Sinh viên', to: '/admin/users?role=student' },
      { label: 'Giảng viên', to: '/admin/users?role=instructor' },
      { label: 'Quản trị viên', to: '/admin/users?role=admin' }
    ]
  },
  {
    key: 'organization',
    label: 'Tổ chức đào tạo',
    icon: 'pi pi-sitemap',
    items: [
      { label: 'Khoa', to: '/admin/organization/faculties' },
      { label: 'Ngành', to: '/admin/organization/majors' },
      { label: 'Chuyên ngành', to: '/admin/organization/specializations' }
    ]
  },
  {
    key: 'academic_training',
    label: 'Đào tạo & Học vụ',
    icon: 'pi pi-briefcase',
    items: [
      { label: 'Chương trình đào tạo', to: '/admin/training/programs' },
      { label: 'Năm học', to: '/admin/training/academic-years' },
      { label: 'Lớp hành chính', to: '/admin/training/administrative-classes' },
      { label: 'Lớp học phần', to: '/admin/training/course-classes' }
    ]
  },
  {
    key: 'courses',
    label: 'Nội dung học tập',
    icon: 'pi pi-book',
    items: [
      { label: 'Kiểm duyệt khóa học', to: '/admin/manage-courses' },
      { label: 'Khóa học', to: '/admin/courses' },
      { label: 'Danh mục', to: '/admin/categories' },
      { label: 'Đánh giá', to: '/admin/reviews' },
      { label: 'Chứng chỉ', to: '/admin/certificates' }
    ]
  },
  {
    key: 'reports',
    label: 'Báo cáo',
    icon: 'pi pi-chart-line',
    items: [
      { label: 'Báo cáo khóa học', to: '/admin/reports/courses' },
      { label: 'Tiến độ học tập', to: '/admin/reports/progress' },
      { label: 'Nhật ký hoạt động', to: '/admin/reports/activity' }
    ]
  },
  {
    key: 'system',
    label: 'Hệ thống',
    icon: 'pi pi-cog',
    items: [
      { label: 'Mẫu email', to: '/admin/email-templates' },
      { label: 'Cài đặt hệ thống', to: '/admin/settings' }
    ]
  }
]

const currentPathWithQuery = computed(() => route.fullPath)

function itemIsActive(item: NavItem) {
  if (item.to) return linkIsActive(item.to)
  return Boolean(item.items?.some(sub => linkIsActive(sub.to)))
}

function linkIsActive(to: string) {
  const [path, query] = to.split('?')
  if (query) return currentPathWithQuery.value === to
  return route.path === path || (path !== '/admin' && route.path.startsWith(`${path}/`))
}

function toggleSidebar() {
  sidebarExpanded.value = !sidebarExpanded.value
  if (!sidebarExpanded.value) activeSubmenu.value = null
}

function openMobileSidebar() {
  mobileSidebarOpen.value = true
  sidebarExpanded.value = true
}

function toggleSubmenu(key: string) {
  if (!sidebarExpanded.value) return
  activeSubmenu.value = activeSubmenu.value === key ? null : key
}

function onRailEnter(key: string) {
  if (!sidebarExpanded.value) hoveredMenu.value = key
}

function onRailLeave(key: string) {
  if (hoveredMenu.value === key) hoveredMenu.value = null
}

function closeMobileSidebar() {
  mobileSidebarOpen.value = false
}

async function handleLogout() {
  const token = useAuthTokenCookie()
  const authUser = useAuthUserCookie()
  token.value = null
  authUser.value = null
  await navigateTo('/login', { replace: true })
}

watch(
  () => route.fullPath,
  () => {
    mobileSidebarOpen.value = false
    hoveredMenu.value = null
    const activeParent = navigation.find(item => item.items?.some(sub => linkIsActive(sub.to)))
    activeSubmenu.value = activeParent?.key || null
  },
  { immediate: true }
)
</script>

<template>
  <div class="admin-layout-shell">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="topbar-icon-btn lg:hidden" type="button" aria-label="Mở menu" @click="openMobileSidebar">
          <i class="pi pi-bars" />
        </button>
        <button class="topbar-icon-btn hidden lg:inline-flex" type="button" aria-label="Thu gọn menu" @click="toggleSidebar">
          <i :class="sidebarExpanded ? 'pi pi-angle-left' : 'pi pi-angle-right'" />
        </button>

        <div class="topbar-search">
          <i class="pi pi-search" />
          <input type="text" placeholder="Tìm kiếm nhanh..." />
          <span>⌘K</span>
        </div>
      </div>

      <div class="topbar-right">
        <button class="topbar-chip hidden sm:inline-flex" type="button">
          <img src="https://flagcdn.com/w20/vn.png" alt="VN" />
          <span>VI</span>
        </button>
        <button class="topbar-icon-btn is-soft" type="button" aria-label="Thông báo">
          <i class="pi pi-bell" />
          <span class="notification-dot" />
        </button>
        <div class="topbar-user">
          <div class="user-avatar">{{ adminInitials }}</div>
          <div class="hidden sm:block min-w-0">
            <strong>{{ adminName }}</strong>
            <span>{{ adminRole }}</span>
          </div>
        </div>
      </div>
    </header>

    <div v-if="mobileSidebarOpen" class="sidebar-backdrop lg:hidden" @click="closeMobileSidebar" />

    <aside
      class="admin-sidebar"
      :class="[
        sidebarExpanded ? 'is-expanded' : 'is-collapsed',
        mobileSidebarOpen ? 'is-mobile-open' : ''
      ]"
    >
      <div class="sidebar-brand">
        <NuxtLink to="/admin" class="brand-link" @click="closeMobileSidebar">
          <span class="brand-mark">Q</span>
          <span v-if="sidebarExpanded" class="brand-copy">
            <strong>QES LMS</strong>
            <small>Admin console</small>
          </span>
        </NuxtLink>
      </div>

      <nav class="sidebar-nav">
        <div
          v-for="item in navigation"
          :key="item.key"
          class="nav-block"
          @mouseenter="onRailEnter(item.key)"
          @mouseleave="onRailLeave(item.key)"
        >
          <NuxtLink
            v-if="!item.items && item.to"
            :to="item.to"
            class="nav-parent"
            :class="{ 'is-active': itemIsActive(item), 'is-compact': !sidebarExpanded }"
            :title="!sidebarExpanded ? item.label : undefined"
            @click="closeMobileSidebar"
          >
            <span class="nav-icon"><i :class="item.icon" /></span>
            <span v-if="sidebarExpanded" class="nav-label">{{ item.label }}</span>
          </NuxtLink>

          <template v-else>
            <button
              type="button"
              class="nav-parent"
              :class="{ 'is-active': itemIsActive(item), 'is-open': activeSubmenu === item.key, 'is-compact': !sidebarExpanded }"
              :title="!sidebarExpanded ? item.label : undefined"
              @click="toggleSubmenu(item.key)"
            >
              <span class="nav-icon"><i :class="item.icon" /></span>
              <span v-if="sidebarExpanded" class="nav-label">{{ item.label }}</span>
              <i v-if="sidebarExpanded" class="pi pi-angle-down nav-chevron" />
            </button>

            <Transition name="submenu-slide">
              <div v-if="sidebarExpanded && activeSubmenu === item.key" class="nav-children">
                <NuxtLink
                  v-for="sub in item.items"
                  :key="sub.to"
                  :to="sub.to"
                  class="nav-child"
                  :class="{ 'is-active': linkIsActive(sub.to) }"
                  @click="closeMobileSidebar"
                >
                  <span class="child-dot" />
                  <span>{{ sub.label }}</span>
                </NuxtLink>
              </div>
            </Transition>

            <div v-if="!sidebarExpanded && hoveredMenu === item.key" class="rail-flyout">
              <div class="flyout-title">
                <i :class="item.icon" />
                <span>{{ item.label }}</span>
              </div>
              <NuxtLink
                v-for="sub in item.items"
                :key="sub.to"
                :to="sub.to"
                class="flyout-link"
                :class="{ 'is-active': linkIsActive(sub.to) }"
              >
                {{ sub.label }}
              </NuxtLink>
            </div>
          </template>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-profile" :class="{ 'is-compact': !sidebarExpanded }">
          <div class="user-avatar is-sidebar">{{ adminInitials }}</div>
          <div v-if="sidebarExpanded" class="profile-copy">
            <strong>{{ adminName }}</strong>
            <span>Admin</span>
          </div>
          <button v-if="sidebarExpanded" class="logout-btn" type="button" title="Đăng xuất" @click="handleLogout">
            <i class="pi pi-sign-out" />
          </button>
        </div>
      </div>
    </aside>

    <main class="admin-main" :class="sidebarExpanded ? 'with-expanded-sidebar' : 'with-collapsed-sidebar'">
      <slot />
    </main>
  </div>
</template>

<style>
:root {
  --admin-text: #172033;
  --admin-muted: #64748b;
  --admin-line: #e2e8f0;
  --admin-soft-line: #eef2f7;
  --admin-surface: #f6f8fb;
  --admin-white: #ffffff;
  --admin-primary: #1d9e75;
  --admin-primary-dark: #087455;
  --admin-primary-soft: rgba(29, 158, 117, 0.1);
  --admin-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);

  --text: var(--admin-text);
  --muted: var(--admin-muted);
  --line: var(--admin-line);
  --surface: var(--admin-surface);
}

.admin-layout-shell {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(29, 158, 117, 0.06), transparent 32rem),
    var(--admin-surface);
  color: var(--admin-text);
  font-family: 'Be Vietnam Pro', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.admin-topbar {
  position: sticky;
  top: 0;
  z-index: 45;
  height: 58px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 0 18px;
  background: rgba(255, 255, 255, 0.88);
  border-bottom: 1px solid var(--admin-line);
  backdrop-filter: blur(16px);
}

.topbar-left,
.topbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.topbar-icon-btn {
  position: relative;
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid transparent;
  border-radius: 11px;
  background: transparent;
  color: var(--admin-muted);
  cursor: pointer;
  transition: all 160ms ease;
}
.topbar-icon-btn:hover,
.topbar-icon-btn.is-soft {
  border-color: var(--admin-line);
  background: #fff;
  color: var(--admin-text);
}

.notification-dot {
  position: absolute;
  top: 8px;
  right: 9px;
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #ef4444;
  box-shadow: 0 0 0 2px #fff;
}

.topbar-search {
  width: min(360px, 38vw);
  height: 36px;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 0 10px;
  border: 1px solid var(--admin-line);
  border-radius: 12px;
  background: #fff;
  color: var(--admin-muted);
}
.topbar-search input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 0.84rem;
  color: var(--admin-text);
}
.topbar-search span {
  border: 1px solid var(--admin-soft-line);
  border-radius: 7px;
  padding: 1px 6px;
  font-size: 0.68rem;
  font-weight: 800;
  color: #94a3b8;
}

.topbar-chip {
  height: 34px;
  align-items: center;
  gap: 7px;
  padding: 0 10px;
  border: 1px solid var(--admin-line);
  border-radius: 11px;
  background: #fff;
  font-size: 0.76rem;
  font-weight: 800;
  color: var(--admin-muted);
}
.topbar-chip img {
  width: 20px;
  height: 14px;
  border-radius: 3px;
  object-fit: cover;
}

.topbar-user {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
  padding-left: 10px;
  border-left: 1px solid var(--admin-line);
}
.topbar-user strong,
.profile-copy strong {
  display: block;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8rem;
  color: var(--admin-text);
}
.topbar-user span,
.profile-copy span {
  display: block;
  margin-top: 1px;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--admin-muted);
}
.user-avatar {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  background: linear-gradient(135deg, var(--admin-primary), #0f766e);
  color: #fff;
  font-size: 0.72rem;
  font-weight: 900;
  box-shadow: 0 8px 18px rgba(29, 158, 117, 0.24);
}

.admin-sidebar {
  position: fixed;
  z-index: 50;
  top: 58px;
  left: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--admin-line);
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 6px 0 24px rgba(15, 23, 42, 0.04);
  backdrop-filter: blur(16px);
  transition: width 240ms cubic-bezier(0.16, 1, 0.3, 1), transform 240ms cubic-bezier(0.16, 1, 0.3, 1);
}
.admin-sidebar.is-expanded { width: 248px; }
.admin-sidebar.is-collapsed { width: 74px; }

.sidebar-brand {
  height: 66px;
  display: flex;
  align-items: center;
  padding: 0 14px;
  border-bottom: 1px solid var(--admin-soft-line);
}
.brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  text-decoration: none;
}
.brand-mark {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  background: var(--admin-primary);
  color: #fff;
  font-size: 1.2rem;
  font-weight: 900;
  box-shadow: 0 12px 24px rgba(29, 158, 117, 0.24);
}
.brand-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.brand-copy strong {
  font-size: 1rem;
  line-height: 1;
  letter-spacing: -0.03em;
  color: var(--admin-text);
}
.brand-copy small {
  margin-top: 4px;
  font-size: 0.62rem;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--admin-muted);
}

.sidebar-nav {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 10px 10px;
  overflow-y: auto;
  overflow-x: visible;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }

.nav-block { position: relative; }
.nav-parent {
  position: relative;
  width: 100%;
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: 10px;
  border: 1px solid transparent;
  border-radius: 12px;
  padding: 0 10px;
  background: transparent;
  color: #475569;
  text-decoration: none;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 750;
  text-align: left;
  cursor: pointer;
  transition: all 160ms ease;
}
.nav-parent:hover {
  background: #f8fafc;
  border-color: var(--admin-soft-line);
  color: var(--admin-primary-dark);
}
.nav-parent.is-active {
  background: var(--admin-primary-soft);
  border-color: rgba(29, 158, 117, 0.18);
  color: var(--admin-primary-dark);
}
.nav-parent.is-active::before {
  content: '';
  position: absolute;
  left: -10px;
  top: 9px;
  bottom: 9px;
  width: 3px;
  border-radius: 999px;
  background: var(--admin-primary);
}
.nav-parent.is-compact {
  justify-content: center;
  padding: 0;
}
.nav-icon {
  width: 20px;
  height: 20px;
  flex: 0 0 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.98rem;
}
.nav-label {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.nav-chevron {
  font-size: 0.72rem;
  color: #94a3b8;
  transition: transform 180ms ease;
}
.nav-parent.is-open .nav-chevron { transform: rotate(180deg); color: var(--admin-primary); }

.nav-children {
  position: relative;
  margin: 4px 0 6px 20px;
  padding: 2px 0 2px 15px;
  border-left: 1px solid #e5e7eb;
}
.nav-child {
  position: relative;
  min-height: 30px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-radius: 9px;
  padding: 0 10px;
  color: #64748b;
  text-decoration: none;
  font-size: 0.76rem;
  font-weight: 700;
  transition: all 140ms ease;
}
.nav-child:hover { color: var(--admin-primary-dark); background: #f8fafc; }
.nav-child.is-active { color: var(--admin-primary-dark); background: var(--admin-primary-soft); font-weight: 900; }
.child-dot {
  position: absolute;
  left: -19px;
  width: 7px;
  height: 7px;
  border: 1px solid #d8dee8;
  border-radius: 999px;
  background: #fff;
}
.nav-child.is-active .child-dot {
  border-color: var(--admin-primary);
  background: var(--admin-primary);
}

.rail-flyout {
  position: fixed;
  left: 66px;
  min-width: 230px;
  max-width: 280px;
  transform: translateY(-42px);
  padding: 8px;
  border: 1px solid var(--admin-line);
  border-radius: 16px;
  background: #fff;
  box-shadow: var(--admin-shadow);
  z-index: 90;
}
.flyout-title {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 9px 10px 11px;
  border-bottom: 1px solid var(--admin-soft-line);
  color: var(--admin-text);
  font-size: 0.82rem;
  font-weight: 900;
}
.flyout-link {
  display: block;
  margin-top: 4px;
  border-radius: 10px;
  padding: 8px 10px;
  color: #64748b;
  text-decoration: none;
  font-size: 0.78rem;
  font-weight: 750;
  transition: all 140ms ease;
}
.flyout-link:hover,
.flyout-link.is-active {
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.sidebar-footer {
  padding: 10px;
  border-top: 1px solid var(--admin-soft-line);
}
.sidebar-profile {
  display: flex;
  align-items: center;
  gap: 9px;
  min-height: 48px;
  border: 1px solid var(--admin-soft-line);
  border-radius: 15px;
  padding: 7px;
  background: #f8fafc;
}
.sidebar-profile.is-compact { justify-content: center; padding: 6px; }
.user-avatar.is-sidebar {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  border-radius: 12px;
}
.profile-copy {
  min-width: 0;
  flex: 1;
}
.logout-btn {
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  transition: all 140ms ease;
}
.logout-btn:hover { background: #fee2e2; color: #dc2626; }

.admin-main {
  min-width: 0;
  padding: 22px;
  transition: padding-left 240ms cubic-bezier(0.16, 1, 0.3, 1);
}
.admin-main.with-expanded-sidebar { padding-left: 270px; }
.admin-main.with-collapsed-sidebar { padding-left: 96px; }

.submenu-slide-enter-active,
.submenu-slide-leave-active {
  transition: max-height 180ms ease, opacity 160ms ease;
  overflow: hidden;
}
.submenu-slide-enter-from,
.submenu-slide-leave-to {
  max-height: 0;
  opacity: 0;
}
.submenu-slide-enter-to,
.submenu-slide-leave-from {
  max-height: 320px;
  opacity: 1;
}

.sidebar-backdrop {
  position: fixed;
  inset: 58px 0 0;
  z-index: 48;
  background: rgba(15, 23, 42, 0.38);
  backdrop-filter: blur(2px);
}

@media (max-width: 1023px) {
  .topbar-search { display: none; }
  .admin-sidebar {
    width: 248px !important;
    transform: translateX(-105%);
  }
  .admin-sidebar.is-mobile-open { transform: translateX(0); }
  .admin-main,
  .admin-main.with-expanded-sidebar,
  .admin-main.with-collapsed-sidebar {
    padding: 18px;
  }
}

@media (max-width: 640px) {
  .admin-topbar { padding: 0 12px; }
  .topbar-right { gap: 6px; }
  .admin-main,
  .admin-main.with-expanded-sidebar,
  .admin-main.with-collapsed-sidebar {
    padding: 14px;
  }
}
</style>
