<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface TuitionItem {
  id: number
  term?: {
    id: number
    name: string
    code: string
    label?: string
    academic_year?: string | null
  } | null
  amount: number
  status: string
  paid_at?: string | null
  note?: string | null
}

interface PaymentHistoryItem {
  id: string
  type: 'tuition' | 'extension_course' | 'career_path'
  title: string
  description?: string | null
  amount: number
  status: string
  payment_method?: string | null
  payment_ref?: string | null
  paid_at?: string | null
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const loading = ref(true)
const paying = ref<number | null>(null)
const items = ref<TuitionItem[]>([])
const paymentHistory = ref<PaymentHistoryItem[]>([])
const tab = ref<'unpaid' | 'paid'>('unpaid')

const unpaidItems = computed(() => items.value.filter(r => r.status !== 'paid'))
const unpaidTotal = computed(() => unpaidItems.value.reduce((sum, r) => sum + Number(r.amount || 0), 0))
const paidTotal = computed(() => paymentHistory.value.reduce((sum, r) => sum + Number(r.amount || 0), 0))

const tabTotalLabel = computed(() =>
  tab.value === 'unpaid' ? t('student.tuition.unpaidTotal') : t('student.tuition.paidTotal'),
)
const tabTotalValue = computed(() => (tab.value === 'unpaid' ? unpaidTotal.value : paidTotal.value))
const emptyTabMessage = computed(() =>
  tab.value === 'unpaid' ? t('student.tuition.emptyUnpaid') : t('student.tuition.emptyPaid'),
)

function money(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0)
}

function termTitle(row: TuitionItem) {
  return row.term?.label || row.term?.name || row.note || t('student.tuition.term')
}

function paymentType(item: PaymentHistoryItem) {
  return t(`student.tuition.paymentTypes.${item.type}`)
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{
      items: TuitionItem[]
      payment_history: PaymentHistoryItem[]
    }>('/me/tuition')
    items.value = res.items || []
    paymentHistory.value = res.payment_history || []
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.tuition.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function confirmPay(row: TuitionItem) {
  confirm.require({
    message: t('student.tuition.confirmPay', { amount: money(row.amount) }),
    header: t('student.tuition.payTitle'),
    icon: 'pi pi-wallet',
    accept: () => pay(row),
  })
}

async function pay(row: TuitionItem) {
  paying.value = row.id
  try {
    await useApi(`/me/tuition/${row.id}/pay`, { method: 'POST' })
    toast.add({ severity: 'success', summary: t('student.tuition.paySuccess'), life: 3000 })
    await load()
    if (!unpaidItems.value.length) tab.value = 'paid'
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.tuition.payError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    paying.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <ConfirmDialog />
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.tuition.title') }}</h1>
        <p>{{ t('student.tuition.subtitle') }}</p>
      </div>
    </header>

    <div class="tabs" role="tablist">
      <button
        type="button"
        class="tab"
        :class="{ on: tab === 'unpaid' }"
        role="tab"
        :aria-selected="tab === 'unpaid'"
        @click="tab = 'unpaid'"
      >
        {{ t('student.tuition.tabUnpaid') }}
      </button>
      <button
        type="button"
        class="tab"
        :class="{ on: tab === 'paid' }"
        role="tab"
        :aria-selected="tab === 'paid'"
        @click="tab = 'paid'"
      >
        {{ t('student.tuition.tabPaid') }}
      </button>
    </div>

    <p v-if="!loading" class="tab-sum">
      {{ tabTotalLabel }}:
      <strong :class="tab === 'unpaid' ? 'due' : 'paid'">{{ money(tabTotalValue) }}</strong>
    </p>

    <div v-if="loading" class="empty">…</div>

    <!-- Chưa nộp: học phí kỳ còn nợ -->
    <template v-else-if="tab === 'unpaid'">
      <div v-if="!unpaidItems.length" class="empty">{{ emptyTabMessage }}</div>
      <div v-else class="list">
        <article v-for="row in unpaidItems" :key="row.id" class="debt-card">
          <header class="debt-head">
            <strong>{{ termTitle(row) }}</strong>
            <Tag severity="warn" :value="t('student.tuition.unpaid')" />
          </header>
          <div class="debt-sep" />
          <div class="debt-rows">
            <div class="debt-row">
              <span>{{ t('student.tuition.feeToPay') }}</span>
              <strong class="amt due">{{ money(row.amount) }}</strong>
            </div>
            <div class="debt-row">
              <span>{{ t('student.tuition.feePaid') }}</span>
              <strong class="amt muted-amt">{{ money(0) }}</strong>
            </div>
          </div>
          <footer class="debt-foot">
            <Button
              :label="t('student.tuition.pay')"
              icon="pi pi-credit-card"
              size="small"
              :loading="paying === row.id"
              @click="confirmPay(row)"
            />
          </footer>
        </article>
      </div>
    </template>

    <!-- Đã nộp = lịch sử thanh toán (học phí + khóa ngoài CTĐT) -->
    <template v-else>
      <div v-if="!paymentHistory.length" class="empty">{{ emptyTabMessage }}</div>
      <div v-else class="list">
        <article v-for="payment in paymentHistory" :key="payment.id" class="debt-card">
          <header class="debt-head">
            <strong>{{ payment.title }}</strong>
            <Tag severity="secondary" :value="paymentType(payment)" />
          </header>
          <div class="debt-sep" />
          <div class="debt-rows">
            <div class="debt-row">
              <span>{{ t('student.tuition.feeToPay') }}</span>
              <strong class="amt">{{ money(payment.amount) }}</strong>
            </div>
            <div class="debt-row">
              <span>{{ t('student.tuition.feePaid') }}</span>
              <strong class="amt muted-amt">{{ money(payment.amount) }}</strong>
            </div>
            <div v-if="payment.paid_at" class="debt-row">
              <span>{{ t('student.tuition.paidOn') }}</span>
              <strong class="amt muted-amt">{{ new Date(payment.paid_at).toLocaleDateString('vi-VN') }}</strong>
            </div>
            <div v-if="payment.description" class="debt-row note">
              <span>{{ payment.description }}</span>
            </div>
          </div>
        </article>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.due { color: var(--red-500, #dc2626); }
.paid { color: var(--green-600, #16a34a); }
.tabs {
  display: inline-flex;
  align-self: flex-start;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.tab {
  border: 0;
  border-radius: 9px;
  padding: 8px 14px;
  background: transparent;
  color: var(--text-muted);
  font-size: .9rem;
  font-weight: 700;
  cursor: pointer;
}
.tab.on {
  background: var(--brand);
  color: #fff;
}
.tab-sum {
  margin: 0;
  color: var(--text-muted);
  font-size: .92rem;
  font-weight: 600;
}
.tab-sum strong { font-weight: 800; }
.empty { padding: 28px; text-align: center; color: var(--text-muted); }
.list { display: grid; gap: 10px; }
.debt-card {
  padding: 14px 16px 12px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 96%, transparent);
}
.debt-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.debt-head strong {
  font-size: .98rem;
  font-weight: 800;
  line-height: 1.35;
}
.debt-sep {
  margin: 12px 0 10px;
  border-top: 1px dashed color-mix(in srgb, var(--border) 85%, #999);
}
.debt-rows { display: grid; gap: 8px; }
.debt-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 16px;
  font-size: .9rem;
}
.debt-row > span { color: var(--text-muted); font-weight: 600; }
.debt-row.note { justify-content: flex-start; }
.debt-row.note > span { font-weight: 500; font-size: .82rem; }
.debt-row .amt { font-weight: 800; font-variant-numeric: tabular-nums; }
.debt-row .due { color: var(--red-500, #dc2626); }
.debt-row .muted-amt { color: color-mix(in srgb, var(--text-muted) 70%, #111); font-weight: 700; }
.debt-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
  padding-top: 4px;
}
</style>
