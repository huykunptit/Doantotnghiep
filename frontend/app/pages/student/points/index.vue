<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface TxItem {
  id: number
  type?: string
  amount?: number
  description?: string | null
  created_at?: string
}

interface QuestItem {
  key: string
  title: string
  description?: string
  points?: number
  category?: string
  done_today?: boolean
  progress?: number
  target?: number
  recurring?: boolean
}

const toast = useToast()
const { t, locale } = useI18n()

const loading = ref(true)
const claiming = ref(false)
const balance = ref(0)
const streak = ref(0)
const transactions = ref<TxItem[]>([])
const quests = ref<QuestItem[]>([])
const myRank = ref<number | null>(null)

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function formatDate(value?: string) {
  if (!value) return '—'
  return new Date(value).toLocaleString(dateLocale.value, {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

async function load() {
  loading.value = true
  try {
    const [summary, questRes, board] = await Promise.all([
      useApi<{ balance?: number, streak_days?: number, recent_transactions?: TxItem[] }>('/points/summary'),
      useApi<{ quests?: QuestItem[], balance?: number, streak_days?: number }>('/points/quests'),
      useApi<{ my_rank?: number }>('/points/leaderboard').catch(() => ({ my_rank: null })),
    ])
    balance.value = Number(summary.balance ?? questRes.balance ?? 0)
    streak.value = Number(summary.streak_days ?? questRes.streak_days ?? 0)
    transactions.value = summary.recent_transactions || []
    quests.value = questRes.quests || []
    myRank.value = board.my_rank ?? null
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.points.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function claimDaily() {
  claiming.value = true
  try {
    const res = await useApi<{ message?: string, rewarded?: boolean, balance?: number, streak?: number }>('/points/daily-login', {
      method: 'POST',
    })
    toast.add({
      severity: res.rewarded ? 'success' : 'info',
      summary: res.message || t('student.points.claimed'),
      life: 2800,
    })
    if (res.balance != null) balance.value = Number(res.balance)
    if (res.streak != null) streak.value = Number(res.streak)
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.points.claimError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    claiming.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.points.title') }}</h1>
        <p>{{ t('student.points.subtitle') }}</p>
      </div>
      <div class="head-actions">
        <Button :label="t('student.points.openShop')" icon="pi pi-gift" @click="navigateTo('/student/points/shop')" />
      </div>
    </header>

    <div v-if="loading" class="empty">…</div>
    <template v-else>
      <section class="kpi-row">
        <article class="kpi">
          <span>{{ t('student.points.balance') }}</span>
          <strong>{{ balance }}</strong>
        </article>
        <article class="kpi">
          <span>{{ t('student.points.streak') }}</span>
          <strong>{{ streak }}</strong>
        </article>
        <article class="kpi">
          <span>{{ t('student.points.rank') }}</span>
          <strong>{{ myRank ?? '—' }}</strong>
        </article>
      </section>

      <section class="panel">
        <header class="panel-head">
          <strong>{{ t('student.points.daily') }}</strong>
          <Button :label="t('student.points.claim')" icon="pi pi-calendar" :loading="claiming" size="small" @click="claimDaily" />
        </header>
        <p class="hint">{{ t('student.points.dailyHint') }}</p>
      </section>

      <section class="panel">
        <header class="panel-head"><strong>{{ t('student.points.quests') }}</strong></header>
        <div class="quest-list">
          <article v-for="q in quests" :key="q.key" class="quest">
            <div>
              <strong>{{ q.title }}</strong>
              <span>{{ q.description }}</span>
              <span v-if="q.target" class="meta">{{ q.progress || 0 }}/{{ q.target }}</span>
            </div>
            <div class="quest-side">
              <Tag :value="`+${q.points || 0}`" severity="success" />
              <Tag v-if="q.done_today" :value="t('student.points.doneToday')" />
            </div>
          </article>
        </div>
      </section>

      <section class="panel">
        <header class="panel-head"><strong>{{ t('student.points.history') }}</strong></header>
        <div v-if="!transactions.length" class="empty">{{ t('student.points.noTx') }}</div>
        <ul v-else class="tx-list">
          <li v-for="tx in transactions" :key="tx.id">
            <div>
              <strong>{{ tx.description || tx.type || '—' }}</strong>
              <span>{{ formatDate(tx.created_at) }}</span>
            </div>
            <em :class="{ earn: (tx.amount || 0) > 0 }">{{ (tx.amount || 0) > 0 ? '+' : '' }}{{ tx.amount }}</em>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: flex-start; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.head-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.kpi-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.kpi {
  padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.kpi span { display: block; color: var(--text-muted); font-size: .8rem; font-weight: 600; }
.kpi strong { font-size: 1.45rem; }
.panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.panel-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
.hint { margin: 0; color: var(--text-muted); font-weight: 500; }
.quest-list, .tx-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.quest, .tx-list li {
  display: flex; justify-content: space-between; gap: 12px; align-items: center;
  padding: 10px 12px; border: 1px solid var(--border); border-radius: 12px;
}
.quest strong, .tx-list strong { display: block; margin-bottom: 2px; }
.quest span, .tx-list span { display: block; color: var(--text-muted); font-size: .85rem; font-weight: 500; }
.quest-side { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
.tx-list em { font-style: normal; font-weight: 750; color: #b91c1c; }
.tx-list em.earn { color: #15803d; }
.empty { color: var(--text-muted); }
@media (max-width: 700px) {
  .kpi-row { grid-template-columns: 1fr; }
}
</style>
