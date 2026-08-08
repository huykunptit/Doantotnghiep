<script setup lang="ts">
withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg'
}>(), {
  size: 'md',
})

const { settings, load } = useSiteSettings()

const src = computed(() =>
  settings.value.site_logo || settings.value.brand_logo || settings.value.logo || '/images/eript-logo.png',
)

onMounted(() => {
  load()
})
</script>

<template>
  <span class="brand-mark" :class="size">
    <img :src="src" alt="ERIPT" width="48" height="48">
  </span>
</template>

<style scoped>
.brand-mark {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  overflow: hidden;
  border-radius: 11px;
  background: #fff;
  border: 1px solid var(--border);
}
.brand-mark img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
  display: block;
  padding: 2px;
}
.brand-mark.sm { width: 32px; height: 32px; border-radius: 9px; }
.brand-mark.md { width: 40px; height: 40px; }
.brand-mark.lg { width: 48px; height: 48px; }
</style>
