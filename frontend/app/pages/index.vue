<script setup lang="ts">
// Simple redirect to admin dashboard
await navigateTo('/admin', { replace: true })
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-[#f8fafc]">
    <div class="flex flex-col items-center gap-3 text-[var(--muted)]">
      <i class="pi pi-spin pi-spinner text-2xl" />
      <span class="text-xs font-semibold">Đang chuyển hướng...</span>
    </div>
  </div>
</template>
