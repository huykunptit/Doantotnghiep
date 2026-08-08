<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'student',
  middleware: ['auth', 'student'],
})

interface AttendanceItem {
  id: number
  status: string
  checked_in_at?: string | null
  device_info?: string | null
  offline_session?: {
    id?: number
    title?: string
    location?: string
    start_at?: string
    lesson_title?: string
    course_title?: string
  } | null
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(true)
const checking = ref(false)
const history = ref<AttendanceItem[]>([])
const qrToken = ref('')
const latitude = ref<number | null>(null)
const longitude = ref<number | null>(null)
const locating = ref(false)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmt(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function statusLabel(status: string) {
  const key = `student.attendance.statuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ history: AttendanceItem[] }>('/me/attendance')
    history.value = res.history || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.attendance.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function captureLocation() {
  if (!import.meta.client || !navigator.geolocation) {
    toast.add({ severity: 'warn', summary: t('student.attendance.geoUnsupported'), life: 3000 })
    return
  }
  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      latitude.value = pos.coords.latitude
      longitude.value = pos.coords.longitude
      locating.value = false
      toast.add({ severity: 'success', summary: t('student.attendance.geoOk'), life: 2000 })
    },
    () => {
      locating.value = false
      toast.add({ severity: 'error', summary: t('student.attendance.geoError'), life: 3000 })
    },
    { enableHighAccuracy: true, timeout: 10000 },
  )
}

async function checkIn() {
  const token = qrToken.value.trim()
  if (!token) {
    toast.add({ severity: 'warn', summary: t('student.attendance.tokenRequired'), life: 2500 })
    return
  }
  if (latitude.value === null || longitude.value === null) {
    toast.add({ severity: 'warn', summary: t('student.attendance.locationRequired'), life: 2500 })
    return
  }
  checking.value = true
  try {
    const res = await useApi<{ message?: string }>('/me/attendance/check-in', {
      method: 'POST',
      body: {
        qr_token: token,
        latitude: latitude.value,
        longitude: longitude.value,
        device_info: import.meta.client ? navigator.userAgent.slice(0, 180) : null,
      },
    })
    toast.add({
      severity: 'success',
      summary: res.message || t('student.attendance.checkInOk'),
      life: 3000,
    })
    qrToken.value = ''
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.attendance.checkInError'),
      detail: error?.data?.message,
      life: 4000,
    })
  }
  finally {
    checking.value = false
  }
}

onMounted(() => {
  load()
  captureLocation()
})
</script>

<template>
  <div class="page">
    <section class="panel checkin">
      <h2>{{ t('student.attendance.checkInTitle') }}</h2>
      <p class="hint">{{ t('student.attendance.checkInHint') }}</p>
      <div class="form">
        <InputText
          v-model="qrToken"
          class="w-full"
          :placeholder="t('student.attendance.tokenPh')"
        />
        <div class="geo">
          <span v-if="latitude !== null && longitude !== null">
            {{ t('student.attendance.coords', { lat: latitude.toFixed(5), lng: longitude.toFixed(5) }) }}
          </span>
          <span v-else>{{ t('student.attendance.noCoords') }}</span>
          <Button
            :label="t('student.attendance.getLocation')"
            icon="pi pi-map-marker"
            severity="secondary"
            outlined
            size="small"
            :loading="locating"
            @click="captureLocation"
          />
        </div>
        <Button
          :label="t('student.attendance.checkIn')"
          icon="pi pi-check"
          :loading="checking"
          @click="checkIn"
        />
      </div>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>{{ t('student.attendance.history') }}</h2>
        <Button icon="pi pi-refresh" text rounded severity="secondary" :loading="loading" @click="load" />
      </div>
      <DataTable :value="history" :loading="loading" data-key="id" striped-rows>
        <Column :header="t('student.attendance.session')" style="min-width:200px">
          <template #body="{ data }">
            <strong>{{ data.offline_session?.title || data.offline_session?.lesson_title || '—' }}</strong>
            <small class="muted">{{ data.offline_session?.course_title || '' }}</small>
          </template>
        </Column>
        <Column :header="t('student.attendance.location')" style="min-width:120px">
          <template #body="{ data }">{{ data.offline_session?.location || '—' }}</template>
        </Column>
        <Column :header="t('student.attendance.status')" style="width:110px">
          <template #body="{ data }">
            <Tag
              :value="statusLabel(data.status)"
              :severity="data.status === 'present' ? 'success' : data.status === 'late' ? 'warn' : 'secondary'"
            />
          </template>
        </Column>
        <Column :header="t('student.attendance.time')" style="min-width:140px">
          <template #body="{ data }">{{ fmt(data.checked_in_at) }}</template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('student.attendance.empty') }}</div>
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.panel { border: 1px solid var(--p-content-border-color); border-radius: 12px; background: var(--p-content-background); padding: 1rem; }
.panel h2 { margin: 0 0 .5rem; font-size: 1.05rem; }
.hint { margin: 0 0 .85rem; color: var(--p-text-muted-color); font-size: .9rem; }
.form { display: flex; flex-direction: column; gap: .75rem; max-width: 520px; }
.geo { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; font-size: .85rem; color: var(--p-text-muted-color); }
.panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .5rem; }
.panel-head h2 { margin: 0; }
.muted { display: block; color: var(--p-text-muted-color); font-size: .8rem; }
.empty { padding: 1rem; text-align: center; color: var(--p-text-muted-color); }
</style>
