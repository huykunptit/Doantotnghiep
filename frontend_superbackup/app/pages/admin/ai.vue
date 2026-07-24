<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from '~/composables/useToast'

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
const resetDialogVisible = ref(false)
const toast = useToast()

const settings     = ref<AiSettings | null>(null)
const stats        = ref<AiStats | null>(null)
const byEndpoint   = ref<EndpointStat[]>([])
const byProvider   = ref<ProviderStat[]>([])
const dailyPoints  = ref<DailyPoint[]>([])
const recentLogs   = ref<RecentLog[]>([])
const providers    = ref<Provider[]>([])
const logPage      = ref(0)
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
    toast.success('Đã lưu cài đặt AI.')
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu cài đặt.'
  }
  finally {
    saving.value = false
  }
}

async function resetQuota() {
  resetDialogVisible.value = false
  resetting.value = true
  try {
    const res = await useApi<any>('/admin/ai/reset-quota', {
      method: 'POST',
      headers: authHeaders(),
    })
    settings.value = res.settings
    if (stats.value) stats.value.total_tokens = 0
    toast.success('Đã reset quota.')
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

function statusSeverity(status: RecentLog['status']) {
  return status === 'success' ? 'success' : 'danger'
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
  <div class="page-stack">
    <header class="page-header">
      <div>
        <h1>Quản lý AI</h1>
        <p>Cấu hình nhà cung cấp, mô hình và theo dõi mức sử dụng AI.</p>
      </div>
      <Button icon="pi pi-refresh" label="Làm mới" severity="secondary" outlined :loading="loading" @click="fetchDashboard" />
    </header>

    <div v-if="error" class="error-banner"><i class="pi pi-exclamation-circle" />{{ error }}</div>

    <section class="stats-grid">
      <Card v-for="item in [
        { label: 'Token đã dùng', value: formatK(settings?.tokens_used ?? 0), icon: 'pi-bolt' },
        { label: 'Token còn lại', value: formatK(tokensRemaining), icon: 'pi-wallet' },
        { label: 'Tổng yêu cầu', value: formatK(stats?.total_requests ?? 0), icon: 'pi-comment' },
        { label: 'Thời gian trung bình', value: formatMs(stats?.avg_response_time ?? 0), icon: 'pi-clock' },
      ]" :key="item.label">
        <template #content>
          <div class="stat-content"><i class="pi" :class="item.icon" /><div><span>{{ item.label }}</span><strong>{{ loading ? '—' : item.value }}</strong></div></div>
        </template>
      </Card>
    </section>

    <Card>
      <template #title>Quota tháng này</template>
      <template #content>
        <div class="quota-head"><span>{{ formatK(settings?.tokens_used ?? 0) }} / {{ formatK(settings?.monthly_token_quota ?? 0) }} tokens</span><strong :style="{ color: quotaBarColor }">{{ usagePercent }}%</strong></div>
        <ProgressBar :value="Math.min(usagePercent, 100)" :show-value="false" />
      </template>
      <template #footer>
        <Button label="Reset quota" icon="pi pi-refresh" severity="danger" text :loading="resetting" @click="resetDialogVisible = true" />
      </template>
    </Card>

    <section class="content-grid">
      <Card>
        <template #title>Cài đặt nhà cung cấp</template>
        <template #content>
          <div class="form-grid">
            <label class="field"><span>Nhà cung cấp</span><Select v-model="form.provider" :options="providers" option-label="name" option-value="id" fluid @change="form.model = availableModels[0]?.id ?? ''" /></label>
            <label class="field"><span>Mô hình</span><Select v-model="form.model" :options="availableModels" option-label="name" option-value="id" fluid><template #option="{ option }"><div class="select-option"><span>{{ option.name }}</span><Tag :value="tierBadge(option.tier).label" severity="secondary" /></div></template></Select></label>
            <label class="field full"><span>API key <Tag v-if="settings?.has_api_key" value="Đã cấu hình" severity="success" /></span><InputText v-model="form.api_key" type="password" fluid :placeholder="settings?.has_api_key ? 'Để trống để giữ key hiện tại' : 'Nhập API key'" /></label>
            <label class="field"><span>Quota hàng tháng</span><InputNumber v-model="form.monthly_token_quota" :min="1000" :step="100000" fluid /></label>
            <label class="field"><span>Yêu cầu / phút</span><InputNumber v-model="form.max_requests_per_minute" :min="1" :max="1000" fluid /></label>
            <label class="toggle-field full"><ToggleSwitch v-model="form.is_active" /><span>Kích hoạt AI</span></label>
          </div>
        </template>
        <template #footer><Button label="Lưu cài đặt" icon="pi pi-save" :loading="saving" fluid @click="saveSettings" /></template>
      </Card>

      <div class="right-stack">
        <Card>
          <template #title>Token theo ngày (14 ngày)</template>
          <template #content>
            <div v-if="loading" class="empty-state">Đang tải dữ liệu...</div>
            <div v-else-if="!dailyPoints.length" class="empty-state">Chưa có dữ liệu</div>
            <div v-else class="chart">
              <div v-for="pt in dailyPoints" :key="pt.date" class="chart-item" :title="`${formatK(pt.tokens)} tokens, ${pt.count} yêu cầu`">
                <div class="chart-track"><span :style="{ height: `${Math.max(4, Math.round((pt.tokens / chartMax) * 100))}px` }" /></div>
                <small>{{ formatDayLabel(pt.date) }}</small>
              </div>
            </div>
          </template>
        </Card>
        <div class="summary-grid">
          <Card><template #title>Theo endpoint</template><template #content><div v-if="!byEndpoint.length" class="empty-state">Chưa có dữ liệu</div><div v-for="item in byEndpoint" :key="item.endpoint" class="summary-row"><code>{{ item.endpoint }}</code><span>{{ formatK(item.tokens) }} · {{ item.count }} req</span></div></template></Card>
          <Card><template #title>Theo provider</template><template #content><div v-if="!byProvider.length" class="empty-state">Chưa có dữ liệu</div><div v-for="item in byProvider" :key="item.provider" class="summary-row"><strong>{{ item.provider }}</strong><span>{{ formatK(item.tokens) }} · {{ item.count }} req</span></div></template></Card>
        </div>
      </div>
    </section>

    <Card>
      <template #title>Nhật ký gần đây</template>
      <template #content>
        <DataTable v-model:first="logPage" :value="recentLogs" :loading="loading" paginator :rows="logPerPage" :rows-per-page-options="[10, 25, 50]" striped-rows scrollable>
          <template #empty>Chưa có yêu cầu nào.</template>
          <Column header="Người dùng"><template #body="{ data }"><div class="user-cell"><img v-if="data.user?.avatar" :src="data.user.avatar" :alt="data.user?.name"><span v-else>{{ (data.user?.name || '?')[0] }}</span><div><strong>{{ data.user?.name || '—' }}</strong><small>{{ data.user?.email }}</small></div></div></template></Column>
          <Column field="endpoint" header="Endpoint"><template #body="{ data }"><code>{{ data.endpoint }}</code></template></Column>
          <Column header="Provider / Model"><template #body="{ data }"><div class="model-cell"><Tag :value="data.provider" severity="info" /><small>{{ data.model }}</small></div></template></Column>
          <Column field="tokens_used" header="Token"><template #body="{ data }">{{ formatK(data.tokens_used) }}</template></Column>
          <Column field="response_time_ms" header="Phản hồi"><template #body="{ data }">{{ formatMs(data.response_time_ms) }}</template></Column>
          <Column field="status" header="Trạng thái"><template #body="{ data }"><Tag :value="data.status === 'success' ? 'Thành công' : 'Lỗi'" :severity="statusSeverity(data.status)" /></template></Column>
          <Column field="created_at" header="Thời điểm"><template #body="{ data }">{{ formatDate(data.created_at) }}</template></Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="resetDialogVisible" modal header="Reset token quota" :style="{ width: 'min(28rem, 92vw)' }">
      <p>Bạn có chắc muốn đưa số token đã sử dụng về 0?</p>
      <template #footer><Button label="Hủy" severity="secondary" text @click="resetDialogVisible = false" /><Button label="Reset quota" severity="danger" :loading="resetting" @click="resetQuota" /></template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-stack,.right-stack{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.page-header h1{margin:0;color:var(--p-text-color);font-size:1.5rem;font-weight:700}.page-header p{margin:.3rem 0 0;color:var(--p-text-muted-color);font-size:.875rem}.error-banner{display:flex;align-items:center;gap:.6rem;padding:.85rem 1rem;border:1px solid var(--p-red-300);border-radius:var(--p-border-radius-lg);background:color-mix(in srgb,var(--p-red-500) 10%,var(--p-content-background));color:var(--p-red-600)}.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.stat-content{display:flex;align-items:center;gap:1rem}.stat-content>i{font-size:1.4rem;color:var(--p-primary-color)}.stat-content span,.field>span{display:block;color:var(--p-text-muted-color);font-size:.75rem;font-weight:600}.stat-content strong{display:block;margin-top:.25rem;color:var(--p-text-color);font-size:1.25rem}.quota-head,.summary-row,.select-option{display:flex;align-items:center;justify-content:space-between;gap:1rem}.quota-head{margin-bottom:.75rem;color:var(--p-text-muted-color);font-size:.82rem}.content-grid{display:grid;grid-template-columns:minmax(18rem,24rem) minmax(0,1fr);gap:1.25rem}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.field{display:flex;flex-direction:column;gap:.4rem}.full{grid-column:1/-1}.toggle-field{display:flex;align-items:center;gap:.65rem;color:var(--p-text-color);font-size:.85rem}.chart{display:flex;height:9rem;align-items:flex-end;gap:.5rem}.chart-item{display:flex;min-width:0;flex:1;flex-direction:column;align-items:center;gap:.35rem}.chart-track{display:flex;height:7rem;width:100%;align-items:flex-end}.chart-track span{width:100%;min-height:4px;border-radius:.3rem .3rem 0 0;background:var(--p-primary-color)}.chart-item small,.summary-row span,.model-cell small,.user-cell small{color:var(--p-text-muted-color);font-size:.7rem}.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}.summary-row{padding:.65rem 0;border-bottom:1px solid var(--p-content-border-color);font-size:.78rem}.summary-row:last-child{border:0}.empty-state{padding:2rem;text-align:center;color:var(--p-text-muted-color);font-size:.85rem}.user-cell,.model-cell{display:flex;align-items:center;gap:.65rem}.user-cell img,.user-cell>span{width:2rem;height:2rem;border-radius:50%}.user-cell img{object-fit:cover}.user-cell>span{display:grid;place-items:center;background:var(--p-primary-100);color:var(--p-primary-700);font-weight:700}.user-cell strong,.user-cell small{display:block;white-space:nowrap}.model-cell{flex-wrap:wrap}code{color:var(--p-text-color);font-size:.72rem}
@media(max-width:1024px){.stats-grid{grid-template-columns:repeat(2,1fr)}.content-grid{grid-template-columns:1fr}}@media(max-width:640px){.page-header{align-items:flex-start;flex-direction:column}.stats-grid,.summary-grid,.form-grid{grid-template-columns:1fr}.full{grid-column:auto}}
</style>
