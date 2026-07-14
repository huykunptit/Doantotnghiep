<script setup lang="ts">
import { onMounted } from 'vue'
// Icons removed - using PrimeIcons

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
  }
  catch (e: any) {
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

<template>
  <div class="payos-shell">
    <div class="payos-card">
      <div class="payos-logo">
        <i class="pi pi-credit-card" style="font-size:2.25rem" />
      </div>
      <div class="payos-spinner" />
      <h1>Đang xử lý thanh toán</h1>
      <p>Vui lòng chờ trong giây lát, hệ thống đang đồng bộ kết quả giao dịch từ <strong>PayOS</strong>.</p>
      <p class="payos-note">Đừng tắt trình duyệt hoặc nhấn nút Quay lại.</p>
    </div>
  </div>
</template>

<style scoped>
.payos-shell {
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.payos-card {
  background: #fff;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 20px;
  padding: 40px 36px;
  max-width: 480px;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 32px rgba(0,0,0,0.06);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.payos-logo {
  width: 64px; height: 64px;
  background: rgba(22,163,74,0.08);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}

.payos-spinner {
  width: 36px; height: 36px;
  border: 3px solid rgba(22,163,74,0.15);
  border-top-color: var(--green, #16a34a);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

h1 { font-size: 1.25rem; font-weight: 700; margin: 0; }
p { font-size: 0.875rem; color: var(--muted, #6b7280); margin: 0; line-height: 1.6; }
.payos-note { font-size: 0.78rem; color: rgba(17,17,17,0.35); }
</style>
