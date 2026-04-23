<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-surface-dim/30 pb-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Category Management</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface mt-1">Hệ thống Danh mục</h2>
          <p class="text-on-surface-variant text-sm mt-2">
            Phân loại, hệ thống hóa và thiết lập Taxonomy mạch lạc cho các luồng Kiến thức học thuật trên hệ thống.
          </p>
        </div>
        <button @click="openCreateModal" class="px-5 py-2.5 cta-gradient text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition-transform active:scale-95 flex items-center gap-2">
           <span class="material-symbols-outlined text-[18px]">add_box</span> Khởi tạo Danh mục
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div v-for="item in 8" :key="item" class="h-32 rounded-[1.25rem] bg-surface-high animate-pulse" />
      </div>

      <!-- Empty State -->
      <div v-else-if="categories.length === 0" class="py-20 text-center bg-surface-low rounded-2xl border border-surface-dim mt-4 shadow-inner">
         <span class="material-symbols-outlined text-6xl text-outline opacity-50 mb-4">account_tree</span>
         <h4 class="font-bold text-on-surface text-lg mb-1">Chưa có Taxonomy (Cấu trúc rễ)</h4>
         <p class="font-medium text-sm text-on-surface-variant max-w-md mx-auto">Hệ thống yêu cầu các bộ danh mục gốc để giảng viên có thể phân nhánh kiến thức.</p>
      </div>

      <!-- Category Tree -->
      <div v-else class="space-y-6">
        <section v-for="root in categoryTree" :key="root.id" class="rounded-[1.5rem] border border-surface-dim/30 bg-surface-lowest p-6 shadow-sm">
          <div class="flex flex-col gap-4 border-b border-surface-dim/30 pb-5 md:flex-row md:items-start md:justify-between">
            <div class="flex items-start gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-surface-low border border-surface-dim/50 text-xl shadow-sm">
                {{ root.icon || '📚' }}
              </div>
              <div>
                <h3 class="font-bold text-on-surface text-lg">{{ root.name }}</h3>
                <p class="mt-1 text-xs font-semibold uppercase tracking-widest text-outline">ID #{{ root.id }} · {{ root.courses_count || 0 }} khóa trực tiếp</p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button @click="openEditModal(root)" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors border border-transparent hover:border-primary/20" title="Chỉnh sửa">
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
              <button @click="removeCategory(root)" class="p-2 text-outline hover:text-error hover:bg-error-container/50 rounded-lg transition-colors border border-transparent hover:border-error/20" title="Xóa">
                <span class="material-symbols-outlined text-[18px]">delete</span>
              </button>
            </div>
          </div>

          <div v-if="root.children?.length" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="child in root.children" :key="child.id" class="rounded-[1.25rem] border border-surface-dim/30 bg-surface-low p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-semibold text-on-surface">{{ child.name }}</p>
                  <p class="mt-1 text-xs text-on-surface-variant">{{ child.courses_count || 0 }} khóa học</p>
                </div>
                <div class="flex items-center gap-1">
                  <button @click="openEditModal(child)" class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Chỉnh sửa">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                  </button>
                  <button @click="removeCategory(child)" class="p-1.5 text-outline hover:text-error hover:bg-error-container/50 rounded-lg transition-colors" title="Xóa">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Add/Edit Modal (Teleport overlay) -->
      <Teleport to="body">
         <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="closeModal">
            <div class="w-full max-w-md rounded-[2rem] bg-surface-lowest p-8 shadow-ambient border border-surface-dim modal-bounce">
               <div class="mb-6 flex items-center justify-between pb-4 border-b border-surface-dim/30">
                  <h3 class="font-headline text-xl font-bold text-on-surface flex items-center gap-2">
                     <span class="material-symbols-outlined text-primary">{{ editingCat ? 'edit_square' : 'add_box' }}</span>
                     {{ editingCat ? 'Chỉnh sửa Danh mục' : 'Danh mục mới' }}
                  </h3>
                  <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="closeModal">
                     <span class="material-symbols-outlined text-[20px]">close</span>
                  </button>
               </div>
               
               <form class="space-y-5" @submit.prevent="submitCategory">
                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Tên danh mục <span class="text-error">*</span></label>
                     <input v-model="form.name" required class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="VD: Backend Development">
                  </div>

                  <div class="flex gap-4">
                     <div class="w-1/3">
                        <label class="block text-sm font-bold text-on-surface mb-2">Emoji/Icon</label>
                        <input v-model="form.icon" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline text-center text-lg" placeholder="💻">
                     </div>
                     <div class="flex-1">
                        <label class="block text-sm font-bold text-on-surface mb-2">Sort Order</label>
                        <input v-model.number="form.sort_order" type="number" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all placeholder-outline" placeholder="0">
                     </div>
                  </div>

                  <div>
                     <label class="block text-sm font-bold text-on-surface mb-2">Phân nhánh Rễ (Danh mục cha)</label>
                     <select v-model="form.parent_id" class="w-full rounded-xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all">
                        <option :value="null">-- Danh mục Rễ (Không có cha) --</option>
                        <option v-for="cat in rootCategories" :key="cat.id" :value="cat.id" :disabled="editingCat?.id === cat.id">
                           {{ cat.name }} (ID: {{ cat.id }})
                        </option>
                     </select>
                  </div>

                  <div v-if="modalError" class="p-3 bg-error-container/30 border border-error/20 text-error text-xs font-bold rounded-lg">{{ modalError }}</div>
                  
                  <div class="sticky bottom-0 -mx-8 -mb-8 mt-6 flex flex-col gap-3 border-t border-surface-dim/30 bg-surface-lowest/95 px-8 py-5 backdrop-blur sm:flex-row sm:justify-end">
                     <UiButton type="button" variant="secondary" size="lg" @click="closeModal">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                        Hủy bỏ
                     </UiButton>
                     <UiButton type="submit" size="lg" :loading="submitting">
                        <span v-if="!submitting" class="material-symbols-outlined text-[18px]">task_alt</span>
                        {{ editingCat ? 'Cập nhật danh mục' : 'Tạo danh mục' }}
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

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })
const auth = useAuthStore()

const categories = ref<any[]>([])
const loading = ref(true)
const showModal = ref(false)
const editingCat = ref<any | null>(null)
const submitting = ref(false)
const modalError = ref('')
const form = reactive({ name: '', icon: '', parent_id: null as number | null, sort_order: 0 })

const rootCategories = computed(() => categories.value.filter((c) => !c.parent_id))
const categoryTree = computed(() => rootCategories.value.map((root) => ({
  ...root,
  children: categories.value.filter((category) => category.parent_id === root.id),
})))

async function fetchCategories() {
  loading.value = true
  try { 
     const data = await $fetch<any[]>('/api/admin/categories', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => []) 
     categories.value = data
  } catch { 
     categories.value = [] 
  } finally { 
     loading.value = false 
  }
}

function resetForm() { 
   form.name = ''
   form.icon = ''
   form.parent_id = null
   form.sort_order = 0
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
    const payload = { name: form.name.trim(), icon: form.icon || null, parent_id: form.parent_id || null, sort_order: Number(form.sort_order) || 0 }
    
    if (editingCat.value) {
       await $fetch(`/api/admin/categories/${editingCat.value.id}`, { method: 'PUT', body: payload, headers: { Authorization: `Bearer ${auth.token}` } })
    } else {
       await $fetch('/api/admin/categories', { method: 'POST', body: payload, headers: { Authorization: `Bearer ${auth.token}` } })
    }
    closeModal()
    await fetchCategories()
  } catch (e: any) { 
     modalError.value = e?.data?.message || 'Có lỗi khi lưu thao diễn danh mục.' 
  } finally { 
     submitting.value = false 
  }
}

async function removeCategory(cat: any) {
  if (cat.courses_count > 0) return alert(`Hành động bị chặn: Không thể xóa Danh mục "${cat.name}" do đang gắn với ${cat.courses_count} khóa học phát hành.`)
  if (!confirm(`Cảnh báo hệ thống:\nBạn chắc chắn xóa bỏ vĩnh viễn thẻ Taxonomy: "${cat.name}"?`)) return
  
  try { 
     await $fetch(`/api/admin/categories/${cat.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
     await fetchCategories() 
  } catch (e: any) { 
     alert(e?.data?.message || 'Lỗi DB, không thể xóa.') 
  }
}

onMounted(fetchCategories)
</script>

<style scoped>
.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
