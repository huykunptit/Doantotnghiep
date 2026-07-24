<script setup lang="ts">
const { siteLogo, siteName, siteTagline, settings } = useSiteSettings()

const supportEmail = computed(() => settings.value?.contact_email || 'support@example.com')
const supportPhone = computed(() => settings.value?.contact_phone || '1900 9999')
const footerCopyright = computed(
  () => settings.value?.footer_copyright || `© ${new Date().getFullYear()} ${siteName.value}`,
)
</script>

<template>
  <footer class="ft">
    <div class="ft-inner">
      <div class="ft-brand">
        <NuxtLink to="/" class="ft-logo">
          <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="ft-logo-img">
          <span v-else class="ft-logo-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22C12 22 22 12 22 2" />
              <path d="M12 12C12 8 16 4 22 2" />
              <path d="M12 12C8 12 4 16 2 22" />
            </svg>
          </span>
          <span class="ft-logo-text">
            <strong>{{ siteName }}</strong>
            <small>{{ siteTagline || 'Nuôi dưỡng tri thức' }}</small>
          </span>
        </NuxtLink>
        <p class="ft-contact">
          <a :href="`mailto:${supportEmail}`">{{ supportEmail }}</a>
          <span aria-hidden="true">·</span>
          <a :href="`tel:${supportPhone}`">{{ supportPhone }}</a>
        </p>
      </div>

      <nav class="ft-nav" aria-label="Liên kết chân trang">
        <div class="ft-col">
          <p class="ft-heading">Khám phá</p>
          <NuxtLink to="/">Trang chủ</NuxtLink>
          <NuxtLink to="/courses">Khoá học</NuxtLink>
          <NuxtLink to="/career">Lộ trình</NuxtLink>
        </div>
        <div class="ft-col">
          <p class="ft-heading">Tài khoản</p>
          <NuxtLink to="/register">Đăng ký</NuxtLink>
          <NuxtLink to="/profile">Hồ sơ</NuxtLink>
          <NuxtLink to="/orders">Đơn hàng</NuxtLink>
        </div>
      </nav>

      <div class="ft-actions">
        <NuxtLink to="/courses" class="ft-btn ft-btn--primary">Xem khoá học</NuxtLink>
        <NuxtLink to="/register" class="ft-btn ft-btn--ghost">Tạo tài khoản</NuxtLink>
      </div>
    </div>

    <div class="ft-bottom">
      <p>{{ footerCopyright }}</p>
    </div>
  </footer>
</template>

<style scoped>
.ft {
  margin-top: 0;
  border-top: 1px solid var(--line);
  background: var(--surface-strong, #fff);
  color: var(--text);
}

.ft-inner {
  width: min(1120px, calc(100% - 48px));
  margin: 0 auto;
  padding: 36px 0 28px;
  display: grid;
  grid-template-columns: 1.4fr 1fr auto;
  gap: 28px 40px;
  align-items: start;
}

.ft-logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: inherit;
}

.ft-logo-img {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
}

.ft-logo-mark {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--green, #1d9e75);
  color: #fff;
  flex-shrink: 0;
}

.ft-logo-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.ft-logo-text strong {
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text);
}

.ft-logo-text small {
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
}

.ft-contact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 12px 0 0;
  font-size: 0.82rem;
  color: var(--muted);
}

.ft-contact a {
  color: inherit;
  text-decoration: none;
}

.ft-contact a:hover {
  color: var(--green, #1d9e75);
}

.ft-nav {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.ft-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ft-heading {
  margin: 0 0 2px;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--text);
}

.ft-col a {
  font-size: 0.875rem;
  color: var(--muted);
  text-decoration: none;
  transition: color 150ms ease;
}

.ft-col a:hover {
  color: var(--green, #1d9e75);
}

.ft-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 148px;
}

.ft-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 16px;
  border-radius: 8px;
  font-size: 0.84rem;
  font-weight: 700;
  text-decoration: none;
  text-align: center;
  transition: background 150ms ease, border-color 150ms ease, color 150ms ease;
}

.ft-btn--primary {
  background: var(--green, #1d9e75);
  color: #fff;
}

.ft-btn--primary:hover {
  filter: brightness(0.95);
}

.ft-btn--ghost {
  border: 1px solid var(--line);
  color: var(--text);
  background: transparent;
}

.ft-btn--ghost:hover {
  border-color: rgba(29, 158, 117, 0.4);
  color: var(--green, #1d9e75);
}

.ft-bottom {
  border-top: 1px solid var(--line);
}

.ft-bottom p {
  width: min(1120px, calc(100% - 48px));
  margin: 0 auto;
  padding: 14px 0;
  font-size: 0.8rem;
  color: var(--muted);
}

@media (max-width: 900px) {
  .ft-inner {
    grid-template-columns: 1fr 1fr;
    gap: 28px 24px;
  }

  .ft-actions {
    grid-column: 1 / -1;
    flex-direction: row;
    flex-wrap: wrap;
  }

  .ft-btn {
    flex: 1;
    min-width: 140px;
  }
}

@media (max-width: 640px) {
  .ft-inner,
  .ft-bottom p {
    width: calc(100% - 32px);
  }

  .ft-inner {
    grid-template-columns: 1fr;
    padding: 28px 0 22px;
    gap: 22px;
  }

  .ft-nav {
    gap: 16px;
  }

  .ft-actions {
    flex-direction: column;
  }

  .ft-btn {
    width: 100%;
  }
}
</style>
