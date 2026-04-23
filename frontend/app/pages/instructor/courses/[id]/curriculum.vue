<template>
  <NuxtLayout name="instructor">
    <div class="space-y-10 pb-20">
      <!-- High-End Page Header -->
      <div class="glass-header rounded-[2.5rem] p-8 shadow-sm border border-surface-dim bg-surface-lowest flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8 overflow-hidden relative group">
        <!-- Background shimmer -->
        <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 pointer-events-none"></div>

        <div class="flex items-center gap-6 relative z-10">
          <div class="w-16 h-16 rounded-[2rem] bg-surface-high flex items-center justify-center text-primary shadow-inner rotate-3">
            <span class="material-symbols-outlined text-[32px]">editor_choice</span>
          </div>
          <div>
            <h1 class="font-headline font-bold text-3xl tracking-tight text-on-surface mb-2">{{ course?.title || 'Studio Giáo trình' }}</h1>
            <div class="flex flex-wrap items-center gap-4">
              <div class="flex items-center gap-2 px-3 py-1 bg-surface-low rounded-full text-xs font-bold text-on-surface-variant border border-surface-dim/30">
                <span class="material-symbols-outlined text-[14px]">history_edu</span>
                Bản nháp Giáo trình
              </div>
              <StatusBadge :value="course?.status || 'draft'" v-if="course" />
            </div>
          </div>
        </div>
        
        <div class="flex items-center gap-3 w-full lg:w-auto relative z-10">
          <NuxtLink to="/instructor/courses" class="px-5 py-3 text-sm font-bold text-on-surface hover:bg-surface-low rounded-xl transition-all border border-surface-dim/40">
            Quay lại
          </NuxtLink>
          <button @click="previewCourse" class="px-5 py-3 text-sm font-bold text-on-surface-variant hover:bg-surface-low rounded-xl transition-all border border-surface-dim/40 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">visibility</span> Xem trước
          </button>
          <div class="h-8 w-[1px] bg-surface-dim/30 mx-2"></div>
          <button v-if="course?.status === 'draft' || course?.status === 'rejected'" :disabled="submitting" @click="submitForReview" class="cta-gradient text-white px-8 py-3 rounded-xl text-sm font-bold shadow-xl transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2 hover:shadow-primary/30">
            <span class="material-symbols-outlined text-[18px]">rocket_launch</span> 
            {{ submitting ? 'Đang gửi...' : 'Gửi kiểm duyệt' }}
          </button>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-12 gap-10">
        
        <!-- Left: The Studio Orchestrator -->
        <div class="xl:col-span-8">
          <CurriculumStudio 
            ref="studioRef"
            :course-id="courseId" 
            @upload-video="handleUploadTrigger"
          />
        </div>

        <!-- Right: Publishing Guide & Assets -->
        <div class="xl:col-span-4 space-y-8">
          <div class="bg-surface-lowest rounded-[2.5rem] p-8 shadow-sm border border-surface-dim sticky top-24">
            <h3 class="font-headline font-bold text-2xl mb-8 text-on-surface flex items-center gap-3">
              <span class="material-symbols-outlined text-amber-500">lightbulb</span>
              Studio Guide
            </h3>
            
            <div class="space-y-6">
              <div v-for="(tip, i) in tips" :key="i" class="flex gap-5 items-start p-6 bg-surface-low/40 rounded-3xl border border-surface-dim/10 hover:border-primary/20 transition-all duration-300">
                <div class="w-10 h-10 rounded-2xl bg-white flex items-center justify-center text-primary shadow-sm shrink-0">
                  <span class="material-symbols-outlined text-sm">{{ tip.icon }}</span>
                </div>
                <div>
                  <h4 class="text-sm font-bold text-on-surface">{{ tip.title }}</h4>
                  <p class="text-[12px] text-on-surface-variant mt-1.5 leading-relaxed">{{ tip.desc }}</p>
                </div>
              </div>
            </div>

            <div class="mt-10 p-6 bg-gradient-to-br from-primary/5 to-secondary/5 rounded-3xl border border-primary/10">
              <p class="text-[11px] font-bold text-primary uppercase tracking-widest mb-2">Lời khuyên từ EduPress</p>
              <p class="text-xs text-on-surface-variant leading-relaxed italic">"Một giáo trình tốt bắt đầu từ sự rõ ràng. Hãy chia nhỏ nội dung vào các Chương để học viên không bị ngợp."</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Video Upload Modal -->
      <Teleport to="body">
        <div v-if="showUploadModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-md" @click.self="closeUploadModal">
          <div class="w-full max-w-2xl rounded-[2.5rem] bg-surface-lowest p-8 shadow-2xl modal-bounce border border-white/20">
            <div class="mb-8 flex items-center justify-between border-b border-surface-dim/30 pb-6">
              <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary">
                  <span class="material-symbols-outlined">video_library</span>
                </div>
                <div>
                  <h3 class="font-headline text-2xl font-bold text-on-surface">Tải lên Bài giảng</h3>
                  <p class="text-sm font-medium text-on-surface-variant truncate max-w-[300px]">{{ uploadingLesson?.title }}</p>
                </div>
              </div>
              <button class="text-outline hover:bg-surface-low p-2 rounded-full transition-colors" @click="closeUploadModal">
                <span class="material-symbols-outlined text-[20px]">close</span>
              </button>
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
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import StatusBadge from '~/components/common/StatusBadge.vue'
import CurriculumStudio from '~/components/course/CurriculumStudio.vue'
import VideoUploader from '~/components/VideoUploader.vue'

definePageMeta({ middleware: 'instructor' })

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
  { icon: 'checklist', title: 'Danh mục', desc: 'Phân chia bài học vào các chương (Section) một cách logic giúp tỷ lệ hoàn thành cao hơn.' }
]

const loadCourse = async () => { 
  try { 
    course.value = await $fetch(`/api/courses/${courseId}`, { headers: { Authorization: `Bearer ${auth.token}` } })
  } catch (error) { 
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
      headers: { Authorization: `Bearer ${auth.token}` } 
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
html { scroll-behavior: smooth; }

.modal-bounce {
  animation: modalBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes modalBounce {
  0% { opacity: 0; transform: scale(0.9) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}

.glass-header {
  @apply relative overflow-hidden;
}

.glass-header::after {
  content: '';
  @apply absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent pointer-events-none;
}
</style>
