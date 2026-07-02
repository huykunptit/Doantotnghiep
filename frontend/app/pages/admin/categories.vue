<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import CategoryNode from '~/components/categories/CategoryNode.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'
import { useAuthTokenCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'

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

const form = reactive({
  name: '',
  icon: '',
  parent_id: '',
  sort_order: 0
})

const authHeaders = () => ({
  Authorization: `Bearer ${token.value}`
})

const rootCategories = computed(() => categories.value.filter(item => !item.parent_id))

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
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản lý khóa học', 'Danh mục']"
    description="Quản lý cấu trúc danh mục học tập theo dạng cây thư mục thông minh. Bạn có thể mở rộng, thu gọn và tương tác trực quan với các danh mục."
    title="Quản lý danh mục"
  >
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Tổng danh mục</p>
        <div class="mini-head">
          <strong>{{ categories.length }}</strong>
          <span>Đang hoạt động trên hệ thống</span>
        </div>
      </article>

      <article class="dashboard-card mini-card tone-amber">
        <p class="mini-title">Danh mục gốc</p>
        <div class="mini-head">
          <strong>{{ rootCategories.length }}</strong>
          <span>Dùng để tổ chức cây danh mục</span>
        </div>
      </article>

      <article class="dashboard-card mini-card">
        <p class="mini-title">Mô hình</p>
        <div class="mini-head">
          <strong>Collapsible Tree</strong>
          <span>Cây danh mục có khả năng đóng/mở trực quan</span>
        </div>
      </article>
    </section>

    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Danh mục khóa học</p>
          <h3>Cấu trúc danh mục hiện tại</h3>
        </div>
        <div class="crud-toolbar-right">
          <button class="crud-export-btn" type="button" @click="exportData">
            <span class="material-symbols-outlined">download</span>
            Xuất Excel
          </button>
          <button class="crud-primary-btn" type="button" @click="openCreateModal">
            Tạo danh mục
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="crud-alert is-error">
        {{ errorMessage }}
      </div>
      <div v-if="successMessage" class="crud-alert is-success">
        {{ successMessage }}
      </div>

      <div class="categories-tree-container">
        <div v-if="loading" class="crud-empty">Đang tải danh mục...</div>
        <div v-else-if="categories.length === 0" class="crud-empty">Chưa có danh mục nào.</div>
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

    <UModal v-model:open="modalOpen" :ui="{ width: 'max-w-lg' }">
      <template #content>
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ modalMode === 'create' ? 'Tạo mới' : 'Chỉnh sửa' }}</p>
              <h3>{{ modalMode === 'create' ? 'Tạo danh mục' : 'Cập nhật danh mục' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="modalOpen = false">✕</button>
          </div>

          <div class="crud-form-grid" style="padding: 24px 28px;">
            <label class="crud-field">
              <span>Tên danh mục</span>
              <input v-model="form.name" type="text" placeholder="Ví dụ: Lập trình web">
            </label>
            <label class="crud-field">
              <span>Icon</span>
              <input v-model="form.icon" type="text" placeholder="Ví dụ: 💻">
            </label>
            <label class="crud-field">
              <span>Danh mục cha</span>
              <select v-model="form.parent_id">
                <option value="">Danh mục gốc</option>
                <option v-for="item in parentOptions" :key="item.id" :value="String(item.id)">
                  {{ `${'— '.repeat(item.depth)}${item.name}` }}
                </option>
              </select>
            </label>
            <label class="crud-field">
              <span>Thứ tự sắp xếp</span>
              <input v-model="form.sort_order" type="number" placeholder="Ví dụ: 1">
            </label>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" :disabled="saving" @click="modalOpen = false">
              Hủy
            </button>
            <button class="crud-primary-btn" type="button" :disabled="saving" @click="submitForm">
              {{ saving ? 'Đang lưu...' : 'Lưu' }}
            </button>
          </div>
        </div>
      </template>
    </UModal>

    <CrudConfirmModal
      :open="confirmOpen"
      title="Xóa danh mục"
      message="Bạn có chắc chắn muốn xóa danh mục này? Hành động này không thể hoàn tác và các danh mục con liên quan cũng sẽ chịu ảnh hưởng."
      @close="confirmOpen = false"
      @confirm="deleteCategory()"
    />
  </AdminWorkspaceShell>
</template>

<style scoped>
.categories-tree-container {
  padding: 12px 0;
}

.tree-root {
  display: flex;
  flex-direction: column;
}

.dropdown-item.is-danger:hover {
  background-color: #fef2f2;
}

.dropdown-divider {
  height: 1px;
  background-color: rgba(17, 17, 17, 0.1);
  margin: 4px 0;
}
</style>

