<script setup lang="ts">
import { ref } from 'vue'
import { useCourseStore } from '~/stores/course'

const props = defineProps<{
  courseId: number
}>()

const emit = defineEmits<{
  success: [review: any]
}>()

const courseStore = useCourseStore()
const rating = ref(5)
const comment = ref('')
const submitting = ref(false)
const error = ref('')

async function handleSubmit() {
  if (submitting.value) return
  error.value = ''
  submitting.value = true
  
  try {
    const res = await courseStore.createReview(props.courseId, {
      rating: rating.value,
      comment: comment.value
    })
    emit('success', res.review)
    comment.value = ''
    rating.value = 5
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể gửi đánh giá. Vui lòng thử lại.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="bg-surface-lowest border border-surface-dim rounded-[2.5rem] p-8 shadow-sm">
    <div class="flex items-center gap-4 mb-6">
       <div class="w-12 h-12 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-600">
         <span class="material-symbols-outlined text-3xl">rate_review</span>
       </div>
       <div>
         <h3 class="text-xl font-bold font-headline text-on-surface">Đánh giá khóa học</h3>
         <p class="text-sm text-on-surface-variant font-medium">Chia sẻ cảm nhận của bạn về nội dung bài học</p>
       </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Star Rating Picker -->
      <div class="space-y-2">
        <label class="block text-sm font-bold text-on-surface ml-1">Chất lượng khóa học</label>
        <div class="flex items-center gap-2">
          <button 
            v-for="i in 5" :key="i"
            type="button"
            class="group p-1 transition-all duration-300 transform active:scale-95"
            @click="rating = i"
          >
            <span 
              class="material-symbols-outlined text-3xl transition-colors"
              :class="i <= rating ? 'text-amber-500' : 'text-surface-high'"
              :style="i <= rating ? 'font-variation-settings: \'FILL\' 1;' : ''"
            >
              grade
            </span>
          </button>
          <span class="ml-4 text-sm font-bold text-on-surface px-3 py-1 bg-surface-low rounded-full">
            {{ rating }} / 5 sao
          </span>
        </div>
      </div>

      <!-- Comment Textarea -->
      <div class="space-y-2">
        <label class="block text-sm font-bold text-on-surface ml-1">Nhận xét của bạn (tùy chọn)</label>
        <textarea 
          v-model="comment" 
          rows="4" 
          maxlength="2000"
          placeholder="Hãy kể cho chúng tôi về trải nghiệm học tập của bạn..."
          class="w-full rounded-3xl border border-surface-dim bg-surface-low px-6 py-4 text-sm focus:ring-2 focus:ring-primary/20 outline-none transition-all placeholder:text-outline/50"
        ></textarea>
        <div class="flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-outline px-2">
          <span>{{ error }}</span>
          <span>{{ comment.length }}/2000 kí tự</span>
        </div>
      </div>

      <div class="flex justify-end">
        <UiButton type="submit" size="lg" :loading="submitting" class="px-10">
          Gửi đánh giá ngay
        </UiButton>
      </div>
    </form>
  </div>
</template>
