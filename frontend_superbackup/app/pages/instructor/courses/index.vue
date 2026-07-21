<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khóa học</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Khóa học của tôi</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Theo dõi trạng thái, cập nhật giáo trình và di chuyển nhanh đến học viên hoặc doanh thu.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <NuxtLink to="/instructor/question-bank" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <span class="material-symbols-outlined text-sm">database</span>
          Ngân hàng câu hỏi
        </NuxtLink>
        <button class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" type="button" @click="exportData">
          <span class="material-symbols-outlined text-sm">download</span>
          Xuất Excel
        </button>
        <button class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors" type="button" @click="openCreateModal">
          <span class="material-symbols-outlined text-sm">add_circle</span>
          Tạo khóa học
        </button>
      </div>
    </div>

    <!-- KPI -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-emerald-50 text-emerald-600">
          <span class="material-symbols-outlined text-xl">school</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng khóa học</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ courses.length }}</strong>
          <span class="text-[10px] text-[var(--muted)]">khóa học</span>
        </div>
      </div>
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-sky-50 text-sky-600">
          <span class="material-symbols-outlined text-xl">menu_book</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng bài giảng</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ totalLessons }}</strong>
          <span class="text-[10px] text-[var(--muted)]">bài học</span>
        </div>
      </div>
      <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-amber-50 text-amber-600">
          <span class="material-symbols-outlined text-xl">group</span>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tổng học viên</p>
          <strong class="text-lg font-extrabold text-[var(--text)] block mt-0.5">{{ totalEnrollments }}</strong>
          <span class="text-[10px] text-[var(--muted)]">ghi danh</span>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col gap-4 p-5">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="flex flex-1 items-center gap-3 w-full sm:max-w-md">
          <div class="relative flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[var(--muted)]">search</span>
            <input v-model="search" class="w-full h-9 pl-9 pr-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] placeholder:text-[var(--muted)] focus:outline-none focus:border-[#1d9e75]" type="text" placeholder="Tìm tên khóa học, mô tả...">
          </div>
          <select v-model="selectedStatus" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="border-b border-[var(--line)] bg-[var(--surface)] text-[0.72rem] font-bold uppercase tracking-wider text-[var(--muted)]">
              <th class="px-4 py-3" style="width:40px">
                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-[var(--line)] text-[#1d9e75] focus:ring-[#1d9e75]">
              </th>
              <th class="px-4 py-3" style="width:56px">STT</th>
              <th class="px-4 py-3">Khóa học</th>
              <th class="px-4 py-3">Danh mục</th>
              <th class="px-4 py-3">Trạng thái</th>
              <th class="px-4 py-3 text-center">Nội dung</th>
              <th class="px-4 py-3 text-center">Học viên</th>
              <th class="px-4 py-3">Học phí</th>
              <th class="px-4 py-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="9" class="p-6 text-center text-xs text-[var(--muted)]">Đang tải dữ liệu...</td></tr>
            <tr v-else-if="allFilteredCourses.length === 0"><td colspan="9" class="p-6 text-center text-xs text-[var(--muted)]">Không tìm thấy khóa học phù hợp.</td></tr>
            <tr v-for="(course, idx) in filteredCourses" :key="course.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
              <td class="px-4 py-3">
                <input type="checkbox" v-model="selectedIds" :value="course.id" class="rounded border-[var(--line)] text-[#1d9e75] focus:ring-[#1d9e75]">
              </td>
              <td class="px-4 py-3 text-xs text-[var(--muted)]">{{ (instrPage - 1) * instrPerPage + idx + 1 }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-14 h-9 rounded-lg overflow-hidden bg-[var(--surface)] border border-[var(--line)] flex items-center justify-center flex-shrink-0">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="w-full h-full object-cover">
                    <span v-else class="material-symbols-outlined text-base text-[#1d9e75]">book</span>
                  </div>
                  <div class="flex flex-col min-w-0">
                    <strong class="text-xs font-bold text-[var(--text)] truncate">{{ course.title }}</strong>
                    <p v-if="course.status === 'rejected' && course.reject_reason" class="text-[10px] text-red-500 font-semibold mt-0.5">
                      Từ chối: {{ course.reject_reason }}
                    </p>
                    <p v-else class="text-[10px] text-[var(--muted)] mt-0.5">{{ course.category?.name || 'Chưa có danh mục' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-xs text-[var(--text)]">{{ course.category?.name || '--' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="{
                  'bg-emerald-50 text-emerald-600 border-emerald-100': course.status === 'published',
                  'bg-sky-50 text-sky-600 border-sky-100': course.status === 'pending_review',
                  'bg-[var(--surface)] text-[var(--muted)] border-[var(--line)]': course.status === 'draft',
                  'bg-red-50 text-red-500 border-red-100': course.status === 'rejected'
                }">
                  {{ statusLabel(course.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-[var(--text)] font-semibold text-center">{{ course.lessons_count || 0 }} bài</td>
              <td class="px-4 py-3 text-xs text-[var(--text)] font-semibold text-center">{{ course.enrollments_count || 0 }}</td>
              <td class="px-4 py-3 text-xs text-emerald-600 font-bold">{{ formatPrice(course.price || 0) }}</td>
              <td class="px-4 py-3 text-right">
                <div class="relative inline-block text-left">
                  <button class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--text)] transition-colors" type="button" @click.stop="toggleDropdown(course.id)">
                    <span class="material-symbols-outlined text-sm">more_vert</span>
                  </button>
                  <div v-if="activeDropdown === course.id" class="absolute right-0 mt-1 w-48 bg-white border border-[var(--line)] rounded-xl shadow-lg py-1 z-50 flex flex-col text-left">
                    <NuxtLink :to="`/instructor/courses/${course.id}/curriculum`" class="px-4 py-2 text-xs text-[var(--text)] hover:bg-[var(--surface)] hover:text-[#1d9e75] transition-colors">Xây dựng giáo trình</NuxtLink>
                    <NuxtLink :to="`/courses/${course.id}`" class="px-4 py-2 text-xs text-[var(--text)] hover:bg-[var(--surface)] hover:text-[#1d9e75] transition-colors" target="_blank">Xem trang khóa học</NuxtLink>
                    <NuxtLink :to="`/instructor/courses/${course.id}/students`" class="px-4 py-2 text-xs text-[var(--text)] hover:bg-[var(--surface)] hover:text-[#1d9e75] transition-colors">Quản lý học viên</NuxtLink>
                    <NuxtLink :to="`/instructor/courses/${course.id}/revenue`" class="px-4 py-2 text-xs text-[var(--text)] hover:bg-[var(--surface)] hover:text-[#1d9e75] transition-colors">Xem doanh thu</NuxtLink>
                    <div class="border-t border-[var(--line)] my-1"></div>
                    <button class="px-4 py-2 text-xs text-red-500 hover:bg-red-50 transition-colors w-full text-left" type="button" @click="selectedCourse = course; confirmOpen = true; activeDropdown = null">Xóa khóa học</button>
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
    </div>
  </div>

  <!-- Create modal -->
  <Teleport to="body">
    <div v-if="modalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-[999]" @click.self="modalOpen = false">
      <div class="bg-white border border-[var(--line)] rounded-2xl w-full max-w-lg shadow-xl overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex justify-between items-center">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Tạo mới</p>
            <h3 class="text-sm font-bold text-[var(--text)] mt-0.5">Tạo khóa học</h3>
          </div>
          <button class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-[var(--line)] text-[var(--muted)]" type="button" @click="modalOpen = false">✕</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Tên khóa học</span>
            <input v-model="form.title" type="text" placeholder="Nhập tên khóa học" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Mô tả</span>
            <RichTextEditor v-model="form.description" placeholder="Mô tả khóa học..." />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold text-[var(--text)]">Giá tiền (VNĐ)</span>
              <input v-model="form.price" type="number" min="0" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75]">
            </div>
            <div class="flex flex-col gap-1.5">
              <span class="text-xs font-semibold text-[var(--text)]">Danh mục</span>
              <select v-model="form.category_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-xs text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                <option value="">Chọn danh mục</option>
                <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-[var(--line)] bg-[var(--surface)] flex justify-end gap-2">
          <button class="h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" type="button" @click="modalOpen = false">Hủy</button>
          <button class="h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50" type="button" :disabled="saving || !form.title.trim()" @click="createCourse">
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
/* Scoped styles kept minimal */
</style>
