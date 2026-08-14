<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface CertItem {
  id: number
  credential_id: string
  issued_at?: string | null
  user?: { name?: string } | null
  course?: { id: number, title: string } | null
  career_path?: { id: number, title: string, slug?: string } | null
  certificate_template?: {
    name?: string
    background_image_url?: string | null
    fields_config?: unknown
  } | null
}

const toast = useToast()
const { t } = useI18n()
const auth = useAuthStore()
const loading = ref(true)
const items = ref<CertItem[]>([])
const viewing = ref<CertItem | null>(null)
const viewOpen = computed({
  get: () => viewing.value !== null,
  set: (open: boolean) => { if (!open) viewing.value = null },
})

async function load() {
  loading.value = true
  try {
    items.value = await useApi<CertItem[]>('/my-certificates')
  }
  catch (error: any) {
    items.value = []
    toast.add({ severity: 'error', summary: t('student.certs.loadError'), detail: error?.data?.message, life: 3500 })
  }
  finally {
    loading.value = false
  }
}

function titleOf(item: CertItem) {
  return item.course?.title || item.career_path?.title || t('student.certs.untitled')
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div v-if="loading" class="empty">{{ t('common.loading') }}</div>
    <CommonEmptyState v-else-if="!items.length" :description="t('student.certs.empty')" />
    <div v-else class="grid">
      <article v-for="item in items" :key="item.id" class="card">
        <button type="button" class="preview-btn" @click="viewing = item">
          <CertificatePreview
            compact
            :student-name="auth.user?.name || t('student.certs.holder')"
            :course-title="titleOf(item)"
            :issued-at="item.issued_at"
            :credential-id="item.credential_id"
            :template="item.certificate_template"
          />
        </button>
        <div class="meta">
          <strong>{{ titleOf(item) }}</strong>
          <span>{{ item.certificate_template?.name || t('student.certs.defaultTpl') }}</span>
          <code>{{ item.credential_id }}</code>
        </div>
        <div class="actions">
          <Button :label="t('student.certs.view')" icon="pi pi-eye" size="small" outlined @click="viewing = item" />
          <Button
            :label="t('student.certs.verify')"
            icon="pi pi-verified"
            size="small"
            @click="navigateTo(`/certificates/verify/${item.credential_id}`)"
          />
        </div>
      </article>
    </div>

    <Dialog
      v-model:visible="viewOpen"
      modal
      :header="viewing ? titleOf(viewing) : ''"
      :style="{ width: 'min(920px, 96vw)' }"
      dismissable-mask
    >
      <CertificatePreview
        v-if="viewing"
        :student-name="auth.user?.name || t('student.certs.holder')"
        :course-title="titleOf(viewing)"
        :issued-at="viewing.issued_at"
        :credential-id="viewing.credential_id"
        :template="viewing.certificate_template"
      />
      <template #footer>
        <Button
          :label="t('student.certs.verify')"
          icon="pi pi-verified"
          outlined
          @click="navigateTo(`/certificates/verify/${viewing?.credential_id}`)"
        />
        <Button :label="t('common.close')" @click="viewing = null" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.empty { color: var(--text-muted); padding: 24px; text-align: center; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.card {
  display: grid; gap: 12px; padding: 12px;
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.preview-btn { padding: 0; border: 0; background: none; cursor: pointer; text-align: left; }
.meta { display: grid; gap: 4px; }
.meta strong { font-size: .95rem; }
.meta span { color: var(--text-muted); font-size: .82rem; }
.meta code {
  width: fit-content; font-size: .75rem; padding: 3px 8px; border-radius: 8px;
  background: var(--surface-subtle); border: 1px solid var(--border);
}
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
