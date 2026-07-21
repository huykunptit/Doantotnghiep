<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CategoryItem { id: number, name: string }
interface LessonItem {
  id: number
  title: string
  type: string
  duration?: number
  is_preview?: boolean
  order?: number
  description?: string | null
  video_url?: string | null
}
interface SectionItem {
  id: number
  title: string
  position: number
  lessons?: LessonItem[]
}
interface CourseDetail {
  id: number
  title: string
  description?: string | null
  price?: number
  status?: string
  thumbnail?: string | null
  category_id?: number | null
  category?: { id: number, name: string } | null
  instructor?: { id?: number, name?: string } | null
}

type Selection =
  | { kind: 'course' }
  | { kind: 'section', sectionId: number }
  | { kind: 'lesson', sectionId: number, lessonId: number }

const CONTENT_TYPES = ['video', 'page', 'file', 'quiz'] as const

const route = useRoute()
const courseId = computed(() => String(route.params.id))
const runtimeConfig = useRuntimeConfig()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(true)
const savingMeta = ref(false)
const savingLesson = ref(false)
const publishing = ref(false)
const course = ref<CourseDetail | null>(null)
const sections = ref<SectionItem[]>([])
const categories = ref<CategoryItem[]>([])
const selection = ref<Selection>({ kind: 'course' })
const expanded = ref<Record<number, boolean>>({})

const metaForm = reactive({
  title: '',
  description: '',
  price: 0,
  category_id: null as number | null,
  status: 'draft',
})
const thumbnailFile = ref<File | null>(null)

const sectionDialogOpen = ref(false)
const sectionForm = reactive({ id: null as number | null, title: '' })

const pickerOpen = ref(false)
const pickerSectionId = ref<number | null>(null)

const lessonForm = reactive({
  id: null as number | null,
  section_id: null as number | null,
  title: '',
  type: 'video',
  description: '',
  video_url: '',
  duration: 0,
  is_preview: false,
})
const videoSourceMode = ref<'embed' | 'upload'>('embed')
const videoFile = ref<File | null>(null)
const videoUploading = ref(false)
const videoUploadProgress = ref(0)
const videoUploadError = ref('')
const resourceFile = ref<File | null>(null)
const quizConfig = reactive({ title: '', description: '', time_limit: 15, pass_score: 70 })

const categoryOptions = computed(() =>
  categories.value.map(c => ({ label: c.name, value: c.id })),
)

const statusOptions = computed(() => [
  { label: t('admin.builder.statuses.draft'), value: 'draft' },
  { label: t('admin.builder.statuses.pending_review'), value: 'pending_review' },
  { label: t('admin.builder.statuses.published'), value: 'published' },
  { label: t('admin.builder.statuses.closed'), value: 'closed' },
])

const contentTypeOptions = computed(() =>
  CONTENT_TYPES.map(key => ({
    key,
    label: t(`admin.builder.types.${key}`),
    icon: typeIcon(key),
  })),
)

function typeIcon(type: string) {
  return ({
    video: 'pi pi-play-circle',
    page: 'pi pi-file-edit',
    file: 'pi pi-file',
    quiz: 'pi pi-question-circle',
  } as Record<string, string>)[type] || 'pi pi-book'
}

function typeLabel(type: string) {
  const key = `admin.builder.types.${type}`
  const translated = t(key)
  return translated === key ? type : translated
}

function detectVideoProvider(url?: string | null) {
  if (!url) return 'unknown'
  const normalized = url.toLowerCase()
  if (normalized.includes('youtube.com') || normalized.includes('youtu.be')) return 'youtube'
  if (normalized.includes('drive.google.com')) return 'gdrive'
  if (normalized.includes('1drv.ms') || normalized.includes('onedrive.live.com')) return 'onedrive'
  return 'file'
}

function inferVideoSourceMode(url?: string | null) {
  return ['youtube', 'gdrive', 'onedrive'].includes(detectVideoProvider(url)) ? 'embed' : (url ? 'upload' : 'embed')
}

function errDetail(error: any) {
  return error?.data?.message || error?.message
}

async function loadCategories() {
  try {
    categories.value = await useApi<CategoryItem[]>('/admin/categories')
  }
  catch {
    try {
      categories.value = await useApi<CategoryItem[]>('/courses/categories')
    }
    catch {
      categories.value = []
    }
  }
}

async function loadCourse() {
  try {
    const detail = await useApi<CourseDetail>(`/admin/courses/${courseId.value}`).catch(() => null)
    if (detail) {
      course.value = detail
    }
    else {
      course.value = await useApi<CourseDetail>(`/courses/${courseId.value}`)
    }
    metaForm.title = course.value.title || ''
    metaForm.description = course.value.description || ''
    metaForm.price = Number(course.value.price || 0)
    metaForm.category_id = course.value.category_id ?? course.value.category?.id ?? null
    metaForm.status = course.value.status || 'draft'
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.loadError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
}

async function loadCurriculum() {
  loading.value = true
  try {
    const res = await useApi<{ data: SectionItem[] }>(`/courses/${courseId.value}/sections`)
    sections.value = res.data || []
    for (const section of sections.value) {
      if (expanded.value[section.id] === undefined) expanded.value[section.id] = true
    }
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.curriculumError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function selectCourse() {
  selection.value = { kind: 'course' }
}

function selectLesson(sectionId: number, lessonId: number) {
  selection.value = { kind: 'lesson', sectionId, lessonId }
  openLessonEditor(sectionId, lessonId)
}

function toggleSection(id: number) {
  expanded.value[id] = !expanded.value[id]
}

function openSectionDialog(section?: SectionItem) {
  sectionForm.id = section?.id ?? null
  sectionForm.title = section?.title ?? ''
  sectionDialogOpen.value = true
}

async function saveSection() {
  if (!sectionForm.title.trim()) return
  try {
    if (sectionForm.id) {
      await useApi(`/sections/${sectionForm.id}`, {
        method: 'PUT',
        body: { title: sectionForm.title.trim() },
      })
      toast.add({ severity: 'success', summary: t('admin.builder.sectionUpdated'), life: 2000 })
    }
    else {
      await useApi(`/courses/${courseId.value}/sections`, {
        method: 'POST',
        body: { title: sectionForm.title.trim() },
      })
      toast.add({ severity: 'success', summary: t('admin.builder.sectionCreated'), life: 2000 })
    }
    sectionDialogOpen.value = false
    await loadCurriculum()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.sectionSaveError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
}

function askDeleteSection(section: SectionItem) {
  confirm.require({
    message: t('admin.builder.deleteSectionConfirm', { title: section.title }),
    header: t('admin.builder.deleteSectionTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/sections/${section.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.builder.sectionDeleted'), life: 2000 })
        if (selection.value.kind === 'lesson' && selection.value.sectionId === section.id) {
          selection.value = { kind: 'course' }
        }
        await loadCurriculum()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.builder.sectionDeleteError'),
          detail: errDetail(error),
          life: 3500,
        })
      }
    },
  })
}

async function moveSection(index: number, direction: -1 | 1) {
  const next = index + direction
  if (next < 0 || next >= sections.value.length) return
  const copy = [...sections.value]
  const [item] = copy.splice(index, 1)
  copy.splice(next, 0, item)
  sections.value = copy
  try {
    await useApi(`/courses/${courseId.value}/sections/reorder`, {
      method: 'POST',
      body: {
        sections: copy.map((s, i) => ({ id: s.id, position: i + 1 })),
      },
    })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.reorderError'),
      detail: errDetail(error),
      life: 3500,
    })
    await loadCurriculum()
  }
}

function openContentPicker(sectionId: number) {
  pickerSectionId.value = sectionId
  pickerOpen.value = true
}

function chooseContentType(type: string) {
  pickerOpen.value = false
  resetLessonForm()
  lessonForm.section_id = pickerSectionId.value
  lessonForm.type = type
  lessonForm.id = null
  selection.value = {
    kind: 'lesson',
    sectionId: pickerSectionId.value!,
    lessonId: -1,
  }
}

function resetLessonForm() {
  lessonForm.id = null
  lessonForm.section_id = null
  lessonForm.title = ''
  lessonForm.type = 'video'
  lessonForm.description = ''
  lessonForm.video_url = ''
  lessonForm.duration = 0
  lessonForm.is_preview = false
  videoSourceMode.value = 'embed'
  videoFile.value = null
  videoUploadProgress.value = 0
  videoUploadError.value = ''
  resourceFile.value = null
  Object.assign(quizConfig, { title: '', description: '', time_limit: 15, pass_score: 70 })
}

async function openLessonEditor(sectionId: number, lessonId: number) {
  resetLessonForm()
  lessonForm.section_id = sectionId
  lessonForm.id = lessonId
  try {
    const detail = await useApi<LessonItem>(`/courses/${courseId.value}/lessons/${lessonId}`)
    lessonForm.title = detail.title || ''
    lessonForm.type = detail.type || 'video'
    lessonForm.description = detail.description || ''
    lessonForm.video_url = detail.video_url || ''
    lessonForm.duration = detail.duration || 0
    lessonForm.is_preview = !!detail.is_preview
    if (lessonForm.type === 'video') {
      videoSourceMode.value = inferVideoSourceMode(lessonForm.video_url)
    }
    if (lessonForm.type === 'quiz') {
      try {
        const res = await useApi<{ quiz?: { title?: string, description?: string, time_limit?: number | null, pass_score?: number | null } }>(
          `/courses/${courseId.value}/lessons/${lessonId}/quiz`,
        )
        Object.assign(quizConfig, {
          title: res.quiz?.title || '',
          description: res.quiz?.description || '',
          time_limit: res.quiz?.time_limit || 15,
          pass_score: res.quiz?.pass_score || 70,
        })
      }
      catch {
        /* new quiz lesson */
      }
    }
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.lessonLoadError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
}

async function uploadLessonVideo(lessonId: number) {
  const file = videoFile.value
  if (!file) return

  videoUploading.value = true
  videoUploadProgress.value = 0
  videoUploadError.value = ''

  const token = useCookie<string | null>('sylva-token').value
  const base = String(runtimeConfig.public.apiBase || '').replace(/\/$/, '')
  const url = `${base}/courses/${courseId.value}/lessons/${lessonId}/upload-video`

  try {
    await new Promise<void>((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('POST', url, true)
      if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`)
      xhr.setRequestHeader('Accept', 'application/json')

      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          videoUploadProgress.value = Math.min(100, Math.round((event.loaded / event.total) * 100))
        }
      }

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          videoUploadProgress.value = 100
          resolve()
          return
        }
        let body: any = null
        try { body = JSON.parse(xhr.responseText || '{}') }
        catch { /* ignore */ }
        const errs = body?.errors ? Object.values(body.errors).flat().join(' / ') : ''
        reject(new Error(body?.message || errs || `HTTP ${xhr.status}`))
      }
      xhr.onerror = () => reject(new Error(t('admin.builder.videoNetworkError')))

      const formData = new FormData()
      formData.append('video', file)
      xhr.send(formData)
    })
  }
  catch (error: any) {
    videoUploadError.value = error?.message || t('admin.builder.videoUploadError')
    throw error
  }
  finally {
    videoUploading.value = false
  }
}

async function onVideoFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] || null
  videoFile.value = file
  videoUploadError.value = ''
  videoUploadProgress.value = 0
  if (!file || !lessonForm.id || lessonForm.id < 0) return
  try {
    await uploadLessonVideo(lessonForm.id)
    videoFile.value = null
    toast.add({ severity: 'success', summary: t('admin.builder.videoUploaded'), life: 2200 })
    await loadCurriculum()
  }
  catch {
    /* shown via videoUploadError */
  }
}

async function saveLesson() {
  if (!lessonForm.title.trim() || !lessonForm.section_id) return
  savingLesson.value = true
  try {
    const payload: Record<string, unknown> = {
      title: lessonForm.title.trim(),
      section_id: lessonForm.section_id,
      type: lessonForm.type,
      description: lessonForm.description || undefined,
      duration: lessonForm.duration || 0,
      is_preview: lessonForm.is_preview,
    }

    if (lessonForm.type === 'video' && videoSourceMode.value === 'embed' && lessonForm.video_url) {
      payload.video_url = lessonForm.video_url
    }
    else if (lessonForm.type === 'file' && lessonForm.video_url && !resourceFile.value) {
      payload.video_url = lessonForm.video_url
    }

    const isNew = !lessonForm.id || lessonForm.id < 0
    const lessonResponse = isNew
      ? await useApi<{ lesson: { id: number } }>(`/courses/${courseId.value}/lessons`, {
          method: 'POST',
          body: payload,
        })
      : await useApi<{ lesson: { id: number } }>(`/courses/${courseId.value}/lessons/${lessonForm.id}`, {
          method: 'PUT',
          body: payload,
        })

    const lessonId = lessonResponse.lesson.id

    if (lessonForm.type === 'video' && videoSourceMode.value === 'upload' && videoFile.value) {
      await uploadLessonVideo(lessonId)
      videoFile.value = null
    }

    if (lessonForm.type === 'file' && resourceFile.value) {
      const formData = new FormData()
      formData.append('file', resourceFile.value)
      const attachmentResponse = await useApi<{ attachment: { url: string } }, FormData>(
        `/courses/${courseId.value}/lessons/${lessonId}/attachments`,
        { method: 'POST', body: formData },
      )
      await useApi(`/courses/${courseId.value}/lessons/${lessonId}`, {
        method: 'PUT',
        body: { video_url: attachmentResponse.attachment.url },
      })
      resourceFile.value = null
    }

    if (lessonForm.type === 'quiz') {
      await useApi(`/courses/${courseId.value}/lessons/${lessonId}/quiz`, {
        method: 'POST',
        body: {
          title: quizConfig.title || lessonForm.title,
          description: quizConfig.description || lessonForm.description || null,
          time_limit: quizConfig.time_limit,
          pass_score: quizConfig.pass_score,
          question_ids: [],
        },
      })
    }

    toast.add({
      severity: 'success',
      summary: isNew ? t('admin.builder.lessonCreated') : t('admin.builder.lessonUpdated'),
      life: 2200,
    })
    await loadCurriculum()
    selection.value = { kind: 'lesson', sectionId: lessonForm.section_id, lessonId }
    lessonForm.id = lessonId
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.lessonSaveError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
  finally {
    savingLesson.value = false
  }
}

function askDeleteLesson(sectionId: number, lesson: LessonItem) {
  confirm.require({
    message: t('admin.builder.deleteLessonConfirm', { title: lesson.title }),
    header: t('admin.builder.deleteLessonTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/courses/${courseId.value}/lessons/${lesson.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.builder.lessonDeleted'), life: 2000 })
        if (selection.value.kind === 'lesson' && selection.value.lessonId === lesson.id) {
          selection.value = { kind: 'course' }
        }
        await loadCurriculum()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.builder.lessonDeleteError'),
          detail: errDetail(error),
          life: 3500,
        })
      }
    },
  })
}

async function moveLesson(section: SectionItem, index: number, direction: -1 | 1) {
  const lessons = [...(section.lessons || [])]
  const next = index + direction
  if (next < 0 || next >= lessons.length) return
  const [item] = lessons.splice(index, 1)
  lessons.splice(next, 0, item)
  section.lessons = lessons
  try {
    await useApi(`/courses/${courseId.value}/lessons/reorder`, {
      method: 'POST',
      body: { order: lessons.map(l => l.id) },
    })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.reorderError'),
      detail: errDetail(error),
      life: 3500,
    })
    await loadCurriculum()
  }
}

async function saveCourseMeta() {
  if (!metaForm.title.trim()) return
  savingMeta.value = true
  try {
    if (thumbnailFile.value) {
      const formData = new FormData()
      formData.append('title', metaForm.title.trim())
      formData.append('description', metaForm.description || '')
      formData.append('price', String(metaForm.price || 0))
      if (metaForm.category_id) formData.append('category_id', String(metaForm.category_id))
      formData.append('status', metaForm.status)
      formData.append('thumbnail_file', thumbnailFile.value)
      const res = await useApi<{ course: CourseDetail }, FormData>(`/courses/${courseId.value}`, {
        method: 'PUT',
        body: formData,
      })
      course.value = res.course
      thumbnailFile.value = null
    }
    else {
      const res = await useApi<{ course: CourseDetail }>(`/courses/${courseId.value}`, {
        method: 'PUT',
        body: {
          title: metaForm.title.trim(),
          description: metaForm.description || null,
          price: metaForm.price,
          category_id: metaForm.category_id,
          status: metaForm.status,
        },
      })
      course.value = res.course
    }
    toast.add({ severity: 'success', summary: t('admin.builder.metaSaved'), life: 2200 })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.metaSaveError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
  finally {
    savingMeta.value = false
  }
}

async function publishCourse() {
  publishing.value = true
  try {
    const res = await useApi<{ message?: string, course: CourseDetail }>(`/courses/${courseId.value}/publish`, {
      method: 'POST',
    })
    course.value = res.course
    metaForm.status = res.course.status || metaForm.status
    toast.add({
      severity: 'success',
      summary: t('admin.builder.published'),
      detail: res.message,
      life: 2800,
    })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.builder.publishError'),
      detail: errDetail(error),
      life: 3500,
    })
  }
  finally {
    publishing.value = false
  }
}

const isNewLessonDraft = computed(() =>
  selection.value.kind === 'lesson' && (!lessonForm.id || lessonForm.id < 0),
)

const showLessonEditor = computed(() =>
  selection.value.kind === 'lesson',
)

onMounted(async () => {
  await Promise.all([loadCategories(), loadCourse(), loadCurriculum()])
})
</script>

<template>
  <div class="page builder-page">
    <header class="builder-topbar">
      <div class="top-left">
        <Button
          icon="pi pi-arrow-left"
          severity="secondary"
          text
          rounded
          :aria-label="t('admin.builder.back')"
          @click="navigateTo('/admin/manage-courses')"
        />
        <div class="titles">
          <span class="eyebrow">{{ t('admin.builder.eyebrow') }}</span>
          <h1>{{ course?.title || t('common.loading') }}</h1>
        </div>
      </div>
      <div class="top-actions">
        <Button
          :label="t('admin.builder.courseSettings')"
          icon="pi pi-cog"
          severity="secondary"
          outlined
          @click="selectCourse"
        />
        <Button
          :label="t('common.save')"
          icon="pi pi-save"
          :loading="savingMeta"
          severity="secondary"
          @click="saveCourseMeta"
        />
        <Button
          :label="t('admin.builder.publish')"
          icon="pi pi-send"
          :loading="publishing"
          @click="publishCourse"
        />
      </div>
    </header>

    <div class="builder-layout">
      <aside class="tree-panel surface">
        <div class="tree-head">
          <strong>{{ t('admin.builder.curriculum') }}</strong>
          <Button
            icon="pi pi-plus"
            size="small"
            text
            rounded
            :aria-label="t('admin.builder.addSection')"
            @click="openSectionDialog()"
          />
        </div>

        <div v-if="loading" class="tree-loading">
          <ProgressSpinner style="width:28px;height:28px" stroke-width="4" />
        </div>

        <div v-else-if="!sections.length" class="tree-empty">
          {{ t('admin.builder.emptyCurriculum') }}
          <Button :label="t('admin.builder.addSection')" icon="pi pi-plus" size="small" class="mt-2" @click="openSectionDialog()" />
        </div>

        <div v-else class="tree-list">
          <div v-for="(section, sIndex) in sections" :key="section.id" class="section-block">
            <div class="section-row">
              <button type="button" class="expand" @click="toggleSection(section.id)">
                <i :class="expanded[section.id] ? 'pi pi-chevron-down' : 'pi pi-chevron-right'" />
              </button>
              <button type="button" class="section-title" @click="expanded[section.id] = true">
                <span>{{ t('admin.builder.sectionN', { n: sIndex + 1 }) }}</span>
                <strong>{{ section.title }}</strong>
              </button>
              <div class="row-actions">
                <Button icon="pi pi-arrow-up" text rounded size="small" :disabled="sIndex === 0" @click="moveSection(sIndex, -1)" />
                <Button icon="pi pi-arrow-down" text rounded size="small" :disabled="sIndex === sections.length - 1" @click="moveSection(sIndex, 1)" />
                <Button icon="pi pi-pencil" text rounded size="small" @click="openSectionDialog(section)" />
                <Button icon="pi pi-plus" text rounded size="small" @click="openContentPicker(section.id)" />
                <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="askDeleteSection(section)" />
              </div>
            </div>

            <div v-if="expanded[section.id]" class="lesson-list">
              <div v-if="!section.lessons?.length" class="lesson-empty">
                {{ t('admin.builder.noLessons') }}
              </div>
              <button
                v-for="(lesson, lIndex) in section.lessons"
                :key="lesson.id"
                type="button"
                class="lesson-row"
                :class="{ on: selection.kind === 'lesson' && selection.lessonId === lesson.id }"
                @click="selectLesson(section.id, lesson.id)"
              >
                <i :class="typeIcon(lesson.type)" />
                <div class="lesson-meta">
                  <strong>{{ lIndex + 1 }}. {{ lesson.title }}</strong>
                  <span>{{ typeLabel(lesson.type) }}</span>
                </div>
                <div class="row-actions" @click.stop>
                  <Button icon="pi pi-arrow-up" text rounded size="small" :disabled="lIndex === 0" @click="moveLesson(section, lIndex, -1)" />
                  <Button icon="pi pi-arrow-down" text rounded size="small" :disabled="lIndex === (section.lessons?.length || 0) - 1" @click="moveLesson(section, lIndex, 1)" />
                  <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="askDeleteLesson(section.id, lesson)" />
                </div>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <section class="editor-panel surface">
        <template v-if="!showLessonEditor">
          <div class="editor-head">
            <div>
              <span class="eyebrow">{{ t('admin.builder.courseSettings') }}</span>
              <h2>{{ t('admin.builder.metaTitle') }}</h2>
            </div>
            <Button :label="t('common.save')" icon="pi pi-save" :loading="savingMeta" @click="saveCourseMeta" />
          </div>

          <div class="form-grid">
            <label class="field">
              <span>{{ t('admin.builder.fields.title') }}</span>
              <InputText v-model="metaForm.title" class="w-full" />
            </label>
            <label class="field">
              <span>{{ t('admin.builder.fields.description') }}</span>
              <Textarea v-model="metaForm.description" rows="5" class="w-full" auto-resize />
            </label>
            <div class="form-row">
              <label class="field">
                <span>{{ t('admin.builder.fields.price') }}</span>
                <InputNumber v-model="metaForm.price" :min="0" class="w-full" />
              </label>
              <label class="field">
                <span>{{ t('admin.builder.fields.category') }}</span>
                <Select
                  v-model="metaForm.category_id"
                  :options="categoryOptions"
                  option-label="label"
                  option-value="value"
                  show-clear
                  class="w-full"
                />
              </label>
              <label class="field">
                <span>{{ t('admin.builder.fields.status') }}</span>
                <Select
                  v-model="metaForm.status"
                  :options="statusOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </label>
            </div>
            <label class="field">
              <span>{{ t('admin.builder.fields.thumbnail') }}</span>
              <div class="thumb-row">
                <img v-if="course?.thumbnail" :src="course.thumbnail" alt="" class="thumb-preview">
                <input type="file" accept="image/*" @change="(e) => thumbnailFile = (e.target as HTMLInputElement).files?.[0] || null">
              </div>
              <small v-if="thumbnailFile" class="hint">{{ thumbnailFile.name }}</small>
            </label>
          </div>
        </template>

        <template v-else>
          <div class="editor-head">
            <div>
              <span class="eyebrow">{{ typeLabel(lessonForm.type) }}</span>
              <h2>{{ isNewLessonDraft ? t('admin.builder.newLesson') : t('admin.builder.editLesson') }}</h2>
            </div>
            <Button :label="t('common.save')" icon="pi pi-save" :loading="savingLesson || videoUploading" @click="saveLesson" />
          </div>

          <div class="form-grid">
            <label class="field">
              <span>{{ t('admin.builder.fields.lessonTitle') }}</span>
              <InputText v-model="lessonForm.title" class="w-full" />
            </label>

            <div class="form-row">
              <label class="field">
                <span>{{ t('admin.builder.fields.duration') }}</span>
                <InputNumber v-model="lessonForm.duration" :min="0" class="w-full" />
              </label>
              <label class="field check-field">
                <span>{{ t('admin.builder.fields.preview') }}</span>
                <div class="check-row">
                  <Checkbox v-model="lessonForm.is_preview" :binary="true" input-id="preview" />
                  <label for="preview">{{ t('admin.builder.fields.previewHint') }}</label>
                </div>
              </label>
            </div>

            <label class="field">
              <span>
                {{ lessonForm.type === 'page'
                  ? t('admin.builder.fields.pageContent')
                  : t('admin.builder.fields.description') }}
              </span>
              <Textarea v-model="lessonForm.description" rows="8" class="w-full" auto-resize />
            </label>

            <template v-if="lessonForm.type === 'video'">
              <div class="source-tabs">
                <button type="button" :class="{ on: videoSourceMode === 'embed' }" @click="videoSourceMode = 'embed'">
                  {{ t('admin.builder.videoEmbed') }}
                </button>
                <button type="button" :class="{ on: videoSourceMode === 'upload' }" @click="videoSourceMode = 'upload'">
                  {{ t('admin.builder.videoUpload') }}
                </button>
              </div>
              <label v-if="videoSourceMode === 'embed'" class="field">
                <span>{{ t('admin.builder.fields.videoUrl') }}</span>
                <InputText v-model="lessonForm.video_url" class="w-full" placeholder="https://..." />
              </label>
              <label v-else class="field">
                <span>{{ t('admin.builder.fields.videoFile') }}</span>
                <input type="file" accept="video/mp4,video/webm,video/quicktime,.mp4,.mov,.webm,.mkv,.m4v,.avi" @change="onVideoFileSelected">
                <ProgressBar v-if="videoUploading || videoUploadProgress" :value="videoUploadProgress" class="mt-2" />
                <small v-if="videoUploadError" class="error">{{ videoUploadError }}</small>
                <small v-if="lessonForm.video_url && !videoFile" class="hint">{{ lessonForm.video_url }}</small>
              </label>
            </template>

            <template v-if="lessonForm.type === 'file'">
              <label class="field">
                <span>{{ t('admin.builder.fields.fileUrl') }}</span>
                <InputText v-model="lessonForm.video_url" class="w-full" placeholder="https://..." />
              </label>
              <label class="field">
                <span>{{ t('admin.builder.fields.fileUpload') }}</span>
                <input type="file" @change="(e) => resourceFile = (e.target as HTMLInputElement).files?.[0] || null">
                <small v-if="resourceFile" class="hint">{{ resourceFile.name }}</small>
              </label>
            </template>

            <template v-if="lessonForm.type === 'quiz'">
              <div class="quiz-box">
                <label class="field">
                  <span>{{ t('admin.builder.fields.quizTitle') }}</span>
                  <InputText v-model="quizConfig.title" class="w-full" />
                </label>
                <label class="field">
                  <span>{{ t('admin.builder.fields.quizDescription') }}</span>
                  <Textarea v-model="quizConfig.description" rows="3" class="w-full" auto-resize />
                </label>
                <div class="form-row">
                  <label class="field">
                    <span>{{ t('admin.builder.fields.timeLimit') }}</span>
                    <InputNumber v-model="quizConfig.time_limit" :min="1" class="w-full" />
                  </label>
                  <label class="field">
                    <span>{{ t('admin.builder.fields.passScore') }}</span>
                    <InputNumber v-model="quizConfig.pass_score" :min="0" :max="100" class="w-full" />
                  </label>
                </div>
                <p class="hint">{{ t('admin.builder.quizHint') }}</p>
              </div>
            </template>
          </div>
        </template>
      </section>
    </div>

    <Dialog
      v-model:visible="sectionDialogOpen"
      modal
      :header="sectionForm.id ? t('admin.builder.editSection') : t('admin.builder.addSection')"
      :style="{ width: 'min(420px, 96vw)' }"
    >
      <label class="field">
        <span>{{ t('admin.builder.fields.sectionTitle') }}</span>
        <InputText v-model="sectionForm.title" class="w-full" autofocus />
      </label>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="sectionDialogOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" @click="saveSection" />
      </template>
    </Dialog>

    <Dialog
      v-model:visible="pickerOpen"
      modal
      :header="t('admin.builder.pickType')"
      :style="{ width: 'min(560px, 96vw)' }"
    >
      <div class="type-grid">
        <button
          v-for="item in contentTypeOptions"
          :key="item.key"
          type="button"
          class="type-card"
          @click="chooseContentType(item.key)"
        >
          <i :class="item.icon" />
          <strong>{{ item.label }}</strong>
          <span>{{ t(`admin.builder.typeHints.${item.key}`) }}</span>
        </button>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.builder-page { gap: 12px; min-height: calc(100vh - 120px); }
.builder-topbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 12px 14px; border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.top-left { display: flex; align-items: center; gap: 8px; min-width: 0; }
.titles { min-width: 0; }
.eyebrow {
  display: block; color: var(--brand); font-size: .74rem; font-weight: 700;
  letter-spacing: .08em; text-transform: uppercase;
}
.titles h1 {
  margin: 2px 0 0; font-size: clamp(1.15rem, 1.8vw, 1.45rem); font-weight: 700;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.top-actions { display: flex; flex-wrap: wrap; gap: 8px; }

.builder-layout {
  display: grid; grid-template-columns: minmax(280px, 360px) minmax(0, 1fr); gap: 12px;
  align-items: start; min-height: 0; flex: 1;
}
.tree-panel, .editor-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
  min-height: 520px;
}
.tree-head, .editor-head {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 12px 14px; border-bottom: 1px solid var(--border);
}
.editor-head h2 { margin: 2px 0 0; font-size: 1.15rem; }
.tree-loading, .tree-empty {
  display: grid; place-items: center; gap: 8px; padding: 36px 16px;
  color: var(--text-muted); text-align: center;
}
.tree-list { padding: 8px; max-height: calc(100vh - 220px); overflow: auto; }
.section-block { margin-bottom: 6px; }
.section-row, .lesson-row {
  display: flex; align-items: center; gap: 4px; width: 100%;
  border-radius: 10px; padding: 6px 4px;
}
.section-row:hover, .lesson-row:hover { background: var(--surface-subtle); }
.lesson-row.on {
  background: var(--brand-soft);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 25%, transparent);
}
.expand {
  width: 28px; height: 28px; border: 0; background: transparent; color: var(--text-muted); cursor: pointer;
}
.section-title {
  flex: 1; min-width: 0; border: 0; background: transparent; text-align: left; cursor: pointer; color: var(--text);
  display: flex; flex-direction: column; gap: 1px; padding: 2px 4px;
}
.section-title span { font-size: .7rem; color: var(--text-muted); font-weight: 600; }
.section-title strong { font-size: .9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.row-actions { display: flex; align-items: center; opacity: .7; }
.section-row:hover .row-actions, .lesson-row:hover .row-actions { opacity: 1; }
.lesson-list { padding: 0 0 4px 18px; display: grid; gap: 2px; }
.lesson-empty { padding: 8px 10px; color: var(--text-muted); font-size: .82rem; }
.lesson-row {
  border: 0; background: transparent; cursor: pointer; color: inherit; font: inherit; text-align: left;
}
.lesson-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.lesson-meta strong { font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lesson-meta span { font-size: .72rem; color: var(--text-muted); }
.lesson-row > i { color: var(--brand); font-size: .95rem; }

.editor-panel { padding-bottom: 16px; }
.form-grid { display: grid; gap: 12px; padding: 14px; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { font-size: .8rem; font-weight: 700; color: var(--brand); }
.check-row { display: flex; align-items: center; gap: 8px; min-height: 38px; }
.hint { color: var(--text-muted); font-size: .78rem; }
.error { color: #b91c1c; font-size: .78rem; }
.thumb-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.thumb-preview {
  width: 72px; height: 72px; border-radius: 12px; object-fit: cover; border: 1px solid var(--border);
}
.source-tabs { display: flex; gap: 6px; }
.source-tabs button {
  border: 1px solid var(--border); background: var(--surface-subtle); color: var(--text);
  border-radius: 999px; padding: 6px 12px; font: inherit; font-size: .82rem; font-weight: 600; cursor: pointer;
}
.source-tabs button.on {
  border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
  background: var(--brand-soft); color: var(--brand);
}
.quiz-box {
  display: grid; gap: 12px; padding: 12px; border-radius: 12px;
  border: 1px dashed color-mix(in srgb, var(--brand) 35%, var(--border));
  background: color-mix(in srgb, var(--brand-soft) 45%, transparent);
}
.type-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.type-card {
  display: flex; flex-direction: column; gap: 4px; align-items: flex-start;
  padding: 14px; border-radius: 14px; border: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 94%, transparent); cursor: pointer; text-align: left; font: inherit; color: inherit;
}
.type-card:hover {
  border-color: color-mix(in srgb, var(--brand) 40%, var(--border));
  background: var(--brand-soft);
}
.type-card i { color: var(--brand); font-size: 1.2rem; }
.type-card strong { font-size: .95rem; }
.type-card span { color: var(--text-muted); font-size: .78rem; line-height: 1.35; }

@media (max-width: 960px) {
  .builder-layout { grid-template-columns: 1fr; }
  .tree-panel, .editor-panel { min-height: 0; }
  .tree-list { max-height: 360px; }
}
</style>
