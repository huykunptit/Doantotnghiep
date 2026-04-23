<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm khóa học...' })
interface CategoryItem { id: number; name: string; }
interface AdminCourse { id: number; title: string; description?: string; thumbnail?: string | null; status: string; lessons_count?: number; enrollments_count?: number; instructor?: { name: string } | null; category?: { id: number; name: string } | null }
interface CourseListResponse { data: AdminCourse[]; current_page: number; last_page: number; total: number }

const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })

const search = ref(''); const status = ref(''); const loading = ref(false); const saving = ref(false); const courses = ref<AdminCourse[]>([])
const categories = ref<CategoryItem[]>([])
const currentPage = ref(1); const lastPage = ref(1); const totalCourses = ref(0); const errorMessage = ref(''); const successMessage = ref('')

const modalOpen = ref(false); const confirmOpen = ref(false); const selectedCourse = ref<AdminCourse | null>(null)
const defaultForm = { title: '', description: '', price: 0, category_id: '' }
const form = reactive({ ...defaultForm })

const statuses = [{ label: 'Tất cả', value: '' }, { label: 'Đã xuất bản', value: 'published' }, { label: 'Chờ duyệt', value: 'pending_review' }, { label: 'Bản nháp', value: 'draft' }, { label: 'Bị từ chối', value: 'rejected' }]
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const statusLabel = (value: string) => ({ pending_review: 'Chờ duyệt', published: 'Đã xuất bản', rejected: 'Bị từ chối', draft: 'Bản nháp' }[value] || value)
const statusClass = (value: string) => ({ pending_review: 'role-student', published: 'role-instructor', rejected: 'role-admin', draft: 'role-admin' }[value] || 'role-admin')

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
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải danh sách khóa học.' } finally { loading.value = false }
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
    successMessage.value = 'Đã tạo khóa học thành công.'
    modalOpen.value = false
    await fetchCourses(1)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tạo khóa học.'
  } finally {
    saving.value = false
  }
}

async function deleteCourse() {
  if (!selectedCourse.value) return
  try {
    await useApi(`/courses/${selectedCourse.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa khóa học.'
    confirmOpen.value = false
    await fetchCourses(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa khóa học.'
  }
}

onMounted(() => {
  fetchCategories()
  fetchCourses()
})
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý khóa học', 'Danh sách khóa học']" description="Trang quản lý nội dung các khóa học đã tạo trong hệ thống. Tại đây Admin có thể thêm mới khóa học, cập nhật thông tin và tiến hành xây dựng nội dung bài giảng." title="Khóa học">
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent="fetchCourses(1)">
          <input v-model="search" class="crud-search" type="text" placeholder="Tìm theo tên hoặc mô tả...">
          <select v-model="status" class="crud-select">
            <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
          <button class="crud-secondary-btn" type="submit">Tìm kiếm</button>
        </form>
        <button class="crud-primary-btn" type="button" @click="openCreateModal">Tạo khóa học</button>
      </div>
      
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>
      
      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead><tr><th>Khóa học</th><th>Giảng viên</th><th>Danh mục</th><th>Trạng thái</th><th>Nội dung</th><th>Thao tác</th></tr></thead>
          <tbody>
            <tr v-if="loading"><td colspan="6" class="crud-empty">Đang tải dữ liệu khóa học...</td></tr>
            <tr v-else-if="courses.length === 0"><td colspan="6" class="crud-empty">Chưa có khóa học.</td></tr>
            <tr v-for="course in courses" :key="course.id">
              <td>
                <div class="crud-course">
                  <div class="crud-course-thumb">
                    <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title">
                    <span v-else>📘</span>
                  </div>
                  <div>
                    <strong>{{ course.title }}</strong>
                    <p>{{ course.description || 'Chưa có mô tả ngắn.' }}</p>
                  </div>
                </div>
              </td>
              <td>{{ course.instructor?.name || '--' }}</td>
              <td>{{ course.category?.name || '--' }}</td>
              <td><span class="crud-badge" :class="statusClass(course.status)">{{ statusLabel(course.status) }}</span></td>
              <td>{{ course.lessons_count || 0 }} bài</td>
              <td>
                <div class="crud-actions">
                  <button class="action-btn is-edit" type="button" @click="navigateTo(`/admin/manage-courses/${course.id}`)">Xây dựng nội dung</button>
                  <button class="action-btn is-delete" type="button" @click="selectedCourse = course; confirmOpen = true">Xóa</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="crud-pagination">
        <p>Trang {{ currentPage }} / {{ lastPage }}</p>
        <div class="crud-pagination-actions">
          <button class="crud-secondary-btn" type="button" :disabled="currentPage <= 1" @click="fetchCourses(currentPage - 1)">Trước</button>
          <button class="crud-secondary-btn" type="button" :disabled="currentPage >= lastPage" @click="fetchCourses(currentPage + 1)">Sau</button>
        </div>
      </div>
    </section>

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
            <label class="crud-field crud-field-full"><span>Mô tả</span><textarea v-model="form.description" class="crud-textarea" placeholder="Mô tả khóa học..."></textarea></label>
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
            <button class="crud-primary-btn" type="button" :disabled="saving" @click="createCourse">
              {{ saving ? 'Đang tạo...' : 'Tạo khóa học' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <CrudConfirmModal :open="confirmOpen" title="Xóa khóa học" :description="`Thao tác này sẽ xóa hoàn toàn khóa học ${selectedCourse?.title}. Không thể hoàn tác.`" confirm-text="Xóa khóa học" tone="danger" @close="confirmOpen = false" @confirm="deleteCourse" />
  </AdminWorkspaceShell>
</template>
