<template>
  <section class="mx-auto flex min-h-[60vh] max-w-3xl items-center px-4 py-10 sm:px-6 lg:px-8">
    <UiCard class="w-full">
      <div class="space-y-4 text-center">
        <div class="mx-auto h-16 w-16 animate-spin rounded-full border-4 border-surface-dim border-t-primary" />
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">PayOS</p>
        <h1 class="text-2xl font-bold text-on-surface">Đang xử lý thanh toán</h1>
        <p class="text-sm leading-6 text-on-surface-variant">Vui lòng chờ trong giây lát, hệ thống đang đồng bộ kết quả giao dịch từ PayOS.</p>
      </div>
    </UiCard>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'

const route = useRoute()
const router = useRouter()

onMounted(async () => {
  try {
    const queryString = new URLSearchParams(route.query as Record<string, string>).toString()
    const result = await useApi<any>(`/payos/return?${queryString}`)
    const status = String(result?.status || '').toLowerCase()
    const ok = status === 'paid' || result?.order?.status === 'paid'

    await router.replace({
      path: '/payment/result',
      query: {
        status: ok ? 'success' : status || 'failed',
        courseId: String(result?.order?.course_id || route.query.courseId || ''),
        message: ok ? 'Thanh toán PayOS thành công.' : (result?.message || 'Thanh toán chưa hoàn tất.'),
      },
    })
  } catch (e: any) {
    await router.replace({
      path: '/payment/result',
      query: {
        status: route.query.cancelled ? 'cancelled' : 'failed',
        courseId: String(route.query.courseId || ''),
        message: route.query.cancelled ? 'Bạn đã huỷ giao dịch PayOS.' : (e?.data?.message || 'Không thể xác minh giao dịch PayOS.'),
      },
    })
  }
})
</script>

