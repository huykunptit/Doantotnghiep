<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import DataTableFooter from '~/components/common/DataTableFooter.vue'
import UiFilters from '~/components/ui/UiFilters.vue'
import UiKpiCards from '~/components/ui/UiKpiCards.vue'
import UiSelect from '~/components/ui/UiSelect.vue'
import UiTable from '~/components/ui/UiTable.vue'
import UModal from '~/components/UModal.vue'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin' })

interface NotificationItem {
  id: number
  user_id?: number
  type?: string | null
  title: string
  message?: string | null
  link?: string | null
  read_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

const token = useAuthTokenCookie()
const toast = useToast()

const notifications = ref<NotificationItem[]>([])
const unreadCount = ref(0)
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const totalItems = ref(0)
const perPage = ref(20)
const selectedNotification = ref<NotificationItem | null>(null)
const detailOpen = ref(false)

const filters = reactive({
  search: '',
  status: '',
  type: ''
})

const columns = [
  { id: 'statusDot', accessorKey: 'read_at', header: '' },
  { id: 'notification', accessorKey: 'title', header: 'Thông báo' },
  { id: 'type', accessorKey: 'type', header: 'Loại' },
  { id: 'status', accessorKey: 'read_at', header: 'Trạng thái' },
  { id: 'created', accessorKey: 'created_at', header: 'Thời gian' },
  { id: 'actions', accessorKey: 'actions', header: 'Thao tác', class: 'text-right' }
]

const statusOptions = [
  { label: 'Tất cả trạng thái', value: '' },
  { label: 'Chưa đọc', value: 'unread' },
  { label: 'Đã đọc', value: 'read' }
]

const typeOptions = computed(() => {
  const types = Array.from(new Set(notifications.value.map(item => item.type).filter(Boolean))) as string[]
  return [{ label: 'Tất cả loại', value: '' }, ...types.map(type => ({ label: typeLabel(type), value: type }))]
})

const filteredNotifications = computed(() => {
  const keyword = filters.search.trim().toLowerCase()
  return notifications.value.filter((item) => {
    const matchKeyword = !keyword
      || item.title.toLowerCase().includes(keyword)
      || String(item.message || '').toLowerCase().includes(keyword)
      || String(item.type || '').toLowerCase().includes(keyword)

    const matchStatus = !filters.status
      || (filters.status === 'unread' && !item.read_at)
      || (filters.status === 'read' && !!item.read_at)

    const matchType = !filters.type || item.type === filters.type
    return matchKeyword && matchStatus && matchType
  })
})

const readCount = computed(() => notifications.value.filter(item => item.read_at).length)
const activeFilterCount = computed(() => [filters.status, filters.type].filter(Boolean).length)
const activeChips = computed(() => {
  const chips: { key: string; label: string }[] = []
  if (filters.status) chips.push({ key: 'status', label: statusOptions.find(item => item.value === filters.status)?.label || filters.status })
  if (filters.type) chips.push({ key: 'type', label: typeLabel(filters.type) })
  return chips
})

const stats = computed(() => [
  { label: 'Tổng thông báo', value: totalItems.value, subText: 'theo tài khoản hiện tại', color: 'info', icon: 'pi-bell' },
  { label: 'Chưa đọc', value: unreadCount.value, subText: 'cần xem', color: 'warning', icon: 'pi-envelope' },
  { label: 'Đã đọc trên trang', value: readCount.value, subText: 'trang hiện tại', color: 'success', icon: 'pi-check-circle' },
  { label: 'Loại thông báo', value: Math.max(typeOptions.value.length - 1, 0), subText: 'phân nhóm hiện có', color: 'purple', icon: 'pi-tags' }
])

function authHeaders() {
  return token.value ? { Authorization: `Bearer ${token.value}` } : {}
}

function typeLabel(type?: string | null) {
  const value = String(type || 'system')
  return ({
    course_approved: 'Khóa học được duyệt',
    course_rejected: 'Khóa học bị từ chối',
    system: 'Hệ thống',
    learning: 'Học tập',
    exam: 'Khảo thí',
    payment: 'Thanh toán'
  } as Record<string, string>)[value] || value
}

function typeClass(type?: string | null) {
  const value = String(type || 'system')
  if (value.includes('approved')) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (value.includes('rejected')) return 'bg-rose-50 text-rose-700 border-rose-200'
  if (value.includes('exam')) return 'bg-violet-50 text-violet-700 border-violet-200'
  return 'bg-blue-50 text-blue-700 border-blue-200'
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  filters.type = ''
}

function removeChip(key: string) {
  if (key === 'status') filters.status = ''
  if (key === 'type') filters.type = ''
}

async function fetchUnreadCount() {
  try {
    const response = await useApi<{ count: number }>('/notifications/unread-count', { headers: authHeaders() })
    unreadCount.value = response.count || 0
  } catch (_) {}
}

async function fetchNotifications(page = 1) {
  loading.value = true
  errorMessage.value = ''
  try {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage.value) })
    const response = await useApi<PaginatedResponse<NotificationItem>>(`/notifications?${query.toString()}`, { headers: authHeaders() })
    notifications.value = response.data || []
    currentPage.value = response.current_page || page
    lastPage.value = response.last_page || 1
    totalItems.value = response.total || notifications.value.length
    await fetchUnreadCount()
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải danh sách thông báo.'
    toast.error('Không thể tải thông báo', errorMessage.value)
  } finally {
    loading.value = false
  }
}

function openDetail(item: NotificationItem) {
  selectedNotification.value = item
  detailOpen.value = true
}

async function markAsRead(item: NotificationItem) {
  if (item.read_at) return
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await useApi(`/notifications/${item.id}/read`, { method: 'PUT', headers: authHeaders() })
    successMessage.value = 'Đã đánh dấu thông báo là đã đọc.'
    toast.success('Đã đánh dấu đã đọc')
    await fetchNotifications(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể đánh dấu đã đọc.'
    toast.error('Không thể cập nhật thông báo', errorMessage.value)
  } finally {
    saving.value = false
  }
}

async function markAllAsRead() {
  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    await useApi('/notifications/read-all', { method: 'PUT', headers: authHeaders() })
    successMessage.value = 'Đã đánh dấu tất cả thông báo là đã đọc.'
    toast.success('Đã đánh dấu tất cả đã đọc')
    await fetchNotifications(currentPage.value)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể đánh dấu tất cả đã đọc.'
    toast.error('Không thể cập nhật thông báo', errorMessage.value)
  } finally {
    saving.value = false
  }
}

onMounted(() => fetchNotifications())
</script>

<template>
  <AdminWorkspaceShell
    title="Thông báo hệ thống"
    subtitle="Theo dõi thông báo của tài khoản hiện tại và xử lý trạng thái đã đọc."
    :breadcrumbs="[{ label: 'Hệ thống' }, { label: 'Thông báo' }]"
  >
    <div class="flex flex-col gap-5">
      <UiFilters
        v-model:search="filters.search"
        search-placeholder="Tìm tiêu đề, nội dung, loại thông báo..."
        :active-filter-count="activeFilterCount"
        :active-chips="activeChips"
        :always-open="true"
        @submit-search="() => {}"
        @reset-filters="resetFilters"
        @remove-chip="removeChip"
      >
        <template #actions>
          <button class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60" type="button" :disabled="saving" @click="markAllAsRead">
            <i class="pi pi-check-circle" />
            Đánh dấu tất cả đã đọc
          </button>
          <button class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-xs font-bold text-white transition hover:bg-[#178563]" type="button" @click="fetchNotifications(currentPage)">
            <i class="pi pi-refresh" />
            Làm mới
          </button>
        </template>

        <template #advanced>
          <label class="flex min-w-[170px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Trạng thái</span>
            <UiSelect v-model="filters.status" :options="statusOptions" placeholder="Tất cả trạng thái" />
          </label>
          <label class="flex min-w-[190px] flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Loại thông báo</span>
            <UiSelect v-model="filters.type" :options="typeOptions" placeholder="Tất cả loại" />
          </label>
        </template>
      </UiFilters>

      <UiKpiCards :items="stats" />

      <div v-if="successMessage" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">
        <i class="pi pi-check-circle" /> {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
        <i class="pi pi-exclamation-circle" /> {{ errorMessage }}
      </div>

      <section class="overflow-hidden rounded-2xl border border-[var(--line)] bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-[var(--line)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-base font-bold text-[var(--text)]">Hộp thông báo</h3>
            <p class="text-xs text-[var(--muted)]">{{ filteredNotifications.length }} / {{ notifications.length }} thông báo trên trang hiện tại</p>
          </div>
          <span class="inline-flex h-8 items-center rounded-full border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-700">
            {{ unreadCount }} chưa đọc
          </span>
        </div>

        <UiTable :columns="columns" :data="filteredNotifications" :loading="loading">
          <template #statusDot-cell="{ row }">
            <span class="block h-2.5 w-2.5 rounded-full" :class="row.original.read_at ? 'bg-slate-300' : 'bg-emerald-500'" />
          </template>

          <template #notification-cell="{ row }">
            <button class="flex min-w-[300px] flex-col text-left" type="button" @click="openDetail(row.original)">
              <strong class="text-sm font-bold" :class="row.original.read_at ? 'text-slate-700' : 'text-[var(--text)]'">{{ row.original.title }}</strong>
              <span class="mt-1 line-clamp-2 text-xs text-[var(--muted)]">{{ row.original.message || 'Không có nội dung.' }}</span>
            </button>
          </template>

          <template #type-cell="{ row }">
            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="typeClass(row.original.type)">
              {{ typeLabel(row.original.type) }}
            </span>
          </template>

          <template #status-cell="{ row }">
            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="row.original.read_at ? 'border-slate-200 bg-slate-50 text-slate-500' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
              {{ row.original.read_at ? 'Đã đọc' : 'Chưa đọc' }}
            </span>
          </template>

          <template #created-cell="{ row }">
            <span class="text-xs text-[var(--muted)]">{{ formatDate(row.original.created_at) }}</span>
          </template>

          <template #actions-cell="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <button class="ds-btn ds-btn--view ds-btn--icon" title="Xem" type="button" @click="openDetail(row.original)"><i class="pi pi-eye" /></button>
              <button class="ds-btn ds-btn--edit ds-btn--icon" title="Đánh dấu đã đọc" type="button" :disabled="!!row.original.read_at || saving" @click="markAsRead(row.original)"><i class="pi pi-check" /></button>
              <NuxtLink v-if="row.original.link" :to="row.original.link" class="ds-btn ds-btn--view ds-btn--icon" title="Mở liên kết"><i class="pi pi-external-link" /></NuxtLink>
            </div>
          </template>
        </UiTable>

        <DataTableFooter :current="currentPage" :last="lastPage" :total="totalItems" :per-page="perPage" @page="fetchNotifications" @update:per-page="perPage = $event; fetchNotifications(1)" />
      </section>
    </div>

    <UModal v-model:open="detailOpen" title="Chi tiết thông báo" :ui="{ width: 'max-w-xl' }">
      <div v-if="selectedNotification" class="space-y-4">
        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="typeClass(selectedNotification.type)">{{ typeLabel(selectedNotification.type) }}</span>
              <h3 class="mt-3 text-base font-bold text-[var(--text)]">{{ selectedNotification.title }}</h3>
              <p class="mt-1 text-xs text-[var(--muted)]">{{ formatDate(selectedNotification.created_at) }}</p>
            </div>
            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="selectedNotification.read_at ? 'border-slate-200 bg-slate-50 text-slate-500' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
              {{ selectedNotification.read_at ? 'Đã đọc' : 'Chưa đọc' }}
            </span>
          </div>
        </div>

        <p class="rounded-xl border border-slate-100 bg-white p-4 text-sm leading-6 text-slate-600">{{ selectedNotification.message || 'Không có nội dung.' }}</p>

        <div class="flex justify-end gap-3">
          <button v-if="!selectedNotification.read_at" class="h-10 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-bold text-emerald-700 hover:bg-emerald-100" type="button" :disabled="saving" @click="markAsRead(selectedNotification)">
            Đánh dấu đã đọc
          </button>
          <NuxtLink v-if="selectedNotification.link" :to="selectedNotification.link" class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-xs font-bold text-white hover:bg-[#178563]">
            Mở liên kết
            <i class="pi pi-external-link" />
          </NuxtLink>
        </div>
      </div>
    </UModal>
  </AdminWorkspaceShell>
</template>