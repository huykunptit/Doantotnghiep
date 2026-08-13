<script setup lang="ts">
definePageMeta({ layout: 'default' })

interface Course {
  id: number
  slug?: string
  title: string
  description?: string
  thumbnail?: string | null
  price?: number
  enrollments_count?: number
  lessons_count?: number
  reviews_avg_rating?: number | string | null
  instructor?: { id: number; name: string } | null
  category?: { id: number; name: string; slug?: string } | null
}

interface Category {
  id: number
  name: string
  slug?: string
  courses_count?: number
}

/** Ba ngành chính quy — luôn public trên trang chủ (kể cả khi API lỗi SSR). */
const PUBLIC_MAJORS: Category[] = [
  { id: 15, name: 'Công nghệ thông tin', slug: 'cong-nghe-thong-tin', courses_count: 0 },
  { id: 19, name: 'Quản trị kinh doanh', slug: 'quan-tri-kinh-doanh', courses_count: 0 },
  { id: 21, name: 'Điện tử viễn thông', slug: 'dien-tu-vien-thong', courses_count: 0 },
]

const { settings } = useSiteSettings()
const brand = computed(() => settings.value.site_name || 'ERIPT LMS')
const tagline = computed(() => settings.value.site_description || 'Nền tảng học tập thích nghi, nuôi dưỡng tri thức lâu dài.')

const { data: coursesData } = await useAsyncData('home-courses', async () => {
  const featured = await useApi<{ data?: Course[] }>('/courses', {
    query: { per_page: 8, status: 'published', featured: 1 },
    token: null,
  }).catch(() => ({ data: [] as Course[] }))

  if (featured.data?.length) return featured

  // Chưa có khóa đánh dấu nổi bật → hiện khóa published mới nhất
  return await useApi<{ data?: Course[] }>('/courses', {
    query: { per_page: 8, status: 'published' },
    token: null,
  }).catch(() => ({ data: [] as Course[] }))
}, { getCachedData: () => undefined })

const { data: categoriesData } = await useAsyncData('home-categories', async () => {
  const list = await useApi<Category[]>('/courses/categories', { token: null }).catch(() => [] as Category[])
  return Array.isArray(list) ? list : []
}, { getCachedData: () => undefined })

const featuredCourses = computed(() => coursesData.value?.data ?? [])

const categories = computed(() => {
  const fromApi = categoriesData.value ?? []
  return PUBLIC_MAJORS.map((major) => {
    const hit = fromApi.find(c => c.slug === major.slug)
    return hit
      ? {
          id: hit.id,
          name: hit.name || major.name,
          slug: hit.slug || major.slug,
          courses_count: hit.courses_count ?? major.courses_count,
        }
      : { ...major }
  })
})

const heroLead = computed(() =>
  'Công nghệ thông tin, Quản trị kinh doanh và Điện tử viễn thông',
)

function formatPrice(price?: number) {
  if (!price) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

function formatRating(value?: number | string | null) {
  const rating = Number(value || 0)
  return rating > 0 ? rating.toFixed(1) : 'Mới'
}

useSeoMeta({
  title: `${brand.value} — Hệ thống học tập trực tuyến`,
  description: tagline.value,
})
</script>

<template>
  <div class="home">
    <section class="hero">
      <div class="hero-media" aria-hidden="true" />
      <div class="hero-shade" aria-hidden="true" />
      <div class="hero-inner">
        <p class="hero-brand">{{ brand }}</p>
        <h1>
          Học tập thích nghi cho<br>
          hành trình tri thức bền vững
        </h1>
        <p class="hero-lead">{{ heroLead }}</p>
        <div class="hero-actions">
          <Button label="Bắt đầu học ngay" icon="pi pi-arrow-right" icon-pos="right" size="large" @click="navigateTo('/register')" />
          <Button label="Khám phá khóa học" class="hero-secondary" outlined size="large" @click="navigateTo('/courses')" />
        </div>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <div>
          <h2>Lĩnh vực đào tạo</h2>
          <p>Ba ngành chính quy — chọn hướng đi phù hợp với lộ trình nghề nghiệp của bạn.</p>
        </div>
      </div>
      <div class="category-grid">
        <NuxtLink
          v-for="(category, index) in categories"
          :key="category.id"
          :to="`/courses?category=${category.slug || category.id}`"
          class="category-item"
        >
          <span class="cat-index">0{{ index + 1 }}</span>
          <strong>{{ category.name }}</strong>
          <span>{{ category.courses_count || 0 }} học phần / khóa học</span>
        </NuxtLink>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <div>
          <h2>Khóa học nổi bật</h2>
          <p>Nội dung được tuyển chọn để bắt đầu nhanh và học sâu.</p>
        </div>
        <NuxtLink to="/courses" class="section-link">Tất cả khóa học <i class="pi pi-arrow-right" /></NuxtLink>
      </div>
      <div class="course-grid">
        <NuxtLink
          v-for="course in featuredCourses"
          :key="course.id"
          :to="`/courses/${course.slug || course.id}`"
          class="course-item"
        >
          <div class="course-media" :style="course.thumbnail ? { backgroundImage: `url(${course.thumbnail})` } : undefined">
            <span v-if="!course.thumbnail"><i class="pi pi-book" /></span>
          </div>
          <div class="course-body">
            <small>{{ course.category?.name || 'Khóa học' }}</small>
            <strong>{{ course.title }}</strong>
            <p>{{ course.instructor?.name || 'Giảng viên Eript' }}</p>
            <div class="course-meta">
              <span><i class="pi pi-star-fill" /> {{ formatRating(course.reviews_avg_rating) }}</span>
              <span>{{ formatPrice(course.price) }}</span>
            </div>
          </div>
        </NuxtLink>
        <div v-if="!featuredCourses.length" class="empty-note">Chưa có khóa học nổi bật hoặc công khai. Hãy quay lại sau.</div>
      </div>
    </section>

    <section class="process">
      <div class="section-head">
        <div>
          <h2>Ba bước để bắt đầu</h2>
          <p>Từ đăng ký đến hoàn thành chứng chỉ trong một luồng rõ ràng.</p>
        </div>
      </div>
      <div class="process-grid">
        <div><span>01</span><strong>Tạo tài khoản</strong><p>Đăng ký miễn phí và xác minh email để mở hồ sơ học tập.</p></div>
        <div><span>02</span><strong>Chọn khóa học</strong><p>Duyệt danh mục, ghi danh và học theo tiến độ của riêng bạn.</p></div>
        <div><span>03</span><strong>Theo dõi kết quả</strong><p>Làm bài kiểm tra, nhận phản hồi và lưu chứng chỉ hoàn thành.</p></div>
      </div>
    </section>

    <section class="cta">
      <div>
        <h2>Sẵn sàng bắt đầu cùng {{ brand }}?</h2>
        <p>Tạo tài khoản ngay để truy cập khóa học, lịch học và hệ thống hỗ trợ học tập.</p>
      </div>
      <div class="cta-actions">
        <Button label="Đăng ký miễn phí" icon="pi pi-user-plus" @click="navigateTo('/register')" />
        <Button label="Tôi đã có tài khoản" class="cta-secondary" outlined @click="navigateTo('/login')" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.home {
  overflow: hidden;
}

.hero {
  position: relative;
  display: grid;
  align-items: center;
  min-height: calc(100dvh - 68px);
  margin: 0 0 56px;
  padding: 0;
  overflow: hidden;
  color: #f4faf8;
}

.hero-media {
  position: absolute;
  inset: 0;
  background-color: #0a1a3a;
  background-image: url('/images/hero-campus.png?v=7');
  background-position: center center;
  background-size: 100% 100%;
  background-repeat: no-repeat;
}

.hero-shade {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(90deg, rgba(6, 14, 36, .88) 0%, rgba(6, 14, 36, .62) 36%, rgba(8, 20, 48, .22) 58%, transparent 78%),
    linear-gradient(180deg, rgba(4, 10, 28, .1) 0%, rgba(4, 10, 28, .28) 100%);
}

.hero-inner {
  position: relative;
  z-index: 1;
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 48px 0;
  animation: hero-rise .7s ease both;
}

.hero-brand {
  margin: 0 0 14px;
  color: #9fd4ff;
  font-family: var(--font-display);
  font-size: clamp(2.2rem, 5.2vw, 3.75rem);
  font-weight: 800;
  letter-spacing: -.05em;
  line-height: 1;
}

.hero h1 {
  max-width: 18em;
  margin: 0 0 14px;
  color: #fff;
  font-family: var(--font-display);
  font-size: clamp(1.45rem, 2.8vw, 2.15rem);
  line-height: 1.28;
  letter-spacing: -.03em;
  font-weight: 500;
}

.hero-lead {
  max-width: 36em;
  margin: 0 0 26px;
  color: rgba(244, 250, 248, .82);
  font-size: 1.02rem;
  line-height: 1.6;
  font-weight: 400;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.hero-actions :deep(.hero-secondary.p-button),
.hero-actions :deep(.p-button.hero-secondary) {
  color: #fff !important;
  border: 1.5px solid rgba(255, 255, 255, .72) !important;
  background: rgba(255, 255, 255, .08) !important;
}

.hero-actions :deep(.hero-secondary.p-button:hover),
.hero-actions :deep(.p-button.hero-secondary:hover) {
  background: rgba(255, 255, 255, .16) !important;
  border-color: #fff !important;
}

@keyframes hero-rise {
  from { opacity: 0; transform: translateY(18px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
  .hero-inner { animation: none; }
}

.section,
.process,
.cta {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto 64px;
}

.section-head {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}

.section-head h2,
.process h2,
.cta h2 {
  margin: 0 0 6px;
  color: var(--text);
  font-size: clamp(1.35rem, 2.4vw, 1.85rem);
  letter-spacing: -.03em;
}

.section-head p,
.process p,
.cta p {
  margin: 0;
  color: var(--text-muted);
  line-height: 1.6;
}

.section-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--brand);
  font-size: .8rem;
  font-weight: 700;
  white-space: nowrap;
}

.category-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.category-item {
  display: grid;
  gap: 8px;
  padding: 22px 20px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  transition: border-color .16s ease, background .16s ease, transform .16s ease;
}

.category-item:hover {
  border-color: color-mix(in srgb, var(--brand) 45%, var(--border));
  background: var(--brand-soft);
  transform: translateY(-2px);
}

.cat-index {
  color: var(--brand);
  font-family: var(--font-display);
  font-size: .78rem;
  font-weight: 800;
  letter-spacing: .08em;
}

.category-item strong {
  display: block;
  color: var(--text);
  font-family: var(--font-display);
  font-size: 1.05rem;
  letter-spacing: -.02em;
}

.category-item span {
  color: var(--text-muted);
  font-size: .8rem;
  font-weight: 600;
}

.course-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.course-item {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--surface);
  transition: transform .18s ease, border-color .18s ease;
}

.course-item:hover {
  transform: translateY(-2px);
  border-color: var(--brand);
}

.course-media {
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 10;
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--brand) 35%, #12352f), #0f766e);
  background-size: cover;
  background-position: center;
  color: white;
  font-size: 1.6rem;
}

.course-body {
  display: grid;
  gap: 6px;
  padding: 14px;
}

.course-body small,
.course-body p {
  color: var(--text-muted);
  font-size: .68rem;
}

.course-body strong {
  color: var(--text);
  font-size: .86rem;
  line-height: 1.35;
}

.course-meta {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-top: 6px;
  color: var(--text);
  font-size: .74rem;
  font-weight: 650;
}

.course-meta span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.course-meta i {
  color: #f59e0b;
  font-size: .7rem;
}

.process-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}

.process-grid > div {
  padding-top: 8px;
  border-top: 2px solid var(--brand);
}

.process-grid span {
  display: block;
  margin-bottom: 10px;
  color: var(--brand);
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .08em;
}

.process-grid strong {
  display: block;
  margin-bottom: 8px;
  color: var(--text);
  font-size: 1rem;
}

.process-grid p {
  font-size: .82rem;
}

.cta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 36px;
  border-radius: 22px;
  background:
    radial-gradient(circle at 85% 20%, rgba(255, 255, 255, .12), transparent 28%),
    linear-gradient(135deg, #103d38, #0f766e);
  color: white;
}

.cta h2,
.cta p {
  color: white;
}

.cta p {
  max-width: 48ch;
  color: rgba(255, 255, 255, .74);
}

.cta-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.cta-actions :deep(.cta-secondary.p-button),
.cta-actions :deep(.p-button.cta-secondary) {
  color: #fff !important;
  border: 1.5px solid rgba(255, 255, 255, .75) !important;
  background: rgba(255, 255, 255, .08) !important;
}

.cta-actions :deep(.cta-secondary.p-button:hover),
.cta-actions :deep(.p-button.cta-secondary:hover) {
  color: #fff !important;
  border-color: #fff !important;
  background: rgba(255, 255, 255, .18) !important;
}

.empty-note {
  grid-column: 1 / -1;
  padding: 28px;
  border: 1px dashed var(--border);
  border-radius: 14px;
  color: var(--text-muted);
  text-align: center;
}

@media (max-width: 980px) {
  .course-grid,
  .category-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .hero {
    min-height: min(72dvh, 640px);
  }

  .hero-inner {
    padding: 56px 0 48px;
  }

  .process-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 700px) {
  .course-grid,
  .category-grid,
  .process-grid {
    grid-template-columns: 1fr;
  }

  .hero {
    min-height: 70dvh;
  }

  .hero-shade {
    background:
      linear-gradient(180deg, rgba(6, 14, 36, .45) 0%, rgba(6, 14, 36, .72) 55%, rgba(4, 10, 28, .88) 100%);
  }

  .section-head,
  .cta {
    flex-direction: column;
    align-items: flex-start;
  }

  .cta {
    padding: 24px;
  }
}
</style>
