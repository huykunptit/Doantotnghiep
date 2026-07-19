<script setup lang="ts">
/**
 * AdminSidebar — White sidebar with PanelMenu (ported from admin-ui)
 * Features: PanelMenu, PrimeIcons, collapse mode, hover accordion, mobile Drawer
 */
import { ref, computed, watch, onMounted } from 'vue'
import PanelMenu from 'primevue/panelmenu'
import Badge from 'primevue/badge'
import { useAdminMenuConfig } from '~/composables/useAdminMenuConfig'
import type { MenuItem } from '~/composables/useAdminMenuConfig'

defineProps<{
  userName: string
  userRole: string
}>()

const route = useRoute()
const { siteName, siteLogo } = useSiteSettings()
const { menuItems } = useAdminMenuConfig()

// ── Collapse state ──
const isCollapsed = ref(false)

function toggleCollapse() {
  isCollapsed.value = !isCollapsed.value
  if (import.meta.client) {
    localStorage.setItem('admin-sidebar-collapsed', isCollapsed.value ? '1' : '0')
  }
}

onMounted(() => {
  if (import.meta.client) {
    const stored = localStorage.getItem('admin-sidebar-collapsed')
    if (stored === '1') isCollapsed.value = true
  }
})

// ── PanelMenu expanded keys ──
const expandedKeys = ref<Record<string, boolean>>({})

function updateExpandedKeys() {
  const newExpanded: Record<string, boolean> = {}
  menuItems.value.forEach((item) => {
    if (item.items && item.key) {
      const hasActiveChild = item.items.some(
        (child) => route.path === child.to || route.path.startsWith(child.to + '/')
      )
      if (hasActiveChild) newExpanded[item.key] = true
    }
  })
  expandedKeys.value = newExpanded
}

watch(() => route.path, updateExpandedKeys, { immediate: true })

// ── Active route detection ──
function isActive(to: string) {
  if (to === '/admin') return route.path === '/admin'
  return route.path === to || route.path.startsWith(to + '/')
}

function isParentActive(item: MenuItem) {
  if (item.to) return isActive(item.to)
  if (item.items) return item.items.some((child) => isActive(child.to))
  return false
}

// ── Collapsed mode: hover accordion ──
const hoveredMenu = ref<string | null>(null)
const openMenu = ref<string | null>(null)

function onMenuMouseEnter(item: MenuItem) {
  hoveredMenu.value = item.key
}

function onMenuMouseLeave(item: MenuItem) {
  if (hoveredMenu.value === item.key) {
    hoveredMenu.value = null
    openMenu.value = null
  }
}

function toggleMenu(item: MenuItem) {
  if (!item.items) return
  openMenu.value = openMenu.value === item.key ? null : item.key
}

watch(() => route.path, () => {
  openMenu.value = null
})

// ── Logo ──
const defaultLogoIcon = computed(() => siteName.value?.slice(0, 1)?.toUpperCase() || 'S')

// ── Logout ──
async function handleLogout() {
  const token = useAuthTokenCookie()
  try {
    if (token.value) {
      await useApi('/auth/logout', {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
      })
    }
  } catch {}
  finally {
    clearAuthSession()
    await navigateTo('/login')
  }
}
</script>

<template>
  <aside
    :class="[
      'sb',
      isCollapsed ? 'sb--collapsed' : '',
    ]"
  >
    <!-- Logo -->
    <div class="sb-brand">
      <NuxtLink to="/admin" class="sb-brand-link">
        <img v-if="siteLogo && !isCollapsed" :src="siteLogo" :alt="siteName" class="sb-brand-img" />
        <img v-else-if="siteLogo && isCollapsed" :src="siteLogo" :alt="siteName" class="sb-brand-favicon" />
        <span v-else class="sb-brand-fallback">{{ defaultLogoIcon }}</span>
      </NuxtLink>
    </div>

    <!-- Gradient divider -->
    <hr class="sb-divider" />

    <!-- Navigation (expanded mode) -->
    <div :class="['sb-nav', isCollapsed ? '' : 'sb-nav--scrollable']">
      <!-- Expanded: PanelMenu -->
      <PanelMenu
        v-if="!isCollapsed"
        v-model:expandedKeys="expandedKeys"
        :model="menuItems as any"
        :multiple="false"
        class="sb-panel-menu"
        :pt="{
          root: { class: '!border-none !gap-0 !p-0' },
          panel: { class: '!border-none !p-0 mb-1' },
          header: { class: '!border-none !p-0' },
          headerAction: { class: 'px-4 py-3 hover:bg-sky-700 hover:text-white transition-colors border-none' },
          headerIcon: { class: 'mr-2' },
          submenuIcon: { class: 'ml-auto' },
          content: { class: 'border-none' },
        }"
      >
        <template #item="{ item }">
          <!-- Item with route (leaf or single-level) -->
          <NuxtLink
            v-if="(item as any).to"
            :to="(item as any).to"
            :class="[
              'sb-link',
              isActive((item as any).to) ? 'sb-link--active' : '',
            ]"
          >
            <span :class="['sb-link-icon', (item as any).icon, isActive((item as any).to) ? 'text-[var(--sidebar-active-text)]' : 'text-[var(--sidebar-text-muted)]']" />
            <span class="sb-link-label">{{ (item as any).label }}</span>
            <Badge v-if="(item as any).badge" class="ml-auto" :value="(item as any).badge" />
          </NuxtLink>

          <!-- Group header (no direct route) -->
          <div v-else class="sb-group-header">
            <span :class="['sb-link-icon', (item as any).icon, 'text-[var(--sidebar-text-muted)]']" />
            <span class="sb-link-label">{{ (item as any).label }}</span>
            <span v-if="(item as any).items" class="pi pi-angle-down text-[var(--sidebar-active-text)] ml-auto" />
          </div>
        </template>
      </PanelMenu>

      <!-- Collapsed: Icon grid with hover accordion -->
      <div v-if="isCollapsed" class="sb-collapsed-nav">
        <div
          v-for="item in menuItems"
          :key="item.key"
          class="sb-collapsed-item"
          @mouseenter="onMenuMouseEnter(item)"
          @mouseleave="onMenuMouseLeave(item)"
        >
          <!-- Icon with children -->
          <div
            v-if="item.items"
            class="sb-collapsed-icon"
            :class="isParentActive(item) ? 'sb-collapsed-icon--active' : ''"
            v-tooltip.right="item.label"
          >
            <i :class="item.icon" />
          </div>

          <!-- Icon without children (direct link) -->
          <NuxtLink
            v-else-if="item.to"
            :to="item.to"
            class="sb-collapsed-icon"
            :class="isActive(item.to) ? 'sb-collapsed-icon--active' : ''"
            v-tooltip.right="item.label"
          >
            <i :class="item.icon" />
          </NuxtLink>

          <!-- Hover accordion panel -->
          <div
            v-if="hoveredMenu === item.key && item.items"
            class="sb-hover-panel"
          >
            <div class="sb-hover-header" @click="toggleMenu(item)">
              <span>{{ item.label }}</span>
              <i
                class="pi pi-angle-down sb-hover-chevron"
                :class="openMenu === item.key ? 'rotate-180' : ''"
              />
            </div>

            <Transition
              enter-active-class="transition-all duration-200 ease-out overflow-hidden"
              leave-active-class="transition-all duration-200 ease-in overflow-hidden"
              enter-from-class="max-h-0 opacity-0"
              enter-to-class="max-h-96 opacity-100"
              leave-from-class="max-h-96 opacity-100"
              leave-to-class="max-h-0 opacity-0"
            >
              <div v-if="openMenu === item.key">
                <NuxtLink
                  v-for="child in item.items"
                  :key="child.to"
                  :to="child.to"
                  :class="[
                    'sb-hover-child',
                    isActive(child.to) ? 'sb-hover-child--active' : '',
                  ]"
                  @click="hoveredMenu = null; openMenu = null"
                >
                  {{ child.label }}
                </NuxtLink>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </div>

    <!-- Collapse toggle -->
    <button type="button" class="sb-toggle" :title="isCollapsed ? 'Mở rộng' : 'Thu gọn'" @click="toggleCollapse">
      <i :class="`pi ${isCollapsed ? 'pi-chevron-right' : 'pi-chevron-left'}`" style="font-size:0.75rem" />
    </button>

    <!-- Footer (user info) -->
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar">
          <span>{{ userName.slice(0, 2).toUpperCase() }}</span>
          <span class="sb-avatar-dot" />
        </div>
        <div v-if="!isCollapsed" class="sb-user-info">
          <span class="sb-user-name">{{ userName }}</span>
          <span class="sb-user-role">{{ userRole }}</span>
        </div>
        <button
          v-if="!isCollapsed"
          type="button"
          class="sb-logout"
          title="Đăng xuất"
          @click="handleLogout"
        >
          <i class="pi pi-sign-out" style="font-size:0.875rem" />
        </button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════════════
   SIDEBAR SHELL — White background, border, rounded (admin-ui)
   ═══════════════════════════════════════════════════════════════ */
.sb {
  display: flex;
  flex-direction: column;
  width: var(--sidebar-width);
  min-height: 100%;
  background: var(--sidebar-bg);
  border: 1px solid var(--sidebar-border);
  border-radius: 12px;
  margin: 8px;
  padding: 8px;
  overflow: hidden;
  flex-shrink: 0;
  transition: width 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

.sb--collapsed {
  width: var(--sidebar-width-collapsed);
}

/* ── Brand / Logo ── */
.sb-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px 4px 8px;
  flex-shrink: 0;
}

.sb-brand-link {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.sb-brand-img {
  height: 70px;
  object-fit: contain;
}

.sb-brand-favicon {
  height: 50px;
  object-fit: contain;
}

.sb-brand-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
  color: #fff;
  font-size: 1.125rem;
  font-weight: 800;
}

/* ── Gradient divider ── */
.sb-divider {
  border: 0;
  height: 1px;
  background: var(--sidebar-gradient-divider);
  margin: 0 0 16px;
  flex-shrink: 0;
}

/* ── Navigation ── */
.sb-nav {
  flex: 1;
  min-height: 0;
}

.sb-nav--scrollable {
  overflow-y: auto;
}

.sb-nav::-webkit-scrollbar {
  width: 4px;
}
.sb-nav::-webkit-scrollbar-track {
  background: transparent;
}
.sb-nav::-webkit-scrollbar-thumb {
  background: rgba(0,0,0,0.08);
  border-radius: 999px;
}

/* ── PanelMenu overrides ── */
.sb-panel-menu :deep(.p-panelmenu-submenu) {
  padding-left: 2rem !important;
  position: relative;
}

.sb-panel-menu :deep(.p-panelmenu-submenu):before {
  background-color: var(--sidebar-submenu-line);
  width: 1px;
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: 24px;
}

/* ── Link item (inside PanelMenu) ── */
.sb-link {
  display: flex;
  align-items: center;
  padding: 8px 16px;
  cursor: pointer;
  border-radius: 6px;
  transition: background 150ms, color 150ms;
  text-decoration: none;
  color: var(--sidebar-text-muted);
  position: relative;
  overflow: visible;
}

.sb-link:hover {
  background: var(--sidebar-hover-bg);
  color: var(--sidebar-text-hover);
}

.sb-link--active {
  color: var(--sidebar-active-text) !important;
}

/* Tree dot indicator for submenu active items */
.sb-panel-menu :deep(.p-panelmenu-submenu) .sb-link::before {
  content: '';
  position: absolute;
  top: 50%;
  left: -0.5rem;
  width: 0.5rem;
  height: 0.5rem;
  border: 1px solid var(--sidebar-submenu-dot);
  border-radius: 100px;
  background-color: #fff;
  transform: translate(-50%, -50%);
  transition: background-color 0.15s ease, border-color 0.15s ease;
  z-index: 1;
  box-sizing: border-box;
}

.sb-panel-menu :deep(.p-panelmenu-submenu) .sb-link.sb-link--active::before {
  background-color: var(--sidebar-submenu-dot-active);
  border-color: var(--sidebar-submenu-dot-active);
}

.sb-link-icon {
  margin-right: 8px;
  font-size: 0.9375rem;
  flex-shrink: 0;
}

.sb-link-label {
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Group header (non-clickable parent) ── */
.sb-group-header {
  display: flex;
  align-items: center;
  padding: 8px 16px;
  color: var(--sidebar-text-muted);
}

/* ═══════════════════════════════════════════════════════════════
   COLLAPSED MODE — Icon grid + hover accordion
   ═══════════════════════════════════════════════════════════════ */
.sb-collapsed-nav {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 0 4px;
}

.sb-collapsed-item {
  position: relative;
  width: 100%;
  display: flex;
  justify-content: center;
}

.sb-collapsed-icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  cursor: pointer;
  transition: background 150ms, color 150ms;
  color: var(--sidebar-text-muted);
  text-decoration: none;
}

.sb-collapsed-icon:hover {
  background: var(--sidebar-hover-bg);
  color: var(--sidebar-text-hover);
}

.sb-collapsed-icon--active {
  background: var(--color-primary);
  color: #fff !important;
}

.sb-collapsed-icon--active:hover {
  background: var(--color-primary-hover);
  color: #fff !important;
}

/* ── Hover accordion panel ── */
.sb-hover-panel {
  position: absolute;
  left: 52px;
  top: 0;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08);
  min-width: 220px;
  z-index: 100;
  border: 1px solid var(--sidebar-border);
}

.sb-hover-header {
  padding: 12px 16px;
  font-size: 0.875rem;
  font-weight: 600;
  border-bottom: 1px solid rgba(0,0,0,0.08);
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;
  border-radius: 8px 8px 0 0;
  transition: background 150ms;
  color: var(--sidebar-text);
}

.sb-hover-header:hover {
  background: #f8fafc;
}

.sb-hover-chevron {
  color: var(--sidebar-active-text);
  transition: transform 200ms ease;
}

.sb-hover-child {
  display: block;
  padding: 8px 16px;
  font-size: 0.8125rem;
  text-decoration: none;
  color: var(--sidebar-text-muted);
  border-radius: 8px;
  margin: 4px 8px;
  transition: background 150ms, color 150ms;
}

.sb-hover-child:hover {
  background: #f1f5f9;
}

.sb-hover-child--active {
  color: var(--sidebar-active-text);
  font-weight: 600;
}

/* ═══════════════════════════════════════════════════════════════
   COLLAPSE TOGGLE BUTTON
   ═══════════════════════════════════════════════════════════════ */
.sb-toggle {
  position: absolute;
  top: 20px;
  right: -12px;
  z-index: 10;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 1px solid var(--sidebar-border);
  background: #fff;
  color: var(--sidebar-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 150ms;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}

.sb-toggle:hover {
  background: var(--sidebar-hover-bg);
  border-color: var(--sidebar-active-text);
  color: var(--sidebar-active-text);
  box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

/* ═══════════════════════════════════════════════════════════════
   FOOTER (User info)
   ═══════════════════════════════════════════════════════════════ */
.sb-footer {
  border-top: 1px solid var(--sidebar-border);
  padding: 8px;
  flex-shrink: 0;
}

.sb-user {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border-radius: 10px;
  transition: background 150ms;
}

.sb--collapsed .sb-user {
  flex-direction: column;
  justify-content: center;
}

.sb-user:hover {
  background: var(--sidebar-hover-bg);
}

.sb-avatar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 800;
  flex-shrink: 0;
  letter-spacing: 0.04em;
}

.sb-avatar-dot {
  position: absolute;
  bottom: -1px;
  right: -1px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #10b981;
  border: 2px solid #fff;
}

.sb-user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sb-user-name {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--sidebar-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sb-user-role {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--sidebar-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.sb-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--sidebar-text-muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 150ms;
}

.sb-logout:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

/* ═══════════════════════════════════════════════════════════════
   DARK MODE
   ═══════════════════════════════════════════════════════════════ */
:global([data-theme="dark"]) .sb {
  background: #0f1f17;
  border-color: rgba(255,255,255,0.06);
}

:global([data-theme="dark"]) .sb-toggle {
  background: #1a2921;
  border-color: rgba(255,255,255,0.08);
  color: #94a3b8;
}

:global([data-theme="dark"]) .sb-hover-panel {
  background: #1a2921;
  border-color: rgba(255,255,255,0.08);
  box-shadow: 0 8px 30px rgba(0,0,0,0.4);
}

:global([data-theme="dark"]) .sb-hover-header {
  color: #e2e8f0;
  border-bottom-color: rgba(255,255,255,0.06);
}

:global([data-theme="dark"]) .sb-hover-header:hover {
  background: rgba(255,255,255,0.04);
}

:global([data-theme="dark"]) .sb-hover-child {
  color: #94a3b8;
}

:global([data-theme="dark"]) .sb-hover-child:hover {
  background: rgba(255,255,255,0.04);
}

:global([data-theme="dark"]) .sb-avatar-dot {
  border-color: #0f1f17;
}

:global([data-theme="dark"]) .sb-footer {
  border-top-color: rgba(255,255,255,0.06);
}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════════ */
@media (max-width: 1080px) {
  .sb {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 300;
  }
}
</style>
