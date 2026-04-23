<template>
  <section class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
      <div>
        <AppPageHeader eyebrow="Thanh toán" title="Xác nhận đăng ký khóa học" description="Kiểm tra thông tin khóa học và chọn phương thức thanh toán phù hợp." />
        <UiCard class="mt-6">
          <div v-if="loading" class="h-64 animate-pulse rounded-3xl bg-surface-low" />
          <div v-else-if="course" class="space-y-6">
            <div class="flex items-center gap-4">
              <div class="h-20 w-28 overflow-hidden rounded-2xl bg-surface-low">
                <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="h-full w-full object-cover">
                <div v-else class="flex h-full items-center justify-center text-3xl text-outline/50">📘</div>
              </div>
              <div>
                <h2 class="text-lg font-semibold text-on-surface">{{ course.title }}</h2>
                <p class="mt-1 text-sm text-on-surface-variant">{{ course.instructor?.name }} · {{ course.lessons_count ?? 0 }} bài học</p>
              </div>
            </div>

            <div v-if="course.price > 0" class="space-y-3">
              <p class="text-sm font-semibold text-on-surface-variant">Phương thức thanh toán</p>
              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  v-for="method in paymentMethods"
                  :key="method.value"
                  class="rounded-2xl border px-4 py-4 text-left transition"
                  :class="selectedPaymentMethod === method.value ? 'border-primary bg-primary/10' : 'border-surface-dim bg-surface-lowest hover:bg-surface-low'"
                  @click="selectedPaymentMethod = method.value"
                >
                  <p class="font-semibold text-on-surface">{{ method.label }}</p>
                  <p class="mt-1 text-sm text-on-surface-variant">{{ method.note }}</p>
                </button>
              </div>
            </div>

            <div v-if="error" class="rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm text-error">{{ error }}</div>
            <div v-if="success" class="rounded-2xl border border-secondary/20 bg-secondary-50 px-4 py-3 text-sm text-secondary">{{ success }}</div>
          </div>
        </UiCard>
      </div>

      <UiCard>
        <div class="space-y-5 lg:sticky lg:top-24">
          <div>
            <p class="text-sm text-on-surface-variant">Tóm tắt thanh toán</p>
            <p class="mt-2 text-3xl font-bold text-on-surface">{{ course?.price > 0 ? formatPrice(course.price) : 'Miễn phí' }}</p>
          </div>
          <div class="space-y-3 rounded-2xl border border-surface-dim bg-surface-low p-4 text-sm text-on-surface-variant">
            <div class="flex justify-between"><span>Giá gốc</span><span>{{ formatPrice(course?.price || 0) }}</span></div>
            <div class="flex justify-between border-t border-surface-dim pt-3 font-semibold text-on-surface"><span>Tổng thanh toán</span><span>{{ formatPrice(course?.price || 0) }}</span></div>
          </div>
          <NuxtLink v-if="paymentUrl" :href="paymentUrl"><UiButton block>Tiếp tục tới cổng thanh toán</UiButton></NuxtLink>
          <UiButton v-else-if="!alreadyEnrolled" block :disabled="paying || loading" @click="handlePay">{{ paying ? 'Đang xử lý...' : (course?.price > 0 ? `Thanh toán qua ${selectedPaymentLabel}` : 'Đăng ký miễn phí') }}</UiButton>
          <div v-if="alreadyEnrolled" class="rounded-2xl border border-secondary/20 bg-secondary-50 p-4 text-sm text-secondary">
            Bạn đã đăng ký khóa học này.
            <div class="mt-3"><NuxtLink :to="`/courses/${courseId}`"><UiButton block variant="secondary">Đến trang khóa học</UiButton></NuxtLink></div>
          </div>
        </div>
      </UiCard>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'auth' })
const route = useRoute()
const courseStore = useCourseStore()
const courseId = Number(route.params.courseId)
const loading = ref(true)
const paying = ref(false)
const error = ref('')
const success = ref('')
const paymentUrl = ref<string | null>(null)
const alreadyEnrolled = ref(false)
const course = ref(courseStore.currentCourse)
const selectedPaymentMethod = ref<'payos' | 'momo' | 'zalopay' | 'bank_transfer'>('payos')
const paymentMethods = [
  { value: 'payos', label: 'PayOS', note: 'Cổng thanh toán QR / chuyển khoản nhanh' },
  { value: 'bank_transfer', label: 'Chuyển khoản', note: 'Sandbox demo' },
  { value: 'momo', label: 'MoMo', note: 'Sandbox demo' },
  { value: 'zalopay', label: 'ZaloPay', note: 'Sandbox demo' },
] as const
const selectedPaymentLabel = computed(() => paymentMethods.find((m) => m.value === selectedPaymentMethod.value)?.label || 'PayOS')
const formatPrice = (price: number) => price <= 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
async function handlePay() {
  paying.value = true
  error.value = ''
  try {
    const result = await courseStore.createOrder(courseId, selectedPaymentMethod.value)
    if (result.enrolled) {
      alreadyEnrolled.value = true
      success.value = 'Đăng ký thành công.'
    } else if (result.payment_url) paymentUrl.value = result.payment_url
    else error.value = 'Không thể tạo liên kết thanh toán.'
  } catch (e: any) {
    const msg = String(e?.data?.message || '')
    if (msg.toLowerCase().includes('already enrolled')) alreadyEnrolled.value = true
    else error.value = e?.data?.message || 'Thanh toán thất bại.'
  } finally {
    paying.value = false
  }
}
onMounted(async () => {
  try {
    if (!courseStore.currentCourse || courseStore.currentCourse.id !== courseId) await courseStore.fetchCourse(courseId)
    course.value = courseStore.currentCourse
    if (course.value?.is_enrolled) alreadyEnrolled.value = true
  } finally {
    loading.value = false
  }
})
</script>
