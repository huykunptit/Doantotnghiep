<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { formatAcademicRange, parseAcademicDate, toYmd as toAcademicYmd } from '~/utils/academic-date'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface AcademicYear {
  id: number
  name: string
  start_date: string
  end_date: string
  is_current: boolean
  status: string
}

interface Term {
  id: number
  academic_year_id: number
  name: string
  code: string
  start_date: string
  end_date: string
  enrollment_start_at?: string | null
  enrollment_end_at?: string | null
  exam_start_at?: string | null
  exam_end_at?: string | null
  is_current: boolean
  status: string
}

interface Paginator<T> {
  data: T[]
  total: number
}

const { t, te, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const institutionId = ref<number | null>(null)
const academicYears = ref<AcademicYear[]>([])
const expandedYearIds = ref<Set<number>>(new Set())
const termsMap = ref<Record<number, Term[]>>({})
const loadingTerms = ref<Set<number>>(new Set())

const yearModalOpen = ref(false)
const yearEditing = ref<AcademicYear | null>(null)
const yearForm = reactive({
  name: '',
  start_date: null as Date | null,
  end_date: null as Date | null,
  status: 'active',
  is_current: false,
})

const termModalOpen = ref(false)
const termEditing = ref<Term | null>(null)
const activeYearId = ref<number | null>(null)
const termForm = reactive({
  name: '',
  code: '',
  start_date: null as Date | null,
  end_date: null as Date | null,
  enrollment_start_at: null as Date | null,
  enrollment_end_at: null as Date | null,
  exam_start_at: null as Date | null,
  exam_end_at: null as Date | null,
  is_current: false,
  status: 'upcoming',
})

const yearStatusOptions = computed(() => [
  { label: t('admin.calendar.yearStatus.active'), value: 'active' },
  { label: t('admin.calendar.yearStatus.inactive'), value: 'inactive' },
])

const termStatusOptions = computed(() => [
  { label: t('admin.calendar.termStatus.upcoming'), value: 'upcoming' },
  { label: t('admin.calendar.termStatus.ongoing'), value: 'ongoing' },
  { label: t('admin.calendar.termStatus.active'), value: 'active' },
  { label: t('admin.calendar.termStatus.planned'), value: 'planned' },
  { label: t('admin.calendar.termStatus.completed'), value: 'completed' },
])

const totalTermsCount = computed(() =>
  Object.values(termsMap.value).reduce((sum, list) => sum + list.length, 0),
)

const currentYearName = computed(() =>
  academicYears.value.find(y => y.is_current)?.name || '—',
)

function toYmd(d: Date | null): string | null {
  return toAcademicYmd(d)
}

function parseYmd(s: string | Date | null | undefined): Date | null {
  return parseAcademicDate(s)
}

function fmtRange(from?: string | null, to?: string | null) {
  return formatAcademicRange(from, to, locale.value)
}

function termStatusKey(status?: string | null) {
  return String(status || '').trim().toLowerCase()
}

function termStatusLabel(status?: string | null) {
  const key = termStatusKey(status)
  if (!key) return '—'
  const i18nKey = `admin.calendar.termStatus.${key}`
  if (te(i18nKey)) return t(i18nKey)
  const fallback: Record<string, string> = {
    upcoming: locale.value === 'en' ? 'Upcoming' : 'Sắp diễn ra',
    ongoing: locale.value === 'en' ? 'Ongoing' : 'Đang diễn ra',
    active: locale.value === 'en' ? 'Active' : 'Đang diễn ra',
    planned: locale.value === 'en' ? 'Planned' : 'Đã lên kế hoạch',
    completed: locale.value === 'en' ? 'Completed' : 'Đã kết thúc',
  }
  return fallback[key] || key
}

function termStatusTone(status: string) {
  const key = termStatusKey(status)
  if (key === 'ongoing' || key === 'active') return 'tone-active'
  if (key === 'upcoming' || key === 'planned') return 'tone-deferred'
  if (key === 'completed') return 'tone-neutral'
  return 'tone-neutral'
}

async function loadInstitution() {
  try {
    const res = await useApi<Paginator<{ id: number }>>('/admin/academic/institutions', {
      query: { per_page: 1 },
    })
    institutionId.value = res.data?.[0]?.id ?? 1
  }
  catch {
    institutionId.value = 1
  }
}

async function loadYears() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AcademicYear>>('/admin/academic/academic-years', {
      query: { per_page: 50 },
    })
    academicYears.value = res.data || []
    const current = academicYears.value.find(y => y.is_current)
    if (current && !expandedYearIds.value.has(current.id)) {
      expandedYearIds.value.add(current.id)
      await loadTerms(current.id)
    }
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.calendar.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

async function loadTerms(yearId: number) {
  if (loadingTerms.value.has(yearId)) return
  loadingTerms.value.add(yearId)
  try {
    const res = await useApi<Paginator<Term>>('/admin/academic/terms', {
      query: { academic_year_id: yearId, per_page: 20 },
    })
    termsMap.value[yearId] = res.data || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.calendar.termsLoadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loadingTerms.value.delete(yearId)
  }
}

async function toggleYear(yearId: number) {
  if (expandedYearIds.value.has(yearId)) {
    expandedYearIds.value.delete(yearId)
  }
  else {
    expandedYearIds.value.add(yearId)
    if (!termsMap.value[yearId]) await loadTerms(yearId)
  }
}

function resetYearForm() {
  Object.assign(yearForm, {
    name: '',
    start_date: null,
    end_date: null,
    status: 'active',
    is_current: false,
  })
}

function openCreateYear() {
  yearEditing.value = null
  resetYearForm()
  yearModalOpen.value = true
}

function openEditYear(year: AcademicYear) {
  yearEditing.value = year
  Object.assign(yearForm, {
    name: year.name,
    start_date: parseYmd(year.start_date),
    end_date: parseYmd(year.end_date),
    status: year.status || 'active',
    is_current: year.is_current,
  })
  yearModalOpen.value = true
}

async function saveYear() {
  if (!yearForm.name.trim() || !yearForm.start_date || !yearForm.end_date) {
    toast.add({ severity: 'warn', summary: t('admin.calendar.yearRequired'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const body = {
      name: yearForm.name.trim(),
      start_date: toYmd(yearForm.start_date),
      end_date: toYmd(yearForm.end_date),
      status: yearForm.status,
      is_current: yearForm.is_current,
    }
    if (yearEditing.value) {
      await useApi(`/admin/academic/academic-years/${yearEditing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.calendar.yearUpdated'), life: 2200 })
    }
    else {
      await useApi('/admin/academic/academic-years', {
        method: 'POST',
        body: { ...body, institution_id: institutionId.value ?? 1 },
      })
      toast.add({ severity: 'success', summary: t('admin.calendar.yearCreated'), life: 2200 })
    }
    yearModalOpen.value = false
    await loadYears()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.calendar.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDeleteYear(year: AcademicYear) {
  confirm.require({
    message: t('admin.calendar.deleteYearConfirm', { name: year.name }),
    header: t('admin.calendar.deleteYearTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/academic-years/${year.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.calendar.yearDeleted'), life: 2200 })
        delete termsMap.value[year.id]
        await loadYears()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.calendar.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

function resetTermForm() {
  Object.assign(termForm, {
    name: '',
    code: '',
    start_date: null,
    end_date: null,
    enrollment_start_at: null,
    enrollment_end_at: null,
    exam_start_at: null,
    exam_end_at: null,
    is_current: false,
    status: 'upcoming',
  })
}

function openCreateTerm(yearId: number) {
  termEditing.value = null
  activeYearId.value = yearId
  resetTermForm()
  termModalOpen.value = true
}

function openEditTerm(term: Term, yearId: number) {
  termEditing.value = term
  activeYearId.value = yearId
  Object.assign(termForm, {
    name: term.name,
    code: term.code,
    start_date: parseYmd(term.start_date),
    end_date: parseYmd(term.end_date),
    enrollment_start_at: parseYmd(term.enrollment_start_at),
    enrollment_end_at: parseYmd(term.enrollment_end_at),
    exam_start_at: parseYmd(term.exam_start_at),
    exam_end_at: parseYmd(term.exam_end_at),
    is_current: term.is_current,
    status: term.status || 'upcoming',
  })
  termModalOpen.value = true
}

async function saveTerm() {
  if (!termForm.name.trim() || !termForm.code.trim() || !termForm.start_date || !termForm.end_date) {
    toast.add({ severity: 'warn', summary: t('admin.calendar.termRequired'), life: 2800 })
    return
  }
  saving.value = true
  try {
    const body = {
      academic_year_id: activeYearId.value,
      name: termForm.name.trim(),
      code: termForm.code.trim(),
      start_date: toYmd(termForm.start_date),
      end_date: toYmd(termForm.end_date),
      enrollment_start_at: toYmd(termForm.enrollment_start_at),
      enrollment_end_at: toYmd(termForm.enrollment_end_at),
      exam_start_at: toYmd(termForm.exam_start_at),
      exam_end_at: toYmd(termForm.exam_end_at),
      is_current: termForm.is_current,
      status: termForm.status,
    }
    if (termEditing.value) {
      await useApi(`/admin/academic/terms/${termEditing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.calendar.termUpdated'), life: 2200 })
    }
    else {
      await useApi('/admin/academic/terms', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.calendar.termCreated'), life: 2200 })
    }
    termModalOpen.value = false
    if (activeYearId.value) await loadTerms(activeYearId.value)
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.calendar.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDeleteTerm(term: Term, yearId: number) {
  confirm.require({
    message: t('admin.calendar.deleteTermConfirm', { name: term.name }),
    header: t('admin.calendar.deleteTermTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/terms/${term.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.calendar.termDeleted'), life: 2200 })
        await loadTerms(yearId)
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.calendar.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

onMounted(async () => {
  await loadInstitution()
  await loadYears()
})
</script>

<template>
  <div class="page calendar-page">
    <header class="page-heading">
      <div>
        <h1>{{ t('admin.calendar.title') }}</h1>
        <p>{{ t('admin.calendar.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="table-toolbar">
        <div class="stats">
          <span>{{ academicYears.length }} {{ t('admin.calendar.yearsLabel') }}</span>
          <span>{{ t('admin.calendar.termsCount', { n: totalTermsCount }) }}</span>
          <span>{{ t('admin.calendar.currentYear') }}: <strong>{{ currentYearName }}</strong></span>
        </div>
        <div class="toolbar-actions">
          <Button :label="t('admin.calendar.addYear')" icon="pi pi-plus" size="small" @click="openCreateYear" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="loadYears" />
        </div>
      </div>

      <div v-if="loading" class="empty">{{ t('common.loading') }}</div>
      <CommonEmptyState v-else-if="!academicYears.length" :description="t('admin.calendar.noYears')">
        <Button :label="t('admin.calendar.addYear')" icon="pi pi-plus" size="small" @click="openCreateYear" />
      </CommonEmptyState>

      <div v-else class="years-list">
        <article
          v-for="year in academicYears"
          :key="year.id"
          class="year-card"
          :class="{ current: year.is_current }"
        >
          <header class="year-head" @click="toggleYear(year.id)">
            <div class="year-main">
              <i class="pi pi-calendar year-icon" />
              <div>
                <div class="year-title">
                  <strong>{{ year.name }}</strong>
                  <span v-if="year.is_current" class="pill tone-active">{{ t('admin.calendar.currentYear') }}</span>
                </div>
                <small>{{ fmtRange(year.start_date, year.end_date) }}</small>
              </div>
            </div>
            <div class="year-actions" @click.stop>
              <span v-if="termsMap[year.id]" class="term-badge">{{ t('admin.calendar.termsCount', { n: termsMap[year.id].length }) }}</span>
              <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEditYear(year)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="askDeleteYear(year)" />
              <i class="pi expand-icon" :class="expandedYearIds.has(year.id) ? 'pi-chevron-up' : 'pi-chevron-down'" />
            </div>
          </header>

          <div v-if="expandedYearIds.has(year.id)" class="terms-panel">
            <div v-if="loadingTerms.has(year.id)" class="empty small">{{ t('common.loading') }}</div>
            <template v-else>
              <DataTable
                v-if="termsMap[year.id]?.length"
                :value="termsMap[year.id]"
                data-key="id"
                class="terms-table"
              >
                <Column field="name" :header="t('admin.calendar.termName')" style="min-width:160px">
                  <template #body="{ data }">
                    <div class="term-name">
                      <strong>{{ data.name }}</strong>
                      <span v-if="data.is_current" class="pill tone-active">{{ t('admin.calendar.currentTerm') }}</span>
                    </div>
                  </template>
                </Column>
                <Column field="code" :header="t('admin.calendar.termCode')" style="width:100px">
                  <template #body="{ data }"><code>{{ data.code }}</code></template>
                </Column>
                <Column :header="t('admin.calendar.startDate')" style="min-width:140px">
                  <template #body="{ data }">{{ fmtRange(data.start_date, data.end_date) }}</template>
                </Column>
                <Column :header="t('admin.calendar.enrollmentPeriod')" style="min-width:140px">
                  <template #body="{ data }">
                    <span v-if="data.enrollment_start_at">{{ fmtRange(data.enrollment_start_at, data.enrollment_end_at) }}</span>
                    <span v-else>—</span>
                  </template>
                </Column>
                <Column :header="t('admin.calendar.examPeriod')" style="min-width:140px">
                  <template #body="{ data }">
                    <span v-if="data.exam_start_at">{{ fmtRange(data.exam_start_at, data.exam_end_at) }}</span>
                    <span v-else>—</span>
                  </template>
                </Column>
                <Column :header="t('admin.calendar.status')" style="width:120px">
                  <template #body="{ data }">
                    <span class="pill" :class="termStatusTone(data.status)">
                      {{ termStatusLabel(data.status) }}
                    </span>
                  </template>
                </Column>
                <Column :header="t('admin.users.actions')" style="width:7rem">
                  <template #body="{ data }">
                    <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEditTerm(data, year.id)" />
                    <Button icon="pi pi-trash" text rounded severity="danger" @click="askDeleteTerm(data, year.id)" />
                  </template>
                </Column>
              </DataTable>
              <div v-else class="empty small">{{ t('admin.calendar.noTerms') }}</div>

              <div class="term-toolbar">
                <Button :label="t('admin.calendar.addTerm')" icon="pi pi-plus" size="small" outlined @click="openCreateTerm(year.id)" />
              </div>
            </template>
          </div>
        </article>
      </div>
    </section>

    <Dialog
      v-model:visible="yearModalOpen"
      modal
      :header="yearEditing ? t('admin.calendar.editYear') : t('admin.calendar.addYear')"
      :style="{ width: 'min(520px, 96vw)' }"
    >
      <div class="form">
        <label class="field full">
          <span>{{ t('admin.calendar.yearName') }}</span>
          <InputText v-model="yearForm.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.startDate') }}</span>
          <DatePicker v-model="yearForm.start_date" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.endDate') }}</span>
          <DatePicker v-model="yearForm.end_date" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.status') }}</span>
          <Select v-model="yearForm.status" :options="yearStatusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field full check">
          <Checkbox v-model="yearForm.is_current" binary input-id="year-current" />
          <label for="year-current">{{ t('admin.calendar.isCurrentYear') }}</label>
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="yearModalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveYear" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="termModalOpen"
      modal
      :header="termEditing ? t('admin.calendar.editTerm') : t('admin.calendar.addTerm')"
      :style="{ width: 'min(640px, 96vw)' }"
    >
      <div class="form">
        <label class="field">
          <span>{{ t('admin.calendar.termName') }}</span>
          <InputText v-model="termForm.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.termCode') }}</span>
          <InputText v-model="termForm.code" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.startDate') }}</span>
          <DatePicker v-model="termForm.start_date" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.endDate') }}</span>
          <DatePicker v-model="termForm.end_date" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>

        <p class="section full">{{ t('admin.calendar.enrollmentPeriod') }}</p>
        <label class="field">
          <span>{{ t('admin.calendar.enrollmentFrom') }}</span>
          <DatePicker v-model="termForm.enrollment_start_at" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.enrollmentTo') }}</span>
          <DatePicker v-model="termForm.enrollment_end_at" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>

        <p class="section full">{{ t('admin.calendar.examPeriod') }}</p>
        <label class="field">
          <span>{{ t('admin.calendar.examFrom') }}</span>
          <DatePicker v-model="termForm.exam_start_at" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.calendar.examTo') }}</span>
          <DatePicker v-model="termForm.exam_end_at" date-format="dd/mm/yy" show-icon class="w-full" />
        </label>

        <label class="field">
          <span>{{ t('admin.calendar.status') }}</span>
          <Select v-model="termForm.status" :options="termStatusOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field check">
          <Checkbox v-model="termForm.is_current" binary input-id="term-current" />
          <label for="term-current">{{ t('admin.calendar.isCurrentTerm') }}</label>
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="termModalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveTerm" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.calendar-page { gap: 14px; }
.page-heading h1 { margin: 0 0 6px; font-size: 1.35rem; }
.page-heading p { margin: 0; color: var(--text-muted); max-width: 72ch; line-height: 1.45; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 12px; flex-wrap: wrap;
}
.stats { display: flex; flex-wrap: wrap; gap: 14px; color: var(--text-muted); font-size: .88rem; font-weight: 600; }
.toolbar-actions { display: flex; align-items: center; gap: 8px; }

.years-list { display: flex; flex-direction: column; gap: 10px; }
.year-card {
  border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 96%, transparent); overflow: hidden;
}
.year-card.current { border-color: color-mix(in srgb, var(--brand) 35%, var(--border)); }
.year-head {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 14px 16px; cursor: pointer;
}
.year-head:hover { background: var(--surface-subtle); }
.year-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
.year-icon { color: var(--brand); font-size: 1.1rem; }
.year-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.year-title strong { font-size: 1rem; }
.year-main small { color: var(--text-muted); font-size: .82rem; }
.year-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.term-badge {
  font-size: .74rem; font-weight: 700; color: var(--text-muted);
  background: var(--surface-subtle); padding: 3px 8px; border-radius: 999px;
}
.expand-icon { color: var(--text-muted); margin-left: 4px; }

.terms-panel { border-top: 1px solid var(--border); padding: 12px 16px 16px; background: var(--surface-subtle); }
.term-toolbar { margin-top: 10px; }
.term-name { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
code { font-family: ui-monospace, monospace; font-size: .82rem; font-weight: 700; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-active { background: #dcfce7; color: #15803d; }
.tone-deferred { background: #fef9c3; color: #a16207; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.empty { padding: 36px; text-align: center; color: var(--text-muted); display: grid; gap: 10px; justify-items: center; }
.empty.small { padding: 16px; }

.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }
.form .section {
  margin: 0; padding-top: 4px; color: var(--text-muted);
  font-size: .74rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.field.check { flex-direction: row; align-items: center; gap: 8px; }
.w-full { width: 100%; }
</style>
