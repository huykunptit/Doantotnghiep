<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'default' })

interface PathCourse {
  id: number
  course_id: number
  sort_order: number
  is_required: boolean
  milestone_label?: string | null
  course?: {
    id: number
    title: string
    thumbnail?: string | null
    price?: number
    lessons_count?: number
  } | null
}

interface PathDetail {
  id: number
  title: string
  slug: string
  description?: string | null
  target_role?: string | null
  price?: number
  cover_url?: string | null
  path_courses_count?: number
  path_courses?: PathCourse[]
  is_following?: boolean
  is_purchased?: boolean
  user_progress?: { progress_percent?: number, status?: string } | null
  enrolled_course_ids?: number[]
}

const route = useRoute()
const toast = useToast()
const auth = useAuthStore()
const { t, locale } = useI18n()
const slug = computed(() => String(route.params.slug))

const loading = ref(true)
const following = ref(false)
const path = ref<PathDetail | null>(null)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatPrice = (price = 0) => {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  }).format(price)
}

const enrolledSet = computed(() => new Set(path.value?.enrolled_course_ids || []))

async function load() {
  loading.value = true
  try {
    path.value = await useApi<PathDetail>(`/career-paths/${slug.value}`)
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.paths.loadError'), detail: error?.data?.message, life: 3500 })
    path.value = null
  }
  finally {
    loading.value = false
  }
}

async function follow() {
  if (!auth.user) {
    await navigateTo('/login')
    return
  }
  if (!path.value) return
  following.value = true
  try {
    await useApi(`/career-paths/${path.value.id}/follow`, { method: 'POST' })
    toast.add({ severity: 'success', summary: t('student.paths.followed'), life: 2200 })
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.paths.followError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    following.value = false
  }
}

async function unfollow() {
  if (!path.value) return
  following.value = true
  try {
    await useApi(`/career-paths/${path.value.id}/follow`, { method: 'DELETE' })
    toast.add({ severity: 'success', summary: t('student.paths.unfollowed'), life: 2200 })
    await load()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.paths.followError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    following.value = false
  }
}

function buy() {
  if (!auth.user) {
    navigateTo('/login')
    return
  }
  if (!path.value) return
  navigateTo(`/checkout/path/${path.value.slug}`)
}

onMounted(load)
</script>

<template>
  <div class="path-detail">
    <div v-if="loading" class="empty">…</div>
    <template v-else-if="path">
      <header class="hero" :style="path.cover_url ? { backgroundImage: `linear-gradient(rgba(15,23,42,.82), rgba(15,23,42,.92)), url(${path.cover_url})` } : undefined">
        <NuxtLink to="/paths" class="back">← {{ t('student.paths.back') }}</NuxtLink>
        <span v-if="path.target_role" class="role">{{ path.target_role }}</span>
        <h1>{{ path.title }}</h1>
        <p>{{ path.description || t('student.paths.noDesc') }}</p>
        <div class="hero-meta">
          <span>{{ t('student.paths.courses', { n: path.path_courses_count || path.path_courses?.length || 0 }) }}</span>
          <strong>{{ formatPrice(path.price || 0) }}</strong>
        </div>
        <div class="actions">
          <Button
            v-if="path.is_purchased"
            :label="t('student.paths.continue')"
            icon="pi pi-play"
            @click="navigateTo(`/student/courses`)"
          />
          <template v-else>
            <Button :label="(path.price || 0) > 0 ? t('student.paths.buy') : t('student.paths.enrollFree')" icon="pi pi-shopping-cart" @click="buy" />
            <Button
              v-if="!path.is_following"
              :label="t('student.paths.follow')"
              severity="secondary"
              outlined
              :loading="following"
              @click="follow"
            />
            <Button
              v-else
              :label="t('student.paths.unfollow')"
              severity="secondary"
              outlined
              :loading="following"
              @click="unfollow"
            />
          </template>
        </div>
        <div v-if="path.user_progress" class="progress">
          {{ t('student.paths.progress', { n: path.user_progress.progress_percent || 0 }) }}
        </div>
      </header>

      <section class="panel">
        <h2>{{ t('student.paths.roadmap') }}</h2>
        <ol class="steps">
          <li v-for="(item, index) in path.path_courses || []" :key="item.id">
            <div class="step-index">{{ index + 1 }}</div>
            <div class="step-body">
              <strong>{{ item.course?.title || `#${item.course_id}` }}</strong>
              <span>
                {{ item.is_required ? t('student.paths.required') : t('student.paths.optional') }}
                <template v-if="item.milestone_label"> · {{ item.milestone_label }}</template>
                · {{ t('student.catalog.lessons', { n: item.course?.lessons_count || 0 }) }}
              </span>
            </div>
            <div class="step-side">
              <span v-if="enrolledSet.has(item.course_id)" class="pill ok">{{ t('student.paths.enrolled') }}</span>
              <Button
                v-if="enrolledSet.has(item.course_id)"
                size="small"
                :label="t('student.catalog.learnNow')"
                @click="navigateTo(`/learn/${item.course_id}`)"
              />
              <NuxtLink v-else :to="`/courses/${item.course_id}`" class="link">{{ t('student.paths.viewCourse') }}</NuxtLink>
            </div>
          </li>
        </ol>
      </section>
    </template>
    <div v-else class="empty">{{ t('student.paths.notFound') }}</div>
  </div>
</template>

<style scoped>
.path-detail { width: min(960px, calc(100% - 32px)); margin: 0 auto 48px; padding-top: 20px; }
.hero {
  border-radius: 20px; padding: 28px; color: #fff;
  background: linear-gradient(135deg, #0f172a, color-mix(in srgb, var(--brand) 55%, #0f172a));
  background-size: cover; background-position: center; margin-bottom: 16px;
}
.back { color: rgba(255,255,255,.8); text-decoration: none; font-weight: 600; font-size: .9rem; }
.role {
  display: inline-block; margin-top: 14px; padding: 4px 10px; border-radius: 999px;
  background: rgba(255,255,255,.16); font-size: .78rem; font-weight: 700; letter-spacing: .04em;
}
.hero h1 { margin: 10px 0 8px; font-size: clamp(1.6rem, 3vw, 2.2rem); text-shadow: 0 1px 12px rgba(0, 0, 0, .4); }
.hero p { margin: 0; max-width: 52ch; color: rgba(255,255,255,.86); font-weight: 500; }
.hero-meta { display: flex; gap: 16px; margin-top: 16px; align-items: baseline; font-weight: 650; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
.progress { margin-top: 14px; font-weight: 700; opacity: .92; }
.panel {
  border: 1px solid var(--border); border-radius: 16px; padding: 18px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.panel h2 { margin: 0 0 14px; font-size: 1.1rem; }
.steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.steps li {
  display: grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items: center;
  padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle);
}
.step-index {
  width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center;
  background: var(--brand-soft); color: var(--brand); font-weight: 800;
}
.step-body span { display: block; color: var(--text-muted); font-size: .86rem; font-weight: 500; }
.step-side { display: flex; gap: 8px; align-items: center; }
.pill {
  display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: .75rem; font-weight: 700;
}
.pill.ok { background: color-mix(in srgb, #16a34a 18%, transparent); color: #166534; }
.link { color: var(--brand); font-weight: 700; text-decoration: none; }
.empty { color: var(--text-muted); padding: 28px 0; }
@media (max-width: 720px) {
  .steps li { grid-template-columns: 40px 1fr; }
  .step-side { grid-column: 2; }
}
</style>
