<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { normalizeRole, useAuthTokenCookie, useAuthUserCookie } from '~/composables/useAuthSession'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

definePageMeta({ layout: 'default' })

interface SectionItem {
  id: number; title: string; position: number
  lessons?: Array<{ id: number; title: string; duration: number; is_preview?: boolean }>
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const courseStore = useCourseStore()
const authUser = useAuthUserCookie()
const token = useAuthTokenCookie()

const course = ref<any>(null)
const lessons = ref<any[]>([])
const sections = ref<SectionItem[]>([])
const openSections = ref<number[]>([])
const reviews = ref<any[]>([])
const loading = ref(true)
const enrolling = ref(false)

const courseId = Number(route.params.id)
const normalizedRole = computed(() => normalizeRole(authUser.value?.role))
const isAdmin = computed(() => normalizedRole.value === 'admin')
const isInstructor = computed(() => normalizedRole.value === 'instructor')
const canPrivilegedEnter = computed(() => isAdmin.value || (isInstructor.value && Number(course.value?.user_id || course.value?.instructor?.id || 0) === Number(authUser.value?.id || 0)))
const isEnrolled = computed(() => course.value?.is_enrolled || courseStore.isEnrolled(courseId))
const previewLessonId = computed(() => lessons.value.find((l: any) => l.is_preview)?.id || 0)
const canEnterLearning = computed(() => canPrivilegedEnter.value || isEnrolled.value)
const isFree = computed(() => !Number(course.value?.price || 0))

const primaryCtaLabel = computed(() => {
  if (canPrivilegedEnter.value) return 'Vào học ngay'
  if (isEnrolled.value) return 'Tiếp tục học'
  if (!auth.isLoggedIn) return 'Đăng nhập để bắt đầu'
  return isFree.value ? 'Đăng ký miễn phí' : 'Mua khóa học'
})

const totalDuration = computed(() => {
  const secs = lessons.value.reduce((sum: number, l: any) => sum + (l.duration || 0), 0)
  const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60)
  return h > 0 ? `${h} giờ ${m} phút` : `${m} phút`
})

const totalLessons = computed(() => lessons.value.length)
const totalSections = computed(() => sections.value.length)
const courseExcerpt = computed(() => {
  const text = typeof course.value?.description === 'string' ? course.value.description.trim() : ''
  if (!text) return ''
  return text.length > 180 ? `${text.slice(0, 180).trim()}...` : text
})

const formatPrice = (p: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p || 0)
const formatDuration = (secs: number) => {
  const m = Math.floor(secs / 60), s = secs % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

async function handlePrimaryAction() {
  if (canEnterLearning.value) { router.push(`/learn/${courseId}`); return }
  if (!auth.isLoggedIn) { router.push(`/login?redirect=${encodeURIComponent(`/courses/${courseId}`)}`); return }
  await handleEnrollment()
}

async function handleEnrollment() {
  if (enrolling.value) return
  enrolling.value = true
  try {
    const res = await courseStore.createOrder(courseId)
    if (res.payment_url) window.location.href = res.payment_url
    else if (res.enrolled) { await courseStore.fetchEnrollments(); router.push(`/learn/${courseId}`) }
    else router.push(`/checkout/${courseId}`)
  } catch (e) { console.error('Enrollment error:', e) }
  finally { enrolling.value = false }
}

function toggleSection(id: number) {
  openSections.value = openSections.value.includes(id)
    ? openSections.value.filter(s => s !== id) : [...openSections.value, id]
}
function isSectionOpen(id: number) { return openSections.value.includes(id) }
function goToPreviewLesson(id?: number) { if (id) router.push(`/learn/${courseId}/${id}`) }

onMounted(async () => {
  try {
    const headers = token.value ? { Authorization: `Bearer ${token.value}` } : undefined
    const [c, l, sectionRes] = await Promise.all([
      courseStore.fetchCourse(courseId),
      courseStore.fetchLessons(courseId).catch(() => []),
      useApi<{ data: SectionItem[] }>(`/courses/${courseId}/sections`, { headers }).catch(() => ({ data: [] })),
    ])
    course.value = c; lessons.value = l || []
    sections.value = sectionRes.data || []
    openSections.value = sections.value.slice(0, 2).map(s => s.id)
    reviews.value = c?.latest_reviews || []
    if (auth.isLoggedIn) await courseStore.fetchEnrollments().catch(() => {})
  } finally { loading.value = false }
})
</script>

<template>
  <div class="cd-page">
    <!-- Hero Banner -->
    <section class="cd-hero">
      <div class="cd-hero-inner">
        <div class="cd-hero-text">
          <div class="cd-breadcrumb">
            <NuxtLink to="/courses">Khóa học</NuxtLink>
            <span class="material-symbols-outlined" style="font-size:14px">chevron_right</span>
            <span>{{ typeof course?.category === 'object' ? course?.category?.name : (course?.category || 'Danh mục') }}</span>
          </div>
          <h1 v-if="course">{{ course.title }}</h1>
          <h1 v-else>Đang tải...</h1>
          <p class="cd-hero-desc">{{ courseExcerpt }}</p>
          <div class="cd-hero-meta">
            <div class="cd-meta-item">
              <span class="material-symbols-outlined">person</span>
              {{ course?.instructor?.name || 'Giảng viên' }}
            </div>
            <div class="cd-meta-item">
              <span class="material-symbols-outlined">group</span>
              {{ course?.enrollments_count || 0 }} học viên
            </div>
            <div class="cd-meta-item">
              <span class="material-symbols-outlined">schedule</span>
              {{ totalDuration }}
            </div>
            <div class="cd-meta-item">
              <span class="material-symbols-outlined">play_circle</span>
              {{ totalLessons }} bài học
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <div class="cd-container">
      <div class="cd-grid">
        <!-- Left Column -->
        <div class="cd-main">
          <!-- Course Image -->
          <section class="cd-card cd-thumbnail-card" v-if="course?.thumbnail">
            <img :src="course.thumbnail" :alt="course.title" class="cd-thumbnail">
          </section>

          <!-- What you'll learn -->
          <section class="cd-card">
            <h2 class="cd-card-title">
              <span class="material-symbols-outlined cd-card-icon">school</span>
              Bạn sẽ học được gì?
            </h2>
            <div class="cd-learn-grid">
              <div class="cd-learn-item">
                <span class="material-symbols-outlined cd-check">check_circle</span>
                <span>Nắm vững kiến thức nền tảng và nâng cao</span>
              </div>
              <div class="cd-learn-item">
                <span class="material-symbols-outlined cd-check">check_circle</span>
                <span>Thực hành với {{ totalLessons }} bài học có hướng dẫn</span>
              </div>
              <div class="cd-learn-item">
                <span class="material-symbols-outlined cd-check">check_circle</span>
                <span>Học từ giảng viên {{ course?.instructor?.name || '' }}</span>
              </div>
              <div class="cd-learn-item">
                <span class="material-symbols-outlined cd-check">check_circle</span>
                <span>Truy cập nội dung mọi lúc mọi nơi</span>
              </div>
            </div>
          </section>

          <!-- Course Description -->
          <section class="cd-card" v-if="course?.description">
            <h2 class="cd-card-title">
              <span class="material-symbols-outlined cd-card-icon">description</span>
              Giới thiệu khóa học
            </h2>
            <div class="cd-course-description" v-html="course.description"></div>
          </section>

          <!-- Curriculum -->
          <section class="cd-card">
            <div class="cd-card-header">
              <h2 class="cd-card-title">
                <span class="material-symbols-outlined cd-card-icon">menu_book</span>
                Nội dung khóa học
              </h2>
              <div class="cd-curriculum-stats">
                <span>{{ totalSections }} chương</span>
                <span>•</span>
                <span>{{ totalLessons }} bài học</span>
                <span>•</span>
                <span>{{ totalDuration }}</span>
              </div>
            </div>

            <div v-if="sections.length" class="cd-sections">
              <div v-for="section in sections" :key="section.id" class="cd-section">
                <button class="cd-section-head" @click="toggleSection(section.id)">
                  <div class="cd-section-left">
                    <span class="material-symbols-outlined cd-section-chevron" :class="{ open: isSectionOpen(section.id) }">expand_more</span>
                    <div>
                      <span class="cd-section-label">Phần {{ section.position }}</span>
                      <h3>{{ section.title }}</h3>
                    </div>
                  </div>
                  <span class="cd-section-count">{{ section.lessons?.length || 0 }} bài</span>
                </button>
                <div v-if="isSectionOpen(section.id)" class="cd-section-body">
                  <div v-for="lesson in section.lessons || []" :key="lesson.id" class="cd-lesson">
                    <div class="cd-lesson-left">
                      <span class="material-symbols-outlined" style="font-size:18px;color:var(--outline)">{{ lesson.is_preview ? 'play_circle' : 'lock' }}</span>
                      <span class="cd-lesson-title">{{ lesson.title }}</span>
                      <span v-if="lesson.is_preview" class="cd-preview-tag">Xem trước</span>
                    </div>
                    <div class="cd-lesson-right">
                      <span class="cd-lesson-dur">{{ formatDuration(lesson.duration || 0) }}</span>
                      <button v-if="lesson.is_preview" class="cd-preview-btn" @click.stop="goToPreviewLesson(lesson.id)">Xem thử</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="cd-sections">
              <div v-for="lesson in lessons" :key="lesson.id" class="cd-lesson" style="border-bottom:1px solid var(--surface-dim,#e5e7eb)">
                <div class="cd-lesson-left">
                  <span class="material-symbols-outlined" style="font-size:18px;color:var(--outline)">{{ lesson.is_preview ? 'play_circle' : 'lock' }}</span>
                  <span class="cd-lesson-title">{{ lesson.title }}</span>
                  <span v-if="lesson.is_preview" class="cd-preview-tag">Xem trước</span>
                </div>
                <div class="cd-lesson-right">
                  <span class="cd-lesson-dur">{{ formatDuration(lesson.duration || 0) }}</span>
                  <button v-if="lesson.is_preview" class="cd-preview-btn" @click="goToPreviewLesson(lesson.id)">Xem thử</button>
                </div>
              </div>
            </div>
          </section>

          <!-- Instructor -->
          <section class="cd-card" v-if="course?.instructor">
            <h2 class="cd-card-title">
              <span class="material-symbols-outlined cd-card-icon">person</span>
              Giảng viên
            </h2>
            <div class="cd-instructor">
              <div class="cd-instructor-avatar">
                <img v-if="course.instructor.avatar" :src="course.instructor.avatar" :alt="course.instructor.name">
                <span v-else>{{ course.instructor.name?.charAt(0) }}</span>
              </div>
              <div>
                <h3 class="cd-instructor-name">{{ course.instructor.name }}</h3>
                <p class="cd-instructor-bio">{{ course.instructor.bio || 'Giảng viên tại EduPress' }}</p>
              </div>
            </div>
          </section>

          <!-- Reviews Section -->
          <CourseReviewSection :course-id="courseId" :is-enrolled="isEnrolled" />
        </div>

        <!-- Right Sidebar (Sticky) -->
        <aside class="cd-sidebar">
          <div class="cd-sidebar-card">
            <div class="cd-price-block">
              <span class="cd-price">{{ isFree ? 'Miễn phí' : formatPrice(course?.price) }}</span>
            </div>

            <button class="cd-cta-btn" :disabled="enrolling" @click="handlePrimaryAction">
              <span class="material-symbols-outlined" style="font-size:20px">{{ canEnterLearning ? 'play_arrow' : (isFree ? 'add' : 'shopping_cart') }}</span>
              {{ enrolling ? 'Đang xử lý...' : primaryCtaLabel }}
            </button>

            <NuxtLink v-if="previewLessonId && !canPrivilegedEnter" :to="`/learn/${courseId}/${previewLessonId}`" class="cd-secondary-btn">
              <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
              Xem thử miễn phí
            </NuxtLink>

            <div v-if="canPrivilegedEnter" class="cd-side-note">
              <span class="material-symbols-outlined" style="font-size:16px">shield</span>
              Đang xem với quyền {{ isAdmin ? 'Admin' : 'Giảng viên' }}
            </div>
            <div v-else-if="isEnrolled" class="cd-side-note cd-side-note--success">
              <span class="material-symbols-outlined" style="font-size:16px">check_circle</span>
              Bạn đã ghi danh khóa học này
            </div>

            <div class="cd-side-features">
              <div class="cd-side-feat">
                <span class="material-symbols-outlined">play_circle</span>
                <span>{{ totalLessons }} bài học</span>
              </div>
              <div class="cd-side-feat">
                <span class="material-symbols-outlined">schedule</span>
                <span>{{ totalDuration }} học</span>
              </div>
              <div class="cd-side-feat">
                <span class="material-symbols-outlined">devices</span>
                <span>Học trên mọi thiết bị</span>
              </div>
              <div class="cd-side-feat">
                <span class="material-symbols-outlined">all_inclusive</span>
                <span>Truy cập trọn đời</span>
              </div>
              <div class="cd-side-feat">
                <span class="material-symbols-outlined">workspace_premium</span>
                <span>Chứng chỉ hoàn thành</span>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="cd-loading">
      <div class="cd-spinner"></div>
      <p>Đang tải thông tin khóa học...</p>
    </div>
  </div>
</template>

<style scoped>
/* ── Hero ─────────────────────────────────────── */
.cd-hero {
  background: var(--green);
  color: #fff; padding: 3rem 1rem 2.5rem; position: relative; overflow: hidden;
}
.cd-hero::before { content: none; }
.cd-hero-inner { max-width: 900px; margin: 0 auto; position: relative; z-index: 1; }
.cd-breadcrumb {
  display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: rgba(255,255,255,0.6); margin-bottom: 1rem;
}
.cd-breadcrumb a { color: rgba(255,255,255,0.8); text-decoration: none; }
.cd-breadcrumb a:hover { color: #fff; }
.cd-hero h1 { font-size: 2rem; font-weight: 800; line-height: 1.25; margin: 0 0 0.75rem; }
.cd-hero-desc { font-size: 1rem; line-height: 1.7; color: rgba(255,255,255,0.75); max-width: 700px; margin-bottom: 1.5rem; }
.cd-hero-meta { display: flex; flex-wrap: wrap; gap: 1.25rem; }
.cd-meta-item {
  display: flex; align-items: center; gap: 6px;
  font-size: 0.85rem; color: rgba(255,255,255,0.7);
}
.cd-meta-item .material-symbols-outlined { font-size: 18px; color: rgba(255,255,255,0.85); }

/* ── Layout ───────────────────────────────────── */
.cd-page { min-height: 100vh; background: var(--surface, #f8fafc); }
.cd-container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem 3rem; }
.cd-grid { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; align-items: start; }

/* ── Cards ────────────────────────────────────── */
.cd-card {
  background: var(--surface-lowest, #fff); border-radius: 16px;
  border: 1px solid var(--surface-dim, #e5e7eb); padding: 1.5rem; margin-bottom: 1.5rem;
}
.cd-card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
.cd-card-title {
  display: flex; align-items: center; gap: 10px;
  font-size: 1.2rem; font-weight: 800; margin: 0 0 1.25rem; color: var(--on-surface, #0f172a);
}
.cd-card-icon { font-size: 22px; color: var(--primary, var(--green)); }

/* ── Thumbnail ────────────────────────────────── */
.cd-thumbnail-card { padding: 0; overflow: hidden; }
.cd-thumbnail { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; }

/* ── Learn Grid ───────────────────────────────── */
.cd-learn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.cd-learn-item { display: flex; align-items: flex-start; gap: 10px; font-size: 0.9rem; color: var(--on-surface-variant, #475569); }
.cd-check { font-size: 20px; color: var(--green); flex-shrink: 0; margin-top: 1px; }

/* ── Curriculum ───────────────────────────────── */
.cd-curriculum-stats { display: flex; gap: 8px; font-size: 0.8rem; color: var(--outline, #94a3b8); }
.cd-sections { display: flex; flex-direction: column; }
.cd-section { border: 1px solid var(--surface-dim, #e5e7eb); border-radius: 12px; margin-bottom: 0.5rem; overflow: hidden; }
.cd-section-head {
  width: 100%; display: flex; justify-content: space-between; align-items: center;
  padding: 1rem 1.25rem; background: var(--surface-low, #f1f5f9); border: none; cursor: pointer;
  text-align: left; transition: background 0.15s;
}
.cd-section-head:hover { background: var(--surface-high, #e2e8f0); }
.cd-section-left { display: flex; align-items: center; gap: 10px; }
.cd-section-chevron { font-size: 22px; color: var(--outline); transition: transform 0.2s; }
.cd-section-chevron.open { transform: rotate(180deg); }
.cd-section-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary, var(--green)); font-weight: 700; }
.cd-section-head h3 { font-size: 0.95rem; font-weight: 700; margin: 2px 0 0; color: var(--on-surface, #0f172a); }
.cd-section-count { font-size: 0.8rem; color: var(--outline, #94a3b8); font-weight: 600; white-space: nowrap; }
.cd-section-body { padding: 0.25rem 0; }

.cd-lesson {
  display: flex; justify-content: space-between; align-items: center;
  padding: 0.75rem 1.25rem; transition: background 0.1s;
}
.cd-lesson:hover { background: var(--surface-low, #f8fafc); }
.cd-lesson-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.cd-lesson-title { font-size: 0.88rem; color: var(--on-surface, #0f172a); }
.cd-preview-tag {
  font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 99px;
  background: rgba(34,197,94,0.1); color: var(--green); white-space: nowrap;
}
.cd-lesson-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.cd-lesson-dur { font-size: 0.8rem; color: var(--outline, #94a3b8); font-family: monospace; }
.cd-preview-btn {
  font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 8px;
  background: var(--primary, var(--green)); color: #fff; border: none; cursor: pointer;
  transition: opacity 0.15s;
}
.cd-preview-btn:hover { opacity: 0.85; }

/* ── Instructor ───────────────────────────────── */
.cd-instructor { display: flex; align-items: center; gap: 1rem; }
.cd-instructor-avatar {
  width: 56px; height: 56px; border-radius: 50%; overflow: hidden;
  background: var(--green); display: flex;
  align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
}
.cd-instructor-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cd-instructor-name { font-size: 1rem; font-weight: 700; margin: 0; color: var(--on-surface); }
.cd-instructor-bio { font-size: 0.85rem; color: var(--outline, #64748b); margin: 4px 0 0; }

/* ── Sidebar ──────────────────────────────────── */
.cd-sidebar { position: sticky; top: 84px; }
.cd-sidebar-card {
  background: var(--surface-lowest, #fff); border-radius: 16px;
  border: 1px solid var(--surface-dim, #e5e7eb); padding: 1.5rem;
  box-shadow: 0 8px 30px -12px rgba(0,0,0,0.12);
}
.cd-price-block { margin-bottom: 1rem; }
.cd-price { font-size: 2rem; font-weight: 900; color: var(--on-surface, #0f172a); }
.cd-cta-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 14px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 800;
  background: var(--green); color: #fff; cursor: pointer;
  transition: all 0.2s; box-shadow: 0 4px 14px -4px rgba(var(--green-rgb),0.5);
}
.cd-cta-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px -4px rgba(var(--green-rgb),0.6); }
.cd-cta-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.cd-secondary-btn {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px; margin-top: 0.75rem; border: 2px solid var(--surface-dim, #e5e7eb);
  border-radius: 12px; font-size: 0.9rem; font-weight: 700;
  color: var(--on-surface, #0f172a); text-decoration: none; transition: all 0.15s;
}
.cd-secondary-btn:hover { border-color: var(--primary, var(--green)); color: var(--primary); }
.cd-side-note {
  display: flex; align-items: center; gap: 8px; margin-top: 1rem;
  padding: 10px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;
  background: var(--surface-low, #f1f5f9); color: var(--outline, #64748b);
}
.cd-side-note--success { background: rgba(34,197,94,0.08); color: var(--green); }
.cd-side-features { margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; }
.cd-side-feat {
  display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: var(--on-surface-variant, #475569);
}
.cd-side-feat .material-symbols-outlined { font-size: 20px; color: var(--outline, #94a3b8); }

/* ── Loading ──────────────────────────────────── */
.cd-loading {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 50vh; gap: 1rem; color: var(--outline);
}
.cd-spinner {
  width: 36px; height: 36px; border: 3px solid var(--surface-dim, #e5e7eb);
  border-top-color: var(--primary, var(--green)); border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Responsive ───────────────────────────────── */
@media (max-width: 960px) {
  .cd-grid { grid-template-columns: 1fr; }
  .cd-sidebar { position: static; order: -1; }
  .cd-hero h1 { font-size: 1.6rem; }
  .cd-learn-grid { grid-template-columns: 1fr; }
}
</style>
