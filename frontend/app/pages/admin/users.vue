<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-surface-dim/30 pb-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Quản trị Hệ thống</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface mt-1">Danh sách Người dùng</h2>
          <p class="text-on-surface-variant text-sm mt-2">
            Tìm kiếm, phân quyền và kiểm soát toàn bộ tài khoản Học viên, Giảng viên, và Quản trị viên.
          </p>
        </div>
        <div class="flex items-center gap-3">
           <input ref="csvInput" type="file" accept=".csv" class="hidden" @change="handleImportCsv">
           <button @click="csvInput?.click()" class="px-5 py-2.5 bg-surface-lowest border border-surface-dim/50 text-on-surface text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">upload_file</span> Nhập CSV
           </button>
           <button @click="exportData" class="px-5 py-2.5 bg-surface-lowest border border-surface-dim/50 text-on-surface text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">download</span> Xuất CSV
           </button>
           <button @click="openCreateModal" class="px-5 py-2.5 cta-gradient text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition-transform active:scale-95 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">person_add</span> Thêm mới
           </button>
        </div>
      </div>

      <!-- Filters & Stats row -->
      <div class="flex flex-col lg:flex-row gap-6 justify-between items-start lg:items-center">
         
         <!-- Stat Chips -->
         <div class="flex flex-wrap gap-2">
            <div class="bg-surface-lowest px-4 py-2 flex items-center gap-2 rounded-lg border border-surface-dim/30 shadow-sm">
               <span class="text-xs font-bold text-outline uppercase tracking-wider">Tổng:</span>
               <span class="text-sm font-bold text-on-surface">{{ totalItems }}</span>
            </div>
            <div class="bg-tertiary/10 px-4 py-2 flex items-center gap-2 rounded-lg border border-tertiary/20 shadow-sm">
               <span class="text-xs font-bold text-tertiary uppercase tracking-wider">Admin:</span>
               <span class="text-sm font-bold text-on-surface">{{ roleCounts.admin || 0 }}</span>
            </div>
            <div class="bg-secondary/10 px-4 py-2 flex items-center gap-2 rounded-lg border border-secondary/20 shadow-sm">
               <span class="text-xs font-bold text-secondary uppercase tracking-wider">Instructor:</span>
               <span class="text-sm font-bold text-on-surface">{{ roleCounts.instructor || 0 }}</span>
            </div>
            <div class="bg-primary/10 px-4 py-2 flex items-center gap-2 rounded-lg border border-primary/20 shadow-sm">
               <span class="text-xs font-bold text-primary uppercase tracking-wider">Student:</span>
               <span class="text-sm font-bold text-on-surface">{{ roleCounts.student || 0 }}</span>
            </div>
         </div>

         <!-- Search & Filter -->
         <div class="flex items-center gap-3 w-full lg:w-auto">
            <div class="relative flex-1 lg:w-64">
               <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
               <input v-model="search" @keyup.enter="fetchUsers(1)" placeholder="Tìm tên hoặc email..." type="text" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-dim/30 bg-surface-lowest shadow-sm placeholder-outline focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm">
            </div>
            <select v-model="roleFilter" @change="fetchUsers(1)" class="rounded-xl border border-surface-dim/30 bg-surface-lowest px-4 py-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm font-medium w-40">
               <option value="">Tất cả vai trò</option>
               <option value="admin">Quản trị (Admin)</option>
               <option value="instructor">Giảng viên</option>
               <option value="student">Học viên</option>
            </select>
         </div>
      </div>

      <!-- Users Data Table (Editorial Layout) -->
      <div class="bg-surface-lowest rounded-2xl shadow-sm border border-surface-dim overflow-hidden">
         <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] table-fixed text-left border-collapse">
               <thead>
                  <tr class="bg-surface-low border-b border-surface-dim/30 text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                     <th class="px-6 py-4">Thành viên</th>
                     <th class="px-6 py-4">Vai trò (Role)</th>
                     <th class="px-6 py-4">Ngày tham gia</th>
                     <th class="px-6 py-4 text-right">Thao tác</th>
                  </tr>
               </thead>
               
               <tbody v-if="loading" class="divide-y divide-surface-dim/20">
                  <tr v-for="i in 5" :key="i">
                     <td class="px-6 py-4"><div class="h-10 w-48 bg-surface-high animate-pulse rounded-lg"></div></td>
                     <td class="px-6 py-4"><div class="h-6 w-20 bg-surface-high animate-pulse rounded-lg"></div></td>
                     <td class="px-6 py-4"><div class="h-4 w-24 bg-surface-high animate-pulse rounded-lg"></div></td>
                     <td class="px-6 py-4 text-right"><div class="h-8 w-24 bg-surface-high animate-pulse rounded-lg ml-auto"></div></td>
                  </tr>
               </tbody>
               
               <tbody v-else-if="users.length === 0">
                  <tr>
                     <td colspan="4" class="px-6 py-20 text-center">
                        <span class="material-symbols-outlined text-5xl text-outline mb-2">person_search</span>
                        <p class="font-medium text-on-surface-variant">Không tìm thấy người dùng phù hợp với bộ lọc.</p>
                     </td>
                  </tr>
               </tbody>

               <tbody v-else class="divide-y divide-surface-dim/20 text-sm">
                  <tr v-for="user in users" :key="user.id" class="group hover:bg-surface-low/50 transition-colors">
                     <td class="px-6 py-4">
                        <div class="flex items-center gap-4">
                           <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white font-bold shadow-sm" :class="avatarBg(user.role)">
                              <img v-if="user.avatar" :src="user.avatar" class="h-full w-full rounded-full object-cover">
                              <span v-else>{{ user.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
                           </div>
                           <div class="min-w-0">
                              <p class="font-bold text-on-surface truncate">{{ user.name }}</p>
                              <p class="text-xs text-on-surface-variant truncate mt-0.5">{{ user.email }}</p>
                           </div>
                        </div>
                     </td>
                     <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider" :class="roleBadgeClasses(user.role)">
                           {{ roleLabel(user.role) }}
                        </span>
                     </td>
                     <td class="px-6 py-4 text-xs font-semibold text-outline">
                        {{ formatDate(user.created_at) }}
                     </td>
                     <td class="px-6 py-4 text-right align-middle">
                        <div class="flex min-w-[108px] items-center justify-end gap-2 transition-opacity md:opacity-100">
                           <button @click="openEditModal(user)" class="p-2.5 bg-surface-lowest hover:bg-primary/10 text-on-surface-variant hover:text-primary rounded-xl border border-surface-dim/50 hover:border-primary/30 shadow-sm hover:shadow-md transition-all active:scale-90" title="Chỉnh sửa">
                              <span class="material-symbols-outlined text-[20px]">edit</span>
                           </button>
                           <button @click="removeUser(user)" class="p-2.5 bg-error-container/10 hover:bg-error text-error hover:text-white rounded-xl border border-error/20 shadow-sm hover:shadow-red-200/50 transition-all active:scale-90" title="Xóa tài khoản">
                              <span class="material-symbols-outlined text-[20px]">delete</span>
                           </button>
                        </div>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>
         
         <!-- Pagination -->
         <div v-if="totalPages > 1" class="px-6 py-4 border-t border-surface-dim/30 flex justify-center bg-surface-lowest">
            <div class="flex gap-1.5">
               <button v-for="page in totalPages" :key="page" @click="fetchUsers(page)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all"
                  :class="page === currentPage ? 'cta-gradient text-white shadow-md' : 'bg-surface-low hover:bg-surface-high text-on-surface'">
                  {{ page }}
               </button>
            </div>
         </div>
      </div>

      <!-- Add/Edit Modal (Teleport overlay) -->
      <Teleport to="body">
         <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="closeModal">
            <div class="w-full max-w-md rounded-[2rem] bg-surface-lowest p-8 shadow-ambient border border-surface-dim modal-bounce">
               <div class="mb-6 flex items-center justify-between pb-4 border-b border-surface-dim/30">
                  <h3 class="font-headline text-xl font-bold text-on-surface flex items-center gap-2">
                     <span class="material-symbols-outlined text-primary">{{ editingUser ? 'manage_accounts' : 'person_add' }}</span>
                     {{ editingUser ? 'Chỉnh sửa Tài khoản' : 'Cấp mới Tài khoản' }}
                  </h3>
                  <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="closeModal">
                     <span class="material-symbols-outlined text-[20px]">close</span>
                  </button>
               </div>
               
               <form class="space-y-5" @submit.prevent="submitUser">
                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Họ & Tên <span class="text-error">*</span></label>
                     <input v-model="form.name" required class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="Nhập họ và tên...">
                  </div>
                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Địa chỉ Email <span class="text-error">*</span></label>
                     <input v-model="form.email" type="email" required class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="user@example.com">
                  </div>
                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Vai trò Hệ thống</label>
                     <select v-model="form.role" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all">
                        <option value="student">Học viên (Student)</option>
                        <option value="instructor">Giảng viên (Instructor)</option>
                        <option value="admin">Quản trị viên (Admin)</option>
                     </select>
                  </div>
                  
                  <!-- Note: Avatar Upload usually uses VideoUploader-like component, simplified to input url for now -->
                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Avatar URL (Tùy chọn)</label>
                     <input v-model="form.avatar" type="url" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="https://...">
                  </div>
                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Mật khẩu cấp phép</label>
                     <input v-model="form.password" type="password" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" :placeholder="editingUser ? 'Để trống nếu không muốn đổi mật khẩu' : 'Mặc định: password123 nếu để trống'">
                  </div>

                  <div v-if="modalError" class="p-3 bg-error-container/30 border border-error/20 text-error text-xs font-bold rounded-lg">{{ modalError }}</div>
                  
                  <div class="sticky bottom-0 -mx-8 -mb-8 mt-6 flex flex-col gap-3 border-t border-surface-dim/30 bg-surface-lowest/95 px-8 py-5 backdrop-blur sm:flex-row sm:justify-end">
                     <UiButton type="button" variant="secondary" size="lg" @click="closeModal">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                        Hủy bỏ
                     </UiButton>
                     <UiButton type="submit" size="lg" :loading="submitting">
                        <span v-if="!submitting" class="material-symbols-outlined text-[18px]">task_alt</span>
                        {{ editingUser ? 'Lưu thay đổi' : 'Khởi tạo user' }}
                     </UiButton>
                  </div>
               </form>
            </div>
         </div>
      </Teleport>

    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const users = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const roleFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(10)
const roleCounts = ref<Record<string, number>>({})
const csvInput = ref<HTMLInputElement | null>(null)
const { exportToCSV } = useExport()

const showModal = ref(false)
const editingUser = ref<any | null>(null)
const submitting = ref(false)
const modalError = ref('')
const form = reactive({ name: '', email: '', role: 'student', avatar: '', password: '' })

const exportData = () => {
  exportToCSV(
    users.value,
    [
      { key: 'id', label: 'ID' },
      { key: 'name', label: 'Họ tên' },
      { key: 'email', label: 'Email' },
      { key: 'role', label: 'Vai trò', format: (value) => roleLabel(String(value || 'student')) },
      { key: 'created_at', label: 'Ngày tham gia', format: (value) => formatDate(value) },
    ],
    'admin_users',
  )
}

const normalizeUser = (user: any) => ({ ...user, role: user?.role || user?.roles?.[0]?.name || user?.roles?.[0] || 'student' })
const roleLabel = (role: string) => ({ admin: 'ĐIỀU HÀNH', instructor: 'GIẢNG VIÊN', student: 'HỌC VIÊN' }[role] || role)
const avatarBg = (role: string) => ({ admin: 'bg-tertiary shadow-tertiary/30', instructor: 'bg-secondary shadow-secondary/30', student: 'bg-primary shadow-primary/30' }[role] || 'bg-primary')

const roleBadgeClasses = (role: string) => {
   const map: Record<string, string> = {
      admin: 'bg-tertiary/10 text-tertiary border border-tertiary/20',
      instructor: 'bg-secondary/10 text-secondary border border-secondary/20',
      student: 'bg-primary/10 text-primary border border-primary/20'
   }
   return map[role] || map.student
}

const formatDate = (date?: string) => !date ? 'N/A' : new Date(date).toLocaleDateString('vi-VN')

async function fetchUsers(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    if (search.value) query.set('search', search.value)
    if (roleFilter.value) query.set('role', roleFilter.value)
    
    // Fallback logic in case API missing during migration test
    const data = await $fetch<any>(`/api/admin/users?${query}`, { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [], last_page: 1, total: 0 }))
    
    users.value = (data.data || []).map(normalizeUser)
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || users.value.length
    roleCounts.value = users.value.reduce((acc: Record<string, number>, item: any) => { acc[item.role] = (acc[item.role] || 0) + 1; return acc }, {})
  } finally {
    loading.value = false
  }
}

function openCreateModal() { 
   editingUser.value = null
   Object.assign(form, { name: '', email: '', role: 'student', avatar: '', password: '' })
   modalError.value = ''
   showModal.value = true 
}

function openEditModal(user: any) { 
   editingUser.value = user
   Object.assign(form, { name: user.name || '', email: user.email || '', role: user.role || 'student', avatar: user.avatar || '', password: '' })
   modalError.value = ''
   showModal.value = true 
}

function closeModal() { showModal.value = false; editingUser.value = null }

async function submitUser() {
  submitting.value = true
  modalError.value = ''
  try {
    const payload = { name: form.name, email: form.email, role: form.role, avatar: form.avatar || null, ...(form.password ? { password: form.password } : {}) }
    if (editingUser.value) {
       await $fetch(`/api/admin/users/${editingUser.value.id}`, { method: 'PUT', body: payload, headers: { Authorization: `Bearer ${auth.token}` } })
    } else {
       await $fetch('/api/admin/users', { method: 'POST', body: payload, headers: { Authorization: `Bearer ${auth.token}` } })
    }
    closeModal()
    await fetchUsers(currentPage.value)
  } catch (e: any) {
    modalError.value = e?.data?.message || 'Có lỗi xảy ra, không thể thay đổi thông tin.'
  } finally {
    submitting.value = false
  }
}

async function removeUser(user: any) {
  if (!confirm(`Xác nhận xóa bỏ vĩnh viễn tài khoản: "${user.name}"?`)) return
  try { 
     await $fetch(`/api/admin/users/${user.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
     await fetchUsers(currentPage.value) 
  } catch (e: any) { 
     alert(e?.data?.message || 'Lỗi: Không thể xóa tài khoản này lúc này.') 
  }
}

function parseCsvLine(line: string) {
  const cells: string[] = []
  let current = ''
  let inQuotes = false

  for (let i = 0; i < line.length; i += 1) {
    const char = line[i]
    const next = line[i + 1]

    if (char === '"' && inQuotes && next === '"') {
      current += '"'
      i += 1
    } else if (char === '"') {
      inQuotes = !inQuotes
    } else if (char === ',' && !inQuotes) {
      cells.push(current.trim())
      current = ''
    } else {
      current += char
    }
  }

  cells.push(current.trim())
  return cells.map((cell) => cell.replace(/^"|"$/g, ''))
}

async function handleImportCsv(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  const text = await file.text()
  const lines = text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)

  if (lines.length < 2) {
    alert('CSV không có dữ liệu để import.')
    input.value = ''
    return
  }

  const headers = parseCsvLine(lines[0]).map((header) => header.toLowerCase())
  let successCount = 0
  let failedCount = 0

  for (const line of lines.slice(1)) {
    const values = parseCsvLine(line)
    const row = headers.reduce((acc: Record<string, string>, header, index) => {
      acc[header] = values[index] || ''
      return acc
    }, {})

    if (!row.name || !row.email) {
      failedCount += 1
      continue
    }

    try {
      await $fetch('/api/admin/users', {
        method: 'POST',
        body: {
          name: row.name,
          email: row.email,
          role: row.role || 'student',
          avatar: row.avatar || null,
          password: row.password || 'password123',
        },
        headers: { Authorization: `Bearer ${auth.token}` },
      })
      successCount += 1
    } catch {
      failedCount += 1
    }
  }

  await fetchUsers(1)
  input.value = ''
  alert(`Import hoàn tất: ${successCount} bản ghi thành công, ${failedCount} bản ghi lỗi.`)
}

onMounted(() => fetchUsers(1))
</script>

<style scoped>
html { scroll-behavior: smooth; }

.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
