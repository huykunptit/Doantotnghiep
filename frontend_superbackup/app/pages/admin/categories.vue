<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthUserCookie, useAuthTokenCookie } from '~/composables/useAuthSession'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

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
const toast = useToast()
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
    toast.success(successMessage.value)
    modalOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể lưu danh mục.'
    toast.error(errorMessage.value)
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
    toast.success(successMessage.value)
    confirmOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa danh mục.'
    toast.error(errorMessage.value)
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
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Khóa học</p>
        <h1 class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-0">Quản lý danh mục</h1>
        <p class="mt-1 text-sm text-surface-500">Tổ chức danh mục khóa học và cấu trúc phân cấp.</p>
      </div>
      <Button label="Tạo danh mục" icon="pi pi-plus" @click="openCreateModal" />
    </header>

    <Card>
      <template #content>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <IconField class="w-full sm:max-w-md">
            <InputIcon class="pi pi-search" />
            <InputText v-model="search" class="w-full" placeholder="Tìm kiếm danh mục..." />
          </IconField>
          <Button label="Xuất CSV" icon="pi pi-download" severity="secondary" outlined @click="exportData" />
        </div>
        <DataTable :value="treeCategories.filter(item => !search.trim() || item.name.toLowerCase().includes(search.toLowerCase()))" :loading="loading" striped-rows responsive-layout="scroll">
          <template #empty>Chưa có danh mục phù hợp.</template>
          <Column field="name" header="Danh mục">
            <template #body="{ data }">
              <div class="flex items-center gap-2" :style="{ paddingLeft: `${data.depth * 1.25}rem` }">
                <span>{{ data.icon || '📁' }}</span><strong>{{ data.name }}</strong>
              </div>
            </template>
          </Column>
          <Column field="parentLabel" header="Danh mục cha" />
          <Column field="courses_count" header="Khóa học"><template #body="{ data }">{{ data.courses_count || 0 }}</template></Column>
          <Column field="sort_order" header="Thứ tự" />
          <Column header="Thao tác" style="width: 10rem">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button icon="pi pi-pencil" severity="secondary" text rounded aria-label="Sửa" @click="openEditModal(data)" />
                <Button icon="pi pi-trash" severity="danger" text rounded aria-label="Xóa" @click="deleteCategory(data)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="modalOpen" modal :header="modalMode === 'create' ? 'Tạo danh mục mới' : 'Cập nhật danh mục'" class="w-[min(32rem,calc(100vw-2rem))]">
      <div class="grid gap-4">
        <label class="grid gap-2"><span class="font-medium">Tên danh mục</span><InputText v-model="form.name" autofocus placeholder="Ví dụ: Lập trình web" /></label>
        <label class="grid gap-2"><span class="font-medium">Icon đại diện</span><InputText v-model="form.icon" placeholder="Ví dụ: 💻" /></label>
        <label class="grid gap-2"><span class="font-medium">Danh mục cha</span>
          <Select v-model="form.parent_id" :options="[{ id: '', name: 'Danh mục gốc' }, ...parentOptions]" option-label="name" option-value="id" placeholder="Chọn danh mục cha" fluid />
        </label>
        <label class="grid gap-2"><span class="font-medium">Thứ tự sắp xếp</span><InputNumber v-model="form.sort_order" :min="0" fluid /></label>
      </div>
      <template #footer>
        <Button label="Hủy" severity="secondary" text @click="modalOpen = false" />
        <Button label="Lưu thay đổi" icon="pi pi-check" :loading="saving" :disabled="!form.name.trim()" @click="submitForm" />
      </template>
    </Dialog>

    <Dialog v-model:visible="confirmOpen" modal header="Xóa danh mục" class="w-[min(28rem,calc(100vw-2rem))]">
      <p>Bạn có chắc muốn xóa danh mục <strong>{{ selectedCategory?.name }}</strong>? Hành động này không thể hoàn tác.</p>
      <template #footer>
        <Button label="Hủy" severity="secondary" text @click="confirmOpen = false" />
        <Button label="Xóa danh mục" severity="danger" icon="pi pi-trash" @click="deleteCategory()" />
      </template>
    </Dialog>
  </div>
</template>
