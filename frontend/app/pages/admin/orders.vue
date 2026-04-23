<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm giao dịch, học viên, khóa học...' })
interface OrderItem { id: number; amount: number; status: string; payment_method?: string | null; payment_ref?: string | null; paid_at?: string | null; created_at?: string | null; user?: { name: string; email: string; avatar?: string | null } | null; course?: { title: string; thumbnail?: string | null } | null; gateway_response?: Record<string, unknown> | null }
interface PaginatedOrders { data: OrderItem[]; current_page: number; last_page: number; total: number }
const user = useAuthUserCookie(); if (!user.value) await navigateTo('/login', { replace: true })
const token = useAuthTokenCookie(); const orders = ref<OrderItem[]>([]); const loading = ref(false); const detailOpen = ref(false)
const currentPage = ref(1); const lastPage = ref(1); const totalOrders = ref(0); const search = ref(''); const status = ref('')
const selectedOrder = ref<OrderItem | null>(null); const errorMessage = ref('')
const statuses = [{ label: 'Tất cả', value: '' }, { label: 'Đã thanh toán', value: 'completed' }, { label: 'Đang xử lý', value: 'pending' }, { label: 'Thất bại', value: 'failed' }]
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const paidCount = computed(() => orders.value.filter(item => ['completed', 'paid'].includes(item.status)).length)
const totalRevenue = computed(() => orders.value.filter(item => ['completed', 'paid'].includes(item.status)).reduce((sum, item) => sum + (item.amount || 0), 0))
function formatMoney(value: number) { return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0) }
function formatDate(value?: string | null) { return value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : '--' }
async function fetchOrders(page = 1) {
  loading.value = true
  try {
    const query = new URLSearchParams({ page: String(page), per_page: '10' }); if (search.value.trim()) query.set('search', search.value.trim()); if (status.value) query.set('status', status.value)
    const response = await useApi<PaginatedOrders>(`/admin/orders?${query.toString()}`, { headers: authHeaders() })
    orders.value = response.data; currentPage.value = response.current_page; lastPage.value = response.last_page; totalOrders.value = response.total
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải đơn hàng.' } finally { loading.value = false }
}
async function openDetail(order: OrderItem) { selectedOrder.value = await useApi<OrderItem>(`/admin/orders/${order.id}`, { headers: authHeaders() }); detailOpen.value = true }
onMounted(fetchOrders)
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản trị hệ thống', 'Đơn hàng']" description="Theo dõi giao dịch thanh toán theo chuẩn bảng quản trị thống nhất, có bộ lọc, thống kê nhanh và modal xem chi tiết." title="Quản lý đơn hàng">
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green"><p class="mini-title">Tổng đơn</p><div class="mini-head"><strong>{{ totalOrders }}</strong><span>Theo bộ lọc hiện tại</span></div></article>
      <article class="dashboard-card mini-card tone-amber"><p class="mini-title">Đã thanh toán</p><div class="mini-head"><strong>{{ paidCount }}</strong><span>Đơn thành công trên trang hiện tại</span></div></article>
      <article class="dashboard-card mini-card"><p class="mini-title">Doanh thu</p><div class="mini-head"><strong>{{ formatMoney(totalRevenue) }}</strong><span>Tạm tính trên dữ liệu đang hiển thị</span></div></article>
    </section>
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar"><form class="crud-toolbar-main" @submit.prevent="fetchOrders(1)"><input v-model="search" class="crud-search" type="text" placeholder="Tìm theo học viên, email hoặc khóa học..."><select v-model="status" class="crud-select"><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select><button class="crud-secondary-btn" type="submit">Tìm kiếm</button></form></div>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div class="crud-table-wrap"><table class="crud-table"><thead><tr><th>Người mua</th><th>Khóa học</th><th>Số tiền</th><th>Thanh toán</th><th>Thời gian</th><th>Thao tác</th></tr></thead><tbody>
        <tr v-if="loading"><td colspan="6" class="crud-empty">Đang tải đơn hàng...</td></tr>
        <tr v-else-if="orders.length === 0"><td colspan="6" class="crud-empty">Không có đơn hàng phù hợp.</td></tr>
        <tr v-for="order in orders" :key="order.id"><td><div class="crud-profile"><div v-if="order.user?.avatar" class="crud-avatar"><img :src="order.user.avatar" :alt="order.user.name"></div><div v-else class="crud-avatar crud-avatar-fallback">{{ order.user?.name?.slice(0,2).toUpperCase() || 'KH' }}</div><div><strong>{{ order.user?.name || '--' }}</strong><p>{{ order.user?.email || '--' }}</p></div></div></td><td><div class="crud-course"><div class="crud-course-thumb"><img v-if="order.course?.thumbnail" :src="order.course.thumbnail" :alt="order.course.title"><span v-else>📘</span></div><div><strong>{{ order.course?.title || '--' }}</strong><p>#{{ order.id }}</p></div></div></td><td>{{ formatMoney(order.amount) }}</td><td><span class="crud-badge" :class="['completed','paid'].includes(order.status) ? 'role-instructor' : 'role-admin'">{{ order.status }}</span><p>{{ order.payment_method || '--' }}</p></td><td>{{ formatDate(order.paid_at || order.created_at) }}</td><td><button class="action-btn is-view" type="button" @click="openDetail(order)">Xem chi tiết</button></td></tr>
      </tbody></table></div>
      <div class="crud-pagination"><p>Trang {{ currentPage }} / {{ lastPage }}</p><div class="crud-pagination-actions"><button class="crud-secondary-btn" type="button" :disabled="currentPage <= 1" @click="fetchOrders(currentPage - 1)">Trước</button><button class="crud-secondary-btn" type="button" :disabled="currentPage >= lastPage" @click="fetchOrders(currentPage + 1)">Sau</button></div></div>
    </section>
    <Teleport to="body"><div v-if="detailOpen" class="crud-modal-backdrop" @click.self="detailOpen = false"><div class="crud-modal"><div class="crud-modal-head"><div><p class="section-kicker">Chi tiết đơn hàng</p><h3>Đơn hàng #{{ selectedOrder?.id }}</h3></div><button class="topbar-ghost" type="button" @click="detailOpen = false">✕</button></div><div class="crud-form-grid"><div class="crud-field"><span>Học viên</span><strong>{{ selectedOrder?.user?.name || '--' }}</strong></div><div class="crud-field"><span>Email</span><strong>{{ selectedOrder?.user?.email || '--' }}</strong></div><div class="crud-field"><span>Khóa học</span><strong>{{ selectedOrder?.course?.title || '--' }}</strong></div><div class="crud-field"><span>Số tiền</span><strong>{{ formatMoney(selectedOrder?.amount || 0) }}</strong></div><div class="crud-field"><span>Phương thức</span><strong>{{ selectedOrder?.payment_method || '--' }}</strong></div><div class="crud-field"><span>Mã tham chiếu</span><strong>{{ selectedOrder?.payment_ref || '--' }}</strong></div></div><div class="crud-modal-foot"><button class="crud-secondary-btn" type="button" @click="detailOpen = false">Đóng</button></div></div></div></Teleport>
  </AdminWorkspaceShell>
</template>
