<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-surface-dim/30 pb-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Content Moderation</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface mt-1">Kiểm duyệt Đánh giá</h2>
          <p class="text-on-surface-variant text-sm mt-2">
            Quản lý chất lượng khóa học qua các nhận xét của học viên. Xử lý hoặc gỡ bỏ các đánh giá vi phạm tiêu chuẩn cộng đồng.
          </p>
        </div>
        <button @click="exportData" class="px-5 py-2.5 bg-surface-lowest border border-surface-dim/50 text-on-surface text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all flex items-center gap-2">
           <span class="material-symbols-outlined text-[18px]">download</span> Xuất dữ liệu
        </button>
      </div>

      <!-- Filters & Stats row -->
      <div class="flex flex-col lg:flex-row gap-6 justify-between items-start lg:items-center">
         
         <!-- Stat Chips -->
         <div class="flex flex-wrap gap-2">
            <div class="bg-surface-lowest px-4 py-2 flex items-center gap-2 rounded-lg border border-surface-dim/30 shadow-sm">
               <span class="text-xs font-bold text-outline uppercase tracking-wider">Tổng Đánh Giá:</span>
               <span class="text-sm font-bold text-on-surface">{{ totalItems }}</span>
            </div>
            <!-- Mock static distribution -->
            <div class="bg-secondary/10 px-4 py-2 flex items-center gap-2 rounded-lg border border-secondary/20 shadow-sm">
               <span class="text-xs font-bold text-secondary uppercase tracking-wider">Tích cực (4-5*):</span>
               <span class="text-sm font-bold text-on-surface">~85%</span>
            </div>
            <div class="bg-error-container/40 px-4 py-2 flex items-center gap-2 rounded-lg border border-error/20 shadow-sm">
               <span class="text-xs font-bold text-error uppercase tracking-wider">Tiêu cực (1-2*):</span>
               <span class="text-sm font-bold text-on-surface">~3%</span>
            </div>
         </div>

         <!-- Search & Filter -->
         <div class="flex items-center gap-3 w-full lg:w-auto">
            <div class="relative flex-1 lg:w-64">
               <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
               <input v-model="search" @keyup.enter="fetchReviews(1)" placeholder="Tra cứu nội dung..." type="text" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-dim/30 bg-surface-lowest shadow-sm placeholder-outline focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm">
            </div>
            <select v-model="ratingFilter" @change="fetchReviews(1)" class="rounded-xl border border-surface-dim/30 bg-surface-lowest px-4 py-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm font-medium w-40">
               <option value="">Toàn bộ Cấp sao</option>
               <option value="5">⭐⭐⭐⭐⭐ (5)</option>
               <option value="4">⭐⭐⭐⭐ (4)</option>
               <option value="3">⭐⭐⭐ (3)</option>
               <option value="2">⭐⭐ (2)</option>
               <option value="1">⭐ (1)</option>
            </select>
         </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
         <div v-for="item in 6" :key="item" class="h-40 rounded-[1.25rem] bg-surface-high animate-pulse" />
      </div>

      <div v-else-if="reviews.length === 0" class="py-20 text-center bg-surface-low rounded-[1.25rem] border border-surface-dim shadow-inner">
         <span class="material-symbols-outlined text-6xl text-outline opacity-50 mb-4">reviews</span>
         <h4 class="font-bold text-on-surface text-lg mb-1">Không thấy Đánh giá</h4>
         <p class="font-medium text-sm text-on-surface-variant max-w-md mx-auto">Thử thiết lập lại tùy chọn bộ lọc hoặc tìm kiếm nội dung khác.</p>
      </div>

      <!-- Reviews Masonry/Grid -->
      <div v-else class="grid gap-6 md:grid-cols-2 xl:grid-cols-3 items-start">
        <div v-for="review in reviews" :key="review.id" class="bg-surface-lowest p-6 rounded-[1.25rem] border border-surface-dim/30 shadow-sm hover:shadow-ambient transition-all duration-300 relative group flex flex-col h-full">
           
           <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
              <button @click="removeReview(review)" class="p-2 text-outline hover:text-error hover:bg-error-container/50 rounded-lg transition-colors border border-transparent hover:border-error/20 bg-surface-lowest shadow-sm" title="Xóa đánh giá này">
                 <span class="material-symbols-outlined text-[18px]">delete</span>
              </button>
           </div>

           <div class="flex items-center gap-3 border-b border-surface-dim/30 pb-4 mb-4">
               <div class="h-10 w-10 overflow-hidden rounded-full font-bold text-white bg-primary shadow-sm flex items-center justify-center shrink-0">
                 <img v-if="review.user?.avatar" :src="review.user.avatar" class="h-full w-full object-cover">
                 <span v-else>{{ review.user?.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
               </div>
               <div class="min-w-0 flex-1 pr-6">
                 <p class="font-bold text-sm text-on-surface truncate">{{ review.user?.name || 'Ẩn danh' }}</p>
                 <p class="text-[10px] text-outline font-medium uppercase tracking-wider flex items-center gap-1 mt-0.5">
                    <span class="material-symbols-outlined text-[12px]">schedule</span> {{ formatDate(review.created_at) }}
                 </p>
               </div>
           </div>

           <div class="flex items-center gap-1 mb-3">
              <span v-for="star in 5" :key="star" class="material-symbols-outlined text-[16px]" :class="star <= review.rating ? 'text-amber-500' : 'text-surface-dim'" :style="star <= review.rating ? 'font-variation-settings: \'FILL\' 1;' : ''">star</span>
           </div>

           <p class="text-sm leading-relaxed text-on-surface-variant flex-1 italic">
             "{{ review.comment || 'Học viên không để lại nhận xét chi tiết.' }}"
           </p>

           <div class="mt-4 pt-3 flex items-center gap-2 bg-surface-low rounded-lg p-3 border border-surface-dim">
              <div class="h-8 w-12 rounded bg-surface-high border border-surface-dim/30 flex shrink-0 items-center justify-center">
                 <span class="material-symbols-outlined text-outline text-[16px]">menu_book</span>
              </div>
              <p class="text-[11px] font-semibold text-on-surface line-clamp-2">{{ review.course?.title || 'Không tìm thấy thông tin khóa học' }}</p>
           </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex flex-wrap justify-center gap-2 pt-6">
        <button v-for="page in totalPages" :key="page" class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold transition-all" :class="page === currentPage ? 'cta-gradient text-white shadow-md' : 'bg-surface-lowest hover:bg-surface-low text-on-surface border border-surface-dim/30 text-on-surface-variant'" @click="fetchReviews(page)">{{ page }}</button>
      </div>

    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })
const auth = useAuthStore()

const reviews = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const ratingFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const { exportToCSV } = useExport()

const formatDate = (date?: string) => !date ? 'N/A' : new Date(date).toLocaleDateString('vi-VN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })

const exportData = () => {
  exportToCSV(
    reviews.value,
    [
      { key: 'id', label: 'ID' },
      { key: 'course.title', label: 'Khóa học' },
      { key: 'user.name', label: 'Người đánh giá' },
      { key: 'user.email', label: 'Email' },
      { key: 'rating', label: 'Số sao' },
      { key: 'comment', label: 'Nội dung' },
      { key: 'created_at', label: 'Ngày tạo', format: (value) => formatDate(value) },
    ],
    'admin_reviews',
  )
}

async function fetchReviews(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const q = new URLSearchParams({ page: String(page), per_page: '10' })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (ratingFilter.value) q.set('rating', ratingFilter.value)
    
    const data = await $fetch<any>(`/api/admin/reviews?${q}`, { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [], total: 0, last_page: 1 }))
    reviews.value = data.data || []
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0
  } finally { 
     loading.value = false 
  }
}

async function removeReview(review: any) {
  if (!confirm(`Xóa vĩnh viễn đánh giá ${review.rating} sao của học viên "${review.user?.name}"? Hệ thống sẽ không thể khôi phục thao tác này.`)) return
  try { 
     await $fetch(`/api/admin/reviews/${review.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
     await fetchReviews(currentPage.value) 
  } catch (e: any) { 
     alert(e?.data?.message || 'Xóa thất bại do lỗi phía Máy chủ.') 
  }
}

onMounted(() => fetchReviews(1))
</script>
