<template>
  <div class="min-h-screen flex items-center justify-center bg-surface">
    <div v-if="error" class="text-center space-y-4 max-w-md px-4">
      <span class="material-symbols-outlined text-5xl text-error">error_outline</span>
      <h2 class="text-2xl font-bold font-headline text-on-surface">Không thể vào học</h2>
      <p class="text-on-surface-variant">{{ error }}</p>
      <div class="flex justify-center gap-3 pt-2">
        <NuxtLink :to="`/courses/${courseId}`"><UiButton variant="secondary">Về trang khóa học</UiButton></NuxtLink>
        <NuxtLink to="/my-courses"><UiButton>Khóa học của tôi</UiButton></NuxtLink>
      </div>
    </div>
    <div v-else class="text-center space-y-3">
      <span class="material-symbols-outlined text-4xl text-primary animate-spin">refresh</span>
      <p class="text-on-surface-variant font-medium">Đang tải bài học...</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const courseStore = useCourseStore()

const courseId = Number(route.params.courseId)
const error = ref('')

onMounted(async () => {
  try {
    // Fetch course progress to find last watched lesson
    let targetLessonId: number | null = null

    try {
      const progress = await courseStore.fetchCourseProgress(courseId)
      // Find the first uncompleted lesson, or the last watched one
      if (progress?.lessons?.length) {
        const firstUncompleted = progress.lessons.find((l: any) => !l.completed)
        if (firstUncompleted) {
          targetLessonId = firstUncompleted.id
        } else {
          // All completed - go to last lesson
          targetLessonId = progress.lessons[progress.lessons.length - 1].id
        }
      }
    } catch {
      // Progress might fail if not enrolled - fall through to lessons list
    }

    // If no target from progress, get first lesson from lessons list
    if (!targetLessonId) {
      const lessons = await courseStore.fetchLessons(courseId)
      if (lessons && lessons.length > 0) {
        targetLessonId = lessons[0].id
      }
    }

    if (targetLessonId) {
      await router.replace(`/learn/${courseId}/${targetLessonId}`)
    } else {
      error.value = 'Khóa học này chưa có bài học nào.'
    }
  } catch (e: any) {
    if (e?.status === 403 || e?.statusCode === 403) {
      error.value = 'Bạn chưa ghi danh vào khóa học này.'
    } else {
      error.value = 'Không thể tải thông tin khóa học. Vui lòng thử lại.'
    }
  }
})
</script>
