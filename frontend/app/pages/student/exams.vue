<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const exams = ref<any[]>([])
const searchQuery = ref('')
const activeTab = ref<'all' | 'upcoming' | 'live' | 'done'>('all')

onMounted(async () => {
  try {
    const res = await useApi<{ exams: any[] }>('/me/exams', {
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    exams.value = res.exams || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

function formatDate(rawStr?: string) {
  if (!rawStr) return 'Tự do'
  return new Date(rawStr).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Client-side filtering
const filteredExams = computed(() => {
  let list = exams.value

  // Tab filter
  if (activeTab.value !== 'all') {
    list = list.filter(e => e.status === activeTab.value)
  }

  // Search query filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(e => 
      e.title.toLowerCase().includes(q) || 
      (e.description && e.description.toLowerCase().includes(q))
    )
  }

  return list
})

const tabCounts = computed(() => {
  return {
    all: exams.value.length,
    upcoming: exams.value.filter(e => e.status === 'upcoming').length,
    live: exams.value.filter(e => e.status === 'active').length,
    done: exams.value.filter(e => e.status === 'completed' || e.status === 'closed').length
  }
})
</script>

<template>
  <div class="exams-container">
    <!-- Header -->
    <div class="exams-header-bar">
      <div>
        <p class="section-kicker">Khảo thí & Đánh giá</p>
        <h1 class="exams-page-title">Kỳ Thi Của Tôi</h1>
        <p class="exams-page-sub">Danh sách các đợt thi học kỳ, thi thử và kiểm tra chất lượng</p>
      </div>

      <!-- Search Box -->
      <div class="search-box-wrap">
        <i class="pi pi-search" style="font-size:1.0rem" />
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Tìm kiếm kỳ thi..." 
          class="search-input" 
        />
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="exams-tabs-bar">
      <button 
        class="tab-item-btn" 
        :class="{ 'is-active': activeTab === 'all' }" 
        @click="activeTab = 'all'"
      >
        Tất cả
        <span class="count-badge">{{ tabCounts.all }}</span>
      </button>
      <button 
        class="tab-item-btn" 
        :class="{ 'is-active': activeTab === 'upcoming' }" 
        @click="activeTab = 'upcoming'"
      >
        Sắp diễn ra
        <span class="count-badge">{{ tabCounts.upcoming }}</span>
      </button>
      <button 
        class="tab-item-btn" 
        :class="{ 'is-active': activeTab === 'live' }" 
        @click="activeTab = 'live'"
      >
        Đang diễn ra
        <span class="count-badge bg-danger text-white">{{ tabCounts.live }}</span>
      </button>
      <button 
        class="tab-item-btn" 
        :class="{ 'is-active': activeTab === 'done' }" 
        @click="activeTab = 'done'"
      >
        Đã hoàn thành / Kết thúc
        <span class="count-badge">{{ tabCounts.done }}</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="exams-grid-layout">
      <div v-for="i in 3" :key="i" class="shimmer-card dashboard-card"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredExams.length === 0" class="exams-empty-state dashboard-card">
      <i class="pi pi-list" style="font-size:3.0rem" />
      <h3>Không tìm thấy kỳ thi nào</h3>
      <p>Không có kỳ thi nào khớp với bộ lọc hiện tại của bạn.</p>
    </div>

    <!-- Exams Grid -->
    <div v-else class="exams-grid-layout">
      <div 
        v-for="exam in filteredExams" 
        :key="exam.id" 
        class="exam-card-item dashboard-card"
        :class="[exam.status, { 'is-active-now': exam.is_open }]"
      >
        <!-- Status indicator bar -->
        <div class="card-status-bar" :class="exam.status"></div>

        <div class="exam-card-content">
          <!-- Top row (Type & Proctoring) -->
          <div class="exam-card-meta-top">
            <span class="type-tag" :class="exam.type">
              {{ exam.type === 'course_final' ? 'Thi học phần' : 'Thi độc lập' }}
            </span>
            <span class="proctoring-tag" v-if="exam.proctoring_enabled">
              <i class="pi pi-lock" style="font-size:0.75rem" /> Giám sát AI
            </span>
          </div>

          <!-- Title & Description -->
          <h3 class="exam-title-name">{{ exam.title }}</h3>
          <p class="exam-desc-snippet" v-if="exam.description">{{ exam.description }}</p>

          <!-- Exam Details Grid -->
          <div class="exam-details-grid">
            <div class="detail-row">
              <i class="pi pi-calendar" style="font-size:0.875rem" />
              <div class="detail-text">
                <span class="label">Thời gian thi:</span>
                <span class="val">{{ formatDate(exam.starts_at) }} — {{ formatDate(exam.ends_at) }}</span>
              </div>
            </div>
            
            <div class="detail-row">
              <i class="pi pi-clock" style="font-size:0.875rem" />
              <div class="detail-text">
                <span class="label">Thời gian làm bài:</span>
                <span class="val">{{ exam.duration }} phút</span>
              </div>
            </div>

            <div class="detail-row">
              <i class="pi pi-verified" style="font-size:0.875rem" />
              <div class="detail-text">
                <span class="label">Điểm điều kiện đạt:</span>
                <span class="val">{{ exam.pass_score }}/10 điểm</span>
              </div>
            </div>
          </div>

          <!-- Bottom block: Attempt results or actions -->
          <div class="exam-card-bottom-actions">
            <!-- Completed state result -->
            <div class="result-display-box" v-if="exam.status === 'completed'">
              <div class="stat-badge">
                <i class="pi pi-check-circle" style="font-size:0.875rem" />
                <span>Hoàn thành đợt thi</span>
              </div>
              <div class="score-display">
                Điểm cao nhất: <strong :class="{ 'text-success': exam.best_score >= exam.pass_score, 'text-danger': exam.best_score < exam.pass_score }">
                  {{ exam.best_score }}/10
                </strong>
              </div>
            </div>

            <div class="result-display-box" v-else-if="exam.status === 'closed'">
              <div class="stat-badge is-closed">
                <i class="pi pi-exclamation-triangle" style="font-size:0.875rem" />
                <span>Kỳ thi đã đóng</span>
              </div>
            </div>

            <!-- Action buttons -->
            <div class="action-buttons-wrap">
              <!-- Result check -->
              <NuxtLink 
                v-if="exam.status === 'completed' && exam.attempt_id" 
                :to="`/exam/result/${exam.attempt_id}`" 
                class="action-btn view-result-btn"
              >
                Xem chi tiết <i class="pi pi-chevron-right" style="font-size:0.875rem" />
              </NuxtLink>

              <!-- Join exam -->
              <NuxtLink 
                v-else-if="exam.is_open" 
                :to="`/exam/${exam.id}`" 
                class="action-btn start-exam-btn"
              >
                <i class="pi pi-play" style="font-size:0.875rem" /> Vào thi ngay
              </NuxtLink>

              <!-- Locked upcoming -->
              <button 
                v-else-if="exam.status === 'upcoming'" 
                class="action-btn locked-btn" 
                disabled
              >
                Chưa tới giờ thi
              </button>

              <button 
                v-else-if="exam.status === 'closed'" 
                class="action-btn locked-btn" 
                disabled
              >
                Đã quá hạn thi
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.exams-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.exams-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 16px;
}

.section-kicker {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--green-deep, #047857);
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}

.exams-page-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 6px 0;
}

.exams-page-sub {
  font-size: 0.88rem;
  color: #64748b;
  margin: 0;
}

/* Search Box */
.search-box-wrap {
  position: relative;
  width: 260px;
}

@media (max-width: 640px) {
  .search-box-wrap {
    width: 100%;
  }
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-input {
  width: 100%;
  padding: 8px 12px 8px 36px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  font-size: 0.85rem;
  outline: none;
  background: #f8fafc;
  transition: all 150ms ease;
}

.search-input:focus {
  border-color: var(--green-deep, #047857);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.05);
}

/* Tabs */
.exams-tabs-bar {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid #f1f5f9;
  padding-bottom: 8px;
  flex-wrap: wrap;
}

.tab-item-btn {
  background: none;
  border: none;
  padding: 8px 16px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 160ms ease;
}

.tab-item-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.tab-item-btn.is-active {
  background: #ecfdf5;
  color: #047857;
}

.count-badge {
  background: #e2e8f0;
  color: #475569;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
}

.tab-item-btn.is-active .count-badge {
  background: rgba(16, 185, 129, 0.15);
  color: #047857;
}

.bg-danger {
  background: #fef2f2 !important;
  color: #ef4444 !important;
}

/* Loading Shimmer Card */
.shimmer-card {
  height: 220px;
  border-radius: 16px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmerAnim 1.5s infinite;
}

/* Empty State */
.exams-empty-state {
  padding: 48px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.exams-empty-state .empty-icon {
  color: #cbd5e1;
}

.exams-empty-state h3 {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0;
  color: #334155;
}

.exams-empty-state p {
  font-size: 0.88rem;
  color: #64748b;
  margin: 0;
}

/* Exams Grid */
.exams-grid-layout {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

@media (max-width: 640px) {
  .exams-grid-layout {
    grid-template-columns: 1fr;
  }
}

.exam-card-item {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 180ms ease;
  border: 1px solid rgba(0, 0, 0, 0.03);
}

.exam-card-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(0,0,0,0.04);
}

.card-status-bar {
  height: 4px;
  width: 100%;
}

.card-status-bar.upcoming {
  background: #3b82f6; /* Blue */
}

.card-status-bar.active {
  background: #ef4444; /* Red / Live */
}

.card-status-bar.completed {
  background: #10b981; /* Green */
}

.card-status-bar.closed {
  background: #94a3b8; /* Muted */
}

.exam-card-content {
  padding: 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.exam-card-meta-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.type-tag {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.type-tag.course_final {
  background: #eff6ff;
  color: #1e40af;
}

.type-tag.standalone {
  background: #faf5ff;
  color: #6b21a8;
}

.proctoring-tag {
  font-size: 0.68rem;
  font-weight: 700;
  color: #ef4444;
  background: #fef2f2;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.exam-title-name {
  font-size: 1.05rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 6px 0;
  line-height: 1.4;
}

.exam-desc-snippet {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0 0 16px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Details Grid */
.exam-details-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
  background: #f8fafc;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #f1f5f9;
}

.detail-row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.detail-text {
  display: flex;
  flex-direction: column;
}

.detail-text .label {
  font-size: 0.68rem;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
}

.detail-text .val {
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  margin-top: 1px;
}

/* Card Bottom Actions */
.exam-card-bottom-actions {
  margin-top: auto;
  border-top: 1px dashed #e2e8f0;
  padding-top: 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.result-display-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #047857;
}

.stat-badge.is-closed {
  color: #64748b;
}

.score-display {
  font-size: 0.78rem;
  color: #475569;
}

.score-display strong {
  font-size: 0.9rem;
}

.action-buttons-wrap {
  display: flex;
  gap: 8px;
}

.action-btn {
  font-size: 0.8rem;
  font-weight: 700;
  padding: 8px 14px;
  border-radius: 8px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 160ms ease;
  white-space: nowrap;
}

.start-exam-btn {
  background: #ef4444;
  color: #fff;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
}

.start-exam-btn:hover {
  background: #dc2626;
  transform: translateY(-1px);
}

.view-result-btn {
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #cbd5e1;
}

.view-result-btn:hover {
  background: #e2e8f0;
}

.locked-btn {
  background: #f1f5f9;
  color: #94a3b8;
  border: 1px solid #e2e8f0;
  cursor: not-allowed;
}

/* Dark mode adjustment override */
[data-theme="dark"] .exams-page-title {
  color: #f1f5f9;
}
[data-theme="dark"] .search-input {
  background: #1e293b;
  border-color: #334155;
}
[data-theme="dark"] .exam-details-grid {
  background: #1e293b;
  border-color: #334155;
}
[data-theme="dark"] .tab-item-btn:hover {
  background: #1e293b;
}
</style>
