<template>
  <InstructorWorkspaceShell
    title="Học viên khóa học"
    description="Theo dõi danh sách học viên, tiến độ hoàn thành và thời điểm đăng ký."
    :breadcrumb="['Trang chủ', 'Khóa học', 'Học viên']"
  >
    <template #actions>
      <NuxtLink to="/instructor/courses" class="crud-secondary-btn">
        <span class="material-symbols-outlined">arrow_back</span>
        Quay lại
      </NuxtLink>
    </template>

    <!-- Summary cards (shown after load) -->
    <div v-if="!loading && students.length > 0" class="ds-stats mb-0">
      <div class="ds-stat ds-stat--blue">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">people</span></div>
        <p class="ds-stat-label">Tổng học viên</p>
        <strong class="ds-stat-value">{{ total }}</strong>
        <span class="ds-stat-sub">ghi danh</span>
      </div>
      <div class="ds-stat ds-stat--green">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">check_circle</span></div>
        <p class="ds-stat-label">Đã hoàn thành</p>
        <strong class="ds-stat-value">{{ completedCount }}</strong>
        <span class="ds-stat-sub">học viên</span>
      </div>
      <div class="ds-stat ds-stat--amber">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">trending_up</span></div>
        <p class="ds-stat-label">Tiến độ trung bình</p>
        <strong class="ds-stat-value">{{ avgProgress }}%</strong>
        <span class="ds-stat-sub">hoàn thành</span>
      </div>
    </div>

    <!-- Content -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent="doSearch">
          <input
            v-model="search"
            type="text"
            placeholder="Tìm theo tên hoặc email..."
            class="crud-search"
            @keyup.enter="doSearch"
          >
          <button type="submit" class="crud-primary-btn">
            <span class="material-symbols-outlined">search</span>
            Tìm kiếm
          </button>
        </form>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="crud-empty" style="padding: 3rem;">Đang tải...</div>

      <!-- Empty -->
      <div v-else-if="students.length === 0" class="crud-empty">
        <span class="material-symbols-outlined" style="font-size:48px;opacity:0.2;">person_off</span>
        <div>
          <strong>Chưa có học viên</strong>
          <p>Danh sách học viên sẽ xuất hiện khi có người đăng ký khóa học.</p>
        </div>
      </div>

      <!-- List -->
      <div v-else class="student-list">
        <div v-for="item in students" :key="item.id" class="student-card">
          <div class="student-card-main">
            <!-- Avatar + name -->
            <div class="student-info">
              <div class="student-avatar">{{ item.user?.name?.slice(0, 2).toUpperCase() || 'HV' }}</div>
              <div class="student-meta">
                <strong>{{ item.user?.name || '—' }}</strong>
                <p>{{ item.user?.email || '—' }}</p>
              </div>
            </div>

            <!-- Progress bar -->
            <div class="student-progress">
              <div class="progress-head">
                <span>Tiến độ</span>
                <strong :class="item.progress_percent >= 100 ? 'text-complete' : 'text-progress'">
                  {{ item.progress_percent || 0 }}%
                </strong>
              </div>
              <div class="progress-track">
                <div
                  class="progress-bar"
                  :class="item.progress_percent >= 100 ? 'is-complete' : 'is-progress'"
                  :style="{ width: `${item.progress_percent || 0}%` }"
                />
              </div>
            </div>

            <!-- Stats -->
            <div class="student-stats">
              <div class="stat-item">
                <p>Bài học</p>
                <strong>{{ item.completed_lessons }}/{{ item.total_lessons }}</strong>
              </div>
              <div class="stat-item">
                <p>Đăng ký</p>
                <span>{{ formatDate(item.enrolled_at) }}</span>
              </div>
              <div class="stat-item">
                <p>Trạng thái</p>
                <span class="status-badge" :class="item.progress_percent >= 100 ? 'is-complete' : 'is-active'">
                  {{ item.progress_percent >= 100 ? 'Hoàn thành' : 'Đang học' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="crud-pagination">
        <p>Trang <strong>{{ currentPage }}</strong> / {{ lastPage }} · {{ total }} học viên</p>
        <div class="crud-pagination-btns">
          <button type="button" class="crud-secondary-btn" :disabled="currentPage <= 1" @click="changePage(currentPage - 1)">
            ← Trước
          </button>
          <button type="button" class="crud-secondary-btn" :disabled="currentPage >= lastPage" @click="changePage(currentPage + 1)">
            Sau →
          </button>
        </div>
      </div>
    </section>
  </InstructorWorkspaceShell>
</template>

<script setup lang="ts">
// @ts-nocheck
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const loading = ref(true)
const search = ref('')
const students = ref<any[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 10

const formatDate = (v: string) => new Date(v).toLocaleDateString('vi-VN')

const completedCount = computed(() =>
  students.value.filter(s => (s.progress_percent || 0) >= 100).length
)

const avgProgress = computed(() => {
  if (!students.value.length) return 0
  const sum = students.value.reduce((acc, s) => acc + (s.progress_percent || 0), 0)
  return Math.round(sum / students.value.length)
})

async function loadData(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage) })
    if (search.value.trim()) query.set('search', search.value.trim())
    const res = await useApi<any>(`/instructor/courses/${courseId}/students?${query}`, { token: auth.token })
    students.value = res.data || []
    currentPage.value = res.current_page || page
    lastPage.value = res.last_page || 1
    total.value = res.total || students.value.length
  }
  finally {
    loading.value = false
  }
}

function doSearch() {
  currentPage.value = 1
  loadData(1)
}

function changePage(page: number) {
  loadData(page)
}

onMounted(() => loadData())
</script>

<style scoped>
.student-list { display: grid; gap: 12px; padding: 4px 0; }
.student-card {
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 16px 20px;
  background: var(--bg);
  transition: box-shadow 0.2s, border-color 0.2s;
}
.student-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.05); border-color: rgba(var(--green-rgb),0.2); }
.student-card-main {
  display: grid;
  grid-template-columns: 200px 1fr 280px;
  align-items: center;
  gap: 20px;
}
@media (max-width: 900px) { .student-card-main { grid-template-columns: 1fr; } }

.student-info { display: flex; align-items: center; gap: 12px; }
.student-avatar {
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  background: rgba(var(--green-rgb),0.1); color: var(--green-deep);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 800;
}
.student-meta strong { display: block; font-size: 0.9rem; color: var(--text); }
.student-meta p { margin: 3px 0 0; font-size: 0.78rem; color: var(--muted); }

.student-progress { display: flex; flex-direction: column; gap: 6px; }
.progress-head { display: flex; justify-content: space-between; font-size: 0.78rem; }
.progress-head span { color: var(--muted); font-weight: 600; }
.text-complete { color: var(--green-deep); }
.text-progress { color: #378add; }
.progress-track { height: 6px; border-radius: 999px; background: var(--bg); border: 1px solid var(--line); overflow: hidden; }
.progress-bar { height: 100%; border-radius: 999px; transition: width 0.5s ease; }
.progress-bar.is-complete { background: var(--green); }
.progress-bar.is-progress { background: #378add; }

.student-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.stat-item { text-align: center; }
.stat-item p { margin: 0; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
.stat-item strong, .stat-item span { display: block; margin-top: 4px; font-size: 0.88rem; font-weight: 700; color: var(--text); }

.status-badge {
  display: inline-flex; align-items: center; height: 22px; padding: 0 10px;
  border-radius: 999px; font-size: 0.72rem; font-weight: 700; border: 1px solid transparent;
}
.status-badge.is-complete { background: rgba(29,158,117,0.1); color: var(--green-deep); border-color: rgba(29,158,117,0.2); }
.status-badge.is-active   { background: rgba(55,138,221,0.1); color: #1a5fa8;           border-color: rgba(55,138,221,0.2); }

.crud-pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px 4px; font-size: 0.85rem; color: var(--muted); border-top: 1px solid var(--line); margin-top: 8px;
}
.crud-pagination-btns { display: flex; gap: 8px; }
</style>
