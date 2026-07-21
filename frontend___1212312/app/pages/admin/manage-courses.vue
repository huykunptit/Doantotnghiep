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

interface CourseItem {
  id: number
  title: string
  slug?: string | null
  code?: string | null
  description?: string | null
  thumbnail?: string | null
  status?: string | number | boolean | null
  reject_reason?: string | null
  published_at?: string | null
  created_at?: string | null
  updated_at?: string | null
  instructor?: { id: number; name: string; avatar?: string | null } | null
  category?: { id: number; name: string; slug?: string | null } | null
  lessons_count?: number
  enrollments_count?: number
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
  per_page?: number
}

const token = useAuthTokenCookie()
const toast = useToast()
const { exportToCSV } = useExport()

const courses = ref<CourseItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalCourses = ref(0)
const perPage = ref(15)

const filters = reactive({
  search: '',
  status: ''
})

const selectedCourse = ref<CourseItem | null>(null)
const detailOpen = ref(false)
const approveOpen = ref(false)
const rejectOpen = ref(false)
const rejectForm = reactive({ reason: '' })

const columns = [
  { id: 'course', accessorKey: 'title', header: 'Khóa học', sortable: true },
  { id: 'instructor', accessorKey: 'instructor', header: 'Giảng viên' },
  { id: 'category', accessorKey: 'category', header: 'Danh mục' },
  { id: 'stats', accessorKey: 'lessons_count', header: 'Nội dung', class: 'text-center' },
  { id: 'status', accessorKey: 'status', header: 'Trạng thái' },
  { id: 'updated', accessorKey: 'updated_at', header: 'Cập nhật' },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

const statusOptions = [
  { label: 'Tất cả trạng thái', value: '' },
  { label: 'Chờ duyệt', value: 'pending_review' },
  { label: 'Bản nháp', value: 'draft' },
  { label: 'Đã xuất bản', value: 'published' },
  { label: 'Bị từ chối', value: 'rejected' },
  { label: 'Đang hoạt động', value: '1' },
  { label: 'Tạm ẩn', value: '0' }
]

const activeFilterCount = computed(() => filters.status ? 1 : 0)
const activeChips = computed(() => {
  if (!filters.status) return []
  return [{ key: 'status', label: `Trạng thái: ${statusOptions.find(item => item.value === filters.status)?.label || filters.status}` }]
})

const stats = computed(() => {
  const pending = courses.value.filter(item => normalizeStatus(item.status) === 'pending_review').length
  const published = courses.value.filter(item => normalizeStatus(item.status) === 'published' || normalizeStatus(item.status) === 'active').length
  const rejected = courses.value.filter(item => normalizeStatus(item.status) === 'rejected').length
  const draft = courses.value.filter(item => normalizeStatus(item.status) === 'draft').length

  return [
    { label: 'Tổng khóa học', value: totalCourses.value, subText: 'theo bộ lọc', color: 'info', icon: 'pi-book' },
    { label: 'Chờ duyệt', value: pending, subText: 'trang hiện tại', color: 'warning', icon: 'pi-clock' },
    { label: 'Đã xuất bản', value: published, subText: 'trang hiện tại', color: 'success', icon: 'pi-check-circle' },
    { label: 'Bị từ chối', value: rejected, subText: `${draft} bản nháp`, color: 'danger', icon: 'pi-times-circle' }
  ]
})

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

function normalizeStatus(status: CourseItem['status']) {
  if (status === 1 || status === '1' || status === true) return 'active'
  if (status === 0 || status === '0' || status === false) return 'inactive'
  return String(status || 'draft')
}

function statusLabel(status: CourseItem['status']) {
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

function statusClass(status: CourseItem['status']) {
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

function stripHtml(value?: string | null) {
  return String(value || '').replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim()
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  fetchCourses(1)
}

function removeChip(key: string) {
  if (key === 'status') filters.status = ''
  fetchCourses(1)
}

async function fetchCourses(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (filters.search.trim()) query.set('search', filters.search.trim())
    if (filters.status) query.set('status', filters.status)

    const response = await useApi<PaginatedResponse<CourseItem>>(`/admin/courses?${query.toString()}`, { headers: authHeaders() })
    courses.value = response.data || []
    currentPage.value = response.current_page || page
    lastPage.value = response.last_page || 1
    totalCourses.value = response.total || courses.value.length
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải danh sách khóa học kiểm duyệt.'
    toast.error('Không thể tải khóa học', errorMessage.value)
  } finally {
    loading.value = false
  }
}

function openDetail(course: CourseItem) {
  selectedCourse.value = course
  detailOpen.value = true
}

function openApprove(course: CourseItem) {
  selectedCourse.value = course
  approveOpen.value = true
}

function openReject(course: CourseItem) {
  selectedCourse.value = course
  rejectForm.reason = course.reject_reason || ''
  rejectOpen.value = true
}

async function approveCourse() {
  if (!selectedCourse.value) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}/approve`, { method: 'PUT', headers: authHeaders() })
    successMessage.value = 'Phê duyệt khóa học thành công.'
    toast.success('Đã phê duyệt khóa học')
    approveOpen.value = false
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể phê duyệt khóa học.'
    toast.error('Không thể phê duyệt', errorMessage.value)
  } finally {
    saving.value = false
  }
}

async function rejectCourse() {
  if (!selectedCourse.value || !rejectForm.reason.trim()) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}/reject`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { reject_reason: rejectForm.reason.trim() }
    })
    successMessage.value = 'Đã từ chối khóa học và gửi lý do cho giảng viên.'
    toast.warning('Đã từ chối khóa học')
    rejectOpen.value = false
    rejectForm.reason = ''
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể từ chối khóa học.'
    toast.error('Không thể từ chối', errorMessage.value)
  } finally {
    saving.value = false
  }
}

function exportData() {
  exportToCSV(courses.value, [
    { key: 'id', label: 'ID' },
    { key: 'title', label: 'Tên khóa học' },
    { key: 'status', label: 'Trạng thái', format: (value: any) => statusLabel(value) },
    { key: 'lessons_count', label: 'Số bài học' },
    { key: 'enrollments_count', label: 'Số học viên' }
  ], 'kiem_duyet_khoa_hoc')
}

onMounted(() => fetchCourses())
</script>

<template>
  <AdminWorkspaceShell
    title="Kiểm duyệt khóa học"
    subtitle="Rà soát nội dung, phê duyệt xuất bản hoặc yêu cầu giảng viên chỉnh sửa."
    :breadcrumbs="[{ label: 'Nội dung học tập' }, { label: 'Kiểm duyệt khóa học' }]"
  >
    <div class="flex flex-col gap-5">
      <UiFilters
        v-model:search="filters.search"
        search-placeholder="Tìm khóa học theo tên hoặc mô tả..."
        :active-filter-count="activeFilterCount"
        :active-chips="activeChips"
        :show-export="true"
        export-text="Xuất kiểm duyệt"
        :always-open="true"
        @submit-search="fetchCourses(1)"
        @reset-filters="resetFilters"
        @remove-chip="removeChip"
        @export="exportData"
      >
        <template #actions>
          <button class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition hover:bg-slate-50" type="button" @click="fetchCourses(currentPage)">
            <i class="pi pi-refresh" /> Làm mới
          </button>
        </template>
        <template #advanced>
          <label class="flex min-w-[190px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Trạng thái</span>
            <UiSelect v-model="filters.status" :options="statusOptions" placeholder="Tất cả trạng thái" @update:modelValue="fetchCourses(1)" />
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
            <h3 class="text-base font-bold text-[var(--text)]">Danh sách khóa học cần quản trị</h3>
            <p class="text-xs text-[var(--muted)]">{{ totalCourses }} khóa học phù hợp bộ lọc hiện tại</p>
          </div>
          <NuxtLink to="/admin/courses" class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50">
            <i class="pi pi-book" /> Quản lý khóa học
          </NuxtLink>
        </div>

        <UiTable :columns="columns" :data="courses" :loading="loading">
          <template #course-cell="{ row }">
            <div class="flex min-w-[280px] items-center gap-3">
              <div class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-100 bg-slate-50 text-slate-400">
                <img v-if="row.original.thumbnail" :src="row.original.thumbnail" :alt="row.original.title" class="h-full w-full object-cover" />
                <i v-else class="pi pi-book text-lg" />
              </div>
              <div class="min-w-0">
                <strong class="block truncate text-sm font-bold text-[var(--text)]">{{ row.original.title || 'Chưa đặt tên' }}</strong>
                <p class="mt-0.5 truncate text-xs text-[var(--muted)]">{{ row.original.code || row.original.slug || 'Chưa có mã khóa học' }}</p>
              </div>
            </div>
          </template>

          <template #instructor-cell="{ row }">
            <span class="text-xs font-semibold text-[var(--text)]">{{ row.original.instructor?.name || '—' }}</span>
          </template>

          <template #category-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ row.original.category?.name || '—' }}</span>
          </template>

          <template #stats-cell="{ row }">
            <div class="text-center text-xs">
              <p class="font-bold text-[var(--text)]">{{ row.original.lessons_count || 0 }} bài</p>
              <p class="mt-0.5 text-[var(--muted)]">{{ row.original.enrollments_count || 0 }} học viên</p>
            </div>
          </template>

          <template #status-cell="{ row }">
            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="statusClass(row.original.status)">
              {{ statusLabel(row.original.status) }}
            </span>
          </template>

          <template #updated-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ formatDate(row.original.updated_at || row.original.created_at) }}</span>
          </template>

          <template #actions-cell="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <button class="ds-btn ds-btn--view ds-btn--icon" title="Xem chi tiết" type="button" @click="openDetail(row.original)"><i class="pi pi-eye" /></button>
              <button class="ds-btn ds-btn--edit ds-btn--icon" title="Phê duyệt" type="button" @click="openApprove(row.original)"><i class="pi pi-check" /></button>
              <button class="ds-btn ds-btn--delete ds-btn--icon" title="Từ chối" type="button" @click="openReject(row.original)"><i class="pi pi-times" /></button>
            </div>
          </template>
        </UiTable>

        <DataTableFooter :current="currentPage" :last="lastPage" :total="totalCourses" :per-page="perPage" @page="fetchCourses" @update:per-page="perPage = $event; fetchCourses(1)" />
      </section>
    </div>

    <UModal v-model:open="detailOpen" title="Chi tiết khóa học kiểm duyệt" :ui="{ width: 'max-w-2xl' }">
      <div v-if="selectedCourse" class="space-y-5">
        <div class="flex gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
          <div class="flex h-24 w-36 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white text-slate-400">
            <img v-if="selectedCourse.thumbnail" :src="selectedCourse.thumbnail" class="h-full w-full object-cover" />
            <i v-else class="pi pi-book text-2xl" />
          </div>
          <div class="min-w-0">
            <h3 class="text-lg font-bold text-[var(--text)]">{{ selectedCourse.title }}</h3>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ selectedCourse.code || selectedCourse.slug || 'Chưa có mã khóa học' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="statusClass(selectedCourse.status)">{{ statusLabel(selectedCourse.status) }}</span>
              <span class="ds-badge">{{ selectedCourse.lessons_count || 0 }} bài học</span>
              <span class="ds-badge">{{ selectedCourse.enrollments_count || 0 }} học viên</span>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-xs">
          <div><p class="font-bold uppercase text-[var(--muted)]">Giảng viên</p><p class="mt-1 font-semibold">{{ selectedCourse.instructor?.name || '—' }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Danh mục</p><p class="mt-1 font-semibold">{{ selectedCourse.category?.name || '—' }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Ngày tạo</p><p class="mt-1 font-semibold">{{ formatDate(selectedCourse.created_at) }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Xuất bản</p><p class="mt-1 font-semibold">{{ formatDate(selectedCourse.published_at) }}</p></div>
        </div>
        <div>
          <p class="text-xs font-bold uppercase text-[var(--muted)]">Mô tả</p>
          <p class="mt-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm leading-6 text-slate-600">{{ stripHtml(selectedCourse.description) || 'Chưa có mô tả.' }}</p>
        </div>
        <div v-if="selectedCourse.reject_reason">
          <p class="text-xs font-bold uppercase text-rose-600">Lý do từ chối gần nhất</p>
          <p class="mt-2 rounded-xl border border-rose-100 bg-rose-50 p-3 text-sm leading-6 text-rose-700">{{ selectedCourse.reject_reason }}</p>
        </div>
      </div>
    </UModal>

    <UModal v-model:open="rejectOpen" title="Từ chối khóa học" subtitle="Kiểm duyệt" :ui="{ width: 'max-w-xl' }">
      <div class="space-y-4">
        <p class="text-sm text-[var(--muted)]">Nhập lý do từ chối để giảng viên chỉnh sửa và gửi duyệt lại.</p>
        <textarea v-model="rejectForm.reason" rows="5" placeholder="Ví dụ: Thumbnail chưa đúng chuẩn, mô tả chưa rõ, thiếu bài học..." class="w-full rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 py-3 text-sm outline-none focus:border-[#1d9e75]" />
        <div class="flex justify-end gap-3">
          <button class="h-10 rounded-xl border border-[var(--line)] px-4 text-xs font-bold text-[var(--muted)] hover:bg-slate-50" type="button" @click="rejectOpen = false">Đóng</button>
          <button class="h-10 rounded-xl bg-red-600 px-5 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-60" type="button" :disabled="saving || !rejectForm.reason.trim()" @click="rejectCourse">
            {{ saving ? 'Đang xử lý...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </div>
    </UModal>

    <CrudConfirmModal
      :open="approveOpen"
      title="Phê duyệt khóa học"
      :description="`Xác nhận phê duyệt và xuất bản khóa học ${selectedCourse?.title || ''}?`"
      confirm-text="Phê duyệt"
      :loading="saving"
      @close="approveOpen = false"
      @confirm="approveCourse"
    />
  </AdminWorkspaceShell>
</template>