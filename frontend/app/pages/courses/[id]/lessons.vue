<template>
  <div class="manage-page">
    <div class="page-header">
      <div>
        <NuxtLink :to="`/courses/${courseId}`" class="back-link">← Quay lại khóa học</NuxtLink>
        <h1>Quản lý bài học</h1>
        <p v-if="course" class="course-name">{{ course.title }}</p>
      </div>
      <button class="btn-add" @click="showAdd = true">+ Thêm bài học</button>
    </div>

    <!-- Add lesson modal -->
    <div v-if="showAdd" class="modal-overlay" @click.self="showAdd = false">
      <div class="modal">
        <h2>Thêm bài học mới</h2>
        <div class="field">
          <label>Tiêu đề *</label>
          <input v-model="newLesson.title" type="text" placeholder="Tên bài học" />
        </div>
        <div class="row-2">
          <div class="field">
            <label>Thứ tự</label>
            <input v-model.number="newLesson.order" type="number" min="1" />
          </div>
          <div class="field">
            <label>Thời lượng (giây)</label>
            <input v-model.number="newLesson.duration" type="number" min="0" />
          </div>
        </div>
        <p v-if="addError" class="error-msg">{{ addError }}</p>
        <div class="modal-actions">
          <button class="btn-cancel" @click="showAdd = false">Hủy</button>
          <button :disabled="addLoading" class="btn-submit" @click="handleAdd">
            {{ addLoading ? 'Đang tạo...' : 'Tạo bài học' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Lesson list -->
    <div v-if="loading" class="loading">Đang tải...</div>
    <div v-else-if="lessons.length === 0" class="empty">Chưa có bài học nào. Hãy thêm bài học mới.</div>
    <div v-else class="lesson-list">
      <div v-for="lesson in lessons" :key="lesson.id" class="lesson-row">
        <div class="lesson-info">
          <span class="order-badge">#{{ lesson.order }}</span>
          <div>
            <p class="lesson-title">{{ lesson.title }}</p>
            <p class="lesson-meta">
              {{ lesson.duration ? formatDuration(lesson.duration) : 'Chưa có thời lượng' }}
              <span v-if="lesson.video_url" class="has-video">· Có video</span>
              <span v-else class="no-video">· Chưa có video</span>
            </p>
          </div>
        </div>
        <div class="lesson-actions">
          <label class="upload-btn">
            {{ uploading === lesson.id ? 'Đang tải...' : 'Upload video' }}
            <input
              type="file"
              accept="video/*"
              style="display:none"
              @change="(e) => handleUpload(lesson.id, e)"
            />
          </label>
          <button class="btn-edit-sm" @click="startEdit(lesson)">Sửa</button>
          <button class="btn-delete" @click="handleDelete(lesson.id)">Xóa</button>
        </div>
      </div>
    </div>

    <!-- Edit lesson modal -->
    <div v-if="editLesson" class="modal-overlay" @click.self="editLesson = null">
      <div class="modal">
        <h2>Sửa bài học</h2>
        <div class="field">
          <label>Tiêu đề</label>
          <input v-model="editForm.title" type="text" />
        </div>
        <div class="row-2">
          <div class="field">
            <label>Thứ tự</label>
            <input v-model.number="editForm.order" type="number" min="1" />
          </div>
          <div class="field">
            <label>Thời lượng (giây)</label>
            <input v-model.number="editForm.duration" type="number" min="0" />
          </div>
        </div>
        <p v-if="editError" class="error-msg">{{ editError }}</p>
        <div class="modal-actions">
          <button class="btn-cancel" @click="editLesson = null">Hủy</button>
          <button :disabled="editLoading" class="btn-submit" @click="handleEdit">
            {{ editLoading ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Lesson } from '~/stores/course'

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
