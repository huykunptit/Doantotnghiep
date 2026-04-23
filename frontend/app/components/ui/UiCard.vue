<script setup lang="ts">
withDefaults(defineProps<{
  padded?: boolean
}>(), {
  padded: true,
})
</script>

<template>
  <div class="rounded-[1.5rem] bg-surface-lowest border border-surface-dim shadow-sm transition-all duration-300">
    <div :class="padded ? 'p-6 sm:p-8' : ''">
      <slot />
    </div>
  </div>
</template>

