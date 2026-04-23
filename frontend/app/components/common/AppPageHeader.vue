<script setup lang="ts">
defineProps<{
  eyebrow?: string
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-col gap-4 lg:gap-5">
    <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.28em] text-primary">{{ eyebrow }}</p>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="font-headline text-3xl font-bold tracking-[-0.02em] text-on-surface sm:text-4xl">{{ title }}</h1>
        <p v-if="description" class="mt-3 max-w-2xl text-sm leading-7 text-on-surface-variant">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-3">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

