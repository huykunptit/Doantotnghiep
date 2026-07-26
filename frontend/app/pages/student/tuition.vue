<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface TuitionItem {
  id: number
  term?: { id: number, name: string, code: string } | null
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
const totalDue = ref(0)
const totalPaid = ref(0)

function money(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0)
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{
      items: TuitionItem[]
      total_due: number
      total_paid: number
      payment_history: PaymentHistoryItem[]
    }>('/me/tuition')
    items.value = res.items || []
    paymentHistory.value = res.payment_history || []
    totalDue.value = Number(res.total_due || 0)
    totalPaid.value = Number(res.total_paid || 0)
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

function paymentType(item: PaymentHistoryItem) {
  return t(`student.tuition.paymentTypes.${item.type}`)
}

function paymentIcon(item: PaymentHistoryItem) {
  if (item.type === 'tuition') return 'pi pi-building-columns'
  if (item.type === 'career_path') return 'pi pi-map'
  return 'pi pi-book'
}

async function pay(row: TuitionItem) {
  paying.value = row.id
  try {
    await useApi(`/me/tuition/${row.id}/pay`, { method: 'POST' })
    toast.add({ severity: 'success', summary: t('student.tuition.paySuccess'), life: 3000 })
    await load()
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

    <section class="stats">
      <div class="stat">
        <span>{{ t('student.tuition.totalDue') }}</span>
        <strong class="due">{{ money(totalDue) }}</strong>
      </div>
      <div class="stat">
        <span>{{ t('student.tuition.totalPaid') }}</span>
        <strong class="paid">{{ money(totalPaid) }}</strong>
      </div>
    </section>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="!items.length" class="empty">{{ t('student.tuition.empty') }}</div>
    <div v-else class="list">
      <div v-for="row in items" :key="row.id" class="row">
        <div class="info">
          <strong>{{ row.term?.name || t('student.tuition.term') }}</strong>
          <small v-if="row.note" class="muted">{{ row.note }}</small>
        </div>
        <span class="amount">{{ money(row.amount) }}</span>
        <Tag
          :severity="row.status === 'paid' ? 'success' : 'warn'"
          :value="row.status === 'paid' ? t('student.tuition.paid') : t('student.tuition.unpaid')"
        />
        <Button
          v-if="row.status !== 'paid'"
          :label="t('student.tuition.pay')"
          icon="pi pi-credit-card"
          size="small"
          :loading="paying === row.id"
          @click="confirmPay(row)"
        />
        <span v-else class="muted paid-at">{{ row.paid_at ? new Date(row.paid_at).toLocaleDateString('vi-VN') : '' }}</span>
      </div>
    </div>

    <section class="history">
      <header class="section-head">
        <div>
          <h2>{{ t('student.tuition.historyTitle') }}</h2>
          <p>{{ t('student.tuition.historySubtitle') }}</p>
        </div>
      </header>

      <div v-if="loading" class="empty">…</div>
      <div v-else-if="!paymentHistory.length" class="empty">{{ t('student.tuition.historyEmpty') }}</div>
      <div v-else class="history-list">
        <article v-for="payment in paymentHistory" :key="payment.id" class="history-row">
          <span class="payment-icon"><i :class="paymentIcon(payment)" /></span>
          <div class="info">
            <strong>{{ payment.title }}</strong>
            <small>{{ payment.description || paymentType(payment) }}</small>
            <small v-if="payment.payment_ref" class="muted">{{ payment.payment_ref }}</small>
          </div>
          <Tag severity="secondary" :value="paymentType(payment)" />
          <div class="payment-total">
            <strong>{{ money(payment.amount) }}</strong>
            <small>{{ payment.paid_at ? new Date(payment.paid_at).toLocaleDateString('vi-VN') : '—' }}</small>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
.stat { padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.stat span { display: block; color: var(--text-muted); font-size: .8rem; font-weight: 600; }
.stat strong { font-size: 1.4rem; }
.due { color: var(--red-500, #dc2626); }
.paid { color: var(--green-600, #16a34a); }
.list { display: grid; gap: 8px; }
.row {
  display: grid; grid-template-columns: 1fr auto auto auto; gap: 14px; align-items: center;
  padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.info strong { display: block; }
.amount { font-weight: 800; }
.muted { color: var(--text-muted); font-weight: 500; }
.paid-at { font-size: .82rem; }
.history { margin-top: 6px; }
.section-head { margin-bottom: 10px; }
.section-head h2 { margin: 0 0 4px; font-size: 1.15rem; }
.section-head p { margin: 0; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.history-list { display: grid; gap: 8px; }
.history-row {
  display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; gap: 12px; align-items: center;
  padding: 13px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.payment-icon {
  display: grid; place-items: center; width: 40px; height: 40px; border-radius: 10px;
  background: var(--brand-soft); color: var(--brand); font-size: 1rem;
}
.info small { display: block; margin-top: 2px; color: var(--text-muted); font-size: .8rem; font-weight: 500; }
.payment-total { display: grid; justify-items: end; gap: 2px; min-width: 120px; }
.payment-total strong { color: var(--brand); }
.payment-total small { color: var(--text-muted); font-size: .78rem; font-weight: 500; }
@media (max-width: 700px) {
  .row { grid-template-columns: 1fr auto; }
  .history-row { grid-template-columns: auto 1fr; }
  .history-row > .p-tag, .payment-total { grid-column: 2; justify-self: start; justify-items: start; }
}
</style>
