<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const enrollments = ref<any[]>([])
const search = ref('')
const filterStatus = ref<'all' | 'in_progress' | 'completed'>('all')

onMounted(async () => {
  try {
    const data = await useApi<any[]>('/user/enrollments', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    enrollments.value = data || []
  } finally {
    loading.value = false
  }
})

const filtered = computed(() => {
  let list = enrollments.value
  if (filterStatus.value === 'in_progress') list = list.filter(e => e.progress < 100)
  if (filterStatus.value === 'completed') list = list.filter(e => e.progress >= 100)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(e => e.course?.title?.toLowerCase().includes(q))
  }
  return list
})

const stats = computed(() => ({
  total: enrollments.value.length,
  inProgress: enrollments.value.filter(e => e.progress < 100).length,
  completed: enrollments.value.filter(e => e.progress >= 100).length,
}))
</script>

<template>
  <div class="sc-page">
    <div class="sc-header">
      <div>
        <p class="sc-kicker">Học tập</p>
        <h1 class="sc-title">Khóa học của tôi</h1>
      </div>
      <NuxtLink to="/courses" class="sc-cta">Khám phá thêm →</NuxtLink>
    </div>

    <!-- Stats -->
    <div class="sc-stats">
      <button class="sc-stat" :class="{ 'is-active': filterStatus === 'all' }" @click="filterStatus = 'all'">
        <span class="sc-stat-num">{{ stats.total }}</span>
        <span class="sc-stat-label">Tất cả</span>
      </button>
      <button class="sc-stat" :class="{ 'is-active': filterStatus === 'in_progress' }" @click="filterStatus = 'in_progress'">
        <span class="sc-stat-num">{{ stats.inProgress }}</span>
        <span class="sc-stat-label">Đang học</span>
      </button>
      <button class="sc-stat" :class="{ 'is-active': filterStatus === 'completed' }" @click="filterStatus = 'completed'">
        <span class="sc-stat-num">{{ stats.completed }}</span>
        <span class="sc-stat-label">Hoàn thành</span>
      </button>
    </div>

    <!-- Search -->
    <div class="sc-search-wrap">
      <i class="pi pi-search" style="font-size:1.0rem" />
      <input v-model="search" class="sc-search" type="text" placeholder="Tìm khóa học...">
    </div>

    <!-- Course grid -->
    <div v-if="loading" class="sc-grid">
      <div v-for="i in 6" :key="i" class="sc-skeleton" />
    </div>
    <div v-else-if="filtered.length === 0" class="sc-empty">
      <i class="pi pi-book" style="font-size:2.5rem" />
      <p>Không tìm thấy khóa học nào.</p>
      <NuxtLink to="/courses" class="sc-empty-link">Khám phá khóa học mới</NuxtLink>
    </div>
    <div v-else class="sc-grid">
      <NuxtLink
        v-for="e in filtered"
        :key="e.id"
        :to="`/learn/${e.course?.id}`"
        class="sc-card"
      >
        <div class="sc-thumb-wrap">
          <img :src="e.course?.thumbnail || 'https://placehold.co/600x400/e1f5ee/085041?text=Course'" :alt="e.course?.title" class="sc-thumb" />
          <div class="sc-progress-overlay">
            <div class="sc-progress-bar">
              <div class="sc-progress-fill" :style="{ width: `${e.progress}%` }" :class="{ 'is-done': e.progress >= 100 }" />
            </div>
            <span class="sc-progress-pct">{{ Math.round(e.progress) }}%</span>
          </div>
        </div>
        <div class="sc-card-body">
          <span class="sc-card-cat">{{ e.course?.category?.name || 'Khóa học' }}</span>
          <h3 class="sc-card-title">{{ e.course?.title }}</h3>
          <p v-if="e.course?.instructor?.name" class="sc-card-instructor">{{ e.course.instructor.name }}</p>
          <div class="sc-card-foot">
            <span class="sc-card-badge" :class="e.progress >= 100 ? 'is-done' : 'is-progress'">
              {{ e.progress >= 100 ? 'Hoàn thành' : 'Đang học' }}
            </span>
            <span class="sc-card-cta">{{ e.progress > 0 ? (e.progress >= 100 ? 'Xem lại' : 'Tiếp tục') : 'Bắt đầu' }} →</span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<style scoped>
.sc-page { max-width: 1200px; margin: 0 auto; }

.sc-header {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 24px;
}
.sc-kicker {
  margin: 0 0 4px; font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted);
}
.sc-title { margin: 0; font-size: 1.7rem; font-weight: 800; color: var(--text); letter-spacing: -0.02em; }
.sc-cta { font-size: 0.84rem; font-weight: 700; color: var(--green); text-decoration: none; }
.sc-cta:hover { text-decoration: underline; }

/* Stats tabs */
.sc-stats { display: flex; gap: 10px; margin-bottom: 20px; }
.sc-stat {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 12px 24px; border-radius: 12px;
  background: var(--surface-strong); border: 1px solid var(--line);
  cursor: pointer; transition: background 150ms, border-color 150ms;
}
.sc-stat:hover { background: var(--bg); }
.sc-stat.is-active {
  background: var(--green-soft, #e1f5ee);
  border-color: rgba(29,158,117,0.3);
}
.sc-stat-num { font-size: 1.4rem; font-weight: 800; color: var(--text); line-height: 1; }
.sc-stat.is-active .sc-stat-num { color: var(--green-deep); }
.sc-stat-label { font-size: 0.72rem; font-weight: 600; color: var(--muted); }

/* Search */
.sc-search-wrap {
  position: relative; margin-bottom: 24px;
  max-width: 420px;
}
.sc-search-icon {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--muted); pointer-events: none;
}
.sc-search {
  width: 100%; padding: 10px 14px 10px 38px;
  border-radius: 10px; border: 1px solid var(--line);
  background: var(--surface-strong);
  font-size: 0.875rem; color: var(--text);
  outline: none; transition: border-color 150ms;
}
.sc-search:focus { border-color: var(--green); }

/* Grid */
.sc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}
.sc-skeleton {
  height: 300px; border-radius: 16px;
  background: linear-gradient(90deg, var(--line) 25%, rgba(221,229,225,0.5) 50%, var(--line) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Card */
.sc-card {
  display: flex; flex-direction: column;
  background: var(--surface-strong); border: 1px solid var(--line);
  border-radius: 16px; overflow: hidden; text-decoration: none; color: inherit;
  transition: transform 200ms, box-shadow 200ms, border-color 200ms;
}
.sc-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px -12px rgba(8,80,65,0.15);
  border-color: rgba(29,158,117,0.3);
}
.sc-thumb-wrap { position: relative; padding-top: 56%; overflow: hidden; background: var(--green-soft); }
.sc-thumb { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 300ms; }
.sc-card:hover .sc-thumb { transform: scale(1.04); }
.sc-progress-overlay {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: 6px 10px 8px;
  background: linear-gradient(0deg, rgba(0,0,0,0.6) 0%, transparent 100%);
  display: flex; align-items: center; gap: 8px;
}
.sc-progress-bar { flex: 1; height: 4px; background: rgba(255,255,255,0.3); border-radius: 99px; overflow: hidden; }
.sc-progress-fill { height: 100%; background: var(--green); border-radius: 99px; transition: width 600ms; }
.sc-progress-fill.is-done { background: #5de8b7; }
.sc-progress-pct { font-size: 0.68rem; font-weight: 800; color: #fff; white-space: nowrap; }

.sc-card-body { padding: 14px 16px 16px; display: flex; flex-direction: column; flex: 1; }
.sc-card-cat { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--green); margin-bottom: 6px; }
.sc-card-title {
  margin: 0 0 6px; font-size: 0.95rem; font-weight: 700; color: var(--text); line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.sc-card-instructor { margin: 0 0 10px; font-size: 0.75rem; color: var(--muted); }
.sc-card-foot { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.sc-card-badge {
  font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 99px;
}
.sc-card-badge.is-progress { background: rgba(234,179,8,0.12); color: #ca8a04; }
.sc-card-badge.is-done { background: var(--green-soft); color: var(--green-deep); }
.sc-card-cta { font-size: 0.78rem; font-weight: 700; color: var(--green); }

/* Empty */
.sc-empty {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  padding: 60px 20px; text-align: center;
}
.sc-empty-icon { color: var(--muted); opacity: 0.4; }
.sc-empty p { margin: 0; color: var(--muted); font-size: 0.9rem; }
.sc-empty-link { font-size: 0.875rem; font-weight: 700; color: var(--green); text-decoration: underline; }

/* Dark */
[data-theme="dark"] .sc-card { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.07); }
[data-theme="dark"] .sc-stat { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.08); }
[data-theme="dark"] .sc-search { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); color: var(--text); }

@media (max-width: 640px) {
  .sc-grid { grid-template-columns: 1fr; }
  .sc-stats { flex-wrap: wrap; }
}
</style>
