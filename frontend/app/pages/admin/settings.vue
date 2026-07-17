<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
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
  <div class="flex flex-col gap-5">
    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-[var(--muted)] mb-1">Cấu hình hệ thống</p>
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text)]">Cài đặt hệ thống</h1>
        <p class="text-sm text-[var(--muted)] mt-0.5">Quản lý thương hiệu, liên hệ, mạng xã hội, SMTP và các thông tin pháp lý của website.</p>
      </div>
    </div>

    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <article class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-1">Website</p>
        <div class="flex flex-col">
          <strong class="text-sm font-bold text-[var(--text)] truncate">{{ form.site_name || 'Chưa đặt tên' }}</strong>
          <span class="text-xs text-[var(--muted)] truncate mt-0.5">{{ form.site_tagline || 'Tên hiển thị của hệ thống' }}</span>
        </div>
      </article>
      <article class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-1">Logo / Favicon</p>
        <div class="flex flex-col">
          <strong class="text-sm font-bold text-[var(--text)] truncate">{{ form.site_logo ? 'Đã có logo' : 'Chưa có logo' }}</strong>
          <span class="text-xs text-[var(--muted)] truncate mt-0.5">{{ form.site_favicon ? 'Đã có favicon' : 'Chưa có favicon' }}</span>
        </div>
      </article>
      <article class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-1">SMTP</p>
        <div class="flex flex-col">
          <strong class="text-sm font-bold text-[var(--text)] truncate">{{ form.smtp_host || '--' }}</strong>
          <span class="text-xs text-[var(--muted)] truncate mt-0.5">{{ form.smtp_from_address || 'Chưa cấu hình email gửi đi' }}</span>
        </div>
      </article>
      <article class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)] mb-1">Liên hệ</p>
        <div class="flex flex-col">
          <strong class="text-sm font-bold text-[var(--text)] truncate">{{ form.contact_phone || form.contact_email || '--' }}</strong>
          <span class="text-xs text-[var(--muted)] truncate mt-0.5">{{ form.contact_address || 'Chưa có địa chỉ liên hệ' }}</span>
        </div>
      </article>
    </section>

    <section class="bg-white border border-[var(--line)] rounded-2xl p-5 shadow-sm">
      <div class="flex items-center justify-between border-b border-[var(--line)] pb-4 mb-4">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Cài đặt</p>
          <h3 class="text-base font-bold text-[var(--text)] mt-0.5">{{ TABS.find(tab => tab.id === activeTab)?.label }}</h3>
        </div>
        <button 
          class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-xl text-xs font-semibold text-white bg-[#1d9e75] hover:bg-[#17876a] transition-colors disabled:opacity-50" 
          type="button" 
          :disabled="saving" 
          @click="saveSettings"
        >
          <i class="pi pi-save" />
          {{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}
        </button>
      </div>

      <nav class="flex flex-wrap gap-2 p-1.5 bg-[var(--surface)] border border-[var(--line)] rounded-2xl mb-6">
        <button
          v-for="tab in TABS"
          :key="tab.id"
          role="tab"
          type="button"
          class="flex-1 min-w-[120px] px-4 py-2 rounded-xl text-xs font-semibold text-[var(--muted)] transition-all cursor-pointer text-center border border-transparent"
          :class="activeTab === tab.id ? 'bg-white border-[var(--line)] text-[var(--text)] shadow-sm' : 'hover:text-[var(--text)]'"
          :aria-selected="activeTab === tab.id"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </nav>

      <div v-if="loading" class="text-center py-8 text-sm text-[var(--muted)]">Đang tải cài đặt...</div>

      <div v-else>
        <!-- Branding -->
        <div v-show="activeTab === 'branding'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Màu chủ đạo (Primary Color)</span>
            <input v-model="form.theme_color_primary" type="color" class="w-full h-11 border border-[var(--line)] rounded-xl cursor-pointer p-0 bg-transparent">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Màu chủ đạo đậm (Deep Color)</span>
            <input v-model="form.theme_color_deep" type="color" class="w-full h-11 border border-[var(--line)] rounded-xl cursor-pointer p-0 bg-transparent">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Brand name</span>
            <input v-model="form.brand_name" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="PTIT LMS">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Brand mark</span>
            <input v-model="form.brand_mark" type="text" maxlength="32" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="PTIT">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Site title</span>
            <input v-model="form.site_title" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="PTIT LMS">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Slogan</span>
            <input v-model="form.site_tagline" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="Học mọi lúc, mọi nơi">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Mô tả website</span>
            <textarea v-model="form.site_description" class="p-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full resize-y" rows="4" maxlength="500" placeholder="Nền tảng học trực tuyến..."></textarea>
            <small class="text-[10px] text-[var(--muted)] mt-1">{{ descriptionLength }}/500 ký tự</small>
          </div>

          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Brand logo</span>
            <MediaUpload
              v-model="logoPreviewUrl"
              folder="settings"
              variant="square"
              accept="image/*"
              label="Tải brand logo"
              hint="PNG/SVG, nền trong suốt, tối thiểu 128×128 — tự động tải lên."
              :placeholder-initial="form.brand_mark || 'LG'"
              @uploaded="onLogoUploaded"
              @error="onUploadError"
            />
          </div>

          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Ảnh trang xác thực</span>
            <MediaUpload
              v-model="authImagePreviewUrl"
              folder="settings"
              variant="banner"
              accept="image/*"
              label="Ảnh trang đăng nhập/đăng ký"
              hint="Ảnh lớn dùng cho khối minh hoạ bên trái — tối đa 5MB."
              placeholder-initial="AUTH"
              @uploaded="onAuthUploaded"
              @error="onUploadError"
            />
          </div>

          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Favicon</span>
            <MediaUpload
              v-model="faviconPreviewUrl"
              folder="settings"
              variant="square"
              accept="image/png,image/x-icon,image/svg+xml,image/*"
              label="Tải favicon"
              hint="ICO/PNG/SVG, kích thước 32×32 hoặc 64×64."
              placeholder-initial="FV"
              @uploaded="onFaviconUploaded"
              @error="onUploadError"
            />
          </div>
        </div>

        <!-- Contact -->
        <div v-show="activeTab === 'contact'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Email liên hệ</span>
            <input v-model="form.contact_email" type="email" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="contact@example.com">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Hotline / Số điện thoại</span>
            <input v-model="form.contact_phone" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="0123 456 789">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Địa chỉ</span>
            <input v-model="form.contact_address" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="Số 1, Đường ABC, Quận XYZ, TP.HCM">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Giờ hỗ trợ</span>
            <input v-model="form.support_hours" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="Thứ 2 - Thứ 7, 8:00 - 17:30">
          </div>
        </div>

        <!-- Social -->
        <div v-show="activeTab === 'social'" class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Facebook</span>
            <input v-model="form.social_facebook" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://facebook.com/your-page">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">YouTube</span>
            <input v-model="form.social_youtube" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://youtube.com/@your-channel">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">TikTok</span>
            <input v-model="form.social_tiktok" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://tiktok.com/@your-account">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">LinkedIn</span>
            <input v-model="form.social_linkedin" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://linkedin.com/company/your-company">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Zalo</span>
            <input v-model="form.social_zalo" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://zalo.me/...">
          </div>
        </div>

        <!-- SMTP -->
        <div v-show="activeTab === 'smtp'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">SMTP host</span>
            <input v-model="form.smtp_host" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="smtp.gmail.com">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">SMTP port</span>
            <input v-model="form.smtp_port" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="587">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">SMTP username</span>
            <input v-model="form.smtp_username" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="noreply@example.com">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">SMTP password</span>
            <input v-model="form.smtp_password" type="password" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="••••••••" autocomplete="new-password">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Mã hóa</span>
            <select v-model="form.smtp_encryption" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full cursor-pointer">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="none">None</option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Email gửi đi</span>
            <input v-model="form.smtp_from_address" type="email" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="noreply@example.com">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Tên người gửi</span>
            <input v-model="form.smtp_from_name" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="Sylva LMS">
          </div>

          <div class="flex flex-col gap-2 md:col-span-2 border-t border-[var(--line)] pt-4 mt-2">
            <span class="text-xs font-semibold text-[var(--text)]">Gửi email kiểm tra</span>
            <div class="flex flex-col sm:flex-row gap-3">
              <input v-model="testEmail" type="email" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] flex-1" placeholder="Email nhận thử (vd: ban@example.com)">
              <button 
                class="inline-flex items-center justify-center h-9 px-4 rounded-xl border border-[var(--line)] bg-white hover:bg-[var(--surface)] text-xs font-semibold text-[var(--text)] transition-colors disabled:opacity-40 disabled:cursor-not-allowed" 
                type="button" 
                :disabled="sendingTest || !hasSmtpConfigured" 
                @click="sendTestEmail"
              >
                {{ sendingTest ? 'Đang gửi...' : 'Gửi thử' }}
              </button>
            </div>
            <small class="text-[10px] text-[var(--muted)]">Hệ thống sẽ lưu cấu hình rồi gửi một email kiểm tra tới địa chỉ ở trên.</small>
          </div>
        </div>

        <!-- Legal / Footer -->
        <div v-show="activeTab === 'legal'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Dòng bản quyền (footer)</span>
            <input v-model="form.footer_copyright" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="© 2026 Sylva LMS. All rights reserved.">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Tên doanh nghiệp</span>
            <input v-model="form.legal_company_name" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="Công ty TNHH ABC">
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Mã số thuế</span>
            <input v-model="form.legal_tax_code" type="text" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="0312345678">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">URL Điều khoản sử dụng</span>
            <input v-model="form.terms_url" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://example.com/terms">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">URL Chính sách bảo mật</span>
            <input v-model="form.privacy_url" type="url" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full" placeholder="https://example.com/privacy">
          </div>
        </div>

        <!-- Localization -->
        <div v-show="activeTab === 'locale'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Ngôn ngữ mặc định</span>
            <select v-model="form.default_locale" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full cursor-pointer">
              <option value="vi">Tiếng Việt</option>
              <option value="en">English</option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-xs font-semibold text-[var(--text)]">Tiền tệ mặc định</span>
            <select v-model="form.default_currency" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full cursor-pointer">
              <option value="VND">VND - Đồng Việt Nam</option>
              <option value="USD">USD - US Dollar</option>
              <option value="EUR">EUR - Euro</option>
              <option value="JPY">JPY - Yên Nhật</option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <span class="text-xs font-semibold text-[var(--text)]">Múi giờ</span>
            <select v-model="form.timezone" class="h-9 px-3 rounded-xl border border-[var(--line)] bg-white text-sm text-[var(--text)] focus:outline-none focus:border-[#1d9e75] w-full cursor-pointer">
              <option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh (UTC+7)</option>
              <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
              <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
              <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
              <option value="UTC">UTC</option>
            </select>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Scoped styles kept minimal */
</style>
