<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import { useAdminUpload } from '~/composables/useAdminUpload'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'


definePageMeta({ layout: 'admin' })

// ─── Types ───────────────────────────────────────────────────────────────────

interface AdminCourse {
  id: number
  title: string
  description?: string
  thumbnail?: string | null
  status: string
  reject_reason?: string | null
  lessons_count?: number
  enrollments_count?: number
  instructor?: { name: string; avatar?: string | null } | null
  category?: { name: string } | null
}

interface CategoryItem {
  id: number
  name: string
}

interface CourseListResponse {
  data: AdminCourse[]
  current_page: number
  last_page: number
  total: number
}

// ─── Auth guard ───────────────────────────────────────────────────────────────

const user = useAuthUserCookie()
const token = useAuthTokenCookie()
if (!user.value || !token.value) await navigateTo('/login', { replace: true })

// ─── Composables & helpers ───────────────────────────────────────────────────

const { uploadImage } = useAdminUpload()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const toast = useToast()

// ─── State ────────────────────────────────────────────────────────────────────

const search = ref('')
const status = ref('pending_review')
const loading = ref(false)
const courses = ref<AdminCourse[]>([])
const expandedRows = ref({})
const categories = ref<CategoryItem[]>([])

const currentPage = ref(1)
const lastPage = ref(1)
const totalCourses = ref(0)
const perPage = ref(10)


// Modal visibility
const approveOpen = ref(false)
const rejectOpen = ref(false)
const createOpen = ref(false)

const selectedCourse = ref<AdminCourse | null>(null)

const rejectForm = reactive({ reason: '' })

const createForm = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: '',
  thumbnail: '',
})

const createSaving = ref(false)
const thumbnailFile = ref<File | null>(null)
const uploadingThumbnail = ref(false)

// ─── Constants ────────────────────────────────────────────────────────────────

const statuses = [
  { label: 'Tất cả', value: '' },
  { label: 'Chờ duyệt', value: 'pending_review' },
  { label: 'Đã xuất bản', value: 'published' },
  { label: 'Bản nháp', value: 'draft' },
  { label: 'Bị từ chối', value: 'rejected' },
]

// ─── Computed ─────────────────────────────────────────────────────────────────

// ─── Utilities ────────────────────────────────────────────────────────────────

const statusLabel = (value: string) =>
  ({
    pending_review: 'Chờ duyệt',
    published: 'Đã xuất bản',
    rejected: 'Bị từ chối',
    draft: 'Bản nháp',
  }[value] || value)

const statusSeverity = (value: string) =>
  ({ pending_review: 'warn', published: 'success', rejected: 'danger', draft: 'secondary' }[value] || 'info')

// ─── API calls ────────────────────────────────────────────────────────────────

async function fetchCourses(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (search.value.trim()) query.set('search', search.value.trim())
    if (status.value) query.set('status', status.value)

    const response = await useApi<CourseListResponse>(`/admin/courses?${query.toString()}`, {
      headers: authHeaders(),
    })

    courses.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    totalCourses.value = response.total
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tải danh sách khóa học.')
  } finally {
    loading.value = false
  }
}

async function fetchCategories() {
  categories.value = await useApi<CategoryItem[]>('/admin/categories', {
    headers: authHeaders(),
  })
}

async function approveCourse() {
  if (!selectedCourse.value) return
  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}/approve`, {
      method: 'PUT',
      headers: authHeaders(),
    })
    toast.success(`Đã phê duyệt khóa học "${selectedCourse.value.title}".`)
    approveOpen.value = false
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể phê duyệt khóa học.')
  }
}

async function rejectCourse() {
  if (!selectedCourse.value || !rejectForm.reason.trim()) return
  try {
    await useApi(`/admin/courses/${selectedCourse.value.id}/reject`, {
      method: 'PUT',
      headers: authHeaders(),
      body: { reject_reason: rejectForm.reason },
    })
    toast.success(`Đã từ chối khóa học "${selectedCourse.value.title}".`)
    rejectOpen.value = false
    rejectForm.reason = ''
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể từ chối khóa học.')
  }
}

async function uploadCourseThumbnail() {
  if (!thumbnailFile.value) return
  uploadingThumbnail.value = true
  try {
    const uploaded = await uploadImage(thumbnailFile.value, 'courses', createForm.thumbnail || null)
    createForm.thumbnail = uploaded.url
  } finally {
    uploadingThumbnail.value = false
  }
}

async function createCourse() {
  if (!createForm.title.trim()) return
  createSaving.value = true
  try {
    await useApi('/courses', {
      method: 'POST',
      headers: authHeaders(),
      body: {
        title: createForm.title.trim(),
        description: createForm.description || null,
        price: Number(createForm.price || 0),
        category_id: createForm.category_id ? Number(createForm.category_id) : null,
        thumbnail: createForm.thumbnail || null,
      },
    })
    toast.success('Đã tạo khóa học mới ở trạng thái bản nháp.')
    createOpen.value = false
    Object.assign(createForm, { title: '', description: '', price: 0, category_id: '', thumbnail: '' })
    thumbnailFile.value = null
    status.value = 'draft'
    await fetchCourses(1)
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tạo khóa học.')
  } finally {
    createSaving.value = false
  }
}

// ─── Export & Pagination ───────────────────────────────────────────────────────

const { exportToCSV } = useExport()

function exportData() {
  const cols = [
    { key: 'id', label: 'ID Khóa học' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'category', label: 'Danh mục', format: (_: any, row: AdminCourse) => row.category?.name || '--' },
    { key: 'instructor', label: 'Giảng viên', format: (_: any, row: AdminCourse) => row.instructor?.name || '--' },
    { key: 'status', label: 'Trạng thái', format: (val: any) => statusLabel(val) },
    { key: 'lessons_count', label: 'Số bài học', format: (val: any) => String(val || 0) },
    { key: 'enrollments_count', label: 'Số học viên', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(courses.value, cols, 'danh_sach_kiem_duyet_khoa_hoc')
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([fetchCourses(), fetchCategories()])
})
</script>

<template>
  <div class="flex flex-col gap-5">
    <header><p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Khóa học</p><h1 class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-0">Kiểm duyệt khóa học</h1><p class="mt-1 text-sm text-surface-500">Rà soát nội dung, giảng viên và trạng thái xuất bản.</p></header>
    <Card><template #content>
      <form class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between" @submit.prevent="fetchCourses(1)">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row"><IconField class="w-full sm:max-w-md"><InputIcon class="pi pi-search" /><InputText v-model="search" class="w-full" placeholder="Tìm khóa học..." /></IconField><Select v-model="status" :options="statuses" option-label="label" option-value="value" class="w-full sm:w-48" /><Button label="Tìm kiếm" icon="pi pi-search" severity="secondary" type="submit" /></div>
        <div class="flex gap-2"><Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined @click="exportData" /><Button label="Tạo khóa học" icon="pi pi-plus" @click="createOpen = true" /></div>
      </form>
      <DataTable v-model:expanded-rows="expandedRows" :value="courses" data-key="id" :loading="loading" striped-rows responsive-layout="scroll">
        <template #empty>Chưa có khóa học phù hợp bộ lọc.</template><Column expander style="width:3rem" />
        <Column header="Khóa học"><template #body="{data}"><div class="flex min-w-64 items-center gap-3"><img v-if="data.thumbnail" :src="data.thumbnail" :alt="data.title" class="h-12 w-20 rounded-lg object-cover" /><div v-else class="grid h-12 w-20 place-items-center rounded-lg bg-surface-100 dark:bg-surface-800">📘</div><strong>{{ data.title }}</strong></div></template></Column>
        <Column header="Giảng viên"><template #body="{data}">{{ data.instructor?.name || '—' }}</template></Column>
        <Column header="Danh mục"><template #body="{data}">{{ data.category?.name || '—' }}</template></Column>
        <Column header="Trạng thái"><template #body="{data}"><Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" /></template></Column>
        <Column header="Nội dung"><template #body="{data}">{{ data.lessons_count || 0 }} bài · {{ data.enrollments_count || 0 }} học viên</template></Column>
        <Column header="Thao tác"><template #body="{data}"><div class="flex gap-1"><Button icon="pi pi-eye" text rounded aria-label="Chi tiết" @click="navigateTo(`/admin/courses/${data.id}`)" /><Button icon="pi pi-check" severity="success" text rounded aria-label="Phê duyệt" @click="selectedCourse=data;approveOpen=true" /><Button icon="pi pi-times" severity="danger" text rounded aria-label="Từ chối" @click="selectedCourse=data;rejectForm.reason=data.reject_reason||'';rejectOpen=true" /></div></template></Column>
        <template #expansion="{data}"><div class="p-4"><strong>Mô tả</strong><RichTextContent :content="data.description" compact empty-text="Chưa có mô tả." /><Button class="mt-3" label="Ngân hàng câu hỏi" icon="pi pi-question-circle" severity="secondary" outlined @click="navigateTo('/admin/question-bank')" /></div></template>
      </DataTable>
      <DataTableFooter :current="currentPage" :last="lastPage" :total="totalCourses" :per-page="perPage" @page="fetchCourses" @update:per-page="perPage=$event;fetchCourses(1)" />
    </template></Card>
    <Dialog v-model:visible="approveOpen" modal header="Phê duyệt khóa học" class="w-[min(30rem,calc(100vw-2rem))]"><p>Xác nhận phê duyệt <strong>{{ selectedCourse?.title }}</strong>? Khóa học sẽ được hiển thị cho học viên.</p><template #footer><Button label="Hủy" severity="secondary" text @click="approveOpen=false" /><Button label="Phê duyệt" icon="pi pi-check" @click="approveCourse" /></template></Dialog>
    <Dialog v-model:visible="rejectOpen" modal header="Từ chối khóa học" class="w-[min(42rem,calc(100vw-2rem))]"><p class="mb-4 text-sm text-surface-500">{{ selectedCourse?.title }}</p><label class="grid gap-2"><span class="font-medium">Lý do từ chối</span><RichTextEditor v-model="rejectForm.reason" placeholder="Nhập lý do để giảng viên chỉnh sửa..." enable-images upload-folder="courses" /></label><template #footer><Button label="Hủy" severity="secondary" text @click="rejectOpen=false" /><Button label="Xác nhận từ chối" severity="danger" :disabled="!rejectForm.reason.trim()" @click="rejectCourse" /></template></Dialog>
    <Dialog v-model:visible="createOpen" modal header="Tạo khóa học" class="w-[min(48rem,calc(100vw-2rem))]">
      <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-2 sm:col-span-2"><span class="font-medium">Tên khóa học</span><InputText v-model="createForm.title" /></label><label class="grid gap-2"><span class="font-medium">Giá</span><InputNumber v-model="createForm.price" :min="0" fluid /></label><label class="grid gap-2"><span class="font-medium">Danh mục</span><Select v-model="createForm.category_id" :options="categories" option-label="name" option-value="id" placeholder="Chọn danh mục" fluid /></label><div class="grid gap-2 sm:col-span-2"><span class="font-medium">Mô tả</span><RichTextEditor v-model="createForm.description" enable-images upload-folder="courses" /></div><div class="grid gap-2 sm:col-span-2"><span class="font-medium">Ảnh khóa học</span><input type="file" accept="image/*" @change="thumbnailFile=($event.target as HTMLInputElement)?.files?.[0]||null"><Button label="Tải ảnh lên" icon="pi pi-upload" severity="secondary" outlined :loading="uploadingThumbnail" :disabled="!thumbnailFile" @click="uploadCourseThumbnail" /><img v-if="createForm.thumbnail" :src="createForm.thumbnail" class="max-h-48 rounded-lg object-cover"></div></div>
      <template #footer><Button label="Hủy" severity="secondary" text @click="createOpen=false" /><Button label="Tạo khóa học" icon="pi pi-plus" :loading="createSaving" :disabled="!createForm.title.trim()" @click="createCourse" /></template>
    </Dialog>
  </div>
</template>
