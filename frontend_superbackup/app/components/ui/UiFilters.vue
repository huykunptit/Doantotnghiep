<script setup lang="ts">
import { ref, watch } from 'vue'

defineOptions({ name: 'UiFilters' })

interface ActiveChip {
  key: string
  label: string
}

const props = withDefaults(defineProps<{
  search?: string
  searchPlaceholder?: string
  activeFilterCount?: number
  activeChips?: ActiveChip[]
  showExport?: boolean
  showImport?: boolean
  exportText?: string
  importText?: string
  /** Advanced filters always visible above the table (default true) */
  alwaysOpen?: boolean
}>(), {
  search: '',
  searchPlaceholder: 'Tìm kiếm...',
  activeFilterCount: 0,
  activeChips: () => [],
  showExport: false,
  showImport: false,
  exportText: 'Xuất CSV',
  importText: 'Nhập CSV',
  alwaysOpen: true,
})

const emit = defineEmits<{
  'update:search': [value: string]
  'submit-search': [value: string]
  'reset-filters': []
  'remove-chip': [key: string]
  'export': []
  'import': []
}>()

const localSearch = ref(props.search)
const filtersOpen = ref(props.alwaysOpen)

watch(() => props.search, (v) => { localSearch.value = v })
watch(() => props.alwaysOpen, (v) => {
  if (v) filtersOpen.value = true
})

function onSearchInput(val: string) {
  localSearch.value = val
  emit('update:search', val)
}

function handleSearchSubmit() {
  emit('submit-search', localSearch.value)
}

function handleRemoveChip(key: string) {
  emit('remove-chip', key)
}

function handleResetAll() {
  localSearch.value = ''
  emit('reset-filters')
}
</script>

<template>
  <div class="ui-filters">
    <div class="filters-toolbar">
      <form class="search-form" @submit.prevent="handleSearchSubmit">
        <div class="search-wrap">
          <i class="pi pi-search search-icon" aria-hidden="true" />
          <input
            :value="search"
            class="search-input"
            :placeholder="searchPlaceholder"
            type="search"
            aria-label="Tìm kiếm"
            @input="onSearchInput(($event.target as HTMLInputElement).value)"
          >
        </div>
        <button class="btn-search" type="submit">Tìm</button>
      </form>

      <div class="toolbar-actions">
        <slot name="actions" />

        <button
          v-if="$slots.advanced && !alwaysOpen"
          type="button"
          class="btn-ghost"
          :class="{ 'is-active': filtersOpen || activeFilterCount > 0 }"
          @click="filtersOpen = !filtersOpen"
        >
          <i class="pi pi-filter" />
          <span>Bộ lọc</span>
          <span v-if="activeFilterCount > 0" class="filter-badge">{{ activeFilterCount }}</span>
        </button>

        <button
          v-if="showImport"
          type="button"
          class="btn-ghost"
          @click="emit('import')"
        >
          <i class="pi pi-upload" />
          <span>{{ importText }}</span>
        </button>

        <button
          v-if="showExport"
          type="button"
          class="btn-ghost"
          @click="emit('export')"
        >
          <i class="pi pi-download" />
          <span>{{ exportText }}</span>
        </button>
      </div>
    </div>

    <div v-if="(filtersOpen || alwaysOpen) && $slots.advanced" class="advanced">
      <div class="advanced-grid">
        <slot name="advanced" />
        <div v-if="activeFilterCount > 0" class="advanced-clear">
          <button class="btn-clear" type="button" @click="handleResetAll">
            Xóa bộ lọc ({{ activeFilterCount }})
          </button>
        </div>
      </div>
    </div>

    <div v-if="activeChips.length > 0 || search" class="chips-bar">
      <div class="chips-list">
        <span v-if="search" class="chip">
          Từ khóa: "{{ search }}"
          <button type="button" class="chip-x" aria-label="Xóa từ khóa" @click="onSearchInput('')">&times;</button>
        </span>
        <span v-for="chip in activeChips" :key="chip.key" class="chip">
          {{ chip.label }}
          <button type="button" class="chip-x" :aria-label="`Xóa ${chip.label}`" @click="handleRemoveChip(chip.key)">&times;</button>
        </span>
      </div>
      <button class="btn-clear-all" type="button" @click="handleResetAll">Xóa tất cả</button>
    </div>
  </div>
</template>

<style scoped>
.ui-filters {
  background: var(--surface-strong, var(--color-surface-strong, #fff));
  border: 1px solid var(--line, var(--color-line));
  border-radius: 12px;
  overflow: hidden;
}

.filters-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 14px;
}

.search-form {
  display: flex;
  flex: 1;
  gap: 8px;
  min-width: 200px;
  align-items: center;
}

.search-wrap {
  position: relative;
  flex: 1;
  max-width: 320px;
}

.search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted, var(--color-text-muted));
  font-size: 0.8rem;
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 34px;
  padding: 0 10px 0 32px;
  border-radius: 8px;
  border: 1px solid var(--line, var(--color-line));
  background: var(--surface, var(--color-surface, #f6f8f7));
  font-size: 0.8125rem;
  color: var(--text, var(--color-text));
  outline: none;
}

.search-input:focus {
  border-color: var(--color-primary, #1d9e75);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.12);
}

.btn-search,
.btn-ghost,
.btn-clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  padding: 0 12px;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
  transition: background 150ms ease, border-color 150ms ease, color 150ms ease;
}

.btn-search {
  border: 0;
  background: var(--color-primary, #1d9e75);
  color: #fff;
  flex-shrink: 0;
}

.btn-search:hover {
  filter: brightness(0.95);
}

.btn-ghost {
  border: 1px solid var(--line, var(--color-line));
  background: var(--surface, var(--color-surface));
  color: var(--muted, var(--color-text-secondary));
}

.btn-ghost:hover,
.btn-ghost.is-active {
  color: var(--color-primary, #1d9e75);
  border-color: rgba(29, 158, 117, 0.35);
  background: rgba(29, 158, 117, 0.08);
}

.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-badge {
  display: inline-grid;
  place-items: center;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--color-primary, #1d9e75);
  color: #fff;
  font-size: 0.62rem;
  font-weight: 800;
}

.advanced {
  padding: 12px 14px;
  border-top: 1px solid var(--line, var(--color-line));
  background: var(--surface, var(--color-surface));
}

.advanced-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
  align-items: end;
}

.advanced-clear {
  display: flex;
  align-items: end;
}

.btn-clear {
  border: 1px solid rgba(239, 68, 68, 0.25);
  background: var(--danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--danger, var(--color-danger, #ef4444));
}

.chips-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 14px;
  border-top: 1px solid var(--line, var(--color-line));
}

.chips-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 24px;
  padding: 0 8px;
  border-radius: 999px;
  background: rgba(29, 158, 117, 0.1);
  border: 1px solid rgba(29, 158, 117, 0.22);
  color: var(--color-primary, #1d9e75);
  font-size: 0.72rem;
  font-weight: 650;
}

.chip-x {
  border: 0;
  background: transparent;
  color: inherit;
  cursor: pointer;
  font-size: 0.95rem;
  line-height: 1;
  padding: 0;
}

.btn-clear-all {
  border: 0;
  background: transparent;
  color: var(--danger, #ef4444);
  font-size: 0.75rem;
  font-weight: 650;
  text-decoration: underline;
  cursor: pointer;
  white-space: nowrap;
}

:global(.dark) .ui-filters,
:global([data-theme='dark']) .ui-filters {
  background: var(--surface-strong);
  border-color: var(--line);
}

:global(.dark) .search-input,
:global([data-theme='dark']) .search-input,
:global(.dark) .btn-ghost,
:global([data-theme='dark']) .btn-ghost {
  background: var(--surface);
  border-color: var(--line);
  color: var(--text);
}

:global(.dark) .advanced,
:global([data-theme='dark']) .advanced {
  background: rgba(255, 255, 255, 0.02);
  border-top-color: var(--line);
}
</style>
