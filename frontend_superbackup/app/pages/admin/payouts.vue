<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

definePageMeta({ layout: 'admin' })

const token = useAuthTokenCookie()
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })

const loading = ref(true)
const error = ref('')
const rawOrders = ref<any[]>([])
const rawCourses = ref<any[]>([])
const search = ref('')
const periodFilter = ref<'all' | 'this_month' | 'last_month' | 'this_year'>('all')
const markedPaid = ref<Set<number>>(new Set())
const payoutPage = ref(1)
const payoutPerPage = ref(10)

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [coursesRes, ordersRes] = await Promise.all([
      useApi<any>('/admin/courses?per_page=200', { headers: authHeaders() }),
      useApi<any>('/admin/orders?per_page=500', { headers: authHeaders() }),
    ])
    rawCourses.value = coursesRes.data || []
    rawOrders.value = ordersRes.data || []
  }
  catch (e: any) {
    error.value = e?.data?.message || 'Không thể tải dữ liệu.'
  }
  finally {
    loading.value = false }
}

const filteredOrders = computed(() => {
  let orders = rawOrders.value.filter(o => ['paid', 'completed'].includes(o.status))
  const now = new Date()
  if (periodFilter.value === 'this_month') {
    orders = orders.filter((o) => {
      const d = new Date(o.created_at)
      return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth()
    })
  }
  else if (periodFilter.value === 'last_month') {
    const lm = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    orders = orders.filter((o) => {
      const d = new Date(o.created_at)
      return d.getFullYear() === lm.getFullYear() && d.getMonth() === lm.getMonth()
    })
  }
  else if (periodFilter.value === 'this_year') {
    orders = orders.filter(o => new Date(o.created_at).getFullYear() === now.getFullYear())
  }
  return orders
})

const courseMap = computed(() =>
  new Map(rawCourses.value.map(c => [c.id, c]))
)

interface PayoutRow {
  instructorId: number
  instructorName: string
  email: string
  revenue: number
  payout: number
  orderCount: number
  courseTitles: string[]
}

const payoutRows = computed<PayoutRow[]>(() => {
  const agg = new Map<number, PayoutRow>()
  for (const order of filteredOrders.value) {
    const course = courseMap.value.get(order.course_id)
    const instructorId = Number(course?.instructor?.id || course?.user_id || 0)
    if (!instructorId) continue
    const existing = agg.get(instructorId) || {
      instructorId,
      instructorName: course?.instructor?.name || 'Giảng viên',
      email: course?.instructor?.email || '',
      revenue: 0,
      payout: 0,
      orderCount: 0,
      courseTitles: [],
    }
    existing.revenue += Number(order.amount || 0)
    existing.payout = Math.round(existing.revenue * 0.7)
    existing.orderCount++
    if (course?.title && !existing.courseTitles.includes(course.title)) {
      existing.courseTitles.push(course.title)
    }
    agg.set(instructorId, existing)
  }
  let rows = Array.from(agg.values()).sort((a, b) => b.revenue - a.revenue)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    rows = rows.filter(r =>
      r.instructorName.toLowerCase().includes(q) || r.email.toLowerCase().includes(q)
    )
  }
  return rows
})

const payoutLastPage = computed(() => Math.max(1, Math.ceil(payoutRows.value.length / payoutPerPage.value)))
const pagedPayoutRows = computed(() => {
  const start = (payoutPage.value - 1) * payoutPerPage.value
  return payoutRows.value.slice(start, start + payoutPerPage.value)
})

const totalRevenue = computed(() => payoutRows.value.reduce((s, r) => s + r.revenue, 0))
const totalPayout = computed(() => payoutRows.value.reduce((s, r) => s + r.payout, 0))
const paidCount = computed(() => payoutRows.value.filter(r => markedPaid.value.has(r.instructorId)).length)

function togglePaid(id: number) {
  const s = new Set(markedPaid.value)
  s.has(id) ? s.delete(id) : s.add(id)
  markedPaid.value = s
}

function markAllAsPaid() {
  markedPaid.value = new Set(payoutRows.value.map(r => r.instructorId))
}

function formatMoney(v: number) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v || 0)
}

function exportCSV() {
  const rows = payoutRows.value.map(r => [
    r.instructorId,
    r.instructorName,
    r.email,
    r.orderCount,
    r.revenue,
    r.payout,
    markedPaid.value.has(r.instructorId) ? 'Đã chi trả' : 'Chưa chi trả',
    r.courseTitles.join('; '),
  ])
  const header = ['ID', 'Giảng viên', 'Email', 'Đơn hàng', 'Doanh thu', 'Payout (70%)', 'Trạng thái', 'Khoá học']
  const csv = [header, ...rows].map(r => r.join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `payout_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(fetchData)
</script>

<template>
  <div class="report-page">
    <header class="page-header"><div><span>Tài chính</span><h1>Chi trả giảng viên</h1><p>Tổng hợp payout 70% từ đơn hàng đã thanh toán.</p></div><div class="actions"><Button label="Đánh dấu tất cả đã trả" severity="secondary" outlined @click="markAllAsPaid"/><Button label="Xuất Excel" icon="pi pi-download" @click="exportCSV"/></div></header>
    <Card><template #title>Bộ lọc</template><template #content><div class="filters"><InputText v-model="search" placeholder="Tìm tên hoặc email giảng viên..."/><Select v-model="periodFilter" :options="[{label:'Tất cả thời gian',value:'all'},{label:'Tháng này',value:'this_month'},{label:'Tháng trước',value:'last_month'},{label:'Năm nay',value:'this_year'}]" option-label="label" option-value="value"/></div></template></Card>
    <div class="metrics"><Card v-for="m in [{l:'Giảng viên',v:payoutRows.length},{l:'Tổng doanh thu',v:formatMoney(totalRevenue)},{l:'Payout 70%',v:formatMoney(totalPayout)},{l:'Đã chi trả',v:`${paidCount}/${payoutRows.length}`}]" :key="m.l" class="metric-card"><template #content><small>{{m.l}}</small><strong>{{m.v}}</strong></template></Card></div>
    <Message v-if="error" severity="error" :closable="false">{{error}}</Message>
    <Card v-else><template #content>
      <DataTable :value="pagedPayoutRows" :loading="loading" data-key="instructorId" striped-rows responsive-layout="scroll" paginator :rows="payoutPerPage" :total-records="payoutRows.length" :first="(payoutPage-1)*payoutPerPage" @page="payoutPerPage=$event.rows;payoutPage=$event.page+1">
        <Column header="Giảng viên"><template #body="{data}"><div class="primary-cell"><strong>{{data.instructorName}}</strong><small>{{data.email||'Không có email'}}</small></div></template></Column>
        <Column header="Khóa học"><template #body="{data}"><span class="wrap-text">{{data.courseTitles.join(', ')||'—'}}</span></template></Column>
        <Column field="orderCount" header="Đơn hàng"/>
        <Column header="Doanh thu"><template #body="{data}"><span class="money">{{formatMoney(data.revenue)}}</span></template></Column>
        <Column header="Payout 70%"><template #body="{data}"><span class="money">{{formatMoney(data.payout)}}</span></template></Column>
        <Column header="Trạng thái"><template #body="{data}"><Tag :value="markedPaid.has(data.instructorId)?'Đã chi trả':'Chưa chi trả'" :severity="markedPaid.has(data.instructorId)?'success':'warn'"/></template></Column>
        <Column header=""><template #body="{data}"><Button :label="markedPaid.has(data.instructorId)?'Hoàn tác':'Đánh dấu đã trả'" size="small" severity="secondary" outlined @click="togglePaid(data.instructorId)"/></template></Column>
        <template #empty>Không có dữ liệu payout cho kỳ đã chọn.</template>
      </DataTable>
      <Message severity="info" :closable="false">Trạng thái chi trả chỉ được lưu trong phiên hiện tại; backend chưa có API payout.</Message>
    </template></Card>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>

<style scoped>.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.detail-grid>div{display:flex;flex-direction:column;gap:.25rem}.detail-grid small{color:var(--p-text-muted-color)}.revenue-chart{display:flex;align-items:flex-end;height:13rem;gap:1rem;border-bottom:1px solid var(--p-content-border-color)}.chart-bar-wrap{height:100%;flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:.5rem}.chart-bar{width:min(3rem,70%);min-height:.25rem;background:var(--p-primary-color);border-radius:.4rem .4rem 0 0}@media(max-width:520px){.detail-grid{grid-template-columns:1fr}}</style>