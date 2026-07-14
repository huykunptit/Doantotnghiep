<script setup lang="ts">
// Icons removed - using PrimeIcons

defineProps<{
  userName: string
  userRole: string
}>()

const route = useRoute()
const { groups, supportItems } = useAdminNavigation()
const { siteName, siteLogo } = useSiteSettings()

function isActive(to: string) {
  const [toPath, toQueryStr] = to.split('?')
  
  if (toPath === '/admin') {
    return route.path === '/admin'
  }
  
  const pathMatches = route.path === toPath || route.path.startsWith(toPath + '/')
  
  if (!pathMatches) return false
  
  if (toQueryStr) {
    const params = new URLSearchParams(toQueryStr)
    for (const [key, val] of params.entries()) {
      if (route.query[key] !== val) return false
    }
    return true
  }
  
  if (route.path === toPath && Object.keys(route.query).length > 0) {
    return false
  }
  
  return true
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
          <i class="pi pi-sign-out" style="font-size:0.9375rem" />
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
  background: linear-gradient(180deg, #064e3b 0%, #052e22 100%); /* Premium Deep Dark Forest Green Gradient */
  border-right: 1px solid rgba(255, 255, 255, 0.05);
  overflow-y: auto;
  overflow-x: hidden;
  flex-shrink: 0;
}

/* Scrollbar styling for a polished look */
.sl::-webkit-scrollbar {
  width: 5px;
}
.sl::-webkit-scrollbar-track {
  background: transparent;
}
.sl::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.08);
  border-radius: 99px;
}
.sl::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.16);
}

/* ── Brand ── */
.sl-brand {
  padding: 20px 16px 16px;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.sl-brand-link {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  padding: 8px;
  border-radius: 12px;
  transition: background 150ms;
}
.sl-brand-link:hover {
  background: rgba(255, 255, 255, 0.03);
}

.sl-brand-logo-wrap {
  flex-shrink: 0;
}

.sl-brand-img {
  height: 36px;
  width: 36px;
  border-radius: 10px;
  object-fit: contain;
  border: 1.5px solid rgba(255, 255, 255, 0.1);
}

.sl-brand-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #0F6E8C 0%, #1D9E75 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(15, 110, 140, 0.3);
}

.sl-brand-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sl-brand-name {
  font-size: 1.025rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #ffffff;
  line-height: 1.2;
}

.sl-brand-badge {
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #5DCAA5; /* Accent mint green */
  background: rgba(93, 202, 165, 0.12);
  padding: 1.5px 8px;
  border-radius: 999px;
  width: fit-content;
}

/* ── Navigation ── */
.sl-nav {
  flex: 1;
  padding: 18px 12px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  overflow-y: auto;
  min-height: 0;
}

.sl-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sl-group-label {
  margin: 0 0 6px 12px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: #ffffff;
 /* soft mint green */
}

.sl-group-items {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sl-item {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 38px;
  padding: 0 12px;
  border-radius: 10px;
  text-decoration: none;
  color: rgba(240, 250, 247, 0.65);
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 150ms;
  position: relative;
}

.sl-item-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: rgba(240, 250, 247, 0.5);
  transition: color 150ms;
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
  transition: transform 150ms, opacity 150ms;
}

/* Hover States */
.sl-item:hover {
  background: rgba(255, 255, 255, 0.04);
  color: #ffffff;
}

.sl-item:hover .sl-item-icon {
  color: #5DCAA5;
}

.sl-item:hover .sl-item-chevron {
  opacity: 0.8;
  transform: translateX(2px);
}

/* Active State */
.sl-item.is-active {
  background: linear-gradient(135deg, #0F6E8C 0%, #0a4f64 100%); /* Primary Teal Gradient */
  color: #ffffff;
  font-weight: 600;
}

.sl-item.is-active .sl-item-icon {
  color: #5DCAA5; /* Accent Mint Green */
}

.sl-item.is-active .sl-item-chevron {
  opacity: 0.95;
  color: #5DCAA5;
}

.sl-item.is-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3.5px;
  border-radius: 0 4px 4px 0;
  background: #5DCAA5;
  box-shadow: 0 0 8px rgba(93, 202, 165, 0.8);
}

/* ── Footer ── */
.sl-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding: 12px;
  flex-shrink: 0;
  background: rgba(0, 0, 0, 0.15);
}

.sl-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border-radius: 12px;
  transition: background 150ms;
  cursor: default;
}
.sl-user:hover {
  background: rgba(255, 255, 255, 0.03);
}

.sl-avatar {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1D9E75 0%, #0F6E8C 100%);
  color: #ffffff;
  font-size: 0.75rem;
  font-weight: 800;
  flex-shrink: 0;
  letter-spacing: 0.04em;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.sl-avatar-dot {
  position: absolute;
  bottom: -1px;
  right: -1px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #5DCAA5;
  border: 2px solid #031c15; /* matching footer background overlay */
}

.sl-user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sl-user-name {
  display: block;
  font-size: 0.825rem;
  font-weight: 700;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sl-user-role {
  display: block;
  font-size: 0.65rem;
  font-weight: 600;
  color: rgba(240, 250, 247, 0.45);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.sl-logout {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: rgba(240, 250, 247, 0.5);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 150ms;
}

.sl-logout:hover {
  background: rgba(239, 68, 68, 0.15);
  color: #F87171;
}

/* Ensure styling persists beautifully in dark mode too */
:global([data-theme="dark"]) .sl {
  background: linear-gradient(180deg, #031c15 0%, #03130e 100%);
  border-right: 1px solid rgba(255, 255, 255, 0.04);
}

:global([data-theme="dark"]) .sl-avatar-dot {
  border-color: #03130e;
}
</style>

