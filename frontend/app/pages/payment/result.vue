<template>
  <section class="mx-auto flex min-h-[72vh] max-w-4xl items-center px-4 py-10 sm:px-6 lg:px-8">
    <UiCard class="w-full overflow-hidden">
      <div class="grid gap-0 lg:grid-cols-[0.92fr_1.08fr]">
        <div :class="['p-8 text-white sm:p-10', panelClass]">
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/70">Payment Result</p>
          <h1 class="mt-4 font-headline text-3xl font-bold tracking-[-0.03em]">{{ title }}</h1>
          <p class="mt-4 max-w-md text-sm leading-7 text-white/85">{{ message }}</p>

          <div class="mt-8 inline-flex h-20 w-20 items-center justify-center rounded-full bg-white/12 text-4xl font-semibold backdrop-blur-sm">
            {{ icon }}
          </div>
        </div>

        <div class="space-y-6 p-8 sm:p-10">
          <div class="rounded-[1.5rem] bg-surface-low p-5">
            <div class="flex items-center justify-between gap-4">
              <span class="text-sm text-on-surface-variant">Trạng thái giao dịch</span>
              <StatusBadge :value="badgeValue" />
            </div>
            <p class="mt-4 text-sm leading-7 text-on-surface-variant">{{ helperText }}</p>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-surface-dim/40 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-outline">Cổng thanh toán</p>
              <p class="mt-2 font-semibold text-on-surface">PayOS</p>
            </div>
            <div class="rounded-2xl border border-surface-dim/40 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-outline">Điểm đến tiếp theo</p>
              <p class="mt-2 font-semibold text-on-surface">{{ nextStep }}</p>
            </div>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <NuxtLink v-if="isSuccess" to="/my-courses" class="flex-1"><UiButton block>Đến khóa học của tôi</UiButton></NuxtLink>
            <NuxtLink v-else :to="retryLink" class="flex-1"><UiButton block>{{ retryLabel }}</UiButton></NuxtLink>
            <NuxtLink to="/courses" class="flex-1"><UiButton block variant="secondary">Quay lại danh sách khóa học</UiButton></NuxtLink>
          </div>
        </div>
      </div>
    </UiCard>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const status = computed(() => String(route.query.status || '').toLowerCase())
const isSuccess = computed(() => ['success', 'paid', 'ok', '00'].includes(status.value))
const isCancelled = computed(() => ['cancelled', 'canceled'].includes(status.value))
const message = computed(() => {
  const raw = String(route.query.message || '').trim()
  if (raw) return raw
  if (isSuccess.value) return 'Giao dịch hoàn tất. Bạn có thể bắt đầu học ngay hoặc xem lại khóa học của mình.'
  if (isCancelled.value) return 'Bạn đã chủ động huỷ giao dịch. Không có khoản thanh toán nào bị trừ.'
  return 'Giao dịch không thành công. Vui lòng thử lại hoặc chọn phương thức khác.'
})
const title = computed(() => isSuccess.value ? 'Thanh toán thành công' : (isCancelled.value ? 'Bạn đã huỷ giao dịch' : 'Thanh toán chưa hoàn tất'))
const icon = computed(() => isSuccess.value ? '✓' : (isCancelled.value ? '↺' : '✕'))
const badgeValue = computed(() => isSuccess.value ? 'paid' : (isCancelled.value ? 'cancelled' : 'failed'))
const panelClass = computed(() => isSuccess.value ? 'bg-[linear-gradient(160deg,#0f7a5c_0%,#17a673_100%)]' : (isCancelled.value ? 'bg-[linear-gradient(160deg,#8a6b1f_0%,#c8961a_100%)]' : 'bg-[linear-gradient(160deg,#9f1239_0%,#e11d48_100%)]'))
const helperText = computed(() => isSuccess.value ? 'Hệ thống đã xác nhận đơn hàng và tự động ghi danh cho bạn vào khóa học.' : (isCancelled.value ? 'Bạn có thể quay lại trang checkout bất kỳ lúc nào để tiếp tục thanh toán.' : 'Nếu tiền đã bị trừ nhưng trạng thái chưa cập nhật, hãy kiểm tra lại sau ít phút hoặc liên hệ hỗ trợ.'))
const nextStep = computed(() => isSuccess.value ? 'Vào học ngay' : 'Quay lại checkout')
const retryLink = computed(() => {
  const courseId = String(route.query.courseId || '').trim()
  return courseId ? `/checkout/${courseId}` : '/courses'
})
const retryLabel = computed(() => isCancelled.value ? 'Tiếp tục thanh toán' : 'Thử lại thanh toán')
</script>

