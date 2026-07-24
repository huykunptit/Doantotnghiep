<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  subtitle?: string
  icon?: string
  padded?: boolean
}>(), {
  title: '',
  subtitle: '',
  icon: '',
  padded: true,
})
</script>

<template>
  <section class="admin-detail-panel" :class="{ 'admin-detail-panel--padded': padded }">
    <header v-if="title || $slots.title || $slots.actions || icon" class="admin-detail-panel__header">
      <div class="admin-detail-panel__heading">
        <slot name="icon">
          <span v-if="icon" class="admin-detail-panel__icon"><i :class="icon" /></span>
        </slot>
        <div>
          <h3 v-if="title || $slots.title"><slot name="title">{{ title }}</slot></h3>
          <p v-if="subtitle || $slots.subtitle"><slot name="subtitle">{{ subtitle }}</slot></p>
        </div>
      </div>
      <div v-if="$slots.actions" class="admin-detail-panel__actions">
        <slot name="actions" />
      </div>
    </header>
    <div class="admin-detail-panel__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="admin-detail-panel__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>

<style scoped>
.admin-detail-panel {
  border: 1px solid var(--line, var(--p-content-border-color));
  border-radius: var(--p-border-radius-lg, 0.625rem);
  background: var(--surface-ground, var(--p-surface-50));
  color: var(--text, var(--p-text-color));
}

.admin-detail-panel--padded {
  padding: 1rem;
}

.admin-detail-panel__header,
.admin-detail-panel__heading,
.admin-detail-panel__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.admin-detail-panel__header {
  justify-content: space-between;
  margin-bottom: 0.875rem;
}

.admin-detail-panel__icon {
  display: grid;
  width: 2rem;
  height: 2rem;
  place-items: center;
  border-radius: var(--p-border-radius-md, 0.5rem);
  background: var(--p-primary-100);
  color: var(--p-primary-700);
}

.admin-detail-panel h3,
.admin-detail-panel p {
  margin: 0;
}

.admin-detail-panel h3 {
  font-size: 0.9rem;
  font-weight: 700;
}

.admin-detail-panel p {
  margin-top: 0.2rem;
  color: var(--muted, var(--p-text-muted-color));
  font-size: 0.76rem;
}

.admin-detail-panel__body {
  min-width: 0;
}

.admin-detail-panel__footer {
  padding-top: 0.875rem;
  margin-top: 0.875rem;
  border-top: 1px solid var(--line, var(--p-content-border-color));
}

:global(.dark) .admin-detail-panel,
:global([data-theme="dark"]) .admin-detail-panel {
  background: var(--surface-ground, var(--p-surface-900));
}
</style>
