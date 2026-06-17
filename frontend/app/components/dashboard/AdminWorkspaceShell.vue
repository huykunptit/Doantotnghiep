<script setup lang="ts">
import { House } from 'lucide-vue-next'

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
            <House v-if="index === 0" :size="14" :stroke-width="1.75" class="breadcrumb-icon" />
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
