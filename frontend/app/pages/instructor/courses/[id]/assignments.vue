<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_courses', 'manage_grades'],
})

interface SubmissionRow {
  id: number
  file_url?: string | null
  student_note?: string | null
  grade?: number | string | null
  feedback?: string | null
  submitted_at?: string | null
  user?: { id: number, name: string, email?: string } | null
}

interface AssignmentBlock {
  lesson_id: number
  lesson_title: string
  assignment_id: number
  due_at?: string | null
  submissions_count: number
  ungraded_count: number
  submissions: SubmissionRow[]
}

interface Payload {
  course?: { id: number, title: string }
  assignments: AssignmentBlock[]
  summary?: { assignments: number, submissions: number, ungraded: number }
}

const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()
const courseId = computed(() => Number(route.params.id))

const loading = ref(true)
const savingId = ref<number | null>(null)
const data = ref<Payload | null>(null)
const draft = reactive<Record<number, { grade: number | null, feedback: string }>>({})

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
function fmt(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(dateLocale.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

async function load() {
  loading.value = true
  try {
    data.value = await useApi<Payload>(`/courses/${courseId.value}/assignments/submissions`)
    for (const block of data.value.assignments || []) {
      for (const sub of block.submissions) {
        draft[sub.id] = {
          grade: sub.grade === null || sub.grade === undefined ? null : Number(sub.grade),
          feedback: sub.feedback || '',
        }
      }
    }
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.assignments.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function save(sub: SubmissionRow) {
  const row = draft[sub.id]
  if (!row || row.grade === null || Number.isNaN(Number(row.grade))) {
    toast.add({ severity: 'warn', summary: t('instructor.assignments.grade'), life: 2200 })
    return
  }
  savingId.value = sub.id
  try {
    await useApi(`/courses/${courseId.value}/assignments/submissions/${sub.id}`, {
      method: 'PUT',
      body: { grade: row.grade, feedback: row.feedback || null },
    })
    toast.add({ severity: 'success', summary: t('instructor.assignments.saved'), life: 2200 })
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.assignments.saveError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    savingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <Button :label="t('instructor.assignments.back')" icon="pi pi-arrow-left" text size="small" @click="navigateTo('/instructor/courses')" />
        <h1>{{ data?.course?.title || t('instructor.assignments.title') }}</h1>
        <p>{{ t('instructor.assignments.subtitle') }}</p>
      </div>
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <div class="kpis">
      <div class="kpi"><span>{{ t('instructor.assignments.kpiAssignments') }}</span><strong>{{ data?.summary?.assignments ?? 0 }}</strong></div>
      <div class="kpi"><span>{{ t('instructor.assignments.kpiSubmissions') }}</span><strong>{{ data?.summary?.submissions ?? 0 }}</strong></div>
      <div class="kpi"><span>{{ t('instructor.assignments.kpiUngraded') }}</span><strong>{{ data?.summary?.ungraded ?? 0 }}</strong></div>
    </div>

    <div v-if="loading" class="empty">{{ t('common.loading') }}</div>
    <CommonEmptyState v-else-if="!data?.assignments.length" :description="t('instructor.assignments.empty')" />

    <section v-for="block in data?.assignments || []" :key="block.assignment_id" class="panel">
      <header class="panel-head">
        <div>
          <strong>{{ block.lesson_title }}</strong>
          <small>{{ t('instructor.assignments.due') }}: {{ fmt(block.due_at) }}</small>
        </div>
        <Tag :value="`${block.ungraded_count} ${t('instructor.assignments.ungraded')}`" :severity="block.ungraded_count ? 'warn' : 'success'" />
      </header>

      <DataTable :value="block.submissions" data-key="id" striped-rows>
        <Column :header="t('instructor.assignments.student')" style="min-width:180px">
          <template #body="{ data: row }">
            <strong>{{ row.user?.name || '—' }}</strong>
            <small class="muted">{{ row.user?.email }}</small>
          </template>
        </Column>
        <Column :header="t('instructor.assignments.submitted')" style="min-width:140px">
          <template #body="{ data: row }">{{ fmt(row.submitted_at) }}</template>
        </Column>
        <Column :header="t('instructor.assignments.file')" style="width:110px">
          <template #body="{ data: row }">
            <a v-if="row.file_url" :href="row.file_url" target="_blank" rel="noopener">{{ t('instructor.assignments.openFile') }}</a>
            <span v-else>—</span>
          </template>
        </Column>
        <Column :header="t('instructor.assignments.note')" style="min-width:140px">
          <template #body="{ data: row }">{{ row.student_note || '—' }}</template>
        </Column>
        <Column :header="t('instructor.assignments.grade')" style="width:110px">
          <template #body="{ data: row }">
            <InputNumber v-if="draft[row.id]" v-model="draft[row.id].grade" :min="0" :max="10" :min-fraction-digits="0" :max-fraction-digits="1" class="w-full" />
          </template>
        </Column>
        <Column :header="t('instructor.assignments.feedback')" style="min-width:180px">
          <template #body="{ data: row }">
            <InputText v-if="draft[row.id]" v-model="draft[row.id].feedback" class="w-full" />
          </template>
        </Column>
        <Column style="width:8rem">
          <template #body="{ data: row }">
            <Button
              :label="t('instructor.assignments.save')"
              size="small"
              :loading="savingId === row.id"
              @click="save(row)"
            />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('instructor.assignments.empty')" />
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.workspace-head h1 { margin: .2rem 0; font-size: 1.5rem; }
.workspace-head p { margin: 0; color: var(--text-muted); }
.kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.kpi { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.kpi span { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
.kpi strong { font-size: 1.35rem; }
.panel { border: 1px solid var(--border); border-radius: 14px; overflow: hidden; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.panel-head { display: flex; justify-content: space-between; gap: 10px; align-items: center; padding: 12px 14px; border-bottom: 1px solid var(--border); }
.panel-head small { display: block; color: var(--text-muted); font-size: .8rem; }
.muted { display: block; color: var(--text-muted); font-size: .8rem; }
.empty { padding: 24px; text-align: center; color: var(--text-muted); }
.w-full { width: 100%; }
@media (max-width: 720px) { .kpis { grid-template-columns: 1fr; } }
</style>
