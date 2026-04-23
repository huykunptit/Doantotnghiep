<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info'
}>(), {
  variant: 'default',
})

const classes = {
  default: 'bg-surface-high text-on-surface',
  success: 'bg-secondary/10 text-secondary',
  warning: 'bg-primary/10 text-primary',
  danger: 'bg-error-container text-error',
  info: 'bg-tertiary/10 text-tertiary',
}
</script>

<template>
  <span :class="['inline-flex items-center gap-1 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]', classes[variant]]">
    <slot />
  </span>
</template>

