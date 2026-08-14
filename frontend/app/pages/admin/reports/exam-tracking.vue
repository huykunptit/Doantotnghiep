<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface ExamRow {
  id: number
  title: string
  status: string
  duration?: number | null
  starts_at?: string | null
  ends_at?: string | null
  exam_enrollments_count?: number
  quiz?: { id: number } | null
}

interface ViolationItem {
  id: number
  type: string
  severity: string
  snapshot_url?: string | null
  metadata?: Record<string, unknown> | null
  created_at?: string | null
}

interface LiveAttempt {
  id: number
  status: string
  score?: number | null
  violations_count?: number
  recent_violations?: ViolationItem[]
  user?: { name?: string, email?: string } | null
}

interface LiveMonitor {
  exam?: { id: number, title: string, status: string }
  attempts?: LiveAttempt[]
  summary?: {
    total?: number
    in_progress?: number
    paused?: number
    submitted?: number
    force_stopped?: number
  }
}

const { t, locale } = useI18n()
const toast = useToast()
const loading = ref(false)
const monitorLoading = ref(false)

const exams = ref<ExamRow[]>([])
const monitorOpen = ref(false)
const monitorExam = ref<ExamRow | null>(null)
const monitorData = ref<LiveMonitor | null>(null)

const violationsPopover = ref()
const activeViolations = ref<ViolationItem[]>([])

let monitorPollTimer: ReturnType<typeof setInterval> | null = null
const MONITOR_POLL_MS = 9000

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).format(new Date(value))
}

function statusTone(status: string) {
  const map: Record<string, string> = {
    draft: 'tone-draft',
    scheduled: 'tone-scheduled',
    active: 'tone-active',
    closed: 'tone-closed',
    archived: 'tone-archived',
    in_progress: 'tone-active',
    paused: 'tone-pending',
    submitted: 'tone-closed',
    force_stopped: 'tone-failed',
  }
  return map[status] || 'tone-neutral'
}

function examStatusLabel(status: string) {
  const key = `admin.reports.examStatuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

function attemptStatusLabel(status: string) {
  const key = `admin.reports.examTracking.attemptStatuses.${status}`
  const translated = t(key)
  return translated === key ? status : translated
}

function violationTypeLabel(type: string) {
  const key = `admin.reports.examTracking.violationTypes.${type}`
  const translated = t(key)
  return translated === key ? type : translated
}

function violationSeverityTone(severity: string) {
  return severity === 'critical' ? 'danger' : 'warn'
}

function fmtDateTime(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value))
}

function showViolations(event: Event, attempt: LiveAttempt) {
  activeViolations.value = attempt.recent_violations || []
  violationsPopover.value?.toggle(event)
}

async function load() {
  loading.value = true
  try {
    exams.value = await useApi<ExamRow[]>('/exams/standalone')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.reports.common.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

async function openMonitor(exam: ExamRow) {
  if (!exam.quiz) {
    toast.add({ severity: 'warn', summary: t('admin.reports.examTracking.noQuiz'), life: 2800 })
    return
  }
  monitorExam.value = exam
  monitorOpen.value = true
  monitorData.value = null
  monitorLoading.value = true
  try {
    monitorData.value = await useApi<LiveMonitor>(`/exams/${exam.id}/live-monitor`)
    startMonitorPoll()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.reports.examTracking.monitorError'),
      detail: error?.data?.message,
      life: 3500,
    })
    monitorOpen.value = false
  }
  finally {
    monitorLoading.value = false
  }
}

/** Silent background refresh while the dialog is open — no spinner, no toast on failure (just retries next tick). */
async function refreshMonitorSilently() {
  if (!monitorExam.value) return
  try {
    monitorData.value = await useApi<LiveMonitor>(`/exams/${monitorExam.value.id}/live-monitor`)
  }
  catch { /* transient — next poll tick will retry */ }
}

function startMonitorPoll() {
  stopMonitorPoll()
  monitorPollTimer = setInterval(refreshMonitorSilently, MONITOR_POLL_MS)
}

function stopMonitorPoll() {
  if (monitorPollTimer) {
    clearInterval(monitorPollTimer)
    monitorPollTimer = null
  }
}

watch(monitorOpen, (open) => {
  if (!open) stopMonitorPoll()
})

onBeforeUnmount(stopMonitorPoll)

onMounted(load)
</script>

<template>
  <div class="page report-page">
    <header class="workspace-head">
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </header>

    <section class="table-panel">
      <div class="panel-head">
        <strong>{{ t('admin.reports.examTracking.examList') }}</strong>
      </div>
      <DataTable :value="exams" :loading="loading" data-key="id" striped-rows>
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="title" :header="t('admin.reports.exams.examTitle')" style="min-width:180px" />
        <Column :header="t('admin.orders.paymentStatus')" style="width:110px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">{{ examStatusLabel(data.status) }}</span>
          </template>
        </Column>
        <Column :header="t('admin.reports.exams.enrolled')" style="width:90px">
          <template #body="{ data }">{{ data.exam_enrollments_count ?? 0 }}</template>
        </Column>
        <Column :header="t('admin.reports.exams.schedule')" style="min-width:150px">
          <template #body="{ data }">
            <small>{{ fmtDate(data.starts_at) }} → {{ fmtDate(data.ends_at) }}</small>
          </template>
        </Column>
        <Column :header="t('admin.reports.exams.quiz')" style="width:90px">
          <template #body="{ data }">
            <Tag v-if="data.quiz" :value="t('admin.reports.exams.hasQuiz')" severity="success" />
            <Tag v-else :value="t('admin.reports.exams.noQuiz')" severity="warn" />
          </template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:7rem">
          <template #body="{ data }">
            <Button
              icon="pi pi-desktop"
              text
              rounded
              severity="secondary"
              :disabled="!data.quiz"
              :aria-label="t('admin.reports.examTracking.monitor')"
              @click="openMonitor(data)"
            />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="monitorOpen"
      modal
      :header="t('admin.reports.examTracking.monitorTitle', { title: monitorExam?.title || '' })"
      :style="{ width: 'min(760px, 96vw)' }"
      :dismissable-mask="true"
      class="scroll-fix-modal"
    >
      <div v-if="monitorLoading" class="monitor-loading">
        <ProgressSpinner style="width:32px;height:32px" stroke-width="4" />
      </div>
      <template v-else-if="monitorData">
        <div class="summary-rail">
          <div class="summary-item">
            <span>{{ t('admin.reports.examTracking.inProgress') }}</span>
            <strong>{{ monitorData.summary?.in_progress ?? 0 }}</strong>
          </div>
          <div class="summary-item">
            <span>{{ t('admin.reports.examTracking.paused') }}</span>
            <strong>{{ monitorData.summary?.paused ?? 0 }}</strong>
          </div>
          <div class="summary-item">
            <span>{{ t('admin.reports.examTracking.submitted') }}</span>
            <strong>{{ monitorData.summary?.submitted ?? 0 }}</strong>
          </div>
          <div class="summary-item">
            <span>{{ t('admin.reports.examTracking.forceStopped') }}</span>
            <strong>{{ monitorData.summary?.force_stopped ?? 0 }}</strong>
          </div>
        </div>
        <DataTable :value="monitorData.attempts || []" size="small" striped-rows scrollable scroll-height="320px">
          <Column :header="t('admin.orders.buyer')">
            <template #body="{ data }">
              <div class="cell-stack">
                <strong>{{ data.user?.name || '—' }}</strong>
                <small>{{ data.user?.email || '—' }}</small>
              </div>
            </template>
          </Column>
          <Column :header="t('admin.orders.paymentStatus')">
            <template #body="{ data }">
              <span class="pill" :class="statusTone(data.status)">{{ attemptStatusLabel(data.status) }}</span>
            </template>
          </Column>
          <Column field="score" :header="t('admin.reports.examTracking.score')" style="width:5rem">
            <template #body="{ data }">{{ data.score ?? '—' }}</template>
          </Column>
          <Column :header="t('admin.reports.examTracking.violations')" style="width:6rem">
            <template #body="{ data }">
              <button
                type="button"
                class="violations-btn"
                :class="{ empty: !data.violations_count }"
                :disabled="!data.violations_count"
                @click="showViolations($event, data)"
              >
                {{ data.violations_count ?? 0 }}
              </button>
            </template>
          </Column>
          <template #empty><CommonEmptyState :description="t('admin.reports.examTracking.noAttempts')" /></template>
        </DataTable>
      </template>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="monitorOpen = false" />
      </template>
    </Dialog>

    <Popover ref="violationsPopover" class="violations-popover">
      <div class="violations-list">
        <div v-if="!activeViolations.length" class="violations-empty">{{ t('admin.reports.examTracking.noViolations') }}</div>
        <div v-for="v in activeViolations" :key="v.id" class="violation-row">
          <Image v-if="v.snapshot_url" :src="v.snapshot_url" preview width="56" class="violation-thumb" />
          <div class="violation-info">
            <div class="violation-head">
              <strong>{{ violationTypeLabel(v.type) }}</strong>
              <Tag :value="v.severity" :severity="violationSeverityTone(v.severity)" />
            </div>
            <small>{{ fmtDateTime(v.created_at) }}</small>
          </div>
        </div>
      </div>
    </Popover>
  </div>
</template>

<style scoped>
.scroll-fix-modal :deep(.p-dialog-content) { overflow: auto; }

.report-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: flex-end; gap: 16px; }

.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.panel-head { margin-bottom: 10px; }
.panel-head strong { font-size: .92rem; }

.pill {
  display: inline-flex; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-scheduled { background: #dbeafe; color: #1d4ed8; }
.tone-active { background: #dcfce7; color: #15803d; }
.tone-closed { background: #fee2e2; color: #b91c1c; }
.tone-archived { background: #ede9fe; color: #6d28d9; }
.tone-pending { background: #fef9c3; color: #a16207; }
.tone-failed { background: #fee2e2; color: #b91c1c; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.monitor-loading { display: grid; place-items: center; padding: 32px; }
.summary-rail {
  display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px;
}
.summary-item {
  padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface-subtle);
}
.summary-item span { display: block; color: var(--text-muted); font-size: .72rem; font-weight: 600; }
.summary-item strong { font-size: 1.1rem; font-weight: 700; }
.cell-stack small { display: block; color: var(--text-muted); font-size: .78rem; }

@media (max-width: 720px) { .summary-rail { grid-template-columns: 1fr 1fr; } }

.violations-btn {
  min-width: 2rem; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--border);
  background: var(--surface-subtle); font-weight: 700; font-size: .82rem; cursor: pointer;
}
.violations-btn:not(.empty) { border-color: #fca5a5; background: #fee2e2; color: #b91c1c; }
.violations-btn.empty { cursor: default; color: var(--text-muted); }

.violations-list { display: grid; gap: 10px; width: min(320px, 80vw); }
.violations-empty { color: var(--text-muted); font-size: .85rem; padding: 4px; }
.violation-row { display: flex; gap: 10px; align-items: flex-start; }
.violation-thumb { border-radius: 8px; overflow: hidden; flex: 0 0 auto; }
.violation-info { display: grid; gap: 4px; flex: 1 1 auto; min-width: 0; }
.violation-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.violation-head strong { font-size: .85rem; }
.violation-info small { color: var(--text-muted); font-size: .75rem; }
</style>
