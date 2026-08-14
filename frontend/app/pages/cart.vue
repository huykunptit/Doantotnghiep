<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { resolveMediaUrl } from '~/utils/media-url'

definePageMeta({ layout: 'default' })

interface QuoteItem {
  id: number
  title: string
  thumbnail?: string | null
  price: number
  discount?: number
  payable?: number
}

interface QuotePayload {
  items: QuoteItem[]
  subtotal: number
  discount: number
  total: number
  applied: { id: number } | null
  suggestions: Array<{
    id: number
    savings: number
    recommended?: boolean
    voucher?: { name?: string | null, type?: string | null, discount_value?: number | null } | null
  }>
}

const toast = useToast()
const auth = useAuthStore()
const cart = useCartStore()
const { t, locale } = useI18n()

const loading = ref(false)
const paying = ref(false)
const paymentUrl = ref<string | null>(null)
const selectedVoucherId = ref<number | null>(null)
const quote = ref<QuotePayload | null>(null)

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
function formatPrice(price = 0) {
  if (!price) return t('student.catalog.free')
  return new Intl.NumberFormat(numberLocale.value, { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(price)
}

const displayItems = computed(() => quote.value?.items?.length
  ? quote.value.items
  : cart.items.map(item => ({
      id: item.id,
      title: item.title,
      thumbnail: item.thumbnail,
      price: item.price,
      discount: 0,
      payable: item.price,
    })))

const subtotal = computed(() => quote.value?.subtotal ?? cart.subtotal)
const discount = computed(() => quote.value?.discount ?? 0)
const total = computed(() => quote.value?.total ?? cart.subtotal)
const suggestions = computed(() => quote.value?.suggestions || [])

async function loadQuote() {
  cart.hydrate()
  paymentUrl.value = null
  if (!cart.items.length) {
    quote.value = null
    selectedVoucherId.value = null
    return
  }
  if (!auth.isAuthenticated) {
    quote.value = {
      items: cart.items.map(item => ({ ...item, discount: 0, payable: item.price })),
      subtotal: cart.subtotal,
      discount: 0,
      total: cart.subtotal,
      applied: null,
      suggestions: [],
    }
    return
  }

  loading.value = true
  try {
    quote.value = await useApi<QuotePayload>('/checkout/quote', {
      method: 'POST',
      body: {
        course_ids: cart.ids,
        user_voucher_id: selectedVoucherId.value,
      },
    })
    if (quote.value?.applied?.id) selectedVoucherId.value = quote.value.applied.id
  }
  catch (error: any) {
    quote.value = null
    toast.add({ severity: 'error', summary: t('student.cart.quoteError'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    loading.value = false
  }
}

watch(selectedVoucherId, () => {
  if (auth.isAuthenticated && cart.items.length) loadQuote()
})

function removeItem(id: number) {
  cart.remove(id)
  if (cart.items.length) loadQuote()
  else {
    quote.value = null
    selectedVoucherId.value = null
  }
}

async function pay() {
  if (!cart.items.length) return
  if (!auth.isAuthenticated) {
    return navigateTo(`/login?redirect=${encodeURIComponent('/cart')}`)
  }
  paying.value = true
  try {
    const res = await useApi<{ enrolled?: boolean, payment_url?: string | null, message?: string, order?: { course_id?: number, cart_items?: Array<{ id: number }> } }>('/orders', {
      method: 'POST',
      body: {
        course_ids: cart.ids,
        payment_method: 'payos',
        user_voucher_id: selectedVoucherId.value,
      },
    })
    const purchased = (res.order?.cart_items || []).map(item => item.id)
    if (res.order?.course_id) purchased.push(res.order.course_id)
    if (res.enrolled) {
      cart.removeMany(purchased.length ? purchased : cart.ids)
      toast.add({ severity: 'success', summary: t('student.checkout.success'), life: 2500 })
      await navigateTo('/student/courses')
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
    toast.add({ severity: 'error', summary: t('student.checkout.error'), detail: error?.data?.message, life: 4000 })
  }
  finally {
    paying.value = false
  }
}

onMounted(async () => {
  cart.hydrate()
  if (!auth.ready) auth.hydrate()
  await loadQuote()
})
</script>

<template>
  <div class="checkout">
    <header class="head">
      <span class="eyebrow">{{ t('student.cart.title') }}</span>
      <h1>{{ t('student.cart.title') }}</h1>
      <p>{{ t('student.cart.subtitle') }}</p>
    </header>

    <div v-if="!cart.items.length" class="empty-box">
      <CommonEmptyState :description="t('student.cart.empty')" />
      <Button :label="t('student.cart.browse')" @click="navigateTo('/courses')" />
    </div>

    <div v-else class="grid">
      <section class="panel">
        <ul class="items">
          <li v-for="item in displayItems" :key="item.id">
            <img v-if="resolveMediaUrl(item.thumbnail)" :src="resolveMediaUrl(item.thumbnail)" :alt="item.title">
            <div>
              <NuxtLink :to="`/courses/${item.id}`">{{ item.title }}</NuxtLink>
              <span v-if="item.discount">{{ formatPrice(item.price) }} → {{ formatPrice(item.payable || 0) }}</span>
              <span v-else>{{ formatPrice(item.price) }}</span>
            </div>
            <Button icon="pi pi-trash" text rounded :aria-label="t('student.cart.remove')" @click="removeItem(item.id)" />
          </li>
        </ul>

        <p v-if="!auth.isAuthenticated" class="login-hint">{{ t('student.cart.loginForVoucher') }}</p>
        <CommonCheckoutVouchers
          v-else
          v-model="selectedVoucherId"
          :suggestions="suggestions"
          :format-price="formatPrice"
        />
      </section>

      <aside class="panel summary">
        <p>{{ t('student.checkout.summary') }}</p>
        <strong class="price">{{ formatPrice(total) }}</strong>
        <div class="line"><span>{{ t('student.checkout.subtotal') }}</span><span>{{ formatPrice(subtotal) }}</span></div>
        <div v-if="discount" class="line save"><span>{{ t('student.checkout.discount') }}</span><span>− {{ formatPrice(discount) }}</span></div>
        <div class="line"><span>{{ t('student.checkout.total') }}</span><span>{{ formatPrice(total) }}</span></div>
        <p v-if="loading" class="hint">{{ t('common.loading') }}</p>
        <a v-if="paymentUrl" :href="paymentUrl"><Button :label="t('student.checkout.continuePay')" class="w-full" /></a>
        <Button
          v-else
          class="w-full"
          :label="auth.isAuthenticated ? t('student.checkout.pay') : t('student.cart.loginToPay')"
          :loading="paying"
          @click="pay"
        />
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
.items { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.items li { display: grid; grid-template-columns: 72px minmax(0, 1fr) auto; gap: 12px; align-items: center; }
.items img { width: 72px; height: 52px; object-fit: cover; border-radius: 10px; }
.items a { font-weight: 700; color: var(--text); }
.items span { display: block; color: var(--text-muted); font-size: .88rem; font-weight: 500; }
.login-hint { margin: 14px 0 0; color: var(--text-muted); font-weight: 550; }
.summary .price { display: block; margin: 8px 0 14px; font-size: 1.8rem; font-family: var(--font-display); }
.line { display: flex; justify-content: space-between; font-weight: 650; margin-bottom: 10px; }
.line.save { color: #16a34a; }
.hint { color: var(--text-muted); font-size: .85rem; }
.w-full { width: 100%; }
.empty-box { display: grid; gap: 14px; justify-items: start; padding: 24px 0; }
@media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
</style>
