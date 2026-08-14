<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const { t } = useI18n()
const cart = useCartStore()

onMounted(async () => {
  try {
    const queryString = new URLSearchParams(route.query as Record<string, string>).toString()
    const result = await useApi<any>(`/payos/return?${queryString}`, { token: null })
    const status = String(result?.status || '').toLowerCase()
    const ok = status === 'paid' || result?.order?.status === 'paid'
    if (ok && result?.order) {
      const ids = (result.order.cart_items || []).map((item: { id?: number }) => Number(item.id)).filter(Boolean)
      if (result.order.course_id) ids.push(Number(result.order.course_id))
      cart.removeMany(ids)
    }
    await navigateTo({
      path: '/payment/result',
      query: {
        status: ok ? 'success' : (status || 'failed'),
        courseId: String(result?.order?.course_id || route.query.courseId || ''),
        message: ok ? t('student.payment.success') : (result?.message || t('student.payment.failed')),
      },
    }, { replace: true })
  }
  catch (e: any) {
    await navigateTo({
      path: '/payment/result',
      query: {
        status: route.query.cancelled ? 'cancelled' : 'failed',
        courseId: String(route.query.courseId || ''),
        message: route.query.cancelled ? t('student.payment.cancelled') : (e?.data?.message || t('student.payment.failed')),
      },
    }, { replace: true })
  }
})
</script>

<template>
  <div class="wrap">
    <div class="card">
      <i class="pi pi-spin pi-spinner" style="font-size:2rem;color:var(--brand)" />
      <h1>{{ t('student.payment.processing') }}</h1>
      <p>{{ t('student.payment.wait') }}</p>
    </div>
  </div>
</template>

<style scoped>
.wrap { min-height: 60vh; display: grid; place-items: center; padding: 24px; }
.card {
  width: min(440px, 100%); text-align: center; padding: 36px 28px; border-radius: 18px;
  border: 1px solid var(--border); background: color-mix(in srgb, var(--surface) 94%, transparent);
  display: grid; gap: 12px; justify-items: center;
}
.card h1 { margin: 0; font-size: 1.35rem; }
.card p { margin: 0; color: var(--text-muted); font-weight: 500; }
</style>
