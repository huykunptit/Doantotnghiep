<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  title?: string
  description?: string
  breadcrumb?: string[]
}>(), {
  title: '',
  description: '',
  breadcrumb: () => [],
})

const breadcrumbItems = computed(() => {
  return props.breadcrumb.map((item, index) => ({
    label: item,
    url: index === 0 ? '/admin' : undefined,
    icon: index === 0 ? 'pi pi-home' : undefined
  }))
})

const breadcrumbHome = computed(() => ({
  icon: 'pi pi-home',
  url: '/admin'
}))
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <Breadcrumb
          v-if="breadcrumb.length"
          :home="breadcrumbHome"
          :model="breadcrumbItems"
          class="workspace-breadcrumb"
        />
        <p class="section-kicker">Khu vực quản trị</p>
        <h2>{{ title }}</h2>
        <p v-if="description">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="crud-page-header-actions">
        <slot name="actions" />
      </div>
    </header>

    <slot />
  </section>
</template>

<style scoped>
.workspace-breadcrumb {
  margin-bottom: 1rem;
}
</style>
