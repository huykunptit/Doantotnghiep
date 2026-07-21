<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface OrderUser { id?: number, name?: string, email?: string, avatar?: string | null }
interface OrderCourse { id?: number, title?: string, thumbnail?: string | null, price?: number }
interface OrderRow {
  id: number
  amount: number
  status: string
  payment_method?: string | null
  payment_ref?: string | null
  paid_at?: string | null
  created_at?: string | null
  user?: OrderUser | null
  course?: OrderCourse | null
  gateway_response?: Record<string, unknown> | null
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
  per_page: number
}

const PAID = ['paid', 'completed']

const { t, locale } = useI18n()
const toast = useToast()

const loading = ref(false)
const detailLoading = ref(false)
const rows = ref<OrderRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  status: null as string | null,
})

const counts = reactive({ all: 0, paid: 0, pending: 0, failed: 0 })
const statusChip = ref<string | null>(null)

const viewOpen = ref(false)
const selected = ref<OrderRow | null>(null)

const statusOptions = computed(() => [
  { label: t('admin.orders.statuses.completed'), value: 'completed' },
  { label: t('admin.orders.statuses.paid'), value: 'paid' },
  { label: t('admin.orders.statuses.pending'), value: 'pending' },
  { label: t('admin.orders.statuses.failed'), value: 'failed' },
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

let searchTimer: ReturnType<typeof setTimeout> | null = null

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value))
}

function fmtMoney(value = 0) {
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency', currency: 'VND', maximumFractionDigits: 0,
  }).format(value)
}

function isPaid(status: string) {
  return PAID.includes(status)
}

function statusTone(status: string) {
  if (isPaid(status)) return 'tone-paid'
  if (status === 'pending') return 'tone-pending'
  if (status === 'failed') return 'tone-failed'
  return 'tone-neutral'
}

function statusLabel(status: string) {
  const key = `admin.orders.statuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

function rowIndex(index: number) {
  return (page.value - 1) * perPage.value + index + 1
}

function toQuery() {
  return {
    page: page.value,
    per_page: perPage.value,
    search: tableSearch.value.trim() || undefined,
    status: statusChip.value || filters.status || undefined,
  }
}

async function loadCounts() {
  try {
    const [all, completed, paid, pending, failed] = await Promise.all([
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 1 } }),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 1, status: 'completed' } }),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 1, status: 'paid' } }),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 1, status: 'pending' } }),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 1, status: 'failed' } }),
    ])
    counts.all = all.total || 0
    counts.paid = (completed.total || 0) + (paid.total || 0)
    counts.pending = pending.total || 0
    counts.failed = failed.total || 0
  }
  catch { /* ignore */ }
}

async function loadOrders() {
  loading.value = true
  try {
    const res = await useApi<Paginator<OrderRow>>('/admin/orders', { query: toQuery() })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.orders.loadError'),
      detail: error?.data?.message || t('admin.dashboard.tryAgain'),
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function applyFilters() {
  statusChip.value = null
  page.value = 1
  loadOrders()
}

function resetFilters() {
  filters.status = null
  statusChip.value = null
  page.value = 1
  loadOrders()
}

function onTableSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadOrders()
  }, 350)
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  loadOrders()
}

function setStatusChip(status: string | null) {
  statusChip.value = status
  filters.status = null
  page.value = 1
  loadOrders()
}

async function openView(order: OrderRow) {
  viewOpen.value = true
  selected.value = order
  detailLoading.value = true
  try {
    selected.value = await useApi<OrderRow>(`/admin/orders/${order.id}`)
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.orders.detailError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    detailLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadCounts(), loadOrders()])
})
</script>

<template>
  <div class="page orders-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.finance') }}</span>
        <h1>{{ t('admin.orders.title') }}</h1>
        <p>{{ t('admin.orders.subtitle') }}</p>
      </div>
    </header>

    <section class="metric-rail">
      <button type="button" class="metric" :class="{ on: !statusChip }" @click="setStatusChip(null)">
        <strong>{{ counts.all }}</strong>
        <span>{{ t('admin.orders.allOrders') }}</span>
      </button>
      <button type="button" class="metric" :class="{ on: statusChip === 'completed' }" @click="setStatusChip('completed')">
        <strong>{{ counts.paid }}</strong>
        <span>{{ t('admin.orders.paid') }}</span>
      </button>
      <button type="button" class="metric" :class="{ on: statusChip === 'pending' }" @click="setStatusChip('pending')">
        <strong>{{ counts.pending }}</strong>
        <span>{{ t('admin.orders.pending') }}</span>
      </button>
      <button type="button" class="metric" :class="{ on: statusChip === 'failed' }" @click="setStatusChip('failed')">
        <strong>{{ counts.failed }}</strong>
        <span>{{ t('admin.orders.failed') }}</span>
      </button>
    </section>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.orders.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.orders.paymentStatus') }}</span>
            <Select
              v-model="filters.status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              show-clear
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.orders.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.orders.reset')" icon="pi pi-times" size="small" severity="secondary" text @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <div class="toolbar-left">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="tableSearch"
              :placeholder="t('admin.orders.searchPh')"
              @input="onTableSearch"
            />
          </IconField>
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
        </div>
        <div class="toolbar-actions">
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="loadOrders" />
        </div>
      </div>

      <DataTable
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[10, 15, 25, 50]"
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        :current-page-report-template="t('admin.users.pageReport')"
        striped-rows
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ rowIndex(index) }}</template>
        </Column>
        <Column :header="t('admin.orders.buyer')" style="min-width:200px">
          <template #body="{ data }">
            <div class="user-cell">
              <Avatar v-if="data.user?.avatar" :image="data.user.avatar" shape="circle" />
              <Avatar v-else :label="(data.user?.name || '?').slice(0, 1).toUpperCase()" shape="circle" />
              <div>
                <button type="button" class="name-link" @click="openView(data)">{{ data.user?.name || '—' }}</button>
                <small>{{ data.user?.email || '—' }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.orders.course')" style="min-width:180px">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column field="amount" :header="t('admin.orders.amount')" style="min-width:120px">
          <template #body="{ data }"><span class="money">{{ fmtMoney(data.amount) }}</span></template>
        </Column>
        <Column field="status" :header="t('admin.orders.paymentStatus')" style="min-width:120px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.orders.paidAt')" style="min-width:130px">
          <template #body="{ data }">{{ fmtDate(data.paid_at || data.created_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:5rem">
          <template #body="{ data }">
            <Button icon="pi pi-eye" text rounded severity="secondary" :aria-label="t('admin.orders.view')" @click="openView(data)" />
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('common.noData') }}</div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="viewOpen"
      modal
      :header="t('admin.orders.viewTitle', { id: selected?.id || '' })"
      :style="{ width: 'min(640px, 96vw)' }"
      :dismissable-mask="true"
    >
      <div v-if="detailLoading" class="detail-loading">
        <ProgressSpinner style="width:32px;height:32px" stroke-width="4" />
      </div>
      <div v-else class="detail-grid">
        <div class="detail-block">
          <span>{{ t('admin.orders.buyer') }}</span>
          <strong>{{ selected?.user?.name || '—' }}</strong>
          <small>{{ selected?.user?.email || '—' }}</small>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.course') }}</span>
          <strong>{{ selected?.course?.title || '—' }}</strong>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.amount') }}</span>
          <strong class="money">{{ fmtMoney(selected?.amount || 0) }}</strong>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.paymentStatus') }}</span>
          <span v-if="selected" class="pill" :class="statusTone(selected.status)">{{ statusLabel(selected.status) }}</span>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.paymentMethod') }}</span>
          <strong>{{ selected?.payment_method || '—' }}</strong>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.paymentRef') }}</span>
          <code>{{ selected?.payment_ref || '—' }}</code>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.createdAt') }}</span>
          <strong>{{ fmtDate(selected?.created_at) }}</strong>
        </div>
        <div class="detail-block">
          <span>{{ t('admin.orders.paidAt') }}</span>
          <strong>{{ fmtDate(selected?.paid_at) }}</strong>
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="viewOpen = false" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.orders-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.metric-rail { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.metric {
  display: flex; flex-direction: column; gap: 2px; align-items: flex-start;
  min-height: 72px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
  color: var(--text); font: inherit; text-align: left; cursor: pointer;
}
.metric strong { font-family: var(--font-display); font-size: 1.35rem; font-weight: 700; }
.metric span { color: var(--text-muted); font-size: .78rem; font-weight: 600; }
.metric.on {
  border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
  background: var(--brand-soft);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 20%, transparent);
}

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle); }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.filter-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.w-full { width: 100%; }

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toolbar-left strong { font-size: .92rem; white-space: nowrap; }
.toolbar-actions { display: flex; align-items: center; gap: 6px; }

.user-cell { display: flex; align-items: center; gap: 10px; }
.user-cell small { display: block; color: var(--text-muted); font-size: .78rem; }
.name-link {
  border: 0; background: none; padding: 0; color: var(--text);
  font: inherit; font-weight: 700; cursor: pointer;
}
.name-link:hover { color: var(--brand); }
.money { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--brand); }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.tone-paid { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef9c3; color: #a16207; }
.tone-failed { background: #fee2e2; color: #b91c1c; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.empty { padding: 40px; color: var(--text-muted); text-align: center; }

.detail-loading { display: grid; place-items: center; padding: 32px; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.detail-block { display: flex; flex-direction: column; gap: 4px; }
.detail-block > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.detail-block small { color: var(--text-muted); font-size: .78rem; }
code { font-family: ui-monospace, monospace; font-size: .82rem; word-break: break-all; }

@media (max-width: 900px) {
  .metric-rail { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 720px) {
  .metric-rail, .detail-grid { grid-template-columns: 1fr; }
}
</style>
