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
  const totalStudents = featuredCourses.value.reduce(
    (sum, c) => sum + (c.enrollments_count || 0),
    0,
  )
  const totalLessons = featuredCourses.value.reduce(
    (sum, c) => sum + (c.lessons_count || 0),
    0,
  )
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

const SITE_URL = 'https://learn.ptit.edu.vn'
const SITE_NAME = 'PTIT Learning'
const SITE_DESCRIPTION =
  'Nền tảng học tập trực tuyến của Học viện Công nghệ Bưu chính Viễn thông — khoá học công nghệ, ngân hàng câu hỏi, kỳ thi giám sát và trợ lý AI tích hợp cho sinh viên và giảng viên PTIT.'

useSeoMeta({
  title: 'PTIT Learning — Học trực tuyến cho sinh viên & giảng viên PTIT',
  description: SITE_DESCRIPTION,
  ogType: 'website',
  ogTitle: 'PTIT Learning — Nền tảng học tập trực tuyến PTIT',
  ogDescription: SITE_DESCRIPTION,
  ogUrl: SITE_URL,
  ogSiteName: SITE_NAME,
  ogLocale: 'vi_VN',
  ogImage: `${SITE_URL}/og-cover.png`,
  twitterCard: 'summary_large_image',
  twitterTitle: 'PTIT Learning — Nền tảng học tập trực tuyến PTIT',
  twitterDescription: SITE_DESCRIPTION,
  twitterImage: `${SITE_URL}/og-cover.png`,
  robots: 'index,follow,max-image-preview:large',
  themeColor: '#2f7a45',
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
        alternateName: 'Học viện Công nghệ Bưu chính Viễn thông',
        url: SITE_URL,
        logo: `${SITE_URL}/logo.png`,
        sameAs: [
          'https://www.facebook.com/HocvienPTIT',
          'https://ptit.edu.vn',
        ],
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
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify(courseListJsonLd.value),
    },
  ],
})

const features = [
  {
    icon: 'school',
    title: 'Khoá học do giảng viên PTIT biên soạn',
    desc: 'Nội dung được kiểm duyệt theo chuẩn học thuật của Học viện, cập nhật theo chương trình mới nhất.',
  },
  {
    icon: 'verified',
    title: 'Kỳ thi giám sát trực tiếp',
    desc: 'Hệ thống live proctoring chuẩn Moodle, ghi nhận vi phạm tự động, can thiệp realtime khi cần.',
  },
  {
    icon: 'auto_awesome',
    title: 'Trợ lý AI theo ngữ cảnh',
    desc: 'Chatbot hiểu nội dung khoá học bạn đang xem — giải thích bài, gợi ý lộ trình nghề nghiệp.',
  },
]

const steps = [
  { num: '01', title: 'Đăng ký tài khoản', desc: 'Tạo tài khoản bằng email PTIT hoặc đăng nhập Google.' },
  { num: '02', title: 'Chọn khoá học', desc: 'Duyệt theo danh mục, lộ trình nghề nghiệp hoặc tìm kiếm theo từ khoá.' },
  { num: '03', title: 'Học và thi', desc: 'Xem video, làm quiz, tham gia kỳ thi và nhận chứng chỉ khi hoàn thành.' },
]
</script>

<template>
  <div class="home">
    <!-- Hero -->
    <section class="hero" aria-labelledby="hero-title">
      <div class="hero-inner">
        <p class="hero-kicker">Học viện Công nghệ Bưu chính Viễn thông</p>
        <h1 id="hero-title" class="hero-title">
          Học trực tuyến với <span class="hero-accent">PTIT Learning</span>
        </h1>
        <p class="hero-lead">
          Nền tảng học tập tập trung dành cho sinh viên và giảng viên PTIT — khoá học công nghệ,
          kỳ thi giám sát trực tiếp, ngân hàng câu hỏi và trợ lý AI tích hợp.
        </p>
        <div class="hero-actions">
          <NuxtLink to="/courses" class="btn-hero-primary">
            <span class="material-symbols-outlined">explore</span>
            Khám phá khoá học
          </NuxtLink>
          <NuxtLink to="/career" class="btn-hero-ghost">
            <span class="material-symbols-outlined">work</span>
            Lộ trình nghề nghiệp
          </NuxtLink>
        </div>

        <ul class="hero-trust">
          <li>
            <strong>{{ stats.totalCourses || '50' }}+</strong>
            <span>Khoá học</span>
          </li>
          <li>
            <strong>{{ stats.totalStudents.toLocaleString('vi-VN') || '2.000' }}+</strong>
            <span>Lượt ghi danh</span>
          </li>
          <li>
            <strong>{{ stats.totalLessons || '500' }}+</strong>
            <span>Bài học</span>
          </li>
          <li>
            <strong>{{ categories.length }}</strong>
            <span>Lĩnh vực</span>
          </li>
        </ul>
      </div>
    </section>

    <!-- Features -->
    <section class="features" aria-labelledby="features-title">
      <header class="section-head">
        <p class="section-kicker">Vì sao chọn PTIT Learning</p>
        <h2 id="features-title">Một nền tảng — đủ công cụ học, dạy và đánh giá</h2>
      </header>
      <div class="features-grid">
        <article v-for="f in features" :key="f.title" class="feature-card">
          <span class="feature-icon material-symbols-outlined">{{ f.icon }}</span>
          <h3>{{ f.title }}</h3>
          <p>{{ f.desc }}</p>
        </article>
      </div>
    </section>

    <!-- Categories -->
    <section v-if="categories.length" class="categories" aria-labelledby="categories-title">
      <header class="section-head">
        <div>
          <p class="section-kicker">Danh mục</p>
          <h2 id="categories-title">Khám phá theo lĩnh vực</h2>
        </div>
        <NuxtLink to="/courses" class="section-link">Xem tất cả →</NuxtLink>
      </header>
      <div class="categories-grid">
        <NuxtLink
          v-for="cat in categories"
          :key="cat.id"
          :to="`/courses?category=${cat.slug || cat.id}`"
          class="category-card"
        >
          <h3>{{ cat.name }}</h3>
          <p v-if="cat.courses_count">{{ cat.courses_count }} khoá học</p>
          <span class="material-symbols-outlined">arrow_forward</span>
        </NuxtLink>
      </div>
    </section>

    <!-- Featured courses -->
    <section v-if="featuredCourses.length" class="courses" aria-labelledby="courses-title">
      <header class="section-head">
        <div>
          <p class="section-kicker">Khoá học nổi bật</p>
          <h2 id="courses-title">Bắt đầu học ngay hôm nay</h2>
        </div>
        <NuxtLink to="/courses" class="section-link">Tất cả khoá học →</NuxtLink>
      </header>
      <div class="courses-grid">
        <article
          v-for="course in featuredCourses.slice(0, 8)"
          :key="course.id"
          class="course-card"
          itemscope
          itemtype="https://schema.org/Course"
        >
          <NuxtLink :to="`/courses/${course.id}`" class="course-thumb-link">
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
            <div v-else class="course-thumb course-thumb-placeholder">
              <span class="material-symbols-outlined">menu_book</span>
            </div>
          </NuxtLink>
          <div class="course-body">
            <p v-if="course.category" class="course-category">{{ course.category.name }}</p>
            <h3 class="course-title">
              <NuxtLink :to="`/courses/${course.id}`" itemprop="name">
                {{ course.title }}
              </NuxtLink>
            </h3>
            <meta itemprop="description" :content="course.description || course.title" />
            <p v-if="course.instructor" class="course-instructor" itemprop="provider" itemscope itemtype="https://schema.org/Person">
              <span class="material-symbols-outlined">person</span>
              <span itemprop="name">{{ course.instructor.name }}</span>
            </p>
            <footer class="course-foot">
              <span class="course-meta">
                <span class="material-symbols-outlined">play_circle</span>
                {{ course.lessons_count || 0 }} bài
              </span>
              <span class="course-meta">
                <span class="material-symbols-outlined">group</span>
                {{ course.enrollments_count || 0 }}
              </span>
              <strong class="course-price">{{ formatPrice(course.price) }}</strong>
            </footer>
          </div>
        </article>
      </div>
    </section>

    <!-- How it works -->
    <section class="steps" aria-labelledby="steps-title">
      <header class="section-head">
        <p class="section-kicker">Bắt đầu thế nào</p>
        <h2 id="steps-title">3 bước để bắt đầu học</h2>
      </header>
      <ol class="steps-list">
        <li v-for="step in steps" :key="step.num" class="step-item">
          <span class="step-num">{{ step.num }}</span>
          <h3>{{ step.title }}</h3>
          <p>{{ step.desc }}</p>
        </li>
      </ol>
    </section>

    <!-- Final CTA -->
    <section class="cta" aria-labelledby="cta-title">
      <div class="cta-inner">
        <h2 id="cta-title">Sẵn sàng bắt đầu hành trình học tập?</h2>
        <p>Tham gia cùng hàng nghìn sinh viên và giảng viên PTIT đang dùng PTIT Learning mỗi ngày.</p>
        <div class="cta-actions">
          <NuxtLink to="/register" class="btn-hero-primary">
            <span class="material-symbols-outlined">person_add</span>
            Tạo tài khoản miễn phí
          </NuxtLink>
          <NuxtLink to="/courses" class="btn-hero-ghost">
            Xem khoá học
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home {
  display: flex;
  flex-direction: column;
  gap: 80px;
  padding-bottom: 80px;
}

/* ── Hero ────────────────────────── */
.hero {
  position: relative;
  padding: 80px 24px 60px;
  background: #2f7a45;
  color: #fff;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.08), transparent 40%),
    radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05), transparent 50%);
  pointer-events: none;
}
.hero-inner {
  position: relative;
  max-width: 960px;
  margin: 0 auto;
  text-align: center;
}
.hero-kicker {
  margin: 0 0 16px;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: rgba(255, 255, 255, 0.8);
}
.hero-title {
  margin: 0;
  font-size: clamp(2rem, 5vw, 3.5rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.1;
}
.hero-accent {
  background: rgba(255, 255, 255, 0.15);
  padding: 0 12px;
  border-radius: 12px;
  display: inline-block;
}
.hero-lead {
  margin: 24px auto 32px;
  max-width: 640px;
  font-size: 1.05rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.85);
}
.hero-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
}
.btn-hero-primary,
.btn-hero-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  padding: 0 24px;
  border-radius: 999px;
  font-size: 0.95rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}
.btn-hero-primary {
  background: #fff;
  color: #2f7a45;
}
.btn-hero-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
}
.btn-hero-ghost {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.3);
}
.btn-hero-ghost:hover {
  background: rgba(255, 255, 255, 0.18);
}
.btn-hero-primary .material-symbols-outlined,
.btn-hero-ghost .material-symbols-outlined {
  font-size: 20px;
}

.hero-trust {
  list-style: none;
  margin: 56px auto 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 24px;
  max-width: 760px;
  border-top: 1px solid rgba(255, 255, 255, 0.18);
  padding-top: 32px;
}
.hero-trust li {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.hero-trust strong {
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.hero-trust span {
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.75);
}

/* ── Section common ────────────────────────── */
section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  width: 100%;
}
section.hero { max-width: none; padding-left: 24px; padding-right: 24px; }
section.cta { max-width: none; }

.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 32px;
}
.section-kicker {
  margin: 0 0 8px;
  font-size: 0.74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: var(--on-surface-variant, #5f675f);
}
.section-head h2 {
  margin: 0;
  font-size: clamp(1.5rem, 3vw, 2rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--on-surface, #111);
}
.section-link {
  font-size: 0.86rem;
  font-weight: 700;
  color: #2f7a45;
  text-decoration: none;
}
.section-link:hover { text-decoration: underline; }

/* ── Features ────────────────────────── */
.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 20px;
}
.feature-card {
  padding: 28px 26px;
  background: #fff;
  border: 1px solid rgba(17, 17, 17, 0.06);
  border-radius: 18px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.feature-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 32px -16px rgba(17, 17, 17, 0.16);
}
.feature-icon {
  display: inline-grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(47, 122, 69, 0.1);
  color: #2f7a45;
  font-size: 28px;
  margin-bottom: 18px;
}
.feature-card h3 {
  margin: 0 0 8px;
  font-size: 1.08rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--on-surface, #111);
}
.feature-card p {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.65;
  color: var(--on-surface-variant, #5f675f);
}

/* ── Categories ────────────────────────── */
.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}
.category-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  background: #fff;
  border: 1px solid rgba(17, 17, 17, 0.08);
  border-radius: 14px;
  text-decoration: none;
  color: var(--on-surface, #111);
  transition: all 0.18s ease;
}
.category-card:hover {
  border-color: rgba(47, 122, 69, 0.4);
  background: rgba(47, 122, 69, 0.04);
  transform: translateY(-2px);
}
.category-card h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
}
.category-card p {
  margin: 2px 0 0;
  font-size: 0.78rem;
  color: var(--on-surface-variant);
}
.category-card .material-symbols-outlined {
  font-size: 18px;
  color: #2f7a45;
  flex-shrink: 0;
}

/* ── Courses ────────────────────────── */
.courses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}
.course-card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border: 1px solid rgba(17, 17, 17, 0.06);
  border-radius: 18px;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.course-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 32px -16px rgba(17, 17, 17, 0.18);
}
.course-thumb-link { display: block; }
.course-thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  background: rgba(47, 122, 69, 0.08);
}
.course-thumb-placeholder {
  display: grid;
  place-items: center;
  color: rgba(47, 122, 69, 0.5);
}
.course-thumb-placeholder .material-symbols-outlined { font-size: 48px; }

.course-body { display: flex; flex-direction: column; gap: 8px; padding: 16px 18px 18px; flex: 1; }
.course-category {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #2f7a45;
}
.course-title { margin: 0; font-size: 0.98rem; font-weight: 700; line-height: 1.4; }
.course-title a {
  color: var(--on-surface, #111);
  text-decoration: none;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.course-title a:hover { color: #2f7a45; }
.course-instructor {
  margin: 0;
  font-size: 0.82rem;
  color: var(--on-surface-variant);
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.course-instructor .material-symbols-outlined { font-size: 14px; }
.course-foot {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid rgba(17, 17, 17, 0.06);
}
.course-meta {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.78rem;
  color: var(--on-surface-variant);
}
.course-meta .material-symbols-outlined { font-size: 14px; }
.course-price {
  margin-left: auto;
  font-size: 0.92rem;
  font-weight: 800;
  color: #2f7a45;
  font-variant-numeric: tabular-nums;
}

/* ── Steps ────────────────────────── */
.steps-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}
.step-item {
  position: relative;
  padding: 28px 26px 24px;
  background: #fff;
  border: 1px solid rgba(17, 17, 17, 0.06);
  border-radius: 18px;
}
.step-num {
  display: inline-block;
  font-size: 1.6rem;
  font-weight: 900;
  letter-spacing: -0.03em;
  color: rgba(47, 122, 69, 0.4);
  margin-bottom: 8px;
  font-variant-numeric: tabular-nums;
}
.step-item h3 {
  margin: 0 0 8px;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--on-surface);
}
.step-item p {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--on-surface-variant);
}

/* ── CTA ────────────────────────── */
.cta {
  padding: 0 24px;
}
.cta-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 56px 32px;
  background: #2f7a45;
  color: #fff;
  border-radius: 28px;
  text-align: center;
}
.cta-inner h2 {
  margin: 0 0 12px;
  font-size: clamp(1.4rem, 3vw, 1.9rem);
  font-weight: 800;
  letter-spacing: -0.03em;
}
.cta-inner p {
  margin: 0 auto 24px;
  max-width: 560px;
  font-size: 0.95rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.85);
}
.cta-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
}

@media (max-width: 600px) {
  .home { gap: 56px; }
  .hero { padding: 56px 20px 40px; }
  .section-head { flex-direction: column; align-items: flex-start; }
}
</style>
