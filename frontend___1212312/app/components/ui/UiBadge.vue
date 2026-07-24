<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'accent'
  pill?: boolean
}>(), {
  variant: 'default',
  pill: false,
})
</script>

<template>
  <span :class="['ui-badge', `ui-badge--${variant}`, pill && 'ui-badge--pill']">
    <slot />
  </span>
</template>

<style scoped>
.ui-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  line-height: 1.6;
  white-space: nowrap;
}

.ui-badge--pill { border-radius: 999px; }

.ui-badge--default {
  background: var(--surface);
  color: var(--muted);
}

.ui-badge--success {
  background: var(--green-soft);
  color: var(--green-deep);
}

.ui-badge--warning {
  background: rgba(233, 162, 59, 0.12);
  color: #9a6117;
}

.ui-badge--danger {
  background: var(--danger-soft);
  color: var(--danger);
}

.ui-badge--info {
  background: var(--secondary-soft);
  color: var(--secondary);
}

.ui-badge--accent {
  background: var(--accent-soft);
  color: var(--accent);
}
</style>
