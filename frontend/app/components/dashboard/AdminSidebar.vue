<script setup lang="ts">
import { LogOut, ChevronRight } from 'lucide-vue-next'

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
  <aside class="sl">
    <!-- Brand -->
    <div class="sl-brand">
      <NuxtLink to="/admin" class="sl-brand-link">
        <div class="sl-brand-logo-wrap">
          <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="sl-brand-img">
          <div v-else class="sl-brand-icon">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22C12 22 22 12 22 2" /><path d="M12 12C12 8 16 4 22 2" /><path d="M12 12C8 12 4 16 2 22" />
            </svg>
          </div>
        </div>
        <div class="sl-brand-text">
          <span class="sl-brand-name">{{ siteName }}</span>
          <span class="sl-brand-badge">Admin Panel</span>
        </div>
      </NuxtLink>
    </div>

    <!-- Navigation -->
    <nav class="sl-nav" aria-label="Admin navigation">
      <div v-for="group in groups" :key="group.label" class="sl-group">
        <p class="sl-group-label">{{ group.label }}</p>
        <div class="sl-group-items">
          <NuxtLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            class="sl-item"
            :class="{ 'is-active': isActive(item.to) }"
          >
            <span class="sl-item-icon">
              <SylvaIcon :name="item.icon" :size="16" :stroke-width="isActive(item.to) ? 2.25 : 1.75" />
            </span>
            <span class="sl-item-label">{{ item.label }}</span>
            <ChevronRight v-if="isActive(item.to)" :size="13" :stroke-width="2.5" class="sl-item-chevron" />
          </NuxtLink>
        </div>
      </div>
    </nav>

    <!-- Footer -->
    <div class="sl-footer">
      <div class="sl-user">
        <div class="sl-avatar">
          <span>{{ userName.slice(0, 2).toUpperCase() }}</span>
          <span class="sl-avatar-dot" />
        </div>
        <div class="sl-user-info">
          <span class="sl-user-name">{{ userName }}</span>
          <span class="sl-user-role">{{ userRole }}</span>
        </div>
        <button type="button" class="sl-logout" title="Đăng xuất" @click="handleLogout">
          <LogOut :size="15" :stroke-width="1.75" />
        </button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* ── Shell ── */
.sl {
  display: flex;
  flex-direction: column;
  width: 240px;
  min-height: 100%;
  background: #0b1329; /* Deep Dark Blue */
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  overflow-y: auto;
  overflow-x: hidden;
  flex-shrink: 0;
}

/* ── Brand ── */
.sl-brand {
  padding: 16px 14px 12px;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.sl-brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  padding: 6px 8px;
  border-radius: 10px;
  transition: background 150ms;
}
.sl-brand-link:hover { background: rgba(255, 255, 255, 0.04); }

.sl-brand-logo-wrap {
  flex-shrink: 0;
}

.sl-brand-img {
  height: 32px;
  width: 32px;
  border-radius: 8px;
  object-fit: contain;
}

.sl-brand-icon {
  width: 32px; height: 32px;
  border-radius: 9px;
  background: linear-gradient(135deg, var(--green) 0%, #0d7a5a 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(29,158,117,0.35);
}

.sl-brand-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sl-brand-name {
  font-size: 0.9375rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: #f8fafc; /* Slate 50 */
  line-height: 1.2;
}

.sl-brand-badge {
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: #5dcaa5;
  background: rgba(93, 202, 165, 0.12);
  padding: 1px 6px;
  border-radius: 999px;
  width: fit-content;
}

/* ── Navigation ── */
.sl-nav {
  flex: 1;
  padding: 12px 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow-y: auto;
  min-height: 0;
}

.sl-group {
  margin-bottom: 14px;
}

.sl-group:last-child { margin-bottom: 0; }

.sl-group-label {
  margin: 0 0 4px 10px;
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: #64748b; /* Slate 500 */
  opacity: 0.85;
}

.sl-group-items {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sl-item {
  display: flex;
  align-items: center;
  gap: 9px;
  height: 37px;
  padding: 0 10px;
  border-radius: 10px;
  text-decoration: none;
  color: #94a3b8; /* Slate 400 */
  font-size: 0.84rem;
  font-weight: 500;
  transition: background 120ms, color 120ms;
  position: relative;
}

.sl-item:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #f8fafc;
}

.sl-item.is-active {
  background: rgba(29, 158, 117, 0.15);
  color: #4ade80;
  font-weight: 700;
}

.sl-item.is-active::before {
  content: '';
  position: absolute;
  left: 0; top: 7px; bottom: 7px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: #22c55e;
}

.sl-item-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px; height: 20px;
  flex-shrink: 0;
  color: inherit;
}

.sl-item-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}

.sl-item-chevron {
  opacity: 0.5;
  flex-shrink: 0;
}

/* ── Footer ── */
.sl-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding: 10px 10px;
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.01);
}

.sl-user {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 8px;
  border-radius: 10px;
  transition: background 150ms;
  cursor: default;
}
.sl-user:hover { background: rgba(255, 255, 255, 0.04); }

.sl-avatar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--green-soft) 0%, rgba(255, 255, 255, 0.08) 100%);
  color: #4ade80;
  font-size: 0.72rem;
  font-weight: 800;
  flex-shrink: 0;
  letter-spacing: 0.04em;
}

.sl-avatar-dot {
  position: absolute;
  bottom: 0; right: 0;
  width: 9px; height: 9px;
  border-radius: 50%;
  background: #22c55e;
  border: 2px solid #0b1329;
}

.sl-user-info {
  flex: 1;
  min-width: 0;
}

.sl-user-name {
  display: block;
  font-size: 0.8125rem;
  font-weight: 700;
  color: #f8fafc;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sl-user-role {
  display: block;
  font-size: 0.67rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.sl-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 150ms, color 150ms;
}

.sl-logout:hover {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}
</style>

