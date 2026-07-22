<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'default', middleware: ['auth'] })

interface CourseDetail {
  id: number
  title: string
  thumbnail?: string | null
  price?: number
  lessons_count?: number
  is_enrolled?: boolean
  instructor?: { name?: string } | null
}

const route = useRoute()
const toast = useToast()
const { t, locale } = useI18n()
const courseId = computed(() => Number(route.params.courseId))

const loading = ref(true)
const paying = ref(false)
const course = ref<CourseDetail | null>(null)
const paymentUrl = ref<string | null>(null)
const alreadyEnrolled = ref(false)
const method = ref<'payos' | 'momo' | 'zalopay' | 'bank_transfer'>('payos')

const methods = computed(() => [
  { value: 'payos' as const, label: t('student.checkout.payos'), note: t('student.checkout.payosNote') },
  { value: 'bank_transfer' as const, label: t('student.checkout.bank'), note: t('student.checkout.bankNote') },
  { value: 'momo' as const, label: t('student.checkout.momo'), note: t('student.checkout.momoNote') },
  { value: 'zalopay' as const, label: t('student.checkout.zalopay'), note: t('student.checkout.zalopayNote') },
])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatPrice = (price = 0) => {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(price)
}

async function load() {
  loading.value = true
  try {
    course.value = await useApi<CourseDetail>(`/courses/${courseId.value}`)
    alreadyEnrolled.value = !!course.value.is_enrolled
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.catalog.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

async function pay() {
  if (!course.value) return
  paying.value = true
  try {
    const res = await useApi<{ enrolled?: boolean, payment_url?: string | null, message?: string }>('/orders', {
      method: 'POST',
      body: { course_id: courseId.value, payment_method: (course.value.price || 0) > 0 ? method.value : 'payos' },
    })
    if (res.enrolled) {
      alreadyEnrolled.value = true
      toast.add({ severity: 'success', summary: t('student.checkout.success'), life: 2500 })
      await navigateTo(`/learn/${courseId.value}`)
      return
    }
    if (res.payment_url) {
      paymentUrl.value = res.payment_url
      if (import.meta.client) window.location.href = res.payment_url
      return
    }
    toast.add({ severity: 'warn', summary: t('student.checkout.error'), detail: res.message, life: 3500 })
  }
  catch (error: any) {
    const msg = error?.data?.message || ''
    if (String(msg).toLowerCase().includes('already enrolled')) {
      alreadyEnrolled.value = true
      await navigateTo(`/learn/${courseId.value}`)
      return
    }
    toast.add({ severity: 'error', summary: t('student.checkout.error'), detail: msg, life: 4000 })
  }
  finally {
    paying.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="checkout">
    <header class="head">
      <span class="eyebrow">{{ t('student.checkout.title') }}</span>
      <h1>{{ t('student.checkout.title') }}</h1>
      <p>{{ t('student.checkout.subtitle') }}</p>
    </header>

    <div v-if="loading" class="empty">…</div>
    <div v-else-if="course" class="grid">
      <section class="panel">
        <div class="course-row">
          <img v-if="course.thumbnail" :src="course.thumbnail" alt="">
          <div>
            <strong>{{ course.title }}</strong>
            <span>{{ course.instructor?.name }} · {{ t('student.catalog.lessons', { n: course.lessons_count || 0 }) }}</span>
          </div>
        </div>

        <div v-if="(course.price || 0) > 0 && !alreadyEnrolled" class="methods">
          <p>{{ t('student.checkout.method') }}</p>
          <button
            v-for="m in methods"
            :key="m.value"
            type="button"
            class="method"
            :class="{ on: method === m.value }"
            @click="method = m.value"
          >
            <strong>{{ m.label }}</strong>
            <span>{{ m.note }}</span>
          </button>
        </div>
      </section>

      <aside class="panel summary">
        <p>{{ t('student.checkout.summary') }}</p>
        <strong class="price">{{ formatPrice(course.price || 0) }}</strong>
        <div class="line"><span>{{ t('student.checkout.total') }}</span><span>{{ formatPrice(course.price || 0) }}</span></div>

        <div v-if="alreadyEnrolled" class="note">
          {{ t('student.checkout.already') }}
          <Button class="mt" :label="t('student.catalog.learnNow')" @click="navigateTo(`/learn/${courseId}`)" />
        </div>
        <template v-else>
          <a v-if="paymentUrl" :href="paymentUrl"><Button :label="t('student.checkout.continuePay')" class="w-full" /></a>
          <Button
            v-else
            class="w-full"
            :label="(course.price || 0) > 0 ? t('student.checkout.pay') : t('student.checkout.enrollFree')"
            :loading="paying"
            @click="pay"
          />
        </template>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.checkout { width: min(960px, calc(100% - 32px)); margin: 0 auto 48px; padding-top: 28px; }
.eyebrow { color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.head h1 { margin: 6px 0 4px; }
.head p { margin: 0 0 18px; color: var(--text-muted); font-weight: 500; }
.grid { display: grid; grid-template-columns: 1.4fr .9fr; gap: 14px; }
.panel {
  border: 1px solid var(--border); border-radius: 16px; padding: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent); backdrop-filter: blur(8px);
}
.course-row { display: flex; gap: 12px; align-items: center; }
.course-row img { width: 88px; height: 64px; object-fit: cover; border-radius: 10px; }
.course-row span { display: block; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.methods { margin-top: 18px; display: grid; gap: 8px; }
.methods > p { margin: 0; font-weight: 700; color: var(--text-muted); font-size: .85rem; }
.method {
  display: flex; flex-direction: column; gap: 2px; padding: 12px; border-radius: 12px;
  border: 1px solid var(--border); background: var(--surface-subtle); text-align: left; font: inherit; cursor: pointer;
}
.method.on { border-color: color-mix(in srgb, var(--brand) 45%, var(--border)); background: var(--brand-soft); }
.method span { color: var(--text-muted); font-size: .82rem; font-weight: 500; }
.summary .price { display: block; margin: 8px 0 14px; font-size: 1.8rem; font-family: var(--font-display); }
.line { display: flex; justify-content: space-between; font-weight: 650; margin-bottom: 14px; }
.note { padding: 12px; border-radius: 12px; background: var(--brand-soft); font-weight: 600; }
.mt { margin-top: 10px; }
.w-full { width: 100%; }
.empty { color: var(--text-muted); padding: 24px; }
@media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
</style>
