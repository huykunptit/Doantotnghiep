<script setup lang="ts">
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
  <Teleport to="body">
    <div v-if="open" class="crud-modal-backdrop" @click.self="emit('close')">
      <div class="crud-modal crud-confirm-modal">
        <div class="crud-modal-head">
          <div>
            <p class="section-kicker">Xác nhận thao tác</p>
            <h3>{{ title }}</h3>
            <p v-if="description">{{ description }}</p>
          </div>
          <button class="topbar-ghost" type="button" @click="emit('close')">✕</button>
        </div>

        <div class="crud-modal-foot">
          <button class="crud-secondary-btn" type="button" :disabled="loading" @click="emit('close')">
            {{ cancelText || 'Đóng' }}
          </button>
          <button
            class="crud-primary-btn"
            :class="{ 'crud-danger-btn': tone === 'danger' }"
            type="button"
            :disabled="loading"
            @click="emit('confirm')"
          >
            {{ loading ? 'Đang xử lý...' : confirmText || 'Xác nhận' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

