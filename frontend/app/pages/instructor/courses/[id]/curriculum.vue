<template>
  <section class="crud-page">
    <!-- Page Header -->
    <header class="crud-page-header dashboard-card">
      <div>
        <p class="section-kicker">Giảng viên / Studio Giáo trình</p>
        <h2>{{ course?.title || 'Studio Giáo trình' }}</h2>
        <p>Quản lý cấu trúc chương học, bài giảng và tài nguyên của khóa học.</p>
        <div style="display:flex; align-items:center; gap:12px; margin-top:10px;">
          <StatusBadge :value="course?.status || 'draft'" v-if="course" />
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:10px; flex-shrink:0; flex-wrap:wrap;">
        <NuxtLink to="/instructor/courses" class="crud-secondary-btn">Quay lại</NuxtLink>
        <button
          class="crud-secondary-btn"
          style="display:flex; align-items:center; gap:6px;"
          @click="previewCourse"
        >
          <span class="material-symbols-outlined" style="font-size:18px;">visibility</span>
          Xem trước
        </button>
        <button
          v-if="course?.status === 'draft' || course?.status === 'rejected'"
          :disabled="submitting"
          class="crud-primary-btn"
          style="display:flex; align-items:center; gap:6px;"
          @click="submitForReview"
        >
          <span class="material-symbols-outlined" style="font-size:18px;">rocket_launch</span>
          {{ submitting ? 'Đang gửi...' : 'Gửi kiểm duyệt' }}
        </button>
      </div>
    </header>

    <!-- Main Content Grid -->
    <div style="display:grid; grid-template-columns:1fr; gap:20px;">
      <div style="display:grid; grid-template-columns:minmax(0,1fr); gap:20px;" class="curriculum-content-wrap">
        <!-- Studio -->
        <div>
          <CurriculumStudio
            ref="studioRef"
            :course-id="courseId"
            @upload-video="handleUploadTrigger"
          />
        </div>

        <!-- Guide Sidebar -->
        <div>
          <div class="dashboard-card" style="position:sticky; top:24px; display:grid; gap:18px;">
            <div>
              <p class="section-kicker">Studio Guide</p>
              <h3 style="margin:4px 0 0; font-size:1.2rem; letter-spacing:-0.03em;">Hướng dẫn nhanh</h3>
            </div>

            <div style="display:grid; gap:12px;">
              <div v-for="(tip, i) in tips" :key="i" class="week-one-item week-one-item is-static" style="flex-direction:column; align-items:flex-start; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:32px;height:32px;border-radius:10px;background:rgba(var(--green-rgb),0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <span class="material-symbols-outlined" style="font-size:16px;color:var(--green-deep);">{{ tip.icon }}</span>
                  </div>
                  <strong style="font-size:0.88rem;">{{ tip.title }}</strong>
                </div>
                <p style="margin:0;font-size:0.8rem;color:var(--muted);line-height:1.6;">{{ tip.desc }}</p>
              </div>
            </div>

            <div style="padding:16px;background:rgba(var(--green-rgb),0.06);border-radius:16px;border:1px solid rgba(var(--green-rgb),0.12);">
              <p class="sidebar-eyebrow" style="margin:0 0 6px;">Lời khuyên</p>
              <p style="margin:0;font-size:0.8rem;color:var(--muted);line-height:1.7;font-style:italic;">"Một giáo trình tốt bắt đầu từ sự rõ ràng. Hãy chia nhỏ nội dung vào các Chương để học viên không bị ngợp."</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Video Upload Modal -->
    <Teleport to="body">
      <div v-if="showUploadModal" class="crud-modal-backdrop" @click.self="closeUploadModal">
        <div class="crud-modal">
          <div class="crud-modal-head">
            <div>
              <p class="section-kicker">Upload Video</p>
              <h3>Tải lên Bài giảng</h3>
              <p class="crud-meta" style="margin:4px 0 0; display:block;">{{ uploadingLesson?.title }}</p>
            </div>
            <button class="topbar-ghost" type="button" @click="closeUploadModal">✕</button>
          </div>
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
    </Teleport>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import StatusBadge from '~/components/common/StatusBadge.vue'
import CurriculumStudio from '~/components/course/CurriculumStudio.vue'
import VideoUploader from '~/components/VideoUploader.vue'

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
  { icon: 'play_lesson', title: 'Video Preview', desc: 'Chọn ít nhất 1-2 bài học miễn phí để học viên dễ dàng quyết định mua khóa học.' },
  { icon: 'speed', title: 'Xử lý Media', desc: 'Hệ thống sẽ tự động nén và tối ưu hóa video sau khi tải lên. Vui lòng đợi trong giây lát.' },
  { icon: 'checklist', title: 'Danh mục', desc: 'Phân chia bài học vào các chương (Section) một cách logic giúp tỷ lệ hoàn thành cao hơn.' },
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
    alert('Gửi duyệt thành công! Vui lòng đợi kết quả từ Admin.')
  } catch (error: any) {
    alert(error?.data?.message || 'Có lỗi khi gửi duyệt.')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.curriculum-content-wrap {
  grid-template-columns: minmax(0, 1fr);
}
@media (min-width: 1280px) {
  .curriculum-content-wrap {
    grid-template-columns: minmax(0, 2fr) 360px;
  }
}
</style>
