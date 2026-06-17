<script setup lang="ts">
import * as LucideIcons from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  name: string
  size?: number
  strokeWidth?: number
  class?: string
}>(), {
  size: 20,
  strokeWidth: 1.5,
})

// Convert kebab-case or snake_case to PascalCase
function toPascal(name: string): string {
  return name
    .replace(/[-_]/g, ' ')
    .split(' ')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join('')
}

const iconComponent = computed(() => {
  const key = toPascal(props.name) as keyof typeof LucideIcons
  return (LucideIcons[key] as any) ?? LucideIcons.HelpCircle
})
</script>

<template>
  <component
    :is="iconComponent"
    :size="size"
    :stroke-width="strokeWidth"
    :class="props.class"
  />
</template>
