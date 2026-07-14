<template>
  <InstructorWorkspaceShell
    :title="course?.title || 'Studio Giáo trình'"
    description="Quản lý cấu trúc chương học, bài giảng và tài nguyên của khóa học."
    :breadcrumb="['Trang chủ', 'Khóa học', 'Giáo trình']"
  >
    <template #actions>
      <StatusBadge v-if="course" :value="course.status || 'draft'" />
      
      <NuxtLink to="/instructor/courses" class="studio-topbar-btn is-secondary">
        <i class="pi pi-arrow-left" style="font-size:0.9375rem" />
        <span>Quay lại</span>
      </NuxtLink>
      
      <button class="studio-topbar-btn is-secondary" @click="previewCourse">
        <i class="pi pi-eye" style="font-size:0.9375rem" />
        <span>Xem trước</span>
      </button>
      
      <button
        v-if="course?.status === 'draft' || course?.status === 'rejected'"
        :disabled="submitting"
        class="studio-topbar-btn is-primary"
        @click="submitForReview"
      >
        <Rocket :size="15" />
        <span>{{ submitting ? 'Đang gửi...' : 'Gửi kiểm duyệt' }}</span>
      </button>
    </template>

    <!-- Main Content Layout Grid -->
    <div class="curriculum-workspace-grid">
      <!-- Left side: Curriculum Studio Workspace -->
      <div class="curriculum-studio-area">
        <CurriculumStudio
          ref="studioRef"
          :course-id="courseId"
          @upload-video="handleUploadTrigger"
        />
      </div>

      <!-- Right side: Studio Guide Sidebar -->
      <aside class="curriculum-guide-sidebar">
        <div class="studio-guide-card">
          <div class="guide-header">
            <span class="guide-kicker">Studio Guide</span>
            <h3 class="guide-title">Hướng dẫn nhanh</h3>
          </div>

          <div class="guide-tips-list">
            <div v-for="(tip, i) in tips" :key="i" class="guide-tip-item">
              <div class="tip-header-row">
                <div class="tip-icon-box">
                  <component :is="tip.icon" :size="15" />
                </div>
                <strong class="tip-title-text">{{ tip.title }}</strong>
              </div>
              <p class="tip-desc-text">{{ tip.desc }}</p>
            </div>
          </div>

          <div class="guide-quote-box">
            <div class="quote-header">
              <Lightbulb :size="14" />
              <span class="quote-kicker-text">Lời khuyên sư phạm</span>
            </div>
            <p class="quote-paragraph">"Một giáo trình tốt bắt đầu từ sự rõ ràng và lộ trình hợp lý. Hãy chia nhỏ bài học thành các chương mục để học viên không bị quá tải kiến thức."</p>
          </div>
        </div>
      </aside>
    </div>

    <!-- Video Upload Modal (Teleport to Body) -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showUploadModal" class="studio-modal-backdrop" @click.self="closeUploadModal">
          <div class="studio-modal-card is-uploader">
            <div class="modal-header">
              <div>
                <span class="modal-subtitle-tag">Upload Video</span>
                <h3 class="modal-title-text">Tải lên bài giảng</h3>
                <span class="uploader-lesson-meta">{{ uploadingLesson?.title }}</span>
              </div>
              <button class="modal-close-x-btn" type="button" @click="closeUploadModal">✕</button>
            </div>
            
            <div class="uploader-modal-body">
              <VideoUploader
                v-if="uploadingLesson"
                :course-id="courseId"
                :lesson-id="uploadingLesson.id"
                :existing-video-url="uploadingLesson.video_url"
                @uploaded="handleVideoUploaded"
                @error="handleUploadError"
              />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </InstructorWorkspaceShell>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
// Icons removed - using PrimeIcons
import { useAuthStore } from '~/stores/auth'
import StatusBadge from '~/components/common/StatusBadge.vue'
import CurriculumStudio from '~/components/course/CurriculumStudio.vue'
import VideoUploader from '~/components/VideoUploader.vue'
import InstructorWorkspaceShell from '~/components/dashboard/InstructorWorkspaceShell.vue'

definePageMeta({ layout: 'instructor', middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)
const course = ref<any>(null)
const studioRef = ref<any>(null)

const showUploadModal = ref(false)
const uploadingLesson = ref<any>(null)
const submitting = ref(false)

const tips = [
  { icon: PlayCircle, title: 'Video Preview', desc: 'Chọn ít nhất 1-2 bài học miễn phí để học viên dễ dàng xem thử trước khi quyết định đăng ký khóa học.' },
  { icon: Zap, title: 'Xử lý Media', desc: 'Hệ thống tự động chuyển mã, nén và tối ưu hóa video sau khi tải lên để đảm bảo tốc độ tải mượt mà.' },
  { icon: CheckSquare, title: 'Phân chia bài giảng', desc: 'Sắp xếp nội dung một cách khoa học theo cấu trúc chương mục tăng tỷ lệ hoàn thành học tập.' },
]

const loadCourse = async () => {
  try {
    course.value = await $fetch(`/api/courses/${courseId}`, { headers: { Authorization: `Bearer ${auth.token}` } })
  } catch {
    course.value = await $fetch(`/api/instructor/courses/${courseId}`, { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => null)
  }
}

onMounted(loadCourse)

function handleUploadTrigger(lesson: any) {
  uploadingLesson.value = lesson
  showUploadModal.value = true
}

async function handleVideoUploaded() {
  closeUploadModal()
  await studioRef.value?.loadSections?.()
}

function handleUploadError(error: string) {
  console.error('Upload Error:', error)
}

function closeUploadModal() {
  showUploadModal.value = false
  uploadingLesson.value = null
}

function previewCourse() {
  window.open(`/courses/${courseId}`, '_blank')
}

async function submitForReview() {
  if (!confirm('Gửi khóa học này cho ban biên tập EduPress để duyệt xuất bản?')) return
  submitting.value = true
  try {
    const res = await $fetch<any>(`/api/courses/${courseId}/publish`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    course.value = res.course
    alert('Gửi duyệt thành công! Vui lòng đợi kết quả kiểm duyệt từ Admin.')
  } catch (error: any) {
    alert(error?.data?.message || 'Có lỗi xảy ra khi gửi yêu cầu kiểm duyệt.')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.curriculum-workspace-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 24px;
  align-items: start;
}

@media (min-width: 1280px) {
  .curriculum-workspace-grid {
    grid-template-columns: minmax(0, 1fr) 360px;
  }
}

/* Sidebar styling */
.curriculum-guide-sidebar {
  position: sticky;
  top: 24px;
}

.studio-guide-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  box-shadow: var(--shadow-sm);
}

.guide-header {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.guide-kicker {
  font-size: 0.68rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--green);
}

.guide-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 850;
  color: var(--text);
  letter-spacing: -0.02em;
}

.guide-tips-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.guide-tip-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border-radius: 12px;
  background: var(--surface-strong);
  border: 1px solid var(--line);
}

.tip-header-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tip-icon-box {
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: var(--green-soft);
  color: var(--green);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.tip-title-text {
  font-size: 0.82rem;
  font-weight: 750;
  color: var(--text);
}

.tip-desc-text {
  margin: 0;
  font-size: 0.76rem;
  color: var(--muted);
  line-height: 1.5;
  font-weight: 500;
}

/* Quote box styling */
.guide-quote-box {
  padding: 16px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(29, 158, 117, 0.04), rgba(29, 158, 117, 0.01));
  border: 1px solid rgba(29, 158, 117, 0.12);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.quote-header {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--green);
}

.quote-kicker-text {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.quote-paragraph {
  margin: 0;
  font-size: 0.78rem;
  color: var(--text-secondary);
  line-height: 1.6;
  font-style: italic;
  font-weight: 500;
}

/* Topbar Buttons */
.studio-topbar-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  border-radius: 10px;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
  text-decoration: none;
  border: none;
}

.studio-topbar-btn.is-secondary {
  border: 1px solid var(--line);
  background: var(--surface-strong);
  color: var(--text-secondary);
}

.studio-topbar-btn.is-secondary:hover {
  background: var(--surface);
  color: var(--text);
}

.studio-topbar-btn.is-primary {
  background: var(--green);
  color: #fff;
  box-shadow: 0 4px 12px rgba(29, 158, 117, 0.15);
}

.studio-topbar-btn.is-primary:hover {
  background: var(--green-deep);
  box-shadow: 0 6px 16px rgba(29, 158, 117, 0.25);
}

/* Upload Dialog Modal specific styles */
.studio-modal-card.is-uploader {
  max-width: 600px;
}

.uploader-lesson-meta {
  font-size: 0.76rem;
  color: var(--muted);
  display: block;
  margin-top: 4px;
  font-weight: 600;
}

.uploader-modal-body {
  padding: 24px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
