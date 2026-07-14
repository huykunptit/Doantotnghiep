<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useToast } from '~/composables/useToast'
// Icons removed - using PrimeIcons
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
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
  <AdminWorkspaceShell
    title="Điểm & Phần thưởng"
    description="Quản lý hệ thống tích điểm, voucher đổi thưởng và theo dõi hoạt động học viên"
    :breadcrumb="['Trang chủ', 'Điểm & Phần thưởng']"
  >
    <div class="pts-admin-body">
      <!-- Tabs -->
      <div class="pts-tabs">
        <button class="pts-tab" :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">
          <BarChart3 :size="15" /> Tổng quan
        </button>
        <button class="pts-tab" :class="{ active: activeTab === 'vouchers' }" @click="activeTab = 'vouchers'">
          <Gift :size="15" /> Quản lý Voucher
        </button>
      </div>

      <!-- ── OVERVIEW ── -->
      <div v-if="activeTab === 'overview'">
        <!-- KPI -->
        <div class="pts-kpi-grid">
          <div class="dashboard-card pts-kpi-card">
            <i class="pi pi-money-bill" style="font-size:1.875rem" />
            <div><p class="kpi-lbl">Điểm đã phát</p><strong class="kpi-val">{{ stats?.totals?.total_issued?.toLocaleString('vi-VN') ?? '—' }}</strong></div>
          </div>
          <div class="dashboard-card pts-kpi-card">
            <Gift :size="30" class="kpi-ico" style="color:#7c3aed" />
            <div><p class="kpi-lbl">Voucher hoạt động</p><strong class="kpi-val">{{ stats?.totals?.active_vouchers ?? '—' }}</strong></div>
          </div>
          <div class="dashboard-card pts-kpi-card">
            <Ticket :size="30" class="kpi-ico" style="color:#0F6E8C" />
            <div><p class="kpi-lbl">Lần đổi quà</p><strong class="kpi-val">{{ stats?.totals?.redemptions?.toLocaleString('vi-VN') ?? '—' }}</strong></div>
          </div>
          <div class="dashboard-card pts-kpi-card">
            <i class="pi pi-arrow-up" style="font-size:1.875rem" />
            <div><p class="kpi-lbl">Điểm đã tiêu</p><strong class="kpi-val">{{ stats?.totals?.total_redeemed?.toLocaleString('vi-VN') ?? '—' }}</strong></div>
          </div>
        </div>

        <!-- Chart + top students -->
        <div class="pts-charts-row">
          <div class="dashboard-card chart-card">
            <div class="chart-head"><BarChart3 :size="15" class="text-primary" /><h3>Điểm phát ra 14 ngày</h3></div>
            <UiBarChart v-if="stats" :values="trendEarned()" :labels="trendLabels()" color="#f59e0b" :height="180" />
            <div v-else class="pts-shimmer" style="height:180px" />
          </div>

          <div class="dashboard-card chart-card">
            <div class="chart-head"><i class="pi pi-trophy" style="font-size:0.9375rem" /><h3>Top học viên điểm cao</h3></div>
            <div class="pts-top-list">
              <div v-if="statsLoading" v-for="i in 5" :key="i" class="pts-shimmer" style="height:40px; border-radius:8px;" />
              <div v-else v-for="(u, i) in stats?.top_students ?? []" :key="u.id" class="pts-top-row">
                <span class="pts-top-rank">{{ ['🥇','🥈','🥉'][i] ?? (i+1) }}</span>
                <div class="pts-top-avatar">{{ u.name?.slice(0,2).toUpperCase() }}</div>
                <div class="pts-top-info"><p>{{ u.name }}</p><small>{{ u.student_code }}</small></div>
                <div class="pts-top-pts"><i class="pi pi-money-bill" style="font-size:0.75rem" /><strong>{{ u.points_balance?.toLocaleString('vi-VN') }}</strong></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent redemptions -->
        <div class="dashboard-card">
          <div class="chart-head" style="padding: 16px 20px 0;"><Ticket :size="15" /><h3>Đổi quà gần đây</h3></div>
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead><tr><th>Học viên</th><th>Voucher</th><th>Điểm tiêu</th><th>Thời gian</th></tr></thead>
              <tbody>
                <tr v-if="!stats?.recent_redemptions?.length"><td colspan="4" class="crud-empty">Chưa có lần đổi quà nào.</td></tr>
                <tr v-else v-for="r in stats.recent_redemptions" :key="r.id">
                  <td><strong>{{ r.user?.name }}</strong><small class="text-muted"> — {{ r.user?.student_code }}</small></td>
                  <td>{{ r.voucher?.name }}</td>
                  <td><span class="pts-spent-badge">{{ r.points_spent?.toLocaleString('vi-VN') }} điểm</span></td>
                  <td class="text-muted" style="font-size:.75rem">{{ new Date(r.created_at).toLocaleString('vi-VN') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── VOUCHERS ── -->
      <div v-if="activeTab === 'vouchers'">
        <div class="crud-toolbar">
          <h3 class="crud-toolbar-title">Danh sách Voucher</h3>
          <button class="crud-primary-btn" @click="openCreate"><i class="pi pi-plus" style="font-size:0.9375rem" /> Tạo Voucher</button>
        </div>

        <div class="crud-panel">
          <div class="crud-table-wrap">
            <table class="crud-table">
              <thead>
                <tr>
                  <th>Tên voucher</th>
                  <th>Loại</th>
                  <th>Chi phí (điểm)</th>
                  <th>Đã đổi / Tổng</th>
                  <th>Trạng thái</th>
                  <th>Hết hạn</th>
                  <th style="width:100px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="voucherLoading"><td colspan="7" class="crud-empty">Đang tải...</td></tr>
                <tr v-else-if="vouchers.length === 0"><td colspan="7" class="crud-empty">Chưa có voucher nào.</td></tr>
                <tr v-else v-for="v in vouchers" :key="v.id">
                  <td><strong>{{ v.name }}</strong><p v-if="v.description" class="text-muted" style="font-size:.72rem;margin:2px 0 0">{{ v.description.slice(0,50) }}</p></td>
                  <td><span class="crud-badge">{{ vTypeLabel(v.type) }}</span></td>
                  <td><strong>{{ v.points_cost.toLocaleString('vi-VN') }}</strong></td>
                  <td>{{ v.redeemed_count }} / {{ v.total_quantity ?? '∞' }}</td>
                  <td>
                    <span class="crud-badge" :class="v.is_active ? 'role-instructor' : 'role-student'">
                      {{ v.is_active ? 'Hoạt động' : 'Tắt' }}
                    </span>
                  </td>
                  <td class="text-muted" style="font-size:.75rem">{{ v.expires_at ? new Date(v.expires_at).toLocaleDateString('vi-VN') : 'Không hạn' }}</td>
                  <td>
                    <div style="display:flex;gap:4px">
                      <button class="action-btn is-edit" @click="openEdit(v)"><i class="pi pi-pencil" style="font-size:0.8125rem" /></button>
                      <button class="action-btn is-delete" :disabled="deleting === v.id" @click="deleteVoucher(v)"><i class="pi pi-trash" style="font-size:0.8125rem" /></button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Voucher Modal ── -->
    <Teleport to="body">
      <Transition name="crud-modal-fade">
        <div v-if="modalOpen" class="crud-modal-backdrop" @click.self="modalOpen = false">
          <div class="crud-modal" style="max-width:480px">
            <div class="crud-modal-head">
              <h3>{{ editingVoucher ? 'Sửa Voucher' : 'Tạo Voucher mới' }}</h3>
              <button class="crud-modal-close" @click="modalOpen = false"><i class="pi pi-times" style="font-size:1.0rem" /></button>
            </div>
            <div class="crud-modal-body">
              <div class="crud-form-grid">
                <label class="crud-field" style="grid-column:1/-1">
                  <span>Tên voucher *</span>
                  <input v-model="form.name" type="text" placeholder="VD: Giảm 20% học phí" />
                </label>
                <label class="crud-field" style="grid-column:1/-1">
                  <span>Mô tả</span>
                  <textarea v-model="form.description" rows="2" placeholder="Mô tả ngắn..." />
                </label>
                <label class="crud-field">
                  <span>Loại *</span>
                  <select v-model="form.type">
                    <option value="discount_percent">Giảm %</option>
                    <option value="discount_fixed">Giảm tiền cố định</option>
                    <option value="free_course">Khóa học miễn phí</option>
                    <option value="physical_gift">Quà tặng vật lý</option>
                    <option value="ai_quota">AI quota</option>
                  </select>
                </label>
                <label class="crud-field">
                  <span>Giá trị giảm</span>
                  <input v-model.number="form.discount_value" type="number" min="0" placeholder="% hoặc VNĐ" />
                </label>
                <label class="crud-field">
                  <span>Chi phí điểm *</span>
                  <input v-model.number="form.points_cost" type="number" min="1" />
                </label>
                <label class="crud-field">
                  <span>Số lượng (trống = không giới hạn)</span>
                  <input v-model.number="form.total_quantity" type="number" min="1" placeholder="Không giới hạn" />
                </label>
                <label class="crud-field">
                  <span>Ngày hết hạn</span>
                  <input v-model="form.expires_at" type="date" />
                </label>
                <label class="crud-field" style="display:flex;flex-direction:row;align-items:center;gap:10px">
                  <input type="checkbox" v-model="form.is_active" style="width:auto" />
                  <span>Đang hoạt động</span>
                </label>
              </div>
            </div>
            <div class="crud-modal-foot">
              <button class="crud-secondary-btn" @click="modalOpen = false">Hủy</button>
              <button class="crud-primary-btn" :disabled="saving" @click="saveVoucher">
                {{ saving ? 'Đang lưu...' : editingVoucher ? 'Cập nhật' : 'Tạo mới' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AdminWorkspaceShell>
</template>

<style scoped>
.pts-admin-body { display: flex; flex-direction: column; gap: 14px; }

.pts-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e2e8f0; }
.pts-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; font-size: 0.82rem; font-weight: 600;
  border: none; background: transparent; color: #64748b; cursor: pointer;
  border-bottom: 3px solid transparent; margin-bottom: -2px;
  border-radius: 6px 6px 0 0; transition: color 0.15s, border-color 0.15s;
}
.pts-tab:hover { color: #1e293b; background: #f8fafc; }
.pts-tab.active { color: #047857; border-bottom-color: #047857; background: #f0fdf4; }

.pts-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
@media (max-width: 900px) { .pts-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
.pts-kpi-card { padding: 18px; display: flex; align-items: center; gap: 14px; }
.kpi-ico { flex-shrink: 0; opacity: 0.85; }
.kpi-lbl { margin: 0; font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
.kpi-val { font-size: 1.6rem; font-weight: 900; color: #1e293b; line-height: 1.1; }

.pts-charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 900px) { .pts-charts-row { grid-template-columns: 1fr; } }
.chart-card { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
.chart-head { display: flex; align-items: center; gap: 8px; }
.chart-head h3 { margin: 0; font-size: 0.88rem; font-weight: 700; color: #1e293b; }
.text-gold { color: #d97706; }

.pts-top-list { display: flex; flex-direction: column; gap: 5px; }
.pts-top-row { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; }
.pts-top-row:hover { background: #f8fafc; }
.pts-top-rank { width: 24px; text-align: center; font-size: 0.9rem; }
.pts-top-avatar {
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, #0F6E8C, #1D9E75);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.68rem; font-weight: 800; color: #fff;
}
.pts-top-info { flex: 1; min-width: 0; }
.pts-top-info p { margin: 0; font-size: 0.8rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pts-top-info small { font-size: 0.65rem; color: #94a3b8; }
.pts-top-pts { display: flex; align-items: center; gap: 3px; font-size: 0.78rem; color: #475569; }
.pts-top-pts strong { font-weight: 800; }

.pts-spent-badge { font-size: 0.72rem; font-weight: 700; background: #fffbeb; color: #b45309; padding: 2px 7px; border-radius: 999px; border: 1px solid #fde68a; }

.pts-shimmer {
  border-radius: 8px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

.crud-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
