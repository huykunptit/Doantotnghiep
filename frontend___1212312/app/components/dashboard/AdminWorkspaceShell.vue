<script setup lang="ts">
defineProps<{
  title?: string
  subtitle?: string
  breadcrumbs?: { label: string; to?: string }[]
}>()
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header & Breadcrumbs -->
    <div v-if="title || breadcrumbs" class="flex flex-col gap-1.5">
      <!-- Breadcrumbs -->
      <nav v-if="breadcrumbs && breadcrumbs.length" class="flex items-center gap-2 text-xs text-[var(--muted)] font-medium">
        <template v-for="(bc, idx) in breadcrumbs" :key="bc.label">
          <NuxtLink v-if="bc.to" :to="bc.to" class="hover:text-[var(--primary)] transition-colors">{{ bc.label }}</NuxtLink>
          <span v-else>{{ bc.label }}</span>
          <i v-if="idx < breadcrumbs.length - 1" class="pi pi-angle-right text-[10px]" />
        </template>
      </nav>

      <!-- Title / Subtitle -->
      <div v-if="title" class="flex flex-col">
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">{{ title }}</h1>
        <p v-if="subtitle" class="text-sm text-[var(--muted)] mt-0.5">{{ subtitle }}</p>
      </div>
    </div>

    <!-- Main Content Slot -->
    <div>
      <slot />
    </div>
  </div>
</template>
