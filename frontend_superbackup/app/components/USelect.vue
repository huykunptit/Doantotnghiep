<script setup lang="ts">
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'

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
  <MultiSelect
    v-if="multiple"
    :model-value="modelValue"
    :options="options"
    option-label="label"
    option-value="value"
    :disabled="disabled"
    :placeholder="placeholder"
    :filter="!!searchInput"
    @update:model-value="emit('update:modelValue', $event)"
  />
  <Dropdown
    v-else
    :model-value="modelValue"
    :options="options"
    option-label="label"
    option-value="value"
    :disabled="disabled"
    :placeholder="placeholder"
    :filter="!!searchInput"
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
