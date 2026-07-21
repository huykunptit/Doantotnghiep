<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  subtitle?: string
  eyebrow?: string
  icon?: string
}>(), {
  subtitle: '',
  eyebrow: '',
  icon: '',
})
</script>

<template>
  <header class="admin-page-header">
    <div class="admin-page-header__main">
      <slot name="breadcrumb" />
      <div class="admin-page-header__title-row">
        <slot name="icon">
          <span v-if="icon" class="admin-page-header__icon" aria-hidden="true">
            <i :class="icon" />
          </span>
        </slot>
        <div class="admin-page-header__copy">
          <p v-if="eyebrow || $slots.eyebrow" class="admin-page-header__eyebrow">
            <slot name="eyebrow">{{ eyebrow }}</slot>
          </p>
          <h1><slot name="title">{{ title }}</slot></h1>
          <p v-if="subtitle || $slots.subtitle" class="admin-page-header__subtitle">
            <slot name="subtitle">{{ subtitle }}</slot>
          </p>
          <div v-if="$slots.meta" class="admin-page-header__meta">
            <slot name="meta" />
          </div>
        </div>
      </div>
    </div>
    <div v-if="$slots.actions" class="admin-page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.admin-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.25rem;
  color: var(--text, var(--p-text-color));
}

.admin-page-header__main,
.admin-page-header__copy {
  min-width: 0;
}

.admin-page-header__title-row {
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
}

.admin-page-header__icon {
  display: grid;
  flex: 0 0 auto;
  width: 2.75rem;
  height: 2.75rem;
  place-items: center;
  border: 1px solid var(--p-primary-200);
  border-radius: var(--p-border-radius-xl, 0.75rem);
  background: var(--p-primary-50);
  color: var(--p-primary-600);
}

.admin-page-header__eyebrow {
  margin: 0 0 0.25rem;
  color: var(--p-primary-600);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

h1 {
  margin: 0;
  font-size: clamp(1.45rem, 3vw, 2rem);
  font-weight: 750;
  letter-spacing: -0.035em;
  line-height: 1.15;
}

.admin-page-header__subtitle {
  max-width: 48rem;
  margin: 0.45rem 0 0;
  color: var(--muted, var(--p-text-muted-color));
  font-size: 0.9rem;
  line-height: 1.55;
}

.admin-page-header__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.admin-page-header__actions {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

:global(.dark) .admin-page-header__icon,
:global([data-theme="dark"]) .admin-page-header__icon {
  border-color: color-mix(in srgb, var(--p-primary-400) 28%, transparent);
  background: color-mix(in srgb, var(--p-primary-500) 14%, transparent);
  color: var(--p-primary-300);
}

@media (max-width: 640px) {
  .admin-page-header {
    flex-direction: column;
  }

  .admin-page-header__actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
