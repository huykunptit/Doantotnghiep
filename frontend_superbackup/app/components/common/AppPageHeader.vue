<script setup lang="ts">
defineProps<{
  eyebrow?: string
  title: string
  description?: string
}>()
</script>

<template>
  <div class="page-hd">
    <p v-if="eyebrow" class="page-hd-eyebrow">{{ eyebrow }}</p>
    <div class="page-hd-row">
      <div class="page-hd-main">
        <h1 class="page-hd-title">{{ title }}</h1>
        <p v-if="description" class="page-hd-desc">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="page-hd-actions">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Mirrors admin-ui DESIGN.md page header pattern */
.page-hd {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 24px;
}

.page-hd-eyebrow {
  margin: 0 0 4px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.28em;
  color: var(--theme-primary, #0ea5e9);
}

.page-hd-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-hd-main { flex: 1; min-width: 0; }

.page-hd-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #1e293b; /* slate-800 */
  line-height: 1.25;
}

.page-hd-desc {
  margin: 6px 0 0;
  max-width: 42rem;
  font-size: 0.875rem;
  line-height: 1.75;
  color: #64748b; /* slate-500 */
}

.page-hd-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
}

@media (min-width: 640px) {
  .page-hd-title { font-size: 1.75rem; }
}

@media (min-width: 1024px) {
  .page-hd-row { flex-direction: row; align-items: flex-end; justify-content: space-between; }
}

:global(.dark) .page-hd-title { color: #f1f5f9; }
:global(.dark) .page-hd-desc  { color: #94a3b8; }
</style>


