<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NewsItem {
  id: number
  title: string
  slug: string
  excerpt?: string | null
  content?: string | null
  cover_image?: string | null
  cover_image_url?: string | null
  status: string
  is_featured?: boolean
  published_at?: string | null
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const items = ref<NewsItem[]>([])
const search = ref('')
const statusFilter = ref<string | null>(null)
const modalOpen = ref(false)
const editing = ref<NewsItem | null>(null)
const form = reactive({
  title: '',
  excerpt: '',
  content: '',
  cover_image: '',
  status: 'draft',
  is_featured: false,
  notify_students: false,
})

const statusOptions = computed(() => [
  { label: t('admin.news.statusDraft'), value: 'draft' },
  { label: t('admin.news.statusPublished'), value: 'published' },
])

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleString(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data?: NewsItem[] }>('/admin/news', {
      query: {
        per_page: 50,
        search: search.value || undefined,
        status: statusFilter.value || undefined,
      },
    })
    items.value = res.data || []
  }
  catch {
    items.value = []
    toast.add({ severity: 'error', summary: t('admin.news.loadError'), life: 3000 })
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    title: '',
    excerpt: '',
    content: '',
    cover_image: '',
    status: 'draft',
    is_featured: false,
    notify_students: false,
  })
  modalOpen.value = true
}

function openEdit(item: NewsItem) {
  editing.value = item
  Object.assign(form, {
    title: item.title,
    excerpt: item.excerpt || '',
    content: item.content || '',
    cover_image: item.cover_image || '',
    status: item.status,
    is_featured: !!item.is_featured,
    notify_students: false,
  })
  modalOpen.value = true
  // load full content
  useApi<NewsItem>(`/admin/news/${item.id}`).then((full) => {
    if (full?.content) form.content = full.content
  }).catch(() => {})
}

async function save() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.news.titleRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const body = {
      title: form.title.trim(),
      excerpt: form.excerpt || null,
      content: form.content || null,
      cover_image: form.cover_image || null,
      status: form.status,
      is_featured: form.is_featured,
      notify_students: form.notify_students,
    }
    if (editing.value) {
      await useApi(`/admin/news/${editing.value.id}`, { method: 'PUT', body })
    }
    else {
      await useApi('/admin/news', { method: 'POST', body })
    }
    toast.add({ severity: 'success', summary: t('admin.news.saved'), life: 2500 })
    modalOpen.value = false
    await load()
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: e?.data?.message || t('admin.news.saveError'), life: 3500 })
  }
  finally {
    saving.value = false
  }
}

function remove(item: NewsItem) {
  confirm.require({
    message: t('admin.news.deleteConfirm'),
    header: t('admin.news.delete'),
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await useApi(`/admin/news/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.news.deleted'), life: 2500 })
        await load()
      }
      catch {
        toast.add({ severity: 'error', summary: t('admin.news.deleteError'), life: 3000 })
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <header class="workspace-head">
      <Button :label="t('admin.news.create')" icon="pi pi-plus" @click="openCreate" />
    </header>

    <section class="panel filters">
      <InputText v-model="search" :placeholder="t('common.search')" class="grow" @keyup.enter="load" />
      <Select
        v-model="statusFilter"
        :options="[{ label: t('common.all'), value: null }, ...statusOptions]"
        option-label="label"
        option-value="value"
        class="status"
        @change="load"
      />
      <Button icon="pi pi-search" :loading="loading" @click="load" />
    </section>

    <section class="panel">
      <DataTable :value="items" :loading="loading" striped-rows size="small">
        <Column :header="t('admin.news.colTitle')">
          <template #body="{ data }">
            <div class="title-cell">
              <strong>{{ data.title }}</strong>
              <small v-if="data.is_featured">★ {{ t('admin.news.featured') }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.news.colStatus')" style="width:110px">
          <template #body="{ data }">
            <Tag :severity="data.status === 'published' ? 'success' : 'secondary'" :value="data.status" />
          </template>
        </Column>
        <Column :header="t('admin.news.colPublished')" style="width:150px">
          <template #body="{ data }">{{ formatDate(data.published_at) }}</template>
        </Column>
        <Column :header="t('common.actions')" style="width:140px">
          <template #body="{ data }">
            <div class="row-actions">
              <Button icon="pi pi-pencil" text rounded @click="openEdit(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="remove(data)" />
            </div>
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('admin.news.empty')" />
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="editing ? t('admin.news.edit') : t('admin.news.create')"
      :style="{ width: 'min(720px, 94vw)' }"
    >
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('admin.news.fieldTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.news.fieldExcerpt') }}</span>
          <Textarea v-model="form.excerpt" rows="2" class="w-full" auto-resize />
        </label>
        <label class="field full">
          <span>{{ t('admin.news.fieldContent') }}</span>
          <CommonRichTextEditor v-model="form.content" height="320px" />
        </label>
        <label class="field">
          <span>{{ t('admin.news.fieldCover') }}</span>
          <InputText v-model="form.cover_image" class="w-full" placeholder="storage/..." />
        </label>
        <label class="field">
          <span>{{ t('admin.news.fieldStatus') }}</span>
          <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field check">
          <Checkbox v-model="form.is_featured" binary input-id="feat" />
          <label for="feat">{{ t('admin.news.featured') }}</label>
        </label>
        <label class="field check">
          <Checkbox v-model="form.notify_students" binary input-id="notify" />
          <label for="notify">{{ t('admin.news.notify') }}</label>
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="modalOpen = false" />
        <Button :label="t('common.save')" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.workspace-head {
  display: flex; align-items: flex-start; justify-content: flex-end; gap: 16px; flex-wrap: wrap;
  margin-bottom: 14px;
}

.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); }
.panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  padding: 12px; margin-bottom: 12px;
}
.filters { display: flex; gap: 8px; flex-wrap: wrap; }
.grow { flex: 1; min-width: 180px; }
.status { width: 160px; }
.title-cell { display: grid; gap: 2px; }
.title-cell small { color: var(--brand); font-weight: 700; }
.row-actions { display: flex; gap: 2px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.field.check { flex-direction: row; align-items: center; gap: 8px; }
.w-full { width: 100%; }
@media (max-width: 720px) { .form-grid { grid-template-columns: 1fr; } }
</style>
