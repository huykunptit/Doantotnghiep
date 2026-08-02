<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface SurveyOption { value: string, label: string }
interface SurveyQuestion {
  id: string
  type: 'single' | 'multi' | 'likert5' | 'compare5' | 'nps' | 'text' | 'textarea'
  label: string
  required?: boolean
  options?: SurveyOption[]
  scale?: string
}
interface SurveySection {
  id: string
  title: string
  scale?: string
  hint?: string
  show_if_ai?: string
  show_if_any_ai?: boolean
  questions: SurveyQuestion[]
}
interface SurveyDef {
  title: string
  intro: string
  likert_labels?: Record<string, string>
  compare_labels?: Record<string, string>
  sections: SurveySection[]
  submitted?: boolean
  submitted_at?: string | null
  updated_at?: string | null
  answers?: Record<string, unknown> | null
  prefill?: Record<string, string | null>
}

type AnswerMap = Record<string, string | number | string[] | null>

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const editing = ref(false)
const survey = ref<SurveyDef | null>(null)
const answers = ref<AnswerMap>({})
const fieldErrors = ref<Record<string, string>>({})

const readOnly = computed(() => !!survey.value?.submitted && !editing.value)

const aiUsed = computed(() => {
  const raw = answers.value.D0
  const list = Array.isArray(raw) ? raw as string[] : []
  if (list.includes('none')) return [] as string[]
  return list.filter(v => ['chatbot', 'career', 'study'].includes(v))
})

const visibleSections = computed(() => {
  const sections = survey.value?.sections || []
  return sections.filter((s) => {
    if (s.show_if_ai) return aiUsed.value.includes(s.show_if_ai)
    if (s.show_if_any_ai) return aiUsed.value.length > 0
    return true
  })
})

function scaleOptions(q: SurveyQuestion) {
  const labels = survey.value?.likert_labels
  return [1, 2, 3, 4, 5].map(n => ({
    value: n,
    label: labels?.[String(n)] || String(n),
  }))
}

function questionVisible(q: SurveyQuestion) {
  if (q.id === 'A6_other') {
    const a6 = answers.value.A6
    return Array.isArray(a6) && a6.includes('other')
  }
  if (q.id === 'C1_other') {
    const c1 = answers.value.C1
    return Array.isArray(c1) && c1.includes('other')
  }
  return true
}

function isMultiChecked(qid: string, value: string) {
  const cur = answers.value[qid]
  return Array.isArray(cur) && cur.includes(value)
}

function toggleMulti(qid: string, value: string) {
  if (readOnly.value) return
  const exclusiveIds = ['A6', 'C1', 'D0']
  const cur = Array.isArray(answers.value[qid]) ? [...(answers.value[qid] as string[])] : []
  if (exclusiveIds.includes(qid)) {
    if (value === 'none') {
      answers.value[qid] = cur.includes('none') ? [] : ['none']
      return
    }
    const withoutNone = cur.filter(v => v !== 'none')
    const idx = withoutNone.indexOf(value)
    if (idx >= 0) withoutNone.splice(idx, 1)
    else withoutNone.push(value)
    answers.value[qid] = withoutNone
    return
  }
  const idx = cur.indexOf(value)
  if (idx >= 0) cur.splice(idx, 1)
  else cur.push(value)
  answers.value[qid] = cur
}

function blankAnswers(def: SurveyDef): AnswerMap {
  const init: AnswerMap = {}
  for (const section of def.sections || []) {
    for (const q of section.questions) {
      init[q.id] = q.type === 'multi' ? [] : null
    }
  }
  if (def.prefill?.A2) init.A2 = def.prefill.A2
  return init
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<SurveyDef>('/me/experience-survey')
    survey.value = res
    editing.value = false
    if (res.answers && Object.keys(res.answers).length) {
      answers.value = { ...blankAnswers(res), ...(res.answers as AnswerMap) }
    }
    else {
      answers.value = blankAnswers(res)
    }
  }
  catch (e: any) {
    toast.add({ severity: 'error', summary: t('student.experienceSurvey.loadError'), detail: e?.data?.message, life: 4000 })
  }
  finally {
    loading.value = false
  }
}

function startEdit() {
  editing.value = true
}

async function submit() {
  saving.value = true
  fieldErrors.value = {}
  try {
    const res = await useApi<{ message?: string }>('/me/experience-survey', {
      method: 'POST',
      body: { answers: answers.value },
    })
    toast.add({
      severity: 'success',
      summary: res.message || t('student.experienceSurvey.submitOk'),
      life: 3500,
    })
    await load()
  }
  catch (e: any) {
    const errs = e?.data?.errors || {}
    const mapped: Record<string, string> = {}
    for (const [k, v] of Object.entries(errs)) {
      mapped[k] = Array.isArray(v) ? String(v[0]) : String(v)
    }
    fieldErrors.value = mapped
    toast.add({
      severity: 'error',
      summary: t('student.experienceSurvey.submitError'),
      detail: e?.data?.message,
      life: 4500,
    })
  }
  finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ survey?.title || t('student.experienceSurvey.title') }}</h1>
        <p>{{ survey?.intro || t('student.experienceSurvey.subtitle') }}</p>
      </div>
      <Button
        v-if="survey?.submitted && !editing"
        :label="t('student.experienceSurvey.reRate')"
        icon="pi pi-pencil"
        severity="secondary"
        outlined
        @click="startEdit"
      />
    </header>

    <div v-if="loading" class="empty">…</div>

    <template v-else-if="survey">
      <div v-if="survey.submitted && !editing" class="banner ok">
        <i class="pi pi-check-circle" />
        <div>
          <strong>{{ t('student.experienceSurvey.alreadyDone') }}</strong>
          <p v-if="survey.submitted_at">{{ t('student.experienceSurvey.submittedAt', { at: new Date(survey.submitted_at).toLocaleString() }) }}</p>
          <p>{{ t('student.experienceSurvey.reRateHint') }}</p>
        </div>
      </div>
      <div v-else-if="editing" class="banner edit">
        <i class="pi pi-pencil" />
        <div>
          <strong>{{ t('student.experienceSurvey.editing') }}</strong>
          <p>{{ t('student.experienceSurvey.editingHint') }}</p>
        </div>
      </div>

      <form class="form" @submit.prevent="submit">
        <section v-for="section in visibleSections" :key="section.id" class="panel">
          <h2>{{ section.title }}</h2>
          <p v-if="section.hint" class="hint">{{ section.hint }}</p>
          <p v-else-if="section.scale === 'likert5'" class="hint">{{ t('student.experienceSurvey.likertHint') }}</p>

          <div
            v-for="q in section.questions.filter(questionVisible)"
            :key="q.id"
            class="q"
            :class="{ err: fieldErrors[q.id] }"
          >
            <label class="qlabel">
              <span>{{ q.label }}</span>
              <em v-if="q.required !== false">*</em>
            </label>

            <div v-if="q.type === 'single'" class="opts">
              <label v-for="opt in q.options" :key="opt.value" class="opt">
                <input v-model="answers[q.id]" type="radio" :name="q.id" :value="opt.value" :disabled="readOnly">
                <span>{{ opt.label }}</span>
              </label>
            </div>

            <div v-else-if="q.type === 'multi'" class="opts">
              <label v-for="opt in q.options" :key="opt.value" class="opt">
                <input
                  type="checkbox"
                  :checked="isMultiChecked(q.id, opt.value)"
                  :disabled="readOnly"
                  @change="toggleMulti(q.id, opt.value)"
                >
                <span>{{ opt.label }}</span>
              </label>
            </div>

            <div v-else-if="q.type === 'likert5'" class="likert">
              <label v-for="opt in scaleOptions(q)" :key="opt.value" class="likert-opt">
                <input v-model.number="answers[q.id]" type="radio" :name="q.id" :value="opt.value" :disabled="readOnly">
                <strong>{{ opt.value }}</strong>
                <span>{{ opt.label }}</span>
              </label>
            </div>

            <div v-else-if="q.type === 'nps'" class="nps">
              <label v-for="n in 11" :key="n - 1" class="nps-opt">
                <input v-model.number="answers[q.id]" type="radio" :name="q.id" :value="n - 1" :disabled="readOnly">
                <span>{{ n - 1 }}</span>
              </label>
            </div>

            <input
              v-else-if="q.type === 'text'"
              v-model="answers[q.id]"
              type="text"
              class="input"
              :disabled="readOnly"
              :placeholder="t('student.experienceSurvey.textPlaceholder')"
            >

            <textarea
              v-else
              v-model="answers[q.id]"
              class="input area"
              rows="3"
              :disabled="readOnly"
              :placeholder="t('student.experienceSurvey.openPlaceholder')"
            />

            <small v-if="fieldErrors[q.id]" class="error">{{ fieldErrors[q.id] }}</small>
          </div>
        </section>

        <div v-if="!readOnly" class="actions">
          <Button
            v-if="editing"
            type="button"
            :label="t('common.cancel')"
            severity="secondary"
            text
            @click="load"
          />
          <Button
            type="submit"
            :label="editing ? t('student.experienceSurvey.update') : t('student.experienceSurvey.submit')"
            icon="pi pi-send"
            :loading="saving"
          />
        </div>
      </form>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.workspace-head h1 { margin: 0 0 6px; font-size: clamp(1.25rem, 2vw, 1.65rem); line-height: 1.3; }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; line-height: 1.55; max-width: 70ch; }
.panel { border: 1px solid var(--border); border-radius: 16px; padding: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.panel h2 { margin: 0 0 10px; font-size: 1.05rem; }
.hint { margin: 0 0 14px; color: var(--text-muted); font-size: .85rem; }
.q { display: grid; gap: 8px; padding: 12px 0; border-top: 1px solid color-mix(in srgb, var(--border) 80%, transparent); }
.q:first-of-type { border-top: 0; padding-top: 0; }
.qlabel { display: flex; gap: 4px; font-weight: 650; line-height: 1.45; }
.qlabel em { color: #dc2626; font-style: normal; }
.opts { display: grid; gap: 8px; }
.opt { display: flex; gap: 8px; align-items: flex-start; font-weight: 500; cursor: pointer; }
.likert, .nps { display: flex; flex-wrap: wrap; gap: 8px; }
.likert-opt, .nps-opt {
  display: grid; justify-items: center; gap: 4px; min-width: 72px; padding: 8px 6px;
  border: 1px solid var(--border); border-radius: 10px; cursor: pointer; font-size: .72rem; color: var(--text-muted);
}
.likert-opt strong, .nps-opt span:last-child { color: var(--text); font-size: .95rem; }
.input {
  width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface); color: var(--text); font: inherit;
}
.area { resize: vertical; min-height: 84px; }
.actions { display: flex; justify-content: flex-end; gap: 8px; }
.banner {
  display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 12px;
  border: 1px solid color-mix(in srgb, #16a34a 35%, var(--border));
  background: color-mix(in srgb, #16a34a 10%, var(--surface));
}
.banner.edit {
  border-color: color-mix(in srgb, #2563eb 35%, var(--border));
  background: color-mix(in srgb, #2563eb 10%, var(--surface));
}
.banner i { color: #16a34a; margin-top: 2px; }
.banner.edit i { color: #2563eb; }
.banner p { margin: 4px 0 0; color: var(--text-muted); font-size: .88rem; }
.error { color: #dc2626; }
.empty { padding: 28px; text-align: center; color: var(--text-muted); }
@media (max-width: 700px) {
  .likert-opt { min-width: calc(50% - 8px); }
}
</style>
