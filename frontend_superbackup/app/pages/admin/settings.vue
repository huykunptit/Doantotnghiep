<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Textarea from 'primevue/textarea'
import MediaUpload from '~/components/common/MediaUpload.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'
import { useToast } from '~/composables/useToast'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm cài đặt...' })

interface SiteSettings {
  // Branding
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
  // Contact
  contact_email?: string | null
  contact_phone?: string | null
  contact_address?: string | null
  support_hours?: string | null
  // Social
  social_facebook?: string | null
  social_youtube?: string | null
  social_tiktok?: string | null
  social_linkedin?: string | null
  social_zalo?: string | null
  // SMTP
  smtp_host?: string | null
  smtp_port?: string | null
  smtp_username?: string | null
  smtp_password?: string | null
  smtp_encryption?: string | null
  smtp_from_address?: string | null
  smtp_from_name?: string | null
  // Legal / Footer
  footer_copyright?: string | null
  legal_company_name?: string | null
  legal_tax_code?: string | null
  terms_url?: string | null
  privacy_url?: string | null
  // Localization
  default_locale?: string | null
  default_currency?: string | null
  timezone?: string | null
}

type FormState = Required<Omit<SiteSettings, 'brand_logo_url' | 'auth_page_image_url' | 'site_logo_url' | 'site_favicon_url'>>

const FORM_DEFAULTS: FormState = {
  theme_color_primary: 'var(--green)', theme_color_deep: 'var(--green-deep)',
  brand_name: '', brand_mark: '', brand_logo: '', site_title: '', auth_page_image: '',
  site_name: '', site_tagline: '', site_description: '', site_logo: '', site_favicon: '',
  contact_email: '', contact_phone: '', contact_address: '', support_hours: '',
  social_facebook: '', social_youtube: '', social_tiktok: '', social_linkedin: '', social_zalo: '',
  smtp_host: '', smtp_port: '', smtp_username: '', smtp_password: '', smtp_encryption: 'tls',
  smtp_from_address: '', smtp_from_name: '',
  footer_copyright: '', legal_company_name: '', legal_tax_code: '', terms_url: '', privacy_url: '',
  default_locale: 'vi', default_currency: 'VND', timezone: 'Asia/Ho_Chi_Minh',
}

const TABS = [
  { id: 'branding', label: 'Thương hiệu' },
  { id: 'contact', label: 'Liên hệ' },
  { id: 'social', label: 'Mạng xã hội' },
  { id: 'smtp', label: 'SMTP' },
  { id: 'legal', label: 'Pháp lý & Footer' },
  { id: 'locale', label: 'Khu vực & Tiền tệ' },
] as const
type TabId = (typeof TABS)[number]['id']
const encryptionOptions = [{ label: 'TLS', value: 'tls' }, { label: 'SSL', value: 'ssl' }, { label: 'Không mã hóa', value: 'none' }]
const localeOptions = [{ label: 'Tiếng Việt', value: 'vi' }, { label: 'English', value: 'en' }]
const currencyOptions = [{ label: 'VND - Đồng Việt Nam', value: 'VND' }, { label: 'USD - US Dollar', value: 'USD' }, { label: 'EUR - Euro', value: 'EUR' }, { label: 'JPY - Yên Nhật', value: 'JPY' }]
const timezoneOptions = ['Asia/Ho_Chi_Minh', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'UTC']

const user = useAuthUserCookie()
if (!user.value) await navigateTo('/login', { replace: true })
const token = useAuthTokenCookie()
const { refreshSettings } = useSiteSettings()

const loading = ref(false)
const saving = ref(false)
const sendingTest = ref(false)
const logoPreviewUrl = ref('')
const authImagePreviewUrl = ref('')
const faviconPreviewUrl = ref('')
const activeTab = ref<TabId>('branding')
const testEmail = ref('')

const form = reactive<FormState>({ ...FORM_DEFAULTS })
const toast = useToast()

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const descriptionLength = computed(() => (form.site_description || '').length)
const hasSmtpConfigured = computed(() => Boolean(form.smtp_host && form.smtp_port))

function applySettings(data?: SiteSettings | { settings?: SiteSettings }) {
  const payload = data && 'settings' in (data as Record<string, unknown>)
    ? (data as { settings?: SiteSettings }).settings
    : (data as SiteSettings | undefined)
  const merged: Partial<FormState> = {}
  if (payload) {
    for (const key of Object.keys(FORM_DEFAULTS) as Array<keyof FormState>) {
      const value = (payload as Record<string, unknown>)[key]
      if (value !== undefined && value !== null) {
        merged[key] = String(value) as FormState[typeof key]
      }
    }
  }
  Object.assign(form, FORM_DEFAULTS, merged)
  form.brand_name = form.brand_name || form.site_name
  form.site_name = form.site_name || form.brand_name
  form.brand_logo = form.brand_logo || form.site_logo
  form.site_logo = form.site_logo || form.brand_logo
  form.site_title = form.site_title || form.brand_name || form.site_name
  logoPreviewUrl.value = payload?.brand_logo_url || payload?.site_logo_url || ''
  authImagePreviewUrl.value = payload?.auth_page_image_url || ''
  faviconPreviewUrl.value = payload?.site_favicon_url || ''
}

async function fetchSettings() {
  loading.value = true
  try {
    applySettings(await useApi<SiteSettings>('/admin/settings', { headers: authHeaders() }))
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể tải cài đặt.')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    const payload: Record<string, string> = {}
    for (const key of Object.keys(FORM_DEFAULTS) as Array<keyof FormState>) {
      const value = form[key] ?? ''
      payload[key] = key === 'site_description' ? String(value).slice(0, 500) : String(value)
    }
    const normalizedBrandName = payload.brand_name || payload.site_name || ''
    const normalizedBrandLogo = payload.brand_logo || payload.site_logo || ''
    payload.brand_name = normalizedBrandName
    payload.site_name = normalizedBrandName
    payload.brand_logo = normalizedBrandLogo
    payload.site_logo = normalizedBrandLogo
    payload.site_title = payload.site_title || normalizedBrandName
    const response = await useApi<{ message?: string; settings?: SiteSettings }>('/admin/settings', {
      method: 'PUT',
      headers: authHeaders(),
      body: payload,
    })
    applySettings(response)
    await refreshSettings()
    toast.success(response?.message || 'Đã lưu cài đặt hệ thống.')
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể lưu cài đặt.')
  } finally {
    saving.value = false
  }
}

type UploadedPayload = { url: string; path: string }

async function onLogoUploaded({ path }: UploadedPayload) {
  form.brand_logo = path
  form.site_logo = path
  await saveSettings()
  toast.success('Đã cập nhật logo website.')
}

async function onAuthUploaded({ path }: UploadedPayload) {
  form.auth_page_image = path
  await saveSettings()
  toast.success('Đã cập nhật ảnh nền trang xác thực.')
}

async function onFaviconUploaded({ path }: UploadedPayload) {
  form.site_favicon = path
  await saveSettings()
  toast.success('Đã cập nhật favicon.')
}

function onUploadError(message: string) {
  toast.error(message)
}

watch(logoPreviewUrl, async (val, prev) => {
  if (prev && !val && (form.brand_logo || form.site_logo)) {
    form.brand_logo = ''
    form.site_logo = ''
    await saveSettings()
  }
})

watch(authImagePreviewUrl, async (val, prev) => {
  if (prev && !val && form.auth_page_image) {
    form.auth_page_image = ''
    await saveSettings()
  }
})

watch(faviconPreviewUrl, async (val, prev) => {
  if (prev && !val && form.site_favicon) {
    form.site_favicon = ''
    await saveSettings()
  }
})

async function sendTestEmail() {
  if (!testEmail.value) {
    toast.error('Vui lòng nhập email nhận thử.')
    return
  }
  sendingTest.value = true
  try {
    await saveSettings()
    const response = await useApi<{ message?: string }>('/admin/settings/test-smtp', {
      method: 'POST',
      headers: authHeaders(),
      body: { to: testEmail.value },
    })
    toast.success(response?.message || 'Đã gửi email kiểm tra.')
  } catch (error: any) {
    toast.error(error?.data?.message || 'Không thể gửi email kiểm tra.')
  } finally {
    sendingTest.value = false
  }
}
onMounted(fetchSettings)
</script>

<template>
  <div class="page-stack">
    <header class="page-header"><div><h1>Cài đặt hệ thống</h1><p>Quản lý thương hiệu, liên hệ, SMTP, pháp lý và khu vực.</p></div><Button label="Lưu cài đặt" icon="pi pi-save" :loading="saving" @click="saveSettings" /></header>

    <section class="summary-grid">
      <Card v-for="item in [
        { label: 'Website', value: form.site_name || 'Chưa đặt tên', detail: form.site_tagline || 'Tên hiển thị của hệ thống' },
        { label: 'Logo / Favicon', value: form.site_logo ? 'Đã có logo' : 'Chưa có logo', detail: form.site_favicon ? 'Đã có favicon' : 'Chưa có favicon' },
        { label: 'SMTP', value: form.smtp_host || 'Chưa cấu hình', detail: form.smtp_from_address || 'Chưa có email gửi đi' },
        { label: 'Liên hệ', value: form.contact_phone || form.contact_email || 'Chưa cấu hình', detail: form.contact_address || 'Chưa có địa chỉ' },
      ]" :key="item.label"><template #content><span class="summary-label">{{ item.label }}</span><strong>{{ item.value }}</strong><small>{{ item.detail }}</small></template></Card>
    </section>

    <Card>
      <template #content>
        <div v-if="loading" class="loading-state"><i class="pi pi-spin pi-spinner" /> Đang tải cài đặt...</div>
        <Tabs v-else v-model:value="activeTab" scrollable>
          <TabList><Tab v-for="tab in TABS" :key="tab.id" :value="tab.id">{{ tab.label }}</Tab></TabList>
          <TabPanels>
            <TabPanel value="branding">
              <div class="form-grid">
                <label class="field"><span>Màu chủ đạo</span><InputText v-model="form.theme_color_primary" placeholder="#1d9e75" fluid /></label>
                <label class="field"><span>Màu chủ đạo đậm</span><InputText v-model="form.theme_color_deep" placeholder="#085041" fluid /></label>
                <label class="field"><span>Brand name</span><InputText v-model="form.brand_name" placeholder="PTIT LMS" fluid /></label>
                <label class="field"><span>Brand mark</span><InputText v-model="form.brand_mark" maxlength="32" placeholder="PTIT" fluid /></label>
                <label class="field"><span>Site title</span><InputText v-model="form.site_title" placeholder="PTIT LMS" fluid /></label>
                <label class="field"><span>Slogan</span><InputText v-model="form.site_tagline" placeholder="Học mọi lúc, mọi nơi" fluid /></label>
                <label class="field full"><span>Mô tả website</span><Textarea v-model="form.site_description" rows="4" maxlength="500" fluid /><small>{{ descriptionLength }}/500 ký tự</small></label>
                <div class="field full"><span>Brand logo</span><MediaUpload v-model="logoPreviewUrl" folder="settings" variant="square" accept="image/*" label="Tải brand logo" hint="PNG/SVG, nền trong suốt, tối thiểu 128×128 — tự động tải lên." :placeholder-initial="form.brand_mark || 'LG'" @uploaded="onLogoUploaded" @error="onUploadError" /></div>
                <div class="field full"><span>Ảnh trang xác thực</span><MediaUpload v-model="authImagePreviewUrl" folder="settings" variant="banner" accept="image/*" label="Ảnh trang đăng nhập/đăng ký" hint="Ảnh lớn dùng cho khối minh họa bên trái — tối đa 5MB." placeholder-initial="AUTH" @uploaded="onAuthUploaded" @error="onUploadError" /></div>
                <div class="field full"><span>Favicon</span><MediaUpload v-model="faviconPreviewUrl" folder="settings" variant="square" accept="image/png,image/x-icon,image/svg+xml,image/*" label="Tải favicon" hint="ICO/PNG/SVG, kích thước 32×32 hoặc 64×64." placeholder-initial="FV" @uploaded="onFaviconUploaded" @error="onUploadError" /></div>
              </div>
            </TabPanel>

            <TabPanel value="contact"><div class="form-grid">
              <label class="field"><span>Email liên hệ</span><InputText v-model="form.contact_email" type="email" placeholder="contact@example.com" fluid /></label>
              <label class="field"><span>Hotline / Số điện thoại</span><InputText v-model="form.contact_phone" placeholder="0123 456 789" fluid /></label>
              <label class="field full"><span>Địa chỉ</span><InputText v-model="form.contact_address" placeholder="Số 1, Đường ABC, Quận XYZ, TP.HCM" fluid /></label>
              <label class="field full"><span>Giờ hỗ trợ</span><InputText v-model="form.support_hours" placeholder="Thứ 2 - Thứ 7, 8:00 - 17:30" fluid /></label>
            </div></TabPanel>

            <TabPanel value="social"><div class="form-grid">
              <label class="field"><span>Facebook</span><InputText v-model="form.social_facebook" type="url" placeholder="https://facebook.com/your-page" fluid /></label>
              <label class="field"><span>YouTube</span><InputText v-model="form.social_youtube" type="url" placeholder="https://youtube.com/@your-channel" fluid /></label>
              <label class="field"><span>TikTok</span><InputText v-model="form.social_tiktok" type="url" placeholder="https://tiktok.com/@your-account" fluid /></label>
              <label class="field"><span>LinkedIn</span><InputText v-model="form.social_linkedin" type="url" placeholder="https://linkedin.com/company/your-company" fluid /></label>
              <label class="field full"><span>Zalo</span><InputText v-model="form.social_zalo" type="url" placeholder="https://zalo.me/..." fluid /></label>
            </div></TabPanel>

            <TabPanel value="smtp"><div class="form-grid">
              <label class="field"><span>SMTP host</span><InputText v-model="form.smtp_host" placeholder="smtp.gmail.com" fluid /></label>
              <label class="field"><span>SMTP port</span><InputText v-model="form.smtp_port" placeholder="587" fluid /></label>
              <label class="field"><span>SMTP username</span><InputText v-model="form.smtp_username" placeholder="noreply@example.com" fluid /></label>
              <label class="field"><span>SMTP password</span><InputText v-model="form.smtp_password" type="password" autocomplete="new-password" fluid /></label>
              <label class="field"><span>Mã hóa</span><Select v-model="form.smtp_encryption" :options="encryptionOptions" option-label="label" option-value="value" fluid /></label>
              <label class="field"><span>Email gửi đi</span><InputText v-model="form.smtp_from_address" type="email" placeholder="noreply@example.com" fluid /></label>
              <label class="field full"><span>Tên người gửi</span><InputText v-model="form.smtp_from_name" placeholder="Sylva LMS" fluid /></label>
              <div class="test-email full"><label class="field"><span>Gửi email kiểm tra</span><InputText v-model="testEmail" type="email" placeholder="Email nhận thử" fluid /></label><Button label="Gửi thử" icon="pi pi-send" severity="secondary" outlined :loading="sendingTest" :disabled="!hasSmtpConfigured" @click="sendTestEmail" /><small>Hệ thống sẽ lưu cấu hình trước khi gửi email kiểm tra.</small></div>
            </div></TabPanel>

            <TabPanel value="legal"><div class="form-grid">
              <label class="field full"><span>Dòng bản quyền (footer)</span><InputText v-model="form.footer_copyright" placeholder="© 2026 Sylva LMS. All rights reserved." fluid /></label>
              <label class="field"><span>Tên doanh nghiệp</span><InputText v-model="form.legal_company_name" placeholder="Công ty TNHH ABC" fluid /></label>
              <label class="field"><span>Mã số thuế</span><InputText v-model="form.legal_tax_code" placeholder="0312345678" fluid /></label>
              <label class="field full"><span>URL Điều khoản sử dụng</span><InputText v-model="form.terms_url" type="url" placeholder="https://example.com/terms" fluid /></label>
              <label class="field full"><span>URL Chính sách bảo mật</span><InputText v-model="form.privacy_url" type="url" placeholder="https://example.com/privacy" fluid /></label>
            </div></TabPanel>

            <TabPanel value="locale"><div class="form-grid">
              <label class="field"><span>Ngôn ngữ mặc định</span><Select v-model="form.default_locale" :options="localeOptions" option-label="label" option-value="value" fluid /></label>
              <label class="field"><span>Tiền tệ mặc định</span><Select v-model="form.default_currency" :options="currencyOptions" option-label="label" option-value="value" fluid /></label>
              <label class="field full"><span>Múi giờ</span><Select v-model="form.timezone" :options="timezoneOptions" fluid /></label>
            </div></TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.page-stack{display:flex;flex-direction:column;gap:1.25rem}.page-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.page-header h1{margin:0;color:var(--p-text-color);font-size:1.5rem;font-weight:700}.page-header p{margin:.3rem 0 0;color:var(--p-text-muted-color);font-size:.875rem}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.summary-label,.summary-grid small{display:block;color:var(--p-text-muted-color);font-size:.72rem}.summary-grid strong{display:block;overflow:hidden;margin:.3rem 0;text-overflow:ellipsis;white-space:nowrap;color:var(--p-text-color);font-size:.9rem}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;padding-top:.5rem}.field{display:flex;min-width:0;flex-direction:column;gap:.45rem}.field>span{color:var(--p-text-color);font-size:.78rem;font-weight:600}.field>small,.test-email>small{color:var(--p-text-muted-color);font-size:.7rem}.full{grid-column:1/-1}.test-email{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:.75rem;padding-top:1rem;border-top:1px solid var(--p-content-border-color)}.test-email small{grid-column:1/-1}.loading-state{padding:3rem;text-align:center;color:var(--p-text-muted-color)}:deep(.p-tabpanels){padding:1.25rem 0 0}:deep(.p-tabs){color:var(--p-text-color)}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.page-header{align-items:flex-start;flex-direction:column}.summary-grid,.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.test-email{grid-template-columns:1fr}.test-email small{grid-column:auto}}
</style>
