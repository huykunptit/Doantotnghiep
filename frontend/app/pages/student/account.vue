<script setup lang="ts">
definePageMeta({
  layout: 'student',
  middleware: ['auth', 'student'],
})

await navigateTo('/student/profile', { replace: true })
</script>

<template>
  <div />
</template>
