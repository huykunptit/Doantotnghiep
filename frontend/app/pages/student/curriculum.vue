<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface PathCourse {
  id: number
  title: string
  code?: string | null
  slug?: string | null
  credits: number
  /** Đã/đang học môn trong CTĐT (ghi danh học vụ), không phải tiến độ khóa web. */
  studied: boolean
}

interface PathTerm {
  term_number: number
  courses: PathCourse[]
  credits: number
}

interface LearningPathResponse {
  has_curriculum: boolean
  message?: string
  curriculum_name?: string
  curriculum_code?: string
  terms?: PathTerm[]
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const data = ref<LearningPathResponse | null>(null)

const visibleTerms = computed(() =>
  (data.value?.terms || []).filter(term => term.courses.length > 0),
)

const programLabel = computed(() => {
  if (!data.value?.has_curriculum) return t('student.curriculum.planFallback')
  const name = data.value.curriculum_name || t('student.curriculum.planFallback')
  return data.value.curriculum_code ? `${name} (${data.value.curriculum_code})` : name
})

function courseLink(course: PathCourse) {
  return `/courses/${course.id}`
}

function printPage() {
  window.print()
}

async function load() {
  loading.value = true
  try {
    data.value = await useApi<LearningPathResponse>('/me/learning-path')
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('student.curriculum.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
    data.value = { has_curriculum: false, terms: [] }
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div v-if="loading" class="empty">…</div>

    <div v-else-if="!data?.has_curriculum" class="empty-card">
      <i class="pi pi-sitemap" />
      <strong>{{ t('student.curriculum.noCurriculumTitle') }}</strong>
      <p>{{ data?.message || t('student.curriculum.noCurriculum') }}</p>
      <Button :label="t('student.dashboard.browse')" icon="pi pi-shop" @click="navigateTo('/courses')" />
    </div>

    <template v-else>
      <div class="toolbar no-print">
        <div class="program-box" :title="programLabel">
          <i class="pi pi-book" />
          <span>{{ programLabel }}</span>
        </div>
        <div class="toolbar-actions">
          <Button
            :label="t('student.curriculum.print')"
            icon="pi pi-print"
            @click="printPage"
          />
        </div>
      </div>

      <CommonEmptyState v-if="!visibleTerms.length" :description="t('student.curriculum.emptyTerms')" />

      <div v-else class="term-list">
        <section v-for="term in visibleTerms" :key="term.term_number" class="term-block">
          <header class="term-head">
            <span>{{ t('student.curriculum.term', { n: term.term_number }) }}</span>
            <strong class="term-credits">{{ term.credits }}</strong>
          </header>

          <div class="table-wrap">
            <table class="ctdt-table">
              <thead>
                <tr>
                  <th>{{ t('student.curriculum.colStt') }}</th>
                  <th>{{ t('student.curriculum.colCode') }}</th>
                  <th class="left">{{ t('student.curriculum.colTitle') }}</th>
                  <th>{{ t('student.curriculum.colCredits') }}</th>
                  <th>{{ t('student.curriculum.colStudied') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(course, idx) in term.courses" :key="course.id">
                  <td>{{ idx + 1 }}</td>
                  <td class="mono">{{ course.code || '—' }}</td>
                  <td class="left title">
                    <NuxtLink :to="courseLink(course)" class="course-link">{{ course.title }}</NuxtLink>
                  </td>
                  <td>{{ course.credits }}</td>
                  <td>
                    <span
                      v-if="course.studied"
                      class="studied-mark"
                      :title="t('student.curriculum.statusCompleted')"
                    >x</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.toolbar {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
}
.program-box {
  display: inline-flex; align-items: center; gap: 8px; max-width: min(520px, 100%);
  padding: 9px 12px; border: 1px solid var(--border); border-radius: 10px;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  font-weight: 650; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.program-box i { color: var(--brand); flex-shrink: 0; }
.toolbar-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.empty { padding: 28px; text-align: center; color: var(--text-muted); }
.empty-card {
  display: grid; justify-items: center; gap: 10px; text-align: center;
  padding: 40px 20px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.empty-card i { font-size: 2rem; color: var(--brand); }
.empty-card p { margin: 0; color: var(--text-muted); max-width: 42ch; }

.term-list { display: flex; flex-direction: column; gap: 16px; }
.term-block {
  border: 1px solid var(--border); border-radius: 14px; overflow: hidden;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
}
.term-head {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 10px 14px;
  background: color-mix(in srgb, var(--brand) 8%, var(--surface));
  border-bottom: 1px solid var(--border);
  font-weight: 800; font-size: .92rem;
}
.term-credits { color: var(--brand); font-size: 1rem; font-weight: 800; }

.table-wrap { overflow-x: auto; }
.ctdt-table {
  width: 100%; border-collapse: collapse; min-width: 560px; font-size: .86rem;
}
.ctdt-table th,
.ctdt-table td {
  padding: 9px 10px; border-bottom: 1px solid var(--border);
  text-align: center; vertical-align: middle;
}
.ctdt-table th {
  background: color-mix(in srgb, var(--surface-subtle, var(--surface)) 90%, transparent);
  color: var(--text-muted); font-size: .72rem; font-weight: 750; text-transform: uppercase; letter-spacing: .04em;
}
.ctdt-table .left { text-align: left; }
.ctdt-table .title { font-weight: 700; }
.ctdt-table .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; letter-spacing: .02em; }
.course-link { color: inherit; text-decoration: none; font-weight: 700; }
.course-link:hover { color: var(--brand); text-decoration: underline; }

.studied-mark {
  color: var(--brand);
  font-size: .95rem;
  font-weight: 800;
  text-transform: lowercase;
}

@media print {
  .no-print { display: none !important; }
  .page { gap: 8px; }
  .term-block { break-inside: avoid; border-radius: 0; }
  .ctdt-table { min-width: 0; font-size: .75rem; }
}
</style>
