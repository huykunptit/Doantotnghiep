<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

interface SiteSettings {
  theme_color_primary?: string | null
  theme_color_deep?: string | null
  brand_name?: string | null
  brand_mark?: string | null
  brand_logo?: string | null
  brand_logo_url?: string | null
  site_title?: string | null
  auth_page_image?: string | null
  auth_page_image_url?: string | null
  site_name?: string | null
  site_tagline?: string | null
  site_description?: string | null
  site_logo?: string | null
  site_logo_url?: string | null
  site_favicon?: string | null
  site_favicon_url?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  contact_address?: string | null
  support_hours?: string | null
  social_facebook?: string | null
  social_youtube?: string | null
  social_tiktok?: string | null
  social_linkedin?: string | null
  social_zalo?: string | null
  smtp_host?: string | null
  smtp_port?: string | null
  smtp_username?: string | null
  smtp_password?: string | null
  smtp_encryption?: string | null
  smtp_from_address?: string | null
  smtp_from_name?: string | null
  footer_copyright?: string | null
  legal_company_name?: string | null
  legal_tax_code?: string | null
  terms_url?: string | null
  privacy_url?: string | null
  default_locale?: string | null
  default_currency?: string | null
  timezone?: string | null
}

type FormState = {
  theme_color_primary: string
  theme_color_deep: string
  brand_name: string
  brand_mark: string
  brand_logo: string
  site_title: string
  auth_page_image: string
  site_name: string
  site_tagline: string
  site_description: string
  site_logo: string
  site_favicon: string
  contact_email: string
  contact_phone: string
  contact_address: string
  support_hours: string
  social_facebook: string
  social_youtube: string
  social_tiktok: string
  social_linkedin: string
  social_zalo: string
  smtp_host: string
  smtp_port: string
  smtp_username: string
  smtp_password: string
  smtp_encryption: string
  smtp_from_address: string
  smtp_from_name: string
  footer_copyright: string
  legal_company_name: string
  legal_tax_code: string
  terms_url: string
  privacy_url: string
  default_locale: string
  default_currency: string
  timezone: string
}

const FORM_DEFAULTS: FormState = {
  theme_color_primary: '#0f766e',
  theme_color_deep: '#0d655e',
  brand_name: '',
  brand_mark: '',
  brand_logo: '',
  site_title: '',
  auth_page_image: '',
  site_name: '',
  site_tagline: '',
  site_description: '',
  site_logo: '',
  site_favicon: '',
  contact_email: '',
  contact_phone: '',
  contact_address: '',
  support_hours: '',
  social_facebook: '',
  social_youtube: '',
  social_tiktok: '',
  social_linkedin: '',
  social_zalo: '',
  smtp_host: '',
  smtp_port: '',
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls',
  smtp_from_address: '',
  smtp_from_name: '',
  footer_copyright: '',
  legal_company_name: '',
  legal_tax_code: '',
  terms_url: '',
  privacy_url: '',
  default_locale: 'vi',
  default_currency: 'VND',
  timezone: 'Asia/Ho_Chi_Minh',
}

const { t } = useI18n()
const toast = useToast()
const siteSettings = useSiteSettings()

const loading = ref(true)
const saving = ref(false)
const activeTab = ref('branding')
const logoPreview = ref('')
const faviconPreview = ref('')
const authPreview = ref('')

const form = reactive<FormState>({ ...FORM_DEFAULTS })

const tabs = computed(() => [
  { id: 'branding', label: t('admin.settings.tabs.branding') },
  { id: 'contact', label: t('admin.settings.tabs.contact') },
  { id: 'social', label: t('admin.settings.tabs.social') },
  { id: 'smtp', label: t('admin.settings.tabs.smtp') },
  { id: 'legal', label: t('admin.settings.tabs.legal') },
  { id: 'locale', label: t('admin.settings.tabs.locale') },
])

const encryptionOptions = computed(() => [
  { label: 'TLS', value: 'tls' },
  { label: 'SSL', value: 'ssl' },
  { label: t('admin.settings.encNone'), value: 'none' },
])

const localeOptions = computed(() => [
  { label: t('admin.settings.localeVi'), value: 'vi' },
  { label: t('admin.settings.localeEn'), value: 'en' },
])

const currencyOptions = [
  { label: 'VND', value: 'VND' },
  { label: 'USD', value: 'USD' },
  { label: 'EUR', value: 'EUR' },
]

const timezoneOptions = [
  { label: 'Asia/Ho_Chi_Minh', value: 'Asia/Ho_Chi_Minh' },
  { label: 'Asia/Bangkok', value: 'Asia/Bangkok' },
  { label: 'Asia/Singapore', value: 'Asia/Singapore' },
  { label: 'UTC', value: 'UTC' },
]

const descriptionLength = computed(() => (form.site_description || '').length)

function applySettings(data?: SiteSettings | { settings?: SiteSettings }) {
  const payload = data && 'settings' in (data as Record<string, unknown>)
    ? (data as { settings?: SiteSettings }).settings
    : (data as SiteSettings | undefined)

  const merged: Partial<FormState> = {}
  if (payload) {
    for (const key of Object.keys(FORM_DEFAULTS) as Array<keyof FormState>) {
      const value = (payload as Record<string, unknown>)[key]
      if (value !== undefined && value !== null) merged[key] = String(value)
    }
  }
  Object.assign(form, FORM_DEFAULTS, merged)
  form.brand_name = form.brand_name || form.site_name
  form.site_name = form.site_name || form.brand_name
  form.brand_logo = form.brand_logo || form.site_logo
  form.site_logo = form.site_logo || form.brand_logo
  form.site_title = form.site_title || form.brand_name || form.site_name
  logoPreview.value = payload?.brand_logo_url || payload?.site_logo_url || ''
  faviconPreview.value = payload?.site_favicon_url || ''
  authPreview.value = payload?.auth_page_image_url || ''
}

async function load() {
  loading.value = true
  try {
    applySettings(await useApi<SiteSettings>('/admin/settings'))
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.settings.loadError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const payload: Record<string, string> = {}
    for (const key of Object.keys(FORM_DEFAULTS) as Array<keyof FormState>) {
      const value = form[key] ?? ''
      payload[key] = key === 'site_description' ? String(value).slice(0, 500) : String(value)
    }
    const brandName = payload.brand_name || payload.site_name || ''
    const brandLogo = payload.brand_logo || payload.site_logo || ''
    payload.brand_name = brandName
    payload.site_name = brandName
    payload.brand_logo = brandLogo
    payload.site_logo = brandLogo
    payload.site_title = payload.site_title || brandName

    const res = await useApi<{ message?: string, settings?: SiteSettings }>('/admin/settings', {
      method: 'PUT',
      body: payload,
    })
    applySettings(res)
    siteSettings.loaded.value = false
    await siteSettings.load()
    toast.add({
      severity: 'success',
      summary: res.message || t('admin.settings.saved'),
      life: 2500,
    })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.settings.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    saving.value = false
  }
}

onMounted(load)

function onLogoUploaded(payload: { url: string, path: string }) {
  form.site_logo = payload.path
  form.brand_logo = payload.path
  logoPreview.value = payload.url
}

function onFaviconUploaded(payload: { url: string, path: string }) {
  form.site_favicon = payload.path
  faviconPreview.value = payload.url
}

function onAuthUploaded(payload: { url: string, path: string }) {
  form.auth_page_image = payload.path
  authPreview.value = payload.url
}
</script>

<template>
  <div class="page settings-page">
    <header class="workspace-head">
      <div>
        <span class="eyebrow">{{ t('admin.menu.system') }}</span>
        <h1>{{ t('admin.settings.title') }}</h1>
        <p>{{ t('admin.settings.subtitle') }}</p>
      </div>
      <div class="page-actions">
        <Button
          :label="t('common.refresh')"
          icon="pi pi-refresh"
          severity="secondary"
          outlined
          :loading="loading"
          @click="load"
        />
        <Button
          :label="t('common.save')"
          icon="pi pi-check"
          :loading="saving"
          :disabled="loading"
          @click="save"
        />
      </div>
    </header>

    <div v-if="loading" class="loading-box">
      <ProgressSpinner style="width:36px;height:36px" stroke-width="4" />
      <span>{{ t('common.loading') }}</span>
    </div>

    <section v-else class="panel">
      <div class="tab-rail">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="tab"
          :class="{ on: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-show="activeTab === 'branding'" class="form-grid">
        <label class="field">
          <span>{{ t('admin.settings.siteName') }}</span>
          <InputText v-model="form.site_name" class="w-full" @update:model-value="(v: string) => form.brand_name = v" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.siteTitle') }}</span>
          <InputText v-model="form.site_title" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.brandMark') }}</span>
          <InputText v-model="form.brand_mark" class="w-full" maxlength="8" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.tagline') }}</span>
          <InputText v-model="form.site_tagline" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.settings.description') }} ({{ descriptionLength }}/500)</span>
          <Textarea v-model="form.site_description" rows="3" auto-resize class="w-full" maxlength="500" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.primaryColor') }}</span>
          <InputText v-model="form.theme_color_primary" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.deepColor') }}</span>
          <InputText v-model="form.theme_color_deep" class="w-full" />
        </label>
        <div class="field">
          <CommonMediaUpload
            v-model="logoPreview"
            folder="settings"
            variant="square"
            :label="t('admin.settings.logoPath')"
            hint="PNG, SVG, JPG — logo site"
            placeholder-initial="LOGO"
            @uploaded="onLogoUploaded"
            @update:model-value="(v) => { if (!v) { form.site_logo = ''; form.brand_logo = '' } }"
          />
        </div>
        <div class="field">
          <CommonMediaUpload
            v-model="faviconPreview"
            folder="settings"
            variant="square"
            :label="t('admin.settings.faviconPath')"
            hint="ICO / PNG — favicon"
            placeholder-initial="ICO"
            @uploaded="onFaviconUploaded"
            @update:model-value="(v) => { if (!v) form.site_favicon = '' }"
          />
        </div>
        <div class="field full">
          <CommonMediaUpload
            v-model="authPreview"
            folder="settings"
            variant="banner"
            :label="t('admin.settings.authImagePath')"
            hint="Ảnh nền trang đăng nhập / đăng ký"
            placeholder-initial="AUTH"
            @uploaded="onAuthUploaded"
            @update:model-value="(v) => { if (!v) form.auth_page_image = '' }"
          />
        </div>
      </div>

      <div v-show="activeTab === 'contact'" class="form-grid">
        <label class="field">
          <span>{{ t('admin.settings.contactEmail') }}</span>
          <InputText v-model="form.contact_email" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.contactPhone') }}</span>
          <InputText v-model="form.contact_phone" class="w-full" />
        </label>
        <label class="field full">
          <span>{{ t('admin.settings.contactAddress') }}</span>
          <Textarea v-model="form.contact_address" rows="2" auto-resize class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.supportHours') }}</span>
          <InputText v-model="form.support_hours" class="w-full" />
        </label>
      </div>

      <div v-show="activeTab === 'social'" class="form-grid">
        <label class="field">
          <span>Facebook</span>
          <InputText v-model="form.social_facebook" class="w-full" />
        </label>
        <label class="field">
          <span>YouTube</span>
          <InputText v-model="form.social_youtube" class="w-full" />
        </label>
        <label class="field">
          <span>TikTok</span>
          <InputText v-model="form.social_tiktok" class="w-full" />
        </label>
        <label class="field">
          <span>LinkedIn</span>
          <InputText v-model="form.social_linkedin" class="w-full" />
        </label>
        <label class="field">
          <span>Zalo</span>
          <InputText v-model="form.social_zalo" class="w-full" />
        </label>
      </div>

      <div v-show="activeTab === 'smtp'" class="form-grid">
        <p class="smtp-note full">{{ t('admin.settings.smtpNote') }}</p>
        <div class="full page-actions" style="margin-bottom: 4px">
          <NuxtLink to="/admin/notifications">
            <Button :label="t('admin.notifications.testEmail')" severity="secondary" outlined size="small" />
          </NuxtLink>
        </div>
        <label class="field">
          <span>{{ t('admin.settings.smtpHost') }}</span>
          <InputText v-model="form.smtp_host" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.smtpPort') }}</span>
          <InputText v-model="form.smtp_port" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.smtpUsername') }}</span>
          <InputText v-model="form.smtp_username" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.smtpPassword') }}</span>
          <Password v-model="form.smtp_password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.smtpEncryption') }}</span>
          <Select
            v-model="form.smtp_encryption"
            :options="encryptionOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.smtpFromAddress') }}</span>
          <InputText v-model="form.smtp_from_address" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.smtpFromName') }}</span>
          <InputText v-model="form.smtp_from_name" class="w-full" />
        </label>
      </div>

      <div v-show="activeTab === 'legal'" class="form-grid">
        <label class="field">
          <span>{{ t('admin.settings.footerCopyright') }}</span>
          <InputText v-model="form.footer_copyright" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.legalCompany') }}</span>
          <InputText v-model="form.legal_company_name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.legalTax') }}</span>
          <InputText v-model="form.legal_tax_code" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.termsUrl') }}</span>
          <InputText v-model="form.terms_url" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.privacyUrl') }}</span>
          <InputText v-model="form.privacy_url" class="w-full" />
        </label>
      </div>

      <div v-show="activeTab === 'locale'" class="form-grid">
        <label class="field">
          <span>{{ t('admin.settings.defaultLocale') }}</span>
          <Select
            v-model="form.default_locale"
            :options="localeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.defaultCurrency') }}</span>
          <Select
            v-model="form.default_currency"
            :options="currencyOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
        <label class="field">
          <span>{{ t('admin.settings.timezone') }}</span>
          <Select
            v-model="form.timezone"
            :options="timezoneOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </label>
      </div>
    </section>
  </div>
</template>

<style scoped>
.settings-page { gap: 14px; }
.workspace-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.eyebrow {
  display: block; margin-bottom: 4px; color: var(--brand);
  font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
}
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }
.page-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.loading-box {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  min-height: 240px; color: var(--text-muted);
}

.panel {
  border: 1px solid var(--border); border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px); padding: 16px;
}

.tab-rail { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
.tab {
  border: 1px solid transparent; border-radius: 999px; padding: 8px 14px;
  background: transparent; color: var(--text-muted); font: inherit; font-size: .84rem;
  font-weight: 700; cursor: pointer;
}
.tab:hover { background: var(--surface-hover); color: var(--text); }
.tab.on {
  border-color: color-mix(in srgb, var(--brand) 35%, var(--border));
  background: var(--brand-soft); color: var(--brand);
}

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.field small { color: var(--text-muted); font-size: .74rem; }
.field.full, .full { grid-column: 1 / -1; }
.w-full { width: 100%; }
.smtp-note {
  margin: 0; padding: 10px 12px; border-radius: 10px;
  background: var(--surface-subtle); color: var(--text-muted); font-size: .84rem; font-weight: 500;
}
.previews { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.preview-logo { max-height: 48px; max-width: 180px; object-fit: contain; }
.preview-fav { width: 32px; height: 32px; object-fit: contain; }
.preview-auth { max-height: 80px; max-width: 160px; border-radius: 8px; object-fit: cover; }

@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
