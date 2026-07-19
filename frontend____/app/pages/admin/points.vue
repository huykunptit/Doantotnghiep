<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useToast } from '~/composables/useToast'
import UiBarChart from '~/components/dashboard/charts/UiBarChart.vue'

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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Cấu hình hệ thống</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Điểm & Phần thưởng</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý hệ thống tích điểm, voucher đổi thưởng và theo dõi hoạt động học viên.</p>
      </div>
    </div>

    <div class="flex flex-col gap-5">
      <!-- Tabs -->
      <div class="flex gap-4 border-b border-[var(--line)]">
        <button
          class="inline-flex items-center gap-1.5 py-2.5 px-4 text-sm font-semibold border-b-2 transition-colors duration-150"
          :class="activeTab === 'overview' 
            ? 'border-[#1d9e75] text-[#1d9e75]' 
            : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'"
          @click="activeTab = 'overview'"
        >
          <i class="pi pi-chart-bar" /> Tổng quan
        </button>
        <button
          class="inline-flex items-center gap-1.5 py-2.5 px-4 text-sm font-semibold border-b-2 transition-colors duration-150"
          :class="activeTab === 'vouchers' 
            ? 'border-[#1d9e75] text-[#1d9e75]' 
            : 'border-transparent text-[var(--muted)] hover:text-[var(--text)]'"
          @click="activeTab = 'vouchers'"
        >
          <i class="pi pi-gift" /> Quản lý Voucher
        </button>
      </div>

      <!-- ── OVERVIEW ── -->
      <div v-if="activeTab === 'overview'" class="flex flex-col gap-5">
        <!-- KPI -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(29,158,117,0.08)] border-[rgba(29,158,117,0.2)]">
            <p class="text-xs font-bold uppercase tracking-wider text-[#1d9e75]">Điểm đã phát</p>
            <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ stats?.totals?.total_issued?.toLocaleString('vi-VN') ?? '—' }}</strong>
            <span class="text-xs text-[var(--muted)] font-medium">điểm tích lũy</span>
          </div>
          <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(139,92,246,0.08)] border-[rgba(139,92,246,0.2)]">
            <p class="text-xs font-bold uppercase tracking-wider text-violet-600">Voucher hoạt động</p>
            <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ stats?.totals?.active_vouchers ?? '—' }}</strong>
            <span class="text-xs text-[var(--muted)] font-medium">voucher áp dụng</span>
          </div>
          <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(55,138,221,0.08)] border-[rgba(55,138,221,0.2)]">
            <p class="text-xs font-bold uppercase tracking-wider text-blue-600">Lần đổi quà</p>
            <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ stats?.totals?.redemptions?.toLocaleString('vi-VN') ?? '—' }}</strong>
            <span class="text-xs text-[var(--muted)] font-medium">lượt thành công</span>
          </div>
          <div class="rounded-2xl p-5 flex flex-col gap-2 border bg-[rgba(245,158,11,0.08)] border-[rgba(245,158,11,0.2)]">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Điểm đã tiêu</p>
            <strong class="text-3xl font-extrabold tracking-tight text-[var(--text)]">{{ stats?.totals?.total_redeemed?.toLocaleString('vi-VN') ?? '—' }}</strong>
            <span class="text-xs text-[var(--muted)] font-medium">điểm quy đổi</span>
          </div>
        </div>

        <!-- Chart + top students -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- Chart -->
          <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
              <i class="pi pi-chart-bar text-[#1d9e75]" />
              <h3 class="text-sm font-semibold text-[var(--text)]">Điểm phát ra 14 ngày</h3>
            </div>
            <UiBarChart v-if="stats" :values="trendEarned()" :labels="trendLabels()" color="#1d9e75" :height="180" />
            <div v-else class="h-[180px] bg-[var(--surface)] animate-pulse rounded-xl" />
          </div>

          <!-- Top students -->
          <div class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
              <i class="pi pi-trophy text-amber-500" />
              <h3 class="text-sm font-semibold text-[var(--text)]">Top học viên điểm cao</h3>
            </div>
            <div class="flex flex-col gap-2">
              <div v-if="statsLoading" v-for="i in 5" :key="i" class="h-10 bg-[var(--surface)] animate-pulse rounded-xl" />
              <div v-else-if="!stats?.top_students?.length" class="text-center py-8 text-sm text-[var(--muted)]">Không có dữ liệu.</div>
              <div v-else v-for="(u, i) in stats?.top_students ?? []" :key="u.id" class="flex items-center justify-between p-2 rounded-xl hover:bg-[var(--surface)] transition-colors">
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold w-6 text-center">{{ ['🥇','🥈','🥉'][i] ?? (i+1) }}</span>
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-extrabold bg-[rgba(29,158,117,0.1)] text-[#085041] border border-[rgba(29,158,117,0.2)]">
                    {{ u.name?.slice(0,2).toUpperCase() }}
                  </div>
                  <div class="flex flex-col">
                    <span class="text-xs font-bold text-[var(--text)]">{{ u.name }}</span>
                    <span class="text-[10px] text-[var(--muted)]">{{ u.student_code }}</span>
                  </div>
                </div>
                <div class="flex items-center gap-1 text-xs text-[var(--text)]">
                  <i class="pi pi-money-bill text-[#1d9e75] text-[10px]" />
                  <strong class="font-extrabold">{{ u.points_balance?.toLocaleString('vi-VN') }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent redemptions -->
        <div class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
          <div class="flex items-center gap-2 px-6 pt-5 pb-4 border-b border-[var(--line)]">
            <i class="pi pi-ticket text-[#1d9e75]" />
            <h3 class="text-sm font-semibold text-[var(--text)]">Đổi quà gần đây</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-[var(--line)] bg-[var(--surface)]">
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Học viên</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Voucher</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Điểm tiêu</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Thời gian</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!stats?.recent_redemptions?.length" class="border-b border-[var(--line)]">
                  <td colspan="4" class="px-4 py-8 text-center text-sm text-[var(--muted)]">Chưa có lần đổi quà nào.</td>
                </tr>
                <tr v-else v-for="r in stats.recent_redemptions" :key="r.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-1.5">
                      <strong class="text-sm font-semibold text-[var(--text)]">{{ r.user?.name }}</strong>
                      <span class="text-xs text-[var(--muted)]">— {{ r.user?.student_code }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-sm font-medium text-[var(--text)]">{{ r.voucher?.name }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold bg-amber-50 text-amber-700 border border-amber-200">{{ r.points_spent?.toLocaleString('vi-VN') }} điểm</span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-xs text-[var(--muted)]">{{ new Date(r.created_at).toLocaleString('vi-VN') }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── VOUCHERS ── -->
      <div v-if="activeTab === 'vouchers'" class="flex flex-col gap-5">
        <div class="flex justify-between items-center">
          <h3 class="text-base font-bold text-[var(--text)]">Danh sách Voucher</h3>
          <button 
            class="inline-flex items-center gap-2 h-9 px-4 rounded-xl text-sm font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors"
            @click="openCreate"
          >
            <i class="pi pi-plus" /> Tạo Voucher
          </button>
        </div>

        <section class="bg-white border border-[var(--line)] rounded-2xl overflow-hidden shadow-sm">
          <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b border-[var(--line)] bg-[var(--surface)]">
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Tên voucher</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Loại</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Chi phí (điểm)</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Đã đổi / Tổng</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Trạng thái</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)]">Hết hạn</th>
                  <th class="px-4 py-3 text-left text-[0.72rem] font-bold uppercase tracking-wide text-[var(--muted)] w-[100px]"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="voucherLoading" class="border-b border-[var(--line)]">
                  <td colspan="7" class="px-4 py-8 text-center text-sm text-[var(--muted)]">Đang tải...</td>
                </tr>
                <tr v-else-if="vouchers.length === 0" class="border-b border-[var(--line)]">
                  <td colspan="7" class="px-4 py-8 text-center text-sm text-[var(--muted)]">Chưa có voucher nào.</td>
                </tr>
                <tr v-else v-for="v in vouchers" :key="v.id" class="border-b border-[var(--line)] hover:bg-[var(--surface)] transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex flex-col">
                      <strong class="text-sm font-semibold text-[var(--text)]">{{ v.name }}</strong>
                      <span v-if="v.description" class="text-xs text-[var(--muted)] mt-0.5">{{ v.description.slice(0,50) }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold bg-[var(--surface)] border border-[var(--line)] text-[var(--muted)]">{{ vTypeLabel(v.type) }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-sm font-semibold text-[var(--text)]">{{ v.points_cost.toLocaleString('vi-VN') }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-sm text-[var(--text)]">{{ v.redeemed_count }} / {{ v.total_quantity ?? '∞' }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <span 
                      class="inline-flex items-center h-5 px-2 rounded-full text-[0.7rem] font-bold"
                      :class="v.is_active 
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                        : 'bg-red-50 text-red-700 border border-red-200'"
                    >
                      {{ v.is_active ? 'Hoạt động' : 'Tắt' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-xs text-[var(--muted)]">
                    {{ v.expires_at ? new Date(v.expires_at).toLocaleDateString('vi-VN') : 'Không hạn' }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex items-center gap-1.5 justify-end">
                      <button 
                        class="w-7 h-7 rounded-lg border border-[var(--line)] bg-transparent hover:bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--text)] flex items-center justify-center transition-colors" 
                        @click="openEdit(v)"
                      >
                        <i class="pi pi-pencil text-xs" />
                      </button>
                      <button 
                        class="w-7 h-7 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 flex items-center justify-center transition-colors disabled:opacity-50" 
                        :disabled="deleting === v.id" 
                        @click="deleteVoucher(v)"
                      >
                        <i class="pi pi-trash text-xs" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>

    <!-- Voucher Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/45 backdrop-blur-sm" @click.self="modalOpen = false">
          <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-[var(--line)]">
              <h3 class="text-lg font-bold tracking-tight text-[var(--text)]">{{ editingVoucher ? 'Sửa Voucher' : 'Tạo Voucher mới' }}</h3>
              <button class="w-8 h-8 rounded-xl flex items-center justify-center border border-[var(--line)] text-sm font-bold text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--text)] transition-colors" @click="modalOpen = false">✕</button>
            </div>
            
            <div class="px-6 py-5 overflow-y-auto max-h-[75vh]">
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Tên voucher *</span>
                  <input v-model="form.name" type="text" placeholder="VD: Giảm 20% học phí" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]" />
                </div>
                <div class="col-span-2 flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Mô tả</span>
                  <textarea v-model="form.description" rows="2" placeholder="Mô tả ngắn..." class="p-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Loại *</span>
                  <select v-model="form.type" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] cursor-pointer">
                    <option value="discount_percent">Giảm %</option>
                    <option value="discount_fixed">Giảm tiền cố định</option>
                    <option value="free_course">Khóa học miễn phí</option>
                    <option value="physical_gift">Quà tặng vật lý</option>
                    <option value="ai_quota">AI quota</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Giá trị giảm</span>
                  <input v-model.number="form.discount_value" type="number" min="0" placeholder="% hoặc VNĐ" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Chi phí điểm *</span>
                  <input v-model.number="form.points_cost" type="number" min="1" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Số lượng (trống = không giới hạn)</span>
                  <input v-model.number="form.total_quantity" type="number" min="1" placeholder="Không giới hạn" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] focus:ring-2 focus:ring-[rgba(29,158,117,0.15)]" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span class="text-xs font-semibold text-[var(--text)]">Ngày hết hạn</span>
                  <input v-model="form.expires_at" type="date" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75]" />
                </div>
                <div class="flex items-center gap-2 mt-4 col-span-2">
                  <input type="checkbox" id="is_active_check" v-model="form.is_active" class="rounded border-gray-300 text-[#1d9e75] focus:ring-[#1d9e75]" />
                  <label for="is_active_check" class="text-xs font-semibold text-[var(--text)] cursor-pointer">Đang hoạt động</label>
                </div>
              </div>
            </div>
            
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-[var(--line)] bg-[var(--surface)]">
              <button class="inline-flex items-center gap-2 h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-sm font-semibold text-[var(--muted)] hover:text-[var(--text)] transition-colors" @click="modalOpen = false">Hủy</button>
              <button class="inline-flex items-center gap-2 h-9 px-4 rounded-xl text-sm font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50" :disabled="saving" @click="saveVoucher">
                {{ saving ? 'Đang lưu...' : editingVoucher ? 'Cập nhật' : 'Tạo mới' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.pts-shimmer {
  border-radius: 8px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

.crud-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
