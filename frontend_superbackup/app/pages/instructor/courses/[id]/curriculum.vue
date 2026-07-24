<template>
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Khóa học &bull; Giáo trình</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">{{ course?.title || 'Studio Giáo trình' }}</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý cấu trúc chương học, bài giảng và tài nguyên của khóa học.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <StatusBadge v-if="course" :value="course.status || 'draft'" />
        
        <NuxtLink to="/instructor/courses" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors">
          <i class="pi pi-arrow-left text-xs" />
          <span>Quay lại</span>
        </NuxtLink>
        
        <button class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors" @click="previewCourse">
          <i class="pi pi-eye text-xs" />
          <span>Xem trước</span>
        </button>
        
        <button
          v-if="course?.status === 'draft' || course?.status === 'rejected'"
          :disabled="submitting"
          class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50"
          @click="submitForReview"
        >
          <i class="pi pi-send text-xs" />
          <span>{{ submitting ? 'Đang gửi...' : 'Gửi kiểm duyệt' }}</span>
        </button>
      </div>
    </div>

    <!-- Main Content Layout Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-6 items-start">
      <!-- Left side: Curriculum Studio Workspace -->
      <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm p-5">
        <CurriculumStudio
          ref="studioRef"
          :course-id="courseId"
          @upload-video="handleUploadTrigger"
        />
      </div>

      <!-- Right side: Studio Guide Sidebar -->
      <aside class="sticky top-6 flex flex-col gap-4">
        <div class="bg-[var(--surface-strong)] border border-[var(--line)] rounded-2xl p-5 shadow-sm flex flex-col gap-4">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#1d9e75]">Studio Guide</span>
            <h3 class="text-sm font-bold text-[var(--text)] mt-1">Hướng dẫn nhanh</h3>
          </div>

          <div class="flex flex-col gap-3">
            <div v-for="(tip, i) in tips" :key="i" class="flex flex-col gap-1.5 p-3 rounded-xl bg-[var(--surface)] border border-[var(--line)]">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded bg-emerald-50 text-[#1d9e75] flex items-center justify-center flex-shrink-0">
                  <i :class="`pi pi-${tip.icon === 'play-circle' ? 'play' : tip.icon === 'bolt' ? 'bolt' : 'check'}`" class="text-xs" />
                </div>
                <strong class="text-xs font-bold text-[var(--text)]">{{ tip.title }}</strong>
              </div>
              <p class="text-[10px] text-[var(--muted)] leading-relaxed">{{ tip.desc }}</p>
            </div>
          </div>

          <div class="p-4 rounded-xl bg-emerald-50/50 border border-emerald-100/50 flex flex-col gap-2">
            <div class="flex items-center gap-1.5 text-[#1d9e75]">
              <i class="pi pi-lightbulb text-xs" />
              <span class="text-[10px] font-bold uppercase tracking-wider">Lời khuyên sư phạm</span>
            </div>
            <p class="text-xs text-[var(--text-secondary)] leading-relaxed italic">"Một giáo trình tốt bắt đầu từ sự rõ ràng và lộ trình hợp lý. Hãy chia nhỏ bài học thành các chương mục để học viên không bị quá tải kiến thức."</p>
          </div>
        </div>
      </aside>
    </div>

    <!-- Video Upload Modal (Teleport to Body) -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showUploadModal" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-[999]" @click.self="closeUploadModal">
          <div class="bg-white border border-[var(--line)] rounded-2xl w-full max-w-lg shadow-xl overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-[var(--line)] bg-[var(--surface)] flex justify-between items-center">
              <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Upload Video</span>
                <h3 class="text-sm font-bold text-[var(--text)] mt-0.5">Tải lên bài giảng</h3>
                <span class="text-[10px] text-[var(--muted)] font-semibold block mt-1">{{ uploadingLesson?.title }}</span>
              </div>
              <button class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-[var(--line)] text-[var(--muted)]" type="button" @click="closeUploadModal">✕</button>
            </div>
            
            <div class="p-6">
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
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
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
  { icon: 'play-circle', title: 'Video Preview', desc: 'Chọn ít nhất 1-2 bài học miễn phí để học viên dễ dàng xem thử trước khi quyết định đăng ký khóa học.' },
  { icon: 'bolt', title: 'Xử lý Media', desc: 'Hệ thống tự động chuyển mã, nén và tối ưu hóa video sau khi tải lên để đảm bảo tốc độ tải mượt mà.' },
  { icon: 'check-square', title: 'Phân chia bài giảng', desc: 'Sắp xếp nội dung một cách khoa học theo cấu trúc chương mục tăng tỷ lệ hoàn thành học tập.' },
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
/* Scoped styles kept minimal */
</style>
