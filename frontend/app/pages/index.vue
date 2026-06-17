<script setup lang="ts">
import { computed } from 'vue'
import {
  Compass, Briefcase, GraduationCap, ShieldCheck, Sparkles,
  ArrowRight, BookOpen, User, PlayCircle, Users, UserPlus, Map,
  TrendingUp, Award, MessageCircle, CheckCircle,
} from 'lucide-vue-next'

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

const stats = computed(() => {
  const totalCourses = featuredCourses.value.length
  const totalStudents = featuredCourses.value.reduce((sum, c) => sum + (c.enrollments_count || 0), 0)
  const totalLessons = featuredCourses.value.reduce((sum, c) => sum + (c.lessons_count || 0), 0)
  return { totalCourses, totalStudents, totalLessons }
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

const features = [
  {
    icon: GraduationCap,
    title: 'Học tập thích nghi linh hoạt',
    desc: 'Hệ thống gợi ý bài giảng theo tiến trình riêng của từng người học, mềm dẻo thích nghi như cành liễu trước gió.',
    color: 'green',
  },
  {
    icon: ShieldCheck,
    title: 'Đánh giá & phát triển bền vững',
    desc: 'Theo dõi tiến trình tích lũy kiến thức sâu sắc qua thời gian, đảm bảo phát triển kỹ năng thực tế bền vững.',
    color: 'blue',
  },
  {
    icon: Sparkles,
    title: 'Trợ lý AI đồng hành thân thiện',
    desc: 'Trợ lý AI am hiểu sâu tài liệu học tập của bạn, giải đáp thắc mắc và gợi ý lộ trình nghề nghiệp cá nhân hóa.',
    color: 'accent',
  },
]

const steps = [
  { num: '01', icon: UserPlus, title: 'Đăng ký mục tiêu', desc: 'Chọn lộ trình học tập và định hướng kỹ năng bạn mong muốn tích lũy.' },
  { num: '02', icon: BookOpen, title: 'Tự do trải nghiệm', desc: 'Học qua video bài giảng, quiz tương tác, trao đổi cùng AI chatbot 24/7.' },
  { num: '03', icon: Award, title: 'Đạt chuẩn năng lực', desc: 'Đạt các cột mốc kiểm tra để nhận chứng chỉ số và khẳng định năng lực bền vững.' },
]

const googleMapsApiKey = (config.public.googleMapsApiKey as string | undefined) || ''
const mapQuery = encodeURIComponent('Hà Nội, Việt Nam')
const googleMapEmbedUrl = computed(() => {
  if (googleMapsApiKey) {
    return `https://www.google.com/maps/embed/v1/place?key=${googleMapsApiKey}&q=${mapQuery}`
  }
  return `https://www.google.com/maps?q=${mapQuery}&z=13&output=embed`
})
</script>

<template>
  <div class="home">

    <!-- ── Hero ─────────────────────────────── -->
    <section class="hero" aria-labelledby="hero-title">
      <div class="hero-bg" aria-hidden="true">
        <div class="hero-orb hero-orb-1" />
        <div class="hero-orb hero-orb-2" />
      </div>
      <div class="hero-inner">
        <p class="hero-kicker">
          <CheckCircle :size="14" :stroke-width="2" />
          Phát triển bền vững · Học tập thích nghi · Trợ lý AI đồng hành
        </p>
        <h1 id="hero-title" class="hero-title">
          Học tập thích nghi<br>
          cùng <span class="hero-accent">Sylva LMS</span>
        </h1>
        <p class="hero-lead">
          Hệ thống quản lý học tập thông minh thế hệ mới — thích nghi linh hoạt,
          đánh giá trực quan và trợ lý AI đồng hành suốt tiến trình của bạn.
        </p>
        <div class="hero-actions">
          <NuxtLink to="/courses" class="btn-primary">
            <Compass :size="18" :stroke-width="1.75" />
            Khám phá khoá học
          </NuxtLink>
          <NuxtLink to="/career" class="btn-ghost">
            <Briefcase :size="18" :stroke-width="1.75" />
            Lộ trình nghề nghiệp
          </NuxtLink>
        </div>
      </div>

      <!-- Stats bar -->
      <div class="hero-stats" role="list" aria-label="Thống kê nền tảng">
        <div class="hero-stat" role="listitem">
          <strong>{{ stats.totalCourses || '50' }}+</strong>
          <span>Khoá học</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true" />
        <div class="hero-stat" role="listitem">
          <strong>{{ stats.totalStudents.toLocaleString('vi-VN') || '2.000' }}+</strong>
          <span>Lượt ghi danh</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true" />
        <div class="hero-stat" role="listitem">
          <strong>{{ stats.totalLessons || '500' }}+</strong>
          <span>Bài học</span>
        </div>
        <div class="hero-stat-divider" aria-hidden="true" />
        <div class="hero-stat" role="listitem">
          <strong>{{ categories.length }}</strong>
          <span>Lĩnh vực</span>
        </div>
      </div>
    </section>

    <!-- ── Features ─────────────────────────── -->
    <section class="section features" aria-labelledby="features-title">
      <header class="section-head center">
        <p class="section-kicker">Vì sao chọn Sylva LMS</p>
        <h2 id="features-title">Một nền tảng — trọn vẹn trải nghiệm</h2>
        <p class="section-sub">Từ bài giảng linh hoạt đến chứng chỉ số, Sylva đồng hành mọi bước tiến trình của người học.</p>
      </header>
      <div class="features-grid">
        <article v-for="f in features" :key="f.title" class="feature-card" :class="`feature-card--${f.color}`">
          <div class="feature-icon-wrap">
            <component :is="f.icon" :size="24" :stroke-width="1.75" />
          </div>
          <h3>{{ f.title }}</h3>
          <p>{{ f.desc }}</p>
        </article>
      </div>
    </section>

    <!-- ── Categories ───────────────────────── -->
    <section v-if="categories.length" class="section categories" aria-labelledby="categories-title">
      <header class="section-head">
        <div>
          <p class="section-kicker">Danh mục học tập</p>
          <h2 id="categories-title">Khám phá theo lĩnh vực</h2>
        </div>
        <NuxtLink to="/courses" class="section-link">
          Xem tất cả <ArrowRight :size="15" :stroke-width="2" />
        </NuxtLink>
      </header>
      <div class="categories-grid">
        <NuxtLink
          v-for="cat in categories"
          :key="cat.id"
          :to="`/courses?category=${cat.slug || cat.id}`"
          class="category-card"
        >
          <div class="category-body">
            <h3>{{ cat.name }}</h3>
            <p v-if="cat.courses_count">{{ cat.courses_count }} khoá học</p>
          </div>
          <div class="category-arrow">
            <ArrowRight :size="16" :stroke-width="2" />
          </div>
        </NuxtLink>
      </div>
    </section>

    <!-- ── Featured Courses ─────────────────── -->
    <section v-if="featuredCourses.length" class="section courses" aria-labelledby="courses-title">
      <header class="section-head">
        <div>
          <p class="section-kicker">Khoá học nổi bật</p>
          <h2 id="courses-title">Bắt đầu học ngay hôm nay</h2>
        </div>
        <NuxtLink to="/courses" class="section-link">
          Tất cả khoá học <ArrowRight :size="15" :stroke-width="2" />
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
          <NuxtLink :to="`/courses/${course.id}`" class="course-thumb-link" :aria-label="course.title">
            <img
              v-if="course.thumbnail"
              :src="course.thumbnail"
              :alt="course.title"
              class="course-thumb"
              loading="lazy"
              width="320"
              height="180"
              itemprop="image"
            />
            <div v-else class="course-thumb course-thumb-placeholder" aria-hidden="true">
              <BookOpen :size="32" :stroke-width="1.5" />
            </div>
          </NuxtLink>

          <div class="course-body">
            <p v-if="course.category" class="course-category">{{ course.category.name }}</p>
            <h3 class="course-title">
              <NuxtLink :to="`/courses/${course.id}`" itemprop="name">{{ course.title }}</NuxtLink>
            </h3>
            <meta itemprop="description" :content="course.description || course.title" />

            <p v-if="course.instructor" class="course-instructor" itemprop="provider" itemscope itemtype="https://schema.org/Person">
              <User :size="13" :stroke-width="1.75" />
              <span itemprop="name">{{ course.instructor.name }}</span>
            </p>

            <footer class="course-foot">
              <span class="course-meta">
                <PlayCircle :size="13" :stroke-width="1.75" />
                {{ course.lessons_count || 0 }} bài
              </span>
              <span class="course-meta">
                <Users :size="13" :stroke-width="1.75" />
                {{ (course.enrollments_count || 0).toLocaleString('vi-VN') }}
              </span>
              <strong class="course-price" :class="{ 'is-free': !course.price || course.price === 0 }">
                {{ formatPrice(course.price) }}
              </strong>
            </footer>
          </div>
        </article>
      </div>
    </section>

    <!-- ── How it works ─────────────────────── -->
    <section class="section steps" aria-labelledby="steps-title">
      <header class="section-head center">
        <p class="section-kicker">Bắt đầu thế nào</p>
        <h2 id="steps-title">3 bước để phát triển năng lực</h2>
      </header>
      <ol class="steps-list">
        <li v-for="(step, idx) in steps" :key="step.num" class="step-item">
          <div class="step-connector" v-if="idx < steps.length - 1" aria-hidden="true" />
          <div class="step-icon-wrap">
            <component :is="step.icon" :size="22" :stroke-width="1.75" />
          </div>
          <span class="step-num">{{ step.num }}</span>
          <h3>{{ step.title }}</h3>
          <p>{{ step.desc }}</p>
        </li>
      </ol>
    </section>

    <!-- ── CTA band ──────────────────────────── -->
    <section class="cta" aria-labelledby="cta-title">
      <div class="cta-inner">
        <div class="cta-copy">
          <p class="section-kicker light">Bắt đầu ngay hôm nay</p>
          <h2 id="cta-title">Sẵn sàng cho hành trình<br>học tập bền vững?</h2>
          <p>Tham gia cùng hàng nghìn học viên đang phát triển bản thân trên Sylva LMS mỗi ngày.</p>
        </div>
        <div class="cta-actions">
          <NuxtLink to="/register" class="btn-cta-primary">
            <UserPlus :size="18" :stroke-width="1.75" />
            Tạo tài khoản miễn phí
          </NuxtLink>
          <NuxtLink to="/courses" class="btn-cta-ghost">
            Xem khoá học
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- ── Map ──────────────────────────────── -->
    <section class="section map" aria-labelledby="map-title">
      <div class="map-inner">
        <div class="map-copy">
          <p class="section-kicker">Liên hệ</p>
          <h2 id="map-title">Tìm đường đến văn phòng Sylva</h2>
          <p>Xem nhanh vị trí hỗ trợ kỹ thuật và vận hành hệ thống Sylva LMS trên bản đồ Google Maps.</p>
          <div class="map-actions">
            <a
              class="btn-primary"
              :href="`https://www.google.com/maps/search/?api=1&query=${mapQuery}`"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Map :size="18" :stroke-width="1.75" />
              Mở Google Maps
            </a>
            <NuxtLink to="/register" class="btn-secondary">
              Tạo tài khoản miễn phí
            </NuxtLink>
          </div>
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
/* ── Reset & wrapper ── */
.home {
  --section-gap: 96px;
  display: flex;
  flex-direction: column;
}

/* ── Common section base ── */
.section {
  max-width: 1280px;
  margin: 0 auto;
  padding: var(--section-gap) 24px;
  width: 100%;
}

/* ── Section header ── */
.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 40px;
}

.section-head.center {
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.section-kicker {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 10px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: var(--green);
}

.section-kicker.light {
  color: rgba(255, 255, 255, 0.65);
  letter-spacing: 0.2em;
}

.section-head h2 {
  margin: 0;
  font-size: clamp(1.5rem, 3vw, 2.1rem);
  font-weight: 700;
  letter-spacing: -0.03em;
  color: var(--text);
  line-height: 1.2;
}

.section-sub {
  margin: 12px 0 0;
  max-width: 52ch;
  color: var(--muted);
  line-height: 1.7;
}

.section-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--green);
  text-decoration: none;
  white-space: nowrap;
  transition: gap 150ms;
}

.section-link:hover {
  gap: 8px;
}

/* ── Shared buttons ── */
.btn-primary, .btn-secondary, .btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 46px;
  padding: 0 24px;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 600;
  text-decoration: none;
  transition: transform 200ms var(--ease-spring, cubic-bezier(0.34, 1.56, 0.64, 1)), background 150ms, box-shadow 150ms;
  cursor: pointer;
  border: none;
}

.btn-primary {
  background: var(--green);
  color: #fff;
  box-shadow: 0 4px 16px rgba(29, 158, 117, 0.25);
}

.btn-primary:hover {
  background: var(--green-deep);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(29, 158, 117, 0.3);
}

.btn-secondary {
  background: var(--green-soft);
  color: var(--green-deep);
  border: 1px solid rgba(29, 158, 117, 0.2);
}

.btn-secondary:hover {
  background: rgba(29, 158, 117, 0.15);
  transform: translateY(-2px);
}

.btn-ghost {
  background: transparent;
  color: rgba(255, 255, 255, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(4px);
}

.btn-ghost:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-2px);
}

/* ── Hero ── */
.hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(160deg, var(--green-deep) 0%, #0F6E56 40%, #1D9E75 100%);
  color: #fff;
  padding: 120px 24px 0;
  margin-top: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.hero-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
}

.hero-orb {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.04);
}

.hero-orb-1 {
  width: 600px;
  height: 600px;
  top: -200px;
  right: -100px;
}

.hero-orb-2 {
  width: 400px;
  height: 400px;
  bottom: 60px;
  left: -120px;
}

.hero-inner {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  max-width: 800px;
  width: 100%;
}

.hero-kicker {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 24px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: rgba(255, 255, 255, 0.65);
  background: rgba(255, 255, 255, 0.08);
  padding: 6px 16px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.hero-title {
  margin: 0;
  font-family: 'Outfit', sans-serif;
  font-size: clamp(2.5rem, 6vw, 4rem);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.04em;
  color: #fff;
}

.hero-accent {
  color: #9FE1CB;
}

.hero-lead {
  margin: 24px 0 40px;
  max-width: 56ch;
  font-size: 1.0625rem;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.8);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  margin-bottom: 80px;
}

/* Stats bar */
.hero-stats {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0;
  width: 100%;
  max-width: 760px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px 16px 0 0;
  padding: 24px 32px;
  backdrop-filter: blur(8px);
}

.hero-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
  min-width: 100px;
  padding: 8px 16px;
}

.hero-stat strong {
  font-family: 'Outfit', sans-serif;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.04em;
  color: #9FE1CB;
  line-height: 1;
}

.hero-stat span {
  font-size: 0.75rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.65);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.hero-stat-divider {
  width: 1px;
  height: 40px;
  background: rgba(255, 255, 255, 0.15);
  flex-shrink: 0;
}

/* ── Features ── */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}

.feature-card {
  padding: 32px 28px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 16px;
  transition: transform 250ms cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 250ms, border-color 200ms;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(31, 49, 43, 0.08);
}

.feature-card--green { border-top: 3px solid var(--green); }
.feature-card--blue  { border-top: 3px solid var(--secondary); }
.feature-card--accent { border-top: 3px solid var(--accent); }

.feature-card--green:hover { border-color: var(--green); }
.feature-card--blue:hover  { border-color: var(--secondary); }
.feature-card--accent:hover { border-color: var(--accent); }

.feature-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px; height: 52px;
  border-radius: 14px;
  background: var(--green-soft);
  color: var(--green);
  margin-bottom: 20px;
}

.feature-card--blue .feature-icon-wrap {
  background: var(--secondary-soft);
  color: var(--secondary);
}

.feature-card--accent .feature-icon-wrap {
  background: var(--accent-soft);
  color: var(--accent);
}

.feature-card h3 {
  margin: 0 0 10px;
  font-size: 1.0625rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text);
}

.feature-card p {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.7;
  color: var(--muted);
}

/* ── Categories ── */
.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.category-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 12px;
  text-decoration: none;
  color: var(--text);
  transition: all 200ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.category-card:hover {
  transform: translateY(-3px);
  border-color: rgba(29, 158, 117, 0.3);
  background: var(--green-soft);
  box-shadow: 0 8px 24px rgba(29, 158, 117, 0.1);
}

.category-body h3 {
  margin: 0 0 4px;
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--text);
}

.category-body p {
  margin: 0;
  font-size: 0.78rem;
  color: var(--muted);
}

.category-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px; height: 30px;
  border-radius: 8px;
  background: rgba(29, 158, 117, 0.08);
  color: var(--green);
  flex-shrink: 0;
  transition: background 150ms, transform 150ms;
}

.category-card:hover .category-arrow {
  background: var(--green);
  color: #fff;
  transform: translateX(2px);
}

/* ── Courses ── */
.courses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}

.course-card {
  display: flex;
  flex-direction: column;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 12px;
  overflow: hidden;
  transition: transform 200ms cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 200ms, border-color 200ms;
}

.course-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(31, 49, 43, 0.1);
  border-color: rgba(29, 158, 117, 0.2);
}

.course-thumb-link {
  display: block;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  flex-shrink: 0;
}

.course-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 350ms ease;
  display: block;
}

.course-card:hover .course-thumb {
  transform: scale(1.04);
}

.course-thumb-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--green-soft);
  color: var(--green);
  width: 100%; height: 100%;
}

.course-body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  flex: 1;
}

.course-category {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--green);
}

.course-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.4;
  letter-spacing: -0.01em;
  flex: 1;
}

.course-title a {
  color: var(--text);
  text-decoration: none;
  transition: color 150ms;
}

.course-title a:hover {
  color: var(--green);
}

.course-instructor {
  display: flex;
  align-items: center;
  gap: 5px;
  margin: 0;
  font-size: 0.8125rem;
  color: var(--muted);
}

.course-foot {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--line);
}

.course-meta {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.78rem;
  color: var(--muted);
}

.course-price {
  margin-left: auto;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text);
}

.course-price.is-free {
  color: var(--green);
}

/* ── Steps ── */
.steps-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 32px;
  position: relative;
}

.step-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
}

.step-connector {
  display: none;
}

@media (min-width: 768px) {
  .step-connector {
    display: block;
    position: absolute;
    top: 28px;
    left: calc(50% + 32px);
    right: calc(-50% + 32px);
    height: 1px;
    border-top: 2px dashed rgba(29, 158, 117, 0.25);
  }

  .step-item:last-child .step-connector {
    display: none;
  }
}

.step-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px; height: 56px;
  border-radius: 16px;
  background: var(--green-soft);
  color: var(--green);
  border: 2px solid rgba(29, 158, 117, 0.2);
  flex-shrink: 0;
}

.step-num {
  font-family: 'Outfit', sans-serif;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: var(--green);
  text-transform: uppercase;
}

.step-item h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
}

.step-item p {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.65;
  color: var(--muted);
  max-width: 22ch;
}

/* ── CTA band ── */
.cta {
  background: linear-gradient(135deg, var(--green-deep) 0%, #0F6E56 100%);
  padding: 80px 24px;
  margin: 80px 0;
}

.cta-inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
  flex-wrap: wrap;
}

.cta-copy h2 {
  margin: 10px 0 12px;
  font-size: clamp(1.5rem, 3vw, 2.25rem);
  font-weight: 700;
  letter-spacing: -0.04em;
  color: #fff;
  line-height: 1.15;
}

.cta-copy p {
  margin: 0;
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.75);
  max-width: 44ch;
  line-height: 1.65;
}

.cta-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.btn-cta-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  padding: 0 28px;
  border-radius: 8px;
  background: #fff;
  color: var(--green-deep);
  font-size: 0.9375rem;
  font-weight: 700;
  text-decoration: none;
  transition: transform 200ms, box-shadow 200ms;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.btn-cta-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
}

.btn-cta-ghost {
  display: inline-flex;
  align-items: center;
  height: 48px;
  padding: 0 24px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.2);
  font-size: 0.9375rem;
  font-weight: 600;
  text-decoration: none;
  transition: background 150ms;
}

.btn-cta-ghost:hover {
  background: rgba(255, 255, 255, 0.18);
}

/* ── Map ── */
.map { padding-bottom: var(--section-gap); }

.map-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 48px;
  align-items: center;
}

.map-copy h2 {
  margin: 0 0 16px;
  font-size: clamp(1.25rem, 2.5vw, 1.75rem);
  font-weight: 700;
  letter-spacing: -0.03em;
  color: var(--text);
}

.map-copy p {
  margin: 0 0 24px;
  font-size: 0.9375rem;
  line-height: 1.7;
  color: var(--muted);
}

.map-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.map-frame-wrap {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--line);
  aspect-ratio: 4 / 3;
}

.map-frame {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .map-inner {
    grid-template-columns: 1fr;
    gap: 32px;
  }
  .cta-inner {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 640px) {
  .hero {
    padding: 100px 16px 0;
  }
  .section {
    padding: 64px 16px;
  }
  .hero-stats {
    padding: 20px 16px;
    gap: 0;
  }
  .hero-stat-divider {
    display: none;
  }
  .hero-stat {
    flex: 1 0 50%;
    padding: 12px;
  }
  .hero-actions {
    flex-direction: column;
    align-items: stretch;
    margin-bottom: 48px;
  }
  .btn-primary, .btn-ghost, .btn-secondary {
    justify-content: center;
  }
  .cta {
    margin: 40px 0;
    padding: 60px 16px;
  }
}

/* ── Dark mode overrides ── */
[data-theme="dark"] .feature-card,
[data-theme="dark"] .course-card,
[data-theme="dark"] .category-card {
  background: rgba(255, 255, 255, 0.04);
  border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .feature-card:hover,
[data-theme="dark"] .course-card:hover,
[data-theme="dark"] .category-card:hover {
  background: rgba(255, 255, 255, 0.07);
}

[data-theme="dark"] .map-frame-wrap {
  border-color: rgba(255, 255, 255, 0.08);
}
</style>
