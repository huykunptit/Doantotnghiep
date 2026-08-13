<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_courses', 'manage_grades'],
})

interface StudentRow {
  id: number
  enrolled_at?: string | null
  last_watched_at?: string | null
  progress_percent?: number
  total_lessons?: number
  completed_lessons?: number
  user?: { id: number, name: string, email?: string, avatar?: string | null } | null
}

const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const courseId = computed(() => Number(route.params.id))

const loading = ref(true)
const courseTitle = ref('')
const rows = ref<StudentRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const search = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const fmtDate = (value?: string | null) => {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, { dateStyle: 'medium' }).format(new Date(value))
}

async function load() {
  if (!courseId.value) return
  loading.value = true
  try {
    const res = await useApi<any>(`/instructor/courses/${courseId.value}/students`, {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
      },
    })
    courseTitle.value = res.course?.title || `#${courseId.value}`
    rows.value = res.data || []
    total.value = res.total || 0
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

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <Button :label="t('instructor.builder.back')" icon="pi pi-arrow-left" text size="small" class="back" @click="navigateTo('/instructor/students')" />
        <h1>{{ courseTitle || t('instructor.menu.courseStudents') }}</h1>
        <p>{{ t('instructor.students.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" :placeholder="t('instructor.students.searchPh')" @input="onSearch" />
        </IconField>
        <strong>{{ total }}</strong>
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
        :rows-per-page-options="[10, 15, 25]"
        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        :current-page-report-template="t('admin.users.pageReport')"
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column :header="t('instructor.students.name')">
          <template #body="{ data }">
            <div class="user-cell">
              <Avatar v-if="data.user?.avatar" :image="data.user.avatar" shape="circle" />
              <Avatar v-else :label="(data.user?.name || '?').slice(0, 1)" shape="circle" />
              <div>
                <strong>{{ data.user?.name || '—' }}</strong>
                <small>{{ data.user?.email }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('instructor.students.progress')">
          <template #body="{ data }">
            <div class="progress">
              <ProgressBar :value="data.progress_percent || 0" :show-value="false" style="height:8px" />
              <span>{{ data.progress_percent || 0 }}% · {{ t('instructor.students.lessonsDone', { done: data.completed_lessons || 0, total: data.total_lessons || 0 }) }}</span>
            </div>
          </template>
        </Column>
        <Column :header="t('instructor.students.enrolledAt')">
          <template #body="{ data }">{{ fmtDate(data.enrolled_at) }}</template>
        </Column>
        <Column :header="t('instructor.students.lastWatched')">
          <template #body="{ data }">{{ fmtDate(data.last_watched_at) }}</template>
        </Column>
        <template #empty><CommonEmptyState :description="t('instructor.students.empty')" /></template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.back { margin-left: -8px; margin-bottom: 4px; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.35rem, 2vw, 1.7rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.table-panel {
  border: 1px solid var(--border); border-radius: 16px; padding: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.table-toolbar { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; align-items: center; }
.user-cell { display: flex; align-items: center; gap: 10px; }
.user-cell small { display: block; color: var(--text-muted); font-size: .78rem; }
.progress { display: grid; gap: 4px; min-width: 140px; }
.progress span { color: var(--text-muted); font-size: .78rem; font-weight: 500; }

</style>
