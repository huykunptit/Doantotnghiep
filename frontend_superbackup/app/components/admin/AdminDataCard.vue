<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  description?: string
  count?: number | string
  loading?: boolean
  error?: string | null
  empty?: boolean
  emptyTitle?: string
  emptyDescription?: string
}>(), {
  title: '',
  description: '',
  count: undefined,
  loading: false,
  error: null,
  empty: false,
  emptyTitle: 'Chưa có dữ liệu',
  emptyDescription: 'Dữ liệu sẽ xuất hiện tại đây khi có bản ghi.',
})

defineEmits<{
  retry: []
}>()
</script>

<template>
  <section class="admin-data-card">
    <header v-if="title || $slots.title || $slots.actions || $slots.export" class="admin-data-card__header">
      <div class="admin-data-card__heading">
        <h2 v-if="title || $slots.title">
          <slot name="title">{{ title }}</slot>
          <span v-if="count !== undefined" class="admin-data-card__count">{{ count }}</span>
        </h2>
        <p v-if="description || $slots.description">
          <slot name="description">{{ description }}</slot>
        </p>
      </div>
      <div class="admin-data-card__actions">
        <slot name="export" />
        <slot name="actions" />
      </div>
    </header>

    <div v-if="$slots.toolbar" class="admin-data-card__toolbar">
      <slot name="toolbar" />
    </div>
    <div v-if="$slots.bulk" class="admin-data-card__bulk">
      <slot name="bulk" />
    </div>

    <div v-if="error" class="admin-data-card__state admin-data-card__state--error" role="alert">
      <slot name="error" :error="error" :retry="() => $emit('retry')">
        <i class="pi pi-exclamation-circle" aria-hidden="true" />
        <strong>Không thể tải dữ liệu</strong>
        <span>{{ error }}</span>
        <Button label="Thử lại" icon="pi pi-refresh" size="small" outlined @click="$emit('retry')" />
      </slot>
    </div>
    <div v-else-if="loading" class="admin-data-card__state" aria-live="polite">
      <slot name="loading">
        <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="5" />
        <span>Đang tải dữ liệu…</span>
      </slot>
    </div>
    <div v-else-if="empty" class="admin-data-card__state">
      <slot name="empty">
        <i class="pi pi-inbox" aria-hidden="true" />
        <strong>{{ emptyTitle }}</strong>
        <span>{{ emptyDescription }}</span>
      </slot>
    </div>
    <div v-else class="admin-data-card__content">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="admin-data-card__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>

<style scoped>
.admin-data-card {
  overflow: hidden;
  border: 1px solid var(--line, var(--p-content-border-color));
  border-radius: var(--p-border-radius-xl, 0.875rem);
  background: var(--surface-card, var(--p-content-background));
  color: var(--text, var(--p-text-color));
  box-shadow: var(--p-card-shadow, 0 1px 2px rgb(0 0 0 / 0.04));
}

.admin-data-card__header,
.admin-data-card__toolbar,
.admin-data-card__bulk,
.admin-data-card__footer {
  padding: 0.9rem 1rem;
}

.admin-data-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid var(--line, var(--p-content-border-color));
}

.admin-data-card__heading h2 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.admin-data-card__heading p {
  margin: 0.3rem 0 0;
  color: var(--muted, var(--p-text-muted-color));
  font-size: 0.8rem;
}

.admin-data-card__count {
  color: var(--muted, var(--p-text-muted-color));
  font-size: 0.78rem;
  font-weight: 600;
}

.admin-data-card__actions,
.admin-data-card__bulk {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.admin-data-card__toolbar,
.admin-data-card__bulk {
  border-bottom: 1px solid var(--line, var(--p-content-border-color));
}

.admin-data-card__bulk {
  background: color-mix(in srgb, var(--p-primary-500) 7%, transparent);
}

.admin-data-card__state {
  display: grid;
  min-height: 14rem;
  padding: 2rem;
  place-items: center;
  align-content: center;
  gap: 0.65rem;
  color: var(--muted, var(--p-text-muted-color));
  text-align: center;
}

.admin-data-card__state > i {
  font-size: 1.8rem;
}

.admin-data-card__state strong {
  color: var(--text, var(--p-text-color));
}

.admin-data-card__state--error > i,
.admin-data-card__state--error strong {
  color: var(--p-red-600);
}

.admin-data-card__footer {
  border-top: 1px solid var(--line, var(--p-content-border-color));
}

@media (max-width: 640px) {
  .admin-data-card__header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
