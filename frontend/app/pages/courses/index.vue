<script setup lang="ts">
definePageMeta({ layout: 'default' })

interface Course {
  id: number
  title: string
  description?: string
  thumbnail?: string | null
  price?: number
  enrollments_count?: number
  reviews_avg_rating?: number | string | null
  instructor?: { id: number; name: string } | null
  category?: { id: number; name: string; slug?: string } | null
}

const route = useRoute()
const search = ref(String(route.query.search || ''))
const category = ref(String(route.query.category || ''))
const loading = ref(false)
const courses = ref<Course[]>([])
const total = ref(0)

async function loadCourses() {
  loading.value = true
  try {
    const response = await useApi<{ data?: Course[]; total?: number }>('/courses', {
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
  if (!price) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', {
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
        <h1>Khóa học</h1>
        <p>Duyệt các khóa học công khai và bắt đầu lộ trình học tập của bạn.</p>
      </div>
    </header>

    <form class="filter-bar" @submit.prevent="loadCourses">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Tìm khóa học..." fluid />
      </IconField>
      <Button type="submit" label="Tìm kiếm" icon="pi pi-search" :loading="loading" />
    </form>

    <p class="result-count"><strong>{{ total }}</strong> khóa học</p>

    <div class="course-grid">
      <NuxtLink v-for="course in courses" :key="course.id" :to="`/courses/${course.id}`" class="course-item">
        <div class="course-media" :style="course.thumbnail ? { backgroundImage: `url(${course.thumbnail})` } : undefined">
          <span v-if="!course.thumbnail"><i class="pi pi-book" /></span>
        </div>
        <div class="course-body">
          <small>{{ course.category?.name || 'Khóa học' }}</small>
          <strong>{{ course.title }}</strong>
          <p>{{ course.instructor?.name || 'Giảng viên Sylva' }}</p>
          <div class="course-meta">
            <span>{{ course.enrollments_count || 0 }} học viên</span>
            <span>{{ formatPrice(course.price) }}</span>
          </div>
        </div>
      </NuxtLink>
      <div v-if="!loading && !courses.length" class="empty-note">Không tìm thấy khóa học phù hợp.</div>
    </div>
  </div>
</template>

<style scoped>
.courses-page {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 36px 0 64px;
}

.filter-bar {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 10px;
  margin: 18px 0 12px;
}

.result-count {
  margin: 0 0 18px;
  color: var(--text-muted);
  font-size: .78rem;
}

.course-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.course-item {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--surface);
}

.course-media {
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 10;
  background: linear-gradient(145deg, #134e4a, #0f766e);
  background-size: cover;
  background-position: center;
  color: white;
  font-size: 1.5rem;
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
  font-size: .9rem;
}

.course-meta {
  display: flex;
  justify-content: space-between;
  margin-top: 4px;
  color: var(--text);
  font-size: .74rem;
  font-weight: 650;
}

.empty-note {
  grid-column: 1 / -1;
  padding: 28px;
  border: 1px dashed var(--border);
  border-radius: 14px;
  color: var(--text-muted);
  text-align: center;
}

@media (max-width: 900px) {
  .course-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 560px) {
  .course-grid,
  .filter-bar {
    grid-template-columns: 1fr;
  }
}
</style>
