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
          <Button icon="pi pi-bell" severity="secondary" text rounded :aria-label="t('common.notifications')" />
          <Avatar v-if="auth.user?.avatar" :image="auth.user.avatar" shape="circle" />
          <Avatar v-else :label="userInitials" shape="circle" />
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
