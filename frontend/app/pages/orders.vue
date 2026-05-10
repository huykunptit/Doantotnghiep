<template>
  <div class="account-shell">
    <section class="account-wrap">
      <header class="account-hero">
        <div>
          <p class="account-eyebrow">Lịch sử giao dịch</p>
          <h1>Đơn hàng của bạn</h1>
          <p class="account-lead">
            Theo dõi các giao dịch đã thực hiện, trạng thái thanh toán và truy cập nhanh tới các khóa học đã sở hữu.
          </p>
        </div>
        <NuxtLink to="/courses" class="account-cta">Khám phá khóa học</NuxtLink>
      </header>

      <div v-if="loading" class="account-grid two-col">
        <div v-for="item in 4" :key="item" class="account-card h-40 animate-pulse" />
      </div>

      <template v-else>
        <section class="account-summary">
          <article class="account-card">
            <span class="account-section-label">Tổng đơn</span>
            <strong>{{ orders.length }}</strong>
          </article>
          <article class="account-card">
            <span class="account-section-label">Đã thanh toán</span>
            <strong>{{ paidCount }}</strong>
          </article>
          <article class="account-card">
            <span class="account-section-label">Đang chờ</span>
            <strong>{{ pendingCount }}</strong>
          </article>
          <article class="account-card">
            <span class="account-section-label">Tổng chi</span>
            <strong>{{ formatPrice(totalPaid) }}</strong>
          </article>
        </section>

        <section v-if="orders.length === 0" class="account-card account-empty">
          <div>
            <strong>Chưa có đơn hàng</strong>
            <p>Bạn chưa thực hiện giao dịch nào. Hãy bắt đầu với một khóa học phù hợp hôm nay.</p>
          </div>
        </section>

        <section v-else class="account-grid">
          <article v-for="order in orders" :key="order.id" class="account-card">
            <div class="account-row">
              <div>
                <p class="account-section-label">Đơn hàng #{{ order.id }}</p>
                <strong>{{ formatDate(order.created_at) }}</strong>
              </div>
              <span :class="['account-badge', badgeClass(order.status)]">{{ statusLabel(order.status) }}</span>
            </div>

            <div class="account-course" style="margin-top: 18px">
              <div class="account-thumb">
                <img v-if="order.course?.thumbnail" :src="order.course.thumbnail" :alt="order.course?.title">
              </div>
              <div>
                <NuxtLink :to="`/courses/${order.course_id}`"><strong>{{ order.course?.title ?? 'Khóa học đã bị gỡ bỏ' }}</strong></NuxtLink>
                <p class="account-meta">{{ order.course?.instructor?.name || 'PTIT LMS' }}</p>
              </div>
            </div>

            <div class="account-row" style="margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line)">
              <div>
                <span class="account-section-label">Giá trị</span>
                <strong class="account-value">{{ formatPrice(order.amount) }}</strong>
              </div>
              <NuxtLink v-if="order.status === 'paid'" :to="`/learn/${order.course_id}`" class="account-cta">Học ngay</NuxtLink>
            </div>
          </article>
        </section>
      </template>
    </section>
  </div>
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
const badgeClass = (status: string) => ({ pending: 'is-warning', paid: 'is-success', failed: 'is-danger' }[status] ?? 'is-warning')
const totalPaid = computed(() => orders.value.filter(o => o.status === 'paid').reduce((sum, o) => sum + (o.amount || 0), 0))
const paidCount = computed(() => orders.value.filter(o => o.status === 'paid').length)
const pendingCount = computed(() => orders.value.filter(o => o.status === 'pending').length)

onMounted(async () => {
  loading.value = true
  orders.value = await courseStore.fetchOrders()
  loading.value = false
})
</script>
