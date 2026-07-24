<script setup lang="ts">
import { resolveStudentBreadcrumb, resolveStudentTitle } from '~/config/student-menu'

const mobileMenuOpen = ref(false)
const route = useRoute()
const auth = useAuthStore()
const { isDark, toggle } = useTheme()
const { t } = useI18n()

const routeTitle = computed(() => resolveStudentTitle(route.path, t))
const crumbs = computed(() => resolveStudentBreadcrumb(route.path, t))
const userInitials = computed(() =>
  (auth.user?.name || 'SV').split(' ').filter(Boolean).slice(-2).map(p => p[0]).join('').toUpperCase(),
)

onMounted(() => {
  if (!auth.ready) auth.hydrate()
})
</script>

<template>
  <div class="sv-shell">
    <div class="desktop-sidebar">
      <StudentSidebar />
    </div>
    <Drawer v-model:visible="mobileMenuOpen" position="left" :show-close-icon="false" class="mobile-drawer">
      <StudentSidebar mobile @navigate="mobileMenuOpen = false" />
    </Drawer>
    <div class="sv-stage">
      <header class="sv-topbar">
        <div class="topbar-left">
          <Button icon="pi pi-bars" severity="secondary" text rounded class="mobile-menu-button" :aria-label="t('common.openMenu')" @click="mobileMenuOpen = true" />
          <div class="topbar-title">
            <nav v-if="crumbs.length" class="crumbs">
              <template v-for="(crumb, index) in crumbs" :key="`${crumb.label}-${index}`">
                <span v-if="index > 0" class="sep">/</span>
                <NuxtLink v-if="crumb.to && index < crumbs.length - 1" :to="crumb.to">{{ crumb.label }}</NuxtLink>
                <span v-else>{{ crumb.label }}</span>
              </template>
            </nav>
            <strong>{{ routeTitle }}</strong>
          </div>
        </div>
        <div class="topbar-actions">
          <LocaleSwitcher />
          <StudentNotificationBell />
          <Button :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'" severity="secondary" text rounded :aria-label="t('common.theme')" @click="toggle" />
          <Avatar v-if="auth.user?.avatar" :image="auth.user.avatar" shape="circle" />
          <Avatar v-else :label="userInitials" shape="circle" />
        </div>
      </header>
      <main class="sv-content">
        <slot />
      </main>
    </div>
    <StudentAiChatbot />
  </div>
</template>

<style scoped>
.sv-shell { display: grid; grid-template-columns: var(--sidebar-width) minmax(0, 1fr); min-height: 100dvh; background: transparent; }
.desktop-sidebar { position: sticky; top: 0; z-index: 20; height: 100dvh; }
.sv-stage { min-width: 0; }
.sv-topbar {
  position: sticky; top: 0; z-index: 15; display: flex; align-items: center; justify-content: space-between;
  height: var(--topbar-height); padding: 0 24px; border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 88%, transparent); backdrop-filter: blur(14px);
}
.topbar-left, .topbar-actions { display: flex; align-items: center; gap: 6px; }
.topbar-title { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.crumbs { display: flex; gap: 6px; color: var(--text-muted); font-size: .78rem; font-weight: 500; }
.crumbs a:hover { color: var(--brand); }
.sep { opacity: .5; }
.topbar-title strong { color: var(--text); font-size: 1.05rem; font-weight: 700; }
.mobile-menu-button { display: none; }
.sv-content { width: 100%; max-width: 1200px; margin: 0 auto; padding: 24px; }
:global(.mobile-drawer) { width: min(280px, 88vw) !important; }
:global(.mobile-drawer .p-drawer-content) { padding: 0; }
@media (max-width: 900px) {
  .sv-shell { grid-template-columns: 1fr; }
  .desktop-sidebar { display: none; }
  .mobile-menu-button { display: inline-flex; }
  .sv-content { padding: 16px; }
}
</style>
