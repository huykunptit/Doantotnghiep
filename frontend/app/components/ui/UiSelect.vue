<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

defineOptions({ inheritAttrs: false })

export interface SelectOption {
  label: string
  value: string | number
  [key: string]: any
}

const props = withDefaults(defineProps<{
  modelValue?: string | number | (string | number)[] | null
  options?: SelectOption[]
  label?: string
  placeholder?: string
  disabled?: boolean
  multiple?: boolean
  searchable?: boolean
  error?: string
  hint?: string
  size?: 'md' | 'lg'
  clearable?: boolean
}>(), {
  options: () => [],
  label: '',
  placeholder: 'Chọn...',
  disabled: false,
  multiple: false,
  searchable: true,
  error: '',
  hint: '',
  size: 'md',
  clearable: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | (string | number)[] | null]
}>()

const isOpen = ref(false)
const searchQuery = ref('')
const containerRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)

const filteredOptions = computed(() => {
  if (!searchQuery.value.trim()) return props.options
  const q = searchQuery.value.toLowerCase()
  return props.options.filter(o => o.label.toLowerCase().includes(q))
})

const selectedValues = computed<(string | number)[]>(() => {
  if (props.modelValue === null || props.modelValue === undefined) return []
  if (Array.isArray(props.modelValue)) return props.modelValue
  return props.modelValue !== '' ? [props.modelValue] : []
})

const displayLabel = computed(() => {
  if (selectedValues.value.length === 0) return ''
  if (props.multiple) {
    if (selectedValues.value.length === 1) {
      return props.options.find(o => o.value === selectedValues.value[0])?.label ?? ''
    }
    return `Đã chọn ${selectedValues.value.length} mục`
  }
  return props.options.find(o => o.value === selectedValues.value[0])?.label ?? ''
})

function isSelected(value: string | number) {
  return selectedValues.value.includes(value)
}

function toggleOption(value: string | number) {
  if (props.multiple) {
    const current = [...selectedValues.value]
    const idx = current.indexOf(value)
    if (idx > -1) current.splice(idx, 1)
    else current.push(value)
    emit('update:modelValue', current)
  } else {
    emit('update:modelValue', value)
    isOpen.value = false
    searchQuery.value = ''
  }
}

function clearAll(e: Event) {
  e.stopPropagation()
  emit('update:modelValue', props.multiple ? [] : null)
  searchQuery.value = ''
}

function toggleOpen() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    searchQuery.value = ''
    setTimeout(() => searchInputRef.value?.focus(), 50)
  }
}

function handleClickOutside(e: MouseEvent) {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    isOpen.value = false
    searchQuery.value = ''
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))

watch(isOpen, (val) => {
  if (!val) searchQuery.value = ''
})
</script>

<template>
  <div ref="containerRef" class="ui-select-wrap">
    <span v-if="props.label" class="ui-select-label">{{ props.label }}</span>

    <div class="ui-select-trigger-wrap">
      <button
        type="button"
        :disabled="props.disabled"
        :class="[
          'ui-select-trigger',
          props.size === 'lg' ? 'ui-select-trigger--lg' : 'ui-select-trigger--md',
          props.error && 'ui-select-trigger--error',
          props.disabled && 'ui-select-trigger--disabled',
          isOpen && 'ui-select-trigger--active',
        ]"
        @click="toggleOpen"
      >
        <!-- Selected tags (multiple) -->
        <div v-if="props.multiple && selectedValues.length > 0" class="ui-select-tags">
          <span v-if="selectedValues.length <= 2" v-for="val in selectedValues" :key="val" class="ui-select-tag">
            {{ options.find(o => o.value === val)?.label }}
          </span>
          <span v-else class="ui-select-tag">{{ selectedValues.length }} đã chọn</span>
        </div>

        <!-- Single value or placeholder -->
        <span v-else :class="['ui-select-value', !displayLabel && 'ui-select-placeholder']">
          {{ displayLabel || props.placeholder }}
        </span>

        <div class="ui-select-actions">
          <button
            v-if="props.clearable && selectedValues.length > 0 && !props.disabled"
            type="button"
            class="ui-select-clear"
            @click="clearAll"
          >
            <i class="pi pi-times" style="font-size:0.8125rem" />
          </button>
          <i class="pi pi-chevron-down ui-select-chevron" :class="{ 'is-open': isOpen }" style="font-size:0.9375rem" />
        </div>
      </button>

      <!-- Dropdown Panel -->
      <transition name="ui-select-fade">
        <div v-if="isOpen" class="ui-select-panel">
          <!-- Search -->
          <div v-if="props.searchable" class="ui-select-search-wrap">
            <i class="pi pi-search ui-select-search-icon" style="font-size:0.875rem" />
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              class="ui-select-search-input"
              placeholder="Tìm kiếm..."
              @click.stop
            />
          </div>

          <!-- Options list -->
          <div class="ui-select-list">
            <div v-if="filteredOptions.length === 0" class="ui-select-empty">
              Không tìm thấy kết quả
            </div>
            <button
              v-for="option in filteredOptions"
              :key="option.value"
              type="button"
              class="ui-select-option"
              :class="{ 'is-selected': isSelected(option.value) }"
              @click="toggleOption(option.value)"
            >
              <span v-if="props.multiple" class="ui-select-checkbox">
                <i v-if="isSelected(option.value)" class="pi pi-check" style="font-size:0.6875rem" />
              </span>
              <span class="ui-select-option-label">{{ option.label }}</span>
              <i v-if="!props.multiple && isSelected(option.value)" class="pi pi-check ui-select-check-single" style="font-size:0.875rem" />
            </button>
          </div>

          <!-- Multi-select footer -->
          <div v-if="props.multiple && selectedValues.length > 0" class="ui-select-footer">
            <span>{{ selectedValues.length }} đã chọn</span>
            <button type="button" class="ui-select-footer-clear" @click="clearAll">Xóa tất cả</button>
          </div>
        </div>
      </transition>
    </div>

    <span v-if="props.error" class="ui-select-error">{{ props.error }}</span>
    <span v-else-if="props.hint" class="ui-select-hint">{{ props.hint }}</span>
  </div>
</template>

<style scoped>
.ui-select-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
  width: 100%;
}

.ui-select-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.ui-select-trigger-wrap {
  position: relative;
  width: 100%;
}

.ui-select-trigger {
  display: flex;
  align-items: center;
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface-strong, #fff);
  color: var(--text);
  font: inherit;
  text-align: left;
  outline: none;
  cursor: pointer;
  gap: 8px;
  transition: border-color 150ms, box-shadow 150ms;
}

.ui-select-trigger--md { height: 40px; padding: 0 10px 0 14px; font-size: 0.875rem; }
.ui-select-trigger--lg { height: 48px; padding: 0 12px 0 16px; font-size: 0.9375rem; }

.ui-select-trigger:hover { border-color: var(--green); }
.ui-select-trigger--active,
.ui-select-trigger:focus { border-color: var(--green); box-shadow: 0 0 0 3px var(--green-soft); }
.ui-select-trigger--error { border-color: var(--danger); background: var(--danger-soft); }
.ui-select-trigger--error:focus { border-color: var(--danger); box-shadow: 0 0 0 3px var(--danger-soft); }
.ui-select-trigger--disabled { cursor: not-allowed; opacity: 0.6; background: var(--surface); }

.ui-select-value { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ui-select-placeholder { color: var(--muted); }

.ui-select-tags {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  min-width: 0;
}

.ui-select-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  background: rgba(29, 158, 117, 0.1);
  color: var(--green, #1d9e75);
  white-space: nowrap;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ui-select-actions {
  display: flex;
  align-items: center;
  gap: 2px;
  flex-shrink: 0;
}

.ui-select-clear {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: none;
  background: none;
  color: var(--muted);
  cursor: pointer;
  transition: background 150ms, color 150ms;
}
.ui-select-clear:hover { background: rgba(0,0,0,0.06); color: var(--text); }

.ui-select-chevron {
  color: var(--muted);
  transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
}
.ui-select-chevron.is-open { transform: rotate(180deg); }

/* Panel */
.ui-select-panel {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  z-index: 100;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 6px 10px -6px rgba(0,0,0,0.05);
  overflow: hidden;
  min-width: 200px;
}

/* Search */
.ui-select-search-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
}
.ui-select-search-icon { color: var(--muted); flex-shrink: 0; }
.ui-select-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font: inherit;
  font-size: 0.875rem;
  color: var(--text);
}
.ui-select-search-input::placeholder { color: var(--muted); }

/* List */
.ui-select-list {
  max-height: 220px;
  overflow-y: auto;
  padding: 6px;
}
.ui-select-empty {
  text-align: center;
  padding: 20px;
  font-size: 0.84rem;
  color: var(--muted);
}
.ui-select-option {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  font: inherit;
  font-size: 0.875rem;
  color: var(--text);
  cursor: pointer;
  text-align: left;
  transition: background 100ms;
}
.ui-select-option:hover { background: rgba(29, 158, 117, 0.07); }
.ui-select-option.is-selected { background: rgba(29, 158, 117, 0.1); color: var(--green, #1d9e75); font-weight: 600; }

.ui-select-checkbox {
  width: 16px;
  height: 16px;
  border: 2px solid var(--line);
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: #fff;
  transition: all 100ms;
}
.is-selected .ui-select-checkbox {
  background: var(--green, #1d9e75);
  border-color: var(--green, #1d9e75);
  color: #fff;
}

.ui-select-option-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ui-select-check-single { color: var(--green, #1d9e75); margin-left: auto; flex-shrink: 0; }

/* Footer */
.ui-select-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-top: 1px solid var(--line);
  font-size: 0.8rem;
  color: var(--muted);
}
.ui-select-footer-clear {
  border: none;
  background: none;
  font: inherit;
  font-size: 0.8rem;
  color: var(--danger, #ef4444);
  cursor: pointer;
  padding: 0;
  font-weight: 600;
}
.ui-select-footer-clear:hover { text-decoration: underline; }

/* Errors / hints */
.ui-select-error { font-size: 0.75rem; font-weight: 500; color: var(--danger); }
.ui-select-hint { font-size: 0.75rem; color: var(--muted); }

/* Transition */
.ui-select-fade-enter-active,
.ui-select-fade-leave-active { transition: transform 150ms cubic-bezier(0.16, 1, 0.3, 1), opacity 150ms; }
.ui-select-fade-enter-from,
.ui-select-fade-leave-to { transform: translateY(6px); opacity: 0; }

/* Dark mode */
:global([data-theme="dark"]) .ui-select-trigger { background: var(--surface-strong, rgba(255,255,255,0.03)); }
:global([data-theme="dark"]) .ui-select-panel { background: #111a17; border-color: rgba(255,255,255,0.08); }
:global([data-theme="dark"]) .ui-select-search-input { color: #e5e7eb; }
:global([data-theme="dark"]) .ui-select-checkbox { background: transparent; }
:global([data-theme="dark"]) .ui-select-clear:hover { background: rgba(255,255,255,0.08); }
</style>
