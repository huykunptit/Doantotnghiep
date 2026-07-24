<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const { t } = useI18n()

const status = computed(() => String(route.query.status || 'failed'))
const courseId = computed(() => String(route.query.courseId || route.query.course_id || ''))
const pathSlug = computed(() => String(route.query.pathSlug || route.query.path_slug || ''))
const message = computed(() => String(route.query.message || ''))

const title = computed(() => {
  if (status.value === 'success') return t('student.payment.success')
  if (status.value === 'cancelled') return t('student.payment.cancelled')
  return t('student.payment.failed')
})

function goPrimary() {
  if (pathSlug.value) return navigateTo(`/paths/${pathSlug.value}`)
  if (courseId.value) return navigateTo(`/learn/${courseId.value}`)
  return navigateTo('/student/courses')
}
</script>

<template>
  <div class="wrap">
    <div class="card" :class="status">
      <i :class="status === 'success' ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
      <h1>{{ title }}</h1>
      <p v-if="message">{{ message }}</p>
      <div class="actions">
        <Button
          v-if="status === 'success' && (courseId || pathSlug)"
          :label="pathSlug ? t('student.payment.goPath') : t('student.payment.goLearn')"
          @click="goPrimary"
        />
        <Button :label="t('student.payment.goCourses')" severity="secondary" @click="navigateTo('/student/courses')" />
        <Button :label="t('student.payment.goCatalog')" text @click="navigateTo('/courses')" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wrap { min-height: 60vh; display: grid; place-items: center; padding: 24px; }
.card {
  width: min(480px, 100%); text-align: center; padding: 36px 28px; border-radius: 18px;
  border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, transparent);
  display: grid; gap: 10px; justify-items: center;
}
.card i { font-size: 2.4rem; }
.card.success i { color: var(--brand); }
.card.failed i, .card.cancelled i { color: #b91c1c; }
.card h1 { margin: 0; font-size: 1.4rem; }
.card p { margin: 0; color: var(--text-muted); font-weight: 500; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 8px; }
</style>
