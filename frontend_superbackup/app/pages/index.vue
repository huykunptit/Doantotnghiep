<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

definePageMeta({
  layout: 'default',
})

interface Course {
  id: number
  title: string
  slug?: string
  description?: string
  thumbnail?: string
  price?: number
  enrollments_count?: number
  lessons_count?: number
  reviews_avg_rating?: number | string | null
  instructor?: { id: number; name: string; avatar?: string } | null
  category?: { id: number; name: string; slug?: string } | null
}

interface Category {
  id: number
  name: string
  slug?: string
  courses_count?: number
}

const config = useRuntimeConfig()
const apiBase = config.public.apiBase
const { siteName, siteTagline } = useSiteSettings()

const { data: coursesData } = await useFetch<{ data?: Course[] }>(`${apiBase}/courses`, {
  key: 'home-courses',
  query: { per_page: 8, status: 'published' },
  default: () => ({ data: [] }),
})

const { data: categoriesData } = await useFetch<Category[]>(`${apiBase}/courses/categories`, {
  key: 'home-categories',
  default: () => [],
})

const featuredCourses = computed(() => coursesData.value?.data ?? [])
const categories = computed(() => (categoriesData.value ?? []).slice(0, 8))
const brand = computed(() => siteName.value || 'Sylva LMS')

const stats = computed(() => {
  const totalCourses = featuredCourses.value.length
  const totalStudents = featuredCourses.value.reduce((sum, c) => sum + (c.enrollments_count || 0), 0)
  const totalLessons = featuredCourses.value.reduce((sum, c) => sum + (c.lessons_count || 0), 0)
  return {
    totalCourses: totalCourses || 50,
    totalStudents: totalStudents || 2000,
    totalLessons: totalLessons || 500,
    totalFields: categories.value.length || 10,
  }
})

const formatPrice = (price?: number) => {
  if (!price || price === 0) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

const SITE_URL = 'https://sylva.edu.vn'
const SITE_NAME = 'Sylva LMS'
const SITE_DESCRIPTION =
  'Sylva LMS — Nền tảng học tập trực tuyến thích nghi thế hệ mới, được thiết kế tinh tế, hỗ trợ thi giám sát tự động và tích hợp trợ lý AI theo ngữ cảnh.'

useSeoMeta({
  title: 'Sylva LMS — Hệ thống học tập trực tuyến thích nghi & bền vững',
  description: SITE_DESCRIPTION,
  ogType: 'website',
  ogTitle: 'Sylva LMS — Nền tảng học tập thích nghi',
  ogDescription: SITE_DESCRIPTION,
  ogUrl: SITE_URL,
  ogSiteName: SITE_NAME,
  ogLocale: 'vi_VN',
  ogImage: `${SITE_URL}/og-cover.png`,
  twitterCard: 'summary_large_image',
  twitterTitle: 'Sylva LMS — Hệ thống học tập trực tuyến thích nghi & bền vững',
  twitterDescription: SITE_DESCRIPTION,
  twitterImage: `${SITE_URL}/og-cover.png`,
  robots: 'index,follow,max-image-preview:large',
  themeColor: '#1D9E75',
})

const courseListJsonLd = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'ItemList',
  itemListElement: featuredCourses.value.map((course, idx) => ({
    '@type': 'ListItem',
    position: idx + 1,
    url: `${SITE_URL}/courses/${course.id}`,
    name: course.title,
  })),
}))

useHead({
  htmlAttrs: { lang: 'vi' },
  link: [{ rel: 'canonical', href: SITE_URL }],
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'EducationalOrganization',
        name: SITE_NAME,
        url: SITE_URL,
        logo: `${SITE_URL}/logo.png`,
        description: SITE_DESCRIPTION,
      }),
    },
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: SITE_NAME,
        url: SITE_URL,
        potentialAction: {
          '@type': 'SearchAction',
          target: `${SITE_URL}/courses?search={search_term_string}`,
          'query-input': 'required name=search_term_string',
        },
      }),
    },
    { type: 'application/ld+json', innerHTML: JSON.stringify(courseListJsonLd.value) },
  ],
})

const pillars = [
  {
    icon: 'pi-sitemap',
    title: 'Học tập thích nghi',
    desc: 'Lộ trình và nội dung điều chỉnh theo tiến độ thực tế của từng người học.',
  },
  {
    icon: 'pi-chart-line',
    title: 'Đánh giá minh bạch',
    desc: 'Theo dõi năng lực theo thời gian với báo cáo rõ ràng, dễ hành động.',
  },
  {
    icon: 'pi-comments',
    title: 'Trợ lý AI đồng hành',
    desc: 'Hỗ trợ giải đáp trong ngữ cảnh bài học và gợi ý bước tiếp theo phù hợp.',
  },
]

const steps = [
  {
    num: '01',
    title: 'Chọn mục tiêu',
    desc: 'Xác định kỹ năng và lộ trình nghề nghiệp bạn muốn theo đuổi.',
  },
  {
    num: '02',
    title: 'Học có hệ thống',
    desc: 'Kết hợp video, bài tập và hỗ trợ AI để tiến bộ ổn định từng tuần.',
  },
  {
    num: '03',
    title: 'Chứng minh năng lực',
    desc: 'Hoàn thành cột mốc đánh giá và nhận chứng chỉ số xác thực.',
  },
]

const googleMapsApiKey = (config.public.googleMapsApiKey as string | undefined) || ''
const mapQuery = encodeURIComponent('Hà Nội, Việt Nam')
const googleMapEmbedUrl = computed(() => {
  if (googleMapsApiKey) {
    return `https://www.google.com/maps/embed/v1/place?key=${googleMapsApiKey}&q=${mapQuery}`
  }
  return `https://www.google.com/maps?q=${mapQuery}&z=13&output=embed`
})

const heroReady = ref(false)
onMounted(() => {
  requestAnimationFrame(() => {
    heroReady.value = true
  })
})
</script>

<template>
  <div class="home" :class="{ 'is-ready': heroReady }">
    <!-- Hero: one composition — brand, headline, lead, CTAs, full-bleed visual -->
    <section class="hero" aria-labelledby="hero-brand">
      <div class="hero-media" aria-hidden="true">
        <img
          src="/hoc-vien-cong-nghe-buu-chinh-vien-thong.jpg"
          alt=""
          class="hero-img"
          width="1920"
          height="1080"
          fetchpriority="high"
        >
        <div class="hero-shade" />
      </div>

      <div class="hero-content">
        <p id="hero-brand" class="hero-brand">{{ brand }}</p>
        <h1 class="hero-title">
          Học tập thích nghi cho hành trình phát triển bền vững
        </h1>
        <p class="hero-lead">
          {{ siteTagline || 'Nền tảng quản lý học tập thông minh — nội dung linh hoạt, đánh giá rõ ràng và trợ lý AI đồng hành suốt quá trình.' }}
        </p>
        <div class="hero-actions">
          <NuxtLink to="/courses" class="btn-primary">
            Khám phá khoá học
            <i class="pi pi-arrow-right" aria-hidden="true" />
          </NuxtLink>
          <NuxtLink to="/career" class="btn-ghost">
            Xem lộ trình nghề nghiệp
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- Trust metrics — outside hero -->
    <section class="metrics" aria-label="Thống kê nền tảng">
      <div class="metrics-inner">
        <div class="metric">
          <strong>{{ stats.totalCourses }}+</strong>
          <span>Khoá học</span>
        </div>
        <div class="metric">
          <strong>{{ stats.totalStudents.toLocaleString('vi-VN') }}+</strong>
          <span>Lượt ghi danh</span>
        </div>
        <div class="metric">
          <strong>{{ stats.totalLessons }}+</strong>
          <span>Bài học</span>
        </div>
        <div class="metric">
          <strong>{{ stats.totalFields }}+</strong>
          <span>Lĩnh vực</span>
        </div>
      </div>
    </section>

    <!-- Value pillars -->
    <section class="section pillars" aria-labelledby="pillars-title">
      <header class="section-head">
        <p class="section-kicker">Năng lực nền tảng</p>
        <h2 id="pillars-title">Một hệ thống, trọn trải nghiệm học tập</h2>
        <p class="section-sub">
          Sylva tập trung vào tiến trình thực của người học thay vì danh sách khoá học rời rạc.
        </p>
      </header>

      <ul class="pillars-list">
        <li v-for="(item, i) in pillars" :key="item.title" class="pillar" :style="{ '--d': `${i * 80}ms` }">
          <span class="pillar-icon" aria-hidden="true">
            <i :class="['pi', item.icon]" />
          </span>
          <div>
            <h3>{{ item.title }}</h3>
            <p>{{ item.desc }}</p>
          </div>
        </li>
      </ul>
    </section>

    <!-- Categories -->
    <section v-if="categories.length" class="section categories" aria-labelledby="categories-title">
      <header class="section-head row">
        <div>
          <p class="section-kicker">Danh mục</p>
          <h2 id="categories-title">Học theo lĩnh vực bạn quan tâm</h2>
        </div>
        <NuxtLink to="/courses" class="section-link">
          Tất cả danh mục
          <i class="pi pi-arrow-right" aria-hidden="true" />
        </NuxtLink>
      </header>

      <div class="categories-grid">
        <NuxtLink
          v-for="cat in categories"
          :key="cat.id"
          :to="`/courses?category=${cat.slug || cat.id}`"
          class="category-link"
        >
          <span class="category-name">{{ cat.name }}</span>
          <span v-if="cat.courses_count" class="category-count">{{ cat.courses_count }} khoá</span>
          <i class="pi pi-arrow-right" aria-hidden="true" />
        </NuxtLink>
      </div>
    </section>

    <!-- Featured courses — cards are interaction containers -->
    <section v-if="featuredCourses.length" class="section courses" aria-labelledby="courses-title">
      <header class="section-head row">
        <div>
          <p class="section-kicker">Khoá học nổi bật</p>
          <h2 id="courses-title">Bắt đầu với nội dung đã sẵn sàng</h2>
        </div>
        <NuxtLink to="/courses" class="section-link">
          Xem tất cả khoá học
          <i class="pi pi-arrow-right" aria-hidden="true" />
        </NuxtLink>
      </header>

      <div class="courses-grid">
        <article
          v-for="course in featuredCourses.slice(0, 8)"
          :key="course.id"
          class="course-card"
          itemscope
          itemtype="https://schema.org/Course"
        >
          <NuxtLink :to="`/courses/${course.id}`" class="course-media" :aria-label="course.title">
            <img
              v-if="course.thumbnail"
              :src="course.thumbnail"
              :alt="course.title"
              class="course-thumb"
              loading="lazy"
              width="320"
              height="180"
              itemprop="image"
            >
            <div v-else class="course-thumb course-thumb--empty" aria-hidden="true">
              <i class="pi pi-book" />
            </div>
          </NuxtLink>

          <div class="course-body">
            <p v-if="course.category" class="course-cat">{{ course.category.name }}</p>
            <h3 class="course-title">
              <NuxtLink :to="`/courses/${course.id}`" itemprop="name">{{ course.title }}</NuxtLink>
            </h3>
            <meta itemprop="description" :content="course.description || course.title">

            <p
              v-if="course.instructor"
              class="course-instructor"
              itemprop="provider"
              itemscope
              itemtype="https://schema.org/Person"
            >
              <span itemprop="name">{{ course.instructor.name }}</span>
            </p>

            <footer class="course-foot">
              <span>{{ course.lessons_count || 0 }} bài học</span>
              <strong :class="{ free: !course.price }">{{ formatPrice(course.price) }}</strong>
            </footer>
          </div>
        </article>
      </div>
    </section>

    <!-- How it works -->
    <section class="section process" aria-labelledby="process-title">
      <header class="section-head">
        <p class="section-kicker">Cách bắt đầu</p>
        <h2 id="process-title">Ba bước rõ ràng để tiến bộ</h2>
        <p class="section-sub">Không phức tạp hoá — tập trung vào mục tiêu, luyện tập và chứng minh năng lực.</p>
      </header>

      <ol class="process-list">
        <li v-for="step in steps" :key="step.num" class="process-item">
          <span class="process-num">{{ step.num }}</span>
          <h3>{{ step.title }}</h3>
          <p>{{ step.desc }}</p>
        </li>
      </ol>
    </section>

    <!-- CTA -->
    <section class="cta" aria-labelledby="cta-title">
      <div class="cta-inner">
        <h2 id="cta-title">Sẵn sàng học cùng {{ brand }}?</h2>
        <p>Tạo tài khoản miễn phí và bắt đầu lộ trình phù hợp với mục tiêu của bạn.</p>
        <div class="cta-actions">
          <NuxtLink to="/register" class="btn-primary">Tạo tài khoản miễn phí</NuxtLink>
          <NuxtLink to="/courses" class="btn-ghost btn-ghost--on-dark">Duyệt khoá học</NuxtLink>
        </div>
      </div>
    </section>

    <!-- Contact / map -->
    <section class="section map" aria-labelledby="map-title">
      <div class="map-grid">
        <div class="map-copy">
          <p class="section-kicker">Liên hệ</p>
          <h2 id="map-title">Văn phòng vận hành Sylva</h2>
          <p>
            Tra cứu vị trí hỗ trợ kỹ thuật và vận hành hệ thống trên bản đồ.
            Đội ngũ sẵn sàng hỗ trợ khi bạn cần.
          </p>
          <a
            class="btn-primary btn-primary--compact"
            :href="`https://www.google.com/maps/search/?api=1&query=${mapQuery}`"
            target="_blank"
            rel="noopener noreferrer"
          >
            Mở Google Maps
            <i class="pi pi-external-link" aria-hidden="true" />
          </a>
        </div>
        <div class="map-frame-wrap">
          <iframe
            class="map-frame"
            :src="googleMapEmbedUrl"
            title="Bản đồ Sylva LMS trên Google Maps"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen
          />
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home {
  --home-primary: var(--color-primary, #1d9e75);
  --home-primary-deep: var(--color-primary-hover, #178563);
  --home-ink: var(--text, #0d1f1a);
  --home-muted: var(--muted, #6b7c73);
  --home-line: var(--line, rgba(0, 0, 0, 0.09));
  --home-surface: var(--surface-strong, #fff);
  display: flex;
  flex-direction: column;
}

/* ── Hero ── */
.hero {
  position: relative;
  min-height: min(92vh, 860px);
  display: grid;
  align-items: end;
  overflow: hidden;
  color: #fff;
}

.hero-media {
  position: absolute;
  inset: 0;
}

.hero-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 35%;
  transform: scale(1.04);
  transition: transform 8s ease-out;
}

.home.is-ready .hero-img {
  transform: scale(1);
}

.hero-shade {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(4, 18, 12, 0.35) 0%, rgba(4, 18, 12, 0.55) 40%, rgba(4, 18, 12, 0.88) 100%),
    linear-gradient(90deg, rgba(4, 18, 12, 0.55) 0%, transparent 55%);
}

.hero-content {
  position: relative;
  z-index: 1;
  width: min(1120px, calc(100% - 48px));
  margin: 0 auto;
  padding: 120px 0 88px;
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 700ms ease, transform 700ms ease;
}

.home.is-ready .hero-content {
  opacity: 1;
  transform: translateY(0);
}

.hero-brand {
  margin: 0 0 18px;
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: clamp(2.75rem, 7vw, 5rem);
  font-weight: 900;
  letter-spacing: -0.055em;
  line-height: 0.95;
  color: #fff;
}

.hero-title {
  margin: 0;
  max-width: 18ch;
  font-size: clamp(1.35rem, 2.6vw, 1.85rem);
  font-weight: 600;
  letter-spacing: -0.03em;
  line-height: 1.25;
  color: rgba(255, 255, 255, 0.92);
}

.hero-lead {
  margin: 18px 0 0;
  max-width: 42ch;
  font-size: 1.05rem;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.72);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 32px;
}

/* ── Buttons ── */
.btn-primary,
.btn-ghost {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 22px;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 700;
  text-decoration: none;
  transition: background 180ms ease, border-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.btn-primary {
  background: var(--home-primary);
  color: #fff;
  border: 1px solid transparent;
}

.btn-primary:hover {
  background: var(--home-primary-deep);
  transform: translateY(-1px);
}

.btn-primary--compact {
  min-height: 44px;
  padding: 0 18px;
}

.btn-ghost {
  background: transparent;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.35);
}

.btn-ghost:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.55);
}

.btn-ghost--on-dark {
  color: rgba(255, 255, 255, 0.9);
}

/* Ghost on light surfaces (map / sections) */
.btn-ghost--light {
  color: var(--home-ink);
  border-color: var(--home-line);
}

.btn-ghost--light:hover {
  background: color-mix(in srgb, var(--home-primary) 8%, transparent);
  border-color: rgba(29, 158, 117, 0.35);
  color: var(--home-primary);
}

/* ── Metrics ── */
.metrics {
  background: var(--home-surface);
  border-bottom: 1px solid var(--home-line);
}

.metrics-inner {
  width: min(1120px, calc(100% - 48px));
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  padding: 28px 0;
}

.metric {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 12px;
}

.metric strong {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: var(--home-ink);
  line-height: 1;
}

.metric span {
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--home-muted);
}

/* ── Sections ── */
.section {
  width: min(1120px, calc(100% - 48px));
  margin: 0 auto;
  padding: 88px 0;
}

.section-head {
  max-width: 640px;
  margin-bottom: 40px;
}

.section-head.row {
  max-width: none;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.section-kicker {
  margin: 0 0 10px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--home-primary);
}

.section-head h2,
.map-copy h2 {
  margin: 0;
  font-size: clamp(1.55rem, 3vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.15;
  color: var(--home-ink);
}

.section-sub,
.map-copy > p:not(.section-kicker) {
  margin: 14px 0 0;
  font-size: 1rem;
  line-height: 1.7;
  color: var(--home-muted);
}

.section-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  font-size: 0.9rem;
  font-weight: 650;
  color: var(--home-primary);
  text-decoration: none;
  transition: gap 160ms ease;
}

.section-link:hover {
  gap: 10px;
}

/* ── Pillars ── */
.pillars-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 40px 32px;
  border-top: 1px solid var(--home-line);
  padding-top: 40px;
}

.pillar {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 16px;
  opacity: 0;
  transform: translateY(12px);
  animation: rise 600ms ease forwards;
  animation-delay: var(--d, 0ms);
  animation-play-state: paused;
}

.home.is-ready .pillar {
  animation-play-state: running;
}

.pillar-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  color: var(--home-primary);
  font-size: 1.2rem;
}

.pillar h3 {
  margin: 0 0 8px;
  font-size: 1.05rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  color: var(--home-ink);
}

.pillar p {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.65;
  color: var(--home-muted);
}

@keyframes rise {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ── Categories ── */
.categories {
  padding-top: 24px;
}

.categories-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0;
  border-top: 1px solid var(--home-line);
}

.category-link {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 12px;
  padding: 18px 4px;
  border-bottom: 1px solid var(--home-line);
  text-decoration: none;
  color: var(--home-ink);
  transition: color 160ms ease, padding-left 160ms ease;
}

.category-link:nth-child(odd) {
  padding-right: 24px;
}

.category-link:nth-child(even) {
  padding-left: 24px;
  border-left: 1px solid var(--home-line);
}

.category-link:hover {
  color: var(--home-primary);
}

.category-name {
  font-size: 1rem;
  font-weight: 650;
}

.category-count {
  font-size: 0.82rem;
  color: var(--home-muted);
}

.category-link .pi {
  font-size: 0.85rem;
  color: var(--home-muted);
  transition: transform 160ms ease, color 160ms ease;
}

.category-link:hover .pi {
  color: var(--home-primary);
  transform: translateX(3px);
}

/* ── Courses ── */
.courses-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.course-card {
  display: flex;
  flex-direction: column;
  background: var(--home-surface);
  border: 1px solid var(--home-line);
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 180ms ease, transform 180ms ease;
}

.course-card:hover {
  border-color: rgba(29, 158, 117, 0.35);
  transform: translateY(-2px);
}

.course-media {
  display: block;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: color-mix(in srgb, var(--home-primary) 10%, var(--home-surface));
}

.course-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 420ms ease;
}

.course-card:hover .course-thumb {
  transform: scale(1.04);
}

.course-thumb--empty {
  display: grid;
  place-items: center;
  color: var(--home-primary);
  font-size: 1.75rem;
}

.course-body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  flex: 1;
}

.course-cat {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--home-primary);
}

.course-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.4;
  letter-spacing: -0.015em;
  flex: 1;
}

.course-title a {
  color: var(--home-ink);
  text-decoration: none;
}

.course-title a:hover {
  color: var(--home-primary);
}

.course-instructor {
  margin: 0;
  font-size: 0.82rem;
  color: var(--home-muted);
}

.course-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--home-line);
  font-size: 0.82rem;
  color: var(--home-muted);
}

.course-foot strong {
  color: var(--home-ink);
  font-weight: 800;
}

.course-foot strong.free {
  color: var(--home-primary);
}

/* ── Process ── */
.process {
  border-top: 1px solid var(--home-line);
}

.process-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
}

.process-num {
  display: block;
  margin-bottom: 14px;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  color: var(--home-primary);
}

.process-item h3 {
  margin: 0 0 10px;
  font-size: 1.15rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  color: var(--home-ink);
}

.process-item p {
  margin: 0;
  max-width: 28ch;
  font-size: 0.95rem;
  line-height: 1.65;
  color: var(--home-muted);
}

/* ── CTA ── */
.cta {
  background:
    linear-gradient(135deg, #0a241a 0%, #123528 55%, #0f2f24 100%);
  color: #fff;
  padding: 88px 24px;
}

.cta-inner {
  width: min(720px, 100%);
  margin: 0 auto;
  text-align: center;
}

.cta-inner h2 {
  margin: 0;
  font-size: clamp(1.6rem, 3vw, 2.2rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.15;
}

.cta-inner p {
  margin: 14px auto 0;
  max-width: 42ch;
  font-size: 1rem;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.7);
}

.cta-actions {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}

/* ── Map ── */
.map {
  padding-bottom: 96px;
}

.map-grid {
  display: grid;
  grid-template-columns: 0.9fr 1.1fr;
  gap: 48px;
  align-items: center;
}

.map-copy .btn-primary {
  margin-top: 28px;
}

.map-frame-wrap {
  border: 1px solid var(--home-line);
  border-radius: 12px;
  overflow: hidden;
  aspect-ratio: 4 / 3;
  background: color-mix(in srgb, var(--home-primary) 8%, var(--home-surface));
}

.map-frame {
  width: 100%;
  height: 100%;
  border: 0;
  display: block;
}

/* ── Responsive ── */
@media (max-width: 1100px) {
  .courses-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 900px) {
  .courses-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .pillars-list,
  .process-list {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .map-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .hero-content,
  .metrics-inner,
  .section {
    width: min(1120px, calc(100% - 40px));
  }

  .section {
    padding: 72px 0;
  }

  .categories-grid {
    grid-template-columns: 1fr;
  }

  .category-link:nth-child(odd),
  .category-link:nth-child(even) {
    padding-left: 0;
    padding-right: 0;
    border-left: 0;
  }
}

@media (max-width: 640px) {
  .hero {
    min-height: min(84vh, 680px);
  }

  .hero-content {
    width: calc(100% - 32px);
    padding: 96px 0 56px;
  }

  .hero-brand {
    font-size: clamp(2.2rem, 11vw, 3.2rem);
  }

  .hero-title {
    max-width: none;
  }

  .metrics-inner,
  .section {
    width: calc(100% - 32px);
  }

  .metrics-inner {
    grid-template-columns: 1fr 1fr;
    padding: 18px 0;
    gap: 4px;
  }

  .metric {
    padding: 10px 6px;
  }

  .metric strong {
    font-size: 1.25rem;
  }

  .section {
    padding: 56px 0;
  }

  .section-head {
    margin-bottom: 28px;
  }

  .section-head.row {
    flex-direction: column;
    align-items: flex-start;
  }

  .courses-grid {
    grid-template-columns: 1fr;
  }

  .hero-actions,
  .cta-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .btn-primary,
  .btn-ghost {
    width: 100%;
  }

  .cta {
    padding: 56px 16px;
  }

  .map {
    padding-bottom: 64px;
  }

  .map-frame-wrap {
    aspect-ratio: 16 / 11;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-img,
  .hero-content,
  .pillar,
  .course-thumb {
    transition: none !important;
    animation: none !important;
    transform: none !important;
    opacity: 1 !important;
  }
}

/* Dark mode — rely on CSS vars; patch remaining hardcoded surfaces */
:global(.dark) .home,
:global([data-theme='dark']) .home {
  --home-ink: var(--text);
  --home-muted: var(--muted);
  --home-line: var(--line);
  --home-surface: var(--surface-strong);
}

:global(.dark) .metrics,
:global([data-theme='dark']) .metrics,
:global(.dark) .course-card,
:global([data-theme='dark']) .course-card {
  background: var(--surface-strong);
}

:global(.dark) .metrics,
:global([data-theme='dark']) .metrics {
  border-bottom-color: var(--line);
}

:global(.dark) .process,
:global([data-theme='dark']) .process,
:global(.dark) .pillars-list,
:global([data-theme='dark']) .pillars-list,
:global(.dark) .categories-grid,
:global([data-theme='dark']) .categories-grid,
:global(.dark) .category-link,
:global([data-theme='dark']) .category-link,
:global(.dark) .course-foot,
:global([data-theme='dark']) .course-foot {
  border-color: var(--line);
}

:global(.dark) .map-frame-wrap,
:global([data-theme='dark']) .map-frame-wrap {
  border-color: var(--line);
  background: color-mix(in srgb, var(--home-primary) 12%, var(--home-surface));
}

:global(.dark) .course-media,
:global([data-theme='dark']) .course-media {
  background: color-mix(in srgb, var(--home-primary) 14%, var(--home-surface));
}

:global(.dark) .cta,
:global([data-theme='dark']) .cta {
  background: linear-gradient(135deg, #061810 0%, #0c261c 55%, #0a1f18 100%);
}</style>
