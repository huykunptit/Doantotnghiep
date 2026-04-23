<script setup lang="ts">
import { computed } from 'vue'
import UiBadge from '~/components/ui/UiBadge.vue'

const props = defineProps<{
  course: any
}>()

function formatPrice(price: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

const courseLink = computed(() => `/courses/${props.course.id}`)
const rating = computed(() => Number(props.course.reviews_avg_rating || props.course.avg_rating || 0))
const reviewCount = computed(() => Number(props.course.reviews_count || 0))
const excerpt = computed(() => {
  const text = String(props.course.description || '').replace(/\s+/g, ' ').trim()
  if (!text) return 'Khóa học được thiết kế để bạn nắm nhanh kiến thức trọng tâm và ứng dụng được ngay.'
  return text.length > 150 ? `${text.slice(0, 147)}...` : text
})
const categoryName = computed(() => props.course.category?.name || props.course.category || 'Khóa học nổi bật')
</script>

<template>
  <NuxtLink :to="courseLink" class="group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-surface-dim/60 bg-surface-lowest shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:border-primary/20 hover:shadow-ambient">
    <div class="relative h-56 overflow-hidden bg-surface-low">
      <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
      <div v-else class="flex h-full items-center justify-center bg-gradient-to-br from-primary/15 via-surface to-secondary/10 text-5xl text-outline">📚</div>
      <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent"></div>

      <div class="absolute left-4 top-4 flex flex-wrap gap-2">
        <UiBadge v-if="course.price === 0" variant="success">Miễn phí</UiBadge>
        <UiBadge variant="default">{{ categoryName }}</UiBadge>
      </div>

      <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-white/85">{{ course.instructor?.name || 'Đội ngũ EduPress' }}</p>
        </div>
        <div class="rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-on-surface shadow-sm">
          {{ course.lessons_count || 0 }} bài học
        </div>
      </div>
    </div>

    <div class="flex flex-1 flex-col p-6">
      <div>
        <h3 class="line-clamp-2 font-headline text-2xl font-bold tracking-[-0.03em] text-on-surface transition-colors group-hover:text-primary">
          {{ course.title }}
        </h3>
        <p class="mt-3 line-clamp-3 text-sm leading-7 text-on-surface-variant">
          {{ excerpt }}
        </p>
      </div>

      <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-on-surface-variant">
        <span class="inline-flex items-center gap-1.5 font-semibold text-on-surface">
          <span class="material-symbols-outlined text-[18px] text-amber-500">star</span>
          {{ rating > 0 ? rating.toFixed(1) : 'Mới' }}
        </span>
        <span v-if="reviewCount > 0">{{ reviewCount }} đánh giá</span>
        <span>{{ course.enrollments_count || 0 }} học viên</span>
      </div>

      <div class="mt-auto pt-6">
        <div class="flex items-center justify-between border-t border-surface-dim/40 pt-5">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-outline">Học phí</p>
            <p class="mt-1 font-headline text-2xl font-bold tracking-[-0.03em] text-primary">
              {{ course.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}
            </p>
          </div>
          <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary">
            Xem khóa học
            <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-1">arrow_forward</span>
          </span>
        </div>
      </div>
    </div>
  </NuxtLink>
</template>
