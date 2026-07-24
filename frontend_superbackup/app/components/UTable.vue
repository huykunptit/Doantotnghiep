<script setup lang="ts">
defineOptions({ name: 'UTable' })

interface Column {
  id: string
  accessorKey: string
  header: string
  class?: string
}

interface RowData {
  original: any
  index: number
}

const props = defineProps<{
  columns: Column[]
  data: any[]
  loading?: boolean
}>()

const slots = defineSlots()
</script>

<template>
  <div class="utable-wrapper">
    <div v-if="loading" class="utable-loading">
      <i class="pi pi-spinner pi-spin" style="font-size: 2rem" />
    </div>
    <table v-else class="utable">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.id" :class="col.class">
            <slot :name="`${col.id}-header`">
              {{ col.header }}
            </slot>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, index) in data" :key="index">
          <td v-for="col in columns" :key="col.id" :class="col.class">
            <slot :name="`${col.id}-cell`" :row="{ original: item, index }">
              {{ item[col.accessorKey] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.utable-wrapper {
  position: relative;
  min-height: 200px;
}

.utable-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  color: var(--p-primary-color);
}

.utable {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9375rem;
}

.utable thead {
  background: var(--surface-50);
  border-bottom: 2px solid var(--surface-200);
}

:global(.dark) .utable thead {
  background: var(--surface-800);
  border-bottom-color: var(--surface-700);
}

.utable th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  color: var(--text-color);
}

.utable td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--surface-200);
  color: var(--text-color);
}

:global(.dark) .utable td {
  border-bottom-color: var(--surface-700);
}

.utable tbody tr:hover {
  background: var(--surface-50);
}

:global(.dark) .utable tbody tr:hover {
  background: var(--surface-800);
}

.text-right {
  text-align: right;
}
</style>

