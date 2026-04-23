<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import CrudConfirmModal from '~/components/dashboard/CrudConfirmModal.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm danh mục...' })
interface CategoryItem { id: number; name: string; icon?: string | null; parent_id?: number | null; parent?: { id: number; name: string } | null; courses_count?: number; sort_order?: number }
const user = useAuthUserCookie(); if (!user.value) await navigateTo('/login', { replace: true })
const token = useAuthTokenCookie(); const categories = ref<CategoryItem[]>([]); const loading = ref(false); const saving = ref(false)
const errorMessage = ref(''); const successMessage = ref(''); const modalOpen = ref(false); const confirmOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create'); const selectedCategory = ref<CategoryItem | null>(null)
const form = reactive({ name: '', icon: '', parent_id: '', sort_order: 0 })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
interface TreeCategoryItem extends CategoryItem { treePrefix: string }

const rootCategories = computed(() => categories.value.filter(item => !item.parent_id))

const treeCategories = computed<TreeCategoryItem[]>(() => {
  const byParent: Record<number, CategoryItem[]> = { 0: [] }
  categories.value.forEach(cat => {
    const pId = cat.parent_id || 0
    if (!byParent[pId]) byParent[pId] = []
    byParent[pId].push(cat)
  })
  
  Object.keys(byParent).forEach(k => {
    byParent[Number(k)].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
  })

  const result: TreeCategoryItem[] = []

  function traverse(parentId: number, prefix: string) {
    const children = byParent[parentId] || []
    for (let i = 0; i < children.length; i++) {
      const child = children[i]!
      const isLast = i === children.length - 1
      const connector = parentId === 0 ? '' : (isLast ? '└── ' : '├── ')
      
      result.push({
        ...child,
        treePrefix: parentId === 0 ? '' : prefix + connector
      })

      const nextPrefix = parentId === 0 ? '' : prefix + (isLast ? '    ' : '│   ')
      traverse(child.id, nextPrefix)
    }
  }

  traverse(0, '')
  return result
})

function resetForm() { form.name = ''; form.icon = ''; form.parent_id = ''; form.sort_order = 0 }
function openCreateModal() { modalMode.value = 'create'; selectedCategory.value = null; resetForm(); modalOpen.value = true }
function openEditModal(item: CategoryItem) { modalMode.value = 'edit'; selectedCategory.value = item; form.name = item.name; form.icon = item.icon || ''; form.parent_id = item.parent_id ? String(item.parent_id) : ''; form.sort_order = item.sort_order || 0; modalOpen.value = true }
async function fetchCategories() { loading.value = true; try { categories.value = await useApi<CategoryItem[]>('/admin/categories', { headers: authHeaders() }) } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải danh mục.' } finally { loading.value = false } }
async function saveCategory() {
  saving.value = true
  try {
    const body = { name: form.name.trim(), icon: form.icon.trim() || null, parent_id: form.parent_id ? Number(form.parent_id) : null, sort_order: Number(form.sort_order || 0) }
    if (modalMode.value === 'create') await useApi('/admin/categories', { method: 'POST', headers: authHeaders(), body })
    else if (selectedCategory.value) await useApi(`/admin/categories/${selectedCategory.value.id}`, { method: 'PUT', headers: authHeaders(), body })
    successMessage.value = modalMode.value === 'create' ? 'Đã tạo danh mục.' : 'Đã cập nhật danh mục.'; modalOpen.value = false; await fetchCategories()
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể lưu danh mục.' } finally { saving.value = false }
}
async function deleteCategory(item?: CategoryItem) {
  if (item) { selectedCategory.value = item; confirmOpen.value = true; return }
  if (!selectedCategory.value) return
  try { await useApi(`/admin/categories/${selectedCategory.value.id}`, { method: 'DELETE', headers: authHeaders() }); successMessage.value = 'Đã xóa danh mục.'; confirmOpen.value = false; await fetchCategories() }
  catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể xóa danh mục.' }
}
onMounted(fetchCategories)
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản lý khóa học', 'Danh mục']" description="Quản lý cấu trúc danh mục theo chuẩn CRUD thống nhất, hỗ trợ root/child và hiển thị số khóa học trong từng danh mục." title="Quản lý danh mục">
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green"><p class="mini-title">Tổng danh mục</p><div class="mini-head"><strong>{{ categories.length }}</strong><span>Đang hoạt động trên hệ thống</span></div></article>
      <article class="dashboard-card mini-card tone-amber"><p class="mini-title">Danh mục gốc</p><div class="mini-head"><strong>{{ rootCategories.length }}</strong><span>Dùng để tổ chức cây danh mục</span></div></article>
      <article class="dashboard-card mini-card"><p class="mini-title">Mô hình</p><div class="mini-head"><strong>CRUD</strong><span>Bảng + modal + xác nhận xóa</span></div></article>
    </section>
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar"><div><p class="section-kicker">Danh mục khóa học</p><h3>Danh sách danh mục hiện tại</h3></div><button class="crud-primary-btn" type="button" @click="openCreateModal">Tạo danh mục</button></div>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div><div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>
      <div class="crud-table-wrap"><table class="crud-table"><thead><tr><th>Tên danh mục</th><th>Cha</th><th>Icon</th><th>Số khóa học</th><th>Thứ tự</th><th>Thao tác</th></tr></thead><tbody>
        <tr v-if="loading"><td colspan="6" class="crud-empty">Đang tải danh mục...</td></tr>
        <tr v-else-if="categories.length === 0"><td colspan="6" class="crud-empty">Chưa có danh mục nào.</td></tr>
        <tr v-for="item in treeCategories" :key="item.id"><td><span style="font-family: monospace; white-space: pre; color: var(--muted);">{{ item.treePrefix }}</span><strong>{{ item.name }}</strong></td><td>{{ item.parent?.name || 'Danh mục gốc' }}</td><td>{{ item.icon || '--' }}</td><td>{{ item.courses_count || 0 }}</td><td>{{ item.sort_order || 0 }}</td><td><div class="crud-actions"><button class="action-btn is-edit" type="button" @click="openEditModal(item)">Sửa</button><button class="action-btn is-delete" type="button" @click="deleteCategory(item)">Xóa</button></div></td></tr>
      </tbody></table></div>
    </section>
    <Teleport to="body"><div v-if="modalOpen" class="crud-modal-backdrop" @click.self="modalOpen = false"><div class="crud-modal"><div class="crud-modal-head"><div><p class="section-kicker">{{ modalMode === 'create' ? 'Tạo mới' : 'Chỉnh sửa' }}</p><h3>{{ modalMode === 'create' ? 'Tạo danh mục' : 'Cập nhật danh mục' }}</h3></div><button class="topbar-ghost" type="button" @click="modalOpen = false">✕</button></div><div class="crud-form-grid"><label class="crud-field"><span>Tên danh mục</span><input v-model="form.name" type="text" placeholder="Ví dụ: Lập trình web"></label><label class="crud-field"><span>Icon</span><input v-model="form.icon" type="text" placeholder="Ví dụ: 💻"></label><label class="crud-field"><span>Danh mục cha</span><select v-model="form.parent_id"><option value="">Danh mục gốc</option><option v-for="item in rootCategories.filter(category => category.id !== selectedCategory?.id)" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></label><label class="crud-field"><span>Thứ tự</span><input v-model="form.sort_order" type="number" min="0"></label></div><div class="crud-modal-foot"><button class="crud-secondary-btn" type="button" @click="modalOpen = false">Đóng</button><button class="crud-primary-btn" type="button" :disabled="saving" @click="saveCategory">{{ saving ? 'Đang lưu...' : 'Lưu danh mục' }}</button></div></div></div></Teleport>
    <CrudConfirmModal :open="confirmOpen" title="Xóa danh mục" :description="`Bạn có chắc chắn muốn xóa ${selectedCategory?.name || 'danh mục này'}?`" confirm-text="Xóa danh mục" tone="danger" @close="confirmOpen = false" @confirm="deleteCategory()" />
  </AdminWorkspaceShell>
</template>
