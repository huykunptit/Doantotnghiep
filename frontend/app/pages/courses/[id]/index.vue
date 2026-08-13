<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'default' })

interface LessonItem {
  id: number
  title: string
  type?: string
  duration?: number
  is_preview?: boolean
}
interface SectionItem {
  id: number
  title: string
  lessons?: LessonItem[]
}
interface CourseDetail {
  id: number
  slug?: string
  title: string
  description?: string | null
  thumbnail?: string | null
  price?: number
  level?: string | null
  trailer_url?: string | null
  learning_outcomes?: string[] | null
  benefits?: string[] | null
  requirements?: string[] | null
  lessons_count?: number
  enrollments_count?: number
  avg_rating?: number
  is_enrolled?: boolean
  has_reviewed?: boolean
  latest_reviews?: Array<{
    id: number
    rating: number
    comment?: string | null
    created_at?: string
    user?: { name?: string, avatar?: string | null } | null
  }>
  instructor?: { id?: number, name?: string, avatar?: string | null } | null
  category?: { name?: string } | null
  lessons?: LessonItem[]
  sections?: SectionItem[]
  path_suggestions?: PathSuggestion[]
}

interface PathSuggestion {
  id: number
  title: string
  slug: string
  path_price: number
  total_count: number
  owned_count: number
  remaining_count: number
  remaining_total_price: number
  remaining_courses: Array<{
    id: number
    title: string
    slug?: string
    thumbnail?: string | null
    price: number
  }>
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { isAdmin } = usePermissions()
const toast = useToast()
const { t, locale } = useI18n()
/** URL param có thể là id số hoặc slug SEO */
const courseParam = computed(() => String(route.params.id || ''))
const courseNumericId = computed(() => course.value?.id || (Number.isFinite(Number(courseParam.value)) ? Number(courseParam.value) : 0))
const loading = ref(true)
const course = ref<CourseDetail | null>(null)
const sections = ref<SectionItem[]>([])
const openSections = ref<number[]>([])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatPrice = (price = 0) => {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

const outcomes = computed(() => (course.value?.learning_outcomes || []).filter(Boolean))
const benefits = computed(() => (course.value?.benefits || []).filter(Boolean))
const requirements = computed(() => (course.value?.requirements || []).filter(Boolean))
const lessonCount = computed(() =>
  course.value?.lessons_count
  || sections.value.reduce((n, s) => n + (s.lessons?.length || 0), 0)
  || course.value?.lessons?.length
  || 0,
)

const levelLabel = computed(() => {
  const level = course.value?.level
  if (!level) return ''
  const key = `admin.builder.levels.${level}`
  const translated = t(key)
  return translated === key ? level : translated
})

const excerpt = computed(() => {
  const raw = String(course.value?.description || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  if (!raw) return ''
  return raw.length > 160 ? `${raw.slice(0, 160)}…` : raw
})

const LESSON_TYPE_ICONS: Record<string, string> = {
  video: 'pi-play-circle',
  audio: 'pi-volume-up',
  page: 'pi-file-edit',
  file: 'pi-file',
  document: 'pi-book',
  scorm: 'pi-box',
  h5p: 'pi-code',
  quiz: 'pi-question-circle',
  assignment: 'pi-pencil',
  forum: 'pi-comments',
  survey: 'pi-chart-bar',
  zoom: 'pi-video',
  meet: 'pi-globe',
  virtual_class: 'pi-desktop',
  live: 'pi-comments',
  offline: 'pi-map-marker',
}
function lessonTypeIcon(type?: string) {
  return LESSON_TYPE_ICONS[type || ''] || 'pi-play-circle'
}

function fmtMinutes(minutes?: number) {
  const n = Number(minutes || 0)
  if (!n) return ''
  return t('student.detail.lessonMinutes', { n })
}

async function load() {
  loading.value = true
  try {
    const [detail, sectionRes] = await Promise.all([
      useApi<CourseDetail>(`/courses/${courseParam.value}`),
      useApi<{ data?: SectionItem[] }>(`/courses/${courseParam.value}/sections`).catch(() => ({ data: [] as SectionItem[] })),
    ])
    course.value = detail
    sections.value = (sectionRes.data && sectionRes.data.length)
      ? sectionRes.data
      : (detail.sections || [])
    openSections.value = sections.value.slice(0, 1).map(s => s.id)
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.catalog.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function toggleSection(id: number) {
  openSections.value = openSections.value.includes(id)
    ? openSections.value.filter(x => x !== id)
    : [...openSections.value, id]
}

function coursePath() {
  return `/courses/${course.value?.slug || courseParam.value}`
}

function goBack() {
  if (window.history.length > 1) router.back()
  else navigateTo('/courses')
}

const isOwnerInstructor = computed(() =>
  Boolean(auth.user?.id && course.value?.instructor?.id === auth.user.id),
)
/** Admins and the owning instructor can open the full lesson content without buying — backend already
 *  serves them unlocked lessons (see LessonController::index's $isOwner check), the storefront CTA just
 *  needs to route them straight to /learn instead of the checkout flow. */
const canBypassPurchase = computed(() => isAdmin.value || isOwnerInstructor.value)
const canUnlockAll = computed(() => Boolean(course.value?.is_enrolled) || canBypassPurchase.value)
function isLessonLocked(lesson: LessonItem) {
  return !canUnlockAll.value && !lesson.is_preview
}

async function primaryAction() {
  const id = courseNumericId.value
  if (!auth.isAuthenticated) return navigateTo(`/login?redirect=${encodeURIComponent(coursePath())}`)
  if (!id) return
  if (course.value?.is_enrolled) return navigateTo(`/learn/${id}`)
  if (canBypassPurchase.value) return navigateTo(`/learn/${id}`)

  // Khóa miễn phí: ghi danh ngay, không qua trang thanh toán
  if ((course.value?.price || 0) <= 0) {
    try {
      const res = await useApi<{ enrolled?: boolean, message?: string }>('/orders', {
        method: 'POST',
        body: { course_id: id, payment_method: 'payos' },
      })
      if (res.enrolled) {
        course.value.is_enrolled = true
        toast.add({ severity: 'success', summary: t('student.checkout.success'), life: 2500 })
        return navigateTo(`/learn/${id}`)
      }
      toast.add({ severity: 'warn', summary: t('student.checkout.error'), detail: res.message, life: 4000 })
    }
    catch (error: any) {
      const msg = error?.data?.message || ''
      if (String(msg).toLowerCase().includes('already enrolled')) {
        course.value.is_enrolled = true
        return navigateTo(`/learn/${id}`)
      }
      toast.add({ severity: 'error', summary: t('student.checkout.error'), detail: msg, life: 4500 })
    }
    return
  }

  return navigateTo(`/checkout/${id}`)
}

const ctaLabel = computed(() => {
  if (!auth.isAuthenticated) return t('student.catalog.loginToBuy')
  if (course.value?.is_enrolled) return t('student.catalog.learnNow')
  if (canBypassPurchase.value) return t('student.catalog.previewAsAdmin')
  if ((course.value?.price || 0) <= 0) return t('student.catalog.enrollFree')
  return t('student.catalog.buy')
})

const pathSuggestions = computed(() => course.value?.path_suggestions || [])

onMounted(load)
</script>

<template>
  <div v-if="loading" class="state">…</div>
  <div v-else-if="!course" class="state">—</div>
  <div v-else class="cd">
    <!-- Full-bleed hero: one composition -->
    <header
      class="hero"
      :style="course.thumbnail
        ? { backgroundImage: `linear-gradient(105deg, rgba(8,20,18,.94) 0%, rgba(8,20,18,.85) 48%, rgba(8,20,18,.55) 100%), url(${course.thumbnail})` }
        : undefined"
    >
      <Button
        icon="pi pi-arrow-left"
        text
        rounded
        class="hero-back"
        :aria-label="t('student.detail.back')"
        @click="goBack"
      />
      <div class="hero-inner">
        <div class="hero-main">
          <p class="kicker">{{ course.category?.name || t('student.catalog.title') }}</p>
          <h1 class="course-title">{{ course.title }}</h1>
          <p v-if="excerpt" class="lede">{{ excerpt }}</p>
          <p class="byline">
            <template v-if="course.instructor?.name">{{ course.instructor.name }} · </template>
            {{ t('student.catalog.lessons', { n: lessonCount }) }}
            <template v-if="levelLabel"> · {{ levelLabel }}</template>
          </p>
        </div>

        <aside class="offer">
          <div
            class="offer-media"
            :style="course.thumbnail ? { backgroundImage: `url(${course.thumbnail})` } : undefined"
          />
          <div class="offer-body">
            <p class="offer-price">{{ formatPrice(course.price || 0) }}</p>
            <Button
              :label="ctaLabel"
              class="offer-cta"
              :disabled="Boolean(course.is_enrolled) && lessonCount === 0"
              @click="primaryAction"
            />
            <ul>
              <li>{{ t('student.detail.includesLessons', { n: lessonCount }) }}</li>
              <li v-if="levelLabel">{{ t('student.detail.includesLevel', { level: levelLabel }) }}</li>
              <li>{{ t('student.detail.includesLifetime') }}</li>
            </ul>
          </div>
        </aside>
      </div>
    </header>

    <div class="body">
      <div class="body-grid">
        <div class="body-main">
          <section v-if="outcomes.length" class="sec">
            <h2><i class="pi pi-check-circle" />{{ t('student.detail.outcomes') }}</h2>
            <ul class="ticks">
              <li v-for="(item, i) in outcomes" :key="`o-${i}`"><i class="pi pi-check" /><span v-html="item" /></li>
            </ul>
          </section>

          <section v-if="benefits.length" class="sec">
            <h2><i class="pi pi-gift" />{{ t('student.detail.benefits') }}</h2>
            <ul class="ticks soft">
              <li v-for="(item, i) in benefits" :key="`b-${i}`"><i class="pi pi-star-fill" /><span v-html="item" /></li>
            </ul>
          </section>

          <section class="sec">
            <h2><i class="pi pi-info-circle" />{{ t('student.detail.about') }}</h2>
            <div class="prose" v-html="course.description || '—'" />
          </section>

          <section v-if="requirements.length" class="sec">
            <h2><i class="pi pi-list-check" />{{ t('student.detail.requirements') }}</h2>
            <ul class="dots">
              <li v-for="(item, i) in requirements" :key="`r-${i}`" v-html="item" />
            </ul>
          </section>
        </div>

        <aside class="body-side">
          <section class="sec curr-sec">
            <div class="curr-head">
              <h2><i class="pi pi-book" />{{ t('student.detail.curriculum') }}</h2>
              <span v-if="sections.length" class="curr-count">{{ t('student.detail.sections', { n: sections.length }) }} · {{ t('student.catalog.lessons', { n: lessonCount }) }}</span>
            </div>
            <div v-if="sections.length" class="curr">
              <article v-for="section in sections" :key="section.id" :class="{ open: openSections.includes(section.id) }">
                <button type="button" class="curr-section-btn" @click="toggleSection(section.id)">
                  <i class="pi pi-chevron-right chev" />
                  <strong>{{ section.title }}</strong>
                  <span>{{ t('student.catalog.lessons', { n: section.lessons?.length || 0 }) }}</span>
                </button>
                <ul v-if="openSections.includes(section.id)" class="curr-lessons">
                  <li v-for="lesson in (section.lessons || [])" :key="lesson.id" :class="{ locked: isLessonLocked(lesson) }">
                    <i class="pi lesson-icon" :class="lessonTypeIcon(lesson.type)" />
                    <span class="lesson-title">{{ lesson.title }}</span>
                    <span class="lesson-meta">
                      <em v-if="fmtMinutes(lesson.duration)">{{ fmtMinutes(lesson.duration) }}</em>
                      <i v-if="isLessonLocked(lesson)" class="pi pi-lock" :title="t('student.detail.locked')" />
                      <i v-else-if="lesson.is_preview && !canUnlockAll" class="pi pi-eye" :title="t('student.detail.preview')" />
                    </span>
                  </li>
                </ul>
              </article>
            </div>
            <ol v-else class="dots curriculum-fallback">
              <li v-for="lesson in (course.lessons || [])" :key="lesson.id">{{ lesson.title }}</li>
              <li v-if="!(course.lessons || []).length">—</li>
            </ol>
          </section>
        </aside>
      </div>

      <section v-if="pathSuggestions.length" class="sec path-suggest">
        <h2><i class="pi pi-map" />{{ t('student.detail.pathSuggestTitle') }}</h2>
        <p class="path-suggest-hint">{{ t('student.detail.pathSuggestHint') }}</p>
        <article v-for="path in pathSuggestions" :key="path.id" class="path-card">
          <div class="path-card-head">
            <div>
              <strong>{{ path.title }}</strong>
              <span>{{ t('student.detail.pathSuggestProgress', {
                owned: path.owned_count,
                total: path.total_count,
                remain: path.remaining_count,
                price: formatPrice(path.remaining_total_price),
              }) }}</span>
            </div>
            <div class="path-card-actions">
              <Button
                :label="t('student.detail.pathSuggestView')"
                text
                size="small"
                @click="navigateTo(`/paths/${path.slug}`)"
              />
              <Button
                :label="t('student.detail.pathSuggestBuyPath', { price: formatPrice(path.path_price) })"
                size="small"
                severity="secondary"
                @click="navigateTo(`/checkout/path/${path.slug}`)"
              />
            </div>
          </div>
          <ul class="path-remain">
            <li v-for="c in path.remaining_courses" :key="c.id">
              <img v-if="c.thumbnail" :src="c.thumbnail" :alt="c.title">
              <div class="path-remain-meta">
                <NuxtLink :to="`/courses/${c.slug || c.id}`">{{ c.title }}</NuxtLink>
                <em>{{ formatPrice(c.price) }}</em>
              </div>
              <Button
                :label="t('student.detail.pathSuggestBuyCourse')"
                size="small"
                outlined
                @click="navigateTo(`/checkout/${c.id}`)"
              />
            </li>
          </ul>
        </article>
      </section>

      <div class="body-foot">
        <StudentCourseReviews
          :course-id="courseNumericId"
          :enrolled="!!course.is_enrolled"
          :has-reviewed="!!course.has_reviewed"
          :avg-rating="course.avg_rating || 0"
          :reviews="course.latest_reviews || []"
          @refreshed="load"
        />

        <StudentCourseQa
          :course-id="courseNumericId"
          :enrolled="!!course.is_enrolled"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.state { padding: 64px 24px; text-align: center; color: var(--text-muted); }

.hero {
  position: relative;
  background: #0b1f1c;
  background-size: cover;
  background-position: center;
  color: #f4faf8;
  padding: 48px 24px 64px;
}
.hero-back {
  position: absolute;
  top: 16px;
  left: 16px;
  color: #f4faf8 !important;
  background: rgba(8, 20, 18, .45) !important;
  z-index: 1;
}
.hero-back:hover { background: rgba(8, 20, 18, .65) !important; }
.hero-inner {
  width: min(1120px, 100%);
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 40px;
  align-items: start;
}
.hero-main {
  color: #f4faf8;
}
.kicker {
  margin: 0 0 10px;
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #9ad5cb;
}
.hero-main h1.course-title,
.hero-main .course-title {
  margin: 0 0 14px;
  font-family: var(--font-display);
  font-size: clamp(2rem, 4vw, 2.75rem);
  line-height: 1.15;
  font-weight: 700;
  max-width: 22ch;
  color: #f8fffc !important;
  -webkit-text-fill-color: #f8fffc;
  text-shadow: 0 1px 14px rgba(0, 0, 0, .45);
}
.lede {
  margin: 0 0 16px;
  max-width: 46ch;
  font-size: 1.05rem;
  line-height: 1.55;
  color: rgba(244, 250, 248, .88);
  font-weight: 500;
}
.byline {
  margin: 0;
  color: rgba(244, 250, 248, .78);
  font-weight: 600;
  font-size: .92rem;
}

.offer {
  background: #fff;
  color: var(--text);
  border-radius: 4px;
  overflow: hidden;
  box-shadow: 0 18px 50px rgba(0, 0, 0, .28);
}
.offer-media {
  aspect-ratio: 16 / 9;
  background: linear-gradient(135deg, #0f766e, #134e4a);
  background-size: cover;
  background-position: center;
}
.offer-body { padding: 18px 18px 20px; display: grid; gap: 12px; }
.offer-price { margin: 0; font-size: 1.7rem; font-weight: 800; }
.offer-cta { width: 100%; }
.offer ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
  color: var(--text-muted);
  font-size: .88rem;
  font-weight: 550;
}
.offer li::before {
  content: '✓ ';
  color: var(--brand);
  font-weight: 800;
}

.body {
  width: min(1120px, calc(100% - 48px));
  margin: -18px auto 64px;
  padding-top: 0;
  display: grid;
  gap: 16px;
  position: relative;
  z-index: 1;
}
.body-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(300px, .85fr);
  gap: 16px;
  align-items: start;
}
.body-main,
.body-side,
.body-foot {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.body-side .curr-sec {
  position: sticky;
  top: 16px;
}
.sec {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 96%, transparent);
  padding: 22px 24px;
}
.sec h2 {
  margin: 0 0 14px;
  font-size: 1.15rem;
  font-family: var(--font-display);
  display: flex;
  align-items: center;
  gap: 10px;
}
.sec h2 i {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: var(--brand-soft, color-mix(in srgb, var(--brand) 14%, transparent));
  color: var(--brand);
  font-size: .95rem;
  flex-shrink: 0;
}

.ticks {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
.body-main .ticks {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.ticks li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  line-height: 1.4;
  font-weight: 550;
  font-size: .95rem;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
  background: color-mix(in srgb, var(--surface-hover, #f1f5f9) 55%, transparent);
}
.ticks li i {
  flex-shrink: 0;
  margin-top: 3px;
  color: var(--brand);
  font-size: .85rem;
}
.ticks.soft li i { color: #d97706; font-size: .72rem; }
.prose {
  line-height: 1.65;
  font-weight: 500;
  color: var(--text);
  max-width: 75ch;
}
.dots {
  margin: 0;
  padding-left: 1.1rem;
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
  font-weight: 550;
}
.dots.curriculum-fallback { grid-template-columns: 1fr; }
/* Items are authored in the rich text editor, so they arrive wrapped in <p>. */
.ticks li span :deep(p),
.dots li :deep(p) { margin: 0; }

.curr-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.curr-head h2 { margin: 0; }
.curr-count { color: var(--text-muted); font-size: .85rem; font-weight: 650; }

.curr { display: grid; gap: 8px; margin-top: 14px; }
.curr article {
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  background: var(--surface);
}
.curr-section-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 0;
  background: transparent;
  cursor: pointer;
  color: inherit;
  font: inherit;
  text-align: left;
}
.curr-section-btn:hover { background: var(--surface-hover, #f8fafc); }
.curr-section-btn .chev {
  flex-shrink: 0;
  font-size: .78rem;
  color: var(--text-muted);
  transition: transform .15s ease;
}
article.open .curr-section-btn .chev { transform: rotate(90deg); }
.curr-section-btn strong { flex: 1; min-width: 0; }
.curr-section-btn span { color: var(--text-muted); font-weight: 650; font-size: .82rem; white-space: nowrap; }

.curr-lessons {
  margin: 0;
  padding: 4px 0 8px;
  list-style: none;
  display: grid;
  border-top: 1px solid var(--border);
}
.curr-lessons li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 14px 9px 40px;
  font-weight: 550;
  color: var(--text);
}
.curr-lessons li:hover { background: var(--surface-hover, #f8fafc); }
.curr-lessons li.locked { color: var(--text-muted); }
.lesson-icon { color: var(--brand); font-size: .88rem; flex-shrink: 0; }
.locked .lesson-icon { color: var(--text-muted); }
.lesson-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lesson-meta {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: .78rem;
  color: var(--text-muted);
}
.lesson-meta em { font-style: normal; }
.lesson-meta .pi-lock { color: var(--text-muted); }
.lesson-meta .pi-eye { color: var(--brand); }

.path-suggest-hint { margin: 0 0 14px; color: var(--text-muted); font-weight: 500; }
.path-card {
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 14px;
  display: grid;
  gap: 12px;
  margin-bottom: 12px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.path-card-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  align-items: flex-start;
}
.path-card-head strong { display: block; font-size: 1.05rem; }
.path-card-head span { display: block; margin-top: 4px; color: var(--text-muted); font-size: .88rem; font-weight: 550; }
.path-card-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.path-remain { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.path-remain li {
  display: grid;
  grid-template-columns: 56px 1fr auto;
  gap: 10px;
  align-items: center;
  padding: 8px;
  border-radius: 10px;
  background: var(--surface-subtle, #f8fafc);
}
.path-remain img { width: 56px; height: 40px; object-fit: cover; border-radius: 8px; }
.path-remain-meta { min-width: 0; display: grid; gap: 2px; }
.path-remain-meta a { font-weight: 650; color: inherit; text-decoration: none; }
.path-remain-meta a:hover { color: var(--brand); }
.path-remain-meta em { font-style: normal; color: var(--text-muted); font-size: .85rem; font-weight: 600; }

@media (max-width: 980px) {
  .body-grid { grid-template-columns: 1fr; }
  .body-side .curr-sec { position: static; }
  .body-main .ticks { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
  .hero { padding: 28px 16px 40px; }
  .hero-inner { grid-template-columns: 1fr; gap: 22px; }
  .hero-main h1 { max-width: none; color: #f8fffc !important; }
  .offer { max-width: 420px; }
  .body { width: min(100%, calc(100% - 32px)); margin-top: 8px; }
  .body-main .ticks { grid-template-columns: 1fr; }
  .path-remain li { grid-template-columns: 1fr; }
}
</style>
