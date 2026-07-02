<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const courses = ref<any[]>([])
const categoryFilter = ref('all')
const freeFilter = ref<'all' | 'free' | 'paid'>('all')
const search = ref('')

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  const [r0] = await Promise.allSettled([
    useApi<any>('/me/recommendations/extensions', { headers: h }),
  ])
  if (r0.status === 'fulfilled') {
    const d = r0.value
    courses.value = Array.isArray(d) ? d : (d?.data || d?.courses || [])
  }
  loading.value = false
})

const categories = computed(() => {
  const cats = new Set(courses.value.map(c => c.category?.name || c.category || '').filter(Boolean))
  return ['all', ...Array.from(cats)]
})

const filtered = computed(() => {
  let list = courses.value
  if (categoryFilter.value !== 'all') list = list.filter(c => (c.category?.name || c.category) === categoryFilter.value)
  if (freeFilter.value === 'free') list = list.filter(c => !c.price || c.price === 0)
  if (freeFilter.value === 'paid') list = list.filter(c => c.price && c.price > 0)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(c => (c.title || '').toLowerCase().includes(q) || (c.instructor?.name || '').toLowerCase().includes(q))
  }
  return list
})

function formatPrice(p?: number) {
  if (!p || p === 0) return 'Miễn phí'
  return p.toLocaleString('vi-VN') + '₫'
}
</script>

<template>
  <div class="rc-page">
    <!-- Header -->
    <div class="rc-hero">
      <div class="rc-hero-text">
        <p class="section-kicker">Khám phá</p>
        <h1 class="rc-title">Gợi ý dành cho bạn</h1>
        <p class="rc-subtitle">Các khóa học được AI chọn lọc dựa trên lộ trình học và kỹ năng của bạn.</p>
      </div>
      <div class="rc-search-wrap">
        <SylvaIcon name="search" :size="15" class="rc-search-icon" />
        <input v-model="search" type="search" placeholder="Tìm khóa học..." class="rc-search" />
      </div>
    </div>

    <!-- Filters -->
    <div class="rc-filters">
      <div class="rc-filter-group">
        <span class="rc-filter-label">Danh mục:</span>
        <div class="rc-tabs">
          <button v-for="cat in categories" :key="cat" class="rc-tab" :class="{active: categoryFilter===cat}"
            @click="categoryFilter=cat">{{ cat === 'all' ? 'Tất cả' : cat }}</button>
        </div>
      </div>
      <div class="rc-filter-group">
        <span class="rc-filter-label">Giá:</span>
        <div class="rc-tabs">
          <button v-for="t in [{k:'all',l:'Tất cả'},{k:'free',l:'Miễn phí'},{k:'paid',l:'Có phí'}]"
            :key="t.k" class="rc-tab" :class="{active: freeFilter===t.k}"
            @click="freeFilter=t.k as any">{{ t.l }}</button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="rc-grid">
      <div v-for="i in 8" :key="i" class="rc-skeleton">
        <span class="sd-shimmer" style="width:100%;height:150px;display:block;border-radius:0"></span>
        <div style="padding:14px;display:flex;flex-direction:column;gap:7px">
          <span class="sd-shimmer" style="height:13px;width:75%;display:block"></span>
          <span class="sd-shimmer" style="height:11px;width:50%;display:block"></span>
          <span class="sd-shimmer" style="height:11px;width:35%;display:block"></span>
        </div>
      </div>
    </div>

    <!-- Grid -->
    <div v-else-if="filtered.length" class="rc-grid">
      <div v-for="course in filtered" :key="course.id" class="rc-card">
        <div class="rc-card-thumb">
          <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" loading="lazy">
          <div v-else class="rc-thumb-fallback">
            <SylvaIcon name="book-open" :size="28" />
          </div>
          <div v-if="course.match_score" class="rc-score-badge">
            <SylvaIcon name="sparkles" :size="10" /> {{ Math.round(course.match_score * 100) }}% phù hợp
          </div>
        </div>
        <div class="rc-card-body">
          <p v-if="course.category?.name || course.category" class="rc-card-cat">{{ course.category?.name || course.category }}</p>
          <h3 class="rc-card-title">{{ course.title }}</h3>
          <p class="rc-card-instructor">{{ course.instructor?.name || course.teacher_name || '' }}</p>

          <!-- Skill chips -->
          <div v-if="course.matched_skills?.length" class="rc-skills">
            <span v-for="s in course.matched_skills.slice(0,3)" :key="s" class="rc-skill-chip">{{ s }}</span>
          </div>

          <div class="rc-card-footer">
            <span class="rc-price" :class="{free: !course.price || course.price===0}">{{ formatPrice(course.price) }}</span>
            <NuxtLink :to="`/courses/${course.id}`" class="rc-btn-enroll">Xem khóa học</NuxtLink>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="sd-empty">
      <SylvaIcon name="sparkles" :size="40" />
      <p>Không tìm thấy gợi ý nào.</p>
    </div>
  </div>
</template>

<style scoped>
.rc-page { display: flex; flex-direction: column; gap: 20px; }
.rc-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.rc-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin: 4px 0 6px; }
.rc-subtitle { font-size: 0.86rem; color: var(--muted); margin: 0; max-width: 500px; }
.rc-search-wrap { position: relative; }
.rc-search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.rc-search {
  padding: 9px 14px 9px 34px; border: 1px solid var(--line); border-radius: 10px;
  background: var(--surface-strong); color: var(--text); font-size: 0.84rem; outline: none; width: 240px;
}
.rc-search:focus { border-color: var(--green); }

.rc-filters { display: flex; flex-wrap: wrap; gap: 12px 24px; align-items: center; }
.rc-filter-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rc-filter-label { font-size: 0.78rem; font-weight: 600; color: var(--muted); white-space: nowrap; }
.rc-tabs { display: flex; gap: 4px; flex-wrap: wrap; }
.rc-tab {
  padding: 4px 10px; border-radius: 7px; border: 1px solid var(--line);
  background: transparent; color: var(--muted);
  font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: background 150ms;
}
.rc-tab:hover { background: var(--bg); color: var(--text); }
.rc-tab.active { background: var(--green-soft); color: var(--green-deep); border-color: var(--green); }

.rc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
.rc-card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: var(--surface-strong); box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.08)); transition: transform 150ms, box-shadow 150ms; }
.rc-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.rc-skeleton { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }

.rc-card-thumb { position: relative; aspect-ratio: 16/9; background: var(--bg); overflow: hidden; }
.rc-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
.rc-thumb-fallback { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: var(--green-soft); color: var(--green); }
.rc-score-badge {
  position: absolute; top: 8px; right: 8px;
  display: flex; align-items: center; gap: 3px;
  font-size: 0.68rem; font-weight: 700;
  background: rgba(0,0,0,0.6); color: #fbbf24;
  padding: 2px 8px; border-radius: 20px; backdrop-filter: blur(4px);
}

.rc-card-body { padding: 12px 14px 14px; }
.rc-card-cat { font-size: 0.68rem; font-weight: 700; color: var(--muted); margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.05em; }
.rc-card-title { font-size: 0.9rem; font-weight: 700; color: var(--text); margin: 0 0 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rc-card-instructor { font-size: 0.75rem; color: var(--muted); margin: 0 0 8px; }
.rc-skills { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
.rc-skill-chip {
  font-size: 0.66rem; font-weight: 600;
  padding: 2px 8px; border-radius: 20px;
  background: var(--secondary-soft); color: var(--secondary);
}
.rc-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 10px; border-top: 1px solid var(--line); }
.rc-price { font-size: 0.88rem; font-weight: 800; color: var(--text); }
.rc-price.free { color: var(--green, #0F6E8C); }
.rc-btn-enroll {
  padding: 5px 12px; border-radius: 7px;
  background: var(--green-soft); color: var(--green-deep);
  font-size: 0.76rem; font-weight: 700; text-decoration: none;
  transition: background 150ms;
}
.rc-btn-enroll:hover { background: rgba(15,110,140,0.2); }

.sd-shimmer { background: linear-gradient(90deg, var(--line) 25%, var(--bg) 50%, var(--line) 75%); background-size: 200% 100%; animation: sd-shimmer 1.5s infinite; border-radius: 6px; }
@keyframes sd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.sd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: var(--muted); gap: 10px; }
.sd-empty p { font-size: 0.9rem; }

[data-theme="dark"] .rc-card { background: var(--surface); }
[data-theme="dark"] .rc-search { background: var(--surface); }
[data-theme="dark"] .rc-tab.active { background: rgba(52,211,153,0.15); color: #6ee7b7; border-color: rgba(52,211,153,0.4); }

@media (max-width: 640px) {
  .rc-hero { flex-direction: column; align-items: flex-start; }
  .rc-search { width: 100%; }
  .rc-search-wrap { width: 100%; }
}
</style>
