<script setup lang="ts">
onMounted(() => {
  document.documentElement.classList.add('auth-lock-scroll')
})
onBeforeUnmount(() => {
  document.documentElement.classList.remove('auth-lock-scroll')
})
</script>

<template>
  <div class="auth-shell">
    <div class="auth-bg" aria-hidden="true" />
    <main class="auth-content">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.auth-shell {
  position: relative;
  height: 100dvh;
  min-height: 100dvh;
  overflow: hidden;
}

.auth-bg {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(105deg, rgba(8, 18, 28, .78) 0%, rgba(8, 18, 28, .55) 48%, rgba(8, 18, 28, .42) 100%),
    url('/images/auth-campus.png?v=3') center / cover no-repeat;
  background-color: #0b1c24;
}

.auth-content {
  position: relative;
  z-index: 1;
  height: 100dvh;
  min-height: 100dvh;
  display: flex;
  align-items: stretch;
  overflow: hidden;
}
</style>

<style>
html.auth-lock-scroll,
html.auth-lock-scroll body {
  height: 100%;
  overflow: hidden;
}
</style>
