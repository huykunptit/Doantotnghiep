<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: 'manage_courses',
})

interface MonthPoint { month: string, label: string, value: number }
interface MyCourse {
  id: number
  title: string
  price?: number
  enrollments_count?: number
  course_mode?: string
}
interface Paginator<T> { data: T[], total: number }
interface InstructorStats {
  total_revenue?: number
  revenue_by_month?: MonthPoint[]
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(true)
const stats = ref<InstructorStats>({})
const courses = ref<MyCourse[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(12)
const search = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatVnd = (n = 0) => new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n)

const { colors: chartColors } = useChartColors()

const revenueChart = computed(() => {
  const c = chartColors()
  const points = stats.value.revenue_by_month || []
  return {
    data: {
      labels: points.map(p => p.label),
      datasets: [{
        data: points.map(p => p.value),
        borderColor: c.brand,
        backgroundColor: c.brandSoft,
        fill: true,
        tension: .35,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: c.text }, grid: { color: c.grid } },
        y: { ticks: { color: c.text }, grid: { color: c.grid } },
      },
    },
  }
})

function unwrapPage<T>(res: any): { rows: T[], total: number } {
  const payload = res?.data && !Array.isArray(res.data) && Array.isArray(res.data.data) ? res.data : res
  const list = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  const count = Number(payload?.total ?? list.length) || 0
  return { rows: list, total: count }
}

async function load() {
  loading.value = true
  try {
    const [s, c] = await Promise.all([
      useApi<InstructorStats>('/instructor/stats'),
      useApi<Paginator<MyCourse>>('/my-courses', {
        query: {
          page: page.value,
          per_page: perPage.value,
          search: search.value || undefined,
          sort: 'learners',
        },
      }),
    ])
    stats.value = s
    const pageData = unwrapPage<MyCourse>(c)
    courses.value = pageData.rows
    total.value = pageData.total
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.revenue.loadError'), detail: error?.data?.message, life: 3500 })
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
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="kpi">
      <span>{{ t('instructor.revenue.total') }}</span>
      <strong>{{ formatVnd(stats.total_revenue || 0) }}</strong>
    </section>

    <section class="panel">
      <header class="panel-head"><strong>{{ t('instructor.revenue.trend') }}</strong></header>
      <ChartsUiChart type="line" :data="revenueChart.data" :options="revenueChart.options" height="240px" />
    </section>

    <section class="table-panel">
      <header class="panel-head row">
        <strong>{{ t('instructor.revenue.byCourse') }}</strong>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" :placeholder="t('instructor.courses.searchPh')" @input="onSearch" />
        </IconField>
      </header>
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
        <Column :header="t('instructor.revenue.courseTitle')">
          <template #body="{ data }">
            <NuxtLink :to="`/instructor/courses/${data.id}/revenue`" class="link">{{ data.title }}</NuxtLink>
          </template>
        </Column>
        <Column :header="t('instructor.revenue.price')">
          <template #body="{ data }">{{ formatVnd(data.price || 0) }}</template>
        </Column>
        <Column :header="t('instructor.revenue.enrollments')">
          <template #body="{ data }">{{ data.enrollments_count || 0 }}</template>
        </Column>
        <Column style="width:8rem">
          <template #body="{ data }">
            <Button :label="t('instructor.revenue.viewDetail')" size="small" text @click="navigateTo(`/instructor/courses/${data.id}/revenue`)" />
          </template>
        </Column>
        <template #empty><CommonEmptyState :description="t('common.noData')" /></template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }

.workspace-head { display: flex; justify-content: flex-end; gap: 12px; }
.kpi, .panel, .table-panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.kpi { display: flex; flex-direction: column; gap: 4px; max-width: 320px; }
.kpi span { color: var(--text-muted); font-size: .78rem; font-weight: 650; }
.kpi strong { font-family: var(--font-display); font-size: 1.6rem; }
.panel-head { margin-bottom: 10px; }
.panel-head.row { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; }
.link { font-weight: 700; color: var(--text); text-decoration: none; }
.link:hover { color: var(--brand); }
</style>
