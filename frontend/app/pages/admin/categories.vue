<template>
  <div>
    <NuxtLayout name="admin">
      <!-- Header -->
      <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-gray-500">Quản lý danh mục khóa học</p>
        <button class="btn-primary text-sm h-9 px-4 flex items-center gap-2" @click="openCreateModal">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          Thêm danh mục
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="card p-5 animate-pulse space-y-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gray-200 rounded-xl"></div>
            <div class="flex-1 space-y-1.5">
              <div class="h-4 bg-gray-200 rounded w-3/4"></div>
              <div class="h-3 bg-gray-200 rounded w-1/2"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="categories.length === 0" class="card p-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
        <p class="text-sm text-gray-500">Chưa có danh mục nào</p>
      </div>

      <!-- Category Grid -->
      <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="cat in categories" :key="cat.id"
          class="card p-5 hover:shadow-md transition-shadow group">
          <div class="flex items-start gap-3 mb-3">
            <div class="w-11 h-11 rounded-xl bg-primary-light flex items-center justify-center flex-shrink-0 text-xl">
              {{ cat.icon || '📚' }}
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-bold text-gray-900 truncate">{{ cat.name }}</h3>
              <p v-if="cat.parent?.name" class="text-xs text-gray-400 mt-0.5">
                <span class="inline-flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                  {{ cat.parent.name }}
                </span>
              </p>
              <p v-else class="text-xs text-primary font-medium mt-0.5">Danh mục gốc</p>
            </div>
          </div>

          <div class="flex items-center justify-between pt-3 border-t border-gray-100">
            <span class="inline-flex items-center gap-1 text-xs text-gray-500">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
              {{ cat.courses_count }} khóa học
            </span>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button class="p-1.5 rounded-lg text-gray-400 hover:text-primary hover:bg-primary-light transition-colors" @click="openEditModal(cat)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              </button>
              <button class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" @click="removeCategory(cat)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </button>
            </div>
          </div>
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
                  {{ editingCat ? 'Chỉnh sửa danh mục' : 'Thêm danh mục mới' }}
                </h3>
                <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
              <form class="p-6 space-y-4" @submit.prevent="submitCategory">
                <div>
                  <label class="label">Tên danh mục <span class="text-red-500">*</span></label>
                  <input v-model="form.name" type="text" class="input" placeholder="VD: Lập trình Web" required />
                </div>
                <div>
                  <label class="label">Emoji / Icon</label>
                  <input v-model="form.icon" type="text" class="input" placeholder="VD: 💻" maxlength="4" />
                </div>
                <div>
                  <label class="label">Danh mục cha</label>
                  <select v-model="form.parent_id" class="input">
                    <option :value="null">— Không có (danh mục gốc) —</option>
                    <option v-for="cat in rootCategories" :key="cat.id" :value="cat.id"
                      :disabled="editingCat?.id === cat.id">
                      {{ cat.icon || '📁' }} {{ cat.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="label">Thứ tự hiển thị</label>
                  <input v-model.number="form.sort_order" type="number" class="input" placeholder="0" min="0" />
                </div>

                <div v-if="modalError" class="flex items-start gap-2 text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2.5">
                  <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  {{ modalError }}
                </div>

                <div class="flex justify-end gap-2 pt-1">
                  <button type="button" class="btn-ghost text-sm" @click="closeModal">Hủy</button>
                  <button type="submit" class="btn-primary text-sm px-5" :disabled="submitting">
                    <svg v-if="submitting" class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ editingCat ? 'Lưu thay đổi' : 'Tạo danh mục' }}
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
const categories = ref<any[]>([])
const loading = ref(true)
const showModal = ref(false)
const editingCat = ref<any | null>(null)
const submitting = ref(false)
const modalError = ref('')

const form = reactive({ name: '', icon: '', parent_id: null as number | null, sort_order: 0 })

const rootCategories = computed(() => categories.value.filter((c) => !c.parent_id))

async function fetchCategories() {
  loading.value = true
  try {
    const data = await useApi<any[]>('/admin/categories', { token: auth.token })
    categories.value = Array.isArray(data) ? data : []
  } catch {
    categories.value = []
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.name = ''; form.icon = ''; form.parent_id = null; form.sort_order = 0
  modalError.value = ''
}

function openCreateModal() {
  editingCat.value = null
  resetForm()
  showModal.value = true
}

function openEditModal(cat: any) {
  editingCat.value = cat
  form.name = cat.name || ''
  form.icon = cat.icon || ''
  form.parent_id = cat.parent_id || null
  form.sort_order = cat.sort_order || 0
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingCat.value = null
}

async function submitCategory() {
  submitting.value = true
  modalError.value = ''
  try {
    const payload = {
      name: form.name,
      icon: form.icon || null,
      parent_id: form.parent_id || null,
      sort_order: form.sort_order,
    }
    if (editingCat.value) {
      const res = await useApi<any>(`/admin/categories/${editingCat.value.id}`, { method: 'PUT', body: payload, token: auth.token })
      const idx = categories.value.findIndex((c) => c.id === res.category.id)
      if (idx >= 0) categories.value[idx] = res.category
    } else {
      const res = await useApi<any>('/admin/categories', { method: 'POST', body: payload, token: auth.token })
      categories.value.push(res.category)
    }
    closeModal()
  } catch (e: any) {
    modalError.value = e?.data?.message || 'Thao tác thất bại'
  } finally {
    submitting.value = false
  }
}

async function removeCategory(cat: any) {
  if (cat.courses_count > 0) {
    alert(`Không thể xóa "${cat.name}" vì đang có ${cat.courses_count} khóa học.`)
    return
  }
  if (!confirm(`Xóa danh mục "${cat.name}"?`)) return
  try {
    await useApi(`/admin/categories/${cat.id}`, { method: 'DELETE', token: auth.token })
    categories.value = categories.value.filter((c) => c.id !== cat.id)
  } catch (e: any) {
    alert(e?.data?.message || 'Xóa thất bại')
  }
}

onMounted(fetchCategories)
</script>
