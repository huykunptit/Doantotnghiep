<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useToast } from '~/composables/useToast'

const toast = useToast()

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const reviews = ref<any[]>([])
const allReviews = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const ratingFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const formatDate = (date?: string) =>
  !date ? '—' : new Date(date).toLocaleDateString('vi-VN', { year: 'numeric', month: 'short', day: 'numeric' })

const positiveCount = computed(() => allReviews.value.filter(r => r.rating >= 4).length)
const negativeCount = computed(() => allReviews.value.filter(r => r.rating <= 2).length)
const avgRating = computed(() => {
  if (!allReviews.value.length) return 0
  return (allReviews.value.reduce((s, r) => s + (r.rating || 0), 0) / allReviews.value.length).toFixed(1)
})
const positivePercent = computed(() =>
  allReviews.value.length ? Math.round((positiveCount.value / allReviews.value.length) * 100) : 0
)
const negativePercent = computed(() =>
  allReviews.value.length ? Math.round((negativeCount.value / allReviews.value.length) * 100) : 0
)

async function fetchReviews(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const q = new URLSearchParams({ page: String(page), per_page: '12' })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (ratingFilter.value) q.set('rating', ratingFilter.value)
    const data = await useApi<any>(`/admin/reviews?${q}`, { headers: authHeaders() })
    reviews.value = data.data || []
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0

    if (allReviews.value.length === 0) {
      const all = await useApi<any>('/admin/reviews?per_page=200', { headers: authHeaders() })
      allReviews.value = all.data || []
    }
  }
  catch { reviews.value = [] }
  finally { loading.value = false }
}

async function removeReview(review: any) {
  if (!confirm(`Xoá đánh giá ${review.rating}★ của "${review.user?.name}"? Không thể hoàn tác.`)) return
  try {
    await useApi(`/admin/reviews/${review.id}`, { method: 'DELETE', headers: authHeaders() })
    allReviews.value = allReviews.value.filter(r => r.id !== review.id)
    toast.success('Đã xoá đánh giá', `Đánh giá của ${review.user?.name || 'người dùng'} đã được xoá.`)
    await fetchReviews(currentPage.value)
  }
  catch (e: any) {
    toast.error('Xoá thất bại', e?.data?.message || 'Không thể xoá đánh giá này.')
  }
}

function exportCSV() {
  const rows = reviews.value.map(r => [
    r.id, r.course?.title || '', r.user?.name || '', r.user?.email || '',
    r.rating, `"${(r.comment || '').replace(/"/g, '""')}"`, formatDate(r.created_at),
  ])
  const header = ['ID', 'Khoá học', 'Người đánh giá', 'Email', 'Số sao', 'Nội dung', 'Ngày tạo']
  const csv = [header, ...rows].map(r => r.join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = 'reviews_export.csv'; a.click()
  URL.revokeObjectURL(url)
}

const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(totalPages.value, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1)
  for (let i = start; i <= end; i++) { if (i >= 1) range.push(i) }
  return range
})

const ratingTier = (r: number) => r >= 4 ? 'positive' : r <= 2 ? 'negative' : 'neutral'

onMounted(() => fetchReviews(1))
</script>

<template>
  <AdminWorkspaceShell
    title="Kiểm duyệt đánh giá"
    description="Quản lý chất lượng khoá học qua nhận xét của học viên. Gỡ bỏ đánh giá vi phạm tiêu chuẩn cộng đồng."
    :breadcrumb="['Trang chủ', 'Nội dung', 'Đánh giá']"
  >
    <!-- KPI Strip -->
    <div class="ds-stats mb-5">
      <div class="ds-stat ds-stat--green">
        <div class="ds-stat-icon"><i class="pi pi-chart-bar" style="font-size:1.0rem" /></div>
        <p class="ds-stat-label">Tổng đánh giá</p>
        <strong class="ds-stat-value">{{ totalItems }}</strong>
        <span class="ds-stat-sub">lượt đánh giá</span>
      </div>
      <div class="ds-stat ds-stat--blue">
        <div class="ds-stat-icon"><i class="pi pi-star" style="font-size:1.0rem" /></div>
        <p class="ds-stat-label">Điểm trung bình</p>
        <strong class="ds-stat-value">{{ avgRating }}</strong>
        <span class="ds-stat-sub">trên 5 sao</span>
      </div>
      <div class="ds-stat ds-stat--green">
        <div class="ds-stat-icon"><i class="pi pi-thumbs-up" style="font-size:1.0rem" /></div>
        <p class="ds-stat-label">Tích cực (4-5★)</p>
        <strong class="ds-stat-value">{{ positivePercent }}<small>%</small></strong>
        <span class="ds-stat-sub">{{ positiveCount }} đánh giá</span>
      </div>
      <div class="ds-stat ds-stat--red">
        <div class="ds-stat-icon"><i class="pi pi-thumbs-down" style="font-size:1.0rem" /></div>
        <p class="ds-stat-label">Tiêu cực (1-2★)</p>
        <strong class="ds-stat-value">{{ negativePercent }}<small>%</small></strong>
        <span class="ds-stat-sub">{{ negativeCount }} đánh giá</span>
      </div>
    </div>

    <!-- Panel -->
    <section class="dashboard-card crud-panel">
      <!-- Toolbar -->
      <div class="crud-toolbar">
        <div class="crud-toolbar-main">
          <input
            v-model="search"
            class="crud-search"
            type="text"
            placeholder="Tìm nội dung đánh giá..."
            @keyup.enter="fetchReviews(1)"
          >
          <select v-model="ratingFilter" class="crud-select" @change="fetchReviews(1)">
            <option value="">Tất cả sao</option>
            <option value="5">5 sao ★★★★★</option>
            <option value="4">4 sao ★★★★</option>
            <option value="3">3 sao ★★★</option>
            <option value="2">2 sao ★★</option>
            <option value="1">1 sao ★</option>
          </select>
        </div>
        <div class="crud-toolbar-right">
          <button type="button" class="crud-export-btn" @click="exportCSV">
            <i class="pi pi-download" style="font-size:1.0rem" />
            Xuất CSV
          </button>
        </div>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="rv-grid">
        <div v-for="i in 9" :key="i" class="rv-skeleton" />
      </div>

      <!-- Empty -->
      <div v-else-if="reviews.length === 0" class="crud-empty">
        Không có đánh giá nào khớp với bộ lọc.
      </div>

      <!-- Grid -->
      <div v-else class="rv-grid">
        <div
          v-for="review in reviews"
          :key="review.id"
          class="rv-card"
          :class="`rv-card--${ratingTier(review.rating)}`"
        >
          <!-- Top row: avatar + meta + delete -->
          <div class="rv-top">
            <div class="rv-avatar">
              {{ review.user?.name?.slice(0, 2).toUpperCase() || 'HV' }}
            </div>
            <div class="rv-meta">
              <strong class="rv-name">{{ review.user?.name || 'Ẩn danh' }}</strong>
              <span class="rv-date">{{ formatDate(review.created_at) }}</span>
            </div>
            <button class="rv-del" type="button" title="Xoá" @click="removeReview(review)">
              <i class="pi pi-trash" style="font-size:0.875rem" />
            </button>
          </div>

          <!-- Stars + badge -->
          <div class="rv-rating-row">
            <div class="rv-stars">
              <i
                v-for="star in 5"
                :key="star"
                class="pi pi-star"
                style="font-size:0.8125rem"
                :style="{
                  color: star <= review.rating ? '#f59e0b' : 'var(--line-strong)',
                }"
              />
            </div>
            <span class="rv-badge" :class="`rv-badge--${ratingTier(review.rating)}`">
              {{ review.rating }}/5
            </span>
          </div>

          <!-- Comment -->
          <p class="rv-comment">{{ review.comment || 'Không có nhận xét chi tiết.' }}</p>

          <!-- Course tag -->
          <div class="rv-course">
            <i class="pi pi-book" style="font-size:0.75rem" />
            <span>{{ review.course?.title || 'Không rõ khoá học' }}</span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="crud-pagination">
        <p>Trang {{ currentPage }} / {{ totalPages }} — {{ totalItems }} đánh giá</p>
        <div class="crud-pagination-actions">
          <button class="pagination-num-btn" type="button" :disabled="currentPage <= 1" @click="fetchReviews(currentPage - 1)">Trước</button>
          <div class="pagination-numbers">
            <button
              v-for="p in visiblePages"
              :key="p"
              class="pagination-num-btn"
              :class="{ 'is-active': p === currentPage }"
              type="button"
              @click="fetchReviews(p)"
            >{{ p }}</button>
          </div>
          <button class="pagination-num-btn" type="button" :disabled="currentPage >= totalPages" @click="fetchReviews(currentPage + 1)">Sau</button>
        </div>
      </div>
    </section>
  </AdminWorkspaceShell>
</template>

<style scoped>
/* ── Skeleton ── */
.rv-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.rv-skeleton {
  height: 156px;
  background: var(--bg, #eff2f0);
  border-radius: 12px;
  border: 1px solid var(--line);
  animation: rv-pulse 1.4s ease-in-out infinite;
}

@keyframes rv-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

/* ── Review Card ── */
.rv-card {
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line-strong, rgba(31,49,43,0.16));
  border-left-width: 3px;
  border-left-color: var(--line-strong, rgba(31,49,43,0.16));
  border-radius: 12px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: box-shadow 160ms ease, transform 160ms ease;
}

.rv-card:hover {
  box-shadow: 0 6px 20px rgba(31,49,43,0.08);
  transform: translateY(-1px);
}

.rv-card--positive { border-left-color: var(--green, #1d9e75); }
.rv-card--neutral  { border-left-color: #f59e0b; }
.rv-card--negative { border-left-color: #ef4444; }

/* ── Top row ── */
.rv-top {
  display: flex;
  align-items: center;
  gap: 10px;
}

.rv-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--green-soft, #e1f5ee);
  border: 1.5px solid rgba(29,158,117,0.25);
  color: var(--green-deep, #085041);
  font-size: 0.72rem;
  font-weight: 800;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  letter-spacing: 0.3px;
}

.rv-meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.rv-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}

.rv-date {
  font-size: 0.7rem;
  color: var(--muted);
}

.rv-del {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  display: grid;
  place-items: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: color 140ms, background 140ms, border-color 140ms;
}

.rv-del:hover {
  color: #ef4444;
  background: #fff1f2;
  border-color: rgba(239,68,68,0.2);
}

/* ── Rating row ── */
.rv-rating-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rv-stars {
  display: flex;
  gap: 2px;
}

.rv-badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}

.rv-badge--positive {
  background: rgba(29,158,117,0.1);
  color: var(--green-deep, #085041);
}

.rv-badge--neutral {
  background: rgba(245,158,11,0.12);
  color: #92400e;
}

.rv-badge--negative {
  background: rgba(239,68,68,0.1);
  color: #b91c1c;
}

/* ── Comment ── */
.rv-comment {
  margin: 0;
  font-size: 0.82rem;
  color: var(--text);
  line-height: 1.6;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ── Course tag ── */
.rv-course {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  background: var(--bg, #eff2f0);
  border: 1px solid var(--line);
  border-radius: 8px;
  color: var(--muted);
  margin-top: auto;
}

.rv-course span {
  font-size: 0.72rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Dark Mode ── */
[data-theme="dark"] .rv-stat {
  background: rgba(255,255,255,0.04);
  border-color: rgba(255,255,255,0.1);
}

[data-theme="dark"] .rv-card {
  background: rgba(255,255,255,0.04);
  border-color: rgba(255,255,255,0.1);
}

[data-theme="dark"] .rv-card--positive { border-left-color: #5DCAA5; }
[data-theme="dark"] .rv-card--neutral  { border-left-color: #fbbf24; }
[data-theme="dark"] .rv-card--negative { border-left-color: #f87171; }

[data-theme="dark"] .rv-skeleton {
  background: rgba(255,255,255,0.05);
  border-color: rgba(255,255,255,0.08);
}

[data-theme="dark"] .rv-course {
  background: rgba(255,255,255,0.05);
  border-color: rgba(255,255,255,0.08);
}

[data-theme="dark"] .rv-del:hover {
  background: rgba(239,68,68,0.15);
  border-color: rgba(239,68,68,0.25);
}

[data-theme="dark"] .rv-avatar {
  background: rgba(29,158,117,0.2);
  border-color: rgba(29,158,117,0.3);
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .rv-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 560px) {
  .rv-stats {
    grid-template-columns: 1fr 1fr;
  }
  .rv-grid {
    grid-template-columns: 1fr;
  }
}
</style>
