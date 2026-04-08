<template>
  <div>
    <NuxtLayout name="admin">
      <!-- Filter bar -->
      <div class="flex flex-col sm:flex-row gap-3 mb-6 items-start sm:items-center justify-between animate-fade-in-up">
        <div class="flex gap-2 w-full sm:w-auto flex-1 max-w-md">
          <div class="relative flex-1">
            <input v-model="search" type="text" placeholder="Tìm nội dung, tên người dùng..." class="input pl-10 h-10 text-sm w-full"
              @keyup.enter="fetchReviews(1)" />
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          </div>
          <select v-model="ratingFilter" class="input h-10 py-1.5 text-sm w-auto focus:ring-0" @change="fetchReviews(1)">
            <option value="">Tất cả sao</option>
            <option value="5">⭐⭐⭐⭐⭐ 5 sao</option>
            <option value="4">⭐⭐⭐⭐ 4 sao</option>
            <option value="3">⭐⭐⭐ 3 sao</option>
            <option value="2">⭐⭐ 2 sao</option>
            <option value="1">⭐ 1 sao</option>
          </select>
        </div>
        
        <button class="btn-export text-sm h-10 px-4 flex items-center gap-2 text-gray-700 w-full sm:w-auto" @click="exportData">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
          Xuất CSV
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 6" :key="i" class="card p-4 animate-pulse flex gap-3">
          <div class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0"></div>
          <div class="flex-1 space-y-2">
            <div class="h-3.5 bg-gray-200 rounded w-32"></div>
            <div class="h-3 bg-gray-200 rounded w-full"></div>
            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="reviews.length === 0" class="card-glass p-16 text-center animate-fade-in-up" style="animation-delay: 0.1s">
        <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-gray-100">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
        </div>
        <h3 class="text-base font-semibold text-gray-900 mb-1">Không có đánh giá nào</h3>
        <p class="text-sm text-gray-500">Chưa có dữ liệu nào khớp với điều kiện lọc.</p>
      </div>

      <!-- Review list -->
      <div v-else class="space-y-4 animate-fade-in-up" style="animation-delay: 0.1s">
        <div v-for="review in reviews" :key="review.id" class="card-glass p-5 group hover:shadow-md transition-shadow">
          <div class="flex items-start gap-3">
            <!-- Avatar -->
            <div class="w-9 h-9 rounded-full bg-primary-light flex items-center justify-center flex-shrink-0 overflow-hidden">
              <img v-if="review.user?.avatar" :src="review.user.avatar" class="w-full h-full object-cover" />
              <span v-else class="text-sm font-bold text-primary">{{ review.user?.name?.charAt(0)?.toUpperCase() }}</span>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-gray-900">{{ review.user?.name }}</span>
                    <span class="text-xs text-gray-400">đánh giá</span>
                    <span class="text-xs font-medium text-primary truncate max-w-[200px]">{{ review.course?.title }}</span>
                  </div>
                  <!-- Star rating -->
                  <div class="flex items-center gap-0.5 mt-1">
                    <span v-for="s in 5" :key="s" class="text-sm" :class="s <= review.rating ? 'text-amber-400' : 'text-gray-200'">★</span>
                    <span class="text-xs text-gray-500 ml-1">{{ review.rating }}/5</span>
                    <span class="text-xs text-gray-400 ml-2">· {{ formatDate(review.created_at) }}</span>
                  </div>
                </div>
                <!-- Delete button -->
                <button
                  class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100 flex-shrink-0"
                  title="Xóa đánh giá"
                  @click="removeReview(review)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </div>

              <!-- Comment -->
              <p v-if="review.comment" class="mt-2 text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg px-3 py-2">
                "{{ review.comment }}"
              </p>
              <p v-else class="mt-2 text-xs text-gray-400 italic">Không có nhận xét</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1 || totalItems > perPage" class="pagination-wrapper mt-6">
        <div class="flex items-center gap-3">
          <p class="text-xs text-gray-500">Hiển thị <span class="font-medium text-gray-900">{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }}</span> / {{ totalItems }}</p>
          <select v-model="perPage" class="input h-8 py-1.5 text-xs w-auto focus:ring-0" @change="fetchReviews(1)">
            <option :value="10">10 / trang</option>
            <option :value="20">20 / trang</option>
            <option :value="50">50 / trang</option>
          </select>
        </div>
        <div class="flex gap-1">
          <button @click="fetchReviews(currentPage - 1)" :disabled="currentPage <= 1"
            class="pagination-btn" :class="currentPage <= 1 ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">‹</button>
          <button v-for="p in paginationRange" :key="p" @click="typeof p === 'number' && fetchReviews(p)"
            class="pagination-btn" :class="p === currentPage ? 'pagination-btn-active' : p === '…' ? 'cursor-default text-gray-400' : 'pagination-btn-inactive'">{{ p }}</button>
          <button @click="fetchReviews(currentPage + 1)" :disabled="currentPage >= totalPages"
            class="pagination-btn" :class="currentPage >= totalPages ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">›</button>
        </div>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const { exportToCSV } = useExport()
const reviews = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const ratingFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(20)

function formatDate(d: string): string {
  if (!d) return ''
  return new Date(d).toLocaleDateString('vi-VN')
}

const paginationRange = computed(() => {
  const total = totalPages.value, cur = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages: (number | string)[] = [1]
  if (cur > 3) pages.push('…')
  for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) pages.push(p)
  if (cur < total - 2) pages.push('…')
  pages.push(total)
  return pages
})

async function fetchReviews(page = 1) {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (ratingFilter.value) q.set('rating', ratingFilter.value)
    const data = await useApi<any>(`/admin/reviews?${q}`, { token: auth.token })
    reviews.value = data.data || []
    currentPage.value = data.current_page || 1
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0
  } catch {
    reviews.value = []
  } finally {
    loading.value = false
  }
}

async function removeReview(review: any) {
  if (!confirm(`Xóa đánh giá của "${review.user?.name}"?`)) return
  try {
    await useApi(`/admin/reviews/${review.id}`, { method: 'DELETE', token: auth.token })
    reviews.value = reviews.value.filter((r) => r.id !== review.id)
    totalItems.value--
  } catch (e: any) {
    alert(e?.data?.message || 'Xóa thất bại')
  }
}

async function exportData() {
  try {
    const q = new URLSearchParams({ page: '1', per_page: '1000' })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (ratingFilter.value) q.set('rating', ratingFilter.value)
    const data = await useApi<any>(`/admin/reviews?${q}`, { token: auth.token })
    const rows = data.data || []
    
    exportToCSV(rows, [
      { key: 'id', label: 'ID' },
      { key: 'course.title', label: 'Khóa học' },
      { key: 'user.name', label: 'Khách hàng' },
      { key: 'rating', label: 'Chấm điểm', format: (v) => `${v} sao` },
      { key: 'comment', label: 'Nhận xét' },
      { key: 'created_at', label: 'Ngày tạo', format: (v) => formatDate(v) }
    ], 'danh_sach_danh_gia')
  } catch (e) {
    alert('Không thể xuất dữ liệu')
  }
}

onMounted(fetchReviews)
</script>

