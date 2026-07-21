<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CategoryItem {
  id: number
  name: string
  icon?: string | null
  parent_id?: number | null
  parent?: { id: number, name: string } | null
  courses_count?: number
  sort_order?: number
}

interface TreeCategory extends CategoryItem {
  depth: number
  parentLabel: string
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const categories = ref<CategoryItem[]>([])
const tableSearch = ref('')

const modalOpen = ref(false)
const editing = ref<CategoryItem | null>(null)
const form = reactive({
  name: '',
  icon: '',
  parent_id: null as number | null,
  sort_order: 0,
})

const childrenByParent = computed(() => {
  const map: Record<number, CategoryItem[]> = {}
  for (const cat of categories.value) {
    const pid = cat.parent_id || 0
    if (!map[pid]) map[pid] = []
    map[pid].push(cat)
  }
  Object.values(map).forEach(list => list.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)))
  return map
})

const treeRows = computed<TreeCategory[]>(() => {
  const result: TreeCategory[] = []
  const q = tableSearch.value.trim().toLowerCase()
  const walk = (parentId: number, depth: number) => {
    for (const child of childrenByParent.value[parentId] || []) {
      const row: TreeCategory = {
        ...child,
        depth,
        parentLabel: child.parent?.name || t('admin.categories.root'),
      }
      const match = !q || child.name.toLowerCase().includes(q)
      if (match) result.push(row)
      walk(child.id, depth + 1)
    }
  }
  walk(0, 0)
  return result
})

const descendantIds = computed(() => {
  if (!editing.value) return new Set<number>()
  const ids = new Set<number>()
  const stack = [editing.value.id]
  while (stack.length) {
    const id = stack.pop()!
    for (const child of childrenByParent.value[id] || []) {
      if (!ids.has(child.id)) {
        ids.add(child.id)
        stack.push(child.id)
      }
    }
  }
  return ids
})

const parentOptions = computed(() =>
  treeRows.value
    .filter(item => item.id !== editing.value?.id && !descendantIds.value.has(item.id))
    .map(item => ({
      label: `${'— '.repeat(item.depth)}${item.name}`,
      value: item.id,
    })),
)

async function load() {
  loading.value = true
  try {
    const res = await useApi<CategoryItem[] | { data: CategoryItem[] }>('/admin/categories')
    categories.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.categories.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function resetForm() {
  Object.assign(form, { name: '', icon: '', parent_id: null, sort_order: 0 })
}

function openCreate() {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: CategoryItem) {
  editing.value = item
  Object.assign(form, {
    name: item.name || '',
    icon: item.icon || '',
    parent_id: item.parent_id ?? null,
    sort_order: item.sort_order || 0,
  })
  modalOpen.value = true
}

async function save() {
  if (!form.name.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.categories.nameRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const body = {
      name: form.name.trim(),
      icon: form.icon.trim() || null,
      parent_id: form.parent_id || null,
      sort_order: Number(form.sort_order) || 0,
    }
    if (editing.value) {
      await useApi(`/admin/categories/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.categories.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/categories', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.categories.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.categories.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: CategoryItem) {
  confirm.require({
    message: t('admin.categories.deleteConfirm', { name: item.name }),
    header: t('admin.categories.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/categories/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.categories.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.categories.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page cat-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.courses') }}</span>
        <h1>{{ t('admin.categories.title') }}</h1>
        <p>{{ t('admin.categories.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.categories.searchPh')" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: treeRows.length }) }}</strong>
          <Button :label="t('admin.categories.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable :value="treeRows" data-key="id" :loading="loading">
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column :header="t('admin.categories.name')" style="min-width:220px">
          <template #body="{ data }">
            <div class="name-cell" :style="{ paddingLeft: `${data.depth * 16}px` }">
              <span v-if="data.icon" class="icon">{{ data.icon }}</span>
              <strong>{{ data.name }}</strong>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.categories.parent')" style="min-width:140px">
          <template #body="{ data }">{{ data.parentLabel }}</template>
        </Column>
        <Column :header="t('admin.categories.courses')" style="width:110px">
          <template #body="{ data }"><strong>{{ data.courses_count ?? 0 }}</strong></template>
        </Column>
        <Column :header="t('admin.categories.sortOrder')" style="width:100px">
          <template #body="{ data }">{{ data.sort_order ?? 0 }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:8rem">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('common.noData') }}</div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="editing ? t('admin.categories.edit') : t('admin.categories.add')"
      :style="{ width: 'min(520px, 96vw)' }"
    >
      <div class="form">
        <label class="field full">
          <span>{{ t('admin.categories.name') }}</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.categories.icon') }}</span>
          <InputText v-model="form.icon" class="w-full" :placeholder="t('admin.categories.iconPh')" />
        </label>
        <label class="field">
          <span>{{ t('admin.categories.sortOrder') }}</span>
          <InputNumber v-model="form.sort_order" :min="0" class="w-full" input-class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.categories.parent') }}</span>
          <Select
            v-model="form.parent_id"
            :options="parentOptions"
            option-label="label"
            option-value="value"
            show-clear
            filter
            :placeholder="t('admin.categories.root')"
            class="w-full"
          />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.cat-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }
.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }

.name-cell { display: flex; align-items: center; gap: 8px; }
.icon { font-size: 1.1rem; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }

@media (max-width: 640px) {
  .form { grid-template-columns: 1fr; }
}
</style>
