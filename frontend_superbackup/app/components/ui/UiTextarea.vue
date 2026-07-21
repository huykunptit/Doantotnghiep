<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue?: string
  label?: string
  placeholder?: string
  rows?: number
  disabled?: boolean
  error?: string
  hint?: string
}>(), {
  modelValue: '',
  label: '',
  placeholder: '',
  rows: 4,
  disabled: false,
  error: '',
  hint: '',
})

const attrs = useAttrs()
const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <label class="ui-textarea-wrap">
    <span v-if="props.label" class="ui-textarea-label">{{ props.label }}</span>
    <textarea
      v-bind="attrs"
      :value="props.modelValue"
      :rows="props.rows"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :class="[
        'ui-textarea',
        props.error && 'ui-textarea--error',
        props.disabled && 'ui-textarea--disabled',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <span v-if="props.error" class="ui-textarea-error">{{ props.error }}</span>
    <span v-else-if="props.hint" class="ui-textarea-hint">{{ props.hint }}</span>
  </label>
</template>

<style scoped>
.ui-textarea-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ui-textarea-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.ui-textarea {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: var(--surface-strong, #fff);
  color: var(--text);
  font: inherit;
  font-size: 0.875rem;
  padding: 10px 14px;
  outline: none;
  resize: vertical;
  transition: border-color 150ms, box-shadow 150ms, background 150ms;
}

.ui-textarea::placeholder { color: var(--muted); }

.ui-textarea:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px var(--green-soft);
}

.ui-textarea--error {
  border-color: var(--danger);
  background: var(--danger-soft);
}

.ui-textarea--error:focus {
  border-color: var(--danger);
  box-shadow: 0 0 0 3px var(--danger-soft);
}

.ui-textarea--disabled {
  cursor: not-allowed;
  opacity: 0.6;
  background: var(--surface);
}

.ui-textarea-error {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--danger);
}

.ui-textarea-hint {
  font-size: 0.75rem;
  color: var(--muted);
}
</style>

