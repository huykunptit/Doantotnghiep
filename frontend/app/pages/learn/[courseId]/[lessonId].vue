<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: false, middleware: ['auth'] })

interface Lesson {
  id: number
  title: string
  type?: string
  description?: string | null
  video_url?: string | null
  order?: number
}

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const courseId = computed(() => Number(route.params.courseId))
const lessonId = computed(() => Number(route.params.lessonId))

const loading = ref(true)
const saving = ref(false)
const courseTitle = ref('')
const lessons = ref<Lesson[]>([])
const lesson = ref<Lesson | null>(null)
const completed = ref<Set<number>>(new Set())
const percent = ref(0)
const sidebarOpen = ref(true)

const currentIndex = computed(() => lessons.value.findIndex(l => l.id === lessonId.value))
const nextLesson = computed(() => currentIndex.value >= 0 ? lessons.value[currentIndex.value + 1] : null)

function isEmbed(url?: string | null) {
  if (!url) return false
  return /youtube\.com|youtu\.be|vimeo\.com/i.test(url)
}

function embedUrl(url: string) {
  const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/)
  if (yt) return `https://www.youtube.com/embed/${yt[1]}`
  const vm = url.match(/vimeo\.com\/(\d+)/)
  if (vm) return `https://player.vimeo.com/video/${vm[1]}`
  return url
}

async function loadProgress() {
  try {
    const progress = await useApi<any>(`/courses/${courseId.value}/progress`)
    const ids = new Set<number>()
    for (const item of progress?.lessons || []) {
      if (item?.completed) ids.add(Number(item.lesson_id || item.id))
    }
    for (const id of progress?.completed_lesson_ids || []) ids.add(Number(id))
    completed.value = ids
    percent.value = Number(progress?.percent || progress?.progress_percent || 0)
    if (!percent.value && lessons.value.length) {
      percent.value = Math.round((ids.size / lessons.value.length) * 100)
    }
  }
  catch {
    /* ignore */
  }
}

async function load() {
  loading.value = true
  try {
    const course = await useApi<any>(`/courses/${courseId.value}`)
    courseTitle.value = course.title || ''
    lessons.value = [...(course.lessons || [])].sort((a: Lesson, b: Lesson) => (a.order || 0) - (b.order || 0))
    lesson.value = lessons.value.find(l => l.id === lessonId.value)
      || await useApi<Lesson>(`/courses/${courseId.value}/lessons/${lessonId.value}`).catch(() => null)
    await loadProgress()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.learn.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function markComplete() {
  saving.value = true
  try {
    await useApi(`/courses/${courseId.value}/lessons/${lessonId.value}/progress`, {
      method: 'PUT',
      body: { completed: true, progress_percent: 100 },
    })
    completed.value = new Set([...completed.value, lessonId.value])
    percent.value = lessons.value.length
      ? Math.round((completed.value.size / lessons.value.length) * 100)
      : percent.value
    toast.add({ severity: 'success', summary: t('student.learn.completed'), life: 1800 })
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.learn.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    saving.value = false
  }
}

function goLesson(id: number) {
  navigateTo(`/learn/${courseId.value}/${id}`)
}

watch([courseId, lessonId], load, { immediate: true })
</script>

<template>
  <div class="learn">
    <header class="topbar">
      <div class="left">
        <Button icon="pi pi-arrow-left" text rounded @click="navigateTo(`/courses/${courseId}`)" />
        <div>
          <strong>{{ courseTitle }}</strong>
          <span>{{ t('student.learn.progress', { n: percent }) }}</span>
        </div>
      </div>
      <div class="right">
        <Button icon="pi pi-list" text rounded class="mobile-only" @click="sidebarOpen = !sidebarOpen" />
        <NuxtLink :to="`/courses/${courseId}`">{{ t('student.learn.backCourse') }}</NuxtLink>
      </div>
      <div class="strip" :style="{ width: `${percent}%` }" />
    </header>

    <div class="body">
      <aside class="sidebar" :class="{ open: sidebarOpen }">
        <h2>{{ t('student.learn.curriculum') }}</h2>
        <button
          v-for="(item, index) in lessons"
          :key="item.id"
          type="button"
          class="lesson-link"
          :class="{ on: item.id === lessonId, done: completed.has(item.id) }"
          @click="goLesson(item.id)"
        >
          <i :class="completed.has(item.id) ? 'pi pi-check-circle' : 'pi pi-circle'" />
          <span>{{ index + 1 }}. {{ item.title }}</span>
        </button>
      </aside>

      <main class="main">
        <div v-if="loading" class="empty">…</div>
        <template v-else-if="lesson">
          <h1>{{ lesson.title }}</h1>

          <section v-if="lesson.type === 'video'" class="player">
            <iframe v-if="lesson.video_url && isEmbed(lesson.video_url)" :src="embedUrl(lesson.video_url)" allowfullscreen />
            <video v-else-if="lesson.video_url" :src="lesson.video_url" controls />
            <div v-else class="placeholder">{{ t('student.learn.noVideo') }}</div>
          </section>

          <section v-else-if="lesson.type === 'page'" class="page-content">
            <div v-if="lesson.description" v-html="lesson.description" />
            <p v-else class="empty">{{ t('student.learn.pageEmpty') }}</p>
          </section>

          <section v-else-if="lesson.type === 'file' || lesson.type === 'document'" class="file-box">
            <p>{{ lesson.description || lesson.title }}</p>
            <a v-if="lesson.video_url" :href="lesson.video_url" target="_blank" rel="noopener">{{ t('student.learn.openFile') }}</a>
          </section>

          <section v-else-if="lesson.type === 'quiz'">
            <StudentLessonQuiz :course-id="courseId" :lesson-id="lessonId" @completed="markComplete" />
          </section>

          <section v-else-if="lesson.type === 'assignment'">
            <StudentLessonAssignment :course-id="courseId" :lesson-id="lessonId" @completed="markComplete" />
          </section>

          <section v-else class="page-content">
            <div v-if="lesson.description" v-html="lesson.description" />
            <a v-if="lesson.video_url" :href="lesson.video_url" target="_blank">{{ t('student.learn.openFile') }}</a>
          </section>

          <footer class="actions">
            <Button
              v-if="lesson.type !== 'quiz' && lesson.type !== 'assignment'"
              :label="t('student.learn.markDone')"
              icon="pi pi-check"
              :loading="saving"
              :disabled="completed.has(lessonId)"
              @click="markComplete"
            />
            <Button
              v-if="nextLesson"
              :label="t('student.learn.next')"
              icon="pi pi-arrow-right"
              icon-pos="right"
              severity="secondary"
              @click="goLesson(nextLesson.id)"
            />
          </footer>
        </template>
      </main>
    </div>

    <StudentAiChatbot :course-id="courseId" />
  </div>
</template>

<style scoped>
.learn { min-height: 100dvh; background: color-mix(in srgb, var(--canvas) 92%, transparent); }
.topbar {
  position: sticky; top: 0; z-index: 20; display: flex; justify-content: space-between; align-items: center;
  min-height: 56px; padding: 8px 16px; border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 90%, transparent); backdrop-filter: blur(12px);
}
.topbar .strip { position: absolute; left: 0; bottom: 0; height: 3px; background: var(--brand); }
.left, .right { display: flex; align-items: center; gap: 10px; }
.left strong { display: block; font-size: .95rem; }
.left span { color: var(--text-muted); font-size: .78rem; font-weight: 600; }
.right a { color: var(--text-muted); font-weight: 600; font-size: .88rem; }
.right a:hover { color: var(--brand); }
.body { display: grid; grid-template-columns: 280px minmax(0, 1fr); min-height: calc(100dvh - 56px); }
.sidebar {
  border-right: 1px solid var(--border); padding: 14px; overflow: auto;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.sidebar h2 { margin: 0 0 10px; font-size: .9rem; }
.lesson-link {
  display: flex; gap: 8px; align-items: flex-start; width: 100%; margin-bottom: 4px; padding: 8px 10px;
  border: 0; border-radius: 10px; background: transparent; color: var(--text-muted); font: inherit; font-weight: 600;
  text-align: left; cursor: pointer;
}
.lesson-link.on { background: var(--brand-soft); color: var(--brand); }
.lesson-link.done { color: var(--text); }
.main { padding: 20px 22px 40px; max-width: 960px; }
.main h1 { margin: 0 0 16px; font-size: 1.35rem; }
.player { aspect-ratio: 16/9; border-radius: 14px; overflow: hidden; background: #0b1220; }
.player iframe, .player video { width: 100%; height: 100%; border: 0; }
.placeholder, .empty { color: var(--text-muted); padding: 24px; }
.page-content, .file-box {
  padding: 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 94%, transparent); line-height: 1.6; font-weight: 500;
}
.file-box a { color: var(--brand); font-weight: 700; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 18px; }
.mobile-only { display: none; }
@media (max-width: 900px) {
  .body { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .sidebar.open { display: block; position: absolute; z-index: 30; inset: 56px 0 auto 0; max-height: 50vh; }
  .mobile-only { display: inline-flex; }
}
</style>
