<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import QRCode from 'qrcode'

const props = defineProps<{
  visible: boolean
  refreshUrl: string
  title?: string
  mode?: 'manual' | 'rotating' | 'static' | string | null
  rotateSeconds?: number | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
}>()

const { t } = useI18n()
const toast = useToast()

const open = computed({
  get: () => props.visible,
  set: (v: boolean) => emit('update:visible', v),
})

const qrDataUrl = ref('')
const qrExpiresAt = ref<string | null>(null)
const qrCountdown = ref('')
const qrRefreshing = ref(false)
const activeMode = ref('manual')
const activeRotateSeconds = ref(60)
const radius = ref(15)

let countdownTimer: ReturnType<typeof setInterval> | null = null
let rotateTimer: ReturnType<typeof setInterval> | null = null

function stopTimers() {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
  if (rotateTimer) {
    clearInterval(rotateTimer)
    rotateTimer = null
  }
}

function tickCountdown() {
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

async function refreshQr(silent = false) {
  if (!props.refreshUrl) return
  qrRefreshing.value = true
  try {
    const res = await useApi<{
      qr_payload: string
      qr_expires_at: string
      qr_mode?: string
      qr_rotate_seconds?: number
      check_in_radius_meters?: number
    }>(props.refreshUrl, { method: 'POST', body: {} })

    activeMode.value = res.qr_mode || props.mode || 'manual'
    activeRotateSeconds.value = res.qr_rotate_seconds || props.rotateSeconds || 60
    radius.value = res.check_in_radius_meters || 15
    qrExpiresAt.value = res.qr_expires_at
    qrDataUrl.value = await QRCode.toDataURL(res.qr_payload, {
      width: 320,
      margin: 2,
      color: { dark: '#0b3d2e', light: '#ffffff' },
    })
    tickCountdown()
    scheduleRotate()
  }
  catch (e: any) {
    if (!silent) {
      toast.add({
        severity: 'error',
        summary: t('admin.attendance.qrError'),
        detail: e?.data?.message,
        life: 4000,
      })
    }
  }
  finally {
    qrRefreshing.value = false
  }
}

function scheduleRotate() {
  if (rotateTimer) {
    clearInterval(rotateTimer)
    rotateTimer = null
  }
  if (activeMode.value !== 'rotating') return
  const ms = Math.max(15, activeRotateSeconds.value) * 1000
  rotateTimer = setInterval(() => {
    void refreshQr(true)
  }, ms)
}

watch(
  () => props.visible,
  async (v) => {
    stopTimers()
    if (!v) return
    activeMode.value = props.mode || 'manual'
    activeRotateSeconds.value = props.rotateSeconds || 60
    countdownTimer = setInterval(tickCountdown, 1000)
    await refreshQr()
  },
)

onBeforeUnmount(stopTimers)

const modeLabel = computed(() => {
  if (activeMode.value === 'rotating') return t('admin.builder.fields.qrModeRotating')
  if (activeMode.value === 'static') return t('admin.builder.fields.qrModeStatic')
  return t('admin.builder.fields.qrModeManual')
})
</script>

<template>
  <Dialog
    v-model:visible="open"
    modal
    :header="title || t('admin.attendance.qrTitle')"
    :style="{ width: 'min(420px, 96vw)' }"
    @hide="stopTimers"
  >
    <div class="qr-box">
      <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR attendance" class="qr-img">
      <ProgressSpinner v-else style="width:48px;height:48px" stroke-width="4" />
      <p class="qr-meta">{{ t('admin.attendance.qrRadius', { n: radius }) }}</p>
      <p class="qr-meta">{{ modeLabel }}</p>
      <p v-if="activeMode === 'rotating'" class="qr-meta muted">
        {{ t('admin.builder.fields.qrRotateHint', { n: activeRotateSeconds }) }}
      </p>
      <p class="qr-count">
        <span>{{ t('admin.attendance.qrCountdown') }}:</span>
        <strong>{{ qrCountdown || '—' }}</strong>
      </p>
    </div>
    <template #footer>
      <Button
        :label="t('admin.attendance.refreshQr')"
        icon="pi pi-refresh"
        :loading="qrRefreshing"
        :disabled="activeMode === 'static'"
        @click="refreshQr()"
      />
      <Button :label="t('common.close')" severity="secondary" text @click="open = false" />
    </template>
  </Dialog>
</template>

<style scoped>
.qr-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.55rem;
  padding: 0.5rem 0 0.25rem;
}
.qr-img {
  width: 280px;
  height: 280px;
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--p-surface-border) 80%, transparent);
  background: #fff;
}
.qr-meta {
  margin: 0;
  font-size: 0.88rem;
  color: var(--p-text-color);
}
.qr-meta.muted {
  color: var(--p-text-muted-color);
  font-size: 0.8rem;
}
.qr-count {
  margin: 0.15rem 0 0;
  display: flex;
  gap: 0.4rem;
  align-items: baseline;
  font-size: 0.92rem;
}
</style>
