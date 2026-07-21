<script setup lang="ts">
withDefaults(defineProps<{
  padded?: boolean
  hoverable?: boolean
}>(), {
  padded: true,
  hoverable: false,
})
</script>

<template>
  <div :class="['ui-card', hoverable && 'ui-card--hoverable']">
    <div :class="padded ? 'ui-card-body' : ''">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.ui-card {
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
  transition: transform 250ms ease, box-shadow 250ms ease;
}

.ui-card--hoverable:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(31, 49, 43, 0.08);
}

.ui-card-body {
  padding: 24px;
}

@media (max-width: 640px) {
  .ui-card-body { padding: 16px; }
}

[data-theme="dark"] .ui-card {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
}
</style>
