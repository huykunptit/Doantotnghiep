<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="ui-empty">
    <div class="ui-empty-icon">
      <slot name="icon">
        <i class="pi pi-inbox" style="font-size:1.25rem" />
      </slot>
    </div>
    <h3 class="ui-empty-title">{{ title }}</h3>
    <p v-if="description" class="ui-empty-desc">{{ description }}</p>
    <div v-if="$slots.default" class="ui-empty-actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.ui-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 32px;
  text-align: center;
  background: #fff;
  border: 1px solid var(--line, rgba(17,17,17,0.08));
  border-radius: 16px;
}
.ui-empty-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  margin-bottom: 16px;
  border-radius: 14px;
  background: rgba(var(--theme-primary-rgb, 14,165,233), 0.08);
  color: var(--theme-primary, #0ea5e9);
}
.ui-empty-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: -0.02em;
}
.ui-empty-desc {
  margin: 10px 0 0;
  max-width: 32rem;
  font-size: 0.875rem;
  line-height: 1.75;
  color: #64748b;
}
.ui-empty-actions {
  margin-top: 20px;
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
}
:global(.dark) .ui-empty {
  background: rgba(255,255,255,0.03);
  border-color: rgba(255,255,255,0.08);
}
:global(.dark) .ui-empty-title { color: #f1f5f9; }
:global(.dark) .ui-empty-desc  { color: #94a3b8; }
</style>


