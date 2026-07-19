<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'

const openMenuId = ref<number | null>(null)
function toggleMenu(id: number) {
  openMenuId.value = openMenuId.value === id ? null : id
}
function closeMenus() {
  openMenuId.value = null
}

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface SectionRow {
  section: {
    id: number
    code: string
    name: string | null
    status: string
    course?: { id: number; title: string; course_mode: string }
    term?: { id: number; name: string; code: string }
    cohort?: { id: number; name: string; code: string }
  }
  enrollments: number
  graded: number
  pending: number
}

const token = useAuthTokenCookie()
const data = ref<{
  instructor: any
  current_term: any
  sections: SectionRow[]
  totals: { sections: number; students: number; pending_grading: number }
} | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi('/instructor/dashboard', {
      headers: { Authorization: `Bearer ${token.value}` },
    })
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dashboard giảng viên.'
  } finally {
    loading.value = false
  }
}

const totals = computed(() => data.value?.totals || { sections: 0, students: 0, pending_grading: 0 })
const term = computed(() => data.value?.current_term)

onMounted(() => {
  load()
  document.addEventListener('click', closeMenus)
})

import { onUnmounted } from 'vue'
onUnmounted(() => document.removeEventListener('click', closeMenus))
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Giảng viên &bull; Học vụ</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Lớp học phần & điểm</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý các lớp học phần, sổ điểm và điểm danh trong kỳ hiện hành.</p>
      </div>
      <div v-if="term" class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-emerald-100 bg-emerald-50 text-xs font-semibold text-emerald-700">
          <span class="material-symbols-outlined text-sm">calendar_today</span>
          {{ term.name }} ({{ term.code }})
        </span>
      </div>
    </div>

    <!-- KPI -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
          <span class="material-symbols-outlined text-xl">class</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Lớp đang phụ trách</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ totals.sections }}</strong>
          <span class="text-[10px] text-[var(--muted)]">lớp học phần</span>
        </div>
      </div>
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
          <span class="material-symbols-outlined text-xl">group</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng sinh viên</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ totals.students }}</strong>
          <span class="text-[10px] text-[var(--muted)]">đang học</span>
        </div>
      </div>
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600">
          <span class="material-symbols-outlined text-xl">pending_actions</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Chờ chấm điểm</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ totals.pending_grading }}</strong>
          <span class="text-[10px] text-[var(--muted)]">bản ghi</span>
        </div>
      </div>
    </div>

    <!-- Content Card -->
    <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
      <div class="flex justify-between items-center px-5 py-4 border-b border-[var(--line)] bg-[var(--surface)]">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Kỳ hiện hành</p>
          <h3 class="text-sm font-bold text-[var(--text)] mt-0.5">Danh sách lớp học phần</h3>
        </div>
        <button class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" type="button" :disabled="loading" @click="load">
          <span class="material-symbols-outlined text-sm">refresh</span>
          <span>{{ loading ? 'Đang tải...' : 'Làm mới' }}</span>
        </button>
      </div>

      <div v-if="error" class="p-4 bg-red-50 border-b border-red-100 text-red-700 text-xs font-semibold">{{ error }}</div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
              <th class="px-5 py-3">Mã lớp</th>
              <th class="px-5 py-3">Học phần</th>
              <th class="px-5 py-3">Khóa</th>
              <th class="px-5 py-3 text-center">SV</th>
              <th class="px-5 py-3 text-center">Đã chấm</th>
              <th class="px-5 py-3 text-center">Chờ chấm</th>
              <th class="px-5 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="px-5 py-8 text-center text-xs text-[var(--muted)]">Đang tải...</td>
            </tr>
            <tr v-else-if="!data?.sections.length">
              <td colspan="7" class="px-5 py-8 text-center text-xs text-[var(--muted)]">Bạn chưa được phân lớp nào trong kỳ này.</td>
            </tr>
            <tr v-for="row in data?.sections || []" :key="row.section.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-5 py-4"><code class="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">{{ row.section.code }}</code></td>
              <td class="px-5 py-4">
                <strong class="text-xs font-bold text-[var(--text)] block">{{ row.section.course?.title || '--' }}</strong>
                <span class="text-[10px] text-[var(--muted)] mt-0.5 block">{{ row.section.term?.name }} &bull; {{ row.section.cohort?.code }}</span>
              </td>
              <td class="px-5 py-4 text-xs font-semibold text-[var(--text)]">{{ row.section.cohort?.name || '--' }}</td>
              <td class="px-5 py-4 text-center text-xs font-semibold text-[var(--text)]">{{ row.enrollments }}</td>
              <td class="px-5 py-4 text-center text-xs font-semibold text-[var(--text)]">{{ row.graded }}</td>
              <td class="px-5 py-4 text-center">
                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold" :class="row.pending > 0 ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'text-emerald-600'">
                  {{ row.pending }}
                </span>
              </td>
              <td class="px-5 py-4 text-right">
                <div class="inline-flex items-center gap-2 justify-end" @click.stop>
                  <NuxtLink :to="`/instructor/sections/${row.section.id}/grades`" class="inline-flex items-center gap-1.5 h-7 px-3 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-[10px] font-bold text-emerald-700 transition-colors">
                    <span class="material-symbols-outlined text-sm">grading</span>
                    <span>Sổ điểm</span>
                  </NuxtLink>
                  <div class="relative">
                    <button type="button" class="w-7 h-7 rounded-lg flex items-center justify-center border border-[var(--line)] hover:bg-[var(--surface)] text-[var(--muted)]" @click="toggleMenu(row.section.id)">
                      <span class="material-symbols-outlined text-sm">more_vert</span>
                    </button>
                    <div v-if="openMenuId === row.section.id" class="absolute right-0 top-full mt-1 z-50 min-w-[200px] bg-white border border-[var(--line)] rounded-xl shadow-xl p-1 flex flex-col">
                      <NuxtLink :to="`/instructor/sections/${row.section.id}/sessions`" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-[var(--text)] hover:bg-[var(--surface)] transition-colors" @click="closeMenus">
                        <span class="material-symbols-outlined text-sm text-[var(--muted)]">qr_code_2</span>
                        Điểm danh QR
                      </NuxtLink>
                      <NuxtLink :to="`/instructor/sections/${row.section.id}/attendance-stats`" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-[var(--text)] hover:bg-[var(--surface)] transition-colors" @click="closeMenus">
                        <span class="material-symbols-outlined text-sm text-[var(--muted)]">bar_chart</span>
                        Thống kê điểm danh
                      </NuxtLink>
                      <NuxtLink :to="`/instructor/sections/${row.section.id}/grade-report`" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-[var(--text)] hover:bg-[var(--surface)] transition-colors" @click="closeMenus">
                        <span class="material-symbols-outlined text-sm text-[var(--muted)]">assessment</span>
                        Báo cáo điểm & GPA
                      </NuxtLink>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
