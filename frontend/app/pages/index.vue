<script setup lang="ts">
import { computed } from 'vue'

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
    icon: 'graduation-cap',
    title: 'Học tập thích nghi linh hoạt',
    desc: 'Hệ thống gợi ý bài giảng theo tiến trình riêng của từng người học, mềm dẻo thích nghi như cành liễu trước gió.',
    color: 'green',
  },
  {
    icon: 'chart-bar',
    title: 'Đánh giá & phát triển bền vững',
    desc: 'Theo dõi tiến trình tích lũy kiến thức sâu sắc qua thời gian, đảm bảo phát triển kỹ năng thực tế bền vững.',
    color: 'blue',
  },
  {
    icon: 'sparkles',
    title: 'Trợ lý AI đồng hành thân thiện',
    desc: 'Trợ lý AI am hiểu sâu tài liệu học tập của bạn, giải đáp thắc mắc và gợi ý lộ trình nghề nghiệp cá nhân hóa.',
    color: 'accent',
  },
]

const steps = [
  { num: '01', icon: 'user-plus', title: 'Đăng ký mục tiêu', desc: 'Chọn lộ trình học tập và định hướng kỹ năng bạn mong muốn tích lũy.' },
  { num: '02', icon: 'book', title: 'Tự do trải nghiệm', desc: 'Học qua video bài giảng, quiz tương tác, trao đổi cùng AI chatbot 24/7.' },
  { num: '03', icon: 'verified', title: 'Đạt chuẩn năng lực', desc: 'Đạt các cột mốc kiểm tra để nhận chứng chỉ số và khẳng định năng lực bền vững.' },
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
      <!-- Background layers -->
      <div class="hero-bg" aria-hidden="true">
        <div class="hero-grid" />
        <div class="hero-glow hero-glow-1" />
        <div class="hero-glow hero-glow-2" />
        <div class="hero-glow hero-glow-3" />
      </div>

      <div class="hero-inner">
        <!-- Eyebrow badge -->
        <div class="hero-badge">
          <span class="hero-badge-dot" />
          Nền tảng học tập thích nghi thế hệ mới
        </div>

        <h1 id="hero-title" class="hero-title">
          Phát triển bản thân<br>
          cùng <em class="hero-em">Sylva LMS</em>
        </h1>

        <p class="hero-lead">
          Hệ thống quản lý học tập thông minh — thích nghi linh hoạt, đánh giá
          trực quan và trợ lý AI đồng hành suốt hành trình của bạn.
        </p>

        <div class="hero-checks">
          <span v-for="item in ['Miễn phí đăng ký', 'AI hỗ trợ 24/7', 'Chứng chỉ số']" :key="item" class="hero-check">
            <i class="pi pi-check-circle" style="font-size:0.875rem" />
            {{ item }}
          </span>
        </div>

        <div class="hero-actions">
          <NuxtLink to="/courses" class="btn-hero-primary">
            <i class="pi pi-compass" style="font-size:1.125rem" />
            Khám phá khoá học
            <i class="pi pi-chevron-right" style="font-size:1.0rem" />
          </NuxtLink>
          <NuxtLink to="/career" class="btn-hero-ghost">
            <i class="pi pi-briefcase" style="font-size:1.062rem" />
            Lộ trình nghề nghiệp
          </NuxtLink>
        </div>
      </div>

      <!-- Stats floating shelf -->
      <div class="hero-stats" role="list" aria-label="Thống kê nền tảng">
        <div class="hero-stat" role="listitem">
          <strong>{{ stats.totalCourses || '50' }}+</strong>
          <span>Khoá học</span>
        </div>
        <div class="hero-stat-sep" aria-hidden="true" />
        <div class="hero-stat" role="listitem">
          <strong>{{ stats.totalStudents.toLocaleString('vi-VN') || '2.000' }}+</strong>
          <span>Lượt ghi danh</span>
        </div>
        <div class="hero-stat-sep" aria-hidden="true" />
        <div class="hero-stat" role="listitem">
          <strong>{{ stats.totalLessons || '500' }}+</strong>
          <span>Bài học</span>
        </div>
        <div class="hero-stat-sep" aria-hidden="true" />
        <div class="hero-stat" role="listitem">
          <strong>{{ categories.length || '10' }}+</strong>
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
          <div class="feature-icon-wrap" :class="`feature-icon-wrap--${f.color}`">
            <i :class="`pi pi-${f.icon}`" style="font-size:1.375rem" />
          </div>
          <h3>{{ f.title }}</h3>
          <p>{{ f.desc }}</p>
          <div class="feature-bottom-link">
            Tìm hiểu thêm <i class="pi pi-arrow-right" style="font-size:0.875rem" />
          </div>
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
          Xem tất cả <i class="pi pi-arrow-right" style="font-size:0.9375rem" />
        </NuxtLink>
      </header>
      <div class="categories-grid">
        <NuxtLink
          v-for="cat in categories"
          :key="cat.id"
          :to="`/courses?category=${cat.slug || cat.id}`"
          class="category-card"
        >
          <div class="category-icon">
            <i class="pi pi-bolt" style="font-size:1.125rem" />
          </div>
          <div class="category-body">
            <h3>{{ cat.name }}</h3>
            <p v-if="cat.courses_count">{{ cat.courses_count }} khoá học</p>
          </div>
          <i class="pi pi-arrow-right" style="font-size:0.9375rem" />
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
          Tất cả khoá học <i class="pi pi-arrow-right" style="font-size:0.9375rem" />
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
              <i class="pi pi-book" style="font-size:2.0rem" />
            </div>
            <div class="course-thumb-overlay">
              <div class="course-play-btn">
                <i class="pi pi-play-circle" style="font-size:1.75rem" />
              </div>
            </div>
          </NuxtLink>

          <div class="course-body">
            <p v-if="course.category" class="course-category">{{ course.category.name }}</p>
            <h3 class="course-title">
              <NuxtLink :to="`/courses/${course.id}`" itemprop="name">{{ course.title }}</NuxtLink>
            </h3>
            <meta itemprop="description" :content="course.description || course.title" />

            <p v-if="course.instructor" class="course-instructor" itemprop="provider" itemscope itemtype="https://schema.org/Person">
              <i class="pi pi-user" style="font-size:0.8125rem" />
              <span itemprop="name">{{ course.instructor.name }}</span>
            </p>

            <footer class="course-foot">
              <span class="course-meta">
                <i class="pi pi-play-circle" style="font-size:0.8125rem" />
                {{ course.lessons_count || 0 }} bài
              </span>
              <span class="course-meta">
                <i class="pi pi-users" style="font-size:0.8125rem" />
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
      <div class="steps-bg" aria-hidden="true" />
      <header class="section-head center">
        <p class="section-kicker">Bắt đầu thế nào</p>
        <h2 id="steps-title">3 bước để phát triển năng lực</h2>
        <p class="section-sub">Đơn giản, nhanh chóng và hiệu quả ngay từ ngày đầu tiên.</p>
      </header>
      <ol class="steps-list">
        <li v-for="(step, idx) in steps" :key="step.num" class="step-item">
          <div class="step-num-badge">{{ step.num }}</div>
          <div v-if="idx < steps.length - 1" class="step-connector" aria-hidden="true" />
          <div class="step-icon-wrap">
            <i :class="`pi pi-${step.icon}`" style="font-size:1.5rem" />
          </div>
          <h3>{{ step.title }}</h3>
          <p>{{ step.desc }}</p>
        </li>
      </ol>
    </section>

    <!-- ── CTA band ──────────────────────────── -->
    <section class="cta" aria-labelledby="cta-title">
      <div class="cta-bg" aria-hidden="true">
        <div class="cta-glow" />
      </div>
      <div class="cta-inner">
        <div class="cta-copy">
          <p class="cta-kicker">Bắt đầu ngay hôm nay</p>
          <h2 id="cta-title">Sẵn sàng cho hành trình<br>học tập bền vững?</h2>
          <p>Tham gia cùng hàng nghìn học viên đang phát triển bản thân trên Sylva LMS mỗi ngày.</p>
        </div>
        <div class="cta-actions">
          <NuxtLink to="/register" class="btn-cta-primary">
            <i class="pi pi-user-plus" style="font-size:1.125rem" />
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
              class="btn-map-primary"
              :href="`https://www.google.com/maps/search/?api=1&query=${mapQuery}`"
              target="_blank"
              rel="noopener noreferrer"
            >
              <i class="pi pi-map" style="font-size:1.062rem" />
              Mở Google Maps
            </a>
            <NuxtLink to="/register" class="btn-map-secondary">
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
/* ── Base ── */
.home {
  display: flex;
  flex-direction: column;
}

.section {
  max-width: 1280px;
  margin: 0 auto;
  padding: 88px 24px;
  width: 100%;
}

/* ── Section header ── */
.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 44px;
}

.section-head.center {
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.section-kicker {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 10px;
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: var(--green);
}

.section-head h2 {
  margin: 0;
  font-size: clamp(1.6rem, 3.2vw, 2.2rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  color: var(--text);
  line-height: 1.15;
}

.section-sub {
  margin: 14px 0 0;
  max-width: 52ch;
  font-size: 0.9625rem;
  color: var(--muted);
  line-height: 1.75;
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

.section-link:hover { gap: 8px; }

/* ── Hero ── */
.hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 50%, #163d2a 100%);
  color: #fff;
  padding: 130px 24px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.hero-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

/* Subtle grid lines */
.hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(29,158,117,0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(29,158,117,0.05) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 0%, transparent 100%);
}

.hero-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
}

.hero-glow-1 {
  width: 500px; height: 500px;
  top: -200px; right: -100px;
  background: radial-gradient(circle, rgba(29,158,117,0.18) 0%, transparent 70%);
}

.hero-glow-2 {
  width: 400px; height: 400px;
  bottom: 40px; left: -120px;
  background: radial-gradient(circle, rgba(29,158,117,0.1) 0%, transparent 70%);
}

.hero-glow-3 {
  width: 320px; height: 320px;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  background: radial-gradient(circle, rgba(29,158,117,0.07) 0%, transparent 70%);
}

.hero-inner {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  max-width: 820px;
  width: 100%;
}

/* Badge */
.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 28px;
  padding: 7px 18px;
  background: rgba(29,158,117,0.12);
  border: 1px solid rgba(29,158,117,0.3);
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: #6eedc7;
}

.hero-badge-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--green);
  box-shadow: 0 0 0 3px rgba(29,158,117,0.25);
  animation: pulse-dot 2s ease infinite;
  flex-shrink: 0;
}

@keyframes pulse-dot {
  0%, 100% { box-shadow: 0 0 0 3px rgba(29,158,117,0.25); }
  50% { box-shadow: 0 0 0 6px rgba(29,158,117,0.1); }
}

.hero-title {
  margin: 0;
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: clamp(2.6rem, 6.5vw, 4.2rem);
  font-weight: 900;
  line-height: 1.08;
  letter-spacing: -0.05em;
  color: #fff;
}

.hero-em {
  font-style: normal;
  background: linear-gradient(90deg, #1D9E75 0%, #34d39b 50%, #6eedc7 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-lead {
  margin: 24px 0 32px;
  max-width: 54ch;
  font-size: 1.0625rem;
  line-height: 1.75;
  color: rgba(255,255,255,0.72);
}

.hero-checks {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 16px;
  margin-bottom: 36px;
}

.hero-check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgba(255,255,255,0.65);
}

.hero-check svg { color: var(--green); flex-shrink: 0; }

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  margin-bottom: 80px;
}

.btn-hero-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 50px;
  padding: 0 28px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--green) 0%, #0d7a5a 100%);
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 4px 20px rgba(29,158,117,0.4), inset 0 1px 0 rgba(255,255,255,0.15);
  transition: transform 200ms cubic-bezier(0.34,1.56,0.64,1), box-shadow 200ms;
}

.btn-hero-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(29,158,117,0.5);
}

.btn-hero-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 50px;
  padding: 0 24px;
  border-radius: 10px;
  background: rgba(255,255,255,0.07);
  color: rgba(255,255,255,0.88);
  border: 1px solid rgba(255,255,255,0.15);
  font-size: 0.9375rem;
  font-weight: 600;
  text-decoration: none;
  backdrop-filter: blur(4px);
  transition: background 150ms, border-color 150ms, transform 200ms cubic-bezier(0.34,1.56,0.64,1);
}

.btn-hero-ghost:hover {
  background: rgba(255,255,255,0.12);
  border-color: rgba(255,255,255,0.3);
  transform: translateY(-2px);
}

/* Hero stats shelf */
.hero-stats {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  width: 100%;
  max-width: 740px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-bottom: none;
  border-radius: 20px 20px 0 0;
  padding: 28px 32px;
  backdrop-filter: blur(12px);
}

.hero-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
  min-width: 100px;
  padding: 4px 12px;
}

.hero-stat strong {
  font-family: 'Be Vietnam Pro', sans-serif;
  font-size: 1.85rem;
  font-weight: 900;
  letter-spacing: -0.05em;
  color: #6eedc7;
  line-height: 1;
}

.hero-stat span {
  font-size: 0.72rem;
  font-weight: 600;
  color: rgba(255,255,255,0.5);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.hero-stat-sep {
  width: 1px;
  height: 36px;
  background: rgba(255,255,255,0.1);
  flex-shrink: 0;
}

/* ── Features ── */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
}

.feature-card {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 32px 28px 28px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 18px;
  overflow: hidden;
  transition: transform 280ms cubic-bezier(0.34,1.56,0.64,1), box-shadow 280ms;
}

.feature-card::before {
  content: '';
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity 250ms;
  border-radius: inherit;
  pointer-events: none;
}

.feature-card--green::before { background: linear-gradient(135deg, rgba(29,158,117,0.04) 0%, transparent 60%); }
.feature-card--blue::before  { background: linear-gradient(135deg, rgba(59,130,246,0.04) 0%, transparent 60%); }
.feature-card--accent::before{ background: linear-gradient(135deg, rgba(139,92,246,0.04) 0%, transparent 60%); }

.feature-card:hover { transform: translateY(-5px); box-shadow: 0 20px 48px rgba(0,0,0,0.08); }
.feature-card:hover::before { opacity: 1; }

.feature-card--green { border-top: 3px solid var(--green); }
.feature-card--blue  { border-top: 3px solid #3b82f6; }
.feature-card--accent{ border-top: 3px solid #8b5cf6; }

.feature-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px; height: 52px;
  border-radius: 14px;
  margin-bottom: 22px;
}

.feature-icon-wrap--green  { background: rgba(29,158,117,0.1); color: var(--green); }
.feature-icon-wrap--blue   { background: rgba(59,130,246,0.1); color: #3b82f6; }
.feature-icon-wrap--accent { background: rgba(139,92,246,0.1); color: #8b5cf6; }

.feature-card h3 {
  margin: 0 0 10px;
  font-size: 1.0625rem;
  font-weight: 700;
  letter-spacing: -0.025em;
  color: var(--text);
}

.feature-card p {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.75;
  color: var(--muted);
  flex: 1;
}

.feature-bottom-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--green);
  transition: gap 150ms;
}

.feature-card:hover .feature-bottom-link { gap: 8px; }

/* ── Categories ── */
.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}

.category-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 14px;
  text-decoration: none;
  color: var(--text);
  transition: all 220ms cubic-bezier(0.34,1.56,0.64,1);
}

.category-card:hover {
  transform: translateY(-3px);
  border-color: rgba(29,158,117,0.35);
  box-shadow: 0 8px 24px rgba(29,158,117,0.1);
}

.category-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 10px;
  background: rgba(29,158,117,0.08);
  color: var(--green);
  flex-shrink: 0;
  transition: background 150ms, color 150ms;
}

.category-card:hover .category-icon {
  background: var(--green);
  color: #fff;
}

.category-body {
  flex: 1;
  min-width: 0;
}

.category-body h3 {
  margin: 0 0 2px;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.category-body p {
  margin: 0;
  font-size: 0.75rem;
  color: var(--muted);
}

.category-arrow {
  color: var(--muted);
  flex-shrink: 0;
  transition: color 150ms, transform 150ms;
}

.category-card:hover .category-arrow {
  color: var(--green);
  transform: translateX(3px);
}

/* ── Courses ── */
.courses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(264px, 1fr));
  gap: 20px;
}

.course-card {
  display: flex;
  flex-direction: column;
  background: var(--surface-strong, #fff);
  border: 1px solid var(--line);
  border-radius: 14px;
  overflow: hidden;
  transition: transform 220ms cubic-bezier(0.34,1.56,0.64,1), box-shadow 220ms, border-color 220ms;
}

.course-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(0,0,0,0.1);
  border-color: rgba(29,158,117,0.25);
}

.course-thumb-link {
  display: block;
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  flex-shrink: 0;
}

.course-thumb {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 400ms ease;
  display: block;
}

.course-card:hover .course-thumb { transform: scale(1.05); }

.course-thumb-overlay {
  position: absolute;
  inset: 0;
  background: rgba(4,12,8,0);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 250ms;
}

.course-card:hover .course-thumb-overlay { background: rgba(4,12,8,0.35); }

.course-play-btn {
  color: #fff;
  opacity: 0;
  transform: scale(0.8);
  transition: opacity 250ms, transform 250ms cubic-bezier(0.34,1.56,0.64,1);
}

.course-card:hover .course-play-btn { opacity: 1; transform: scale(1); }

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
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--green);
}

.course-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.4;
  letter-spacing: -0.015em;
  flex: 1;
}

.course-title a {
  color: var(--text);
  text-decoration: none;
  transition: color 150ms;
}

.course-title a:hover { color: var(--green); }

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
  font-size: 0.875rem;
  font-weight: 800;
  color: var(--text);
}

.course-price.is-free { color: var(--green); }

/* ── Steps ── */
.steps {
  position: relative;
}

.steps-bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 0%, rgba(29,158,117,0.025) 50%, transparent 100%);
  pointer-events: none;
}

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
  gap: 14px;
}

.step-num-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.65rem;
  font-weight: 900;
  letter-spacing: 0.12em;
  color: rgba(29,158,117,0.6);
  background: var(--green-soft, rgba(29,158,117,0.06));
  border: 1px solid rgba(29,158,117,0.15);
  border-radius: 999px;
  padding: 2px 10px;
}

.step-connector {
  position: absolute;
  top: 44px;
  left: calc(50% + 40px);
  right: calc(-50% + 40px);
  height: 0;
  border-top: 2px dashed rgba(29,158,117,0.2);
  display: none;
}

@media (min-width: 768px) {
  .step-connector { display: block; }
  .step-item:last-child .step-connector { display: none; }
}

.step-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px; height: 64px;
  border-radius: 18px;
  background: rgba(29,158,117,0.07);
  color: var(--green);
  border: 2px solid rgba(29,158,117,0.18);
  margin-top: 24px;
  flex-shrink: 0;
  transition: background 200ms, border-color 200ms, color 200ms, transform 200ms;
}

.step-item:hover .step-icon-wrap {
  background: var(--green);
  color: #fff;
  border-color: var(--green);
  transform: translateY(-4px);
}

.step-item h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--text);
}

.step-item p {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.7;
  color: var(--muted);
  max-width: 22ch;
}

/* ── CTA band ── */
.cta {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #071812 0%, #0d2e1e 50%, #163d2a 100%);
  padding: 96px 24px;
  margin: 16px 0;
}

.cta-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.cta-glow {
  position: absolute;
  width: 600px; height: 400px;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  background: radial-gradient(ellipse, rgba(29,158,117,0.15) 0%, transparent 70%);
  filter: blur(40px);
}

.cta-inner {
  position: relative;
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
  flex-wrap: wrap;
}

.cta-kicker {
  font-size: 0.7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: rgba(29,158,117,0.9);
  margin: 0 0 12px;
}

.cta-copy h2 {
  margin: 0 0 14px;
  font-size: clamp(1.6rem, 3.2vw, 2.4rem);
  font-weight: 900;
  letter-spacing: -0.05em;
  color: #fff;
  line-height: 1.1;
}

.cta-copy p {
  margin: 0;
  font-size: 1rem;
  color: rgba(255,255,255,0.65);
  max-width: 44ch;
  line-height: 1.7;
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
  gap: 9px;
  height: 50px;
  padding: 0 30px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--green) 0%, #0d7a5a 100%);
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 700;
  text-decoration: none;
  box-shadow: 0 4px 20px rgba(29,158,117,0.4);
  transition: transform 200ms cubic-bezier(0.34,1.56,0.64,1), box-shadow 200ms;
}

.btn-cta-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(29,158,117,0.5);
}

.btn-cta-ghost {
  display: inline-flex;
  align-items: center;
  height: 50px;
  padding: 0 24px;
  border-radius: 10px;
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.88);
  border: 1px solid rgba(255,255,255,0.15);
  font-size: 0.9375rem;
  font-weight: 600;
  text-decoration: none;
  transition: background 150ms, border-color 150ms;
}

.btn-cta-ghost:hover {
  background: rgba(255,255,255,0.15);
  border-color: rgba(255,255,255,0.3);
}

/* ── Map ── */
.map { padding-bottom: 96px; }

.map-inner {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 56px;
  align-items: center;
}

.map-copy h2 {
  margin: 0 0 14px;
  font-size: clamp(1.3rem, 2.6vw, 1.8rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--text);
  line-height: 1.2;
}

.map-copy p {
  margin: 0 0 28px;
  font-size: 0.9375rem;
  line-height: 1.75;
  color: var(--muted);
}

.map-actions { display: flex; gap: 12px; flex-wrap: wrap; }

.btn-map-primary, .btn-map-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 44px;
  padding: 0 22px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  transition: transform 200ms cubic-bezier(0.34,1.56,0.64,1), box-shadow 200ms;
}

.btn-map-primary {
  background: var(--green);
  color: #fff;
  box-shadow: 0 4px 14px rgba(29,158,117,0.25);
}

.btn-map-primary:hover {
  background: var(--green-deep);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(29,158,117,0.35);
}

.btn-map-secondary {
  background: var(--green-soft, rgba(29,158,117,0.08));
  color: var(--green-deep, #0d6b4f);
  border: 1px solid rgba(29,158,117,0.2);
}

.btn-map-secondary:hover {
  background: rgba(29,158,117,0.14);
  transform: translateY(-2px);
}

.map-frame-wrap {
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--line);
  aspect-ratio: 4 / 3;
  box-shadow: 0 8px 32px rgba(0,0,0,0.06);
}

.map-frame {
  width: 100%; height: 100%;
  border: none;
  display: block;
}

/* ── Responsive ── */
@media (max-width: 960px) {
  .map-inner { grid-template-columns: 1fr; gap: 32px; }
  .cta-inner { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 640px) {
  .hero { padding: 110px 16px 0; }
  .section { padding: 64px 16px; }

  .hero-stats {
    flex-wrap: wrap;
    padding: 20px 16px;
    max-width: 100%;
  }

  .hero-stat-sep { display: none; }

  .hero-stat {
    flex: 1 0 40%;
    padding: 10px;
  }

  .hero-actions {
    flex-direction: column;
    align-items: stretch;
    margin-bottom: 52px;
  }

  .btn-hero-primary, .btn-hero-ghost {
    justify-content: center;
  }

  .cta { padding: 72px 16px; margin: 0; }
}

/* ── Dark mode ── */
:global([data-theme="dark"]) .feature-card,
:global([data-theme="dark"]) .course-card,
:global([data-theme="dark"]) .category-card {
  background: rgba(255,255,255,0.03);
  border-color: rgba(255,255,255,0.07);
}

:global([data-theme="dark"]) .feature-card:hover { box-shadow: 0 20px 48px rgba(0,0,0,0.4); }
:global([data-theme="dark"]) .course-card:hover  { box-shadow: 0 16px 40px rgba(0,0,0,0.4); }

:global([data-theme="dark"]) .map-frame-wrap { border-color: rgba(255,255,255,0.07); }

:global([data-theme="dark"]) .btn-map-secondary {
  background: rgba(29,158,117,0.1);
  color: #6eedc7;
  border-color: rgba(29,158,117,0.2);
}
</style>
