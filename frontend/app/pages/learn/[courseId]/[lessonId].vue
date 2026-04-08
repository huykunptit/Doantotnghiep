<template>
  <div class="learn-page">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <NuxtLink :to="`/courses/${courseId}`" class="back-link">← Tổng quan</NuxtLink>
        <h2 v-if="course">{{ course.title }}</h2>
      </div>

      <!-- Progress bar -->
      <div v-if="progress" class="progress-section">
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" :style="{ width: progress.percent + '%' }" />
        </div>
        <p class="progress-text">{{ progress.percent }}% hoàn thành</p>
      </div>

      <ul class="lesson-nav">
        <li
          v-for="lesson in lessons"
          :key="lesson.id"
          :class="['nav-item', lesson.id === currentLessonId ? 'active' : '']"
        >
          <NuxtLink
            :to="`/learn/${courseId}/${lesson.id}`"
            class="nav-link"
          >
            <span class="check-icon">
              {{ completedSet.has(lesson.id) ? '✓' : '○' }}
            </span>
            <span class="nav-title">{{ lesson.title }}</span>
            <span v-if="lesson.duration" class="nav-dur">{{ formatDuration(lesson.duration) }}</span>
          </NuxtLink>
        </li>
      </ul>
    </aside>

    <!-- Main video area -->
    <main class="video-area">
      <!-- Video Player Component (presigned URL + auto progress) -->
      <div class="video-wrap">
        <VideoPlayer
          v-if="lesson && lesson.video_url !== undefined"
          :key="`${courseId}-${currentLessonId}`"
          :course-id="courseId"
          :lesson-id="currentLessonId"
          :autoplay="false"
          @progress="onPlayerProgress"
          @ended="onPlayerEnded"
        />
        <div v-else-if="!lesson" class="video-placeholder">
          <p>⏳ Đang tải bài học...</p>
        </div>
        <div v-else class="video-placeholder">
          <p>⏳ Video chưa được tải lên hoặc đang xử lý.</p>
        </div>
      </div>

      <div v-if="lesson" class="lesson-info">
        <h1>{{ lesson.title }}</h1>
        <div class="lesson-meta">
          <span v-if="lesson.duration">⏱ {{ formatDuration(lesson.duration) }}</span>
          <span v-if="lesson.is_preview" class="preview-badge">👁 Xem thử miễn phí</span>
          <span v-if="isCompleted" class="completed-badge">✓ Đã hoàn thành</span>
        </div>
        <p v-if="lesson.description" class="lesson-description">{{ lesson.description }}</p>
      </div>

      <!-- Navigation -->
      <div class="nav-btns">
        <button v-if="prevLesson" class="nav-btn" @click="goTo(prevLesson.id)">
          ← Bài trước
        </button>
        <button v-if="nextLesson" class="nav-btn primary" @click="goTo(nextLesson.id)">
          Bài tiếp →
        </button>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import type { CourseProgress } from '~/stores/course'

// Lesson type mở rộng với các fields mới
interface LessonExtended {
  id: number
  course_id: number
  section_id?: number | null
  title: string
  description?: string | null
  video_url?: string | null
  video_status?: 'pending' | 'processing' | 'ready' | 'failed'
  video_size?: string | null
  order: number
  duration: number
  is_preview?: boolean
  locked?: boolean
}

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const courseStore = useCourseStore()

const courseId = Number(route.params.courseId)
const currentLessonId = computed(() => Number(route.params.lessonId))

const course = ref(courseStore.currentCourse)
const lesson = ref<LessonExtended | null>(null)
const lessons = ref<LessonExtended[]>([])
const progress = ref<CourseProgress | null>(null)

const completedSet = computed(() => {
  const set = new Set<number>()
  progress.value?.lessons?.forEach((l) => { if (l.completed) set.add(l.id) })
  return set
})

const isCompleted = computed(() => completedSet.value.has(currentLessonId.value))

const currentIndex = computed(() =>
  lessons.value.findIndex((l) => l.id === currentLessonId.value)
)
const prevLesson = computed(() =>
  currentIndex.value > 0 ? lessons.value[currentIndex.value - 1] : null
)
const nextLesson = computed(() =>
  currentIndex.value < lessons.value.length - 1 ? lessons.value[currentIndex.value + 1] : null
)

function formatDuration(seconds: number) {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

async function loadLesson() {
  try {
    lesson.value = await courseStore.fetchLesson(courseId, currentLessonId.value) as LessonExtended
  } catch {
    lesson.value = null
  }
}

// Handlers từ VideoPlayer component
const onPlayerProgress = async (data: { watched_seconds: number; completed: boolean }) => {
  if (data.completed) {
    try {
      progress.value = await courseStore.fetchCourseProgress(courseId)
    } catch { }
  }
}

const onPlayerEnded = async () => {
  try {
    progress.value = await courseStore.fetchCourseProgress(courseId)
  } catch { }
}

function goTo(lessonId: number) {
  router.push(`/learn/${courseId}/${lessonId}`)
}

async function init() {
  if (!courseStore.currentCourse || courseStore.currentCourse.id !== courseId) {
    await courseStore.fetchCourse(courseId)
  }
  course.value = courseStore.currentCourse

  const data = await courseStore.fetchLessons(courseId)
  lessons.value = Array.isArray(data)
    ? data.filter((l: any) => !l.locked) as LessonExtended[]
    : []

  try {
    progress.value = await courseStore.fetchCourseProgress(courseId)
  } catch { }

  await loadLesson()
}

watch(currentLessonId, async () => {
  await loadLesson()
})

onMounted(init)
</script>

<style scoped>
.learn-page { display: grid; grid-template-columns: 300px 1fr; min-height: calc(100vh - 64px); gap: 0; margin: -20px -16px; }

/* Sidebar */
.sidebar { background: #1f2937; color: #f3f4f6; overflow-y: auto; display: flex; flex-direction: column; }
.sidebar-header { padding: 20px 16px 12px; border-bottom: 1px solid #374151; }
.back-link { font-size: 12px; color: #9ca3af; text-decoration: none; display: block; margin-bottom: 8px; }
.back-link:hover { color: #f3f4f6; }
.sidebar-header h2 { font-size: 14px; font-weight: 700; color: #f9fafb; line-height: 1.4; }
.progress-section { padding: 12px 16px; border-bottom: 1px solid #374151; }
.progress-bar-wrap { height: 4px; background: #374151; border-radius: 2px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: #10b981; border-radius: 2px; transition: width 0.4s; }
.progress-text { font-size: 11px; color: #9ca3af; margin-top: 6px; }
.lesson-nav { list-style: none; padding: 8px 0; margin: 0; flex: 1; overflow-y: auto; }
.nav-item { border-left: 3px solid transparent; }
.nav-item.active { border-left-color: #10b981; background: rgba(16,185,129,0.1); }
.nav-link { display: flex; align-items: center; gap: 8px; padding: 10px 16px; text-decoration: none; color: #d1d5db; font-size: 13px; transition: background 0.15s; }
.nav-link:hover { background: rgba(255,255,255,0.05); }
.nav-item.active .nav-link { color: #f9fafb; }
.check-icon { font-size: 12px; flex-shrink: 0; width: 16px; color: #10b981; }
.nav-title { flex: 1; line-height: 1.4; }
.nav-dur { font-size: 11px; color: #6b7280; flex-shrink: 0; }

/* Video area */
.video-area { background: #f8fafc; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.video-placeholder { background: #111827; border-radius: 12px; min-height: 360px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 15px; }
.video-wrap { background: #000; border-radius: 12px; overflow: hidden; }
.video-player { width: 100%; max-height: 540px; display: block; }
.lesson-info { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; }
.lesson-info h1 { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 8px; }
.lesson-meta { display: flex; align-items: center; gap: 12px; color: #6b7280; font-size: 14px; flex-wrap: wrap; }
.lesson-description { margin-top: 12px; color: #374151; font-size: 15px; line-height: 1.6; }
.completed-badge { background: #d1fae5; color: #065f46; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; }
.preview-badge { background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 12px; }
.nav-btns { display: flex; gap: 12px; }
.nav-btn { padding: 10px 20px; border: 1px solid #d1d5db; background: #fff; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; color: #374151; }
.nav-btn.primary { background: #111827; color: #fff; border-color: #111827; }
.nav-btn:hover { background: #f3f4f6; }
.nav-btn.primary:hover { background: #1f2937; }

@media (max-width: 768px) {
  .learn-page { grid-template-columns: 1fr; }
  .sidebar { max-height: 240px; }
  .video-area { padding: 12px; }
}
</style>
