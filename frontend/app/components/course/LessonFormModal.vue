<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { 
  Film,
  BookOpen,
  FileQuestion,
  Package,
  Cpu,
  MonitorPlay,
  ClipboardList,
  Search,
  CheckCircle,
  HelpCircle,
  UploadCloud,
  FileCheck,
  VideoIcon,
  Calendar,
  Globe,
  Settings,
  X,
  FileCode,
  FolderOpen,
  ArrowLeft,
  Info,
  RefreshCw,
  Pencil,
  Clock
} from 'lucide-vue-next'
import { LESSON_TYPES, type LessonType } from '~/constants/lesson-types'

const props = defineProps<{
  show: boolean
  lesson?: any
  saving: boolean
  uploadProgress?: number
  uploadLabel?: string
}>()

const emit = defineEmits<{
  close: []
  save: [data: any]
}>()

/* ── Content type registry ── */
const contentTypes = [
  { key: LESSON_TYPES.VIDEO,        label: 'Bài học video',    kind: 'resource', icon: Film,         help: 'Tải lên video bài học trực tiếp hoặc liên kết từ Youtube, Drive.',       bg: 'rgba(16,185,129,0.10)',  color: '#10B981' },
  { key: LESSON_TYPES.DOCUMENT,     label: 'Tài liệu / File',  kind: 'resource', icon: BookOpen,     help: 'Học liệu PDF, slide bài giảng, hình ảnh hoặc tài liệu đọc thêm.',       bg: 'rgba(139,92,246,0.10)', color: '#8B5CF6' },
  { key: LESSON_TYPES.QUIZ,         label: 'Quiz / Kiểm tra',  kind: 'activity', icon: FileQuestion, help: 'Đánh giá kiến thức với bài trắc nghiệm tự động chấm điểm.',             bg: 'rgba(139,92,246,0.10)', color: '#8B5CF6' },
  { key: LESSON_TYPES.SCORM,        label: 'Gói SCORM',        kind: 'resource', icon: Package,      help: 'Tích hợp gói chuẩn SCORM .zip đóng gói sẵn học tập.',                   bg: 'rgba(234,88,12,0.10)',  color: '#EA580C' },
  { key: 'h5p',                     label: 'H5P / Embed',      kind: 'resource', icon: Cpu,          help: 'Nhúng học liệu tương tác H5P hoặc nội dung iframe bên thứ ba.',         bg: 'rgba(236,72,153,0.10)', color: '#EC4899' },
  { key: LESSON_TYPES.VIRTUAL_CLASS,label: 'Lớp trực tuyến',   kind: 'activity', icon: MonitorPlay,  help: 'Tổ chức buổi học trực tiếp qua Zoom, Google Meet hoặc Jitsi.',          bg: 'rgba(14,165,233,0.10)', color: '#0EA5E9' },
  { key: LESSON_TYPES.ASSIGNMENT,   label: 'Bài tập nộp file', kind: 'activity', icon: ClipboardList,help: 'Yêu cầu thực hành, nhận bài làm đính kèm từ học viên.',                 bg: 'rgba(245,158,11,0.10)', color: '#D97706' },
]

function typeLabel(key: string) {
  return contentTypes.find(t => t.key === key)?.label || key
}

function typeIcon(key: string) {
  return contentTypes.find(t => t.key === key)?.icon || FileText
}

/* ── Step management: 'pick' | 'form' ── */
const step = ref<'pick' | 'form'>('pick')

/* ── Picker state ── */
const pickerTab = ref<'all' | 'activity' | 'resource'>('all')
const pickerSearch = ref('')
const filteredTypes = computed(() => contentTypes.filter((item) => {
  const matchesTab = pickerTab.value === 'all' || item.kind === pickerTab.value
  const matchesSearch = !pickerSearch.value.trim() || item.label.toLowerCase().includes(pickerSearch.value.toLowerCase())
  return matchesTab && matchesSearch
}))

/* ── Form state ── */
const createDefaultForm = () => ({
  title: '',
  description: '',
  type: LESSON_TYPES.VIDEO as LessonType | 'h5p',
  is_preview: false,
  duration: 0,
  video_url: '',
  video_file: null as File | null,
  attachments: [] as File[],
  assignment: {
    instructions: '',
    max_file_size: 10240,
    allowed_extensions: 'pdf,doc,docx,zip',
    due_at: '',
  },
  virtual_class: {
    provider: 'zoom',
    meeting_id: '',
    meeting_password: '',
    join_url: '',
    start_url: '',
    start_at: '',
    duration: 90,
  },
  scorm_package: {
    entry_url: '',
    title: '',
    identifier: '',
    version: '1.2',
  },
  scorm_file: null as File | null,
})

const form = ref(createDefaultForm())

/* ── Watchers ── */
watch(() => props.show, (val) => {
  if (val) {
    pickerTab.value = 'all'
    pickerSearch.value = ''
    step.value = props.lesson ? 'form' : 'pick'
  }
})

watch(() => props.lesson, (newVal) => {
  if (newVal) {
    form.value = {
      title: newVal.title || '',
      description: newVal.description || '',
      type: (newVal.type || LESSON_TYPES.VIDEO) as LessonType | 'h5p',
      is_preview: !!newVal.is_preview,
      duration: Number(newVal.duration || 0),
      video_url: newVal.video_url || '',
      video_file: null,
      attachments: [],
      assignment: {
        instructions: newVal.assignment?.instructions || '',
        max_file_size: Number(newVal.assignment?.max_file_size || 10240),
        allowed_extensions: newVal.assignment?.allowed_extensions || 'pdf,doc,docx,zip',
        due_at: newVal.assignment?.due_at ? newVal.assignment.due_at.slice(0, 16) : '',
      },
      virtual_class: {
        provider: newVal.virtual_class?.provider || 'zoom',
        meeting_id: newVal.virtual_class?.meeting_id || '',
        meeting_password: newVal.virtual_class?.meeting_password || '',
        join_url: newVal.virtual_class?.join_url || '',
        start_url: newVal.virtual_class?.start_url || '',
        start_at: newVal.virtual_class?.start_at ? newVal.virtual_class.start_at.slice(0, 16) : '',
        duration: Number(newVal.virtual_class?.duration || 90),
      },
      scorm_package: {
        entry_url: newVal.scorm_package?.entry_url || '',
        title: newVal.scorm_package?.title || '',
        identifier: newVal.scorm_package?.identifier || '',
        version: newVal.scorm_package?.version || (newVal.type === 'h5p' ? 'h5p' : '1.2'),
      },
      scorm_file: null,
    }
  } else {
    form.value = createDefaultForm()
  }
}, { immediate: true })

const embedPreviewUrl = computed(() => {
  if (form.value.type !== 'h5p') return ''
  return form.value.scorm_package.entry_url?.trim() || ''
})

/* ── Handlers ── */
function pickType(key: string) {
  form.value.type = key as LessonType | 'h5p'
  step.value = 'form'
}

function backToPicker() {
  if (!props.lesson) step.value = 'pick'
}

function onAttachmentChange(event: Event) {
  const input = event.target as HTMLInputElement
  form.value.attachments = Array.from(input.files || [])
}

function onVideoChange(event: Event) {
  const input = event.target as HTMLInputElement
  form.value.video_file = input.files?.[0] || null
}

function onScormChange(event: Event) {
  const input = event.target as HTMLInputElement
  form.value.scorm_file = input.files?.[0] || null
}

function handleSubmit() {
  emit('save', { ...form.value })
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="studio-modal-backdrop" @click.self="emit('close')">
        <div class="studio-modal-card is-curriculum">

          <!-- ── Step 1: Content Type Picker ── -->
          <template v-if="step === 'pick'">
            <div class="modal-header">
              <div>
                <span class="modal-subtitle-tag">Thêm học liệu</span>
                <h3 class="modal-title-text">Chọn hoạt động hoặc tài nguyên</h3>
              </div>
              <button class="modal-close-x-btn" type="button" @click="emit('close')">✕</button>
            </div>

            <div class="picker-panel-body">
              <div class="picker-search-bar">
                <div class="search-input-wrap">
                  <Search :size="15" class="search-icon" />
                  <input 
                    v-model="pickerSearch" 
                    type="text" 
                    placeholder="Tìm kiếm hoạt động hoặc tài nguyên..."
                    class="search-input-field"
                  />
                </div>
                
                <div class="picker-tabs-segmented">
                  <button 
                    type="button" 
                    :class="['segment-tab-btn', { 'is-active': pickerTab === 'all' }]" 
                    @click="pickerTab = 'all'"
                  >
                    Tất cả
                  </button>
                  <button 
                    type="button" 
                    :class="['segment-tab-btn', { 'is-active': pickerTab === 'activity' }]" 
                    @click="pickerTab = 'activity'"
                  >
                    Hoạt động
                  </button>
                  <button 
                    type="button" 
                    :class="['segment-tab-btn', { 'is-active': pickerTab === 'resource' }]" 
                    @click="pickerTab = 'resource'"
                  >
                    Tài nguyên
                  </button>
                </div>
              </div>

              <div class="content-cards-grid">
                <button
                  v-for="item in filteredTypes"
                  :key="item.key"
                  type="button"
                  class="activity-picker-card"
                  @click="pickType(item.key)"
                >
                  <div class="card-icon-frame" :style="{ background: item.bg, color: item.color }">
                    <component :is="item.icon" :size="20" />
                  </div>
                  <div class="card-info-wrap">
                    <strong class="card-label-name">{{ item.label }}</strong>
                    <span class="card-help-desc">{{ item.help }}</span>
                    <span :class="['card-badge-type', item.kind === 'activity' ? 'is-activity' : 'is-resource']">
                      {{ item.kind === 'activity' ? 'Hoạt động' : 'Tài nguyên' }}
                    </span>
                  </div>
                </button>
              </div>

              <div v-if="filteredTypes.length === 0" class="picker-empty-result">
                Không tìm thấy loại học liệu phù hợp với từ khóa tìm kiếm.
              </div>
            </div>
          </template>

          <!-- ── Step 2: Lesson Config Form ── -->
          <template v-else>
            <div class="modal-header">
              <div class="header-type-title-row">
                <div class="selected-type-icon-box">
                  <component :is="typeIcon(form.type)" :size="20" />
                </div>
                <div>
                  <span class="modal-subtitle-tag">{{ typeLabel(form.type) }}</span>
                  <h3 class="modal-title-text">{{ lesson ? 'Cập nhật nội dung học liệu' : 'Tạo mới học liệu giảng dạy' }}</h3>
                </div>
              </div>
              
              <div class="header-operations-row">
                <button v-if="!lesson" type="button" class="back-to-picker-btn" @click="backToPicker">
                  <ArrowLeft :size="13" />
                  <span>Chọn lại</span>
                </button>
                <button class="modal-close-x-btn" type="button" @click="emit('close')">✕</button>
              </div>
            </div>

            <form @submit.prevent="handleSubmit" class="modal-form-wrapper">
              <div class="modal-form-scroll-body">
                
                <!-- Core form fields group -->
                <div class="form-fields-grid-2">
                  <div class="form-field-group is-full-width">
                    <label class="custom-label">
                      <span>Tên học liệu</span>
                      <span class="required-indicator">*</span>
                    </label>
                    <div class="input-icon-wrapper">
                      <Pencil :size="14" class="input-icon" />
                      <input 
                        v-model="form.title" 
                        type="text" 
                        class="custom-input"
                        placeholder="Ví dụ: Bài 1 – Tổng quan kiến trúc MVC" 
                        required
                      />
                    </div>
                  </div>

                  <div class="form-field-group">
                    <label class="custom-label">Thời lượng ước tính (giây)</label>
                    <div class="input-icon-wrapper">
                      <Clock :size="14" class="input-icon" />
                      <input 
                        v-model.number="form.duration" 
                        type="number" 
                        min="0" 
                        class="custom-input"
                        placeholder="0"
                      />
                    </div>
                  </div>

                  <div class="form-field-group align-end">
                    <label class="checkbox-preview-card">
                      <input 
                        v-model="form.is_preview" 
                        type="checkbox" 
                        class="custom-checkbox"
                      />
                      <div class="checkbox-label-block">
                        <span class="checkbox-title">Cho phép học thử</span>
                        <span class="checkbox-desc">Học viên chưa đăng ký vẫn xem được bài này.</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- VIDEO specific panel -->
                <div v-if="form.type === LESSON_TYPES.VIDEO" class="lesson-type-fields-panel theme-video">
                  <h4 class="panel-section-title">Nguồn video bài giảng</h4>
                  <p class="panel-section-desc">Hỗ trợ tải lên video trực tiếp từ máy tính hoặc nhúng liên kết từ Youtube, Drive.</p>
                  
                  <div class="form-fields-grid-2">
                    <div class="form-field-group">
                      <span class="custom-label">Tải lên video trực tiếp</span>
                      <label class="custom-file-dropzone">
                        <input type="file" accept="video/mp4,video/mov,video/avi,video/webm" @change="onVideoChange" class="dropzone-input-file" />
                        <UploadCloud :size="24" class="dropzone-icon" />
                        <strong class="dropzone-title">
                          {{ form.video_file ? form.video_file.name : (lesson?.video_url ? 'Đã liên kết video' : 'Chọn file video') }}
                        </strong>
                        <span class="dropzone-hint">Định dạng MP4, WebM, MOV. Tối đa 200MB.</span>
                      </label>
                    </div>

                    <div class="form-field-group">
                      <span class="custom-label">Hoặc liên kết video URL</span>
                      <div class="input-icon-wrapper">
                        <Globe :size="14" class="input-icon" />
                        <input 
                          v-model="form.video_url" 
                          type="url" 
                          class="custom-input" 
                          placeholder="Ví dụ: https://youtube.com/watch?v=..."
                        />
                      </div>
                      <span class="input-hint-small text-muted mt-1">Chấp nhận link YouTube public, Google Drive chia sẻ công khai hoặc URL trực tiếp.</span>
                    </div>
                  </div>

                  <div v-if="lesson?.video_url" class="attachment-status-toast">
                    <CheckCircle :size="14" />
                    <span>Bài học hiện đã được cấu hình đường dẫn video bài giảng.</span>
                  </div>
                </div>

                <!-- QUIZ specific panel -->
                <div v-if="form.type === LESSON_TYPES.QUIZ" class="lesson-type-fields-panel theme-quiz">
                  <h4 class="panel-section-title">Nội dung bài kiểm tra (Quiz)</h4>
                  <div class="info-alert-block">
                    <Info :size="16" class="alert-icon" />
                    <p class="alert-text">Để tạo câu hỏi trắc nghiệm, vui lòng hoàn tất việc khởi tạo bài học này trước. Sau đó nhấn biểu tượng "Quiz" ngoài danh sách bài giảng để thiết lập chi tiết câu hỏi.</p>
                  </div>
                </div>

                <!-- DOCUMENT specific panel -->
                <div v-if="form.type === LESSON_TYPES.DOCUMENT" class="lesson-type-fields-panel theme-document">
                  <h4 class="panel-section-title">Tải lên tài liệu học tập</h4>
                  <p class="panel-section-desc">Học viên có thể xem trực tuyến hoặc tải về tài nguyên học tập đính kèm ở bài học này.</p>
                  
                  <label class="custom-file-dropzone is-wide">
                    <input type="file" multiple @change="onAttachmentChange" class="dropzone-input-file" />
                    <UploadCloud :size="24" class="dropzone-icon" />
                    <strong class="dropzone-title">
                      {{ form.attachments.length ? `${form.attachments.length} file tài liệu đã chọn` : 'Chọn file tài liệu giảng dạy' }}
                    </strong>
                    <span class="dropzone-hint">Chấp nhận PDF, DOCX, PPTX, XLSX, ZIP.</span>
                  </label>

                  <div v-if="lesson?.attachments?.length" class="existing-attachments-panel">
                    <h5 class="attachments-section-title">Danh sách tài liệu hiện có</h5>
                    <div class="attachments-list">
                      <div v-for="item in lesson.attachments" :key="item.id" class="attachment-row-item">
                        <span class="attachment-file-name">{{ item.title || item.file_name || item.name || 'Tài liệu đính kèm' }}</span>
                        <span class="attachment-status-tag">Đã tải lên</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ASSIGNMENT specific panel -->
                <div v-if="form.type === LESSON_TYPES.ASSIGNMENT" class="lesson-type-fields-panel theme-assignment">
                  <h4 class="panel-section-title">Cấu hình bài tập thực hành</h4>
                  <p class="panel-section-desc">Cấu hình mô tả yêu cầu đề bài và hạn nộp, học viên sẽ phải giải quyết và nộp lại file.</p>
                  
                  <div class="form-fields-grid-2">
                    <div class="form-field-group is-full-width">
                      <label class="custom-label">Yêu cầu và đề bài tập</label>
                      <textarea 
                        v-model="form.assignment.instructions" 
                        rows="4" 
                        class="custom-textarea"
                        placeholder="Mô tả chi tiết đề bài tập, hướng dẫn cách làm và các tiêu chí đánh giá cho học viên..."
                      ></textarea>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Dung lượng tối đa được phép (KB)</label>
                      <div class="input-icon-wrapper">
                        <FileCheck :size="14" class="input-icon" />
                        <input 
                          v-model.number="form.assignment.max_file_size" 
                          type="number" 
                          min="1" 
                          class="custom-input"
                        />
                      </div>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Định dạng file cho phép</label>
                      <div class="input-icon-wrapper">
                        <FileCode :size="14" class="input-icon" />
                        <input 
                          v-model="form.assignment.allowed_extensions" 
                          type="text" 
                          class="custom-input"
                          placeholder="Ví dụ: pdf,docx,zip"
                        />
                      </div>
                    </div>

                    <div class="form-field-group is-full-width">
                      <label class="custom-label">Hạn chót nộp bài (Deadline)</label>
                      <div class="input-icon-wrapper">
                        <Calendar :size="14" class="input-icon" />
                        <input 
                          v-model="form.assignment.due_at" 
                          type="datetime-local" 
                          class="custom-input"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- VIRTUAL CLASS specific panel -->
                <div v-if="form.type === LESSON_TYPES.VIRTUAL_CLASS" class="lesson-type-fields-panel theme-virtual">
                  <h4 class="panel-section-title">Thiết lập phòng học trực tuyến</h4>
                  <p class="panel-section-desc">Lên lịch các lớp học trực tuyến đồng bộ tích hợp với Zoom, Google Meet.</p>
                  
                  <div class="form-fields-grid-2">
                    <div class="form-field-group">
                      <label class="custom-label">Nền tảng phòng học</label>
                      <div class="select-wrapper">
                        <select v-model="form.virtual_class.provider" class="custom-select">
                          <option value="zoom">Zoom Meeting</option>
                          <option value="google_meet">Google Meet</option>
                          <option value="jitsi">Jitsi Meet</option>
                          <option value="other">Link hội thoại khác</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Thời gian bắt đầu</label>
                      <div class="input-icon-wrapper">
                        <Calendar :size="14" class="input-icon" />
                        <input 
                          v-model="form.virtual_class.start_at" 
                          type="datetime-local" 
                          class="custom-input"
                        />
                      </div>
                    </div>

                    <div class="form-field-group is-full-width">
                      <label class="custom-label">Đường dẫn tham gia (Cho học viên)</label>
                      <div class="input-icon-wrapper">
                        <Globe :size="14" class="input-icon" />
                        <input 
                          v-model="form.virtual_class.join_url" 
                          type="url" 
                          class="custom-input"
                          placeholder="https://zoom.us/j/..."
                        />
                      </div>
                    </div>

                    <div class="form-field-group is-full-width">
                      <label class="custom-label">Đường dẫn bắt đầu (Host)</label>
                      <div class="input-icon-wrapper">
                        <Settings :size="14" class="input-icon" />
                        <input 
                          v-model="form.virtual_class.start_url" 
                          type="url" 
                          class="custom-input"
                          placeholder="https://zoom.us/s/..."
                        />
                      </div>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Meeting ID</label>
                      <div class="input-icon-wrapper">
                        <FileCode :size="14" class="input-icon" />
                        <input 
                          v-model="form.virtual_class.meeting_id" 
                          type="text" 
                          class="custom-input"
                        />
                      </div>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Mật khẩu phòng học (Passcode)</label>
                      <div class="input-icon-wrapper">
                        <Settings :size="14" class="input-icon" />
                        <input 
                          v-model="form.virtual_class.meeting_password" 
                          type="text" 
                          class="custom-input"
                        />
                      </div>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Thời lượng dự kiến (phút)</label>
                      <div class="input-icon-wrapper">
                        <Clock :size="14" class="input-icon" />
                        <input 
                          v-model.number="form.virtual_class.duration" 
                          type="number" 
                          min="1" 
                          class="custom-input"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <!-- SCORM / H5P specific panel -->
                <div v-if="form.type === LESSON_TYPES.SCORM || form.type === 'h5p'" class="lesson-type-fields-panel theme-scorm">
                  <h4 class="panel-section-title">
                    {{ form.type === 'h5p' ? 'Nội dung tích hợp H5P / Iframe' : 'Gói tài liệu SCORM Package' }}
                  </h4>
                  <p class="panel-section-desc">
                    {{ form.type === 'h5p' ? 'Dán mã nhúng Iframe hoặc đường dẫn URL học liệu tương tác H5P.' : 'Tải lên gói tiêu chuẩn ZIP SCORM 1.2 / 2004, hệ thống sẽ tự động giải nén cấu trúc.' }}
                  </p>
                  
                  <div class="form-fields-grid-2">
                    <template v-if="form.type === 'h5p'">
                      <div class="form-field-group is-full-width">
                        <label class="custom-label">Embed Code hoặc Launch URL tương tác</label>
                        <div class="input-icon-wrapper">
                          <Globe :size="14" class="input-icon" style="top: 20px;" />
                          <textarea 
                            v-model="form.scorm_package.entry_url" 
                            rows="2" 
                            class="custom-textarea"
                            placeholder="Ví dụ: &lt;iframe src=&quot;https://h5p.org/h5p/embed/612&quot; ...&gt;&lt;/iframe&gt;"
                          ></textarea>
                        </div>
                        <span class="input-hint-small text-muted mt-1">Hỗ trợ tự động bóc tách thuộc tính src nếu dán mã nhúng iframe trực tiếp.</span>
                      </div>
                    </template>
                    <template v-else>
                      <div class="form-field-group is-full-width">
                        <label class="custom-label">Tải lên gói SCORM (.zip)</label>

                        <!-- Upload progress bar (visible while uploading) -->
                        <div v-if="props.uploadProgress && props.uploadProgress > 0 && props.uploadProgress < 100" class="scorm-upload-progress">
                          <div class="scorm-upload-progress-header">
                            <span class="scorm-upload-icon material-symbols-outlined">upload_file</span>
                            <span class="scorm-upload-filename">{{ form.scorm_file?.name || 'Đang tải lên...' }}</span>
                            <span class="scorm-upload-pct">{{ props.uploadProgress }}%</span>
                          </div>
                          <div class="scorm-upload-track">
                            <div class="scorm-upload-fill" :style="{ width: props.uploadProgress + '%' }" />
                          </div>
                          <p class="scorm-upload-label">{{ props.uploadLabel || 'Đang xử lý gói SCORM...' }}</p>
                        </div>

                        <!-- Normal dropzone -->
                        <label v-else class="custom-file-dropzone is-wide">
                          <input type="file" accept=".zip,application/zip" @change="onScormChange" class="dropzone-input-file" />
                          <UploadCloud :size="24" class="dropzone-icon" />
                          <strong class="dropzone-title">
                            {{ form.scorm_file ? form.scorm_file.name : 'Chọn gói SCORM định dạng .zip' }}
                          </strong>
                          <span class="dropzone-hint">Phiên bản imsmanifest.xml tự kích hoạt tự động.</span>
                        </label>
                      </div>
                    </template>

                    <div class="form-field-group">
                      <label class="custom-label">Tiêu đề Package</label>
                      <div class="input-icon-wrapper">
                        <Pencil :size="14" class="input-icon" />
                        <input 
                          v-model="form.scorm_package.title" 
                          type="text" 
                          class="custom-input"
                        />
                      </div>
                    </div>

                    <div class="form-field-group">
                      <label class="custom-label">Mã định danh Identifier (Tùy chọn)</label>
                      <div class="input-icon-wrapper">
                        <FileCode :size="14" class="input-icon" />
                        <input 
                          v-model="form.scorm_package.identifier" 
                          type="text" 
                          class="custom-input"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Interactive preview live -->
                  <div v-if="form.type === 'h5p' && embedPreviewUrl" class="iframe-preview-frame">
                    <div class="preview-header-bar">Bản xem trước H5P trực quan</div>
                    <iframe :src="embedPreviewUrl" allowfullscreen></iframe>
                  </div>
                </div>

                <!-- Description (all types except assignments) -->
                <div v-if="form.type !== LESSON_TYPES.ASSIGNMENT" class="form-field-group is-full-width mt-4">
                  <label class="custom-label">Hướng dẫn và mô tả học liệu (Không bắt buộc)</label>
                  <textarea 
                    v-model="form.description" 
                    rows="3" 
                    class="custom-textarea"
                    placeholder="Mô tả sơ lược nội dung kiến thức truyền tải, tài nguyên hoặc chỉ dẫn học tập cho học viên..."
                  ></textarea>
                </div>

              </div>

              <!-- Footer Buttons controls -->
              <div class="modal-footer-ops">
                <button class="modal-secondary-action" type="button" @click="emit('close')">Hủy bỏ</button>
                <button class="modal-primary-action" type="submit" :disabled="saving">
                  <RefreshCw v-if="saving" :size="14" class="spin-icon" />
                  <span>{{ saving ? 'Đang lưu...' : (lesson ? 'Cập nhật học liệu' : 'Tạo học liệu') }}</span>
                </button>
              </div>
            </form>
          </template>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* segment tab styles */
.picker-panel-body {
  padding: 24px;
}

.picker-search-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.search-input-wrap {
  position: relative;
  flex: 1;
  min-width: 260px;
}

.search-input-wrap .search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
}

.search-input-field {
  width: 100%;
  padding: 10px 14px 10px 40px;
  border-radius: 10px;
  border: 1.5px solid var(--line);
  background: var(--surface);
  color: var(--text);
  outline: none;
  font-size: 0.88rem;
  transition: all 0.2s ease;
}

.search-input-field:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.08);
}

/* Segmented Tabs controls */
.picker-tabs-segmented {
  display: flex;
  background: var(--surface-strong);
  border: 1.5px solid var(--line);
  border-radius: 10px;
  padding: 4px;
  height: 40px;
  align-items: center;
}

.segment-tab-btn {
  padding: 0 16px;
  height: 30px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
}

.segment-tab-btn:hover {
  color: var(--text);
}

.segment-tab-btn.is-active {
  background: var(--green);
  color: #fff;
}

/* Picker cards grid */
.content-cards-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 768px) {
  .content-cards-grid {
    grid-template-columns: 1fr;
  }
}

.activity-picker-card {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  cursor: pointer;
  transition: all 200ms;
  text-align: left;
}

.activity-picker-card:hover {
  border-color: var(--green);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
  background: var(--surface);
}

.card-icon-frame {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: var(--green-soft);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-info-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.card-label-name {
  font-size: 0.92rem;
  font-weight: 800;
  color: var(--text);
}

.card-help-desc {
  font-size: 0.76rem;
  color: var(--muted);
  font-weight: 500;
  line-height: 1.4;
}

.card-badge-type {
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 6px;
  width: fit-content;
  margin-top: 4px;
}

.card-badge-type.is-activity {
  background: rgba(245, 158, 11, 0.08);
  color: #D97706;
  border: 1px solid rgba(245, 158, 11, 0.15);
}

.card-badge-type.is-resource {
  background: rgba(139, 92, 246, 0.08);
  color: #8B5CF6;
  border: 1px solid rgba(139, 92, 246, 0.15);
}

.picker-empty-result {
  text-align: center;
  padding: 40px 0;
  color: var(--muted);
  font-size: 0.88rem;
  font-weight: 600;
}

/* Form layouts */
.header-type-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.selected-type-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: var(--green-soft);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-operations-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.back-to-picker-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-weight: 750;
  cursor: pointer;
  transition: all 150ms;
}

.back-to-picker-btn:hover {
  background: var(--surface);
  color: var(--text);
  border-color: var(--muted);
}

.modal-form-scroll-body {
  padding: 24px;
  flex: 1;
  overflow-y: auto;
  max-height: 56vh;
}

.form-fields-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

@media (max-width: 640px) {
  .form-fields-grid-2 {
    grid-template-columns: 1fr;
  }
}

.form-field-group.is-full-width {
  grid-column: 1 / -1;
}

.form-field-group.align-end {
  align-self: end;
}

.checkbox-preview-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  cursor: pointer;
  user-select: none;
  min-height: 44px;
}

.custom-checkbox {
  width: 15px;
  height: 15px;
  accent-color: var(--green);
  cursor: pointer;
  margin-top: 3px;
}

.checkbox-label-block {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.checkbox-title {
  font-size: 0.82rem;
  font-weight: 750;
  color: var(--text);
}

.checkbox-desc {
  font-size: 0.72rem;
  color: var(--muted);
}

/* Specific panels */
.lesson-type-fields-panel {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px dashed var(--line);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel-section-title {
  margin: 0;
  font-size: 0.94rem;
  font-weight: 850;
  color: var(--text);
}

.panel-section-desc {
  margin: 0;
  font-size: 0.78rem;
  color: var(--muted);
  font-weight: 500;
  line-height: 1.4;
}

.input-hint-small {
  font-size: 0.72rem;
  color: var(--muted);
}

/* Dropzone styling */
.dropzone-input-file {
  display: none;
}

.custom-file-dropzone {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 24px;
  border-radius: 14px;
  border: 1.5px dashed var(--line);
  background: var(--surface-strong);
  cursor: pointer;
  text-align: center;
  transition: all 180ms ease;
}

.custom-file-dropzone.is-wide {
  width: 100%;
}

.custom-file-dropzone:hover {
  border-color: var(--green);
  background: var(--green-soft);
}

.dropzone-icon {
  color: var(--muted);
}

.custom-file-dropzone:hover .dropzone-icon {
  color: var(--green);
}

.dropzone-title {
  font-size: 0.84rem;
  font-weight: 750;
  color: var(--text-secondary);
}

.dropzone-hint {
  font-size: 0.72rem;
  color: var(--muted);
}

.attachment-status-toast {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--green-soft);
  color: var(--green);
  font-size: 0.78rem;
  font-weight: 700;
  border: 1px solid rgba(16, 185, 129, 0.1);
  margin-top: 4px;
}

.info-alert-block {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
}

.info-alert-block .alert-icon {
  color: var(--green);
  flex-shrink: 0;
  margin-top: 2px;
}

.info-alert-block .alert-text {
  margin: 0;
  font-size: 0.78rem;
  color: var(--text-secondary);
  line-height: 1.5;
  font-weight: 500;
}

/* Existing attachments */
.existing-attachments-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.attachments-section-title {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 800;
  color: var(--text-secondary);
}

.attachments-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.attachment-row-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: var(--surface-strong);
  font-size: 0.78rem;
}

.attachment-file-name {
  color: var(--text);
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 320px;
}

.attachment-status-tag {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--muted);
}

/* Dropdown styling */
.select-wrapper {
  position: relative;
  width: 100%;
}

.select-wrapper::after {
  content: '▼';
  font-size: 9px;
  color: var(--muted);
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

.custom-select {
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--line);
  background: var(--surface);
  color: var(--text);
  outline: none;
  font-size: 0.88rem;
  transition: all 0.2s ease;
  appearance: none;
  padding-right: 32px;
}

.custom-select:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(29, 158, 117, 0.08);
}

/* SCORM H5P Iframe preview */
.iframe-preview-frame {
  margin-top: 16px;
  border-radius: 14px;
  border: 1px solid var(--line);
  overflow: hidden;
  background: #000;
}

.preview-header-bar {
  background: var(--surface-strong);
  border-bottom: 1px solid var(--line);
  padding: 8px 14px;
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.iframe-preview-frame iframe {
  width: 100%;
  aspect-ratio: 16/9;
  border: none;
  display: block;
}

/* Dialog Backdrop Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Modal Backdrop & Card Layouts ── */
.studio-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.studio-modal-card {
  width: 100%;
  max-width: 600px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  animation: modal-enter 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.studio-modal-card.is-curriculum {
  max-width: 800px;
}

@keyframes modal-enter {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 24px;
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
}

.modal-subtitle-tag {
  font-size: 0.68rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--green);
  display: block;
  margin-bottom: 4px;
}

.modal-title-text {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 850;
  color: var(--text);
  letter-spacing: -0.02em;
}

.modal-close-x-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: var(--muted);
  cursor: pointer;
  transition: color 150ms;
}

.modal-close-x-btn:hover {
  color: var(--text);
}

.modal-footer-ops {
  padding: 16px 24px;
  border-top: 1px solid var(--line);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  flex-shrink: 0;
}

.modal-secondary-action {
  display: inline-flex;
  align-items: center;
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
}

.modal-secondary-action:hover {
  background: var(--surface);
  color: var(--text);
}

.modal-primary-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 10px;
  border: none;
  background: var(--green);
  color: #fff;
  font-size: 0.84rem;
  font-weight: 750;
  cursor: pointer;
  transition: background 150ms;
}

.modal-primary-action:hover:not(:disabled) {
  background: var(--green-deep);
}

.modal-primary-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ── Colored Themes for Lesson Type Panels ── */
.lesson-type-fields-panel.theme-video {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.02), rgba(16, 185, 129, 0.05));
  border-color: rgba(16, 185, 129, 0.15);
}
.lesson-type-fields-panel.theme-quiz {
  background: linear-gradient(135deg, rgba(139, 92, 246, 0.02), rgba(139, 92, 246, 0.05));
  border-color: rgba(139, 92, 246, 0.15);
}
.lesson-type-fields-panel.theme-document {
  background: linear-gradient(135deg, rgba(139, 92, 246, 0.02), rgba(139, 92, 246, 0.05));
  border-color: rgba(139, 92, 246, 0.15);
}
.lesson-type-fields-panel.theme-assignment {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.02), rgba(245, 158, 11, 0.05));
  border-color: rgba(245, 158, 11, 0.15);
}
.lesson-type-fields-panel.theme-virtual {
  background: linear-gradient(135deg, rgba(14, 165, 233, 0.02), rgba(14, 165, 233, 0.05));
  border-color: rgba(14, 165, 233, 0.15);
}
.lesson-type-fields-panel.theme-scorm {
  background: linear-gradient(135deg, rgba(236, 72, 153, 0.02), rgba(236, 72, 153, 0.05));
  border-color: rgba(236, 72, 153, 0.15);
}

/* ── Floating Input Icon Wrappers ── */
.input-icon-wrapper {
  position: relative;
  width: 100%;
  display: flex;
  align-items: center;
}

.input-icon-wrapper .input-icon {
  position: absolute;
  left: 14px;
  color: var(--muted);
  pointer-events: none;
  transition: color 0.2s ease;
  z-index: 10;
}

.input-icon-wrapper .custom-input,
.input-icon-wrapper .custom-textarea {
  padding-left: 40px !important;
}

.input-icon-wrapper:focus-within .input-icon {
  color: var(--green);
}

/* ── SCORM upload progress (inside dropzone area) ── */
.scorm-upload-progress {
  border: 2px solid var(--green, #1d9e75);
  border-radius: 12px;
  padding: 16px 20px;
  background: color-mix(in srgb, var(--green, #1d9e75) 8%, transparent);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.scorm-upload-progress-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.scorm-upload-icon {
  font-size: 22px;
  color: var(--green, #1d9e75);
  flex-shrink: 0;
}

.scorm-upload-filename {
  flex: 1;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary, #111827);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.scorm-upload-pct {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--green, #1d9e75);
  flex-shrink: 0;
}

.scorm-upload-track {
  width: 100%;
  height: 8px;
  background: color-mix(in srgb, var(--green, #1d9e75) 20%, transparent);
  border-radius: 99px;
  overflow: hidden;
}

.scorm-upload-fill {
  height: 100%;
  background: var(--green, #1d9e75);
  border-radius: 99px;
  transition: width 0.2s ease;
}

.scorm-upload-label {
  font-size: 0.78rem;
  color: var(--text-muted, #6b7280);
  margin: 0;
}
</style>
