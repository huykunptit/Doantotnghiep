<script setup lang="ts">
import AppHeader from '~/components/common/AppHeader.vue'
import AppFooter from '~/components/common/AppFooter.vue'
import AIChatbot from '~/components/AIChatbot.vue'
</script>

<template>
  <div class="public-shell">
    <AppHeader />
    <main class="public-main">
      <slot />
    </main>
    <AppFooter />
    <AIChatbot />
  </div>
</template>

<style scoped>
.public-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: var(--page-bg, #f6f6f6);
  color: #1e293b;
}

/* AppHeader is fixed h-16 (64px) */
.public-main {
  flex: 1;
  padding-top: 4rem; /* offset for fixed topbar */
}

:global(.dark) .public-shell {
  background-color: #0f0f11;
  color: #f1f5f9;
}
</style>
