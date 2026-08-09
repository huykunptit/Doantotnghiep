<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface VoucherItem {
  id: number
  name: string
  description?: string | null
  points_cost: number
  discount_type?: string | null
  discount_value?: number | null
  course?: { id: number, title: string } | null
}

interface UserVoucher {
  id: number
  code: string
  status?: string
  points_spent?: number
  expires_at?: string | null
  voucher?: VoucherItem | null
}

const toast = useToast()
const { t, locale } = useI18n()
const tab = ref<'shop' | 'mine'>('shop')
const loading = ref(true)
const redeeming = ref<number | null>(null)
const balance = ref(0)
const shop = ref<VoucherItem[]>([])
const mine = ref<UserVoucher[]>([])

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

async function load() {
  loading.value = true
  try {
    const [summary, vouchers, owned] = await Promise.all([
      useApi<{ balance?: number }>('/points/summary'),
      useApi<VoucherItem[]>('/vouchers'),
      useApi<UserVoucher[]>('/me/vouchers'),
    ])
    balance.value = Number(summary.balance || 0)
    shop.value = Array.isArray(vouchers) ? vouchers : []
    mine.value = Array.isArray(owned) ? owned : []
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.points.shopError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function redeem(item: VoucherItem) {
  redeeming.value = item.id
  try {
    const res = await useApi<{ message?: string, balance?: number }>(`/vouchers/${item.id}/redeem`, { method: 'POST' })
    toast.add({ severity: 'success', summary: res.message || t('student.points.redeemOk'), life: 2800 })
    if (res.balance != null) balance.value = Number(res.balance)
    tab.value = 'mine'
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.points.redeemError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    redeeming.value = null
  }
}

function formatExpiry(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(dateLocale.value)
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <p class="balance-note">{{ t('student.points.shopSubtitle', { n: balance }) }}</p>
      <Button :label="t('student.points.backPoints')" icon="pi pi-arrow-left" text @click="navigateTo('/student/points')" />
    </header>

    <div class="tabs">
      <button type="button" :class="{ on: tab === 'shop' }" @click="tab = 'shop'">{{ t('student.points.tabShop') }}</button>
      <button type="button" :class="{ on: tab === 'mine' }" @click="tab = 'mine'">{{ t('student.points.tabMine') }}</button>
    </div>

    <div v-if="loading" class="empty">…</div>
    <template v-else-if="tab === 'shop'">
      <CommonEmptyState v-if="!shop.length" :description="t('student.points.shopEmpty')" />
      <div v-else class="grid">
        <article v-for="item in shop" :key="item.id" class="card">
          <strong>{{ item.name }}</strong>
          <span>{{ item.description || item.course?.title || t('student.points.voucherGeneric') }}</span>
          <div class="row">
            <Tag :value="`${item.points_cost} ${t('student.points.pts')}`" severity="info" />
            <Button
              :label="t('student.points.redeem')"
              size="small"
              :loading="redeeming === item.id"
              :disabled="balance < item.points_cost"
              @click="redeem(item)"
            />
          </div>
        </article>
      </div>
    </template>
    <template v-else>
      <CommonEmptyState v-if="!mine.length" :description="t('student.points.mineEmpty')" />
      <div v-else class="grid">
        <article v-for="item in mine" :key="item.id" class="card">
          <strong>{{ item.voucher?.name || t('student.points.voucherGeneric') }}</strong>
          <code>{{ item.code }}</code>
          <span>{{ t('student.points.status') }}: {{ item.status || '—' }}</span>
          <span>{{ t('student.points.expires') }}: {{ formatExpiry(item.expires_at) }}</span>
        </article>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.workspace-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.balance-note { margin: 0; color: var(--text-muted); font-weight: 600; }
.tabs { display: flex; gap: 8px; }
.tabs button {
  border: 1px solid var(--border); background: transparent; border-radius: 999px;
  padding: 8px 14px; font-weight: 650; cursor: pointer; color: var(--text);
}
.tabs button.on { background: var(--brand); color: #fff; border-color: var(--brand); }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
.card {
  display: grid; gap: 8px; padding: 14px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.card span { color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.card code { font-size: .85rem; padding: 4px 8px; border-radius: 8px; background: var(--surface-subtle); width: fit-content; }
.row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.empty { color: var(--text-muted); }
</style>
