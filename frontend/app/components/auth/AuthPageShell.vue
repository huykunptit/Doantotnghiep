<script setup lang="ts">
import { useSiteSettings } from '~/composables/useSiteSettings'

defineProps<{
  panelKicker: string
  panelTitle: string
  panelDescription: string
  footText?: string
  footLinkText?: string
  footLinkTo?: string
}>()

const { authPageImage } = useSiteSettings()
</script>

<template>
  <main class="auth-shell">
    <section class="auth-stage">
      <section class="auth-panel">
        <div class="panel-head">
          <p class="panel-kicker">{{ panelKicker }}</p>
          <h2>{{ panelTitle }}</h2>
          <p>{{ panelDescription }}</p>
        </div>

        <slot />

        <div v-if="footText && footLinkText && footLinkTo" class="panel-foot">
          <p>{{ footText }}</p>
          <NuxtLink :to="footLinkTo">{{ footLinkText }}</NuxtLink>
        </div>
      </section>

      <div class="auth-image-side">
        <img v-if="authPageImage" :src="authPageImage" alt="Authentication" class="auth-cover">
        <div v-else class="auth-cover-placeholder"></div>
      </div>
    </section>
  </main>
</template>

