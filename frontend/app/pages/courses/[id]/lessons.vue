<template>
  <NuxtLayout name="instructor">
    <section class="mx-auto max-w-5xl space-y-8">
      <AppPageHeader eyebrow="Instructor" :title="course?.title || 'Quản lý bài học'" description="Thêm, sửa, upload video và sắp xếp các bài học của khóa học.">
        <template #actions>
          <NuxtLink :to="`/courses/${courseId}/edit`"><UiButton variant="secondary">Chỉnh sửa khóa học</UiButton></NuxtLink>
          <UiButton @click="showAdd = true">Thêm bài học</UiButton>
        </template>
      </AppPageHeader>

      <div v-if="showAdd" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/40 p-4" @click.self="showAdd = false">
        <div class="w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Thêm bài học mới</h2>
          <div class="space-y-4">
            <UiInput v-model="newLesson.title" label="Tiêu đề" placeholder="Tên bài học" />
            <div class="grid gap-4 md:grid-cols-2">
              <UiInput v-model="newLesson.order" label="Thứ tự" type="number" />
              <UiInput v-model="newLesson.duration" label="Thời lượng (giây)" type="number" />
            </div>
            <div v-if="addError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ addError }}</div>
            <div class="flex justify-end gap-3">
              <UiButton variant="ghost" @click="showAdd = false">Hủy</UiButton>
              <UiButton :disabled="addLoading" @click="handleAdd">{{ addLoading ? 'Đang tạo...' : 'Tạo bài học' }}</UiButton>
            </div>
          </div>
        </div>
      </div>

      <div v-if="loading" class="grid gap-4">
        <div v-for="item in 5" :key="item" class="h-24 rounded-3xl border border-slate-200 bg-white animate-pulse" />
      </div>
      <UiEmptyState v-else-if="lessons.length === 0" title="Chưa có bài học nào" description="Hãy thêm bài học đầu tiên để bắt đầu xây dựng nội dung khóa học." />
      <div v-else class="space-y-4">
        <UiCard v-for="lesson in lessons" :key="lesson.id">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
              <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-600">#{{ lesson.order }}</div>
              <div>
                <p class="font-semibold text-slate-900">{{ lesson.title }}</p>
                <p class="text-sm text-slate-500">{{ lesson.duration ? formatDuration(lesson.duration) : 'Chưa có thời lượng' }} · {{ lesson.video_url ? 'Có video' : 'Chưa có video' }}</p>
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <label class="inline-flex cursor-pointer items-center rounded-2xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                {{ uploading === lesson.id ? 'Đang tải...' : 'Upload video' }}
                <input type="file" accept="video/*" class="hidden" @change="(e) => handleUpload(lesson.id, e)">
              </label>
              <UiButton size="sm" variant="secondary" @click="startEdit(lesson)">Sửa</UiButton>
              <UiButton size="sm" variant="ghost" @click="handleDelete(lesson.id)">Xóa</UiButton>
            </div>
          </div>
        </UiCard>
      </div>

      <div v-if="editLesson" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/40 p-4" @click.self="editLesson = null">
        <div class="w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Sửa bài học</h2>
          <div class="space-y-4">
            <UiInput v-model="editForm.title" label="Tiêu đề" />
            <div class="grid gap-4 md:grid-cols-2">
              <UiInput v-model="editForm.order" label="Thứ tự" type="number" />
              <UiInput v-model="editForm.duration" label="Thời lượng (giây)" type="number" />
            </div>
            <div v-if="editError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ editError }}</div>
            <div class="flex justify-end gap-3">
              <UiButton variant="ghost" @click="editLesson = null">Hủy</UiButton>
              <UiButton :disabled="editLoading" @click="handleEdit">{{ editLoading ? 'Đang lưu...' : 'Lưu' }}</UiButton>
            </div>
          </div>
        </div>
      </div>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Lesson } from '~/stores/course'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()

const courseId = Number(route.params.id)
const loading = ref(true)
const course = ref(courseStore.currentCourse)
const lessons = ref<Lesson[]>([])
const showAdd = ref(false)
const addLoading = ref(false)
const addError = ref('')
const uploading = ref<number | null>(null)
const editLesson = ref<Lesson | null>(null)
const editLoading = ref(false)
const editError = ref('')

const newLesson = reactive({ title: '', order: 1, duration: 0 })
const editForm = reactive({ title: '', order: 1, duration: 0 })

function formatDuration(s: number) {
  const m = Math.floor(s / 60)
  const sec = s % 60
  return `${m}:${String(sec).padStart(2, '0')}`
}

async function load() {
  loading.value = true
  try {
    if (auth.token && !auth.user) {
      await auth.fetchMe()
    }

    if (!courseStore.currentCourse || courseStore.currentCourse.id !== courseId) {
      await courseStore.fetchCourse(courseId)
    }
    course.value = courseStore.currentCourse

    if (!auth.user?.roles?.includes('admin') && Number(course.value?.user_id) !== Number(auth.user?.id)) {
      router.push(`/courses/${courseId}`)
      return
    }

    const data = await courseStore.fetchLessons(courseId)
    lessons.value = Array.isArray(data) ? data : []
    if (lessons.value.length > 0) {
      newLesson.order = Math.max(...lessons.value.map((l) => l.order)) + 1
    }
  } finally {
    loading.value = false
  }
}

async function handleAdd() {
  if (!newLesson.title.trim()) return
  addLoading.value = true
  addError.value = ''
  try {
    const lesson = await courseStore.createLesson(courseId, { ...newLesson })
    lessons.value.push(lesson)
    newLesson.title = ''
    newLesson.order = lesson.order + 1
    showAdd.value = false
  } catch (e: any) {
    addError.value = e?.data?.message || 'Không thể tạo bài học.'
  } finally {
    addLoading.value = false
  }
}

async function handleUpload(lessonId: number, event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploading.value = lessonId
  try {
    const formData = new FormData()
    formData.append('video', file)
    const config = useRuntimeConfig()
    const token = auth.token
    await $fetch(`/courses/${courseId}/lessons/${lessonId}/upload-video`, {
      baseURL: config.public.apiBase || '/api',
      method: 'POST',
      body: formData,
      headers: { Authorization: `Bearer ${token}` },
    })
    const idx = lessons.value.findIndex((l) => l.id === lessonId)
    if (idx !== -1) lessons.value[idx].video_url = 'queued'
  } catch (e: any) {
    alert(e?.data?.message || 'Upload thất bại.')
  } finally {
    uploading.value = null
  }
}

function startEdit(lesson: Lesson) {
  editLesson.value = lesson
  editForm.title = lesson.title
  editForm.order = lesson.order
  editForm.duration = lesson.duration
}

async function handleEdit() {
  if (!editLesson.value) return
  editLoading.value = true
  editError.value = ''
  try {
    const updated = await courseStore.updateLesson(courseId, editLesson.value.id, { ...editForm })
    const idx = lessons.value.findIndex((l) => l.id === editLesson.value!.id)
    if (idx !== -1) lessons.value[idx] = { ...lessons.value[idx], ...updated }
    editLesson.value = null
  } catch (e: any) {
    editError.value = e?.data?.message || 'Không thể lưu.'
  } finally {
    editLoading.value = false
  }
}

async function handleDelete(lessonId: number) {
  if (!confirm('Xóa bài học này?')) return
  try {
    await courseStore.deleteLesson(courseId, lessonId)
    lessons.value = lessons.value.filter((l) => l.id !== lessonId)
  } catch {
    alert('Không thể xóa bài học.')
  }
}

onMounted(load)
</script>

<style scoped>
.manage-page { max-width: 800px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; }
.back-link { font-size: 13px; color: #6b7280; text-decoration: none; display: block; margin-bottom: 6px; }
h1 { font-size: 24px; font-weight: 700; color: #111827; }
.course-name { font-size: 14px; color: #6b7280; margin-top: 4px; }
.btn-add { background: #111827; color: #fff; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; }
.loading, .empty { text-align: center; padding: 60px; color: #6b7280; }
.lesson-list { display: flex; flex-direction: column; gap: 10px; }
.lesson-row { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.lesson-info { display: flex; align-items: center; gap: 14px; flex: 1; }
.order-badge { background: #f3f4f6; color: #374151; font-weight: 700; font-size: 13px; padding: 4px 10px; border-radius: 6px; flex-shrink: 0; }
.lesson-title { font-weight: 600; font-size: 15px; color: #111827; }
.lesson-meta { font-size: 12px; color: #6b7280; margin-top: 2px; }
.has-video { color: #059669; font-weight: 600; }
.no-video { color: #d97706; }
.lesson-actions { display: flex; gap: 8px; flex-shrink: 0; }
.upload-btn { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-edit-sm { background: #f9fafb; border: 1px solid #d1d5db; color: #374151; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-delete { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 50; display: flex; align-items: center; justify-content: center; }
.modal { background: #fff; border-radius: 16px; padding: 28px; width: 100%; max-width: 480px; }
.modal h2 { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #111827; }
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.field label { font-size: 13px; font-weight: 600; color: #374151; }
.field input { padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
.field input:focus { border-color: #111827; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; }
.btn-cancel { padding: 9px 18px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; background: #fff; cursor: pointer; font-weight: 600; font-size: 14px; }
.btn-submit { padding: 9px 22px; background: #111827; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 8px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; }
</style>
