<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { LESSON_TYPES, type LessonType } from '~/constants/lesson-types'

const props = defineProps<{
  show: boolean
  lesson?: any
  saving: boolean
}>()

const emit = defineEmits<{
  close: []
  save: [data: any]
}>()

/* ── Content type registry ── */
const contentTypes = [
  { key: LESSON_TYPES.VIDEO,        label: 'Bài học video',    kind: 'resource', icon: '🎬', help: 'Upload video hoặc gắn link YouTube, Drive.' },
  { key: LESSON_TYPES.DOCUMENT,     label: 'Tài liệu / File',  kind: 'resource', icon: '📎', help: 'PDF, slide, biểu mẫu hoặc file đọc thêm.' },
  { key: LESSON_TYPES.QUIZ,         label: 'Quiz / Kiểm tra',  kind: 'activity', icon: '📝', help: 'Bài kiểm tra trắc nghiệm, tự luận.' },
  { key: LESSON_TYPES.SCORM,        label: 'Gói SCORM',        kind: 'resource', icon: '📦', help: 'Upload file .zip SCORM, hệ thống tự giải nén.' },
  { key: 'h5p',                     label: 'H5P / Embed',      kind: 'resource', icon: '✨', help: 'Nhúng nội dung H5P, iframe, link embed.' },
  { key: LESSON_TYPES.VIRTUAL_CLASS,label: 'Lớp trực tuyến',   kind: 'activity', icon: '📹', help: 'Zoom, Google Meet, Jitsi hoặc link họp khác.' },
  { key: LESSON_TYPES.ASSIGNMENT,   label: 'Bài tập nộp file', kind: 'activity', icon: '📚', help: 'Bài tập có hạn nộp, nhận file từ học viên.' },
]

function typeLabel(key: string) {
  return contentTypes.find(t => t.key === key)?.label || key
}

function typeIcon(key: string) {
  return contentTypes.find(t => t.key === key)?.icon || '📘'
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
    <div v-if="show" class="crud-modal-backdrop" @click.self="emit('close')">
      <div class="crud-modal" :class="step === 'pick' ? 'crud-modal-wide' : 'crud-modal-wide'">

        <!-- ── Step 1: Content Type Picker ── -->
        <template v-if="step === 'pick'">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Thêm học liệu</p>
              <h3>Chọn hoạt động hoặc tài nguyên</h3>
            </div>
            <button class="topbar-ghost" type="button" @click="emit('close')">✕</button>
          </div>

          <div class="picker-toolbar">
            <input v-model="pickerSearch" class="crud-search" type="text" placeholder="Tìm kiếm hoạt động hoặc tài nguyên...">
            <div class="picker-tabs">
              <button type="button" :class="['picker-tab', { 'is-active': pickerTab === 'all' }]" @click="pickerTab = 'all'">Tất cả</button>
              <button type="button" :class="['picker-tab', { 'is-active': pickerTab === 'activity' }]" @click="pickerTab = 'activity'">Hoạt động</button>
              <button type="button" :class="['picker-tab', { 'is-active': pickerTab === 'resource' }]" @click="pickerTab = 'resource'">Tài nguyên</button>
            </div>
          </div>

          <div class="content-picker-grid">
            <button
              v-for="item in filteredTypes"
              :key="item.key"
              type="button"
              class="content-picker-card"
              @click="pickType(item.key)"
            >
              <span class="content-picker-icon">{{ item.icon }}</span>
              <strong>{{ item.label }}</strong>
              <span>{{ item.kind === 'activity' ? 'Hoạt động' : 'Tài nguyên' }}</span>
            </button>
          </div>

          <div v-if="filteredTypes.length === 0" class="crud-empty" style="margin-top:12px;">Không tìm thấy loại học liệu phù hợp.</div>
        </template>

        <!-- ── Step 2: Lesson Config Form ── -->
        <template v-else>
          <div class="crud-modal-head">
            <div style="display:flex; align-items:center; gap:14px;">
              <span class="content-picker-icon" style="font-size:1.4rem; flex-shrink:0;">{{ typeIcon(form.type) }}</span>
              <div>
                <p class="section-kicker">{{ typeLabel(form.type) }}</p>
                <h3>{{ lesson ? 'Cập nhật học liệu' : 'Tạo học liệu' }}</h3>
              </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <button v-if="!lesson" type="button" class="crud-secondary-btn" style="font-size:0.8rem; min-height:36px;" @click="backToPicker">← Chọn lại</button>
              <button class="topbar-ghost" type="button" @click="emit('close')">✕</button>
            </div>
          </div>

          <form @submit.prevent="handleSubmit">
            <div class="crud-form-grid" style="margin-bottom:16px;">
              <!-- Title -->
              <div class="crud-field crud-field-full">
                <span>Tên học liệu <span style="color:#ae3d37;">*</span></span>
                <input v-model="form.title" type="text" placeholder="VD: Bài 1 – Giới thiệu tổng quan" required>
              </div>

              <!-- Duration + Preview -->
              <div class="crud-field">
                <span>Thời lượng (giây)</span>
                <input v-model="form.duration" type="number" min="0" placeholder="0">
              </div>
              <div class="crud-field" style="align-self:end;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px 14px; border-radius:14px; border:1px solid rgba(17,17,17,0.08); background:rgba(17,17,17,0.02); min-height:48px;">
                  <input v-model="form.is_preview" type="checkbox" style="width:16px; height:16px; accent-color:var(--green-deep); cursor:pointer;">
                  <div>
                    <p style="margin:0; font-size:0.85rem; font-weight:700;">Cho phép xem thử</p>
                    <p style="margin:0; font-size:0.72rem; color:var(--muted);">Học viên chưa mua vẫn xem được.</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- VIDEO section -->
            <div v-if="form.type === LESSON_TYPES.VIDEO" class="lesson-section-panel" style="margin-bottom:16px;">
              <strong style="font-size:0.9rem; font-weight:800;">Nguồn video bài giảng</strong>
              <p style="margin:4px 0 0; font-size:0.8rem; color:var(--muted);">Upload file video trực tiếp hoặc gắn link. File upload sẽ được ưu tiên khi lưu.</p>
              <div class="crud-form-grid">
                <div class="crud-field">
                  <span>Upload file video</span>
                  <label class="upload-dropzone upload-dropzone-compact">
                    <input class="upload-dropzone-input" type="file" accept="video/mp4,video/mov,video/avi,video/webm" @change="onVideoChange">
                    <span class="upload-dropzone-icon">🎬</span>
                    <strong>{{ form.video_file ? form.video_file.name : (lesson?.video_url ? 'Đã có video hiện tại' : 'Chọn file video') }}</strong>
                    <span>MP4, WebM, MOV hoặc AVI</span>
                  </label>
                </div>
                <div class="crud-field">
                  <span>Hoặc gắn link video</span>
                  <input v-model="form.video_url" type="url" placeholder="https://youtube.com/... hoặc https://drive.google.com/...">
                  <small style="font-size:0.75rem; color:var(--muted); margin-top:4px;">YouTube, Google Drive, OneDrive hoặc URL trực tiếp.</small>
                </div>
              </div>
              <div v-if="lesson?.video_url" style="padding:10px 14px; border-radius:12px; background:rgba(var(--green-rgb),0.06); border:1px solid rgba(var(--green-rgb),0.12); font-size:0.8rem; color:var(--green-deep);">
                <strong>Trạng thái:</strong> Bài học này đã có video/link video.
              </div>
            </div>

            <!-- QUIZ section -->
            <div v-if="form.type === LESSON_TYPES.QUIZ" class="lesson-section-panel" style="margin-bottom:16px;">
              <strong style="font-size:0.9rem; font-weight:800; display:flex; align-items:center; gap:8px;">
                📝 Quiz / Kiểm tra
              </strong>
              <p style="margin:4px 0 0; font-size:0.8rem; color:var(--muted);">
                Sau khi tạo bài học, vào trang quản lý quiz để thêm câu hỏi hoặc chọn từ ngân hàng câu hỏi có sẵn.
              </p>
              <div style="display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:12px; background:#fff; border:1px solid rgba(17,17,17,0.07); font-size:0.82rem; color:var(--muted);">
                <span class="material-symbols-outlined" style="font-size:16px; color:var(--green-deep); flex-shrink:0; margin-top:1px;">info</span>
                <span>Quiz sẽ được cấu hình chi tiết sau khi bài học được tạo thành công.</span>
              </div>
            </div>

            <!-- DOCUMENT section -->
            <div v-if="form.type === LESSON_TYPES.DOCUMENT" class="lesson-section-panel" style="margin-bottom:16px;">
              <strong style="font-size:0.9rem; font-weight:800;">📎 File đính kèm</strong>
              <p style="margin:4px 0 0; font-size:0.8rem; color:var(--muted);">Tải tài liệu ngay khi tạo bài học. Học viên sẽ xem ở tab tài liệu.</p>
              <label class="upload-dropzone upload-dropzone-compact">
                <input class="upload-dropzone-input" type="file" multiple @change="onAttachmentChange">
                <span class="upload-dropzone-icon">📎</span>
                <strong>{{ form.attachments.length ? `${form.attachments.length} file đã chọn` : 'Chọn file tài liệu' }}</strong>
                <span>PDF, DOCX, PPTX, ZIP và nhiều định dạng khác.</span>
              </label>
              <div v-if="lesson?.attachments?.length" style="display:grid; gap:6px;">
                <p style="margin:0; font-size:0.82rem; font-weight:700;">Tài liệu hiện có</p>
                <div
                  v-for="item in lesson.attachments"
                  :key="item.id"
                  style="display:flex; align-items:center; justify-content:space-between; padding:7px 12px; border-radius:10px; background:rgba(17,17,17,0.03); border:1px solid rgba(17,17,17,0.07); font-size:0.8rem;"
                >
                  <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ item.title || item.file_name || item.name || 'Tài liệu đính kèm' }}</span>
                  <span style="font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); flex-shrink:0; margin-left:8px;">Đã tải</span>
                </div>
              </div>
            </div>

            <!-- ASSIGNMENT section -->
            <div v-if="form.type === LESSON_TYPES.ASSIGNMENT" class="lesson-section-panel" style="margin-bottom:16px;">
              <strong style="font-size:0.9rem; font-weight:800;">📚 Cấu hình bài tập</strong>
              <p style="margin:4px 0 0; font-size:0.8rem; color:var(--muted);">Mô tả yêu cầu, giới hạn file và hạn nộp cho bài tập về nhà.</p>
              <div class="crud-form-grid">
                <div class="crud-field crud-field-full">
                  <span>Yêu cầu bài tập</span>
                  <textarea
                    v-model="form.assignment.instructions"
                    rows="4"
                    class="crud-textarea"
                    placeholder="Nêu yêu cầu bài tập, tiêu chí chấm điểm, cách nộp bài..."
                  ></textarea>
                </div>
                <div class="crud-field">
                  <span>Dung lượng tối đa (KB)</span>
                  <input v-model="form.assignment.max_file_size" type="number" min="1">
                </div>
                <div class="crud-field">
                  <span>Định dạng cho phép</span>
                  <input v-model="form.assignment.allowed_extensions" type="text" placeholder="pdf,docx,zip">
                </div>
                <div class="crud-field crud-field-full">
                  <span>Hạn nộp</span>
                  <input v-model="form.assignment.due_at" type="datetime-local">
                </div>
              </div>
              <div style="padding:10px 14px; border-radius:12px; background:rgba(17,17,17,0.03); border:1px solid rgba(17,17,17,0.07); font-size:0.8rem; color:var(--muted);">
                Học viên nộp định dạng <strong>{{ form.assignment.allowed_extensions || 'pdf,docx,zip' }}</strong>,
                giới hạn <strong>{{ form.assignment.max_file_size || 10240 }} KB</strong>.
              </div>
            </div>

            <!-- VIRTUAL CLASS section -->
            <div v-if="form.type === LESSON_TYPES.VIRTUAL_CLASS" class="lesson-section-panel" style="margin-bottom:16px;">
              <strong style="font-size:0.9rem; font-weight:800;">📹 Lớp học trực tuyến</strong>
              <p style="margin:4px 0 0; font-size:0.8rem; color:var(--muted);">Hỗ trợ Zoom, Google Meet, Jitsi hoặc link họp khác.</p>
              <div class="crud-form-grid">
                <div class="crud-field">
                  <span>Nền tảng</span>
                  <select v-model="form.virtual_class.provider">
                    <option value="zoom">Zoom</option>
                    <option value="google_meet">Google Meet</option>
                    <option value="jitsi">Jitsi</option>
                    <option value="other">Khác</option>
                  </select>
                </div>
                <div class="crud-field">
                  <span>Thời gian bắt đầu</span>
                  <input v-model="form.virtual_class.start_at" type="datetime-local">
                </div>
                <div class="crud-field">
                  <span>Link tham gia</span>
                  <input v-model="form.virtual_class.join_url" type="url" placeholder="https://...">
                </div>
                <div class="crud-field">
                  <span>Link host (tùy chọn)</span>
                  <input v-model="form.virtual_class.start_url" type="url" placeholder="https://...">
                </div>
                <div class="crud-field">
                  <span>Meeting ID</span>
                  <input v-model="form.virtual_class.meeting_id" type="text">
                </div>
                <div class="crud-field">
                  <span>Mật khẩu phòng</span>
                  <input v-model="form.virtual_class.meeting_password" type="text">
                </div>
                <div class="crud-field crud-field-full">
                  <span>Thời lượng (phút)</span>
                  <input v-model="form.virtual_class.duration" type="number" min="1">
                </div>
              </div>
            </div>

            <!-- SCORM / H5P section -->
            <div v-if="form.type === LESSON_TYPES.SCORM || form.type === 'h5p'" class="lesson-section-panel" style="margin-bottom:16px;">
              <strong style="font-size:0.9rem; font-weight:800;">{{ form.type === 'h5p' ? '✨ H5P / Embed' : '📦 SCORM Package' }}</strong>
              <p style="margin:4px 0 0; font-size:0.8rem; color:var(--muted);">
                {{ form.type === 'h5p' ? 'H5P dùng link embed/launch URL hoặc dán nguyên đoạn iframe.' : 'SCORM dùng file .zip, hệ thống sẽ giải nén và tìm file launch.' }}
              </p>
              <div class="crud-form-grid">
                <template v-if="form.type === 'h5p'">
                  <div class="crud-field crud-field-full">
                    <span>Embed / Launch URL</span>
                    <textarea
                      v-model="form.scorm_package.entry_url"
                      rows="2"
                      class="crud-textarea"
                      placeholder="https://h5p.org/h5p/embed/612 — hoặc dán nguyên đoạn &lt;iframe src=&quot;...&quot;&gt;"
                    ></textarea>
                    <small style="font-size:0.75rem; color:var(--muted); margin-top:4px;">Hỗ trợ URL trần hoặc đoạn embed code. Hệ thống tự bóc <code style="padding:1px 5px; background:rgba(17,17,17,0.07); border-radius:4px;">src</code>.</small>
                  </div>
                </template>
                <template v-else>
                  <div class="crud-field crud-field-full">
                    <span>Upload file SCORM (.zip)</span>
                    <label class="upload-dropzone upload-dropzone-compact">
                      <input class="upload-dropzone-input" type="file" accept=".zip,application/zip" @change="onScormChange">
                      <span class="upload-dropzone-icon">📦</span>
                      <strong>{{ form.scorm_file ? form.scorm_file.name : 'Chọn file ZIP' }}</strong>
                      <span>Phiên bản 1.2 / 2004 tự nhận từ imsmanifest.xml.</span>
                    </label>
                  </div>
                </template>
                <div class="crud-field">
                  <span>Tiêu đề package</span>
                  <input v-model="form.scorm_package.title" type="text">
                </div>
                <div class="crud-field">
                  <span>Identifier</span>
                  <input v-model="form.scorm_package.identifier" type="text">
                </div>
              </div>
              <!-- H5P preview -->
              <div v-if="form.type === 'h5p' && embedPreviewUrl" style="overflow:hidden; border-radius:12px; border:1px solid rgba(17,17,17,0.1); background:#000; aspect-ratio:16/9;">
                <iframe :src="embedPreviewUrl" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>
              </div>
            </div>

            <!-- Description (all types except assignment) -->
            <div v-if="form.type !== LESSON_TYPES.ASSIGNMENT" class="crud-field crud-field-full" style="margin-bottom:16px;">
              <span>Mô tả nội dung học <span style="color:var(--muted); font-weight:400;">(Tùy chọn)</span></span>
              <textarea
                v-model="form.description"
                rows="3"
                class="crud-textarea"
                placeholder="Tóm tắt mục tiêu, cách học hoặc hướng dẫn học viên..."
              ></textarea>
            </div>

            <!-- Footer -->
            <div class="crud-modal-foot">
              <button class="crud-secondary-btn" type="button" @click="emit('close')">Hủy bỏ</button>
              <button class="crud-primary-btn" type="submit" :disabled="saving">
                <span v-if="saving" class="material-symbols-outlined" style="font-size:15px; margin-right:4px; animation:spin 1s linear infinite;">progress_activity</span>
                {{ saving ? 'Đang lưu...' : (lesson ? 'Cập nhật module' : 'Tạo module') }}
              </button>
            </div>
          </form>
        </template>

      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* ── Picker toolbar ── */
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
  padding: 0 18px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 600;
  font-family: inherit;
  color: var(--muted, #5f675f);
  transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
}

.picker-tab.is-active {
  background: rgba(var(--green-rgb), 0.12);
  color: var(--green-deep, var(--green-deep));
  border-color: rgba(var(--green-rgb), 0.2);
  font-weight: 700;
}

/* ── Content picker grid ── */
.content-picker-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 900px) {
  .content-picker-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 600px) {
  .content-picker-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.content-picker-card {
  display: grid;
  justify-items: start;
  gap: 10px;
  min-height: 148px;
  padding: 18px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 18px;
  background: #fff;
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}

.content-picker-card:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--green-rgb), 0.2);
  box-shadow: 0 18px 40px -28px rgba(17, 17, 17, 0.3);
}

.content-picker-card strong {
  font-size: 0.95rem;
  color: var(--text, #111111);
}

.content-picker-card > span:last-child {
  color: var(--muted, #5f675f);
  font-size: 0.85rem;
}

.content-picker-icon {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  background: rgba(var(--green-rgb), 0.1);
  font-size: 1.4rem;
}

/* ── Upload dropzone ── */
.upload-dropzone {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 10px;
  padding: 28px 20px;
  border: 2px dashed rgba(249, 115, 22, 0.85);
  border-radius: 24px;
  background: rgba(255, 247, 237, 0.75);
  text-align: center;
  cursor: pointer;
  transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}

.upload-dropzone:hover {
  transform: translateY(-1px);
  border-color: rgba(234, 88, 12, 0.95);
  box-shadow: 0 20px 40px -28px rgba(249, 115, 22, 0.45);
}

.upload-dropzone-compact {
  justify-items: start;
  text-align: left;
  padding: 16px 18px;
  border-radius: 18px;
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
  width: 48px;
  height: 48px;
  border-radius: 999px;
  background: rgba(249, 115, 22, 0.12);
  color: #ea580c;
  font-size: 1.4rem;
}

.upload-dropzone strong {
  font-size: 0.95rem;
  color: var(--text, #111111);
}

.upload-dropzone span:last-child {
  color: var(--muted, #5f675f);
  font-size: 0.88rem;
  line-height: 1.5;
}

/* ── Spin animation ── */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
