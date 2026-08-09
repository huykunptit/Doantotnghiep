<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface TermOption { id: number, name: string, code?: string }
interface StudentOption { id: number, name: string, email?: string, student_code?: string | null }
interface TuitionRow {
  id: number
  amount: number
  status: 'unpaid' | 'paid' | string
  paid_at?: string | null
  note?: string | null
  user?: StudentOption | null
  term?: { id: number, name: string, code?: string, label?: string, academic_year?: string } | null
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
const saving = ref(false)
const rows = ref<TuitionRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const search = ref('')
const statusFilter = ref<string | null>(null)
const termFilter = ref<number | null>(null)

const terms = ref<TermOption[]>([])
const students = ref<StudentOption[]>([])

const modalOpen = ref(false)
const editing = ref<TuitionRow | null>(null)
const form = reactive({
  user_id: null as number | null,
  term_id: null as number | null,
  amount: 0,
  status: 'unpaid' as string,
  note: '',
})

const statusOptions = computed(() => [
  { label: t('admin.tuition.statusUnpaid'), value: 'unpaid' },
  { label: t('admin.tuition.statusPaid'), value: 'paid' },
])

const numberLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))
const money = (n = 0) => new Intl.NumberFormat(numberLocale.value, {
  style: 'currency', currency: 'VND', maximumFractionDigits: 0,
}).format(n)

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(numberLocale.value, { dateStyle: 'medium' }).format(new Date(value))
}

async function loadTerms() {
  try {
    const res = await useApi<any>('/admin/academic/terms', { query: { per_page: 100 } })
    terms.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    terms.value = []
  }
}

async function loadStudents() {
  try {
    const res = await useApi<any>('/admin/students', { query: { per_page: 200 } })
    students.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch {
    try {
      const res = await useApi<any>('/admin/users', { query: { role: 'student', per_page: 200 } })
      students.value = res.data || []
    }
    catch {
      students.value = []
    }
  }
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<Paginator<TuitionRow>>('/admin/tuitions', {
      query: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        term_id: termFilter.value || undefined,
      },
    })
    rows.value = res.data || []
    total.value = res.total || 0
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.tuition.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, { user_id: null, term_id: null, amount: 0, status: 'unpaid', note: '' })
  modalOpen.value = true
}

function openEdit(row: TuitionRow) {
  editing.value = row
  Object.assign(form, {
    user_id: row.user?.id || null,
    term_id: row.term?.id || null,
    amount: Number(row.amount || 0),
    status: row.status || 'unpaid',
    note: row.note || '',
  })
  modalOpen.value = true
}

async function save() {
  if (!editing.value && (!form.user_id || !form.term_id)) {
    toast.add({ severity: 'warn', summary: t('admin.tuition.required'), life: 2500 })
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await useApi(`/admin/tuitions/${editing.value.id}`, {
        method: 'PUT',
        body: {
          amount: form.amount,
          status: form.status,
          note: form.note || null,
          term_id: form.term_id,
        },
      })
      toast.add({ severity: 'success', summary: t('admin.tuition.updated'), life: 2200 })
    }
    else {
      await useApi('/admin/tuitions', {
        method: 'POST',
        body: {
          user_id: form.user_id,
          term_id: form.term_id,
          amount: form.amount,
          status: form.status,
          note: form.note || null,
        },
      })
      toast.add({ severity: 'success', summary: t('admin.tuition.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.tuition.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

async function markPaid(row: TuitionRow) {
  try {
    await useApi(`/admin/tuitions/${row.id}/mark-paid`, { method: 'POST' })
    toast.add({ severity: 'success', summary: t('admin.tuition.markedPaid'), life: 2200 })
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.tuition.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
}

function askDelete(row: TuitionRow) {
  confirm.require({
    message: t('admin.tuition.deleteConfirm', { name: row.user?.name || `#${row.id}` }),
    header: t('admin.tuition.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/tuitions/${row.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.tuition.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.tuition.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

function onPage(event: { page: number, rows: number }) {
  page.value = event.page + 1
  perPage.value = event.rows
  load()
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 300)
}

onMounted(async () => {
  await Promise.all([loadTerms(), loadStudents(), load()])
})
</script>

<template>
  <div class="page">
    <header class="workspace-head">
      <div>
        <h1>{{ t('admin.tuition.title') }}</h1>
        <p>{{ t('admin.tuition.subtitle') }}</p>
      </div>
      <Button :label="t('admin.tuition.add')" icon="pi pi-plus" @click="openCreate" />
    </header>

    <section class="filters panel">
      <InputText v-model="search" :placeholder="t('admin.tuition.searchPh')" @input="onSearch" />
      <Select
        v-model="statusFilter"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        show-clear
        :placeholder="t('admin.tuition.status')"
        @change="page = 1; load()"
      />
      <Select
        v-model="termFilter"
        :options="terms"
        option-label="name"
        option-value="id"
        show-clear
        filter
        :placeholder="t('admin.tuition.term')"
        @change="page = 1; load()"
      />
      <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
    </section>

    <section class="panel">
      <DataTable
        :value="rows"
        data-key="id"
        :loading="loading"
        lazy
        paginator
        :rows="perPage"
        :total-records="total"
        :first="(page - 1) * perPage"
        @page="onPage"
        striped-rows
      >
        <Column :header="t('admin.tuition.student')" style="min-width:180px">
          <template #body="{ data }">
            <strong>{{ data.user?.name || '—' }}</strong>
            <small class="muted">{{ data.user?.student_code || data.user?.email || '' }}</small>
          </template>
        </Column>
        <Column :header="t('admin.tuition.term')" style="min-width:140px">
          <template #body="{ data }">{{ data.term?.label || data.term?.name || '—' }}</template>
        </Column>
        <Column :header="t('admin.tuition.amount')" style="min-width:120px">
          <template #body="{ data }">{{ money(data.amount) }}</template>
        </Column>
        <Column :header="t('admin.tuition.status')" style="width:110px">
          <template #body="{ data }">
            <Tag
              :value="data.status === 'paid' ? t('admin.tuition.statusPaid') : t('admin.tuition.statusUnpaid')"
              :severity="data.status === 'paid' ? 'success' : 'warn'"
            />
          </template>
        </Column>
        <Column :header="t('admin.tuition.paidAt')" style="min-width:120px">
          <template #body="{ data }">{{ fmtDate(data.paid_at) }}</template>
        </Column>
        <Column :header="t('common.actions')" style="width:160px">
          <template #body="{ data }">
            <Button
              v-if="data.status !== 'paid'"
              icon="pi pi-check"
              text
              rounded
              severity="success"
              :title="t('admin.tuition.markPaid')"
              @click="markPaid(data)"
            />
            <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <CommonEmptyState :description="t('admin.tuition.empty')" />
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="editing ? t('admin.tuition.edit') : t('admin.tuition.add')"
      :style="{ width: 'min(480px, 96vw)' }"
    >
      <div class="form-grid">
        <label v-if="!editing" class="field">
          <span>{{ t('admin.tuition.student') }} *</span>
          <Select
            v-model="form.user_id"
            :options="students"
            option-label="name"
            option-value="id"
            filter
            class="w-full"
            :placeholder="t('admin.tuition.selectStudent')"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.tuition.term') }} *</span>
          <Select
            v-model="form.term_id"
            :options="terms"
            option-label="name"
            option-value="id"
            filter
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.tuition.amount') }} *</span>
          <InputNumber v-model="form.amount" :min="0" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.tuition.status') }}</span>
          <Select
            v-model="form.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label class="field full">
          <span>{{ t('admin.tuition.note') }}</span>
          <InputText v-model="form.note" class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.workspace-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
.workspace-head h1 { margin: 0; font-size: 1.55rem; }
.workspace-head p { margin: .25rem 0 0; color: var(--p-text-muted-color); }
.panel { border: 1px solid var(--p-content-border-color); border-radius: 12px; background: var(--p-content-background); padding: .75rem; }
.filters { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; }
.filters > :first-child { min-width: 220px; flex: 1; }
.muted { display: block; color: var(--p-text-muted-color); font-size: .8rem; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }
.field { display: flex; flex-direction: column; gap: .35rem; font-size: .85rem; font-weight: 600; }
.field.full { grid-column: 1 / -1; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
</style>
