<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: 'manage_courses',
})

interface CategoryItem { id: number, name: string }
interface MyCourse {
  id: number
  title: string
  thumbnail?: string | null
  status: string
  course_mode?: string
  price?: number
  lessons_count?: number
  enrollments_count?: number
  category?: { id: number, name: string } | null
}
interface Paginator<T> { data: T[], total: number }

const { t, locale } = useI18n()
const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const rows = ref<MyCourse[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(12)
const tableSearch = ref('')
const categories = ref<CategoryItem[]>([])
const modalOpen = ref(false)
const form = reactive({ title: '', description: '', price: 0, category_id: null as number | null })
let searchTimer: ReturnType<typeof setTimeout> | null = null

const filters = reactive({
  status: null as string | null,
  course_mode: null as string | null,
  pricing: null as string | null,
  category_id: null as number | null,
  sort: 'newest' as string,
})

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatVnd = (n = 0) => {
  if (!n) return t('instructor.courses.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n)
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

function statusLabel(status: string) {
  const key = `admin.manageCourses.statuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

function modeTone(mode?: string) {
  return mode === 'extension' ? 'tone-extension' : 'tone-core'
}

async function loadCategories() {
  try {
    const res = await useApi<CategoryItem[] | { data: CategoryItem[] }>('/categories')
    categories.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    categories.value = []
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
    sort: filters.sort || undefined,
  }
}

function unwrapPage<T>(res: any): { rows: T[], total: number } {
  const payload = res?.data && !Array.isArray(res.data) && Array.isArray(res.data.data) ? res.data : res
  const list = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  const count = Number(payload?.total ?? list.length) || 0
  return { rows: list, total: count }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<MyCourse>>('/my-courses', { query: toQuery() })
    const pageData = unwrapPage<MyCourse>(res)
    rows.value = pageData.rows
    total.value = pageData.total
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.courses.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 300)
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
  form.title = ''
  form.description = ''
  form.price = 0
  form.category_id = null
  modalOpen.value = true
}

async function createCourse() {
  if (!form.title.trim()) {
    toast.add({ severity: 'warn', summary: t('instructor.courses.titleRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const res = await useApi<{ course: MyCourse }>('/courses', {
      method: 'POST',
      body: {
        title: form.title.trim(),
        description: form.description || null,
        price: form.price || 0,
        category_id: form.category_id,
      },
    })
    toast.add({ severity: 'success', summary: t('instructor.courses.created'), life: 2200 })
    modalOpen.value = false
    await navigateTo(`/instructor/courses/${res.course.id}/edit`)
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('instructor.courses.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

onMounted(async () => {
  await loadCategories()
  await load()
})
</script>

<template>
  <div class="page">
    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('instructor.courses.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('instructor.courses.status') }}</span>
            <Select v-model="filters.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.mode') }}</span>
            <Select v-model="filters.course_mode" :options="modeOptions" option-label="label" option-value="value" class="w-full" />
          </label>
          <label class="field">
            <span>{{ t('admin.manageCourses.pricing') }}</span>
            <Select v-model="filters.pricing" :options="pricingOptions" option-label="label" option-value="value" class="w-full" />
          </label>
          <label class="field">
            <span>{{ t('instructor.courses.fieldCategory') }}</span>
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
            <span>{{ t('admin.manageCourses.sort') }}</span>
            <Select v-model="filters.sort" :options="sortOptions" option-label="label" option-value="value" class="w-full" />
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
          <InputText v-model="tableSearch" :placeholder="t('instructor.courses.searchPh')" @input="onSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button :label="t('instructor.courses.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :first="(page - 1) * perPage"
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[8, 12, 24, 48]"
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        :current-page-report-template="t('admin.users.pageReport')"
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column :header="t('instructor.courses.fieldTitle')" style="min-width:16rem">
          <template #body="{ data }">
            <div class="course-cell">
              <img v-if="data.thumbnail" :src="data.thumbnail" alt="">
              <div v-else class="thumb-ph"><i class="pi pi-book" /></div>
              <div>
                <NuxtLink :to="`/instructor/courses/${data.id}/edit`" class="title-link">{{ data.title }}</NuxtLink>
                <small v-if="data.category">{{ data.category.name }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.manageCourses.mode')" style="width:8rem">
          <template #body="{ data }">
            <span class="pill" :class="modeTone(data.course_mode)">
              {{ t(`admin.manageCourses.modes.${data.course_mode || 'extension'}`, data.course_mode || '—') }}
            </span>
          </template>
        </Column>
        <Column :header="t('instructor.courses.status')" style="width:8rem">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
          </template>
        </Column>
        <Column :header="t('instructor.courses.price')" style="width:8rem">
          <template #body="{ data }">
            <span :class="{ paid: (data.price || 0) > 0 }">{{ formatVnd(data.price || 0) }}</span>
          </template>
        </Column>
        <Column :header="t('instructor.courses.lessons')" style="width:6rem">
          <template #body="{ data }">{{ data.lessons_count || 0 }}</template>
        </Column>
        <Column :header="t('instructor.courses.learners')" style="width:6rem">
          <template #body="{ data }">{{ data.enrollments_count || 0 }}</template>
        </Column>
        <Column style="width:12rem">
          <template #body="{ data }">
            <div class="row-actions">
              <Button icon="pi pi-pencil" text rounded severity="secondary" :title="t('instructor.courses.edit')" @click="navigateTo(`/instructor/courses/${data.id}/edit`)" />
              <Button icon="pi pi-users" text rounded severity="secondary" :title="t('instructor.courses.students')" @click="navigateTo(`/instructor/courses/${data.id}/students`)" />
              <Button icon="pi pi-wallet" text rounded severity="secondary" :title="t('instructor.courses.revenue')" @click="navigateTo(`/instructor/courses/${data.id}/revenue`)" />
              <a :href="`/courses/${data.id}`" target="_blank" rel="noopener">
                <Button icon="pi pi-eye" text rounded severity="secondary" :title="t('instructor.courses.preview')" />
              </a>
            </div>
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>

    <Dialog v-model:visible="modalOpen" modal :header="t('instructor.courses.createTitle')" :style="{ width: 'min(520px, 96vw)' }">
      <div class="form">
        <label class="field">
          <span>{{ t('instructor.courses.fieldTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('instructor.courses.fieldDesc') }}</span>
          <CommonRichTextEditor v-model="form.description" height="180px" />
        </label>
        <label class="field">
          <span>{{ t('instructor.courses.fieldPrice') }}</span>
          <InputNumber v-model="form.price" class="w-full" :min="0" />
        </label>
        <label class="field">
          <span>{{ t('instructor.courses.fieldCategory') }}</span>
          <Select
            v-model="form.category_id"
            :options="categories"
            option-label="name"
            option-value="id"
            show-clear
            class="w-full"
          />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="createCourse" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px; padding: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.filter-bar { margin-bottom: 12px; }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
.filter-actions { display: flex; gap: 8px; margin-top: 10px; }
.table-toolbar { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
.toolbar-actions { display: flex; align-items: center; gap: 8px; }
.course-cell { display: flex; align-items: center; gap: 10px; }
.course-cell img, .thumb-ph { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
.thumb-ph { display: grid; place-items: center; background: var(--surface-hover, #f1f5f9); color: var(--text-muted); }
.course-cell small { display: block; color: var(--text-muted); font-size: .78rem; }
.title-link { font-weight: 700; color: var(--text); text-decoration: none; }
.title-link:hover { color: var(--brand); }
.paid { font-weight: 750; }
.row-actions { display: flex; align-items: center; gap: 2px; }
.pill {
  display: inline-flex; padding: 3px 9px; border-radius: 999px; font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.tone-published { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef3c7; color: #a16207; }
.tone-rejected { background: #fee2e2; color: #b91c1c; }
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }
.tone-core { background: #e0f2fe; color: #0369a1; }
.tone-extension { background: #fce7f3; color: #be185d; }

.form { display: grid; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }
</style>
