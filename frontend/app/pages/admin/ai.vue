<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface AiSettings {
  id: number
  provider: string
  model: string
  monthly_token_quota: number
  tokens_used: number
  max_requests_per_minute: number
  is_active: boolean
  quota_reset_at?: string | null
  has_api_key: boolean
  usage_percent: number
}

interface AiStats {
  total_requests: number
  success_requests: number
  error_requests: number
  unique_users: number
  total_tokens: number
  avg_response_time: number
  total_cvs?: number
  total_recommendations?: number
}

interface EndpointStat { endpoint: string, count: number, tokens: number }
interface ProviderStat { provider: string, count: number, tokens: number }
interface DailyPoint { date: string, count: number, tokens: number }
interface RecentLog {
  id: number
  user?: { name?: string, email?: string } | null
  endpoint: string
  provider: string
  model: string
  tokens_used: number
  response_time_ms: number
  status: 'success' | 'error' | string
  error_message?: string | null
  created_at: string
}
interface ProviderModel { id: string, name: string, tier: string }
interface Provider { id: string, name: string, color?: string, models: ProviderModel[] }

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(true)
const saving = ref(false)
const resetting = ref(false)

const settings = ref<AiSettings | null>(null)
const stats = ref<AiStats | null>(null)
const byEndpoint = ref<EndpointStat[]>([])
const byProvider = ref<ProviderStat[]>([])
const dailyPoints = ref<DailyPoint[]>([])
const recentLogs = ref<RecentLog[]>([])
const providers = ref<Provider[]>([])

const form = reactive({
  provider: 'chatgpt',
  model: 'gpt-4o-mini',
  api_key: '',
  monthly_token_quota: 1_000_000,
  max_requests_per_minute: 60,
  is_active: true,
})

const providerOptions = computed(() =>
  providers.value.map(p => ({ label: p.name, value: p.id })),
)

const modelOptions = computed(() => {
  const current = providers.value.find(p => p.id === form.provider)
  return (current?.models || []).map(m => ({
    label: m.name,
    value: m.id,
    tier: m.tier,
  }))
})

const usagePercent = computed(() => settings.value?.usage_percent ?? 0)
const tokensRemaining = computed(() => {
  if (!settings.value) return 0
  return Math.max(0, settings.value.monthly_token_quota - settings.value.tokens_used)
})
const chartMax = computed(() => Math.max(...dailyPoints.value.map(d => d.tokens), 1))

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function formatK(n: number) {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`
  return String(n)
}

function formatMs(ms: number) {
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`
}

function formatDate(str?: string | null) {
  if (!str) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(str))
}

function formatDayLabel(str: string) {
  const d = new Date(str)
  return `${d.getDate()}/${d.getMonth() + 1}`
}

function quotaTone() {
  if (usagePercent.value >= 90) return 'tone-danger'
  if (usagePercent.value >= 70) return 'tone-warn'
  return 'tone-ok'
}

function applyForm(s: AiSettings) {
  form.provider = s.provider
  form.model = s.model
  form.monthly_token_quota = s.monthly_token_quota
  form.max_requests_per_minute = s.max_requests_per_minute
  form.is_active = s.is_active
  form.api_key = ''
}

async function load() {
  loading.value = true
  try {
    const [dash, prov] = await Promise.all([
      useApi<{
        settings: AiSettings
        stats: AiStats
        by_endpoint?: EndpointStat[]
        by_provider?: ProviderStat[]
        daily_requests?: DailyPoint[]
        recent_logs?: RecentLog[]
      }>('/admin/ai/dashboard'),
      useApi<{ providers: Provider[] }>('/admin/ai/providers'),
    ])
    settings.value = dash.settings
    stats.value = dash.stats
    byEndpoint.value = dash.by_endpoint ?? []
    byProvider.value = dash.by_provider ?? []
    dailyPoints.value = dash.daily_requests ?? []
    recentLogs.value = dash.recent_logs ?? []
    providers.value = prov.providers ?? []
    if (dash.settings) applyForm(dash.settings)
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.ai.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

watch(() => form.provider, (next) => {
  const models = providers.value.find(p => p.id === next)?.models || []
  if (models.length && !models.some(m => m.id === form.model)) {
    form.model = models[0]!.id
  }
})

async function save() {
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      provider: form.provider,
      model: form.model,
      monthly_token_quota: form.monthly_token_quota,
      max_requests_per_minute: form.max_requests_per_minute,
      is_active: form.is_active,
    }
    if (form.api_key.trim()) body.api_key = form.api_key.trim()

    const res = await useApi<{ settings: AiSettings }>('/admin/ai/settings', {
      method: 'PUT',
      body,
    })
    settings.value = res.settings
    applyForm(res.settings)
    toast.add({ severity: 'success', summary: t('admin.ai.saved'), life: 2500 })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.ai.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function askResetQuota() {
  confirm.require({
    message: t('admin.ai.resetConfirm'),
    header: t('admin.ai.resetQuota'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      resetting.value = true
      try {
        const res = await useApi<{ settings: AiSettings }>('/admin/ai/reset-quota', { method: 'POST' })
        settings.value = res.settings
        toast.add({ severity: 'success', summary: t('admin.ai.resetSuccess'), life: 2500 })
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.ai.resetError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
      finally {
        resetting.value = false
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page ai-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.system') }}</span>
        <h1>{{ t('admin.ai.title') }}</h1>
        <p>{{ t('admin.ai.subtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button
          :label="t('common.refresh')"
          icon="pi pi-refresh"
          severity="secondary"
          outlined
          :loading="loading"
          @click="load"
        />
        <Button
          :label="t('admin.ai.resetQuota')"
          icon="pi pi-replay"
          severity="danger"
          outlined
          :loading="resetting"
          :disabled="loading"
          @click="askResetQuota"
        />
        <Button
          :label="t('common.save')"
          icon="pi pi-check"
          :loading="saving"
          :disabled="loading"
          @click="save"
        />
      </div>
    </header>

    <div v-if="loading" class="loading-box">
      <ProgressSpinner style="width:36px;height:36px" stroke-width="4" />
      <span>{{ t('common.loading') }}</span>
    </div>

    <template v-else>
      <section class="metric-rail">
        <div class="metric">
          <strong>{{ formatK(stats?.total_requests || 0) }}</strong>
          <span>{{ t('admin.ai.totalRequests') }}</span>
        </div>
        <div class="metric">
          <strong>{{ formatK(stats?.success_requests || 0) }}</strong>
          <span>{{ t('admin.ai.successRequests') }}</span>
        </div>
        <div class="metric">
          <strong>{{ formatK(stats?.error_requests || 0) }}</strong>
          <span>{{ t('admin.ai.errorRequests') }}</span>
        </div>
        <div class="metric">
          <strong>{{ formatK(stats?.unique_users || 0) }}</strong>
          <span>{{ t('admin.ai.uniqueUsers') }}</span>
        </div>
        <div class="metric">
          <strong>{{ formatK(stats?.total_tokens || 0) }}</strong>
          <span>{{ t('admin.ai.totalTokens') }}</span>
        </div>
        <div class="metric">
          <strong>{{ formatMs(stats?.avg_response_time || 0) }}</strong>
          <span>{{ t('admin.ai.avgResponse') }}</span>
        </div>
      </section>

      <div class="grid-2">
        <section class="panel">
          <div class="panel-head">
            <h2>{{ t('admin.ai.settingsTitle') }}</h2>
            <span class="pill" :class="settings?.is_active ? 'tone-ok' : 'tone-muted'">
              {{ settings?.is_active ? t('admin.ai.active') : t('admin.ai.inactive') }}
            </span>
          </div>

          <div class="form-grid">
            <label class="field">
              <span>{{ t('admin.ai.provider') }}</span>
              <Select
                v-model="form.provider"
                :options="providerOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
            </label>
            <label class="field">
              <span>{{ t('admin.ai.model') }}</span>
              <Select
                v-model="form.model"
                :options="modelOptions"
                option-label="label"
                option-value="value"
                class="w-full"
              />
            </label>
            <label class="field full">
              <span>{{ t('admin.ai.apiKey') }}</span>
              <Password
                v-model="form.api_key"
                :feedback="false"
                toggle-mask
                :placeholder="settings?.has_api_key ? t('admin.ai.apiKeyConfigured') : t('admin.ai.apiKeyPh')"
                class="w-full"
                input-class="w-full"
              />
              <small>{{ t('admin.ai.apiKeyHint') }}</small>
            </label>
            <label class="field">
              <span>{{ t('admin.ai.monthlyQuota') }}</span>
              <InputNumber v-model="form.monthly_token_quota" :min="1000" :step="1000" class="w-full" />
            </label>
            <label class="field">
              <span>{{ t('admin.ai.rateLimit') }}</span>
              <InputNumber v-model="form.max_requests_per_minute" :min="1" :max="1000" class="w-full" />
            </label>
            <label class="field switch-field">
              <span>{{ t('admin.ai.enableAi') }}</span>
              <ToggleSwitch v-model="form.is_active" />
            </label>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2>{{ t('admin.ai.quotaTitle') }}</h2>
            <span class="pill" :class="quotaTone()">{{ usagePercent }}%</span>
          </div>
          <div class="quota-block">
            <ProgressBar :value="Math.min(100, usagePercent)" :show-value="false" />
            <div class="quota-meta">
              <div>
                <span>{{ t('admin.ai.used') }}</span>
                <strong>{{ formatK(settings?.tokens_used || 0) }}</strong>
              </div>
              <div>
                <span>{{ t('admin.ai.remaining') }}</span>
                <strong>{{ formatK(tokensRemaining) }}</strong>
              </div>
              <div>
                <span>{{ t('admin.ai.quota') }}</span>
                <strong>{{ formatK(settings?.monthly_token_quota || 0) }}</strong>
              </div>
            </div>
            <small v-if="settings?.quota_reset_at">
              {{ t('admin.ai.lastReset') }}: {{ formatDate(settings.quota_reset_at) }}
            </small>
          </div>

          <div class="chart-block">
            <h3>{{ t('admin.ai.dailyUsage') }}</h3>
            <div v-if="dailyPoints.length" class="bars">
              <div v-for="point in dailyPoints" :key="point.date" class="bar-col">
                <div
                  class="bar"
                  :style="{ height: `${Math.max(8, (point.tokens / chartMax) * 100)}%` }"
                  :title="`${formatK(point.tokens)} tokens`"
                />
                <span>{{ formatDayLabel(point.date) }}</span>
              </div>
            </div>
            <p v-else class="empty-inline">{{ t('common.noData') }}</p>
          </div>
        </section>
      </div>

      <div class="grid-2">
        <section class="panel">
          <h2>{{ t('admin.ai.byEndpoint') }}</h2>
          <DataTable :value="byEndpoint" data-key="endpoint" size="small" striped-rows>
            <Column field="endpoint" :header="t('admin.ai.endpoint')" />
            <Column field="count" :header="t('admin.ai.requests')" />
            <Column field="tokens" :header="t('admin.ai.tokens')">
              <template #body="{ data }">{{ formatK(data.tokens || 0) }}</template>
            </Column>
            <template #empty><div class="empty">{{ t('common.noData') }}</div></template>
          </DataTable>
        </section>
        <section class="panel">
          <h2>{{ t('admin.ai.byProvider') }}</h2>
          <DataTable :value="byProvider" data-key="provider" size="small" striped-rows>
            <Column field="provider" :header="t('admin.ai.provider')" />
            <Column field="count" :header="t('admin.ai.requests')" />
            <Column field="tokens" :header="t('admin.ai.tokens')">
              <template #body="{ data }">{{ formatK(data.tokens || 0) }}</template>
            </Column>
            <template #empty><div class="empty">{{ t('common.noData') }}</div></template>
          </DataTable>
        </section>
      </div>

      <section class="panel">
        <h2>{{ t('admin.ai.recentLogs') }}</h2>
        <DataTable
          :value="recentLogs"
          data-key="id"
          paginator
          :rows="10"
          :rows-per-page-options="[10, 20]"
          striped-rows
          size="small"
        >
          <Column :header="t('admin.ai.user')" style="min-width:160px">
            <template #body="{ data }">
              <div class="user-cell">
                <strong>{{ data.user?.name || '—' }}</strong>
                <small>{{ data.user?.email || '' }}</small>
              </div>
            </template>
          </Column>
          <Column field="endpoint" :header="t('admin.ai.endpoint')" />
          <Column field="provider" :header="t('admin.ai.provider')" />
          <Column field="model" :header="t('admin.ai.model')" />
          <Column field="tokens_used" :header="t('admin.ai.tokens')">
            <template #body="{ data }">{{ formatK(data.tokens_used || 0) }}</template>
          </Column>
          <Column field="response_time_ms" :header="t('admin.ai.responseTime')">
            <template #body="{ data }">{{ formatMs(data.response_time_ms || 0) }}</template>
          </Column>
          <Column field="status" :header="t('admin.ai.status')">
            <template #body="{ data }">
              <span class="pill" :class="data.status === 'success' ? 'tone-ok' : 'tone-danger'">
                {{ data.status }}
              </span>
            </template>
          </Column>
          <Column field="created_at" :header="t('admin.ai.time')">
            <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
          </Column>
          <template #empty><div class="empty">{{ t('common.noData') }}</div></template>
        </DataTable>
      </section>
    </template>
  </div>
</template>

<style scoped>
.ai-page { gap: 14px; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }
.page-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.loading-box {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  min-height: 240px; color: var(--text-muted);
}

.metric-rail {
  display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px;
}
.metric {
  display: flex; flex-direction: column; gap: 2px;
  min-height: 72px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.metric strong { font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; }
.metric span { color: var(--text-muted); font-size: .74rem; font-weight: 600; }

.grid-2 { display: grid; grid-template-columns: 1.1fr .9fr; gap: 12px; }
.panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 16px;
}
.panel h2 { margin: 0 0 12px; font-size: 1.05rem; }
.panel-head {
  display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px;
}
.panel-head h2 { margin: 0; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field small { color: var(--text-muted); font-size: .74rem; font-weight: 500; }
.field.full { grid-column: 1 / -1; }
.switch-field { flex-direction: row; align-items: center; justify-content: space-between; }
.w-full { width: 100%; }

.quota-block { display: grid; gap: 12px; margin-bottom: 18px; }
.quota-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.quota-meta span { display: block; color: var(--text-muted); font-size: .7rem; font-weight: 700; }
.quota-meta strong { font-size: .95rem; }
.chart-block h3 { margin: 0 0 10px; font-size: .9rem; }
.bars {
  display: flex; align-items: flex-end; gap: 6px; height: 120px;
}
.bar-col {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
  height: 100%; gap: 4px;
}
.bar {
  width: 100%; max-width: 18px; border-radius: 6px 6px 2px 2px;
  background: color-mix(in srgb, var(--brand) 75%, #94a3b8);
}
.bar-col span { color: var(--text-muted); font-size: .62rem; font-weight: 600; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.tone-ok { background: #dcfce7; color: #15803d; }
.tone-warn { background: #fef9c3; color: #a16207; }
.tone-danger { background: #fee2e2; color: #b91c1c; }
.tone-muted { background: var(--surface-hover); color: var(--text-muted); }

.user-cell strong { display: block; font-size: .86rem; }
.user-cell small { color: var(--text-muted); font-size: .74rem; }
.empty, .empty-inline { padding: 20px; color: var(--text-muted); text-align: center; }

@media (max-width: 1100px) {
  .metric-rail { grid-template-columns: repeat(3, 1fr); }
  .grid-2 { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
  .metric-rail, .form-grid, .quota-meta { grid-template-columns: 1fr; }
}
</style>
