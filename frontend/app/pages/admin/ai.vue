<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
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
const logPage      = ref(1)
const logPerPage   = ref(10)

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
const logLastPage = computed(() => Math.max(1, Math.ceil(recentLogs.value.length / logPerPage.value)))
const pagedLogs = computed(() => {
  const start = (logPage.value - 1) * logPerPage.value
  return recentLogs.value.slice(start, start + logPerPage.value)
})

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Cấu hình hệ thống</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Quản lý AI</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Cấu hình provider, model, theo dõi token và thống kê sử dụng AI.</p>
      </div>
    </div>

    <!-- Alert -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-5 py-4 text-sm">{{ error }}</div>
    <div v-if="success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl px-5 py-4 text-sm">{{ success }}</div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <UiStatCard
        label="Token đã dùng"
        :value="formatK(settings?.tokens_used ?? 0)"
        icon="pi-bolt"
        icon-bg="rgba(124,58,237,0.1)"
        icon-color="#7c3aed"
        :loading="loading"
      />
      <UiStatCard
        label="Token còn lại"
        :value="formatK(tokensRemaining)"
        icon="pi-wallet"
        icon-bg="rgba(22,163,74,0.1)"
        icon-color="#16a34a"
        :loading="loading"
      />
      <UiStatCard
        label="Tổng yêu cầu"
        :value="formatK(stats?.total_requests ?? 0)"
        icon="pi-comment"
        icon-bg="rgba(37,99,235,0.1)"
        icon-color="#2563eb"
        :loading="loading"
      />
      <UiStatCard
        label="Thời gian TB"
        :value="formatMs(stats?.avg_response_time ?? 0)"
        icon="pi-clock"
        icon-bg="rgba(217,119,6,0.1)"
        icon-color="#d97706"
        :loading="loading"
      />
    </div>

    <!-- Quota bar -->
    <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
      <div class="flex justify-between items-center mb-3">
        <span class="text-sm font-bold text-[var(--text)]">Quota tháng này</span>
        <span class="text-base font-extrabold text-[var(--text)]" :style="{ color: quotaBarColor }">{{ usagePercent }}%</span>
      </div>
      <div class="h-2.5 bg-[var(--surface)] rounded-full overflow-hidden mb-3">
        <div
          class="h-full rounded-full transition-all duration-500"
          :style="{ width: `${Math.min(usagePercent, 100)}%`, background: quotaBarColor }"
        />
      </div>
      <div class="flex justify-between items-center text-xs text-[var(--muted)]">
        <span>{{ formatK(settings?.tokens_used ?? 0) }} / {{ formatK(settings?.monthly_token_quota ?? 0) }} tokens</span>
        <button 
          class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors disabled:opacity-40" 
          :disabled="resetting" 
          @click="resetQuota"
        >
          <i class="pi pi-refresh" />
          {{ resetting ? 'Đang reset...' : 'Reset quota' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[380px_1fr] gap-5">
      <!-- Settings card -->
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
        <h3 class="text-sm font-bold text-[var(--text)] flex items-center gap-2">
          <i class="pi pi-cog text-[#1d9e75]" /> Cài đặt Provider
        </h3>

        <!-- Provider selector -->
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-2">Provider</p>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="prov in providers"
              :key="prov.id"
              class="flex items-center gap-2 p-3 rounded-xl border border-[var(--line)] hover:bg-[var(--surface)] text-sm font-semibold transition-all w-full text-left"
              :class="{ 'border-transparent ring-2': form.provider === prov.id }"
              :style="form.provider === prov.id ? { borderColor: prov.color, boxShadow: `0 0 0 3px ${prov.color}22` } : {}"
              @click="form.provider = prov.id; form.model = prov.models[0]?.id ?? ''"
            >
              <i class="pi pi-circle-fill text-[8px]" :style="{ color: prov.color }" />
              <span class="text-xs text-[var(--text)] truncate">{{ prov.name }}</span>
            </button>
          </div>
        </div>

        <!-- Model selector -->
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-2">Model</p>
          <div class="flex flex-col gap-1.5 max-h-[220px] overflow-y-auto pr-1">
            <button
              v-for="m in availableModels"
              :key="m.id"
              class="flex items-center justify-between p-2.5 rounded-xl border border-[var(--line)] hover:bg-[var(--surface)] text-left transition-colors"
              :class="{ 'border-[#1d9e75] bg-[rgba(29,158,117,0.06)]': form.model === m.id }"
              @click="form.model = m.id"
            >
              <span class="text-xs font-semibold text-[var(--text)]">{{ m.name }}</span>
              <span
                class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                :style="{ background: `${tierBadge(m.tier).color}18`, color: tierBadge(m.tier).color }"
              >{{ tierBadge(m.tier).label }}</span>
            </button>
          </div>
        </div>

        <!-- API Key -->
        <div class="flex flex-col gap-1.5">
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] flex items-center justify-between">
            API Key
            <span v-if="settings?.has_api_key" class="inline-flex items-center gap-1 text-[10px] text-emerald-600 lowercase tracking-normal font-semibold">
              <i class="pi pi-check-circle" /> Đã cấu hình
            </span>
          </p>
          <input
            v-model="form.api_key"
            type="password"
            class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)] w-full"
            :placeholder="settings?.has_api_key ? '••••••••  (để trống = giữ key cũ)' : 'Nhập API key...'"
          />
        </div>

        <!-- Quota settings -->
        <div class="grid grid-cols-2 gap-3">
          <div class="flex flex-col gap-1.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Quota hàng tháng</p>
            <input v-model.number="form.monthly_token_quota" type="number" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" min="1000" step="100000" />
          </div>
          <div class="flex flex-col gap-1.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">req/phút</p>
            <input v-model.number="form.max_requests_per_minute" type="number" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" min="1" max="1000" />
          </div>
        </div>

        <!-- Active toggle -->
        <div class="flex items-center gap-2 mt-2">
          <input type="checkbox" id="is_active_check" v-model="form.is_active" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75]" />
          <label for="is_active_check" class="text-xs font-semibold text-[var(--text)] cursor-pointer">Kích hoạt AI</label>
        </div>

        <button 
          class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl text-sm font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors w-full disabled:opacity-50 disabled:cursor-not-allowed mt-2" 
          :disabled="saving" 
          @click="saveSettings"
        >
          <i class="pi pi-save" />
          {{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}
        </button>
      </div>

      <!-- Right column -->
      <div class="flex flex-col gap-5">
        <!-- Daily chart -->
        <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <h3 class="text-sm font-bold text-[var(--text)] flex items-center gap-2">
            <i class="pi pi-chart-bar text-[#1d9e75]" /> Token theo ngày (14 ngày)
          </h3>
          <div v-if="loading" class="h-36 bg-[var(--surface)] animate-pulse rounded-xl" />
          <div v-else-if="dailyPoints.length === 0" class="text-center py-8 text-sm text-[var(--muted)]">Chưa có dữ liệu</div>
          <div v-else class="flex items-end gap-2.5 h-36 pt-2">
            <div
              v-for="pt in dailyPoints"
              :key="pt.date"
              class="flex flex-col items-center flex-1 gap-1.5 group"
              :title="`${formatDayLabel(pt.date)}: ${formatK(pt.tokens)} tokens, ${pt.count} req`"
            >
              <div class="flex-1 flex items-end w-full">
                <div
                  class="w-full bg-[#1d9e75] rounded-t min-h-[4px] opacity-80 group-hover:opacity-100 transition-all duration-300"
                  :style="{ height: `${Math.max(4, Math.round((pt.tokens / chartMax) * 100))}px` }"
                />
              </div>
              <span class="text-[9px] text-[var(--muted)] group-hover:text-[var(--text)] transition-colors">{{ formatDayLabel(pt.date) }}</span>
            </div>
          </div>
        </div>

        <!-- By endpoint & provider -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
            <h3 class="text-sm font-bold text-[var(--text)] flex items-center gap-2">
              <i class="pi pi-server text-[#1d9e75]" /> Theo endpoint
            </h3>
            <div v-if="byEndpoint.length === 0" class="text-center py-4 text-xs text-[var(--muted)]">Chưa có dữ liệu</div>
            <div v-for="ep in byEndpoint" :key="ep.endpoint" class="flex justify-between items-center py-2 border-b border-[var(--line)] last:border-0 text-xs">
              <code class="font-mono text-[var(--text)] bg-[var(--surface)] px-1.5 py-0.5 rounded border border-[var(--line)]">{{ ep.endpoint }}</code>
              <div class="flex items-center gap-2 font-semibold">
                <span class="text-[var(--text)]">{{ formatK(ep.tokens) }} tokens</span>
                <span class="text-[var(--muted)]">({{ ep.count }} req)</span>
              </div>
            </div>
          </div>
          
          <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
            <h3 class="text-sm font-bold text-[var(--text)] flex items-center gap-2">
              <i class="pi pi-share-alt text-[#1d9e75]" /> Theo provider
            </h3>
            <div v-if="byProvider.length === 0" class="text-center py-4 text-xs text-[var(--muted)]">Chưa có dữ liệu</div>
            <div v-for="pv in byProvider" :key="pv.provider" class="flex justify-between items-center py-2 border-b border-[var(--line)] last:border-0 text-xs">
              <span class="font-semibold text-[var(--text)]">{{ pv.provider }}</span>
              <div class="flex items-center gap-2 font-semibold">
                <span class="text-[var(--text)]">{{ formatK(pv.tokens) }} tokens</span>
                <span class="text-[var(--muted)]">({{ pv.count }} req)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent logs -->
    <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
      <div class="flex items-center gap-2 px-6 pt-5 pb-4 border-b border-[var(--line)]">
        <i class="pi pi-history text-[#1d9e75]" />
        <h3 class="text-sm font-semibold text-[var(--text)]">Nhật ký gần đây</h3>
      </div>
      <div v-if="loading" class="text-center py-8 text-sm text-[var(--muted)]">Đang tải...</div>
      <div v-else-if="recentLogs.length === 0" class="text-center py-8 text-sm text-[var(--muted)]">Chưa có yêu cầu nào.</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)]">
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Người dùng</th>
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Endpoint</th>
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Provider / Model</th>
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Token</th>
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Phản hồi</th>
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái</th>
              <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Thời điểm</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in pagedLogs" :key="log.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div v-if="log.user?.avatar" class="w-7 h-7 rounded-full overflow-hidden border border-[var(--line)]">
                    <img :src="log.user.avatar" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold bg-[rgba(29,158,117,0.1)] text-[#085041] border border-[rgba(29,158,117,0.2)]">{{ (log.user?.name ?? '?')[0] }}</div>
                  <span class="text-xs font-semibold text-[var(--text)]">{{ log.user?.name ?? '—' }}</span>
                </div>
              </td>
              <td class="px-4 py-3"><code class="font-mono text-[10px] bg-[var(--surface)] px-1.5 py-0.5 rounded border border-[var(--line)] text-[var(--text)]">{{ log.endpoint }}</code></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="inline-block text-[9px] font-bold bg-violet-50 text-violet-700 border border-violet-200 px-1.5 py-0.5 rounded">{{ log.provider }}</span>
                  <span class="text-[10px] font-mono text-[var(--muted)]">{{ log.model }}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-xs font-semibold text-[var(--text)]">{{ formatK(log.tokens_used) }}</td>
              <td class="px-4 py-3 text-xs text-[var(--muted)]">{{ formatMs(log.response_time_ms) }}</td>
              <td class="px-4 py-3">
                <span 
                  class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                  :class="log.status === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                >
                  {{ log.status === 'success' ? 'OK' : 'Lỗi' }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-[var(--muted)]">{{ formatDate(log.created_at) }}</td>
            </tr>
          </tbody>
        </table>

        <DataTableFooter
          :current="logPage"
          :last="logLastPage"
          :total="recentLogs.length"
          :per-page="logPerPage"
          @page="logPage = $event"
          @update:per-page="logPerPage = $event; logPage = 1"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
