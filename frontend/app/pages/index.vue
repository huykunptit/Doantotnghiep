<script setup lang="ts">
definePageMeta({ layout: 'default' })

interface Course {
  id: number
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

const { settings } = useSiteSettings()
const brand = computed(() => settings.value.site_name || 'Sylva LMS')
const tagline = computed(() => settings.value.site_description || 'Nền tảng học tập thích nghi, nuôi dưỡng tri thức lâu dài.')

const { data: coursesData } = await useAsyncData('home-courses', () =>
  useApi<{ data?: Course[] }>('/courses', {
    query: { per_page: 8, status: 'published' },
    token: null,
  }).catch(() => ({ data: [] as Course[] })),
)

const { data: categoriesData } = await useAsyncData('home-categories', () =>
  useApi<Category[]>('/courses/categories', { token: null }).catch(() => [] as Category[]),
)

const featuredCourses = computed(() => coursesData.value?.data ?? [])
const categories = computed(() => (categoriesData.value ?? []).slice(0, 8))

const stats = computed(() => {
  const students = featuredCourses.value.reduce((sum, course) => sum + (course.enrollments_count || 0), 0)
  const lessons = featuredCourses.value.reduce((sum, course) => sum + (course.lessons_count || 0), 0)
  return [
    { label: 'Khóa học nổi bật', value: featuredCourses.value.length || '—' },
    { label: 'Lượt ghi danh', value: students || '—' },
    { label: 'Bài học', value: lessons || '—' },
    { label: 'Lĩnh vực', value: categories.value.length || '—' },
  ]
})

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
      <div class="hero-copy">
        <p class="hero-brand">{{ brand }}</p>
        <h1>Học tập thích nghi cho hành trình tri thức bền vững.</h1>
        <p class="hero-lead">{{ tagline }}</p>
        <div class="hero-actions">
          <Button label="Bắt đầu học ngay" icon="pi pi-arrow-right" icon-pos="right" size="large" @click="navigateTo('/register')" />
          <Button label="Khám phá khóa học" severity="secondary" outlined size="large" @click="navigateTo('/courses')" />
        </div>
      </div>
      <div class="hero-visual" aria-hidden="true">
        <div class="hero-orb" />
        <div class="hero-panel">
          <span>Học kỳ đang diễn ra</span>
          <strong>Tiến độ trung bình 78%</strong>
          <div class="hero-bars">
            <i style="--w:72%" /><i style="--w:88%" /><i style="--w:64%" /><i style="--w:91%" />
          </div>
          <small>Theo dõi lớp học, kỳ thi và chứng chỉ trong một không gian.</small>
        </div>
      </div>
    </section>

    <section class="stats-band" aria-label="Thống kê">
      <div v-for="item in stats" :key="item.label" class="stat-item">
        <strong>{{ item.value }}</strong>
        <span>{{ item.label }}</span>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <div>
          <h2>Lĩnh vực đào tạo</h2>
          <p>Chọn hướng đi phù hợp với lộ trình nghề nghiệp của bạn.</p>
        </div>
        <NuxtLink to="/courses" class="section-link">Xem tất cả <i class="pi pi-arrow-right" /></NuxtLink>
      </div>
      <div class="category-grid">
        <NuxtLink
          v-for="category in categories"
          :key="category.id"
          :to="`/courses?category=${category.slug || category.id}`"
          class="category-item"
        >
          <strong>{{ category.name }}</strong>
          <span>{{ category.courses_count || 0 }} khóa học</span>
        </NuxtLink>
        <div v-if="!categories.length" class="empty-note">Danh mục sẽ xuất hiện khi hệ thống có dữ liệu công khai.</div>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <div>
          <h2>Khóa học nổi bật</h2>
          <p>Nội dung được tuyển chọn để bắt đầu nhanh và học sâu.</p>
        </div>
        <NuxtLink to="/courses" class="section-link">Duyệt khoá học <i class="pi pi-arrow-right" /></NuxtLink>
      </div>
      <div class="course-grid">
        <NuxtLink
          v-for="course in featuredCourses"
          :key="course.id"
          :to="`/courses/${course.id}`"
          class="course-item"
        >
          <div class="course-media" :style="course.thumbnail ? { backgroundImage: `url(${course.thumbnail})` } : undefined">
            <span v-if="!course.thumbnail"><i class="pi pi-book" /></span>
          </div>
          <div class="course-body">
            <small>{{ course.category?.name || 'Khóa học' }}</small>
            <strong>{{ course.title }}</strong>
            <p>{{ course.instructor?.name || 'Giảng viên Sylva' }}</p>
            <div class="course-meta">
              <span><i class="pi pi-star-fill" /> {{ formatRating(course.reviews_avg_rating) }}</span>
              <span>{{ formatPrice(course.price) }}</span>
            </div>
          </div>
        </NuxtLink>
        <div v-if="!featuredCourses.length" class="empty-note">Chưa có khóa học công khai. Hãy quay lại sau.</div>
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
        <Button label="Tôi đã có tài khoản" severity="secondary" outlined @click="navigateTo('/login')" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.home {
  overflow: hidden;
}

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(280px, .95fr);
  gap: 28px;
  align-items: end;
  width: min(1180px, calc(100% - 32px));
  min-height: calc(100dvh - 68px);
  margin: 0 auto;
  padding: 48px 0 36px;
}

.hero-brand {
  margin: 0 0 14px;
  color: var(--brand);
  font-size: clamp(1.8rem, 4vw, 3.4rem);
  font-weight: 800;
  letter-spacing: -.06em;
  line-height: 1;
}

.hero h1 {
  max-width: 14ch;
  margin: 0 0 16px;
  color: var(--text);
  font-size: clamp(1.55rem, 3vw, 2.35rem);
  line-height: 1.2;
  letter-spacing: -.035em;
  font-weight: 650;
}

.hero-lead {
  max-width: 42ch;
  margin: 0 0 28px;
  color: var(--text-muted);
  font-size: 1rem;
  line-height: 1.7;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.hero-visual {
  position: relative;
  min-height: 420px;
}

.hero-orb {
  position: absolute;
  inset: 8% 4% auto auto;
  width: min(420px, 100%);
  aspect-ratio: 1;
  border-radius: 40% 60% 55% 45%;
  background:
    radial-gradient(circle at 30% 30%, rgba(255, 255, 255, .28), transparent 40%),
    linear-gradient(145deg, #0f766e, #134e4a 55%, #0b3d39);
  filter: saturate(1.05);
}

.hero-panel {
  position: absolute;
  right: 0;
  bottom: 28px;
  left: 12%;
  display: grid;
  gap: 8px;
  padding: 22px;
  border: 1px solid color-mix(in srgb, white 18%, transparent);
  border-radius: 18px;
  background: color-mix(in srgb, #0b3d39 72%, transparent);
  color: white;
  backdrop-filter: blur(18px);
}

.hero-panel span,
.hero-panel small {
  color: rgba(255, 255, 255, .72);
  font-size: .72rem;
}

.hero-panel strong {
  font-size: 1.35rem;
  letter-spacing: -.03em;
}

.hero-bars {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  height: 72px;
  margin: 8px 0;
}

.hero-bars i {
  display: block;
  align-self: end;
  height: var(--w);
  border-radius: 8px 8px 4px 4px;
  background: linear-gradient(180deg, #7dd3c7, #2dd4bf);
}

.stats-band {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1px;
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto 56px;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--border);
}

.stat-item {
  display: grid;
  gap: 4px;
  padding: 22px;
  background: var(--surface);
}

.stat-item strong {
  color: var(--text);
  font-size: 1.4rem;
  letter-spacing: -.03em;
}

.stat-item span {
  color: var(--text-muted);
  font-size: .72rem;
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
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.category-item {
  padding: 18px;
  border-bottom: 1px solid var(--border);
  transition: .16s ease;
}

.category-item:hover {
  border-color: var(--brand);
  background: var(--brand-soft);
}

.category-item strong {
  display: block;
  margin-bottom: 4px;
  color: var(--text);
  font-size: .9rem;
}

.category-item span {
  color: var(--text-muted);
  font-size: .72rem;
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

.empty-note {
  grid-column: 1 / -1;
  padding: 28px;
  border: 1px dashed var(--border);
  border-radius: 14px;
  color: var(--text-muted);
  text-align: center;
}

@media (max-width: 980px) {
  .hero,
  .course-grid,
  .category-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .hero {
    min-height: auto;
    align-items: start;
  }

  .hero-visual {
    min-height: 320px;
  }

  .stats-band,
  .process-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 700px) {
  .hero,
  .course-grid,
  .category-grid,
  .stats-band,
  .process-grid {
    grid-template-columns: 1fr;
  }

  .hero-visual {
    order: -1;
    min-height: 280px;
  }

  .hero-panel {
    left: 0;
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
