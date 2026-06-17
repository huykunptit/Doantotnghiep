<script setup lang="ts">
import { LogOut } from 'lucide-vue-next'

defineProps<{
  userName: string
  userRole: string
}>()

const route = useRoute()
const { groups, supportItems } = useAdminNavigation()
const { siteName, siteLogo } = useSiteSettings()

function isActive(path: string) {
  if (path === '/admin') return route.path === '/admin'
  return route.path.startsWith(path)
}

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
  <aside class="sl-sidebar">
    <!-- Brand -->
    <div class="sl-brand">
      <NuxtLink to="/admin" class="sl-brand-link">
        <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="sl-brand-logo">
        <div v-else class="sl-brand-icon">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 22C12 22 22 12 22 2" /><path d="M12 12C12 8 16 4 22 2" /><path d="M12 12C8 12 4 16 2 22" />
          </svg>
        </div>
        <div>
          <span class="sl-brand-name">{{ siteName }}</span>
          <span class="sl-brand-role">Admin Panel</span>
        </div>
      </NuxtLink>
    </div>

    <div class="sl-divider" />

    <!-- Navigation -->
    <nav class="sl-nav" aria-label="Admin navigation">
      <div v-for="group in groups" :key="group.label" class="sl-nav-group">
        <p class="sl-group-label">{{ group.label }}</p>
        <div class="sl-nav-items">
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="sl-nav-item"
            :class="{ 'is-active': isActive(item.to) }"
          >
            <span class="sl-nav-icon">
              <SylvaIcon :name="item.icon" :size="16" :stroke-width="1.75" />
            </span>
            <span class="sl-nav-label">{{ item.label }}</span>
          </NuxtLink>
        </div>
      </div>
    </nav>

    <!-- Footer profile -->
    <div class="sl-footer">
      <div class="sl-avatar">{{ userName.slice(0, 2).toUpperCase() }}</div>
      <div class="sl-footer-info">
        <span class="sl-footer-name">{{ userName }}</span>
        <span class="sl-footer-role">{{ userRole }}</span>
      </div>
      <button type="button" class="sl-logout" title="Đăng xuất" @click="handleLogout">
        <LogOut :size="16" :stroke-width="1.75" />
      </button>
    </div>
  </aside>
</template>

<style scoped>
/* ── Shell ── */
.sl-sidebar {
  display: flex;
  flex-direction: column;
  width: 240px;
  min-height: 100%;
  background: var(--surface-strong, #fff);
  border-right: 1px solid var(--line);
  overflow-y: auto;
  overflow-x: hidden;
  flex-shrink: 0;
}

[data-theme="dark"] .sl-sidebar {
  background: #0F2219;
  border-right-color: var(--line);
}

/* ── Brand ── */
.sl-brand {
  padding: 18px 16px 14px;
  flex-shrink: 0;
}

.sl-brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}

.sl-brand-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: 10px;
  background: var(--green);
  color: #fff;
  flex-shrink: 0;
}

.sl-brand-logo {
  height: 34px;
  border-radius: 8px;
  object-fit: contain;
  flex-shrink: 0;
}

.sl-brand-name {
  display: block;
  font-family: 'Outfit', sans-serif;
  font-size: 0.9375rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text);
  line-height: 1.2;
}

.sl-brand-role {
  display: block;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--muted);
}

/* ── Divider ── */
.sl-divider {
  height: 1px;
  background: var(--line);
  margin: 0 16px;
  flex-shrink: 0;
}

/* ── Navigation ── */
.sl-nav {
  flex: 1;
  padding: 12px 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow-y: auto;
  min-height: 0;
}

.sl-nav-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 8px;
}

.sl-group-label {
  margin: 0 0 4px;
  padding: 0 8px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--muted);
}

.sl-nav-items {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sl-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 36px;
  padding: 0 10px;
  border-radius: 8px;
  text-decoration: none;
  color: var(--muted);
  font-size: 0.8375rem;
  font-weight: 500;
  transition: background 150ms, color 150ms;
  position: relative;
}

.sl-nav-item:hover {
  background: rgba(var(--primary-rgb), 0.06);
  color: var(--text);
}

.sl-nav-item.is-active {
  background: var(--green-soft);
  color: var(--green-deep);
  font-weight: 600;
}

.sl-nav-item.is-active::before {
  content: '';
  position: absolute;
  left: 0; top: 6px; bottom: 6px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--green);
}

.sl-nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px; height: 22px;
  flex-shrink: 0;
  color: inherit;
}

.sl-nav-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

/* ── Footer ── */
.sl-footer {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border-top: 1px solid var(--line);
  flex-shrink: 0;
}

.sl-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: 999px;
  background: var(--green-soft);
  color: var(--green-deep);
  font-size: 0.78rem;
  font-weight: 700;
  flex-shrink: 0;
}

.sl-footer-info {
  flex: 1;
  min-width: 0;
}

.sl-footer-name {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sl-footer-role {
  display: block;
  font-size: 0.7rem;
  color: var(--muted);
}

.sl-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 150ms, color 150ms;
}

.sl-logout:hover {
  background: var(--danger-soft);
  color: var(--danger);
}
</style>
