<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface AdminReview {
  id: number
  rating: number
  comment?: string | null
  created_at?: string
  user?: { id: number, name?: string, email?: string, avatar?: string | null } | null
  course?: { id: number, title?: string } | null
}

interface Paginator<T> {
  data: T[]
  total: number
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const rows = ref<AdminReview[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  rating: null as number | null,
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

const ratingOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.reviews.stars', { n: 5 }), value: 5 },
  { label: t('admin.reviews.stars', { n: 4 }), value: 4 },
  { label: t('admin.reviews.stars', { n: 3 }), value: 3 },
  { label: t('admin.reviews.stars', { n: 2 }), value: 2 },
  { label: t('admin.reviews.stars', { n: 1 }), value: 1 },
])

const activeFilterCount = computed(() => (filters.rating ? 1 : 0))

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

function ratingTone(rating: number) {
  if (rating >= 4) return 'tone-good'
  if (rating <= 2) return 'tone-bad'
  return 'tone-mid'
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AdminReview>>('/admin/reviews', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: tableSearch.value || undefined,
        rating: filters.rating || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.reviews.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function onTableSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
}

function applyFilters() {
  page.value = 1
  load()
}

function resetFilters() {
  filters.rating = null
  page.value = 1
  load()
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

function askDelete(review: AdminReview) {
  confirm.require({
    message: t('admin.reviews.deleteConfirm', {
      name: review.user?.name || t('admin.reviews.anonymous'),
      rating: review.rating,
    }),
    header: t('admin.reviews.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/reviews/${review.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.reviews.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.reviews.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page reviews-page">

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.reviews.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.reviews.rating') }}</span>
            <Select
              v-model="filters.rating"
              :options="ratingOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.reviews.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.reviews.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.reviews.searchPh')" @input="onTableSearch" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: total }) }}</strong>
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :rows="perPage"
        :total-records="total"
        :rows-per-page-options="[10, 15, 25, 50]"
        @page="onPage"
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ (page - 1) * perPage + index + 1 }}</template>
        </Column>
        <Column :header="t('admin.reviews.learner')" style="min-width:160px">
          <template #body="{ data }">
            <div>
              <strong>{{ data.user?.name || t('admin.reviews.anonymous') }}</strong>
              <small>{{ data.user?.email || '—' }}</small>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.reviews.course')" style="min-width:180px">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column :header="t('admin.reviews.rating')" style="width:110px">
          <template #body="{ data }">
            <span class="pill" :class="ratingTone(data.rating)">{{ data.rating }}/5</span>
          </template>
        </Column>
        <Column :header="t('admin.reviews.comment')" style="min-width:220px">
          <template #body="{ data }">
            <p class="comment">{{ data.comment || t('admin.reviews.noComment') }}</p>
          </template>
        </Column>
        <Column :header="t('admin.reviews.createdAt')" style="width:120px">
          <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:5rem">
          <template #body="{ data }">
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('common.noData')" />
        </template>
      </DataTable>
    </section>
  </div>
</template>

<style scoped>
.reviews-page { gap: 14px; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.filter-actions { display: flex; gap: 8px; margin-top: 10px; }
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }

small { display: block; color: var(--text-muted); margin-top: 2px; }
.comment {
  margin: 0; max-width: 360px; white-space: pre-wrap;
  color: var(--text-muted); font-size: .9rem; font-weight: 500;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.tone-good { background: #dcfce7; color: #15803d; }
.tone-mid { background: #fef3c7; color: #b45309; }
.tone-bad { background: #fee2e2; color: #b91c1c; }

</style>
