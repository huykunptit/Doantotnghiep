<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'student' })

const auth = useAuthStore()
const loading = ref(true)
const taskGroups = ref<any[]>([])
const filter = ref<'all' | 'todo' | 'done'>('all')
const expanded = ref<Set<number | string>>(new Set())

onMounted(async () => {
  const h = { Authorization: `Bearer ${auth.token}` }
  try {
    const res = await useApi<any[]>('/me/tasks', { headers: h })
    taskGroups.value = res || []
  } catch {
    // fallback siừng
  } finally {
    loading.value = false
  }
})

interface Task { id: any; title: string; type: string; deadline?: string; done: boolean }

function getTasks(group: any): Task[] {
  const src: any[] = group.tasks || []
  return src.map((t: any) => ({
    id: t.id,
    title: t.title || t.name || 'Nhiệm vụ',
    type: t.type || t.lesson_type || 'task',
    deadline: t.deadline || t.due_date,
    done: !!t.completed_at || !!t.is_completed,
  }))
}

const courseGroups = computed(() => {
  return taskGroups.value.map(g => {
    const allTasks = getTasks(g)
    const tasks = filter.value === 'todo' ? allTasks.filter(t => !t.done)
      : filter.value === 'done' ? allTasks.filter(t => t.done)
      : allTasks
    return {
      id: g.course_id,
      title: g.course_title || 'Khóa học',
      thumbnail: g.thumbnail,
      allCount: allTasks.length,
      doneCount: allTasks.filter(t => t.done).length,
      tasks,
    }
  }).filter(g => g.tasks.length > 0 || filter.value === 'all')
})

const totalTasks = computed(() => taskGroups.value.reduce((s, g) => s + getTasks(g).length, 0))
const doneTasks = computed(() => taskGroups.value.reduce((s, g) => s + getTasks(g).filter((t: any) => t.done).length, 0))
const overallPct = computed(() => totalTasks.value ? Math.round(doneTasks.value / totalTasks.value * 100) : 0)

function toggle(id: any) {
  if (expanded.value.has(id)) expanded.value.delete(id)
  else expanded.value.add(id)
  expanded.value = new Set(expanded.value)
}

function typeIcon(type: string) {
  if (type.includes('quiz') || type.includes('exam')) return 'clipboard-list'
  if (type.includes('video') || type.includes('lesson')) return 'play-circle'
  if (type.includes('assign')) return 'file-text'
  return 'check-square'
}

function typeLabel(type: string) {
  if (type.includes('quiz') || type.includes('exam')) return 'Quiz'
  if (type.includes('video') || type.includes('lesson')) return 'Video'
  if (type.includes('assign')) return 'Bài tập'
  return 'Nhiệm vụ'
}

function formatDeadline(d?: string) {
  if (!d) return null
  return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function isOverdue(d?: string) {
  if (!d) return false
  return new Date(d).getTime() < Date.now()
}
</script>

<template>
  <div class="tk-page">
    <!-- Header -->
    <div class="tk-header">
      <div>
        <p class="section-kicker">Học tập</p>
        <h1 class="tk-title">Nhiệm vụ</h1>
      </div>
    </div>

    <!-- KPI + Filter row -->
    <div class="tk-top-row">
      <div class="tk-kpi-row">
        <div class="tk-kpi-card">
          <span class="tk-kpi-val">{{ totalTasks }}</span>
          <span class="tk-kpi-lbl">Tổng nhiệm vụ</span>
        </div>
        <div class="tk-kpi-card">
          <span class="tk-kpi-val">{{ doneTasks }}</span>
          <span class="tk-kpi-lbl">Đã hoàn thành</span>
        </div>
        <div class="tk-kpi-card">
          <span class="tk-kpi-val" :class="{done: overallPct===100}">{{ overallPct }}%</span>
          <span class="tk-kpi-lbl">Tiến độ</span>
        </div>
      </div>
      <div class="tk-filter-tabs" role="tablist">
        <button v-for="t in [{k:'all',l:'Tất cả'},{k:'todo',l:'Chưa làm'},{k:'done',l:'Đã xong'}]"
          :key="t.k" role="tab" class="tk-filter-tab" :class="{active: filter===t.k}"
          @click="filter=t.k as any">{{ t.l }}</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="tk-list">
      <div v-for="i in 3" :key="i" class="dashboard-card tk-group-skeleton">
        <span class="sd-shimmer" style="height:48px;display:block;border-radius:8px"></span>
        <div style="padding:12px;display:flex;flex-direction:column;gap:8px">
          <span v-for="j in 3" :key="j" class="sd-shimmer" style="height:36px;display:block;border-radius:8px"></span>
        </div>
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="!courseGroups.length" class="sd-empty">
      <SylvaIcon name="check-square" :size="40" />
      <p>Không có nhiệm vụ nào.</p>
    </div>

    <!-- Task groups -->
    <div v-else class="tk-list">
      <div v-for="group in courseGroups" :key="group.id" class="dashboard-card tk-group">
        <!-- Group header -->
        <button class="tk-group-head" @click="toggle(group.id)" :aria-expanded="expanded.has(group.id)">
          <div class="tk-group-thumb">
            <img v-if="group.thumbnail" :src="group.thumbnail" :alt="group.title">
            <SylvaIcon v-else name="book-open" :size="14" />
          </div>
          <div class="tk-group-info">
            <p class="tk-group-title">{{ group.title }}</p>
            <div class="tk-group-progress">
              <div class="tk-gpb"><div class="tk-gpb-fill" :style="{width: `${group.allCount ? group.doneCount/group.allCount*100 : 0}%`}"></div></div>
              <span class="tk-group-count">{{ group.doneCount }}/{{ group.allCount }}</span>
            </div>
          </div>
          <SylvaIcon :name="expanded.has(group.id) ? 'chevron-up' : 'chevron-down'" :size="16" class="tk-chevron" />
        </button>

        <!-- Tasks list -->
        <Transition name="tk-slide">
          <div v-if="expanded.has(group.id) || courseGroups.length === 1" class="tk-tasks">
            <div v-for="task in group.tasks" :key="task.id" class="tk-task-row" :class="{done: task.done}">
              <div class="tk-task-check" :class="{checked: task.done}" aria-hidden="true">
                <SylvaIcon v-if="task.done" name="check" :size="11" />
              </div>
              <div class="tk-task-icon-wrap" :class="`type-${task.type.split('_')[0]}`">
                <SylvaIcon :name="typeIcon(task.type)" :size="13" />
              </div>
              <div class="tk-task-info">
                <p class="tk-task-title">{{ task.title }}</p>
                <div class="tk-task-meta">
                  <span class="tk-type-badge">{{ typeLabel(task.type) }}</span>
                  <span v-if="task.deadline" class="tk-deadline" :class="{overdue: isOverdue(task.deadline) && !task.done}">
                    <SylvaIcon name="calendar" :size="10" />
                    {{ formatDeadline(task.deadline) }}
                  </span>
                </div>
              </div>
              <span v-if="task.done" class="tk-done-badge">Hoàn thành</span>
              <span v-else-if="task.deadline && isOverdue(task.deadline)" class="tk-overdue-badge">Trễ hạn</span>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tk-page { display: flex; flex-direction: column; gap: 20px; }
.tk-header { display: flex; align-items: flex-end; justify-content: space-between; }
.tk-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin: 4px 0 0; }

.tk-top-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }

.tk-kpi-row { display: flex; gap: 12px; }
.tk-kpi-card {
  display: flex; flex-direction: column; align-items: center;
  padding: 10px 18px; border-radius: 10px;
  background: var(--surface-strong); border: 1px solid var(--line);
  min-width: 80px;
}
.tk-kpi-val { font-size: 1.4rem; font-weight: 800; color: var(--text); line-height: 1; }
.tk-kpi-val.done { color: var(--green, #0F6E8C); }
.tk-kpi-lbl { font-size: 0.68rem; color: var(--muted); font-weight: 600; margin-top: 3px; text-align: center; }

.tk-filter-tabs { display: flex; gap: 4px; }
.tk-filter-tab {
  padding: 6px 14px; border-radius: 8px; border: 1px solid var(--line);
  background: transparent; color: var(--muted);
  font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: background 150ms, color 150ms;
}
.tk-filter-tab:hover { background: var(--bg); color: var(--text); }
.tk-filter-tab.active { background: var(--green-soft); color: var(--green-deep); border-color: var(--green); }

.tk-list { display: flex; flex-direction: column; gap: 12px; }
.tk-group { overflow: hidden; padding: 0; }

.tk-group-head {
  display: flex; align-items: center; gap: 12px;
  width: 100%; padding: 14px 16px;
  background: transparent; border: none; cursor: pointer;
  text-align: left; transition: background 150ms;
}
.tk-group-head:hover { background: var(--bg); }
.tk-group-thumb {
  width: 40px; height: 40px; border-radius: 8px;
  background: var(--green-soft); color: var(--green-deep);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; flex-shrink: 0;
}
.tk-group-thumb img { width: 100%; height: 100%; object-fit: cover; }
.tk-group-info { flex: 1; min-width: 0; }
.tk-group-title { font-size: 0.9rem; font-weight: 700; color: var(--text); margin: 0 0 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tk-group-progress { display: flex; align-items: center; gap: 8px; }
.tk-gpb { flex: 1; height: 5px; background: var(--line); border-radius: 5px; overflow: hidden; }
.tk-gpb-fill { height: 100%; background: var(--green); border-radius: 5px; transition: width 400ms; }
.tk-group-count { font-size: 0.72rem; color: var(--muted); font-weight: 600; white-space: nowrap; }
.tk-chevron { color: var(--muted); flex-shrink: 0; }

.tk-tasks { border-top: 1px solid var(--line); padding: 8px 0; }
.tk-task-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 16px; transition: background 120ms;
}
.tk-task-row:hover { background: var(--bg); }
.tk-task-row.done { opacity: 0.65; }

.tk-task-check {
  width: 18px; height: 18px; border-radius: 5px;
  border: 2px solid var(--line); flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: background 150ms, border-color 150ms;
}
.tk-task-check.checked { background: var(--green); border-color: var(--green); color: #fff; }

.tk-task-icon-wrap {
  width: 26px; height: 26px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  background: var(--bg); color: var(--muted); flex-shrink: 0;
}
.tk-task-icon-wrap.type-quiz, .tk-task-icon-wrap.type-exam { background: var(--accent-soft); color: #92400e; }
.tk-task-icon-wrap.type-video, .tk-task-icon-wrap.type-lesson { background: var(--secondary-soft); color: var(--secondary); }
.tk-task-icon-wrap.type-assign { background: var(--green-soft); color: var(--green-deep); }

.tk-task-info { flex: 1; min-width: 0; }
.tk-task-title { font-size: 0.84rem; font-weight: 600; color: var(--text); margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tk-task-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tk-type-badge {
  font-size: 0.68rem; font-weight: 700; padding: 1px 7px; border-radius: 20px;
  background: var(--bg); color: var(--muted); border: 1px solid var(--line);
}
.tk-deadline {
  display: flex; align-items: center; gap: 3px;
  font-size: 0.7rem; color: var(--muted);
}
.tk-deadline.overdue { color: var(--danger, #ef4444); }
.tk-done-badge {
  font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 20px;
  background: var(--green-soft); color: var(--green-deep); white-space: nowrap;
}
.tk-overdue-badge {
  font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 20px;
  background: rgba(239,68,68,0.1); color: var(--danger); white-space: nowrap;
}

/* Slide transition */
.tk-slide-enter-active { transition: all 200ms ease; }
.tk-slide-leave-active { transition: all 150ms ease; }
.tk-slide-enter-from, .tk-slide-leave-to { opacity: 0; transform: translateY(-8px); }

.sd-shimmer { background: linear-gradient(90deg, var(--line) 25%, var(--bg) 50%, var(--line) 75%); background-size: 200% 100%; animation: sd-shimmer 1.5s infinite; border-radius: 6px; }
@keyframes sd-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.sd-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: var(--muted); gap: 10px; }
.sd-empty p { font-size: 0.9rem; }

[data-theme="dark"] .tk-kpi-card { background: var(--surface); }
[data-theme="dark"] .tk-filter-tab.active { background: rgba(52,211,153,0.15); color: #6ee7b7; border-color: rgba(52,211,153,0.4); }

@media (max-width: 640px) {
  .tk-top-row { flex-direction: column; align-items: flex-start; }
  .tk-kpi-row { width: 100%; }
  .tk-kpi-card { flex: 1; }
}
</style>
