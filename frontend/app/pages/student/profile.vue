<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface NamedRef { id: number, code?: string | null, name?: string | null }

interface LearnerProfileUser {
  id: number
  name: string
  student_code: string | null
  program: NamedRef | null
  major: NamedRef | null
  cohort: NamedRef | null
  administrative_class: NamedRef | null
}

interface LearnerProfileResponse {
  user: LearnerProfileUser
}

const { t, locale } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const { settings, load: loadSiteSettings } = useSiteSettings()

const loading = ref(true)
const profileUser = ref<LearnerProfileUser | null>(null)

const displayName = computed(() => profileUser.value?.name || auth.user?.name || '—')
const studentCode = computed(() => profileUser.value?.student_code || auth.user?.student_code || '—')
const className = computed(() => profileUser.value?.administrative_class?.code || profileUser.value?.administrative_class?.name || '—')
const programName = computed(() => profileUser.value?.program?.name || '—')
const majorName = computed(() => profileUser.value?.major?.name || null)
const programMajorLabel = computed(() => {
  if (programName.value !== '—' && majorName.value) return `${programName.value} — ${majorName.value}`
  return majorName.value || programName.value
})
const cohortName = computed(() => profileUser.value?.cohort?.name || null)

const dobLabel = computed(() => {
  const raw = auth.user?.date_of_birth
  if (!raw) return null
  const date = new Date(raw)
  if (Number.isNaN(date.getTime())) return null
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(date)
})

const institutionName = computed(() => settings.value.site_name || t('student.idCard.defaultInstitution'))

/** Deterministic pseudo-barcode bars derived from the student code — no external QR/barcode dependency needed. */
const barcodeBars = computed(() => {
  const code = studentCode.value === '—' ? '00000000' : studentCode.value
  const chars = code.split('')
  if (!chars.length) return []
  return chars.map((ch, index) => {
    const code0 = ch.charCodeAt(0) + index
    return {
      width: 2 + (code0 % 4),
      tall: code0 % 5 !== 0,
    }
  })
})

async function loadLearnerProfile() {
  loading.value = true
  try {
    const res = await useApi<LearnerProfileResponse>('/me/learner-profile')
    profileUser.value = res.user
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.idCard.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function printCard() {
  window.print()
}

onMounted(async () => {
  await Promise.all([loadLearnerProfile(), loadSiteSettings()])
})
</script>

<template>
  <div class="page id-card-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.idCard.title') }}</h1>
        <p>{{ t('student.idCard.subtitle') }}</p>
      </div>
      <Button
        :label="t('student.idCard.print')"
        icon="pi pi-print"
        severity="secondary"
        outlined
        class="print-btn"
        @click="printCard"
      />
    </header>

    <div v-if="loading" class="empty">…</div>

    <div v-else class="card-stage">
      <div class="id-card" id="student-id-card">
        <div class="watermark">{{ t('student.idCard.cardTitle') }}</div>

        <div class="card-header">
          <Avatar
            v-if="settings.logo"
            :image="settings.logo"
            shape="square"
            class="inst-logo"
          />
          <i v-else class="pi pi-building-columns inst-logo-fallback" />
          <div class="header-text">
            <strong class="inst-name">{{ institutionName }}</strong>
            <span class="card-title">{{ t('student.idCard.cardTitle') }}</span>
            <span class="card-subtitle">{{ t('student.idCard.cardSubtitle') }}</span>
          </div>
        </div>

        <div class="card-photo-row">
          <div class="photo-frame">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="displayName">
            <i v-else class="pi pi-user photo-fallback" />
          </div>
          <div class="name-block">
            <span class="name-label">{{ t('student.idCard.fullName') }}</span>
            <strong class="name-value">{{ displayName }}</strong>
            <span v-if="cohortName" class="cohort-chip">{{ cohortName }}</span>
          </div>
        </div>

        <dl class="card-fields">
          <div class="field-row">
            <dt>{{ t('student.idCard.studentCode') }}</dt>
            <dd class="mono">{{ studentCode }}</dd>
          </div>
          <div v-if="dobLabel" class="field-row">
            <dt>{{ t('student.idCard.dob') }}</dt>
            <dd>{{ dobLabel }}</dd>
          </div>
          <div class="field-row">
            <dt>{{ t('student.idCard.className') }}</dt>
            <dd>{{ className }}</dd>
          </div>
          <div class="field-row">
            <dt>{{ t('student.idCard.program') }}</dt>
            <dd>{{ programMajorLabel || '—' }}</dd>
          </div>
        </dl>

        <div class="barcode-block">
          <div class="barcode-bars">
            <span
              v-for="(bar, index) in barcodeBars"
              :key="index"
              class="bar"
              :class="{ short: !bar.tall }"
              :style="{ width: `${bar.width}px` }"
            />
          </div>
          <span class="barcode-code">{{ studentCode }}</span>
        </div>
      </div>

      <p class="card-hint">{{ t('student.idCard.hint') }}</p>
    </div>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 18px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }

.card-stage { display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 10px 0 30px; }

.id-card {
  position: relative;
  width: min(340px, 90vw);
  border-radius: 20px;
  overflow: hidden;
  background: linear-gradient(165deg, var(--brand) 0%, var(--brand-hover) 55%, #0b3b36 100%);
  color: #fff;
  box-shadow: 0 20px 45px -18px color-mix(in srgb, var(--brand) 65%, black 20%), 0 2px 8px rgba(0, 0, 0, .12);
  padding: 20px 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.watermark {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.6rem;
  font-weight: 900;
  letter-spacing: .1em;
  color: rgba(255, 255, 255, .08);
  transform: rotate(-22deg) scale(1.3);
  pointer-events: none;
  white-space: nowrap;
  user-select: none;
}

.card-header { display: flex; align-items: center; gap: 10px; position: relative; z-index: 1; }
.inst-logo, .inst-logo-fallback {
  width: 38px; height: 38px; border-radius: 10px; flex: 0 0 38px;
  background: rgba(255, 255, 255, .15);
}
.inst-logo-fallback { display: grid; place-items: center; font-size: 1.1rem; }
.header-text { display: flex; flex-direction: column; min-width: 0; }
.inst-name {
  font-size: .72rem; font-weight: 800; letter-spacing: .03em; text-transform: uppercase;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.card-title { font-size: 1.05rem; font-weight: 900; letter-spacing: .04em; margin-top: 2px; }
.card-subtitle { font-size: .62rem; font-weight: 600; letter-spacing: .18em; opacity: .75; }

.card-photo-row { display: flex; align-items: center; gap: 14px; position: relative; z-index: 1; }
.photo-frame {
  width: 76px; height: 92px; border-radius: 10px; overflow: hidden; flex: 0 0 76px;
  background: rgba(255, 255, 255, .18); border: 2px solid rgba(255, 255, 255, .55);
  display: grid; place-items: center;
}
.photo-frame img { width: 100%; height: 100%; object-fit: cover; }
.photo-fallback { font-size: 2rem; opacity: .7; }
.name-block { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.name-label { font-size: .62rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .7; }
.name-value { font-size: 1.05rem; font-weight: 800; line-height: 1.2; overflow-wrap: anywhere; }
.cohort-chip {
  align-self: flex-start; margin-top: 2px; padding: 2px 8px; border-radius: 999px;
  background: rgba(255, 255, 255, .18); font-size: .68rem; font-weight: 700;
}

.card-fields { display: flex; flex-direction: column; gap: 7px; margin: 0; position: relative; z-index: 1; }
.field-row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; align-items: baseline; }
.field-row dt { margin: 0; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; opacity: .75; }
.field-row dd { margin: 0; font-size: .88rem; font-weight: 700; overflow-wrap: anywhere; }
.field-row dd.mono { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; letter-spacing: .04em; }

.barcode-block {
  position: relative; z-index: 1; margin-top: 4px; padding-top: 12px;
  border-top: 1px dashed rgba(255, 255, 255, .3);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  background: rgba(255, 255, 255, .08); border-radius: 10px; padding: 12px 10px 10px;
}
.barcode-bars { display: flex; align-items: flex-end; gap: 1.5px; height: 34px; }
.bar { display: block; height: 100%; background: #fff; border-radius: 1px; }
.bar.short { height: 62%; opacity: .75; }
.barcode-code { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: .78rem; font-weight: 700; letter-spacing: .12em; }

.card-hint { color: var(--text-muted); font-size: .82rem; text-align: center; max-width: 340px; }

@media print {
  .workspace-head, .card-hint, .print-btn { display: none !important; }
  .page, .card-stage { padding: 0; gap: 0; }
  .id-card { box-shadow: none; }
}
</style>
