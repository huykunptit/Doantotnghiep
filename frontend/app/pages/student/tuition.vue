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

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const loading = ref(true)
const paying = ref<number | null>(null)
const items = ref<TuitionItem[]>([])
const totalDue = ref(0)
const totalPaid = ref(0)

function money(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0)
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ items: TuitionItem[], total_due: number, total_paid: number }>('/me/tuition')
    items.value = res.items || []
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
@media (max-width: 700px) { .row { grid-template-columns: 1fr auto; } }
</style>
