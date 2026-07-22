<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'default' })

interface CourseDetail {
  id: number
  title: string
  description?: string | null
  thumbnail?: string | null
  price?: number
  lessons_count?: number
  enrollments_count?: number
  avg_rating?: number
  is_enrolled?: boolean
  instructor?: { id?: number, name?: string } | null
  category?: { name?: string } | null
  lessons?: Array<{ id: number, title: string, type?: string }>
}

const route = useRoute()
const auth = useAuthStore()
const toast = useToast()
const { t, locale } = useI18n()
const courseId = computed(() => Number(route.params.id))
const loading = ref(true)
const course = ref<CourseDetail | null>(null)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatPrice = (price = 0) => {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(price)
}

async function load() {
  loading.value = true
  try {
    course.value = await useApi<CourseDetail>(`/courses/${courseId.value}`)
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.catalog.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function primaryAction() {
  if (!auth.isAuthenticated) return navigateTo('/login')
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
  <div class="detail">
    <div v-if="loading" class="empty">…</div>
    <template v-else-if="course">
      <div class="hero" :style="course.thumbnail ? { backgroundImage: `linear-gradient(180deg, rgba(0,0,0,.35), rgba(0,0,0,.55)), url(${course.thumbnail})` } : undefined">
        <div class="hero-inner">
          <small>{{ course.category?.name || t('student.catalog.title') }}</small>
          <h1>{{ course.title }}</h1>
          <p>{{ course.instructor?.name || t('student.catalog.instructor') }} · {{ t('student.catalog.lessons', { n: course.lessons_count || course.lessons?.length || 0 }) }}</p>
          <div class="cta-row">
            <strong>{{ formatPrice(course.price || 0) }}</strong>
            <Button :label="ctaLabel" icon="pi pi-arrow-right" icon-pos="right" @click="primaryAction" />
          </div>
        </div>
      </div>
      <section class="body">
        <h2>{{ t('common.menu') === 'Menu' ? 'About' : 'Giới thiệu' }}</h2>
        <div class="desc" v-html="course.description || '—'" />
        <h3>{{ t('student.learn.curriculum') }}</h3>
        <ol class="lessons">
          <li v-for="(lesson, i) in (course.lessons || [])" :key="lesson.id">
            <span>{{ i + 1 }}. {{ lesson.title }}</span>
            <small>{{ lesson.type || 'lesson' }}</small>
          </li>
          <li v-if="!(course.lessons || []).length" class="empty">—</li>
        </ol>
      </section>
    </template>
  </div>
</template>

<style scoped>
.detail { width: min(980px, calc(100% - 32px)); margin: 0 auto 48px; }
.hero {
  min-height: 280px; border-radius: 18px; background: linear-gradient(135deg, #0f766e, #134e4a);
  background-size: cover; background-position: center; color: #fff; display: flex; align-items: flex-end;
}
.hero-inner { padding: 28px; width: 100%; }
.hero small { opacity: .85; font-weight: 650; text-transform: uppercase; letter-spacing: .06em; font-size: .75rem; }
.hero h1 { margin: 8px 0; font-size: clamp(1.6rem, 3vw, 2.2rem); }
.hero p { margin: 0 0 16px; opacity: .9; font-weight: 500; }
.cta-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.cta-row strong { font-size: 1.35rem; }
.body { margin-top: 22px; padding: 18px; border: 1px solid var(--border); border-radius: 16px; background: color-mix(in srgb, var(--surface) 92%, transparent); }
.desc { color: var(--text); line-height: 1.6; font-weight: 500; }
.lessons { margin: 12px 0 0; padding: 0; list-style: none; display: grid; gap: 8px; }
.lessons li { display: flex; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 10px; background: var(--surface-subtle); font-weight: 600; }
.lessons small { color: var(--text-muted); font-weight: 500; text-transform: uppercase; font-size: .72rem; }
.empty { color: var(--text-muted); padding: 24px; }
</style>
