<script setup lang="ts">
import { computed } from 'vue'
import { House } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  title?: string
  description?: string
  breadcrumb?: string[]
}>(), {
  title: '',
  description: '',
  breadcrumb: () => [],
})

const formattedBreadcrumb = computed(() => {
  return props.breadcrumb.map((item, index) => {
    const to = index === 0 ? '/instructor' : undefined
    return {
      label: item,
      to,
      icon: index === 0 ? House : undefined
    }
  })
})
</script>

<template>
  <section class="crud-page">
    <header class="crud-page-header dashboard-card">
      <div>
        <UBreadcrumb
          v-if="breadcrumb.length"
          :items="formattedBreadcrumb"
          class="mb-4"
        />
        <p class="section-kicker">Khu vực giảng viên</p>
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
