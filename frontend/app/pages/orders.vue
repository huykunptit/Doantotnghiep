<template>
  <div class="ord-page">
    <!-- ───────────── HERO ───────────── -->
    <section class="ord-hero">
      <div class="ord-hero-bg">
        <div class="ord-grid" />
        <div class="ord-glow ord-glow-1" />
        <div class="ord-glow ord-glow-2" />
      </div>
      <div class="ord-hero-inner">
        <div class="ord-hero-content">
          <div class="ord-hero-badge">
            <span class="ord-badge-dot" />
            Lịch sử giao dịch
          </div>
          <h1 class="ord-hero-title">Đơn hàng của bạn</h1>
          <p class="ord-hero-lead">
            Theo dõi các giao dịch đã thực hiện, trạng thái thanh toán và truy cập nhanh tới các khóa học đã sở hữu.
          </p>
        </div>
        <NuxtLink to="/courses" class="ord-hero-cta">
          Khám phá khoá học
          <ArrowRight :size="18" />
        </NuxtLink>
      </div>
    </section>

    <!-- ───────────── STATS ───────────── -->
    <section class="ord-stats-wrap">
      <div class="ord-stats">
        <div class="ord-stat-card">
          <div class="ord-stat-icon" style="--icon-color: var(--green)">
            <ShoppingBag :size="22" />
          </div>
          <div class="ord-stat-body">
            <span class="ord-stat-value">{{ orders.length }}</span>
            <span class="ord-stat-label">Tổng đơn</span>
          </div>
        </div>
        <div class="ord-stat-card">
          <div class="ord-stat-icon" style="--icon-color: #22c55e">
            <CheckCircle :size="22" />
          </div>
          <div class="ord-stat-body">
            <span class="ord-stat-value">{{ paidCount }}</span>
            <span class="ord-stat-label">Đã thanh toán</span>
          </div>
        </div>
        <div class="ord-stat-card">
          <div class="ord-stat-icon" style="--icon-color: #f59e0b">
            <Clock :size="22" />
          </div>
          <div class="ord-stat-body">
            <span class="ord-stat-value">{{ pendingCount }}</span>
            <span class="ord-stat-label">Đang chờ</span>
          </div>
        </div>
        <div class="ord-stat-card">
          <div class="ord-stat-icon" style="--icon-color: var(--green)">
            <ShoppingBag :size="22" />
          </div>
          <div class="ord-stat-body">
            <span class="ord-stat-value ord-stat-value--sm">{{ formatPrice(totalPaid) }}</span>
            <span class="ord-stat-label">Tổng chi tiêu</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ───────────── MAIN CONTENT ───────────── -->
    <section class="ord-content">

      <!-- Loading skeleton -->
      <template v-if="loading">
        <div class="ord-skeleton-list">
          <div v-for="n in 3" :key="n" class="ord-skeleton-card ord-pulse">
            <div class="ord-sk-thumb" />
            <div class="ord-sk-body">
              <div class="ord-sk-line ord-sk-line--wide" />
              <div class="ord-sk-line ord-sk-line--med" />
              <div class="ord-sk-line ord-sk-line--short" />
            </div>
            <div class="ord-sk-right">
              <div class="ord-sk-line ord-sk-line--med" />
              <div class="ord-sk-badge" />
            </div>
          </div>
        </div>
      </template>

      <template v-else>
        <!-- Empty state -->
        <div v-if="orders.length === 0" class="ord-empty">
          <div class="ord-empty-icon">
            <BookOpen :size="36" />
          </div>
          <h3 class="ord-empty-title">Chưa có đơn hàng nào</h3>
          <p class="ord-empty-desc">
            Bạn chưa thực hiện giao dịch nào. Hãy bắt đầu với một khoá học phù hợp hôm nay.
          </p>
          <NuxtLink to="/courses" class="ord-cta-btn">
            Khám phá khoá học
            <ArrowRight :size="16" />
          </NuxtLink>
        </div>

        <!-- Orders list -->
        <div v-else class="ord-list">
          <article v-for="order in orders" :key="order.id" class="ord-card">
            <!-- Thumbnail -->
            <div class="ord-thumb">
              <img
                v-if="order.course?.thumbnail"
                :src="order.course.thumbnail"
                :alt="order.course?.title"
                class="ord-thumb-img"
              >
              <div v-else class="ord-thumb-fallback">
                <BookOpen :size="28" />
              </div>
            </div>

            <!-- Middle info -->
            <div class="ord-card-body">
              <NuxtLink :to="`/courses/${order.course_id}`" class="ord-course-title">
                {{ order.course?.title ?? 'Khoá học đã bị gỡ bỏ' }}
              </NuxtLink>
              <p class="ord-instructor">{{ order.course?.instructor?.name || 'PTIT LMS' }}</p>
              <p class="ord-meta">#{{ order.id }} · {{ formatDate(order.created_at) }}</p>
            </div>

            <!-- Right actions -->
            <div class="ord-card-right">
              <div class="ord-price-row">
                <span class="ord-price">{{ formatPrice(order.amount) }}</span>
                <span :class="['ord-badge', badgeClass(order.status)]">
                  <CheckCircle v-if="order.status === 'paid'" :size="13" />
                  <Clock v-else-if="order.status === 'pending'" :size="13" />
                  <XCircle v-else :size="13" />
                  {{ statusLabel(order.status) }}
                </span>
              </div>
              <NuxtLink
                v-if="order.status === 'paid'"
                :to="`/learn/${order.course_id}`"
                class="ord-learn-btn"
              >
                <PlayCircle :size="15" />
                Học ngay
              </NuxtLink>
            </div>
          </article>
        </div>
      </template>

    </section>
  </div>
</template>

<script setup lang="ts">
import { ArrowRight, BookOpen, CheckCircle, Clock, PlayCircle, ShoppingBag, XCircle } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'auth' })
const courseStore = useCourseStore()
const loading = ref(true)
const orders = ref<any[]>([])

const formatPrice = (price: number) => price <= 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
const formatDate = (date?: string) => !date ? '' : new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
const statusLabel = (status: string) => ({ pending: 'Chờ thanh toán', paid: 'Đã thanh toán', failed: 'Thất bại' }[status] ?? status)
const badgeClass = (status: string) => ({ pending: 'is-warning', paid: 'is-success', failed: 'is-danger' }[status] ?? 'is-warning')
const totalPaid = computed(() => orders.value.filter(o => o.status === 'paid').reduce((sum, o) => sum + (o.amount || 0), 0))
const paidCount = computed(() => orders.value.filter(o => o.status === 'paid').length)
const pendingCount = computed(() => orders.value.filter(o => o.status === 'pending').length)

onMounted(async () => {
  loading.value = true
  orders.value = await courseStore.fetchOrders()
  loading.value = false
})
</script>

<style scoped>
/* ─── Page wrapper ─── */
.ord-page {
  min-height: 100vh;
  background: var(--bg, #f7f9f8);
  font-family: 'Be Vietnam Pro', sans-serif;
}

/* ─── Hero ─── */
.ord-hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 50%, #163d2a 100%);
  padding: 72px 24px 80px;
}

.ord-hero-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.ord-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(29, 158, 117, 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(29, 158, 117, 0.06) 1px, transparent 1px);
  background-size: 48px 48px;
}

.ord-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.35;
}

.ord-glow-1 {
  width: 520px;
  height: 520px;
  background: radial-gradient(circle, rgba(29, 158, 117, 0.45) 0%, transparent 70%);
  top: -160px;
  left: -80px;
}

.ord-glow-2 {
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(22, 61, 42, 0.6) 0%, transparent 70%);
  bottom: -120px;
  right: 80px;
}

.ord-hero-inner {
  position: relative;
  z-index: 1;
  max-width: 960px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 32px;
  flex-wrap: wrap;
}

.ord-hero-content {
  flex: 1;
  min-width: 260px;
}

.ord-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(29, 158, 117, 0.15);
  border: 1px solid rgba(29, 158, 117, 0.3);
  color: #4ade80;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 6px 14px;
  border-radius: 100px;
  margin-bottom: 20px;
}

.ord-badge-dot {
  width: 8px;
  height: 8px;
  background: #4ade80;
  border-radius: 50%;
  animation: badge-pulse 2s ease-in-out infinite;
}

@keyframes badge-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.75); }
}

.ord-hero-title {
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 800;
  color: #ffffff;
  line-height: 1.15;
  margin: 0 0 16px;
  letter-spacing: -0.02em;
}

.ord-hero-lead {
  font-size: 16px;
  color: rgba(255, 255, 255, 0.65);
  line-height: 1.65;
  max-width: 520px;
  margin: 0;
}

.ord-hero-cta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, var(--green, #1d9e75), #16a85f);
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  padding: 14px 28px;
  border-radius: 12px;
  text-decoration: none;
  white-space: nowrap;
  transition: opacity 0.2s, transform 0.2s;
  box-shadow: 0 8px 24px rgba(29, 158, 117, 0.35);
}

.ord-hero-cta:hover {
  opacity: 0.9;
  transform: translateY(-2px);
}

/* ─── Stats bar ─── */
.ord-stats-wrap {
  background: #fff;
  border-bottom: 1px solid var(--line, #e8ede9);
}

[data-theme="dark"] .ord-stats-wrap {
  background: #0f1f17;
  border-bottom-color: rgba(255,255,255,0.07);
}

.ord-stats {
  max-width: 960px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  padding: 28px 24px;
}

.ord-stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--surface-strong, #f7f9f8);
  border: 1px solid var(--line, #e8ede9);
  border-radius: 16px;
  padding: 24px 28px;
  transition: box-shadow 0.2s, transform 0.2s;
}

.ord-stat-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
  transform: translateY(-2px);
}

[data-theme="dark"] .ord-stat-card {
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(255, 255, 255, 0.07);
}

.ord-stat-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--icon-color) 12%, transparent);
  color: var(--icon-color);
  display: flex;
  align-items: center;
  justify-content: center;
}

.ord-stat-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ord-stat-value {
  font-size: 26px;
  font-weight: 800;
  color: var(--text, #0f2318);
  line-height: 1.1;
}

.ord-stat-value--sm {
  font-size: 16px;
  font-weight: 700;
}

.ord-stat-label {
  font-size: 13px;
  color: var(--muted, #6b8070);
  font-weight: 500;
}

[data-theme="dark"] .ord-stat-value { color: #fff; }
[data-theme="dark"] .ord-stat-label { color: rgba(255,255,255,0.5); }

/* ─── Content section ─── */
.ord-content {
  max-width: 960px;
  margin: 0 auto;
  padding: 40px 24px 80px;
}

/* ─── Skeleton ─── */
.ord-skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ord-skeleton-card {
  display: flex;
  align-items: center;
  gap: 20px;
  background: #fff;
  border: 1px solid var(--line, #e8ede9);
  border-radius: 16px;
  padding: 20px 24px;
}

[data-theme="dark"] .ord-skeleton-card {
  background: rgba(255,255,255,0.03);
  border-color: rgba(255,255,255,0.07);
}

.ord-sk-thumb {
  flex-shrink: 0;
  width: 96px;
  height: 96px;
  border-radius: 12px;
  background: var(--line, #e8ede9);
}

.ord-sk-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ord-sk-right {
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: flex-end;
}

.ord-sk-line {
  height: 14px;
  border-radius: 8px;
  background: var(--line, #e8ede9);
}

.ord-sk-line--wide  { width: 70%; }
.ord-sk-line--med   { width: 45%; }
.ord-sk-line--short { width: 25%; }

.ord-sk-badge {
  width: 100px;
  height: 28px;
  border-radius: 100px;
  background: var(--line, #e8ede9);
}

@keyframes pulse-anim {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

.ord-pulse { animation: pulse-anim 1.6s ease-in-out infinite; }

/* ─── Empty state ─── */
.ord-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 80px 24px;
  gap: 16px;
}

.ord-empty-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(29, 158, 117, 0.12);
  color: var(--green, #1d9e75);
  display: flex;
  align-items: center;
  justify-content: center;
}

.ord-empty-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--text, #0f2318);
  margin: 0;
}

.ord-empty-desc {
  font-size: 15px;
  color: var(--muted, #6b8070);
  max-width: 400px;
  line-height: 1.6;
  margin: 0;
}

.ord-cta-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, var(--green, #1d9e75), #16a85f);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  padding: 12px 24px;
  border-radius: 10px;
  text-decoration: none;
  margin-top: 8px;
  transition: opacity 0.2s, transform 0.2s;
}

.ord-cta-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

[data-theme="dark"] .ord-empty-title { color: #fff; }

/* ─── Orders list ─── */
.ord-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ord-card {
  display: flex;
  align-items: center;
  gap: 20px;
  background: #fff;
  border: 1px solid var(--line, #e8ede9);
  border-radius: 16px;
  padding: 20px 24px;
  transition: box-shadow 0.2s, transform 0.2s;
}

.ord-card:hover {
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
  transform: translateY(-3px);
}

[data-theme="dark"] .ord-card {
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(255, 255, 255, 0.07);
}

/* Thumbnail */
.ord-thumb {
  flex-shrink: 0;
  width: 96px;
  height: 96px;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(29, 158, 117, 0.1);
}

.ord-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.ord-thumb-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--green, #1d9e75);
}

/* Middle body */
.ord-card-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ord-course-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text, #0f2318);
  text-decoration: none;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  transition: color 0.15s;
}

.ord-course-title:hover {
  color: var(--green, #1d9e75);
}

.ord-instructor {
  font-size: 13px;
  color: var(--muted, #6b8070);
  margin: 0;
}

.ord-meta {
  font-size: 12px;
  color: var(--muted, #6b8070);
  margin: 0;
  opacity: 0.75;
}

[data-theme="dark"] .ord-course-title { color: #f0f7f4; }

/* Right column */
.ord-card-right {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12px;
  padding-left: 20px;
  border-left: 1px solid var(--line, #e8ede9);
}

[data-theme="dark"] .ord-card-right {
  border-left-color: rgba(255,255,255,0.07);
}

.ord-price-row {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.ord-price {
  font-size: 17px;
  font-weight: 800;
  color: var(--text, #0f2318);
  white-space: nowrap;
}

[data-theme="dark"] .ord-price { color: #fff; }

/* Status badge */
.ord-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 100px;
  white-space: nowrap;
}

.ord-badge.is-success {
  background: rgba(34, 197, 94, 0.12);
  color: #16a34a;
  border: 1px solid rgba(34, 197, 94, 0.25);
}

.ord-badge.is-warning {
  background: rgba(245, 158, 11, 0.12);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.ord-badge.is-danger {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

[data-theme="dark"] .ord-badge.is-success { color: #4ade80; }
[data-theme="dark"] .ord-badge.is-warning { color: #fbbf24; }
[data-theme="dark"] .ord-badge.is-danger  { color: #f87171; }

/* "Học ngay" button */
.ord-learn-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: linear-gradient(135deg, var(--green, #1d9e75), #16a85f);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  height: 36px;
  padding: 0 16px;
  border-radius: 8px;
  text-decoration: none;
  white-space: nowrap;
  transition: opacity 0.2s, transform 0.2s;
  box-shadow: 0 4px 12px rgba(29, 158, 117, 0.3);
}

.ord-learn-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* ─── Responsive ─── */
@media (max-width: 768px) {
  .ord-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    padding: 20px 16px;
  }

  .ord-stat-card {
    padding: 16px 18px;
  }

  .ord-stat-value {
    font-size: 20px;
  }

  .ord-content {
    padding: 28px 16px 60px;
  }

  .ord-card {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .ord-thumb {
    width: 80px;
    height: 80px;
  }

  .ord-card-right {
    width: 100%;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding-left: 0;
    padding-top: 16px;
    border-left: none;
    border-top: 1px solid var(--line, #e8ede9);
  }

  [data-theme="dark"] .ord-card-right {
    border-top-color: rgba(255,255,255,0.07);
  }

  .ord-price-row {
    align-items: flex-start;
  }

  .ord-hero-inner {
    flex-direction: column;
    align-items: flex-start;
    gap: 24px;
  }

  .ord-skeleton-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .ord-sk-thumb {
    width: 80px;
    height: 80px;
  }
}

@media (max-width: 400px) {
  .ord-stats {
    grid-template-columns: 1fr;
  }
}
</style>
