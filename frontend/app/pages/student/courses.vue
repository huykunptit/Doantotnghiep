<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface EnrollmentRow {
  id: number
  progress?: number
  course?: { id: number, title: string, thumbnail?: string | null, instructor?: { name?: string } | null } | null
}

const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const rows = ref<EnrollmentRow[]>([])

async function load() {
  loading.value = true
  try {
    rows.value = await useApi<EnrollmentRow[]>('/enrollments')
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.courses.loadError'), detail: error?.data?.message, life: 3500 })
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
        <span class="eyebrow">{{ t('student.console') }}</span>
        <h1>{{ t('student.courses.title') }}</h1>
        <p>{{ t('student.courses.subtitle') }}</p>
      </div>
      <Button :label="t('student.dashboard.browse')" icon="pi pi-shop" @click="navigateTo('/courses')" />
    </header>

    <section class="grid" :aria-busy="loading">
      <button
        v-for="item in rows"
        :key="item.id"
        type="button"
        class="card"
        @click="item.course && navigateTo(`/learn/${item.course.id}`)"
      >
        <img v-if="item.course?.thumbnail" :src="item.course.thumbnail" alt="">
        <div class="body">
          <strong>{{ item.course?.title }}</strong>
          <span>{{ item.course?.instructor?.name || t('student.catalog.instructor') }}</span>
          <ProgressBar :value="item.progress || 0" :show-value="false" style="height:8px;margin-top:8px" />
          <small>{{ t('student.dashboard.progress', { n: item.progress || 0 }) }}</small>
        </div>
        <Button :label="t('student.courses.learn')" size="small" @click.stop="item.course && navigateTo(`/learn/${item.course.id}`)" />
      </button>
      <div v-if="!loading && !rows.length" class="empty">{{ t('student.courses.empty') }}</div>
    </section>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.eyebrow { display: block; margin-bottom: 4px; color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.workspace-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 2vw, 1.75rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-weight: 500; }
.grid { display: grid; gap: 10px; }
.card {
  display: grid; grid-template-columns: 72px 1fr auto; gap: 14px; align-items: center;
  padding: 12px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); color: var(--text); font: inherit; text-align: left; cursor: pointer;
}
.card img { width: 72px; height: 72px; object-fit: cover; border-radius: 12px; }
.body { min-width: 0; }
.body strong { display: block; }
.body span, .body small { color: var(--text-muted); font-size: .85rem; font-weight: 500; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
@media (max-width: 700px) { .card { grid-template-columns: 56px 1fr; } .card > :last-child { grid-column: 1 / -1; } }
</style>
