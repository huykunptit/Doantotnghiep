<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({ layout: 'student', middleware: ['auth', 'student'] })

interface CertItem {
  id: number
  credential_id: string
  issued_at?: string | null
  course?: { id: number, title: string } | null
  career_path?: { id: number, title: string, slug?: string } | null
  certificate_template?: { name?: string } | null
}

const toast = useToast()
const { t, locale } = useI18n()
const loading = ref(true)
const items = ref<CertItem[]>([])

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

function formatDate(value?: string | null) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(dateLocale.value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

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

onMounted(load)
</script>

<template>
  <div class="page">
    <div v-if="loading" class="empty">…</div>
    <CommonEmptyState v-else-if="!items.length" :description="t('student.certs.empty')" />
    <div v-else class="list">
      <article v-for="item in items" :key="item.id" class="card">
        <div>
          <strong>{{ item.course?.title || item.career_path?.title || t('student.certs.untitled') }}</strong>
          <span>{{ item.certificate_template?.name || t('student.certs.defaultTpl') }}</span>
          <span>{{ t('student.certs.issued') }}: {{ formatDate(item.issued_at) }}</span>
        </div>
        <div class="actions">
          <code>{{ item.credential_id }}</code>
          <Button
            :label="t('student.certs.verify')"
            icon="pi pi-verified"
            size="small"
            @click="navigateTo(`/certificates/verify/${item.credential_id}`)"
          />
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 14px; }
.empty { color: var(--text-muted); }
.list { display: grid; gap: 10px; }
.card {
  display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: center;
  padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
}
.card strong { display: block; margin-bottom: 4px; }
.card span { display: block; color: var(--text-muted); font-size: .85rem; font-weight: 500; }
.actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
.actions code {
  font-size: .78rem; padding: 4px 8px; border-radius: 8px; background: var(--surface-subtle);
  border: 1px solid var(--border);
}
</style>
