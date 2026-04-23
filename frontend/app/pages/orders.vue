<template>
  <section class="max-w-7xl mx-auto px-4 md:px-8 py-10 md:py-16 min-h-[80vh]">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-6">
      <div>
        <h2 class="text-3xl md:text-4xl font-bold font-headline tracking-tight text-on-surface">Lịch sử giao dịch</h2>
        <p class="text-on-surface-variant text-sm mt-2">Theo dõi trạng thái thanh toán và truy cập nhanh tới các khóa học bạn đã mua.</p>
      </div>
      <NuxtLink to="/courses" class="bg-surface-high text-on-surface hover:text-primary px-5 py-2.5 rounded-xl font-semibold text-sm transition-all hover:bg-surface-highest">
        Khám phá thêm
      </NuxtLink>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="item in 5" :key="item" class="h-14 rounded-xl bg-surface-high animate-pulse" />
    </div>

    <UiEmptyState v-else-if="orders.length === 0" class="py-20 border border-surface-dim rounded-2xl" title="Chưa có đơn hàng nào" description="Bạn vẫn chưa thực hiện giao dịch nào. Hãy khám phá khóa học và bắt đầu!">
      <template #icon>
        <span class="material-symbols-outlined text-4xl">receipt_long</span>
      </template>
      <NuxtLink to="/courses">
        <UiButton class="mt-4">Khám phá khóa học</UiButton>
      </NuxtLink>
    </UiEmptyState>

    <!-- Compact Table -->
    <div v-else class="bg-surface-lowest rounded-2xl border border-surface-dim shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-surface-low text-left text-xs font-bold uppercase tracking-widest text-on-surface-variant">
              <th class="px-5 py-3.5">Mã ĐH</th>
              <th class="px-5 py-3.5">Khóa học</th>
              <th class="px-5 py-3.5 text-center">Ngày mua</th>
              <th class="px-5 py-3.5 text-center">Trạng thái</th>
              <th class="px-5 py-3.5 text-right">Số tiền</th>
              <th class="px-5 py-3.5 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-dim/50">
            <tr v-for="order in orders" :key="order.id" class="hover:bg-surface-low/50 transition-colors">
              <td class="px-5 py-4 text-on-surface-variant font-mono text-xs">#{{ order.id }}</td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="h-9 w-14 shrink-0 rounded-lg overflow-hidden bg-surface-high">
                    <img v-if="order.course?.thumbnail" :src="order.course.thumbnail" class="h-full w-full object-cover">
                    <div v-else class="flex h-full items-center justify-center text-xs">📘</div>
                  </div>
                  <NuxtLink :to="`/courses/${order.course_id}`" class="font-semibold text-on-surface hover:text-primary transition-colors line-clamp-1 max-w-xs">
                    {{ order.course?.title ?? 'Khóa học không tồn tại' }}
                  </NuxtLink>
                </div>
              </td>
              <td class="px-5 py-4 text-center text-on-surface-variant text-xs">{{ formatDate(order.created_at) }}</td>
              <td class="px-5 py-4 text-center"><StatusBadge :value="statusLabel(order.status)" /></td>
              <td class="px-5 py-4 text-right font-semibold text-on-surface whitespace-nowrap">{{ formatPrice(order.amount) }}</td>
              <td class="px-5 py-4 text-right">
                <NuxtLink v-if="order.status === 'paid'" :to="`/learn/${order.course_id}`" class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/20 transition-colors">
                  <span class="material-symbols-outlined text-[14px]">play_arrow</span> Vào học
                </NuxtLink>
                <span v-else-if="order.status === 'pending'" class="text-xs text-on-surface-variant">Chờ TT</span>
                <span v-else class="text-xs text-error">Thất bại</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Summary -->
      <div class="border-t border-surface-dim bg-surface-low px-5 py-3 flex justify-between items-center text-xs text-on-surface-variant">
        <span>Tổng: {{ orders.length }} đơn hàng</span>
        <span class="font-semibold text-on-surface">{{ formatPrice(totalPaid) }}</span>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useCourseStore } from '~/stores/course'

definePageMeta({ middleware: 'auth' })
const courseStore = useCourseStore()
const loading = ref(true)
const orders = ref<any[]>([])

const formatPrice = (price: number) => price <= 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price)
const formatDate = (date?: string) => !date ? '' : new Date(date).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
const statusLabel = (status: string) => ({ pending: 'Chờ thanh toán', paid: 'Đã thanh toán', failed: 'Thất bại' }[status] ?? status)

const totalPaid = computed(() => orders.value.filter(o => o.status === 'paid').reduce((sum, o) => sum + (o.amount || 0), 0))

onMounted(async () => {
  loading.value = true
  orders.value = await courseStore.fetchOrders()
  loading.value = false
})
</script>
