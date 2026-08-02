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
  instructor?: { id: number, name: string } | null
  category?: { id: number, name: string, slug?: string } | null
}

const route = useRoute()
const auth = useAuthStore()
const { t, locale } = useI18n()
const search = ref(String(route.query.search || ''))
const category = ref(String(route.query.category || ''))
const loading = ref(false)
const courses = ref<Course[]>([])
const total = ref(0)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

async function loadCourses() {
  loading.value = true
  try {
    const response = await useApi<{ data?: Course[], total?: number }>('/courses', {
      query: {
        per_page: 12,
        status: 'published',
        search: search.value || undefined,
        category: category.value || undefined,
      },
      token: null,
    })
    courses.value = response.data || []
    total.value = response.total || courses.value.length
  }
  catch {
    courses.value = []
    total.value = 0
  }
  finally {
    loading.value = false
  }
}

function formatPrice(price?: number) {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

onMounted(loadCourses)
watch(() => route.query, () => {
  search.value = String(route.query.search || '')
  category.value = String(route.query.category || '')
  loadCourses()
})
</script>

<template>
  <div class="courses-page">
    <header class="page-heading">
      <div>
        <h1>{{ t('student.catalog.title') }}</h1>
        <p>{{ t('student.catalog.subtitle') }}</p>
      </div>
    </header>

    <form class="filter-bar" @submit.prevent="loadCourses">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('student.catalog.search')" fluid />
      </IconField>
      <Button type="submit" :label="t('student.catalog.searchBtn')" icon="pi pi-search" :loading="loading" />
    </form>

    <p class="result-count"><strong>{{ total }}</strong> {{ t('student.catalog.result', { n: total }).replace(/^\d+\s*/, '') }}</p>

    <StudentCourseRecommendations v-if="auth.isAuthenticated" :limit="4" compact :show-more="false" class="rec-block" />

    <div class="course-grid">
      <NuxtLink v-for="course in courses" :key="course.id" :to="`/courses/${course.slug || course.id}`" class="course-item">
        <div class="course-media" :style="course.thumbnail ? { backgroundImage: `url(${course.thumbnail})` } : undefined">
          <span v-if="!course.thumbnail"><i class="pi pi-book" /></span>
        </div>
        <div class="course-body">
          <small>{{ course.category?.name || t('student.catalog.title') }}</small>
          <strong>{{ course.title }}</strong>
          <p>{{ course.instructor?.name || t('student.catalog.instructor') }}</p>
          <div class="course-meta">
            <span>{{ t('student.catalog.learners', { n: course.enrollments_count || 0 }) }}</span>
            <span>{{ formatPrice(course.price) }}</span>
          </div>
        </div>
      </NuxtLink>
      <div v-if="!loading && !courses.length" class="empty-note">{{ t('student.catalog.empty') }}</div>
    </div>
  </div>
</template>

<style scoped>
.courses-page {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 36px 0 64px;
}
.page-heading h1 { margin: 0 0 6px; font-size: clamp(1.6rem, 3vw, 2rem); }
.page-heading p { margin: 0; color: var(--text-muted); font-weight: 500; }
.rec-block { margin: 18px 0 22px; }
.filter-bar {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 10px;
  margin: 18px 0 12px;
}
.result-count { margin: 0 0 18px; color: var(--text-muted); font-weight: 500; }
.course-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
}
.course-item {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  color: var(--text);
  transition: border-color .15s ease, transform .15s ease;
}
.course-item:hover {
  border-color: color-mix(in srgb, var(--brand) 40%, var(--border));
  transform: translateY(-2px);
}
.course-media {
  display: grid;
  place-items: center;
  height: 150px;
  background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 25%, #134e4a), #0f766e);
  background-size: cover;
  background-position: center;
  color: white;
  font-size: 1.8rem;
}
.course-body { padding: 12px 14px 16px; display: grid; gap: 4px; }
.course-body small { color: var(--text-muted); font-size: .75rem; font-weight: 650; text-transform: uppercase; }
.course-body p { margin: 0; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.course-meta { display: flex; justify-content: space-between; gap: 8px; margin-top: 8px; font-size: .88rem; font-weight: 650; }
.empty-note { grid-column: 1 / -1; padding: 40px; text-align: center; color: var(--text-muted); }
</style>
