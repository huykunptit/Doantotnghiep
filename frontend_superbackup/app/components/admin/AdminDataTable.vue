<script setup lang="ts">
import type { CSSProperties } from 'vue'
import { computed, useAttrs, useSlots } from 'vue'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import AdminDetailPanel from './AdminDetailPanel.vue'

defineOptions({ inheritAttrs: false })

interface AdminDataColumn {
  field: string
  header: string
  sortable?: boolean
  hiddenBelow?: 'sm' | 'md' | 'lg' | 'xl'
  style?: string | CSSProperties
  headerStyle?: string | CSSProperties
  bodyClass?: string
  headerClass?: string
}

interface AdminTableQuery {
  page: number
  perPage: number
  first: number
  sortField: string | null
  sortOrder: 1 | -1 | 0 | null
  filters: Record<string, unknown>
}

const props = withDefaults(defineProps<{
  rows?: Record<string, any>[]
  columns: AdminDataColumn[]
  dataKey?: string
  totalRecords?: number
  page?: number
  rowsPerPage?: number
  rowsPerPageOptions?: number[]
  paginator?: boolean
  lazy?: boolean
  loading?: boolean
  error?: string | null
  emptyTitle?: string
  emptyDescription?: string
  sortField?: string | null
  sortOrder?: 1 | -1 | 0 | null
  filters?: Record<string, any>
  filterDisplay?: 'menu' | 'row'
  selection?: Record<string, any>[] | Record<string, any> | null
  selectable?: boolean
  selectionMode?: 'single' | 'multiple'
  expandedRows?: Record<string, any>[] | Record<string, boolean>
  stripedRows?: boolean
  size?: 'small' | 'large'
}>(), {
  rows: () => [],
  dataKey: 'id',
  totalRecords: 0,
  page: 1,
  rowsPerPage: 20,
  rowsPerPageOptions: () => [10, 20, 50, 100],
  paginator: true,
  lazy: true,
  loading: false,
  error: null,
  emptyTitle: 'Không có dữ liệu',
  emptyDescription: 'Không tìm thấy bản ghi phù hợp với bộ lọc hiện tại.',
  sortField: null,
  sortOrder: null,
  filters: () => ({}),
  filterDisplay: 'menu',
  selection: () => [],
  selectable: false,
  selectionMode: 'multiple',
  expandedRows: () => [],
  stripedRows: true,
  size: 'small',
})

const emit = defineEmits<{
  'update:selection': [value: Record<string, any>[] | Record<string, any> | null]
  'update:filters': [value: Record<string, any>]
  'update:expandedRows': [value: Record<string, any>[] | Record<string, boolean>]
  page: [event: any]
  sort: [event: any]
  filter: [event: any]
  retry: []
  rowExpand: [event: any]
  rowCollapse: [event: any]
  queryChange: [query: AdminTableQuery]
}>()

const attrs = useAttrs()
const slots = useSlots()
const first = computed(() => Math.max(0, (props.page - 1) * props.rowsPerPage))
const hasDetail = computed(() => Boolean(slots.detail))
const selectedCount = computed(() =>
  Array.isArray(props.selection) ? props.selection.length : props.selection ? 1 : 0,
)

function valueAt(row: Record<string, any>, path: string) {
  return path.split('.').reduce<any>((value, key) => value?.[key], row)
}

function responsiveClass(column: AdminDataColumn) {
  return column.hiddenBelow ? `admin-data-table__hide-below-${column.hiddenBelow}` : undefined
}

function currentQuery(overrides: Partial<AdminTableQuery> = {}): AdminTableQuery {
  return {
    page: props.page,
    perPage: props.rowsPerPage,
    first: first.value,
    sortField: props.sortField,
    sortOrder: props.sortOrder,
    filters: props.filters,
    ...overrides,
  }
}

function onPage(event: any) {
  emit('page', event)
  emit('queryChange', currentQuery({
    page: Number(event.page ?? 0) + 1,
    perPage: Number(event.rows ?? props.rowsPerPage),
    first: Number(event.first ?? 0),
  }))
}

function onSort(event: any) {
  emit('sort', event)
  emit('queryChange', currentQuery({
    page: 1,
    first: 0,
    sortField: typeof event.sortField === 'string' ? event.sortField : null,
    sortOrder: event.sortOrder ?? null,
  }))
}

function onFilter(event: any) {
  emit('filter', event)
  emit('queryChange', currentQuery({
    page: 1,
    first: 0,
    filters: event.filters ?? props.filters,
  }))
}
</script>

<template>
  <div class="admin-data-table">
    <div
      v-if="$slots.toolbar || $slots.export || $slots.bulkActions"
      class="admin-data-table__toolbar"
    >
      <div class="admin-data-table__toolbar-main">
        <slot name="toolbar" />
        <div v-if="selectedCount > 0 && $slots.bulkActions" class="admin-data-table__bulk">
          <span>{{ selectedCount }} đã chọn</span>
          <slot name="bulkActions" :selection="selection" :count="selectedCount" />
        </div>
      </div>
      <div v-if="$slots.export" class="admin-data-table__export">
        <slot name="export" :rows="rows" :query="currentQuery()" />
      </div>
    </div>

    <div v-if="error" class="admin-data-table__state admin-data-table__state--error" role="alert">
      <slot name="error" :error="error">
        <i class="pi pi-exclamation-circle" aria-hidden="true" />
        <div>
          <strong>Không thể tải dữ liệu</strong>
          <p>{{ error }}</p>
        </div>
        <Button label="Thử lại" icon="pi pi-refresh" size="small" outlined @click="emit('retry')" />
      </slot>
    </div>

    <DataTable
      v-else
      v-bind="attrs"
      :value="rows"
      :data-key="dataKey"
      :lazy="lazy"
      :loading="loading"
      :paginator="paginator"
      :first="first"
      :rows="rowsPerPage"
      :rows-per-page-options="rowsPerPageOptions"
      :total-records="totalRecords"
      :sort-field="sortField || undefined"
      :sort-order="sortOrder || undefined"
      :filters="filters"
      :filter-display="filterDisplay"
      :selection="selection"
      :expanded-rows="expandedRows"
      :striped-rows="stripedRows"
      :size="size"
      paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
      current-page-report-template="{first}–{last} / {totalRecords}"
      responsive-layout="scroll"
      @page="onPage"
      @sort="onSort"
      @filter="onFilter"
      @update:filters="emit('update:filters', $event)"
      @update:selection="emit('update:selection', $event)"
      @update:expanded-rows="emit('update:expandedRows', $event)"
      @row-expand="emit('rowExpand', $event)"
      @row-collapse="emit('rowCollapse', $event)"
    >
      <template v-if="$slots.header" #header>
        <slot name="header" :query="currentQuery()" />
      </template>

      <Column
        v-if="selectable"
        :selection-mode="selectionMode"
        header-style="width: 3rem"
        :exportable="false"
        frozen
      />
      <Column
        v-if="hasDetail"
        expander
        header-style="width: 3rem"
        :exportable="false"
      />

      <Column
        v-for="column in columns"
        :key="column.field"
        :field="column.field"
        :header="column.header"
        :sortable="column.sortable"
        :style="column.style"
        :header-style="column.headerStyle"
        :body-class="[column.bodyClass, responsiveClass(column)]"
        :header-class="[column.headerClass, responsiveClass(column)]"
      >
        <template #body="{ data, index }">
          <slot
            :name="`cell-${column.field}`"
            :row="data"
            :data="data"
            :index="index"
            :value="valueAt(data, column.field)"
            :column="column"
          >
            {{ valueAt(data, column.field) ?? '—' }}
          </slot>
        </template>
        <template v-if="$slots[`filter-${column.field}`]" #filter="{ filterModel, filterCallback }">
          <slot
            :name="`filter-${column.field}`"
            :filter-model="filterModel"
            :filter-callback="filterCallback"
            :column="column"
          />
        </template>
      </Column>

      <Column
        v-if="$slots.actions"
        :header="$slots.actionHeader ? undefined : 'Thao tác'"
        :exportable="false"
        header-class="admin-data-table__actions-column"
        body-class="admin-data-table__actions-column"
      >
        <template v-if="$slots.actionHeader" #header>
          <slot name="actionHeader" />
        </template>
        <template #body="{ data, index }">
          <div class="admin-data-table__row-actions">
            <slot name="actions" :row="data" :data="data" :index="index" />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="admin-data-table__empty">
          <slot name="empty">
            <i class="pi pi-inbox" aria-hidden="true" />
            <strong>{{ emptyTitle }}</strong>
            <span>{{ emptyDescription }}</span>
          </slot>
        </div>
      </template>

      <template #expansion="{ data }">
        <AdminDetailPanel class="admin-data-table__detail">
          <slot name="detail" :row="data" :data="data" />
        </AdminDetailPanel>
      </template>

      <template v-if="$slots.footer" #footer>
        <slot name="footer" :query="currentQuery()" />
      </template>
    </DataTable>
  </div>
</template>

<style scoped>
.admin-data-table {
  min-width: 0;
  color: var(--text, var(--p-text-color));
}

.admin-data-table__toolbar,
.admin-data-table__toolbar-main,
.admin-data-table__bulk,
.admin-data-table__export,
.admin-data-table__row-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.admin-data-table__toolbar {
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--line, var(--p-content-border-color));
  background: var(--surface-card, var(--p-content-background));
}

.admin-data-table__toolbar-main {
  min-width: 0;
}

.admin-data-table__bulk {
  padding: 0.35rem 0.6rem;
  border-radius: var(--p-border-radius-md, 0.5rem);
  background: color-mix(in srgb, var(--p-primary-500) 9%, transparent);
  color: var(--p-primary-700);
  font-size: 0.78rem;
  font-weight: 650;
}

.admin-data-table__state {
  display: flex;
  min-height: 12rem;
  align-items: center;
  justify-content: center;
  gap: 0.8rem;
  padding: 2rem;
  color: var(--muted, var(--p-text-muted-color));
  text-align: left;
}

.admin-data-table__state strong {
  color: var(--text, var(--p-text-color));
}

.admin-data-table__state p {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
}

.admin-data-table__state--error > i {
  color: var(--p-red-500);
  font-size: 1.5rem;
}

.admin-data-table__empty {
  display: grid;
  min-height: 10rem;
  place-items: center;
  align-content: center;
  gap: 0.5rem;
  color: var(--muted, var(--p-text-muted-color));
  text-align: center;
}

.admin-data-table__empty i {
  font-size: 1.7rem;
}

.admin-data-table__empty strong {
  color: var(--text, var(--p-text-color));
}

.admin-data-table__row-actions {
  justify-content: flex-end;
  white-space: nowrap;
}

.admin-data-table__detail {
  margin: 0.5rem;
}

:deep(.p-datatable) {
  --p-datatable-header-background: var(--surface-card, var(--p-content-background));
  --p-datatable-row-background: var(--surface-card, var(--p-content-background));
  --p-datatable-header-cell-background: var(--surface-ground, var(--p-surface-50));
  --p-datatable-row-striped-background: var(--surface-ground, var(--p-surface-50));
  --p-datatable-border-color: var(--line, var(--p-content-border-color));
}

:deep(.p-datatable-thead > tr > th) {
  color: var(--muted, var(--p-text-muted-color));
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

:deep(.admin-data-table__actions-column) {
  text-align: right;
}

@media (max-width: 1279px) {
  :deep(.admin-data-table__hide-below-xl) {
    display: none;
  }
}

@media (max-width: 1023px) {
  :deep(.admin-data-table__hide-below-lg) {
    display: none;
  }
}

@media (max-width: 767px) {
  :deep(.admin-data-table__hide-below-md) {
    display: none;
  }
}

@media (max-width: 639px) {
  :deep(.admin-data-table__hide-below-sm) {
    display: none;
  }

  .admin-data-table__state {
    align-items: center;
    flex-direction: column;
    text-align: center;
  }
}
</style>
