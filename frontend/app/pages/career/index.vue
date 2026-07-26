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
}

const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const evaluating = ref(false)
const uploading = ref(false)
const mode = ref<'choose' | 'upload' | 'form' | 'result'>('choose')
const cv = ref<CvRow | null>(null)
const evaluation = ref<Evaluation | null>(null)
const courses = ref<CourseCard[]>([])
const fileInput = ref<HTMLInputElement | null>(null)

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

function money(n?: number | null) {
  if (!n) return '—'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(n)
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ cv: CvRow | null }>('/career/advisor')
    cv.value = res.cv || null
    if (cv.value) {
      evaluation.value = cv.value.evaluation_json || null
      form.target_role = cv.value.target_role || ''
      form.expected_salary = cv.value.expected_salary || 8000000
      mode.value = 'result'
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
    evaluation.value = res.evaluation
    mode.value = 'result'
    toast.add({ severity: 'success', summary: t('career.formSaved'), life: 3000 })
    await runEvaluate()
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
  try {
    const body = new FormData()
    body.append('cv', file)
    const res = await useApi<{ cv: CvRow }>('/career/upload-cv', { method: 'POST', body })
    cv.value = res.cv
    mode.value = 'result'
    toast.add({ severity: 'success', summary: t('career.uploadOk'), life: 3000 })
    await runEvaluate()
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
}

async function runEvaluate() {
  if (!cv.value) return
  evaluating.value = true
  try {
    const res = await useApi<{ evaluation: Evaluation, suggested_courses: CourseCard[], cv: CvRow }>('/career/evaluate', {
      method: 'POST',
      body: {
        target_role: form.target_role || null,
        expected_salary: form.expected_salary,
      },
    })
    evaluation.value = res.evaluation
    courses.value = res.suggested_courses || []
    cv.value = res.cv
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('career.evalError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    evaluating.value = false
  }
}

async function recommend() {
  if (!form.target_role.trim()) {
    toast.add({ severity: 'warn', summary: t('career.roleRequired'), life: 2500 })
    return
  }
  evaluating.value = true
  try {
    await useApi('/career/recommend', {
      method: 'POST',
      body: { job_title: form.target_role, expected_salary: form.expected_salary },
    })
    await runEvaluate()
    toast.add({ severity: 'success', summary: t('career.recommendOk'), life: 3000 })
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('career.recommendError'), detail: e?.data?.message, life: 3500 })
  }
  finally {
    evaluating.value = false
  }
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

    <section v-else-if="mode === 'choose'" class="choose">
      <button type="button" class="choice" @click="mode = 'upload'">
        <i class="pi pi-upload" />
        <strong>{{ t('career.uploadTitle') }}</strong>
        <span>{{ t('career.uploadHint') }}</span>
      </button>
      <button type="button" class="choice" @click="mode = 'form'">
        <i class="pi pi-file-edit" />
        <strong>{{ t('career.formTitle') }}</strong>
        <span>{{ t('career.formHint') }}</span>
      </button>
    </section>

    <section v-else-if="mode === 'upload'" class="panel">
      <input ref="fileInput" type="file" accept=".pdf,.doc,.docx" class="hidden" @change="onFileChange">
      <p>{{ t('career.uploadHint') }}</p>
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
        <label class="field full"><span>{{ t('career.summary') }}</span><CommonRichTextEditor v-model="form.summary" height="180px" /></label>
        <label class="field full"><span>{{ t('career.skills') }}</span><Textarea v-model="form.skillsText" rows="2" class="w-full" :placeholder="t('career.skillsHint')" /></label>
        <label class="field"><span>{{ t('career.school') }}</span><InputText v-model="form.education[0].school" class="w-full" /></label>
        <label class="field"><span>{{ t('career.degree') }}</span><InputText v-model="form.education[0].degree" class="w-full" /></label>
        <label class="field full"><span>{{ t('career.project') }}</span><InputText v-model="form.projects[0].name" class="w-full" /></label>
        <label class="field full"><span>{{ t('career.projectDesc') }}</span><CommonRichTextEditor v-model="form.projects[0].description" height="160px" /></label>
      </div>
      <div class="actions">
        <Button :label="t('career.saveForm')" icon="pi pi-check" :loading="saving" @click="saveForm" />
        <Button :label="t('common.cancel')" severity="secondary" text @click="mode = 'choose'" />
      </div>
    </section>

    <template v-else-if="mode === 'result'">
      <section class="panel goal">
        <h2>{{ t('career.orientation') }}</h2>
        <div class="form-grid">
          <label class="field">
            <span>{{ t('career.targetRole') }}</span>
            <InputText v-model="form.target_role" class="w-full" :placeholder="t('career.targetRoleHint')" />
          </label>
          <label class="field">
            <span>{{ t('career.salary') }}</span>
            <InputNumber v-model="form.expected_salary" :min="0" class="w-full" />
          </label>
        </div>
        <div class="actions">
          <Button :label="t('career.evaluate')" icon="pi pi-sparkles" :loading="evaluating" @click="recommend" />
          <Button :label="t('career.newCv')" severity="secondary" outlined @click="mode = 'choose'" />
        </div>
      </section>

      <section v-if="evaluation" class="panel">
        <div class="score-row">
          <div>
            <h2>{{ t('career.evalTitle') }}</h2>
            <p>{{ evaluation.summary }}</p>
          </div>
          <div class="score">{{ evaluation.score }}</div>
        </div>
        <div class="checks">
          <div v-for="c in evaluation.checks" :key="c.key" class="check" :class="{ ok: c.ok }">
            <i :class="c.ok ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
            <span>{{ c.label }}</span>
          </div>
        </div>
        <div v-if="evaluation.warnings?.length" class="block warn">
          <h3>{{ t('career.warnings') }}</h3>
          <ul><li v-for="(w, i) in evaluation.warnings" :key="i">{{ w }}</li></ul>
        </div>
        <div v-if="evaluation.fixes?.length" class="block">
          <h3>{{ t('career.fixes') }}</h3>
          <ul><li v-for="(f, i) in evaluation.fixes" :key="i">{{ f }}</li></ul>
        </div>
        <p v-if="evaluation.salary_note" class="muted">{{ evaluation.salary_note }}</p>
      </section>

      <section v-if="courses.length" class="panel">
        <h2>{{ t('career.coursesTitle') }}</h2>
        <p class="muted">{{ t('career.coursesHint') }}</p>
        <div class="courses">
          <button v-for="c in courses" :key="c.id" type="button" class="course" @click="navigateTo(`/courses/${c.id}`)">
            <strong>{{ c.title }}</strong>
            <span>{{ money(c.price) }} · {{ c.level || '—' }}</span>
          </button>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.choose { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.choice {
  display: grid; gap: 6px; justify-items: start; text-align: left; padding: 20px;
  border: 1px solid var(--border); border-radius: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent);
  cursor: pointer; font: inherit; color: inherit;
}
.choice i { color: var(--brand); font-size: 1.4rem; }
.choice span { color: var(--text-muted); font-size: .9rem; }
.panel { border: 1px solid var(--border); border-radius: 16px; padding: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field.full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.hidden { display: none; }
.score-row { display: flex; justify-content: space-between; gap: 16px; align-items: center; }
.score { font-size: 2.4rem; font-weight: 800; color: var(--brand); }
.checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin: 12px 0; }
.check { display: flex; gap: 8px; align-items: center; padding: 10px; border-radius: 10px; border: 1px solid var(--border); }
.check.ok { background: #d1fae540; border-color: #6ee7b7; }
.block { margin-top: 10px; }
.block.warn { color: #b45309; }
.block ul { margin: 6px 0 0; padding-left: 18px; }
.muted { color: var(--text-muted); }
.courses { display: grid; gap: 8px; margin-top: 10px; }
.course {
  display: grid; gap: 2px; text-align: left; padding: 12px 14px; border-radius: 12px;
  border: 1px solid var(--border); background: transparent; cursor: pointer; font: inherit; color: inherit;
}
.course span { color: var(--text-muted); font-size: .85rem; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
@media (max-width: 720px) { .choose, .form-grid { grid-template-columns: 1fr; } }
</style>
