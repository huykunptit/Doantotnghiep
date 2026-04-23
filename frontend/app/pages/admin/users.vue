<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import { useAdminUpload } from '~/composables/useAdminUpload'

definePageMeta({
  layout: 'admin',
  adminSearchPlaceholder: 'Tim nguoi dung, email, vai tro...',
})

interface RoleItem {
  id: number
  name: 'admin' | 'instructor' | 'student'
}

interface AdminUser {
  id: number
  name: string
  email: string
  avatar?: string | null
  created_at?: string
  updated_at?: string
  roles?: RoleItem[]
}

interface PaginatedUsers {
  data: AdminUser[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const user = useAuthUserCookie()
const token = useAuthTokenCookie()
const { uploadImage } = useAdminUpload()

if (!user.value || !token.value) {
  await navigateTo('/login', { replace: true })
}

if (user.value && normalizeRole(user.value.role) !== 'admin') {
  await navigateTo(getDashboardPath(user.value.role), { replace: true })
}

const filters = reactive({
  search: '',
  role: '',
})

const users = ref<AdminUser[]>([])
const loading = ref(false)
const saving = ref(false)
const deletingId = ref<number | null>(null)
const errorMessage = ref('')
const successMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalUsers = ref(0)

const modalMode = ref<'create' | 'edit' | 'view'>('create')
const modalOpen = ref(false)
const deleteModalOpen = ref(false)
const selectedUser = ref<AdminUser | null>(null)
const avatarFile = ref<File | null>(null)
const uploadingAvatar = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  avatar: '',
  role: 'student',
})

const adminNavItems = computed(() => [
  { label: 'Tong quan', icon: '◧', to: '/admin', active: false },
  { label: 'Nguoi dung', icon: '◎', to: '/admin/users', active: true },
  { label: 'Khoa hoc', icon: '△', to: '/admin', active: false },
  { label: 'Doanh thu', icon: '◌', to: '/admin', active: false },
])

function authHeaders() {
  return token.value
    ? {
        Authorization: `Bearer ${token.value}`,
      }
    : {}
}

function resolveRole(item: AdminUser) {
  return item.roles?.[0]?.name || 'student'
}

function formatDate(value?: string) {
  if (!value) return '--'

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}

function avatarInitials(name: string) {
  return name
    .split(' ')
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
}

async function fetchUsers(page = 1) {
  loading.value = true
  errorMessage.value = ''

  try {
    const query = new URLSearchParams()
    query.set('page', String(page))
    query.set('per_page', '8')
    if (filters.search.trim()) query.set('search', filters.search.trim())
    if (filters.role) query.set('role', filters.role)

    const response = await useApi<PaginatedUsers>(`/admin/users?${query.toString()}`, {
      headers: authHeaders(),
    })

    users.value = response.data
    currentPage.value = response.current_page
    lastPage.value = response.last_page
    totalUsers.value = response.total
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Khong the tai danh sach nguoi dung.'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.name = ''
  form.email = ''
  form.password = ''
  form.avatar = ''
  form.role = 'student'
}

function openCreateModal() {
  modalMode.value = 'create'
  selectedUser.value = null
  resetForm()
  modalOpen.value = true
}

function openViewModal(item: AdminUser) {
  modalMode.value = 'view'
  selectedUser.value = item
  form.name = item.name
  form.email = item.email
  form.password = ''
  form.avatar = item.avatar || ''
  form.role = resolveRole(item)
  modalOpen.value = true
}

function openEditModal(item: AdminUser) {
  modalMode.value = 'edit'
  selectedUser.value = item
  form.name = item.name
  form.email = item.email
  form.password = ''
  form.avatar = item.avatar || ''
  form.role = resolveRole(item)
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedUser.value = null
  avatarFile.value = null
}

async function uploadAvatar() {
  if (!avatarFile.value) return

  uploadingAvatar.value = true
  try {
    const uploaded = await uploadImage(avatarFile.value, 'users', form.avatar || null)
    form.avatar = uploaded.url
    successMessage.value = 'Đã tải ảnh đại diện lên.'
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải ảnh đại diện.'
  } finally {
    uploadingAvatar.value = false
  }
}

async function saveUser() {
  if (modalMode.value === 'view') return

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (modalMode.value === 'create') {
      await useApi('/admin/users', {
        method: 'POST',
        headers: authHeaders(),
        body: {
          name: form.name,
          email: form.email,
          password: form.password,
          avatar: form.avatar || null,
          role: form.role,
        },
      })

      successMessage.value = 'Da tao nguoi dung moi.'
    } else if (selectedUser.value) {
      const payload: Record<string, string | null> = {
        name: form.name,
        email: form.email,
        avatar: form.avatar || null,
        role: form.role,
      }

      if (form.password.trim()) {
        payload.password = form.password
      }

      await useApi(`/admin/users/${selectedUser.value.id}`, {
        method: 'PUT',
        headers: authHeaders(),
        body: payload,
      })

      successMessage.value = 'Da cap nhat nguoi dung.'
    }

    closeModal()
    await fetchUsers(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Khong the luu thong tin nguoi dung.'
  } finally {
    saving.value = false
  }
}

async function deleteUser(item?: AdminUser) {
  if (item) {
    selectedUser.value = item
    deleteModalOpen.value = true
    return
  }

  if (!selectedUser.value) return

  deletingId.value = selectedUser.value.id
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await useApi(`/admin/users/${selectedUser.value.id}`, {
      method: 'DELETE',
      headers: authHeaders(),
    })

    successMessage.value = 'Đã xóa người dùng.'
    deleteModalOpen.value = false
    await fetchUsers(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa người dùng.'
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý người dùng']"
    description="Mẫu CRUD này được dùng làm chuẩn cho các bảng quản trị có tìm kiếm, lọc, bảng dữ liệu và modal thao tác."
    title="Quản lý người dùng"
  >
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <form class="crud-toolbar-main" @submit.prevent="fetchUsers(1)">
          <input
            v-model="filters.search"
            class="crud-search"
            placeholder="Tìm theo tên hoặc email..."
            type="text"
          >

          <select v-model="filters.role" class="crud-select">
            <option value="">Tất cả vai trò</option>
            <option value="admin">Admin</option>
            <option value="instructor">Instructor</option>
            <option value="student">Student</option>
          </select>

          <button class="crud-secondary-btn" type="submit">Tìm kiếm</button>
          <button class="crud-secondary-btn" type="button" @click="fetchUsers(currentPage)">Làm mới</button>
        </form>

        <button class="crud-primary-btn" type="button" @click="openCreateModal">Thêm người dùng</button>
      </div>

      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

      <div class="crud-meta">
        <p>{{ totalUsers }} người dùng</p>
        <p>Chuẩn thiết kế được thống nhất trong `frontend/docs/CRUD_DESIGN_GUIDELINES.md`</p>
      </div>

      <div class="crud-table-wrap">
        <table class="crud-table">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Vai tro</th>
              <th>Email</th>
              <th>Ngay tao</th>
              <th>Cap nhat</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="crud-empty">Đang tải dữ liệu người dùng...</td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="6" class="crud-empty">Chưa có người dùng nào phù hợp bộ lọc.</td>
            </tr>
            <tr v-for="item in users" :key="item.id">
              <td>
                <div class="crud-profile">
                  <div v-if="item.avatar" class="crud-avatar">
                    <img :src="item.avatar" :alt="item.name">
                  </div>
                  <div v-else class="crud-avatar crud-avatar-fallback">
                    {{ avatarInitials(item.name) }}
                  </div>
                  <div>
                    <strong>{{ item.name }}</strong>
                    <p>#{{ item.id }}</p>
                  </div>
                </div>
              </td>
              <td>
                <span class="crud-badge" :class="`role-${resolveRole(item)}`">
                  {{ resolveRole(item) }}
                </span>
              </td>
              <td>{{ item.email }}</td>
              <td>{{ formatDate(item.created_at) }}</td>
              <td>{{ formatDate(item.updated_at) }}</td>
              <td>
                <div class="crud-actions">
                  <button class="action-btn is-view" type="button" @click="openViewModal(item)">Xem</button>
                  <button class="action-btn is-edit" type="button" @click="openEditModal(item)">Sửa</button>
                  <button
                    class="action-btn is-delete"
                    type="button"
                    :disabled="deletingId === item.id"
                    @click="deleteUser(item)"
                  >
                    {{ deletingId === item.id ? 'Đang xóa' : 'Xóa' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="crud-pagination">
        <p>Trang {{ currentPage }} / {{ lastPage }}</p>
        <div class="crud-pagination-actions">
          <button class="crud-secondary-btn" type="button" :disabled="currentPage <= 1" @click="fetchUsers(currentPage - 1)">
            Trước
          </button>
          <button class="crud-secondary-btn" type="button" :disabled="currentPage >= lastPage" @click="fetchUsers(currentPage + 1)">
            Sau
          </button>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="modalOpen" class="crud-modal-backdrop" @click.self="closeModal">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ modalMode === 'create' ? 'Tạo mới' : modalMode === 'edit' ? 'Chỉnh sửa' : 'Chi tiết' }}</p>
              <h3>
                {{
                  modalMode === 'create'
                    ? 'Thêm người dùng mới'
                    : modalMode === 'edit'
                      ? 'Cập nhật thông tin người dùng'
                      : 'Chi tiết người dùng'
                }}
              </h3>
            </div>
            <button class="topbar-ghost" type="button" @click="closeModal">✕</button>
          </div>

          <div class="crud-form-grid">
            <label class="crud-field">
              <span>Họ và tên</span>
              <input v-model="form.name" :disabled="modalMode === 'view'" type="text" placeholder="Nguyễn Văn A">
            </label>

            <label class="crud-field">
              <span>Email</span>
              <input v-model="form.email" :disabled="modalMode === 'view'" type="email" placeholder="user@example.com">
            </label>

            <label class="crud-field">
              <span>Vai trò</span>
              <select v-model="form.role" :disabled="modalMode === 'view'">
                <option value="admin">Admin</option>
                <option value="instructor">Instructor</option>
                <option value="student">Student</option>
              </select>
            </label>

            <div class="crud-field crud-field-full">
              <span>Ảnh đại diện</span>
              <div class="crud-image-preview">
                <img v-if="form.avatar" :src="form.avatar" alt="Avatar preview">
                <div v-else class="crud-image-fallback">{{ form.name ? avatarInitials(form.name) : 'AV' }}</div>
                <div>
                  <input :disabled="modalMode === 'view'" type="file" accept="image/*" @change="avatarFile = ($event.target as HTMLInputElement)?.files?.[0] || null">
                  <div v-if="modalMode !== 'view'" class="crud-inline-actions crud-modal-foot">
                    <button class="crud-secondary-btn" type="button" :disabled="uploadingAvatar || !avatarFile" @click="uploadAvatar">
                      {{ uploadingAvatar ? 'Đang tải...' : 'Tải ảnh lên' }}
                    </button>
                  </div>
                  <p>Backend hiện lưu đường dẫn ảnh. Giao diện sẽ render trực tiếp ảnh từ đường dẫn này.</p>
                </div>
              </div>
            </div>

            <label class="crud-field crud-field-full">
              <span>{{ modalMode === 'edit' ? 'Mật khẩu mới (không bắt buộc)' : 'Mật khẩu' }}</span>
              <input
                v-model="form.password"
                :disabled="modalMode === 'view'"
                type="password"
                :placeholder="modalMode === 'edit' ? 'Bỏ trống nếu không đổi' : 'Nhập mật khẩu tối thiểu 6 ký tự'"
              >
            </label>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="closeModal">Đóng</button>
            <button
              v-if="modalMode !== 'view'"
              class="crud-primary-btn"
              type="button"
              :disabled="saving"
              @click="saveUser"
            >
              {{ saving ? 'Đang lưu...' : modalMode === 'create' ? 'Tạo người dùng' : 'Lưu thay đổi' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <CrudConfirmModal
      :open="deleteModalOpen"
      title="Xóa người dùng"
      :description="`Bạn có chắc chắn muốn xóa ${selectedUser?.name || 'người dùng này'}? Thao tác này không thể hoàn tác.`"
      confirm-text="Xóa người dùng"
      tone="danger"
      :loading="deletingId === selectedUser?.id"
      @close="deleteModalOpen = false"
      @confirm="deleteUser()"
    />
  </AdminWorkspaceShell>
</template>
