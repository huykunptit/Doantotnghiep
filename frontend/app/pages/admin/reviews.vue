<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Download, Trash2, Star, BookOpen } from 'lucide-vue-next'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

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
    await fetchReviews(currentPage.value)
  }
  catch (e: any) {
    alert(e?.data?.message || 'Xoá thất bại.')
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
  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) {
    if (i >= 1) range.push(i)
  }
  return range
})

onMounted(() => fetchReviews(1))
</script>

<template>
  <AdminWorkspaceShell
    title="Kiểm duyệt đánh giá"
    description="Quản lý chất lượng khoá học qua nhận xét của học viên. Gỡ bỏ đánh giá vi phạm tiêu chuẩn cộng đồng."
    :breadcrumb="['Trang chủ', 'Nội dung', 'Đánh giá']"
  >
    <!-- KPI -->
    <section class="dashboard-grid" style="margin-bottom: 24px;">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Tổng đánh giá</p>
        <div class="mini-head"><strong>{{ totalItems }}</strong><span>Trang hiện tại lọc</span></div>
      </article>
      <article class="dashboard-card mini-card tone-blue">
        <p class="mini-title">Điểm trung bình</p>
        <div class="mini-head">
          <strong>{{ avgRating }}</strong>
          <span>/ 5 sao</span>
        </div>
      </article>
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Tích cực (4-5★)</p>
        <div class="mini-head">
          <strong>{{ positivePercent }}%</strong>
          <span>{{ positiveCount }} đánh giá</span>
        </div>
      </article>
      <article class="dashboard-card mini-card tone-amber">
        <p class="mini-title">Tiêu cực (1-2★)</p>
        <div class="mini-head">
          <strong>{{ allReviews.length ? Math.round((negativeCount / allReviews.length) * 100) : 0 }}%</strong>
          <span>{{ negativeCount }} đánh giá</span>
        </div>
      </article>
    </section>

    <!-- Toolbar -->
    <section class="dashboard-card crud-panel">
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
            <option value="5">5 sao ⭐⭐⭐⭐⭐</option>
            <option value="4">4 sao ⭐⭐⭐⭐</option>
            <option value="3">3 sao ⭐⭐⭐</option>
            <option value="2">2 sao ⭐⭐</option>
            <option value="1">1 sao ⭐</option>
          </select>
        </div>
        <div class="crud-toolbar-right">
          <button type="button" class="crud-export-btn" @click="exportCSV">
            <Download :size="18" :stroke-width="1.75" />
            Xuất Excel
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="reviews-grid">
        <div v-for="i in 6" :key="i" class="review-skeleton" />
      </div>

      <!-- Empty -->
      <div v-else-if="reviews.length === 0" class="crud-empty">
        Không có đánh giá nào khớp với bộ lọc.
      </div>

      <!-- Reviews grid -->
      <div v-else class="reviews-grid">
        <div
          v-for="review in reviews"
          :key="review.id"
          class="review-card"
          :class="{
            'is-negative': review.rating <= 2,
            'is-neutral': review.rating === 3,
          }"
        >
          <!-- Delete button -->
          <button type="button" class="review-delete-btn" @click="removeReview(review)">
            <Trash2 :size="16" :stroke-width="1.75" />
          </button>

          <!-- User -->
          <div class="review-user">
            <div class="crud-avatar crud-avatar-fallback" style="width: 36px; height: 36px; font-size: 0.8rem;">
              {{ review.user?.name?.slice(0, 2).toUpperCase() || 'HV' }}
            </div>
            <div style="min-width: 0;">
              <strong style="font-size: 0.875rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                {{ review.user?.name || 'Ẩn danh' }}
              </strong>
              <span style="font-size: 0.7rem; color: var(--muted);">{{ formatDate(review.created_at) }}</span>
            </div>
          </div>

          <!-- Stars -->
          <div class="review-stars">
            <Star
              v-for="star in 5"
              :key="star"
              :size="15"
              :stroke-width="1.75"
              :style="{
                color: star <= review.rating ? '#f59e0b' : 'rgba(17,17,17,0.12)',
                fill: star <= review.rating ? '#f59e0b' : 'none',
              }"
            />
          </div>

          <!-- Comment -->
          <p class="review-comment">
            "{{ review.comment || 'Không có nhận xét chi tiết.' }}"
          </p>

          <!-- Course -->
          <div class="review-course">
            <BookOpen :size="14" :stroke-width="1.75" style="color: var(--muted);" />
            <span style="font-size: 0.75rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              {{ review.course?.title || 'Không rõ khoá học' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="crud-pagination">
        <p>Hiển thị trang {{ currentPage }} / {{ totalPages }} (Tổng số {{ totalItems }} đánh giá)</p>
        <div class="crud-pagination-actions">
          <button class="pagination-num-btn" type="button" :disabled="currentPage <= 1" @click="fetchReviews(currentPage - 1)">
            Trước
          </button>
          <div class="pagination-numbers">
            <button
              v-for="p in visiblePages"
              :key="p"
              class="pagination-num-btn"
              :class="{ 'is-active': p === currentPage }"
              type="button"
              @click="fetchReviews(p)"
            >
              {{ p }}
            </button>
          </div>
          <button class="pagination-num-btn" type="button" :disabled="currentPage >= totalPages" @click="fetchReviews(currentPage + 1)">
            Sau
          </button>
        </div>
      </div>
    </section>
  </AdminWorkspaceShell>
</template>

<style scoped>
.reviews-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
  margin-top: 8px;
}
.review-skeleton {
  height: 160px;
  background: rgba(17,17,17,0.05);
  border-radius: 16px;
  animation: pulse 1.5s infinite;
}
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.5} }

.review-card {
  position: relative;
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 16px;
  background: rgba(255,255,255,0.7);
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: box-shadow 0.2s;
}
.review-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.review-card.is-negative { border-color: #fca5a5; background: #fff1f2; }
.review-card.is-neutral { border-color: #fcd34d; background: #fffbeb; }

.review-delete-btn {
  position: absolute;
  top: 12px; right: 12px;
  width: 28px; height: 28px;
  border-radius: 50%;
  border: none;
  background: transparent;
  cursor: pointer;
  opacity: 0;
  display: flex; align-items: center; justify-content: center;
  color: #ef4444;
  transition: all 0.2s;
}
.review-card:hover .review-delete-btn { opacity: 1; }
.review-delete-btn:hover { background: #fff1f2; }

.review-user { display: flex; align-items: center; gap: 10px; padding-right: 32px; }
.review-stars { display: flex; gap: 1px; }
.review-comment {
  font-size: 0.82rem;
  color: var(--muted);
  font-style: italic;
  line-height: 1.6;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.review-course {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: rgba(17,17,17,0.04);
  border-radius: 8px;
  overflow: hidden;
}
</style>
