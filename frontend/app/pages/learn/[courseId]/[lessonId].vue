<template>
  <div class="learn-shell">
    <!-- Top bar -->
    <header class="learn-topbar">
      <div class="learn-topbar-left">
        <NuxtLink :to="`/courses/${courseId}`" class="learn-back-btn" aria-label="Quay lại khóa học">
          <span class="material-symbols-outlined">arrow_back_ios_new</span>
        </NuxtLink>
        <NuxtLink :to="`/courses/${courseId}`" class="learn-course-pill">
          {{ (course?.title?.charAt(0) || 'C').toUpperCase() }}
        </NuxtLink>
        <h1 class="learn-course-title">{{ course?.title || 'Khóa học' }}</h1>
      </div>

      <div class="learn-topbar-right">
        <div class="learn-progress-summary">
          <span class="learn-progress-percent">{{ progress?.percent || 0 }}%</span>
          <span class="learn-progress-meta">·</span>
          <span class="learn-progress-meta">{{ completedSet.size }}/{{ lessons.length }} bài học</span>
        </div>
        <button type="button" class="learn-topbar-btn" @click="notePanelOpen = true">
          <span class="material-symbols-outlined">edit_note</span>
          <span>Ghi chú</span>
        </button>
        <button type="button" class="learn-topbar-btn" @click="activeTab = 'overview'">
          <span class="material-symbols-outlined">help</span>
          <span>Hướng dẫn</span>
        </button>
        <button type="button" class="learn-topbar-btn learn-topbar-btn--mobile" @click="isSidebarCollapsed = !isSidebarCollapsed" aria-label="Mở nội dung khóa học">
          <span class="material-symbols-outlined">{{ isSidebarCollapsed ? 'menu_book' : 'close' }}</span>
        </button>
      </div>
    </header>

    <!-- Body -->
    <div class="learn-body">
      <!-- Main column -->
      <main class="learn-main">
        <!-- Player area -->
        <section class="learn-player-wrap">
          <div class="learn-player">
            <div v-if="!lesson" class="learn-player-loading">
              <span class="material-symbols-outlined">progress_activity</span>
              <p>Đang tải bài học...</p>
            </div>
            <template v-else>
              <template v-if="lesson.type === 'video'">
                <VideoPlayer
                  v-if="lesson.video_url"
                  :key="`vid-${lesson.id}`"
                  :course-id="courseId"
                  :lesson-id="lesson.id"
                  class="learn-player-fill"
                  @progress="onPlayerProgress"
                  @ended="onPlayerEnded"
                />
                <div v-else class="learn-player-placeholder">
                  <span class="material-symbols-outlined">play_circle</span>
                  <p>Video đang xử lý hoặc chưa có.</p>
                </div>
              </template>

              <template v-else-if="lesson.type === 'audio'">
                <div class="learn-player-placeholder">
                  <span class="material-symbols-outlined">podcasts</span>
                  <h3>{{ lesson.title }}</h3>
                  <audio v-if="lesson.video_url" :src="lesson.video_url" controls class="learn-audio" @ended="onAudioEnded" />
                  <p v-else>Chưa có file audio cho bài học này.</p>
                </div>
              </template>

              <template v-else-if="lesson.type === 'file'">
                <div class="learn-resource-stage learn-resource-stage--document">
                  <div class="learn-resource-stage-copy">
                    <span class="material-symbols-outlined learn-resource-stage-icon">draft</span>
                    <h3>{{ lesson.title }}</h3>
                    <p>{{ lesson.description || 'Tải xuống hoặc mở trực tiếp tài liệu của bài học này.' }}</p>
                    <div class="learn-resource-actions">
                      <a v-if="lesson.video_url" :href="lesson.video_url" target="_blank" class="learn-resource-link">Mở tài liệu</a>
                      <button type="button" class="learn-resource-link learn-resource-link--ghost" @click="activeTab = 'files'">Xem tệp đính kèm</button>
                    </div>
                  </div>
                  <iframe v-if="canPreviewInline(lesson.video_url)" :src="lesson.video_url || undefined" class="learn-resource-preview-frame" title="Xem trước tài liệu" />
                </div>
              </template>

              <template v-else-if="lesson.type === 'page'">
                <div class="learn-page-stage">
                  <div class="learn-page-header">
                    <span class="material-symbols-outlined">article</span>
                    <div>
                      <h3>{{ lesson.title }}</h3>
                      <p>Trang nội dung bài học</p>
                    </div>
                  </div>
                  <div v-if="lesson.description" class="learn-page-content" v-html="lesson.description" />
                  <div v-else class="learn-player-placeholder learn-player-placeholder--light">
                    <span class="material-symbols-outlined">article</span>
                    <p>Trang này chưa có nội dung hiển thị.</p>
                  </div>
                </div>
              </template>

              <template v-else-if="lesson.type === 'h5p'">
                <H5PEmbed :src="lesson.scorm_package?.entry_url" class="learn-player-fill learn-player-fill--light" />
              </template>

              <template v-else-if="lesson.type === 'scorm'">
                <div class="learn-embed-stage">
                  <div class="learn-embed-stage-copy">
                    <span class="material-symbols-outlined learn-resource-stage-icon">subscriptions</span>
                    <h3>{{ lesson.title }}</h3>
                    <p>{{ lesson.description || 'Gói SCORM đang được phát trong khung tương tác phía bên cạnh.' }}</p>
                    <div class="learn-resource-actions">
                      <button type="button" class="learn-resource-link" @click="activeTab = 'overview'">Xem hướng dẫn</button>
                      <button type="button" class="learn-resource-link learn-resource-link--ghost" @click="activeTab = 'files'">Tài nguyên liên quan</button>
                    </div>
                  </div>
                  <ScormPlayer :course-id="courseId" :lesson-id="lesson.id" :package-data="lesson.scorm_package" class="learn-player-fill learn-player-fill--light" @completed="onScormCompleted" />
                </div>
              </template>

              <template v-else-if="lesson.type === 'virtual_class' || lesson.type === 'zoom' || lesson.type === 'meet'">
                <VirtualClassView v-if="lesson.virtual_class" :data="lesson.virtual_class" class="learn-player-fill learn-player-fill--light" />
                <div v-else class="learn-player-placeholder">
                  <span class="material-symbols-outlined">video_camera_front</span>
                  <p>Chưa có thông tin lớp học trực tuyến.</p>
                </div>
              </template>

              <template v-else-if="lesson.type === 'forum'">
                <div class="learn-discussion-stage">
                  <div class="learn-discussion-stage-copy">
                    <span class="material-symbols-outlined learn-resource-stage-icon">forum</span>
                    <h3>{{ lesson.title }}</h3>
                    <p>{{ lesson.description || 'Đây là bài học dạng thảo luận. Hãy đặt câu hỏi, chia sẻ kinh nghiệm và phản hồi ngay trong chủ đề này.' }}</p>
                    <div class="learn-resource-actions">
                      <button type="button" class="learn-resource-link" @click="activeTab = 'qa'">Mở thảo luận</button>
                      <button type="button" class="learn-resource-link learn-resource-link--ghost" @click="activeTab = 'overview'">Xem mô tả</button>
                    </div>
                  </div>
                  <div class="learn-discussion-stage-panel">
                    <QaSection :course-id="courseId" :lesson-id="lesson.id" />
                  </div>
                </div>
              </template>

              <template v-else-if="lesson.type === 'survey'">
                <div class="learn-survey-stage">
                  <div class="learn-survey-stage-copy">
                    <span class="material-symbols-outlined learn-resource-stage-icon">bar_chart</span>
                    <h3>{{ lesson.title }}</h3>
                    <p>{{ lesson.description || 'Khảo sát này giúp thu thập phản hồi hoặc kiểm tra cảm nhận của học viên sau nội dung bài học.' }}</p>
                    <div class="learn-survey-checklist">
                      <div>• Trả lời trung thực để giảng viên cải thiện khóa học</div>
                      <div>• Có thể mở khảo sát trong tab mới nếu được cấu hình liên kết</div>
                      <div>• Nếu chưa có form ngoài, mô tả khảo sát hiển thị ở phần Tổng quan</div>
                    </div>
                    <div class="learn-resource-actions">
                      <a v-if="lesson.video_url" :href="lesson.video_url" target="_blank" class="learn-resource-link">Mở khảo sát</a>
                      <button type="button" class="learn-resource-link learn-resource-link--ghost" @click="activeTab = 'overview'">Xem hướng dẫn</button>
                    </div>
                  </div>
                </div>
              </template>

              <template v-else-if="lesson.type === 'assignment'">
                <div v-if="lesson.assignment" class="learn-assignment-stage">
                  <div class="learn-assignment-stage-copy">
                    <span class="material-symbols-outlined learn-resource-stage-icon">assignment</span>
                    <h3>{{ lesson.title }}</h3>
                    <p>{{ lesson.description || 'Hoàn thành yêu cầu bài tập, đính kèm file đúng định dạng và nộp trước hạn để được chấm điểm.' }}</p>
                    <div class="learn-survey-checklist">
                      <div v-if="lesson.assignment?.due_at">• Hạn nộp: {{ new Date(lesson.assignment.due_at).toLocaleString('vi-VN') }}</div>
                      <div v-if="lesson.assignment?.allowed_extensions">• Định dạng cho phép: {{ lesson.assignment.allowed_extensions }}</div>
                      <div v-if="lesson.assignment?.max_file_size">• Dung lượng tối đa: {{ Math.round((lesson.assignment.max_file_size || 0) / 1024) }} MB</div>
                    </div>
                    <div class="learn-resource-actions">
                      <button type="button" class="learn-resource-link" @click="activeTab = 'overview'">Xem hướng dẫn</button>
                      <button type="button" class="learn-resource-link learn-resource-link--ghost" @click="activeTab = 'files'">Xem định dạng nộp</button>
                    </div>
                  </div>
                  <AssignmentView :data="lesson.assignment" :course-id="courseId" :lesson-id="lesson.id" class="learn-player-fill learn-player-fill--light learn-player-scroll" @submitted="onAssignmentSubmitted" />
                </div>
                <div v-else class="learn-player-placeholder">
                  <span class="material-symbols-outlined">assignment</span>
                  <p>Chưa có thông tin bài tập.</p>
                </div>
              </template>

              <template v-else-if="lesson.type === 'quiz'">
                <div class="learn-quiz-inline">
                  <StudentQuiz :course-id="courseId" :lesson-id="lesson.id" @completed="onQuizCompleted" />
                </div>
              </template>

              <template v-else>
                <div class="learn-player-placeholder">
                  <span class="material-symbols-outlined">{{ getIconForType(lesson.type) }}</span>
                  <h3>{{ lesson.title }}</h3>
                  <p>Nội dung bài học đang được hiển thị bên dưới.</p>
                </div>
              </template>
            </template>
          </div>
        </section>

        <!-- Lesson info -->
        <section v-if="lesson" class="learn-info">
          <h2 class="learn-lesson-title">{{ lesson.title }}</h2>
          <p class="learn-lesson-meta">
            <span v-if="course?.created_at">Cập nhật {{ formatUpdatedAt(course.created_at) }}</span>
            <span v-else>{{ getTypeText(lesson.type) }}</span>
            <span v-if="lesson.duration"> · {{ formatDuration(lesson.duration) }}</span>
          </p>

          <div class="learn-toolbar">
            <button type="button" class="learn-add-note-btn" @click="notePanelOpen = true">
              <span class="material-symbols-outlined">add</span>
              Thêm ghi chú tại <strong>{{ formatDuration(noteTimestamp) || '00:00' }}</strong>
            </button>

            <!-- Completion status / action -->
            <div v-if="isLessonCompleted" class="learn-complete-badge">
              <span class="material-symbols-outlined">check_circle</span>
              Đã hoàn thành bài học
            </div>
            <button
              v-else-if="canManuallyComplete"
              type="button"
              class="learn-complete-btn"
              :disabled="markingComplete"
              @click="markLessonComplete()"
            >
              <span class="material-symbols-outlined">{{ markingComplete ? 'progress_activity' : 'task_alt' }}</span>
              {{ markingComplete ? 'Đang lưu...' : 'Đánh dấu đã hoàn thành' }}
            </button>
            <span v-else class="learn-complete-hint">
              <span class="material-symbols-outlined">schedule</span>
              {{ autoCompleteHint }}
            </span>
          </div>

          <!-- Tabs -->
          <div class="learn-tabs">
            <button
              v-for="t in tabs"
              :key="t.id"
              :class="['learn-tab', { 'learn-tab--active': activeTab === t.id }]"
              @click="activeTab = t.id"
            >
              <span class="material-symbols-outlined">{{ t.iconStr }}</span>
              {{ t.label }}
            </button>
          </div>

          <div class="learn-tab-pane">
            <div v-if="activeTab === 'overview'" class="learn-prose">
              <div v-if="lesson.description" v-html="lesson.description" />
              <div v-else class="learn-empty">Không có mô tả chi tiết cho bài học này.</div>
            </div>
            <div v-else-if="activeTab === 'quiz'">
              <StudentQuiz :course-id="courseId" :lesson-id="lesson.id" @completed="onQuizCompleted" />
            </div>
            <div v-else-if="activeTab === 'files'">
              <div class="learn-file-list">
                <a v-if="lesson.video_url && ['file', 'audio', 'page'].includes(lesson.type)" :href="lesson.video_url" target="_blank" class="learn-file-item">
                  <span class="material-symbols-outlined">{{ getIconForType(lesson.type) }}</span>
                  <span>{{ lesson.type === 'page' ? 'Mở tài nguyên trang liên kết' : 'Mở tài liệu chính của bài học' }}</span>
                </a>
                <div v-if="lesson.assignment" class="learn-file-item learn-file-item--muted">
                  <span class="material-symbols-outlined">assignment</span>
                  <span>{{ lesson.assignment.allowed_extensions ? `Cho phép nộp: ${lesson.assignment.allowed_extensions}` : 'Bài tập có nộp file đính kèm' }}</span>
                </div>
                <a v-for="file in lesson.attachments || []" :key="file.id" :href="file.url" target="_blank" class="learn-file-item">
                  <span class="material-symbols-outlined">attach_file</span>
                  <span>{{ file.original_name || 'Tài liệu đính kèm' }}</span>
                </a>
              </div>
              <StudentAttachments v-if="!lesson.attachments?.length" :course-id="courseId" :lesson-id="lesson.id" />
            </div>
            <div v-else-if="activeTab === 'qa'">
              <QaSection :course-id="courseId" :lesson-id="lesson.id" />
            </div>
          </div>
        </section>

        <!-- Floating Q&A -->
        <button type="button" class="learn-qa-fab" @click="activeTab = 'qa'">
          <span class="material-symbols-outlined">forum</span>
          Hỏi đáp
        </button>
      </main>

      <!-- Right sidebar -->
      <aside :class="['learn-sidebar', { 'learn-sidebar--open': !isSidebarCollapsed }]">
        <div class="learn-sidebar-head">
          <h3>Nội dung khóa học</h3>
          <button type="button" class="learn-sidebar-close" @click="isSidebarCollapsed = true" aria-label="Đóng">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="learn-streak">
          <div class="learn-streak-icon">
            <span class="material-symbols-outlined">local_fire_department</span>
          </div>
          <div>
            <p class="learn-streak-title"><strong>1</strong> ngày liên tục</p>
            <p class="learn-streak-sub">Học hôm nay để giữ chuỗi</p>
          </div>
        </div>

        <nav class="learn-section-list">
          <div v-for="(sec, idx) in sectionGroups" :key="`sec-${sec.id}`" class="learn-section">
            <button type="button" class="learn-section-head" @click="toggleSection(sec.id)">
              <div class="learn-section-titles">
                <p class="learn-section-title">{{ idx + 1 }}. {{ sec.title }}</p>
                <p class="learn-section-meta">
                  {{ sec.completedCount }}/{{ sec.lessons.length }} | {{ formatTotalDuration(sec.totalDuration) }}
                </p>
              </div>
              <span class="material-symbols-outlined learn-section-chevron" :class="{ 'is-open': openSections[sec.id] }">expand_more</span>
            </button>

            <ul v-show="openSections[sec.id]" class="learn-lesson-list">
              <li v-for="l in sec.lessons" :key="l.id">
                <NuxtLink
                  :to="`/learn/${courseId}/${l.id}`"
                  :class="['learn-lesson-item', { 'is-active': l.id === currentLessonId, 'is-done': completedSet.has(l.id) }]"
                >
                  <div class="learn-lesson-num">{{ l.indexLabel }}</div>
                  <div class="learn-lesson-body">
                    <p class="learn-lesson-name">{{ l.title }}</p>
                    <p class="learn-lesson-time">
                      <span class="material-symbols-outlined">{{ getIconForType(l.type) }}</span>
                      {{ l.duration ? formatDuration(l.duration) : getTypeText(l.type) }}
                    </p>
                  </div>
                  <span v-if="completedSet.has(l.id)" class="material-symbols-outlined learn-lesson-check">check_circle</span>
                </NuxtLink>
              </li>
            </ul>
          </div>
        </nav>
      </aside>

      <div v-if="!isSidebarCollapsed" class="learn-sidebar-backdrop" @click="isSidebarCollapsed = true"></div>
    </div>

    <!-- Bottom navigation -->
    <footer class="learn-bottom">
      <button
        type="button"
        class="learn-bottom-btn learn-bottom-btn--prev"
        :disabled="!prevLesson"
        @click="goTo(prevLesson?.id)"
      >
        <span class="material-symbols-outlined">chevron_left</span>
        BÀI TRƯỚC
      </button>
      <button
        type="button"
        class="learn-bottom-btn learn-bottom-btn--next"
        :disabled="!nextLesson"
        @click="goTo(nextLesson?.id)"
      >
        BÀI TIẾP THEO
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
      <div class="learn-bottom-hint">
        <span class="material-symbols-outlined">menu_book</span>
        <span>{{ currentSectionLabel }}</span>
      </div>
    </footer>

    <!-- Note drawer -->
    <Teleport to="body">
      <div v-if="notePanelOpen" class="note-drawer-backdrop" @click.self="notePanelOpen = false">
        <aside class="note-drawer">
          <div class="note-drawer-head">
            <div>
              <p class="note-drawer-kicker">Ghi chú bài học</p>
              <h3>{{ lesson?.title || 'Bài học hiện tại' }}</h3>
            </div>
            <button type="button" class="note-close-btn" @click="notePanelOpen = false">Đóng</button>
          </div>

          <div class="note-drawer-form">
            <textarea v-model="noteDraft" rows="5" class="note-textarea" placeholder="Viết ghi chú nhanh cho bài học này..."></textarea>
            <div class="note-drawer-actions">
              <button type="button" class="learn-resource-link" @click="saveNote">Lưu ghi chú</button>
            </div>
          </div>

          <div class="note-list">
            <article v-for="(item, index) in savedNotes" :key="`${item.createdAt}-${index}`" class="note-card">
              <div class="note-card-head">
                <strong>Ghi chú {{ index + 1 }}</strong>
                <button type="button" class="note-delete-btn" @click="deleteNote(index)">Xóa</button>
              </div>
              <p>{{ item.content }}</p>
              <span>{{ new Date(item.createdAt).toLocaleString('vi-VN') }}</span>
            </article>
            <div v-if="savedNotes.length === 0" class="note-empty-state">Chưa có ghi chú nào cho bài học này.</div>
          </div>
        </aside>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QaSection from '../../../components/learn/QaSection.vue'
import { useCourseStore } from '~/stores/course'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: false })

interface LessonExtended {
  id: number
  course_id: number
  section_id?: number | null
  title: string
  type: string
  description?: string | null
  video_url?: string | null
  duration: number
  is_preview?: boolean
  virtual_class?: any
  scorm_package?: any
  offline_session?: any
  assignment?: any
  attachments?: { id: number; original_name?: string; url?: string; mime_type?: string }[]
  quiz?: { id: number; title?: string; description?: string | null } | null
}

interface SectionData {
  id: number | string
  title: string
  position?: number
  lessons: any[]
}

const route = useRoute()
const router = useRouter()
const courseStore = useCourseStore()

const courseId = Number(route.params.courseId)
const currentLessonId = computed(() => Number(route.params.lessonId))

const course = ref(courseStore.currentCourse)
const lesson = ref<LessonExtended | null>(null)
const lessons = ref<LessonExtended[]>([])
const sections = ref<SectionData[]>([])
const progress = ref<any>(null)
const activeTab = ref('overview')
const isSidebarCollapsed = ref(true)
const notePanelOpen = ref(false)
const noteDraft = ref('')
const noteTimestamp = ref(0)
const savedNotes = ref<Array<{ lessonId: number; content: string; createdAt: string }>>([])
const openSections = reactive<Record<string | number, boolean>>({})
const markingComplete = ref(false)

// Lesson types whose completion can ONLY be triggered via an explicit user
// action (no reliable auto-signal: opening a file, reading a page, embedded
// iframe content, joining a virtual class, etc.).
const MANUAL_COMPLETE_TYPES = new Set([
  'file', 'page', 'h5p', 'virtual_class', 'zoom', 'meet', 'forum', 'survey',
])
// Types where the player/component reports completion automatically.
const AUTO_COMPLETE_HINTS: Record<string, string> = {
  video: 'Tự động đánh dấu khi xem hết video',
  audio: 'Tự động đánh dấu khi nghe hết bài',
  quiz: 'Tự động đánh dấu khi vượt qua bài kiểm tra',
  assignment: 'Tự động đánh dấu khi nộp bài',
  scorm: 'Tự động đánh dấu khi gói SCORM báo hoàn thành',
}

function noteStorageKey() {
  return `lesson-notes:${courseId}:${currentLessonId.value}`
}

function loadNotes() {
  if (!import.meta.client) return
  try {
    const raw = localStorage.getItem(noteStorageKey())
    savedNotes.value = raw ? JSON.parse(raw) : []
  } catch {
    savedNotes.value = []
  }
}

function saveNote() {
  if (!import.meta.client || !noteDraft.value.trim()) return
  const next = [{ lessonId: currentLessonId.value, content: noteDraft.value.trim(), createdAt: new Date().toISOString() }, ...savedNotes.value]
  savedNotes.value = next
  localStorage.setItem(noteStorageKey(), JSON.stringify(next))
  noteDraft.value = ''
}

function deleteNote(index: number) {
  if (!import.meta.client) return
  savedNotes.value = savedNotes.value.filter((_, itemIndex) => itemIndex !== index)
  localStorage.setItem(noteStorageKey(), JSON.stringify(savedNotes.value))
}

const hasQuizTab = computed(() => lesson.value?.type === 'quiz' || Boolean(lesson.value?.quiz))
const hasFilesTab = computed(() => ['file', 'audio', 'video', 'scorm', 'h5p'].includes(lesson.value?.type || '') || Boolean(lesson.value?.attachments?.length))
const hasQaTab = computed(() => Boolean(lesson.value))

const tabs = computed(() => {
  const items = [{ id: 'overview', label: 'Tổng quan', iconStr: 'info' }]
  if (hasQuizTab.value) items.push({ id: 'quiz', label: 'Kiểm tra', iconStr: 'quiz' })
  if (hasFilesTab.value) items.push({ id: 'files', label: 'Tài liệu', iconStr: 'attach_file' })
  if (hasQaTab.value) items.push({ id: 'qa', label: lesson.value?.type === 'forum' ? 'Thảo luận' : 'Hỏi đáp', iconStr: 'forum' })
  return items
})

const completedSet = computed(() => {
  const set = new Set<number>()
  progress.value?.lessons?.forEach((l: any) => { if (l.completed) set.add(l.id) })
  return set
})

const isLessonCompleted = computed(() => Boolean(lesson.value?.id) && completedSet.value.has(lesson.value!.id))
const canManuallyComplete = computed(() => Boolean(lesson.value?.type) && MANUAL_COMPLETE_TYPES.has(lesson.value!.type))
const autoCompleteHint = computed(() => AUTO_COMPLETE_HINTS[lesson.value?.type || ''] || 'Hệ thống sẽ tự đánh dấu hoàn thành khi đủ điều kiện')

const currentIndex = computed(() => lessons.value.findIndex(l => l.id === currentLessonId.value))
const prevLesson = computed(() => currentIndex.value > 0 ? lessons.value[currentIndex.value - 1] : null)
const nextLesson = computed(() => currentIndex.value < lessons.value.length - 1 ? lessons.value[currentIndex.value + 1] : null)

const sectionGroups = computed(() => {
  const lessonList = lessons.value
  if (!lessonList.length) return []

  let groups: SectionData[]
  if (sections.value.length) {
    groups = sections.value.map(sec => ({
      id: sec.id,
      title: sec.title,
      position: sec.position,
      lessons: lessonList.filter(l => l.section_id === sec.id),
    })).filter(g => g.lessons.length)
  } else {
    const map = new Map<string | number, SectionData>()
    lessonList.forEach(l => {
      const key = l.section_id ?? '__none__'
      if (!map.has(key)) {
        map.set(key, { id: key, title: 'Nội dung khóa học', lessons: [] })
      }
      map.get(key)!.lessons.push(l)
    })
    groups = Array.from(map.values())
  }

  return groups.map((g, gi) => {
    const totalDuration = g.lessons.reduce((acc: number, l: any) => acc + (l.duration || 0), 0)
    const completedCount = g.lessons.filter((l: any) => completedSet.value.has(l.id)).length
    return {
      ...g,
      totalDuration,
      completedCount,
      lessons: g.lessons.map((l: any, li: number) => ({
        ...l,
        indexLabel: `${gi + 1}.${li + 1}`,
      })),
    }
  })
})

const currentSection = computed(() => sectionGroups.value.find(g => g.lessons.some((l: any) => l.id === currentLessonId.value)) || null)
const currentSectionLabel = computed(() => {
  const idx = sectionGroups.value.findIndex(g => g.id === currentSection.value?.id)
  if (idx < 0) return ''
  return `${idx + 1}. ${currentSection.value!.title}`
})

function toggleSection(id: number | string) {
  openSections[id] = !openSections[id]
}

function getTypeText(type: string) {
  const map: Record<string, string> = {
    video: 'Video', audio: 'Audio', file: 'Tệp', page: 'Trang', forum: 'Diễn đàn', survey: 'Khảo sát',
    scorm: 'SCORM', h5p: 'H5P', virtual_class: 'Lớp trực tuyến', zoom: 'Zoom', meet: 'Google Meet',
    offline: 'Offline', assignment: 'Bài tập', quiz: 'Kiểm tra',
  }
  return map[type] || 'Tài liệu'
}

function getIconForType(type: string) {
  const map: Record<string, string> = {
    video: 'play_circle', audio: 'podcasts', file: 'draft', page: 'article', forum: 'forum',
    survey: 'bar_chart', scorm: 'subscriptions', h5p: 'extension', virtual_class: 'video_camera_front',
    zoom: 'video_camera_front', meet: 'video_camera_front', offline: 'groups', assignment: 'assignment', quiz: 'quiz',
  }
  return map[type] || 'description'
}

function canPreviewInline(url?: string | null) {
  if (!url) return false
  const normalized = url.toLowerCase()
  return normalized.endsWith('.pdf') || normalized.endsWith('.txt') || normalized.endsWith('.png') || normalized.endsWith('.jpg') || normalized.endsWith('.jpeg') || normalized.endsWith('.webp')
}

function defaultTabForLesson(type?: string) {
  if (type === 'forum') return 'qa'
  if (type === 'quiz') return 'quiz'
  return 'overview'
}

function formatDuration(seconds: number) {
  if (!seconds) return ''
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

function formatTotalDuration(seconds: number) {
  if (!seconds) return '0 phút'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  if (h > 0) return `${h} giờ ${m} phút`
  return `${m} phút`
}

function formatUpdatedAt(value: string) {
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  return `tháng ${d.getMonth() + 1} năm ${d.getFullYear()}`
}

async function loadLesson() {
  try {
    lesson.value = await courseStore.fetchLesson(courseId, currentLessonId.value) as unknown as LessonExtended
    loadNotes()
    activeTab.value = defaultTabForLesson(lesson.value?.type)
  } catch {
    lesson.value = null
    savedNotes.value = []
  }
}

const onPlayerProgress = async (payload?: { watched_seconds?: number }) => {
  if (payload?.watched_seconds != null) noteTimestamp.value = Math.floor(payload.watched_seconds)
  progress.value = await courseStore.fetchCourseProgress(courseId)
}

const onPlayerEnded = async () => {
  await markLessonComplete({ silent: true })
}

async function markLessonComplete(opts: { silent?: boolean } = {}) {
  if (!lesson.value || isLessonCompleted.value) return
  if (!opts.silent) markingComplete.value = true
  try {
    await useApi(`/courses/${courseId}/lessons/${lesson.value.id}/progress`, {
      method: 'PUT',
      body: { completed: true },
    })
    progress.value = await courseStore.fetchCourseProgress(courseId)
  } catch (err) {
    // eslint-disable-next-line no-console
    console.error('[markLessonComplete]', err)
  } finally {
    markingComplete.value = false
  }
}

const onAudioEnded = () => markLessonComplete({ silent: true })
const onQuizCompleted = (payload: { passed?: boolean }) => {
  if (payload?.passed) markLessonComplete({ silent: true })
}
const onAssignmentSubmitted = () => markLessonComplete({ silent: true })
const onScormCompleted = () => {
  // Backend already marks via scorm/track endpoint — just refresh progress.
  courseStore.fetchCourseProgress(courseId).then(p => { progress.value = p }).catch(() => {})
}

function goTo(lessonId?: number) {
  if (!lessonId) return
  activeTab.value = 'overview'
  router.push(`/learn/${courseId}/${lessonId}`)
}

async function init() {
  if (!courseStore.currentCourse || courseStore.currentCourse.id !== courseId) {
    await courseStore.fetchCourse(courseId)
  }
  course.value = courseStore.currentCourse

  const data = await courseStore.fetchLessons(courseId)
  const rawList: any[] = Array.isArray(data)
    ? data
    : Array.isArray((data as any)?.data)
      ? (data as any).data
      : []
  lessons.value = rawList.filter((l: any) => !l.locked)

  try {
    const secs = await courseStore.fetchSections(courseId)
    sections.value = (secs || []).map((s: any) => ({
      id: s.id,
      title: s.title,
      position: s.position,
      lessons: s.lessons || [],
    }))
  } catch {
    sections.value = []
  }

  try {
    progress.value = await courseStore.fetchCourseProgress(courseId)
  } catch { }

  await loadLesson()
}

watch(currentLessonId, async () => {
  await loadLesson()
})

watch(currentSection, (sec) => {
  if (sec) openSections[sec.id] = true
})

watch(
  () => tabs.value.map(tab => tab.id),
  (tabIds) => {
    if (!lesson.value) return
    if (!tabIds.includes(activeTab.value)) {
      activeTab.value = tabIds[0] || 'overview'
    }
  },
  { immediate: true }
)

onMounted(init)
</script>

<style scoped>
.learn-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: #f6f8f3;
  color: #111111;
}

/* ───── Topbar ───── */
.learn-topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 60px;
  padding: 0 20px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(17, 17, 17, 0.08);
}

.learn-topbar-left,
.learn-topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.learn-back-btn {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: transparent;
  color: #5f675f;
  text-decoration: none;
  transition: background 0.15s;
}
.learn-back-btn:hover { background: rgba(47, 122, 69, 0.08); }
.learn-back-btn .material-symbols-outlined { font-size: 18px; }

.learn-course-pill {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #2f7a45;
  color: #fff;
  font-weight: 800;
  font-size: 0.85rem;
  text-decoration: none;
}

.learn-course-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: #111111;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 320px;
}

.learn-progress-summary {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-right: 8px;
  font-size: 0.85rem;
}
.learn-progress-percent {
  color: #2f7a45;
  font-weight: 700;
  font-size: 0.95rem;
}
.learn-progress-meta {
  color: #5f675f;
}

.learn-topbar-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 12px;
  border-radius: 8px;
  background: transparent;
  border: none;
  color: #5f675f;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
}
.learn-topbar-btn:hover { background: rgba(47, 122, 69, 0.08); }
.learn-topbar-btn .material-symbols-outlined { font-size: 18px; }
.learn-topbar-btn--mobile { display: none; }

/* ───── Body ───── */
.learn-body {
  display: flex;
  flex: 1;
  min-height: 0;
}

.learn-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  position: relative;
  padding-bottom: 80px;
}

/* ───── Player ───── */
.learn-player-wrap {
  background: #000;
  display: flex;
  justify-content: center;
}
.learn-player {
  position: relative;
  width: 100%;
  max-width: 1280px;
  aspect-ratio: 16 / 9;
  background: #000;
}
.learn-player-fill {
  width: 100%;
  height: 100%;
}
.learn-player-fill--light { background: #fff; }
.learn-player-scroll { overflow-y: auto; padding: 24px; }

.learn-player-loading,
.learn-player-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  color: rgba(255, 255, 255, 0.7);
  text-align: center;
  padding: 32px;
}
.learn-player-placeholder h3 {
  font-size: 1.15rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
}
.learn-player-placeholder p { margin: 0; max-width: 480px; }
.learn-player-placeholder .material-symbols-outlined { font-size: 64px; color: rgba(47, 122, 69, 0.7); }
.learn-player-loading .material-symbols-outlined { font-size: 40px; animation: spin 1.2s linear infinite; }

.learn-quiz-inline {
  position: absolute;
  inset: 0;
  overflow-y: auto;
  background: #fff;
}

.learn-audio { width: min(100%, 480px); }
.learn-resource-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  padding: 0 18px;
  border-radius: 999px;
  background: #2f7a45;
  color: #fff;
  font-weight: 700;
  text-decoration: none;
  border: none;
  cursor: pointer;
}

.learn-resource-link--ghost {
  background: transparent;
  border: 1px solid rgba(47, 122, 69, 0.35);
  color: #2f7a45;
}

.learn-resource-stage,
.learn-page-stage {
  width: 100%;
  height: 100%;
  background: #fff;
  color: #111111;
}

.learn-resource-stage {
  display: grid;
  grid-template-columns: minmax(0, 340px) minmax(0, 1fr);
}

.learn-resource-stage-copy,
.learn-page-stage {
  padding: 28px;
}

.learn-resource-stage-copy {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;
  background: rgba(47, 122, 69, 0.07);
  border-right: 1px solid rgba(17, 17, 17, 0.08);
}

.learn-resource-stage-copy h3,
.learn-page-header h3 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  color: #111111;
}

.learn-resource-stage-copy p,
.learn-page-header p {
  margin: 0;
  color: #5f675f;
  line-height: 1.7;
}

.learn-resource-stage-icon,
.learn-page-header .material-symbols-outlined {
  font-size: 42px;
  color: #2f7a45;
}

.learn-resource-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.learn-resource-preview-frame {
  width: 100%;
  height: 100%;
  min-height: 420px;
  border: 0;
  background: #e2e8f0;
}

.learn-page-stage {
  overflow-y: auto;
}

.learn-page-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 20px;
}

.learn-page-content {
  line-height: 1.8;
  color: #334155;
}

.learn-page-content :deep(h1),
.learn-page-content :deep(h2),
.learn-page-content :deep(h3) {
  color: #111111;
}

.learn-file-item--muted {
  opacity: 0.86;
}

/* ───── Lesson info ───── */
.learn-info {
  padding: 24px 32px;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
}
.learn-lesson-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 6px;
  color: #111111;
}

.learn-embed-stage,
.learn-discussion-stage,
.learn-survey-stage {
  width: 100%;
  height: 100%;
  display: grid;
  grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
  background: #fff;
  color: #111111;
}

.learn-embed-stage-copy,
.learn-discussion-stage-copy,
.learn-survey-stage-copy {
  padding: 28px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;
  background: rgba(47, 122, 69, 0.07);
  border-right: 1px solid rgba(17, 17, 17, 0.08);
}

.learn-discussion-stage-panel {
  min-height: 420px;
  padding: 20px;
  overflow-y: auto;
  background: #f6f8f3;
}

.learn-assignment-stage {
  width: 100%;
  height: 100%;
  display: grid;
  grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
  background: #fff;
  color: #111111;
}

.learn-assignment-stage-copy {
  padding: 28px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;
  background: rgba(47, 122, 69, 0.07);
  border-right: 1px solid rgba(17, 17, 17, 0.08);
}

.learn-assignment-stage :deep(.assignment-view) {
  padding: 0;
  background: #fff;
}

.learn-assignment-stage :deep(.main-card) {
  min-height: 100%;
  border-radius: 0;
  border: none;
  box-shadow: none;
}

.learn-survey-checklist {
  display: grid;
  gap: 10px;
  color: #5f675f;
  line-height: 1.7;
}

.learn-embed-stage :deep(.scorm-player-container) {
  height: 100%;
  min-height: 420px;
  border-radius: 0;
  box-shadow: none;
}

@media (max-width: 960px) {
  .learn-resource-stage,
  .learn-embed-stage,
  .learn-discussion-stage,
  .learn-survey-stage {
    grid-template-columns: 1fr;
  }

  .learn-resource-stage-copy,
  .learn-embed-stage-copy,
  .learn-discussion-stage-copy,
  .learn-survey-stage-copy {
    border-right: none;
    border-bottom: 1px solid rgba(17, 17, 17, 0.08);
  }
}

.learn-lesson-meta {
  font-size: 0.85rem;
  color: #5f675f;
  margin: 0 0 18px;
}

.learn-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.learn-complete-btn,
.learn-complete-badge,
.learn-complete-hint {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 16px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
}

.learn-complete-btn {
  background: #2f7a45;
  color: #fff;
  border: none;
  cursor: pointer;
  transition: filter 0.15s, transform 0.15s, box-shadow 0.15s;
  box-shadow: 0 6px 16px rgba(47, 122, 69, 0.32);
}
.learn-complete-btn:hover:not(:disabled) {
  filter: brightness(1.05);
  transform: translateY(-1px);
}
.learn-complete-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  box-shadow: none;
}
.learn-complete-btn .material-symbols-outlined { font-size: 18px; }
.learn-complete-btn[disabled] .material-symbols-outlined {
  animation: spin 1.2s linear infinite;
}

.learn-complete-badge {
  background: rgba(47, 122, 69, 0.1);
  color: #1f5d33;
  border: 1px solid rgba(47, 122, 69, 0.28);
}
.learn-complete-badge .material-symbols-outlined {
  font-size: 18px;
  color: #2f7a45;
  font-variation-settings: 'FILL' 1;
}

.learn-complete-hint {
  background: rgba(17, 17, 17, 0.03);
  border: 1px dashed rgba(17, 17, 17, 0.12);
  color: #5f675f;
  font-weight: 500;
}
.learn-complete-hint .material-symbols-outlined {
  font-size: 16px;
  color: #5f675f;
}

.learn-add-note-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 8px;
  background: rgba(47, 122, 69, 0.06);
  border: 1px solid rgba(47, 122, 69, 0.14);
  color: #111111;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s;
}
.learn-add-note-btn:hover { background: rgba(47, 122, 69, 0.1); }
.learn-add-note-btn strong { color: #2f7a45; font-weight: 700; }
.learn-add-note-btn .material-symbols-outlined { font-size: 16px; }

.learn-tabs {
  display: flex;
  gap: 24px;
  margin-top: 28px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.08);
}
.learn-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 12px 0;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #5f675f;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.learn-tab:hover { color: #111111; }
.learn-tab--active { color: #2f7a45; border-bottom-color: #2f7a45; }
.learn-tab .material-symbols-outlined { font-size: 18px; }

.learn-tab-pane {
  padding: 24px 0 40px;
  min-height: 200px;
  color: #111111;
}
.learn-prose { line-height: 1.7; }
.learn-prose :deep(h1),
.learn-prose :deep(h2),
.learn-prose :deep(h3) { color: #111111; }
.learn-prose :deep(a) { color: #2f7a45; }
.learn-empty { color: #5f675f; font-style: italic; }

.learn-file-list {
  display: grid;
  gap: 10px;
}
.learn-file-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  background: rgba(255, 255, 255, 0.88);
  color: #111111;
  text-decoration: none;
}
.learn-file-item:hover { background: rgba(47, 122, 69, 0.06); }

/* ───── Floating QA ───── */
.learn-qa-fab {
  position: fixed;
  right: calc(400px + 28px);
  bottom: 96px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 999px;
  background: #2f7a45;
  color: #fff;
  font-weight: 700;
  font-size: 0.9rem;
  border: none;
  box-shadow: 0 12px 28px rgba(47, 122, 69, 0.42);
  cursor: pointer;
  z-index: 30;
  transition: transform 0.15s;
}
.learn-qa-fab:hover { transform: translateY(-2px); }
.learn-qa-fab .material-symbols-outlined { font-size: 20px; }

/* ───── Sidebar ───── */
.learn-sidebar {
  width: 400px;
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.92);
  color: #111111;
  border-left: 1px solid rgba(17, 17, 17, 0.08);
  display: flex;
  flex-direction: column;
  height: calc(100vh - 60px);
  position: sticky;
  top: 60px;
  overflow: hidden;
}

.learn-sidebar-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(17, 17, 17, 0.08);
}
.learn-sidebar-head h3 {
  font-size: 1rem;
  font-weight: 700;
  margin: 0;
  color: #111111;
}
.learn-sidebar-close {
  display: none;
  background: none;
  border: none;
  cursor: pointer;
  color: #5f675f;
}

.learn-streak {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 16px 20px;
  padding: 12px 16px;
  border-radius: 14px;
  background: rgba(47, 122, 69, 0.06);
  border: 1px solid rgba(47, 122, 69, 0.18);
}
.learn-streak-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #fff;
  color: #2f7a45;
}
.learn-streak-icon .material-symbols-outlined { font-size: 22px; }
.learn-streak-title {
  margin: 0;
  font-size: 0.9rem;
  color: #111111;
}
.learn-streak-title strong { color: #1f5d33; font-weight: 800; }
.learn-streak-sub {
  margin: 2px 0 0;
  font-size: 0.78rem;
  color: #5f675f;
}

.learn-section-list {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 24px;
}

.learn-section + .learn-section { border-top: 1px solid rgba(17, 17, 17, 0.06); }

.learn-section-head {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 20px;
  background: rgba(17, 17, 17, 0.02);
  border: none;
  border-top: 1px solid rgba(17, 17, 17, 0.06);
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;
}
.learn-section-head:hover { background: rgba(47, 122, 69, 0.06); }
.learn-section-titles { min-width: 0; flex: 1; }
.learn-section-title {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 700;
  color: #111111;
}
.learn-section-meta {
  margin: 4px 0 0;
  font-size: 0.78rem;
  color: #5f675f;
}
.learn-section-chevron {
  color: #5f675f;
  transition: transform 0.2s;
}
.learn-section-chevron.is-open { transform: rotate(180deg); }

.learn-lesson-list {
  list-style: none;
  margin: 0;
  padding: 0;
  background: rgba(255, 255, 255, 0.88);
}

.learn-lesson-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  text-decoration: none;
  color: #111111;
  border-left: 3px solid transparent;
  transition: background 0.15s;
}
.learn-lesson-item:hover { background: rgba(47, 122, 69, 0.04); }
.learn-lesson-item.is-active {
  background: rgba(47, 122, 69, 0.08);
  border-left-color: #2f7a45;
}
.learn-lesson-item.is-active .learn-lesson-name { color: #1f5d33; font-weight: 700; }
.learn-lesson-item.is-done .learn-lesson-name { color: #5f675f; }

.learn-lesson-num {
  flex-shrink: 0;
  width: 36px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #5f675f;
  text-align: center;
}
.learn-lesson-body { min-width: 0; flex: 1; }
.learn-lesson-name {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.4;
  color: #111111;
}
.learn-lesson-time {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 4px 0 0;
  font-size: 0.75rem;
  color: #5f675f;
}
.learn-lesson-time .material-symbols-outlined { font-size: 14px; }
.learn-lesson-check {
  flex-shrink: 0;
  font-size: 18px;
  color: #2f7a45;
}

.learn-sidebar-backdrop {
  display: none;
}

/* ───── Bottom navigation ───── */
.learn-bottom {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 40;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  height: 64px;
  padding: 0 24px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(16px);
  border-top: 1px solid rgba(17, 17, 17, 0.08);
  padding-right: calc(400px + 24px);
}

.learn-bottom-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 38px;
  padding: 0 18px;
  border-radius: 999px;
  background: #2f7a45;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  border: none;
  cursor: pointer;
  transition: filter 0.15s;
}
.learn-bottom-btn:hover { filter: brightness(1.05); }
.learn-bottom-btn:disabled {
  background: rgba(17, 17, 17, 0.06);
  color: rgba(17, 17, 17, 0.3);
  cursor: not-allowed;
}
.learn-bottom-btn .material-symbols-outlined { font-size: 18px; }
.learn-bottom-btn--prev { background: rgba(17, 17, 17, 0.06); color: #5f675f; }
.learn-bottom-btn--prev:not(:disabled):hover { background: rgba(47, 122, 69, 0.08); color: #111111; }

.learn-bottom-hint {
  position: absolute;
  right: calc(400px + 24px);
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: #5f675f;
}
.learn-bottom-hint .material-symbols-outlined { font-size: 16px; }

/* ───── Note drawer (kept) ───── */
.note-drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 70;
  background: rgba(17, 17, 17, 0.24);
  display: flex;
  justify-content: flex-end;
}
.note-drawer {
  width: min(100%, 440px);
  height: 100%;
  background: #fff;
  color: #111111;
  padding: 24px;
  overflow-y: auto;
  box-shadow: -24px 0 60px rgba(17, 17, 17, 0.14);
}
.note-drawer-head,
.note-card-head,
.note-drawer-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.note-drawer-kicker {
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #2f7a45;
}
.note-drawer-head h3 {
  margin-top: 4px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #111111;
}
.note-close-btn {
  background: rgba(17, 17, 17, 0.06);
  border: none;
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
}
.note-drawer-form { margin-top: 24px; }
.note-textarea {
  width: 100%;
  border-radius: 18px;
  border: 1px solid rgba(17, 17, 17, 0.1);
  padding: 16px;
  resize: vertical;
}
.note-list {
  margin-top: 24px;
  display: grid;
  gap: 14px;
}
.note-card,
.note-empty-state {
  border-radius: 18px;
  border: 1px solid rgba(17, 17, 17, 0.08);
  padding: 16px;
  background: #fff;
}
.note-card p {
  margin: 10px 0;
  color: #334155;
  white-space: pre-wrap;
}
.note-card span,
.note-empty-state,
.note-delete-btn {
  font-size: 0.82rem;
  color: #5f675f;
}
.note-delete-btn {
  background: none;
  border: none;
  cursor: pointer;
}

/* ───── Animations ───── */
@keyframes spin {
  from { transform: rotate(0); }
  to { transform: rotate(360deg); }
}

/* ───── Responsive ───── */
@media (max-width: 1024px) {
  .learn-topbar-btn--mobile { display: inline-flex; }
  .learn-course-title { max-width: 200px; }

  .learn-sidebar {
    position: fixed;
    top: 60px;
    right: 0;
    bottom: 0;
    transform: translateX(100%);
    transition: transform 0.25s ease;
    z-index: 60;
    box-shadow: -16px 0 40px rgba(17, 17, 17, 0.18);
  }
  .learn-sidebar--open { transform: translateX(0); }
  .learn-sidebar-close { display: inline-grid; place-items: center; }
  .learn-sidebar-backdrop {
    display: block;
    position: fixed;
    inset: 60px 0 0;
    background: rgba(17, 17, 17, 0.24);
    z-index: 55;
  }

  .learn-bottom { padding-right: 24px; }
  .learn-bottom-hint { display: none; }
  .learn-qa-fab { right: 24px; bottom: 88px; }
}

@media (max-width: 640px) {
  .learn-info { padding: 20px 16px; }
  .learn-lesson-title { font-size: 1.2rem; }
  .learn-progress-summary { display: none; }
  .learn-topbar-btn span:not(.material-symbols-outlined) { display: none; }
  .learn-sidebar { width: 100%; max-width: 360px; }
}
</style>
