<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface OrderRow {
  id: number
  amount: number
  status: string
  paid_at?: string | null
  created_at?: string | null
  user?: { name?: string, email?: string } | null
  course?: { title?: string } | null
}

interface Paginator<T> { data: T[], total: number }

interface Stats {
  total_revenue?: number
  paid_orders?: number
  total_orders?: number
}

const PAID = ['paid', 'completed']

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(false)

const stats = ref<Stats>({})
const pendingCount = ref(0)
const recentPaid = ref<OrderRow[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtMoney(value = 0) {
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency', currency: 'VND', maximumFractionDigits: 0,
  }).format(value)
}

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

function fmtNumber(value = 0) {
  return value.toLocaleString(numberLocale.value)
}

const kpis = computed(() => [
  { label: t('admin.reports.payments.totalRevenue'), value: fmtMoney(stats.value.total_revenue || 0), icon: 'pi-wallet', tone: 'brand' },
  { label: t('admin.reports.payments.paidCount'), value: fmtNumber(stats.value.paid_orders || 0), icon: 'pi-check-circle', tone: 'green' },
  { label: t('admin.reports.payments.pendingCount'), value: fmtNumber(pendingCount.value), icon: 'pi-clock', tone: 'amber' },
])

async function load() {
  loading.value = true
  try {
    const [statsRes, pendingRes, completedRes, paidRes] = await Promise.all([
      useApi<Stats>('/admin/stats'),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 1, status: 'pending' } }),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 50, status: 'completed' } }),
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 50, status: 'paid' } }).catch(() => ({ data: [] as OrderRow[] })),
    ])
    stats.value = statsRes
    pendingCount.value = pendingRes.total || 0

    const merged = [...(completedRes.data || []), ...(paidRes.data || [])]
    recentPaid.value = merged
      .filter(o => PAID.includes(o.status))
      .sort((a, b) => new Date(b.paid_at || b.created_at || 0).getTime() - new Date(a.paid_at || a.created_at || 0).getTime())
      .slice(0, 20)
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.reports.common.loadError'),
      detail: error?.data?.message || t('admin.dashboard.tryAgain'),
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page report-page">
    <header class="workspace-head">
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="kpi-rail">
      <article v-for="kpi in kpis" :key="kpi.label" class="kpi" :class="kpi.tone">
        <i :class="['pi', kpi.icon]" />
        <div>
          <span>{{ kpi.label }}</span>
          <strong>{{ kpi.value }}</strong>
        </div>
      </article>
    </section>

    <section class="table-panel">
      <div class="panel-head">
        <div>
          <strong>{{ t('admin.reports.payments.recentPaid') }}</strong>
          <p>{{ t('admin.reports.payments.recentPaidHint') }}</p>
        </div>
      </div>
      <DataTable :value="recentPaid" :loading="loading" data-key="id" striped-rows>
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column :header="t('admin.orders.buyer')" style="min-width:160px">
          <template #body="{ data }">
            <div class="cell-stack">
              <strong>{{ data.user?.name || '—' }}</strong>
              <small>{{ data.user?.email || '—' }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.orders.course')" style="min-width:160px">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column :header="t('admin.orders.amount')" style="min-width:110px">
          <template #body="{ data }"><span class="money">{{ fmtMoney(data.amount) }}</span></template>
        </Column>
        <Column :header="t('admin.orders.paidAt')" style="min-width:110px">
          <template #body="{ data }">{{ fmtDate(data.paid_at || data.created_at) }}</template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.report-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: flex-end; gap: 16px; }

.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.kpi-rail { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.kpi {
  display: flex; align-items: center; gap: 12px; padding: 16px;
  border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.kpi i { font-size: 1.25rem; color: var(--brand); }
.kpi span { display: block; color: var(--text-muted); font-size: .76rem; font-weight: 600; }
.kpi strong { font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; }
.kpi.green i { color: #15803d; }
.kpi.amber i { color: #a16207; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.panel-head { margin-bottom: 10px; padding: 4px 4px 8px; }
.panel-head strong { font-size: .95rem; }
.panel-head p { margin: 2px 0 0; color: var(--text-muted); font-size: .82rem; }

.cell-stack small { display: block; color: var(--text-muted); font-size: .78rem; }
.money { font-weight: 700; color: var(--brand); font-variant-numeric: tabular-nums; }

@media (max-width: 720px) { .kpi-rail { grid-template-columns: 1fr; } }
</style>
