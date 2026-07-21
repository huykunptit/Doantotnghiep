<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface FieldConfig {
  key: string
  label: string
  x: number
  y: number
  font_size: number
  font_family: string
  color: string
  font_weight: 'normal' | 'bold'
  text_align: 'left' | 'center' | 'right'
  visible: boolean
}

interface CertificateTemplate {
  id: number
  name: string
  background_image_url?: string | null
  fields_config?: FieldConfig[] | null
  created_at?: string
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const DEFAULT_FIELDS: FieldConfig[] = [
  { key: 'student_name', label: 'Student name', x: 50, y: 42, font_size: 36, font_family: 'Georgia, serif', color: '#1a1a1a', font_weight: 'bold', text_align: 'center', visible: true },
  { key: 'course_title', label: 'Course title', x: 50, y: 55, font_size: 18, font_family: 'Arial, sans-serif', color: '#444444', font_weight: 'normal', text_align: 'center', visible: true },
  { key: 'issued_date', label: 'Issued date', x: 50, y: 68, font_size: 13, font_family: 'Arial, sans-serif', color: '#666666', font_weight: 'normal', text_align: 'center', visible: true },
  { key: 'credential_id', label: 'Credential ID', x: 50, y: 78, font_size: 11, font_family: '"Courier New", monospace', color: '#888888', font_weight: 'normal', text_align: 'center', visible: true },
]

const loading = ref(false)
const saving = ref(false)
const rows = ref<CertificateTemplate[]>([])
const tableSearch = ref('')

const modalOpen = ref(false)
const editing = ref<CertificateTemplate | null>(null)
const form = reactive({
  name: '',
  background_image_url: '',
})

const filteredRows = computed(() => {
  const q = tableSearch.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter(r => r.name.toLowerCase().includes(q))
})

function fmtDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  }).format(new Date(value))
}

async function load() {
  loading.value = true
  try {
    const res = await useApi<CertificateTemplate[] | { data: CertificateTemplate[] }>('/admin/certificates')
    rows.value = Array.isArray(res) ? res : (res.data || [])
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.certificates.loadError'),
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
  Object.assign(form, { name: '', background_image_url: '' })
  modalOpen.value = true
}

function openEdit(item: CertificateTemplate) {
  editing.value = item
  Object.assign(form, {
    name: item.name || '',
    background_image_url: item.background_image_url || '',
  })
  modalOpen.value = true
}

async function save() {
  if (!form.name.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.certificates.nameRequired'), life: 2500 })
    return
  }
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      name: form.name.trim(),
      background_image_url: form.background_image_url.trim() || null,
    }
    if (editing.value) {
      await useApi(`/admin/certificates/${editing.value.id}`, { method: 'PUT', body })
      toast.add({ severity: 'success', summary: t('admin.certificates.updated'), life: 2200 })
    }
    else {
      body.fields_config = DEFAULT_FIELDS
      await useApi('/admin/certificates', { method: 'POST', body })
      toast.add({ severity: 'success', summary: t('admin.certificates.created'), life: 2200 })
    }
    modalOpen.value = false
    await load()
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.certificates.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

function askDelete(item: CertificateTemplate) {
  confirm.require({
    message: t('admin.certificates.deleteConfirm', { name: item.name }),
    header: t('admin.certificates.deleteTitle'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await useApi(`/admin/certificates/${item.id}`, { method: 'DELETE' })
        toast.add({ severity: 'success', summary: t('admin.certificates.deleted'), life: 2200 })
        await load()
      }
      catch (error: any) {
        toast.add({
          severity: 'error',
          summary: t('admin.certificates.deleteError'),
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
  <div class="page cert-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.courses') }}</span>
        <h1>{{ t('admin.certificates.title') }}</h1>
        <p>{{ t('admin.certificates.subtitle') }}</p>
      </div>
    </header>

    <section class="table-panel">
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="tableSearch" :placeholder="t('admin.certificates.searchPh')" />
        </IconField>
        <div class="toolbar-actions">
          <strong>{{ t('admin.users.result', { n: filteredRows.length }) }}</strong>
          <Button :label="t('admin.certificates.add')" icon="pi pi-plus" size="small" @click="openCreate" />
          <Button icon="pi pi-refresh" severity="secondary" text rounded :loading="loading" @click="load" />
        </div>
      </div>

      <DataTable :value="filteredRows" data-key="id" :loading="loading">
        <Column :header="t('admin.users.stt')" style="width:4rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column :header="t('admin.certificates.template')" style="min-width:220px">
          <template #body="{ data }">
            <div class="tpl-cell">
              <img v-if="data.background_image_url" :src="data.background_image_url" :alt="data.name" class="preview">
              <div v-else class="preview placeholder"><i class="pi pi-image" /></div>
              <strong>{{ data.name }}</strong>
            </div>
          </template>
        </Column>
        <Column :header="t('admin.certificates.fields')" style="width:110px">
          <template #body="{ data }">{{ (data.fields_config || []).length }}</template>
        </Column>
        <Column :header="t('admin.certificates.createdAt')" style="width:120px">
          <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
        </Column>
        <Column :header="t('admin.users.actions')" style="width:8rem">
          <template #body="{ data }">
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
      :header="editing ? t('admin.certificates.edit') : t('admin.certificates.add')"
      :style="{ width: 'min(560px, 96vw)' }"
    >
      <div class="form">
        <label class="field full">
          <span>{{ t('admin.certificates.name') }}</span>
          <InputText v-model="form.name" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.certificates.backgroundUrl') }}</span>
          <InputText v-model="form.background_image_url" class="w-full" :placeholder="t('admin.certificates.backgroundPh')" />
        </label>
        <div v-if="form.background_image_url" class="preview-box full">
          <img :src="form.background_image_url" :alt="form.name">
        </div>
      </div>
      <template #footer>
        <Button :label="t('common.cancel')" severity="secondary" text @click="modalOpen = false" />
        <Button :label="t('common.save')" icon="pi pi-check" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.cert-page { gap: 14px; }
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
.table-toolbar {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-wrap: wrap;
}
.toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { color: var(--text-muted); font-size: .75rem; font-weight: 700; }
.w-full { width: 100%; }
.form { display: grid; grid-template-columns: 1fr; gap: 12px; }
.form .full { grid-column: 1 / -1; }

.tpl-cell { display: flex; align-items: center; gap: 10px; }
.preview {
  width: 72px; height: 48px; object-fit: cover; border-radius: 8px; flex-shrink: 0;
  border: 1px solid var(--border);
}
.preview.placeholder {
  display: grid; place-items: center; background: var(--surface-hover, #f1f5f9); color: var(--text-muted);
}
.preview-box img {
  width: 100%; max-height: 200px; object-fit: contain; border-radius: 10px;
  border: 1px solid var(--border); background: #fff;
}

.empty { padding: 36px; text-align: center; color: var(--text-muted); }
</style>
