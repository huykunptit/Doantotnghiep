<template>
  <NuxtLayout name="instructor">
    <section class="space-y-8">
      <AppPageHeader eyebrow="Instructor" title="Doanh thu khóa học" description="Theo dõi doanh thu, đơn hàng đã thanh toán và giá trị trung bình của từng giao dịch.">
        <template #actions>
          <UiButton to="/instructor/courses" variant="secondary">Quay lại</UiButton>
        </template>
      </AppPageHeader>

      <div v-if="loading" class="grid gap-4 md:grid-cols-3">
        <div v-for="item in 3" :key="item" class="h-28 rounded-3xl border border-surface-dim bg-surface-lowest animate-pulse" />
      </div>

      <template v-else>
        <div class="grid gap-4 md:grid-cols-3">
          <UiCard>
            <p class="text-sm text-on-surface-variant">Tổng doanh thu</p>
            <p class="mt-2 text-2xl font-bold text-on-surface">{{ money(summary.total_revenue) }}</p>
          </UiCard>
          <UiCard>
            <p class="text-sm text-on-surface-variant">Đơn đã thanh toán</p>
            <p class="mt-2 text-2xl font-bold text-on-surface">{{ summary.paid_orders || 0 }}</p>
          </UiCard>
          <UiCard>
            <p class="text-sm text-on-surface-variant">Giá trị trung bình</p>
            <p class="mt-2 text-2xl font-bold text-primary">{{ money(summary.average_order_value) }}</p>
          </UiCard>
        </div>

        <UiCard>
          <h2 class="text-lg font-semibold text-on-surface">Danh sách đơn hàng</h2>
          <div v-if="orders.length === 0" class="mt-6"><UiEmptyState title="Chưa có dữ liệu doanh thu" description="Doanh thu sẽ xuất hiện khi khóa học bắt đầu có giao dịch." /></div>
          <div v-else class="mt-6 space-y-4">
            <div v-for="order in orders" :key="order.id" class="flex flex-col gap-4 rounded-2xl border border-surface-dim px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <p class="font-semibold text-on-surface">{{ order.user?.name }}</p>
                <p class="text-sm text-on-surface-variant">{{ order.user?.email }}</p>
              </div>
              <div class="grid gap-3 sm:grid-cols-3 lg:w-[420px]">
                <div>
                  <p class="text-xs uppercase tracking-wide text-outline">Số tiền</p>
                  <p class="mt-1 font-semibold text-on-surface">{{ money(order.amount) }}</p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-outline">Trạng thái</p>
                  <p class="mt-1 font-semibold text-on-surface">{{ order.status }}</p>
                </div>
                <div>
                  <p class="text-xs uppercase tracking-wide text-outline">Thanh toán</p>
                  <p class="mt-1 font-semibold text-on-surface">{{ order.paid_at ? formatDate(order.paid_at) : '-' }}</p>
                </div>
              </div>
            </div>
          </div>
        </UiCard>
      </template>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
// @ts-nocheck
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'

definePageMeta({ middleware: 'instructor' })
const route = useRoute()
const auth = useAuthStore()
const courseId = Number(route.params.id)
const loading = ref(true)
const summary = ref<any>({})
const orders = ref<any[]>([])
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
const formatDate = (value: string) => new Date(value).toLocaleDateString('vi-VN')
onMounted(async () => { try { const res = await useApi<any>(`/instructor/courses/${courseId}/revenue`, { token: auth.token }); summary.value = res.summary || {}; orders.value = res.orders?.data || [] } finally { loading.value = false } })
</script>
