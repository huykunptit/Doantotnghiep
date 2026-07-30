<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface NamedRef {
  id: number
  code?: string | null
  name?: string | null
  start_year?: number | null
  end_year?: number | null
  program_type?: NamedRef | null
}

interface LearnerProfileUser {
  id: number
  name: string
  student_code: string | null
  gender?: string | null
  date_of_birth?: string | null
  hometown?: string | null
  permanent_address?: string | null
  nationality?: string | null
  study_status?: string | null
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
const className = computed(() =>
  profileUser.value?.administrative_class?.code
  || profileUser.value?.administrative_class?.name
  || '—',
)

/** Physical cards use degree level (Đại học); training mode like "Chính quy" maps to that default. */
const programTypeName = computed(() => {
  const type = profileUser.value?.program?.program_type
  if (!type?.name || type.code === 'CHINH_QUY') return t('student.idCard.defaultProgramLevel')
  return type.name
})

const majorLabel = computed(() => {
  const major = profileUser.value?.major
  if (!major) return '—'
  return major.code || major.name || '—'
})

const permanentResidence = computed(() => {
  const fromProfile = profileUser.value?.hometown || profileUser.value?.permanent_address
  if (fromProfile) return fromProfile
  return auth.user?.hometown || auth.user?.permanent_address || '—'
})

const cohortYears = computed(() => {
  const cohort = profileUser.value?.cohort
  if (!cohort) return '—'
  if (cohort.start_year && cohort.end_year) return `${cohort.start_year}-${cohort.end_year}`
  return cohort.code || cohort.name || '—'
})

const dobLabel = computed(() => {
  const raw = profileUser.value?.date_of_birth || auth.user?.date_of_birth
  if (!raw) return '—'
  const date = new Date(raw)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(date)
})

const genderLabel = computed(() => {
  const gender = (profileUser.value?.gender || auth.user?.gender || '').toLowerCase()
  if (gender === 'male' || gender === 'nam') return t('student.idCard.genderMale')
  if (gender === 'female' || gender === 'nu' || gender === 'nữ') return t('student.idCard.genderFemale')
  return gender || '—'
})

const institutionName = computed(() => settings.value.site_name || t('student.idCard.defaultInstitution'))

/** Deterministic pseudo-barcode bars derived from the student code — no external QR/barcode dependency needed. */
const barcodeBars = computed(() => {
  const code = studentCode.value === '—' ? '00000000' : studentCode.value
  const chars = code.split('')
  if (!chars.length) return []
  const expanded: { width: number, tall: boolean }[] = []
  for (let i = 0; i < chars.length; i++) {
    const code0 = chars[i].charCodeAt(0) + i
    expanded.push({ width: 2 + (code0 % 4), tall: code0 % 5 !== 0 })
    expanded.push({ width: 1 + (code0 % 2), tall: true })
  }
  return expanded
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
        class="print-btn"
        @click="printCard"
      />
    </header>

    <div v-if="loading" class="empty">…</div>

    <div v-else class="card-stage">
      <div id="student-id-card" class="id-card">
        <div class="watermark" aria-hidden="true">
          <i class="pi pi-building-columns" />
        </div>

        <div class="card-header">
          <Avatar
            v-if="settings.logo"
            :image="settings.logo"
            shape="square"
            class="inst-logo"
          />
          <div v-else class="inst-logo-fallback">
            <i class="pi pi-building-columns" />
          </div>
          <div class="header-text">
            <strong class="inst-name">{{ institutionName }}</strong>
          </div>
        </div>

        <div class="title-band">
          <span class="card-title">{{ t('student.idCard.cardTitle') }}</span>
          <span class="card-subtitle">{{ t('student.idCard.cardSubtitle') }}</span>
        </div>

        <div class="card-body">
          <div class="photo-frame">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" :alt="displayName">
            <i v-else class="pi pi-user photo-fallback" />
          </div>

          <dl class="card-fields">
            <div class="field-row">
              <dt>{{ t('student.idCard.fullName') }}</dt>
              <dd class="name-value">{{ displayName }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.dob') }}</dt>
              <dd>{{ dobLabel }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.gender') }}</dt>
              <dd>{{ genderLabel }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.residence') }}</dt>
              <dd>{{ permanentResidence }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.className') }}</dt>
              <dd>{{ className }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.programLevel') }}</dt>
              <dd>{{ programTypeName }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.major') }}</dt>
              <dd>{{ majorLabel }}</dd>
            </div>
            <div class="field-row">
              <dt>{{ t('student.idCard.cohort') }}</dt>
              <dd>{{ cohortYears }}</dd>
            </div>
          </dl>
        </div>

        <div class="card-footer">
          <div class="student-code-row">
            <span class="code-label">{{ t('student.idCard.studentCode') }}</span>
            <strong class="code-value">{{ studentCode }}</strong>
          </div>
          <div class="barcode-block" aria-hidden="true">
            <div class="barcode-bars">
              <span
                v-for="(bar, index) in barcodeBars"
                :key="index"
                class="bar"
                :class="{ short: !bar.tall }"
                :style="{ width: `${bar.width}px` }"
              />
            </div>
          </div>
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
  --card-red: #c62828;
  --card-blue: #1a237e;
  --card-ink: #1f2937;
  position: relative;
  width: min(360px, 92vw);
  border-radius: 14px;
  overflow: hidden;
  background:
    radial-gradient(ellipse 80% 50% at 70% 55%, rgba(198, 40, 40, .06), transparent 70%),
    linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
  color: var(--card-ink);
  border: 1px solid #e5e7eb;
  box-shadow: 0 18px 40px -20px rgba(15, 23, 42, .35), 0 2px 8px rgba(0, 0, 0, .06);
  padding: 16px 18px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.watermark {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  z-index: 0;
}
.watermark i {
  font-size: 7.5rem;
  color: rgba(198, 40, 40, .07);
  transform: translateY(8px);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 10px;
  position: relative;
  z-index: 1;
}
.inst-logo, .inst-logo-fallback {
  width: 42px; height: 42px; border-radius: 8px; flex: 0 0 42px;
  object-fit: contain;
}
.inst-logo-fallback {
  display: grid; place-items: center;
  background: color-mix(in srgb, var(--card-red) 12%, white);
  color: var(--card-red);
  font-size: 1.15rem;
}
.header-text { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.inst-name {
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--card-blue);
  line-height: 1.35;
}

.title-band {
  position: relative;
  z-index: 1;
  text-align: center;
  padding: 6px 0 8px;
  border-top: 2px solid var(--card-red);
  border-bottom: 1px solid #e8eaed;
}
.card-title {
  display: block;
  font-size: 1.15rem;
  font-weight: 900;
  letter-spacing: .08em;
  color: var(--card-red);
}
.card-subtitle {
  display: block;
  margin-top: 2px;
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .16em;
  color: #6b7280;
  text-transform: uppercase;
}

.card-body {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  position: relative;
  z-index: 1;
}
.photo-frame {
  width: 88px; height: 110px; border-radius: 4px; overflow: hidden; flex: 0 0 88px;
  background: #e8eef5; border: 1px solid #c5d0dc;
  display: grid; place-items: center;
}
.photo-frame img { width: 100%; height: 100%; object-fit: cover; }
.photo-fallback { font-size: 2.2rem; color: #94a3b8; }

.card-fields {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
  flex: 1;
  min-width: 0;
}
.field-row {
  display: grid;
  grid-template-columns: 78px 1fr;
  gap: 4px;
  align-items: baseline;
}
.field-row dt {
  margin: 0;
  font-size: .68rem;
  font-weight: 600;
  color: #4b5563;
}
.field-row dd {
  margin: 0;
  font-size: .8rem;
  font-weight: 700;
  color: var(--card-blue);
  overflow-wrap: anywhere;
  line-height: 1.25;
}
.field-row dd.name-value {
  font-size: .88rem;
  font-weight: 800;
}

.card-footer {
  position: relative;
  z-index: 1;
  margin-top: 2px;
  padding-top: 8px;
  border-top: 1px dashed #d1d5db;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.student-code-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.code-label {
  font-size: .68rem;
  font-weight: 600;
  color: #4b5563;
}
.code-value {
  font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
  font-size: .95rem;
  font-weight: 800;
  letter-spacing: .04em;
  color: var(--card-red);
}
.barcode-block {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  padding: 8px 10px;
  display: flex;
  justify-content: center;
}
.barcode-bars {
  display: flex;
  align-items: flex-end;
  gap: 1px;
  height: 36px;
  width: 100%;
  justify-content: center;
}
.bar { display: block; height: 100%; background: #111; border-radius: 0; }
.bar.short { height: 62%; }

.card-hint { color: var(--text-muted); font-size: .82rem; text-align: center; max-width: 360px; }

@media print {
  .workspace-head, .card-hint, .print-btn { display: none !important; }
  .page, .card-stage { padding: 0; gap: 0; }
  .id-card { box-shadow: none; border-color: #ccc; }
}
</style>
