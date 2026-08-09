<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface VoucherRow {
  id: number
  name: string
  description?: string | null
  type: string
  discount_value?: number | null
  points_cost: number
  total_quantity?: number | null
  redeemed_count?: number
  is_active?: boolean
  expires_at?: string | null
  course_id?: number | null
}

interface PointsStats {
  totals?: {
    total_issued?: number
    total_redeemed?: number
    active_vouchers?: number
    redemptions?: number
  }
  trend?: Array<{ date: string, earned: number }>
  top_students?: Array<{ id: number, name: string, student_code?: string, points_balance?: number }>
  recent_redemptions?: Array<{
    id: number
    points_spent?: number
    created_at?: string
    user?: { name?: string, student_code?: string }
    voucher?: { name?: string }
  }>
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const activeTab = ref<'overview' | 'vouchers'>('overview')
const statsLoading = ref(false)
const voucherLoading = ref(false)
const stats = ref<PointsStats | null>(null)
const vouchers = ref<VoucherRow[]>([])
const voucherPage = ref(1)
const voucherTotal = ref(0)

const modalOpen = ref(false)
const editing = ref<VoucherRow | null>(null)
const saving = ref(false)
const form = reactive({
  name: '',
  description: '',
  type: 'discount_percent',
  discount_value: null as number | null,
  points_cost: 100,
  total_quantity: null as number | null,
  is_active: true,
  expires_at: null as Date | null,
  course_id: null as number | null,
})

const typeOptions = computed(() => [
  { label: t('admin.points.types.discount_percent'), value: 'discount_percent' },
  { label: t('admin.points.types.discount_fixed'), value: 'discount_fixed' },
  { label: t('admin.points.types.free_course'), value: 'free_course' },
  { label: t('admin.points.types.physical_gift'), value: 'physical_gift' },
  { label: t('admin.points.types.ai_quota'), value: 'ai_quota' },
])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const fmtNum = (n = 0) => Number(n || 0).toLocaleString(numberLocale.value)
const fmtDate = (value?: string | null) => {
  if (!value) return t('admin.points.noExpiry')
  return new Intl.DateTimeFormat(numberLocale.value, { dateStyle: 'medium' }).format(new Date(value))
}

const trendChart = computed(() => {
  const points = stats.value?.trend || []
  return {
    data: {
      labels: points.map(p => String(p.date).slice(5)),
      datasets: [{
        label: t('admin.points.issued'),
        data: points.map(p => p.earned),
        backgroundColor: '#0f766e',
        borderRadius: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
    },
  }
})

async function loadStats() {
  statsLoading.value = true
  try {
    stats.value = await useApi<PointsStats>('/admin/points/stats')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.points.statsError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    statsLoading.value = false
  }
}

async function loadVouchers() {
  voucherLoading.value = true
  try {
    const res = await useApi<any>('/admin/vouchers', { query: { page: voucherPage.value } })
    vouchers.value = res.data || []
    voucherTotal.value = res.total || res.meta?.total || vouchers.value.length
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.points.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    voucherLoading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    name: '', description: '', type: 'discount_percent', discount_value: null,
    points_cost: 100, total_quantity: null, is_active: true, expires_at: null, course_id: null,
  })
  modalOpen.value = true
}

function openEdit(row: VoucherRow) {
  editing.value = row
  Object.assign(form, {
    name: row.name,
    description: row.description || '',
    type: row.type,
    discount_value: row.discount_value ?? null,
    points_cost: row.points_cost,
    total_quantity: row.total_quantity ?? null,
    is_active: !!row.is_active,
    expires_at: row.expires_at ? new Date(row.expires_at) : null,
    course_id: row.course_id ?? null,
  })
  modalOpen.value = true
}

async function save() {
  if (!form.name.trim() || !form.points_cost) {
    toast.add({ severity: 'warn', summary: t('admin.points.required'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      description: form.description || null,
      type: form.type,
      discount_value: form.discount_value,
      points_cost: form.points_cost,
      total_quantity: form.total_quantity,
      is_active: form.is_active,
      expires_at: form.expires_at ? form.expires_at.toISOString() : null,
      course_id: form.course_id,
    }
    if (editing.value) {
      await useApi(`/admin/vouchers/${editing.value.id}`, { method: 'PUT', body: payload })
      toast.add({ severity: 'success', summary: t('admin.points.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/vouchers', { method: 'POST', body: payload })
      toast.add({ severity: 'success', summary: t('admin.points.created'), life: 2200 })
    }
    modalOpen.value = false
    await Promise.all([loadVouchers(), loadStats()])
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.points.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(row: VoucherRow) {
  confirm.require({
    message: t('admin.points.deleteConfirm', { name: row.name }),
    header: t('admin.points.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/vouchers/${row.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.points.deleted'), life: 2200 })
        await loadVouchers()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.points.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

function typeLabel(type: string) {
  const key = `admin.points.types.${type}`
  const translated = t(key)
  return translated === key ? type : translated
}

onMounted(() => {
  loadStats()
  loadVouchers()
})
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <h1>{{ t('admin.points.title') }}</h1>
        <p>{{ t('admin.points.subtitle') }}</p>
      </div>
      <div class="tabs">
        <Button
          :label="t('admin.points.tabOverview')"
          :outlined="activeTab !== 'overview'"
          @click="activeTab = 'overview'"
        />
        <Button
          :label="t('admin.points.tabVouchers')"
          :outlined="activeTab !== 'vouchers'"
          @click="activeTab = 'vouchers'"
        />
      </div>
    </header>

    <template v-if="activeTab === 'overview'">
      <div class="kpis">
        <div class="kpi">
          <span>{{ t('admin.points.issued') }}</span>
          <strong>{{ fmtNum(stats?.totals?.total_issued) }}</strong>
        </div>
        <div class="kpi">
          <span>{{ t('admin.points.activeVouchers') }}</span>
          <strong>{{ fmtNum(stats?.totals?.active_vouchers) }}</strong>
        </div>
        <div class="kpi">
          <span>{{ t('admin.points.redemptions') }}</span>
          <strong>{{ fmtNum(stats?.totals?.redemptions) }}</strong>
        </div>
        <div class="kpi">
          <span>{{ t('admin.points.spent') }}</span>
          <strong>{{ fmtNum(stats?.totals?.total_redeemed) }}</strong>
        </div>
      </div>

      <div class="split">
        <section class="panel chart-panel">
          <h3>{{ t('admin.points.trendTitle') }}</h3>
          <div class="chart-box">
            <Chart v-if="stats" type="bar" :data="trendChart.data" :options="trendChart.options" />
            <ProgressSpinner v-else-if="statsLoading" />
          </div>
        </section>
        <section class="panel">
          <h3>{{ t('admin.points.topStudents') }}</h3>
          <div v-for="(u, i) in stats?.top_students || []" :key="u.id" class="list-row">
            <span>{{ i + 1 }}. {{ u.name }} <small>{{ u.student_code }}</small></span>
            <strong>{{ fmtNum(u.points_balance) }}</strong>
          </div>
          <CommonEmptyState v-if="!statsLoading && !(stats?.top_students || []).length" :description="t('common.noData')" />
        </section>
      </div>

      <section class="panel">
        <h3>{{ t('admin.points.recent') }}</h3>
        <DataTable :value="stats?.recent_redemptions || []" :loading="statsLoading" data-key="id" striped-rows>
          <Column :header="t('admin.points.student')">
            <template #body="{ data }">{{ data.user?.name || '—' }}</template>
          </Column>
          <Column :header="t('admin.points.voucher')">
            <template #body="{ data }">{{ data.voucher?.name || '—' }}</template>
          </Column>
          <Column :header="t('admin.points.cost')">
            <template #body="{ data }">{{ fmtNum(data.points_spent) }}</template>
          </Column>
          <Column :header="t('admin.points.time')">
            <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
          </Column>
          <template #empty><CommonEmptyState :description="t('common.noData')" /></template>
        </DataTable>
      </section>
    </template>

    <template v-else>
      <section class="panel">
        <div class="panel-head">
          <h3>{{ t('admin.points.voucherList') }}</h3>
          <Button :label="t('admin.points.add')" icon="pi pi-plus" size="small" @click="openCreate" />
        </div>
        <DataTable
          :value="vouchers"
          :loading="voucherLoading"
          data-key="id"
          striped-rows
          lazy
          paginator
          :rows="15"
          :total-records="voucherTotal"
          :first="(voucherPage - 1) * 15"
          @page="voucherPage = $event.page + 1; loadVouchers()"
        >
          <Column :header="t('admin.points.voucher')" style="min-width:200px">
            <template #body="{ data }">
              <strong>{{ data.name }}</strong>
              <small class="muted">{{ data.description || '—' }}</small>
            </template>
          </Column>
          <Column :header="t('admin.points.type')">
            <template #body="{ data }"><Tag :value="typeLabel(data.type)" /></template>
          </Column>
          <Column :header="t('admin.points.cost')">
            <template #body="{ data }">{{ fmtNum(data.points_cost) }}</template>
          </Column>
          <Column :header="t('admin.points.stock')">
            <template #body="{ data }">{{ data.redeemed_count || 0 }} / {{ data.total_quantity ?? '∞' }}</template>
          </Column>
          <Column :header="t('admin.points.status')">
            <template #body="{ data }">
              <Tag
                :value="data.is_active ? t('admin.points.active') : t('admin.points.inactive')"
                :severity="data.is_active ? 'success' : 'danger'"
              />
            </template>
          </Column>
          <Column :header="t('admin.points.expires')">
            <template #body="{ data }">{{ fmtDate(data.expires_at) }}</template>
          </Column>
          <Column :header="t('common.actions')" style="width:110px">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
            </template>
          </Column>
          <template #empty><CommonEmptyState :description="t('admin.points.empty')" /></template>
        </DataTable>
      </section>
    </template>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="editing ? t('admin.points.edit') : t('admin.points.add')"
      :style="{ width: 'min(520px, 96vw)' }"
    >
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('admin.points.name') }} *</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.points.description') }}</span>
          <Textarea v-model="form.description" rows="2" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.points.type') }}</span>
          <Select v-model="form.type" :options="typeOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.points.discountValue') }}</span>
          <InputNumber v-model="form.discount_value" :min="0" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.points.cost') }} *</span>
          <InputNumber v-model="form.points_cost" :min="1" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.points.quantity') }}</span>
          <InputNumber v-model="form.total_quantity" :min="1" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.points.expires') }}</span>
          <DatePicker v-model="form.expires_at" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>
        <label class="check">
          <Checkbox v-model="form.is_active" binary />
          <span>{{ t('admin.points.active') }}</span>
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.workspace-head { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: flex-start; }
.workspace-head h1 { margin: 0; font-size: 1.55rem; }
.workspace-head p { margin: .25rem 0 0; color: var(--p-text-muted-color); }
.tabs { display: flex; gap: .5rem; }
.kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
.kpi { border: 1px solid var(--p-content-border-color); border-radius: 12px; padding: .9rem 1rem; background: var(--p-content-background); }
.kpi span { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; color: var(--p-text-muted-color); }
.kpi strong { font-size: 1.35rem; }
.split { display: grid; grid-template-columns: 2fr 1fr; gap: .75rem; }
.panel { border: 1px solid var(--p-content-border-color); border-radius: 12px; background: var(--p-content-background); padding: .9rem 1rem; }
.panel h3, .panel-head h3 { margin: 0 0 .75rem; font-size: 1rem; }
.panel-head { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .5rem; }
.panel-head h3 { margin: 0; }
.chart-box { height: 220px; }
.list-row { display: flex; justify-content: space-between; gap: .75rem; padding: .45rem 0; border-bottom: 1px solid var(--p-content-border-color); }
.list-row small { color: var(--p-text-muted-color); }
.muted { display: block; color: var(--p-text-muted-color); font-size: .8rem; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
.field { display: flex; flex-direction: column; gap: .35rem; font-size: .85rem; font-weight: 600; }
.field.full { grid-column: 1 / -1; }
.check { display: flex; align-items: center; gap: .5rem; grid-column: 1 / -1; }
@media (max-width: 900px) {
  .kpis, .split, .form-grid { grid-template-columns: 1fr; }
}
</style>
