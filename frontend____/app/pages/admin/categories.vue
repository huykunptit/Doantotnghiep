<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import CategoryNode from '~/components/categories/CategoryNode.vue'
import { useAuthUserCookie, useAuthTokenCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'

// Unified UI Components
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UModal from '~/components/UModal.vue'

definePageMeta({
  layout: 'admin',
  adminSearchPlaceholder: 'Tìm danh mục...'
})

interface CategoryItem {
  id: number
  name: string
  icon?: string | null
  parent_id?: number | null
  parent?: { id: number; name: string } | null
  courses_count?: number
  sort_order?: number
}

interface TreeCategoryItem extends CategoryItem {
  depth: number
  hasChildren: boolean
  parentLabel: string
}

const user = useAuthUserCookie()
if (!user.value) {
  await navigateTo('/login', { replace: true })
}

const token = useAuthTokenCookie()
const categories = ref<CategoryItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const modalOpen = ref(false)
const confirmOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const selectedCategory = ref<CategoryItem | null>(null)
const selectedIds = ref<number[]>([])

const search = ref('')

const form = reactive({
  name: '',
  icon: '',
  parent_id: '',
  sort_order: 0
})

const authHeaders = () => ({
  Authorization: `Bearer ${token.value}`
})

const rootCategories = computed(() => {
  let list = categories.value.filter(item => !item.parent_id)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(item => item.name.toLowerCase().includes(q))
  }
  return list
})

const childrenByParent = computed(() => {
  const map: Record<number, CategoryItem[]> = { 0: [] }
  categories.value.forEach(cat => {
    const pId = cat.parent_id || 0
    if (!map[pId]) map[pId] = []
    map[pId].push(cat)
  })
  Object.keys(map).forEach(key => map[Number(key)].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)))
  return map
})

const descendantIds = computed(() => {
  if (!selectedCategory.value) return new Set<number>()
  const ids = new Set<number>()
  const stack = [selectedCategory.value.id]
  while (stack.length) {
    const parentId = stack.pop()!
    for (const child of childrenByParent.value[parentId] || []) {
      if (!ids.has(child.id)) {
        ids.add(child.id)
        stack.push(child.id)
      }
    }
  }
  return ids
})

const treeCategories = computed<TreeCategoryItem[]>(() => {
  const result: TreeCategoryItem[] = []
  const traverse = (parentId: number, depth: number) => {
    const children = childrenByParent.value[parentId] || []
    children.forEach(child => {
      result.push({
        ...child,
        depth,
        hasChildren: (childrenByParent.value[child.id] || []).length > 0,
        parentLabel: child.parent?.name || 'Danh mục gốc'
      })
      traverse(child.id, depth + 1)
    })
  }
  traverse(0, 0)
  return result
})

const parentOptions = computed(() => {
  return treeCategories.value.filter(item =>
    item.id !== selectedCategory.value?.id &&
    !descendantIds.value.has(item.id)
  )
})

function resetForm() {
  form.name = ''
  form.icon = ''
  form.parent_id = ''
  form.sort_order = 0
}

function openCreateModal() {
  modalMode.value = 'create'
  selectedCategory.value = null
  resetForm()
  modalOpen.value = true
}

function openEditModal(item: CategoryItem) {
  modalMode.value = 'edit'
  selectedCategory.value = item
  form.name = item.name
  form.icon = item.icon || ''
  form.parent_id = item.parent_id ? String(item.parent_id) : ''
  form.sort_order = item.sort_order || 0
  modalOpen.value = true
}

async function fetchCategories() {
  loading.value = true
  try {
    categories.value = await useApi<CategoryItem[]>('/admin/categories', {
      headers: authHeaders()
    })
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải danh mục.'
  } finally {
    loading.value = false
  }
}

async function submitForm() {
  saving.value = true
  errorMessage.value = ''
  try {
    const body = {
      name: form.name.trim(),
      icon: form.icon.trim() || null,
      parent_id: form.parent_id ? Number(form.parent_id) : null,
      sort_order: Number(form.sort_order || 0)
    }

    if (modalMode.value === 'create') {
      await useApi('/admin/categories', {
        method: 'POST',
        headers: authHeaders(),
        body
      })
    } else if (selectedCategory.value) {
      await useApi(`/admin/categories/${selectedCategory.value.id}`, {
        method: 'PUT',
        headers: authHeaders(),
        body
      })
    }

    successMessage.value = modalMode.value === 'create' ? 'Đã tạo danh mục.' : 'Đã cập nhật danh mục.'
    modalOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể lưu danh mục.'
  } finally {
    saving.value = false
  }
}

async function deleteCategory(item?: CategoryItem) {
  if (item) {
    selectedCategory.value = item
    confirmOpen.value = true
    return
  }

  if (!selectedCategory.value) return

  try {
    await useApi(`/admin/categories/${selectedCategory.value.id}`, {
      method: 'DELETE',
      headers: authHeaders()
    })
    successMessage.value = 'Đã xóa danh mục.'
    confirmOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa danh mục.'
  }
}

const { exportToCSV } = useExport()

function exportData() {
  const cols = [
    { key: 'id', label: 'ID Danh mục' },
    { key: 'name', label: 'Tên danh mục' },
    { key: 'parentLabel', label: 'Cha', format: (_: any, row: TreeCategoryItem) => row.parentLabel || 'Danh mục gốc' },
    { key: 'icon', label: 'Icon', format: (val: any) => String(val || '--') },
    { key: 'courses_count', label: 'Số khóa học', format: (val: any) => String(val || 0) },
    { key: 'sort_order', label: 'Thứ tự', format: (val: any) => String(val || 0) }
  ]
  exportToCSV(treeCategories.value, cols, 'danh_sach_danh_muc')
}

onMounted(fetchCategories)
</script>

<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-[0.68rem] font-bold uppercase tracking-widest mb-1" style="color:var(--muted)">Khóa học</p>
        <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text)">Quản lý danh mục</h1>
        <p class="text-sm mt-0.5" style="color:var(--muted)">Quản lý cấu trúc danh mục học tập theo dạng cây thư mục. Mở rộng, thu gọn và tương tác trực quan với các danh mục.</p>
      </div>
      <button
        class="inline-flex items-center gap-2 h-10 px-5 rounded-xl bg-[#1d9e75] hover:bg-[#178762] text-white text-sm font-semibold transition-colors shrink-0 cursor-pointer"
        type="button"
        @click="openCreateModal"
      >
        Tạo danh mục
      </button>
    </div>

    <!-- Stats KPI Cards -->
    <UiKpiCards
      :items="[
        { label: 'Tổng danh mục', value: categories.length, subText: 'Đang hoạt động trên hệ thống', color: 'primary', icon: 'pi-folder' },
        { label: 'Danh mục gốc', value: rootCategories.length, subText: 'Dùng để tổ chức cây danh mục', color: 'warning', icon: 'pi-folder-open' },
        { label: 'Kiểu hiển thị', value: 'Collapsible Tree', subText: 'Cây danh mục đóng/mở trực quan', color: 'info', icon: 'pi-sitemap' }
      ]"
    />

    <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm flex flex-col gap-5">
      
      <!-- Toolbar & Filters -->
      <UiFilters
        v-model:search="search"
        search-placeholder="Tìm kiếm danh mục khóa học..."
        :show-export="true"
        @export="exportData"
      />

      <div v-if="errorMessage" class="mx-5 flex items-center gap-2 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
        <i class="pi pi-exclamation-circle shrink-0" />{{ errorMessage }}
      </div>
      <div v-if="successMessage" class="mx-5 flex items-center gap-2 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
        <i class="pi pi-check-circle shrink-0" />{{ successMessage }}
      </div>

      <div class="categories-tree-container px-5 pb-5">
        <div v-if="loading" class="text-center py-12 text-sm text-[var(--muted)]">Đang tải danh mục...</div>
        <div v-else-if="categories.length === 0" class="text-center py-12 text-sm text-[var(--muted)]">Chưa có danh mục nào.</div>
        <div v-else class="tree-root">
          <CategoryNode
            v-for="item in rootCategories"
            :key="item.id"
            :category="item"
            :children-map="childrenByParent"
            :depth="0"
            @edit="openEditModal($event)"
            @delete="deleteCategory($event)"
          />
        </div>
      </div>
    </section>

    <!-- Standardized Modal -->
    <UModal 
      v-model:open="modalOpen" 
      :title="modalMode === 'create' ? 'Tạo danh mục mới' : 'Cập nhật danh mục'"
      :subtitle="modalMode === 'create' ? 'Tạo mới' : 'Chỉnh sửa'"
      :ui="{ width: 'max-w-lg' }"
    >
      <div class="flex flex-col gap-4">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Tên danh mục</span>
          <input v-model="form.name" type="text" placeholder="Ví dụ: Lập trình web" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
        </label>
        
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Icon đại diện</span>
          <input v-model="form.icon" type="text" placeholder="Ví dụ: 💻" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
        </label>
        
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Danh mục cha</span>
          <select v-model="form.parent_id" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75] cursor-pointer">
            <option value="">Danh mục gốc</option>
            <option v-for="item in parentOptions" :key="item.id" :value="String(item.id)">
              {{ `${'— '.repeat(item.depth)}${item.name}` }}
            </option>
          </select>
        </label>
        
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-[var(--text)]">Thứ tự sắp xếp</span>
          <input v-model="form.sort_order" type="number" placeholder="Ví dụ: 1" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm focus:outline-none focus:border-[#1d9e75]">
        </label>
      </div>

      <template #footer>
        <button class="btn-secondary" type="button" :disabled="saving" @click="modalOpen = false">
          Hủy
        </button>
        <button class="btn-primary" type="button" :disabled="saving" @click="submitForm">
          {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
        </button>
      </template>
    </UModal>

    <!-- Delete Confirm Dialog -->
    <CrudConfirmModal
      :open="confirmOpen"
      title="Xóa danh mục"
      message="Bạn có chắc chắn muốn xóa danh mục này? Hành động này không thể hoàn tác và các danh mục con liên quan cũng sẽ chịu ảnh hưởng."
      @close="confirmOpen = false"
      @confirm="deleteCategory()"
    />
  </div>
</template>

<style scoped>
.categories-tree-container {
  padding: 12px 20px;
}

.tree-root {
  display: flex;
  flex-direction: column;
}
</style>
