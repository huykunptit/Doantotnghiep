<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
await navigateTo('/student', { replace: true })
</script>

<template>
  <div class="flex items-center justify-center min-h-[50vh]">
    <span class="material-symbols-outlined animate-spin text-[var(--muted)]">progress_activity</span>
  </div>
</template>
