<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-surface-dim/30 pb-6">
        <div class="max-w-2xl">
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Operation & Finance</p>
          <h2 class="text-3xl font-bold font-headline tracking-tight text-on-surface mt-1">Giao dịch & Đơn hàng</h2>
          <p class="text-on-surface-variant text-sm mt-2">
            Theo dõi dòng tiền, trạng thái thanh toán và tra soát các giao dịch phát sinh từ Học viên.
          </p>
        </div>
        <button @click="exportData" class="px-5 py-2.5 cta-gradient text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition-transform active:scale-95 flex items-center gap-2">
           <span class="material-symbols-outlined text-[18px]">receipt_long</span> Xuất File CSV
        </button>
      </div>

      <!-- Bento Stats Grid -->
      <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 sm:col-span-6 xl:col-span-3 bg-surface-lowest p-6 rounded-[1.25rem] border border-surface-dim/30 shadow-sm flex flex-col justify-between hover:shadow-ambient transition-all">
           <div class="flex items-start justify-between mb-4">
              <span class="material-symbols-outlined p-2.5 rounded-lg bg-surface-low text-outline">inventory_2</span>
              <span class="text-[10px] font-bold text-outline uppercase tracking-wider">Tổng Đơn</span>
           </div>
           <h3 class="text-3xl font-bold font-headline text-on-surface">{{ totalItems }}</h3>
        </div>
        
        <div class="col-span-12 sm:col-span-6 xl:col-span-3 bg-surface-lowest p-6 rounded-[1.25rem] border border-surface-dim/30 shadow-sm flex flex-col justify-between hover:shadow-ambient transition-all relative overflow-hidden group">
           <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/10 rounded-full group-hover:scale-150 transition-transform duration-500 blur-2xl"></div>
           <div class="flex items-start justify-between mb-4 relative z-10">
              <span class="material-symbols-outlined p-2.5 rounded-lg bg-amber-500/10 text-amber-600">hourglass_empty</span>
              <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Chờ Thanh Toán</span>
           </div>
           <h3 class="text-3xl font-bold font-headline text-on-surface relative z-10">{{ pendingCount }}</h3>
        </div>
        
        <div class="col-span-12 sm:col-span-6 xl:col-span-3 bg-surface-lowest p-6 rounded-[1.25rem] border border-surface-dim/30 shadow-sm flex flex-col justify-between hover:shadow-ambient transition-all relative overflow-hidden group">
           <div class="absolute -right-6 -top-6 w-24 h-24 bg-secondary/10 rounded-full group-hover:scale-150 transition-transform duration-500 blur-2xl"></div>
           <div class="flex items-start justify-between mb-4 relative z-10">
              <span class="material-symbols-outlined p-2.5 rounded-lg bg-secondary/10 text-secondary">verified</span>
              <span class="text-[10px] font-bold text-secondary uppercase tracking-wider">Hoàn tất</span>
           </div>
           <h3 class="text-3xl font-bold font-headline text-on-surface relative z-10">{{ paidCount }}</h3>
        </div>
        
        <div class="col-span-12 sm:col-span-6 xl:col-span-3 bg-primary-fixed p-6 rounded-[1.25rem] border border-primary/20 shadow-md flex flex-col justify-between hover:-translate-y-1 transition-all relative overflow-hidden">
           <div class="absolute right-0 bottom-0 text-[100px] text-primary/5 -mr-4 -mb-8 pointer-events-none material-symbols-outlined">payments</div>
           <div class="flex items-start justify-between mb-4 relative z-10">
              <span class="material-symbols-outlined p-2.5 rounded-lg bg-white/50 text-primary">payments</span>
              <span class="text-[10px] font-bold text-on-primary-fixed uppercase tracking-wider">Doanh Thu NV</span>
           </div>
           <h3 class="text-3xl font-bold font-headline text-on-primary-fixed relative z-10 truncate" :title="formatMoney(totalRevenue)">{{ compactPrice(totalRevenue) }}</h3>
        </div>
      </div>

      <!-- Filters Row -->
      <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between mt-8 p-4 bg-surface-lowest border border-surface-dim rounded-2xl shadow-sm">
         <div class="relative w-full lg:w-96">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[20px]">search</span>
            <input v-model="search" @keyup.enter="fetchOrders(1)" placeholder="Tra cứu Code giao dịch, email User..." type="text" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-dim/30 bg-surface-low placeholder-outline focus:outline-none focus:ring-2 focus:ring-primary/20 focus:bg-surface-lowest transition-all text-sm">
         </div>
         <select v-model="statusFilter" @change="fetchOrders(1)" class="w-full lg:w-auto rounded-xl border border-surface-dim/30 bg-surface-low px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:bg-surface-lowest text-sm font-bold uppercase tracking-wider text-on-surface-variant transition-all">
            <option value="">Tất Cả Trạng Thái</option>
            <option value="pending">⏳ Chờ Thanh toán</option>
            <option value="paid">✅ Đã Thanh toán</option>
            <option value="completed">🏆 Hoàn thành</option>
            <option value="failed">❌ Giao dịch Lỗi</option>
         </select>
      </div>

      <!-- Data Table -->
      <div class="bg-surface-lowest rounded-[1.25rem] shadow-sm border border-surface-dim overflow-hidden">
         <div class="overflow-x-auto">
            <table class="admin-table">
               <thead>
                  <tr>
                     <th>Mã GD</th>
                     <th>Thông tin Đơn hàng</th>
                     <th class="text-center">Trạng thái</th>
                     <th class="text-right">Tổng thanh toán</th>
                  </tr>
               </thead>
               
               <tbody v-if="loading" class="divide-y divide-surface-dim/20">
                  <tr v-for="i in 5" :key="i">
                     <td class="px-6 py-4"><div class="h-6 w-16 bg-surface-high animate-pulse rounded"></div></td>
                     <td class="px-6 py-4">
                        <div class="flex gap-4">
                           <div class="h-16 w-24 bg-surface-high animate-pulse rounded-lg"></div>
                           <div class="space-y-2">
                              <div class="h-4 w-48 bg-surface-high animate-pulse rounded"></div>
                              <div class="h-3 w-32 bg-surface-high animate-pulse rounded"></div>
                           </div>
                        </div>
                     </td>
                     <td class="px-6 py-4 flex justify-center"><div class="h-6 w-24 bg-surface-high animate-pulse rounded-md"></div></td>
                     <td class="px-6 py-4 text-right"><div class="h-6 w-20 bg-surface-high animate-pulse rounded ml-auto"></div></td>
                  </tr>
               </tbody>
               
               <tbody v-else-if="orders.length === 0">
                  <tr>
                     <td colspan="4" class="px-6 py-24 text-center">
                        <span class="material-symbols-outlined text-5xl text-outline mb-2 opacity-50">receipt_long</span>
                        <p class="font-medium text-sm text-on-surface-variant">Không tìm thấy giao dịch nào.</p>
                     </td>
                  </tr>
               </tbody>

               <tbody v-else class="divide-y divide-surface-dim/20 text-sm">
                  <tr v-for="order in orders" :key="order.id" class="group hover:bg-surface-low/50 transition-colors">
                     <td class="px-6 py-4 font-mono text-xs font-semibold text-outline-variant uppercase tracking-wider">
                        #{{ order.id }}
                     </td>
                     <td class="px-6 py-4">
                        <div class="flex items-center gap-4">
                           <div class="h-16 w-24 overflow-hidden rounded-[8px] bg-surface-high shadow-sm shrink-0 border border-surface-dim/10 relative">
                              <img v-if="order.course?.thumbnail" :src="order.course.thumbnail" class="h-full w-full object-cover group-hover:scale-105 transition-transform">
                              <div v-else class="flex h-full items-center justify-center text-outline">📘</div>
                           </div>
                           <div class="min-w-0">
                              <p class="font-bold text-on-surface truncate">{{ order.course?.title || 'Khóa học' }}</p>
                              <p class="text-[11px] font-medium text-on-surface-variant flex items-center gap-1 mt-1 truncate">
                                 <span class="material-symbols-outlined text-[12px]">person</span> {{ order.user?.name }} &bull; {{ order.user?.email }}
                              </p>
                              <p class="text-[10px] font-bold text-outline uppercase tracking-wider mt-1.5 flex items-center gap-1">
                                 <span class="material-symbols-outlined text-[12px]">schedule</span> {{ formatDate(order.created_at) }}
                              </p>
                           </div>
                        </div>
                     </td>
                     <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider" :class="statusBadgeClasses(order.status)">
                           {{ orderStatusLabel(order.status) }}
                        </span>
                     </td>
                     <td class="px-6 py-4 text-right">
                        <p class="font-headline font-bold text-lg text-on-surface">
                           {{ order.amount > 0 ? formatMoney(order.amount) : 'Free' }}
                        </p>
                     </td>
                  </tr>
               </tbody>
            </table>
         </div>

         <!-- Pagination -->
         <div v-if="totalPages > 1" class="px-6 py-4 border-t border-surface-dim/30 flex justify-center bg-surface-lowest">
            <div class="flex gap-1.5">
               <button v-for="page in totalPages" :key="page" @click="fetchOrders(page)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all"
                  :class="page === currentPage ? 'cta-gradient text-white shadow-md' : 'bg-surface-low hover:bg-surface-high text-on-surface'">
                  {{ page }}
               </button>
            </div>
         </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })
const auth = useAuthStore()

const orders = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const totalRevenue = ref(0)
const paidCount = ref(0)
const pendingCount = ref(0)
const { exportToCSV } = useExport()

const formatMoney = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0)
const compactPrice = (value: number) => {
  if (value >= 1000000) return (value / 1000000).toFixed(1).replace(/\.0$/, '') + 'M Đ'
  if (value >= 1000) return (value / 1000).toFixed(1).replace(/\.0$/, '') + 'K Đ'
  return formatMoney(value)
}
const formatDate = (date?: string) => !date ? 'N/A' : new Date(date).toLocaleDateString('vi-VN', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })

const orderStatusLabel = (status: string) => ({ pending: '⏳ Chờ Thanh Toán', paid: '✅ Đã Thanh Toán', completed: '🏆 Hoàn Thành', failed: '❌ Thất Bại' }[status] || status)

const statusBadgeClasses = (status: string) => {
   const map: Record<string, string> = {
      pending: 'bg-amber-500/10 text-amber-700 border border-amber-500/20',
      paid: 'bg-secondary/10 text-secondary border border-secondary/20',
      completed: 'bg-primary/10 text-primary border border-primary/20',
      failed: 'bg-error-container/40 text-error border border-error/20'
   }
   return map[status] || 'bg-surface-high text-outline'
}

async function fetchOrders(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const q = new URLSearchParams({ page: String(page), per_page: '10' })
    if (search.value.trim()) q.set('search', search.value.trim())
    if (statusFilter.value) q.set('status', statusFilter.value)
    
    // Mock for now if API isn't fully ready
    const data = await $fetch<any>(`/api/admin/orders?${q}`, { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ data: [], total: 0, last_page: 1 }))
    orders.value = data.data || []
    totalPages.value = data.last_page || 1
    totalItems.value = data.total || 0
  } finally { 
     loading.value = false 
  }
}

async function fetchStats() {
  try {
    const stats = await $fetch<any>('/api/admin/stats', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => null)
    if(stats) totalRevenue.value = stats.total_revenue || 0
    
    // Attempting to get simplified counts
    const [paidRes, pendingRes] = await Promise.all([
       $fetch<any>('/api/admin/orders?status=paid&per_page=1', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ total: 0 })), 
       $fetch<any>('/api/admin/orders?status=pending&per_page=1', { headers: { Authorization: `Bearer ${auth.token}` } }).catch(() => ({ total: 0 }))
    ])
    paidCount.value = (paidRes.total || 0) + (stats?.orders_by_status?.paid || 0)
    pendingCount.value = (pendingRes.total || 0) + (stats?.orders_by_status?.pending || 0)
  } catch {}
}

const exportData = () => {
  exportToCSV(
    orders.value,
    [
      { key: 'id', label: 'Mã đơn' },
      { key: 'course.title', label: 'Khóa học' },
      { key: 'user.name', label: 'Học viên' },
      { key: 'user.email', label: 'Email' },
      { key: 'status', label: 'Trạng thái', format: (value) => orderStatusLabel(String(value || 'pending')) },
      { key: 'amount', label: 'Số tiền', format: (value) => String(value || 0) },
      { key: 'created_at', label: 'Ngày tạo', format: (value) => formatDate(value) },
    ],
    'admin_orders',
  )
}

onMounted(() => { fetchOrders(1); fetchStats() })
</script>
