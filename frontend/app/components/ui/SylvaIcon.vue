<script setup lang="ts">
// PrimeIcons wrapper - maps name to pi- classes
const props = withDefaults(defineProps<{
  name: string
  size?: number
  class?: string
}>(), {
  size: 20,
})

// Convert kebab-case/snake_case to pi- class
function toIconClass(name: string): string {
  return `pi pi-${name.toLowerCase().replace(/_/g, '-')}`
}

const iconClass = computed(() => [
  toIconClass(props.name),
  props.class,
])

const sizeStyle = computed(() => ({
  fontSize: `${props.size}px`,
}))
</script>

<template>
  <i :class="iconClass" :style="sizeStyle" />
</template>
