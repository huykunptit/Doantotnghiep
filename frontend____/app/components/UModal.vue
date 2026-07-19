<script setup lang="ts">
import { computed } from 'vue'
import Dialog from 'primevue/dialog'

defineOptions({ name: 'UModal' })

const props = withDefaults(defineProps<{
  open?: boolean
  title?: string
  subtitle?: string
  ui?: { width?: string }
}>(), {
  open: false,
  title: '',
  subtitle: '',
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
      <slot name="content">
        <div class="w-full bg-white dark:bg-[#111a17] rounded-2xl shadow-2xl overflow-hidden flex flex-col border border-[var(--line)] dark:border-zinc-800">
          <!-- Header -->
          <div v-if="title || $slots.header" class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-[var(--line)] dark:border-zinc-800">
            <div>
              <p v-if="subtitle" class="text-[0.68rem] font-bold uppercase tracking-widest text-[var(--muted)] mb-1">{{ subtitle }}</p>
              <h3 class="text-lg font-bold tracking-tight text-[var(--text)]">
                <slot name="header">{{ title }}</slot>
              </h3>
            </div>
            <button class="w-8 h-8 rounded-xl flex items-center justify-center border border-[var(--line)] dark:border-zinc-800 text-sm font-bold text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--text)] transition-colors" type="button" @click="emit('update:open', false)">✕</button>
          </div>
          
          <!-- Body -->
          <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">
            <slot />
          </div>
          
          <!-- Footer -->
          <div v-if="$slots.footer" class="flex items-center justify-end gap-2 px-6 py-4 border-t border-[var(--line)] dark:border-zinc-800 bg-[var(--surface)] dark:bg-zinc-900/40">
            <slot name="footer" />
          </div>
        </div>
      </slot>
    </template>
  </Dialog>
</template>

<style>
/* Override PrimeVue Dialog styles for cleaner look */
.p-dialog .p-dialog-content {
  padding: 0;
  border-radius: 16px;
  background: transparent;
  border: none;
}

.p-dialog .p-dialog-header {
  display: none;
}
</style>

