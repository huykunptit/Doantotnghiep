<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import RichTextEditor from '~/components/dashboard/RichTextEditor.vue'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

interface CategoryItem { id: number; name: string }

const auth = useAuthStore()
const toast = useToast()
const { exportToCSV } = useExport()

const loading = ref(true)
const saving = ref(false)
const courses = ref<any[]>([])
const categories = ref<CategoryItem[]>([])
const search = ref('')
const selectedStatus = ref('all')
const instrPage = ref(1)
const instrPerPage = ref(10)

const modalOpen = ref(false)
const confirmOpen = ref(false)
const selectedCourse = ref<any>(null)
const selectedIds = ref<number[]>([])
const activeDropdown = ref<number | null>(null)

const defaultForm = { title: '', description: '', price: 0, category_id: '' }
const form = reactive({ ...defaultForm })

const statusOptions = [
  { value: 'all', label: 'Tất cả trạng thái' },
  { value: 'published', label: 'Đã xuất bản' },
  { value: 'pending_review', label: 'Chờ duyệt' },
  { value: 'draft', label: 'Bản nháp' },
  { value: 'rejected', label: 'Bị từ chối' },
]

const authHeaders = () => ({ Authorization: `Bearer ${auth.token}` })

const allFilteredCourses = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  return courses.value.filter((c) => {
    const matchesStatus = selectedStatus.value === 'all' || c.status === selectedStatus.value
    const haystack = `${c.title || ''} ${c.description || ''}`.toLowerCase()
    return matchesStatus && (!keyword || haystack.includes(keyword))
  })
})

const instrLastPage = computed(() => Math.max(1, Math.ceil(allFilteredCourses.value.length / instrPerPage.value)))
const filteredCourses = computed(() => {
  const start = (instrPage.value - 1) * instrPerPage.value
  return allFilteredCourses.value.slice(start, start + instrPerPage.value)
})

const totalLessons = computed(() => courses.value.reduce((sum, c) => sum + Number(c.lessons_count || 0), 0))
const totalEnrollments = computed(() => courses.value.reduce((sum, c) => sum + Number(c.enrollments_count || 0), 0))

const isAllSelected = computed(() =>
  filteredCourses.value.length > 0 && filteredCourses.value.every(c => selectedIds.value.includes(c.id))
)

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !filteredCourses.value.find(c => c.id === id))
  } else {
    const newIds = filteredCourses.value.map(c => c.id).filter(id => !selectedIds.value.includes(id))
    selectedIds.value = [...selectedIds.value, ...newIds]
  }
}

function toggleDropdown(id: number) {
  activeDropdown.value = activeDropdown.value === id ? null : id
}

const statusLabel = (s: string) => ({ published: 'Đã xuất bản', draft: 'Bản nháp', pending_review: 'Chờ duyệt', rejected: 'Bị từ chối' }[s] || s)
const statusClass = (s: string) => ({ published: 'is-published', pending_review: 'is-pending', draft: 'is-draft', rejected: 'is-rejected' }[s] || 'is-draft')
const formatPrice = (v: number) => v ? `${new Intl.NumberFormat('vi-VN').format(v)} đ` : 'Miễn phí'

async function fetchCategories() {
  try {
    categories.value = await $fetch<CategoryItem[]>('/api/categories', { headers: authHeaders() })
  } catch {}
}

async function loadCourses() {
  loading.value = true
  try {
    const res = await $fetch<any>('/api/instructor/courses', { headers: authHeaders() })
    courses.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tải danh sách khóa học.')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  Object.assign(form, defaultForm)
  modalOpen.value = true
}

async function createCourse() {
  if (!form.title.trim()) return
  saving.value = true
  try {
    await $fetch('/api/courses', {
      method: 'POST',
      headers: authHeaders(),
      body: { title: form.title, description: form.description, price: form.price, category_id: form.category_id ? Number(form.category_id) : null },
    })
    toast.success('Đã tạo khóa học thành công.')
    modalOpen.value = false
    await loadCourses()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể tạo khóa học.')
  } finally {
    saving.value = false
  }
}

async function deleteCourse() {
  if (!selectedCourse.value) return
  try {
    await $fetch(`/api/courses/${selectedCourse.value.id}`, { method: 'DELETE', headers: authHeaders() })
    toast.success('Đã xóa khóa học.')
    confirmOpen.value = false
    await loadCourses()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa khóa học.')
  }
}

function exportData() {
  const cols = [
    { key: 'id', label: 'ID' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'category', label: 'Danh mục', format: (_: any, row: any) => row.category?.name || '--' },
    { key: 'status', label: 'Trạng thái', format: (v: any) => statusLabel(v) },
    { key: 'lessons_count', label: 'Số bài học', format: (v: any) => String(v || 0) },
    { key: 'enrollments_count', label: 'Số học viên', format: (v: any) => String(v || 0) },
    { key: 'price', label: 'Học phí', format: (v: any) => formatPrice(v || 0) },
  ]
  exportToCSV(allFilteredCourses.value, cols, 'danh_sach_khoa_hoc_cua_toi')
}

onMounted(() => {
  fetchCategories()
  loadCourses()
})
</script>

<template>
  <InstructorWorkspaceShell
    title="Khóa học của tôi"
    description="Theo dõi trạng thái, cập nhật giáo trình và di chuyển nhanh đến học viên hoặc doanh thu."
    :breadcrumb="['Trang chủ', 'Khóa học']"
  >
    <template #actions>
      <NuxtLink to="/instructor/question-bank" class="crud-secondary-btn">
        <span class="material-symbols-outlined">database</span>
        Ngân hàng câu hỏi
      </NuxtLink>
      <button class="crud-export-btn" type="button" @click="exportData">
        <span class="material-symbols-outlined">download</span>
        Xuất Excel
      </button>
      <button class="crud-primary-btn" type="button" @click="openCreateModal">
        <span class="material-symbols-outlined">add_circle</span>
        Tạo khóa học
      </button>
    </template>

    <!-- KPI -->
    <div class="ds-stats mb-0">
      <div class="ds-stat ds-stat--green">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">school</span></div>
        <p class="ds-stat-label">Tổng khóa học</p>
        <strong class="ds-stat-value">{{ courses.length }}</strong>
        <span class="ds-stat-sub">khóa học</span>
      </div>
      <div class="ds-stat ds-stat--blue">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">menu_book</span></div>
        <p class="ds-stat-label">Tổng bài giảng</p>
        <strong class="ds-stat-value">{{ totalLessons }}</strong>
        <span class="ds-stat-sub">bài học</span>
      </div>
      <div class="ds-stat ds-stat--amber">
        <div class="ds-stat-icon"><span class="material-symbols-outlined">group</span></div>
        <p class="ds-stat-label">Tổng học viên</p>
        <strong class="ds-stat-value">{{ totalEnrollments }}</strong>
        <span class="ds-stat-sub">ghi danh</span>
      </div>
    </div>

    <!-- Table -->
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent>
          <div class="search-input-wrap">
            <span class="material-symbols-outlined search-icon">search</span>
            <input v-model="search" class="crud-search" type="text" placeholder="Tìm tên khóa học, mô tả...">
          </div>
          <select v-model="selectedStatus" class="crud-select">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </form>
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th style="width:40px"><input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll"></th>
              <th style="width:56px">STT</th>
              <th>Khóa học</th>
              <th>Danh mục</th>
              <th>Trạng thái</th>
              <th>Nội dung</th>
              <th>Học viên</th>
              <th>Học phí</th>
              <th style="text-align:right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="9" class="crud-empty">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="allFilteredCourses.length === 0"><td colspan="9" class="crud-empty">Không tìm thấy khóa học phù hợp.</td></tr>
            <tr v-for="(course, idx) in filteredCourses" :key="course.id">
              <td><input type="checkbox" v-model="selectedIds" :value="course.id"></td>
              <td>{{ (instrPage - 1) * instrPerPage + idx + 1 }}</td>
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                    <span v-else class="material-symbols-outlined text-thumb">book</span>
                  </div>
                  <div class="course-info">
                    <strong>{{ course.title }}</strong>
                    <p v-if="course.status === 'rejected' && course.reject_reason" class="reject-reason">
                      Từ chối: {{ course.reject_reason }}
                    </p>
                    <p v-else class="category-name">{{ course.category?.name || 'Chưa có danh mục' }}</p>
                  </div>
                </div>
              </td>
              <td>{{ course.category?.name || '--' }}</td>
              <td><span class="status-badge" :class="statusClass(course.status)">{{ statusLabel(course.status) }}</span></td>
              <td class="stat-col">{{ course.lessons_count || 0 }} bài</td>
              <td class="stat-col">{{ course.enrollments_count || 0 }}</td>
              <td class="price-col">{{ formatPrice(course.price || 0) }}</td>
              <td>
                <div class="crud-actions-dropdown" style="text-align:right">
                  <button class="action-toggle-btn" type="button" @click.stop="toggleDropdown(course.id)">
                    <span class="material-symbols-outlined">more_vert</span>
                  </button>
                  <div v-if="activeDropdown === course.id" class="dropdown-menu">
                    <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="dropdown-item">Xây dựng giáo trình</NuxtLink>
                    <NuxtLink :to="`/courses/${course.id}`" class="dropdown-item" target="_blank">Xem trang khóa học</NuxtLink>
                    <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="dropdown-item">Quản lý học viên</NuxtLink>
                    <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="dropdown-item">Xem doanh thu</NuxtLink>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item is-danger" type="button" @click="selectedCourse = course; confirmOpen = true; activeDropdown = null">Xóa khóa học</button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DataTableFooter
        :current="instrPage"
        :last="instrLastPage"
        :total="allFilteredCourses.length"
        :per-page="instrPerPage"
        @page="instrPage = $event"
        @update:per-page="instrPerPage = $event; instrPage = 1"
      />
    </section>
  </InstructorWorkspaceShell>

  <!-- Create modal -->
  <Teleport to="body">
    <div v-if="modalOpen" class="crud-modal-backdrop" @click.self="modalOpen = false">
      <div class="crud-modal">
        <div class="crud-modal-head">
          <div>
            <p class="section-kicker">Tạo mới</p>
            <h3>Tạo khóa học</h3>
          </div>
          <button class="topbar-ghost" type="button" @click="modalOpen = false">✕</button>
        </div>
        <div class="crud-form-grid">
          <label class="crud-field crud-field-full"><span>Tên khóa học</span><input v-model="form.title" type="text" placeholder="Nhập tên khóa học"></label>
          <div class="crud-field crud-field-full"><span>Mô tả</span><RichTextEditor v-model="form.description" placeholder="Mô tả khóa học..." /></div>
          <label class="crud-field"><span>Giá tiền (VNĐ)</span><input v-model="form.price" type="number" min="0"></label>
          <label class="crud-field">
            <span>Danh mục</span>
            <select v-model="form.category_id" class="crud-select">
              <option value="">Chọn danh mục</option>
              <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
            </select>
          </label>
        </div>
        <div class="crud-modal-foot">
          <button class="crud-secondary-btn" type="button" @click="modalOpen = false">Hủy</button>
          <button class="crud-primary-btn" type="button" :disabled="saving || !form.title.trim()" @click="createCourse">
            {{ saving ? 'Đang tạo...' : 'Tạo khóa học' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <CrudConfirmModal
    :open="confirmOpen"
    title="Xóa khóa học"
    :description="`Thao tác này sẽ xóa hoàn toàn khóa học &quot;${selectedCourse?.title}&quot;. Không thể hoàn tác.`"
    confirm-text="Xóa khóa học"
    tone="danger"
    @close="confirmOpen = false"
    @confirm="deleteCourse"
  />
</template>

<style scoped>
.search-input-wrap { position: relative; flex: 1; max-width: 320px; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 20px; color: var(--muted); pointer-events: none; }
.crud-search { padding-left: 40px !important; width: 100%; }

.crud-course { display: flex; align-items: center; gap: 16px; }
.crud-course-thumb { width: 72px; height: 48px; border-radius: 10px; overflow: hidden; background: var(--bg); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--line); }
.crud-course-thumb img { width: 100%; height: 100%; object-fit: cover; }
.text-thumb { font-size: 22px; color: var(--green); }
.course-info strong { font-size: 0.9rem; color: var(--text); display: block; }
.reject-reason { color: #e24b4a; font-size: 0.75rem; margin: 4px 0 0; font-weight: 500; }
.category-name { color: var(--muted); font-size: 0.78rem; margin: 4px 0 0; }

.stat-col { font-weight: 700; text-align: center; }
.price-col { font-weight: 700; color: var(--green); }

.status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; border: 1px solid transparent; }
.is-published  { background: rgba(29,158,117,0.1);  color: var(--green-deep);  border-color: rgba(29,158,117,0.2); }
.is-pending    { background: rgba(55,138,221,0.1);  color: #1a5fa8;            border-color: rgba(55,138,221,0.2); }
.is-draft      { background: rgba(17,17,17,0.06);   color: var(--muted);       border-color: var(--line); }
.is-rejected   { background: rgba(239,68,68,0.1);   color: #b91c1c;            border-color: rgba(239,68,68,0.2); }

.crud-actions-dropdown { position: relative; display: block; }

.action-toggle-btn { background: transparent; border: none; cursor: pointer; padding: 4px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #64748b; transition: background-color 0.2s; }
.action-toggle-btn:hover { background-color: rgba(17,17,17,0.05); }

.dropdown-menu { position: absolute; right: 0; top: 100%; margin-top: 4px; background: white; border: 1px solid rgba(17,17,17,0.1); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); min-width: 200px; z-index: 50; padding: 8px 0; display: flex; flex-direction: column; text-align: left; }
.dropdown-item { background: transparent; border: none; width: 100%; text-align: left; padding: 8px 16px; font-size: 0.9rem; cursor: pointer; color: #1e293b; transition: all 0.2s; text-decoration: none; display: block; }
.dropdown-item:hover { background-color: rgba(29,158,117,0.08); color: var(--green); }
.dropdown-item.is-danger { color: #dc2626; }
.dropdown-item.is-danger:hover { background-color: #fef2f2; }
.dropdown-divider { height: 1px; background-color: rgba(17,17,17,0.1); margin: 4px 0; }

[data-theme="dark"] .dropdown-menu { background: var(--surface-strong); border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .dropdown-item { color: var(--text); }
[data-theme="dark"] .dropdown-item.is-danger { color: #f87171; }
[data-theme="dark"] .dropdown-item.is-danger:hover { background: rgba(239,68,68,0.1); }
[data-theme="dark"] .is-draft { background: rgba(255,255,255,0.05); }
</style>
