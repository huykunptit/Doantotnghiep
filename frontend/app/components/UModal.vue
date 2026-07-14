<script setup lang="ts">
import Dialog from 'primevue/dialog'

defineOptions({ name: 'UModal' })

const props = withDefaults(defineProps<{
  open?: boolean
  ui?: { width?: string }
}>(), {
  open: false,
  ui: () => ({ width: 'max-w-lg' })
})

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const modalStyle = computed(() => {
  const width = props.ui?.width || 'max-w-lg'
  const widthMap: Record<string, string> = {
    'max-w-sm': '24rem',
    'max-w-md': '28rem',
    'max-w-lg': '32rem',
    'max-w-xl': '36rem',
    'max-w-2xl': '42rem',
    'max-w-3xl': '48rem',
    'max-w-4xl': '56rem',
  }
  return {
    width: widthMap[width] || '32rem'
  }
})
</script>

<template>
  <Dialog
    :visible="open"
    :modal="true"
    :dismissable-mask="true"
    :closable="false"
    :style="modalStyle"
    @update:visible="emit('update:open', $event)"
  >
    <template #default>
      <slot name="content" />
    </template>
  </Dialog>
</template>

<style>
/* Override PrimeVue Dialog styles for cleaner look */
.p-dialog .p-dialog-content {
  padding: 0;
  border-radius: 8px;
}

.p-dialog .p-dialog-header {
  display: none;
}
</style>

