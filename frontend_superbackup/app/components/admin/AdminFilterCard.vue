<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  description?: string
  activeCount?: number
  loading?: boolean
  resetLabel?: string
  applyLabel?: string
  showReset?: boolean
  showApply?: boolean
}>(), {
  title: 'Bộ lọc',
  description: '',
  activeCount: 0,
  loading: false,
  resetLabel: 'Đặt lại',
  applyLabel: 'Áp dụng',
  showReset: false,
  showApply: false,
})

defineEmits<{
  reset: []
  apply: []
}>()
</script>

<template>
  <section class="admin-filter-card" aria-label="Bộ lọc dữ liệu">
    <div class="admin-filter-card__header">
      <div>
        <div class="admin-filter-card__title">
          <slot name="title">{{ title }}</slot>
          <span v-if="activeCount > 0" class="admin-filter-card__count">{{ activeCount }}</span>
        </div>
        <p v-if="description || $slots.description" class="admin-filter-card__description">
          <slot name="description">{{ description }}</slot>
        </p>
      </div>
      <div v-if="$slots.actions" class="admin-filter-card__actions">
        <slot name="actions" />
      </div>
    </div>

    <form class="admin-filter-card__form" @submit.prevent="$emit('apply')">
      <div v-if="$slots.search" class="admin-filter-card__search">
        <slot name="search" />
      </div>
      <div class="admin-filter-card__fields">
        <slot />
        <slot name="filters" />
      </div>
      <div v-if="showReset || showApply || $slots.footer" class="admin-filter-card__footer">
        <slot name="footer">
          <Button
            v-if="showReset"
            type="button"
            severity="secondary"
            outlined
            size="small"
            :label="resetLabel"
            icon="pi pi-filter-slash"
            :disabled="loading"
            @click="$emit('reset')"
          />
          <Button
            v-if="showApply"
            type="submit"
            size="small"
            :label="applyLabel"
            icon="pi pi-search"
            :loading="loading"
          />
        </slot>
      </div>
    </form>
  </section>
</template>

<style scoped>
.admin-filter-card {
  display: grid;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--line, var(--p-content-border-color));
  border-radius: var(--p-border-radius-xl, 0.875rem);
  background: var(--surface-card, var(--p-content-background));
  color: var(--text, var(--p-text-color));
  box-shadow: var(--p-card-shadow, 0 1px 2px rgb(0 0 0 / 0.04));
}

.admin-filter-card__header,
.admin-filter-card__form,
.admin-filter-card__fields,
.admin-filter-card__footer,
.admin-filter-card__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.admin-filter-card__header {
  justify-content: space-between;
}

.admin-filter-card__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 700;
}

.admin-filter-card__count {
  min-width: 1.35rem;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  font-size: 0.7rem;
  text-align: center;
}

.admin-filter-card__description {
  margin: 0.25rem 0 0;
  color: var(--muted, var(--p-text-muted-color));
  font-size: 0.78rem;
}

.admin-filter-card__form {
  align-items: flex-end;
  flex-wrap: wrap;
}

.admin-filter-card__search {
  flex: 1 1 16rem;
  min-width: min(100%, 14rem);
}

.admin-filter-card__fields {
  flex: 2 1 28rem;
  align-items: flex-end;
  flex-wrap: wrap;
}

.admin-filter-card__footer {
  margin-left: auto;
}

@media (max-width: 640px) {
  .admin-filter-card__header {
    align-items: flex-start;
  }

  .admin-filter-card__form,
  .admin-filter-card__fields,
  .admin-filter-card__footer {
    width: 100%;
  }

  .admin-filter-card__footer {
    justify-content: flex-end;
  }
}
</style>
