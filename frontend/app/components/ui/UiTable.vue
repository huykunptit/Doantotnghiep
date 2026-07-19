<script setup lang="ts">
defineOptions({ name: 'UiTable' })

interface Column {
  id: string
  accessorKey: string
  header: string
  class?: string
  sortable?: boolean
}

const props = withDefaults(defineProps<{
  columns: Column[]
  data: any[]
  loading?: boolean
  sortBy?: string
  sortOrder?: 'asc' | 'desc' | ''
}>(), {
  loading: false,
  sortBy: '',
  sortOrder: '',
})

const emit = defineEmits<{
  sort: [{ key: string; order: 'asc' | 'desc' | '' }]
}>()

function handleSort(col: Column) {
  if (!col.sortable) return
  
  let nextOrder: 'asc' | 'desc' | '' = 'asc'
  if (props.sortBy === col.id || props.sortBy === col.accessorKey) {
    if (props.sortOrder === 'asc') {
      nextOrder = 'desc'
    } else if (props.sortOrder === 'desc') {
      nextOrder = ''
    } else {
      nextOrder = 'asc'
    }
  }
  
  emit('sort', {
    key: col.accessorKey || col.id,
    order: nextOrder
  })
}
</script>

<template>
  <div class="ui-table-wrapper">
    <div v-if="loading" class="ui-table-loading">
      <i class="pi pi-spinner pi-spin" style="font-size: 2rem" />
    </div>
    
    <div v-else class="overflow-x-auto">
      <table class="ui-table">
        <thead>
          <tr>
            <th 
              v-for="col in columns" 
              :key="col.id" 
              :class="[
                col.class, 
                col.sortable && 'is-sortable',
                (sortBy === col.id || sortBy === col.accessorKey) && 'is-active-sort'
              ]"
              @click="handleSort(col)"
            >
              <div class="header-content">
                <slot :name="`${col.id}-header`">
                  {{ col.header }}
                </slot>
                
                <span v-if="col.sortable" class="sort-icon-wrap">
                  <i 
                    v-if="sortBy === col.id || sortBy === col.accessorKey"
                    :class="[
                      'pi', 
                      sortOrder === 'asc' ? 'pi-sort-amount-up' : sortOrder === 'desc' ? 'pi-sort-amount-down-alt' : 'pi-sort-alt'
                    ]"
                  />
                  <i v-else class="pi pi-sort-alt text-[var(--color-text-muted)] opacity-40 hover:opacity-100" />
                </span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="data.length === 0">
            <td :colspan="columns.length" class="text-center py-12">
              <slot name="empty">
                <div class="flex flex-col items-center justify-center gap-2 text-[var(--color-text-muted)]">
                  <i class="pi pi-inbox text-3xl opacity-40" />
                  <p class="text-sm font-medium">Không có dữ liệu</p>
                </div>
              </slot>
            </td>
          </tr>
          <tr v-else v-for="(item, index) in data" :key="index">
            <td v-for="col in columns" :key="col.id" :class="col.class">
              <slot :name="`${col.id}-cell`" :row="{ original: item, index }">
                {{ item[col.accessorKey] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.ui-table-wrapper {
  position: relative;
  width: 100%;
  border-radius: 12px;
  background: var(--color-surface-strong, #fff);
}

.ui-table-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  color: var(--color-primary);
}

.ui-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.ui-table thead {
  background: var(--color-surface, #fbf7ef);
  border-bottom: 1px solid var(--color-line);
}

.ui-table th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 700;
  color: var(--color-text-secondary, #4a6059);
  text-transform: uppercase;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  user-select: none;
}

.ui-table th.is-sortable {
  cursor: pointer;
}

.ui-table th.is-sortable:hover {
  background: rgba(var(--color-primary-rgb), 0.04);
  color: var(--color-primary);
}

.ui-table th.is-active-sort {
  color: var(--color-primary);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 6px;
}

.sort-icon-wrap {
  display: inline-flex;
  align-items: center;
  font-size: 0.75rem;
}

.ui-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-line-soft);
  color: var(--color-text);
  vertical-align: middle;
}

.ui-table tbody tr {
  transition: background 150ms;
}

.ui-table tbody tr:hover {
  background: var(--color-surface);
}

.ui-table tbody tr:last-child td {
  border-bottom: none;
}

/* Helper align classes */
.text-right {
  text-align: right;
}
.text-center {
  text-align: center;
}

/* Dark theme overrides */
:global([data-theme="dark"]) .ui-table-wrapper {
  background: rgba(255, 255, 255, 0.03);
}

:global([data-theme="dark"]) .ui-table thead {
  background: rgba(255, 255, 255, 0.02);
  border-bottom-color: rgba(255, 255, 255, 0.08);
}

:global([data-theme="dark"]) .ui-table th {
  color: rgba(255, 255, 255, 0.7);
}

:global([data-theme="dark"]) .ui-table td {
  border-bottom-color: rgba(255, 255, 255, 0.06);
  color: rgba(255, 255, 255, 0.9);
}

:global([data-theme="dark"]) .ui-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.02);
}
</style>
