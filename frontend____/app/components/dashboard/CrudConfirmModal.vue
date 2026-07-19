<script setup lang="ts">
const props = defineProps<{
  open: boolean
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  tone?: 'default' | 'danger'
  loading?: boolean
}>()

const emit = defineEmits<{
  close: []
  confirm: []
}>()
</script>

<template>
  <Dialog
    :visible="open"
    modal
    :closable="false"
    :draggable="false"
    :style="{ width: '28rem', maxWidth: '90vw' }"
    @update:visible="emit('close')"
  >
    <template #header>
      <div class="confirm-modal-visual">
        <div class="visual-icon-wrap" :class="tone === 'danger' ? 'is-danger' : 'is-warning'">
          <i v-if="tone === 'danger'" class="pi pi-trash" style="font-size:1.25rem" />
          <i v-else class="pi pi-exclamation-triangle" style="font-size:1.25rem" />
        </div>
        <button class="confirm-close-btn" type="button" @click="emit('close')">
          <i class="pi pi-times" style="font-size:0.875rem" />
        </button>
      </div>
    </template>

    <div class="confirm-modal-content">
      <h3>{{ title }}</h3>
      <p v-if="description" class="confirm-desc">{{ description }}</p>
    </div>

    <template #footer>
      <div class="confirm-foot">
        <Button
          :label="cancelText || 'Đóng'"
          severity="secondary"
          :disabled="loading"
          @click="emit('close')"
        />
        <Button
          :label="loading ? 'Đang xử lý...' : confirmText || 'Xác nhận'"
          :severity="tone === 'danger' ? 'danger' : 'warning'"
          :disabled="loading"
          :loading="loading"
          @click="emit('confirm')"
        />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.confirm-modal-visual {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  width: 100%;
  padding-bottom: 4px;
}

.visual-icon-wrap {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
}
.visual-icon-wrap.is-danger  { background: rgba(239,68,68,0.1);  color: #ef4444; }
.visual-icon-wrap.is-warning { background: rgba(245,158,11,0.1); color: #f59e0b; }

.confirm-close-btn {
  background: transparent; border: none;
  color: #94a3b8; cursor: pointer;
  padding: 6px; border-radius: 50%;
  transition: all 0.2s;
  display: flex; align-items: center; justify-content: center;
}
.confirm-close-btn:hover { background: rgba(17,17,17,0.06); color: #1e293b; }

.confirm-modal-content {
  padding: 4px 0 8px;
}
.confirm-modal-content h3 {
  margin: 0 0 8px; font-size: 1.125rem; font-weight: 700;
  color: #1e293b; letter-spacing: -0.02em;
}
.confirm-desc {
  margin: 0; font-size: 0.875rem; line-height: 1.6; color: #64748b;
}

.confirm-foot {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 10px; padding-top: 4px;
}

:global(.dark) .confirm-close-btn:hover { background: rgba(255,255,255,0.08); color: #f1f5f9; }
:global(.dark) .confirm-modal-content h3 { color: #f1f5f9; }
:global(.dark) .confirm-desc { color: #94a3b8; }
</style>
