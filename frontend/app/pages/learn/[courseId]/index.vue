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
const lessons = ref<Lesson[]>([])

async function load() {
  loading.value = true
  try {
    const [course, progress] = await Promise.all([
      useApi<any>(`/courses/${courseId.value}`),
      useApi<any>(`/courses/${courseId.value}/progress`).catch(() => null),
    ])
    courseTitle.value = course.title || ''
    lessons.value = [...(course.lessons || [])].sort((a, b) => (a.order || 0) - (b.order || 0))
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
    <i class="pi pi-spin pi-spinner" />
    <span>{{ loading ? '…' : courseTitle }}</span>
  </div>
</template>

<style scoped>
.boot { min-height: 100dvh; display: grid; place-items: center; gap: 10px; color: var(--text-muted); font-weight: 600; }
</style>
