<script setup lang="ts">
import { computed, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'

interface Breadcrumb {
  label: string
}

interface KpiItem {
  label: string
  value: string | number
  sub?: string
  icon?: string
  tone?: 'green' | 'blue' | 'amber' | 'violet' | 'rose' | 'cyan' | 'slate'
}

interface TableColumn {
  key: string
  label: string
  align?: 'left' | 'right' | 'center'
}

interface ActionItem {
  label: string
  description?: string
  icon?: string
  tone?: KpiItem['tone']
}

interface RecordItem {
  id: number | string
  title: string
  subtitle?: string
  meta?: string
  status?: string
  statusTone?: KpiItem['tone']
  owner?: string
  count?: string | number
  updated?: string
}

const props = withDefaults(defineProps<{
  title: string
  subtitle: string
  breadcrumbs?: Breadcrumb[]
  kicker?: string
  heroTitle?: string
  heroDescription?: string
  kpis?: KpiItem[]
  columns?: TableColumn[]
  records?: RecordItem[]
  actions?: ActionItem[]
  searchPlaceholder?: string
  primaryActionLabel?: string
  primaryActionIcon?: string
  emptyText?: string
}>(), {
  breadcrumbs: () => [],
  kicker: 'Quản trị hệ thống',
  heroTitle: '',
  heroDescription: '',
  kpis: () => [],
  columns: () => [
    { key: 'title', label: 'Tên' },
    { key: 'owner', label: 'Phụ trách' },
    { key: 'count', label: 'Số lượng', align: 'center' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'updated', label: 'Cập nhật' },
    { key: 'actions', label: 'Thao tác', align: 'right' }
  ],
  records: () => [],
  actions: () => [],
  searchPlaceholder: 'Tìm kiếm...',
  primaryActionLabel: 'Tạo mới',
  primaryActionIcon: 'pi pi-plus',
  emptyText: 'Chưa có dữ liệu phù hợp.'
})

const search = ref('')
const selectedStatus = ref('all')

const filteredRecords = computed(() => {
  const keyword = search.value.trim().toLowerCase()
  return props.records.filter((item) => {
    const matchKeyword = !keyword
      || item.title.toLowerCase().includes(keyword)
      || String(item.subtitle || '').toLowerCase().includes(keyword)
      || String(item.owner || '').toLowerCase().includes(keyword)
      || String(item.meta || '').toLowerCase().includes(keyword)

    const matchStatus = selectedStatus.value === 'all' || item.status === selectedStatus.value
    return matchKeyword && matchStatus
  })
})

const statusOptions = computed(() => {
  const unique = new Map<string, string>()
  props.records.forEach((item) => {
    if (item.status) unique.set(item.status, item.status)
  })
  return [{ label: 'Tất cả trạng thái', value: 'all' }, ...Array.from(unique.values()).map(value => ({ label: value, value }))]
})

function toneClass(tone?: KpiItem['tone']) {
  const map: Record<string, string> = {
    green: 'text-emerald-700 bg-emerald-50 border-emerald-100',
    blue: 'text-blue-700 bg-blue-50 border-blue-100',
    amber: 'text-amber-700 bg-amber-50 border-amber-100',
    violet: 'text-violet-700 bg-violet-50 border-violet-100',
    rose: 'text-rose-700 bg-rose-50 border-rose-100',
    cyan: 'text-cyan-700 bg-cyan-50 border-cyan-100',
    slate: 'text-slate-700 bg-slate-50 border-slate-200'
  }
  return map[tone || 'green'] || map.green
}

function statusClass(tone?: KpiItem['tone']) {
  return toneClass(tone || 'slate')
}
</script>

<template>
  <AdminWorkspaceShell
    :title="title"
    :subtitle="subtitle"
    :breadcrumbs="breadcrumbs"
  >
    <div class="space-y-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div class="flex flex-wrap items-center gap-2">
            <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1d9e75] px-4 text-sm font-bold text-white transition hover:bg-[#178563]" type="button">
              <i :class="primaryActionIcon" />
              {{ primaryActionLabel }}
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50" type="button">
              <i class="pi pi-download" />
              Xuất dữ liệu
            </button>
            <button class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:bg-slate-50" type="button">
              <i class="pi pi-refresh" />
              Làm mới
            </button>
          </div>

          <div v-if="actions.length" class="flex gap-2 overflow-x-auto pb-1 xl:max-w-[52%] xl:justify-end">
            <button
              v-for="action in actions.slice(0, 4)"
              :key="action.label"
              type="button"
              class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-600 transition hover:border-emerald-100 hover:bg-emerald-50 hover:text-emerald-700"
            >
              <i :class="action.icon || 'pi pi-arrow-right'" />
              {{ action.label }}
            </button>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="item in kpis"
          :key="item.label"
          class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-bold text-slate-500">{{ item.label }}</p>
              <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ item.value }}</p>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-xl border" :class="toneClass(item.tone)">
              <i :class="item.icon || 'pi pi-chart-line'" />
            </span>
          </div>
          <p class="mt-4 text-xs text-slate-400">{{ item.sub || 'Cập nhật theo dữ liệu hiện tại' }}</p>
        </article>
      </section>

      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-5 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h3 class="text-base font-bold text-slate-900">Danh sách quản lý</h3>
            <p class="text-xs text-slate-400">{{ filteredRecords.length }} bản ghi đang hiển thị</p>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-72">
              <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" />
              <input
                v-model="search"
                type="text"
                :placeholder="searchPlaceholder"
                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm outline-none transition focus:border-[#1d9e75] focus:bg-white"
              >
            </div>
            <select
              v-model="selectedStatus"
              class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 outline-none transition focus:border-[#1d9e75]"
            >
              <option v-for="item in statusOptions" :key="item.value" :value="item.value">
                {{ item.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th
                  v-for="col in columns"
                  :key="col.key"
                  class="whitespace-nowrap px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500"
                  :class="{
                    'text-right': col.align === 'right',
                    'text-center': col.align === 'center',
                    'text-left': !col.align || col.align === 'left'
                  }"
                >
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="filteredRecords.length === 0">
                <td :colspan="columns.length" class="px-5 py-12 text-center text-sm text-slate-400">
                  <i class="pi pi-inbox mb-3 block text-3xl opacity-50" />
                  {{ emptyText }}
                </td>
              </tr>
              <tr v-for="item in filteredRecords" :key="item.id" class="transition hover:bg-slate-50/80">
                <td class="min-w-[280px] px-5 py-4">
                  <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border" :class="toneClass(item.statusTone)">
                      <i class="pi pi-folder" />
                    </span>
                    <span class="min-w-0">
                      <strong class="block truncate text-sm font-bold text-slate-800">{{ item.title }}</strong>
                      <span class="mt-0.5 block truncate text-xs text-slate-400">{{ item.subtitle || item.meta || '—' }}</span>
                    </span>
                  </div>
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-xs font-semibold text-slate-600">{{ item.owner || '—' }}</td>
                <td class="whitespace-nowrap px-5 py-4 text-center text-xs font-bold text-slate-800">{{ item.count || '—' }}</td>
                <td class="whitespace-nowrap px-5 py-4">
                  <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[10px] font-bold" :class="statusClass(item.statusTone)">
                    {{ item.status || 'Đang hoạt động' }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-400">{{ item.updated || 'Vừa cập nhật' }}</td>
                <td class="whitespace-nowrap px-5 py-4 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800" type="button" title="Xem">
                      <i class="pi pi-eye text-xs" />
                    </button>
                    <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-100 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100" type="button" title="Sửa">
                      <i class="pi pi-pencil text-xs" />
                    </button>
                    <button class="flex h-8 w-8 items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-600 transition hover:bg-rose-100" type="button" title="Xóa">
                      <i class="pi pi-trash text-xs" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-2 border-t border-slate-100 px-5 py-4 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
          <span>Hiển thị {{ filteredRecords.length }} / {{ records.length }} bản ghi</span>
          <span>Dữ liệu mẫu giao diện, sẵn sàng nối API.</span>
        </div>
      </section>
    </div>
  </AdminWorkspaceShell>
</template>