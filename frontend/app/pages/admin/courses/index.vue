<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface AdminCourse {
  id: number
  title: string
  description?: string | null
  thumbnail?: string | null
  status: string
  reject_reason?: string | null
  lessons_count?: number
  enrollments_count?: number
  instructor?: { name: string, avatar?: string | null } | null
  category?: { name: string } | null
  created_at?: string
}

interface Paginator<T> {
  data: T[]
  total: number
  current_page: number
  per_page: number
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const acting = ref(false)
const rows = ref<AdminCourse[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(15)
const tableSearch = ref('')

const filters = reactive({
  status: 'pending_review' as string | null,
})

const rejectOpen = ref(false)
const selected = ref<AdminCourse | null>(null)
const rejectReason = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null

const statusOptions = computed(() => [
  { label: t('common.all'), value: null },
  { label: t('admin.courseReview.statuses.pending_review'), value: 'pending_review' },
  { label: t('admin.courseReview.statuses.published'), value: 'published' },
  { label: t('admin.courseReview.statuses.draft'), value: 'draft' },
  { label: t('admin.courseReview.statuses.rejected'), value: 'rejected' },
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0))

function statusTone(status: string) {
  if (status === 'published') return 'tone-published'
  if (status === 'pending_review') return 'tone-pending'
  if (status === 'rejected') return 'tone-rejected'
  if (status === 'draft') return 'tone-draft'
  return 'tone-neutral'
}

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<AdminCourse>>('/admin/courses', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: tableSearch.value || undefined,
        status: filters.status || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.courseReview.loadError'),
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
  filters.status = 'pending_review'
  page.value = 1
  load()
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

function askApprove(course: AdminCourse) {
  confirm.require({
    message: t('admin.courseReview.approveConfirm', { title: course.title }),
    header: t('admin.courseReview.approveTitle'),
    icon: 'pi pi-check-circle',
    accept: async () => {
      acting.value = true
      try {
        await useApi(`/admin/courses/${course.id}/approve`, { method: 'PUT' })
        toast.add({ severity: 'success', summary: t('admin.courseReview.approved'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.courseReview.approveError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
      finally {
        acting.value = false
      }
    },
  })
}

function openReject(course: AdminCourse) {
  selected.value = course
  rejectReason.value = ''
  rejectOpen.value = true
}

async function submitReject() {
  if (!selected.value || !rejectReason.value.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.courseReview.reasonRequired'), life: 2800 })
    return
  }
  acting.value = true
  try {
    await useApi(`/admin/courses/${selected.value.id}/reject`, {
      method: 'PUT',
      body: { reject_reason: rejectReason.value.trim() },
    })
    toast.add({ severity: 'success', summary: t('admin.courseReview.rejected'), life: 2200 })
    rejectOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.courseReview.rejectError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    acting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page review-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.courses') }}</span>
        <h1>{{ t('admin.courseReview.title') }}</h1>
        <p>{{ t('admin.courseReview.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <strong>{{ t('admin.courseReview.filters') }}</strong>
          <Tag v-if="activeFilterCount" :value="String(activeFilterCount)" severity="info" />
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.courseReview.status') }}</span>
            <Select
              v-model="filters.status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.courseReview.apply')" icon="pi pi-filter" size="small" @click="applyFilters" />
          <Button :label="t('admin.courseReview.reset')" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </div>

      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.courseReview.searchPh')" @input="onTableSearch" />
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
        <Column :header="t('admin.courseReview.course')" style="min-width:220px">
          <template #body="{ data }">
            <div class="course-cell">
              <img v-if="data.thumbnail" :src="data.thumbnail" :alt="data.title" class="thumb">
              <div v-else class="thumb placeholder"><i class="pi pi-book" /></div>
              <div>
                <strong>{{ data.title }}</strong>
                <small>{{ data.category?.name || '—' }}</small>
              </div>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.courseReview.instructor')" style="min-width:140px">
          <template #body="{ data }">{{ data.instructor?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.courseReview.content')" style="min-width:120px">
          <template #body="{ data }">
            {{ t('admin.courseReview.lessonsEnrollments', { lessons: data.lessons_count || 0, enroll: data.enrollments_count || 0 }) }}
          </template>
        </Column>
        <Column :header="t('admin.courseReview.status')" style="width:130px">
          <template #body="{ data }">
            <span class="pill" :class="statusTone(data.status)">
              {{ t(`admin.courseReview.statuses.${data.status}`, data.status) }}
            </span>
          </template>
        </Column>
        <Column :header="t('admin.courseReview.createdAt')" style="width:120px">
          <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:9rem">
          <template #body="{ data }">
            <Button
              v-if="data.status === 'pending_review'"
              icon="pi pi-check"
              text
              rounded
              severity="success"
              :aria-label="t('admin.courseReview.approve')"
              :disabled="acting"
              @click="askApprove(data)"
            />
            <Button
              v-if="data.status === 'pending_review'"
              icon="pi pi-times"
              text
              rounded
              severity="danger"
              :aria-label="t('admin.courseReview.reject')"
              :disabled="acting"
              @click="openReject(data)"
            />
            <NuxtLink :to="`/admin/manage-courses/${data.id}`" class="icon-link">
              <Button icon="pi pi-eye" text rounded severity="secondary" />
            </NuxtLink>
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('common.noData') }}</div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="rejectOpen"
      modal
      :header="t('admin.courseReview.rejectTitle')"
      :style="{ width: 'min(520px, 96vw)' }"
    >
      <p class="hint">{{ t('admin.courseReview.rejectHint', { title: selected?.title || '' }) }}</p>
      <label class="field">
        <span>{{ t('admin.courseReview.reason') }}</span>
        <Textarea v-model="rejectReason" rows="4" class="w-full" :placeholder="t('admin.courseReview.reasonPh')" />
      </label>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="rejectOpen = false" />
        <Button
          :label="t('admin.courseReview.reject')"
          icon="pi pi-times"
          severity="danger"
          :loading="acting"
          @click="submitReject"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.review-page { gap: 14px; }
.workspace-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.table-panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 12px;
}
.filter-bar { margin-bottom: 12px; }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.filter-actions { display: flex; gap: 8px; margin-top: 10px; }
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }
.hint { margin: 0 0 12px; color: var(--text-muted); font-weight: 500; }

.course-cell { display: flex; align-items: center; gap: 10px; }
.thumb {
  width: 64px; height: 40px; object-fit: cover; border-radius: 8px; flex-shrink: 0;
}
.thumb.placeholder {
  display: grid; place-items: center; background: var(--surface-hover, #f1f5f9); color: var(--text-muted);
}
.course-cell small { display: block; color: var(--text-muted); margin-top: 2px; }

.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .72rem; font-weight: 700; white-space: nowrap;
}
.tone-published { background: #dcfce7; color: #15803d; }
.tone-pending { background: #fef3c7; color: #b45309; }
.tone-rejected { background: #fee2e2; color: #b91c1c; }
.tone-draft { background: #e2e8f0; color: #475569; }
.tone-neutral { background: var(--surface-hover); color: var(--text-muted); }

.icon-link { display: inline-flex; }
.empty { padding: 36px; text-align: center; color: var(--text-muted); }
</style>
