<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface AdminClassItem { id: number, code: string, name: string }
interface TermItem { id: number, code: string, name: string, is_current?: boolean }
interface CourseItem { id: number, title: string }
interface LecturerItem { id: number, name: string, email?: string }
interface ScheduleItem {
  id: number
  administrative_class_id: number
  course_id?: number | null
  term_id?: number | null
  lecturer_id?: number | null
  weekday: number
  start_time: string
  end_time: string
  room?: string | null
  administrative_class?: AdminClassItem | null
  course?: { id: number, title: string } | null
  term?: { id: number, name: string, code: string } | null
  lecturer?: { id: number, name: string } | null
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const rows = ref<ScheduleItem[]>([])
const total = ref(0)

const classes = ref<AdminClassItem[]>([])
const terms = ref<TermItem[]>([])
const courses = ref<CourseItem[]>([])
const lecturers = ref<LecturerItem[]>([])

const classFilter = ref<number | null>(null)
const termFilter = ref<number | null>(null)

const modalOpen = ref(false)
const editing = ref<ScheduleItem | null>(null)

function defaultTime(hour: number) {
  const d = new Date()
  d.setHours(hour, 0, 0, 0)
  return d
}

const form = reactive({
  administrative_class_id: null as number | null,
  course_id: null as number | null,
  term_id: null as number | null,
  lecturer_id: null as number | null,
  weekday: 1,
  start_time: defaultTime(7) as Date,
  end_time: defaultTime(9) as Date,
  room: '',
})

const weekdayOptions = computed(() => [
  { label: t('admin.academic.schedules.weekdays.mon'), value: 1 },
  { label: t('admin.academic.schedules.weekdays.tue'), value: 2 },
  { label: t('admin.academic.schedules.weekdays.wed'), value: 3 },
  { label: t('admin.academic.schedules.weekdays.thu'), value: 4 },
  { label: t('admin.academic.schedules.weekdays.fri'), value: 5 },
  { label: t('admin.academic.schedules.weekdays.sat'), value: 6 },
  { label: t('admin.academic.schedules.weekdays.sun'), value: 7 },
])

function weekdayLabel(value: number) {
  return weekdayOptions.value.find(w => w.value === value)?.label || `#${value}`
}

function timeToDate(value: string) {
  const [h, m] = (value || '00:00').split(':').map(Number)
  const d = defaultTime(h || 0)
  d.setMinutes(m || 0)
  return d
}

function dateToTime(value: Date | null) {
  if (!value) return '00:00'
  const h = String(value.getHours()).padStart(2, '0')
  const m = String(value.getMinutes()).padStart(2, '0')
  return `${h}:${m}`
}

async function loadClasses() {
  try {
    const res = await useApi<{ data?: AdminClassItem[] } | AdminClassItem[]>('/admin/academic/administrative-classes', { query: { per_page: 200 } })
    classes.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    classes.value = []
  }
}

async function loadTerms() {
  try {
    const res = await useApi<{ data?: TermItem[] } | TermItem[]>('/admin/academic/terms', { query: { per_page: 100 } })
    terms.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    terms.value = []
  }
}

async function loadCourses() {
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses', { query: { per_page: 200 } })
    courses.value = res.data || []
  }
  catch {
    courses.value = []
  }
}

async function loadLecturers() {
  try {
    const res = await useApi<{ data: LecturerItem[] }>('/admin/instructors', { query: { per_page: 200 } })
    lecturers.value = res.data || []
  }
  catch {
    lecturers.value = []
  }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data: ScheduleItem[], total: number }>('/admin/academic/class-schedules', {
      query: {
        per_page: 200,
        administrative_class_id: classFilter.value || undefined,
        term_id: termFilter.value || undefined,
      },
    })
    rows.value = [...(res.data || [])].sort((a, b) => a.weekday - b.weekday || a.start_time.localeCompare(b.start_time))
    total.value = res.total || rows.value.length
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.academic.schedules.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function resetForm() {
  form.administrative_class_id = classFilter.value
  form.course_id = null
  form.term_id = termFilter.value
  form.lecturer_id = null
  form.weekday = 1
  form.start_time = defaultTime(7)
  form.end_time = defaultTime(9)
  form.room = ''
}

function openCreate() {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: ScheduleItem) {
  editing.value = item
  form.administrative_class_id = item.administrative_class_id
  form.course_id = item.course_id || null
  form.term_id = item.term_id || null
  form.lecturer_id = item.lecturer_id || null
  form.weekday = item.weekday
  form.start_time = timeToDate(item.start_time)
  form.end_time = timeToDate(item.end_time)
  form.room = item.room || ''
  modalOpen.value = true
}

async function save() {
  if (!form.administrative_class_id) {
    toast.add({ severity: 'warn', summary: t('admin.academic.schedules.classRequired'), life: 2500 })
    return
  }
  if (dateToTime(form.end_time) <= dateToTime(form.start_time)) {
    toast.add({ severity: 'warn', summary: t('admin.academic.schedules.timeInvalid'), life: 2500 })
    return
  }

  saving.value = true
  try {
    const body = {
      administrative_class_id: form.administrative_class_id,
      course_id: form.course_id || null,
      term_id: form.term_id || null,
      lecturer_id: form.lecturer_id || null,
      weekday: form.weekday,
      start_time: dateToTime(form.start_time),
      end_time: dateToTime(form.end_time),
      room: form.room || null,
    }
    if (editing.value) {
      await useApi(`/admin/academic/class-schedules/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.academic.schedules.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/academic/class-schedules', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.academic.schedules.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.academic.schedules.saveError'),
      detail: error?.data?.message || Object.values(error?.data?.errors || {}).flat()?.[0],
      life: 4000,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: ScheduleItem) {
  confirm.require({
    message: t('admin.academic.schedules.deleteConfirm'),
    header: t('admin.academic.schedules.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/academic/class-schedules/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.academic.schedules.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({ severity: 'error', summary: t('admin.academic.schedules.deleteError'), detail: error?.data?.message, life: 3500 })
      }
    },
  })
}

watch([classFilter, termFilter], load)

onMounted(async () => {
  await Promise.all([loadClasses(), loadTerms(), loadCourses(), loadLecturers()])
  await load()
})
</script>

<template>
  <div class="page schedules-page">

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <i class="pi pi-filter" />
          <strong>{{ t('admin.academic.schedules.filters') }}</strong>
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.academic.schedules.adminClass') }}</span>
            <Select
              v-model="classFilter"
              :options="classes"
              option-label="code"
              option-value="id"
              filter
              show-clear
              :placeholder="t('common.all')"
              class="w-full"
            >
              <template #option="{ option }">
                <div>{{ option.code }} — {{ option.name }}</div>
              </template>
            </Select>
          </label>
          <label class="field">
            <span>{{ t('admin.academic.schedules.term') }}</span>
            <Select
              v-model="termFilter"
              :options="terms"
              option-label="name"
              option-value="id"
              filter
              show-clear
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.academic.schedules.reset')" icon="pi pi-times" size="small" severity="secondary" text @click="classFilter = null; termFilter = null" />
        </div>
      </div>

      <div class="table-toolbar">
        <strong>{{ t('admin.users.result', { n: total }) }}</strong>
        <div class="toolbar-actions">
          <Button :label="t('admin.academic.schedules.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable :value="rows" data-key="id" :loading="loading" paginator :rows="15" :rows-per-page-options="[10, 15, 25]" striped-rows>
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.adminClass')" style="min-width:150px">
          <template #body="{ data }">{{ data.administrative_class?.code || '—' }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.weekday')" style="min-width:110px">
          <template #body="{ data }">{{ weekdayLabel(data.weekday) }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.time')" style="min-width:120px">
          <template #body="{ data }">{{ data.start_time?.slice(0, 5) }} – {{ data.end_time?.slice(0, 5) }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.course')" style="min-width:180px">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.lecturer')" style="min-width:150px">
          <template #body="{ data }">{{ data.lecturer?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.room')" style="min-width:100px">
          <template #body="{ data }">{{ data.room || '—' }}</template>
        </Column>
        <Column :header="t('admin.academic.schedules.term')" style="min-width:120px">
          <template #body="{ data }">{{ data.term?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:8rem">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
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
      :header="editing ? t('admin.academic.schedules.edit') : t('admin.academic.schedules.add')"
      :style="{ width: 'min(640px, 96vw)' }"
    >
      <div class="form">
        <label class="field full">
          <span>{{ t('admin.academic.schedules.adminClass') }} *</span>
          <Select
            v-model="form.administrative_class_id"
            :options="classes"
            option-label="code"
            option-value="id"
            filter
            class="w-full"
          >
            <template #option="{ option }">
              <div>{{ option.code }} — {{ option.name }}</div>
            </template>
          </Select>
        </label>
        <label class="field">
          <span>{{ t('admin.academic.schedules.weekday') }} *</span>
          <Select v-model="form.weekday" :options="weekdayOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.academic.schedules.term') }}</span>
          <Select v-model="form.term_id" :options="terms" option-label="name" option-value="id" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.academic.schedules.startTime') }} *</span>
          <DatePicker v-model="form.start_time" time-only hour-format="24" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.academic.schedules.endTime') }} *</span>
          <DatePicker v-model="form.end_time" time-only hour-format="24" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.academic.schedules.course') }}</span>
          <Select v-model="form.course_id" :options="courses" option-label="title" option-value="id" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.academic.schedules.lecturer') }}</span>
          <Select v-model="form.lecturer_id" :options="lecturers" option-label="name" option-value="id" filter show-clear class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.academic.schedules.room') }}</span>
          <InputText v-model="form.room" class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.schedules-page { gap: 14px; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle); }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.filter-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.w-full { width: 100%; }

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }

@media (max-width: 720px) {
  .form { grid-template-columns: 1fr; }
}
</style>
