<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

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
  <InstructorWorkspaceShell
    title="Lớp học phần & điểm"
    description="Quản lý các lớp học phần, sổ điểm và điểm danh trong kỳ hiện hành."
    :breadcrumb="['Trang chủ', 'Học vụ', 'Lớp học phần']"
  >
    <template v-if="term" #actions>
      <span class="ds-term-badge">
        <span class="material-symbols-outlined">calendar_today</span>
        {{ term.name }} ({{ term.code }})
      </span>
    </template>

    <!-- KPI -->
    <div class="ds-stats mb-0">
      <div class="ds-stat ds-stat--blue">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">class</span></div>
        <p class="ds-stat-label">Lớp đang phụ trách</p>
        <strong class="ds-stat-value">{{ totals.sections }}</strong>
        <span class="ds-stat-sub">lớp học phần</span>
      </div>
      <div class="ds-stat ds-stat--green">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">group</span></div>
        <p class="ds-stat-label">Tổng sinh viên</p>
        <strong class="ds-stat-value">{{ totals.students }}</strong>
        <span class="ds-stat-sub">đang học</span>
      </div>
      <div class="ds-stat ds-stat--amber">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">pending_actions</span></div>
        <p class="ds-stat-label">Chờ chấm điểm</p>
        <strong class="ds-stat-value">{{ totals.pending_grading }}</strong>
        <span class="ds-stat-sub">bản ghi</span>
      </div>
    </div>

    <div class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Kỳ hiện hành</p>
          <h3 class="ds-section-title">Danh sách lớp học phần</h3>
        </div>
        <button class="crud-secondary-btn" type="button" :disabled="loading" @click="load">
          <span class="material-symbols-outlined">refresh</span>
          <span>{{ loading ? 'Đang tải...' : 'Làm mới' }}</span>
        </button>
      </div>

      <div v-if="error" class="crud-alert is-error">{{ error }}</div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Mã lớp</th>
              <th>Học phần</th>
              <th>Khóa</th>
              <th class="text-center">SV</th>
              <th class="text-center">Đã chấm</th>
              <th class="text-center">Chờ chấm</th>
              <th class="text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="crud-empty">Đang tải...</td>
            </tr>
            <tr v-else-if="!data?.sections.length">
              <td colspan="7" class="crud-empty">Bạn chưa được phân lớp nào trong kỳ này.</td>
            </tr>
            <tr v-for="row in data?.sections || []" :key="row.section.id">
              <td><code>{{ row.section.code }}</code></td>
              <td>
                <strong>{{ row.section.course?.title || '--' }}</strong>
                <p class="text-xs text-muted">{{ row.section.term?.name }} · {{ row.section.cohort?.code }}</p>
              </td>
              <td>{{ row.section.cohort?.name || '--' }}</td>
              <td class="text-center">{{ row.enrollments }}</td>
              <td class="text-center">{{ row.graded }}</td>
              <td class="text-center">
                <span :class="row.pending > 0 ? 'badge-warning' : 'badge-ok'">{{ row.pending }}</span>
              </td>
              <td class="text-right">
                <div class="action-wrap" @click.stop>
                  <NuxtLink :to="`/instructor/sections/${row.section.id}/grades`" class="crud-primary-btn-sm">
                    <span class="material-symbols-outlined">grading</span>
                    <span>Sổ điểm</span>
                  </NuxtLink>
                  <div class="menu-wrap">
                    <button type="button" class="more-btn" @click="toggleMenu(row.section.id)">
                      <span class="material-symbols-outlined">more_vert</span>
                    </button>
                    <div v-if="openMenuId === row.section.id" class="dropdown-menu">
                      <NuxtLink :to="`/instructor/sections/${row.section.id}/sessions`" class="menu-item" @click="closeMenus">
                        <span class="material-symbols-outlined">qr_code_2</span>
                        Điểm danh QR
                      </NuxtLink>
                      <NuxtLink :to="`/instructor/sections/${row.section.id}/attendance-stats`" class="menu-item" @click="closeMenus">
                        <span class="material-symbols-outlined">bar_chart</span>
                        Thống kê điểm danh
                      </NuxtLink>
                      <NuxtLink :to="`/instructor/sections/${row.section.id}/grade-report`" class="menu-item" @click="closeMenus">
                        <span class="material-symbols-outlined">assessment</span>
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
  </InstructorWorkspaceShell>
</template>

<style scoped>

.crud-primary-btn-sm {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  font-weight: 600;
  font-size: 0.85rem;
  text-decoration: none;
  transition: background-color 140ms ease;
}
.crud-primary-btn-sm:hover { background: rgba(var(--green-rgb), 0.22); }
.crud-primary-btn-sm .material-symbols-outlined { font-size: 18px; }

.badge-ok { color: var(--green-deep); font-weight: 700; }
.badge-warning {
  background: rgba(217, 119, 6, 0.12);
  color: #b45309;
  padding: 2px 10px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.85rem;
}

.action-wrap {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  justify-content: flex-end;
}

.menu-wrap {
  position: relative;
}

.more-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: var(--muted);
  cursor: pointer;
  transition: background 150ms, color 150ms;
}
.more-btn:hover { background: rgba(0,0,0,0.06); color: var(--on-surface); }
.more-btn .material-symbols-outlined { font-size: 20px; }

.dropdown-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 50;
  min-width: 200px;
  background: white;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 10px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  padding: 4px;
  display: flex;
  flex-direction: column;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 7px;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--on-surface);
  text-decoration: none;
  transition: background 120ms;
  white-space: nowrap;
}
.menu-item:hover { background: rgba(var(--green-rgb), 0.08); color: var(--green-deep); }
.menu-item .material-symbols-outlined { font-size: 18px; color: var(--muted); }
.menu-item:hover .material-symbols-outlined { color: var(--green-deep); }
</style>
