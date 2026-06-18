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
  return text.length > 200 ? `${text.slice(0, 200).trim()}...` : text
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

    <!-- Loading overlay -->
    <div v-if="loading" class="cd-loading">
      <div class="cd-spinner" />
      <p>Đang tải thông tin khóa học...</p>
    </div>

    <template v-else-if="course">
      <!-- ── Cinematic Hero ── -->
      <section class="cd-hero">
        <!-- Background: blurred thumbnail -->
        <div
          class="cd-hero-bg"
          :style="course.thumbnail ? `background-image: url('${course.thumbnail}')` : ''"
        />
        <div class="cd-hero-gradient" />

        <div class="cd-hero-inner">
          <!-- Breadcrumb -->
          <nav class="cd-breadcrumb" aria-label="breadcrumb">
            <NuxtLink to="/courses">Khóa học</NuxtLink>
            <span class="material-symbols-outlined">chevron_right</span>
            <span>{{ typeof course?.category === 'object' ? course?.category?.name : (course?.category || 'Danh mục') }}</span>
          </nav>

          <!-- Title & Meta -->
          <div class="cd-hero-content">
            <div class="cd-hero-main">
              <h1 class="cd-hero-title">{{ course.title }}</h1>
              <p class="cd-hero-desc">{{ courseExcerpt }}</p>

              <div class="cd-hero-pills">
                <span v-if="isFree" class="cd-pill cd-pill--free">Miễn phí</span>
                <span class="cd-pill cd-pill--cat">
                  <span class="material-symbols-outlined">folder</span>
                  {{ typeof course?.category === 'object' ? course?.category?.name : (course?.category || 'Danh mục') }}
                </span>
              </div>

              <div class="cd-hero-meta">
                <div class="cd-meta-chip">
                  <span class="material-symbols-outlined">person</span>
                  {{ course?.instructor?.name || 'Giảng viên' }}
                </div>
                <div class="cd-meta-chip">
                  <span class="material-symbols-outlined">group</span>
                  {{ course?.enrollments_count || 0 }} học viên
                </div>
                <div class="cd-meta-chip">
                  <span class="material-symbols-outlined">schedule</span>
                  {{ totalDuration }}
                </div>
                <div class="cd-meta-chip">
                  <span class="material-symbols-outlined">play_circle</span>
                  {{ totalLessons }} bài học
                </div>
              </div>
            </div>

            <!-- Mobile CTA (shows below hero on small screens) -->
            <div class="cd-hero-cta-mobile">
              <div class="cd-price-display">
                <span class="cd-price-big">{{ isFree ? 'Miễn phí' : formatPrice(course?.price) }}</span>
              </div>
              <button class="cd-cta-primary" :disabled="enrolling" @click="handlePrimaryAction">
                <span class="material-symbols-outlined">{{ canEnterLearning ? 'play_arrow' : (isFree ? 'add' : 'shopping_cart') }}</span>
                {{ enrolling ? 'Đang xử lý...' : primaryCtaLabel }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Content ── -->
      <div class="cd-wrap">
        <div class="cd-grid">

          <!-- ── Left Column ── -->
          <div class="cd-main">

            <!-- What you'll learn -->
            <section class="cd-card">
              <h2 class="cd-card-title">
                <span class="cd-card-icon material-symbols-outlined">school</span>
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

            <!-- Thumbnail (if exists) -->
            <section v-if="course?.thumbnail" class="cd-card cd-thumb-card">
              <img :src="course.thumbnail" :alt="course.title" class="cd-thumb-img">
            </section>

            <!-- Description -->
            <section v-if="course?.description" class="cd-card">
              <h2 class="cd-card-title">
                <span class="cd-card-icon material-symbols-outlined">description</span>
                Giới thiệu khóa học
              </h2>
              <div class="cd-prose" v-html="course.description" />
            </section>

            <!-- Curriculum -->
            <section class="cd-card">
              <div class="cd-card-header">
                <h2 class="cd-card-title">
                  <span class="cd-card-icon material-symbols-outlined">menu_book</span>
                  Nội dung khóa học
                </h2>
                <div class="cd-curr-stats">
                  <span>{{ totalSections }} chương</span>
                  <span>·</span>
                  <span>{{ totalLessons }} bài</span>
                  <span>·</span>
                  <span>{{ totalDuration }}</span>
                </div>
              </div>

              <div v-if="sections.length" class="cd-sections">
                <div v-for="section in sections" :key="section.id" class="cd-section">
                  <button class="cd-section-btn" @click="toggleSection(section.id)">
                    <div class="cd-section-left">
                      <span class="cd-section-num">{{ section.position }}</span>
                      <div>
                        <p class="cd-section-label">Phần {{ section.position }}</p>
                        <h3 class="cd-section-name">{{ section.title }}</h3>
                      </div>
                    </div>
                    <div class="cd-section-right">
                      <span class="cd-section-count">{{ section.lessons?.length || 0 }} bài</span>
                      <span class="material-symbols-outlined cd-section-chevron" :class="{ open: isSectionOpen(section.id) }">expand_more</span>
                    </div>
                  </button>
                  <div v-if="isSectionOpen(section.id)" class="cd-section-body">
                    <div v-for="lesson in section.lessons || []" :key="lesson.id" class="cd-lesson">
                      <div class="cd-lesson-left">
                        <span class="material-symbols-outlined cd-lesson-icon" :class="lesson.is_preview ? 'is-preview' : ''">
                          {{ lesson.is_preview ? 'play_circle' : 'lock' }}
                        </span>
                        <span class="cd-lesson-name">{{ lesson.title }}</span>
                        <span v-if="lesson.is_preview" class="cd-preview-badge">Xem trước</span>
                      </div>
                      <div class="cd-lesson-right">
                        <span class="cd-lesson-dur">{{ formatDuration(lesson.duration || 0) }}</span>
                        <button v-if="lesson.is_preview" class="cd-lesson-preview-btn" @click.stop="goToPreviewLesson(lesson.id)">
                          Xem thử
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="cd-sections">
                <div v-for="lesson in lessons" :key="lesson.id" class="cd-lesson cd-lesson--flat">
                  <div class="cd-lesson-left">
                    <span class="material-symbols-outlined cd-lesson-icon" :class="lesson.is_preview ? 'is-preview' : ''">
                      {{ lesson.is_preview ? 'play_circle' : 'lock' }}
                    </span>
                    <span class="cd-lesson-name">{{ lesson.title }}</span>
                    <span v-if="lesson.is_preview" class="cd-preview-badge">Xem trước</span>
                  </div>
                  <div class="cd-lesson-right">
                    <span class="cd-lesson-dur">{{ formatDuration(lesson.duration || 0) }}</span>
                    <button v-if="lesson.is_preview" class="cd-lesson-preview-btn" @click="goToPreviewLesson(lesson.id)">
                      Xem thử
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <!-- Instructor -->
            <section v-if="course?.instructor" class="cd-card">
              <h2 class="cd-card-title">
                <span class="cd-card-icon material-symbols-outlined">person</span>
                Giảng viên
              </h2>
              <div class="cd-instructor">
                <div class="cd-instructor-avatar">
                  <img v-if="course.instructor.avatar" :src="course.instructor.avatar" :alt="course.instructor.name">
                  <span v-else>{{ course.instructor.name?.charAt(0) }}</span>
                </div>
                <div class="cd-instructor-info">
                  <h3 class="cd-instructor-name">{{ course.instructor.name }}</h3>
                  <p class="cd-instructor-bio">{{ course.instructor.bio || 'Giảng viên tại EduPress' }}</p>
                </div>
              </div>
            </section>

            <!-- Reviews -->
            <CourseReviewSection :course-id="courseId" :is-enrolled="isEnrolled" />
          </div>

          <!-- ── Sidebar ── -->
          <aside class="cd-sidebar">
            <div class="cd-sidebar-card">
              <!-- Price + thumbnail preview -->
              <div class="cd-side-thumb">
                <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="cd-side-thumb-img">
                <div v-else class="cd-side-thumb-fallback">
                  <span class="material-symbols-outlined">school</span>
                </div>
                <div class="cd-side-thumb-overlay">
                  <button class="cd-side-play-btn" @click="handlePrimaryAction">
                    <span class="material-symbols-outlined">play_circle</span>
                  </button>
                </div>
              </div>

              <div class="cd-side-body">
                <div class="cd-side-price-row">
                  <span class="cd-side-price">{{ isFree ? 'Miễn phí' : formatPrice(course?.price) }}</span>
                </div>

                <button class="cd-cta-primary" :disabled="enrolling" @click="handlePrimaryAction">
                  <span class="material-symbols-outlined">{{ canEnterLearning ? 'play_arrow' : (isFree ? 'add' : 'shopping_cart') }}</span>
                  {{ enrolling ? 'Đang xử lý...' : primaryCtaLabel }}
                </button>

                <NuxtLink
                  v-if="previewLessonId && !canPrivilegedEnter"
                  :to="`/learn/${courseId}/${previewLessonId}`"
                  class="cd-cta-secondary"
                >
                  <span class="material-symbols-outlined">visibility</span>
                  Xem thử miễn phí
                </NuxtLink>

                <div v-if="canPrivilegedEnter" class="cd-side-note">
                  <span class="material-symbols-outlined">shield</span>
                  Đang xem với quyền {{ isAdmin ? 'Admin' : 'Giảng viên' }}
                </div>
                <div v-else-if="isEnrolled" class="cd-side-note cd-side-note--success">
                  <span class="material-symbols-outlined">check_circle</span>
                  Bạn đã ghi danh khóa học này
                </div>

                <div class="cd-side-divider" />

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
            </div>
          </aside>

        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* ── Page ── */
.cd-page {
  min-height: 100vh;
  background: var(--surface-strong, #f6f8f3);
}

/* ── Loading ── */
.cd-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  gap: 14px;
  color: var(--muted);
}
.cd-spinner {
  width: 38px; height: 38px;
  border: 3px solid rgba(var(--green-rgb), 0.15);
  border-top-color: var(--green);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Hero ── */
.cd-hero {
  position: relative;
  min-height: 420px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.cd-hero-bg {
  position: absolute;
  inset: 0;
  background-color: #0d2e1e;
  background-size: cover;
  background-position: center;
  filter: blur(3px) brightness(0.3) saturate(1.4);
  transform: scale(1.06);
}

.cd-hero-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgba(4, 12, 8, 0.98) 0%,
    rgba(7, 20, 13, 0.75) 40%,
    rgba(10, 26, 18, 0.4) 75%,
    rgba(10, 26, 18, 0.2) 100%
  );
}

.cd-hero-inner {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 56px 24px 44px;
}

/* Breadcrumb */
.cd-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 20px;
}
.cd-breadcrumb a {
  color: rgba(255, 255, 255, 0.72);
  text-decoration: none;
  transition: color 150ms;
}
.cd-breadcrumb a:hover { color: #fff; }
.cd-breadcrumb .material-symbols-outlined { font-size: 14px; }

/* Hero content */
.cd-hero-content {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
  flex-wrap: wrap;
}

.cd-hero-main { max-width: 680px; }

.cd-hero-title {
  font-size: clamp(1.8rem, 4vw, 2.8rem);
  font-weight: 900;
  letter-spacing: -0.045em;
  line-height: 1.1;
  color: #fff;
  margin: 0 0 14px;
}

.cd-hero-desc {
  font-size: 1rem;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.62);
  margin: 0 0 20px;
  max-width: 580px;
}

.cd-hero-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}
.cd-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 26px;
  padding: 0 11px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.cd-pill .material-symbols-outlined { font-size: 14px; }
.cd-pill--free {
  background: var(--green);
  color: #fff;
}
.cd-pill--cat {
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.cd-hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.cd-meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.1);
}
.cd-meta-chip .material-symbols-outlined { font-size: 15px; color: rgba(255, 255, 255, 0.5); }

/* Mobile CTA inside hero */
.cd-hero-cta-mobile { display: none; }

/* ── Content wrap ── */
.cd-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 24px 64px;
}

.cd-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 28px;
  align-items: start;
}

/* ── Cards ── */
.cd-card {
  background: #fff;
  border-radius: 20px;
  border: 1px solid var(--line);
  padding: 28px;
  margin-bottom: 20px;
}
[data-theme="dark"] .cd-card {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.07);
}

.cd-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.cd-card-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  margin: 0 0 20px;
  color: var(--text);
}
.cd-card-header .cd-card-title { margin: 0; }
.cd-card-icon { font-size: 22px; color: var(--green); }

/* What you'll learn */
.cd-learn-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.cd-learn-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.9rem;
  color: var(--muted);
  line-height: 1.55;
}
.cd-check {
  font-size: 20px;
  color: var(--green);
  flex-shrink: 0;
  margin-top: 1px;
  font-variation-settings: 'FILL' 1;
}

/* Thumbnail */
.cd-thumb-card { padding: 0; overflow: hidden; }
.cd-thumb-img {
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  display: block;
}

/* Prose */
.cd-prose {
  font-size: 0.93rem;
  line-height: 1.8;
  color: var(--muted);
}
.cd-prose :deep(h2), .cd-prose :deep(h3) {
  color: var(--text);
  font-weight: 800;
  margin: 1.25em 0 0.5em;
}
.cd-prose :deep(p) { margin: 0 0 0.8em; }
.cd-prose :deep(ul), .cd-prose :deep(ol) { padding-left: 1.4em; }

/* Curriculum stats */
.cd-curr-stats {
  display: flex;
  gap: 8px;
  font-size: 0.8rem;
  color: var(--muted);
  align-items: center;
}

/* Sections */
.cd-sections { display: flex; flex-direction: column; gap: 8px; }
.cd-section {
  border: 1px solid var(--line);
  border-radius: 14px;
  overflow: hidden;
}
[data-theme="dark"] .cd-section { border-color: rgba(255, 255, 255, 0.08); }

.cd-section-btn {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  background: rgba(var(--green-rgb), 0.03);
  border: none;
  cursor: pointer;
  text-align: left;
  gap: 12px;
  transition: background 150ms;
}
.cd-section-btn:hover { background: rgba(var(--green-rgb), 0.07); }
[data-theme="dark"] .cd-section-btn { background: rgba(255, 255, 255, 0.03); }
[data-theme="dark"] .cd-section-btn:hover { background: rgba(255, 255, 255, 0.06); }

.cd-section-left { display: flex; align-items: center; gap: 12px; }
.cd-section-num {
  width: 28px; height: 28px;
  border-radius: 8px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  font-size: 0.78rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.cd-section-label {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--green-deep);
  margin: 0 0 2px;
}
.cd-section-name {
  font-size: 0.92rem;
  font-weight: 700;
  margin: 0;
  color: var(--text);
}
.cd-section-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.cd-section-count { font-size: 0.8rem; color: var(--muted); font-weight: 600; }
.cd-section-chevron {
  font-size: 22px;
  color: var(--muted);
  transition: transform 200ms ease;
}
.cd-section-chevron.open { transform: rotate(180deg); }

.cd-section-body { border-top: 1px solid var(--line); }
[data-theme="dark"] .cd-section-body { border-top-color: rgba(255, 255, 255, 0.07); }

.cd-lesson {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 11px 18px;
  gap: 12px;
  transition: background 120ms;
}
.cd-lesson:hover { background: rgba(var(--green-rgb), 0.04); }
.cd-lesson--flat {
  border-bottom: 1px solid var(--line);
  border-radius: 0;
}
.cd-lesson--flat:last-child { border-bottom: none; }

.cd-lesson-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}
.cd-lesson-icon {
  font-size: 18px;
  color: var(--muted);
  flex-shrink: 0;
}
.cd-lesson-icon.is-preview { color: var(--green); }
.cd-lesson-name {
  font-size: 0.87rem;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cd-preview-badge {
  font-size: 0.63rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(var(--green-rgb), 0.1);
  color: var(--green-deep);
  white-space: nowrap;
  flex-shrink: 0;
}
.cd-lesson-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.cd-lesson-dur {
  font-size: 0.8rem;
  color: var(--muted);
  font-family: monospace;
}
.cd-lesson-preview-btn {
  height: 26px;
  padding: 0 12px;
  border-radius: 8px;
  border: none;
  background: var(--green);
  color: #fff;
  font-size: 0.74rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 150ms;
}
.cd-lesson-preview-btn:hover { opacity: 0.85; }

/* Instructor */
.cd-instructor {
  display: flex;
  align-items: center;
  gap: 16px;
}
.cd-instructor-avatar {
  width: 60px; height: 60px;
  border-radius: 50%;
  overflow: hidden;
  background: linear-gradient(135deg, var(--green) 0%, #0d7a5a 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.4rem;
  font-weight: 800;
  flex-shrink: 0;
}
.cd-instructor-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cd-instructor-name {
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 4px;
  color: var(--text);
}
.cd-instructor-bio {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0;
  line-height: 1.55;
}

/* ── Sidebar ── */
.cd-sidebar {
  position: sticky;
  top: 80px;
}

.cd-sidebar-card {
  background: #fff;
  border-radius: 20px;
  border: 1px solid var(--line);
  overflow: hidden;
  box-shadow: 0 20px 60px -20px rgba(0, 0, 0, 0.14);
}
[data-theme="dark"] .cd-sidebar-card {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
}

/* Thumbnail preview in sidebar */
.cd-side-thumb {
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: #0d2e1e;
  cursor: pointer;
}
.cd-side-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 300ms ease;
}
.cd-side-thumb:hover .cd-side-thumb-img { transform: scale(1.04); }
.cd-side-thumb-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #071812 0%, #163d2a 100%);
}
.cd-side-thumb-fallback .material-symbols-outlined {
  font-size: 52px;
  color: rgba(29, 158, 117, 0.4);
}
.cd-side-thumb-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 200ms;
}
.cd-side-thumb:hover .cd-side-thumb-overlay { opacity: 1; }
.cd-side-play-btn {
  width: 56px; height: 56px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(8px);
  border: 2px solid rgba(255, 255, 255, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 150ms, background 150ms;
}
.cd-side-play-btn:hover {
  transform: scale(1.08);
  background: rgba(255, 255, 255, 0.25);
}
.cd-side-play-btn .material-symbols-outlined {
  font-size: 30px;
  color: #fff;
}

/* Sidebar body */
.cd-side-body {
  padding: 22px 22px 26px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cd-side-price-row { display: flex; align-items: baseline; gap: 10px; }
.cd-side-price {
  font-size: 2.2rem;
  font-weight: 900;
  letter-spacing: -0.05em;
  color: var(--text);
  line-height: 1;
}

/* CTAs */
.cd-cta-primary {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 52px;
  border: none;
  border-radius: 14px;
  font-size: 1rem;
  font-weight: 800;
  background: linear-gradient(135deg, var(--green) 0%, #0d7a5a 100%);
  color: #fff;
  cursor: pointer;
  transition: all 200ms ease;
  box-shadow: 0 6px 20px rgba(var(--green-rgb), 0.35);
}
.cd-cta-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(var(--green-rgb), 0.45);
}
.cd-cta-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.cd-cta-primary .material-symbols-outlined { font-size: 22px; }

.cd-cta-secondary {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 46px;
  border: 2px solid var(--line);
  border-radius: 14px;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text);
  text-decoration: none;
  transition: all 150ms;
}
.cd-cta-secondary:hover {
  border-color: rgba(var(--green-rgb), 0.4);
  color: var(--green-deep);
}
.cd-cta-secondary .material-symbols-outlined { font-size: 18px; }

/* Side note */
.cd-side-note {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 0.82rem;
  font-weight: 600;
  background: rgba(17, 17, 17, 0.04);
  color: var(--muted);
}
.cd-side-note .material-symbols-outlined { font-size: 16px; }
.cd-side-note--success {
  background: rgba(var(--green-rgb), 0.07);
  color: var(--green-deep);
  border: 1px solid rgba(var(--green-rgb), 0.15);
}

.cd-side-divider {
  height: 1px;
  background: var(--line);
  margin: 4px 0;
}

/* Side features */
.cd-side-features { display: flex; flex-direction: column; gap: 10px; }
.cd-side-feat {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.87rem;
  color: var(--muted);
}
.cd-side-feat .material-symbols-outlined {
  font-size: 19px;
  color: var(--green-deep);
  opacity: 0.7;
}

/* ── Responsive ── */
@media (max-width: 1000px) {
  .cd-grid { grid-template-columns: 1fr; }
  .cd-sidebar {
    position: static;
    order: -1;
  }
  .cd-sidebar-card {
    display: grid;
    grid-template-columns: 280px 1fr;
  }
  .cd-side-thumb { border-radius: 0; aspect-ratio: auto; height: 100%; }
}

@media (max-width: 760px) {
  .cd-hero { min-height: 320px; }
  .cd-hero-title { font-size: 1.75rem; }
  .cd-hero-cta-mobile { display: block; }
  .cd-sidebar-card { grid-template-columns: 1fr; }
  .cd-side-thumb { display: none; }
  .cd-wrap { padding: 20px 16px 48px; }
  .cd-card { padding: 20px; }
  .cd-learn-grid { grid-template-columns: 1fr; }
  .cd-hero-inner { padding: 40px 16px 32px; }
}
</style>
