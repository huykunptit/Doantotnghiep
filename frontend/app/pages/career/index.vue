<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface EvalCheck { key: string, ok: boolean, label: string }
interface Evaluation {
  score: number
  checks: EvalCheck[]
  warnings: string[]
  fixes: string[]
  summary: string
  salary_note?: string | null
  target_role?: string | null
  expected_salary?: number | null
  skills_found?: string[]
  reviewed?: boolean
  verdict?: string | null
  overview?: string | null
  strengths?: string[]
  weaknesses?: string[]
  improvements?: string[]
  missing_items?: string[]
  skill_gaps?: string[]
  interview_focus?: string[]
  completeness_score?: number
  explanation_unavailable?: boolean
}
interface CourseCard {
  id: number
  title: string
  slug?: string
  price?: number
  thumbnail?: string | null
  level?: string | null
}
interface CvRow {
  id: number
  source?: string
  skills?: string[]
  target_role?: string | null
  expected_salary?: number | null
  evaluation_json?: Evaluation | null
  profile_json?: Record<string, any> | null
  file_name?: string | null
  parsed_text?: string | null
}

const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const evaluating = ref(false)
const uploading = ref(false)
const deleting = ref(false)
const mode = ref<'choose' | 'upload' | 'form' | 'result'>('choose')
const cv = ref<CvRow | null>(null)
const evaluation = ref<Evaluation | null>(null)
const courses = ref<CourseCard[]>([])
const parseWarning = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const dragOver = ref(false)

const form = reactive({
  full_name: auth.user?.name || '',
  email: auth.user?.email || '',
  phone: '',
  headline: '',
  summary: '',
  skillsText: '',
  education: [{ school: 'PTIT', degree: '', year: '' }],
  experience: [{ company: '', role: '', description: '' }],
  projects: [{ name: '', description: '' }],
  target_role: '',
  expected_salary: 8000000 as number | null,
})

const hasEvaluation = computed(() =>
  !!evaluation.value
  && (evaluation.value.reviewed === true || !!evaluation.value.overview || (evaluation.value.strengths?.length ?? 0) > 0),
)
const skillsPreview = computed(() => (cv.value?.skills || evaluation.value?.skills_found || []).filter(Boolean))
const structureChecks = computed(() => evaluation.value?.checks || [])

function money(n?: number | null) {
  if (n == null) return '—'
  if (Number(n) <= 0) return t('student.catalog.free')
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(n))
}

function levelLabel(level?: string | null) {
  if (!level) return t('career.levelUnset')
  const key = `admin.builder.levels.${level}`
  const translated = t(key)
  return translated === key ? level : translated
}

async function load() {
  loading.value = true
  parseWarning.value = ''
  try {
    const res = await useApi<{
      cv: CvRow | null
      usable?: boolean
      has_unparsed_cv?: boolean
      unparsed_file_name?: string | null
    }>('/career/advisor')

    if (res.has_unparsed_cv) {
      parseWarning.value = t('career.parseFailedHint', {
        file: res.unparsed_file_name || 'CV',
      })
      mode.value = 'choose'
      cv.value = null
      evaluation.value = null
      courses.value = []
      return
    }

    cv.value = res.cv || null
    if (cv.value) {
      const stored = cv.value.evaluation_json || null
      evaluation.value = (stored?.reviewed || stored?.overview) ? stored : null
      form.target_role = cv.value.target_role || ''
      form.expected_salary = cv.value.expected_salary || 8000000
      mode.value = 'result'
      courses.value = []
    }
    else {
      mode.value = 'choose'
      evaluation.value = null
      courses.value = []
    }
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('career.loadError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function saveForm() {
  if (!form.full_name.trim()) {
    toast.add({ severity: 'warn', summary: t('career.nameRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const skills = form.skillsText.split(/[,;\n]+/).map(s => s.trim()).filter(Boolean)
    const res = await useApi<{ cv: CvRow, evaluation: Evaluation }>('/career/cv-form', {
      method: 'POST',
      body: {
        full_name: form.full_name,
        email: form.email,
        phone: form.phone,
        headline: form.headline,
        summary: form.summary,
        skills,
        education: form.education,
        experience: form.experience,
        projects: form.projects,
        target_role: form.target_role || null,
        expected_salary: form.expected_salary,
      },
    })
    cv.value = res.cv
    evaluation.value = (res.evaluation?.reviewed || res.evaluation?.overview) ? res.evaluation : null
    courses.value = []
    parseWarning.value = ''
    mode.value = 'result'
    toast.add({ severity: 'success', summary: t('career.formSaved'), life: 3000 })
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('career.formError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    saving.value = false
  }
}

async function uploadFile(file: File) {
  uploading.value = true
  parseWarning.value = ''
  try {
    const body = new FormData()
    body.append('cv', file)
    const res = await useApi<{
      cv: CvRow
      usable?: boolean
      parse_failed?: boolean
      evaluation?: Evaluation | null
      message?: string
    }>('/career/upload-cv', { method: 'POST', body })

    if (res.parse_failed || res.usable === false) {
      parseWarning.value = res.message || t('career.parseFailed')
      cv.value = null
      evaluation.value = null
      courses.value = []
      mode.value = 'choose'
      toast.add({ severity: 'warn', summary: t('career.parseFailed'), detail: parseWarning.value, life: 5000 })
      return
    }

    cv.value = res.cv
    evaluation.value = (res.evaluation?.reviewed || res.cv.evaluation_json?.reviewed)
      ? (res.evaluation || res.cv.evaluation_json || null)
      : null
    courses.value = []
    mode.value = 'result'
    toast.add({ severity: 'success', summary: t('career.uploadOk'), life: 3000 })
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('career.uploadError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    uploading.value = false
  }
}

function onFileChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) uploadFile(file)
  input.value = ''
}

function onDrop(ev: DragEvent) {
  ev.preventDefault()
  dragOver.value = false
  const file = ev.dataTransfer?.files?.[0]
  if (file) uploadFile(file)
}

function onDragOver(ev: DragEvent) {
  ev.preventDefault()
  dragOver.value = true
}

function onDragLeave(ev: DragEvent) {
  ev.preventDefault()
  dragOver.value = false
}

async function deleteCv() {
  deleting.value = true
  try {
    await useApi('/career/cv', { method: 'DELETE' })
    cv.value = null
    evaluation.value = null
    courses.value = []
    parseWarning.value = ''
    mode.value = 'upload'
    toast.add({ severity: 'success', summary: t('career.deleteOk'), life: 2500 })
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('career.deleteError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    deleting.value = false
  }
}

async function runEvaluate() {
  if (!cv.value) return
  if (!form.target_role.trim()) {
    toast.add({ severity: 'warn', summary: t('career.roleRequired'), life: 2500 })
    return
  }
  evaluating.value = true
  try {
    const res = await useApi<{
      evaluation: Evaluation
      suggested_courses: CourseCard[]
      cv: CvRow
    }>('/career/evaluate', {
      method: 'POST',
      body: {
        target_role: form.target_role || null,
        expected_salary: form.expected_salary,
      },
    })
    evaluation.value = res.evaluation
    courses.value = res.suggested_courses || []
    cv.value = res.cv
    toast.add({ severity: 'success', summary: t('career.evalOk'), life: 2500 })
  }
  catch (e: any) {
    if (e?.data?.parse_failed) {
      parseWarning.value = e?.data?.message || t('career.parseFailed')
      mode.value = 'choose'
      cv.value = null
      evaluation.value = null
      courses.value = []
    }
    toast.add({ severity: 'error', summary: t('career.evalError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    evaluating.value = false
  }
}

async function recommend() {
  await runEvaluate()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">AI Career</span>
        <h1>{{ t('career.title') }}</h1>
        <p>{{ t('career.subtitle') }}</p>
      </div>
    </header>

    <div v-if="loading" class="empty">…</div>

    <div v-else-if="parseWarning" class="banner warn">
      <i class="pi pi-exclamation-triangle" />
      <div class="banner-body">
        <strong>{{ t('career.parseFailed') }}</strong>
        <p>{{ parseWarning }}</p>
        <div class="actions">
          <Button :label="t('career.deleteCv')" icon="pi pi-trash" severity="danger" outlined size="small" :loading="deleting" @click="deleteCv" />
          <Button :label="t('career.uploadAgain')" icon="pi pi-upload" size="small" @click="parseWarning = ''; mode = 'upload'" />
        </div>
      </div>
    </div>

    <section v-if="!loading && mode === 'choose'" class="choose">
      <button type="button" class="choice" @click="mode = 'upload'">
        <i class="pi pi-upload" />
        <strong>{{ t('career.uploadTitle') }}</strong>
        <span>{{ t('career.uploadHint') }}</span>
      </button>
      <button type="button" class="choice preferred" @click="mode = 'form'">
        <i class="pi pi-file-edit" />
        <strong>{{ t('career.formTitle') }}</strong>
        <span>{{ t('career.formHint') }}</span>
        <em>{{ t('career.formRecommended') }}</em>
      </button>
    </section>

    <section v-else-if="mode === 'upload'" class="panel">
      <input ref="fileInput" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="hidden" @change="onFileChange">
      <p>{{ t('career.uploadHint') }}</p>
      <p class="muted">{{ t('career.uploadFormats') }}</p>
      <button
        type="button"
        class="dropzone"
        :class="{ over: dragOver, busy: uploading }"
        :disabled="uploading"
        @click="fileInput?.click()"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <i class="pi pi-cloud-upload" />
        <strong>{{ uploading ? t('career.uploading') : t('career.dropTitle') }}</strong>
        <span>{{ t('career.dropHint') }}</span>
      </button>
      <div class="actions">
        <Button :label="t('career.pickFile')" icon="pi pi-upload" :loading="uploading" @click="fileInput?.click()" />
        <Button :label="t('common.cancel')" severity="secondary" text @click="mode = 'choose'" />
      </div>
    </section>

    <section v-else-if="mode === 'form'" class="panel">
      <div class="form-grid">
        <label class="field"><span>{{ t('career.fullName') }} *</span><InputText v-model="form.full_name" class="w-full" /></label>
        <label class="field"><span>Email</span><InputText v-model="form.email" class="w-full" /></label>
        <label class="field"><span>{{ t('career.phone') }}</span><InputText v-model="form.phone" class="w-full" /></label>
        <label class="field"><span>{{ t('career.headline') }}</span><InputText v-model="form.headline" class="w-full" /></label>
        <label class="field full"><span>{{ t('career.summary') }}</span><CommonRichTextEditor v-model="form.summary" height="160px" /></label>
        <label class="field full"><span>{{ t('career.skills') }}</span><Textarea v-model="form.skillsText" rows="2" class="w-full" :placeholder="t('career.skillsHint')" /></label>
        <label class="field"><span>{{ t('career.school') }}</span><InputText v-model="form.education[0].school" class="w-full" /></label>
        <label class="field"><span>{{ t('career.degree') }}</span><InputText v-model="form.education[0].degree" class="w-full" /></label>
        <label class="field full"><span>{{ t('career.project') }}</span><InputText v-model="form.projects[0].name" class="w-full" /></label>
        <label class="field full"><span>{{ t('career.projectDesc') }}</span><CommonRichTextEditor v-model="form.projects[0].description" height="140px" /></label>
      </div>
      <div class="actions">
        <Button :label="t('career.saveForm')" icon="pi pi-check" :loading="saving" @click="saveForm" />
        <Button :label="t('common.cancel')" severity="secondary" text @click="mode = 'choose'" />
      </div>
    </section>

    <template v-else-if="mode === 'result' && cv">
      <section class="panel cv-card">
        <div>
          <h2>{{ t('career.cvReady') }}</h2>
          <p class="muted">
            <template v-if="cv.file_name">{{ cv.file_name }}</template>
            <template v-else>{{ t('career.fromForm') }}</template>
          </p>
          <div v-if="skillsPreview.length" class="tags">
            <span v-for="s in skillsPreview.slice(0, 10)" :key="s">{{ s }}</span>
          </div>
        </div>
        <div class="cv-actions">
          <Button :label="t('career.deleteCv')" icon="pi pi-trash" severity="danger" outlined :loading="deleting" @click="deleteCv" />
          <Button :label="t('career.uploadAgain')" icon="pi pi-upload" severity="secondary" outlined @click="mode = 'upload'" />
        </div>
      </section>

      <section class="panel goal">
        <h2>{{ t('career.orientation') }}</h2>
        <p class="muted">{{ t('career.orientationHint') }}</p>
        <div class="form-grid">
          <label class="field">
            <span>{{ t('career.targetRole') }} *</span>
            <InputText v-model="form.target_role" class="w-full" :placeholder="t('career.targetRoleHint')" />
          </label>
          <label class="field">
            <span>{{ t('career.salary') }}</span>
            <InputNumber v-model="form.expected_salary" :min="0" class="w-full" />
          </label>
        </div>
        <div class="actions">
          <Button :label="t('career.evaluate')" icon="pi pi-sparkles" :loading="evaluating" @click="recommend" />
        </div>
      </section>

      <section v-if="hasEvaluation" class="panel review">
        <div class="score-row">
          <div>
            <p class="eyebrow">{{ t('career.recruiterLabel') }}</p>
            <h2>{{ t('career.evalTitle') }}</h2>
            <p v-if="evaluation!.verdict || evaluation!.summary" class="verdict">
              {{ evaluation!.verdict || evaluation!.summary }}
            </p>
            <p v-if="evaluation!.target_role" class="muted role-line">
              {{ t('career.fitFor') }}: <strong>{{ evaluation!.target_role }}</strong>
            </p>
          </div>
          <div class="score-wrap">
            <div class="score" :class="{ mid: evaluation!.score >= 50 && evaluation!.score < 80, low: evaluation!.score < 50 }">
              {{ evaluation!.score }}
            </div>
            <span class="score-cap">{{ t('career.fitScore') }}</span>
          </div>
        </div>

        <div v-if="evaluation!.explanation_unavailable" class="banner warn">
          <i class="pi pi-exclamation-triangle" />
          <div class="banner-body">
            <p>{{ t('career.explanationUnavailable') }}</p>
          </div>
        </div>

        <div v-if="evaluation!.overview && !evaluation!.explanation_unavailable" class="overview">
          <h3>{{ t('career.overview') }}</h3>
          <p>{{ evaluation!.overview }}</p>
        </div>

        <div class="review-grid">
          <div v-if="evaluation!.strengths?.length" class="block good">
            <h3>{{ t('career.strengths') }}</h3>
            <ul><li v-for="(item, i) in evaluation!.strengths" :key="'s'+i">{{ item }}</li></ul>
          </div>
          <div v-if="evaluation!.weaknesses?.length" class="block bad">
            <h3>{{ t('career.weaknesses') }}</h3>
            <ul><li v-for="(item, i) in evaluation!.weaknesses" :key="'w'+i">{{ item }}</li></ul>
          </div>
        </div>

        <div v-if="evaluation!.improvements?.length" class="block">
          <h3>{{ t('career.improvements') }}</h3>
          <ul><li v-for="(item, i) in evaluation!.improvements" :key="'i'+i">{{ item }}</li></ul>
        </div>

        <div v-if="evaluation!.missing_items?.length" class="block">
          <h3>{{ t('career.missingItems') }}</h3>
          <ul><li v-for="(item, i) in evaluation!.missing_items" :key="'m'+i">{{ item }}</li></ul>
        </div>

        <div v-if="evaluation!.skill_gaps?.length" class="block">
          <h3>{{ t('career.skillGaps') }}</h3>
          <div class="tags gap-tags">
            <span v-for="g in evaluation!.skill_gaps" :key="g">{{ g }}</span>
          </div>
        </div>

        <div v-if="evaluation!.interview_focus?.length" class="block">
          <h3>{{ t('career.interviewFocus') }}</h3>
          <ul><li v-for="(item, i) in evaluation!.interview_focus" :key="'f'+i">{{ item }}</li></ul>
        </div>

        <p v-if="evaluation!.salary_note" class="salary-note">{{ evaluation!.salary_note }}</p>

        <details v-if="structureChecks.length" class="structure">
          <summary>{{ t('career.structureChecks') }}</summary>
          <div class="checks">
            <div v-for="c in structureChecks" :key="c.key" class="check" :class="{ ok: c.ok }">
              <i :class="c.ok ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
              <span>{{ c.label }}</span>
            </div>
          </div>
        </details>
      </section>

      <section v-else class="panel empty-eval">
        <i class="pi pi-sparkles" />
        <p>{{ t('career.evalPending') }}</p>
      </section>

      <section v-if="hasEvaluation && courses.length" class="panel courses-panel">
        <p class="eyebrow">{{ t('career.afterReview') }}</p>
        <h2>{{ t('career.coursesTitle') }}</h2>
        <p class="muted">{{ t('career.coursesHint') }}</p>
        <div class="courses">
          <button
            v-for="c in courses"
            :key="c.id"
            type="button"
            class="course"
            @click="navigateTo(`/courses/${c.slug || c.id}`)"
          >
            <strong>{{ c.title }}</strong>
            <span>{{ money(c.price) }} · {{ levelLabel(c.level) }}</span>
          </button>
        </div>
      </section>

      <section v-else-if="hasEvaluation" class="panel courses-panel">
        <p class="eyebrow">{{ t('career.afterReview') }}</p>
        <h2>{{ t('career.coursesTitle') }}</h2>
        <p class="muted">{{ t('career.coursesEmpty') }}</p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.banner {
  display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 14px;
  border: 1px solid #f59e0b66; background: #fffbeb;
}
.banner.warn i { color: #d97706; margin-top: 2px; }
.banner strong { display: block; margin-bottom: 4px; }
.banner p { margin: 0; color: var(--text-muted); font-size: .92rem; }
.choose { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.choice {
  display: grid; gap: 6px; justify-items: start; text-align: left; padding: 20px;
  border: 1px solid var(--border); border-radius: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent);
  cursor: pointer; font: inherit; color: inherit;
}
.choice.preferred { border-color: color-mix(in srgb, var(--brand) 45%, var(--border)); }
.choice i { color: var(--brand); font-size: 1.4rem; }
.choice span { color: var(--text-muted); font-size: .9rem; }
.choice em { font-style: normal; font-size: .78rem; font-weight: 700; color: var(--brand); }
.panel { border: 1px solid var(--border); border-radius: 16px; padding: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.cv-card { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.cv-card h2 { margin: 0 0 4px; font-size: 1.1rem; }
.cv-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.banner-body { flex: 1; min-width: 0; }
.dropzone {
  display: grid; gap: 6px; justify-items: center; text-align: center;
  width: 100%; margin-top: 12px; padding: 28px 16px;
  border: 2px dashed color-mix(in srgb, var(--brand) 40%, var(--border));
  border-radius: 16px; background: color-mix(in srgb, var(--brand-soft, #ecfdf5) 55%, var(--surface));
  cursor: pointer; font: inherit; color: inherit; transition: border-color .15s ease, background .15s ease;
}
.dropzone:hover, .dropzone.over {
  border-color: var(--brand);
  background: color-mix(in srgb, var(--brand) 10%, var(--surface));
}
.dropzone.busy { opacity: .7; cursor: wait; }
.dropzone i { font-size: 1.8rem; color: var(--brand); }
.dropzone strong { font-size: 1rem; }
.dropzone span { color: var(--text-muted); font-size: .88rem; }
.tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.tags span {
  padding: 4px 8px; border-radius: 999px; font-size: .75rem; font-weight: 600;
  background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand);
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.hidden { display: none; }
.score-row { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.score-wrap { display: grid; justify-items: center; gap: 2px; }
.score { font-size: 2.4rem; font-weight: 800; color: #059669; line-height: 1; }
.score.mid { color: #d97706; }
.score.low { color: #dc2626; }
.score-cap { font-size: .72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.verdict { margin: 6px 0 0; font-size: 1.05rem; font-weight: 650; }
.role-line { margin: 6px 0 0; }
.overview {
  margin-top: 14px; padding: 14px 16px; border-radius: 12px;
  background: color-mix(in srgb, var(--brand) 8%, transparent);
  border: 1px solid color-mix(in srgb, var(--brand) 22%, var(--border));
}
.overview h3, .block h3 { margin: 0 0 8px; font-size: .95rem; }
.overview p { margin: 0; line-height: 1.65; white-space: pre-line; }
.review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin: 12px 0; }
.check { display: flex; gap: 8px; align-items: center; padding: 10px; border-radius: 10px; border: 1px solid var(--border); }
.check.ok { background: #d1fae540; border-color: #6ee7b7; }
.block { margin-top: 12px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); }
.block.good { border-color: #6ee7b7; background: #ecfdf580; }
.block.bad { border-color: #fca5a5; background: #fef2f580; }
.block.warn { color: #b45309; }
.block ul { margin: 6px 0 0; padding-left: 18px; line-height: 1.55; }
.gap-tags span { background: #fee2e2; color: #b91c1c; }
.salary-note {
  margin-top: 12px; padding: 10px 12px; border-radius: 10px;
  background: #fffbeb; border: 1px solid #f59e0b66; color: #92400e; font-size: .92rem;
}
.structure { margin-top: 14px; color: var(--text-muted); }
.structure summary { cursor: pointer; font-weight: 650; }
.muted { color: var(--text-muted); }
.courses-panel { border-style: dashed; }
.courses { display: grid; gap: 8px; margin-top: 10px; }
.course {
  display: grid; gap: 2px; text-align: left; padding: 12px 14px; border-radius: 12px;
  border: 1px solid var(--border); background: transparent; cursor: pointer; font: inherit; color: inherit;
}
.course span { color: var(--text-muted); font-size: .85rem; }
.empty, .empty-eval { padding: 28px; text-align: center; color: var(--text-muted); }
.empty-eval { display: grid; gap: 8px; justify-items: center; }
.empty-eval i { font-size: 1.4rem; color: var(--brand); }
@media (max-width: 720px) {
  .choose, .form-grid, .review-grid { grid-template-columns: 1fr; }
  .cv-card { flex-direction: column; }
}
</style>
