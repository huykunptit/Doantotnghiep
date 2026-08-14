<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_grades', 'view_grades'],
})

interface GradeComponent {
  id: number
  name: string
  weight: number | string
  max_score: number | string
  position: number
}
interface StudentRow {
  enrollment_id: number
  student: { id: number, name: string, email?: string, student_code?: string | null }
  final_score: number | null
  letter_grade?: string | null
  gpa4?: number | null
  completed_courses_count?: number
  evaluation?: 'pending' | 'fail' | 'warning' | 'pass' | 'excellent'
  entries: Array<{
    grade_component_id: number
    score: number | string | null
    note?: string | null
  }>
}
interface GradebookResponse {
  class_section: any
  components: GradeComponent[]
  students: StudentRow[]
}

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const sectionId = computed(() => Number(route.params.id))

const data = ref<GradebookResponse | null>(null)
const loading = ref(true)
const saving = ref(false)
const applyingPreset = ref(false)
const scoreMap = reactive<Record<string, string>>({})
const detailStu = ref<StudentRow | null>(null)
const warnStu = ref<StudentRow | null>(null)
const warnMessage = ref('')
const warning = ref(false)
const detailOpen = computed({
  get: () => detailStu.value !== null,
  set: (open: boolean) => { if (!open) detailStu.value = null },
})
const warnOpen = computed({
  get: () => warnStu.value !== null,
  set: (open: boolean) => { if (!open) warnStu.value = null },
})

const sectionTitle = computed(() => {
  if (!data.value) return ''
  const cs = data.value.class_section
  return `${cs?.code || ''} — ${cs?.course?.title || ''}`
})

const courseId = computed(() => data.value?.class_section?.course_id || data.value?.class_section?.course?.id)

function scoreKey(enrollmentId: number, componentId: number) {
  return `${enrollmentId}-${componentId}`
}

async function load() {
  loading.value = true
  try {
    data.value = await useApi<GradebookResponse>(`/instructor/sections/${sectionId.value}/grades`)
    Object.keys(scoreMap).forEach(k => delete scoreMap[k])
    for (const stu of data.value.students) {
      for (const entry of stu.entries) {
        scoreMap[scoreKey(stu.enrollment_id, entry.grade_component_id)]
          = entry.score === null || entry.score === undefined ? '' : String(entry.score)
      }
    }
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('instructor.grades.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function previewFinal(stu: StudentRow): string {
  if (!data.value?.components.length) return '—'
  let weightSum = 0
  let weighted = 0
  for (const c of data.value.components) {
    const raw = scoreMap[scoreKey(stu.enrollment_id, c.id)]
    if (raw === '' || raw === undefined) continue
    const score = Number(raw)
    if (Number.isNaN(score)) continue
    const max = Number(c.max_score) || 10
    const w = Number(c.weight) || 0
    weighted += (score / max) * 10 * w
    weightSum += w
  }
  return weightSum > 0 ? (weighted / weightSum).toFixed(2) : '—'
}

function evalLabel(key?: string) {
  const map: Record<string, string> = {
    pending: t('instructor.grades.evalPending'),
    fail: t('instructor.grades.evalFail'),
    warning: t('instructor.grades.evalWarning'),
    pass: t('instructor.grades.evalPass'),
    excellent: t('instructor.grades.evalExcellent'),
  }
  return map[key || 'pending'] || key || '—'
}

function evalTone(key?: string) {
  if (key === 'excellent') return 'success'
  if (key === 'pass') return 'info'
  if (key === 'warning') return 'warn'
  if (key === 'fail') return 'danger'
  return 'secondary'
}

function openWarn(stu: StudentRow) {
  warnStu.value = stu
  warnMessage.value = ''
}

async function sendWarn() {
  if (!warnStu.value?.student?.id) return
  warning.value = true
  try {
    const res = await useApi<{ message: string }>(
      `/instructor/sections/${sectionId.value}/students/${warnStu.value.student.id}/warn`,
      { method: 'POST', body: { message: warnMessage.value || null } },
    )
    toast.add({ severity: 'success', summary: res.message || t('instructor.grades.warnOk'), life: 2500 })
    warnStu.value = null
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('instructor.grades.warnError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    warning.value = false
  }
}

async function save() {
  if (!data.value) return
  saving.value = true
  try {
    const entries: Array<{ enrollment_id: number, grade_component_id: number, score: number | null }> = []
    for (const stu of data.value.students) {
      for (const c of data.value.components) {
        const raw = scoreMap[scoreKey(stu.enrollment_id, c.id)]
        entries.push({
          enrollment_id: stu.enrollment_id,
          grade_component_id: c.id,
          score: raw === '' || raw === undefined ? null : Number(raw),
        })
      }
    }
    const result = await useApi<{ message: string, written: number }>(
      `/instructor/sections/${sectionId.value}/grades`,
      { method: 'PUT', body: { entries } },
    )
    toast.add({
      severity: 'success',
      summary: result.message || t('instructor.grades.saved'),
      detail: t('instructor.grades.written', { n: result.written }),
      life: 2500,
    })
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('instructor.grades.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

async function applyPreset() {
  if (!courseId.value) return
  applyingPreset.value = true
  try {
    await useApi(`/instructor/courses/${courseId.value}/grade-components/preset`, { method: 'POST' })
    toast.add({ severity: 'success', summary: t('instructor.grades.presetOk'), life: 2500 })
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('instructor.grades.presetError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    applyingPreset.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <p class="eyebrow">{{ t('instructor.grades.bookEyebrow') }}</p>
        <h1>{{ sectionTitle || t('instructor.grades.loading') }}</h1>
        <p v-if="data?.class_section">
          {{ data.class_section.term?.name }} · {{ data.class_section.cohort?.code || '—' }}
        </p>
      </div>
      <div class="head-actions">
        <Button
          :label="t('instructor.grades.back')"
          icon="pi pi-arrow-left"
          severity="secondary"
          outlined
          @click="navigateTo('/instructor/sections')"
        />
        <Button
          v-if="data && !data.components.length"
          :label="t('instructor.grades.applyPreset')"
          icon="pi pi-sparkles"
          severity="help"
          :loading="applyingPreset"
          @click="applyPreset"
        />
        <Button
          :label="t('instructor.grades.save')"
          icon="pi pi-save"
          :loading="saving"
          :disabled="loading || !data?.components.length"
          @click="save"
        />
      </div>
    </header>

    <section class="panel">
      <div v-if="loading" class="empty">{{ t('instructor.grades.loading') }}</div>
      <CommonEmptyState v-else-if="!data?.components.length" :description="t('instructor.grades.noComponents')">
        <Button
          :label="t('instructor.grades.applyPreset')"
          icon="pi pi-sparkles"
          :loading="applyingPreset"
          @click="applyPreset"
        />
      </CommonEmptyState>
      <div v-else class="table-wrap">
        <table class="grade-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t('instructor.grades.studentCode') }}</th>
              <th>{{ t('instructor.grades.studentName') }}</th>
              <th v-for="c in data.components" :key="c.id" class="center">
                {{ c.name }}
                <small>/{{ c.max_score }} ({{ c.weight }}%)</small>
              </th>
              <th class="center">{{ t('instructor.grades.final') }}</th>
              <th class="center">{{ t('instructor.grades.letter') }}</th>
              <th class="center">{{ t('instructor.grades.completedCourses') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(stu, i) in data.students" :key="stu.enrollment_id">
              <td>{{ i + 1 }}</td>
              <td><code>{{ stu.student.student_code || '—' }}</code></td>
              <td><strong>{{ stu.student.name }}</strong></td>
              <td v-for="c in data.components" :key="c.id" class="center">
                <input
                  v-model="scoreMap[scoreKey(stu.enrollment_id, c.id)]"
                  type="number"
                  step="0.1"
                  min="0"
                  :max="Number(c.max_score)"
                  class="score-input"
                >
              </td>
              <td class="center">
                <strong :class="Number(previewFinal(stu)) >= 5 ? 'pass' : 'fail'">{{ previewFinal(stu) }}</strong>
              </td>
              <td class="center">
                <Tag :value="evalLabel(stu.evaluation)" :severity="evalTone(stu.evaluation)" />
                <small v-if="stu.letter_grade" class="letter">{{ stu.letter_grade }}</small>
              </td>
              <td class="center">{{ stu.completed_courses_count ?? 0 }}</td>
              <td class="actions">
                <Button icon="pi pi-eye" text rounded size="small" :aria-label="t('instructor.grades.detail')" @click="detailStu = stu" />
                <Button
                  v-if="stu.evaluation === 'fail' || stu.evaluation === 'warning'"
                  icon="pi pi-send"
                  text
                  rounded
                  size="small"
                  severity="warn"
                  :aria-label="t('instructor.grades.warn')"
                  @click="openWarn(stu)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <Dialog v-model:visible="detailOpen" modal :header="t('instructor.grades.detailTitle')" :style="{ width: 'min(480px, 96vw)' }">
      <template v-if="detailStu">
        <p class="dlg-name">{{ detailStu.student.name }} <small>{{ detailStu.student.student_code }}</small></p>
        <ul class="detail-list">
          <li v-for="c in data?.components || []" :key="c.id">
            <span>{{ c.name }}</span>
            <strong>{{ scoreMap[scoreKey(detailStu.enrollment_id, c.id)] || '—' }} / {{ c.max_score }}</strong>
          </li>
          <li>
            <span>{{ t('instructor.grades.final') }}</span>
            <strong>{{ previewFinal(detailStu) }} {{ detailStu.letter_grade ? `(${detailStu.letter_grade})` : '' }}</strong>
          </li>
          <li>
            <span>{{ t('instructor.grades.completedCourses') }}</span>
            <strong>{{ detailStu.completed_courses_count ?? 0 }}</strong>
          </li>
        </ul>
      </template>
    </Dialog>

    <Dialog v-model:visible="warnOpen" modal :header="t('instructor.grades.warnTitle')" :style="{ width: 'min(480px, 96vw)' }">
      <p v-if="warnStu" class="dlg-name">{{ warnStu.student.name }}</p>
      <p class="hint">{{ t('instructor.grades.warnHint') }}</p>
      <Textarea v-model="warnMessage" rows="4" class="w-full" />
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="warnStu = null" />
        <Button :label="t('instructor.grades.warnSend')" icon="pi pi-send" :loading="warning" @click="sendWarn" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.workspace-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.eyebrow { margin: 0; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--p-text-muted-color); }
.workspace-head h1 { margin: .2rem 0; font-size: 1.45rem; }
.workspace-head p { margin: 0; color: var(--p-text-muted-color); }
.head-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.panel { border: 1px solid var(--p-content-border-color); border-radius: 12px; overflow: hidden; background: var(--p-content-background); }
.empty { padding: 2rem 1rem; text-align: center; color: var(--p-text-muted-color); display: flex; flex-direction: column; align-items: center; gap: .75rem; }
.table-wrap { overflow-x: auto; }
.grade-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.grade-table th, .grade-table td { padding: .65rem .75rem; border-bottom: 1px solid var(--p-content-border-color); text-align: left; vertical-align: middle; }
.grade-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--p-text-muted-color); background: var(--p-surface-50); }
.grade-table th small { display: block; font-weight: 500; text-transform: none; margin-top: .15rem; }
.center { text-align: center !important; }
.pass { color: #0f766e; }
.fail { color: #b91c1c; }
code { font-size: .8rem; background: var(--p-surface-100); padding: .1rem .35rem; border-radius: 4px; }
.score-input {
  width: 4.5rem;
  height: 2rem;
  text-align: center;
  border: 1px solid var(--p-content-border-color);
  border-radius: 8px;
  background: var(--p-content-background);
  color: var(--p-text-color);
  font-weight: 600;
}
.actions { white-space: nowrap; }
.letter { display: block; margin-top: 4px; color: var(--p-text-muted-color); font-size: .75rem; }
.dlg-name { margin: 0 0 10px; font-weight: 700; }
.dlg-name small { color: var(--p-text-muted-color); font-weight: 500; }
.hint { margin: 0 0 10px; color: var(--p-text-muted-color); font-size: .88rem; }
.detail-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.detail-list li { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--p-content-border-color); }
.w-full { width: 100%; }
</style>
