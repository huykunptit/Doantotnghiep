<script setup lang="ts">
const year = new Date().getFullYear()

const quickLinks = [
  { label: 'Bảng điều khiển', to: '/admin' },
  { label: 'Khóa học', to: '/admin/courses' },
  { label: 'Người dùng', to: '/admin/users' },
  { label: 'Cài đặt', to: '/admin/settings' },
]
</script>

<template>
  <footer class="af">
    <div class="af-inner">

      <!-- Brand -->
      <div class="af-brand">
        <div class="af-brand-mark" />
        <div>
          <p class="af-brand-name">EduPress Admin</p>
          <p class="af-brand-sub"> PTIT {{ year }}</p>
        </div>
      </div>

      <!-- Quick links -->
      <nav class="af-links">
        <NuxtLink
          v-for="link in quickLinks"
          :key="link.to"
          :to="link.to"
          class="af-link"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <!-- Right: status + version -->
      <div class="af-right">
        <div class="af-status">
          <span class="af-status-dot" />
          <span class="af-status-text">Hệ thống hoạt động</span>
        </div>
        <span class="af-version">v1.1.0</span>
      </div>

    </div>
  </footer>
</template>

<style scoped>
.af {
  border: 1px solid rgba(255, 255, 255, 0.74);
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(16px);
  box-shadow: 0 24px 60px -30px rgba(17, 17, 17, 0.1);
  border-radius: 24px;
  margin-top: 4px;
}

.af-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  padding: 18px 26px;
}

/* ── Brand ── */
.af-brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
.af-brand-mark {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: var(--green);
  flex-shrink: 0;
  position: relative;
}
.af-brand-mark::after {
  content: '';
  position: absolute;
  inset: 7px;
  border-radius: 3px;
  background: rgba(255, 255, 255, 0.35);
}
.af-brand-name {
  margin: 0;
  font-size: 0.88rem;
  font-weight: 800;
  color: var(--text, #111111);
  letter-spacing: -0.02em;
}
.af-brand-sub {
  margin: 2px 0 0;
  font-size: 0.72rem;
  color: var(--muted, #5f675f);
}

/* ── Links ── */
.af-links {
  display: flex;
  align-items: center;
  gap: 4px;
}
.af-link {
  height: 32px;
  padding: 0 12px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--muted, #5f675f);
  text-decoration: none;
  transition: background 150ms ease, color 150ms ease;
}
.af-link:hover {
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep, var(--green-deep));
}

/* ── Right ── */
.af-right {
  display: flex;
  align-items: center;
  gap: 14px;
}
.af-status {
  display: flex;
  align-items: center;
  gap: 7px;
}
.af-status-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--green);
  box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.18);
  animation: af-pulse 2.4s ease-in-out infinite;
}
@keyframes af-pulse {
  0%, 100% { box-shadow: 0 0 0 3px rgba(var(--green-rgb), 0.18); }
  50% { box-shadow: 0 0 0 5px rgba(var(--green-rgb), 0.08); }
}
.af-status-text {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--green-deep, var(--green-deep));
}
.af-version {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--muted, #5f675f);
  background: rgba(17, 17, 17, 0.05);
  border-radius: 8px;
  padding: 3px 9px;
  letter-spacing: 0.04em;
}

/* ── Responsive ── */
@media (max-width: 760px) {
  .af-inner { flex-direction: column; align-items: flex-start; gap: 14px; padding: 16px 18px; }
  .af-links { flex-wrap: wrap; }
}
</style>
