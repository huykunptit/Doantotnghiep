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
  is_featured?: boolean
  thumbnail?: string | null
  category_id?: number | null
  category?: { id: number, name: string } | null
  instructor?: { id?: number, name?: string } | null
  learning_outcomes?: string[] | null
  benefits?: string[] | null
  requirements?: string[] | null
  level?: string | null
  trailer_url?: string | null
  certificate_template_id?: number | null
  certificate_template?: { id: number, name: string } | null
}

type Selection =
  | { kind: 'course' }
  | { kind: 'section', sectionId: number }
  | { kind: 'lesson', sectionId: number, lessonId: number }

const CONTENT_TYPE_GROUPS = [
  { key: 'content', types: ['video', 'audio', 'page', 'file', 'document', 'scorm', 'h5p'] },
  { key: 'activity', types: ['quiz', 'assignment', 'forum', 'survey'] },
  { key: 'live', types: ['zoom', 'meet', 'virtual_class'] },
] as const

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
  is_featured: false,
  certificate_template_id: null as number | null,
  level: '' as string,
  trailer_url: '',
  learning_outcomes: [''] as string[],
  benefits: [''] as string[],
  requirements: [''] as string[],
})
const certOptions = ref<Array<{ label: string, value: number }>>([])
const thumbnailFile = ref<File | null>(null)
const thumbnailUrl = ref<string | null>(null)

const sectionDialogOpen = ref(false)
const sectionForm = reactive({ id: null as number | null, title: '' })

const pickerOpen = ref(false)
const pickerSectionId = ref<number | null>(null)
const lessonDialogOpen = ref(false)

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
const scormFile = ref<File | null>(null)
const scormConfig = reactive({ entry_url: '', title: '', identifier: '', version: '1.2' })
const liveConfig = reactive({
  provider: 'zoom' as string,
  meeting_id: '',
  meeting_password: '',
  join_url: '',
  start_at: '',
  duration: 60,
})
const quizConfig = reactive({ title: '', description: '', time_limit: 15, pass_score: 70 })
const selectedQuestionIds = ref<number[]>([])
const questionOptions = ref<{ label: string, value: number }[]>([])
const quizSyncQuestions = ref(false)
const assignmentConfig = reactive({
  instructions: '',
  max_file_size: 10240,
  allowed_extensions: 'pdf,doc,docx,zip',
  due_at: '' as string,
})

const categoryOptions = computed(() =>
  categories.value.map(c => ({ label: c.name, value: c.id })),
)

const statusOptions = computed(() => [
  { label: t('admin.builder.statuses.draft'), value: 'draft' },
  { label: t('admin.builder.statuses.pending_review'), value: 'pending_review' },
  { label: t('admin.builder.statuses.published'), value: 'published' },
  { label: t('admin.builder.statuses.rejected'), value: 'rejected' },
  { label: t('admin.builder.statuses.closed'), value: 'closed' },
])

const contentTypeGroups = computed(() =>
  CONTENT_TYPE_GROUPS.map(group => ({
    key: group.key,
    label: t(`admin.builder.pickGroup${group.key === 'content' ? 'Content' : group.key === 'activity' ? 'Activity' : 'Live'}`),
    items: group.types.map(key => ({
      key,
      label: t(`admin.builder.types.${key}`),
      icon: typeIcon(key),
    })),
  })),
)

function typeIcon(type: string) {
  return ({
    video: 'pi pi-play-circle',
    audio: 'pi pi-volume-up',
    page: 'pi pi-file-edit',
    file: 'pi pi-file',
    document: 'pi pi-book',
    scorm: 'pi pi-box',
    h5p: 'pi pi-code',
    quiz: 'pi pi-question-circle',
    assignment: 'pi pi-pencil',
    forum: 'pi pi-comments',
    survey: 'pi pi-chart-bar',
    zoom: 'pi pi-video',
    meet: 'pi pi-globe',
    virtual_class: 'pi pi-desktop',
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

function stripHtml(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

function truncateText(text: string, max = 80) {
  const plain = stripHtml(text)
  return plain.length > max ? `${plain.slice(0, max)}…` : plain
}

function onQuizQuestionsChange() {
  quizSyncQuestions.value = true
}

async function loadQuestionOptions() {
  try {
    const res = await useApi<{ banks: Array<{ id: number }> }>(`/courses/${courseId.value}/question-banks`)
    const map = new Map<number, { label: string, value: number }>()
    for (const bank of res.banks || []) {
      try {
        const detail = await useApi<{
          questions?: Array<{ id: number, content: string }>
          groups?: Array<{ questions?: Array<{ id: number, content: string }> }>
        }>(`/courses/${courseId.value}/question-banks/${bank.id}`)
        const fromBank = detail.questions || []
        const fromGroups = (detail.groups || []).flatMap(g => g.questions || [])
        for (const q of [...fromBank, ...fromGroups]) {
          if (!map.has(q.id)) {
            map.set(q.id, { label: truncateText(q.content || `#${q.id}`), value: q.id })
          }
        }
      }
      catch {
        /* skip bank */
      }
    }
    questionOptions.value = [...map.values()]
  }
  catch {
    questionOptions.value = []
  }
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

async function loadCertTemplates() {
  try {
    const res = await useApi<any>('/admin/certificates')
    const list = Array.isArray(res) ? res : (res.data || [])
    certOptions.value = list.map((c: any) => ({ label: c.name, value: c.id }))
  }
  catch {
    certOptions.value = []
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
    metaForm.is_featured = !!course.value.is_featured
    metaForm.certificate_template_id = course.value.certificate_template_id
      || course.value.certificate_template?.id
      || null
    metaForm.level = course.value.level || ''
    metaForm.trailer_url = course.value.trailer_url || ''
    metaForm.learning_outcomes = (course.value.learning_outcomes?.length ? [...course.value.learning_outcomes] : [''])
    metaForm.benefits = (course.value.benefits?.length ? [...course.value.benefits] : [''])
    metaForm.requirements = (course.value.requirements?.length ? [...course.value.requirements] : [''])
    thumbnailUrl.value = course.value.thumbnail || null
    thumbnailFile.value = null
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
  mainTab.value = 'info'
}

function onMainTab(key: string | number) {
  const next = String(key) === 'curriculum' ? 'curriculum' : 'info'
  mainTab.value = next
  if (next === 'info') {
    selection.value = { kind: 'course' }
  }
}

function selectLesson(sectionId: number, lessonId: number) {
  mainTab.value = 'curriculum'
  selection.value = { kind: 'lesson', sectionId, lessonId }
  lessonDialogOpen.value = true
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
  mainTab.value = 'curriculum'
  selection.value = {
    kind: 'lesson',
    sectionId: pickerSectionId.value!,
    lessonId: -1,
  }
  lessonDialogOpen.value = true
  if (type === 'quiz') {
    loadQuestionOptions()
  }
  if (type === 'zoom' || type === 'meet') {
    liveConfig.provider = type === 'meet' ? 'meet' : 'zoom'
  }
  if (type === 'virtual_class') {
    liveConfig.provider = 'zoom'
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
  scormFile.value = null
  Object.assign(scormConfig, { entry_url: '', title: '', identifier: '', version: '1.2' })
  Object.assign(liveConfig, {
    provider: 'zoom',
    meeting_id: '',
    meeting_password: '',
    join_url: '',
    start_at: '',
    duration: 60,
  })
  Object.assign(quizConfig, { title: '', description: '', time_limit: 15, pass_score: 70 })
  selectedQuestionIds.value = []
  quizSyncQuestions.value = false
  Object.assign(assignmentConfig, {
    instructions: '',
    max_file_size: 10240,
    allowed_extensions: 'pdf,doc,docx,zip',
    due_at: '',
  })
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
        const res = await useApi<{
          quiz?: {
            title?: string
            description?: string
            time_limit?: number | null
            pass_score?: number | null
            questions?: Array<{ id: number }>
          }
          questions?: Array<{ id: number }>
        }>(
          `/courses/${courseId.value}/lessons/${lessonId}/quiz`,
        )
        Object.assign(quizConfig, {
          title: res.quiz?.title || '',
          description: res.quiz?.description || '',
          time_limit: res.quiz?.time_limit || 15,
          pass_score: res.quiz?.pass_score || 70,
        })
        const ids = res.questions?.map(q => q.id)
          || res.quiz?.questions?.map(q => q.id)
          || []
        selectedQuestionIds.value = ids
        if (ids.length) quizSyncQuestions.value = true
      }
      catch {
        /* new quiz lesson */
      }
      await loadQuestionOptions()
    }
    if (lessonForm.type === 'assignment') {
      try {
        const asg = await useApi<{
          instructions?: string
          max_file_size?: number
          allowed_extensions?: string
          due_at?: string | null
        }>(`/courses/${courseId.value}/lessons/${lessonId}/assignment`)
        Object.assign(assignmentConfig, {
          instructions: asg.instructions || '',
          max_file_size: asg.max_file_size || 10240,
          allowed_extensions: asg.allowed_extensions || 'pdf,doc,docx,zip',
          due_at: asg.due_at ? String(asg.due_at).slice(0, 16) : '',
        })
      }
      catch {
        /* new assignment */
      }
    }
    if (['zoom', 'meet', 'virtual_class'].includes(lessonForm.type)) {
      try {
        const res = await useApi<{
          provider?: string
          meeting_id?: string
          meeting_password?: string
          join_url?: string
          start_at?: string
          duration?: number
        }>(`/courses/${courseId.value}/lessons/${lessonId}/virtual-class`)
        Object.assign(liveConfig, {
          provider: res.provider === 'google_meet' ? 'meet' : (res.provider || lessonForm.type || 'zoom'),
          meeting_id: res.meeting_id || '',
          meeting_password: res.meeting_password || '',
          join_url: res.join_url || '',
          start_at: res.start_at ? String(res.start_at).slice(0, 16) : '',
          duration: res.duration || 60,
        })
      }
      catch {
        /* new live lesson */
      }
    }
    if (['scorm', 'h5p'].includes(lessonForm.type)) {
      try {
        const res = await useApi<{
          entry_url?: string
          title?: string
          identifier?: string
          version?: string
        }>(`/courses/${courseId.value}/lessons/${lessonId}/scorm-package`)
        Object.assign(scormConfig, {
          entry_url: res.entry_url || '',
          title: res.title || '',
          identifier: res.identifier || '',
          version: res.version || '1.2',
        })
      }
      catch {
        /* new scorm/h5p */
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

  const token = useCookie<string | null>('eript-token').value
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
    else if (['file', 'document', 'audio'].includes(lessonForm.type) && lessonForm.video_url && !resourceFile.value) {
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

    if (['file', 'document', 'audio'].includes(lessonForm.type) && resourceFile.value) {
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
      const quizBody: Record<string, unknown> = {
        title: quizConfig.title || lessonForm.title,
        description: quizConfig.description || lessonForm.description || null,
        time_limit: quizConfig.time_limit,
        pass_score: quizConfig.pass_score,
      }
      if (quizSyncQuestions.value || selectedQuestionIds.value.length) {
        quizBody.question_ids = selectedQuestionIds.value
      }
      await useApi(`/courses/${courseId.value}/lessons/${lessonId}/quiz`, {
        method: 'POST',
        body: quizBody,
      })
    }

    if (lessonForm.type === 'assignment') {
      await useApi(`/courses/${courseId.value}/lessons/${lessonId}/assignment`, {
        method: 'POST',
        body: {
          instructions: assignmentConfig.instructions || lessonForm.description || lessonForm.title,
          max_file_size: assignmentConfig.max_file_size,
          allowed_extensions: assignmentConfig.allowed_extensions,
          due_at: assignmentConfig.due_at || null,
        },
      })
    }

    if (['zoom', 'meet', 'virtual_class'].includes(lessonForm.type)) {
      const provider = lessonForm.type === 'meet' || liveConfig.provider === 'meet'
        ? 'google_meet'
        : 'zoom'
      await useApi(`/courses/${courseId.value}/lessons/${lessonId}/virtual-class`, {
        method: 'POST',
        body: {
          provider,
          meeting_id: liveConfig.meeting_id || null,
          meeting_password: liveConfig.meeting_password || null,
          join_url: liveConfig.join_url || null,
          start_url: null,
          start_at: liveConfig.start_at || null,
          duration: liveConfig.duration || 60,
        },
      })
    }

    if (['scorm', 'h5p'].includes(lessonForm.type)) {
      const formData = new FormData()
      formData.append('type', lessonForm.type)
      if (lessonForm.type === 'h5p') {
        const raw = scormConfig.entry_url.trim()
        const match = raw.match(/<iframe[^>]+src=["']([^"']+)["']/i)
        const src = match ? match[1] : raw
        if (src) formData.append('entry_url', src)
      }
      if (lessonForm.type === 'scorm' && scormFile.value) {
        formData.append('scorm_file', scormFile.value)
      }
      else if (lessonForm.type === 'scorm' && scormConfig.entry_url.trim()) {
        formData.append('entry_url', scormConfig.entry_url.trim())
      }
      await useApi(`/courses/${courseId.value}/lessons/${lessonId}/scorm-package`, {
        method: 'POST',
        body: formData,
      })
      scormFile.value = null
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

const mainTab = ref<'curriculum' | 'info'>('info')
const metaTab = ref<'basic' | 'sell' | 'media'>('basic')
const metaTabs = computed(() => [
  { key: 'basic' as const, label: t('admin.builder.metaTabs.basic') },
  { key: 'sell' as const, label: t('admin.builder.metaTabs.sell') },
  { key: 'media' as const, label: t('admin.builder.metaTabs.media') },
])

const mainTabs = computed(() => [
  { key: 'info' as const, label: t('admin.builder.tabInfo'), icon: 'pi pi-info-circle' },
  { key: 'curriculum' as const, label: t('admin.builder.tabCurriculum'), icon: 'pi pi-book' },
])

const levelOptions = computed(() => [
  { label: t('admin.builder.levels.beginner'), value: 'beginner' },
  { label: t('admin.builder.levels.intermediate'), value: 'intermediate' },
  { label: t('admin.builder.levels.advanced'), value: 'advanced' },
])

function metaStatusForApi() {
  if (['draft', 'published', 'closed', 'pending_review', 'rejected'].includes(metaForm.status)) {
    return metaForm.status
  }
  return 'draft'
}

function cleanList(items: string[]) {
  return items.map(s => s.trim()).filter(Boolean)
}

function addListItem(key: 'learning_outcomes' | 'benefits' | 'requirements') {
  metaForm[key].push('')
}

function removeListItem(key: 'learning_outcomes' | 'benefits' | 'requirements', index: number) {
  if (metaForm[key].length <= 1) {
    metaForm[key][0] = ''
    return
  }
  metaForm[key].splice(index, 1)
}

function metaPayload() {
  return {
    title: metaForm.title.trim(),
    description: metaForm.description || null,
    price: metaForm.price,
    category_id: metaForm.category_id,
    status: metaStatusForApi(),
    is_featured: Boolean(metaForm.is_featured),
    certificate_template_id: metaForm.certificate_template_id,
    level: metaForm.level || null,
    trailer_url: metaForm.trailer_url || null,
    learning_outcomes: cleanList(metaForm.learning_outcomes),
    benefits: cleanList(metaForm.benefits),
    requirements: cleanList(metaForm.requirements),
  }
}

async function saveCourseMeta() {
  if (!metaForm.title.trim()) return
  savingMeta.value = true
  try {
    const payload = metaPayload()
    if (thumbnailFile.value) {
      const formData = new FormData()
      formData.append('_method', 'PUT')
      formData.append('title', payload.title)
      formData.append('description', payload.description || '')
      formData.append('price', String(payload.price || 0))
      if (payload.category_id) formData.append('category_id', String(payload.category_id))
      formData.append('status', payload.status)
      formData.append('is_featured', payload.is_featured ? '1' : '0')
      if (payload.certificate_template_id) {
        formData.append('certificate_template_id', String(payload.certificate_template_id))
      }
      else {
        formData.append('certificate_template_id', '')
      }
      formData.append('level', payload.level || '')
      formData.append('trailer_url', payload.trailer_url || '')
      formData.append('learning_outcomes', JSON.stringify(payload.learning_outcomes))
      formData.append('benefits', JSON.stringify(payload.benefits))
      formData.append('requirements', JSON.stringify(payload.requirements))
      formData.append('thumbnail_file', thumbnailFile.value)
      const res = await useApi<{ course: CourseDetail }, FormData>(`/courses/${courseId.value}`, {
        method: 'POST',
        body: formData,
      })
      course.value = res.course
      metaForm.status = res.course.status || metaForm.status
      metaForm.is_featured = Boolean(res.course.is_featured)
      metaForm.certificate_template_id = res.course.certificate_template_id
        || res.course.certificate_template?.id
        || null
      thumbnailUrl.value = res.course.thumbnail || thumbnailUrl.value
      thumbnailFile.value = null
    }
    else {
      const res = await useApi<{ course: CourseDetail }>(`/courses/${courseId.value}`, {
        method: 'PUT',
        body: {
          ...payload,
          is_featured: Boolean(payload.is_featured),
          certificate_template_id: payload.certificate_template_id || null,
          ...(thumbnailUrl.value ? { thumbnail: thumbnailUrl.value } : {}),
        },
      })
      course.value = res.course
      metaForm.status = res.course.status || metaForm.status
      metaForm.is_featured = Boolean(res.course.is_featured)
      metaForm.certificate_template_id = res.course.certificate_template_id
        || res.course.certificate_template?.id
        || null
      thumbnailUrl.value = res.course.thumbnail || thumbnailUrl.value
    }
    toast.add({
      severity: 'success',
      summary: t('admin.builder.metaSaved'),
      detail: metaForm.is_featured ? t('admin.builder.fields.featuredOn') : t('admin.builder.fields.featuredOff'),
      life: 2200,
    })
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

onMounted(async () => {
  await Promise.all([loadCategories(), loadCertTemplates(), loadCourse(), loadCurriculum()])
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


        <nav class="builder-tabnav" role="tablist" aria-label="Course builder tabs">
      <button
        v-for="tab in mainTabs"
        :key="tab.key"
        type="button"
        role="tab"
        class="builder-tabnav__item"
        :class="{ 'is-active': mainTab === tab.key }"
        :aria-selected="mainTab === tab.key"
        @click="onMainTab(tab.key)"
      >
        <i :class="tab.icon" aria-hidden="true" />
        <span>{{ tab.label }}</span>
      </button>
    </nav>

    <div v-if="mainTab === 'info'">
<section class="info-panel surface">
          <div class="editor-head">
            <div>
              <span class="eyebrow">{{ t('admin.builder.tabInfo') }}</span>
              <h2>{{ t('admin.builder.metaTitle') }}</h2>
            </div>
            <Button :label="t('common.save')" icon="pi pi-save" :loading="savingMeta" @click="saveCourseMeta" />
          </div>

          <div class="meta-tabs" role="tablist">
            <button
              v-for="tab in metaTabs"
              :key="tab.key"
              type="button"
              role="tab"
              :class="{ on: metaTab === tab.key }"
              :aria-selected="metaTab === tab.key"
              @click="metaTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <div v-show="metaTab === 'basic'" class="form-grid">
            <label class="field">
              <span>{{ t('admin.builder.fields.title') }}</span>
              <InputText v-model="metaForm.title" class="w-full" />
            </label>
            <label class="field">
              <span>{{ t('admin.builder.fields.description') }}</span>
              <CommonRichTextEditor v-model="metaForm.description" height="220px" />
            </label>
            <div class="form-row">
              <label class="field">
                <span>{{ t('admin.builder.fields.price') }}</span>
                <InputNumber v-model="metaForm.price" :min="0" class="w-full" />
              </label>
              <label class="field">
                <span>{{ t('admin.builder.fields.level') }}</span>
                <Select
                  v-model="metaForm.level"
                  :options="levelOptions"
                  option-label="label"
                  option-value="value"
                  show-clear
                  class="w-full"
                />
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
              <div class="field">
                <span>{{ t('admin.builder.fields.featured') }}</span>
                <div class="check-row">
                  <Checkbox v-model="metaForm.is_featured" binary input-id="course-featured" />
                  <label for="course-featured">{{ t('admin.builder.fields.featuredHint') }}</label>
                </div>
              </div>
              <label class="field">
                <span>{{ t('admin.builder.fields.certificate') }}</span>
                <Select
                  v-model="metaForm.certificate_template_id"
                  :options="certOptions"
                  option-label="label"
                  option-value="value"
                  show-clear
                  class="w-full"
                  :placeholder="t('admin.builder.fields.certificatePh')"
                />
              </label>
            </div>
          </div>

          <div v-show="metaTab === 'sell'" class="form-grid">
            <p class="tab-lead">{{ t('admin.builder.metaTabs.sellLead') }}</p>
            <div class="list-field">
              <div class="list-head">
                <span>{{ t('admin.builder.fields.outcomes') }}</span>
                <Button :label="t('common.add')" icon="pi pi-plus" text size="small" @click="addListItem('learning_outcomes')" />
              </div>
              <p class="list-hint">{{ t('admin.builder.fields.outcomesHint') }}</p>
              <div v-for="(_item, idx) in metaForm.learning_outcomes" :key="`out-${idx}`" class="list-row">
                <CommonRichTextEditor v-model="metaForm.learning_outcomes[idx]" compact :placeholder="t('admin.builder.fields.outcomesPh')" />
                <Button icon="pi pi-times" text rounded severity="secondary" @click="removeListItem('learning_outcomes', idx)" />
              </div>
            </div>

            <div class="list-field">
              <div class="list-head">
                <span>{{ t('admin.builder.fields.benefits') }}</span>
                <Button :label="t('common.add')" icon="pi pi-plus" text size="small" @click="addListItem('benefits')" />
              </div>
              <p class="list-hint">{{ t('admin.builder.fields.benefitsHint') }}</p>
              <div v-for="(_item, idx) in metaForm.benefits" :key="`ben-${idx}`" class="list-row">
                <CommonRichTextEditor v-model="metaForm.benefits[idx]" compact :placeholder="t('admin.builder.fields.benefitsPh')" />
                <Button icon="pi pi-times" text rounded severity="secondary" @click="removeListItem('benefits', idx)" />
              </div>
            </div>

            <div class="list-field">
              <div class="list-head">
                <span>{{ t('admin.builder.fields.requirements') }}</span>
                <Button :label="t('common.add')" icon="pi pi-plus" text size="small" @click="addListItem('requirements')" />
              </div>
              <div v-for="(_item, idx) in metaForm.requirements" :key="`req-${idx}`" class="list-row">
                <CommonRichTextEditor v-model="metaForm.requirements[idx]" compact :placeholder="t('admin.builder.fields.requirementsPh')" />
                <Button icon="pi pi-times" text rounded severity="secondary" @click="removeListItem('requirements', idx)" />
              </div>
            </div>
          </div>

          <div v-show="metaTab === 'media'" class="form-grid">
            <label class="field">
              <span>{{ t('admin.builder.fields.trailer') }}</span>
              <InputText v-model="metaForm.trailer_url" class="w-full" placeholder="https://..." />
            </label>
            <label class="field">
              <span>{{ t('admin.builder.fields.thumbnail') }}</span>
              <CommonMediaUpload
                v-model="thumbnailUrl"
                folder="courses"
                :label="t('admin.builder.fields.thumbnail')"
                :hint="t('upload.imageOnly')"
                variant="thumbnail"
                :placeholder-initial="(metaForm.title || 'C').slice(0, 1).toUpperCase()"
              />
            </label>
          </div>
    </section>
    </div>

    <div v-if="mainTab === 'curriculum'">
      <section class="tree-panel surface curriculum-panel">
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
      </section>
    </div>

    <Dialog
      v-model:visible="lessonDialogOpen"
      modal
      maximizable
      :header="`${typeLabel(lessonForm.type)} — ${isNewLessonDraft ? t('admin.builder.newLesson') : t('admin.builder.editLesson')}`"
      :style="{ width: 'min(900px, 96vw)' }"
      class="lesson-dialog"
    >
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
          <CommonRichTextEditor v-model="lessonForm.description" height="300px" />
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
            <CommonFileDropzone
              v-model="videoFile"
              :label="t('admin.builder.fields.videoFile')"
              hint="MP4, WEBM, MOV — kéo thả hoặc chọn tệp"
              accept="video/mp4,video/webm,video/quicktime,.mp4,.mov,.webm,.mkv,.m4v,.avi"
              :max-size-mb="500"
              :uploading="videoUploading"
              :progress="videoUploadProgress"
              :existing-url="lessonForm.video_url"
              icon="pi pi-video"
            />
            <small v-if="videoUploadError" class="error">{{ videoUploadError }}</small>
          </label>
        </template>

        <template v-if="lessonForm.type === 'file' || lessonForm.type === 'document' || lessonForm.type === 'audio'">
          <label class="field">
            <span>{{ lessonForm.type === 'audio' ? t('admin.builder.fields.audioUrl') : t('admin.builder.fields.fileUrl') }}</span>
            <InputText v-model="lessonForm.video_url" class="w-full" placeholder="https://..." />
          </label>
          <label class="field">
            <span>{{ lessonForm.type === 'audio' ? t('admin.builder.fields.audioUpload') : t('admin.builder.fields.fileUpload') }}</span>
            <CommonFileDropzone
              v-model="resourceFile"
              :label="lessonForm.type === 'audio' ? t('admin.builder.fields.audioUpload') : t('admin.builder.fields.fileUpload')"
              :hint="lessonForm.type === 'audio' ? 'MP3, WAV, M4A… — kéo thả hoặc chọn tệp' : 'PDF, DOC, ZIP… — kéo thả hoặc chọn tệp'"
              :accept="lessonForm.type === 'audio' ? 'audio/*,.mp3,.wav,.m4a,.ogg,.aac' : undefined"
              :max-size-mb="100"
              :existing-url="lessonForm.video_url"
              :icon="lessonForm.type === 'audio' ? 'pi pi-volume-up' : 'pi pi-file'"
            />
          </label>
        </template>

        <template v-if="lessonForm.type === 'assignment'">
          <label class="field">
            <span>{{ t('admin.builder.fields.assignmentInstructions') }}</span>
            <CommonRichTextEditor v-model="assignmentConfig.instructions" height="240px" />
          </label>
          <div class="form-row">
            <label class="field">
              <span>{{ t('admin.builder.fields.assignmentExt') }}</span>
              <InputText v-model="assignmentConfig.allowed_extensions" class="w-full" />
            </label>
            <label class="field">
              <span>{{ t('admin.builder.fields.assignmentMaxMb') }}</span>
              <InputNumber v-model="assignmentConfig.max_file_size" :min="1024" :step="1024" class="w-full" />
            </label>
          </div>
          <label class="field">
            <span>{{ t('admin.builder.fields.assignmentDue') }}</span>
            <InputText v-model="assignmentConfig.due_at" type="datetime-local" class="w-full" />
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
              <CommonRichTextEditor v-model="quizConfig.description" height="160px" />
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
            <label class="field">
              <span>{{ t('admin.builder.fields.quizQuestions') }}</span>
              <MultiSelect
                v-model="selectedQuestionIds"
                :options="questionOptions"
                option-label="label"
                option-value="value"
                display="chip"
                filter
                class="w-full"
                :placeholder="t('admin.builder.fields.quizQuestions')"
                @update:model-value="onQuizQuestionsChange"
              />
            </label>
            <NuxtLink to="/admin/question-bank" class="question-bank-link">
              <Button
                :label="t('admin.menu.questionBank')"
                icon="pi pi-external-link"
                severity="secondary"
                text
                size="small"
              />
            </NuxtLink>
            <p class="hint">{{ t('admin.builder.quizHint') }}</p>
          </div>
        </template>

        <template v-if="['zoom', 'meet', 'virtual_class'].includes(lessonForm.type)">
          <label class="field">
            <span>{{ t('admin.builder.fields.joinUrl') }}</span>
            <InputText v-model="liveConfig.join_url" class="w-full" placeholder="https://..." />
          </label>
          <div class="form-row">
            <label class="field">
              <span>{{ t('admin.builder.fields.meetingId') }}</span>
              <InputText v-model="liveConfig.meeting_id" class="w-full" />
            </label>
            <label class="field">
              <span>{{ t('admin.builder.fields.meetingPassword') }}</span>
              <InputText v-model="liveConfig.meeting_password" class="w-full" />
            </label>
          </div>
          <div class="form-row">
            <label class="field">
              <span>{{ t('admin.builder.fields.startAt') }}</span>
              <InputText v-model="liveConfig.start_at" type="datetime-local" class="w-full" />
            </label>
            <label class="field">
              <span>{{ t('admin.builder.fields.liveDuration') }}</span>
              <InputNumber v-model="liveConfig.duration" :min="15" :step="15" class="w-full" />
            </label>
          </div>
        </template>

        <template v-if="lessonForm.type === 'h5p'">
          <label class="field">
            <span>{{ t('admin.builder.fields.h5pUrl') }}</span>
            <Textarea v-model="scormConfig.entry_url" rows="3" class="w-full" auto-resize placeholder="https://h5p.org/h5p/embed/..." />
            <small class="hint">{{ t('admin.builder.fields.h5pHint') }}</small>
          </label>
        </template>

        <template v-if="lessonForm.type === 'scorm'">
          <label class="field">
            <span>{{ t('admin.builder.fields.scormZip') }}</span>
            <CommonFileDropzone
              v-model="scormFile"
              :label="t('admin.builder.fields.scormZip')"
              hint="ZIP SCORM 1.2 / 2004 — kéo thả hoặc chọn tệp"
              accept=".zip,application/zip"
              :max-size-mb="200"
              :existing-url="scormConfig.entry_url"
              icon="pi pi-box"
            />
          </label>
          <label class="field">
            <span>{{ t('admin.builder.fields.fileUrl') }}</span>
            <InputText v-model="scormConfig.entry_url" class="w-full" placeholder="https://... (tuỳ chọn nếu đã upload ZIP)" />
          </label>
        </template>
      </div>

      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="lessonDialogOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-save" :loading="savingLesson || videoUploading" @click="saveLesson" />
      </template>
    </Dialog>

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
      :style="{ width: 'min(760px, 96vw)' }"
    >
      <div class="type-groups">
        <div v-for="group in contentTypeGroups" :key="group.key" class="type-group">
          <h4>{{ group.label }}</h4>
          <div class="type-grid">
            <button
              v-for="item in group.items"
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
        </div>
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

.builder-tabnav {
  display: flex; flex-wrap: wrap; gap: 0;
  width: 100%;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 1px 2px color-mix(in srgb, var(--text) 6%, transparent);
  overflow-x: auto;
}
.builder-tabnav__item {
  display: inline-flex; align-items: center; gap: 8px;
  border: 0; background: transparent;
  color: #64748b;
  padding: 16px 22px;
  cursor: pointer; font: inherit; font-size: .95rem; font-weight: 600;
  border-bottom: 3px solid transparent;
  transition: color .15s ease, border-color .15s ease, background .15s ease;
}
.builder-tabnav__item:hover { color: #0f172a; background: #f8fafc; }
.builder-tabnav__item.is-active {
  color: var(--brand, #16a34a);
  border-bottom-color: var(--brand, #16a34a);
  background: color-mix(in srgb, var(--brand, #16a34a) 8%, #fff);
}
.builder-tabnav__item i { font-size: 1rem; }

.tree-panel, .info-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
  min-height: 520px;
}
.curriculum-panel { min-height: 0; }
.info-panel { padding-bottom: 16px; }
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

.lesson-dialog .form-grid { padding: 0; }
.meta-tabs {
  display: flex; gap: 4px; padding: 0 14px; border-bottom: 1px solid var(--border);
}
.meta-tabs button {
  border: 0; background: transparent; padding: 10px 14px; cursor: pointer; font-weight: 650;
  color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.meta-tabs button.on { color: var(--brand); border-bottom-color: var(--brand); }
.tab-lead { margin: 0; color: var(--text-muted); font-weight: 500; font-size: .9rem; }
.form-grid { display: grid; gap: 12px; padding: 14px; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.list-field { display: grid; gap: 8px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; }
.list-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.list-head > span { font-size: .8rem; font-weight: 700; color: var(--brand); }
.list-hint { margin: 0; color: var(--text-muted); font-size: .78rem; font-weight: 500; }
.list-row { display: flex; gap: 6px; align-items: flex-start; }
.list-row > :first-child { flex: 1; min-width: 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { font-size: .8rem; font-weight: 700; color: var(--brand); }
.check-row { display: flex; align-items: center; gap: 8px; min-height: 38px; }
.hint { color: var(--text-muted); font-size: .78rem; }
.question-bank-link { display: inline-flex; text-decoration: none; }
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
.type-groups { display: grid; gap: 16px; }
.type-group h4 {
  margin: 0 0 8px; font-size: .78rem; font-weight: 700;
  letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted);
}
.type-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
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
  .tree-panel { min-height: 0; }
  .tree-list { max-height: 360px; }
  .type-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .type-grid { grid-template-columns: 1fr; }
  .builder-tabnav__item { flex: 1; justify-content: center; padding: 12px 10px; }
}
</style>
