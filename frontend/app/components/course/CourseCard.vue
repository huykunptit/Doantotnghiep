<script setup lang="ts">
import { computed } from 'vue'
import { GraduationCap, Star, ArrowRight } from 'lucide-vue-next'

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
    <!-- Thumbnail -->
    <div class="card-thumb">
      <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="thumb-img">
      <div v-else class="thumb-fallback">
        <GraduationCap :size="40" :stroke-width="1.25" />
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

    <!-- Body -->
    <div class="card-body">
      <h3 class="card-title">{{ course.title }}</h3>
      <p class="card-excerpt">{{ excerpt }}</p>

      <div class="card-meta">
        <span class="meta-rating">
          <Star :size="14" :stroke-width="0" fill="#d4a017" />
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
          <ArrowRight :size="14" :stroke-width="2.5" class="cta-arrow" />
        </span>
      </div>
    </div>
  </NuxtLink>
</template>

<style scoped>
.course-card {
  display: flex; flex-direction: column; height: 100%;
  border-radius: 12px; border: 1px solid var(--line);
  background: var(--surface-strong, #fff);
  overflow: hidden; text-decoration: none; color: inherit;
  transition: transform 240ms ease, box-shadow 240ms ease, border-color 240ms ease;
}
.course-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 40px -16px rgba(31, 49, 43, 0.15);
  border-color: rgba(var(--primary-rgb), 0.25);
}

/* ── Thumbnail ── */
.card-thumb {
  position: relative; height: 180px; overflow: hidden;
  background: var(--green-soft); flex-shrink: 0;
}
.thumb-img {
  width: 100%; height: 100%; object-fit: cover;
  transition: transform 400ms ease;
}
.course-card:hover .thumb-img { transform: scale(1.05); }

.thumb-fallback {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 100%;
  color: var(--green); opacity: 0.4;
}

.thumb-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(10, 26, 20, 0.55) 0%, transparent 60%);
}

.thumb-badges {
  position: absolute; top: 10px; left: 10px;
  display: flex; flex-wrap: wrap; gap: 5px;
}
.badge {
  display: inline-flex; align-items: center; height: 22px; padding: 0 9px;
  border-radius: 999px; font-size: 0.675rem; font-weight: 700;
  letter-spacing: 0.04em; text-transform: uppercase;
}
.badge--free { background: var(--green); color: #fff; }
.badge--cat { background: rgba(255,255,255,0.88); color: #111; }

.thumb-footer {
  position: absolute; bottom: 10px; left: 10px; right: 10px;
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.instructor-name {
  font-size: 0.78rem; font-weight: 600; color: rgba(255,255,255,0.88);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;
}
.lesson-pill {
  flex-shrink: 0; height: 22px; padding: 0 9px; border-radius: 999px;
  background: rgba(255,255,255,0.9); font-size: 0.675rem; font-weight: 700; color: #111;
}

/* ── Body ── */
.card-body {
  display: flex; flex-direction: column; flex: 1; padding: 16px;
}
.card-title {
  margin: 0 0 7px; font-size: 0.9375rem; font-weight: 700;
  line-height: 1.35; letter-spacing: -0.02em; color: var(--text);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  transition: color 150ms;
}
.course-card:hover .card-title { color: var(--green-deep); }

.card-excerpt {
  margin: 0 0 11px; font-size: 0.8rem; line-height: 1.6;
  color: var(--muted);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

.card-meta {
  display: flex; flex-wrap: wrap; align-items: center;
  gap: 7px; font-size: 0.78rem; color: var(--muted); margin-bottom: 12px;
}
.meta-rating {
  display: inline-flex; align-items: center; gap: 3px;
  font-weight: 700; color: var(--text);
}
.meta-item { font-size: 0.76rem; }

/* ── Footer ── */
.card-footer {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  margin-top: auto; padding-top: 12px; border-top: 1px solid var(--line);
}
.price-label {
  margin: 0; font-size: 0.65rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted);
}
.price-value {
  margin: 2px 0 0; font-size: 1.05rem; font-weight: 800;
  letter-spacing: -0.03em; color: var(--green-deep);
}
.card-cta {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 0.78rem; font-weight: 700;
  color: var(--green-deep); white-space: nowrap;
}
.cta-arrow { transition: transform 150ms ease; }
.course-card:hover .cta-arrow { transform: translateX(3px); }

[data-theme="dark"] .course-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
</style>
