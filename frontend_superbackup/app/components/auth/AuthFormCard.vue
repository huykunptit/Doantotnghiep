<script setup lang="ts">

defineProps<{
  title: string
  description: string
}>()
</script>

<template>
  <UiCard class="bg-surface-lowest">
    <div class="space-y-7">
      <div class="space-y-3">
        <h1 class="font-headline text-3xl font-bold tracking-[-0.02em] text-on-surface">{{ title }}</h1>
        <p class="text-sm leading-7 text-on-surface-variant">{{ description }}</p>
      </div>
      <slot />
    </div>
  </UiCard>
</template>

