<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'
import { useExport } from '~/composables/useExport'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin' })

interface CategoryItem {
  id: number
  name: string
  slug?: string | null
  icon?: string | null
  parent_id?: number | null
  parent?: { id: number; name: string } | null
  sort_order?: number | null
  courses_count?: number
  created_at?: string | null
  updated_at?: string | null
}

const token = useAuthTokenCookie()
const toast = useToast()
const { exportToCSV } = useExport()

const categories = ref<CategoryItem[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const search = ref('')
const parentFilter = ref('')
const modalOpen = ref(false)
const deleteOpen = ref(false)
const modalMode = ref<'create' | 'edit' | 'view'>('create')
const selectedCategory = ref<CategoryItem | null>(null)

const form = reactive({
  name: '',
  icon: '',
  parent_id: '',
  sort_order: 0
})

const columns = [
  { id: 'index', accessorKey: 'index', header: '#' },
  { id: 'category', accessorKey: 'name', header: 'Danh mục', sortable: true },
  { id: 'parent', accessorKey: 'parent', header: 'Danh mục cha' },
  { id: 'courses', accessorKey: 'courses_count', header: 'Khóa học', sortable: true, class: 'text-center' },
  { id: 'sort', accessorKey: 'sort_order', header: 'Thứ tự', sortable: true, class: 'text-center' },
  { id: 'updated', accessorKey: 'updated_at', header: 'Cập nhật' },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

const parentOptions = computed(() => [
  { label: 'Không có danh mục cha', value: '' },
  ...categories.value
    .filter(item => !selectedCategory.value || item.id !== selectedCategory.value.id)
    .map(item => ({ label: item.name, value: String(item.id) }))
])

const filterParentOptions = computed(() => [
  { label: 'Tất cả cấp danh mục', value: '' },
  { label: 'Danh mục gốc', value: 'root' },
  ...categories.value.map(item => ({ label: `Con của ${item.name}`, value: String(item.id) }))
])

const filteredCategories = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  return categories.value.filter((item) => {
    const matchKeyword = !keyword
      || item.name.toLowerCase().includes(keyword)
      || String(item.slug || '').toLowerCase().includes(keyword)
      || String(item.parent?.name || '').toLowerCase().includes(keyword)

    const matchParent = !parentFilter.value
      || (parentFilter.value === 'root' && !item.parent_id)
      || String(item.parent_id || '') === parentFilter.value

    return matchKeyword && matchParent
  })
})

const stats = computed(() => {
  const roots = categories.value.filter(item => !item.parent_id).length
  const withCourses = categories.value.filter(item => Number(item.courses_count || 0) > 0).length
  const empty = categories.value.filter(item => Number(item.courses_count || 0) === 0).length
  const totalCourses = categories.value.reduce((sum, item) => sum + Number(item.courses_count || 0), 0)

  return [
    { label: 'Tổng danh mục', value: categories.value.length, subText: `${roots} danh mục gốc`, color: 'info', icon: 'pi-tags' },
    { label: 'Đang có khóa học', value: withCourses, subText: 'danh mục hoạt động', color: 'success', icon: 'pi-book' },
    { label: 'Chưa gắn khóa', value: empty, subText: 'cần rà soát', color: 'warning', icon: 'pi-inbox' },
    { label: 'Tổng khóa được gắn', value: totalCourses, subText: 'theo category', color: 'purple', icon: 'pi-sitemap' }
  ]
})

const activeFilterCount = computed(() => parentFilter.value ? 1 : 0)
const activeChips = computed(() => {
  if (!parentFilter.value) return []
  const label = filterParentOptions.value.find(item => item.value === parentFilter.value)?.label || parentFilter.value
  return [{ key: 'parent', label }]
})

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

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
  form.name = item.name || ''
  form.icon = item.icon || ''
  form.parent_id = item.parent_id ? String(item.parent_id) : ''
  form.sort_order = Number(item.sort_order || 0)
  modalOpen.value = true
}

function openViewModal(item: CategoryItem) {
  modalMode.value = 'view'
  selectedCategory.value = item
  modalOpen.value = true
}

function confirmDelete(item: CategoryItem) {
  selectedCategory.value = item
  deleteOpen.value = true
}

function resetFilters() {
  search.value = ''
  parentFilter.value = ''
}

function removeChip() {
  parentFilter.value = ''
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

async function fetchCategories() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await useApi<CategoryItem[]>('/admin/categories', { headers: authHeaders() })
    categories.value = Array.isArray(response) ? response : []
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải danh mục khóa học.'
    toast.error('Không thể tải danh mục', errorMessage.value)
  } finally {
    loading.value = false
  }
}

async function saveCategory() {
  if (!form.name.trim()) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  const payload = {
    name: form.name.trim(),
    icon: form.icon.trim() || null,
    parent_id: form.parent_id ? Number(form.parent_id) : null,
    sort_order: Number(form.sort_order || 0)
  }

  try {
    if (modalMode.value === 'create') {
      await useApi('/admin/categories', { method: 'POST', headers: authHeaders(), body: payload })
      successMessage.value = 'Tạo danh mục thành công.'
      toast.success('Tạo danh mục thành công')
    } else if (selectedCategory.value) {
      await useApi(`/admin/categories/${selectedCategory.value.id}`, { method: 'PUT', headers: authHeaders(), body: payload })
      successMessage.value = 'Cập nhật danh mục thành công.'
      toast.success('Cập nhật danh mục thành công')
    }

    modalOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể lưu danh mục.'
    toast.error('Không thể lưu danh mục', errorMessage.value)
  } finally {
    saving.value = false
  }
}

async function deleteCategory() {
  if (!selectedCategory.value) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await useApi(`/admin/categories/${selectedCategory.value.id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Xóa danh mục thành công.'
    toast.success('Xóa danh mục thành công')
    deleteOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể xóa danh mục. Hãy kiểm tra danh mục còn khóa học hay danh mục con không.'
    toast.error('Không thể xóa danh mục', errorMessage.value)
  } finally {
    saving.value = false
  }
}

function exportData() {
  exportToCSV(filteredCategories.value, [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Tên danh mục' },
    { key: 'slug', label: 'Slug' },
    { key: 'courses_count', label: 'Số khóa học' },
    { key: 'sort_order', label: 'Thứ tự' }
  ], 'danh_muc_khoa_hoc')
}

onMounted(fetchCategories)
</script>

<template>
  <AdminWorkspaceShell
    title="Danh mục khóa học"
    subtitle="Quản lý cây danh mục, thứ tự hiển thị và nhóm nội dung học tập."
    :breadcrumbs="[{ label: 'Nội dung học tập' }, { label: 'Danh mục khóa học' }]"
  >
    <div class="flex flex-col gap-5">
      <UiFilters
        v-model:search="search"
        search-placeholder="Tìm danh mục, slug, danh mục cha..."
        :active-filter-count="activeFilterCount"
        :active-chips="activeChips"
        :show-export="true"
        export-text="Xuất danh mục"
        :always-open="true"
        @submit-search="() => {}"
        @reset-filters="resetFilters"
        @remove-chip="removeChip"
        @export="exportData"
      >
        <template #actions>
          <button class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-xs font-bold text-white transition hover:bg-[#178563]" type="button" @click="openCreateModal">
            <i class="pi pi-plus" />
            Tạo danh mục
          </button>
        </template>

        <template #advanced>
          <label class="flex min-w-[220px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Cấp danh mục</span>
            <UiSelect v-model="parentFilter" :options="filterParentOptions" placeholder="Tất cả cấp danh mục" />
          </label>
        </template>
      </UiFilters>

      <UiKpiCards :items="stats" />

      <div v-if="successMessage" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">
        <i class="pi pi-check-circle" /> {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
        <i class="pi pi-exclamation-circle" /> {{ errorMessage }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-[var(--line)] bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-[var(--line)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-base font-bold text-[var(--text)]">Danh sách danh mục</h3>
            <p class="text-xs text-[var(--muted)]">{{ filteredCategories.length }} / {{ categories.length }} danh mục phù hợp bộ lọc</p>
          </div>
          <button class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50" type="button" @click="fetchCategories">
            <i class="pi pi-refresh" />
            Làm mới
          </button>
        </div>

        <UiTable :columns="columns" :data="filteredCategories" :loading="loading">
          <template #index-cell="{ row }">
            <span class="text-xs font-medium text-[var(--muted)]">{{ row.index + 1 }}</span>
          </template>

          <template #category-cell="{ row }">
            <div class="flex min-w-[260px] items-center gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-lg">
                <span v-if="row.original.icon">{{ row.original.icon }}</span>
                <i v-else class="pi pi-folder text-sm text-emerald-700" />
              </div>
              <div class="min-w-0">
                <strong class="block truncate text-sm font-bold text-[var(--text)]">{{ row.original.name }}</strong>
                <span class="mt-0.5 block truncate text-xs text-[var(--muted)]">{{ row.original.slug || 'Chưa có slug' }}</span>
              </div>
            </div>
          </template>

          <template #parent-cell="{ row }">
            <span v-if="row.original.parent" class="inline-flex h-6 items-center rounded-full border border-blue-100 bg-blue-50 px-2.5 text-[10px] font-bold text-blue-700">
              {{ row.original.parent.name }}
            </span>
            <span v-else class="inline-flex h-6 items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 text-[10px] font-bold text-slate-500">
              Gốc
            </span>
          </template>

          <template #courses-cell="{ row }">
            <span class="text-xs font-bold text-[var(--text)]">{{ row.original.courses_count || 0 }}</span>
          </template>

          <template #sort-cell="{ row }">
            <span class="text-xs font-semibold text-[var(--muted)]">{{ row.original.sort_order || 0 }}</span>
          </template>

          <template #updated-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ formatDate(row.original.updated_at) }}</span>
          </template>

          <template #actions-cell="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <button class="ds-btn ds-btn--view ds-btn--icon" title="Xem" type="button" @click="openViewModal(row.original)"><i class="pi pi-eye" /></button>
              <button class="ds-btn ds-btn--edit ds-btn--icon" title="Sửa" type="button" @click="openEditModal(row.original)"><i class="pi pi-pencil" /></button>
              <button class="ds-btn ds-btn--delete ds-btn--icon" title="Xóa" type="button" @click="confirmDelete(row.original)"><i class="pi pi-trash" /></button>
            </div>
          </template>
        </UiTable>
      </section>
    </div>

    <UModal v-model:open="modalOpen" :title="modalMode === 'create' ? 'Tạo danh mục' : modalMode === 'edit' ? 'Chỉnh sửa danh mục' : 'Chi tiết danh mục'" :ui="{ width: 'max-w-xl' }">
      <div v-if="modalMode === 'view' && selectedCategory" class="space-y-4">
        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-xl">
              <span v-if="selectedCategory.icon">{{ selectedCategory.icon }}</span>
              <i v-else class="pi pi-folder text-emerald-700" />
            </div>
            <div>
              <h3 class="text-base font-bold text-[var(--text)]">{{ selectedCategory.name }}</h3>
              <p class="text-xs text-[var(--muted)]">{{ selectedCategory.slug || 'Chưa có slug' }}</p>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-xs">
          <div><p class="font-bold uppercase text-[var(--muted)]">Danh mục cha</p><p class="mt-1 font-semibold">{{ selectedCategory.parent?.name || 'Danh mục gốc' }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Số khóa học</p><p class="mt-1 font-semibold">{{ selectedCategory.courses_count || 0 }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Thứ tự</p><p class="mt-1 font-semibold">{{ selectedCategory.sort_order || 0 }}</p></div>
          <div><p class="font-bold uppercase text-[var(--muted)]">Cập nhật</p><p class="mt-1 font-semibold">{{ formatDate(selectedCategory.updated_at) }}</p></div>
        </div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="saveCategory">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[var(--text)]">Tên danh mục</span>
          <input v-model="form.name" required class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" placeholder="VD: Công nghệ thông tin" />
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Icon emoji</span>
            <input v-model="form.icon" maxlength="10" class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" placeholder="📚" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-bold text-[var(--text)]">Thứ tự</span>
            <input v-model="form.sort_order" type="number" min="0" class="h-10 rounded-xl border border-[var(--line)] bg-[#f8fafc] px-3.5 text-sm outline-none focus:border-[#1d9e75]" />
          </label>
        </div>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-[var(--text)]">Danh mục cha</span>
          <UiSelect v-model="form.parent_id" :options="parentOptions" placeholder="Không có danh mục cha" />
        </label>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="h-10 rounded-xl border border-[var(--line)] px-4 text-xs font-bold text-[var(--muted)] hover:bg-slate-50" @click="modalOpen = false">Hủy</button>
          <button type="submit" :disabled="saving" class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-5 text-xs font-bold text-white hover:bg-[#178563] disabled:opacity-60">
            <i v-if="saving" class="pi pi-spin pi-spinner" />
            {{ modalMode === 'create' ? 'Tạo danh mục' : 'Lưu thay đổi' }}
          </button>
        </div>
      </form>
    </UModal>

    <CrudConfirmModal
      :open="deleteOpen"
      title="Xóa danh mục"
      :description="`Bạn có chắc chắn muốn xóa danh mục ${selectedCategory?.name || ''}? Danh mục đang có khóa học sẽ không thể xóa.`"
      confirm-text="Xóa danh mục"
      tone="danger"
      :loading="saving"
      @close="deleteOpen = false"
      @confirm="deleteCategory"
    />
  </AdminWorkspaceShell>
</template>