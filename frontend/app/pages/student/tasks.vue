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
    // fallback
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
  if (type.includes('quiz') || type.includes('exam')) return 'assignment'
  if (type.includes('video') || type.includes('lesson')) return 'play_circle'
  if (type.includes('assign')) return 'article'
  return 'task_alt'
}

function typeLabel(type: string) {
  if (type.includes('quiz') || type.includes('exam')) return 'Quiz'
  if (type.includes('video') || type.includes('lesson')) return 'Video'
  if (type.includes('assign')) return 'Bài tập'
  return 'Nhiệm vụ'
}

function typeIconClass(type: string) {
  if (type.includes('quiz') || type.includes('exam')) return 'bg-amber-50 text-amber-600'
  if (type.includes('video') || type.includes('lesson')) return 'bg-sky-50 text-sky-600'
  if (type.includes('assign')) return 'bg-emerald-50 text-emerald-600'
  return 'bg-slate-50 text-slate-600'
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
  <div class="flex flex-col gap-6 max-w-7xl mx-auto px-4 py-2">
    <!-- Header -->
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Học tập</p>
      <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Nhiệm vụ</h1>
    </div>

    <!-- KPI + Filter row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="grid grid-cols-3 gap-3 w-full sm:w-auto">
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex flex-col gap-0.5 min-w-[100px] text-center">
          <span class="text-lg font-extrabold text-[var(--text)]">{{ totalTasks }}</span>
          <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Tổng số</span>
        </div>
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex flex-col gap-0.5 min-w-[100px] text-center">
          <span class="text-lg font-extrabold text-[var(--text)]">{{ doneTasks }}</span>
          <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Đã xong</span>
        </div>
        <div class="bg-white border border-[var(--line)] rounded-2xl p-4 shadow-sm flex flex-col gap-0.5 min-w-[100px] text-center">
          <span class="text-lg font-extrabold" :class="overallPct === 100 ? 'text-emerald-600' : 'text-[var(--text)]'">{{ overallPct }}%</span>
          <span class="text-[9px] font-bold text-[var(--muted)] uppercase tracking-wider">Tiến độ</span>
        </div>
      </div>
      <div class="flex gap-1.5 border border-[var(--line)] bg-[var(--surface)] p-1 rounded-xl">
        <button 
          v-for="t in [{k:'all',l:'Tất cả'},{k:'todo',l:'Chưa làm'},{k:'done',l:'Đã xong'}]"
          :key="t.k" 
          class="h-8 px-4 rounded-lg text-xs font-bold transition-all"
          :class="filter === t.k ? 'bg-white text-[var(--text)] shadow-sm' : 'text-[var(--muted)] hover:text-[var(--text)]'"
          @click="filter = t.k as any"
        >
          {{ t.l }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-col gap-4 animate-pulse">
      <div v-for="i in 3" :key="i" class="h-16 bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="!courseGroups.length" class="flex flex-col items-center gap-4 text-center py-16 bg-white border border-[var(--line)] rounded-2xl shadow-sm">
      <span class="material-symbols-outlined text-4xl text-[var(--muted)] opacity-60">task_alt</span>
      <p class="text-sm font-semibold text-[var(--muted)]">Không có nhiệm vụ nào.</p>
    </div>

    <!-- Task groups -->
    <div v-else class="flex flex-col gap-4">
      <div v-for="group in courseGroups" :key="group.id" class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
        <!-- Group header -->
        <button class="flex items-center gap-3 px-5 py-4 hover:bg-[var(--surface)] transition-colors text-left" @click="toggle(group.id)">
          <div class="w-10 h-10 rounded-xl bg-[rgba(29,158,117,0.08)] text-emerald-700 flex items-center justify-center overflow-hidden flex-shrink-0">
            <img v-if="group.thumbnail" :src="group.thumbnail" :alt="group.title" class="w-full h-full object-cover">
            <span v-else class="material-symbols-outlined text-lg">book</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-[var(--text)] truncate">{{ group.title }}</p>
            <div class="flex items-center gap-2 mt-1.5">
              <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden border border-[var(--line)]">
                <div class="h-full bg-[#1d9e75] rounded-full transition-all" :style="{ width: `${group.allCount ? group.doneCount / group.allCount * 100 : 0}%` }"></div>
              </div>
              <span class="text-[10px] font-bold text-[var(--muted)]">{{ group.doneCount }}/{{ group.allCount }}</span>
            </div>
          </div>
          <span class="material-symbols-outlined text-lg text-[var(--muted)]">{{ expanded.has(group.id) ? 'expand_less' : 'expand_more' }}</span>
        </button>

        <!-- Tasks list -->
        <div v-show="expanded.has(group.id) || courseGroups.length === 1" class="border-t border-[var(--line)] py-2 flex flex-col">
          <div v-for="task in group.tasks" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-[var(--surface)] transition-colors" :class="{ 'opacity-60': task.done }">
            <!-- Checkbox mimic -->
            <div class="w-5 h-5 rounded-md border-2 border-[var(--line)] flex items-center justify-center flex-shrink-0 transition-colors" :class="task.done ? 'bg-[#1d9e75] border-[#1d9e75] text-white' : 'bg-white'">
              <span v-if="task.done" class="material-symbols-outlined text-xs leading-none font-bold">check</span>
            </div>
            <!-- Type Icon -->
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" :class="typeIconClass(task.type)">
              <span class="material-symbols-outlined text-sm">{{ typeIcon(task.type) }}</span>
            </div>
            <!-- Task Info -->
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-[var(--text)] truncate">{{ task.title }}</p>
              <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-[9px] font-bold text-slate-500 border border-slate-200">{{ typeLabel(task.type) }}</span>
                <span v-if="task.deadline" class="inline-flex items-center gap-0.5 text-[9px] font-bold" :class="isOverdue(task.deadline) && !task.done ? 'text-red-500' : 'text-[var(--muted)]'">
                  <span class="material-symbols-outlined text-xs">calendar_today</span>
                  {{ formatDeadline(task.deadline) }}
                </span>
              </div>
            </div>
            <!-- Status badges -->
            <span v-if="task.done" class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">Hoàn thành</span>
            <span v-else-if="task.deadline && isOverdue(task.deadline)" class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-red-50 text-red-600 border border-red-100">Trễ hạn</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
