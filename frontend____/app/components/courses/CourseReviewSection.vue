<template>
  <section class="cd-card">
    <div class="cd-card-header">
      <h2 class="cd-card-title">
        <i class="pi pi-star" style="font-size:1.375rem" />
        Đánh giá của học viên
      </h2>
    </div>

    <!-- Review Form -->
    <div v-if="isEnrolled && canReview" class="mb-6 rounded-2xl bg-surface-low p-5 border border-surface-dim/50">
      <h3 class="mb-3 text-sm font-bold text-on-surface">Đánh giá khóa học này</h3>
      <div class="flex items-center gap-2 mb-3">
        <button
          v-for="star in 5" :key="star"
          @click="reviewForm.rating = star"
          @mouseenter="hoverRating = star"
          @mouseleave="hoverRating = 0"
          class="text-2xl transition-colors focus:outline-none"
          :class="(hoverRating || reviewForm.rating) >= star ? 'text-amber-400' : 'text-surface-dim'"
        >
          <i
            class="pi pi-star"
            style="font-size:1.5rem"
            :style="{ color: (hoverRating || reviewForm.rating) >= star ? '#FBBF24' : '#D1D5DB', fill: (hoverRating || reviewForm.rating) >= star ? '#FBBF24' : 'none' }"
          />
        </button>
        <span v-if="reviewForm.rating" class="ml-2 text-sm font-bold text-amber-500">{{ reviewForm.rating }} / 5</span>
      </div>
      <textarea 
        v-model="reviewForm.comment" 
        rows="3" 
        class="w-full rounded-xl border border-surface-dim/40 bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary transition-colors" 
        placeholder="Chia sẻ cảm nhận của bạn về khóa học này..."
      ></textarea>
      <div class="mt-3 flex justify-end">
        <button 
          @click="submitReview" 
          :disabled="submitting || !reviewForm.rating"
          class="rounded-xl bg-primary px-5 py-2 text-sm font-bold text-white shadow-sm transition-all hover:bg-primary/90 disabled:opacity-50"
        >
          {{ submitting ? 'Đang gửi...' : 'Gửi đánh giá' }}
        </button>
      </div>
    </div>
    <div v-else-if="isEnrolled && !canReview" class="mb-6 rounded-xl bg-green-50 p-4 border border-green-100 text-sm text-green-700 font-medium">
      Cảm ơn bạn đã đánh giá khóa học này!
    </div>

    <!-- Reviews List -->
    <div v-if="loading" class="animate-pulse space-y-4">
      <div v-for="i in 2" :key="i" class="h-24 bg-surface-low rounded-xl"></div>
    </div>
    <div v-else-if="reviews.length > 0" class="space-y-4">
      <div v-for="review in reviews" :key="review.id" class="rounded-xl border border-surface-dim/40 bg-surface-lowest p-5">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-surface-dim/30 font-bold text-on-surface overflow-hidden">
              <img v-if="review.user?.avatar" :src="review.user.avatar" class="h-full w-full object-cover">
              <span v-else>{{ review.user?.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
            </div>
            <div>
              <p class="text-sm font-bold text-on-surface">{{ review.user?.name || 'Học viên ẩn danh' }}</p>
              <div class="flex items-center gap-1 mt-0.5">
                <i
                  v-for="star in 5"
                  :key="star"
                  class="pi pi-star"
                  style="font-size:0.875rem"
                  :style="{ color: star <= review.rating ? '#FBBF24' : '#D1D5DB' }"
                />
                <span class="ml-2 text-xs text-on-surface-variant">{{ relativeTime(review.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>
        <p v-if="review.comment" class="mt-3 text-sm text-on-surface-variant leading-relaxed">
          "{{ review.comment }}"
        </p>
      </div>
      
      <!-- Load more could go here -->
    </div>
    <div v-else class="rounded-xl border border-dashed border-surface-dim bg-surface-lowest p-8 text-center text-sm text-on-surface-variant">
      Chưa có đánh giá nào cho khóa học này.
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import { useToast } from '~/composables/useToast'

const toast = useToast()

const props = defineProps<{
  courseId: number
  isEnrolled: boolean
}>()

const token = useAuthTokenCookie()
const authUser = useAuthUserCookie()

const reviews = ref<any[]>([])
const loading = ref(true)
const submitting = ref(false)
const hoverRating = ref(0)
const reviewForm = ref({ rating: 0, comment: '' })

// Check if user has already reviewed
const canReview = computed(() => {
  if (!authUser.value) return false
  return !reviews.value.some(r => r.user_id === authUser.value?.id)
})

async function fetchReviews() {
  loading.value = true
  try {
    const data = await useApi<any>(`/courses/${props.courseId}/reviews`)
    reviews.value = data.data || [] // Paginated
  } catch (e) {
    console.error('Lỗi khi tải đánh giá', e)
  } finally {
    loading.value = false
  }
}

async function submitReview() {
  if (!reviewForm.value.rating) return
  submitting.value = true
  try {
    const res = await useApi<any>(`/courses/${props.courseId}/reviews`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: reviewForm.value
    })
    
    // Add to top
    if (res.review) {
      reviews.value.unshift(res.review)
    }
    
    // Reset form
    reviewForm.value = { rating: 0, comment: '' }
    toast.success('Cảm ơn bạn đã gửi đánh giá!')
  } catch (e: any) {
    toast.error(e.data?.message || 'Không thể gửi đánh giá, vui lòng thử lại sau.')
  } finally {
    submitting.value = false
  }
}

function relativeTime(iso: string) {
  if (!iso) return ''
  const d = new Date(iso)
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return 'vừa xong'
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`
  if (diff < 2592000) return `${Math.floor(diff / 86400)} ngày trước`
  if (diff < 31104000) return `${Math.floor(diff / 2592000)} tháng trước`
  return `${Math.floor(diff / 31104000)} năm trước`
}

onMounted(() => {
  fetchReviews()
})
</script>

<style scoped>
.cd-card {
  background: var(--surface-lowest, #fff); border-radius: 16px;
  border: 1px solid var(--surface-dim, #e5e7eb); padding: 1.5rem; margin-bottom: 1.5rem;
}
.cd-card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem; }
.cd-card-title {
  display: flex; align-items: center; gap: 10px;
  font-size: 1.2rem; font-weight: 800; margin: 0; color: var(--on-surface, #0f172a);
}
.cd-card-icon { font-size: 22px; color: var(--primary, var(--green)); }
</style>
