<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
// Icons removed - using PrimeIcons
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'auth' })

const courseStore = useCourseStore()
const loading = ref(true)
const enrollments = ref<any[]>([])

const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const activeEnrollment = computed(() => enrollments.value[0] ?? null)
const secondaryEnrollments = computed(() => enrollments.value.slice(1))

onMounted(async () => {
  loading.value = true
  enrollments.value = await courseStore.fetchEnrollments()
  loading.value = false
})
</script>

<template>
  <section class="mc-page">
    <!-- Header -->
    <header class="mc-header">
      <div>
        <h1 class="mc-title">Khóa học của tôi</h1>
        <p class="mc-subtitle">Theo dõi tiến độ và quay lại bài học đang học dở bất cứ lúc nào.</p>
      </div>
      <NuxtLink to="/courses" class="mc-explore-btn">Khám phá thêm</NuxtLink>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="mc-loading">
      <div class="mc-skeleton mc-skeleton--hero" />
      <div class="mc-skeleton mc-skeleton--side" />
    </div>

    <!-- Empty -->
    <UiEmptyState
      v-else-if="enrollments.length === 0"
      class="mc-empty"
      title="Chưa có khóa học nào"
      description="Bạn chưa đăng ký khóa học nào. Hãy khám phá ngay để không bỏ lỡ kiến thức!"
    >
      <template #icon>
        <i class="pi pi-graduation-cap" style="font-size:2.5rem" />
      </template>
      <NuxtLink to="/courses">
        <UiButton class="mt-4">Khám phá khóa học</UiButton>
      </NuxtLink>
    </UiEmptyState>

    <!-- Bento Grid -->
    <div v-else class="mc-bento">
      <!-- Featured (active) enrollment -->
      <NuxtLink :to="`/learn/${activeEnrollment.course_id}`" class="mc-featured">
        <div class="mc-featured-thumb">
          <img v-if="activeEnrollment.course?.thumbnail" :src="activeEnrollment.course.thumbnail" :alt="activeEnrollment.course?.title" class="mc-featured-img">
          <div v-else class="mc-featured-fallback">
            <i class="pi pi-graduation-cap" style="font-size:3.0rem" />
          </div>
          <div class="mc-featured-overlay" />
        </div>
        <div class="mc-featured-body">
          <div class="mc-featured-badges">
            <span class="mc-badge mc-badge--active">Đang học</span>
            <span class="mc-date">{{ formatDate(activeEnrollment.enrolled_at) }}</span>
          </div>
          <h3 class="mc-featured-title">{{ activeEnrollment.course?.title }}</h3>
          <p class="mc-featured-desc">Tiếp tục bài học của bạn và hoàn thành khối lượng kiến thức của khóa học này.</p>
          <div class="mc-progress-block">
            <div class="mc-progress-meta">
              <span>Tiến độ: {{ Math.round(activeEnrollment.progress || 0) }}%</span>
            </div>
            <div class="mc-progress-track">
              <div class="mc-progress-fill" :style="{ width: `${activeEnrollment.progress || 0}%` }" />
            </div>
          </div>
          <div class="mc-featured-cta">
            Tiếp tục học
            <i class="pi pi-arrow-right" style="font-size:0.9375rem" />
          </div>
        </div>
      </NuxtLink>

      <!-- Stats card -->
      <div class="mc-stats-card">
        <h4 class="mc-stats-title">Thống kê học tập</h4>
        <div class="mc-stats-list">
          <div class="mc-stat-row">
            <div class="mc-stat-icon mc-stat-icon--green">
              <i class="pi pi-graduation-cap" style="font-size:1.25rem" />
            </div>
            <div>
              <p class="mc-stat-val">{{ enrollments.length }}</p>
              <p class="mc-stat-lbl">Khóa học đã đăng ký</p>
            </div>
          </div>
          <div class="mc-stat-row">
            <div class="mc-stat-icon mc-stat-icon--blue">
              <CheckCheck :size="20" :stroke-width="1.75" />
            </div>
            <div>
              <p class="mc-stat-val">0</p>
              <p class="mc-stat-lbl">Chứng chỉ đạt được</p>
            </div>
          </div>
        </div>
        <blockquote class="mc-quote">"Đầu tư vào tri thức luôn mang lại lãi suất cao nhất."</blockquote>
      </div>

      <!-- Secondary cards -->
      <NuxtLink
        v-for="e in secondaryEnrollments"
        :key="e.id"
        :to="`/learn/${e.course_id}`"
        class="mc-card"
      >
        <div class="mc-card-thumb">
          <img v-if="e.course?.thumbnail" :src="e.course.thumbnail" :alt="e.course?.title" class="mc-card-img">
          <div v-else class="mc-card-fallback">
            <i class="pi pi-graduation-cap" style="font-size:2.0rem" />
          </div>
          <div v-if="e.progress >= 100" class="mc-card-badge mc-card-badge--done">Hoàn thành</div>
        </div>
        <div class="mc-card-body">
          <h4 class="mc-card-title">{{ e.course?.title }}</h4>
          <p class="mc-card-date">Đăng ký: {{ formatDate(e.enrolled_at) }}</p>
          <div class="mc-progress-track mc-progress-track--sm">
            <div class="mc-progress-fill" :style="{ width: `${e.progress || 0}%` }" />
          </div>
          <div class="mc-card-footer">
            <span class="mc-card-pct">{{ Math.round(e.progress || 0) }}%</span>
            <span class="mc-card-action">
              {{ e.progress >= 100 ? 'Xem lại' : 'Tiếp tục' }}
              <CheckCheck v-if="e.progress >= 100" :size="13" :stroke-width="2.5" />
              <Play v-else :size="12" :stroke-width="2.5" />
            </span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </section>
</template>

<style scoped>
.mc-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 16px;
  min-height: 80vh;
}

/* ── Header ── */
.mc-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  flex-wrap: wrap; gap: 16px; margin-bottom: 32px;
}
.mc-title {
  margin: 0 0 6px;
  font-family: 'Be Vietnam Pro', sans-serif; font-size: 2rem; font-weight: 800;
  letter-spacing: -0.04em; color: var(--text);
}
.mc-subtitle { margin: 0; font-size: 1rem; color: var(--muted); line-height: 1.6; }
.mc-explore-btn {
  display: inline-flex; padding: 10px 22px; border-radius: 8px;
  border: 1px solid var(--line); background: var(--surface-strong, #fff);
  font-size: 0.875rem; font-weight: 600; color: var(--text);
  text-decoration: none; transition: background 150ms, transform 150ms;
  white-space: nowrap;
}
.mc-explore-btn:hover { background: var(--surface); transform: translateY(-1px); }

/* ── Loading ── */
.mc-loading { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
.mc-skeleton {
  border-radius: 12px; background: var(--surface);
  animation: pulse 1.4s ease infinite;
}
.mc-skeleton--hero { height: 320px; }
.mc-skeleton--side { height: 320px; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.45; } }

/* ── Empty ── */
.mc-empty { padding: 48px 0; }

/* ── Bento Grid ── */
.mc-bento {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 20px;
  align-items: start;
}

/* ── Featured ── */
.mc-featured {
  grid-column: span 8;
  display: flex; gap: 28px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line); border-radius: 12px;
  overflow: hidden; text-decoration: none; color: inherit;
  transition: transform 250ms, box-shadow 250ms, border-color 250ms;
}
.mc-featured:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 36px -16px rgba(31, 49, 43, 0.14);
  border-color: rgba(var(--primary-rgb), 0.25);
}
.mc-featured-thumb {
  position: relative; width: 240px; flex-shrink: 0;
  background: var(--green-soft);
}
.mc-featured-img { width: 100%; height: 100%; object-fit: cover; transition: transform 400ms; }
.mc-featured:hover .mc-featured-img { transform: scale(1.05); }
.mc-featured-fallback {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 100%; color: var(--green); opacity: 0.4;
}
.mc-featured-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to right, transparent 50%, rgba(10, 26, 20, 0.25));
}
.mc-featured-body {
  flex: 1; padding: 24px 24px 24px 0;
  display: flex; flex-direction: column; justify-content: center; gap: 12px;
}
.mc-featured-badges {
  display: flex; align-items: center; gap: 10px;
}
.mc-badge {
  display: inline-flex; padding: 3px 10px; border-radius: 999px;
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
}
.mc-badge--active { background: var(--secondary-soft, rgba(55,138,221,0.12)); color: var(--secondary, #378ADD); }
.mc-date { font-size: 0.8rem; color: var(--muted); }
.mc-featured-title {
  margin: 0; font-family: 'Be Vietnam Pro', sans-serif; font-size: 1.3rem;
  font-weight: 700; line-height: 1.3; color: var(--text);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  transition: color 150ms;
}
.mc-featured:hover .mc-featured-title { color: var(--green-deep); }
.mc-featured-desc {
  margin: 0; font-size: 0.875rem; color: var(--muted); line-height: 1.6;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* ── Progress ── */
.mc-progress-block { display: flex; flex-direction: column; gap: 6px; }
.mc-progress-meta { font-size: 0.8rem; font-weight: 600; color: var(--text); }
.mc-progress-track {
  width: 100%; height: 6px; background: var(--surface); border-radius: 99px; overflow: hidden;
}
.mc-progress-track--sm { height: 4px; }
.mc-progress-fill {
  height: 100%; background: var(--green); border-radius: 99px;
  transition: width 500ms ease-out;
}

.mc-featured-cta {
  display: inline-flex; align-items: center; gap: 6px;
  width: fit-content; padding: 10px 20px; border-radius: 8px;
  background: var(--green); color: #fff; font-size: 0.875rem; font-weight: 700;
  transition: background 150ms;
}
.mc-featured:hover .mc-featured-cta { background: var(--green-deep); }
.mc-cta-arrow { transition: transform 150ms; }
.mc-featured:hover .mc-cta-arrow { transform: translateX(3px); }

/* ── Stats card ── */
.mc-stats-card {
  grid-column: span 4;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line); border-radius: 12px;
  padding: 24px; display: flex; flex-direction: column; gap: 20px;
}
.mc-stats-title {
  margin: 0; font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted);
}
.mc-stats-list { display: flex; flex-direction: column; gap: 16px; }
.mc-stat-row { display: flex; align-items: center; gap: 14px; }
.mc-stat-icon {
  display: flex; align-items: center; justify-content: center;
  width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
}
.mc-stat-icon--green { background: var(--green-soft); color: var(--green); }
.mc-stat-icon--blue { background: rgba(55,138,221,0.1); color: var(--secondary, #378ADD); }
.mc-stat-val {
  margin: 0 0 2px; font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.5rem; font-weight: 800; color: var(--text);
}
.mc-stat-lbl { margin: 0; font-size: 0.75rem; color: var(--muted); }
.mc-quote {
  margin: 0; padding-top: 16px; border-top: 1px solid var(--line);
  font-size: 0.8rem; font-style: italic; color: var(--muted); line-height: 1.6;
}

/* ── Secondary cards ── */
.mc-card {
  grid-column: span 4;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line); border-radius: 12px;
  overflow: hidden; text-decoration: none; color: inherit;
  transition: transform 250ms, box-shadow 250ms, border-color 250ms;
  display: flex; flex-direction: column;
}
.mc-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px -12px rgba(31, 49, 43, 0.14);
  border-color: rgba(var(--primary-rgb), 0.2);
}
.mc-card-thumb {
  position: relative; height: 140px; overflow: hidden; background: var(--green-soft);
}
.mc-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 400ms; }
.mc-card:hover .mc-card-img { transform: scale(1.05); }
.mc-card-fallback {
  display: flex; align-items: center; justify-content: center;
  width: 100%; height: 100%; color: var(--green); opacity: 0.4;
}
.mc-card-badge {
  position: absolute; top: 10px; right: 10px;
  padding: 3px 10px; border-radius: 999px;
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
}
.mc-card-badge--done { background: var(--green); color: #fff; }
.mc-card-body { padding: 14px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.mc-card-title {
  margin: 0; font-size: 0.9375rem; font-weight: 700; color: var(--text);
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  transition: color 150ms;
}
.mc-card:hover .mc-card-title { color: var(--green-deep); }
.mc-card-date { margin: 0; font-size: 0.78rem; color: var(--muted); }
.mc-card-footer {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: auto; padding-top: 6px;
}
.mc-card-pct { font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
.mc-card-action {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 0.8rem; font-weight: 700; color: var(--green);
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .mc-featured { grid-column: span 12; }
  .mc-stats-card { grid-column: span 12; }
  .mc-card { grid-column: span 6; }
}
@media (max-width: 640px) {
  .mc-featured { grid-column: span 12; flex-direction: column; }
  .mc-featured-thumb { width: 100%; height: 180px; }
  .mc-featured-body { padding: 16px; }
  .mc-card { grid-column: span 12; }
  .mc-loading { grid-template-columns: 1fr; }
}

[data-theme="dark"] .mc-featured,
[data-theme="dark"] .mc-stats-card,
[data-theme="dark"] .mc-card {
  background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08);
}
</style>
