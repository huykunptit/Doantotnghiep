<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-surface-dim/30 pb-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Trí tuệ nhân tạo</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface mt-1">AI Command Center</h2>
          <p class="text-on-surface-variant text-sm mt-2">
            Theo dõi hiệu suất, kiểm soát token quota và chuyển đổi linh hoạt giữa các nhà cung cấp AI.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl border shadow-sm" :class="settings.is_active ? 'bg-primary/5 border-primary/20' : 'bg-error-container/20 border-error/20'">
            <span class="relative flex h-2.5 w-2.5">
              <span v-if="settings.is_active" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="settings.is_active ? 'bg-primary' : 'bg-error'"></span>
            </span>
            <span class="text-xs font-bold" :class="settings.is_active ? 'text-primary' : 'text-error'">
              {{ settings.is_active ? 'ĐANG HOẠT ĐỘNG' : 'TẮT' }}
            </span>
          </div>
          <button @click="toggleActive" class="px-5 py-2.5 text-sm font-bold rounded-lg shadow-sm border transition-all active:scale-95" :class="settings.is_active ? 'bg-error-container/20 border-error/20 text-error hover:bg-error-container/40' : 'cta-gradient text-white border-transparent hover:shadow-md'">
            <span class="material-symbols-outlined text-[16px] mr-1 align-middle">{{ settings.is_active ? 'pause_circle' : 'play_circle' }}</span>
            {{ settings.is_active ? 'Tạm dừng AI' : 'Kích hoạt AI' }}
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="grid grid-cols-12 gap-6">
        <div v-for="i in 4" :key="i" class="col-span-12 md:col-span-3 h-40 bg-surface-high animate-pulse rounded-2xl"></div>
        <div class="col-span-12 h-64 bg-surface-high animate-pulse rounded-2xl"></div>
      </div>

      <template v-else>

        <!-- ═══ BENTO METRICS ═══ -->
        <section class="grid grid-cols-12 gap-5">

          <!-- Active Provider Card -->
          <div class="col-span-12 md:col-span-6 lg:col-span-3 bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm hover:-translate-y-0.5 transition-all group">
            <div class="flex items-center justify-between mb-5">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-inner" :style="{ backgroundColor: activeProviderColor + '15' }">
                <span class="material-symbols-outlined" :style="{ color: activeProviderColor }">{{ activeProviderIcon }}</span>
              </div>
              <span class="text-[10px] font-bold uppercase tracking-widest text-outline">Provider</span>
            </div>
            <p class="text-2xl font-bold font-headline text-on-surface tracking-tight">{{ providerDisplayName }}</p>
            <p class="text-xs font-bold text-on-surface-variant mt-1 truncate">{{ settings.model }}</p>
          </div>

          <!-- Total Requests -->
          <div class="col-span-12 md:col-span-6 lg:col-span-3 bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm hover:-translate-y-0.5 transition-all">
            <div class="flex items-center justify-between mb-5">
              <div class="w-10 h-10 rounded-xl bg-secondary/10 flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-secondary">query_stats</span>
              </div>
              <span class="text-[10px] font-bold uppercase tracking-widest text-outline">Requests</span>
            </div>
            <p class="text-3xl font-bold font-headline text-on-surface tracking-tighter">{{ formatNumber(stats.total_requests) }}</p>
            <p class="text-xs font-bold text-on-surface-variant mt-1">{{ stats.unique_users }} người dùng</p>
          </div>

          <!-- Token Usage -->
          <div class="col-span-12 md:col-span-6 lg:col-span-3 bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm hover:-translate-y-0.5 transition-all">
            <div class="flex items-center justify-between mb-5">
              <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-primary">token</span>
              </div>
              <span class="text-[10px] font-bold uppercase tracking-widest text-outline">Token Quota</span>
            </div>
            <p class="text-3xl font-bold font-headline text-on-surface tracking-tighter">{{ usagePercent }}%</p>
            <div class="mt-2 h-2 w-full bg-surface-high rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700" :class="usagePercent > 80 ? 'bg-error' : usagePercent > 50 ? 'bg-amber-500' : 'cta-gradient'" :style="{ width: Math.min(usagePercent, 100) + '%' }"></div>
            </div>
            <p class="text-[10px] font-bold text-outline mt-2">{{ formatNumber(settings.tokens_used) }} / {{ formatNumber(settings.monthly_token_quota) }}</p>
          </div>

          <!-- Avg Response Time -->
          <div class="col-span-12 md:col-span-6 lg:col-span-3 bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm hover:-translate-y-0.5 transition-all">
            <div class="flex items-center justify-between mb-5">
              <div class="w-10 h-10 rounded-xl bg-tertiary/10 flex items-center justify-center shadow-inner">
                <span class="material-symbols-outlined text-tertiary">speed</span>
              </div>
              <span class="text-[10px] font-bold uppercase tracking-widest text-outline">Latency</span>
            </div>
            <p class="text-3xl font-bold font-headline text-on-surface tracking-tighter">{{ stats.avg_response_time || 0 }}<span class="text-base text-on-surface-variant ml-0.5">ms</span></p>
            <div class="flex items-center gap-3 mt-2 text-xs font-bold">
              <span class="flex items-center gap-1 text-primary"><span class="w-1.5 h-1.5 rounded-full bg-primary inline-block"></span>{{ stats.success_requests }} OK</span>
              <span class="flex items-center gap-1 text-error"><span class="w-1.5 h-1.5 rounded-full bg-error inline-block"></span>{{ stats.error_requests }} lỗi</span>
            </div>
          </div>
        </section>

        <!-- ═══ PROVIDER SWITCHER + SETTINGS ═══ -->
        <section class="grid grid-cols-12 gap-6">

          <!-- Provider Selector (Left) -->
          <div class="col-span-12 lg:col-span-8 bg-surface-lowest rounded-2xl border border-surface-dim shadow-sm overflow-hidden">
            <div class="p-6 border-b border-surface-dim/30">
              <h3 class="text-lg font-bold font-headline text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">swap_horiz</span>
                Chuyển đổi Nhà cung cấp AI
              </h3>
              <p class="text-xs text-on-surface-variant mt-1">Chọn nhà cung cấp và model phù hợp với nhu cầu của bạn</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div
                v-for="provider in providers"
                :key="provider.id"
                @click="selectProvider(provider)"
                class="relative p-5 rounded-2xl border-2 cursor-pointer transition-all duration-300 group/card"
                :class="settings.provider === provider.id ? 'border-primary bg-primary/5 shadow-md' : 'border-surface-dim/40 bg-surface-low hover:border-surface-dim hover:shadow-sm'"
              >
                <!-- Active badge -->
                <div v-if="settings.provider === provider.id" class="absolute -top-2 -right-2 w-6 h-6 cta-gradient rounded-full flex items-center justify-center shadow-md">
                  <span class="material-symbols-outlined text-white text-[14px]">check</span>
                </div>

                <div class="flex items-center gap-3 mb-4">
                  <div class="w-11 h-11 rounded-xl flex items-center justify-center shadow-inner" :style="{ backgroundColor: provider.color + '15' }">
                    <span class="material-symbols-outlined text-[22px]" :style="{ color: provider.color }">{{ provider.icon }}</span>
                  </div>
                  <div>
                    <p class="font-bold text-sm text-on-surface">{{ provider.name }}</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-outline">{{ provider.models.length }} models</p>
                  </div>
                </div>

                <!-- Model selector (only for active provider) -->
                <div v-if="settings.provider === provider.id" class="space-y-1.5 mt-4 pt-4 border-t border-surface-dim/30">
                  <button
                    v-for="model in provider.models"
                    :key="model.id"
                    @click.stop="selectModel(model.id)"
                    class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between transition-all"
                    :class="settings.model === model.id ? 'bg-primary text-white shadow-sm' : 'bg-surface-lowest hover:bg-surface-high text-on-surface-variant border border-surface-dim/20'"
                  >
                    <span>{{ model.name }}</span>
                    <span
                      class="px-1.5 py-0.5 rounded text-[9px] uppercase tracking-wider"
                      :class="tierBadge(model.tier, settings.model === model.id)"
                    >{{ tierLabel(model.tier) }}</span>
                  </button>
                </div>

                <!-- Collapsed model list (inactive providers) -->
                <div v-else class="flex flex-wrap gap-1 mt-3">
                  <span v-for="model in provider.models.slice(0, 3)" :key="model.id" class="px-2 py-0.5 bg-surface-lowest rounded text-[10px] font-bold text-outline border border-surface-dim/20">
                    {{ model.name.split(' ').pop() }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Settings Panel (Right) -->
          <div class="col-span-12 lg:col-span-4 space-y-5">

            <!-- API Key Card -->
            <div class="bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm">
              <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">key</span>
                API Key
              </h4>
              <div class="flex items-center gap-3 mb-4">
                <div class="flex-1 px-4 py-2.5 bg-surface-low rounded-lg border border-surface-dim/30 text-sm font-mono truncate" :class="settings.has_api_key ? 'text-on-surface' : 'text-outline italic'">
                  {{ settings.has_api_key ? '••••••••••••••••' : 'Chưa cấu hình' }}
                </div>
                <span class="material-symbols-outlined text-[18px]" :class="settings.has_api_key ? 'text-primary' : 'text-error'">
                  {{ settings.has_api_key ? 'verified' : 'warning' }}
                </span>
              </div>
              <form class="flex gap-2" @submit.prevent="saveApiKey">
                <input v-model="apiKeyInput" type="password" placeholder="Nhập API Key mới..." class="flex-1 px-3 py-2 rounded-lg border border-surface-dim/30 bg-surface-low text-xs outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                <button type="submit" :disabled="!apiKeyInput.trim()" class="px-4 py-2 cta-gradient text-white text-xs font-bold rounded-lg shadow-sm hover:shadow-md transition-all active:scale-95 disabled:opacity-50">Lưu</button>
              </form>
            </div>

            <!-- Quota Settings -->
            <div class="bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm">
              <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">tune</span>
                Cấu hình Quota
              </h4>
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-bold text-on-surface mb-1.5">Token hàng tháng</label>
                  <input v-model.number="quotaInput" type="number" min="1000" class="w-full px-3 py-2.5 rounded-lg border border-surface-dim/30 bg-surface-low text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                </div>
                <div>
                  <label class="block text-xs font-bold text-on-surface mb-1.5">Rate limit (req/phút)</label>
                  <input v-model.number="rateLimitInput" type="number" min="1" max="1000" class="w-full px-3 py-2.5 rounded-lg border border-surface-dim/30 bg-surface-low text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                </div>
                <div class="flex gap-2 pt-2">
                  <button @click="saveQuotaSettings" class="flex-1 px-4 py-2.5 cta-gradient text-white text-xs font-bold rounded-lg shadow-sm hover:shadow-md transition-all active:scale-95">
                    Lưu cấu hình
                  </button>
                  <button @click="resetQuota" class="px-4 py-2.5 bg-error-container/20 text-error border border-error/20 text-xs font-bold rounded-lg hover:bg-error-container/40 transition-all active:scale-95">
                    Reset Quota
                  </button>
                </div>
              </div>
            </div>

            <!-- Career Advisor Stats Mini -->
            <div class="bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm">
              <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">work</span>
                Career Advisor
              </h4>
              <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-3 bg-surface-low rounded-xl">
                  <p class="text-2xl font-bold font-headline text-on-surface">{{ stats.total_cvs }}</p>
                  <p class="text-[10px] font-bold uppercase tracking-widest text-outline mt-0.5">CV đã tải</p>
                </div>
                <div class="text-center p-3 bg-surface-low rounded-xl">
                  <p class="text-2xl font-bold font-headline text-on-surface">{{ stats.total_recommendations }}</p>
                  <p class="text-[10px] font-bold uppercase tracking-widest text-outline mt-0.5">Đề xuất</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ═══ USAGE CHART + RECENT LOGS ═══ -->
        <section class="grid grid-cols-12 gap-6">

          <!-- Usage Chart -->
          <div class="col-span-12 lg:col-span-7 bg-surface-lowest rounded-2xl border border-surface-dim shadow-sm overflow-hidden">
            <div class="p-6 border-b border-surface-dim/30 flex justify-between items-center">
              <div>
                <h3 class="text-lg font-bold font-headline text-on-surface">Lượng request 14 ngày</h3>
                <p class="text-xs text-on-surface-variant mt-0.5">Xu hướng sử dụng AI theo ngày</p>
              </div>
            </div>
            <div class="p-6">
              <div v-if="dailyRequests.length === 0" class="py-16 text-center text-outline">
                <span class="material-symbols-outlined text-4xl mb-2">bar_chart</span>
                <p class="text-sm font-medium">Chưa có dữ liệu request</p>
              </div>
              <div v-else class="flex items-end gap-2 h-48">
                <div v-for="(day, i) in dailyRequests" :key="day.date" class="flex-1 flex flex-col items-center group relative">
                  <div
                    class="w-full rounded-t-lg transition-all duration-300 cursor-crosshair"
                    :class="i === dailyRequests.length - 1 ? 'cta-gradient shadow-sm' : 'bg-primary/20 hover:bg-primary/40'"
                    :style="{ height: Math.max(8, calcBarHeight(day.count, maxDailyCount)) + '%' }"
                  ></div>
                  <span class="text-[9px] font-bold text-outline mt-1.5 tabular-nums">{{ day.date.slice(5) }}</span>
                  <!-- Tooltip -->
                  <div class="absolute -top-10 bg-on-surface text-white text-[10px] font-bold px-2.5 py-1 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 shadow-md">
                    {{ day.count }} reqs · {{ formatNumber(day.tokens) }} tokens
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Endpoint / Provider Breakdown -->
          <div class="col-span-12 lg:col-span-5 space-y-5">

            <!-- By Endpoint -->
            <div class="bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm">
              <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-4">Phân bổ theo Endpoint</h4>
              <div v-if="byEndpoint.length === 0" class="py-6 text-center text-outline text-xs">Chưa có dữ liệu</div>
              <div v-else class="space-y-3">
                <div v-for="ep in byEndpoint" :key="ep.endpoint" class="flex items-center gap-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-center mb-1">
                      <span class="text-xs font-bold text-on-surface font-mono truncate">{{ ep.endpoint }}</span>
                      <span class="text-[10px] font-bold text-outline tabular-nums">{{ ep.count }}</span>
                    </div>
                    <div class="h-1.5 w-full bg-surface-high rounded-full overflow-hidden">
                      <div class="h-full cta-gradient rounded-full transition-all" :style="{ width: stats.total_requests > 0 ? (ep.count / stats.total_requests * 100) + '%' : '0%' }"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- By Provider -->
            <div class="bg-surface-lowest p-6 rounded-2xl border border-surface-dim shadow-sm">
              <h4 class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-4">Phân bổ theo Provider</h4>
              <div v-if="byProvider.length === 0" class="py-6 text-center text-outline text-xs">Chưa có dữ liệu</div>
              <div v-else class="space-y-3">
                <div v-for="prov in byProvider" :key="prov.provider" class="flex items-center gap-3">
                  <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-center mb-1">
                      <span class="text-xs font-bold text-on-surface capitalize">{{ prov.provider }}</span>
                      <span class="text-[10px] font-bold text-outline tabular-nums">{{ formatNumber(prov.tokens) }} tokens</span>
                    </div>
                    <div class="h-1.5 w-full bg-surface-high rounded-full overflow-hidden">
                      <div class="h-full bg-secondary rounded-full transition-all" :style="{ width: stats.total_requests > 0 ? (prov.count / stats.total_requests * 100) + '%' : '0%' }"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ═══ RECENT REQUEST LOGS TABLE ═══ -->
        <section class="bg-surface-lowest rounded-2xl border border-surface-dim shadow-sm overflow-hidden">
          <div class="px-6 py-5 border-b border-surface-dim/30 flex justify-between items-center">
            <h3 class="text-lg font-bold font-headline text-on-surface flex items-center gap-2">
              <span class="material-symbols-outlined text-primary">history</span>
              Nhật ký Request gần đây
            </h3>
            <span class="bg-surface-low px-3 py-1 rounded-lg text-[10px] font-bold text-outline uppercase tracking-widest">{{ recentLogs.length }} bản ghi</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] table-fixed text-left border-collapse">
              <thead>
                <tr class="bg-surface-low border-b border-surface-dim/30 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                  <th class="px-6 py-3 w-[180px]">Người dùng</th>
                  <th class="px-6 py-3 w-[140px]">Endpoint</th>
                  <th class="px-6 py-3 w-[120px]">Provider / Model</th>
                  <th class="px-6 py-3 w-[80px] text-right">Token</th>
                  <th class="px-6 py-3 w-[80px] text-right">Latency</th>
                  <th class="px-6 py-3 w-[80px] text-center">Trạng thái</th>
                  <th class="px-6 py-3 w-[120px] text-right">Thời gian</th>
                </tr>
              </thead>
              <tbody v-if="recentLogs.length === 0">
                <tr>
                  <td colspan="7" class="px-6 py-16 text-center text-outline">
                    <span class="material-symbols-outlined text-4xl mb-2">receipt_long</span>
                    <p class="text-sm font-medium">Chưa có request nào được ghi nhận</p>
                  </td>
                </tr>
              </tbody>
              <tbody v-else class="divide-y divide-surface-dim/20 text-sm">
                <tr v-for="log in recentLogs" :key="log.id" class="hover:bg-surface-low/50 transition-colors">
                  <td class="px-6 py-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                      <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold shrink-0">
                        {{ log.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                      </div>
                      <span class="truncate text-xs font-bold text-on-surface">{{ log.user?.name || 'Hệ thống' }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-3">
                    <span class="font-mono text-xs font-bold text-on-surface-variant bg-surface-low px-2 py-0.5 rounded">{{ log.endpoint }}</span>
                  </td>
                  <td class="px-6 py-3">
                    <p class="text-xs font-bold text-on-surface capitalize">{{ log.provider }}</p>
                    <p class="text-[10px] text-outline truncate">{{ log.model }}</p>
                  </td>
                  <td class="px-6 py-3 text-right text-xs font-bold text-on-surface tabular-nums">{{ formatNumber(log.tokens_used) }}</td>
                  <td class="px-6 py-3 text-right text-xs font-bold tabular-nums" :class="log.response_time_ms > 3000 ? 'text-error' : 'text-on-surface'">{{ log.response_time_ms }}ms</td>
                  <td class="px-6 py-3 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                      :class="log.status === 'success' ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-error-container/20 text-error border border-error/20'">
                      {{ log.status === 'success' ? 'OK' : 'LỖI' }}
                    </span>
                  </td>
                  <td class="px-6 py-3 text-right text-[10px] font-bold text-outline tabular-nums">{{ formatDate(log.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

      </template>

      <!-- ═══ TOAST NOTIFICATION ═══ -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition-all duration-300"
          enter-from-class="opacity-0 translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition-all duration-200"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-4"
        >
          <div v-if="toast.show" class="fixed bottom-6 right-6 z-50 max-w-sm">
            <div class="flex items-center gap-3 px-5 py-3 rounded-xl shadow-2xl border" :class="toast.type === 'success' ? 'bg-primary text-white border-primary/50' : 'bg-error text-white border-error/50'">
              <span class="material-symbols-outlined text-[18px]">{{ toast.type === 'success' ? 'check_circle' : 'error' }}</span>
              <p class="text-sm font-bold">{{ toast.message }}</p>
            </div>
          </div>
        </Transition>
      </Teleport>

    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const loading = ref(true)

// ── Data ──
const settings = reactive<any>({
  provider: 'chatgpt',
  model: 'gpt-4o-mini',
  is_active: true,
  has_api_key: false,
  monthly_token_quota: 1000000,
  tokens_used: 0,
  max_requests_per_minute: 60,
})

const stats = reactive<any>({
  total_requests: 0,
  success_requests: 0,
  error_requests: 0,
  unique_users: 0,
  total_tokens: 0,
  avg_response_time: 0,
  total_cvs: 0,
  total_recommendations: 0,
})

const providers = ref<any[]>([])
const byEndpoint = ref<any[]>([])
const byProvider = ref<any[]>([])
const dailyRequests = ref<any[]>([])
const recentLogs = ref<any[]>([])

// ── Form state ──
const apiKeyInput = ref('')
const quotaInput = ref(1000000)
const rateLimitInput = ref(60)

// ── Toast ──
const toast = reactive({ show: false, message: '', type: 'success' as 'success' | 'error' })
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showToast(message: string, type: 'success' | 'error' = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.message = message
  toast.type = type
  toast.show = true
  toastTimer = setTimeout(() => { toast.show = false }, 3000)
}

// ── Computed ──
const usagePercent = computed(() => {
  if (settings.monthly_token_quota <= 0) return 0
  return Math.round((settings.tokens_used / settings.monthly_token_quota) * 100 * 10) / 10
})

const maxDailyCount = computed(() => Math.max(...dailyRequests.value.map((d: any) => d.count), 1))

const activeProviderData = computed(() => providers.value.find((p: any) => p.id === settings.provider))
const activeProviderColor = computed(() => activeProviderData.value?.color || '#6366f1')
const activeProviderIcon = computed(() => activeProviderData.value?.icon || 'smart_toy')
const providerDisplayName = computed(() => activeProviderData.value?.name || settings.provider)

// ── Helpers ──
const formatNumber = (n: number) => n ? new Intl.NumberFormat('vi-VN').format(n) : '0'
const formatDate = (d: string) => !d ? 'N/A' : new Date(d).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
const calcBarHeight = (value: number, maxValue: number) => Math.max(0, Math.round(100 * (maxValue > 0 ? value / maxValue : 0)))

function tierBadge(tier: string, isActive: boolean) {
  if (isActive) return 'bg-white/20 text-white'
  const map: Record<string, string> = {
    premium: 'bg-amber-500/10 text-amber-600 border border-amber-500/20',
    standard: 'bg-primary/10 text-primary border border-primary/20',
    economy: 'bg-surface-high text-outline border border-surface-dim/30',
  }
  return map[tier] || map.standard
}

function tierLabel(tier: string) {
  return { premium: 'Pro', standard: 'Std', economy: 'Eco' }[tier] || tier
}

// ── API calls ──
const headers = () => ({ Authorization: `Bearer ${auth.token}` })

async function fetchDashboard() {
  loading.value = true
  try {
    const [dashRes, provRes] = await Promise.all([
      $fetch<any>('/api/admin/ai/dashboard', { headers: headers() }).catch(() => null),
      $fetch<any>('/api/admin/ai/providers', { headers: headers() }).catch(() => null),
    ])

    if (dashRes) {
      Object.assign(settings, dashRes.settings || {})
      Object.assign(stats, dashRes.stats || {})
      byEndpoint.value = dashRes.by_endpoint || []
      byProvider.value = dashRes.by_provider || []
      dailyRequests.value = dashRes.daily_requests || []
      recentLogs.value = dashRes.recent_logs || []
      quotaInput.value = settings.monthly_token_quota
      rateLimitInput.value = settings.max_requests_per_minute
    }

    if (provRes) {
      providers.value = provRes.providers || []
    }
  } finally {
    loading.value = false
  }
}

async function updateSettings(payload: Record<string, any>) {
  try {
    const res = await $fetch<any>('/api/admin/ai/settings', { method: 'PUT', body: payload, headers: headers() })
    if (res?.settings) Object.assign(settings, res.settings)
    showToast(res?.message || 'Đã cập nhật!')
  } catch (e: any) {
    showToast(e?.data?.message || 'Lỗi cập nhật cấu hình', 'error')
  }
}

function selectProvider(provider: any) {
  const defaultModel = provider.models?.[0]?.id || settings.model
  updateSettings({ provider: provider.id, model: defaultModel })
}

function selectModel(modelId: string) {
  updateSettings({ model: modelId })
}

function toggleActive() {
  updateSettings({ is_active: !settings.is_active })
}

function saveApiKey() {
  if (!apiKeyInput.value.trim()) return
  updateSettings({ api_key: apiKeyInput.value.trim() })
  apiKeyInput.value = ''
}

function saveQuotaSettings() {
  updateSettings({
    monthly_token_quota: quotaInput.value,
    max_requests_per_minute: rateLimitInput.value,
  })
}

async function resetQuota() {
  if (!confirm('Xác nhận reset bộ đếm token? Hành động này không thể hoàn tác.')) return
  try {
    const res = await $fetch<any>('/api/admin/ai/reset-quota', { method: 'POST', headers: headers() })
    if (res?.settings) Object.assign(settings, res.settings)
    showToast('Token quota đã được reset!')
  } catch (e: any) {
    showToast('Lỗi reset quota', 'error')
  }
}

onMounted(fetchDashboard)
</script>

<style scoped>
.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
