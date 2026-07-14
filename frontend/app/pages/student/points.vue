<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
// Icons removed - using PrimeIcons
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

const iconMap: Record<string, any> = {
  'calendar-check': CalendarCheck,
  'flame': Flame,
  'trophy': Trophy,
  'book-open-check': BookOpen,
  'graduation-cap': GraduationCap,
  'medal': Medal,
  'shopping-bag': ShoppingBag,
  'clipboard-list': ClipboardList,
  'star': Star,
}
function getIcon(key: string) { return iconMap[key] || Star }

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
  <div class="pts-page">
    <!-- Hero KPI strip -->
    <div class="pts-hero">
      <div class="pts-hero-main">
        <div class="pts-coin-icon"><i class="pi pi-money-bill" style="font-size:1.75rem" /></div>
        <div>
          <p class="pts-hero-label">Điểm tích lũy</p>
          <strong class="pts-hero-val">{{ summary?.balance?.toLocaleString('vi-VN') ?? '—' }}</strong>
        </div>
      </div>
      <div class="pts-hero-stats">
        <div class="pts-stat">
          <i class="pi pi-bolt" style="font-size:1.125rem" />
          <div><strong>{{ summary?.streak_days ?? 0 }}</strong><span>ngày streak</span></div>
        </div>
        <div class="pts-stat">
          <i class="pi pi-trophy" style="font-size:1.125rem" />
          <div><strong>#{{ myRank }}</strong><span>xếp hạng</span></div>
        </div>
      </div>
      <button
        class="pts-claim-btn"
        :class="{ 'is-claimed': claimedToday }"
        :disabled="claimedToday || claimLoading"
        @click="claimDaily"
      >
        <i class="pi pi-calendar" style="font-size:1.0rem" />
        {{ claimedToday ? 'Đã nhận hôm nay' : claimLoading ? 'Đang nhận...' : 'Nhận điểm hàng ngày (+5)' }}
      </button>
    </div>

    <!-- Tab nav -->
    <div class="pts-tabs">
      <button class="pts-tab" :class="{ active: activeTab === 'shop' }" @click="activeTab = 'shop'">
        <Gift :size="15" /> Cửa hàng đổi quà
      </button>
      <button class="pts-tab" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
        <History :size="15" /> Lịch sử điểm
      </button>
      <button class="pts-tab" :class="{ active: activeTab === 'leaderboard' }" @click="activeTab = 'leaderboard'">
        <BarChart3 :size="15" /> Bảng xếp hạng
      </button>
    </div>

    <!-- ── SHOP ── -->
    <div v-if="activeTab === 'shop'">
      <!-- My vouchers -->
      <div v-if="myVouchers.length" class="pts-section">
        <h3 class="pts-section-title"><Ticket :size="16" /> Voucher của tôi</h3>
        <div class="pts-myvoucher-list">
          <div v-for="uv in myVouchers" :key="uv.id" class="pts-myvoucher-item" :class="uv.status">
            <div class="pts-uv-left">
              <p class="pts-uv-name">{{ uv.voucher?.name }}</p>
              <p class="pts-uv-code">{{ uv.code }}</p>
            </div>
            <div class="pts-uv-right">
              <span class="pts-uv-status" :class="uv.status">{{ statusLabel(uv.status) }}</span>
              <small>Hết hạn: {{ uv.expires_at ? new Date(uv.expires_at).toLocaleDateString('vi-VN') : '∞' }}</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Shop listing -->
      <h3 class="pts-section-title"><Gift :size="16" /> Đổi điểm lấy quà</h3>
      <div v-if="loading" class="pts-shimmer-grid">
        <div v-for="i in 6" :key="i" class="pts-shimmer" />
      </div>
      <div v-else-if="vouchers.length === 0" class="pts-empty">
        <Gift :size="40" style="opacity:.3" />
        <p>Chưa có phần thưởng nào.</p>
      </div>
      <div v-else class="pts-shop-grid">
        <div v-for="v in vouchers" :key="v.id" class="pts-shop-card">
          <div class="pts-shop-top">
            <div class="pts-shop-badge" :class="v.type">{{ voucherTypeLabel(v.type) }}</div>
            <span v-if="v.total_quantity" class="pts-shop-qty">{{ v.total_quantity - v.redeemed_count }} còn lại</span>
          </div>
          <h4 class="pts-shop-name">{{ v.name }}</h4>
          <p class="pts-shop-desc">{{ v.description }}</p>
          <div v-if="v.discount_value" class="pts-shop-discount">
            {{ v.type === 'discount_percent' ? v.discount_value + '%' : v.discount_value?.toLocaleString('vi-VN') + 'đ' }} giảm
          </div>
          <div class="pts-shop-footer">
            <div class="pts-shop-cost">
              <i class="pi pi-money-bill" style="font-size:0.875rem" />
              <strong>{{ v.points_cost.toLocaleString('vi-VN') }}</strong> điểm
            </div>
            <button
              class="pts-redeem-btn"
              :disabled="!!redeemingId || (summary?.balance ?? 0) < v.points_cost"
              :class="{ 'cant-afford': (summary?.balance ?? 0) < v.points_cost }"
              @click="redeem(v)"
            >
              {{ redeemingId === v.id ? 'Đang đổi...' : 'Đổi ngay' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── HISTORY ── -->
    <div v-if="activeTab === 'history'">
      <h3 class="pts-section-title"><i class="pi pi-clock" style="font-size:1.0rem" /> Lịch sử giao dịch</h3>
      <div class="pts-tx-list">
        <div v-if="transactions.length === 0" class="pts-empty"><p>Chưa có giao dịch nào.</p></div>
        <div v-else v-for="tx in transactions" :key="tx.id" class="pts-tx-item">
          <div class="pts-tx-dot" :class="tx.type === 'earn' ? 'earn' : 'redeem'" />
          <div class="pts-tx-info">
            <p class="pts-tx-desc">{{ tx.description || txActionLabel[tx.action] || tx.action }}</p>
            <span class="pts-tx-time">{{ relTime(tx.created_at) }}</span>
          </div>
          <span class="pts-tx-amount" :class="tx.type === 'earn' ? 'earn' : 'redeem'">
            {{ tx.type === 'earn' ? '+' : '' }}{{ tx.amount.toLocaleString('vi-VN') }}
          </span>
        </div>
      </div>
    </div>

    <!-- ── LEADERBOARD ── -->
    <div v-if="activeTab === 'leaderboard'">
      <h3 class="pts-section-title"><i class="pi pi-trophy" style="font-size:1.0rem" /> Bảng xếp hạng điểm</h3>
      <div v-if="leaderboard?.my_rank" class="pts-my-rank-banner">
        <i class="pi pi-trophy" style="font-size:1.125rem" />
        <span>Xếp hạng của bạn: <strong>#{{ leaderboard.my_rank }}</strong> — {{ leaderboard.my_balance?.toLocaleString('vi-VN') }} điểm</span>
      </div>
      <div class="pts-lb-list">
        <div
          v-for="(u, i) in leaderboard?.top ?? []"
          :key="u.id"
          class="pts-lb-row"
          :class="{ 'is-me': u.id === auth.user?.id, 'is-top3': i < 3 }"
        >
          <div class="pts-lb-rank">
            <span v-if="i === 0" class="pts-lb-medal gold">🥇</span>
            <span v-else-if="i === 1" class="pts-lb-medal silver">🥈</span>
            <span v-else-if="i === 2" class="pts-lb-medal bronze">🥉</span>
            <span v-else class="pts-lb-num">{{ i + 1 }}</span>
          </div>
          <div class="pts-lb-avatar">
            <img v-if="u.avatar" :src="u.avatar" :alt="u.name">
            <span v-else>{{ u.name?.slice(0,2).toUpperCase() }}</span>
          </div>
          <div class="pts-lb-info">
            <p class="pts-lb-name">{{ u.name }}</p>
            <span class="pts-lb-code">{{ u.student_code }}</span>
          </div>
          <div class="pts-lb-streak"><i class="pi pi-bolt" style="font-size:0.8125rem" /> {{ u.streak_days }}d</div>
          <div class="pts-lb-pts">
            <i class="pi pi-money-bill" style="font-size:0.8125rem" />
            <strong>{{ u.points_balance?.toLocaleString('vi-VN') }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pts-page { display: flex; flex-direction: column; gap: 20px; max-width: 900px; }

/* Hero */
.pts-hero {
  display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
  padding: 20px 24px; border-radius: 16px;
  background: linear-gradient(135deg, #064e3b 0%, #0F6E8C 100%);
  color: #fff;
}
.pts-hero-main { display: flex; align-items: center; gap: 14px; flex: 1; }
.pts-coin-icon {
  width: 52px; height: 52px; border-radius: 14px;
  background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.pts-hero-label { margin: 0; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.7; }
.pts-hero-val { font-size: 2.2rem; font-weight: 900; line-height: 1; letter-spacing: -0.03em; }
.pts-hero-stats { display: flex; gap: 24px; }
.pts-stat { display: flex; align-items: center; gap: 8px; }
.pts-stat > div { display: flex; flex-direction: column; }
.pts-stat strong { font-size: 1.2rem; font-weight: 800; line-height: 1; }
.pts-stat span { font-size: 0.68rem; opacity: 0.7; }
.text-orange { color: #fb923c; }
.text-gold { color: #fbbf24; }

.pts-claim-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 18px; border-radius: 10px;
  background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
  color: #fff; font-size: 0.82rem; font-weight: 700; cursor: pointer;
  transition: all 150ms; flex-shrink: 0;
}
.pts-claim-btn:hover:not(:disabled) { background: rgba(255,255,255,0.25); }
.pts-claim-btn.is-claimed { opacity: 0.6; cursor: not-allowed; }
.pts-claim-btn:disabled { cursor: not-allowed; opacity: 0.6; }

/* Tabs */
.pts-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e2e8f0; }
.pts-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; font-size: 0.82rem; font-weight: 600;
  border: none; background: transparent; color: #64748b; cursor: pointer;
  border-bottom: 3px solid transparent; margin-bottom: -2px;
  border-radius: 6px 6px 0 0; transition: color 0.15s, border-color 0.15s;
}
.pts-tab:hover { color: #1e293b; background: #f8fafc; }
.pts-tab.active { color: #047857; border-bottom-color: #047857; background: #f0fdf4; }

/* Section title */
.pts-section-title {
  font-size: 0.9rem; font-weight: 800; color: #1e293b;
  margin: 0 0 12px; display: inline-flex; align-items: center; gap: 7px;
}
.pts-section { margin-bottom: 20px; }

/* My vouchers */
.pts-myvoucher-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 20px; }
.pts-myvoucher-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 14px; border-radius: 10px; border: 1px solid #e2e8f0;
  background: #f8fafc;
}
.pts-myvoucher-item.used { opacity: 0.55; }
.pts-uv-name { margin: 0; font-size: 0.82rem; font-weight: 700; color: #1e293b; }
.pts-uv-code { margin: 2px 0 0; font-family: monospace; font-size: 0.75rem; color: #047857; background: #ecfdf5; padding: 1px 6px; border-radius: 4px; display: inline-block; }
.pts-uv-right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.pts-uv-status { font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; }
.pts-uv-status.unused { background: #f0fdf4; color: #15803d; }
.pts-uv-status.used { background: #f1f5f9; color: #64748b; }
.pts-uv-status.expired { background: #fef2f2; color: #b91c1c; }

/* Shop grid */
.pts-shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.pts-shop-card {
  display: flex; flex-direction: column; gap: 8px;
  padding: 16px; border-radius: 14px; border: 1px solid #e2e8f0;
  background: #fff; transition: box-shadow 150ms, border-color 150ms;
}
.pts-shop-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); border-color: #cbd5e1; }
.pts-shop-top { display: flex; justify-content: space-between; align-items: center; }
.pts-shop-badge {
  font-size: 0.62rem; font-weight: 800; text-transform: uppercase; padding: 2px 7px; border-radius: 999px;
}
.pts-shop-badge.discount_percent { background: #dbeafe; color: #1d4ed8; }
.pts-shop-badge.discount_fixed { background: #ede9fe; color: #6d28d9; }
.pts-shop-badge.free_course { background: #d1fae5; color: #065f46; }
.pts-shop-badge.physical_gift { background: #fce7f3; color: #be185d; }
.pts-shop-badge.ai_quota { background: #f3f4f6; color: #374151; }
.pts-shop-qty { font-size: 0.68rem; color: #64748b; }
.pts-shop-name { margin: 0; font-size: 0.88rem; font-weight: 800; color: #1e293b; }
.pts-shop-desc { margin: 0; font-size: 0.74rem; color: #64748b; line-height: 1.4; flex: 1; }
.pts-shop-discount { font-size: 1rem; font-weight: 900; color: #047857; }
.pts-shop-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
.pts-shop-cost { display: flex; align-items: center; gap: 4px; font-size: 0.8rem; color: #475569; }
.pts-shop-cost strong { font-weight: 800; color: #1e293b; }
.pts-redeem-btn {
  padding: 6px 14px; border-radius: 8px; border: none;
  background: linear-gradient(135deg, #0F6E8C, #1D9E75);
  color: #fff; font-size: 0.78rem; font-weight: 700; cursor: pointer;
  transition: opacity 150ms;
}
.pts-redeem-btn:hover:not(:disabled) { opacity: 0.88; }
.pts-redeem-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.pts-redeem-btn.cant-afford { background: #e2e8f0; color: #94a3b8; }

/* Shimmer */
.pts-shimmer-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
.pts-shimmer {
  height: 180px; border-radius: 14px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Empty */
.pts-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px; color: #94a3b8; }
.pts-empty p { margin: 0; font-size: 0.9rem; }

/* Transactions */
.pts-tx-list { display: flex; flex-direction: column; gap: 6px; }
.pts-tx-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc;
}
.pts-tx-dot {
  width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
}
.pts-tx-dot.earn { background: #10b981; }
.pts-tx-dot.redeem { background: #f59e0b; }
.pts-tx-info { flex: 1; min-width: 0; }
.pts-tx-desc { margin: 0; font-size: 0.82rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pts-tx-time { font-size: 0.68rem; color: #94a3b8; }
.pts-tx-amount { font-size: 0.9rem; font-weight: 800; flex-shrink: 0; }
.pts-tx-amount.earn { color: #059669; }
.pts-tx-amount.redeem { color: #f59e0b; }

/* My rank banner */
.pts-my-rank-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; border-radius: 12px;
  background: #fffbeb; border: 1px solid #fde68a;
  font-size: 0.85rem; color: #92400e; margin-bottom: 14px;
}
.pts-my-rank-banner strong { font-weight: 800; }

/* Leaderboard */
.pts-lb-list { display: flex; flex-direction: column; gap: 6px; }
.pts-lb-row {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc;
  transition: background 150ms;
}
.pts-lb-row.is-me { background: #f0fdf4; border-color: #bbf7d0; }
.pts-lb-row.is-top3 { border-color: #fde68a; background: #fffbeb; }
.pts-lb-rank { width: 32px; text-align: center; flex-shrink: 0; }
.pts-lb-medal { font-size: 1.2rem; }
.pts-lb-num { font-size: 0.82rem; font-weight: 800; color: #64748b; }
.pts-lb-avatar {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, #0F6E8C, #1D9E75);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem; font-weight: 800; color: #fff; overflow: hidden;
}
.pts-lb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.pts-lb-info { flex: 1; min-width: 0; }
.pts-lb-name { margin: 0; font-size: 0.82rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pts-lb-code { font-size: 0.68rem; color: #94a3b8; }
.pts-lb-streak { display: flex; align-items: center; gap: 3px; font-size: 0.72rem; color: #ea580c; font-weight: 700; flex-shrink: 0; }
.pts-lb-pts { display: flex; align-items: center; gap: 4px; flex-shrink: 0; font-size: 0.82rem; color: #475569; }
.pts-lb-pts strong { font-weight: 800; color: #1e293b; }
</style>
