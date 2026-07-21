<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin', middleware: ['auth', 'instructor'] })

const token = useAuthTokenCookie()
const toast = useToast()
function h() { return token.value ? { Authorization: `Bearer ${token.value}` } : {} }

const activeTab = ref<'overview' | 'vouchers'>('overview')

// ── Stats ─────────────────────────────────────────────────────────────────────
const statsLoading = ref(false)
const stats = ref<any>(null)

// ── Vouchers ──────────────────────────────────────────────────────────────────
const voucherLoading = ref(false)
const vouchers = ref<any[]>([])
const voucherPage = ref(1)
const voucherMeta = ref<any>(null)

// ── Modal state ───────────────────────────────────────────────────────────────
const modalOpen = ref(false)
const editingVoucher = ref<any>(null)
const saving = ref(false)
const deleting = ref<number | null>(null)

const form = ref({
  name: '',
  description: '',
  type: 'discount_percent',
  discount_value: null as number | null,
  points_cost: 100,
  total_quantity: null as number | null,
  is_active: true,
  expires_at: '',
  course_id: null as number | null,
})

onMounted(async () => {
  await Promise.all([loadStats(), loadVouchers()])
})

async function loadStats() {
  statsLoading.value = true
  try {
    stats.value = await useApi<any>('/admin/points/stats', { headers: h() })
  } catch { toast.error('Không thể tải thống kê điểm.') }
  finally { statsLoading.value = false }
}

async function loadVouchers() {
  voucherLoading.value = true
  try {
    const res = await useApi<any>(`/admin/vouchers?page=${voucherPage.value}`, { headers: h() })
    vouchers.value = res.data || []
    voucherMeta.value = res.meta || null
  } catch { toast.error('Không thể tải danh sách voucher.') }
  finally { voucherLoading.value = false }
}

function openCreate() {
  editingVoucher.value = null
  form.value = { name: '', description: '', type: 'discount_percent', discount_value: null, points_cost: 100, total_quantity: null, is_active: true, expires_at: '', course_id: null }
  modalOpen.value = true
}

function openEdit(v: any) {
  editingVoucher.value = v
  form.value = {
    name: v.name, description: v.description || '',
    type: v.type, discount_value: v.discount_value,
    points_cost: v.points_cost, total_quantity: v.total_quantity,
    is_active: v.is_active,
    expires_at: v.expires_at ? v.expires_at.slice(0, 10) : '',
    course_id: v.course_id,
  }
  modalOpen.value = true
}

async function saveVoucher() {
  if (!form.value.name.trim() || !form.value.points_cost) {
    toast.error('Vui lòng điền đầy đủ thông tin bắt buộc.')
    return
  }
  saving.value = true
  try {
    const payload = { ...form.value, expires_at: form.value.expires_at || null }
    if (editingVoucher.value) {
      await useApi(`/admin/vouchers/${editingVoucher.value.id}`, { method: 'PUT', headers: h(), body: payload })
      toast.success('Đã cập nhật voucher.')
    } else {
      await useApi('/admin/vouchers', { method: 'POST', headers: h(), body: payload })
      toast.success('Đã tạo voucher mới.')
    }
    modalOpen.value = false
    await loadVouchers()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Lưu thất bại.')
  } finally { saving.value = false }
}

async function deleteVoucher(v: any) {
  if (!confirm(`Xóa voucher "${v.name}"?`)) return
  deleting.value = v.id
  try {
    await useApi(`/admin/vouchers/${v.id}`, { method: 'DELETE', headers: h() })
    toast.success('Đã xóa voucher.')
    vouchers.value = vouchers.value.filter(x => x.id !== v.id)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Không thể xóa.')
  } finally { deleting.value = null }
}

const trendLabels = () =>
  (stats.value?.trend ?? []).map((d: any) => d.date.slice(5))

const trendEarned = () =>
  (stats.value?.trend ?? []).map((d: any) => d.earned)

function vTypeLabel(t: string) {
  const m: Record<string, string> = {
    discount_percent: 'Giảm %', discount_fixed: 'Giảm tiền',
    free_course: 'KH miễn phí', physical_gift: 'Quà tặng', ai_quota: 'AI quota',
  }
  return m[t] || t
}
</script>

<template>
  <div class="report-page">
    <Toast/><ConfirmDialog/>
    <header class="page-header"><div><span>Cấu hình hệ thống</span><h1>Điểm & phần thưởng</h1><p>Quản lý tích điểm, voucher đổi thưởng và hoạt động học viên.</p></div></header>
    <div class="actions"><Button label="Tổng quan" :severity="activeTab==='overview'?'primary':'secondary'" :outlined="activeTab!=='overview'" @click="activeTab='overview'"/><Button label="Quản lý voucher" :severity="activeTab==='vouchers'?'primary':'secondary'" :outlined="activeTab!=='vouchers'" @click="activeTab='vouchers'"/></div>
    <template v-if="activeTab==='overview'">
      <div class="metrics"><Card v-for="m in [{l:'Điểm đã phát',v:stats?.totals?.total_issued?.toLocaleString('vi-VN')??'—'},{l:'Voucher hoạt động',v:stats?.totals?.active_vouchers??'—'},{l:'Lần đổi quà',v:stats?.totals?.redemptions?.toLocaleString('vi-VN')??'—'},{l:'Điểm đã tiêu',v:stats?.totals?.total_redeemed?.toLocaleString('vi-VN')??'—'}]" :key="m.l" class="metric-card"><template #content><small>{{m.l}}</small><strong>{{m.v}}</strong></template></Card></div>
      <div class="distribution"><Card><template #title>Điểm phát ra 14 ngày</template><template #content><Chart v-if="stats" type="bar" :data="{labels:trendLabels(),datasets:[{label:'Điểm đã phát',data:trendEarned(),backgroundColor:'#1d9e75',borderRadius:6}]}"/><ProgressSpinner v-else/></template></Card><Card><template #title>Top học viên điểm cao</template><template #content><div class="list"><div v-for="(u,i) in stats?.top_students??[]" :key="u.id" class="list-row"><span>{{i+1}}. {{u.name}} <small class="muted">{{u.student_code}}</small></span><strong>{{u.points_balance?.toLocaleString('vi-VN')}}</strong></div><span v-if="!statsLoading&&!stats?.top_students?.length" class="muted">Không có dữ liệu.</span></div></template></Card></div>
      <Card><template #title>Đổi quà gần đây</template><template #content><DataTable :value="stats?.recent_redemptions??[]" :loading="statsLoading" data-key="id" striped-rows responsive-layout="scroll"><Column header="Học viên"><template #body="{data}"><div class="primary-cell"><strong>{{data.user?.name||'—'}}</strong><small>{{data.user?.student_code||'—'}}</small></div></template></Column><Column header="Voucher"><template #body="{data}">{{data.voucher?.name||'—'}}</template></Column><Column header="Điểm tiêu"><template #body="{data}"><Tag :value="`${data.points_spent?.toLocaleString('vi-VN')} điểm`" severity="warn"/></template></Column><Column header="Thời gian"><template #body="{data}"><span class="muted">{{new Date(data.created_at).toLocaleString('vi-VN')}}</span></template></Column><template #empty>Chưa có lần đổi quà nào.</template></DataTable></template></Card>
    </template>
    <template v-else>
      <Card><template #title><div class="page-header"><span>Danh sách voucher</span><Button label="Tạo voucher" icon="pi pi-plus" @click="openCreate"/></div></template><template #content>
        <DataTable :value="vouchers" :loading="voucherLoading" data-key="id" striped-rows responsive-layout="scroll" lazy paginator :rows="15" :total-records="voucherMeta?.total||vouchers.length" :first="(voucherPage-1)*15" @page="voucherPage=$event.page+1;loadVouchers()">
          <Column header="Voucher"><template #body="{data}"><div class="primary-cell"><strong>{{data.name}}</strong><small class="wrap-text">{{data.description||'—'}}</small></div></template></Column>
          <Column header="Loại"><template #body="{data}"><Tag :value="vTypeLabel(data.type)" severity="secondary"/></template></Column>
          <Column header="Chi phí"><template #body="{data}"><strong>{{data.points_cost.toLocaleString('vi-VN')}} điểm</strong></template></Column>
          <Column header="Đã đổi / Tổng"><template #body="{data}">{{data.redeemed_count}} / {{data.total_quantity??'∞'}}</template></Column>
          <Column header="Trạng thái"><template #body="{data}"><Tag :value="data.is_active?'Hoạt động':'Tắt'" :severity="data.is_active?'success':'danger'"/></template></Column>
          <Column header="Hết hạn"><template #body="{data}"><span class="muted">{{data.expires_at?new Date(data.expires_at).toLocaleDateString('vi-VN'):'Không hạn'}}</span></template></Column>
          <Column header=""><template #body="{data}"><div class="actions"><Button icon="pi pi-pencil" severity="secondary" text rounded @click="openEdit(data)"/><Button icon="pi pi-trash" severity="danger" text rounded :loading="deleting===data.id" @click="deleteVoucher(data)"/></div></template></Column>
          <template #empty>Chưa có voucher nào.</template>
        </DataTable>
      </template></Card>
    </template>
    <Dialog v-model:visible="modalOpen" modal :header="editingVoucher?'Sửa voucher':'Tạo voucher mới'" :style="{width:'min(42rem,95vw)'}">
      <div class="form-grid"><label class="full">Tên voucher *<InputText v-model="form.name" fluid/></label><label class="full">Mô tả<Textarea v-model="form.description" rows="2" fluid/></label><label>Loại<Select v-model="form.type" :options="[{label:'Giảm %',value:'discount_percent'},{label:'Giảm tiền cố định',value:'discount_fixed'},{label:'Khóa học miễn phí',value:'free_course'},{label:'Quà tặng vật lý',value:'physical_gift'},{label:'AI quota',value:'ai_quota'}]" option-label="label" option-value="value" fluid/></label><label>Giá trị giảm<InputNumber v-model="form.discount_value" :min="0" fluid/></label><label>Chi phí điểm *<InputNumber v-model="form.points_cost" :min="1" fluid/></label><label>Số lượng<InputNumber v-model="form.total_quantity" :min="1" fluid/></label><label>Ngày hết hạn<DatePicker :model-value="form.expires_at?new Date(form.expires_at):null" date-format="dd/mm/yy" show-icon fluid @update:model-value="form.expires_at=$event?new Date($event).toISOString().slice(0,10):''"/></label><label class="check"><Checkbox v-model="form.is_active" binary/> Đang hoạt động</label></div>
      <template #footer><Button label="Hủy" severity="secondary" outlined @click="modalOpen=false"/><Button :label="editingVoucher?'Cập nhật':'Tạo mới'" :loading="saving" @click="saveVoucher"/></template>
    </Dialog>
  </div>
</template>

<style scoped>
.report-page{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-header span,.metric-card small{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--p-text-muted-color)}.page-header h1{margin:.2rem 0;font-size:1.75rem;color:var(--p-text-color)}.page-header p,.muted,.metric-card span{color:var(--p-text-muted-color)}.actions,.filters{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}.filters>*{min-width:12rem}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.metric-card :deep(.p-card-content){display:flex;flex-direction:column;gap:.3rem;padding:0}.metric-card strong{font-size:1.45rem;color:var(--p-text-color);font-variant-numeric:tabular-nums}.primary-cell{display:flex;flex-direction:column;min-width:11rem}.primary-cell small{color:var(--p-text-muted-color)}.money{font-weight:700;font-variant-numeric:tabular-nums;color:var(--p-primary-color)}.wrap-text{white-space:normal;min-width:12rem}.distribution{display:grid;grid-template-columns:2fr 1fr;gap:1rem}.list{display:flex;flex-direction:column;gap:.9rem}.list-row{display:flex;justify-content:space-between;gap:1rem;color:var(--p-text-color)}.bar{height:.45rem;border-radius:999px;background:var(--p-content-border-color);overflow:hidden}.bar>i{display:block;height:100%;background:var(--p-primary-color)}.notice{padding:1rem;border-left:4px solid var(--p-orange-500);background:var(--p-orange-50);color:var(--p-orange-900);border-radius:var(--p-border-radius-md)}:global(.dark) .notice{background:color-mix(in srgb,var(--p-orange-500) 12%,var(--p-content-background));color:var(--p-text-color)}@media(max-width:900px){.page-header{flex-direction:column}.metrics{grid-template-columns:repeat(2,1fr)}.distribution{grid-template-columns:1fr}}@media(max-width:520px){.metrics{grid-template-columns:1fr}.filters>*{width:100%}}
</style>

<style scoped>.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.form-grid label,.form-stack{display:flex;flex-direction:column;gap:.45rem;color:var(--p-text-color);font-size:.85rem;font-weight:600}.form-grid .full{grid-column:1/-1}.check{flex-direction:row!important;align-items:center}.operation-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}@media(max-width:900px){.operation-grid{grid-template-columns:1fr}}@media(max-width:520px){.form-grid{grid-template-columns:1fr}.form-grid .full{grid-column:auto}}</style>