<script setup lang="ts">
import { resolveAdminBreadcrumb, resolveAdminTitle } from '~/config/admin-menu'

const mobileMenuOpen = ref(false)
const route = useRoute()
const auth = useAuthStore()
const { isDark, toggle } = useTheme()
const { t } = useI18n()

const routeTitle = computed(() => resolveAdminTitle(route.path, t))
const crumbs = computed(() => resolveAdminBreadcrumb(route.path, t))
const userInitials = computed(() =>
  (auth.user?.name || 'AD')
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map(part => part[0])
    .join('')
    .toUpperCase(),
)

const accountPanel = ref()
const loggingOut = ref(false)

function toggleAccountPanel(event: Event) {
  accountPanel.value?.toggle(event)
}

async function goProfile() {
  accountPanel.value?.hide()
  await navigateTo('/admin/profile')
}

async function goHome() {
  accountPanel.value?.hide()
  await navigateTo('/')
}

async function logout() {
  accountPanel.value?.hide()
  loggingOut.value = true
  try {
    await auth.logout()
    await navigateTo('/login')
  }
  finally {
    loggingOut.value = false
  }
}

onMounted(() => {
  if (!auth.ready) auth.hydrate()
})
</script>

<template>
  <div class="admin-shell">
    <div class="desktop-sidebar">
      <AdminSidebar />
    </div>

    <Drawer v-model:visible="mobileMenuOpen" position="left" :show-close-icon="false" class="mobile-drawer">
      <AdminSidebar mobile @navigate="mobileMenuOpen = false" />
    </Drawer>

    <div class="admin-stage">
      <header class="admin-topbar">
        <div class="topbar-left">
          <Button
            icon="pi pi-bars"
            severity="secondary"
            text
            rounded
            class="mobile-menu-button"
            :aria-label="t('common.openMenu')"
            @click="mobileMenuOpen = true"
          />
          <div class="topbar-title">
            <nav v-if="crumbs.length" class="crumbs" aria-label="Breadcrumb">
              <template v-for="(crumb, index) in crumbs" :key="`${crumb.label}-${index}`">
                <span v-if="index > 0" class="sep">/</span>
                <NuxtLink v-if="crumb.to && index < crumbs.length - 1" :to="crumb.to">{{ crumb.label }}</NuxtLink>
                <span v-else>{{ crumb.label }}</span>
              </template>
            </nav>
            <strong>{{ routeTitle }}</strong>
          </div>
        </div>

        <div class="topbar-search">
          <i class="pi pi-search" />
          <input type="search" :placeholder="t('common.search')" :aria-label="t('common.search')">
          <kbd>⌘ K</kbd>
        </div>

        <div class="topbar-actions">
          <LocaleSwitcher />
          <Button
            :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
            severity="secondary"
            text
            rounded
            :aria-label="t('common.theme')"
            @click="toggle"
          />
          <StudentNotificationBell view-all-to="/admin/notifications" />
          <button type="button" class="account-trigger" :aria-label="t('common.myProfile')" @click="toggleAccountPanel">
            <Avatar v-if="auth.user?.avatar" :image="auth.user.avatar" shape="circle" />
            <Avatar v-else :label="userInitials" shape="circle" />
          </button>
          <Popover ref="accountPanel" class="account-pop">
            <div class="account-menu">
              <div class="account-menu-head">
                <Avatar v-if="auth.user?.avatar" :image="auth.user.avatar" shape="circle" />
                <Avatar v-else :label="userInitials" shape="circle" />
                <div class="account-menu-info">
                  <strong>{{ auth.user?.name || 'Admin' }}</strong>
                  <span>{{ auth.user?.email }}</span>
                </div>
              </div>
              <button type="button" class="account-menu-item" @click="goProfile">
                <i class="pi pi-user" />
                <span>{{ t('common.myProfile') }}</span>
              </button>
              <button type="button" class="account-menu-item" @click="goHome">
                <i class="pi pi-home" />
                <span>{{ t('common.home') }}</span>
              </button>
              <button type="button" class="account-menu-item danger" :disabled="loggingOut" @click="logout">
                <i class="pi pi-sign-out" />
                <span>{{ t('common.logout') }}</span>
              </button>
            </div>
          </Popover>
        </div>
      </header>

      <main class="admin-content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.admin-shell {
  display: grid;
  grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
  min-height: 100dvh;
  background: transparent;
}

.desktop-sidebar {
  position: sticky;
  top: 0;
  z-index: 20;
  height: 100dvh;
}

.admin-stage {
  min-width: 0;
}

.admin-topbar {
  position: sticky;
  top: 0;
  z-index: 15;
  display: grid;
  grid-template-columns: minmax(180px, 1fr) minmax(240px, 420px) minmax(160px, 1fr);
  align-items: center;
  height: var(--topbar-height);
  padding: 0 24px;
  border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 88%, transparent);
  backdrop-filter: blur(14px);
}

.topbar-left,
.topbar-actions {
  display: flex;
  align-items: center;
}

.topbar-title {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.crumbs {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
  font-size: .78rem;
  font-weight: 500;
}

.crumbs a:hover {
  color: var(--brand);
}

.sep {
  opacity: .5;
}

.topbar-title strong {
  overflow: hidden;
  color: var(--text);
  font-size: 1.05rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.topbar-search {
  display: flex;
  align-items: center;
  gap: 9px;
  height: 36px;
  padding: 0 11px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: color-mix(in srgb, var(--surface) 90%, transparent);
}

.topbar-search i {
  color: var(--text-muted);
  font-size: .9rem;
}

.topbar-search input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--text);
  font-size: .9rem;
  font-weight: 500;
}

.topbar-search kbd {
  padding: 2px 6px;
  border: 1px solid var(--border);
  border-radius: 5px;
  color: var(--text-muted);
  font-size: .72rem;
  font-weight: 600;
}

.topbar-actions {
  justify-content: flex-end;
  gap: 6px;
}

.mobile-menu-button {
  display: none;
}

.account-trigger {
  display: inline-flex;
  border: 0;
  padding: 0;
  background: transparent;
  cursor: pointer;
  border-radius: 50%;
}

.account-menu {
  display: flex;
  flex-direction: column;
  width: min(260px, 86vw);
  gap: 4px;
}

.account-menu-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 4px 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--border);
}

.account-menu-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.account-menu-info strong {
  overflow: hidden;
  color: var(--text);
  font-size: .92rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-menu-info span {
  overflow: hidden;
  color: var(--text-muted);
  font-size: .78rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 38px;
  padding: 0 10px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: var(--text);
  font: inherit;
  font-size: .88rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: background .15s ease;
}

.account-menu-item i {
  width: 16px;
  font-size: .85rem;
  text-align: center;
}

.account-menu-item:hover {
  background: var(--surface-hover);
}

.account-menu-item.danger {
  color: #dc2626;
}

.account-menu-item:disabled {
  opacity: .6;
  cursor: not-allowed;
}

.admin-content {
  width: 100%;
  max-width: 1540px;
  margin: 0 auto;
  padding: 24px;
}

:global(.mobile-drawer) {
  width: min(280px, 88vw) !important;
}

:global(.mobile-drawer .p-drawer-content) {
  padding: 0;
}

@media (max-width: 900px) {
  .admin-shell {
    grid-template-columns: 1fr;
  }

  .desktop-sidebar {
    display: none;
  }

  .admin-topbar {
    grid-template-columns: 1fr auto;
    padding: 0 14px;
  }

  .mobile-menu-button {
    display: inline-flex;
  }

  .topbar-search {
    display: none;
  }

  .admin-content {
    padding: 16px;
  }
}
</style>
