<script setup lang="ts">
import { computed, ref } from 'vue'

defineOptions({ name: 'UiTable' })

interface Column {
  id: string
  accessorKey: string
  header: string
  class?: string
  sortable?: boolean
  /** secondary columns are hidden until user expands with + */
  priority?: 'primary' | 'secondary'
}

const props = withDefaults(defineProps<{
  columns: Column[]
  data: any[]
  loading?: boolean
  sortBy?: string
  sortOrder?: 'asc' | 'desc' | ''
  /** Auto-hide columns after this count when priority not set (default 6) */
  compactThreshold?: number
}>(), {
  loading: false,
  sortBy: '',
  sortOrder: '',
  compactThreshold: 6,
})

const emit = defineEmits<{
  sort: [{ key: string; order: 'asc' | 'desc' | '' }]
}>()

const expanded = ref(false)
const expandedRows = ref<Set<number>>(new Set())

const normalizedColumns = computed(() => {
  const hasExplicit = props.columns.some(c => c.priority === 'secondary')
  if (hasExplicit) return props.columns

  if (props.columns.length <= props.compactThreshold) {
    return props.columns.map(c => ({ ...c, priority: 'primary' as const }))
  }

  // Keep first N-1 and last (often actions) as primary; middle as secondary
  const lastIdx = props.columns.length - 1
  const keepHead = Math.max(props.compactThreshold - 1, 3)
  return props.columns.map((c, i) => ({
    ...c,
    priority: (i < keepHead || i === lastIdx) ? 'primary' as const : 'secondary' as const,
  }))
})

const hasSecondary = computed(() =>
  normalizedColumns.value.some(c => c.priority === 'secondary'),
)

const visibleColumns = computed(() =>
  expanded.value
    ? normalizedColumns.value
    : normalizedColumns.value.filter(c => c.priority !== 'secondary'),
)

const hiddenCount = computed(() =>
  normalizedColumns.value.filter(c => c.priority === 'secondary').length,
)

function handleSort(col: Column) {
  if (!col.sortable) return

  let nextOrder: 'asc' | 'desc' | '' = 'asc'
  if (props.sortBy === col.id || props.sortBy === col.accessorKey) {
    if (props.sortOrder === 'asc') nextOrder = 'desc'
    else if (props.sortOrder === 'desc') nextOrder = ''
    else nextOrder = 'asc'
  }

  emit('sort', {
    key: col.accessorKey || col.id,
    order: nextOrder,
  })
}

function toggleRow(index: number) {
  const next = new Set(expandedRows.value)
  if (next.has(index)) next.delete(index)
  else next.add(index)
  expandedRows.value = next
}

function cellText(item: any, col: Column) {
  const val = item?.[col.accessorKey]
  if (val === null || val === undefined || val === '') return '—'
  return String(val)
}

function isLongText(text: string) {
  return text.length > 48
}
</script>

<template>
  <div class="ui-table-wrap">
    <div v-if="hasSecondary" class="table-expand-bar">
      <button
        type="button"
        class="btn-expand-cols"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
      >
        <i :class="['pi', expanded ? 'pi-minus' : 'pi-plus']" />
        <span>{{ expanded ? 'Thu gọn cột' : `Xem thêm ${hiddenCount} cột` }}</span>
      </button>
    </div>

    <div v-if="loading" class="ui-table-loading">
      <i class="pi pi-spinner pi-spin" />
    </div>

    <div v-else class="table-scroll">
      <table class="ui-table">
        <thead>
          <tr>
            <th v-if="hasSecondary" class="col-toggle">
              <button
                type="button"
                class="btn-plus"
                :title="expanded ? 'Thu gọn cột' : 'Hiện đủ cột'"
                :aria-label="expanded ? 'Thu gọn cột' : 'Hiện đủ cột'"
                @click="expanded = !expanded"
              >
                <i :class="['pi', expanded ? 'pi-minus' : 'pi-plus']" />
              </button>
            </th>
            <th
              v-for="col in visibleColumns"
              :key="col.id"
              :class="[
                col.class,
                col.sortable && 'is-sortable',
                (sortBy === col.id || sortBy === col.accessorKey) && 'is-active-sort',
              ]"
              @click="handleSort(col)"
            >
              <div class="header-content">
                <slot :name="`${col.id}-header`">{{ col.header }}</slot>
                <span v-if="col.sortable" class="sort-icon">
                  <i
                    v-if="sortBy === col.id || sortBy === col.accessorKey"
                    :class="['pi', sortOrder === 'asc' ? 'pi-sort-amount-up' : sortOrder === 'desc' ? 'pi-sort-amount-down-alt' : 'pi-sort-alt']"
                  />
                  <i v-else class="pi pi-sort-alt muted-sort" />
                </span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!data.length">
            <td :colspan="visibleColumns.length + (hasSecondary ? 1 : 0)" class="empty-cell">
              <slot name="empty">
                <div class="empty">
                  <i class="pi pi-inbox" />
                  <p>Không có dữ liệu</p>
                </div>
              </slot>
            </td>
          </tr>

          <template v-else>
            <tr v-for="(item, index) in data" :key="index">
              <td v-if="hasSecondary" class="col-toggle">
                <button
                  type="button"
                  class="btn-plus btn-plus--row"
                  :title="expandedRows.has(index) ? 'Thu gọn dòng' : 'Xem đủ nội dung dòng'"
                  :aria-label="expandedRows.has(index) ? 'Thu gọn dòng' : 'Xem đủ nội dung dòng'"
                  @click="toggleRow(index)"
                >
                  <i :class="['pi', expandedRows.has(index) ? 'pi-minus' : 'pi-plus']" />
                </button>
              </td>
              <td
                v-for="col in visibleColumns"
                :key="col.id"
                :class="[col.class, expandedRows.has(index) && 'is-row-expanded']"
              >
                <slot :name="`${col.id}-cell`" :row="{ original: item, index }">
                  <span
                    class="cell-text"
                    :class="{ 'is-clamped': !expandedRows.has(index) && isLongText(cellText(item, col)) }"
                  >
                    {{ cellText(item, col) }}
                  </span>
                </slot>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.ui-table-wrap {
  position: relative;
  width: 100%;
  border-radius: 12px;
  background: var(--surface-strong, var(--color-surface-strong, #fff));
  border: 1px solid var(--line, var(--color-line));
  overflow: hidden;
}

.table-expand-bar {
  display: flex;
  justify-content: flex-end;
  padding: 8px 12px;
  border-bottom: 1px solid var(--line, var(--color-line));
  background: var(--surface, var(--color-surface));
}

.btn-expand-cols,
.btn-plus {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 1px solid var(--line, var(--color-line));
  background: var(--surface-strong, #fff);
  color: var(--muted, var(--color-text-muted));
  cursor: pointer;
  transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
}

.btn-expand-cols {
  height: 30px;
  padding: 0 10px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 650;
}

.btn-expand-cols:hover,
.btn-plus:hover {
  color: var(--color-primary, #1d9e75);
  border-color: rgba(29, 158, 117, 0.35);
  background: rgba(29, 158, 117, 0.08);
}

.btn-plus {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  font-size: 0.7rem;
}

.btn-plus--row {
  width: 24px;
  height: 24px;
}

.ui-table-loading {
  display: grid;
  place-items: center;
  min-height: 180px;
  color: var(--color-primary, #1d9e75);
  font-size: 1.5rem;
}

.table-scroll {
  overflow-x: auto;
}

.ui-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.ui-table thead {
  background: var(--surface, var(--color-surface));
  border-bottom: 1px solid var(--line, var(--color-line));
}

.ui-table th {
  padding: 10px 12px;
  text-align: left;
  font-weight: 750;
  color: var(--muted, var(--color-text-secondary));
  text-transform: uppercase;
  font-size: 0.68rem;
  letter-spacing: 0.05em;
  white-space: nowrap;
  user-select: none;
}

.ui-table th.is-sortable {
  cursor: pointer;
}

.ui-table th.is-sortable:hover,
.ui-table th.is-active-sort {
  color: var(--color-primary, #1d9e75);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 4px;
}

.sort-icon {
  font-size: 0.7rem;
}

.muted-sort {
  opacity: 0.35;
}

.ui-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--line, var(--color-line));
  color: var(--text, var(--color-text));
  vertical-align: middle;
}

.ui-table tbody tr:hover {
  background: var(--surface, var(--color-surface));
}

.ui-table tbody tr:last-child td {
  border-bottom: none;
}

.col-toggle {
  width: 40px;
  text-align: center;
  padding-left: 8px !important;
  padding-right: 8px !important;
}

.cell-text {
  display: inline;
  color: inherit;
}

.cell-text.is-clamped {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  max-width: 28ch;
}

td.is-row-expanded .cell-text {
  -webkit-line-clamp: unset;
  overflow: visible;
  max-width: none;
  white-space: normal;
}

.empty-cell {
  text-align: center;
  padding: 40px 16px !important;
}

.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  color: var(--muted);
}

.empty i {
  font-size: 1.75rem;
  opacity: 0.35;
}

.empty p {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
}

:global(.dark) .ui-table-wrap,
:global([data-theme='dark']) .ui-table-wrap {
  background: var(--surface-strong);
  border-color: var(--line);
}

:global(.dark) .table-expand-bar,
:global([data-theme='dark']) .table-expand-bar,
:global(.dark) .ui-table thead,
:global([data-theme='dark']) .ui-table thead {
  background: rgba(255, 255, 255, 0.03);
}

:global(.dark) .btn-expand-cols,
:global([data-theme='dark']) .btn-expand-cols,
:global(.dark) .btn-plus,
:global([data-theme='dark']) .btn-plus {
  background: var(--surface);
  border-color: var(--line);
  color: var(--muted);
}
</style>
