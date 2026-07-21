<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface CourseItem { id: number, title: string }
interface BankItem {
  id: number
  name: string
  description?: string | null
  questions_count?: number
  groups_count?: number
  course_id?: number
  course?: { id: number, title: string } | null
  difficulty_distribution?: Record<string, number>
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const loadingCourses = ref(false)
const saving = ref(false)
const banks = ref<BankItem[]>([])
const courses = ref<CourseItem[]>([])
const tableSearch = ref('')
const courseFilter = ref<number | null>(null)

const modalOpen = ref(false)
const modalMode = ref<'create' | 'edit'>('create')
const editing = ref<BankItem | null>(null)
const form = reactive({
  name: '',
  description: '',
  course_id: null as number | null,
})

const filtered = computed(() => {
  const q = tableSearch.value.trim().toLowerCase()
  return banks.value.filter((bank) => {
    if (courseFilter.value && (bank.course_id || bank.course?.id) !== courseFilter.value) return false
    if (!q) return true
    return bank.name.toLowerCase().includes(q)
      || (bank.description || '').toLowerCase().includes(q)
      || (bank.course?.title || '').toLowerCase().includes(q)
  })
})

async function loadCourses() {
  loadingCourses.value = true
  try {
    const res = await useApi<{ data: CourseItem[] }>('/admin/courses?per_page=100')
    courses.value = res.data || []
  }
  catch {
    courses.value = []
  }
  finally {
    loadingCourses.value = false
  }
}

async function loadBanks() {
  loading.value = true
  try {
    const res = await useApi<{ banks: BankItem[] }>('/admin/question-banks')
    banks.value = res.banks || []
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.questionBank.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  modalMode.value = 'create'
  editing.value = null
  form.name = ''
  form.description = ''
  form.course_id = courseFilter.value
  modalOpen.value = true
}

function openEdit(bank: BankItem) {
  modalMode.value = 'edit'
  editing.value = bank
  form.name = bank.name
  form.description = bank.description || ''
  form.course_id = bank.course_id || bank.course?.id || null
  modalOpen.value = true
}

async function saveBank() {
  if (!form.name.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.questionBank.nameRequired'), life: 2500 })
    return
  }
  if (modalMode.value === 'create' && !form.course_id) {
    toast.add({ severity: 'warn', summary: t('admin.questionBank.courseRequired'), life: 2500 })
    return
  }

  saving.value = true
  try {
    const body = { name: form.name.trim(), description: form.description || null }
    if (modalMode.value === 'create') {
      await useApi(`/courses/${form.course_id}/question-banks`, { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.questionBank.created'), life: 2500 })
    }
    else if (editing.value) {
      const courseId = editing.value.course_id || editing.value.course?.id
      await useApi(`/courses/${courseId}/question-banks/${editing.value.id}`, {
        method: 'PUT',
        body,
      })
      toast.add({ severity: 'success', summary: t('admin.questionBank.updated'), life: 2500 })
    }
    modalOpen.value = false
    await loadBanks()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.questionBank.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(bank: BankItem) {
  const courseId = bank.course_id || bank.course?.id
  confirm.require({
    message: t('admin.questionBank.deleteConfirm', { name: bank.name }),
    header: t('admin.questionBank.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/courses/${courseId}/question-banks/${bank.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.questionBank.deleted'), life: 2500 })
        await loadBanks()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.questionBank.deleteError'),
          detail: error?.data?.message,
          life: 3500,
        })
      }
    },
  })
}

function openBank(bank: BankItem) {
  const courseId = bank.course_id || bank.course?.id
  navigateTo(`/admin/question-bank/${bank.id}?courseId=${courseId}`)
}

onMounted(async () => {
  await Promise.all([loadCourses(), loadBanks()])
})
</script>

<template>
  <div class="page qb-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.assessment') }}</span>
        <h1>{{ t('admin.questionBank.title') }}</h1>
        <p>{{ t('admin.questionBank.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="filter-bar">
        <div class="filter-title">
          <i class="pi pi-filter" />
          <strong>{{ t('admin.questionBank.filters') }}</strong>
        </div>
        <div class="filter-grid">
          <label class="field">
            <span>{{ t('admin.questionBank.course') }}</span>
            <Select
              v-model="courseFilter"
              :options="courses"
              option-label="title"
              option-value="id"
              filter
              show-clear
              :loading="loadingCourses"
              :placeholder="t('common.all')"
              class="w-full"
            />
          </label>
        </div>
        <div class="filter-actions">
          <Button :label="t('admin.questionBank.reset')" icon="pi pi-times" size="small" severity="secondary" text @click="courseFilter = null; tableSearch = ''" />
        </div>
      </div>

      <div class="table-toolbar">
        <div class="toolbar-left">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="tableSearch" :placeholder="t('admin.questionBank.searchPh')" />
          </IconField>
          <strong>{{ t('admin.users.result', { n: filtered.length }) }}</strong>
        </div>
        <div class="toolbar-actions">
          <Button :label="t('admin.questionBank.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="loadBanks" />
        </div>
      </div>

      <DataTable
        :value="filtered"
        data-key="id"
        :loading="loading"
        paginator
        :rows="15"
        :rows-per-page-options="[10, 15, 25]"
        striped-rows
      >
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="name" :header="t('admin.questionBank.name')" sortable style="min-width:200px">
          <template #body="{ data }">
            <button type="button" class="name-link" @click="openBank(data)">{{ data.name }}</button>
          </template>
        </Column>
        <Column :header="t('admin.questionBank.course')" style="min-width:180px">
          <template #body="{ data }">{{ data.course?.title || '—' }}</template>
        </Column>
        <Column field="questions_count" :header="t('admin.questionBank.questions')" sortable style="min-width:100px">
          <template #body="{ data }">
            <span class="pill tone-info">{{ data.questions_count || 0 }}</span>
          </template>
        </Column>
        <Column :header="t('admin.questionBank.description')" style="min-width:200px">
          <template #body="{ data }">
            <span class="desc">{{ data.description || '—' }}</span>
          </template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:9rem">
          <template #body="{ data }">
            <Button icon="pi pi-eye" text rounded severity="secondary" @click="openBank(data)" />
            <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
          </template>
        </Column>
        <template #empty>
          <div class="empty">{{ t('common.noData') }}</div>
        </template>
      </DataTable>
    </section>

    <Dialog
      v-model:visible="modalOpen"
      modal
      :header="modalMode === 'create' ? t('admin.questionBank.add') : t('admin.questionBank.edit')"
      :style="{ width: 'min(520px, 96vw)' }"
    >
      <div class="modal-grid">
        <label v-if="modalMode === 'create'" class="field">
          <span>{{ t('admin.questionBank.course') }} *</span>
          <Select
            v-model="form.course_id"
            :options="courses"
            option-label="title"
            option-value="id"
            filter
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.name') }} *</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.questionBank.description') }}</span>
          <Textarea v-model="form.description" rows="4" auto-resize class="w-full" />
        </label>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="saveBank" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.qb-page { gap: 14px; }
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
.filter-bar { margin-bottom: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-subtle); }
.filter-title { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.filter-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.w-full { width: 100%; }

.table-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toolbar-actions { display: flex; align-items: center; gap: 6px; }

.name-link {
  border: 0; background: none; padding: 0; color: var(--text);
  font: inherit; font-weight: 700; cursor: pointer;
}
.name-link:hover { color: var(--brand); }
.desc {
  display: block; max-width: 28rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  color: var(--text-muted); font-size: .84rem;
}
.pill {
  display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px;
  font-size: .74rem; font-weight: 700;
}
.tone-info { background: #e0f2fe; color: #0369a1; }
.empty { padding: 40px; color: var(--text-muted); text-align: center; }
.modal-grid { display: grid; gap: 12px; }
</style>
