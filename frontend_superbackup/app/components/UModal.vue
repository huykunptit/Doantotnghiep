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
  ui: () => ({ width: 'max-w-lg' }),
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
    width: widthMap[width] || '32rem',
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
    pt:root:class="u-modal-root"
    @update:visible="emit('update:open', $event)"
  >
    <template #default>
      <slot name="content">
        <div class="u-modal">
          <div v-if="title || $slots.header" class="u-modal-head">
            <div class="u-modal-titles">
              <p v-if="subtitle" class="u-modal-sub">{{ subtitle }}</p>
              <h3 class="u-modal-title">
                <slot name="header">{{ title }}</slot>
              </h3>
            </div>
            <button
              class="u-modal-close"
              type="button"
              aria-label="Đóng"
              @click="emit('update:open', false)"
            >✕</button>
          </div>

          <div class="u-modal-body">
            <slot />
          </div>

          <div v-if="$slots.footer" class="u-modal-foot">
            <slot name="footer" />
          </div>
        </div>
      </slot>
    </template>
  </Dialog>
</template>

<style>
.p-dialog.u-modal-root .p-dialog-content,
.p-dialog .p-dialog-content {
  padding: 0;
  border-radius: 14px;
  background: transparent;
  border: none;
  box-shadow: none;
}

.p-dialog .p-dialog-header {
  display: none;
}
</style>

<style scoped>
.u-modal {
  width: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 14px;
  border: 1px solid var(--line, var(--color-line));
  background: var(--surface-strong, var(--color-surface-strong, #fff));
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
  color: var(--text, var(--color-text));
}

.u-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px 14px;
  border-bottom: 1px solid var(--line, var(--color-line));
}

.u-modal-sub {
  margin: 0 0 4px;
  font-size: 0.68rem;
  font-weight: 750;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted, var(--color-text-muted));
}

.u-modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  line-height: 1.3;
  color: var(--text, var(--color-text));
}

.u-modal-close {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid var(--line, var(--color-line));
  background: transparent;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  flex-shrink: 0;
}

.u-modal-close:hover {
  color: var(--text);
  background: var(--surface);
}

.u-modal-body {
  padding: 16px 18px;
  max-height: min(70vh, 640px);
  overflow-y: auto;
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--text);
}

.u-modal-body :deep(label),
.u-modal-body :deep(.field-label),
.u-modal-body :deep(.form-label) {
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--text);
}

.u-modal-body :deep(input),
.u-modal-body :deep(select),
.u-modal-body :deep(textarea) {
  font-size: 0.8125rem;
  color: var(--text);
  background: var(--surface-strong);
  border-color: var(--line);
}

.u-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 18px;
  border-top: 1px solid var(--line, var(--color-line));
  background: var(--surface, var(--color-surface));
}

:global(.dark) .u-modal,
:global([data-theme='dark']) .u-modal {
  background: var(--surface-strong);
  border-color: var(--line);
}
</style>
