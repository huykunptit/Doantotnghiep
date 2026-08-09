<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CategoryItem { id: number, name: string }
interface AdminCourse {
  id: number
  title: string
  description?: string | null
  thumbnail?: string | null
  status: string
  is_featured?: boolean
  price?: number
  lessons_count?: number
  enrollments_count?: number
  instructor?: { name: string } | null
  category?: { id: number, name: string } | null
  created_at?: string
}
interface Paginator<T> {
  data: T[]
  total: number
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const featuringId = ref<number | null>(null)
const rows = ref<AdminCourse[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')
const categories = ref<CategoryItem[]>([])

const filters = reactive({
  status: null as string | null,
})

const modalOpen = ref(false)
const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: null as number | null,
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.statuses.published'), value: 'published' },
  { label: t('admin.manageCourses.statuses.pending_review'), value: 'pending_review' },
  { label: t('admin.manageCourses.statuses.draft'), value: 'draft' },
  { label: t('admin.manageCourses.statuses.rejected'), value: 'rejected' },
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))

function statusTone(status: string) {
  if (status === 'published') return 'tone-published'
  if (status === 'pending_review') return 'tone-pending'
  if (status === 'rejected') return 'tone-rejected'
  if (status === 'draft') return 'tone-draft'
  return 'tone-neutral'
}

async function loadCategories() {
  try {
    const res = await useApi<CategoryItem[] | { data: CategoryItem[] }>('/admin/categories')
    categories.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    categories.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AdminCourse>>('/admin/courses', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: tableSearch.value || undefined,
        status: filters.status || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.manageCourses.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function onTableSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
}

function applyFilters() {
  page.value = 1
  load()
}

function resetFilters() {
  filters.status = null
  page.value = 1
  load()
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

function openCreate() {
  Object.assign(form, { title: '', description: '', price: 0, category_id: null })
  modalOpen.value = true
}

async function createCourse() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.manageCourses.titleRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    await useApi('/courses', {
      method: 'POST',
      body: {
        title: form.title.trim(),
        description: form.description.trim() || null,
        price: Number(form.price) || 0,
        category_id: form.category_id || null,
      },
    })
    toast.add({ severity: 'success', summary: t('admin.manageCourses.created'), life: 2200 })
    modalOpen.value = false
    filters.status = 'draft'
    page.value = 1
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.manageCourses.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

async function toggleFeatured(course: AdminCourse) {
  if (featuringId.value) return
  const next = !course.is_featured
  featuringId.value = course.id
  const prev = Boolean(course.is_featured)
  course.is_featured = next
  try {
    const res = await useApi<{ course: AdminCourse }>(`/courses/${course.id}`, {
      method: 'PUT',
      body: { is_featured: next },
    })
    course.is_featured = Boolean(res.course?.is_featured ?? next)
    toast.add({
      severity: 'success',
      summary: course.is_featured
        ? t('admin.manageCourses.featuredOn')
        : t('admin.manageCourses.featuredOff'),
      life: 2000,
    })
  }
  catch (error: any) {
    course.is_featured = prev
    toast.add({
      severity: 'error',
      summary: t('admin.manageCourses.featuredError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    featuringId.value = null
  }
}

function askDelete(course: AdminCourse) {
  confirm.require({
    message: t('admin.manageCourses.deleteConfirm', { title: course.title }),
    header: t('admin.manageCourses.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/courses/${course.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.manageCourses.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.manageCourses.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

onMounted(async () => {
  await Promise.all([loadCategories(), load()])
})
</script>

<template>
  <div class="page manage-page">

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.manageCourses.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.manageCourses.status') }}</span>
            <Select
              v-model="filters.status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.manageCourses.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.manageCourses.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.manageCourses.searchPh')" @input="onTableSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button :label="t('admin.manageCourses.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[10, 15, 25, 50]"
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column :header="t('admin.manageCourses.course')" style="min-width:220px">
          <template #body="{ data }">
            <div class="course-cell">
              <img v-if="data.thumbnail" :src="data.thumbnail" :alt="data.title" class="thumb">
              <div v-else class="thumb placeholder"><i class="pi pi-book" /></div>
              <div>
                <strong>{{ data.title }}</strong>
                <small>{{ data.category?.name || '—' }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.manageCourses.instructor')" style="min-width:140px">
          <template #body="{ data }">{{ data.instructor?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.manageCourses.lessons')" style="width:100px">
          <template #body="{ data }">{{ data.lessons_count || 0 }}</template>
        </Column>
        <Column :header="t('admin.manageCourses.learners')" style="width:100px">
          <template #body="{ data }">{{ data.enrollments_count || 0 }}</template>
        </Column>
        <Column :header="t('admin.manageCourses.status')" style="width:130px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">
              {{ t(`admin.manageCourses.statuses.${data.status}`, data.status) }}
            </span>
          </template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:14rem">
          <template #body="{ data }">
            <a :href="`/courses/${data.id}`" target="_blank" rel="noopener" :title="t('admin.manageCourses.preview')">
              <Button icon="pi pi-eye" text rounded severity="secondary" :aria-label="t('admin.manageCourses.preview')" />
            </a>
            <Button
              :icon="data.is_featured ? 'pi pi-star-fill' : 'pi pi-star'"
              text
              rounded
              class="featured-btn"
              :class="{ on: data.is_featured }"
              :loading="featuringId === data.id"
              :aria-label="t('admin.manageCourses.toggleFeatured')"
              :aria-pressed="Boolean(data.is_featured)"
              :title="data.is_featured ? t('admin.manageCourses.featuredOnHint') : t('admin.manageCourses.featuredOffHint')"
              @click="toggleFeatured(data)"
            />
            <NuxtLink :to="`/admin/manage-courses/${data.id}`">
              <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="t('admin.manageCourses.builder')" />
            </NuxtLink>
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="t('admin.manageCourses.add')"
      :style="{ width: 'min(560px, 96vw)' }"
    >
      <div class="form">
        <label class="field full">
          <span>{{ t('admin.manageCourses.courseTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.manageCourses.description') }}</span>
          <CommonRichTextEditor v-model="form.description" height="200px" />
        </label>
        <label class="field">
          <span>{{ t('admin.manageCourses.price') }}</span>
          <InputNumber v-model="form.price" :min="0" class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.manageCourses.category') }}</span>
          <Select
            v-model="form.category_id"
            :options="categories"
            option-label="name"
            option-value="id"
            show-clear
            filter
            class="w-full"
          />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('admin.manageCourses.add')" icon="pi pi-check" :loading="saving" @click="createCourse" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.manage-page { gap: 14px; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.filter-actions { display: flex; gap: 8px; margin-top: 10px; }
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

.course-cell { display: flex; align-items: center; gap: 10px; }
.thumb { width: 64px; height: 40px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
.thumb.placeholder {
  display: grid; place-items: center; background: var(--surface-hover, #f1f5f9); color: var(--text-muted);
}
.course-cell small { display: block; color: var(--text-muted); margin-top: 2px; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-published { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef3c7; color: #b45309; }
.tone-rejected { background: #fee2e2; color: #b91c1c; }
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.featured-btn { color: var(--text-muted) !important; }
.featured-btn.on { color: #d97706 !important; }

@media (max-width: 640px) {
  .form { grid-template-columns: 1fr; }
}
</style>
