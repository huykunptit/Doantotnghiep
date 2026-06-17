<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import UiStatCard from '~/components/dashboard/charts/UiStatCard.vue'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

// ─── Types ────────────────────────────────────────────────────────────────────

interface AiSettings {
  id: number
  provider: string
  model: string
  api_key?: string
  monthly_token_quota: number
  tokens_used: number
  max_requests_per_minute: number
  is_active: boolean
  quota_reset_at?: string
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
}

interface EndpointStat { endpoint: string; count: number; tokens: number }
interface ProviderStat  { provider: string; count: number; tokens: number }
interface DailyPoint    { date: string; count: number; tokens: number }

interface RecentLog {
  id: number
  user?: { name: string; email: string; avatar?: string }
  endpoint: string
  provider: string
  model: string
  tokens_used: number
  response_time_ms: number
  status: 'success' | 'error'
  error_message?: string
  created_at: string
}

interface ProviderModel { id: string; name: string; tier: string }
interface Provider      { id: string; name: string; icon: string; color: string; models: ProviderModel[] }

// ─── State ────────────────────────────────────────────────────────────────────

const loading   = ref(true)
const saving    = ref(false)
const resetting = ref(false)
const error     = ref('')
const success   = ref('')

const settings     = ref<AiSettings | null>(null)
const stats        = ref<AiStats | null>(null)
const byEndpoint   = ref<EndpointStat[]>([])
const byProvider   = ref<ProviderStat[]>([])
const dailyPoints  = ref<DailyPoint[]>([])
const recentLogs   = ref<RecentLog[]>([])
const providers    = ref<Provider[]>([])

const form = ref({
  provider: 'chatgpt',
  model: 'gpt-4o-mini',
  api_key: '',
  monthly_token_quota: 1_000_000,
  max_requests_per_minute: 60,
  is_active: true,
})

// ─── Composable ───────────────────────────────────────────────────────────────

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

// ─── Fetch ────────────────────────────────────────────────────────────────────

async function fetchDashboard() {
  loading.value = true
  error.value = ''
  try {
    const [dash, prov] = await Promise.all([
      useApi<any>('/admin/ai/dashboard', { headers: authHeaders() }),
      useApi<any>('/admin/ai/providers',  { headers: authHeaders() }),
    ])

    settings.value   = dash.settings
    stats.value      = dash.stats
    byEndpoint.value = dash.by_endpoint  ?? []
    byProvider.value = dash.by_provider  ?? []
    dailyPoints.value = dash.daily_requests ?? []
    recentLogs.value = dash.recent_logs  ?? []
    providers.value  = prov.providers    ?? []

    if (settings.value) {
      form.value.provider              = settings.value.provider
      form.value.model                 = settings.value.model
      form.value.monthly_token_quota   = settings.value.monthly_token_quota
      form.value.max_requests_per_minute = settings.value.max_requests_per_minute
      form.value.is_active             = settings.value.is_active
    }
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu AI.'
  }
  finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  success.value = ''
  error.value   = ''
  try {
    const body: any = {
      provider:                form.value.provider,
      model:                   form.value.model,
      monthly_token_quota:     form.value.monthly_token_quota,
      max_requests_per_minute: form.value.max_requests_per_minute,
      is_active:               form.value.is_active,
    }
    if (form.value.api_key.trim()) body.api_key = form.value.api_key.trim()

    const res = await useApi<any>('/admin/ai/settings', {
      method: 'PUT',
      body,
      headers: authHeaders(),
    })
    settings.value    = res.settings
    form.value.api_key = ''
    success.value = 'Đã lưu cài đặt AI.'
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu cài đặt.'
  }
  finally {
    saving.value = false
  }
}

async function resetQuota() {
  if (!confirm('Reset token quota về 0?')) return
  resetting.value = true
  try {
    const res = await useApi<any>('/admin/ai/reset-quota', {
      method: 'POST',
      headers: authHeaders(),
    })
    settings.value = res.settings
    if (stats.value) stats.value.total_tokens = 0
    success.value = 'Đã reset quota.'
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể reset quota.'
  }
  finally {
    resetting.value = false
  }
}

// ─── Computed ────────────────────────────────────────────────────────────────

const currentProvider = computed(() =>
  providers.value.find(p => p.id === form.value.provider)
)

const availableModels = computed(() =>
  currentProvider.value?.models ?? []
)

const usagePercent = computed(() => settings.value?.usage_percent ?? 0)

const quotaBarColor = computed(() => {
  if (usagePercent.value >= 90) return '#dc2626'
  if (usagePercent.value >= 70) return '#d97706'
  return 'var(--green)'
})

const tokensRemaining = computed(() => {
  if (!settings.value) return 0
  return Math.max(0, settings.value.monthly_token_quota - settings.value.tokens_used)
})

const chartMax = computed(() => Math.max(...dailyPoints.value.map(d => d.tokens), 1))

function tierBadge(tier: string) {
  const map: Record<string, { label: string; color: string }> = {
    free:     { label: 'Free',    color: '#16a34a' },
    economy:  { label: 'Economy', color: '#2563eb' },
    standard: { label: 'Standard', color: '#7c3aed' },
    premium:  { label: 'Premium', color: '#b45309' },
  }
  return map[tier] ?? { label: tier, color: '#64748b' }
}

function formatK(n: number) {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`
  return String(n)
}

function formatMs(ms: number) {
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`
}

function formatDate(str: string) {
  return new Date(str).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
}

function formatDayLabel(str: string) {
  const d = new Date(str)
  return `${d.getDate()}/${d.getMonth() + 1}`
}

onMounted(fetchDashboard)
</script>

<template>
  <AdminWorkspaceShell
    title="Quản lý AI"
    description="Cấu hình provider, model, theo dõi token và thống kê sử dụng AI."
    :breadcrumb="['Admin', 'Quản lý AI']"
  >
    <!-- Alert -->
    <div v-if="error"   class="ai-alert is-error"   >{{ error }}</div>
    <div v-if="success" class="ai-alert is-success"  >{{ success }}</div>

    <!-- Stats -->
    <div class="ai-stats-grid">
      <UiStatCard
        label="Token đã dùng"
        :value="formatK(settings?.tokens_used ?? 0)"
        icon="token"
        icon-bg="rgba(124,58,237,0.1)"
        icon-color="#7c3aed"
        :loading="loading"
      />
      <UiStatCard
        label="Token còn lại"
        :value="formatK(tokensRemaining)"
        icon="savings"
        icon-bg="rgba(22,163,74,0.1)"
        icon-color="#16a34a"
        :loading="loading"
      />
      <UiStatCard
        label="Tổng yêu cầu"
        :value="formatK(stats?.total_requests ?? 0)"
        icon="chat_bubble"
        icon-bg="rgba(37,99,235,0.1)"
        icon-color="#2563eb"
        :loading="loading"
      />
      <UiStatCard
        label="Thời gian TB"
        :value="formatMs(stats?.avg_response_time ?? 0)"
        icon="timer"
        icon-bg="rgba(217,119,6,0.1)"
        icon-color="#d97706"
        :loading="loading"
      />
    </div>

    <!-- Quota bar -->
    <div class="dashboard-card ai-quota-card">
      <div class="ai-quota-header">
        <span class="ai-quota-title">Quota tháng này</span>
        <span class="ai-quota-pct" :style="{ color: quotaBarColor }">{{ usagePercent }}%</span>
      </div>
      <div class="ai-quota-bar-bg">
        <div
          class="ai-quota-bar-fill"
          :style="{ width: `${Math.min(usagePercent, 100)}%`, background: quotaBarColor }"
        />
      </div>
      <div class="ai-quota-meta">
        <span>{{ formatK(settings?.tokens_used ?? 0) }} / {{ formatK(settings?.monthly_token_quota ?? 0) }} tokens</span>
        <button class="ai-reset-btn" :disabled="resetting" @click="resetQuota">
          <span class="material-symbols-outlined">restart_alt</span>
          {{ resetting ? 'Đang reset...' : 'Reset quota' }}
        </button>
      </div>
    </div>

    <div class="ai-main-grid">
      <!-- Settings card -->
      <div class="dashboard-card ai-settings-card">
        <h3 class="ai-card-title">
          <span class="material-symbols-outlined">settings</span> Cài đặt Provider
        </h3>

        <!-- Provider selector -->
        <p class="ai-field-label">Provider</p>
        <div class="ai-provider-grid">
          <button
            v-for="prov in providers"
            :key="prov.id"
            class="ai-provider-btn"
            :class="{ 'is-active': form.provider === prov.id }"
            :style="form.provider === prov.id ? { borderColor: prov.color, boxShadow: `0 0 0 3px ${prov.color}22` } : {}"
            @click="form.provider = prov.id; form.model = prov.models[0]?.id ?? ''"
          >
            <span class="material-symbols-outlined" :style="{ color: prov.color }">{{ prov.icon }}</span>
            <span class="ai-provider-name">{{ prov.name }}</span>
          </button>
        </div>

        <!-- Model selector -->
        <p class="ai-field-label">Model</p>
        <div class="ai-model-grid">
          <button
            v-for="m in availableModels"
            :key="m.id"
            class="ai-model-btn"
            :class="{ 'is-active': form.model === m.id }"
            @click="form.model = m.id"
          >
            <span class="ai-model-name">{{ m.name }}</span>
            <span
              class="ai-model-tier"
              :style="{ background: `${tierBadge(m.tier).color}18`, color: tierBadge(m.tier).color }"
            >{{ tierBadge(m.tier).label }}</span>
          </button>
        </div>

        <!-- API Key -->
        <p class="ai-field-label">
          API Key
          <span v-if="settings?.has_api_key" class="ai-key-set">
            <span class="material-symbols-outlined">check_circle</span> Đã cấu hình
          </span>
        </p>
        <input
          v-model="form.api_key"
          type="password"
          class="ai-input"
          :placeholder="settings?.has_api_key ? '••••••••  (để trống = giữ key cũ)' : 'Nhập API key...'"
        />

        <!-- Quota settings -->
        <div class="ai-row">
          <div class="ai-col">
            <p class="ai-field-label">Quota hàng tháng (tokens)</p>
            <input v-model.number="form.monthly_token_quota" type="number" class="ai-input" min="1000" step="100000" />
          </div>
          <div class="ai-col">
            <p class="ai-field-label">Giới hạn req/phút</p>
            <input v-model.number="form.max_requests_per_minute" type="number" class="ai-input" min="1" max="1000" />
          </div>
        </div>

        <!-- Active toggle -->
        <label class="ai-toggle">
          <input v-model="form.is_active" type="checkbox" />
          <span class="ai-toggle-track" />
          <span class="ai-toggle-label">Kích hoạt AI</span>
        </label>

        <button class="ai-save-btn" :disabled="saving" @click="saveSettings">
          <span class="material-symbols-outlined">save</span>
          {{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}
        </button>
      </div>

      <!-- Right column -->
      <div class="ai-right-col">
        <!-- Daily chart -->
        <div class="dashboard-card ai-chart-card">
          <h3 class="ai-card-title">
            <span class="material-symbols-outlined">bar_chart</span> Token theo ngày (14 ngày)
          </h3>
          <div v-if="loading" class="ai-chart-skeleton" />
          <div v-else-if="dailyPoints.length === 0" class="ai-empty">Chưa có dữ liệu</div>
          <div v-else class="ai-bar-chart">
            <div
              v-for="pt in dailyPoints"
              :key="pt.date"
              class="ai-bar-col"
              :title="`${formatDayLabel(pt.date)}: ${formatK(pt.tokens)} tokens, ${pt.count} req`"
            >
              <div class="ai-bar-wrap">
                <div
                  class="ai-bar"
                  :style="{ height: `${Math.max(4, Math.round((pt.tokens / chartMax) * 120))}px` }"
                />
              </div>
              <span class="ai-bar-label">{{ formatDayLabel(pt.date) }}</span>
            </div>
          </div>
        </div>

        <!-- By endpoint & provider -->
        <div class="ai-two-col">
          <div class="dashboard-card">
            <h3 class="ai-card-title">
              <span class="material-symbols-outlined">api</span> Theo endpoint
            </h3>
            <div v-if="byEndpoint.length === 0" class="ai-empty">Chưa có dữ liệu</div>
            <div v-for="ep in byEndpoint" :key="ep.endpoint" class="ai-stat-row">
              <span class="ai-stat-label">{{ ep.endpoint }}</span>
              <span class="ai-stat-val">{{ formatK(ep.tokens) }}</span>
              <span class="ai-stat-count">{{ ep.count }} req</span>
            </div>
          </div>
          <div class="dashboard-card">
            <h3 class="ai-card-title">
              <span class="material-symbols-outlined">hub</span> Theo provider
            </h3>
            <div v-if="byProvider.length === 0" class="ai-empty">Chưa có dữ liệu</div>
            <div v-for="pv in byProvider" :key="pv.provider" class="ai-stat-row">
              <span class="ai-stat-label">{{ pv.provider }}</span>
              <span class="ai-stat-val">{{ formatK(pv.tokens) }}</span>
              <span class="ai-stat-count">{{ pv.count }} req</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent logs -->
    <div class="dashboard-card ai-logs-card">
      <h3 class="ai-card-title">
        <span class="material-symbols-outlined">history</span> Nhật ký gần đây
      </h3>
      <div v-if="loading" class="ai-empty">Đang tải...</div>
      <div v-else-if="recentLogs.length === 0" class="ai-empty">Chưa có yêu cầu nào.</div>
      <div v-else class="ai-table-wrap">
        <table class="ai-table">
          <thead>
            <tr>
              <th>Người dùng</th>
              <th>Endpoint</th>
              <th>Provider / Model</th>
              <th>Tokens</th>
              <th>Thời gian</th>
              <th>Trạng thái</th>
              <th>Lúc</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in recentLogs" :key="log.id">
              <td>
                <div class="ai-user-cell">
                  <img v-if="log.user?.avatar" :src="log.user.avatar" class="ai-avatar" />
                  <div v-else class="ai-avatar-placeholder">{{ (log.user?.name ?? '?')[0] }}</div>
                  <span>{{ log.user?.name ?? '—' }}</span>
                </div>
              </td>
              <td><code class="ai-code">{{ log.endpoint }}</code></td>
              <td>
                <span class="ai-provider-tag">{{ log.provider }}</span>
                <span class="ai-model-tag">{{ log.model }}</span>
              </td>
              <td>{{ formatK(log.tokens_used) }}</td>
              <td>{{ formatMs(log.response_time_ms) }}</td>
              <td>
                <span class="ai-status-badge" :class="log.status === 'success' ? 'is-success' : 'is-error'">
                  {{ log.status === 'success' ? 'OK' : 'Lỗi' }}
                </span>
              </td>
              <td class="ai-date">{{ formatDate(log.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
.ai-alert {
  padding: 12px 16px;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: 0.88rem;
  font-weight: 500;
}
.ai-alert.is-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.ai-alert.is-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

/* Stats */
.ai-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}
@media (max-width: 900px) { .ai-stats-grid { grid-template-columns: repeat(2, 1fr); } }

/* Quota */
.ai-quota-card { margin-bottom: 16px; }
.ai-quota-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.ai-quota-title  { font-weight: 700; font-size: 0.9rem; }
.ai-quota-pct    { font-size: 1.1rem; font-weight: 800; }
.ai-quota-bar-bg { height: 10px; background: var(--surface-dim, #e5e7eb); border-radius: 99px; overflow: hidden; margin-bottom: 10px; }
.ai-quota-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.ai-quota-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: var(--on-surface-variant); }
.ai-reset-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 5px 12px; border-radius: 8px; border: 1px solid var(--surface-dim);
  background: transparent; cursor: pointer; font-size: 0.82rem; color: var(--on-surface-variant);
  transition: background 0.15s;
}
.ai-reset-btn:hover { background: var(--surface-low); }
.ai-reset-btn .material-symbols-outlined { font-size: 16px; }
.ai-reset-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Main grid */
.ai-main-grid {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 16px;
  margin-bottom: 16px;
  align-items: start;
}
@media (max-width: 1100px) { .ai-main-grid { grid-template-columns: 1fr; } }

/* Settings card */
.ai-card-title {
  display: flex; align-items: center; gap: 8px;
  font-size: 0.92rem; font-weight: 700; margin: 0 0 16px;
}
.ai-card-title .material-symbols-outlined { font-size: 18px; color: var(--green); }

.ai-field-label {
  font-size: 0.76rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: var(--on-surface-variant); margin: 0 0 8px;
  display: flex; align-items: center; gap: 6px;
}
.ai-key-set {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 0.72rem; color: #16a34a; text-transform: none; letter-spacing: 0;
}
.ai-key-set .material-symbols-outlined { font-size: 13px; }

/* Provider buttons */
.ai-provider-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 16px; }
.ai-provider-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; border-radius: 10px; border: 1.5px solid var(--surface-dim);
  background: var(--surface-lowest); cursor: pointer; font-size: 0.85rem;
  font-weight: 600; color: var(--on-surface); transition: all 0.15s;
}
.ai-provider-btn:hover { background: var(--surface-low); }
.ai-provider-btn.is-active { background: var(--surface-low); }
.ai-provider-btn .material-symbols-outlined { font-size: 20px; }
.ai-provider-name { line-height: 1.2; }

/* Model buttons */
.ai-model-grid { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; max-height: 220px; overflow-y: auto; }
.ai-model-btn {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; border-radius: 8px; border: 1.5px solid var(--surface-dim);
  background: var(--surface-lowest); cursor: pointer; text-align: left; transition: all 0.15s;
}
.ai-model-btn:hover  { background: var(--surface-low); }
.ai-model-btn.is-active { border-color: var(--green); background: rgba(var(--green-rgb), 0.06); }
.ai-model-name { font-size: 0.85rem; font-weight: 500; color: var(--on-surface); }
.ai-model-tier {
  font-size: 0.68rem; font-weight: 700; padding: 2px 8px;
  border-radius: 99px; text-transform: uppercase; letter-spacing: 0.06em;
}

/* Input */
.ai-input {
  width: 100%; padding: 9px 12px; border-radius: 9px;
  border: 1.5px solid var(--surface-dim); background: var(--surface-lowest);
  font-size: 0.88rem; color: var(--on-surface); outline: none;
  transition: border-color 0.15s; margin-bottom: 14px; box-sizing: border-box;
}
.ai-input:focus { border-color: var(--green); }

.ai-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ai-col  {}

/* Toggle */
.ai-toggle {
  display: flex; align-items: center; gap: 10px;
  cursor: pointer; margin-bottom: 16px; user-select: none;
}
.ai-toggle input { display: none; }
.ai-toggle-track {
  width: 38px; height: 22px; border-radius: 99px;
  background: var(--surface-dim); position: relative; transition: background 0.2s;
  flex-shrink: 0;
}
.ai-toggle-track::after {
  content: ''; position: absolute; top: 3px; left: 3px;
  width: 16px; height: 16px; border-radius: 50%;
  background: #fff; transition: transform 0.2s;
}
.ai-toggle input:checked ~ .ai-toggle-track { background: var(--green); }
.ai-toggle input:checked ~ .ai-toggle-track::after { transform: translateX(16px); }
.ai-toggle-label { font-size: 0.88rem; font-weight: 600; color: var(--on-surface); }

.ai-save-btn {
  display: flex; align-items: center; gap: 6px; justify-content: center;
  width: 100%; padding: 11px; border-radius: 10px; border: none;
  background: var(--green); color: #fff; font-size: 0.9rem; font-weight: 700;
  cursor: pointer; transition: opacity 0.15s;
}
.ai-save-btn:hover   { opacity: 0.88; }
.ai-save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.ai-save-btn .material-symbols-outlined { font-size: 18px; }

/* Right col */
.ai-right-col { display: flex; flex-direction: column; gap: 16px; }

/* Chart */
.ai-chart-card {}
.ai-chart-skeleton { height: 140px; background: var(--surface-low); border-radius: 10px; animation: pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
.ai-bar-chart {
  display: flex; align-items: flex-end; gap: 6px;
  height: 140px; padding-top: 8px;
}
.ai-bar-col { display: flex; flex-direction: column; align-items: center; flex: 1; gap: 4px; }
.ai-bar-wrap { flex: 1; display: flex; align-items: flex-end; }
.ai-bar {
  width: 100%; background: var(--green); border-radius: 4px 4px 0 0;
  opacity: 0.8; transition: opacity 0.15s; min-height: 4px;
}
.ai-bar-col:hover .ai-bar { opacity: 1; }
.ai-bar-label { font-size: 0.65rem; color: var(--on-surface-variant); white-space: nowrap; }

/* Two col */
.ai-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 700px) { .ai-two-col { grid-template-columns: 1fr; } }

.ai-stat-row {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 0; border-bottom: 1px solid var(--surface-dim); font-size: 0.84rem;
}
.ai-stat-row:last-child { border-bottom: none; }
.ai-stat-label { flex: 1; color: var(--on-surface); font-weight: 500; }
.ai-stat-val   { font-weight: 700; color: var(--on-surface); }
.ai-stat-count { font-size: 0.76rem; color: var(--on-surface-variant); }

/* Logs */
.ai-logs-card {}
.ai-table-wrap { overflow-x: auto; }
.ai-table {
  width: 100%; border-collapse: collapse; font-size: 0.84rem;
}
.ai-table th {
  text-align: left; padding: 8px 12px; font-size: 0.72rem;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--on-surface-variant); border-bottom: 1px solid var(--surface-dim);
}
.ai-table td { padding: 10px 12px; border-bottom: 1px solid var(--surface-dim); vertical-align: middle; }
.ai-table tr:last-child td { border-bottom: none; }

.ai-user-cell { display: flex; align-items: center; gap: 8px; }
.ai-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
.ai-avatar-placeholder {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--green); color: #fff;
  display: grid; place-items: center; font-size: 0.75rem; font-weight: 700;
}
.ai-code { font-family: monospace; font-size: 0.78rem; background: var(--surface-low); padding: 2px 6px; border-radius: 4px; }
.ai-provider-tag {
  display: inline-block; font-size: 0.72rem; font-weight: 700;
  background: rgba(124,58,237,0.1); color: #7c3aed;
  padding: 2px 6px; border-radius: 4px; margin-right: 4px;
}
.ai-model-tag {
  display: inline-block; font-size: 0.72rem;
  color: var(--on-surface-variant); font-family: monospace;
}
.ai-status-badge {
  display: inline-block; padding: 2px 10px; border-radius: 99px;
  font-size: 0.74rem; font-weight: 700;
}
.ai-status-badge.is-success { background: rgba(22,163,74,0.1); color: #16a34a; }
.ai-status-badge.is-error   { background: rgba(220,38,38,0.1); color: #dc2626; }
.ai-date { color: var(--on-surface-variant); font-size: 0.8rem; white-space: nowrap; }

.ai-empty { color: var(--on-surface-variant); font-size: 0.88rem; padding: 16px 0; text-align: center; }
</style>
