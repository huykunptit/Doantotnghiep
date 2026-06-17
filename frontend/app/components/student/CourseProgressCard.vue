<script setup lang="ts">
import { computed } from 'vue'
import { CircleCheckBig, Clock } from 'lucide-vue-next'

const props = defineProps<{
  course: any
  progress: number
}>()

const isCompleted = computed(() => props.progress >= 100)
const progressStr = computed(() => `${Math.round(props.progress)}%`)
</script>

<template>
  <div class="pc-card">
    <div class="pc-thumb-wrap">
      <img :src="course.thumbnail || 'https://placehold.co/600x400?text=Course'" :alt="course.title" class="pc-thumb" />
      <div v-if="isCompleted" class="pc-badge pc-badge--done">
        <CircleCheckBig :size="12" :stroke-width="2.5" /> Đã hoàn thành
      </div>
      <div v-else-if="progress > 0" class="pc-badge pc-badge--progress">
        <Clock :size="12" :stroke-width="2.5" /> Đang học
      </div>
    </div>

    <div class="pc-body">
      <div class="pc-category">{{ course.category?.name || 'Khóa học' }}</div>
      <h3 class="pc-title" :title="course.title">{{ course.title }}</h3>

      <div class="pc-progress-wrap">
        <div class="pc-progress-meta">
          <span class="pc-progress-label">Tiến độ</span>
          <span class="pc-progress-value" :class="{ 'is-done': isCompleted }">{{ progressStr }}</span>
        </div>
        <div class="pc-progress-track">
          <div class="pc-progress-fill" :class="{ 'is-done': isCompleted }" :style="{ width: progressStr }" />
        </div>
      </div>

      <div class="pc-actions">
        <NuxtLink :to="`/learn/${course.id}`" class="pc-btn" :class="isCompleted ? 'pc-btn--outline' : 'pc-btn--primary'">
          {{ progress > 0 ? (isCompleted ? 'Xem lại bài' : 'Tiếp tục học') : 'Bắt đầu học' }}
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pc-card {
  display: flex; flex-direction: column;
  background: var(--surface-strong, #fff); border: 1px solid var(--line);
  border-radius: 12px; overflow: hidden;
  transition: transform 200ms ease, box-shadow 200ms ease, border-color 200ms ease;
}
.pc-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px -10px rgba(31, 49, 43, 0.14);
  border-color: rgba(var(--primary-rgb), 0.25);
}

.pc-thumb-wrap {
  position: relative; width: 100%; padding-top: 56.25%; overflow: hidden;
  background: var(--green-soft);
}
.pc-thumb {
  position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: cover; transition: transform 300ms ease;
}
.pc-card:hover .pc-thumb { transform: scale(1.05); }

.pc-badge {
  position: absolute; top: 10px; left: 10px;
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 99px;
  font-size: 0.68rem; font-weight: 700; color: #fff;
}
.pc-badge--progress { background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }
.pc-badge--done { background: var(--green); }

.pc-body { padding: 16px; display: flex; flex-direction: column; flex: 1; }

.pc-category {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--green); margin-bottom: 6px;
}
.pc-title {
  font-size: 1rem; font-weight: 700; line-height: 1.4; color: var(--text);
  margin: 0 0 14px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

.pc-progress-wrap { margin-top: auto; margin-bottom: 16px; }
.pc-progress-meta {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;
}
.pc-progress-label { font-size: 0.78rem; font-weight: 600; color: var(--muted); }
.pc-progress-value { font-size: 0.875rem; font-weight: 800; color: var(--text); }
.pc-progress-value.is-done { color: var(--green); }
.pc-progress-track {
  width: 100%; height: 6px; background: var(--surface); border-radius: 99px; overflow: hidden;
}
.pc-progress-fill {
  height: 100%; background: var(--green); border-radius: 99px;
  transition: width 500ms ease-out;
}

.pc-actions { display: flex; }
.pc-btn {
  width: 100%; display: inline-flex; justify-content: center; align-items: center;
  padding: 10px 16px; border-radius: 8px;
  font-size: 0.875rem; font-weight: 700; text-decoration: none;
  transition: background 150ms, color 150ms;
}
.pc-btn--primary { background: var(--green-soft); color: var(--green-deep); }
.pc-btn--primary:hover { background: var(--green); color: #fff; }
.pc-btn--outline { border: 1px solid var(--line); color: var(--muted); background: transparent; }
.pc-btn--outline:hover { background: var(--surface); color: var(--text); }

[data-theme="dark"] .pc-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
</style>
