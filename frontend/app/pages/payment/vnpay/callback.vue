<template>
  <div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="card max-w-md w-full p-10 text-center">
      <div class="space-y-4">
        <div class="w-16 h-16 mx-auto border-4 border-gray-200 border-t-primary rounded-full animate-spin"></div>
        <p class="text-gray-500 font-medium">Đang xử lý thanh toán VNPAY...</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const router = useRouter()

onMounted(async () => {
  try {
    const queryString = new URLSearchParams(route.query as Record<string, string>).toString()
    const result = await useApi<any>(`/vnpay/return?${queryString}`)

    const ok = !!(result?.message?.toLowerCase?.().includes('success') || result?.order?.status === 'paid')
    await router.replace({
      path: '/payment/result',
      query: {
        status: ok ? 'success' : 'failed',
        message: ok ? 'Thanh toán VNPAY thành công.' : (result?.message || 'Thanh toán không thành công.'),
      },
    })
  } catch (e: any) {
    const responseCode = String(route.query.vnp_ResponseCode || '')
    const ok = responseCode === '00'
    await router.replace({
      path: '/payment/result',
      query: {
        status: ok ? 'success' : 'failed',
        message: ok ? 'Thanh toán VNPAY thành công.' : (e?.data?.message || `Mã lỗi: ${responseCode || 'unknown'}`),
      },
    })
  }
})
</script>
