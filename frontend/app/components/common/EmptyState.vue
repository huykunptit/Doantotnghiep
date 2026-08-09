<script setup lang="ts">
withDefaults(defineProps<{
  icon?: string
  title?: string
  description?: string
}>(), {
  icon: 'pi pi-inbox',
})
</script>

<template>
  <div class="empty-state">
    <i :class="icon" />
    <strong v-if="title">{{ title }}</strong>
    <p v-if="description">{{ description }}</p>
    <div v-if="$slots.default" class="empty-state-actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 48px 24px;
  text-align: center;
  color: var(--text-muted);
}

.empty-state > i {
  margin-bottom: 8px;
  font-size: 2rem;
  color: var(--border-strong);
}

.empty-state > strong {
  color: var(--text);
  font-size: 1.02rem;
  font-weight: 700;
}

.empty-state > p {
  max-width: 42ch;
  margin: 0;
  font-size: .92rem;
  line-height: 1.5;
}

.empty-state-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 8px;
}
</style>
