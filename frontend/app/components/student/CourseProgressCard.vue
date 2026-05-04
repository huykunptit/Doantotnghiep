<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  course: any
  progress: number
}>()

const isCompleted = computed(() => props.progress >= 100)
const progressStr = computed(() => `${Math.round(props.progress)}%`)
</script>

<template>
  <div class="sc-card">
    <div class="sc-thumbnail-wrap">
      <img :src="course.thumbnail || 'https://placehold.co/600x400?text=Course+Image'" :alt="course.title" class="sc-thumbnail" />
      <div v-if="isCompleted" class="sc-badge sc-badge-completed">
        <span class="material-symbols-outlined">verified</span> Đã hoàn thành
      </div>
      <div v-else-if="progress > 0" class="sc-badge sc-badge-progress">
        <span class="material-symbols-outlined">schedule</span> Đang học
      </div>
    </div>
    
    <div class="sc-content">
      <div class="sc-category">{{ course.category?.name || 'Khóa học' }}</div>
      <h3 class="sc-title" :title="course.title">{{ course.title }}</h3>
      
      <div class="sc-progress-section">
        <div class="sc-progress-meta">
          <span class="sc-progress-label">Tiến độ</span>
          <span class="sc-progress-value" :class="{'text-success': isCompleted}">{{ progressStr }}</span>
        </div>
        <div class="sc-progress-bar-bg">
          <div class="sc-progress-bar" :style="{ width: progressStr }" :class="{'bg-success': isCompleted}"></div>
        </div>
      </div>
      
      <div class="sc-actions">
        <NuxtLink :to="`/learn/${course.id}`" class="sc-btn" :class="isCompleted ? 'sc-btn-outline' : 'sc-btn-primary'">
          {{ progress > 0 ? (isCompleted ? 'Xem lại bài' : 'Tiếp tục học') : 'Bắt đầu học' }}
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sc-card {
  display: flex;
  flex-direction: column;
  background: var(--surface-lowest, #fff);
  border: 1px solid var(--surface-dim, #e5e7eb);
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.2s ease;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.sc-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.1);
  border-color: var(--primary, #6366f1);
}

.sc-thumbnail-wrap {
  position: relative;
  width: 100%;
  padding-top: 56.25%; /* 16:9 Aspect Ratio */
  overflow: hidden;
  background: var(--surface-low);
}
.sc-thumbnail {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}
.sc-card:hover .sc-thumbnail {
  transform: scale(1.05);
}

.sc-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 0.7rem;
  font-weight: 700;
  color: #fff;
  z-index: 2;
}
.sc-badge .material-symbols-outlined { font-size: 14px; }
.sc-badge-progress { background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(4px); }
.sc-badge-completed { background: rgba(22, 163, 74, 0.9); backdrop-filter: blur(4px); }

.sc-content {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.sc-category {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--primary, #6366f1);
  margin-bottom: 0.5rem;
}

.sc-title {
  font-size: 1.1rem;
  font-weight: 800;
  line-height: 1.4;
  color: var(--on-surface, #0f172a);
  margin: 0 0 1rem 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.sc-progress-section {
  margin-top: auto;
  margin-bottom: 1.25rem;
}
.sc-progress-meta {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 6px;
}
.sc-progress-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--on-surface-variant, #475569);
}
.sc-progress-value {
  font-size: 0.9rem;
  font-weight: 800;
  color: var(--on-surface, #0f172a);
}
.text-success { color: #16a34a !important; }

.sc-progress-bar-bg {
  width: 100%;
  height: 6px;
  background: var(--surface-dim, #e5e7eb);
  border-radius: 99px;
  overflow: hidden;
}
.sc-progress-bar {
  height: 100%;
  background: var(--primary, #2f7a45);
  border-radius: 99px;
  transition: width 0.5s ease-out;
}
.bg-success { background: #22c55e !important; }

.sc-actions {
  display: flex;
}
.sc-btn {
  width: 100%;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s;
  text-align: center;
}
.sc-btn-primary {
  background: var(--surface-low, #f1f5f9);
  color: var(--primary, #6366f1);
}
.sc-btn-primary:hover {
  background: var(--primary, #6366f1);
  color: #fff;
}
.sc-btn-outline {
  border: 1px solid var(--surface-dim, #e5e7eb);
  color: var(--on-surface-variant, #475569);
  background: transparent;
}
.sc-btn-outline:hover {
  background: var(--surface-low, #f8fafc);
  color: var(--on-surface, #0f172a);
}
</style>
