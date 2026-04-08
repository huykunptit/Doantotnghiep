<template>
  <div class="max-w-lg mx-auto px-4 py-10">
    <div class="card p-8">
      <h1 class="text-xl font-bold text-gray-900 mb-6">Thanh toán</h1>

      <div v-if="loading" class="text-center py-10">
        <div class="w-10 h-10 mx-auto border-3 border-gray-200 border-t-primary rounded-full animate-spin mb-3"></div>
        <p class="text-sm text-gray-500">Đang tải...</p>
      </div>

      <div v-else-if="course">
        <div class="flex gap-4 items-center mb-6">
          <div class="w-20 h-14 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden">
            <img v-if="course.thumbnail" :src="course.thumbnail" :alt="course.title" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center bg-primary-light">
              <span class="text-lg font-bold text-primary/30">{{ course.title.charAt(0) }}</span>
            </div>
          </div>
          <div class="flex-1 min-w-0">
            <h2 class="text-sm font-bold text-gray-900 line-clamp-2">{{ course.title }}</h2>
            <p class="text-xs text-gray-500 mt-1">{{ course.instructor?.name }} · {{ course.lessons_count ?? 0 }} bài học</p>
          </div>
        </div>

        <div class="border-t border-gray-100 py-4 space-y-3">
          <div class="flex justify-between text-sm text-gray-600">
            <span>Giá gốc</span>
            <span>{{ formatPrice(course.price) }}</span>
          </div>
          <div class="flex justify-between text-base font-bold text-gray-900 border-t border-gray-100 pt-3">
            <span>Tổng thanh toán</span>
            <span class="text-primary text-lg">{{ formatPrice(course.price) }}</span>
          </div>
        </div>

        <div v-if="course.price > 0" class="mt-4 space-y-3">
          <label class="label !mb-0">Phương thức thanh toán</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <button
              v-for="m in paymentMethods"
              :key="m.value"
              type="button"
              class="text-left px-3 py-2.5 rounded-xl border transition-all text-sm"
              :class="selectedPaymentMethod === m.value
                ? 'border-primary bg-primary-light text-primary-dark'
                : 'border-gray-200 text-gray-700 hover:border-gray-300 hover:bg-gray-50'"
              @click="selectedPaymentMethod = m.value"
            >
              <div class="font-semibold">{{ m.label }}</div>
              <div class="text-xs opacity-70 mt-0.5">{{ m.note }}</div>
            </button>
          </div>
          <p class="text-xs text-gray-500">
            MoMo/ZaloPay/Chuyển khoản đang chạy chế độ sandbox mock để demo end-to-end.
          </p>
        </div>

        <div v-if="error" class="mt-4 p-3 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700">{{ error }}</div>
        <div v-if="success" class="mt-4 p-3 rounded-xl bg-primary-light border border-primary/20 text-sm text-primary-dark font-medium">{{ success }}</div>

        <button
          v-if="!paymentUrl && !alreadyEnrolled"
          :disabled="paying"
          class="btn-primary w-full mt-6 py-3.5 text-base"
          @click="handlePay"
        >
          {{ paying ? 'Đang xử lý...' : (course.price > 0 ? `Thanh toán qua ${selectedPaymentLabel}` : 'Đăng ký miễn phí') }}
        </button>

        <a v-if="paymentUrl" :href="paymentUrl" class="block w-full mt-6 py-3.5 text-base text-center font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors">
          Tiếp tục thanh toán VNPay →
        </a>

        <div v-if="alreadyEnrolled" class="mt-6 text-center space-y-3">
          <p class="text-sm text-primary font-semibold">✓ Bạn đã đăng ký khóa học này.</p>
          <NuxtLink :to="`/courses/${courseId}`" class="btn-primary">Đến khóa học</NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
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
const selectedPaymentMethod = ref<'vnpay' | 'momo' | 'zalopay' | 'bank_transfer'>('vnpay')

const paymentMethods = [
  { value: 'vnpay', label: 'VNPAY', note: 'Cổng thanh toán chính' },
  { value: 'momo', label: 'MoMo', note: 'Sandbox mock' },
  { value: 'zalopay', label: 'ZaloPay', note: 'Sandbox mock' },
  { value: 'bank_transfer', label: 'Chuyển khoản', note: 'Sandbox mock' },
] as const

const selectedPaymentLabel = computed(() => {
  return paymentMethods.find((m) => m.value === selectedPaymentMethod.value)?.label || 'VNPAY'
})

function formatPrice(price: number) {
  if (price <= 0) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

async function handlePay() {
  paying.value = true
  error.value = ''
  try {
    const result = await courseStore.createOrder(courseId, selectedPaymentMethod.value)
    if (result.enrolled) {
      alreadyEnrolled.value = true
      success.value = 'Đăng ký thành công!'
    } else if (result.payment_url) {
      paymentUrl.value = result.payment_url
    } else if (result.order) {
      error.value = 'Không thể tạo link thanh toán. Hãy thử lại sau.'
    }
  } catch (e: any) {
    const msg = String(e?.data?.message || '')
    if (msg.includes('Already enrolled') || msg.includes('already enrolled')) {
      alreadyEnrolled.value = true
    } else {
      error.value = e?.data?.message || 'Thanh toán thất bại.'
    }
  } finally {
    paying.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    if (!courseStore.currentCourse || courseStore.currentCourse.id !== courseId) {
      await courseStore.fetchCourse(courseId)
    }
    course.value = courseStore.currentCourse

    if (course.value?.is_enrolled) {
      alreadyEnrolled.value = true
    }
  } finally {
    loading.value = false
  }
})
</script>
