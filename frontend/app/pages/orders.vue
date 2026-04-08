<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Lịch sử đơn hàng</h1>
    <p class="text-sm text-gray-500 mb-8">Theo dõi các giao dịch mua khóa học của bạn</p>

    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="card p-5 animate-pulse flex gap-4">
        <div class="w-16 h-11 bg-gray-200 rounded-lg flex-shrink-0"></div>
        <div class="flex-1 space-y-2"><div class="h-4 bg-gray-200 rounded w-1/2"></div><div class="h-3 bg-gray-200 rounded w-1/3"></div></div>
        <div class="w-20 h-6 bg-gray-200 rounded-full"></div>
      </div>
    </div>

    <div v-else-if="orders.length === 0" class="card p-16 text-center">
      <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
      <h3 class="text-base font-semibold text-gray-900 mb-1">Chưa có đơn hàng nào</h3>
      <p class="text-sm text-gray-500 mb-4">Hãy mua khóa học đầu tiên!</p>
      <NuxtLink to="/courses" class="btn-primary text-sm">Khám phá khóa học</NuxtLink>
    </div>

    <div v-else class="space-y-3">
      <div v-for="order in orders" :key="order.id" class="card p-5 flex items-center gap-4">
        <div class="w-16 h-11 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden">
          <img v-if="order.course?.thumbnail" :src="order.course.thumbnail" :alt="order.course?.title" class="w-full h-full object-cover" />
          <div v-else class="w-full h-full flex items-center justify-center bg-primary-light">
            <span class="text-sm font-bold text-primary/50">{{ order.course?.title?.charAt(0) }}</span>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <NuxtLink :to="`/courses/${order.course_id}`" class="text-sm font-semibold text-gray-900 hover:text-primary transition-colors truncate block">
            {{ order.course?.title ?? 'Khóa học' }}
          </NuxtLink>
          <p class="text-xs text-gray-400 mt-1">{{ formatDate(order.created_at) }}</p>
        </div>
        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
          <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
            :class="{
              'bg-amber-50 text-amber-700 border border-amber-100': order.status === 'pending',
              'bg-emerald-50 text-emerald-700 border border-emerald-100': order.status === 'paid',
              'bg-red-50 text-red-700 border border-red-100': order.status === 'failed'
            }">
            {{ statusLabel(order.status) }}
          </span>
          <span class="text-sm font-bold text-gray-900">{{ formatPrice(order.amount) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const courseStore = useCourseStore()
const loading = ref(true)
const orders = ref(courseStore.orders)

function formatPrice(price: number) {
  if (price <= 0) return 'Miễn phí'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
}

function formatDate(date?: string) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function statusLabel(status: string) {
  return { pending: 'Chờ thanh toán', paid: 'Đã thanh toán', failed: 'Thất bại' }[status] ?? status
}

onMounted(async () => {
  loading.value = true
  orders.value = await courseStore.fetchOrders()
  loading.value = false
})
</script>
