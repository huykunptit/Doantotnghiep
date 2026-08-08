<script setup lang="ts">
const { settings, load: loadSiteSettings } = useSiteSettings()
const authImage = computed(() => settings.value.auth_page_image || '/images/auth-campus.png?v=3')

onMounted(() => {
  document.documentElement.classList.add('auth-lock-scroll')
  loadSiteSettings()
})
onBeforeUnmount(() => {
  document.documentElement.classList.remove('auth-lock-scroll')
})
</script>

<template>
  <div class="auth-shell">
    <div class="auth-bg" aria-hidden="true" :style="{ backgroundImage: `linear-gradient(105deg, rgba(8, 18, 28, .78) 0%, rgba(8, 18, 28, .55) 48%, rgba(8, 18, 28, .42) 100%), url('${authImage}')` }" />
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
  /* background-image comes from the inline :style (site-settings auth_page_image, or the default). */
  background-color: #0b1c24;
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
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

@media (max-width: 900px) {
  .auth-shell,
  .auth-content {
    height: auto;
    min-height: 100dvh;
    overflow: visible;
  }
}
</style>

<style>
html.auth-lock-scroll,
html.auth-lock-scroll body {
  height: 100%;
  overflow: hidden;
}

/* Small screens: let the page scroll normally so nothing (e.g. the
   submit button, once the mobile keyboard opens) gets stranded
   below the fold with no way to reach it. */
@media (max-width: 900px) {
  html.auth-lock-scroll,
  html.auth-lock-scroll body {
    height: auto;
    overflow: auto;
  }
}
</style>
