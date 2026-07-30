<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: false, middleware: ['auth'] })

interface Lesson {
  id: number
  title: string
  type?: string
  description?: string | null
  video_url?: string | null
  section_id?: number | null
  order?: number
}

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const courseId = computed(() => Number(route.params.courseId))

const loading = ref(true)
const courseTitle = ref('')
const empty = ref(false)
const lessons = ref<Lesson[]>([])

async function load() {
  loading.value = true
  empty.value = false
  try {
    const [course, progress] = await Promise.all([
      useApi<any>(`/courses/${courseId.value}`),
      useApi<any>(`/courses/${courseId.value}/progress`).catch(() => null),
    ])
    courseTitle.value = course.title || ''
    lessons.value = [...(course.lessons || [])].sort((a, b) => (a.order || 0) - (b.order || 0))

    if (!lessons.value.length) {
      empty.value = true
      return
    }

    const completedIds = new Set<number>(
      (progress?.lessons || progress?.completed_lesson_ids || [])
        .filter((x: any) => x?.completed || typeof x === 'number')
        .map((x: any) => (typeof x === 'number' ? x : x.lesson_id || x.id)),
    )
    const firstIncomplete = lessons.value.find(l => !completedIds.has(l.id))
    const target = firstIncomplete || lessons.value[0]
    if (target) await navigateTo(`/learn/${courseId.value}/${target.id}`, { replace: true })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.learn.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="boot">
    <template v-if="loading">
      <i class="pi pi-spin pi-spinner" />
      <span>…</span>
    </template>
    <template v-else-if="empty">
      <i class="pi pi-book empty-icon" />
      <strong>{{ courseTitle || t('student.learn.emptyTitle') }}</strong>
      <p>{{ t('student.learn.emptyBody') }}</p>
      <div class="actions">
        <Button :label="t('student.transcript.title')" severity="secondary" outlined @click="navigateTo('/student/transcript')" />
        <Button :label="t('student.courses.title')" @click="navigateTo('/student/courses')" />
      </div>
    </template>
    <template v-else>
      <i class="pi pi-spin pi-spinner" />
      <span>{{ courseTitle }}</span>
    </template>
  </div>
</template>

<style scoped>
.boot {
  min-height: 100dvh;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 10px;
  padding: 24px;
  color: var(--text-muted);
  font-weight: 600;
  text-align: center;
}
.empty-icon { font-size: 2rem; opacity: .7; }
.boot strong { color: var(--text); font-size: 1.15rem; }
.boot p { margin: 0; max-width: 360px; font-weight: 500; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 8px; }
</style>
