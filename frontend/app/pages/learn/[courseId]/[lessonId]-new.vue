<template>
  <div class="learn-page">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <NuxtLink :to="`/courses/${courseId}`" class="back-link">
          ← Back to Course
        </NuxtLink>
        <h2 v-if="course">{{ course.title }}</h2>
      </div>

      <!-- Progress -->
      <div v-if="progress" class="progress-section">
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" :style="{ width: progress.percent + '%' }"></div>
        </div>
        <p class="progress-text">{{ progress.percent }}% complete</p>
      </div>

      <!-- Sections & Lessons Navigation -->
      <div class="curriculum-nav">
        <div v-for="section in sections" :key="section.id" class="section-nav">
          <div class="section-title">{{ section.title }}</div>
          <ul class="lesson-list">
            <li
              v-for="lesson in section.lessons"
              :key="lesson.id"
              :class="['lesson-item', lesson.id === currentLessonId ? 'active' : '']"
            >
              <NuxtLink :to="`/learn/${courseId}/${lesson.id}`" class="lesson-link">
                <span class="check-icon" :class="{ completed: isLessonCompleted(lesson.id) }">
                  {{ isLessonCompleted(lesson.id) ? '✓' : '○' }}
                </span>
                <span class="lesson-title">{{ lesson.title }}</span>
                <span v-if="lesson.duration" class="lesson-duration">
                  {{ formatDuration(lesson.duration) }}
                </span>
              </NuxtLink>
            </li>
          </ul>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="video-area">
      <!-- Video Player -->
      <div v-if="lesson" class="video-container">
        <VideoPlayer
          :course-id="courseId"
          :lesson-id="currentLessonId"
          @progress="handleProgress"
          @ended="handleVideoEnded"
        />
      </div>

      <!-- Lesson Info -->
      <div v-if="lesson" class="lesson-info">
        <h1 class="lesson-heading">{{ lesson.title }}</h1>
        <div class="lesson-meta">
          <span v-if="lesson.duration" class="meta-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ formatDuration(lesson.duration) }}
          </span>
          <span v-if="isLessonCompleted(lesson.id)" class="completed-badge">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Completed
          </span>
        </div>
        <div v-if="lesson.description" class="lesson-description">
          <p>{{ lesson.description }}</p>
        </div>
      </div>

      <!-- Navigation Buttons -->
      <div class="nav-buttons">
        <button v-if="prevLesson" @click="navigateToLesson(prevLesson.id)" class="nav-btn prev">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Previous Lesson
        </button>
        <div class="spacer"></div>
        <button
          v-if="!isLessonCompleted(currentLessonId)"
          @click="markAsComplete"
          class="nav-btn complete"
        >
          Mark as Complete
        </button>
        <button v-if="nextLesson" @click="navigateToLesson(nextLesson.id)" class="nav-btn next primary">
          Next Lesson
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'

definePageMeta({
  middleware: ['auth'],
  layout: 'default'
})

const route = useRoute()
const router = useRouter()

const courseId = Number(route.params.courseId)
const currentLessonId = computed(() => Number(route.params.lessonId))

const course = ref<any>(null)
const sections = ref<any[]>([])
const lesson = ref<any>(null)
const progress = ref<any>(null)
const completedLessons = ref<Set<number>>(new Set())

onMounted(() => {
  loadCourse()
  loadSections()
  loadProgress()
})

watch(() => route.params.lessonId, () => {
  loadLesson()
})

const loadCourse = async () => {
  try {
    const { $api } = useNuxtApp()
    const response = await $api(`/courses/${courseId}`)
    course.value = response
  } catch (error) {
    console.error('Failed to load course:', error)
  }
}

const loadSections = async () => {
  try {
    const { $api } = useNuxtApp()
    const response = await $api(`/courses/${courseId}/sections`)
    sections.value = response.data || []
    
    // Load current lesson after sections are loaded
    loadLesson()
  } catch (error) {
    console.error('Failed to load sections:', error)
  }
}

const loadLesson = () => {
  // Find lesson in sections
  for (const section of sections.value) {
    const found = section.lessons?.find((l: any) => l.id === currentLessonId.value)
    if (found) {
      lesson.value = found
      break
    }
  }
}

const loadProgress = async () => {
  try {
    const { $api } = useNuxtApp()
    const response = await $api(`/courses/${courseId}/progress`)
    progress.value = response
    
    // Build completed lessons set
    if (response.completed_lessons) {
      completedLessons.value = new Set(response.completed_lessons.map((l: any) => l.id))
    }
  } catch (error) {
    console.error('Failed to load progress:', error)
  }
}

const isLessonCompleted = (lessonId: number) => {
  return completedLessons.value.has(lessonId)
}

const handleProgress = (data: any) => {
  console.log('Progress update:', data)
  if (data.completed) {
    completedLessons.value.add(currentLessonId.value)
    loadProgress() // Reload progress to update percentage
  }
}

const handleVideoEnded = () => {
  completedLessons.value.add(currentLessonId.value)
  
  // Auto-navigate to next lesson
  if (nextLesson.value) {
    setTimeout(() => {
      navigateToLesson(nextLesson.value.id)
    }, 1500)
  }
}

const markAsComplete = async () => {
  try {
    const { $api } = useNuxtApp()
    await $api(`/courses/${courseId}/lessons/${currentLessonId.value}/progress`, {
      method: 'POST',
      body: {
        watched_seconds: 0,
        is_completed: true
      }
    })
    
    completedLessons.value.add(currentLessonId.value)
    loadProgress()
  } catch (error) {
    console.error('Failed to mark as complete:', error)
  }
}

const allLessons = computed(() => {
  return sections.value.flatMap((section: any) => section.lessons || [])
})

const prevLesson = computed(() => {
  const lessons = allLessons.value
  const currentIndex = lessons.findIndex((l: any) => l.id === currentLessonId.value)
  return currentIndex > 0 ? lessons[currentIndex - 1] : null
})

const nextLesson = computed(() => {
  const lessons = allLessons.value
  const currentIndex = lessons.findIndex((l: any) => l.id === currentLessonId.value)
  return currentIndex < lessons.length - 1 ? lessons[currentIndex + 1] : null
})

const navigateToLesson = (lessonId: number) => {
  router.push(`/learn/${courseId}/${lessonId}`)
}

const formatDuration = (seconds: number) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}
</script>

<style scoped>
.learn-page {
  display: flex;
  min-height: 100vh;
  background: #000;
}

.sidebar {
  width: 350px;
  background: #1a1a1a;
  color: white;
  overflow-y: auto;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 24px;
  border-bottom: 1px solid #333;
}

.back-link {
  display: inline-block;
  color: #9ca3af;
  font-size: 14px;
  margin-bottom: 12px;
  text-decoration: none;
  transition: color 0.2s;
}

.back-link:hover {
  color: #16a34a;
}

.sidebar-header h2 {
  font-size: 18px;
  font-weight: 600;
  line-height: 1.4;
  margin: 0;
}

.progress-section {
  padding: 20px 24px;
  border-bottom: 1px solid #333;
}

.progress-bar-wrap {
  height: 8px;
  background: #333;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #16a34a, #22c55e);
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 13px;
  color: #9ca3af;
  margin: 0;
}

.curriculum-nav {
  padding: 16px 0;
}

.section-nav {
  margin-bottom: 16px;
}

.section-title {
  padding: 12px 24px;
  font-size: 13px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.lesson-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.lesson-item {
  border-left: 3px solid transparent;
  transition: all 0.2s;
}

.lesson-item.active {
  background: #16a34a15;
  border-left-color: #16a34a;
}

.lesson-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 24px;
  color: #d1d5db;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
}

.lesson-item:hover .lesson-link {
  background: #ffffff10;
  color: white;
}

.check-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #4b5563;
  border-radius: 50%;
  font-size: 12px;
  color: #6b7280;
  flex-shrink: 0;
}

.check-icon.completed {
  background: #16a34a;
  border-color: #16a34a;
  color: white;
}

.lesson-title {
  flex: 1;
  line-height: 1.4;
}

.lesson-duration {
  font-size: 12px;
  color: #9ca3af;
  flex-shrink: 0;
}

.video-area {
  flex: 1;
  overflow-y: auto;
  background: #000;
}

.video-container {
  position: relative;
  background: #000;
}

.lesson-info {
  padding: 32px;
  background: #111;
  border-top: 1px solid #222;
}

.lesson-heading {
  font-size: 28px;
  font-weight: 700;
  color: white;
  margin: 0 0 16px 0;
}

.lesson-meta {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #9ca3af;
  font-size: 14px;
}

.meta-item .icon {
  width: 18px;
  height: 18px;
}

.completed-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #16a34a20;
  color: #22c55e;
  font-size: 13px;
  font-weight: 600;
  border-radius: 6px;
}

.completed-badge .icon {
  width: 16px;
  height: 16px;
}

.lesson-description {
  color: #d1d5db;
  line-height: 1.6;
  font-size: 15px;
}

.nav-buttons {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 32px;
  background: #111;
  border-top: 1px solid #222;
}

.spacer {
  flex: 1;
}

.nav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #1a1a1a;
  border: 1px solid #333;
  border-radius: 8px;
  color: white;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.nav-btn .icon {
  width: 18px;
  height: 18px;
}

.nav-btn:hover {
  background: #222;
  border-color: #16a34a;
}

.nav-btn.primary {
  background: #16a34a;
  border-color: #16a34a;
}

.nav-btn.primary:hover {
  background: #15803d;
}

.nav-btn.complete {
  background: #16a34a20;
  border-color: #16a34a;
  color: #22c55e;
}

.nav-btn.complete:hover {
  background: #16a34a;
  color: white;
}

@media (max-width: 1024px) {
  .sidebar {
    width: 300px;
  }
}

@media (max-width: 768px) {
  .learn-page {
    flex-direction: column;
  }

  .sidebar {
    width: 100%;
    max-height: 40vh;
  }
}
</style>


