<script setup lang="ts">
const props = withDefaults(defineProps<{
  columns?: 1 | 2 | 3 | 4
  minColumnWidth?: string
  compact?: boolean
  as?: 'div' | 'form' | 'fieldset'
}>(), {
  columns: 2,
  minColumnWidth: '15rem',
  compact: false,
  as: 'div',
})

defineEmits<{
  submit: [event: Event]
}>()

const gridStyle = computed(() => ({
  '--admin-form-columns': String(props.columns),
  '--admin-form-min-width': props.minColumnWidth,
}))
</script>

<template>
  <component
    :is="as"
    class="admin-form-grid"
    :class="{ 'admin-form-grid--compact': compact }"
    :style="gridStyle"
    @submit="$emit('submit', $event)"
  >
    <slot />
  </component>
</template>

<style scoped>
.admin-form-grid {
  display: grid;
  grid-template-columns: repeat(
    var(--admin-form-columns),
    minmax(min(100%, var(--admin-form-min-width)), 1fr)
  );
  gap: 1rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.admin-form-grid--compact {
  gap: 0.75rem;
}

.admin-form-grid :deep(.admin-form-grid__full) {
  grid-column: 1 / -1;
}

@media (max-width: 768px) {
  .admin-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
