<template>
  <div class="rounded-[1.5rem] bg-surface-lowest p-8 text-center shadow-ambient">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
      <slot name="icon">•</slot>
    </div>
    <h3 class="font-headline text-2xl font-bold tracking-[-0.02em] text-on-surface">{{ title }}</h3>
    <p v-if="description" class="mt-3 text-sm leading-7 text-on-surface-variant">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

