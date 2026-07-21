<script setup lang="ts">
definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})
</script>

<template>
  <AdminPageStub title="Yêu cầu rút tiền" />
</template>
