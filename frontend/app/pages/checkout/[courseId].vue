<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'default', middleware: ['auth'] })

interface PathSuggestion {
  id: number
  title: string
  slug: string
  path_price: number
  remaining_count: number
  remaining_total_price: number
}

interface CourseDetail {
  id: number
  title: string
  thumbnail?: string | null
  price?: number
  lessons_count?: number
  is_enrolled?: boolean
  instructor?: { name?: string } | null
  path_suggestions?: PathSuggestion[]
}

const route = useRoute()
const toast = useToast()
const { t, locale } = useI18n()
const courseId = computed(() => Number(route.params.courseId))
const cart = useCartStore()

const loading = ref(true)
const paying = ref(false)
const course = ref<CourseDetail | null>(null)
const paymentUrl = ref<string | null>(null)
const alreadyEnrolled = ref(false)
const method = ref<'payos'>('payos')
const selectedVoucherId = ref<number | null>(null)
const quote = ref<{
  subtotal: number
  discount: number
  total: number
  suggestions: Array<{
    id: number
    savings: number
    recommended?: boolean
    voucher?: { name?: string | null, type?: string | null, discount_value?: number | null } | null
  }>
} | null>(null)

const methods = computed(() => [
  { value: 'payos' as const, label: t('student.checkout.payos'), note: t('student.checkout.payosNote') },
])

const pathSuggestion = computed(() => course.value?.path_suggestions?.[0] || null)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const formatPrice = (price = 0) => {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(price)
}

const payable = computed(() => quote.value?.total ?? course.value?.price ?? 0)
const subtotal = computed(() => quote.value?.subtotal ?? course.value?.price ?? 0)
const discount = computed(() => quote.value?.discount ?? 0)

async function loadQuote() {
  if (!courseId.value || alreadyEnrolled.value) return
  try {
    quote.value = await useApi('/checkout/quote', {
      method: 'POST',
      body: {
        course_ids: [courseId.value],
        user_voucher_id: selectedVoucherId.value,
      },
    })
  }
  catch (error: any) {
    quote.value = null
    toast.add({ severity: 'warn', summary: t('student.cart.quoteError'), detail: error?.data?.message, life: 3500 })
  }
}

async function load() {
  loading.value = true
  try {
    course.value = await useApi<CourseDetail>(`/courses/${courseId.value}`)
    alreadyEnrolled.value = !!course.value.is_enrolled
    if (!alreadyEnrolled.value && (course.value.price || 0) > 0) await loadQuote()
  }
  catch (error: any) {
    toast.add({ severity: 'error', summary: t('student.catalog.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

watch(selectedVoucherId, (id, prev) => {
  if (id !== prev) loadQuote()
})

async function pay() {
  if (!course.value) return
  paying.value = true
  try {
    const res = await useApi<{ enrolled?: boolean, payment_url?: string | null, message?: string }>('/orders', {
      method: 'POST',
      body: { course_id: courseId.value, payment_method: 'payos', user_voucher_id: selectedVoucherId.value },
    })
    if (res.enrolled) {
      alreadyEnrolled.value = true
      cart.remove(courseId.value)
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

        <CommonCheckoutVouchers
          v-if="(course.price || 0) > 0 && !alreadyEnrolled"
          v-model="selectedVoucherId"
          :suggestions="quote?.suggestions || []"
          :format-price="formatPrice"
        />

        <aside v-if="pathSuggestion && !alreadyEnrolled" class="path-hint">
          <strong>{{ t('student.checkout.pathSuggestTitle') }}</strong>
          <p>
            {{ t('student.checkout.pathSuggestBody', {
              path: pathSuggestion.title,
              n: pathSuggestion.remaining_count,
              price: formatPrice(pathSuggestion.remaining_total_price),
            }) }}
          </p>
          <div class="path-hint-actions">
            <Button
              :label="t('student.detail.pathSuggestView')"
              text
              size="small"
              @click="navigateTo(`/paths/${pathSuggestion.slug}`)"
            />
            <Button
              :label="t('student.detail.pathSuggestBuyPath', { price: formatPrice(pathSuggestion.path_price) })"
              size="small"
              severity="secondary"
              @click="navigateTo(`/checkout/path/${pathSuggestion.slug}`)"
            />
          </div>
        </aside>
      </section>

      <aside class="panel summary">
        <p>{{ t('student.checkout.summary') }}</p>
        <strong class="price">{{ formatPrice(payable) }}</strong>
        <div class="line"><span>{{ t('student.checkout.subtotal') }}</span><span>{{ formatPrice(subtotal) }}</span></div>
        <div v-if="discount" class="line save"><span>{{ t('student.checkout.discount') }}</span><span>− {{ formatPrice(discount) }}</span></div>
        <div class="line"><span>{{ t('student.checkout.total') }}</span><span>{{ formatPrice(payable) }}</span></div>

        <div v-if="alreadyEnrolled" class="note">
          {{ t('student.checkout.already') }}
          <Button class="mt" :label="t('student.catalog.learnNow')" @click="navigateTo(`/learn/${courseId}`)" />
        </div>
        <template v-else>
          <a v-if="paymentUrl" :href="paymentUrl"><Button :label="t('student.checkout.continuePay')" class="w-full" /></a>
          <Button
            v-else
            class="w-full"
            :label="payable > 0 ? t('student.checkout.pay') : t('student.checkout.enrollFree')"
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
.path-hint {
  margin-top: 16px; padding: 12px; border-radius: 12px;
  background: var(--brand-soft); display: grid; gap: 6px;
}
.path-hint p { margin: 0; color: var(--text-muted); font-size: .9rem; font-weight: 550; }
.path-hint-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.summary .price { display: block; margin: 8px 0 14px; font-size: 1.8rem; font-family: var(--font-display); }
.line { display: flex; justify-content: space-between; font-weight: 650; margin-bottom: 14px; }
.line.save { color: #16a34a; }
.note { padding: 12px; border-radius: 12px; background: var(--brand-soft); font-weight: 600; }
.mt { margin-top: 10px; }
.w-full { width: 100%; }
.empty { color: var(--text-muted); padding: 24px; }
@media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
</style>
