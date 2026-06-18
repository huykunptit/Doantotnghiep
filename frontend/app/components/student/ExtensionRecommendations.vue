<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { GraduationCap, Star } from 'lucide-vue-next'
import { useApi } from '~/composables/useApi'

interface RecItem {
  course: {
    id: number
    title: string
    slug: string
    price: number
    thumbnail?: string | null
    course_mode: string
    instructor?: { id: number; name: string }
    category?: { name: string }
  }
  score: number
  matched_skills: string[]
}

const props = defineProps<{ limit?: number }>()
const token = useAuthTokenCookie()

const items = ref<RecItem[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await useApi<{ recommendations: RecItem[] }>('/me/recommendations/extensions', {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    items.value = (response.recommendations || []).slice(0, props.limit || 6)
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải gợi ý.'
  } finally {
    loading.value = false
  }
}

function formatPrice(n: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n || 0)
}

onMounted(load)
</script>

<template>
  <section class="dashboard-card">
    <header class="rec-head">
      <div>
        <p class="section-kicker">Gợi ý mở rộng</p>
        <h3>Khóa học extension phù hợp với lộ trình của bạn</h3>
      </div>
      <NuxtLink to="/courses" class="rec-more">Xem tất cả →</NuxtLink>
    </header>

    <div v-if="error" class="crud-alert is-error">{{ error }}</div>

    <div v-if="loading" class="rec-skeleton">
      <div v-for="i in 3" :key="i" class="rec-skeleton-card" />
    </div>

    <div v-else-if="!items.length" class="crud-empty">
      Chưa có gợi ý phù hợp. Hãy hoàn thành thêm vài khóa core để có gợi ý tốt hơn.
    </div>

    <div v-else class="rec-grid">
      <NuxtLink
        v-for="item in items"
        :key="item.course.id"
        :to="`/courses/${item.course.slug || item.course.id}`"
        class="rec-card"
      >
        <div class="rec-thumb">
          <img v-if="item.course.thumbnail" :src="item.course.thumbnail" :alt="item.course.title">
          <GraduationCap v-else :size="36" :stroke-width="1.75" />
        </div>
        <div class="rec-body">
          <p class="rec-category">{{ item.course.category?.name || 'Extension' }}</p>
          <h4>{{ item.course.title }}</h4>
          <p class="rec-instructor">{{ item.course.instructor?.name || '' }}</p>
          <div class="rec-skills">
            <span v-for="skill in item.matched_skills" :key="skill" class="rec-skill-chip">{{ skill }}</span>
          </div>
          <div class="rec-foot">
            <strong class="rec-price">{{ item.course.price > 0 ? formatPrice(item.course.price) : 'Miễn phí' }}</strong>
            <span class="rec-score" :title="`Điểm gợi ý: ${item.score}`">
              <Star :size="14" :stroke-width="1.75" />
              {{ item.score }}
            </span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </section>
</template>

<style scoped>
.rec-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}
.rec-head h3 { margin: 4px 0 0; font-size: 1.15rem; letter-spacing: -0.02em; }
.rec-more {
  font-weight: 600;
  color: var(--green-deep);
  text-decoration: none;
}
.rec-more:hover { text-decoration: underline; }

.rec-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
}

.rec-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border-radius: 16px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
}
.rec-card:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--green-rgb), 0.32);
  box-shadow: 0 12px 24px -18px rgba(var(--green-rgb), 0.55);
}

.rec-thumb {
  height: 110px;
  border-radius: 12px;
  background: rgba(var(--green-rgb), 0.08);
  overflow: hidden;
  display: grid;
  place-items: center;
  color: var(--green-deep);
}
.rec-thumb img { width: 100%; height: 100%; object-fit: cover; }

.rec-body { display: grid; gap: 4px; }
.rec-category { color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin: 0; }
.rec-body h4 {
  margin: 2px 0;
  font-size: 0.96rem;
  font-weight: 700;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.rec-instructor { color: var(--muted); font-size: 0.8rem; margin: 0; }

.rec-skills {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}
.rec-skill-chip {
  font-size: 0.72rem;
  padding: 2px 8px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  border-radius: 999px;
  font-weight: 600;
}

.rec-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 6px;
}
.rec-price { color: var(--green-deep); font-size: 0.9rem; }
.rec-score {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
}
.rec-score svg { color: #ca8a04; }

.rec-skeleton {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
}
.rec-skeleton-card {
  height: 220px;
  border-radius: 16px;
  background: rgba(17, 17, 17, 0.04);
  animation: pulse 1.4s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .rec-card { background: var(--surface-strong); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .rec-skeleton-card { background: rgba(255, 255, 255, 0.05); }
[data-theme="dark"] .rec-head h3, [data-theme="dark"] .rec-body h4 { color: var(--text); }
</style>
