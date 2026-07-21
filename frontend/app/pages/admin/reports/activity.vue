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
  created_at?: string | null
  user?: { name?: string } | null
  course?: { title?: string } | null
}

interface DayPoint { date: string, label: string, value: number }
interface NotificationRow { id?: number, title?: string, message?: string, created_at?: string, type?: string }
interface SectionRow {
  id?: number
  code?: string
  name?: string
  enrolled_count?: number
  capacity?: number
  course?: { title?: string } | null
  lecturer?: { name?: string } | null
}

interface Paginator<T> { data: T[] }

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(false)

const recentOrders = ref<OrderRow[]>([])
const notifications = ref<NotificationRow[]>([])
const dailyEnrollments = ref<DayPoint[]>([])
const upcomingSections = ref<SectionRow[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

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

async function load() {
  loading.value = true
  try {
    const [ordersRes, extraRes] = await Promise.all([
      useApi<Paginator<OrderRow>>('/admin/orders', { query: { per_page: 15 } }),
      useApi<{
        daily_enrollments?: DayPoint[]
        notifications?: NotificationRow[]
        upcoming_sections?: SectionRow[]
      }>('/admin/dashboard-extra'),
    ])
    recentOrders.value = ordersRes.data || []
    dailyEnrollments.value = extraRes.daily_enrollments || []
    notifications.value = extraRes.notifications || []
    upcomingSections.value = extraRes.upcoming_sections || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.reports.common.loadError'),
      detail: error?.data?.message,
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
      <div>
        <span class="eyebrow">{{ t('admin.menu.reports') }}</span>
        <h1>{{ t('admin.reports.activity.title') }}</h1>
        <p>{{ t('admin.reports.activity.subtitle') }}</p>
      </div>
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <div class="split">
      <section class="table-panel">
        <div class="panel-head">
          <strong>{{ t('admin.reports.activity.recentOrders') }}</strong>
          <p>{{ t('admin.reports.activity.recentOrdersHint') }}</p>
        </div>
        <DataTable :value="recentOrders" :loading="loading" size="small" striped-rows>
          <Column :header="t('admin.orders.orderId')" style="width:4rem">
            <template #body="{ data }">#{{ data.id }}</template>
          </Column>
          <Column :header="t('admin.orders.buyer')">
            <template #body="{ data }">{{ data.user?.name || '—' }}</template>
          </Column>
          <Column :header="t('admin.orders.amount')" style="width:110px">
            <template #body="{ data }"><span class="money">{{ fmtMoney(data.amount) }}</span></template>
          </Column>
          <Column :header="t('admin.orders.createdAt')" style="width:130px">
            <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
          </Column>
          <template #empty><div class="empty">{{ t('common.noData') }}</div></template>
        </DataTable>
      </section>

      <section class="table-panel">
        <div class="panel-head">
          <strong>{{ t('admin.reports.activity.notifications') }}</strong>
          <p>{{ t('admin.reports.activity.notificationsHint') }}</p>
        </div>
        <DataTable :value="notifications" :loading="loading" size="small" striped-rows>
          <Column :header="t('admin.reports.activity.notificationTitle')">
            <template #body="{ data }">
              <div class="cell-stack">
                <strong>{{ data.title || t('admin.dashboard.notificationFallback') }}</strong>
                <small>{{ data.message || t('admin.dashboard.noContent') }}</small>
              </div>
            </template>
          </Column>
          <Column :header="t('admin.orders.createdAt')" style="width:130px">
            <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
          </Column>
          <template #empty><div class="empty">{{ t('admin.dashboard.noNotifications') }}</div></template>
        </DataTable>
      </section>
    </div>

    <div class="split">
      <section class="table-panel">
        <div class="panel-head">
          <strong>{{ t('admin.reports.activity.dailyEnrollments') }}</strong>
          <p>{{ t('admin.reports.activity.dailyEnrollmentsHint') }}</p>
        </div>
        <DataTable :value="dailyEnrollments" :loading="loading" size="small" striped-rows>
          <Column field="label" :header="t('admin.reports.activity.day')" />
          <Column field="value" :header="t('admin.dashboard.enrollments')" style="width:6rem" />
          <template #empty><div class="empty">{{ t('admin.dashboard.noTraffic') }}</div></template>
        </DataTable>
      </section>

      <section class="table-panel">
        <div class="panel-head">
          <strong>{{ t('admin.reports.activity.upcomingSections') }}</strong>
          <p>{{ t('admin.reports.activity.upcomingSectionsHint') }}</p>
        </div>
        <DataTable :value="upcomingSections" :loading="loading" size="small" striped-rows>
          <Column field="code" :header="t('admin.sections.code')" style="width:90px" />
          <Column :header="t('admin.sections.course')">
            <template #body="{ data }">{{ data.course?.title || data.name || '—' }}</template>
          </Column>
          <Column :header="t('admin.sections.lecturer')">
            <template #body="{ data }">{{ data.lecturer?.name || t('admin.dashboard.noLecturer') }}</template>
          </Column>
          <Column :header="t('admin.sections.enrolled')" style="width:90px">
            <template #body="{ data }">{{ data.enrolled_count ?? 0 }}/{{ data.capacity ?? '—' }}</template>
          </Column>
          <template #empty><div class="empty">{{ t('admin.dashboard.noOpenClasses') }}</div></template>
        </DataTable>
      </section>
    </div>
  </div>
</template>

<style scoped>
.report-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.split { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.panel-head { margin-bottom: 10px; }
.panel-head strong { font-size: .92rem; }
.panel-head p { margin: 2px 0 0; color: var(--text-muted); font-size: .82rem; }

.cell-stack small { display: block; color: var(--text-muted); font-size: .78rem; }
.money { font-weight: 700; color: var(--brand); font-variant-numeric: tabular-nums; }
.empty { padding: 28px; text-align: center; color: var(--text-muted); }

@media (max-width: 900px) { .split { grid-template-columns: 1fr; } }
</style>
