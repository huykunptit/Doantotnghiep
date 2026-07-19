<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue?: string | number
  label?: string
  placeholder?: string
  type?: string
  disabled?: boolean
  error?: string
  hint?: string
  size?: 'md' | 'lg'
}>(), {
  modelValue: '',
  label: '',
  placeholder: '',
  type: 'text',
  disabled: false,
  error: '',
  hint: '',
  size: 'md',
})

const attrs = useAttrs()
const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()
</script>

<template>
  <label class="ui-input-wrap">
    <span v-if="props.label" class="ui-input-label">{{ props.label }}</span>
    <input
      v-bind="attrs"
      :value="props.modelValue"
      :type="props.type"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :class="[
        'ui-input',
        props.size === 'lg' ? 'ui-input--lg' : 'ui-input--md',
        props.error && 'ui-input--error',
        props.disabled && 'ui-input--disabled',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    >
    <span v-if="props.error" class="ui-input-error">{{ props.error }}</span>
    <span v-else-if="props.hint" class="ui-input-hint">{{ props.hint }}</span>
  </label>
</template>

<style scoped>
.ui-input-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ui-input-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.ui-input {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--surface-strong, #fff);
  color: var(--text);
  font: inherit;
  outline: none;
  transition: border-color 150ms, box-shadow 150ms, background 150ms;
}

.ui-input::placeholder { color: var(--muted); }

.ui-input--md {
  height: 40px;
  padding: 0 14px;
  font-size: 0.875rem;
}

.ui-input--lg {
  height: 48px;
  padding: 0 16px;
  font-size: 0.9375rem;
}

.ui-input:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px var(--green-soft);
}

.ui-input--error {
  border-color: var(--danger);
  background: var(--danger-soft);
}

.ui-input--error:focus {
  border-color: var(--danger);
  box-shadow: 0 0 0 3px var(--danger-soft);
}

.ui-input--disabled {
  cursor: not-allowed;
  opacity: 0.6;
  background: var(--surface);
}

.ui-input-error {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--danger);
}

.ui-input-hint {
  font-size: 0.75rem;
  color: var(--muted);
}
</style>
