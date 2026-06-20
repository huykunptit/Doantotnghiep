<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Save, RotateCcw, ShieldCheck, ShieldAlert } from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

interface Permission { id: number; name: string }
interface Role { id: number; name: string; permissions?: Permission[] }

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const roles = ref<Role[]>([])
const permissions = ref<Permission[]>([])
const errorMessage = ref('')
const successMessage = ref('')

const original = ref<Record<number, Set<string>>>({})
const current = ref<Record<number, Set<string>>>({})

const ROLE_LABELS: Record<string, string> = {
  admin: 'Quản trị viên',
  instructor: 'Giảng viên',
  student: 'Học viên',
}

const ROLE_COLOR: Record<string, string> = {
  admin: 'rl-role--admin',
  instructor: 'rl-role--instructor',
  student: 'rl-role--student',
}

const PERMISSION_LABELS: Record<string, string> = {
  view_dashboard: 'Xem bảng điều khiển',
  manage_users: 'Quản lý người dùng',
  manage_roles: 'Quản lý vai trò & phân quyền',
  manage_courses: 'Quản lý khoá học',
  manage_lessons: 'Quản lý bài học',
  manage_exams: 'Quản lý kỳ thi & quiz',
  manage_finances: 'Quản lý tài chính & đơn hàng',
  take_exams: 'Tham gia làm bài thi',
  view_reports: 'Xem báo cáo & thống kê',
  submit_reviews: 'Gửi đánh giá khoá học',
}

const PERMISSION_GROUPS: Array<{ key: string; label: string; match: (name: string) => boolean }> = [
  { key: 'system', label: 'Hệ thống & Quản trị', match: (n) => /^(view_dashboard|manage_users|manage_roles)$/.test(n) },
  { key: 'course', label: 'Khoá học & Bài giảng', match: (n) => /(course|lesson)/.test(n) },
  { key: 'exam', label: 'Thi cử & Đánh giá', match: (n) => /(exam|review)/.test(n) },
  { key: 'finance', label: 'Tài chính & Báo cáo', match: (n) => /(finance|report)/.test(n) },
  { key: 'other', label: 'Khác', match: () => true },
]

const roleLabel = (name: string) => ROLE_LABELS[name] || name
const roleColor = (name: string) => ROLE_COLOR[name] || ''
const permLabel = (name: string) =>
  PERMISSION_LABELS[name] || name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

const permissionGroups = computed(() => {
  const used = new Set<number>()
  return PERMISSION_GROUPS
    .map((g) => {
      const items = permissions.value.filter((p) => {
        if (used.has(p.id)) return false
        if (!g.match(p.name)) return false
        used.add(p.id)
        return true
      })
      return { ...g, items }
    })
    .filter((g) => g.items.length > 0)
})

const hasPermission = (role: Role, permName: string) => {
  if (role.name === 'admin') return true
  return current.value[role.id]?.has(permName) ?? false
}

const permCount = (role: Role) =>
  role.name === 'admin' ? permissions.value.length : current.value[role.id]?.size ?? 0

const togglePermission = (role: Role, permName: string) => {
  if (role.name === 'admin') return
  const set = current.value[role.id] ?? new Set<string>()
  if (set.has(permName)) set.delete(permName)
  else set.add(permName)
  current.value = { ...current.value, [role.id]: new Set(set) }
}

const hasUnsavedChanges = computed(() => {
  return roles.value.some((role) => {
    if (role.name === 'admin') return false
    const a = original.value[role.id] ?? new Set()
    const b = current.value[role.id] ?? new Set()
    if (a.size !== b.size) return true
    for (const x of a) if (!b.has(x)) return true
    return false
  })
})

const snapshot = (data: Role[]) => {
  const orig: Record<number, Set<string>> = {}
  const curr: Record<number, Set<string>> = {}
  data.forEach((role) => {
    const set = new Set((role.permissions || []).map((p) => p.name))
    orig[role.id] = set
    curr[role.id] = new Set(set)
  })
  original.value = orig
  current.value = curr
}

const resetChanges = () => {
  const curr: Record<number, Set<string>> = {}
  Object.entries(original.value).forEach(([id, set]) => { curr[Number(id)] = new Set(set) })
  current.value = curr
  successMessage.value = ''
  errorMessage.value = ''
}

const loadData = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await useApi<any>('/admin/roles', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    roles.value = res.roles || []
    permissions.value = res.permissions || []
    snapshot(roles.value)
  } catch {
    errorMessage.value = 'Không thể tải danh sách quyền. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

const savePermissions = async () => {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const targets = roles.value.filter((r) => r.name !== 'admin')
    await Promise.all(
      targets.map((role) =>
        useApi(`/admin/roles/${role.id}/permissions`, {
          method: 'PUT',
          headers: { Authorization: `Bearer ${auth.token}` },
          body: { permissions: Array.from(current.value[role.id] ?? []) },
        }),
      ),
    )
    successMessage.value = 'Cập nhật phân quyền thành công.'
    await loadData()
  } catch (err: any) {
    errorMessage.value = err?.data?.message || 'Có lỗi xảy ra khi lưu phân quyền.'
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <AdminWorkspaceShell
    title="Phân quyền hệ thống"
    description="Cấu hình quyền hạn cho từng vai trò. Vai trò admin luôn có toàn bộ quyền và không thể chỉnh sửa."
    :breadcrumb="['Trang chủ', 'Phân quyền']"
  >
    <div class="rl-stack">

      <!-- Skeleton -->
      <template v-if="loading">
        <div class="rl-skeleton rl-skeleton--sm" />
        <div class="rl-skeleton rl-skeleton--lg" />
        <div class="rl-skeleton rl-skeleton--lg" />
      </template>

      <template v-else>

        <!-- Role summary -->
        <div class="dashboard-card rl-summary">
          <p class="rl-summary-label">Tổng quan vai trò</p>
          <div class="rl-role-chips">
            <div v-for="role in roles" :key="role.id" class="rl-role-chip" :class="roleColor(role.name)">
              <span class="rl-role-name">{{ roleLabel(role.name) }}</span>
              <span class="rl-role-stat">
                {{ permCount(role) }}<span class="rl-role-total"> / {{ permissions.length }}</span>
              </span>
              <div class="rl-role-bar">
                <div
                  class="rl-role-fill"
                  :style="{ width: `${Math.round((permCount(role) / permissions.length) * 100)}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Sticky action bar -->
        <div class="rl-actionbar dashboard-card">
          <div class="rl-actionbar-status">
            <template v-if="hasUnsavedChanges">
              <ShieldAlert :size="15" class="rl-status-icon rl-status-icon--warn" />
              <span class="rl-status-text rl-status-text--warn">Có thay đổi chưa lưu</span>
            </template>
            <template v-else>
              <ShieldCheck :size="15" class="rl-status-icon rl-status-icon--ok" />
              <span class="rl-status-text rl-status-text--ok">Đã đồng bộ</span>
            </template>
          </div>
          <div class="rl-actionbar-btns">
            <button
              class="rl-btn rl-btn--ghost"
              type="button"
              :disabled="!hasUnsavedChanges || saving"
              @click="resetChanges"
            >
              <RotateCcw :size="14" /> Hoàn tác
            </button>
            <button
              class="rl-btn rl-btn--primary"
              type="button"
              :disabled="!hasUnsavedChanges || saving"
              @click="savePermissions"
            >
              <Save :size="14" /> {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
        <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>

        <!-- Permission groups -->
        <div
          v-for="group in permissionGroups"
          :key="group.key"
          class="dashboard-card rl-group"
        >
          <div class="rl-group-header">
            <strong class="rl-group-title">{{ group.label }}</strong>
            <span class="rl-group-count">{{ group.items.length }} quyền</span>
          </div>

          <div class="rl-table-wrap">
            <table class="rl-table">
              <thead>
                <tr>
                  <th class="rl-th-perm">Quyền</th>
                  <th v-for="role in roles" :key="role.id" class="rl-th-role">
                    <span class="rl-role-pill" :class="roleColor(role.name)">
                      {{ roleLabel(role.name) }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="perm in group.items" :key="perm.id" class="rl-row">
                  <td class="rl-td-perm">
                    <span class="rl-perm-label">{{ permLabel(perm.name) }}</span>
                    <code class="rl-perm-code">{{ perm.name }}</code>
                  </td>
                  <td v-for="role in roles" :key="role.id" class="rl-td-toggle">
                    <button
                      type="button"
                      class="permission-toggle"
                      :class="{
                        'is-checked': hasPermission(role, perm.name),
                        'is-locked': role.name === 'admin',
                      }"
                      :disabled="role.name === 'admin'"
                      :aria-checked="hasPermission(role, perm.name)"
                      role="switch"
                      @click="togglePermission(role, perm.name)"
                    >
                      <span class="permission-toggle-thumb" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </template>
    </div>
  </AdminWorkspaceShell>
</template>

<style scoped>
/* ── Layout ── */
.rl-stack {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ── Skeleton ── */
.rl-skeleton {
  border-radius: 30px;
  background: var(--bg, #eff2f0);
  border: 1px solid var(--line);
  animation: rl-pulse 1.4s ease-in-out infinite;
}
.rl-skeleton--sm  { height: 80px; }
.rl-skeleton--lg  { height: 200px; }

@keyframes rl-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

/* ── Role summary ── */
.rl-summary {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rl-summary-label {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--muted);
}

.rl-role-chips {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.rl-role-chip {
  flex: 1;
  min-width: 160px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid var(--line-strong, rgba(31,49,43,0.16));
  background: var(--surface-strong, #fff);
}

.rl-role-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text);
}

.rl-role-stat {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--text);
  line-height: 1;
}

.rl-role-total {
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--muted);
}

.rl-role-bar {
  height: 4px;
  border-radius: 999px;
  background: var(--bg, #eff2f0);
  overflow: hidden;
}

.rl-role-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 500ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* Role accent colors */
.rl-role--admin   .rl-role-fill { background: var(--green, #1d9e75); }
.rl-role--admin   { border-color: rgba(29,158,117,0.25); }

.rl-role--instructor .rl-role-fill { background: #378add; }
.rl-role--instructor { border-color: rgba(55,138,221,0.25); }

.rl-role--student .rl-role-fill { background: #7c3aed; }
.rl-role--student { border-color: rgba(124,58,237,0.25); }

/* ── Action bar ── */
.rl-actionbar {
  position: sticky;
  top: 16px;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 20px !important;
}

.rl-actionbar-status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rl-status-icon--ok   { color: var(--green, #1d9e75); flex-shrink: 0; }
.rl-status-icon--warn { color: #b45309; flex-shrink: 0; }

.rl-status-text {
  font-size: 0.84rem;
  font-weight: 600;
}
.rl-status-text--ok   { color: var(--green-deep, #085041); }
.rl-status-text--warn { color: #b45309; }

.rl-actionbar-btns {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rl-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 16px;
  border-radius: 10px;
  font-size: 0.84rem;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background 140ms, border-color 140ms, transform 140ms, opacity 140ms;
}

.rl-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
.rl-btn:not(:disabled):hover { transform: translateY(-1px); }

.rl-btn--ghost {
  background: transparent;
  color: var(--text);
  border-color: var(--line-strong, rgba(31,49,43,0.16));
}
.rl-btn--ghost:not(:disabled):hover { background: var(--bg, #eff2f0); }

.rl-btn--primary {
  background: linear-gradient(135deg, var(--green, #1d9e75) 0%, var(--green-deep, #085041) 100%);
  color: #fff;
  box-shadow: 0 4px 12px -4px rgba(29,158,117,0.5);
}
.rl-btn--primary:not(:disabled):hover {
  box-shadow: 0 6px 16px -4px rgba(29,158,117,0.6);
}

/* ── Permission group ── */
.rl-group {
  padding: 0 !important;
  overflow: hidden;
}

.rl-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 2px solid var(--line-strong, rgba(31,49,43,0.16));
}

.rl-group-title {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--text);
}

.rl-group-count {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--bg, #eff2f0);
  border: 1px solid var(--line);
  color: var(--muted);
}

/* ── Table ── */
.rl-table-wrap {
  overflow-x: auto;
}

.rl-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

.rl-table thead tr {
  border-bottom: 1px solid var(--line, #dde5e1);
}

.rl-th-perm {
  padding: 10px 20px;
  text-align: left;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--muted);
  width: 100%;
}

.rl-th-role {
  padding: 10px 16px;
  text-align: center;
  white-space: nowrap;
}

/* Role pill in header */
.rl-role-pill {
  display: inline-flex;
  align-items: center;
  height: 26px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  border: 1px solid transparent;
}

.rl-role-pill.rl-role--admin       { background: rgba(29,158,117,0.1);  color: var(--green-deep); border-color: rgba(29,158,117,0.22); }
.rl-role-pill.rl-role--instructor  { background: rgba(55,138,221,0.1);  color: #1a5fa8;           border-color: rgba(55,138,221,0.22); }
.rl-role-pill.rl-role--student     { background: rgba(124,58,237,0.1);  color: #5b21b6;           border-color: rgba(124,58,237,0.22); }

/* ── Table rows ── */
.rl-row {
  border-bottom: 1px solid var(--line, #dde5e1);
  transition: background 140ms;
}
.rl-row:last-child { border-bottom: none; }
.rl-row:hover { background: rgba(17,17,17,0.025); }

.rl-td-perm {
  padding: 12px 20px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.rl-perm-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.rl-perm-code {
  font-size: 0.68rem;
  color: var(--muted);
  font-family: 'Courier New', monospace;
  opacity: 0.7;
}

.rl-td-toggle {
  padding: 12px 16px;
  text-align: center;
  vertical-align: middle;
}

/* ── Toggle switch ── */
.permission-toggle {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
  border-radius: 999px;
  background: var(--line-strong, rgba(31,49,43,0.16));
  border: none;
  cursor: pointer;
  transition: background 200ms ease;
  padding: 0;
  vertical-align: middle;
  flex-shrink: 0;
}

.permission-toggle:hover:not(:disabled) {
  background: rgba(31,49,43,0.25);
}

.permission-toggle.is-checked {
  background: var(--green, #1d9e75);
}

.permission-toggle.is-locked {
  background: rgba(29,158,117,0.45);
  cursor: not-allowed;
}

.permission-toggle:focus-visible {
  outline: 2px solid var(--green);
  outline-offset: 2px;
}

.permission-toggle-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 4px rgba(17,17,17,0.2);
  transition: transform 200ms ease;
}

.permission-toggle.is-checked .permission-toggle-thumb,
.permission-toggle.is-locked .permission-toggle-thumb {
  transform: translateX(18px);
}

/* ── Dark mode ── */
[data-theme="dark"] .rl-role-chip       { background: rgba(255,255,255,0.04); }
[data-theme="dark"] .rl-role-bar        { background: rgba(255,255,255,0.08); }
[data-theme="dark"] .rl-group-count     { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .rl-skeleton        { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.08); }
[data-theme="dark"] .rl-btn--ghost      { border-color: rgba(255,255,255,0.12); }
[data-theme="dark"] .rl-btn--ghost:not(:disabled):hover { background: rgba(255,255,255,0.06); }
[data-theme="dark"] .rl-table thead tr  { border-color: rgba(255,255,255,0.1); }
[data-theme="dark"] .rl-row             { border-color: rgba(255,255,255,0.07); }
[data-theme="dark"] .rl-row:hover       { background: rgba(255,255,255,0.03); }
[data-theme="dark"] .permission-toggle  { background: rgba(255,255,255,0.15); }
[data-theme="dark"] .rl-role--admin     { border-color: rgba(29,158,117,0.3); }
[data-theme="dark"] .rl-role--instructor { border-color: rgba(55,138,221,0.3); }
[data-theme="dark"] .rl-role--student   { border-color: rgba(124,58,237,0.3); }
[data-theme="dark"] .rl-role-pill.rl-role--admin      { background: rgba(29,158,117,0.15); color: #5ddfb4; }
[data-theme="dark"] .rl-role-pill.rl-role--instructor { background: rgba(55,138,221,0.15); color: #7db8ed; }
[data-theme="dark"] .rl-role-pill.rl-role--student    { background: rgba(124,58,237,0.15); color: #a78bfa; }
</style>
