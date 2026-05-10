<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ course: any }>()

function formatPrice(price: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

const courseLink = computed(() => `/courses/${props.course.id}`)
const rating = computed(() => Number(props.course.reviews_avg_rating || props.course.avg_rating || 0))
const reviewCount = computed(() => Number(props.course.reviews_count || 0))
const excerpt = computed(() => {
  const text = String(props.course.description || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()
  if (!text) return 'Khóa học được thiết kế để bạn nắm nhanh kiến thức trọng tâm và ứng dụng được ngay.'
  return text.length > 120 ? `${text.slice(0, 117)}...` : text
})
const categoryName = computed(() => props.course.category?.name || props.course.category || 'Khóa học')
const isFree = computed(() => Number(props.course.price || 0) === 0)
</script>

<template>
  <NuxtLink :to="courseLink" class="course-card">
    <div class="card-thumb">
      <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="thumb-img">
      <div v-else class="thumb-fallback">
        <span class="material-symbols-outlined">school</span>
      </div>
      <div class="thumb-overlay" />

      <div class="thumb-badges">
        <span v-if="isFree" class="badge badge--free">Miễn phí</span>
        <span class="badge badge--cat">{{ categoryName }}</span>
      </div>

      <div class="thumb-footer">
        <span class="instructor-name">{{ course.instructor?.name || 'EduPress' }}</span>
        <span class="lesson-pill">{{ course.lessons_count || 0 }} bài</span>
      </div>
    </div>

    <div class="card-body">
      <h3 class="card-title">{{ course.title }}</h3>
      <p class="card-excerpt">{{ excerpt }}</p>

      <div class="card-meta">
        <span class="meta-rating">
          <span class="material-symbols-outlined star-icon">star</span>
          {{ rating > 0 ? rating.toFixed(1) : 'Mới' }}
        </span>
        <span v-if="reviewCount > 0" class="meta-item">{{ reviewCount }} đánh giá</span>
        <span class="meta-item">{{ course.enrollments_count || 0 }} học viên</span>
      </div>

      <div class="card-footer">
        <div>
          <p class="price-label">Học phí</p>
          <p class="price-value">{{ isFree ? 'Miễn phí' : formatPrice(course.price) }}</p>
        </div>
        <span class="card-cta">
          Xem khóa học
          <span class="material-symbols-outlined cta-arrow">arrow_forward</span>
        </span>
      </div>
    </div>
  </NuxtLink>
</template>

<style scoped>
.course-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  border-radius: 24px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: rgba(255, 255, 255, 0.9);
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  transition: transform 240ms ease, box-shadow 240ms ease, border-color 240ms ease;
}
.course-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 20px 48px -18px rgba(17, 17, 17, 0.2);
  border-color: rgba(var(--green-rgb), 0.2);
}

/* Thumbnail */
.card-thumb {
  position: relative;
  height: 188px;
  overflow: hidden;
  background: rgba(var(--green-rgb), 0.08);
  flex-shrink: 0;
}
.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 400ms ease;
}
.course-card:hover .thumb-img { transform: scale(1.05); }

.thumb-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: rgba(var(--green-rgb),0.08);
}
.thumb-fallback .material-symbols-outlined {
  font-size: 48px;
  color: rgba(var(--green-rgb), 0.4);
}

.thumb-overlay {
  position: absolute;
  inset: 0;
  background: rgba(17,17,17,0.32);
}

.thumb-badges {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.badge {
  display: inline-flex;
  align-items: center;
  height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.badge--free {
  background: rgba(var(--green-rgb), 0.9);
  color: #fff;
}
.badge--cat {
  background: rgba(255, 255, 255, 0.88);
  color: #111111;
}

.thumb-footer {
  position: absolute;
  bottom: 12px;
  left: 12px;
  right: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.instructor-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.88);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}
.lesson-pill {
  flex-shrink: 0;
  height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  font-size: 0.72rem;
  font-weight: 800;
  color: #111111;
  white-space: nowrap;
}

/* Body */
.card-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  padding: 18px;
}

.card-title {
  margin: 0 0 8px;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.35;
  letter-spacing: -0.02em;
  color: #111111;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  transition: color 180ms ease;
}
.course-card:hover .card-title { color: var(--green-deep, var(--green-deep)); }

.card-excerpt {
  margin: 0 0 12px;
  font-size: 0.82rem;
  line-height: 1.65;
  color: var(--muted, #5f675f);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.card-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  color: var(--muted, #5f675f);
  margin-bottom: 14px;
}
.meta-rating {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-weight: 700;
  color: #111111;
}
.star-icon {
  font-size: 16px;
  color: #d4a017;
  font-variation-settings: 'FILL' 1;
}
.meta-item { font-size: 0.78rem; }

/* Footer */
.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid rgba(17, 17, 17, 0.07);
}
.price-label {
  margin: 0;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--muted, #5f675f);
}
.price-value {
  margin: 3px 0 0;
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--green-deep, var(--green-deep));
}

.card-cta {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--green-deep, var(--green-deep));
  white-space: nowrap;
}
.cta-arrow {
  font-size: 16px;
  transition: transform 180ms ease;
}
.course-card:hover .cta-arrow { transform: translateX(3px); }
</style>
