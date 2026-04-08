<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <NuxtLink to="/instructor/courses" class="text-sm text-gray-500">← Quay lại</NuxtLink>
      <h1 class="text-2xl font-bold text-gray-900 mt-1">Doanh thu khóa học</h1>
    </div>

    <div v-if="loading" class="card p-6 text-sm text-gray-500">Đang tải...</div>
    <template v-else>
      <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="card p-4">
          <p class="text-xs text-gray-500">Tổng doanh thu</p>
          <p class="text-xl font-bold text-gray-900 mt-1">{{ money(summary.total_revenue) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-gray-500">Đơn đã thanh toán</p>
          <p class="text-xl font-bold text-gray-900 mt-1">{{ summary.paid_orders || 0 }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-gray-500">Giá trị trung bình</p>
          <p class="text-xl font-bold text-gray-900 mt-1">{{ money(summary.average_order_value) }}</p>
        </div>
      </div>

      <div class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left p-3">Học viên</th>
              <th class="text-left p-3">Số tiền</th>
              <th class="text-left p-3">Trạng thái</th>
              <th class="text-left p-3">Thanh toán</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id" class="border-t border-gray-100">
              <td class="p-3">
                <p class="font-medium text-gray-900">{{ order.user?.name }}</p>
                <p class="text-xs text-gray-500">{{ order.user?.email }}</p>
              </td>
              <td class="p-3">{{ money(order.amount) }}</td>
              <td class="p-3">{{ order.status }}</td>
              <td class="p-3">{{ order.paid_at ? formatDate(order.paid_at) : '-' }}</td>
            </tr>
            <tr v-if="orders.length === 0">
              <td colspan="4" class="p-6 text-center text-sm text-gray-500">Chưa có dữ liệu doanh thu.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'instructor' })

const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)

const loading = ref(true)
const summary = ref<any>({})
const orders = ref<any[]>([])

function money(value: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('vi-VN')
}

onMounted(async () => {
  try {
    const res = await useApi<any>(`/instructor/courses/${courseId}/revenue`, { token: auth.token })
    summary.value = res.summary || {}
    orders.value = res.orders?.data || []
  } finally {
    loading.value = false
  }
})
</script>

