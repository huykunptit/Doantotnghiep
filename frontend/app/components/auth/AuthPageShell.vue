<script setup lang="ts">
import { useSiteSettings } from '~/composables/useSiteSettings'

defineProps<{
  panelKicker: string
  panelTitle: string
  panelDescription: string
  footText?: string
  footLinkText?: string
  footLinkTo?: string
}>()

const { siteName, authPageImage } = useSiteSettings()
</script>

<template>
  <main class="auth-shell">
    <div class="auth-stage">

      <!-- Left: form panel -->
      <section class="auth-panel">
        <!-- Brand -->
        <NuxtLink to="/" class="auth-brand">
          <div class="auth-brand-icon">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 22C12 22 22 12 22 2" /><path d="M12 12C12 8 16 4 22 2" /><path d="M12 12C8 12 4 16 2 22" />
            </svg>
          </div>
          <span class="auth-brand-name">{{ siteName }}</span>
        </NuxtLink>

        <!-- Heading -->
        <div class="auth-head">
          <p class="auth-kicker">{{ panelKicker }}</p>
          <h1 class="auth-title">{{ panelTitle }}</h1>
          <p class="auth-desc">{{ panelDescription }}</p>
        </div>

        <!-- Form slot -->
        <div class="auth-body">
          <slot />
        </div>

        <!-- Footer -->
        <div v-if="footText && footLinkText && footLinkTo" class="auth-foot">
          <span>{{ footText }}</span>
          <NuxtLink :to="footLinkTo" class="auth-foot-link">{{ footLinkText }}</NuxtLink>
        </div>
      </section>

      <!-- Right: visual panel -->
      <div class="auth-visual" aria-hidden="true">
        <img v-if="authPageImage" :src="authPageImage" alt="" class="auth-visual-img">
        <div v-else class="auth-visual-placeholder">
          <div class="auth-visual-content">
            <div class="auth-visual-orb auth-visual-orb-1" />
            <div class="auth-visual-orb auth-visual-orb-2" />
            <div class="auth-visual-card">
              <div class="auth-visual-quote">
                <p>"Kiến thức là gốc rễ — hãy để Sylva nuôi dưỡng nó mỗi ngày."</p>
                <span>— Sylva LMS</span>
              </div>
              <div class="auth-visual-stats">
                <div class="auth-visual-stat"><strong>50+</strong><span>Khoá học</span></div>
                <div class="auth-visual-stat"><strong>2K+</strong><span>Học viên</span></div>
                <div class="auth-visual-stat"><strong>95%</strong><span>Hài lòng</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</template>

<style scoped>
/* ── Shell ── */
.auth-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: var(--bg);
}

/* ── Stage ── */
.auth-stage {
  display: grid;
  grid-template-columns: minmax(360px, 440px) minmax(360px, 1fr);
  width: 100%;
  max-width: 1060px;
  min-height: 620px;
  background: var(--surface-strong, #fff);
  border-radius: 20px;
  box-shadow: 0 24px 60px -20px rgba(31, 49, 43, 0.15);
  border: 1px solid var(--line);
  overflow: hidden;
}

/* ── Panel ── */
.auth-panel {
  display: flex;
  flex-direction: column;
  padding: 40px 44px;
  gap: 24px;
}

/* ── Brand ── */
.auth-brand {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
}

.auth-brand-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 8px;
  background: var(--green);
  color: #fff;
  flex-shrink: 0;
}

.auth-brand-name {
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 0.9375rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text);
}

/* ── Heading ── */
.auth-head { display: flex; flex-direction: column; gap: 6px; }

.auth-kicker {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--green);
}

.auth-title {
  margin: 0;
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.04em;
  color: var(--text);
  line-height: 1.2;
}

.auth-desc {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.65;
  color: var(--muted);
}

/* ── Body ── */
.auth-body { flex: 1; }

/* ── Foot ── */
.auth-foot {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-top: 16px;
  border-top: 1px solid var(--line);
  font-size: 0.875rem;
  color: var(--muted);
}

.auth-foot-link {
  font-weight: 700;
  color: var(--green-deep);
  text-decoration: none;
}

.auth-foot-link:hover { text-decoration: underline; }

/* ── Visual panel ── */
.auth-visual {
  position: relative;
  overflow: hidden;
  background: linear-gradient(140deg, var(--green-deep) 0%, #0F6E56 50%, #1D9E75 100%);
}

.auth-visual-img {
  position: absolute;
  inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
}

.auth-visual-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.auth-visual-content {
  position: relative;
  width: 100%;
  max-width: 320px;
}

.auth-visual-orb {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.05);
}

.auth-visual-orb-1 {
  width: 280px; height: 280px;
  top: -80px; right: -80px;
}

.auth-visual-orb-2 {
  width: 160px; height: 160px;
  bottom: -40px; left: -60px;
}

.auth-visual-card {
  position: relative;
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 16px;
  padding: 28px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.auth-visual-quote p {
  margin: 0 0 8px;
  font-size: 1.0625rem;
  font-weight: 500;
  line-height: 1.55;
  color: rgba(255, 255, 255, 0.9);
  font-style: italic;
}

.auth-visual-quote span {
  font-size: 0.8125rem;
  color: #9FE1CB;
  font-weight: 600;
}

.auth-visual-stats {
  display: flex;
  gap: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  padding-top: 18px;
}

.auth-visual-stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.auth-visual-stat strong {
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  color: #9FE1CB;
}

.auth-visual-stat span {
  font-size: 0.72rem;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .auth-stage {
    grid-template-columns: 1fr;
    max-width: 480px;
  }
  .auth-visual { display: none; }
}

@media (max-width: 540px) {
  .auth-shell { padding: 16px; align-items: flex-start; padding-top: 32px; }
  .auth-panel { padding: 28px 24px; }
  .auth-title { font-size: 1.5rem; }
}
</style>
