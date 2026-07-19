<script setup lang="ts">
import { ref, computed } from 'vue'

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
  alwaysOpen: false,
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
  <div class="ui-filters-panel card">
    <!-- Primary Toolbar -->
    <div class="filters-toolbar">
      <form class="search-form" @submit.prevent="handleSearchSubmit">
        <div class="relative w-full max-w-sm">
          <i class="pi pi-search search-icon" />
          <input
            :value="search"
            class="search-input"
            :placeholder="searchPlaceholder"
            type="text"
            @input="onSearchInput(($event.target as HTMLInputElement).value)"
          >
        </div>
      </form>
      
      <div class="toolbar-actions">
        <!-- Custom actions slot -->
        <slot name="actions" />
        
        <!-- Toggle Advanced Filters -->
        <button
          v-if="$slots.advanced && !alwaysOpen"
          type="button"
          :class="[
            'btn-filter-toggle',
            (filtersOpen || activeFilterCount > 0) && 'is-active'
          ]"
          @click="filtersOpen = !filtersOpen"
        >
          <i class="pi pi-filter" />
          <span>Bộ lọc</span>
          <span v-if="activeFilterCount > 0" class="filter-badge">
            {{ activeFilterCount }}
          </span>
        </button>
        
        <!-- Import Action -->
        <button
          v-if="showImport"
          type="button"
          class="btn-import"
          @click="emit('import')"
        >
          <i class="pi pi-upload" />
          <span>{{ importText }}</span>
        </button>

        <!-- Export Action -->
        <button
          v-if="showExport"
          type="button"
          class="btn-export"
          @click="emit('export')"
        >
          <i class="pi pi-download" />
          <span>{{ exportText }}</span>
        </button>
      </div>
    </div>

    <!-- Advanced Filters Collapsible Section -->
    <Transition name="slide-down">
      <div v-if="(filtersOpen || alwaysOpen) && $slots.advanced" class="advanced-filters-section">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 items-end">
          <slot name="advanced" />
          
          <div class="flex items-end">
            <button 
              v-if="activeFilterCount > 0" 
              class="btn-clear-inline"
              type="button" 
              @click="handleResetAll"
            >
              Xóa bộ lọc ({{ activeFilterCount }})
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Active Chips Bar -->
    <div v-if="activeChips.length > 0 || search" class="active-chips-bar">
      <div class="chips-list">
        <span v-if="search" class="filter-chip font-mono">
          Từ khóa: "{{ search }}"
          <button type="button" class="chip-remove" @click="onSearchInput('')">&times;</button>
        </span>
        <span 
          v-for="chip in activeChips" 
          :key="chip.key" 
          class="filter-chip"
        >
          {{ chip.label }}
          <button type="button" class="chip-remove" @click="handleRemoveChip(chip.key)">&times;</button>
        </span>
      </div>
      
      <button 
        v-if="activeChips.length > 0 || search" 
        class="btn-clear-all" 
        type="button" 
        @click="handleResetAll"
      >
        Xóa tất cả bộ lọc
      </button>
    </div>
  </div>
</template>

<style scoped>
.ui-filters-panel {
  background: var(--color-surface-strong, #fff);
  border: 1px solid var(--color-line);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.filters-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 20px;
}

.search-form {
  flex: 1;
  min-width: 240px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-muted, #8a8a80);
  font-size: 0.85rem;
}

.search-input {
  width: 100%;
  height: 38px;
  padding-left: 36px;
  padding-right: 12px;
  border-radius: 10px;
  border: 1px solid var(--color-line);
  background: var(--color-surface, #fbf7ef);
  font-size: 0.875rem;
  color: var(--color-text, #1f2421);
  outline: none;
  transition: border-color 150ms, box-shadow 150ms;
}

.search-input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-primary-soft);
  background: var(--color-surface-strong, #fff);
}

.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

/* Toggle Advanced Filters Button */
.btn-filter-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  border-radius: 10px;
  border: 1px solid var(--color-line);
  background: var(--color-surface, #fbf7ef);
  color: var(--color-text-secondary, #4a6059);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms;
  user-select: none;
}

.btn-filter-toggle:hover {
  background: var(--color-line-soft);
  color: var(--color-text);
}

.btn-filter-toggle.is-active {
  background: var(--color-primary-soft);
  border-color: rgba(29, 158, 117, 0.35);
  color: var(--color-primary);
}

.filter-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--color-primary);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
}

/* Import / Export Buttons */
.btn-export,
.btn-import {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 38px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid var(--color-line);
  background: var(--color-surface-strong, #fff);
  color: var(--color-text-secondary, #4a6059);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms;
}

.btn-export:hover,
.btn-import:hover {
  border-color: var(--color-primary);
  color: var(--color-primary);
  background: var(--color-primary-soft);
}

/* Advanced Filters Section */
.advanced-filters-section {
  padding: 16px 20px;
  border-top: 1px solid var(--color-line-soft);
  background: var(--color-surface, #fbf7ef);
}

.btn-clear-inline {
  height: 32px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--color-danger-soft);
  background: rgba(239, 68, 68, 0.04);
  color: var(--color-danger);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms;
}

.btn-clear-inline:hover {
  background: var(--color-danger);
  color: #fff;
}

/* Chips Bar */
.active-chips-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 20px;
  border-top: 1px solid var(--color-line-soft);
  font-size: 0.75rem;
}

.chips-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--color-primary-soft);
  border: 1px solid rgba(29, 158, 117, 0.22);
  color: var(--color-primary);
  font-weight: 600;
}

.chip-remove {
  border: none;
  background: none;
  color: var(--color-primary);
  font-size: 0.95rem;
  cursor: pointer;
  padding: 0 0 0 2px;
  line-height: 1;
}

.chip-remove:hover {
  color: var(--color-primary-hover);
}

.btn-clear-all {
  border: none;
  background: none;
  color: var(--color-danger);
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.btn-clear-all:hover {
  color: #b91c1c;
}

/* Collapsible slide-down animation */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 250ms ease-out;
  max-height: 400px;
  overflow: hidden;
}

.slide-down-enter-from,
.slide-down-leave-to {
  max-height: 0;
  opacity: 0;
  padding-top: 0;
  padding-bottom: 0;
}

/* Dark Mode Overrides */
:global([data-theme="dark"]) .ui-filters-panel {
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(255, 255, 255, 0.08);
}

:global([data-theme="dark"]) .search-input {
  background: rgba(0, 0, 0, 0.15);
  border-color: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.9);
}

:global([data-theme="dark"]) .btn-filter-toggle,
:global([data-theme="dark"]) .btn-export,
:global([data-theme="dark"]) .btn-import {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.85);
}

:global([data-theme="dark"]) .btn-filter-toggle:hover,
:global([data-theme="dark"]) .btn-export:hover,
:global([data-theme="dark"]) .btn-import:hover {
  background: rgba(255, 255, 255, 0.08);
}

:global([data-theme="dark"]) .advanced-filters-section {
  background: rgba(0, 0, 0, 0.1);
  border-top-color: rgba(255, 255, 255, 0.06);
}
</style>
