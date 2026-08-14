<script setup lang="ts">
import { dashboardFor } from '~/types/auth'

const auth = useAuthStore()
const { isDark, toggle } = useTheme()
const { settings } = useSiteSettings()
const { t } = useI18n()
const mobileOpen = ref(false)

const brand = computed(() => settings.value.site_name || 'ERIPT LMS')
const dashboardPath = computed(() => dashboardFor(auth.user))
const isStudentUser = computed(() => {
  const roles = auth.user?.roles || (auth.user?.role ? [auth.user.role] : [])
  return auth.isAuthenticated && roles.includes('student') && !roles.includes('admin') && !roles.includes('instructor')
})

/** Thông tin liên hệ / địa chỉ — fallback theo Viện Kinh tế Bưu điện (ERIPT). */
const footerContact = computed(() => ({
  org: settings.value.legal_company_name || 'Viện Kinh tế Bưu điện',
  phone: settings.value.contact_phone || '024-35746799',
  fax: '024-37339432',
  email: settings.value.contact_email || 'namtd@ptit.edu.vn',
  address: settings.value.contact_address
    || settings.value.address
    || 'Số 122 Hoàng Quốc Việt, phường Nghĩa Đô, thành phố Hà Nội',
  addressExtra: 'Cơ sở Đào tạo Hà Đông: Số 96A Trần Phú, phường Hà Đông, thành phố Hà Nội',
  director: 'TS. Trần Đình Nam',
  directorEmail: 'namtd@ptit.edu.vn',
}))

const footerMajors = [
  { name: 'Công nghệ thông tin', slug: 'cong-nghe-thong-tin' },
  { name: 'Quản trị kinh doanh', slug: 'quan-tri-kinh-doanh' },
  { name: 'Điện tử viễn thông', slug: 'dien-tu-vien-thong' },
]

onMounted(() => {
  if (!auth.ready) auth.hydrate()
})
</script>

<template>
  <div class="public-shell">
    <header class="public-header">
      <div class="header-inner">
        <NuxtLink to="/" class="brand" @click="mobileOpen = false">
          <CommonBrandMark size="sm" />
          <strong>{{ brand }}</strong>
        </NuxtLink>

        <nav class="header-nav" :aria-label="t('common.menu')">
          <NuxtLink to="/">{{ t('common.home') }}</NuxtLink>
          <NuxtLink to="/courses">{{ t('common.courses') }}</NuxtLink>
          <NuxtLink to="/paths">{{ t('common.paths') }}</NuxtLink>
        </nav>

        <div class="header-actions">
          <CommonCartButton />
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
            <CommonAccountMenu />
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
        <NuxtLink to="/paths" @click="mobileOpen = false">{{ t('common.paths') }}</NuxtLink>
        <NuxtLink to="/cart" @click="mobileOpen = false">{{ t('common.cart') }}</NuxtLink>
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

    <StudentAiChatbot v-if="isStudentUser" />
    <CommonPublicAiChatbot v-else-if="!auth.isAuthenticated" />

    <footer class="public-footer">
      <div class="footer-inner">
        <div class="footer-brand">
          <strong>{{ brand }}</strong>
          <p>{{ settings.site_description || 'Nền tảng học tập trực tuyến.' }}</p>
        </div>
        <div class="footer-nav">
          <span>{{ t('common.courses') }}</span>
          <NuxtLink
            v-for="major in footerMajors"
            :key="major.slug"
            :to="`/courses?category=${major.slug}`"
          >
            {{ major.name }}
          </NuxtLink>
        </div>
        <div class="footer-contact">
          <span>{{ t('common.contact') }}</span>
          <p class="footer-line">
            <i class="pi pi-phone" />
            <a :href="`tel:${footerContact.phone.replace(/\s|-/g, '')}`">Hotline: {{ footerContact.phone }}</a>
          </p>
          <p class="footer-line">
            <i class="pi pi-print" />
            <span>Fax: {{ footerContact.fax }}</span>
          </p>
          <p class="footer-line">
            <i class="pi pi-envelope" />
            <a :href="`mailto:${footerContact.directorEmail}`">
              Viện trưởng {{ footerContact.director }} — {{ footerContact.directorEmail }}
            </a>
          </p>
          <p class="footer-line">
            <i class="pi pi-map-marker" />
            <span>{{ footerContact.address }}</span>
          </p>
          <p class="footer-line muted-extra">
            <i class="pi pi-map-marker" />
            <span>{{ footerContact.addressExtra }}</span>
          </p>
        </div>
      </div>
      <div class="footer-bottom">© {{ new Date().getFullYear() }} {{ brand }} — {{ footerContact.org }}.</div>
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

.user-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  background: transparent;
  border-radius: 999px;
  cursor: pointer;
}
.user-chip :deep(.p-avatar) {
  width: 2.1rem;
  height: 2.1rem;
  background: var(--brand-soft);
  color: var(--brand);
  font-size: .78rem;
  font-weight: 800;
}
.user-chip:hover :deep(.p-avatar) {
  outline: 2px solid color-mix(in srgb, var(--brand) 35%, transparent);
  outline-offset: 2px;
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
  grid-template-columns: max-content max-content minmax(0, 1fr);
  justify-content: start;
  align-items: start;
  column-gap: 160px;
  row-gap: 24px;
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 36px 0 24px;
}

.footer-brand {
  max-width: 200px;
}

.footer-nav {
  min-width: 170px;
}

.footer-contact {
  min-width: 0;
  max-width: none;
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
  gap: 10px;
}

.footer-inner > div > span:first-child {
  margin-bottom: 4px;
  color: var(--text);
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.footer-line {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
}

.footer-line i {
  margin-top: 3px;
  color: var(--brand);
  font-size: .78rem;
  flex: 0 0 auto;
}

.footer-line.muted-extra {
  opacity: .9;
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
