<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin' })

interface RelItem { id: number; name?: string; title?: string }
interface AdminCourse {
  id: number
  title: string
  code?: string | null
  slug?: string | null
  description?: string | null
  thumbnail?: string | null
  price?: number | string | null
  type?: number | string | null
  status?: string | number | null
  lessons_count?: number
  enrollments_count?: number
  total_enrolled?: number
  instructor?: { name: string; avatar?: string | null } | null
  category?: RelItem | null
  category_id?: number | null
  course_level?: RelItem | null
  reject_reason?: string | null
  created_at?: string | null
}

interface CourseListResponse {
  data: AdminCourse[]
  current_page: number
  last_page: number
  total: number
}

const token = useAuthTokenCookie()
const toast = useToast()
const { exportToCSV } = useExport()

const filters = reactive({
  search: '',
  status: '',
  type: '',
  category_id: ''
})

const courses = ref<AdminCourse[]>([])
const categories = ref<RelItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalCourses = ref(0)
const perPage = ref(12)
const sortBy = ref('')
const sortOrder = ref<'asc' | 'desc' | ''>('')

const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit' | 'view'>('create')
const approveOpen = ref(false)
const rejectOpen = ref(false)
const deleteOpen = ref(false)
const selectedCourse = ref<AdminCourse | null>(null)

const form = reactive({
  title: '',
  code: '',
  description: '',
  price: 0,
  category_id: '',
  type: '1',
  status: 'draft',
  thumbnail: ''
})

const rejectForm = reactive({ reason: '' })

const columns = [
  { id: 'course', accessorKey: 'title', header: 'Khóa học', sortable: true },
  { id: 'instructor', accessorKey: 'instructor', header: 'Giảng viên' },
  { id: 'category', accessorKey: 'category', header: 'Danh mục' },
  { id: 'stats', accessorKey: 'lessons_count', header: 'Chỉ số' },
  { id: 'status', accessorKey: 'status', header: 'Trạng thái', sortable: true },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

const statusOptions = [
  { label: 'Tất cả trạng thái', value: '' },
  { label: 'Chờ duyệt', value: 'pending_review' },
  { label: 'Đã xuất bản', value: 'published' },
  { label: 'Bản nháp', value: 'draft' },
  { label: 'Bị từ chối', value: 'rejected' },
  { label: 'Đang hoạt động', value: '1' },
  { label: 'Tạm ẩn', value: '0' }
]

const typeOptions = [
  { label: 'Tất cả loại khóa học', value: '' },
  { label: 'Online', value: '1' },
  { label: 'Bài kiểm tra', value: '2' },
  { label: 'Hybrid / Offline', value: '3' }
]

const categoryOptions = computed(() => [
  { label: 'Tất cả danh mục', value: '' },
  ...categories.value.map(item => ({ label: item.name || item.title || `Danh mục #${item.id}`, value: String(item.id) }))
])

const activeFilterCount = computed(() => [filters.status, filters.type, filters.category_id].filter(Boolean).length)

const activeChips = computed(() => {
  const chips: { key: string; label: string }[] = []
  if (filters.status) chips.push({ key: 'status', label: `Trạng thái: ${statusOptions.find(x => x.value === filters.status)?.label}` })
  if (filters.type) chips.push({ key: 'type', label: `Loại: ${typeOptions.find(x => x.value === filters.type)?.label}` })
  if (filters.category_id) chips.push({ key: 'category_id', label: `Danh mục: ${categoryOptions.value.find(x => x.value === filters.category_id)?.label}` })
  return chips
})

const stats = computed(() => {
  const published = courses.value.filter(item => normalizeStatus(item.status) === 'published' || normalizeStatus(item.status) === 'active').length
  const pending = courses.value.filter(item => normalizeStatus(item.status) === 'pending_review').length
  const draft = courses.value.filter(item => normalizeStatus(item.status) === 'draft').length
  const students = courses.value.reduce((sum, item) => sum + Number(item.enrollments_count || item.total_enrolled || 0), 0)
  return [
    { label: 'Tổng khóa học', value: totalCourses.value, subText: 'theo bộ lọc', color: 'info', icon: 'pi-book' },
    { label: 'Đã xuất bản', value: published, subText: 'trên trang hiện tại', color: 'success', icon: 'pi-check-circle' },
    { label: 'Chờ duyệt', value: pending, subText: 'cần kiểm tra', color: 'warning', icon: 'pi-clock' },
    { label: 'Học viên ghi danh', value: students, subText: `${draft} bản nháp`, color: 'purple', icon: 'pi-users' }
  ]
})

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

function normalizeStatus(status: AdminCourse['status']) {
  if (status === 1 || status === '1' || status === true) return 'active'
  if (status === 0 || status === '0' || status === false) return 'inactive'
  return String(status || 'draft')
}

function statusLabel(status: AdminCourse['status']) {
  const value = normalizeStatus(status)
  return ({
    pending_review: 'Chờ duyệt',
    published: 'Đã xuất bản',
    rejected: 'Bị từ chối',
    draft: 'Bản nháp',
    active: 'Đang hoạt động',
    inactive: 'Tạm ẩn'
  } as Record<string, string>)[value] || value
}

function statusClass(status: AdminCourse['status']) {
  const value = normalizeStatus(status)
  return ({
    pending_review: 'bg-amber-50 text-amber-700 border-amber-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 border-rose-200',
    draft: 'bg-slate-100 text-slate-600 border-slate-200',
    active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    inactive: 'bg-slate-100 text-slate-600 border-slate-200'
  } as Record<string, string>)[value] || 'bg-slate-100 text-slate-600 border-slate-200'
}

function typeLabel(type: AdminCourse['type']) {
  return ({ '1': 'Online', '2': 'Quiz', '3': 'Hybrid' } as Record<string, string>)[String(type || '1')] || 'Online'
}

function formatMoney(value: AdminCourse['price']) {
  const num = Number(value || 0)
  return num === 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN').format(num) + ' ₫'
}

function stripHtml(value?: string | null) {
  return String(value || '').replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim()
}

function handleRemoveChip(key: string) {
  if (key in filters) {
    filters[key as keyof typeof filters] = ''
    fetchCourses(1)
  }
}

function handleSort(event: { key: string; order: 'asc' | 'desc' | '' }) {
  sortBy.value = event.key
  sortOrder.value = event.order
  fetchCourses(1)
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  filters.type = ''
  filters.category_id = ''
  sortBy.value = ''
  sortOrder.value = ''
  fetchCourses(1)
}

async function fetchCourses(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (filters.search.trim()) query.set('search', filters.search.trim())
    if (filters.status) query.set('status', filters.status)
    if (filters.type) query.set('type', filters.type)
    if (filters.category_id) query.set('category_id', filters.category_id)
    if (sortBy.value && sortOrder.value) {
      query.set('sort_by', sortBy.value)
      query.set('sort_order', sortOrder.value)
    }

    const response = await useApi<CourseListResponse>(`/admin/courses?${query.toString()}`, { headers: authHeaders() })
    courses.value = response.data || []
    currentPage.value = response.current_page || page
    lastPage.value = response.last_page || 1
    totalCourses.value = response.total || courses.value.length
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải danh sách khóa học.'
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  try {
    const response = await useApi<any>('/admin/categories?per_page=200', { headers: authHeaders() })
    categories.value = Array.isArray(response) ? response : (response.data || [])
  } catch (_) {
    categories.value = []
  }
}

function resetForm() {
  Object.assign(form, { title: '', code: '', description: '', price: 0, category_id: '', type: '1', status: 'draft', thumbnail: '' })
}

function openCreateModal() {
  modalMode.value = 'create'
  selectedCourse.value = null
  resetForm()
  modalOpen.value = true
}

function openEditModal(course: AdminCourse) {
  modalMode.value = 'edit'
  selectedCourse.value = course
  Object.assign(form, {
    title: course.title || '',
    code: course.code || '',
    description: stripHtml(course.description),
    price: Number(course.price || 0),
    category_id: course.category_id ? String(course.category_id) : course.category?.id ? String(course.category.id) : '',
    type: String(course.type || '1'),
    status: normalizeStatus(course.status),
    thumbnail: course.thumbnail || ''
  })
  modalOpen.value = true
}

function openViewModal(course: AdminCourse) {
  modalMode.value = 'view'
  selectedCourse.value = course
  modalOpen.value = true
}

async function saveCourse() {
  if (!form.title.trim()) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  const payload = {
    title: form.title.trim(),
    code: form.code || null,
    description: form.description || null,
    price: Number(form.price || 0),
    category_id: form.category_id ? Number(form.category_id) : null,
    type: Number(form.type || 1),
    status: form.status,
    thumbnail: form.thumbnail || null
  }
  try {
    if (modalMode.value === 'create') {
      await useApi('/courses', { method: 'POST', headers: authHeaders(), body: payload })
      successMessage.value = 'Đã tạo khóa học mới.'
      toast.success('Tạo khóa học thành công')
    } else if (selectedCourse.value) {
      await useApi(`/admin/courses/${selectedCourse.value.id}`, { method: 'PUT', headers: authHeaders(), body: payload })
      successMessage.value = 'Đã cập nhật khóa học.'
      toast.success('Cập nhật khóa học thành công')
    }
    modalOpen.value = false
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể lưu khóa học.'
    toast.error('Không thể lưu khóa học', errorMessage.value)
  } finally {
    saving.value = false
  }
}

async function approveCourse() {
  if (!selectedCourse.value) return
  saving.value = true
  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}/approve`, { method: 'PUT', headers: authHeaders() })
    approveOpen.value = false
    toast.success('Đã phê duyệt khóa học')
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    toast.error('Không thể phê duyệt', error?.data?.message)
  } finally {
    saving.value = false
  }
}

async function rejectCourse() {
  if (!selectedCourse.value || !rejectForm.reason.trim()) return
  saving.value = true
  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}/reject`, { method: 'PUT', headers: authHeaders(), body: { reject_reason: rejectForm.reason } })
    rejectOpen.value = false
    rejectForm.reason = ''
    toast.warning('Đã từ chối khóa học')
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    toast.error('Không thể từ chối', error?.data?.message)
  } finally {
    saving.value = false
  }
}

async function deleteCourse() {
  if (!selectedCourse.value) return
  saving.value = true
  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}`, { method: 'DELETE', headers: authHeaders() })
    deleteOpen.value = false
    toast.success('Đã xóa khóa học')
    await fetchCourses(1)
  } catch (error: any) {
    toast.error('Không thể xóa khóa học', error?.data?.message)
  } finally {
    saving.value = false
  }
}

function exportData() {
  exportToCSV(courses.value, [
    { key: 'id', label: 'ID' },
    { key: 'title', label: 'Tên khóa học' },
    { key: 'status', label: 'Trạng thái', format: (value: any) => statusLabel(value) },
    { key: 'price', label: 'Giá', format: (value: any) => formatMoney(value) }
  ], 'danh_sach_khoa_hoc')
}

onMounted(async () => {
  await Promise.all([fetchCourses(), fetchCategories()])
})
</script>

<template>
  <AdminWorkspaceShell
    title="Quản lý khóa học"
    subtitle="Xem, cập nhật và kiểm duyệt các khóa học trên hệ thống."
    :breadcrumbs="[{ label: 'Nội dung & Khóa học' }, { label: 'Khóa học' }]"
  >
    <div class="flex flex-col gap-5">
      <UiFilters
        v-model:search="filters.search"
        search-placeholder="Tìm khóa học, mã khóa học, mô tả..."
        :active-filter-count="activeFilterCount"
        :active-chips="activeChips"
        :show-export="true"
        export-text="Xuất danh sách"
        :always-open="true"
        @submit-search="fetchCourses(1)"
        @reset-filters="resetFilters"
        @remove-chip="handleRemoveChip"
        @export="exportData"
      >
        <template #actions>
          <button class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-xs font-bold text-white transition hover:bg-[#178563]" type="button" @click="openCreateModal">
            <i class="pi pi-plus" />
            Tạo khóa học
          </button>
        </template>

        <template #advanced>
          <label class="flex min-w-[170px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Trạng thái</span>
            <UiSelect v-model="filters.status" :options="statusOptions" placeholder="Tất cả trạng thái" @update:modelValue="fetchCourses(1)" />
          </label>
          <label class="flex min-w-[170px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Loại khóa học</span>
            <UiSelect v-model="filters.type" :options="typeOptions" placeholder="Tất cả loại" @update:modelValue="fetchCourses(1)" />
          </label>
          <label class="flex min-w-[170px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Danh mục</span>
            <UiSelect v-model="filters.category_id" :options="categoryOptions" placeholder="Tất cả danh mục" @update:modelValue="fetchCourses(1)" />
          </label>
        </template>
      </UiFilters>

      <UiKpiCards :items="stats" />

      <div v-if="successMessage" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">
        <i class="pi pi-check-circle" /> {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
        <i class="pi pi-exclamation-circle" /> {{ errorMessage }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-[var(--line)] bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-[var(--line)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-base font-bold text-[var(--text)]">Danh sách khóa học</h3>
            <p class="text-xs text-[var(--muted)]">{{ totalCourses }} khóa học phù hợp bộ lọc hiện tại</p>
          </div>
          <NuxtLink to="/admin/manage-courses" class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50">
            <i class="pi pi-verified" />
            Kiểm duyệt nâng cao
          </NuxtLink>
        </div>

        <UiTable :columns="columns" :data="courses" :loading="loading" :sort-by="sortBy" :sort-order="sortOrder" @sort="handleSort">
          <template #course-cell="{ row }">
            <div class="flex min-w-[280px] items-center gap-3">
              <div class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-100 bg-slate-50 text-slate-400">
                <img v-if="row.original.thumbnail" :src="row.original.thumbnail" :alt="row.original.title" class="h-full w-full object-cover" />
                <i v-else class="pi pi-book text-lg" />
              </div>
              <div class="min-w-0">
                <strong class="block truncate text-sm font-bold text-[var(--text)]">{{ row.original.title || 'Chưa đặt tên' }}</strong>
                <p class="mt-0.5 truncate text-xs text-[var(--muted)]">{{ row.original.code || row.original.slug || 'Chưa có mã khóa học' }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <span class="rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">{{ typeLabel(row.original.type) }}</span>
                  <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-500">{{ formatMoney(row.original.price) }}</span>
                </div>
              </div>
            </div>
          </template>

          <template #instructor-cell="{ row }">
            <span class="text-xs font-semibold text-[var(--text)]">{{ row.original.instructor?.name || '—' }}</span>
          </template>

          <template #category-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ row.original.category?.name || row.original.category?.title || '—' }}</span>
          </template>

          <template #stats-cell="{ row }">
            <div class="flex flex-col gap-0.5 text-xs">
              <span class="font-semibold text-[var(--text)]">{{ row.original.lessons_count || 0 }} bài học</span>
              <span class="text-[var(--muted)]">{{ row.original.enrollments_count || row.original.total_enrolled || 0 }} học viên</span>
            </div>
          </template>

          <template #status-cell="{ row }">
            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="statusClass(row.original.status)">
              {{ statusLabel(row.original.status) }}
            </span>
          </template>

          <template #actions-cell="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <button class="ds-btn ds-btn--view ds-btn--icon" title="Xem" type="button" @click="openViewModal(row.original)"><i class="pi pi-eye" /></button>
              <button class="ds-btn ds-btn--edit ds-btn--icon" title="Sửa" type="button" @click="openEditModal(row.original)"><i class="pi pi-pencil" /></button>
              <button class="ds-btn ds-btn--view ds-btn--icon" title="Phê duyệt" type="button" @click="selectedCourse = row.original; approveOpen = true"><i class="pi pi-check" /></button>
              <button class="ds-btn ds-btn--delete ds-btn--icon" title="Từ chối" type="button" @click="selectedCourse = row.original; rejectForm.reason = row.original.reject_reason || ''; rejectOpen = true"><i class="pi pi-times" /></button>
              <button class="ds-btn ds-btn--delete ds-btn--icon" title="Xóa" type="button" @click="selectedCourse = row.original; deleteOpen = true"><i class="pi pi-trash" /></button>
            </div>
          </template>
        </UiTable>

        <DataTableFooter :current="currentPage" :last="lastPage" :total="totalCourses" :per-page="perPage" @page="fetchCourses" @update:per-page="perPage = $event; fetchCourses(1)" />
      </section>
    </div>

    <UModal v-model:open="modalOpen" :title="modalMode === 'create' ? 'Tạo khóa học' : modalMode === 'edit' ? 'Chỉnh sửa khóa học' : 'Chi tiết khóa học'" :ui="{ width: 'max-w-2xl' }">
      <div v-if="modalMode === 'view' && selectedCourse" class="space-y-5">
        <div class="flex gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
          <div class="flex h-24 w-36 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white text-slate-400">
            <img v-if="selectedCourse.thumbnail" :src="selectedCourse.thumbnail" class="h-full w-full object-cover" />
            <i v-else class="pi pi-book text-2xl" />
          </div>
          <div class="min-w-0">
            <h3 class="text-lg font-bold text-[var(--text)]">{{ selectedCourse.title }}</h3>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ selectedCourse.code || selectedCourse.slug || 'Chưa có mã khóa học' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="ds-badge ds-badge--info">{{ typeLabel(selectedCourse.type) }}</span>
              <span class="inline-flex h-5 items-center rounded-full border px-2 text-[10px] font-bold" :class="statusClass(selectedCourse.status)">{{ statusLabel(selectedCourse.status) }}</span>
              <span class="ds-badge">{{ formatMoney(selectedCourse.price) }}</span>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-xs">
          <div><p class="font-bold uppercase text-[var(--muted)]">Giảng viên</p><p class="mt-1 font-semibold">{{ selectedCourse.instructor?.name || '—' }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Danh mục</p><p class="mt-1 font-semibold">{{ selectedCourse.category?.name || selectedCourse.category?.title || '—' }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Bài học</p><p class="mt-1 font-semibold">{{ selectedCourse.lessons_count || 0 }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Học viên</p><p class="mt-1 font-semibold">{{ selectedCourse.enrollments_count || selectedCourse.total_enrolled || 0 }}</p></div>
        </div>
        <div>
          <p class="text-xs font-bold uppercase text-[var(--muted)]">Mô tả</p>
          <p class="mt-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm leading-6 text-slate-600">{{ stripHtml(selectedCourse.description) || 'Chưa có mô tả.' }}</p>
        </div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="saveCourse">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-bold text-[var(--text)]">Tên khóa học</span>
            <input v-model="form.title" required class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Mã khóa học</span>
            <input v-model="form.code" class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Giá</span>
            <input v-model="form.price" type="number" min="0" class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Loại khóa học</span>
            <UiSelect v-model="form.type" :options="typeOptions.filter(x => x.value !== '')" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Danh mục</span>
            <UiSelect v-model="form.category_id" :options="categoryOptions.filter(x => x.value !== '')" placeholder="Chọn danh mục" />
          </label>
          <label class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-bold text-[var(--text)]">Thumbnail URL</span>
            <input v-model="form.thumbnail" class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" />
          </label>
          <label class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-bold text-[var(--text)]">Mô tả</span>
            <textarea v-model="form.description" rows="4" class="rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 py-3 text-sm outline-none focus:border-[#1d9e75]" />
          </label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="h-10 rounded-xl border border-[var(--line)] px-4 text-xs font-bold text-[var(--muted)] hover:bg-slate-50" @click="modalOpen = false">Hủy</button>
          <button type="submit" :disabled="saving" class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-5 text-xs font-bold text-white hover:bg-[#178563] disabled:opacity-60">
            <i v-if="saving" class="pi pi-spin pi-spinner" />
            {{ modalMode === 'create' ? 'Tạo khóa học' : 'Lưu thay đổi' }}
          </button>
        </div>
      </form>
    </UModal>

    <UModal v-model:open="rejectOpen" title="Từ chối khóa học" subtitle="Kiểm duyệt" :ui="{ width: 'max-w-xl' }">
      <div class="space-y-4">
        <p class="text-sm text-[var(--muted)]">Nhập lý do từ chối để giảng viên có thể chỉnh sửa và gửi duyệt lại.</p>
        <textarea v-model="rejectForm.reason" rows="5" placeholder="Ví dụ: Thumbnail chưa đúng chuẩn, nội dung mô tả chưa rõ..." class="w-full rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 py-3 text-sm outline-none focus:border-[#1d9e75]" />
        <div class="flex justify-end gap-3">
          <button class="h-10 rounded-xl border border-[var(--line)] px-4 text-xs font-bold text-[var(--muted)] hover:bg-slate-50" type="button" @click="rejectOpen = false">Đóng</button>
          <button class="h-10 rounded-xl bg-red-600 px-5 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-60" type="button" :disabled="saving || !rejectForm.reason.trim()" @click="rejectCourse">Xác nhận từ chối</button>
        </div>
      </div>
    </UModal>

    <CrudConfirmModal :open="approveOpen" title="Phê duyệt khóa học" :description="`Xác nhận phê duyệt khóa học ${selectedCourse?.title || ''}?`" confirm-text="Phê duyệt" :loading="saving" @close="approveOpen = false" @confirm="approveCourse" />
    <CrudConfirmModal :open="deleteOpen" title="Xóa khóa học" :description="`Bạn có chắc chắn muốn xóa khóa học ${selectedCourse?.title || ''}? Hành động này không thể hoàn tác.`" confirm-text="Xóa khóa học" tone="danger" :loading="saving" @close="deleteOpen = false" @confirm="deleteCourse" />
  </AdminWorkspaceShell>
</template>
