<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { FEATURE_ATTENDANCE_ENABLED } from '~/config/feature-flags'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_courses', 'manage_grades'],
})

if (!FEATURE_ATTENDANCE_ENABLED) {
  await navigateTo('/instructor')
}

interface NamedRef { id: number, code?: string, name?: string, title?: string }
interface MyCourse {
  id: number
  title: string
  thumbnail?: string | null
}
interface AdminClass {
  id: number
  code: string
  name?: string | null
  program?: NamedRef | null
}
interface OfflineSession {
  id: number
  title: string
  location: string
  room?: string | null
  start_at: string
  duration: number
  latitude: number
  longitude: number
  check_in_radius_meters?: number
  is_active: boolean
  attendances_count?: number
  qr_enabled?: boolean
  qr_mode?: 'manual' | 'rotating' | 'static' | string
  qr_rotate_seconds?: number
  course?: NamedRef | null
  administrative_class?: NamedRef | null
}
interface AttendanceRow {
  user_id: number
  name: string
  student_code?: string | null
  email?: string
  status: string
  checked_in_at?: string | null
  distance_meters?: number | null
}
interface Paginator<T> { data: T[], total?: number }

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const apiBase = '/instructor'
const loading = ref(false)
const saving = ref(false)
const courses = ref<MyCourse[]>([])
const adminClasses = ref<AdminClass[]>([])
const selectedCourseId = ref<number | null>(null)
const selectedAdminClassId = ref<number | null>(null)
const sessions = ref<OfflineSession[]>([])

const modalOpen = ref(false)
const editing = ref<OfflineSession | null>(null)
const form = reactive({
  title: '',
  location: '',
  room: '',
  start_at: null as Date | null,
  duration: 90,
  latitude: null as number | null,
  longitude: null as number | null,
  check_in_radius_meters: 15,
  qr_enabled: true,
  qr_mode: 'manual' as 'manual' | 'rotating' | 'static',
  qr_rotate_seconds: 60,
})

const qrModeOptions = computed(() => [
  { label: t('admin.builder.fields.qrModeManual'), value: 'manual' },
  { label: t('admin.builder.fields.qrModeRotating'), value: 'rotating' },
  { label: t('admin.builder.fields.qrModeStatic'), value: 'static' },
])

const courseOptions = computed(() =>
  courses.value.map(c => ({ label: c.title, value: c.id })),
)
const adminClassOptions = computed(() =>
  adminClasses.value.map(c => ({
    label: `${c.code}${c.name ? ` — ${c.name}` : ''}${c.program?.name ? ` (${c.program.name})` : ''}`,
    value: c.id,
  })),
)

const qrOpen = ref(false)
const qrSession = ref<OfflineSession | null>(null)
const qrRefreshUrl = computed(() =>
  qrSession.value ? `${apiBase}/sessions/${qrSession.value.id}/qr` : '',
)

const reportOpen = ref(false)
const reportRows = ref<AttendanceRow[]>([])
const reportSummary = ref<{ total: number, present: number, late: number, absent: number } | null>(null)
const reportLoading = ref(false)

function toLocalInput(iso?: string | null) {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

function unwrapList<T>(res: any): T[] {
  if (Array.isArray(res)) return res
  if (Array.isArray(res?.data)) return res.data
  if (Array.isArray(res?.data?.data)) return res.data.data
  return []
}

async function loadCourses() {
  try {
    const res = await useApi<Paginator<MyCourse>>('/my-courses', { query: { per_page: 100, sort: 'title' } })
    courses.value = unwrapList<MyCourse>(res)
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.attendance.loadCoursesError'), detail: e?.data?.message, life: 3500 })
  }
}

async function loadAdminClasses() {
  try {
    const res = await useApi<Paginator<AdminClass>>('/instructor/academic/administrative-classes', {
      query: { per_page: 200 },
    })
    adminClasses.value = unwrapList<AdminClass>(res)
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('instructor.attendance.loadClassesError'), detail: e?.data?.message, life: 3500 })
  }
}

async function loadSessions() {
  loading.value = true
  try {
    const res = await useApi<{ sessions: OfflineSession[] }>(`${apiBase}/attendance/sessions`, {
      query: {
        course_id: selectedCourseId.value || undefined,
        administrative_class_id: selectedAdminClassId.value || undefined,
      },
    })
    sessions.value = res.sessions || []
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.loadSessionsError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  if (!selectedCourseId.value || !selectedAdminClassId.value) {
    toast.add({ severity: 'warn', summary: t('instructor.attendance.pickCourseClass'), life: 3000 })
    return
  }
  editing.value = null
  Object.assign(form, {
    title: '',
    location: '',
    room: '',
    start_at: new Date(),
    duration: 90,
    latitude: null,
    longitude: null,
    check_in_radius_meters: 15,
    qr_enabled: true,
    qr_mode: 'manual',
    qr_rotate_seconds: 60,
  })
  modalOpen.value = true
}

function openEdit(session: OfflineSession) {
  editing.value = session
  Object.assign(form, {
    title: session.title,
    location: session.location,
    room: session.room || '',
    start_at: toLocalInput(session.start_at),
    duration: session.duration,
    latitude: session.latitude,
    longitude: session.longitude,
    check_in_radius_meters: session.check_in_radius_meters || 15,
    qr_enabled: session.qr_enabled !== false,
    qr_mode: (session.qr_mode as any) || 'manual',
    qr_rotate_seconds: session.qr_rotate_seconds || 60,
  })
  modalOpen.value = true
}

function useMyLocation() {
  if (!import.meta.client || !navigator.geolocation) {
    toast.add({ severity: 'warn', summary: t('admin.attendance.geoUnsupported'), life: 3000 })
    return
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.latitude = Number(pos.coords.latitude.toFixed(7))
      form.longitude = Number(pos.coords.longitude.toFixed(7))
      toast.add({ severity: 'success', summary: t('admin.attendance.geoOk'), life: 2000 })
    },
    () => toast.add({ severity: 'error', summary: t('admin.attendance.geoFail'), life: 3000 }),
    { enableHighAccuracy: true, timeout: 15000 },
  )
}

async function saveSession() {
  if (!form.title.trim() || !form.location.trim() || !form.start_at || form.latitude == null || form.longitude == null) {
    toast.add({ severity: 'warn', summary: t('admin.attendance.formIncomplete'), life: 3000 })
    return
  }
  saving.value = true
  try {
    const body = {
      title: form.title.trim(),
      location: form.location.trim(),
      room: form.room.trim() || null,
      start_at: form.start_at.toISOString(),
      duration: form.duration,
      latitude: form.latitude,
      longitude: form.longitude,
      check_in_radius_meters: form.check_in_radius_meters || 15,
      qr_enabled: form.qr_enabled,
      qr_mode: form.qr_mode,
      qr_rotate_seconds: form.qr_rotate_seconds || 60,
    }
    if (editing.value) {
      await useApi(`${apiBase}/sessions/${editing.value.id}`, { method: 'PUT', body })
    }
    else {
      await useApi(`${apiBase}/attendance/sessions`, {
        method: 'POST',
        body: {
          ...body,
          course_id: selectedCourseId.value,
          administrative_class_id: selectedAdminClassId.value,
        },
      })
    }
    modalOpen.value = false
    toast.add({ severity: 'success', summary: t('admin.attendance.saved'), life: 2200 })
    await loadSessions()
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.saveError'), detail: e?.data?.message, life: 4000 })
  }
  finally {
    saving.value = false
  }
}

async function toggleActive(session: OfflineSession) {
  try {
    await useApi(`${apiBase}/sessions/${session.id}`, {
      method: 'PUT',
      body: { is_active: !session.is_active },
    })
    await loadSessions()
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.saveError'), detail: e?.data?.message, life: 3500 })
  }
}

function askDelete(session: OfflineSession) {
  confirm.require({
    message: t('admin.attendance.deleteConfirm', { title: session.title }),
    header: t('common.confirm'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`${apiBase}/sessions/${session.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.attendance.deleted'), life: 2000 })
        await loadSessions()
      }
      catch (e: any) {
        toast.add({ severity: 'error', summary: t('admin.attendance.saveError'), detail: e?.data?.message, life: 3500 })
      }
    },
  })
}

function openQr(session: OfflineSession) {
  qrSession.value = session
  qrOpen.value = true
}

async function openReport(session: OfflineSession) {
  reportOpen.value = true
  reportLoading.value = true
  reportRows.value = []
  reportSummary.value = null
  try {
    const res = await useApi<{
      rows: AttendanceRow[]
      summary: { total: number, present: number, late: number, absent: number }
    }>(`${apiBase}/sessions/${session.id}/attendance`)
    reportRows.value = res.rows || []
    reportSummary.value = res.summary
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.reportError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    reportLoading.value = false
  }
}

function statusLabel(status: string) {
  if (status === 'present') return t('admin.attendance.statusPresent')
  if (status === 'late') return t('admin.attendance.statusLate')
  return t('admin.attendance.statusAbsent')
}

function applyFilters() {
  loadSessions()
}

watch([selectedCourseId, selectedAdminClassId], () => { loadSessions() })

onMounted(async () => {
  await Promise.all([loadCourses(), loadAdminClasses()])
  await loadSessions()
})
</script>

<template>
  <div class="page">
    <header class="page-heading">
      <div>
        <h1>{{ t('instructor.attendance.title') }}</h1>
        <p>{{ t('instructor.attendance.subtitle') }}</p>
      </div>
      <Button
        :label="t('admin.attendance.create')"
        icon="pi pi-plus"
        :disabled="!selectedCourseId || !selectedAdminClassId"
        @click="openCreate"
      />
    </header>

    <section class="panel filters">
      <label class="field">
        <span>{{ t('instructor.attendance.course') }}</span>
        <Select
          v-model="selectedCourseId"
          :options="courseOptions"
          option-label="label"
          option-value="value"
          filter
          show-clear
          class="w-full"
          :placeholder="t('instructor.attendance.coursePh')"
        />
      </label>
      <label class="field">
        <span>{{ t('instructor.attendance.adminClass') }}</span>
        <Select
          v-model="selectedAdminClassId"
          :options="adminClassOptions"
          option-label="label"
          option-value="value"
          filter
          show-clear
          class="w-full"
          :placeholder="t('instructor.attendance.adminClassPh')"
        />
      </label>
      <div class="filter-actions">
        <Button :label="t('admin.manageCourses.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
        <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="loadSessions" />
      </div>
      <p class="hint">{{ t('instructor.attendance.hint') }}</p>
    </section>

    <section class="panel">
      <DataTable :value="sessions" data-key="id" :loading="loading" size="small">
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="title" :header="t('admin.attendance.colTitle')" />
        <Column :header="t('instructor.attendance.course')">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column :header="t('instructor.attendance.adminClass')">
          <template #body="{ data }">
            {{ (data.administrative_class || data.administrativeClass)?.code || '—' }}
            <small v-if="(data.administrative_class || data.administrativeClass)?.name">
              — {{ (data.administrative_class || data.administrativeClass).name }}
            </small>
          </template>
        </Column>
        <Column field="location" :header="t('admin.attendance.colLocation')" />
        <Column field="room" :header="t('admin.attendance.colRoom')" />
        <Column :header="t('admin.attendance.colTime')">
          <template #body="{ data }">
            {{ new Date(data.start_at).toLocaleString() }} · {{ data.duration }}′
          </template>
        </Column>
        <Column :header="t('admin.attendance.colStatus')" style="width:7rem">
          <template #body="{ data }">
            <Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? t('admin.attendance.open') : t('admin.attendance.closed')" />
          </template>
        </Column>
        <Column style="width:14rem">
          <template #body="{ data }">
            <div class="row-actions">
              <Button icon="pi pi-qrcode" text rounded :title="t('admin.attendance.showQr')" @click="openQr(data)" />
              <Button icon="pi pi-list" text rounded :title="t('admin.attendance.reportTitle')" @click="openReport(data)" />
              <Button
                :icon="data.is_active ? 'pi pi-lock' : 'pi pi-lock-open'"
                text
                rounded
                severity="secondary"
                @click="toggleActive(data)"
              />
              <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
            </div>
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('instructor.attendance.empty')" />
        </template>
      </DataTable>
    </section>

    <Dialog v-model:visible="modalOpen" modal :header="editing ? t('admin.attendance.edit') : t('admin.attendance.create')" class="form-dialog">
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('admin.attendance.colTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.colLocation') }}</span>
          <InputText v-model="form.location" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.colRoom') }}</span>
          <InputText v-model="form.room" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.colTime') }}</span>
          <DatePicker v-model="form.start_at" show-time hour-format="24" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.fieldDuration') }}</span>
          <InputNumber v-model="form.duration" :min="15" :step="15" suffix=" ′" class="w-full" />
        </label>
        <label class="field">
          <span>Lat</span>
          <InputNumber v-model="form.latitude" :max-fraction-digits="7" class="w-full" />
        </label>
        <label class="field">
          <span>Lng</span>
          <InputNumber v-model="form.longitude" :max-fraction-digits="7" class="w-full" />
        </label>
        <div class="field full">
          <Button :label="t('admin.attendance.useMyLocation')" icon="pi pi-map-marker" text size="small" @click="useMyLocation" />
        </div>
        <label class="field">
          <span>{{ t('admin.attendance.fieldRadius') }}</span>
          <InputNumber v-model="form.check_in_radius_meters" :min="5" :max="500" suffix=" m" class="w-full" />
        </label>
        <label class="field check">
          <span>{{ t('admin.builder.fields.qrEnabled') }}</span>
          <ToggleSwitch v-model="form.qr_enabled" />
        </label>
        <label v-if="form.qr_enabled" class="field full">
          <span>{{ t('admin.builder.fields.qrMode') }}</span>
          <Select v-model="form.qr_mode" :options="qrModeOptions" option-label="label" option-value="value" class="w-full" />
        </label>
        <label v-if="form.qr_enabled && form.qr_mode === 'rotating'" class="field">
          <span>{{ t('admin.builder.fields.qrRotateSeconds') }}</span>
          <InputNumber v-model="form.qr_rotate_seconds" :min="15" :max="600" :step="15" suffix=" s" class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" text severity="secondary" @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveSession" />
      </template>
    </Dialog>

    <CommonOfflineSessionQrDialog
      v-model:visible="qrOpen"
      :refresh-url="qrRefreshUrl"
      :title="qrSession?.title || t('admin.attendance.qrTitle')"
      :mode="qrSession?.qr_mode || form.qr_mode"
      :rotate-seconds="qrSession?.qr_rotate_seconds || 60"
    />

    <Dialog v-model:visible="reportOpen" modal :header="t('admin.attendance.reportTitle')" class="report-dialog">
      <div v-if="reportSummary" class="summary">
        <Tag :value="`${t('admin.attendance.statusPresent')}: ${reportSummary.present}`" severity="success" />
        <Tag :value="`${t('admin.attendance.statusLate')}: ${reportSummary.late}`" severity="warn" />
        <Tag :value="`${t('admin.attendance.statusAbsent')}: ${reportSummary.absent}`" severity="danger" />
      </div>
      <DataTable :value="reportRows" :loading="reportLoading" data-key="user_id" size="small">
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="student_code" :header="t('admin.attendance.colCode')" />
        <Column field="name" :header="t('admin.attendance.colStudent')" />
        <Column :header="t('admin.attendance.colStatus')">
          <template #body="{ data }">{{ statusLabel(data.status) }}</template>
        </Column>
        <Column :header="t('admin.attendance.colDistance')">
          <template #body="{ data }">{{ data.distance_meters != null ? `${data.distance_meters}m` : '—' }}</template>
        </Column>
      </DataTable>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.page-heading { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.page-heading h1 { margin: 0; font-size: 1.35rem; }
.page-heading p { margin: 4px 0 0; color: var(--text-muted); }
.panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; align-items: end; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.field.check { flex-direction: row; align-items: center; justify-content: space-between; }
.field.full { grid-column: 1 / -1; }
.hint { grid-column: 1 / -1; margin: 0; color: var(--text-muted); font-size: .85rem; }
.filter-actions { display: flex; gap: 8px; align-items: center; }
.row-actions { display: flex; gap: 2px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.w-full { width: 100%; }
.summary { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.form-dialog, .report-dialog { width: min(720px, 96vw); }
@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
