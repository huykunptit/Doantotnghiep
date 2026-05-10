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
        <nav class="crud-breadcrumb mb-4" aria-label="Breadcrumb">
        <span v-for="(item, index) in breadcrumb" :key="`${item}-${index}`" :class="{ 'is-active': index === breadcrumb.length - 1 }">
          <span v-if="index === 0" class="material-symbols-outlined breadcrumb-icon">home</span>
          {{ item }}
        </span>
      </nav>

        <p class="section-kicker">Khu vực quản trị</p>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
      </div>

     
    </header>

    <slot />
  </section>
</template>
