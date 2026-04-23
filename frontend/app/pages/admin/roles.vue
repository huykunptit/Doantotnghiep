<template>
  <NuxtLayout name="admin">
    <div class="space-y-8 pb-12">
      <div class="flex flex-col gap-4 border-b border-surface-dim/30 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-widest text-outline">Access Control</p>
          <h2 class="mt-1 text-3xl font-bold font-headline tracking-tight text-on-surface">Vai trò hệ thống</h2>
          <p class="mt-2 text-sm text-on-surface-variant">Theo dõi nhanh phân bổ quyền admin, giảng viên và học viên trong hệ thống.</p>
        </div>
        <UiButton variant="secondary" @click="exportRoles">Xuất CSV</UiButton>
      </div>

      <div class="grid gap-6 md:grid-cols-3">
        <UiCard v-for="card in summaryCards" :key="card.key">
          <p class="text-xs font-bold uppercase tracking-widest text-outline">{{ card.label }}</p>
          <p class="mt-3 text-4xl font-headline font-bold text-on-surface">{{ card.count }}</p>
        </UiCard>
      </div>

      <UiCard>
        <div class="flex items-center justify-between gap-4 border-b border-surface-dim/30 pb-4">
          <div>
            <h3 class="text-lg font-semibold text-on-surface">Danh sách người dùng theo quyền</h3>
            <p class="text-sm text-on-surface-variant">Dữ liệu lấy trực tiếp từ API người dùng hiện có.</p>
          </div>
        </div>

        <div v-if="loading" class="mt-6 grid gap-4 md:grid-cols-2">
          <div v-for="item in 4" :key="item" class="h-28 rounded-2xl bg-surface-high animate-pulse" />
        </div>

        <div v-else class="mt-6 grid gap-4 md:grid-cols-2">
          <div
            v-for="group in groupedUsers"
            :key="group.role"
            class="rounded-2xl border border-surface-dim bg-surface-low p-5"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-on-surface">{{ roleLabel(group.role) }}</p>
                <p class="text-xs text-on-surface-variant">{{ group.users.length }} tài khoản</p>
              </div>
              <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ group.role }}</span>
            </div>

            <div class="mt-4 space-y-3">
              <div v-for="user in group.users.slice(0, 6)" :key="user.id" class="flex items-center justify-between gap-3 text-sm">
                <div class="min-w-0">
                  <p class="truncate font-medium text-on-surface">{{ user.name }}</p>
                  <p class="truncate text-xs text-on-surface-variant">{{ user.email }}</p>
                </div>
                <span class="text-xs text-outline">{{ formatDate(user.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </UiCard>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useExport } from '~/composables/useExport'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const loading = ref(true)
const users = ref<any[]>([])
const { exportToCSV } = useExport()

const normalizeRole = (user: any) => user?.role || user?.roles?.[0]?.name || user?.roles?.[0] || 'student'
const roleLabel = (role: string) => ({ admin: 'Quản trị viên', instructor: 'Giảng viên', student: 'Học viên' }[role] || role)
const formatDate = (date?: string) => !date ? 'N/A' : new Date(date).toLocaleDateString('vi-VN')

const groupedUsers = computed(() => {
  const groups = ['admin', 'instructor', 'student'].map((role) => ({
    role,
    users: users.value.filter((user) => normalizeRole(user) === role),
  }))
  return groups
})

const summaryCards = computed(() => groupedUsers.value.map((group) => ({
  key: group.role,
  label: roleLabel(group.role),
  count: group.users.length,
})))

const exportRoles = () => {
  exportToCSV(
    users.value.map((user) => ({ ...user, normalized_role: normalizeRole(user) })),
    [
      { key: 'id', label: 'ID' },
      { key: 'name', label: 'Họ tên' },
      { key: 'email', label: 'Email' },
      { key: 'normalized_role', label: 'Vai trò', format: (value) => roleLabel(String(value)) },
      { key: 'created_at', label: 'Ngày tạo', format: (value) => formatDate(value) },
    ],
    'admin_roles',
  )
}

onMounted(async () => {
  try {
    const response = await $fetch<any>('/api/admin/users?per_page=100', {
      headers: { Authorization: `Bearer ${auth.token}` },
    }).catch(() => ({ data: [] }))

    users.value = response.data || []
  } finally {
    loading.value = false
  }
})
</script>
