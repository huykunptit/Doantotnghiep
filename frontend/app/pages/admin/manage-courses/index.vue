<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CategoryItem { id: number, name: string }
interface OptionItem { label: string, value: number | string | null }
interface AdminCourse {
  id: number
  title: string
  description?: string | null
  thumbnail?: string | null
  status: string
  course_mode?: string
  is_featured?: boolean
  price?: number
  lessons_count?: number
  enrollments_count?: number
  instructor?: { id?: number, name: string } | null
  category?: { id: number, name: string } | null
  program?: { id: number, code?: string, name: string } | null
  created_at?: string
}
interface Paginator<T> {
  data: T[]
  total: number
}

const { t, locale } = useI18n()
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
const instructorOptions = ref<OptionItem[]>([])
const programOptions = ref<OptionItem[]>([])

const filters = reactive({
  status: null as string | null,
  course_mode: null as string | null,
  pricing: null as string | null,
  category_id: null as number | null,
  instructor_id: null as number | null,
  program_id: null as number | null,
  is_featured: null as string | null,
  sort: 'newest' as string,
})

const modalOpen = ref(false)
const form = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: null as number | null,
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
function formatPrice(price = 0) {
  if (!price) return t('admin.manageCourses.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.statuses.published'), value: 'published' },
  { label: t('admin.manageCourses.statuses.pending_review'), value: 'pending_review' },
  { label: t('admin.manageCourses.statuses.draft'), value: 'draft' },
  { label: t('admin.manageCourses.statuses.rejected'), value: 'rejected' },
])

const modeOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.modes.core'), value: 'core' },
  { label: t('admin.manageCourses.modes.extension'), value: 'extension' },
])

const pricingOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.pricingPaid'), value: 'paid' },
  { label: t('admin.manageCourses.pricingFree'), value: 'free' },
])

const featuredOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.featuredOnly'), value: 'true' },
  { label: t('admin.manageCourses.notFeatured'), value: 'false' },
])

const sortOptions = computed(() => [
  { label: t('admin.manageCourses.sortNewest'), value: 'newest' },
  { label: t('admin.manageCourses.sortPriceAsc'), value: 'price_asc' },
  { label: t('admin.manageCourses.sortPriceDesc'), value: 'price_desc' },
  { label: t('admin.manageCourses.sortTitle'), value: 'title' },
  { label: t('admin.manageCourses.sortLearners'), value: 'learners' },
])

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.status) n++
  if (filters.course_mode) n++
  if (filters.pricing) n++
  if (filters.category_id) n++
  if (filters.instructor_id) n++
  if (filters.program_id) n++
  if (filters.is_featured) n++
  if (filters.sort && filters.sort !== 'newest') n++
  return n
})

function statusTone(status: string) {
  if (status === 'published') return 'tone-published'
  if (status === 'pending_review') return 'tone-pending'
  if (status === 'rejected') return 'tone-rejected'
  if (status === 'draft') return 'tone-draft'
  return 'tone-neutral'
}

function modeTone(mode?: string) {
  return mode === 'extension' ? 'tone-extension' : 'tone-core'
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

async function loadFilterOptions() {
  try {
    const [instructors, programs] = await Promise.all([
      useApi<{ data?: Array<{ id: number, name: string, staff_code?: string }> }>('/admin/instructors', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
      useApi<{ data?: Array<{ id: number, name: string, code?: string }> }>('/admin/academic/programs', { query: { per_page: 100 } }).catch(() => ({ data: [] })),
    ])
    instructorOptions.value = (instructors.data || []).map(u => ({
      label: u.staff_code ? `${u.staff_code} — ${u.name}` : u.name,
      value: u.id,
    }))
    programOptions.value = (programs.data || []).map(p => ({
      label: p.code ? `${p.code} — ${p.name}` : p.name,
      value: p.id,
    }))
  }
  catch {
    instructorOptions.value = []
    programOptions.value = []
  }
}

function toQuery() {
  return {
    page: page.value,
    per_page: perPage.value,
    search: tableSearch.value || undefined,
    status: filters.status || undefined,
    course_mode: filters.course_mode || undefined,
    pricing: filters.pricing || undefined,
    category_id: filters.category_id || undefined,
    instructor_id: filters.instructor_id || undefined,
    program_id: filters.program_id || undefined,
    is_featured: filters.is_featured || undefined,
    sort: filters.sort || undefined,
  }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AdminCourse>>('/admin/courses', { query: toQuery() })
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
  filters.course_mode = null
  filters.pricing = null
  filters.category_id = null
  filters.instructor_id = null
  filters.program_id = null
  filters.is_featured = null
  filters.sort = 'newest'
  tableSearch.value = ''
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
  await Promise.all([loadCategories(), loadFilterOptions(), load()])
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
          <label class="field">
            <span>{{ t('admin.manageCourses.mode') }}</span>
            <Select
              v-model="filters.course_mode"
              :options="modeOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.pricing') }}</span>
            <Select
              v-model="filters.pricing"
              :options="pricingOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.category') }}</span>
            <Select
              v-model="filters.category_id"
              :options="categories"
              option-label="name"
              option-value="id"
              show-clear
              filter
              class="w-full"
              :placeholder="t('common.all')"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.instructor') }}</span>
            <Select
              v-model="filters.instructor_id"
              :options="instructorOptions"
              option-label="label"
              option-value="value"
              show-clear
              filter
              class="w-full"
              :placeholder="t('common.all')"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.program') }}</span>
            <Select
              v-model="filters.program_id"
              :options="programOptions"
              option-label="label"
              option-value="value"
              show-clear
              filter
              class="w-full"
              :placeholder="t('common.all')"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.featured') }}</span>
            <Select
              v-model="filters.is_featured"
              :options="featuredOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.sort') }}</span>
            <Select
              v-model="filters.sort"
              :options="sortOptions"
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
        <Column :header="t('admin.manageCourses.course')" style="min-width:240px">
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
        <Column :header="t('admin.manageCourses.mode')" style="width:120px">
          <template #body="{ data }">
            <span class="pill" :class="modeTone(data.course_mode)">
              {{ t(`admin.manageCourses.modes.${data.course_mode || 'extension'}`, data.course_mode || '—') }}
            </span>
          </template>
        </Column>
        <Column :header="t('admin.manageCourses.price')" style="min-width:120px">
          <template #body="{ data }">
            <span class="price-cell" :class="{ paid: (data.price || 0) > 0 }">
              {{ formatPrice(data.price || 0) }}
            </span>
          </template>
        </Column>
        <Column :header="t('admin.manageCourses.instructor')" style="min-width:140px">
          <template #body="{ data }">{{ data.instructor?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.manageCourses.lessons')" style="width:90px">
          <template #body="{ data }">{{ data.lessons_count || 0 }}</template>
        </Column>
        <Column :header="t('admin.manageCourses.learners')" style="width:90px">
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
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
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

.price-cell { font-weight: 650; color: var(--text-muted); }
.price-cell.paid { color: var(--text); font-weight: 750; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-published { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef3c7; color: #b45309; }
.tone-rejected { background: #fee2e2; color: #b91c1c; }
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }
.tone-core { background: #e0f2fe; color: #0369a1; }
.tone-extension { background: #fce7f3; color: #be185d; }

.featured-btn { color: var(--text-muted) !important; }
.featured-btn.on { color: #d97706 !important; }

@media (max-width: 640px) {
  .form { grid-template-columns: 1fr; }
}
</style>
