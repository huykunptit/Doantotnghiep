<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_grades', 'view_grades'],
})

interface SectionRow {
  section: {
    id: number
    code: string
    name?: string | null
    status?: string
    course?: { id: number, title: string, course_mode?: string } | null
    term?: { id: number, name: string, code: string } | null
    cohort?: { id: number, name: string, code: string } | null
  }
  enrollments: number
  graded: number
  pending: number
}

interface DashboardRes {
  current_term?: { id: number, name: string, code: string } | null
  sections: SectionRow[]
  totals?: { sections: number, students: number, pending_grading: number }
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const data = ref<DashboardRes | null>(null)

const totals = computed(() => data.value?.totals || { sections: 0, students: 0, pending_grading: 0 })
const term = computed(() => data.value?.current_term)

async function load() {
  loading.value = true
  try {
    data.value = await useApi<DashboardRes>('/instructor/dashboard')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('instructor.grades.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <p class="eyebrow">{{ t('instructor.console') }}</p>
        <h1>{{ t('instructor.grades.title') }}</h1>
        <p>{{ t('instructor.grades.subtitle') }}</p>
      </div>
      <div class="head-actions">
        <Tag v-if="term" :value="`${term.name} (${term.code})`" severity="info" />
        <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
      </div>
    </header>

    <div class="kpis">
      <div class="kpi">
        <span>{{ t('instructor.grades.kpiSections') }}</span>
        <strong>{{ totals.sections }}</strong>
      </div>
      <div class="kpi">
        <span>{{ t('instructor.grades.kpiStudents') }}</span>
        <strong>{{ totals.students }}</strong>
      </div>
      <div class="kpi">
        <span>{{ t('instructor.grades.kpiPending') }}</span>
        <strong>{{ totals.pending_grading }}</strong>
      </div>
    </div>

    <section class="panel">
      <DataTable :value="data?.sections || []" data-key="section.id" :loading="loading" striped-rows>
        <Column :header="t('instructor.grades.code')" style="min-width:110px">
          <template #body="{ data: row }">
            <code>{{ row.section.code }}</code>
          </template>
        </Column>
        <Column :header="t('instructor.grades.course')" style="min-width:220px">
          <template #body="{ data: row }">
            <strong>{{ row.section.course?.title || '—' }}</strong>
            <small v-if="row.section.name" class="muted">{{ row.section.name }}</small>
          </template>
        </Column>
        <Column :header="t('instructor.grades.cohort')" style="min-width:100px">
          <template #body="{ data: row }">{{ row.section.cohort?.code || '—' }}</template>
        </Column>
        <Column :header="t('instructor.grades.enrolled')" style="width:90px">
          <template #body="{ data: row }">{{ row.enrollments }}</template>
        </Column>
        <Column :header="t('instructor.grades.graded')" style="width:90px">
          <template #body="{ data: row }">{{ row.graded }}</template>
        </Column>
        <Column :header="t('instructor.grades.pending')" style="width:90px">
          <template #body="{ data: row }">{{ row.pending }}</template>
        </Column>
        <Column :header="t('common.actions')" style="width:140px">
          <template #body="{ data: row }">
            <Button
              :label="t('instructor.grades.openBook')"
              icon="pi pi-pencil"
              size="small"
              @click="navigateTo(`/instructor/sections/${row.section.id}/grades`)"
            />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('instructor.grades.empty')" />
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.workspace-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.eyebrow { margin: 0; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--p-text-muted-color); }
.workspace-head h1 { margin: .2rem 0; font-size: 1.6rem; }
.workspace-head p { margin: 0; color: var(--p-text-muted-color); }
.head-actions { display: flex; align-items: center; gap: .5rem; }
.kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
.kpi { border: 1px solid var(--p-content-border-color); border-radius: 12px; padding: .9rem 1rem; background: var(--p-content-background); display: flex; flex-direction: column; gap: .25rem; }
.kpi span { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--p-text-muted-color); }
.kpi strong { font-size: 1.4rem; }
.panel { border: 1px solid var(--p-content-border-color); border-radius: 12px; overflow: hidden; background: var(--p-content-background); }
.muted { display: block; color: var(--p-text-muted-color); font-size: .8rem; }

code { font-size: .8rem; background: var(--p-surface-100); padding: .15rem .4rem; border-radius: 6px; }
@media (max-width: 720px) { .kpis { grid-template-columns: 1fr; } }
</style>
