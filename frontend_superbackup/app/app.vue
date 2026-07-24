<script setup lang="ts">
import { watchEffect } from 'vue'
import { useSiteSettings } from '~/composables/useSiteSettings'

const { themeColorPrimary, themeColorDeep } = useSiteSettings()

const hexToRgb = (hex: string) => {
  if (!hex || typeof hex !== 'string') return '15, 110, 140'
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex.trim());
  return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : '15, 110, 140';
}

if (import.meta.client) {
  watchEffect(() => {
    const primary = themeColorPrimary.value || '#0F6E8C'
    const deep = themeColorDeep.value || '#0b5167'
    const primaryRgb = hexToRgb(primary)
    const deepRgb = hexToRgb(deep)
    
    const styleId = 'dynamic-theme-vars'
    let styleEl = document.getElementById(styleId)
    if (!styleEl) {
      styleEl = document.createElement('style')
      styleEl.id = styleId
      document.head.appendChild(styleEl)
    }
    
    styleEl.innerHTML = `
      :root {
        --green: ${primary} !important;
        --green-rgb: ${primaryRgb} !important;
        --green-deep: ${deep} !important;
        --green-deep-rgb: ${deepRgb} !important;
        --primary: ${primary} !important;
        --primary-rgb: ${primaryRgb} !important;
        --primary-deep: ${deep} !important;
        --primary-deep-rgb: ${deepRgb} !important;
        --green-soft: rgba(${primaryRgb}, 0.1) !important;
      }
    `
  })
}
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
