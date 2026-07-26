<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import QRCode from 'qrcode'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface NamedRef { id: number, code?: string, name?: string, title?: string }
interface ClassSection {
  id: number
  code: string
  name?: string | null
  course?: NamedRef | null
  term?: NamedRef | null
  lecturer?: { id: number, name: string } | null
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
  qr_token?: string | null
  qr_expires_at?: string | null
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

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const apiBase = computed(() => '/admin/academic')

const loadingSections = ref(false)
const loadingSessions = ref(false)
const saving = ref(false)
const sectionOptions = ref<{ label: string, value: number }[]>([])
const selectedSectionId = ref<number | null>(null)
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
})

const qrOpen = ref(false)
const qrSession = ref<OfflineSession | null>(null)
const qrPayload = ref('')
const qrDataUrl = ref('')
const qrExpiresAt = ref<string | null>(null)
const qrRefreshing = ref(false)
const qrCountdown = ref('')
let qrTimer: ReturnType<typeof setInterval> | null = null

const reportOpen = ref(false)
const reportRows = ref<AttendanceRow[]>([])
const reportSummary = ref<{ total: number, present: number, late: number, absent: number } | null>(null)
const reportLoading = ref(false)

function toLocalInput(iso?: string | null) {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

async function loadSections() {
  loadingSections.value = true
  try {
    const res = await useApi<any>('/admin/academic/class-sections', {
      query: { per_page: 200 },
    })
    const rows: ClassSection[] = res.data || res.class_sections || res || []
    sectionOptions.value = rows.map((s) => ({
      value: s.id,
      label: `${s.code}${s.name ? ` — ${s.name}` : ''}${s.course?.title ? ` (${s.course.title})` : ''}`,
    }))
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.loadSectionsError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loadingSections.value = false
  }
}

async function loadSessions() {
  if (!selectedSectionId.value) {
    sessions.value = []
    return
  }
  loadingSessions.value = true
  try {
    const res = await useApi<{ sessions: OfflineSession[] }>(
      `${apiBase.value}/class-sections/${selectedSectionId.value}/sessions`,
    )
    sessions.value = res.sessions || []
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.loadSessionsError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loadingSessions.value = false
  }
}

function openCreate() {
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
  })
  modalOpen.value = true
}

async function useMyLocation() {
  if (!navigator.geolocation) {
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
  if (!selectedSectionId.value) return
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
    }
    if (editing.value) {
      await useApi(`${apiBase.value}/sessions/${editing.value.id}`, { method: 'PUT', body })
    }
    else {
      await useApi(`${apiBase.value}/class-sections/${selectedSectionId.value}/sessions`, { method: 'POST', body })
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
    await useApi(`${apiBase.value}/sessions/${session.id}`, {
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
        await useApi(`${apiBase.value}/sessions/${session.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.attendance.deleted'), life: 2000 })
        await loadSessions()
      }
      catch (e: any) {
        toast.add({ severity: 'error', summary: t('admin.attendance.saveError'), detail: e?.data?.message, life: 3500 })
      }
    },
  })
}

function stopQrTimer() {
  if (qrTimer) {
    clearInterval(qrTimer)
    qrTimer = null
  }
}

function startQrTimer() {
  stopQrTimer()
  const tick = () => {
    if (!qrExpiresAt.value) {
      qrCountdown.value = ''
      return
    }
    const ms = new Date(qrExpiresAt.value).getTime() - Date.now()
    if (ms <= 0) {
      qrCountdown.value = t('admin.attendance.qrExpired')
      return
    }
    const sec = Math.ceil(ms / 1000)
    const m = Math.floor(sec / 60)
    const s = sec % 60
    qrCountdown.value = `${m}:${String(s).padStart(2, '0')}`
  }
  tick()
  qrTimer = setInterval(tick, 1000)
}

async function renderQr(payload: string) {
  qrDataUrl.value = await QRCode.toDataURL(payload, {
    width: 320,
    margin: 2,
    color: { dark: '#0f172a', light: '#ffffff' },
  })
}

async function openQr(session: OfflineSession) {
  qrSession.value = session
  qrOpen.value = true
  await refreshQr()
}

async function refreshQr() {
  if (!qrSession.value) return
  qrRefreshing.value = true
  try {
    const res = await useApi<{
      qr_payload: string
      qr_expires_at: string
      session: OfflineSession
    }>(`${apiBase.value}/sessions/${qrSession.value.id}/qr`, { method: 'POST', body: { ttl_minutes: 5 } })
    qrPayload.value = res.qr_payload
    qrExpiresAt.value = res.qr_expires_at
    qrSession.value = res.session
    await renderQr(res.qr_payload)
    startQrTimer()
    await loadSessions()
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('admin.attendance.qrError'), detail: e?.data?.message, life: 4000 })
  }
  finally {
    qrRefreshing.value = false
  }
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
    }>(`${apiBase.value}/sessions/${session.id}/attendance`)
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

watch(selectedSectionId, () => { loadSessions() })
watch(qrOpen, (open) => { if (!open) stopQrTimer() })

onMounted(loadSections)
onBeforeUnmount(stopQrTimer)
</script>

<template>
  <div class="page">
    <header class="page-heading">
      <div>
        <h1>{{ t('admin.attendance.title') }}</h1>
        <p>{{ t('admin.attendance.subtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button
          :label="t('admin.attendance.create')"
          icon="pi pi-plus"
          :disabled="!selectedSectionId"
          @click="openCreate"
        />
      </div>
    </header>

    <section class="surface panel">
      <label class="field">
        <span>{{ t('admin.attendance.selectSection') }}</span>
        <Select
          v-model="selectedSectionId"
          :options="sectionOptions"
          option-label="label"
          option-value="value"
          :loading="loadingSections"
          :placeholder="t('admin.attendance.selectSectionPh')"
          filter
          show-clear
          class="w-full"
        />
      </label>
      <p class="hint">{{ t('admin.attendance.radiusHint') }}</p>
    </section>

    <section class="surface panel">
      <DataTable :value="sessions" :loading="loadingSessions" data-key="id" striped-rows>
        <template #empty>
          <div class="empty">{{ selectedSectionId ? t('admin.attendance.empty') : t('admin.attendance.pickSectionFirst') }}</div>
        </template>
        <Column field="title" :header="t('admin.attendance.colTitle')" />
        <Column field="location" :header="t('admin.attendance.colLocation')" />
        <Column :header="t('admin.attendance.colRoom')">
          <template #body="{ data }">{{ data.room || '—' }}</template>
        </Column>
        <Column :header="t('admin.attendance.colTime')">
          <template #body="{ data }">
            {{ new Date(data.start_at).toLocaleString() }} · {{ data.duration }}'
          </template>
        </Column>
        <Column :header="t('admin.attendance.colRadius')">
          <template #body="{ data }">{{ data.check_in_radius_meters || 15 }}m</template>
        </Column>
        <Column :header="t('admin.attendance.colStatus')">
          <template #body="{ data }">
            <Tag :severity="data.is_active ? 'success' : 'secondary'" :value="data.is_active ? t('admin.attendance.open') : t('admin.attendance.closed')" />
          </template>
        </Column>
        <Column :header="t('common.actions')" style="min-width: 280px">
          <template #body="{ data }">
            <div class="row-actions">
              <Button icon="pi pi-qrcode" :label="t('admin.attendance.showQr')" size="small" @click="openQr(data)" />
              <Button icon="pi pi-users" text rounded severity="secondary" @click="openReport(data)" />
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
      </DataTable>
    </section>

    <Dialog v-model:visible="modalOpen" modal :header="editing ? t('admin.attendance.edit') : t('admin.attendance.create')" class="att-dialog">
      <div class="form-grid">
        <label class="field full">
          <span>{{ t('admin.attendance.fieldTitle') }}</span>
          <InputText v-model="form.title" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.attendance.fieldLocation') }}</span>
          <InputText v-model="form.location" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.attendance.fieldRoom') }}</span>
          <InputText v-model="form.room" :placeholder="t('admin.attendance.fieldRoomPh')" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.fieldStart') }}</span>
          <DatePicker v-model="form.start_at" show-time hour-format="24" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.fieldDuration') }}</span>
          <InputNumber v-model="form.duration" :min="1" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.fieldLat') }}</span>
          <InputNumber v-model="form.latitude" :max-fraction-digits="7" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.fieldLng') }}</span>
          <InputNumber v-model="form.longitude" :max-fraction-digits="7" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.attendance.fieldRadius') }}</span>
          <InputNumber v-model="form.check_in_radius_meters" :min="5" :max="500" suffix=" m" class="w-full" />
        </label>
        <div class="field">
          <span>&nbsp;</span>
          <Button :label="t('admin.attendance.useMyLocation')" icon="pi pi-map-marker" severity="secondary" outlined class="w-full" @click="useMyLocation" />
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" text severity="secondary" @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveSession" />
      </template>
    </Dialog>

    <Dialog v-model:visible="qrOpen" modal :header="t('admin.attendance.qrTitle')" class="qr-dialog">
      <div class="qr-box">
        <p class="qr-meta">{{ qrSession?.title }} · {{ qrSession?.location }}<template v-if="qrSession?.room"> · {{ t('admin.attendance.fieldRoom') }}: {{ qrSession.room }}</template></p>
        <p class="qr-radius">{{ t('admin.attendance.qrRadius', { n: qrSession?.check_in_radius_meters || 15 }) }}</p>
        <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR attendance" class="qr-img">
        <p class="qr-count">{{ t('admin.attendance.qrCountdown') }}: <strong>{{ qrCountdown }}</strong></p>
        <Button :label="t('admin.attendance.refreshQr')" icon="pi pi-refresh" :loading="qrRefreshing" @click="refreshQr" />
      </div>
    </Dialog>

    <Dialog v-model:visible="reportOpen" modal :header="t('admin.attendance.reportTitle')" class="report-dialog">
      <div v-if="reportSummary" class="summary">
        <Tag :value="`${t('admin.attendance.statusPresent')}: ${reportSummary.present}`" severity="success" />
        <Tag :value="`${t('admin.attendance.statusLate')}: ${reportSummary.late}`" severity="warn" />
        <Tag :value="`${t('admin.attendance.statusAbsent')}: ${reportSummary.absent}`" severity="danger" />
      </div>
      <DataTable :value="reportRows" :loading="reportLoading" data-key="user_id" size="small">
        <Column field="student_code" :header="t('admin.attendance.colCode')" />
        <Column field="name" :header="t('admin.attendance.colStudent')" />
        <Column :header="t('admin.attendance.colStatus')">
          <template #body="{ data }">{{ statusLabel(data.status) }}</template>
        </Column>
        <Column :header="t('admin.attendance.colDistance')">
          <template #body="{ data }">{{ data.distance_meters != null ? `${data.distance_meters}m` : '—' }}</template>
        </Column>
        <Column :header="t('admin.attendance.colCheckedIn')">
          <template #body="{ data }">{{ data.checked_in_at ? new Date(data.checked_in_at).toLocaleString() : '—' }}</template>
        </Column>
      </DataTable>
    </Dialog>
  </div>
</template>

<style scoped>
.panel { padding: 16px; margin-bottom: 14px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.hint { margin: 10px 0 0; color: var(--text-muted); font-size: .85rem; }
.empty { padding: 24px; text-align: center; color: var(--text-muted); }
.row-actions { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; min-width: min(560px, 80vw); }
.qr-box { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 8px 12px 4px; }
.qr-img { width: 280px; height: 280px; border-radius: 12px; border: 1px solid var(--border); }
.qr-meta { margin: 0; font-weight: 700; }
.qr-radius, .qr-count { margin: 0; color: var(--text-muted); font-size: .9rem; }
.summary { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.att-dialog :deep(.p-dialog-content),
.qr-dialog :deep(.p-dialog-content),
.report-dialog :deep(.p-dialog-content) { overflow: auto; }
@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; min-width: 0; }
}
</style>
