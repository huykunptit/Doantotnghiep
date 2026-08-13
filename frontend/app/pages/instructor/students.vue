<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_courses', 'manage_grades'],
})

interface MyCourse {
  id: number
  title: string
  thumbnail?: string | null
  enrollments_count?: number
  status: string
  course_mode?: string
  category?: { name?: string } | null
}
interface Paginator<T> { data: T[], total: number }

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const courses = ref<MyCourse[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(12)
const search = ref('')
const status = ref<string | null>(null)
const courseMode = ref<string | null>(null)
let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.statuses.published'), value: 'published' },
  { label: t('admin.manageCourses.statuses.draft'), value: 'draft' },
  { label: t('admin.manageCourses.statuses.pending_review'), value: 'pending_review' },
])

const modeOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.manageCourses.modes.core'), value: 'core' },
  { label: t('admin.manageCourses.modes.extension'), value: 'extension' },
])

function unwrapPage<T>(res: any): { rows: T[], total: number } {
  const payload = res?.data && !Array.isArray(res.data) && Array.isArray(res.data.data) ? res.data : res
  const list = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  const count = Number(payload?.total ?? list.length) || 0
  return { rows: list, total: count }
}

function statusLabel(status: string) {
  const key = `admin.manageCourses.statuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<MyCourse>>('/my-courses', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        status: status.value || undefined,
        course_mode: courseMode.value || undefined,
        sort: 'learners',
      },
    })
    const pageData = unwrapPage<MyCourse>(res)
    courses.value = pageData.rows
    total.value = pageData.total
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.students.loadError'), detail: error?.data?.message, life: 3500 })
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
  search.value = ''
  status.value = null
  courseMode.value = null
  page.value = 1
  load()
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <section class="panel">
      <div class="filters">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" :placeholder="t('instructor.students.searchPh')" @input="onSearch" />
        </IconField>
        <Select v-model="status" :options="statusOptions" option-label="label" option-value="value" class="flt" />
        <Select v-model="courseMode" :options="modeOptions" option-label="label" option-value="value" class="flt" />
        <Button :label="t('admin.manageCourses.apply')" size="small" icon="pi pi-filter" @click="applyFilters" />
        <Button :label="t('admin.manageCourses.reset')" size="small" text severity="secondary" @click="resetFilters" />
        <strong class="count">{{ t('admin.users.result', { n: total }) }}</strong>
      </div>

      <DataTable
        :value="courses"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :first="(page - 1) * perPage"
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[8, 12, 24]"
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
                <strong>{{ data.title }}</strong>
                <small v-if="data.category?.name">{{ data.category.name }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.manageCourses.mode')" style="width:8rem">
          <template #body="{ data }">
            {{ t(`admin.manageCourses.modes.${data.course_mode || 'extension'}`, data.course_mode || '—') }}
          </template>
        </Column>
        <Column :header="t('instructor.courses.status')" style="width:8rem">
          <template #body="{ data }">{{ statusLabel(data.status) }}</template>
        </Column>
        <Column :header="t('instructor.courses.learners')" style="width:7rem">
          <template #body="{ data }">{{ data.enrollments_count || 0 }}</template>
        </Column>
        <Column style="width:10rem">
          <template #body="{ data }">
            <Button
              :label="t('instructor.students.view')"
              size="small"
              text
              icon="pi pi-users"
              @click="navigateTo(`/instructor/courses/${data.id}/students`)"
            />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.panel {
  border: 1px solid var(--border); border-radius: 16px; padding: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
.flt { min-width: 140px; }
.count { margin-left: auto; color: var(--text-muted); }
.course-cell { display: flex; align-items: center; gap: 10px; }
.course-cell img, .thumb-ph { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
.thumb-ph { display: grid; place-items: center; background: var(--surface-hover); color: var(--text-muted); }
.course-cell small { display: block; color: var(--text-muted); font-size: .78rem; }
</style>
