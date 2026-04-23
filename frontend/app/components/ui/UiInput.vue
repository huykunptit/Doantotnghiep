<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue?: string | number
  label?: string
  placeholder?: string
  type?: string
  disabled?: boolean
  error?: string
}>(), {
  modelValue: '',
  label: '',
  placeholder: '',
  type: 'text',
  disabled: false,
  error: '',
})

const attrs = useAttrs()
const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()
</script>

<template>
  <label class="block space-y-2">
    <span v-if="props.label" class="text-sm font-semibold text-on-surface-variant">{{ props.label }}</span>
    <input
      v-bind="attrs"
      :value="props.modelValue"
      :type="props.type"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :class="[
        'w-full rounded-lg border-0 bg-surface-low px-4 py-3 text-sm text-on-surface outline-none transition-all duration-200 placeholder:text-outline/70',
        props.error ? 'bg-error-container/70 focus:ring-2 focus:ring-error/15' : 'focus:bg-surface-lowest focus:ring-2 focus:ring-surface-tint/20',
        props.disabled && 'cursor-not-allowed bg-surface-high text-outline',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
    <span v-if="props.error" class="text-xs font-medium text-error">{{ props.error }}</span>
  </label>
</template>

