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

const typeOptions: Array<{ value: LessonType | 'h5p'; label: string; help: string; icon: string; color: string }> = [
  { value: LESSON_TYPES.VIDEO, label: 'Video bài giảng', help: 'Upload file video hoặc gắn link video có sẵn.', icon: 'play_circle', color: 'text-blue-600 bg-blue-100' },
  { value: LESSON_TYPES.DOCUMENT, label: 'Tài liệu / File', help: 'PDF, slide, biểu mẫu hoặc file đọc thêm.', icon: 'description', color: 'text-orange-600 bg-orange-100' },
  { value: LESSON_TYPES.QUIZ, label: 'Quiz / Kiểm tra', help: 'Bài kiểm tra trắc nghiệm, tự luận.', icon: 'quiz', color: 'text-purple-600 bg-purple-100' },
  { value: LESSON_TYPES.SCORM, label: 'SCORM Package', help: 'Upload file .zip SCORM, hệ thống tự giải nén.', icon: 'package_2', color: 'text-teal-600 bg-teal-100' },
  { value: 'h5p', label: 'H5P / Embed Link', help: 'Nhúng nội dung H5P, iframe, hoặc link từ site khác.', icon: 'code', color: 'text-pink-600 bg-pink-100' },
  { value: LESSON_TYPES.VIRTUAL_CLASS, label: 'Lớp trực tuyến', help: 'Zoom, Google Meet, Jitsi hoặc link họp khác.', icon: 'videocam', color: 'text-green-600 bg-green-100' },
  { value: LESSON_TYPES.ASSIGNMENT, label: 'Bài tập nộp file', help: 'Bài tập có hạn nộp, nhận file từ học viên.', icon: 'assignment', color: 'text-amber-600 bg-amber-100' },
]

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
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-md" @click.self="emit('close')">
      <div class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-[2.5rem] bg-surface-lowest p-8 shadow-2xl modal-bounce border border-white/20">
        <div class="mb-8 flex items-center justify-between border-b border-surface-dim/30 pb-6">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shadow-sm">
              <span class="material-symbols-outlined">{{ lesson ? 'edit' : 'add_circle' }}</span>
            </div>
            <div>
              <h3 class="font-headline text-2xl font-bold text-on-surface">{{ lesson ? 'Chỉnh sửa Bài học' : 'Tạo Module Học liệu' }}</h3>
              <p class="text-sm font-medium text-on-surface-variant">Mỗi bài học là một module nội dung riêng với cấu hình và preview phù hợp cho từng loại học liệu.</p>
            </div>
          </div>
          <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="emit('close')">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <form class="space-y-8" @submit.prevent="handleSubmit">
          <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-6">
              <UiInput v-model="form.title" label="Tiêu đề bài học" placeholder="VD: Buổi 1 - Tổng quan hệ thống" required />

              <div class="space-y-2">
                <label class="block font-bold text-sm text-on-surface ml-1">Mô tả nội dung học</label>
                <textarea
                  v-model="form.description"
                  rows="4"
                  placeholder="Tóm tắt nhanh nội dung, mục tiêu, cách học hoặc hướng dẫn học viên."
                  class="w-full rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-4 text-sm focus:border-primary focus:ring-1 focus:ring-primary shadow-sm outline-none transition-all"
                ></textarea>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <UiInput v-model="form.duration" label="Thời lượng dự kiến (giây)" type="number" min="0" />
                <label class="flex items-center gap-4 group cursor-pointer p-4 rounded-2xl border border-surface-dim/30 hover:border-primary/50 transition-all bg-surface-low/50 mt-7">
                  <input v-model="form.is_preview" type="checkbox" class="w-5 h-5 rounded-lg border-outline text-primary focus:ring-primary cursor-pointer">
                  <div>
                    <p class="text-sm font-bold text-on-surface group-hover:text-primary transition-colors">Cho phép xem thử</p>
                    <p class="text-xs text-on-surface-variant">Học viên chưa mua khóa học vẫn xem được module này.</p>
                  </div>
                </label>
              </div>
            </div>

            <div class="space-y-4">
              <p class="text-sm font-bold uppercase tracking-widest text-outline">Chọn loại nội dung</p>
              <div class="grid grid-cols-2 gap-3">
                <label
                  v-for="option in typeOptions"
                  :key="option.value"
                  class="cursor-pointer rounded-2xl border p-3 transition-all text-center group"
                  :class="form.type === option.value ? 'border-primary bg-primary/5 shadow-sm ring-1 ring-primary/30' : 'border-surface-dim/30 hover:border-primary/30 hover:bg-surface-low/50'"
                >
                  <input v-model="form.type" type="radio" :value="option.value" class="hidden">
                  <div class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors" :class="form.type === option.value ? 'bg-primary/10 text-primary' : option.color">
                      <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">{{ option.icon }}</span>
                    </div>
                    <div>
                      <p class="font-semibold text-on-surface text-xs leading-tight">{{ option.label }}</p>
                      <p class="mt-0.5 text-[10px] text-on-surface-variant leading-tight">{{ option.help }}</p>
                    </div>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <section v-if="form.type === LESSON_TYPES.VIDEO" class="space-y-4 rounded-[2rem] border border-surface-dim/30 bg-surface-low/40 p-6">
            <div>
              <h4 class="font-headline text-xl font-bold text-on-surface">Nguồn video bài giảng</h4>
              <p class="mt-1 text-sm text-on-surface-variant">Upload file video trực tiếp hoặc gắn link video có sẵn. File upload sẽ được ưu tiên khi lưu.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-4 text-sm text-on-surface">
                <span>{{ form.video_file ? form.video_file.name : (lesson?.video_url ? 'Đã có video hiện tại' : 'Chọn file video bài giảng') }}</span>
                <input type="file" accept="video/mp4,video/mov,video/avi,video/webm" class="hidden" @change="onVideoChange">
                <span class="font-semibold text-primary">Upload video</span>
              </label>
              <UiInput v-model="form.video_url" label="Hoặc gắn link video" type="url" placeholder="https://..." />
            </div>
            <div class="rounded-2xl border border-surface-dim/50 bg-surface-lowest p-4 text-sm text-on-surface-variant">
              <p><strong class="text-on-surface">Trạng thái hiện tại:</strong> {{ lesson?.video_url ? 'Bài học này đã có video hoặc link video.' : 'Chưa có nguồn video nào được gắn.' }}</p>
            </div>
          </section>

          <section v-if="form.type === LESSON_TYPES.QUIZ" class="space-y-4 rounded-[2rem] border border-purple-200 bg-purple-50/50 p-6">
            <div>
              <h4 class="font-headline text-xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-600">quiz</span> Quiz / Kiểm tra
              </h4>
              <p class="mt-1 text-sm text-on-surface-variant">Sau khi tạo bài học, bạn có thể vào trang quản lý quiz của bài học để thêm câu hỏi hoặc chọn từ ngân hàng câu hỏi có sẵn.</p>
            </div>
            <div class="rounded-xl bg-surface-lowest border border-surface-dim p-4 text-sm text-on-surface-variant flex items-start gap-3">
              <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">info</span>
              <span>Quiz sẽ được cấu hình chi tiết sau khi bài học được tạo thành công.</span>
            </div>
          </section>

          <section v-if="form.type === LESSON_TYPES.DOCUMENT" class="space-y-4 rounded-[2rem] border border-surface-dim/30 bg-surface-low/40 p-6">
            <div>
              <h4 class="font-headline text-xl font-bold text-on-surface">File đính kèm</h4>
              <p class="mt-1 text-sm text-on-surface-variant">Tải tài liệu ngay khi tạo bài học. Học viên sẽ xem ở tab tài liệu.</p>
            </div>
            <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-4 text-sm text-on-surface">
              <span>{{ form.attachments.length ? `${form.attachments.length} file đã chọn` : 'Chọn file tài liệu' }}</span>
              <input type="file" multiple class="hidden" @change="onAttachmentChange">
              <span class="font-semibold text-primary">Chọn file</span>
            </label>
            <div v-if="lesson?.attachments?.length" class="rounded-2xl border border-surface-dim/50 bg-surface-lowest p-4">
              <p class="text-sm font-semibold text-on-surface">Tài liệu hiện có</p>
              <ul class="mt-3 space-y-2 text-sm text-on-surface-variant">
                <li v-for="item in lesson.attachments" :key="item.id" class="flex items-center justify-between rounded-xl bg-surface-low px-3 py-2">
                  <span class="truncate">{{ item.title || item.file_name || item.name || 'Tài liệu đính kèm' }}</span>
                  <span class="text-xs font-semibold uppercase tracking-widest text-outline">Đã tải</span>
                </li>
              </ul>
            </div>
          </section>

          <section v-if="form.type === LESSON_TYPES.ASSIGNMENT" class="space-y-4 rounded-[2rem] border border-surface-dim/30 bg-surface-low/40 p-6">
            <div>
              <h4 class="font-headline text-xl font-bold text-on-surface">Cấu hình bài tập</h4>
              <p class="mt-1 text-sm text-on-surface-variant">Tạo mô tả, giới hạn file và hạn nộp cho bài tập về nhà.</p>
            </div>
            <textarea v-model="form.assignment.instructions" rows="5" class="w-full rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-4 text-sm outline-none focus:border-primary" placeholder="Nêu yêu cầu bài tập, tiêu chí chấm điểm, cách nộp bài..."></textarea>
            <div class="grid gap-4 md:grid-cols-3">
              <UiInput v-model="form.assignment.max_file_size" label="Dung lượng tối đa (KB)" type="number" min="1" />
              <UiInput v-model="form.assignment.allowed_extensions" label="Định dạng cho phép" placeholder="pdf,docx,zip" />
              <UiInput v-model="form.assignment.due_at" label="Hạn nộp" type="datetime-local" />
            </div>
            <div class="rounded-2xl border border-surface-dim/50 bg-surface-lowest p-4 text-sm text-on-surface-variant">
              Học viên sẽ nộp file theo định dạng {{ form.assignment.allowed_extensions || 'pdf,docx,zip' }} với giới hạn {{ form.assignment.max_file_size || 10240 }} KB.
            </div>
          </section>

          <section v-if="form.type === LESSON_TYPES.VIRTUAL_CLASS" class="space-y-4 rounded-[2rem] border border-surface-dim/30 bg-surface-low/40 p-6">
            <div>
              <h4 class="font-headline text-xl font-bold text-on-surface">Lớp học trực tuyến</h4>
              <p class="mt-1 text-sm text-on-surface-variant">Hỗ trợ Zoom, Google Meet, Jitsi hoặc link họp khác.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-2">
                <span class="block text-sm font-semibold text-on-surface">Nền tảng</span>
                <select v-model="form.virtual_class.provider" class="w-full rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary">
                  <option value="zoom">Zoom</option>
                  <option value="google_meet">Google Meet</option>
                  <option value="jitsi">Jitsi</option>
                  <option value="other">Khác</option>
                </select>
              </label>
              <UiInput v-model="form.virtual_class.start_at" label="Thời gian bắt đầu" type="datetime-local" />
              <UiInput v-model="form.virtual_class.join_url" label="Link tham gia" type="url" placeholder="https://..." />
              <UiInput v-model="form.virtual_class.start_url" label="Link host (tuỳ chọn)" type="url" placeholder="https://..." />
              <UiInput v-model="form.virtual_class.meeting_id" label="Meeting ID" />
              <UiInput v-model="form.virtual_class.meeting_password" label="Mật khẩu phòng" />
              <UiInput v-model="form.virtual_class.duration" label="Thời lượng (phút)" type="number" min="1" />
            </div>
            <div class="rounded-2xl border border-surface-dim/50 bg-surface-lowest p-4 text-sm text-on-surface-variant">
              <p><strong class="text-on-surface">Preview link tham gia:</strong> {{ form.virtual_class.join_url || 'Chưa gắn link tham gia' }}</p>
              <a v-if="form.virtual_class.join_url" :href="form.virtual_class.join_url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-2 font-semibold text-primary hover:underline">
                Mở thử link họp
                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
              </a>
            </div>
          </section>

          <section v-if="form.type === LESSON_TYPES.SCORM || form.type === 'h5p'" class="space-y-4 rounded-[2rem] border border-surface-dim/30 bg-surface-low/40 p-6">
            <div>
              <h4 class="font-headline text-xl font-bold text-on-surface">{{ form.type === 'h5p' ? 'H5P / Embed' : 'SCORM package' }}</h4>
              <p class="mt-1 text-sm text-on-surface-variant">
                {{ form.type === 'h5p' ? 'H5P dùng link embed/launch URL.' : 'SCORM dùng file .zip thật, hệ thống sẽ giải nén và tự tìm file launch.' }}
              </p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <template v-if="form.type === 'h5p'">
                <UiInput v-model="form.scorm_package.entry_url" label="Embed / Launch URL" type="url" placeholder="https://..." />
              </template>
              <template v-else>
                <label class="md:col-span-2 flex cursor-pointer items-center justify-between rounded-2xl border border-outline-variant bg-surface-lowest px-4 py-4 text-sm text-on-surface">
                  <span>{{ form.scorm_file ? form.scorm_file.name : 'Chọn file SCORM (.zip)' }}</span>
                  <input type="file" accept=".zip,application/zip" class="hidden" @change="onScormChange">
                  <span class="font-semibold text-primary">Chọn file</span>
                </label>
              </template>
              <UiInput v-model="form.scorm_package.title" label="Tiêu đề package" />
              <UiInput v-model="form.scorm_package.identifier" label="Identifier" />
              <UiInput v-model="form.scorm_package.version" label="Version" :placeholder="form.type === 'h5p' ? 'h5p' : '1.2'" />
            </div>
            <div v-if="form.type === 'scorm'" class="rounded-2xl border border-surface-dim/50 bg-surface-lowest p-4 text-sm text-on-surface-variant">
              <strong class="text-on-surface">Trạng thái package:</strong> {{ form.scorm_file ? `Sẵn sàng tải lên: ${form.scorm_file.name}` : (lesson?.scorm_package?.entry_url ? 'Đã có package SCORM đang hoạt động.' : 'Chưa có package nào được tải lên.') }}
            </div>
            <div v-if="form.type === 'h5p'" class="space-y-3 rounded-2xl border border-surface-dim/50 bg-surface-lowest p-4">
              <p class="text-sm font-semibold text-on-surface">Preview H5P / Embed</p>
              <div v-if="embedPreviewUrl" class="overflow-hidden rounded-2xl border border-surface-dim bg-black/5 aspect-video">
                <iframe :src="embedPreviewUrl" class="h-full w-full" frameborder="0" allowfullscreen />
              </div>
              <p v-else class="text-sm text-on-surface-variant">Gắn link embed để xem trước nội dung ngay trong form.</p>
            </div>
          </section>

          <div class="sticky bottom-0 -mx-6 -mb-6 mt-8 flex flex-col gap-3 border-t border-surface-dim/30 bg-surface-lowest/95 px-6 py-4 backdrop-blur sm:-mx-8 sm:-mb-8 sm:flex-row sm:justify-end sm:px-8">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-error/30 bg-error/10 px-6 py-3 text-sm font-bold text-error hover:bg-error hover:text-white hover:shadow-lg transition-all cursor-pointer"
              @click="emit('close')"
            >
              <span class="material-symbols-outlined text-[18px]">close</span>
              Hủy bỏ
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary/20 hover:bg-primary-dark hover:shadow-lg transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              <span v-else class="material-symbols-outlined text-[18px]">task_alt</span>
              {{ lesson ? 'Cập nhật module' : 'Tạo module' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
