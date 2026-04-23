<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue?: string
  label?: string
  placeholder?: string
  rows?: number
  disabled?: boolean
  error?: string
}>(), {
  modelValue: '',
  label: '',
  placeholder: '',
  rows: 4,
  disabled: false,
  error: '',
})

const attrs = useAttrs()
const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <label class="block space-y-2">
    <span v-if="props.label" class="text-sm font-semibold text-on-surface-variant">{{ props.label }}</span>
    <textarea
      v-bind="attrs"
      :value="props.modelValue"
      :rows="props.rows"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :class="[
        'w-full rounded-xl border bg-surface-lowest px-4 py-3 text-sm text-on-surface outline-none transition placeholder:text-outline',
        props.error ? 'border-error/30 focus:border-error focus:ring-4 focus:ring-error-container' : 'border-surface-dim focus:border-primary focus:ring-4 focus:ring-primary/10',
        props.disabled && 'cursor-not-allowed bg-surface-low text-on-surface-variant',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <span v-if="props.error" class="text-xs font-medium text-error">{{ props.error }}</span>
  </label>
</template>

