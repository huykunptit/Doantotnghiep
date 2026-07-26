<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'instructor',
  middleware: ['auth', 'instructor', 'permission'],
  permission: ['manage_courses', 'manage_grades'],
})

interface MyCourse {
  id: number
  title: string
  thumbnail?: string | null
  enrollments_count?: number
  status: string
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const courses = ref<MyCourse[]>([])

async function load() {
  loading.value = true
  try {
    const res = await useApi<{ data: MyCourse[] }>('/my-courses', { query: { per_page: 100 } })
    courses.value = res.data || []
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('instructor.students.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('instructor.console') }}</span>
        <h1>{{ t('instructor.students.title') }}</h1>
        <p>{{ t('instructor.students.subtitle') }}</p>
      </div>
    </header>

    <section class="grid" :aria-busy="loading">
      <button
        v-for="course in courses"
        :key="course.id"
        type="button"
        class="card"
        @click="navigateTo(`/instructor/courses/${course.id}/students`)"
      >
        <img v-if="course.thumbnail" :src="course.thumbnail" alt="">
        <div class="copy">
          <strong>{{ course.title }}</strong>
          <span>{{ t('instructor.dashboard.enrollments', { n: course.enrollments_count || 0 }) }}</span>
        </div>
        <i class="pi pi-angle-right" />
      </button>
      <div v-if="!loading && !courses.length" class="empty">{{ t('common.noData') }}</div>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.45rem, 2vw, 1.8rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.grid { display: grid; gap: 10px; }
.card {
  display: grid; grid-template-columns: 56px 1fr auto; align-items: center; gap: 12px;
  width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); color: var(--text);
  font: inherit; text-align: left; cursor: pointer;
}
.card:hover { border-color: color-mix(in srgb, var(--brand) 40%, var(--border)); }
.card img { width: 56px; height: 56px; object-fit: cover; border-radius: 10px; }
.copy { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.copy strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.copy span { color: var(--text-muted); font-size: .85rem; font-weight: 500; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
</style>
