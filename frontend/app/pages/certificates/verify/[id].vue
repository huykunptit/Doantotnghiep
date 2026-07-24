<script setup lang="ts">
definePageMeta({ layout: 'default' })

interface VerifyResult {
  credential_id?: string
  issued_at?: string | null
  user?: { name?: string } | null
  course?: { title?: string } | null
  career_path?: { title?: string } | null
  certificate_template?: { name?: string } | null
  message?: string
}

const route = useRoute()
const { t, locale } = useI18n()
const credentialId = computed(() => String(route.params.id || ''))

const loading = ref(true)
const error = ref('')
const data = ref<VerifyResult | null>(null)

const dateLocale = computed(() => (locale.value === 'en' ? 'en-US' : 'vi-VN'))

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await useApi<VerifyResult>(`/certificates/verify/${credentialId.value}`, { token: null })
  }
  catch (e: any) {
    error.value = e?.data?.message || t('student.certs.verifyFail')
    data.value = null
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="wrap">
    <div class="card">
      <span class="eyebrow">{{ t('student.certs.verifyEyebrow') }}</span>
      <h1>{{ t('student.certs.verifyTitle') }}</h1>
      <p v-if="loading">…</p>
      <p v-else-if="error" class="err">{{ error }}</p>
      <template v-else-if="data">
        <div class="ok"><i class="pi pi-verified" /> {{ t('student.certs.verifyOk') }}</div>
        <dl>
          <div><dt>{{ t('student.certs.holder') }}</dt><dd>{{ data.user?.name || '—' }}</dd></div>
          <div><dt>{{ t('student.certs.courseOrPath') }}</dt><dd>{{ data.course?.title || data.career_path?.title || '—' }}</dd></div>
          <div><dt>{{ t('student.certs.credential') }}</dt><dd><code>{{ data.credential_id || credentialId }}</code></dd></div>
          <div>
            <dt>{{ t('student.certs.issued') }}</dt>
            <dd>{{ data.issued_at ? new Date(data.issued_at).toLocaleDateString(dateLocale) : '—' }}</dd>
          </div>
        </dl>
      </template>
      <Button :label="t('student.payment.goCatalog')" text @click="navigateTo('/courses')" />
    </div>
  </div>
</template>

<style scoped>
.wrap { min-height: 70vh; display: grid; place-items: center; padding: 24px; }
.card {
  width: min(520px, 100%); padding: 28px; border-radius: 16px; border: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 94%, transparent); display: grid; gap: 12px;
}
.eyebrow { color: var(--brand); font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
h1 { margin: 0; font-size: 1.45rem; }
.ok { display: flex; gap: 8px; align-items: center; color: var(--brand); font-weight: 750; }
.err { color: #b91c1c; font-weight: 600; }
dl { margin: 0; display: grid; gap: 10px; }
dl div { display: grid; gap: 2px; }
dt { color: var(--text-muted); font-size: .78rem; font-weight: 650; text-transform: uppercase; }
dd { margin: 0; font-weight: 650; }
code { font-size: .85rem; }
</style>
