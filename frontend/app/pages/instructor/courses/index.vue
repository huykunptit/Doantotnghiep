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

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatVnd = (n = 0) => new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n)

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

async function loadCategories() {
  try {
    const res = await useApi<CategoryItem[] | { data: CategoryItem[] }>('/categories')
    categories.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    categories.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<MyCourse>>('/my-courses', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: tableSearch.value || undefined,
      },
    })
    let data = res.data || []
    if (tableSearch.value.trim()) {
      const q = tableSearch.value.trim().toLowerCase()
      data = data.filter(c => c.title.toLowerCase().includes(q))
    }
    rows.value = data
    total.value = res.total || data.length
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
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('instructor.courses.searchPh')" @input="onSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ total }}</strong>
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
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[8, 12, 24]"
        @page="onPage"
      >
        <Column :header="t('instructor.courses.fieldTitle')" style="min-width:16rem">
          <template #body="{ data }">
            <div class="course-cell">
              <img v-if="data.thumbnail" :src="data.thumbnail" alt="">
              <div>
                <NuxtLink :to="`/instructor/courses/${data.id}/edit`" class="title-link">{{ data.title }}</NuxtLink>
                <small v-if="data.category">{{ data.category.name }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('instructor.courses.status')">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ statusLabel(data.status) }}</span>
          </template>
        </Column>
        <Column :header="t('instructor.courses.price')">
          <template #body="{ data }">{{ formatVnd(data.price || 0) }}</template>
        </Column>
        <Column :header="t('instructor.courses.lessons')">
          <template #body="{ data }">{{ data.lessons_count || 0 }}</template>
        </Column>
        <Column :header="t('instructor.courses.learners')">
          <template #body="{ data }">{{ data.enrollments_count || 0 }}</template>
        </Column>
        <Column style="width:14rem">
          <template #body="{ data }">
            <a :href="`/courses/${data.id}`" target="_blank" rel="noopener">
              <Button :label="t('instructor.courses.preview')" icon="pi pi-eye" size="small" text severity="secondary" />
            </a>
            <Button :label="t('instructor.courses.edit')" size="small" text @click="navigateTo(`/instructor/courses/${data.id}/edit`)" />
            <Button icon="pi pi-users" text rounded severity="secondary" @click="navigateTo(`/instructor/courses/${data.id}/students`)" />
            <Button icon="pi pi-wallet" text rounded severity="secondary" @click="navigateTo(`/instructor/courses/${data.id}/revenue`)" />
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
.table-toolbar { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
.toolbar-actions { display: flex; align-items: center; gap: 8px; }
.course-cell { display: flex; align-items: center; gap: 10px; }
.course-cell img { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; }
.course-cell small { display: block; color: var(--text-muted); font-size: .78rem; }
.title-link { font-weight: 700; color: var(--text); }
.title-link:hover { color: var(--brand); }
.pill {
  display: inline-flex; padding: 3px 9px; border-radius: 999px; font-size: .74rem; font-weight: 700;
}
.tone-published { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef3c7; color: #a16207; }
.tone-rejected { background: #fee2e2; color: #b91c1c; }
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.form { display: grid; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }
</style>
