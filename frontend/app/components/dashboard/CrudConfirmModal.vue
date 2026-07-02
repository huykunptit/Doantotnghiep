<script setup lang="ts">
import { AlertTriangle, Trash2, X } from 'lucide-vue-next'

defineProps<{
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
  <UModal :open="open" @update:open="emit('close')">
    <template #content>
      <div class="crud-modal crud-confirm-modal" :class="{ 'confirm-danger': tone === 'danger' }" :style="{ width: '100%' }">
        
        <!-- Header area with beautiful visual icon -->
        <div class="confirm-modal-visual">
          <div class="visual-icon-wrap" :class="tone === 'danger' ? 'is-danger' : 'is-warning'">
            <Trash2 v-if="tone === 'danger'" :size="22" />
            <AlertTriangle v-else :size="22" />
          </div>
          <button class="confirm-close-btn" type="button" @click="emit('close')"><X :size="16" /></button>
        </div>

        <div class="confirm-modal-content">
          <h3>{{ title }}</h3>
          <p v-if="description" class="confirm-desc">{{ description }}</p>
        </div>

        <div class="crud-modal-foot confirm-foot">
          <button class="crud-secondary-btn confirm-btn-cancel" type="button" :disabled="loading" @click="emit('close')">
            {{ cancelText || 'Đóng' }}
          </button>
          <button
            class="crud-primary-btn confirm-btn-action"
            :class="tone === 'danger' ? 'crud-danger-btn' : 'crud-warning-btn'"
            type="button"
            :disabled="loading"
            @click="emit('confirm')"
          >
            {{ loading ? 'Đang xử lý...' : confirmText || 'Xác nhận' }}
          </button>
        </div>
      </div>
    </template>
  </UModal>
</template>

<style scoped>
.crud-confirm-modal {
  width: 100% !important;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 
    0 20px 25px -5px rgba(0, 0, 0, 0.1), 
    0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.confirm-modal-visual {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 24px 24px 12px;
}

.visual-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.visual-icon-wrap.is-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.visual-icon-wrap.is-warning {
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.confirm-close-btn {
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 6px;
  border-radius: 50%;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.confirm-close-btn:hover {
  background: rgba(17, 17, 17, 0.06);
  color: var(--text);
}

[data-theme="dark"] .confirm-close-btn:hover {
  background: rgba(255, 255, 255, 0.08);
}

.confirm-modal-content {
  padding: 0 24px 20px;
}

.confirm-modal-content h3 {
  margin: 0 0 8px;
  font-size: 1.2rem;
  font-weight: 750;
  color: var(--text);
  letter-spacing: -0.02em;
}

.confirm-desc {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--muted);
}

.confirm-foot {
  padding: 16px 24px 20px;
  border-top: none;
  background: transparent;
  margin-top: 0;
}

.confirm-btn-cancel {
  padding: 10px 18px;
  font-size: 0.9rem;
  font-weight: 600;
}

.confirm-btn-action {
  padding: 10px 20px;
  font-size: 0.9rem;
  font-weight: 600;
  border-radius: 10px;
}

.crud-warning-btn {
  background: #f59e0b;
  color: white;
}

.crud-warning-btn:hover {
  background: #d97706;
}
</style>
