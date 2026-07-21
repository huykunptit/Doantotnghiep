<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useApi } from '~/composables/useApi'

definePageMeta({ layout: 'admin', middleware: ['auth', 'admin'] })

interface Permission { id: number; name: string }
interface Role { id: number; name: string; permissions?: Permission[] }

const auth = useAuthStore()
const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const roles = ref<Role[]>([])
const permissions = ref<Permission[]>([])
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

const PERMISSION_GROUPS = [
  { key: 'system', label: 'Hệ thống & Quản trị', match: (n: string) => /^(view_dashboard|manage_users|manage_roles)$/.test(n) },
  { key: 'course', label: 'Khoá học & Bài giảng', match: (n: string) => /(course|lesson)/.test(n) },
  { key: 'exam', label: 'Thi cử & Đánh giá', match: (n: string) => /(exam|review)/.test(n) },
  { key: 'finance', label: 'Tài chính & Báo cáo', match: (n: string) => /(finance|report)/.test(n) },
  { key: 'other', label: 'Khác', match: () => true },
]

const roleLabel = (name: string) => ROLE_LABELS[name] || name
const permLabel = (name: string) =>
  PERMISSION_LABELS[name] || name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())

const permissionGroups = computed(() => {
  const used = new Set<number>()
  return PERMISSION_GROUPS.map(group => ({
    ...group,
    items: permissions.value.filter((permission) => {
      if (used.has(permission.id) || !group.match(permission.name)) return false
      used.add(permission.id)
      return true
    }),
  })).filter(group => group.items.length)
})

const hasPermission = (role: Role, name: string) =>
  role.name === 'admin' || (current.value[role.id]?.has(name) ?? false)

const permissionCount = (role: Role) =>
  role.name === 'admin' ? permissions.value.length : current.value[role.id]?.size ?? 0

const hasUnsavedChanges = computed(() => roles.value.some((role) => {
  if (role.name === 'admin') return false
  const before = original.value[role.id] ?? new Set()
  const after = current.value[role.id] ?? new Set()
  return before.size !== after.size || [...before].some(item => !after.has(item))
}))

function snapshot(data: Role[]) {
  const before: Record<number, Set<string>> = {}
  const after: Record<number, Set<string>> = {}
  data.forEach((role) => {
    const values = new Set((role.permissions || []).map(permission => permission.name))
    before[role.id] = values
    after[role.id] = new Set(values)
  })
  original.value = before
  current.value = after
}

function togglePermission(role: Role, name: string) {
  if (role.name === 'admin') return
  const values = new Set(current.value[role.id] ?? [])
  values.has(name) ? values.delete(name) : values.add(name)
  current.value = { ...current.value, [role.id]: values }
}

function resetChanges() {
  const values: Record<number, Set<string>> = {}
  Object.entries(original.value).forEach(([id, set]) => { values[Number(id)] = new Set(set) })
  current.value = values
}

async function loadData() {
  loading.value = true
  try {
    const response = await useApi<any>('/admin/roles', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    roles.value = response.roles || []
    permissions.value = response.permissions || []
    snapshot(roles.value)
  }
  catch {
    toast.error('Không thể tải danh sách quyền. Vui lòng thử lại.')
  }
  finally {
    loading.value = false
  }
}

async function savePermissions() {
  saving.value = true
  try {
    await Promise.all(roles.value.filter(role => role.name !== 'admin').map(role =>
      useApi(`/admin/roles/${role.id}/permissions`, {
        method: 'PUT',
        headers: { Authorization: `Bearer ${auth.token}` },
        body: { permissions: Array.from(current.value[role.id] ?? []) },
      }),
    ))
    toast.success('Cập nhật phân quyền thành công.')
    await loadData()
  }
  catch (error: any) {
    toast.error(error?.data?.message || 'Có lỗi xảy ra khi lưu phân quyền.')
  }
  finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <div class="roles-page">
    <header class="page-header">
      <div>
        <h1>Phân quyền hệ thống</h1>
        <p>Cấu hình quyền cho từng vai trò. Quản trị viên luôn có toàn bộ quyền.</p>
      </div>
      <div class="header-actions">
        <Button
          label="Hoàn tác"
          icon="pi pi-replay"
          severity="secondary"
          outlined
          :disabled="!hasUnsavedChanges || saving"
          @click="resetChanges"
        />
        <Button
          :label="saving ? 'Đang lưu...' : 'Lưu thay đổi'"
          icon="pi pi-save"
          :loading="saving"
          :disabled="!hasUnsavedChanges"
          @click="savePermissions"
        />
      </div>
    </header>

    <div v-if="loading" class="summary-grid">
      <Skeleton v-for="item in 3" :key="item" height="7rem" border-radius="12px" />
    </div>

    <template v-else>
      <div class="summary-grid">
        <Card v-for="role in roles" :key="role.id">
          <template #content>
            <div class="role-summary">
              <div>
                <span class="muted">Vai trò</span>
                <strong>{{ roleLabel(role.name) }}</strong>
              </div>
              <Tag
                :value="`${permissionCount(role)} / ${permissions.length} quyền`"
                :severity="role.name === 'admin' ? 'success' : 'info'"
              />
            </div>
            <ProgressBar
              :value="permissions.length ? Math.round(permissionCount(role) / permissions.length * 100) : 0"
              :show-value="false"
              class="role-progress"
            />
          </template>
        </Card>
      </div>

      <Message :severity="hasUnsavedChanges ? 'warn' : 'success'" :closable="false">
        {{ hasUnsavedChanges ? 'Có thay đổi phân quyền chưa được lưu.' : 'Dữ liệu phân quyền đã đồng bộ.' }}
      </Message>

      <Card v-for="group in permissionGroups" :key="group.key">
        <template #title>
          <div class="card-title">
            <span>{{ group.label }}</span>
            <Tag :value="`${group.items.length} quyền`" severity="secondary" />
          </div>
        </template>
        <template #content>
          <DataTable :value="group.items" data-key="id" striped-rows responsive-layout="scroll">
            <Column header="Quyền" class="permission-column">
              <template #body="{ data }">
                <div class="permission-name">
                  <strong>{{ permLabel(data.name) }}</strong>
                  <code>{{ data.name }}</code>
                </div>
              </template>
            </Column>
            <Column v-for="role in roles" :key="role.id" :header="roleLabel(role.name)">
              <template #body="{ data }">
                <div class="checkbox-cell">
                  <Checkbox
                    :model-value="hasPermission(role, data.name)"
                    binary
                    :disabled="role.name === 'admin'"
                    :aria-label="`${roleLabel(role.name)}: ${permLabel(data.name)}`"
                    @update:model-value="togglePermission(role, data.name)"
                  />
                  <i v-if="role.name === 'admin'" class="pi pi-lock muted" />
                </div>
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </template>
  </div>
</template>

<style scoped>
.roles-page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.page-header h1 { margin: 0; color: var(--p-text-color); font-size: 1.75rem; }
.page-header p { margin: .35rem 0 0; color: var(--p-text-muted-color); }
.header-actions { display: flex; gap: .5rem; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.role-summary, .card-title { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.role-summary > div, .permission-name { display: flex; flex-direction: column; gap: .25rem; }
.role-summary strong { color: var(--p-text-color); font-size: 1.1rem; }
.muted, .permission-name code { color: var(--p-text-muted-color); }
.role-progress { height: .4rem; margin-top: 1rem; }
.card-title { width: 100%; }
.permission-column { min-width: 18rem; }
.permission-name code { font-size: .75rem; }
.checkbox-cell { display: flex; justify-content: center; align-items: center; gap: .5rem; min-width: 8rem; }
@media (max-width: 640px) {
  .header-actions { width: 100%; }
  .header-actions :deep(.p-button) { flex: 1; }
}
</style>
