<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface EnrollmentRow {
  id: number
  progress?: number
  course?: { id: number, title: string, thumbnail?: string | null } | null
}

const auth = useAuthStore()
const toast = useToast()
const { t } = useI18n()
const loading = ref(true)
const enrollments = ref<EnrollmentRow[]>([])

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return t('student.dashboard.greetingMorning')
  if (h < 18) return t('student.dashboard.greetingAfternoon')
  return t('student.dashboard.greetingEvening')
})

const continueList = computed(() =>
  enrollments.value.filter(e => e.course).slice(0, 4),
)

const FALLBACK_THUMBS = [
  'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80',
  'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=600&q=80',
  'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=600&q=80',
  'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=600&q=80',
  'https://images.unsplash.com/photo-1547658719-da2b51169166?w=600&q=80',
  'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&q=80',
  'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=600&q=80',
  'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=600&q=80',
]

function thumbFor(course?: { id?: number, title?: string, thumbnail?: string | null } | null) {
  if (course?.thumbnail) return course.thumbnail
  const title = (course?.title || '').toLowerCase()
  if (title.includes('trí tuệ') || title.includes('ai')) return FALLBACK_THUMBS[3]
  if (title.includes('mạng')) return FALLBACK_THUMBS[2]
  if (title.includes('phần mềm') || title.includes('lập trình')) return FALLBACK_THUMBS[1]
  if (title.includes('web')) return FALLBACK_THUMBS[4]
  const id = course?.id || 0
  return FALLBACK_THUMBS[id % FALLBACK_THUMBS.length]
}

async function load() {
  loading.value = true
  try {
    enrollments.value = await useApi<EnrollmentRow[]>('/enrollments')
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.dashboard.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  // Soft claim so streak progresses when SV opens rewards hub elsewhere
  useApi('/points/daily-login', { method: 'POST' }).catch(() => null)
})
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ greeting }}, {{ auth.user?.name || t('student.roleLabel') }}</h1>
        <p>{{ t('student.dashboard.subtitle') }}</p>
      </div>
      <Button :label="t('student.dashboard.browse')" icon="pi pi-shop" @click="navigateTo('/courses')" />
    </header>

    <section class="kpi">
      <span>{{ t('student.dashboard.enrolled') }}</span>
      <strong>{{ enrollments.length }}</strong>
    </section>

    <section class="panel">
      <header class="panel-head"><strong>{{ t('student.dashboard.continue') }}</strong></header>
      <div v-if="loading" class="empty">…</div>
      <div v-else-if="!continueList.length" class="empty">
        {{ t('student.dashboard.empty') }}
        <div class="mt"><Button :label="t('student.dashboard.browse')" text @click="navigateTo('/courses')" /></div>
      </div>
      <div v-else class="cards">
        <button
          v-for="item in continueList"
          :key="item.id"
          type="button"
          class="card"
          @click="navigateTo(`/learn/${item.course!.id}`)"
        >
          <div class="thumb">
            <img :src="thumbFor(item.course)" :alt="item.course?.title || ''">
          </div>
          <div class="copy">
            <strong>{{ item.course?.title }}</strong>
            <span>{{ t('student.dashboard.progress', { n: item.progress || 0 }) }}</span>
          </div>
          <i class="pi pi-play-circle play" />
        </button>
      </div>
    </section>

    <StudentCurriculumEvaluation />
    <StudentPathRecommendations :limit="4" />
    <StudentCourseRecommendations :limit="4" />
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.kpi, .panel {
  border: 1px solid var(--border); border-radius: 14px; padding: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.kpi { max-width: 220px; }
.kpi span { color: var(--text-muted); font-size: .78rem; font-weight: 650; }
.kpi strong { display: block; margin-top: 4px; font-family: var(--font-display); font-size: 1.6rem; }
.panel-head { margin-bottom: 10px; }
.cards { display: grid; gap: 8px; }
.card {
  display: grid; grid-template-columns: 56px minmax(0, 1fr) auto; gap: 12px; align-items: center; width: 100%;
  padding: 10px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle);
  color: var(--text); font: inherit; text-align: left; cursor: pointer;
}
.card:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.thumb { width: 56px; height: 56px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: var(--surface-subtle); }
.thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.copy { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.copy strong {
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  font-weight: 700;
}
.copy span { color: var(--text-muted); font-size: .85rem; font-weight: 500; white-space: nowrap; }
.play { font-size: 1.25rem; color: var(--brand); }
.empty { color: var(--text-muted); }
.mt { margin-top: 8px; }
</style>
