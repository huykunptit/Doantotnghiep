<script setup lang="ts">
import { useSiteSettings } from '~/composables/useSiteSettings'

defineProps<{
  panelKicker?: string
  panelTitle: string
  panelDescription: string
  footText?: string
  footLinkText?: string
  footLinkTo?: string
  heroTitle?: string
  heroSubtitle?: string
}>()

const { siteName, brandLogo, authPageImage, siteTagline } = useSiteSettings()
</script>

<template>
  <main class="auth-shell">

    <!-- Left: background image with overlay -->
    <div class="auth-bg" aria-hidden="true">
      <img v-if="authPageImage" :src="authPageImage" alt="" class="auth-bg-img">
      <div class="auth-bg-overlay" />
      <div class="auth-bg-content">
        <div class="auth-bg-card">
          <h2 class="auth-bg-title">
            {{ heroTitle || `Chào mừng bạn đến với hệ thống học tập trực tuyến ${siteName}` }}
          </h2>
          <p class="auth-bg-sub">
            {{ heroSubtitle || siteTagline || 'Nền tảng học tập hiện đại, linh hoạt và hiệu quả.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Right: form panel -->
    <aside class="auth-panel">
      <!-- Panel header: logo -->
      <div class="auth-panel-head">
        <NuxtLink to="/" class="auth-logo">
          <img v-if="brandLogo" :src="brandLogo" alt="Logo" class="auth-logo-img">
          <span v-else class="auth-logo-text">{{ siteName }}</span>
        </NuxtLink>
      </div>

      <!-- Panel body: form -->
      <div class="auth-panel-body">
        <div class="auth-form-wrap">
          <!-- Heading -->
          <div class="auth-heading">
            <p v-if="panelKicker" class="auth-kicker">{{ panelKicker }}</p>
            <h1 class="auth-title">{{ panelTitle }}</h1>
            <p class="auth-desc">{{ panelDescription }}</p>
          </div>

          <!-- Form slot -->
          <div class="auth-body">
            <slot />
          </div>

          <!-- Footer link -->
          <div v-if="footText && footLinkText && footLinkTo" class="auth-foot">
            <span>{{ footText }}</span>
            <NuxtLink :to="footLinkTo" class="auth-foot-link">{{ footLinkText }}</NuxtLink>
          </div>
        </div>
      </div>

      <!-- Panel footer -->
      <div class="auth-panel-footer">
        <p>© 2026 <strong>{{ siteName }}</strong></p>
      </div>
    </aside>

  </main>
</template>

<style scoped>
/* ── Shell ── */
.auth-shell {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #0d1f1a;
}

/* ── Background (left) ── */
.auth-bg {
  flex: 1;
  position: relative;
  overflow: hidden;
  height: 100vh;
}

.auth-bg-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.auth-bg-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    rgba(5, 30, 22, 0.72) 0%,
    rgba(10, 40, 30, 0.55) 60%,
    rgba(5, 25, 18, 0.65) 100%
  );
}

.auth-bg-content {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 56px 52px;
}

.auth-bg-card {
  max-width: 520px;
  background: rgba(0, 0, 0, 0.38);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  padding: 28px 32px;
}

.auth-bg-title {
  margin: 0 0 10px;
  font-size: 1.625rem;
  font-weight: 700;
  line-height: 1.3;
  color: #fff;
  letter-spacing: -0.02em;
}

.auth-bg-sub {
  margin: 0;
  font-size: 0.9375rem;
  color: rgba(255, 255, 255, 0.78);
  line-height: 1.6;
}

/* ── Panel (right sidebar) ── */
.auth-panel {
  width: clamp(340px, 30vw, 460px);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  background: var(--surface-strong, #fff);
  border-left: 1px solid var(--line, rgba(0,0,0,0.09));
  position: relative;
  z-index: 10;
}

/* ── Panel header ── */
.auth-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 32px 0;
  flex-shrink: 0;
}

.auth-logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}

.auth-logo-img {
  height: 36px;
  width: auto;
  object-fit: contain;
}

.auth-logo-text {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text, #0d1f1a);
  letter-spacing: -0.02em;
}

/* ── Panel body ── */
.auth-panel-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 20px 32px;
  overflow: hidden;
}

.auth-form-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ── Heading ── */
.auth-heading {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.auth-kicker {
  margin: 0;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--green, #1D9E75);
}

.auth-title {
  margin: 0;
  font-size: 1.375rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  color: var(--text, #0d1f1a);
  line-height: 1.25;
}

.auth-desc {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.55;
  color: var(--muted, #6b7c73);
}

/* ── Body ── */
.auth-body {
  display: flex;
  flex-direction: column;
}

/* ── Foot link ── */
.auth-foot {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--line, rgba(0,0,0,0.09));
  font-size: 0.8125rem;
  color: var(--muted, #6b7c73);
}

.auth-foot-link {
  font-weight: 700;
  color: var(--green-deep, #085041);
  text-decoration: none;
}

.auth-foot-link:hover {
  text-decoration: underline;
}

/* ── Panel footer ── */
.auth-panel-footer {
  padding: 12px 32px 16px;
  text-align: center;
  font-size: 0.75rem;
  color: var(--muted, #6b7c73);
  border-top: 1px solid var(--line, rgba(0,0,0,0.07));
  flex-shrink: 0;
}

.auth-panel-footer strong {
  color: var(--green-deep, #085041);
  font-weight: 600;
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .auth-bg { display: none; }
  .auth-panel {
    width: 100%;
    height: 100vh;
    border-left: none;
    overflow-y: auto;
  }
}

@media (max-width: 540px) {
  .auth-panel-head { padding: 16px 20px 0; }
  .auth-panel-body { padding: 16px 20px; }
  .auth-panel-footer { padding: 10px 20px 14px; }
  .auth-title { font-size: 1.25rem; }
}

/* ── Dark mode ── */
[data-theme="dark"] .auth-panel {
  background: var(--surface-strong);
  border-left-color: rgba(255, 255, 255, 0.08);
}
</style>
