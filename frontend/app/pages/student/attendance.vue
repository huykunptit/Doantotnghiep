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
const history = ref<AttendanceItem[]>([])

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

onMounted(() => {
  load()
})
</script>

<template>
  <div class="page">
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
          <CommonEmptyState :description="t('student.attendance.empty')" />
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.panel { border: 1px solid var(--p-content-border-color); border-radius: 12px; background: var(--p-content-background); padding: 1rem; }
.panel h2 { margin: 0 0 .5rem; font-size: 1.05rem; }
.panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .5rem; }
.panel-head h2 { margin: 0; }
.muted { display: block; color: var(--p-text-muted-color); font-size: .8rem; }

</style>
