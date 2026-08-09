<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

type TabKey = 'curriculum' | 'external'

interface GradeComponentRow {
  stt: number
  name: string
  weight: number
  score: number | null
  max_score?: number
}

interface DetailCourse {
  title: string
  components: GradeComponentRow[]
}

interface ExternalCourse extends DetailCourse {
  enrollment_id: number
  course_id: number
  credits: number
  score10: number | null
  passed: boolean | null
}

interface CurriculumCourseRow extends DetailCourse {
  stt: number
  course_id: number
  code?: string | null
  credits: number
  attendance_score?: number | null
  midterm_score?: number | null
  exam_score?: number | null
  score10?: number | null
  score4?: number | null
  letter?: string | null
  passed?: boolean | null
}

interface CurriculumSemester {
  term_number: number
  label: string
  is_current?: boolean
  courses: CurriculumCourseRow[]
  semester_gpa4: number | null
  semester_gpa10: number | null
  semester_credits_earned: number | null
  cumulative_gpa4: number | null
  cumulative_gpa10: number | null
  cumulative_credits: number | null
}

interface TranscriptResponse {
  curriculum?: {
    has_curriculum: boolean
    semesters: CurriculumSemester[]
    overall: {
      gpa4: number | null
      gpa10: number | null
      credits_earned: number
      credits_attempted: number
      courses_graded: number
    }
  }
  external?: {
    courses: ExternalCourse[]
    summary: {
      total_courses: number
      graded: number
      passed: number
      average_score: number | null
    }
  }
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const tab = ref<TabKey>('curriculum')
const curriculum = ref<TranscriptResponse['curriculum'] | null>(null)
const external = ref<TranscriptResponse['external'] | null>(null)

const detailOpen = ref(false)
const detailCourse = ref<DetailCourse | null>(null)

async function load() {
  loading.value = true
  try {
    const res = await useApi<TranscriptResponse>('/me/transcript')
    curriculum.value = res.curriculum || null
    external.value = res.external || null
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.transcript.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function fmtNum(value?: number | null, digits = 2) {
  if (value === null || value === undefined) return '—'
  return Number(value).toFixed(digits)
}

function fmtStat(value?: number | null, blank = false, digits = 2) {
  if (value === null || value === undefined) return blank ? '' : '—'
  return Number(value).toFixed(digits)
}

/** Phân loại theo thang GPA 4.0 (PTIT-style) — cùng ngưỡng với cố vấn học tập. */
function classifyGpa(gpa?: number | null) {
  if (gpa == null || Number.isNaN(gpa)) return 'none'
  if (gpa >= 3.6) return 'excellent'
  if (gpa >= 3.2) return 'very_good'
  if (gpa >= 2.5) return 'good'
  if (gpa >= 2.0) return 'average'
  if (gpa >= 1.0) return 'weak'
  return 'none'
}

const gpaClassLabel = computed(() => {
  const key = classifyGpa(curriculum.value?.overall?.gpa4)
  return t(`student.transcript.gpaClass.${key}`)
})

function semesterClassLabel(gpa?: number | null, blank = false) {
  if (blank) return ''
  const key = classifyGpa(gpa)
  return t(`student.transcript.gpaClass.${key}`)
}

function openDetail(course: DetailCourse) {
  detailCourse.value = course
  detailOpen.value = true
}

function printPage() {
  window.print()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header v-if="tab === 'curriculum'" class="workspace-head">
      <Button
        :label="t('student.transcript.print')"
        icon="pi pi-print"
        class="print-btn"
        @click="printPage"
      />
    </header>

    <div class="tabs" role="tablist">
      <button
        type="button"
        class="tab"
        :class="{ on: tab === 'curriculum' }"
        role="tab"
        :aria-selected="tab === 'curriculum'"
        @click="tab = 'curriculum'"
      >
        {{ t('student.transcript.tabCurriculum') }}
      </button>
      <button
        type="button"
        class="tab"
        :class="{ on: tab === 'external' }"
        role="tab"
        :aria-selected="tab === 'external'"
        @click="tab = 'external'"
      >
        {{ t('student.transcript.tabExternal') }}
      </button>
    </div>

    <div v-if="loading" class="empty">…</div>

    <!-- ===== Điểm trong CTĐT ===== -->
    <template v-else-if="tab === 'curriculum'">
      <section v-if="curriculum?.overall" class="stats">
        <div class="stat highlight">
          <span>{{ t('student.transcript.cpa4') }}</span>
          <strong>{{ fmtNum(curriculum.overall.gpa4) }}</strong>
        </div>
        <div class="stat">
          <span>{{ t('student.transcript.cpa10') }}</span>
          <strong>{{ fmtNum(curriculum.overall.gpa10) }}</strong>
        </div>
        <div class="stat">
          <span>{{ t('student.transcript.creditsEarned') }}</span>
          <strong>{{ curriculum.overall.credits_earned }}</strong>
        </div>
        <div class="stat">
          <span>{{ t('student.transcript.classification') }}</span>
          <strong class="class-label">{{ gpaClassLabel }}</strong>
        </div>
      </section>

      <div v-if="!curriculum?.semesters?.length" class="empty">
        {{ curriculum?.has_curriculum === false ? t('student.transcript.noCurriculum') : t('student.transcript.emptyCurriculum') }}
      </div>

      <div v-else class="semester-list">
        <section v-for="sem in curriculum.semesters" :key="sem.term_number" class="semester-block">
          <header class="semester-head">{{ sem.label }}</header>

          <div class="table-wrap">
            <table class="grade-table">
              <thead>
                <tr>
                  <th>{{ t('student.transcript.colStt') }}</th>
                  <th>{{ t('student.transcript.colCode') }}</th>
                  <th class="left">{{ t('student.transcript.colTitle') }}</th>
                  <th>{{ t('student.transcript.colCredits') }}</th>
                  <th>{{ t('student.transcript.colExam') }}</th>
                  <th>{{ t('student.transcript.colScore10') }}</th>
                  <th>{{ t('student.transcript.colScore4') }}</th>
                  <th>{{ t('student.transcript.colLetter') }}</th>
                  <th>{{ t('student.transcript.colResult') }}</th>
                  <th>{{ t('student.transcript.colDetail') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in sem.courses" :key="row.course_id">
                  <td>{{ row.stt }}</td>
                  <td class="mono">{{ row.code || '—' }}</td>
                  <td class="left title">
                    <NuxtLink :to="`/learn/${row.course_id}`" class="course-link">{{ row.title }}</NuxtLink>
                  </td>
                  <td>{{ row.credits }}</td>
                  <!-- Kỳ đang học: đủ cột nhưng để trống điểm -->
                  <template v-if="sem.is_current">
                    <td /><td /><td /><td /><td /><td />
                  </template>
                  <template v-else>
                    <td>{{ row.exam_score ?? '—' }}</td>
                    <td class="score">{{ row.score10 ?? '—' }}</td>
                    <td>{{ row.score4 ?? '—' }}</td>
                    <td>{{ row.letter || '—' }}</td>
                    <td>
                      <i
                        v-if="row.passed === true"
                        class="pi pi-check-circle ok"
                        :title="t('student.transcript.pass')"
                      />
                      <i
                        v-else-if="row.passed === false"
                        class="pi pi-times-circle fail"
                        :title="t('student.transcript.fail')"
                      />
                      <span v-else class="muted">—</span>
                    </td>
                    <td>
                      <button type="button" class="detail-btn" @click="openDetail(row)">
                        <i class="pi pi-list" />
                        <span>{{ t('student.transcript.colDetail') }}</span>
                      </button>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="semester-summary" :class="{ blank: sem.is_current }">
            <div class="sum-col">
              <div>
                <span>- {{ t('student.transcript.semGpa4') }}:</span>
                <strong>{{ fmtStat(sem.semester_gpa4, !!sem.is_current) }}</strong>
              </div>
              <div>
                <span>- {{ t('student.transcript.semGpa10') }}:</span>
                <strong>{{ fmtStat(sem.semester_gpa10, !!sem.is_current) }}</strong>
              </div>
              <div>
                <span>- {{ t('student.transcript.semCredits') }}:</span>
                <strong>{{ sem.is_current ? '' : (sem.semester_credits_earned ?? '—') }}</strong>
              </div>
            </div>
            <div class="sum-col">
              <div>
                <span>- {{ t('student.transcript.cumGpa4') }}:</span>
                <strong>{{ fmtStat(sem.cumulative_gpa4, !!sem.is_current) }}</strong>
              </div>
              <div>
                <span>- {{ t('student.transcript.cumGpa10') }}:</span>
                <strong>{{ fmtStat(sem.cumulative_gpa10, !!sem.is_current) }}</strong>
              </div>
              <div>
                <span>- {{ t('student.transcript.cumCredits') }}:</span>
                <strong>{{ sem.is_current ? '' : (sem.cumulative_credits ?? '—') }}</strong>
              </div>
            </div>
            <div class="sum-col class-col">
              <div>
                <span>- {{ t('student.transcript.semClassification') }}:</span>
                <strong>{{ semesterClassLabel(sem.semester_gpa4, !!sem.is_current) }}</strong>
              </div>
            </div>
          </div>
        </section>
      </div>
    </template>

    <!-- ===== Điểm ngoài CTĐT (khóa marketplace) ===== -->
    <template v-else>
      <section v-if="external?.summary" class="stats">
        <div class="stat">
          <span>{{ t('student.transcript.extTotal') }}</span>
          <strong>{{ external.summary.total_courses }}</strong>
        </div>
        <div class="stat">
          <span>{{ t('student.transcript.extGraded') }}</span>
          <strong>{{ external.summary.graded }}</strong>
        </div>
        <div class="stat">
          <span>{{ t('student.transcript.passed') }}</span>
          <strong>{{ external.summary.passed }}</strong>
        </div>
        <div class="stat highlight">
          <span>{{ t('student.transcript.average') }}</span>
          <strong>{{ external.summary.average_score ?? '—' }}</strong>
        </div>
      </section>

      <CommonEmptyState v-if="!external?.courses?.length" :description="t('student.transcript.emptyExternal')" />
      <div v-else class="table-wrap card-table">
        <table class="grade-table external">
          <thead>
            <tr>
              <th class="left">{{ t('student.transcript.colCourseName') }}</th>
              <th>{{ t('student.transcript.colFinalScore') }}</th>
              <th>{{ t('student.transcript.colResult') }}</th>
              <th>{{ t('student.transcript.colDetail') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in external.courses" :key="row.enrollment_id">
              <td class="left title">
                <NuxtLink :to="`/learn/${row.course_id}`" class="course-link">{{ row.title }}</NuxtLink>
              </td>
              <td class="score">{{ row.score10 ?? '—' }}</td>
              <td>
                <i
                  v-if="row.passed === true"
                  class="pi pi-check ok"
                  :title="t('student.transcript.pass')"
                />
                <i
                  v-else-if="row.passed === false"
                  class="pi pi-times fail"
                  :title="t('student.transcript.fail')"
                />
                <span v-else class="muted">—</span>
              </td>
              <td>
                <button type="button" class="detail-btn" @click="openDetail(row)">
                  <i class="pi pi-list" />
                  <span>{{ t('student.transcript.colDetail') }}</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <Dialog
      v-model:visible="detailOpen"
      modal
      :header="detailCourse?.title || t('student.transcript.colDetail')"
      :style="{ width: 'min(520px, 94vw)' }"
    >
      <div v-if="!detailCourse?.components?.length" class="empty compact">
        {{ t('student.transcript.noComponents') }}
      </div>
      <table v-else class="detail-table">
        <thead>
          <tr>
            <th>{{ t('student.transcript.colStt') }}</th>
            <th class="left">{{ t('student.transcript.colComponent') }}</th>
            <th>{{ t('student.transcript.colWeight') }}</th>
            <th>{{ t('student.transcript.colComponentScore') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in detailCourse.components" :key="`${c.stt}-${c.name}`">
            <td>{{ c.stt }}</td>
            <td class="left">{{ c.name }}</td>
            <td>{{ c.weight }}%</td>
            <td class="score">{{ c.score ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
      <template #footer>
        <Button
          :label="t('student.transcript.close')"
          icon="pi pi-times"
          severity="warn"
          outlined
          @click="detailOpen = false"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.p-dialog-header-actions .p-dialog-close-button) {
  border: 0;
  border-radius: 0;
  box-shadow: none;
}
:deep(.p-dialog-footer) {
  display: flex;
  justify-content: center;
}
.page { display: flex; flex-direction: column; gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: flex-end; gap: 12px; }

.tabs {
  display: inline-flex;
  align-self: flex-start;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.tab {
  border: 0;
  border-radius: 9px;
  padding: 8px 14px;
  background: transparent;
  color: var(--text-muted);
  font-size: .9rem;
  font-weight: 700;
  cursor: pointer;
}
.tab.on {
  background: var(--brand);
  color: #fff;
}

.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.stat { padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.stat span { display: block; color: var(--text-muted); font-size: .8rem; font-weight: 600; }
.stat strong { font-size: 1.5rem; }
.stat strong.class-label { font-size: 1.25rem; }
.stat.highlight { background: color-mix(in srgb, var(--brand) 10%, var(--surface)); border-color: var(--brand); }

.semester-list { display: flex; flex-direction: column; gap: 16px; }
.semester-block {
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
}
.semester-head {
  padding: 10px 14px;
  background: color-mix(in srgb, var(--brand) 8%, var(--surface));
  border-bottom: 1px solid var(--border);
  font-weight: 800;
  font-size: .92rem;
}
.table-wrap { overflow-x: auto; }
.card-table {
  border: 1px solid var(--border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
}
.grade-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
  font-size: .86rem;
}
.grade-table.external { min-width: 560px; }
.grade-table th,
.grade-table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--border);
  text-align: center;
  vertical-align: middle;
}
.grade-table th {
  background: color-mix(in srgb, var(--surface-subtle, var(--surface)) 90%, transparent);
  color: var(--text-muted);
  font-size: .72rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: .03em;
  white-space: nowrap;
}
.grade-table .left { text-align: left; }
.grade-table .title { font-weight: 700; max-width: 360px; }
.course-link {
  color: inherit;
  text-decoration: none;
  font-weight: 700;
}
.course-link:hover {
  color: var(--brand);
  text-decoration: underline;
}
.grade-table .mono { font-family: ui-monospace, Menlo, monospace; font-size: .8rem; }
.semester-summary {
  display: grid;
  grid-template-columns: 1.15fr 1.15fr 0.9fr;
  gap: 10px 36px;
  padding: 12px 16px 14px;
  background: #d7e0ec;
  border-top: 2px solid #7a1f2b;
  color: #7a1f2b;
}
.sum-col { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.sum-col > div {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 16px;
  font-size: .88rem;
  line-height: 1.5;
}
.sum-col span { color: inherit; font-weight: 600; }
.sum-col strong {
  font-weight: 800;
  flex: 0 0 3.25rem;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.class-col > div strong {
  flex: 0 0 auto;
  min-width: 3.25rem;
}
.semester-summary.blank strong { min-width: 2ch; }
.ok { color: #16a34a; font-size: 1.15rem; font-weight: 800; }
.fail { color: #ea580c; font-size: 1.15rem; font-weight: 800; }
.muted { color: var(--text-muted); font-weight: 500; }

.detail-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: transparent;
  color: var(--brand);
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
}
.detail-btn:hover {
  border-color: color-mix(in srgb, var(--brand) 40%, var(--border));
  background: var(--brand-soft);
}

.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .9rem;
}
.detail-table th,
.detail-table td {
  padding: 10px 8px;
  border-bottom: 1px solid var(--border);
  text-align: center;
}
.detail-table th {
  color: var(--text-muted);
  font-size: .75rem;
  font-weight: 750;
  text-transform: uppercase;
}
.detail-table .left { text-align: left; }

.score { font-weight: 800; }

.empty { padding: 36px; text-align: center; color: var(--text-muted); }
.empty.compact { padding: 18px; }

@media (max-width: 900px) {
  .stats { grid-template-columns: repeat(2, 1fr); }
  .semester-summary { grid-template-columns: 1fr; }
}

@media print {
  .tabs, .print-btn, .detail-btn, :global(.sv-topbar), :global(.desktop-sidebar), :global(.ai-fab) { display: none !important; }
  .page { gap: 10px; }
  .semester-block { break-inside: avoid; }
}
</style>
