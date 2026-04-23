<template>
  <div class="min-h-screen bg-background text-on-background flex flex-col">
    <!-- Mobile Sidebar Toggle -->
    <button @click="isSidebarCollapsed = !isSidebarCollapsed" class="lg:hidden fixed bottom-6 right-6 z-50 w-14 h-14 bg-primary text-white rounded-full shadow-lg flex items-center justify-center">
      <span class="material-symbols-outlined">{{ isSidebarCollapsed ? 'menu_book' : 'close' }}</span>
    </button>

    <div class="flex flex-1">
      <!-- Curriculum Sidebar -->
      <aside :class="[
        'w-80 bg-surface-lowest border-r border-surface-dim flex flex-col z-40 transition-transform duration-300 fixed lg:sticky top-16 h-[calc(100vh-4rem)]',
        isSidebarCollapsed ? '-translate-x-full lg:translate-x-0' : 'translate-x-0'
      ]">
        <div class="p-6 flex flex-col gap-1 border-b border-surface-dim">
          <h2 class="text-xs font-bold tracking-widest text-outline uppercase mb-2">Tiến độ khóa học</h2>
          <div class="w-full bg-surface-high h-1.5 rounded-full overflow-hidden">
            <div class="progress-gradient h-full transition-all duration-500" :style="{ width: `${progress?.percent || 0}%` }"></div>
          </div>
          <span class="text-xs text-on-surface-variant mt-2 font-medium">{{ progress?.percent || 0 }}% Hoàn thành • {{ completedSet.size }}/{{ lessons.length }} Bài</span>
        </div>
        
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
          <NuxtLink 
            v-for="(l, idx) in lessons" :key="l.id" 
            :to="`/learn/${courseId}/${l.id}`" 
            :class="[
              'flex items-center gap-3 p-3 rounded-xl font-medium transition-all group',
              l.id === currentLessonId ? 'bg-surface-lowest shadow-sm border-l-4 border-primary' : 'hover:bg-surface-low text-on-surface-variant border-l-4 border-transparent'
            ]"
          >
            <span class="material-symbols-outlined" :class="completedSet.has(l.id) ? 'text-secondary' : (l.id === currentLessonId ? 'text-primary' : 'text-outline')" :style="l.id === currentLessonId ? 'font-variation-settings: \'FILL\' 1;' : ''">
              {{ completedSet.has(l.id) ? 'check_circle' : getIconForType(l.type) }}
            </span>
            <div class="min-w-0 flex-1">
              <p :class="['text-sm line-clamp-2', l.id === currentLessonId ? 'font-bold text-on-surface' : '']">{{ l.title }}</p>
              <p v-if="l.duration" class="text-[10px] text-outline mt-0.5">{{ formatDuration(l.duration) }}</p>
            </div>
          </NuxtLink>
        </nav>
      </aside>

      <!-- Main Workspace Content -->
      <main class="flex-1 flex flex-col min-w-0">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-10 w-full h-full flex flex-col">
          
          <!-- Breadcrumbs -->
          <nav class="flex items-center gap-2 text-xs font-medium text-on-surface-variant mb-6 hidden sm:flex">
            <NuxtLink :to="`/courses/${courseId}`" class="hover:text-primary transition-colors">{{ course?.title || 'Khóa học' }}</NuxtLink>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-primary truncate max-w-[200px]">{{ lesson?.title || 'Đang tải...' }}</span>
          </nav>

          <!-- Core Content / Video Player Area -->
          <div v-if="!lesson" class="flex-1 flex flex-col items-center justify-center text-outline">
            <span class="material-symbols-outlined text-4xl mb-4 animate-spin">refresh</span>
            Đang tải không gian học tập...
          </div>
          <div v-else class="space-y-10 flex-1">
            
            <!-- Player Section -->
            <section class="relative rounded-2xl md:rounded-[2rem] overflow-hidden shadow-ambient bg-on-surface aspect-video group border border-surface-dim">
              <template v-if="lesson.type === 'video'">
                <VideoPlayer v-if="lesson.video_url" :key="`vid-${lesson.id}`" :course-id="courseId" :lesson-id="lesson.id" @progress="onPlayerProgress" @ended="onPlayerEnded" />
                <div v-else class="flex h-full items-center justify-center text-outline bg-on-surface/90 absolute inset-0">Video đang xử lý hoặc chưa có.</div>
              </template>
              <template v-else-if="lesson.type === 'scorm' || lesson.type === 'h5p'">
                <ScormPlayer :package-data="lesson.scorm_package" class="w-full h-full bg-surface-lowest" />
              </template>
              <template v-else-if="lesson.type === 'virtual_class'">
                <VirtualClassView v-if="lesson.virtual_class" :data="lesson.virtual_class" class="w-full h-full bg-surface-lowest" />
                <div v-else class="flex h-full items-center justify-center text-outline bg-on-surface/90 absolute inset-0">Chưa có thông tin lớp học trực tuyến.</div>
              </template>
              <template v-else-if="lesson.type === 'assignment'">
                <AssignmentView v-if="lesson.assignment" :data="lesson.assignment" :course-id="courseId" :lesson-id="lesson.id" class="w-full h-full bg-surface-lowest p-6 overflow-y-auto" />
                <div v-else class="flex h-full items-center justify-center text-outline bg-on-surface/90 absolute inset-0">Chưa có thông tin bài tập.</div>
              </template>
              <template v-else>
                <div class="absolute inset-0 bg-surface-low p-8 flex flex-col justify-center items-center text-center">
                  <span class="material-symbols-outlined text-6xl text-primary/40 mb-4">{{ getIconForType(lesson.type) }}</span>
                  <h2 class="text-2xl font-bold font-headline text-on-surface">{{ lesson.title }}</h2>
                </div>
              </template>
            </section>

            <!-- Editorial Layout Content -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-12 pb-10">
              
              <!-- Primary Left Content -->
              <div class="col-span-1 lg:col-span-8 space-y-8">
                <div>
                  <h2 class="text-3xl font-bold font-headline text-on-surface">{{ lesson.title }}</h2>
                  <div class="flex items-center gap-4 mt-3">
                    <span class="bg-surface-high text-xs font-bold text-on-surface uppercase tracking-widest px-2 py-1 rounded">{{ getTypeText(lesson.type) }}</span>
                    <span v-if="lesson.duration" class="text-sm font-medium text-on-surface-variant">{{ formatDuration(lesson.duration) }}</span>
                  </div>
                </div>

                <div class="flex items-center gap-6 border-b border-surface-dim/30">
                  <button 
                    v-for="t in tabs" :key="t.id"
                    :class="[
                      'pb-3 text-sm font-bold flex items-center gap-2 border-b-2 transition-all relative top-[1px]',
                      activeTab === t.id ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'
                    ]"
                    @click="activeTab = t.id"
                  >
                    <span class="material-symbols-outlined text-[18px]">{{ t.iconStr }}</span>
                    {{ t.label }}
                  </button>
                </div>

                <!-- Tab Panes -->
                <div class="min-h-[200px]">
                  <div v-if="activeTab === 'overview'" class="prose prose-slate max-w-none prose-p:leading-relaxed prose-headings:font-headline prose-a:text-primary">
                    <div v-if="lesson.description" v-html="lesson.description" />
                    <div v-else class="text-outline italic">Không có mô tả chi tiết cho bài học này.</div>
                  </div>
                  <div v-else-if="activeTab === 'quiz'">
                    <StudentQuiz :course-id="courseId" :lesson-id="lesson.id" />
                  </div>
                  <div v-else-if="activeTab === 'files'">
                    <StudentAttachments :course-id="courseId" :lesson-id="lesson.id" />
                  </div>
                  <div v-else-if="activeTab === 'qa'">
                    <QaSection :course-id="courseId" :lesson-id="lesson.id" />
                  </div>
                </div>

                <!-- Footer Navigation Controls -->
                <div class="flex items-center justify-between pt-8 border-t border-surface-dim/30">
                  <button 
                    @click="goTo(prevLesson?.id)" 
                    :disabled="!prevLesson"
                    :class="[
                      'flex items-center gap-3 px-5 py-3 rounded-xl font-bold text-sm transition-all',
                      prevLesson ? 'bg-surface-high text-on-surface hover:bg-surface-highest' : 'bg-surface-low text-outline cursor-not-allowed opacity-50'
                    ]"
                  >
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Bài trước
                  </button>
                  <span class="text-sm font-bold text-outline uppercase tracking-widest hidden sm:inline">Bài {{ currentIndex + 1 }} / {{ lessons.length }}</span>
                  <button 
                    @click="goTo(nextLesson?.id)" 
                    :disabled="!nextLesson"
                    :class="[
                      'flex items-center gap-3 px-5 py-3 rounded-xl font-bold text-sm transition-all',
                      nextLesson ? 'cta-gradient text-white shadow-md hover:shadow-lg hover:-translate-y-0.5' : 'bg-surface-low text-outline cursor-not-allowed opacity-50'
                    ]"
                  >
                    Bài tiếp
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                  </button>
                </div>
              </div>

              <!-- Secondary Right Sidebar -->
              <div class="col-span-1 lg:col-span-4 space-y-6">
                <!-- Instructor Brief -->
                <div class="p-6 rounded-2xl bg-surface-lowest border border-surface-dim shadow-sm">
                  <h4 class="text-[10px] font-bold text-outline uppercase tracking-widest mb-4">Giảng viên</h4>
                  <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-lg">
                      {{ course?.instructor?.name?.charAt(0) || 'I' }}
                    </div>
                    <div>
                      <span class="block text-sm font-bold text-on-surface">{{ course?.instructor?.name || 'Giảng viên' }}</span>
                      <span class="text-[10px] text-outline">Chuyên gia hệ thống</span>
                    </div>
                  </div>
                  <button @click="activeTab = 'qa'" class="w-full mt-2 text-xs font-bold text-primary hover:underline flex items-center justify-center gap-1 bg-surface-high py-2 rounded-lg">
                    Hỏi đáp Giảng viên <span class="material-symbols-outlined text-[14px]">chat</span>
                  </button>
                </div>

                <div class="p-6 rounded-2xl bg-surface-lowest border border-surface-dim shadow-sm">
                  <h4 class="text-[10px] font-bold text-outline uppercase tracking-widest mb-4">Tương tác bài học</h4>
                  <p class="text-sm text-on-surface-variant leading-relaxed">
                    Mở tab <strong class="text-on-surface">Hỏi đáp</strong> để đặt câu hỏi cho giảng viên, trao đổi về nội dung đang học và xem các phản hồi ngay trong bài học này.
                  </p>
                  <button
                    @click="activeTab = 'qa'"
                    class="mt-4 w-full rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-bold text-primary transition-all hover:bg-primary hover:text-white"
                  >
                    Mở khu vực hỏi đáp
                  </button>
                </div>
              </div>

            </section>
          </div>

        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QaSection from '../../../components/learn/QaSection.vue'
import { useCourseStore } from '~/stores/course'

interface LessonExtended {
  id: number
  course_id: number
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
}

const route = useRoute()
const router = useRouter()
const courseStore = useCourseStore()

const courseId = Number(route.params.courseId)
const currentLessonId = computed(() => Number(route.params.lessonId))

const course = ref(courseStore.currentCourse)
const lesson = ref<LessonExtended | null>(null)
const lessons = ref<LessonExtended[]>([])
const progress = ref<any>(null)
const activeTab = ref('overview')
const isSidebarCollapsed = ref(true)

const tabs = [
  { id: 'overview', label: 'Tổng quan', iconStr: 'info' },
  { id: 'quiz', label: 'Kiểm tra', iconStr: 'quiz' },
  { id: 'files', label: 'Tài liệu', iconStr: 'attach_file' },
  { id: 'qa', label: 'Hỏi đáp', iconStr: 'forum' }
]

const completedSet = computed(() => {
  const set = new Set<number>()
  progress.value?.lessons?.forEach((l: any) => { if (l.completed) set.add(l.id) })
  return set
})

const currentIndex = computed(() => lessons.value.findIndex(l => l.id === currentLessonId.value))
const prevLesson = computed(() => currentIndex.value > 0 ? lessons.value[currentIndex.value - 1] : null)
const nextLesson = computed(() => currentIndex.value < lessons.value.length - 1 ? lessons.value[currentIndex.value + 1] : null)

function getTypeText(type: string) {
  const map: Record<string, string> = {
    video: 'Video',
    scorm: 'SCORM',
    h5p: 'H5P',
    virtual_class: 'Online',
    offline: 'Offline',
    assignment: 'Bài tập',
    quiz: 'Kiểm tra'
  }
  return map[type] || 'Tài liệu'
}

function getIconForType(type: string) {
  const map: Record<string, string> = {
    video: 'play_circle',
    scorm: 'subscriptions',
    h5p: 'extension',
    virtual_class: 'video_camera_front',
    offline: 'groups',
    assignment: 'assignment',
    quiz: 'quiz'
  }
  return map[type] || 'description'
}

function formatDuration(seconds: number) {
  if (!seconds) return ''
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

async function loadLesson() {
  try {
    lesson.value = await courseStore.fetchLesson(courseId, currentLessonId.value) as LessonExtended
  } catch {
    lesson.value = null
  }
}

const onPlayerProgress = async () => {
    progress.value = await courseStore.fetchCourseProgress(courseId)
}

const onPlayerEnded = async () => {
    progress.value = await courseStore.fetchCourseProgress(courseId)
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
  lessons.value = (data as any[] || []).filter(l => !l.locked)
  
  try {
    progress.value = await courseStore.fetchCourseProgress(courseId)
  } catch { }

  await loadLesson()
}

watch(currentLessonId, async () => {
  await loadLesson()
})

onMounted(init)
</script>

<style scoped>
html {
  scroll-behavior: smooth;
}
</style>
