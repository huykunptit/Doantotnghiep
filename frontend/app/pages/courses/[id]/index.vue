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
  instructor?: { id?: number, name?: string, avatar?: string | null } | null
  category?: { name?: string } | null
  lessons?: LessonItem[]
}

const route = useRoute()
const auth = useAuthStore()
const toast = useToast()
const { t, locale } = useI18n()
const courseId = computed(() => Number(route.params.id))
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

async function load() {
  loading.value = true
  try {
    const [detail, sectionRes] = await Promise.all([
      useApi<CourseDetail>(`/courses/${courseId.value}`),
      useApi<{ data?: SectionItem[] }>(`/courses/${courseId.value}/sections`).catch(() => ({ data: [] })),
    ])
    course.value = detail
    sections.value = sectionRes.data || []
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

function primaryAction() {
  if (!auth.isAuthenticated) return navigateTo(`/login?redirect=${encodeURIComponent(`/courses/${courseId.value}`)}`)
  if (course.value?.is_enrolled) return navigateTo(`/learn/${courseId.value}`)
  return navigateTo(`/checkout/${courseId.value}`)
}

const ctaLabel = computed(() => {
  if (!auth.isAuthenticated) return t('student.catalog.loginToBuy')
  if (course.value?.is_enrolled) return t('student.catalog.learnNow')
  if ((course.value?.price || 0) <= 0) return t('student.catalog.enrollFree')
  return t('student.catalog.buy')
})

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
        ? { backgroundImage: `linear-gradient(105deg, rgba(8,20,18,.92) 0%, rgba(8,20,18,.72) 48%, rgba(8,20,18,.35) 100%), url(${course.thumbnail})` }
        : undefined"
    >
      <div class="hero-inner">
        <div class="hero-main">
          <p class="kicker">{{ course.category?.name || t('student.catalog.title') }}</p>
          <h1>{{ course.title }}</h1>
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
            <Button :label="ctaLabel" class="offer-cta" @click="primaryAction" />
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
      <section v-if="outcomes.length" class="sec">
        <h2>{{ t('student.detail.outcomes') }}</h2>
        <ul class="ticks">
          <li v-for="(item, i) in outcomes" :key="`o-${i}`" v-html="item" />
        </ul>
      </section>

      <section v-if="benefits.length" class="sec">
        <h2>{{ t('student.detail.benefits') }}</h2>
        <ul class="ticks soft">
          <li v-for="(item, i) in benefits" :key="`b-${i}`" v-html="item" />
        </ul>
      </section>

      <section class="sec">
        <h2>{{ t('student.detail.about') }}</h2>
        <div class="prose" v-html="course.description || '—'" />
      </section>

      <section v-if="requirements.length" class="sec">
        <h2>{{ t('student.detail.requirements') }}</h2>
        <ul class="dots">
          <li v-for="(item, i) in requirements" :key="`r-${i}`" v-html="item" />
        </ul>
      </section>

      <section class="sec">
        <h2>{{ t('student.learn.curriculum') }}</h2>
        <div v-if="sections.length" class="curr">
          <article v-for="section in sections" :key="section.id">
            <button type="button" @click="toggleSection(section.id)">
              <strong>{{ section.title }}</strong>
              <span>{{ section.lessons?.length || 0 }}</span>
            </button>
            <ul v-if="openSections.includes(section.id)">
              <li v-for="lesson in (section.lessons || [])" :key="lesson.id">{{ lesson.title }}</li>
            </ul>
          </article>
        </div>
        <ol v-else class="dots">
          <li v-for="lesson in (course.lessons || [])" :key="lesson.id">{{ lesson.title }}</li>
          <li v-if="!(course.lessons || []).length">—</li>
        </ol>
      </section>
    </div>
  </div>
</template>

<style scoped>
.state { padding: 64px 24px; text-align: center; color: var(--text-muted); }

.hero {
  background: #0b1f1c;
  background-size: cover;
  background-position: center;
  color: #f4faf8;
  padding: 48px 24px 64px;
}
.hero-inner {
  width: min(1120px, 100%);
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 40px;
  align-items: start;
}
.kicker {
  margin: 0 0 10px;
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #9ad5cb;
}
.hero-main h1 {
  margin: 0 0 14px;
  font-family: var(--font-display);
  font-size: clamp(2rem, 4vw, 2.75rem);
  line-height: 1.15;
  font-weight: 700;
  max-width: 18ch;
}
.lede {
  margin: 0 0 16px;
  max-width: 42ch;
  font-size: 1.05rem;
  line-height: 1.55;
  color: rgba(244, 250, 248, .84);
  font-weight: 500;
}
.byline {
  margin: 0;
  color: rgba(244, 250, 248, .7);
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
  width: min(720px, calc(100% - 48px));
  margin: -8px auto 72px;
  padding-top: 36px;
  display: grid;
  gap: 36px;
}
.sec h2 {
  margin: 0 0 14px;
  font-size: 1.35rem;
  font-family: var(--font-display);
}
.ticks {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 24px;
}
.ticks li {
  position: relative;
  padding-left: 22px;
  line-height: 1.45;
  font-weight: 550;
}
.ticks li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: var(--brand);
  font-weight: 800;
}
.ticks.soft li::before { content: '•'; color: var(--text-muted); }
.prose { line-height: 1.7; font-weight: 500; color: var(--text); }
.dots { margin: 0; padding-left: 1.1rem; display: grid; gap: 8px; font-weight: 550; }
/* Items are authored in the rich text editor, so they arrive wrapped in <p>. */
.ticks li :deep(p),
.dots li :deep(p) { margin: 0; }

.curr { display: grid; gap: 8px; border-top: 1px solid var(--border); }
.curr article { border-bottom: 1px solid var(--border); }
.curr button {
  width: 100%;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  color: inherit;
  font: inherit;
  text-align: left;
}
.curr button span { color: var(--text-muted); font-weight: 650; }
.curr ul {
  margin: 0 0 12px;
  padding: 0 0 0 4px;
  list-style: none;
  display: grid;
  gap: 8px;
  color: var(--text-muted);
  font-weight: 550;
}

@media (max-width: 900px) {
  .hero { padding: 28px 16px 40px; }
  .hero-inner { grid-template-columns: 1fr; gap: 22px; }
  .hero-main h1 { max-width: none; }
  .offer { max-width: 420px; }
  .body { width: min(100%, calc(100% - 32px)); margin-top: 8px; }
  .ticks { grid-template-columns: 1fr; }
}
</style>
