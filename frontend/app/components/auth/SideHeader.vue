<script setup lang="ts">
const { isDark, toggle } = useTheme()
const { t } = useI18n()
</script>

<template>
  <div class="auth-side-header">
    <NuxtLink to="/" class="auth-side-brand">
      <CommonBrandMark size="sm" />
      <span>
        <strong>ERIPT LMS</strong>
      </span>
    </NuxtLink>
    <div class="auth-side-actions">
      <LocaleSwitcher />
      <Button
        :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
        severity="secondary"
        text
        rounded
        :aria-label="t('common.theme')"
        @click="toggle"
      />
    </div>
  </div>
</template>

<style scoped>
.auth-side-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 22px;
}

.auth-side-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text);
  text-decoration: none;
}

.auth-side-brand strong {
  display: block;
  font-size: 1.05rem;
  font-weight: 750;
  letter-spacing: -.02em;
  line-height: 1.1;
}

.auth-side-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>
