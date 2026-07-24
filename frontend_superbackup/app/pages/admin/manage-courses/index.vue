<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'
import RichTextEditor from '~/components/dashboard/RichTextEditor.vue'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học...' })
interface CategoryItem { id: number; name: string; }
interface AdminCourse { id: number; title: string; description?: string; thumbnail?: string | null; status: string; lessons_count?: number; enrollments_count?: number; instructor?: { name: string } | null; category?: { id: number; name: string } | null }
interface CourseListResponse { data: AdminCourse[]; current_page: number; last_page: number; total: number }

const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const toast = useToast()

const search = ref(''); const status = ref(''); const loading = ref(false); const saving = ref(false); const courses = ref<AdminCourse[]>([])
const categories = ref<CategoryItem[]>([])
const currentPage = ref(1); const lastPage = ref(1); const totalCourses = ref(0)

const modalOpen = ref(false); const confirmOpen = ref(false); const selectedCourse = ref<AdminCourse | null>(null)
const defaultForm = { title: '', description: '', price: 0, category_id: '' }
const form = reactive({ ...defaultForm })

const statuses = [{ label: 'Tất cả', value: '' }, { label: 'Đã xuất bản', value: 'published' }, { label: 'Chờ duyệt', value: 'pending_review' }, { label: 'Bản nháp', value: 'draft' }, { label: 'Bị từ chối', value: 'rejected' }]
const expandedRows = ref({})
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const statusLabel = (value: string) => ({ pending_review: 'Chờ duyệt', published: 'Đã xuất bản', rejected: 'Bị từ chối', draft: 'Bản nháp' }[value] || value)
const statusSeverity = (value: string) => ({ pending_review: 'warn', published: 'success', rejected: 'danger', draft: 'secondary' }[value] || 'info')

async function fetchCategories() {
  try {
    categories.value = await useApi<CategoryItem[]>('/admin/categories', { headers: authHeaders() })
  } catch (e) {
    console.error('Failed to load categories')
  }
}

async function fetchCourses(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams({ page: String(page), per_page: '12' }); if (search.value.trim()) query.set('search', search.value.trim()); if (status.value) query.set('status', status.value)
    const response = await useApi<CourseListResponse>(`/admin/courses?${query.toString()}`, { headers: authHeaders() })
    courses.value = response.data; currentPage.value = response.current_page; lastPage.value = response.last_page; totalCourses.value = response.total
  } catch (error: any) { toast.error(error?.data?.message || 'Không thể tải danh sách khóa học.') } finally { loading.value = false }
}

function openCreateModal() {
  Object.assign(form, defaultForm)
  modalOpen.value = true
}

async function createCourse() {
  if (!form.title.trim() || form.price < 0) return
  saving.value = true
  try {
    const body = { title: form.title, description: form.description, price: form.price, category_id: form.category_id ? Number(form.category_id) : null }
    await useApi('/courses', { method: 'POST', headers: authHeaders(), body })
    toast.success('Đã tạo khóa học thành công.')
    modalOpen.value = false
    await fetchCourses(1)
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tạo khóa học.')
  } finally {
    saving.value = false
  }
}

async function deleteCourse() {
  if (!selectedCourse.value) return
  try {
    await useApi(`/courses/${selectedCourse.value.id}`, { method: 'DELETE', headers: authHeaders() })
    toast.success('Đã xóa khóa học.')
    confirmOpen.value = false
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể xóa khóa học.')
  }
}

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
  exportToCSV(courses.value, cols, 'danh_sach_quan_ly_khoa_hoc')
}

const visiblePages = computed(() => {
  const range: number[] = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(lastPage.value, start + maxVisible - 1)
  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) {
    if (i >= 1) range.push(i)
  }
  return range
})

onMounted(() => {
  fetchCategories()
  fetchCourses()
})
</script>

<template>
  <div class="flex flex-col gap-5">
    <header><p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Khóa học</p><h1 class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-0">Quản lý khóa học</h1><p class="mt-1 text-sm text-surface-500">Tạo khóa học và xây dựng nội dung giảng dạy.</p></header>
    <Card><template #content>
      <form class="mb-4 flex flex-col gap-3 lg:flex-row lg:justify-between" @submit.prevent="fetchCourses(1)"><div class="flex flex-1 flex-col gap-3 sm:flex-row"><IconField class="w-full sm:max-w-md"><InputIcon class="pi pi-search" /><InputText v-model="search" class="w-full" placeholder="Tìm khóa học..." /></IconField><Select v-model="status" :options="statuses" option-label="label" option-value="value" class="w-full sm:w-48" /><Button label="Tìm kiếm" severity="secondary" type="submit" /></div><div class="flex gap-2"><Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined @click="exportData" /><Button label="Tạo khóa học" icon="pi pi-plus" @click="openCreateModal" /></div></form>
      <DataTable v-model:expanded-rows="expandedRows" :value="courses" data-key="id" :loading="loading" striped-rows responsive-layout="scroll">
        <template #empty>Chưa có khóa học.</template><Column expander style="width:3rem" /><Column header="Khóa học"><template #body="{data}"><div class="flex min-w-64 items-center gap-3"><img v-if="data.thumbnail" :src="data.thumbnail" class="h-12 w-20 rounded-lg object-cover"><div v-else class="grid h-12 w-20 place-items-center rounded-lg bg-surface-100 dark:bg-surface-800">📘</div><strong>{{ data.title }}</strong></div></template></Column><Column header="Giảng viên"><template #body="{data}">{{ data.instructor?.name || '—' }}</template></Column><Column header="Danh mục"><template #body="{data}">{{ data.category?.name || '—' }}</template></Column><Column header="Trạng thái"><template #body="{data}"><Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" /></template></Column><Column header="Nội dung"><template #body="{data}">{{ data.lessons_count || 0 }} bài</template></Column><Column header="Thao tác"><template #body="{data}"><div class="flex gap-1"><Button icon="pi pi-eye" text rounded @click="navigateTo(`/courses/${data.id}`)" /><Button icon="pi pi-pencil" text rounded @click="navigateTo(`/admin/manage-courses/${data.id}`)" /><Button icon="pi pi-trash" severity="danger" text rounded @click="selectedCourse=data;confirmOpen=true" /></div></template></Column>
        <template #expansion="{data}"><div class="p-4"><strong>Mô tả</strong><p class="mt-2 text-surface-500">{{ data.description || 'Chưa có mô tả.' }}</p><p class="mt-2 text-sm">{{ data.enrollments_count || 0 }} học viên</p></div></template>
      </DataTable><Paginator :first="(currentPage-1)*12" :rows="12" :total-records="totalCourses" @page="fetchCourses($event.page+1)" />
    </template></Card>
    <Dialog v-model:visible="modalOpen" modal header="Tạo khóa học" class="w-[min(46rem,calc(100vw-2rem))]"><div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-2 sm:col-span-2"><span class="font-medium">Tên khóa học</span><InputText v-model="form.title" /></label><div class="grid gap-2 sm:col-span-2"><span class="font-medium">Mô tả</span><RichTextEditor v-model="form.description" /></div><label class="grid gap-2"><span class="font-medium">Giá tiền</span><InputNumber v-model="form.price" :min="0" fluid /></label><label class="grid gap-2"><span class="font-medium">Danh mục</span><Select v-model="form.category_id" :options="categories" option-label="name" option-value="id" fluid /></label></div><template #footer><Button label="Hủy" severity="secondary" text @click="modalOpen=false" /><Button label="Tạo khóa học" :loading="saving" :disabled="!form.title.trim()||form.price<0" @click="createCourse" /></template></Dialog>
    <Dialog v-model:visible="confirmOpen" modal header="Xóa khóa học" class="w-[min(28rem,calc(100vw-2rem))]"><p>Xóa hoàn toàn <strong>{{ selectedCourse?.title }}</strong>? Không thể hoàn tác.</p><template #footer><Button label="Hủy" severity="secondary" text @click="confirmOpen=false" /><Button label="Xóa khóa học" severity="danger" icon="pi pi-trash" @click="deleteCourse" /></template></Dialog>
  </div>
</template>
