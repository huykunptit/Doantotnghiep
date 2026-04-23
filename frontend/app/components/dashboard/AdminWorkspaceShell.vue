<script setup lang="ts">
defineProps<{
  title: string
  description: string
  breadcrumb: string[]
}>()
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Khu vực quản trị</p>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
      </div>

      <nav class="crud-breadcrumb" aria-label="Breadcrumb">
        <span v-for="(item, index) in breadcrumb" :key="`${item}-${index}`">
          {{ item }}
        </span>
      </nav>
    </header>

    <slot />
  </section>
</template>
