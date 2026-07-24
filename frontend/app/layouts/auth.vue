<script setup lang="ts">
const { isDark, toggle } = useTheme()
const { t } = useI18n()
</script>

<template>
  <div class="auth-shell">
    <header class="auth-header">
      <NuxtLink to="/" class="auth-brand"><span><i class="pi pi-sparkles" /></span><strong>Sylva LMS</strong></NuxtLink>
      <div class="auth-actions">
        <LocaleSwitcher />
        <Button :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'" severity="secondary" text rounded :aria-label="t('common.theme')" @click="toggle" />
      </div>
    </header>
    <main class="auth-content"><slot /></main>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100dvh;
  background: transparent;
}
.auth-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
  padding: 0 clamp(18px, 4vw, 56px);
}
.auth-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.auth-brand {
  display: flex;
  align-items: center;
  gap: 9px;
  color: var(--text);
}
.auth-brand > span {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: var(--brand);
  color: white;
}
.auth-brand strong {
  font-size: 1rem;
  font-weight: 700;
}
.auth-content {
  display: grid;
  place-items: center;
  min-height: calc(100dvh - 64px);
  padding: 24px;
}
</style>
