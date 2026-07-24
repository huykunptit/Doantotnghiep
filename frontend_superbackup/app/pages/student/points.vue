<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const toast = useToast()
function h() { return { Authorization: `Bearer ${auth.token}` } }

const activeTab = ref<'shop' | 'history' | 'leaderboard'>('shop')

// ── Data ─────────────────────────────────────────────────────────────────────
const summary = ref<any>(null)
const vouchers = ref<any[]>([])
const myVouchers = ref<any[]>([])
const transactions = ref<any[]>([])
const leaderboard = ref<any>(null)
const loading = ref(true)
const redeemingId = ref<number | null>(null)

// Daily login
const claimLoading = ref(false)
const claimedToday = ref(false)

onMounted(async () => {
  loading.value = true
  const [r0, r1, r2, r3, r4] = await Promise.allSettled([
    useApi<any>('/points/summary', { headers: h() }),
    useApi<any[]>('/vouchers', { headers: h() }),
    useApi<any[]>('/me/vouchers', { headers: h() }),
    useApi<any>('/points/transactions', { headers: h() }),
    useApi<any>('/points/leaderboard', { headers: h() }),
  ])
  if (r0.status === 'fulfilled') {
    summary.value = r0.value
    claimedToday.value = r0.value?.last_login_date === new Date().toISOString().slice(0, 10)
  }
  if (r1.status === 'fulfilled') vouchers.value = Array.isArray(r1.value) ? r1.value : []
  if (r2.status === 'fulfilled') myVouchers.value = Array.isArray(r2.value) ? r2.value : []
  if (r3.status === 'fulfilled') transactions.value = r3.value?.data || []
  if (r4.status === 'fulfilled') leaderboard.value = r4.value
  loading.value = false
})

async function claimDaily() {
  if (claimedToday.value || claimLoading.value) return
  claimLoading.value = true
  try {
    const res = await useApi<any>('/points/daily-login', { method: 'POST', headers: h() })
    if (res.rewarded) {
      claimedToday.value = true
      if (summary.value) summary.value.balance = res.balance
      toast.success(res.message)
    } else {
      toast.info(res.message)
    }
  } catch {
    toast.error('Không thể nhận điểm.')
  } finally { claimLoading.value = false }
}

async function redeem(voucher: any) {
  if (redeemingId.value) return
  redeemingId.value = voucher.id
  try {
    const res = await useApi<any>(`/vouchers/${voucher.id}/redeem`, { method: 'POST', headers: h() })
    toast.success(res.message)
    if (summary.value) summary.value.balance = res.balance
    myVouchers.value.unshift(res.user_voucher)
    // Refresh shop to update counts
    const fresh = await useApi<any[]>('/vouchers', { headers: h() })
    vouchers.value = Array.isArray(fresh) ? fresh : []
  } catch (e: any) {
    toast.error(e?.data?.message || 'Đổi thất bại.')
  } finally { redeemingId.value = null }
}

const txActionLabel: Record<string, string> = {
  login_daily: 'Đăng nhập hàng ngày',
  streak_7: 'Chuỗi 7 ngày',
  streak_30: 'Chuỗi 30 ngày',
  lesson_complete: 'Hoàn thành bài học',
  course_complete: 'Hoàn thành khóa học',
  exam_high_score: 'Điểm cao kỳ thi',
  purchase: 'Mua khóa học',
  survey: 'Khảo sát',
  review_course: 'Đánh giá khóa học',
  redeem_voucher: 'Đổi voucher',
}

function voucherTypeLabel(type: string) {
  const m: Record<string, string> = {
    discount_percent: 'Giảm %',
    discount_fixed: 'Giảm tiền',
    free_course: 'Khóa học miễn phí',
    physical_gift: 'Quà tặng',
    ai_quota: 'AI quota',
  }
  return m[type] || type
}

function statusLabel(s: string) {
  return s === 'unused' ? 'Chưa dùng' : s === 'used' ? 'Đã dùng' : 'Hết hạn'
}

function relTime(d: string) {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  return new Date(d).toLocaleDateString('vi-VN')
}

const myRank = computed(() => leaderboard.value?.my_rank ?? '—')
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Hero KPI strip -->
    <div class="bg-gradient-to-br from-[#064e3b] to-[#0F6E8C] rounded-3xl p-6 text-white shadow-md flex flex-col md:flex-row items-center gap-6 justify-between">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
        </div>
        <div class="flex flex-col gap-0.5">
          <p class="text-[10px] font-bold text-white/70 uppercase tracking-widest">Điểm tích lũy</p>
          <strong class="text-3xl font-black tracking-tight leading-none">{{ summary?.balance?.toLocaleString('vi-VN') ?? '—' }}</strong>
        </div>
      </div>
      
      <div class="flex gap-6 items-center">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-lg text-amber-300">bolt</span>
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-extrabold leading-none">{{ summary?.streak_days ?? 0 }}</span>
            <span class="text-[9px] text-white/60 font-semibold uppercase tracking-wider">Ngày Streak</span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-lg text-amber-300">trophy</span>
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-extrabold leading-none">#{{ myRank }}</span>
            <span class="text-[9px] text-white/60 font-semibold uppercase tracking-wider">Xếp hạng</span>
          </div>
        </div>
      </div>

      <button
        class="h-10 px-5 rounded-xl text-xs font-bold transition-all inline-flex items-center justify-center gap-2 border border-white/20"
        :class="claimedToday ? 'bg-white/10 text-white/50 cursor-not-allowed' : 'bg-white text-emerald-800 hover:bg-white/95'"
        :disabled="claimedToday || claimLoading"
        @click="claimDaily"
      >
        <span class="material-symbols-outlined text-base">calendar_today</span>
        {{ claimedToday ? 'Đã nhận hôm nay' : claimLoading ? 'Đang nhận...' : 'Nhận điểm hàng ngày (+5)' }}
      </button>
    </div>

    <!-- Tab nav -->
    <div class="flex flex-wrap gap-2 border-b border-[var(--line)] pb-px">
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'shop' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'shop'"
      >
        <span class="material-symbols-outlined text-sm">shopping_bag</span> Cửa hàng đổi quà
      </button>
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'history' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'history'"
      >
        <span class="material-symbols-outlined text-sm">history</span> Lịch sử điểm
      </button>
      <button 
        class="h-9 px-4 text-xs font-bold border-b-2 transition-all inline-flex items-center gap-1.5" 
        :class="activeTab === 'leaderboard' ? 'border-[#1d9e75] text-[#1d9e75]' : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'" 
        @click="activeTab = 'leaderboard'"
      >
        <span class="material-symbols-outlined text-sm">leaderboard</span> Bảng xếp hạng
      </button>
    </div>

    <!-- ── SHOP ── -->
    <div v-if="activeTab === 'shop'" class="flex flex-col gap-6">
      <!-- My vouchers -->
      <div v-if="myVouchers.length" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
        <h3 class="text-xs font-bold text-[var(--text)] inline-flex items-center gap-1.5 border-b border-[var(--line)] pb-3">
          <span class="material-symbols-outlined text-base">local_activity</span> Voucher của tôi
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="uv in myVouchers" :key="uv.id" class="flex justify-between items-center p-4 rounded-xl border border-[var(--line)] bg-[var(--surface)] transition-all" :class="{ 'opacity-60': uv.status === 'used' }">
            <div class="flex flex-col gap-1 min-w-0">
              <p class="text-xs font-bold text-[var(--text)] truncate">{{ uv.voucher?.name }}</p>
              <p class="font-mono text-[9px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 self-start">{{ uv.code }}</p>
            </div>
            <div class="flex flex-col items-end gap-1.5">
              <span class="px-2 py-0.5 rounded text-[8px] font-bold border" :class="uv.status === 'unused' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-400 border-slate-200'">{{ statusLabel(uv.status) }}</span>
              <small class="text-[8px] text-[var(--muted)] font-semibold">Hạn: {{ uv.expires_at ? new Date(uv.expires_at).toLocaleDateString('vi-VN') : '∞' }}</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Shop listing -->
      <div class="flex flex-col gap-4">
        <h3 class="text-xs font-bold text-[var(--text)] inline-flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base">local_mall</span> Đổi điểm lấy quà
        </h3>
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-pulse">
          <div v-for="i in 3" :key="i" class="h-44 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl" />
        </div>
        <div v-else-if="vouchers.length === 0" class="flex flex-col items-center gap-3 text-center py-10">
          <span class="material-symbols-outlined text-3xl text-[var(--muted)] opacity-60">local_mall</span>
          <p class="text-xs font-semibold text-[var(--muted)]">Chưa có phần thưởng nào.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="v in vouchers" :key="v.id" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col gap-3 relative">
            <div class="flex justify-between items-center">
              <div class="px-2 py-0.5 rounded text-[8px] font-bold border uppercase tracking-wider" :class="v.type === 'free_course' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-sky-50 text-sky-700 border-sky-100'">
                {{ voucherTypeLabel(v.type) }}
              </div>
              <span v-if="v.total_quantity" class="text-[9px] font-semibold text-[var(--muted)]">{{ v.total_quantity - v.redeemed_count }} còn lại</span>
            </div>
            <h4 class="text-xs font-bold text-[var(--text)] leading-snug">{{ v.name }}</h4>
            <p class="text-[10px] text-[var(--muted)] leading-relaxed flex-1">{{ v.description }}</p>
            
            <div v-if="v.discount_value" class="text-lg font-black text-[#1d9e75]">
              {{ v.type === 'discount_percent' ? v.discount_value + '%' : v.discount_value?.toLocaleString('vi-VN') + 'đ' }} giảm
            </div>
            
            <div class="flex justify-between items-center border-t border-[var(--line)] pt-3 mt-1">
              <div class="flex items-center gap-1 text-[10px] font-bold text-[var(--muted)]">
                <span class="material-symbols-outlined text-sm">account_balance_wallet</span>
                <span><strong class="text-xs text-[var(--text)] font-extrabold">{{ v.points_cost.toLocaleString('vi-VN') }}</strong> điểm</span>
              </div>
              <button
                class="h-8 px-4 rounded-lg font-bold text-[10px] flex items-center justify-center transition-colors"
                :disabled="!!redeemingId || (summary?.balance ?? 0) < v.points_cost"
                :class="(summary?.balance ?? 0) < v.points_cost ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-[#1d9e75] hover:bg-[#157959] text-white'"
                @click="redeem(v)"
              >
                {{ redeemingId === v.id ? 'Đang đổi...' : 'Đổi ngay' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── HISTORY ── -->
    <div v-if="activeTab === 'history'" class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
      <h3 class="text-xs font-bold text-[var(--text)] inline-flex items-center gap-1.5 border-b border-[var(--line)] pb-3">
        <span class="material-symbols-outlined text-base">history</span> Lịch sử giao dịch
      </h3>
      <div class="flex flex-col gap-3">
        <div v-if="transactions.length === 0" class="text-center text-xs text-[var(--muted)] py-6">Chưa có giao dịch nào.</div>
        <div v-else v-for="tx in transactions" :key="tx.id" class="flex items-center justify-between p-3 rounded-xl border border-[var(--line)] bg-[var(--surface-strong)] transition-all">
          <div class="flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="tx.type === 'earn' ? 'bg-emerald-500' : 'bg-amber-500'" />
            <div class="flex flex-col gap-0.5">
              <p class="text-xs font-bold text-[var(--text)]">{{ tx.description || txActionLabel[tx.action] || tx.action }}</p>
              <span class="text-[9px] text-[var(--muted)] font-semibold">{{ relTime(tx.created_at) }}</span>
            </div>
          </div>
          <span class="text-xs font-bold" :class="tx.type === 'earn' ? 'text-emerald-600' : 'text-amber-500'">
            {{ tx.type === 'earn' ? '+' : '' }}{{ tx.amount.toLocaleString('vi-VN') }}
          </span>
        </div>
      </div>
    </div>

    <!-- ── LEADERBOARD ── -->
    <div v-if="activeTab === 'leaderboard'" class="flex flex-col gap-4">
      <div v-if="leaderboard?.my_rank" class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-4 flex items-center gap-3 text-xs font-semibold">
        <span class="material-symbols-outlined text-lg text-amber-500">trophy</span>
        <span>Xếp hạng của bạn: <strong class="font-bold text-amber-700">#{{ leaderboard.my_rank }}</strong> &mdash; {{ leaderboard.my_balance?.toLocaleString('vi-VN') }} điểm</span>
      </div>
      <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
        <h3 class="text-xs font-bold text-[var(--text)] inline-flex items-center gap-1.5 border-b border-[var(--line)] pb-3">
          <span class="material-symbols-outlined text-base">star</span> Bảng xếp hạng điểm
        </h3>
        <div class="flex flex-col gap-2.5">
          <div
            v-for="(u, i) in leaderboard?.top ?? []"
            :key="u.id"
            class="flex items-center justify-between p-3 rounded-xl border transition-all"
            :class="[
              u.id === auth.user?.id ? 'bg-emerald-50 border-emerald-200' : 'border-[var(--line)]',
              i < 3 ? 'bg-amber-50/50 border-amber-200/50' : '',
            ]"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-8 text-center flex-shrink-0">
                <span v-if="i === 0" class="text-lg">🥇</span>
                <span v-else-if="i === 1" class="text-lg">🥈</span>
                <span v-else-if="i === 2" class="text-lg">🥉</span>
                <span v-else class="text-xs font-bold text-[var(--muted)]">{{ i + 1 }}</span>
              </div>
              
              <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#0F6E8C] to-[#1D9E75] text-white font-black text-xs flex items-center justify-center overflow-hidden flex-shrink-0">
                <img v-if="u.avatar" :src="u.avatar" :alt="u.name" class="w-full h-full object-cover">
                <span v-else>{{ u.name?.slice(0,2).toUpperCase() }}</span>
              </div>
              
              <div class="flex flex-col gap-0.5 min-w-0">
                <p class="text-xs font-bold text-[var(--text)] truncate">{{ u.name }}</p>
                <span class="text-[9px] text-[var(--muted)] font-semibold">{{ u.student_code }}</span>
              </div>
            </div>
            
            <div class="flex items-center gap-4 flex-shrink-0">
              <div class="flex items-center gap-0.5 text-xs font-bold text-amber-600">
                <span class="material-symbols-outlined text-sm">bolt</span>
                <span>{{ u.streak_days }}d</span>
              </div>
              <div class="flex items-center gap-1 text-xs text-[var(--muted)]">
                <span class="material-symbols-outlined text-sm">account_balance_wallet</span>
                <span><strong class="text-[var(--text)] font-extrabold">{{ u.points_balance?.toLocaleString('vi-VN') }}</strong></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
