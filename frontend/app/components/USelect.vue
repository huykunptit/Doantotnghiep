<script setup lang="ts">
defineOptions({ name: 'USelect' })

const props = withDefaults(defineProps<{
  modelValue?: string | number | (string | number)[]
  options?: Array<{ label: string; value: string | number; [key: string]: any }>
  disabled?: boolean
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  placeholder?: string
  searchInput?: boolean | object
  multiple?: boolean
}>(), {
  options: () => [],
  disabled: false,
  size: 'md',
  placeholder: 'Chọn...',
  searchInput: true,
  multiple: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | (string | number)[]]
}>()
</script>

<template>
  <USelectMenu
    :model-value="modelValue"
    :items="options"
    value-key="value"
    :disabled="disabled"
    :size="size"
    :placeholder="placeholder"
    :search-input="searchInput"
    :multiple="multiple"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
