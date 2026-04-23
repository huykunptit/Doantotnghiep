<template>
  <div class="flex min-h-screen flex-col bg-surface text-on-surface">
    <AppHeader />
    <main class="flex-1 pt-16">
      <slot />
    </main>
    <AppFooter />
    <AIChatbot />
  </div>
</template>

<script setup lang="ts">
import AIChatbot from '../components/AIChatbot.vue'
import AppFooter from '../components/common/AppFooter.vue'
import AppHeader from '../components/common/AppHeader.vue'
</script>
