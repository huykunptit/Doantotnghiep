<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Phân quyền']"
    description="Cấu hình quyền hạn cho từng vai trò. Vai trò admin luôn có toàn bộ quyền và không thể chỉnh sửa."
    title="Phân quyền hệ thống"
  >
    <section class="space-y-6">
      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-4">
        <div class="h-12 rounded-2xl bg-surface-high animate-pulse" />
        <div class="h-64 rounded-2xl bg-surface-high animate-pulse" />
      </div>

      <template v-else>
        <!-- Role summary chips -->
        <UiCard>
          <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm font-semibold text-on-surface-variant">Tổng quan vai trò:</p>
            <span
              v-for="role in roles"
              :key="role.id"
              class="inline-flex items-center gap-2 rounded-full border border-surface-dim/50 bg-surface-low px-3 py-1.5 text-sm"
            >
              <span class="font-bold text-on-surface">{{ roleLabel(role.name) }}</span>
              <span class="text-xs text-on-surface-variant">
                {{ permCount(role) }} / {{ permissions.length }} quyền
              </span>
            </span>
          </div>
        </UiCard>

        <!-- Action bar -->
        <div
          class="sticky top-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-surface-dim/50 bg-surface-lowest/95 px-5 py-3 backdrop-blur-md shadow-sm"
        >
          <div class="flex items-center gap-3">
            <span
              v-if="hasUnsavedChanges"
              class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700"
            >
              <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse" />
              Có thay đổi chưa lưu
            </span>
            <span
              v-else
              class="inline-flex items-center gap-1.5 rounded-full bg-secondary-50 px-3 py-1 text-xs font-bold text-secondary"
            >
              <span class="h-1.5 w-1.5 rounded-full bg-secondary" />
              Đã đồng bộ
            </span>
          </div>
          <div class="flex items-center gap-2">
            <UiButton variant="ghost" :disabled="!hasUnsavedChanges || saving" @click="resetChanges">
              Hoàn tác
            </UiButton>
            <UiButton :disabled="!hasUnsavedChanges || saving" @click="savePermissions">
              {{ saving ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </UiButton>
          </div>
        </div>

        <!-- Feedback -->
        <div v-if="errorMessage" class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error">
          {{ errorMessage }}
        </div>
        <div v-if="successMessage" class="rounded-xl border border-success/30 bg-success/10 p-3 text-sm text-success">
          {{ successMessage }}
        </div>

        <!-- Permission groups -->
        <UiCard
          v-for="group in permissionGroups"
          :key="group.key"
          class="overflow-hidden"
        >
          <div class="flex items-center justify-between border-b border-surface-dim/50 pb-3 mb-3">
            <div>
              <h3 class="text-base font-bold text-on-surface">{{ group.label }}</h3>
              <p class="text-xs text-on-surface-variant">{{ group.items.length }} quyền</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                  <th class="px-3 py-3 min-w-[260px]">Quyền</th>
                  <th
                    v-for="role in roles"
                    :key="role.id"
                    class="px-3 py-3 text-center min-w-[120px]"
                  >
                    <span class="rounded-full bg-primary/10 px-3 py-1 text-primary">
                      {{ roleLabel(role.name) }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-dim/40">
                <tr
                  v-for="perm in group.items"
                  :key="perm.id"
                  class="hover:bg-surface-low/50 transition-colors"
                >
                  <td class="px-3 py-3">
                    <p class="font-semibold text-on-surface">{{ permLabel(perm.name) }}</p>
                    <code class="text-[11px] text-on-surface-variant/80">{{ perm.name }}</code>
                  </td>
                  <td v-for="role in roles" :key="role.id" class="px-3 py-3 text-center">
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
        </UiCard>
      </template>
    </section>
  </AdminWorkspaceShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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
  { key: 'system', label: 'Hệ thống & quản trị', match: (n) => /^(view_dashboard|manage_users|manage_roles)$/.test(n) },
  { key: 'course', label: 'Khoá học & bài giảng', match: (n) => /(course|lesson)/.test(n) },
  { key: 'exam', label: 'Thi cử & đánh giá', match: (n) => /(exam|review)/.test(n) },
  { key: 'finance', label: 'Tài chính & báo cáo', match: (n) => /(finance|report)/.test(n) },
  { key: 'other', label: 'Khác', match: () => true },
]

const roleLabel = (name: string) => ROLE_LABELS[name] || name
const permLabel = (name: string) =>
  PERMISSION_LABELS[name] ||
  name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

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

const hasPermission = (role: Role, permissionName: string) => {
  if (role.name === 'admin') return true
  return current.value[role.id]?.has(permissionName) ?? false
}

const permCount = (role: Role) =>
  role.name === 'admin' ? permissions.value.length : current.value[role.id]?.size ?? 0

const togglePermission = (role: Role, permissionName: string) => {
  if (role.name === 'admin') return
  const set = current.value[role.id] ?? new Set<string>()
  if (set.has(permissionName)) set.delete(permissionName)
  else set.add(permissionName)
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
  Object.entries(original.value).forEach(([id, set]) => {
    curr[Number(id)] = new Set(set)
  })
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
    errorMessage.value = 'Không thể tải danh sách quyền. Vui lòng kiểm tra API.'
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

<style scoped>
.permission-toggle {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  border-radius: 999px;
  background: var(--surface-high, #dde0db);
  border: none;
  cursor: pointer;
  transition: background 0.2s ease;
  padding: 0;
  vertical-align: middle;
}
.permission-toggle:hover:not(:disabled) {
  background: var(--surface-dim, #ced2cc);
}
.permission-toggle.is-checked {
  background: var(--green);
}
.permission-toggle.is-locked {
  background: rgba(var(--green-rgb), 0.4);
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
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(17, 17, 17, 0.18);
  transition: transform 0.2s ease;
}
.permission-toggle.is-checked .permission-toggle-thumb,
.permission-toggle.is-locked .permission-toggle-thumb {
  transform: translateX(20px);
}
</style>
