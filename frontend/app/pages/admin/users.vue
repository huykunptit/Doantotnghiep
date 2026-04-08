<template>
  <div>
    <NuxtLayout name="admin">
      <!-- Header bar -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <div class="flex flex-col sm:flex-row gap-2 flex-1">
          <div class="relative flex-1 max-w-xs">
            <input v-model="search" type="text" placeholder="Tìm tên, email..." class="input pl-9 pr-3 h-9 text-sm"
              @keyup.enter="fetchUsers(1)" />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <select v-model="roleFilter" class="input h-9 text-sm w-auto" @change="fetchUsers(1)">
            <option value="">Tất cả vai trò</option>
            <option value="admin">Admin</option>
            <option value="instructor">Giảng viên</option>
            <option value="student">Học viên</option>
          </select>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button class="btn-export text-sm h-9 px-4 flex items-center gap-2 text-gray-700" @click="exportData">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            Xuất CSV
          </button>
          <button class="btn-primary text-sm h-9 px-4 flex items-center gap-2 shadow-sm" @click="openCreateModal">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Thêm người dùng
          </button>
        </div>
      </div>

      <!-- Summary chips -->
      <div class="flex gap-2 flex-wrap mb-4">
        <span v-for="chip in summaryChips" :key="chip.label"
          class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full"
          :class="chip.cls">
          <span class="w-1.5 h-1.5 rounded-full" :class="chip.dot"></span>
          {{ chip.label }}: {{ chip.value }}
        </span>
      </div>

      <!-- Table -->
      <div class="card-glass overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s">
        <!-- Loading skeleton -->
        <div v-if="loading" class="divide-y divide-gray-100">
          <div v-for="i in 8" :key="i" class="flex items-center gap-4 px-5 py-3 animate-pulse">
            <div class="w-9 h-9 rounded-full bg-gray-200 flex-shrink-0"></div>
            <div class="flex-1 space-y-1.5">
              <div class="h-3.5 bg-gray-200 rounded w-32"></div>
              <div class="h-3 bg-gray-200 rounded w-48"></div>
            </div>
            <div class="h-5 w-16 bg-gray-200 rounded-full"></div>
            <div class="h-3 w-20 bg-gray-200 rounded hidden md:block"></div>
            <div class="flex gap-1.5">
              <div class="h-7 w-12 bg-gray-200 rounded-lg"></div>
              <div class="h-7 w-12 bg-gray-200 rounded-lg"></div>
            </div>
          </div>
        </div>

        <div v-else-if="users.length === 0" class="p-12 text-center">
          <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
          <p class="text-sm text-gray-500">Không tìm thấy người dùng nào</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Người dùng</th>
                <th class="hidden md:table-cell">Email</th>
                <th>Vai trò</th>
                <th class="hidden lg:table-cell">Ngày tạo</th>
                <th class="text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50/80 transition-colors group">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden"
                      :class="!user.avatar ? avatarBg(user.role) : ''">
                      <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                      <span v-else class="text-sm font-bold text-white">{{ user.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-gray-900 truncate max-w-[160px]">{{ user.name }}</p>
                      <p class="text-xs text-gray-400 md:hidden truncate max-w-[160px]">{{ user.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3 text-sm text-gray-600 hidden md:table-cell">{{ user.email }}</td>
                <td class="px-5 py-3">
                  <span class="badge text-xs" :class="roleBadge(user.role)">{{ roleLabel(user.role) }}</span>
                </td>
                <td class="px-5 py-3 text-xs text-gray-400 hidden lg:table-cell">{{ formatDate(user.created_at) }}</td>
                <td class="px-5 py-3 text-right">
                  <div class="flex justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button class="p-1.5 rounded-lg text-gray-400 hover:text-primary hover:bg-primary-light transition-colors" title="Chỉnh sửa" @click="openEditModal(user)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </button>
                    <button class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-rose-50 transition-colors" title="Xóa" @click="removeUser(user)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1 || totalItems > perPage" class="pagination-wrapper mt-4">
        <div class="flex items-center gap-3">
          <p class="text-xs text-gray-500">Hiển thị <span class="font-medium text-gray-900">{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }}</span> / {{ totalItems }}</p>
          <select v-model="perPage" class="input h-8 py-1.5 text-xs w-auto focus:ring-0" @change="fetchUsers(1)">
            <option :value="10">10 / trang</option>
            <option :value="20">20 / trang</option>
            <option :value="50">50 / trang</option>
          </select>
        </div>
        <div class="flex gap-1">
          <button @click="fetchUsers(currentPage - 1)" :disabled="currentPage <= 1"
            class="pagination-btn" :class="currentPage <= 1 ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">‹</button>
          <button v-for="p in paginationRange" :key="p" @click="typeof p === 'number' && fetchUsers(p)"
            class="pagination-btn" :class="p === currentPage ? 'pagination-btn-active' : p === '…' ? 'cursor-default text-gray-400' : 'pagination-btn-inactive'">{{ p }}</button>
          <button @click="fetchUsers(currentPage + 1)" :disabled="currentPage >= totalPages"
            class="pagination-btn" :class="currentPage >= totalPages ? 'pagination-btn-disabled' : 'pagination-btn-inactive'">›</button>
        </div>
      </div>

      <!-- Modal -->
      <Teleport to="body">
        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100"
          leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
              <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">
                  {{ editingUser ? 'Chỉnh sửa người dùng' : 'Thêm người dùng mới' }}
                </h3>
                <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>

              <form class="p-6 space-y-4" @submit.prevent="submitUser">
                <div class="grid grid-cols-1 gap-3">
                  <div>
                    <label class="label">Họ và tên <span class="text-red-500">*</span></label>
                    <input v-model="form.name" type="text" class="input" placeholder="Nguyễn Văn A" required />
                  </div>
                  <div>
                    <label class="label">Email <span class="text-red-500">*</span></label>
                    <input v-model="form.email" type="email" class="input" placeholder="user@example.com" required />
                  </div>
                  <div>
                    <label class="label">Vai trò <span class="text-red-500">*</span></label>
                    <select v-model="form.role" class="input" required>
                      <option value="student">Học viên</option>
                      <option value="instructor">Giảng viên</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>
                  <div>
                    <label class="label">Avatar URL</label>
                    <input v-model="form.avatar" type="url" class="input" placeholder="https://..." />
                  </div>
                  <div>
                    <label class="label">
                      Mật khẩu
                      <span v-if="!editingUser" class="text-red-500">*</span>
                      <span v-else class="text-gray-400 text-xs font-normal ml-1">(để trống nếu không đổi)</span>
                    </label>
                    <input v-model="form.password" type="password" class="input" :required="!editingUser" minlength="6"
                      placeholder="Tối thiểu 6 ký tự" />
                  </div>
                </div>

                <div v-if="modalError"
                  class="flex items-start gap-2 text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2.5">
                  <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <span>{{ modalError }}</span>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                  <button type="button" class="btn-ghost text-sm" @click="closeModal">Hủy bỏ</button>
                  <button type="submit" class="btn-primary text-sm px-5" :disabled="submitting">
                    <svg v-if="submitting" class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ editingUser ? 'Lưu thay đổi' : 'Tạo người dùng' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </Transition>
      </Teleport>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const { exportToCSV } = useExport()
const users = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const roleFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(20)
const roleCounts = ref<Record<string, number>>({})

const showModal = ref(false)
const editingUser = ref<any | null>(null)
const submitting = ref(false)
const modalError = ref('')

const form = reactive({ name: '', email: '', role: 'student', avatar: '', password: '' })

// ─── Helpers ─────────────────────────────────────────────────────────────────

function normalizeUser(user: any) {
  const role = user?.role || user?.roles?.[0]?.name || user?.roles?.[0] || 'student'
  return { ...user, role }
}

function roleBadge(role: string): string {
  const m: Record<string, string> = {
    admin: 'bg-red-100 text-red-700',
    instructor: 'bg-blue-100 text-blue-700',
    student: 'bg-gray-100 text-gray-600',
  }
  return m[role] || 'bg-gray-100 text-gray-600'
}

function avatarBg(role: string): string {
  const m: Record<string, string> = { admin: 'bg-red-500', instructor: 'bg-blue-500', student: 'bg-primary' }
  return m[role] || 'bg-gray-500'
}

function roleLabel(role: string): string {
  const m: Record<string, string> = { admin: 'Admin', instructor: 'Giảng viên', student: 'Học viên' }
  return m[role] || role
}

function formatDate(d: string): string {
  if (!d) return ''
  return new Date(d).toLocaleDateString('vi-VN')
}

const summaryChips = computed(() => [
  { label: 'Tổng', value: totalItems.value, cls: 'bg-gray-100 text-gray-700', dot: 'bg-gray-500' },
  { label: 'Admin', value: roleCounts.value['admin'] ?? 0, cls: 'bg-red-50 text-red-700', dot: 'bg-red-500' },
  { label: 'Giảng viên', value: roleCounts.value['instructor'] ?? 0, cls: 'bg-blue-50 text-blue-700', dot: 'bg-blue-500' },
  { label: 'Học viên', value: roleCounts.value['student'] ?? 0, cls: 'bg-primary-light text-primary-dark', dot: 'bg-primary' },
])

const paginationRange = computed(() => {
  const total = totalPages.value
  const cur = currentPage.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages: (number | string)[] = [1]
  if (cur > 3) pages.push('…')
  for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) pages.push(p)
  if (cur < total - 2) pages.push('…')
  pages.push(total)
  return pages
})

// ─── CRUD ─────────────────────────────────────────────────────────────────────

async function fetchUsers(page = 1) {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (search.value) q.set('search', search.value)
    if (roleFilter.value) q.set('role', roleFilter.value)
    const data = await useApi<any>(`/admin/users?${q}`, { token: auth.token })
    users.value = (data.data || []).map(normalizeUser)
    currentPage.value = data.current_page || 1
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0
  } catch {
    users.value = []
  } finally {
    loading.value = false
  }
}

async function fetchRoleCounts() {
  try {
    const [all, admins, instructors, students] = await Promise.all([
      useApi<any>('/admin/users?per_page=1', { token: auth.token }),
      useApi<any>('/admin/users?role=admin&per_page=1', { token: auth.token }),
      useApi<any>('/admin/users?role=instructor&per_page=1', { token: auth.token }),
      useApi<any>('/admin/users?role=student&per_page=1', { token: auth.token }),
    ])
    roleCounts.value = {
      admin: admins.total || 0,
      instructor: instructors.total || 0,
      student: students.total || 0,
    }
    totalItems.value = all.total || 0
  } catch {}
}

function resetForm() {
  form.name = ''; form.email = ''; form.role = 'student'; form.avatar = ''; form.password = ''
  modalError.value = ''
}

function openCreateModal() {
  editingUser.value = null
  resetForm()
  showModal.value = true
}

function openEditModal(user: any) {
  editingUser.value = user
  form.name = user.name || ''
  form.email = user.email || ''
  form.role = user.role || 'student'
  form.avatar = user.avatar || ''
  form.password = ''
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingUser.value = null
}

async function submitUser() {
  submitting.value = true
  modalError.value = ''
  try {
    if (editingUser.value) {
      const payload: any = { name: form.name, email: form.email, role: form.role, avatar: form.avatar || null }
      if (form.password.trim()) payload.password = form.password
      const res = await useApi<any>(`/admin/users/${editingUser.value.id}`, { method: 'PUT', body: payload, token: auth.token })
      const updated = normalizeUser(res.user)
      const idx = users.value.findIndex((u) => u.id === updated.id)
      if (idx >= 0) users.value[idx] = updated
    } else {
      const res = await useApi<any>('/admin/users', {
        method: 'POST',
        body: { name: form.name, email: form.email, password: form.password, role: form.role, avatar: form.avatar || null },
        token: auth.token,
      })
      users.value.unshift(normalizeUser(res.user))
      totalItems.value++
    }
    closeModal()
  } catch (e: any) {
    modalError.value = e?.data?.message || 'Thao tác thất bại'
  } finally {
    submitting.value = false
  }
}

async function removeUser(user: any) {
  if (!confirm(`Bạn chắc chắn muốn xóa người dùng "${user.name}"?`)) return
  try {
    await useApi(`/admin/users/${user.id}`, { method: 'DELETE', token: auth.token })
    users.value = users.value.filter((u) => u.id !== user.id)
    totalItems.value--
  } catch (e: any) {
    alert(e?.data?.message || 'Xóa thất bại')
  }
}

async function exportData() {
  try {
    const q = new URLSearchParams({ page: '1', per_page: '1000' }) // Export everything
    if (search.value) q.set('search', search.value)
    if (roleFilter.value) q.set('role', roleFilter.value)
    const data = await useApi<any>(`/admin/users?${q}`, { token: auth.token })
    const rows = (data.data || []).map(normalizeUser)
    
    exportToCSV(rows, [
      { key: 'id', label: 'ID' },
      { key: 'name', label: 'Tên người dùng' },
      { key: 'email', label: 'Email' },
      { key: 'role', label: 'Vai trò', format: (val) => roleLabel(val) },
      { key: 'created_at', label: 'Ngày tạo', format: (val) => formatDate(val) },
    ], 'danh_sach_nguoi_dung')
  } catch (e) {
    alert('Không thể xuất dữ liệu, vui lòng thử lại sau.')
  }
}

onMounted(() => {
  fetchUsers()
  fetchRoleCounts()
})
</script>

