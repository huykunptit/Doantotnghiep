<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import RichTextEditor from '~/components/dashboard/RichTextEditor.vue'

definePageMeta({ layout: 'admin' })

const route = useRoute()
const courseId = route.params.id as string
const runtimeConfig = useRuntimeConfig()

const user = useAuthUserCookie(); const token = useAuthTokenCookie(); if (!user.value || !token.value) await navigateTo('/login', { replace: true })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

interface LessonItem { id: number; title: string; type: string; duration: number; is_preview: boolean }
interface SectionItem { id: number; title: string; position: number; lessons?: LessonItem[] }
interface CourseDetail { id: number; title: string }

const course = ref<CourseDetail | null>(null)
const sections = ref<SectionItem[]>([])
const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const sectionModalOpen = ref(false)
const sectionForm = reactive({ title: '', id: null as number | null })

const pickerModalOpen = ref(false)
const selectedSectionId = ref<number | null>(null)
const contentTypes = [
  { key: 'video', label: 'Bài học video', kind: 'resource', icon: 'play_circle' },
  { key: 'audio', label: 'Audio', kind: 'resource', icon: 'headphones' },
  { key: 'file', label: 'Tệp', kind: 'resource', icon: 'description' },
  { key: 'page', label: 'Trang', kind: 'resource', icon: 'article' },
  { key: 'scorm', label: 'Gói SCORM', kind: 'resource', icon: 'deployed_code' },
  { key: 'h5p', label: 'H5P', kind: 'resource', icon: 'extension' },
  { key: 'quiz', label: 'Đề thi', kind: 'activity', icon: 'quiz' },
  { key: 'assignment', label: 'Bài tập về nhà', kind: 'activity', icon: 'assignment' },
  { key: 'forum', label: 'Diễn đàn', kind: 'activity', icon: 'forum' },
  { key: 'survey', label: 'Khảo sát', kind: 'activity', icon: 'poll' },
  { key: 'zoom', label: 'Zoom', kind: 'activity', icon: 'video_camera_front' },
  { key: 'meet', label: 'Google Meet', kind: 'activity', icon: 'video_chat' },
]

const pickerTab = ref<'all' | 'activity' | 'resource'>('all')
const pickerSearch = ref('')
const filteredContentTypes = computed(() => {
  return contentTypes.filter((item) => {
    const matchesTab = pickerTab.value === 'all' || item.kind === pickerTab.value
    const matchesSearch = !pickerSearch.value.trim() || item.label.toLowerCase().includes(pickerSearch.value.trim().toLowerCase())
    return matchesTab && matchesSearch
  })
})

function typeHelperText(type: string) {
  return {
    video: 'Dùng cho bài giảng video học tập.',
    audio: 'Dùng cho podcast, bài nghe hoặc luyện phát âm.',
    file: 'Dùng để chia sẻ file PDF, DOCX, slide hoặc tài liệu tải về.',
    page: 'Dùng để viết nội dung trực tiếp giống một trang bài học.',
    quiz: 'Dùng để tạo bài kiểm tra / đề thi cho học viên.',
    assignment: 'Dùng cho bài tập nộp bài và giao bài về nhà.',
    forum: 'Dùng để mở chủ đề thảo luận giữa giảng viên và học viên.',
    survey: 'Dùng để khảo sát hoặc lấy phản hồi.',
    zoom: 'Dùng để tổ chức lớp học trực tuyến qua Zoom.',
    meet: 'Dùng để tổ chức lớp học trực tuyến qua Google Meet.',
    scorm: 'Dùng để nhúng gói học liệu SCORM.',
    h5p: 'Dùng để nhúng học liệu tương tác H5P.',
  }[type] || 'Cấu hình học liệu phù hợp với nhu cầu giảng dạy.'
}

function lessonDescriptionLabel(type: string) {
  return {
    page: 'Nội dung trang học',
    forum: 'Mô tả chủ đề thảo luận',
    survey: 'Mô tả khảo sát',
    assignment: 'Yêu cầu bài tập',
    quiz: 'Mô tả bài kiểm tra',
    zoom: 'Mô tả buổi học trực tuyến',
    meet: 'Mô tả buổi học trực tuyến',
  }[type] || 'Nội dung / mô tả'
}

function lessonDescriptionPlaceholder(type: string) {
  return {
    page: 'Nhập nội dung bài học dạng trang để học viên đọc trực tiếp...',
    forum: 'Giới thiệu chủ đề thảo luận, mục tiêu trao đổi, quy tắc tương tác...',
    survey: 'Mô tả mục tiêu khảo sát và hướng dẫn học viên trả lời...',
    assignment: 'Nêu đề bài, yêu cầu đầu ra, cách nộp bài và tiêu chí chấm...',
    quiz: 'Mô tả phạm vi kiến thức, thời lượng, hướng dẫn làm bài...',
    zoom: 'Mô tả lịch học, cách tham gia, lưu ý trước buổi học...',
    meet: 'Mô tả lịch học, cách tham gia, lưu ý trước buổi học...',
  }[type] || 'Nhập nội dung hoặc hướng dẫn cho học liệu này'
}

function lessonLinkPlaceholder(type: string) {
  return {
    video: 'https://video.example.com/lesson-1',
    audio: 'https://audio.example.com/podcast-1.mp3',
    file: 'https://files.example.com/tai-lieu.pdf',
    scorm: 'https://storage.example.com/scorm/package.zip',
    h5p: 'https://storage.example.com/h5p/content',
    zoom: 'https://zoom.us/j/123456789',
    meet: 'https://meet.google.com/xxx-yyyy-zzz',
  }[type] || 'https://...'
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
  return ['youtube', 'gdrive', 'onedrive'].includes(detectVideoProvider(url)) ? 'embed' : 'upload'
}




const lessonModalOpen = ref(false)
const lessonForm = reactive({
  title: '',
  section_id: '',
  type: 'video',
  description: '',
  video_url: '',
  duration: 0,
  is_preview: false,
  id: null as number | null,
})
const resourceFile = ref<File | null>(null)
const videoFile = ref<File | null>(null)
const videoSourceMode = ref<'embed' | 'upload'>('embed')
const videoUploading = ref(false)
const videoUploadProgress = ref(0)
const videoUploadError = ref('')
const scormFile = ref<File | null>(null)
const lessonSaving = ref(false)
const quizConfig = reactive({ title: '', description: '', time_limit: 15, pass_score: 70 })
const assignmentConfig = reactive({ instructions: '', available_from: '', submission_open_at: '', due_at: '', allowed_extensions: 'pdf,doc,docx,zip', max_file_size: 10240 })
const liveConfig = reactive({ provider: 'zoom', meeting_id: '', meeting_password: '', join_url: '', start_at: '', duration: 60 })
const scormConfig = reactive({ entry_url: '', title: '', identifier: '', version: '1.2' })

async function fetchCourseDetails() {
  try {
    course.value = await useApi<CourseDetail>(`/courses/${courseId}`, { headers: authHeaders() })
  } catch {
    errorMessage.value = 'Không thể tải thông tin khóa học.'
  }
}

async function fetchCurriculum() {
  loading.value = true
  try {
    const res = await useApi<{ data: SectionItem[] }>(`/courses/${courseId}/sections`, { headers: authHeaders() })
    sections.value = res.data
  } catch {
    errorMessage.value = 'Lỗi tải nội dung bài giảng.'
  } finally {
    loading.value = false
  }
}

function openSectionModal(section?: SectionItem) {
  if (section) {
    sectionForm.id = section.id
    sectionForm.title = section.title
  } else {
    sectionForm.id = null
    sectionForm.title = ''
  }
  sectionModalOpen.value = true
}

async function saveSection() {
  if (!sectionForm.title.trim()) return
  try {
    if (sectionForm.id) {
      await useApi(`/sections/${sectionForm.id}`, { method: 'PUT', headers: authHeaders(), body: { title: sectionForm.title } })
      successMessage.value = 'Đã cập nhật chương.'
    } else {
      await useApi(`/courses/${courseId}/sections`, { method: 'POST', headers: authHeaders(), body: { title: sectionForm.title } })
      successMessage.value = 'Đã thêm chương mới.'
    }
    sectionModalOpen.value = false
    fetchCurriculum()
  } catch {
    errorMessage.value = 'Lỗi lưu chương / phần học.'
  }
}

async function deleteSection(id: number) {
  if (!confirm('Xác nhận xóa chương này? Tất cả học liệu bên trong phải được xóa trước.')) return
  try {
    await useApi(`/sections/${id}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa chương.'
    fetchCurriculum()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Lỗi khi xóa chương.'
  }
}

function lessonTypeLabel(type: string) {
  return contentTypes.find(item => item.key === type)?.label || type
}

function lessonTypeIcon(type: string) {
  return contentTypes.find(item => item.key === type)?.icon || 'book'
}


function resourceUrlLabel(type: string) {
  return {
    video: 'Đường dẫn video',
    audio: 'Đường dẫn audio',
    file: 'Đường dẫn tệp',
    scorm: 'Đường dẫn gói SCORM',
    h5p: 'Đường dẫn nội dung H5P',
    zoom: 'Link Zoom',
    meet: 'Link Google Meet',
  }[type] || 'Đường dẫn tài nguyên'
}

function needsResourceUrl(type: string) {
  return ['video', 'audio', 'file', 'scorm', 'h5p', 'zoom', 'meet'].includes(type)
}

function openContentPicker(sectionId: number) {
  selectedSectionId.value = sectionId
  pickerModalOpen.value = true
}

function chooseContentType(type: string) {
  pickerModalOpen.value = false
  openLessonModal(selectedSectionId.value || 0, undefined, type)
}

function resetLessonConfigs() {
  lessonForm.description = ''
  lessonForm.video_url = ''
  lessonForm.duration = 0
  lessonForm.is_preview = false
  resourceFile.value = null
  videoFile.value = null
  videoSourceMode.value = 'embed'
  scormFile.value = null
  Object.assign(quizConfig, { title: '', description: '', time_limit: 15, pass_score: 70 })
  Object.assign(assignmentConfig, { instructions: '', available_from: '', submission_open_at: '', due_at: '', allowed_extensions: 'pdf,doc,docx,zip', max_file_size: 10240 })
  Object.assign(liveConfig, { provider: 'zoom', meeting_id: '', meeting_password: '', join_url: '', start_at: '', duration: 60 })
  Object.assign(scormConfig, { entry_url: '', title: '', identifier: '', version: '1.2' })
}

async function loadLessonConfigs(lessonId: number, type: string) {
  try {
    if (type === 'quiz') {
      const res = await useApi<{ quiz: { title?: string; description?: string; time_limit?: number | null; pass_score?: number | null } }>(`/courses/${courseId}/lessons/${lessonId}/quiz`, { headers: authHeaders() })
      Object.assign(quizConfig, { title: res.quiz?.title || '', description: res.quiz?.description || '', time_limit: res.quiz?.time_limit || 15, pass_score: res.quiz?.pass_score || 70 })
    } else if (type === 'assignment') {
      const res = await useApi<{ instructions?: string; available_from?: string | null; submission_open_at?: string | null; due_at?: string | null; allowed_extensions?: string | null; max_file_size?: number | null }>(`/courses/${courseId}/lessons/${lessonId}/assignment`, { headers: authHeaders() })
      Object.assign(assignmentConfig, {
        instructions: res.instructions || '',
        available_from: res.available_from ? String(res.available_from).slice(0, 16) : '',
        submission_open_at: res.submission_open_at ? String(res.submission_open_at).slice(0, 16) : '',
        due_at: res.due_at ? String(res.due_at).slice(0, 16) : '',
        allowed_extensions: res.allowed_extensions || 'pdf,doc,docx,zip',
        max_file_size: res.max_file_size || 10240,
      })
    } else if (['zoom', 'meet', 'virtual_class'].includes(type)) {
      const res = await useApi<{ provider?: string; meeting_id?: string; meeting_password?: string; join_url?: string; start_at?: string; duration?: number }>(`/courses/${courseId}/lessons/${lessonId}/virtual-class`, { headers: authHeaders() })
      Object.assign(liveConfig, { provider: res.provider === 'google_meet' ? 'meet' : (res.provider || 'zoom'), meeting_id: res.meeting_id || '', meeting_password: res.meeting_password || '', join_url: res.join_url || '', start_at: res.start_at ? String(res.start_at).slice(0, 16) : '', duration: res.duration || 60 })
    } else if (['scorm', 'h5p'].includes(type)) {
      const res = await useApi<{ entry_url?: string; title?: string; identifier?: string; version?: string }>(`/courses/${courseId}/lessons/${lessonId}/scorm-package`, { headers: authHeaders() })
      Object.assign(scormConfig, { entry_url: res.entry_url || '', title: res.title || '', identifier: res.identifier || '', version: res.version || '1.2' })
    }
  } catch {
    // ignore missing config on new/unstyled lessons
  }
}

async function uploadLessonVideo(lessonId: number) {
  const file = videoFile.value
  if (!file) return

  videoUploading.value = true
  videoUploadProgress.value = 0
  videoUploadError.value = ''

  // Hit Laravel backend directly to bypass the Nuxt /api proxy which buffers
  // multipart bodies in memory and rejects larger files with HTTP 413.
  const base = (runtimeConfig.public.apiBase as string).replace(/\/$/, '')
  const url = `${base}/courses/${courseId}/lessons/${lessonId}/upload-video`

  try {
    await new Promise<void>((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      xhr.open('POST', url, true)
      const headers = authHeaders()
      if (headers.Authorization) {
        xhr.setRequestHeader('Authorization', headers.Authorization)
      }
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
        // eslint-disable-next-line no-console
        console.error(`[upload-video HTTP ${xhr.status}]`, xhr.responseText)
        let body: any = null
        try { body = JSON.parse(xhr.responseText || '{}') } catch { /* not JSON */ }
        const errs = body?.errors ? Object.values(body.errors).flat().join(' / ') : ''
        const message = body?.message || ''
        if (xhr.status === 413) {
          reject(new Error(message || 'File quá lớn so với giới hạn server.'))
        } else if (xhr.status === 422) {
          reject(new Error(`Upload bị từ chối (422): ${errs || message || 'không rõ lý do'}`))
        } else {
          reject(new Error(message || xhr.statusText || `Upload thất bại (HTTP ${xhr.status}).`))
        }
      }

      xhr.onerror = () => reject(new Error('Lỗi mạng khi tải lên video.'))

      const formData = new FormData()
      formData.append('video', file)
      xhr.send(formData)
    })
  } catch (error: any) {
    videoUploadError.value = error?.message || 'Lỗi upload video.'
    throw error
  } finally {
    videoUploading.value = false
  }
}

async function onVideoFileSelected(file: File | null) {
  videoFile.value = file
  videoUploadError.value = ''
  videoUploadProgress.value = 0
  if (!file) return
  // Auto-start upload as soon as a file is dropped on an existing lesson so
  // the user can see progress immediately instead of waiting for "Lưu học liệu".
  if (lessonForm.id) {
    try {
      await uploadLessonVideo(lessonForm.id)
      // Clear the staged file so saveLesson doesn't re-upload it.
      videoFile.value = null
      successMessage.value = 'Đã tải video lên thành công.'
    } catch {
      /* error message is shown via videoUploadError */
    }
  }
}

async function openLessonModal(sectionId: number, lesson?: LessonItem, forcedType?: string) {
  lessonForm.section_id = String(sectionId)
  resetLessonConfigs()
  if (lesson) {
    lessonForm.id = lesson.id
    lessonForm.title = lesson.title
    lessonForm.type = lesson.type || 'video'
    lessonForm.duration = lesson.duration || 0
    lessonForm.is_preview = lesson.is_preview || false
    const lessonDetail = await useApi<{ description?: string | null; video_url?: string | null }>(`/courses/${courseId}/lessons/${lesson.id}`, { headers: authHeaders() }).catch(() => null)
    lessonForm.description = lessonDetail?.description || ''
    lessonForm.video_url = lessonDetail?.video_url || ''
    if (lessonForm.type === 'video') {
      videoSourceMode.value = inferVideoSourceMode(lessonForm.video_url)
    }
    await loadLessonConfigs(lesson.id, lessonForm.type)
  } else {
    lessonForm.id = null
    lessonForm.title = ''
    lessonForm.type = forcedType || 'video'
    if (lessonForm.type === 'video') {
      videoSourceMode.value = 'embed'
    }
  }
  lessonModalOpen.value = true
}

async function saveLesson() {
  if (!lessonForm.title.trim() || !lessonForm.section_id) return

  if (!token.value) {
    errorMessage.value = 'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại.'
    await navigateTo('/login', { replace: true })
    return
  }

  lessonSaving.value = true
  try {
    const payload = {
      title: lessonForm.title,
      section_id: Number(lessonForm.section_id),
      type: lessonForm.type,
      description: lessonForm.description || undefined,
      duration: lessonForm.duration,
      is_preview: lessonForm.is_preview,
      video_url: lessonForm.type === 'video' && videoSourceMode.value === 'upload' ? undefined : (lessonForm.video_url || undefined),
    }

    const lessonResponse = lessonForm.id
      ? await useApi<{ lesson: { id: number } }>(`/courses/${courseId}/lessons/${lessonForm.id}`, { method: 'PUT', headers: authHeaders(), body: payload })
      : await useApi<{ lesson: { id: number } }>(`/courses/${courseId}/lessons`, { method: 'POST', headers: authHeaders(), body: payload })

    const lessonId = lessonResponse.lesson.id

    if (lessonForm.type === 'video' && videoSourceMode.value === 'upload' && videoFile.value) {
      await uploadLessonVideo(lessonId)
    }

    if (['file', 'audio'].includes(lessonForm.type) && resourceFile.value) {
      const formData = new FormData()
      formData.append('file', resourceFile.value)
      const attachmentResponse = await useApi<{ attachment: { url: string } }, FormData>(`/courses/${courseId}/lessons/${lessonId}/attachments`, { method: 'POST', headers: authHeaders(), body: formData })
      await useApi(`/courses/${courseId}/lessons/${lessonId}`, { method: 'PUT', headers: authHeaders(), body: { video_url: attachmentResponse.attachment.url } })
    }

    if (lessonForm.type === 'quiz') {
      await useApi(`/courses/${courseId}/lessons/${lessonId}/quiz`, {
        method: 'POST', headers: authHeaders(), body: {
          title: quizConfig.title || lessonForm.title,
          description: quizConfig.description || lessonForm.description || null,
          time_limit: quizConfig.time_limit,
          pass_score: quizConfig.pass_score,
          question_ids: [],
        },
      })
    }

    if (lessonForm.type === 'assignment') {
      await useApi(`/courses/${courseId}/lessons/${lessonId}/assignment`, {
        method: 'POST', headers: authHeaders(), body: {
          instructions: assignmentConfig.instructions || lessonForm.description,
          max_file_size: assignmentConfig.max_file_size,
          allowed_extensions: assignmentConfig.allowed_extensions,
          available_from: assignmentConfig.available_from || null,
          submission_open_at: assignmentConfig.submission_open_at || null,
          due_at: assignmentConfig.due_at || null,
        },
      })
    }

    if (['zoom', 'meet'].includes(lessonForm.type)) {
      await useApi(`/courses/${courseId}/lessons/${lessonId}/virtual-class`, {
        method: 'POST', headers: authHeaders(), body: {
          provider: lessonForm.type === 'meet' ? 'google_meet' : 'zoom',
          meeting_id: liveConfig.meeting_id || null,
          meeting_password: liveConfig.meeting_password || null,
          join_url: liveConfig.join_url,
          start_url: null,
          start_at: liveConfig.start_at,
          duration: liveConfig.duration,
        },
      })
    }

    if (['scorm', 'h5p'].includes(lessonForm.type)) {
      const formData = new FormData()
      formData.append('type', lessonForm.type)
      if (lessonForm.type === 'h5p') {
        // Accept either a bare URL or a pasted iframe embed snippet — extract the src.
        const raw = scormConfig.entry_url.trim()
        const match = raw.match(/<iframe[^>]+src=["']([^"']+)["']/i)
        const src = match ? match[1] : raw
        if (src) formData.append('entry_url', src)
      }
      if (lessonForm.type === 'scorm' && scormFile.value) formData.append('scorm_file', scormFile.value)
      await useApi(`/courses/${courseId}/lessons/${lessonId}/scorm-package`, { method: 'POST', headers: authHeaders(), body: formData })
    }

    successMessage.value = lessonForm.id ? 'Đã cập nhật học liệu.' : 'Đã thêm học liệu mới.'
    lessonModalOpen.value = false
    fetchCurriculum()
  } catch (error: any) {
    // eslint-disable-next-line no-console
    console.error('[saveLesson]', { status: error?.status || error?.statusCode, url: error?.request, data: error?.data, raw: error })
    if ((error?.status || error?.statusCode) === 401) {
      errorMessage.value = 'Phiên đăng nhập đã hết. Vui lòng đăng xuất và đăng nhập lại.'
    } else {
      errorMessage.value = error?.data?.message || 'Lỗi lưu học liệu.'
    }
  } finally {
    lessonSaving.value = false
  }
}

async function deleteLesson(lessonId: number) {
  if (!confirm('Xác nhận xóa học liệu này?')) return
  try {
    await useApi(`/courses/${courseId}/lessons/${lessonId}`, { method: 'DELETE', headers: authHeaders() })
    successMessage.value = 'Đã xóa học liệu.'
    fetchCurriculum()
  } catch (error) {
    errorMessage.value = 'Lỗi khi xóa học liệu.'
  }
}

// ─── Quick lesson preview (admin can verify content without leaving the page)
const previewModalOpen = ref(false)
const previewLesson = ref<any>(null)
const previewLoading = ref(false)
const previewError = ref('')

async function openLessonPreview(lesson: { id: number; title?: string; type?: string }) {
  previewModalOpen.value = true
  previewLesson.value = { id: lesson.id, title: lesson.title, type: lesson.type }
  previewLoading.value = true
  previewError.value = ''
  try {
    const detail = await useApi<any>(`/courses/${courseId}/lessons/${lesson.id}`, { headers: authHeaders() })
    previewLesson.value = detail
  } catch (err: any) {
    previewError.value = err?.data?.message || 'Không tải được nội dung học liệu để xem trước.'
  } finally {
    previewLoading.value = false
  }
}

function closeLessonPreview() {
  previewModalOpen.value = false
  // Drop reference next tick so iframe/video unmounts cleanly.
  setTimeout(() => { previewLesson.value = null }, 200)
}

function openCoursePreview() {
  if (import.meta.client) window.open(`/courses/${courseId}`, '_blank', 'noopener')
}

function previewLessonTypeLabel(type?: string) {
  const map: Record<string, string> = {
    video: 'Video', audio: 'Audio', file: 'Tệp', page: 'Trang', forum: 'Diễn đàn', survey: 'Khảo sát',
    scorm: 'SCORM', h5p: 'H5P', virtual_class: 'Lớp trực tuyến', zoom: 'Zoom', meet: 'Google Meet',
    offline: 'Offline', assignment: 'Bài tập', quiz: 'Kiểm tra',
  }
  return map[type || ''] || 'Học liệu'
}

onMounted(() => {
  fetchCourseDetails()
  fetchCurriculum()
})
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Khóa học', course?.title || 'Đang tải...']" description="Sắp xếp và quản lý nội dung giảng dạy. Bạn có thể xây dựng cấu trúc theo các phần học và bài giảng." title="Xây dựng Nội dung">

    <div class="crud-toolbar" style="margin-bottom: 24px;">
      <button class="crud-primary-btn" type="button" @click="openSectionModal()">+ Thêm Chương mới</button>
      <button class="crud-secondary-btn" style="display:inline-flex;align-items:center;gap:6px;" type="button" @click="openCoursePreview">
        <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span>
        Xem trang khóa học
      </button>
      <button class="crud-secondary-btn" type="button" @click="navigateTo('/admin/manage-courses')">← Quay lại danh sách</button>
    </div>

    <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
    <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

    <div v-if="loading" style="padding: 40px; text-align: center; color: var(--muted);">Đang tải cấu trúc nội dung...</div>

    <div v-else-if="sections.length === 0" class="crud-empty dashboard-card">
      Chưa có nội dung nào được tạo. Hãy bắt đầu bằng cách bấm "Thêm Chương mới".
    </div>

    <div v-else class="curriculum-builder">
      <div v-for="(section, index) in sections" :key="section.id" class="section-card dashboard-card">
        <div class="section-header">
          <div class="section-title">
            <strong>Chương {{ index + 1 }}:</strong> <span>{{ section.title }}</span>
          </div>
          <div class="section-actions">
            <button class="action-btn is-edit" type="button" @click="openSectionModal(section)">Sửa chương</button>
            <button class="action-btn is-delete" type="button" @click="deleteSection(section.id)">Xóa chương</button>
            <button class="action-btn is-add" type="button" @click="openContentPicker(section.id)">
              <span class="material-symbols-outlined" style="font-size: 16px;">add</span>
              Thêm hoạt động / tài nguyên
            </button>
          </div>
        </div>

        <div class="lessons-list">
          <div v-if="!section.lessons || section.lessons.length === 0" class="no-lessons">
            Chưa có học liệu nào trong phần này.
          </div>
          <div v-for="(lesson, lIndex) in section.lessons" :key="lesson.id" class="lesson-item">
            <div class="lesson-info">
              <span class="lesson-icon material-symbols-outlined" style="font-size: 16px;">{{ lessonTypeIcon(lesson.type) }}</span>
              <div>
                <span class="lesson-name">{{ lIndex + 1 }}. {{ lesson.title }}</span>
                <div class="lesson-meta-row">
                  <span class="crud-badge role-admin">{{ lessonTypeLabel(lesson.type) }}</span>
                  <span v-if="lesson.is_preview" class="crud-badge role-instructor">Học thử</span>
                  <span class="lesson-duration" v-if="lesson.duration">{{ lesson.duration }} phút</span>
                </div>
              </div>
            </div>
            <div class="lesson-actions">
              <button class="action-btn is-preview" type="button" @click="openLessonPreview(lesson)" title="Xem thử nhanh">
                <span class="material-symbols-outlined">visibility</span>
                Xem thử
              </button>
              <button class="action-btn is-edit" type="button" @click="openLessonModal(section.id, lesson)">Sửa</button>
              <button class="action-btn is-delete" type="button" @click="deleteLesson(lesson.id)">Xóa</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Section Modal -->
    <Teleport to="body">
      <div v-if="sectionModalOpen" class="crud-modal-backdrop" @click.self="sectionModalOpen = false">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Chương / Phần</p>
              <h3>{{ sectionForm.id ? 'Sửa tên chương' : 'Thêm chương mới' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="sectionModalOpen = false">✕</button>
          </div>
          <div class="crud-form-grid">
            <label class="crud-field crud-field-full">
              <span>Tên chương</span>
              <input v-model="sectionForm.title" type="text" placeholder="Ví dụ: Giới thiệu khóa học">
            </label>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="sectionModalOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" @click="saveSection">Lưu chương</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Content Picker Modal -->
    <Teleport to="body">
      <div v-if="pickerModalOpen" class="crud-modal-backdrop" @click.self="pickerModalOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Thêm học liệu</p>
              <h3>Chọn hoạt động hoặc tài nguyên</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="pickerModalOpen = false">✕</button>
          </div>
          <div class="picker-body">
            <div class="picker-toolbar">
              <input v-model="pickerSearch" class="crud-search" type="text" placeholder="Tìm kiếm hoạt động hoặc tài nguyên...">
              <div class="picker-tabs">
                <button type="button" :class="['picker-tab', { 'is-active': pickerTab === 'all' }]" @click="pickerTab = 'all'">Tất cả</button>
                <button type="button" :class="['picker-tab', { 'is-active': pickerTab === 'activity' }]" @click="pickerTab = 'activity'">Hoạt động</button>
                <button type="button" :class="['picker-tab', { 'is-active': pickerTab === 'resource' }]" @click="pickerTab = 'resource'">Tài nguyên</button>
              </div>
            </div>
            <ul class="content-picker-list">
              <li v-for="item in filteredContentTypes" :key="item.key" class="content-picker-item" @click="chooseContentType(item.key)">
                <div class="item-icon-wrapper">
                  <span class="material-symbols-outlined item-icon">{{ item.icon }}</span>
                </div>
                <div class="item-details">
                  <strong class="item-title">{{ item.label }}</strong>
                  <p class="item-desc">{{ typeHelperText(item.key) }}</p>
                </div>
                <div class="item-badge-container">
                  <span :class="['item-badge', item.kind]">{{ item.kind === 'activity' ? 'Hoạt động' : 'Tài nguyên' }}</span>
                </div>
              </li>
            </ul>
            <div v-if="filteredContentTypes.length === 0" class="crud-empty">Không tìm thấy loại học liệu phù hợp.</div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Lesson Modal -->
    <Teleport to="body">
      <div v-if="lessonModalOpen" class="crud-modal-backdrop" @click.self="lessonModalOpen = false">
        <div class="crud-modal crud-modal-wide">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ lessonTypeLabel(lessonForm.type) }}</p>
              <h3>{{ lessonForm.id ? 'Cập nhật học liệu' : 'Tạo học liệu' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="lessonModalOpen = false">✕</button>
          </div>
          <div class="crud-form-grid">
            <label class="crud-field crud-field-full">
              <span>Tên học liệu</span>
              <input v-model="lessonForm.title" type="text" placeholder="Ví dụ: Bài 1 - Giới thiệu tổng quan">
            </label>
            <div v-if="lessonForm.type === 'video'" class="crud-field crud-field-full lesson-config-grid">
              <div class="video-mode-switch">
                <button type="button" :class="['picker-tab', { 'is-active': videoSourceMode === 'embed' }]" @click="videoSourceMode = 'embed'">Video iframe / link</button>
                <button type="button" :class="['picker-tab', { 'is-active': videoSourceMode === 'upload' }]" @click="videoSourceMode = 'upload'">Tự tải lên</button>
              </div>
              <label v-if="videoSourceMode === 'embed'" class="crud-field crud-field-full">
                <span>Link video (YouTube / Google Drive / OneDrive)</span>
                <input v-model="lessonForm.video_url" type="url" placeholder="https://youtube.com/... hoặc https://drive.google.com/...">
              </label>
              <div v-else class="crud-field crud-field-full">
                <span>Upload file video</span>
                <label class="upload-dropzone">
                  <input class="upload-dropzone-input" type="file" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" @change="onVideoFileSelected(($event.target as HTMLInputElement)?.files?.[0] || null)">
                  <span class="upload-dropzone-icon material-symbols-outlined">movie</span>
                  <strong>Tải video bài học</strong>
                  <span>{{ videoFile?.name || 'Chọn file MP4, WebM, MOV hoặc AVI để tải lên MinIO.' }}</span>
                </label>
                <div v-if="videoUploading" class="mt-4">
                  <div class="h-2 w-full bg-surface-high rounded-full overflow-hidden">
                    <div class="h-full progress-gradient transition-all duration-200" :style="{ width: `${videoUploadProgress}%` }"></div>
                  </div>
                  <p class="text-xs text-on-surface-variant mt-2">Đang tải lên video: {{ videoUploadProgress }}%</p>
                </div>
                <p v-if="videoUploadError" class="text-sm text-error mt-3">{{ videoUploadError }}</p>
                <p class="lesson-upload-help">Video sẽ được tải lên MinIO và học viên sẽ xem qua URL ký tạm thời.</p>
              </div>
            </div>

            <label class="crud-field">
              <span>Loại học liệu</span>
              <input :value="lessonTypeLabel(lessonForm.type)" type="text" disabled>
            </label>
            <div class="crud-field crud-field-full lesson-helper-box">
              <span>Gợi ý cấu hình</span>
              <p>{{ typeHelperText(lessonForm.type) }}</p>
              <div v-if="lessonForm.type === 'quiz'" class="crud-inline-actions crud-modal-foot">
                <button class="crud-secondary-btn" type="button" @click="navigateTo(`/admin/question-bank`)">Đi tới Ngân hàng câu hỏi</button>
                <button class="crud-secondary-btn" type="button" @click="navigateTo(`/admin/quiz`)">Đi tới Đề thi</button>
              </div>
              <div v-else-if="lessonForm.type === 'assignment'" class="lesson-helper-tags">
                <span class="crud-badge role-student">Deadline</span>
                <span class="crud-badge role-admin">Nộp bài</span>
                <span class="crud-badge role-instructor">Chấm điểm</span>
              </div>
              <div v-else-if="lessonForm.type === 'forum'" class="lesson-helper-tags">
                <span class="crud-badge role-student">Chủ đề</span>
                <span class="crud-badge role-admin">Trao đổi</span>
              </div>
              <div v-else-if="['zoom', 'meet'].includes(lessonForm.type)" class="lesson-helper-tags">
                <span class="crud-badge role-instructor">Lớp trực tuyến</span>
                <span class="crud-badge role-admin">Lịch học</span>
              </div>
            </div>
            <label v-if="needsResourceUrl(lessonForm.type) && lessonForm.type !== 'video'" class="crud-field">
              <span>{{ resourceUrlLabel(lessonForm.type) }}</span>
              <input v-model="lessonForm.video_url" type="url" :placeholder="lessonLinkPlaceholder(lessonForm.type)">
            </label>
            <div v-if="['file', 'audio'].includes(lessonForm.type)" class="crud-field">
              <span>Tải file thật</span>
              <label class="upload-dropzone upload-dropzone-compact">
                <input class="upload-dropzone-input" type="file" @change="resourceFile = ($event.target as HTMLInputElement)?.files?.[0] || null">
                <span class="upload-dropzone-icon material-symbols-outlined">upload_file</span>
                <strong>{{ lessonForm.type === 'audio' ? 'Tải file audio' : 'Tải tài liệu đính kèm' }}</strong>
                <span>{{ resourceFile?.name || 'Chọn file để đính kèm cho học viên.' }}</span>
              </label>
            </div>
            <label v-if="lessonForm.type !== 'assignment'" class="crud-field">
              <span>Thời lượng (phút)</span>
              <input v-model="lessonForm.duration" type="number" min="0">
            </label>
            <div v-if="lessonForm.type === 'quiz'" class="crud-field crud-field-full lesson-config-grid">
              <label class="crud-field"><span>Tiêu đề quiz</span><input v-model="quizConfig.title" type="text" placeholder="Quiz chương 1"></label>
              <label class="crud-field"><span>Thời gian làm bài (phút)</span><input v-model="quizConfig.time_limit" type="number" min="0"></label>
              <label class="crud-field"><span>Điểm đạt</span><input v-model="quizConfig.pass_score" type="number" min="0" max="100"></label>
              <div class="crud-field crud-field-full"><span>Mô tả quiz</span><RichTextEditor v-model="quizConfig.description" placeholder="Mô tả quiz và hướng dẫn làm bài" enable-images upload-folder="courses" /></div>
            </div>
            <div v-if="lessonForm.type === 'assignment'" class="crud-field crud-field-full lesson-config-grid">
              <div class="crud-field crud-field-full assignment-timeline">
                <span class="assignment-timeline-title">Mốc thời gian</span>
                <div class="assignment-timeline-grid">
                  <label class="assignment-date-card assignment-date-card--open">
                    <span class="assignment-date-card-head">
                      <span class="material-symbols-outlined">event_available</span>
                      Ngày nhận bài
                    </span>
                    <input v-model="assignmentConfig.available_from" type="datetime-local">
                    <span class="assignment-date-card-hint">Lúc bài tập hiển thị cho học viên</span>
                  </label>

                  <span class="assignment-timeline-bar"></span>

                  <label class="assignment-date-card assignment-date-card--submit">
                    <span class="assignment-date-card-head">
                      <span class="material-symbols-outlined">task_alt</span>
                      Ngày bắt đầu nộp
                    </span>
                    <input v-model="assignmentConfig.submission_open_at" type="datetime-local">
                    <span class="assignment-date-card-hint">Học viên được phép submit từ lúc này</span>
                  </label>

                  <span class="assignment-timeline-bar"></span>

                  <label class="assignment-date-card assignment-date-card--close">
                    <span class="assignment-date-card-head">
                      <span class="material-symbols-outlined">lock_clock</span>
                      Ngày đóng
                    </span>
                    <input v-model="assignmentConfig.due_at" type="datetime-local">
                    <span class="assignment-date-card-hint">Sau lúc này không nộp được nữa</span>
                  </label>
                </div>
              </div>

              <label class="crud-field"><span>Dung lượng tối đa (KB)</span><input v-model="assignmentConfig.max_file_size" type="number" min="1"></label>
              <label class="crud-field crud-field-full"><span>Định dạng cho phép</span><input v-model="assignmentConfig.allowed_extensions" type="text" placeholder="pdf,doc,docx,zip"></label>
              <div class="crud-field crud-field-full"><span>Yêu cầu bài tập</span><RichTextEditor v-model="assignmentConfig.instructions" placeholder="Mô tả yêu cầu, đầu ra, cách nộp bài" enable-images upload-folder="courses" /></div>
            </div>
            <div v-if="['zoom', 'meet'].includes(lessonForm.type)" class="crud-field crud-field-full lesson-config-grid">
              <label class="crud-field"><span>Link tham gia</span><input v-model="liveConfig.join_url" type="url" :placeholder="lessonLinkPlaceholder(lessonForm.type)"></label>
              <label class="crud-field"><span>Bắt đầu lúc</span><input v-model="liveConfig.start_at" type="datetime-local"></label>
              <label class="crud-field"><span>Thời lượng buổi học (phút)</span><input v-model="liveConfig.duration" type="number" min="1"></label>
              <label class="crud-field"><span>Mã phòng</span><input v-model="liveConfig.meeting_id" type="text" placeholder="Meeting ID"></label>
              <label class="crud-field"><span>Mật khẩu</span><input v-model="liveConfig.meeting_password" type="text" placeholder="Mật khẩu phòng"></label>
            </div>
            <div v-if="lessonForm.type === 'h5p'" class="crud-field crud-field-full lesson-config-grid">
              <label class="crud-field crud-field-full">
                <span>Link nhúng H5P</span>
                <textarea v-model="scormConfig.entry_url" class="crud-textarea" rows="2" placeholder="https://h5p.org/h5p/embed/612 — hoặc dán nguyên đoạn <iframe src=&quot;...&quot;>...</iframe>"></textarea>
                <small class="crud-field-hint">Hỗ trợ dán URL trần hoặc nguyên đoạn embed code. Hệ thống tự bóc <code>src</code> và bật h5p-resizer.</small>
              </label>
            </div>
            <div v-if="lessonForm.type === 'scorm'" class="crud-field crud-field-full lesson-config-grid">
              <div class="crud-field crud-field-full">
                <span>Upload gói SCORM (.zip)</span>
                <label class="upload-dropzone upload-dropzone-compact">
                  <input class="upload-dropzone-input" type="file" accept=".zip,application/zip" @change="scormFile = ($event.target as HTMLInputElement)?.files?.[0] || null">
                  <span class="upload-dropzone-icon material-symbols-outlined">deployed_code</span>
                  <strong>Tải gói SCORM</strong>
                  <span>{{ scormFile?.name || 'Chọn file ZIP. Phiên bản (1.2 / 2004) sẽ tự nhận diện từ imsmanifest.xml.' }}</span>
                </label>
              </div>
            </div>
            <div v-if="lessonForm.type !== 'assignment'" class="crud-field crud-field-full">
              <span>{{ lessonDescriptionLabel(lessonForm.type) }}</span>
              <RichTextEditor v-model="lessonForm.description" :placeholder="lessonDescriptionPlaceholder(lessonForm.type)" enable-images upload-folder="courses" />
            </div>
            <label class="crud-field checkbox-field" style="display: flex; align-items: center; gap: 8px;">
              <input v-model="lessonForm.is_preview" type="checkbox">
              <span>Cho phép Học thử (Miễn phí)</span>
            </label>
          </div>
          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="lessonModalOpen = false">Hủy</button>
            <button class="crud-primary-btn" type="button" @click="saveLesson">Lưu học liệu</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Quick lesson preview modal -->
    <Teleport to="body">
      <div v-if="previewModalOpen" class="crud-modal-backdrop" @click.self="closeLessonPreview">
        <div class="crud-modal preview-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">{{ previewLessonTypeLabel(previewLesson?.type) }}</p>
              <h3>{{ previewLesson?.title || 'Xem thử học liệu' }}</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="closeLessonPreview">✕</button>
          </div>

          <div class="preview-modal-body">
            <div v-if="previewLoading" class="preview-state">
              <span class="material-symbols-outlined preview-state-spin">progress_activity</span>
              <p>Đang tải nội dung...</p>
            </div>
            <div v-else-if="previewError" class="preview-state preview-state--error">
              <span class="material-symbols-outlined">error</span>
              <p>{{ previewError }}</p>
            </div>
            <div v-else-if="previewLesson" class="preview-stage" :data-type="previewLesson.type">
              <!-- Video -->
              <template v-if="previewLesson.type === 'video'">
                <VideoPlayer
                  v-if="previewLesson.video_url"
                  :key="`preview-video-${previewLesson.id}`"
                  :course-id="Number(courseId)"
                  :lesson-id="previewLesson.id"
                  class="preview-fill"
                />
                <div v-else class="preview-state">
                  <span class="material-symbols-outlined">play_circle</span>
                  <p>Bài học này chưa có video.</p>
                </div>
              </template>

              <!-- Audio -->
              <template v-else-if="previewLesson.type === 'audio'">
                <div class="preview-resource">
                  <span class="material-symbols-outlined preview-resource-icon">podcasts</span>
                  <h4>{{ previewLesson.title }}</h4>
                  <audio v-if="previewLesson.video_url" :src="previewLesson.video_url" controls class="preview-audio" />
                  <p v-else>Chưa có file audio.</p>
                </div>
              </template>

              <!-- File / Page -->
              <template v-else-if="['file', 'page'].includes(previewLesson.type)">
                <div class="preview-resource">
                  <span class="material-symbols-outlined preview-resource-icon">{{ previewLesson.type === 'page' ? 'article' : 'draft' }}</span>
                  <h4>{{ previewLesson.title }}</h4>
                  <a v-if="previewLesson.video_url" :href="previewLesson.video_url" target="_blank" class="preview-link">
                    <span class="material-symbols-outlined">open_in_new</span>
                    Mở tài liệu
                  </a>
                  <div v-if="previewLesson.description" class="preview-rich" v-html="previewLesson.description" />
                </div>
              </template>

              <!-- SCORM -->
              <template v-else-if="previewLesson.type === 'scorm'">
                <ScormPlayer
                  v-if="previewLesson.scorm_package"
                  :course-id="Number(courseId)"
                  :lesson-id="previewLesson.id"
                  :package-data="previewLesson.scorm_package"
                  class="preview-fill"
                />
                <div v-else class="preview-state">
                  <span class="material-symbols-outlined">subscriptions</span>
                  <p>Chưa upload gói SCORM.</p>
                </div>
              </template>

              <!-- H5P -->
              <template v-else-if="previewLesson.type === 'h5p'">
                <H5PEmbed :src="previewLesson.scorm_package?.entry_url" class="preview-fill" />
              </template>

              <!-- Quiz -->
              <template v-else-if="previewLesson.type === 'quiz'">
                <StudentQuiz :course-id="Number(courseId)" :lesson-id="previewLesson.id" class="preview-scroll" />
              </template>

              <!-- Assignment -->
              <template v-else-if="previewLesson.type === 'assignment'">
                <AssignmentView
                  v-if="previewLesson.assignment"
                  :data="previewLesson.assignment"
                  :course-id="Number(courseId)"
                  :lesson-id="previewLesson.id"
                  class="preview-scroll"
                />
                <div v-else class="preview-state">
                  <span class="material-symbols-outlined">assignment</span>
                  <p>Chưa cấu hình bài tập.</p>
                </div>
              </template>

              <!-- Virtual class -->
              <template v-else-if="['virtual_class', 'zoom', 'meet'].includes(previewLesson.type)">
                <div class="preview-resource">
                  <span class="material-symbols-outlined preview-resource-icon">video_camera_front</span>
                  <h4>{{ previewLesson.title }}</h4>
                  <p v-if="previewLesson.virtual_class">
                    <strong>Mã phòng:</strong> {{ previewLesson.virtual_class.meeting_id || '—' }}<br>
                    <strong>Bắt đầu:</strong> {{ previewLesson.virtual_class.start_at || '—' }}
                  </p>
                  <a v-if="previewLesson.virtual_class?.join_url" :href="previewLesson.virtual_class.join_url" target="_blank" class="preview-link">
                    <span class="material-symbols-outlined">open_in_new</span>
                    Mở phòng học trực tuyến
                  </a>
                </div>
              </template>

              <!-- Forum / Survey / fallback -->
              <template v-else>
                <div class="preview-resource">
                  <span class="material-symbols-outlined preview-resource-icon">{{ previewLesson.type === 'forum' ? 'forum' : (previewLesson.type === 'survey' ? 'bar_chart' : 'description') }}</span>
                  <h4>{{ previewLesson.title }}</h4>
                  <div v-if="previewLesson.description" class="preview-rich" v-html="previewLesson.description" />
                  <p v-else>Bài học loại "{{ previewLessonTypeLabel(previewLesson.type) }}" — không có player riêng để xem thử.</p>
                </div>
              </template>
            </div>
          </div>

          <div class="crud-modal-foot">
            <button class="crud-secondary-btn" type="button" @click="closeLessonPreview">Đóng</button>
            <button class="crud-primary-btn" type="button" @click="previewLesson?.id && navigateTo(`/learn/${courseId}/${previewLesson.id}`)">
              Mở trong trang học
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.curriculum-builder {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-card {
  padding: 0;
  border-radius: 12px;
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: var(--bg-alt);
  border-bottom: 1px solid var(--border);
}

.section-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.action-btn.is-add {
  color: var(--green-deep);
  background: rgba(var(--green-rgb), 0.08);
  border-color: rgba(var(--green-rgb), 0.18);
}

.action-btn.is-add:hover {
  background: rgba(var(--green-rgb), 0.15);
  border-color: rgba(var(--green-rgb), 0.3);
}

.section-title {
  font-size: 1rem;
}
.section-title strong {
  margin-right: 6px;
  color: var(--text);
}
.section-title span {
  font-weight: 500;
  color: var(--text);
}

.lessons-list {
  padding: 12px 20px;
}

.no-lessons {
  padding: 20px;
  text-align: center;
  color: var(--muted);
  font-size: 0.95rem;
  background: rgba(17,17,17,0.02);
  border-radius: 8px;
  border: 1px dashed rgba(17,17,17,0.1);
}

.lesson-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
  margin-bottom: 8px;
  background: #fff;
  border: 1px solid rgba(17,17,17,0.08);
  border-radius: 10px;
  transition: all 0.2s;
}

.lesson-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
  flex-shrink: 0;
}

.lesson-actions .action-btn {
  white-space: nowrap;
}

.lesson-meta-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  flex-wrap: wrap;
}

.picker-toolbar {
  display: grid;
  gap: 14px;
  margin-bottom: 20px;
}

.picker-tabs {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.picker-tab {
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
}

.picker-tab.is-active {
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep);
  border-color: rgba(var(--green-rgb), 0.2);
}

.picker-body {
  padding: 24px 28px 28px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.content-picker-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  list-style: none;
  padding: 0;
  margin: 0;
}

@media (max-width: 640px) {
  .content-picker-list {
    grid-template-columns: 1fr;
  }
}

.content-picker-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.content-picker-item:hover {
  border-color: rgba(var(--green-rgb), 0.3);
  background: rgba(var(--green-rgb), 0.02);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
  transform: translateY(-1px);
}

.item-icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep);
  flex-shrink: 0;
}

.item-icon {
  font-size: 24px;
}

.item-details {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.item-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text);
}

.item-desc {
  font-size: 0.8rem;
  color: var(--muted);
  margin: 0;
  line-height: 1.3;
}

.item-badge-container {
  flex-shrink: 0;
}

.item-badge {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
}

.item-badge.resource {
  background: rgba(55, 138, 221, 0.08);
  color: #1a5fa8;
}

.item-badge.activity {
  background: rgba(16, 185, 129, 0.08);
  color: var(--green-deep);
}

.lesson-helper-box {
  padding: 16px;
  border: 1px dashed rgba(17, 17, 17, 0.12);
  border-radius: 18px;
  background: rgba(17, 17, 17, 0.02);
}

.lesson-helper-box p {
  margin: 6px 0 0;
  color: var(--muted);
}

.lesson-helper-tags {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.lesson-upload-help {
  margin-top: 10px;
  color: var(--muted);
  font-size: 0.92rem;
}

.upload-dropzone {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 10px;
  padding: 28px 20px;
  border: 2px dashed rgba(16, 185, 129, 0.85);
  border-radius: 24px;
  background: rgba(236, 253, 245, 0.75);
  text-align: center;
  cursor: pointer;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}

.upload-dropzone:hover {
  transform: translateY(-1px);
  border-color: rgba(5, 150, 105, 0.95);
  box-shadow: 0 20px 40px -28px rgba(16, 185, 129, 0.45);
}

.upload-dropzone-compact {
  justify-items: start;
  text-align: left;
  padding: 20px 18px;
  border-radius: 20px;
}

.upload-dropzone-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.upload-dropzone-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  font-size: 1.6rem;
}

.upload-dropzone strong {
  font-size: 1.02rem;
  color: var(--text);
}

.upload-dropzone span:last-child {
  color: var(--muted);
  font-size: 0.95rem;
  line-height: 1.5;
}

.video-mode-switch {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}


.lesson-item:last-child {
  margin-bottom: 0;
}

.lesson-item:hover {
  border-color: rgba(17,17,17,0.15);
  box-shadow: 0 4px 12px rgba(0,0,0,0.03);
  transform: translateY(-1px);
}

.lesson-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.lesson-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: rgba(var(--green-rgb), 0.08);
  color: var(--green-deep);
  border-radius: 8px;
}

.lesson-icon svg {
  width: 16px;
  height: 16px;
}

.lesson-name {
  font-weight: 500;
  color: var(--text);
  font-size: 0.95rem;
}

.lesson-duration {
  font-size: 0.85rem;
  color: var(--muted);
  background: rgba(17,17,17,0.04);
  padding: 4px 8px;
  border-radius: 6px;
  margin-left: auto;
}

@media (max-width: 1080px) {
  .content-picker-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .section-header,
  .lesson-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .content-picker-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .content-picker-grid {
    grid-template-columns: 1fr;
  }
}

/* ───── Field hint (small explanatory caption) ───── */
.crud-field-hint {
  display: block;
  margin-top: 6px;
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.5;
}
.crud-field-hint code {
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(15, 23, 42, 0.06);
  font-size: 0.92em;
  font-family: ui-monospace, SFMono-Regular, monospace;
}

/* ───── Assignment milestone timeline ───── */
.assignment-timeline {
  padding: 18px;
  border-radius: 18px;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, 0.1);
}

.assignment-timeline-title {
  display: block;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #64748b;
  margin-bottom: 14px;
}

.assignment-timeline-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto 1fr;
  gap: 12px;
  align-items: stretch;
}

.assignment-timeline-bar {
  align-self: center;
  height: 2px;
  width: 28px;
  background: rgba(15, 23, 42, 0.15);
  border-radius: 2px;
}

.assignment-date-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 14px 12px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
  cursor: pointer;
}

.assignment-date-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}

.assignment-date-card::before {
  content: "";
  position: absolute;
  top: 0;
  left: 14px;
  right: 14px;
  height: 3px;
  border-radius: 0 0 3px 3px;
  background: currentColor;
  opacity: 0.85;
}

.assignment-date-card--open   { color: #10b981; }
.assignment-date-card--submit { color: #10b981; }
.assignment-date-card--close  { color: #dc2626; }

.assignment-date-card-head {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #1f2937;
}

.assignment-date-card-head .material-symbols-outlined {
  font-size: 16px;
  color: currentColor;
}

.assignment-date-card input[type="datetime-local"] {
  appearance: none;
  -webkit-appearance: none;
  width: 100%;
  min-height: 40px;
  padding: 0 12px;
  border: 1px solid rgba(15, 23, 42, 0.15);
  border-radius: 10px;
  background: #ffffff;
  font: inherit;
  font-size: 0.88rem;
  color: #0f172a;
  outline: none;
  transition: border-color 0.15s, background 0.15s;
}

.assignment-date-card input[type="datetime-local"]:hover {
  background: #fff;
}

.assignment-date-card input[type="datetime-local"]:focus {
  border-color: currentColor;
  background: #fff;
  box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 18%, transparent);
}

.assignment-date-card input[type="datetime-local"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0.6;
  filter: invert(20%);
  padding: 4px;
  border-radius: 6px;
  transition: opacity 0.15s, background 0.15s;
}

.assignment-date-card input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
  background: rgba(15, 23, 42, 0.06);
}

.assignment-date-card-hint {
  font-size: 0.7rem;
  color: #94a3b8;
  line-height: 1.4;
}

@media (max-width: 720px) {
  .assignment-timeline-grid {
    grid-template-columns: 1fr;
  }
  .assignment-timeline-bar {
    height: 16px;
    width: 2px;
    justify-self: center;
    background: rgba(15, 23, 42, 0.15);
  }
}

/* ───── Preview button + modal ───── */
.action-btn.is-preview {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #1d4ed8;
  background: rgba(var(--green-rgb), 0.1);
  font-weight: 600;
}
.action-btn.is-preview:hover { background: rgba(var(--green-rgb), 0.18); }
.action-btn.is-preview .material-symbols-outlined { font-size: 16px; }

.preview-modal {
  width: min(100%, 1100px) !important;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
}

.preview-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 0;
  background: #0f172a;
  min-height: 320px;
  max-height: calc(92vh - 160px);
  display: flex;
}

.preview-stage {
  width: 100%;
  display: flex;
  flex-direction: column;
}
.preview-stage[data-type="video"],
.preview-stage[data-type="scorm"],
.preview-stage[data-type="h5p"] {
  aspect-ratio: 16 / 9;
  max-height: calc(92vh - 160px);
}

.preview-fill {
  width: 100%;
  height: 100%;
  flex: 1;
}

.preview-scroll {
  background: #f8fafc;
  color: #0f172a;
  overflow-y: auto;
  padding: 0;
  width: 100%;
}

.preview-resource {
  background: #fff;
  color: #0f172a;
  padding: 32px;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
}
.preview-resource h4 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
}
.preview-resource p {
  margin: 0;
  color: #475569;
}
.preview-resource-icon {
  font-size: 56px;
  color: #059669;
  background: #ecfdf5;
  border-radius: 16px;
  padding: 14px;
}
.preview-audio {
  width: min(100%, 540px);
}
.preview-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: 999px;
  background: #10b981;
  color: #fff;
  text-decoration: none;
  font-weight: 700;
  font-size: 0.85rem;
}
.preview-link:hover { filter: brightness(1.05); }
.preview-link .material-symbols-outlined { font-size: 16px; }

.preview-rich {
  text-align: left;
  width: 100%;
  max-width: 760px;
  line-height: 1.7;
  color: #334155;
  margin-top: 8px;
}
.preview-rich :deep(p) { margin: 0 0 8px; }

.preview-state {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 60px 24px;
  color: #cbd5e1;
  text-align: center;
}
.preview-state .material-symbols-outlined { font-size: 44px; color: #059669; }
.preview-state-spin { animation: preview-spin 1.2s linear infinite; }
.preview-state--error .material-symbols-outlined { color: #ef4444; }

@keyframes preview-spin {
  to { transform: rotate(360deg); }
}

/* ====== DARK MODE OVERRIDES ====== */
[data-theme="dark"] .course-builder-shell { background: var(--bg); }
[data-theme="dark"] .cb-sidebar, [data-theme="dark"] .cb-main, [data-theme="dark"] .course-header, [data-theme="dark"] .course-tab-panel, [data-theme="dark"] .course-info-panel, [data-theme="dark"] .preview-resource, [data-theme="dark"] .preview-scroll { background: var(--surface-strong); color: var(--text); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .cb-sidebar-link:hover, [data-theme="dark"] .cb-sidebar-link.is-active { background: rgba(255, 255, 255, 0.05); color: #10b981; }
[data-theme="dark"] .cb-form-field input, [data-theme="dark"] .cb-form-field textarea, [data-theme="dark"] .cb-form-field select { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.15); color: var(--text); }
[data-theme="dark"] .preview-resource h4, [data-theme="dark"] .preview-resource p, [data-theme="dark"] .preview-rich { color: var(--text); }
[data-theme="dark"] .curriculum-section, [data-theme="dark"] .curriculum-lesson { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); color: var(--text); }
[data-theme="dark"] .action-btn { background: rgba(255, 255, 255, 0.06); color: var(--text); }
[data-theme="dark"] .action-btn.is-add {
  color: #6ee7b7;
  background: rgba(16, 185, 129, 0.15);
  border-color: rgba(16, 185, 129, 0.25);
}
[data-theme="dark"] .action-btn.is-add:hover {
  background: rgba(16, 185, 129, 0.25);
  border-color: rgba(16, 185, 129, 0.4);
}
[data-theme="dark"] .course-settings-panel { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); color: var(--text); }
[data-theme="dark"] .cb-topbar { background: rgba(15, 34, 25, 0.95); border-color: rgba(255, 255, 255, 0.08); }

/* Dark mode for section cards */
[data-theme="dark"] .section-card { background: rgba(255, 255, 255, 0.05); }
[data-theme="dark"] .section-header { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .section-title, [data-theme="dark"] .section-title strong, [data-theme="dark"] .section-title span { color: var(--text); }

/* Dark mode for lesson items */
[data-theme="dark"] .lesson-item { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.08); color: var(--text); }
[data-theme="dark"] .lesson-item:hover { border-color: rgba(255, 255, 255, 0.15); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
[data-theme="dark"] .lesson-name, [data-theme="dark"] .lesson-icon { color: var(--text); background: rgba(16, 185, 129, 0.12); }

/* Dark mode for modals */
[data-theme="dark"] .crud-modal-backdrop { background: rgba(0, 0, 0, 0.6); }
[data-theme="dark"] .crud-modal { background: var(--surface-strong); color: var(--text); border-color: rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .crud-modal-head { background: rgba(255, 255, 255, 0.03); border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
[data-theme="dark"] .crud-form-grid input, [data-theme="dark"] .crud-form-grid textarea, [data-theme="dark"] .crud-form-grid select { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.15); color: var(--text); }
[data-theme="dark"] .crud-form-grid input:focus, [data-theme="dark"] .crud-form-grid textarea:focus, [data-theme="dark"] .crud-form-grid select:focus { background: rgba(255, 255, 255, 0.12); border-color: rgba(16, 185, 129, 0.4); }
[data-theme="dark"] .crud-form-grid input::placeholder { color: rgba(255, 255, 255, 0.5); }

/* Dark mode for picker tabs */
[data-theme="dark"] .picker-tab { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: var(--text); }
[data-theme="dark"] .picker-tab.is-active { background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.3); color: #a7f3d0; }

/* Dark mode for content picker list items */
[data-theme="dark"] .content-picker-item {
  background: rgba(255, 255, 255, 0.03);
  border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .content-picker-item:hover {
  background: rgba(16, 185, 129, 0.08);
  border-color: rgba(16, 185, 129, 0.3);
}

[data-theme="dark"] .item-icon-wrapper {
  background: rgba(16, 185, 129, 0.15);
  color: #6ee7b7;
}

[data-theme="dark"] .item-badge.resource {
  background: rgba(55, 138, 221, 0.15);
  color: #93c5fd;
}

[data-theme="dark"] .item-badge.activity {
  background: rgba(16, 185, 129, 0.15);
  color: #6ee7b7;
}

/* Dark mode for helper boxes */
[data-theme="dark"] .lesson-helper-box { background: rgba(255, 255, 255, 0.02); border-color: rgba(255, 255, 255, 0.1); }

/* Dark mode for assignment timeline */
[data-theme="dark"] .assignment-timeline { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); }
[data-theme="dark"] .assignment-date-card { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.15); }
[data-theme="dark"] .assignment-date-card input[type="datetime-local"] { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.15); color: var(--text); }
[data-theme="dark"] .assignment-date-card input[type="datetime-local"]:hover { background: rgba(255, 255, 255, 0.12); }
[data-theme="dark"] .assignment-date-card input[type="datetime-local"]:focus { background: rgba(255, 255, 255, 0.15); border-color: currentColor; }

/* Dark mode for upload dropzone */
[data-theme="dark"] .upload-dropzone { background: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.3); }
[data-theme="dark"] .upload-dropzone:hover { border-color: rgba(16, 185, 129, 0.5); box-shadow: 0 20px 40px -28px rgba(16, 185, 129, 0.3); }
[data-theme="dark"] .upload-dropzone-icon { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
[data-theme="dark"] .upload-dropzone strong { color: var(--text); }
[data-theme="dark"] .upload-dropzone span:last-child { color: rgba(255, 255, 255, 0.6); }

/* Dark mode for preview elements */
[data-theme="dark"] .preview-modal-body { background: #1a1f2e; }
[data-theme="dark"] .preview-scroll { background: #0f172a; color: var(--text); }
[data-theme="dark"] .preview-resource { background: rgba(255, 255, 255, 0.05); color: var(--text); }
[data-theme="dark"] .preview-resource-icon { background: rgba(16, 185, 129, 0.12); color: #6ee7b7; }
[data-theme="dark"] .preview-link { background: #10b981; color: #fff; }
[data-theme="dark"] .preview-link:hover { filter: brightness(1.2); }
[data-theme="dark"] .preview-state { color: rgba(255, 255, 255, 0.7); }
[data-theme="dark"] .preview-state .material-symbols-outlined { color: #10b981; }
</style>
