<script setup lang="ts">
import { dashboardFor } from '~/types/auth'

const auth = useAuthStore()
const { isDark, toggle } = useTheme()
const { settings } = useSiteSettings()
const { t } = useI18n()
const mobileOpen = ref(false)

const brand = computed(() => settings.value.site_name || 'Sylva LMS')
const dashboardPath = computed(() => dashboardFor(auth.user))

onMounted(() => {
  if (!auth.ready) auth.hydrate()
})
</script>

<template>
  <div class="public-shell">
    <header class="public-header">
      <div class="header-inner">
        <NuxtLink to="/" class="brand" @click="mobileOpen = false">
          <span class="brand-mark"><i class="pi pi-sparkles" /></span>
          <strong>{{ brand }}</strong>
        </NuxtLink>

        <nav class="header-nav" :aria-label="t('common.menu')">
          <NuxtLink to="/">{{ t('common.home') }}</NuxtLink>
          <NuxtLink to="/courses">{{ t('common.courses') }}</NuxtLink>
          <NuxtLink to="/login">{{ t('common.learn') }}</NuxtLink>
        </nav>

        <div class="header-actions">
          <LocaleSwitcher />
          <Button
            :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
            severity="secondary"
            text
            rounded
            :aria-label="t('common.theme')"
            @click="toggle"
          />
          <template v-if="auth.isAuthenticated">
            <Button :label="auth.user?.name || t('common.dashboard')" icon="pi pi-user" severity="secondary" outlined @click="navigateTo(dashboardPath)" />
          </template>
          <template v-else>
            <NuxtLink to="/login" class="ghost-link">{{ t('common.login') }}</NuxtLink>
            <Button :label="t('common.register')" icon="pi pi-arrow-right" icon-pos="right" @click="navigateTo('/register')" />
          </template>
          <Button
            icon="pi pi-bars"
            severity="secondary"
            text
            rounded
            class="mobile-toggle"
            :aria-label="t('common.openMenu')"
            @click="mobileOpen = true"
          />
        </div>
      </div>
    </header>

    <Drawer v-model:visible="mobileOpen" position="right" class="public-drawer" :header="t('common.menu')">
      <nav class="drawer-nav">
        <NuxtLink to="/" @click="mobileOpen = false">{{ t('common.home') }}</NuxtLink>
        <NuxtLink to="/courses" @click="mobileOpen = false">{{ t('common.courses') }}</NuxtLink>
        <NuxtLink v-if="auth.isAuthenticated" :to="dashboardPath" @click="mobileOpen = false">{{ t('common.dashboard') }}</NuxtLink>
        <template v-else>
          <NuxtLink to="/login" @click="mobileOpen = false">{{ t('common.login') }}</NuxtLink>
          <NuxtLink to="/register" @click="mobileOpen = false">{{ t('common.register') }}</NuxtLink>
        </template>
      </nav>
    </Drawer>

    <main class="public-main">
      <slot />
    </main>

    <footer class="public-footer">
      <div class="footer-inner">
        <div>
          <strong>{{ brand }}</strong>
          <p>{{ settings.site_description || 'Nền tảng học tập thích nghi, nuôi dưỡng tri thức lâu dài.' }}</p>
        </div>
        <div>
          <span>{{ t('common.courses') }}</span>
          <NuxtLink to="/courses">{{ t('common.courses') }}</NuxtLink>
          <NuxtLink to="/register">{{ t('common.register') }}</NuxtLink>
          <NuxtLink to="/login">{{ t('common.login') }}</NuxtLink>
        </div>
        <div>
          <span>Contact</span>
          <a v-if="settings.contact_email" :href="`mailto:${settings.contact_email}`">{{ settings.contact_email }}</a>
          <span v-if="settings.contact_phone">{{ settings.contact_phone }}</span>
          <span v-if="settings.address">{{ settings.address }}</span>
        </div>
      </div>
      <div class="footer-bottom">© {{ new Date().getFullYear() }} {{ brand }}.</div>
    </footer>
  </div>
</template>

<style scoped>
.public-shell {
  min-height: 100dvh;
  background: transparent;
  color: var(--text);
}

.public-header {
  position: sticky;
  top: 0;
  z-index: 30;
  border-bottom: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
  background: color-mix(in srgb, var(--surface) 88%, transparent);
  backdrop-filter: blur(16px);
}

.header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  width: min(1180px, calc(100% - 32px));
  height: 68px;
  margin: 0 auto;
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--brand);
  color: white;
}

.brand strong {
  font-size: .95rem;
  letter-spacing: -.02em;
}

.header-nav {
  display: flex;
  gap: 22px;
}

.header-nav a {
  color: var(--text-muted);
  font-size: .82rem;
  font-weight: 600;
}

.header-nav a:hover,
.header-nav a.router-link-active {
  color: var(--brand);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ghost-link {
  color: var(--text);
  font-size: .8rem;
  font-weight: 650;
  padding: 0 8px;
}

.mobile-toggle {
  display: none;
}

.public-main {
  min-height: calc(100dvh - 68px - 220px);
}

.public-footer {
  border-top: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px);
}

.footer-inner {
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr;
  gap: 28px;
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 36px 0 24px;
}

.footer-inner strong {
  display: block;
  margin-bottom: 8px;
  color: var(--text);
  font-size: 1rem;
}

.footer-inner p,
.footer-inner a,
.footer-inner span {
  color: var(--text-muted);
  font-size: .78rem;
  line-height: 1.7;
}

.footer-inner > div {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.footer-inner > div > span:first-child {
  margin-bottom: 4px;
  color: var(--text);
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.footer-bottom {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 14px 0 22px;
  border-top: 1px solid var(--border);
  color: var(--text-muted);
  font-size: .7rem;
}

.drawer-nav {
  display: grid;
  gap: 4px;
}

.drawer-nav a {
  padding: 12px 10px;
  border-radius: 10px;
  color: var(--text);
  font-weight: 600;
}

.drawer-nav a:hover {
  background: var(--surface-hover);
}

:global(.public-drawer) {
  width: min(280px, 88vw) !important;
}

@media (max-width: 820px) {
  .header-nav,
  .ghost-link {
    display: none;
  }

  .mobile-toggle {
    display: inline-flex;
  }

  .footer-inner {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}
</style>
