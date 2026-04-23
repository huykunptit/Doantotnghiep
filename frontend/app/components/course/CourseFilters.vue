<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  modelValue: {
    search: string
    category: string
    sort: string
  }
  categories: any[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: { search: string; category: string; sort: string }]
  apply: []
}>()

const localFilters = reactive({ ...props.modelValue })

watch(() => props.modelValue, (value) => Object.assign(localFilters, value), { deep: true })
watch(localFilters, (value) => emit('update:modelValue', { ...value }), { deep: true })
</script>

<template>
  <div class="rounded-[1.5rem] bg-surface-lowest p-4 shadow-ambient">
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_180px_auto]">
      <UiInput v-model="localFilters.search" placeholder="Tìm kiếm khóa học..." />
      <select v-model="localFilters.category" class="w-full rounded-lg border-0 bg-surface-low px-4 py-3 text-sm text-on-surface outline-none transition-all focus:bg-surface-lowest focus:ring-2 focus:ring-surface-tint/20">
        <option value="">Tất cả danh mục</option>
        <template v-for="cat in categories" :key="cat.id">
          <option :value="cat.id">{{ cat.name }}</option>
          <option v-for="child in cat.children || []" :key="child.id" :value="child.id">- {{ child.name }}</option>
        </template>
      </select>
      <select v-model="localFilters.sort" class="w-full rounded-lg border-0 bg-surface-low px-4 py-3 text-sm text-on-surface outline-none transition-all focus:bg-surface-lowest focus:ring-2 focus:ring-surface-tint/20">
        <option value="newest">Mới nhất</option>
        <option value="price_asc">Giá tăng dần</option>
        <option value="price_desc">Giá giảm dần</option>
        <option value="popular">Nhiều học viên</option>
      </select>
      <UiButton class="w-full lg:w-auto" @click="emit('apply')">Áp dụng</UiButton>
    </div>
  </div>
</template>

