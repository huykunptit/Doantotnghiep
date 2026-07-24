<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  description?: string
}>(), {
  title: 'Đã có lỗi xảy ra',
  description: 'Vui lòng thử lại sau hoặc kiểm tra lại dữ liệu.',
})
</script>

<template>
  <div class="ui-error">
    <div class="ui-error-content">
      <div class="ui-error-icon">
        <i class="pi pi-exclamation-circle" />
      </div>
      <div class="ui-error-text">
        <h3 class="ui-error-title">{{ title }}</h3>
        <p class="ui-error-desc">{{ description }}</p>
        <div v-if="$slots.default" class="ui-error-actions">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ui-error {
  padding: 24px;
  border-radius: 16px;
  border: 1px solid rgba(239, 68, 68, 0.2);
  background: rgba(254, 226, 226, 0.5);
}
.ui-error-content {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.ui-error-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #fff;
  color: #ef4444;
  font-size: 1.25rem;
  flex-shrink: 0;
}
.ui-error-text {
  flex: 1;
  min-width: 0;
}
.ui-error-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: #991b1b;
}
.ui-error-desc {
  margin: 4px 0 0;
  font-size: 0.875rem;
  color: #dc2626;
  line-height: 1.5;
}
.ui-error-actions {
  margin-top: 12px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
:global(.dark) .ui-error {
  background: rgba(127, 29, 29, 0.2);
  border-color: rgba(239, 68, 68, 0.3);
}
:global(.dark) .ui-error-icon {
  background: rgba(255, 255, 255, 0.05);
  color: #fca5a5;
}
:global(.dark) .ui-error-title { color: #fca5a5; }
:global(.dark) .ui-error-desc { color: #f87171; }
</style>

