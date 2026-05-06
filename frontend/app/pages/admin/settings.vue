<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'
import { useAdminUpload } from '~/composables/useAdminUpload'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm cài đặt...' })

interface SiteSettings {
  // Branding
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

type FormState = Required<Omit<SiteSettings, 'site_logo_url' | 'site_favicon_url'>>

const FORM_DEFAULTS: FormState = {
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
const { uploadImage } = useAdminUpload()
const { refreshSettings } = useSiteSettings()

const loading = ref(false)
const saving = ref(false)
const uploadingLogo = ref(false)
const uploadingFavicon = ref(false)
const sendingTest = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const logoFile = ref<File | null>(null)
const faviconFile = ref<File | null>(null)
const logoPreviewUrl = ref('')
const faviconPreviewUrl = ref('')
const activeTab = ref<TabId>('branding')
const testEmail = ref('')

const form = reactive<FormState>({ ...FORM_DEFAULTS })

const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
const descriptionLength = computed(() => (form.site_description || '').length)
const hasSmtpConfigured = computed(() => Boolean(form.smtp_host && form.smtp_port))

function resetAlerts() { errorMessage.value = ''; successMessage.value = '' }

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
  logoPreviewUrl.value = payload?.site_logo_url || ''
  faviconPreviewUrl.value = payload?.site_favicon_url || ''
}

async function fetchSettings() {
  loading.value = true
  resetAlerts()
  try {
    applySettings(await useApi<SiteSettings>('/admin/settings', { headers: authHeaders() }))
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải cài đặt.'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  resetAlerts()
  try {
    const payload: Record<string, string> = {}
    for (const key of Object.keys(FORM_DEFAULTS) as Array<keyof FormState>) {
      const value = form[key] ?? ''
      payload[key] = key === 'site_description' ? String(value).slice(0, 500) : String(value)
    }
    const response = await useApi<{ message?: string; settings?: SiteSettings }>('/admin/settings', {
      method: 'PUT',
      headers: authHeaders(),
      body: payload,
    })
    applySettings(response)
    await refreshSettings()
    successMessage.value = response?.message || 'Đã lưu cài đặt hệ thống.'
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể lưu cài đặt.'
  } finally {
    saving.value = false
  }
}

async function uploadSettingImage(type: 'logo' | 'favicon') {
  const file = type === 'logo' ? logoFile.value : faviconFile.value
  if (!file) return
  resetAlerts()
  if (type === 'logo') uploadingLogo.value = true
  else uploadingFavicon.value = true
  try {
    const oldPath = type === 'logo' ? form.site_logo : form.site_favicon
    const uploaded = await uploadImage(file, 'settings', oldPath || null)
    if (type === 'logo') {
      form.site_logo = uploaded.path
      logoPreviewUrl.value = uploaded.url
      logoFile.value = null
    } else {
      form.site_favicon = uploaded.path
      faviconPreviewUrl.value = uploaded.url
      faviconFile.value = null
    }
    await saveSettings()
    successMessage.value = type === 'logo' ? 'Đã cập nhật logo website.' : 'Đã cập nhật favicon.'
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể tải ảnh lên.'
  } finally {
    if (type === 'logo') uploadingLogo.value = false
    else uploadingFavicon.value = false
  }
}

async function clearImage(type: 'logo' | 'favicon') {
  if (type === 'logo') {
    form.site_logo = ''
    logoPreviewUrl.value = ''
    logoFile.value = null
  } else {
    form.site_favicon = ''
    faviconPreviewUrl.value = ''
    faviconFile.value = null
  }
  await saveSettings()
}

async function sendTestEmail() {
  if (!testEmail.value) {
    errorMessage.value = 'Vui lòng nhập email nhận thử.'
    return
  }
  sendingTest.value = true
  resetAlerts()
  try {
    await saveSettings()
    const response = await useApi<{ message?: string }>('/admin/settings/test-smtp', {
      method: 'POST',
      headers: authHeaders(),
      body: { to: testEmail.value },
    })
    successMessage.value = response?.message || 'Đã gửi email kiểm tra.'
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Không thể gửi email kiểm tra.'
  } finally {
    sendingTest.value = false
  }
}
onMounted(fetchSettings)
</script>

<template>
  <AdminWorkspaceShell
    :breadcrumb="['Trang chủ', 'Quản trị hệ thống', 'Cài đặt']"
    description="Quản lý thương hiệu, liên hệ, mạng xã hội, SMTP và các thông tin pháp lý của website."
    title="Cài đặt hệ thống"
  >
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green">
        <p class="mini-title">Website</p>
        <div class="mini-head">
          <strong>{{ form.site_name || 'Chưa đặt tên' }}</strong>
          <span>{{ form.site_tagline || 'Tên hiển thị của hệ thống' }}</span>
        </div>
      </article>
      <article class="dashboard-card mini-card tone-amber">
        <p class="mini-title">Logo / Favicon</p>
        <div class="mini-head">
          <strong>{{ form.site_logo ? 'Đã có logo' : 'Chưa có logo' }}</strong>
          <span>{{ form.site_favicon ? 'Đã có favicon' : 'Chưa có favicon' }}</span>
        </div>
      </article>
      <article class="dashboard-card mini-card">
        <p class="mini-title">SMTP</p>
        <div class="mini-head">
          <strong>{{ form.smtp_host || '--' }}</strong>
          <span>{{ form.smtp_from_address || 'Chưa cấu hình email gửi đi' }}</span>
        </div>
      </article>
      <article class="dashboard-card mini-card tone-violet">
        <p class="mini-title">Liên hệ</p>
        <div class="mini-head">
          <strong>{{ form.contact_phone || form.contact_email || '--' }}</strong>
          <span>{{ form.contact_address || 'Chưa có địa chỉ liên hệ' }}</span>
        </div>
      </article>
    </section>

    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar">
        <div>
          <p class="section-kicker">Cài đặt</p>
          <h3>{{ TABS.find(tab => tab.id === activeTab)?.label }}</h3>
        </div>
        <button class="crud-primary-btn" type="button" :disabled="saving" @click="saveSettings">
          {{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}
        </button>
      </div>

      <nav class="settings-tabs" role="tablist">
        <button
          v-for="tab in TABS"
          :key="tab.id"
          role="tab"
          type="button"
          :class="['settings-tab', { 'is-active': activeTab === tab.id }]"
          :aria-selected="activeTab === tab.id"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </nav>

      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div>
      <div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>
      <div v-if="loading" class="crud-empty">Đang tải cài đặt...</div>

      <div v-else>
        <!-- Branding -->
        <div v-show="activeTab === 'branding'" class="crud-form-grid">
          <label class="crud-field">
            <span>Tên website</span>
            <input v-model="form.site_name" type="text" placeholder="ERIPT LMS">
          </label>
          <label class="crud-field">
            <span>Slogan</span>
            <input v-model="form.site_tagline" type="text" placeholder="Học mọi lúc, mọi nơi">
          </label>
          <label class="crud-field crud-field-full">
            <span>Mô tả website</span>
            <textarea v-model="form.site_description" class="crud-textarea" rows="4" maxlength="500" placeholder="Nền tảng học trực tuyến..."></textarea>
            <small class="settings-help">{{ descriptionLength }}/500 ký tự</small>
          </label>

          <div class="crud-field crud-field-full">
            <span>Logo website</span>
            <div class="crud-image-preview">
              <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Logo website">
              <div v-else class="crud-image-fallback">LG</div>
              <div class="settings-upload-wrap">
                <label class="upload-dropzone upload-dropzone-compact">
                  <input class="upload-dropzone-input" type="file" accept="image/*" @change="logoFile = ($event.target as HTMLInputElement)?.files?.[0] || null">
                  <span class="upload-dropzone-icon">🖼️</span>
                  <strong>Tải logo website</strong>
                  <span>{{ logoFile?.name || 'PNG/SVG, nền trong suốt, tối thiểu 128×128.' }}</span>
                </label>
                <div class="crud-inline-actions crud-modal-foot">
                  <button class="crud-secondary-btn" type="button" :disabled="uploadingLogo || !logoFile" @click="uploadSettingImage('logo')">
                    {{ uploadingLogo ? 'Đang tải...' : 'Tải logo lên' }}
                  </button>
                  <button v-if="form.site_logo" class="crud-secondary-btn" type="button" :disabled="uploadingLogo" @click="clearImage('logo')">
                    Xoá logo
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="crud-field crud-field-full">
            <span>Favicon</span>
            <div class="crud-image-preview">
              <img v-if="faviconPreviewUrl" :src="faviconPreviewUrl" alt="Favicon website">
              <div v-else class="crud-image-fallback">FV</div>
              <div class="settings-upload-wrap">
                <label class="upload-dropzone upload-dropzone-compact">
                  <input class="upload-dropzone-input" type="file" accept="image/png,image/x-icon,image/svg+xml,image/*" @change="faviconFile = ($event.target as HTMLInputElement)?.files?.[0] || null">
                  <span class="upload-dropzone-icon">⭐</span>
                  <strong>Tải favicon</strong>
                  <span>{{ faviconFile?.name || 'ICO/PNG, kích thước 32×32 hoặc 64×64.' }}</span>
                </label>
                <div class="crud-inline-actions crud-modal-foot">
                  <button class="crud-secondary-btn" type="button" :disabled="uploadingFavicon || !faviconFile" @click="uploadSettingImage('favicon')">
                    {{ uploadingFavicon ? 'Đang tải...' : 'Tải favicon lên' }}
                  </button>
                  <button v-if="form.site_favicon" class="crud-secondary-btn" type="button" :disabled="uploadingFavicon" @click="clearImage('favicon')">
                    Xoá favicon
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Contact -->
        <div v-show="activeTab === 'contact'" class="crud-form-grid">
          <label class="crud-field">
            <span>Email liên hệ</span>
            <input v-model="form.contact_email" type="email" placeholder="contact@example.com">
          </label>
          <label class="crud-field">
            <span>Hotline / Số điện thoại</span>
            <input v-model="form.contact_phone" type="text" placeholder="0123 456 789">
          </label>
          <label class="crud-field crud-field-full">
            <span>Địa chỉ</span>
            <input v-model="form.contact_address" type="text" placeholder="Số 1, Đường ABC, Quận XYZ, TP.HCM">
          </label>
          <label class="crud-field crud-field-full">
            <span>Giờ hỗ trợ</span>
            <input v-model="form.support_hours" type="text" placeholder="Thứ 2 - Thứ 7, 8:00 - 17:30">
          </label>
        </div>

        <!-- Social -->
        <div v-show="activeTab === 'social'" class="crud-form-grid">
          <label class="crud-field crud-field-full">
            <span>Facebook</span>
            <input v-model="form.social_facebook" type="url" placeholder="https://facebook.com/your-page">
          </label>
          <label class="crud-field crud-field-full">
            <span>YouTube</span>
            <input v-model="form.social_youtube" type="url" placeholder="https://youtube.com/@your-channel">
          </label>
          <label class="crud-field crud-field-full">
            <span>TikTok</span>
            <input v-model="form.social_tiktok" type="url" placeholder="https://tiktok.com/@your-account">
          </label>
          <label class="crud-field crud-field-full">
            <span>LinkedIn</span>
            <input v-model="form.social_linkedin" type="url" placeholder="https://linkedin.com/company/your-company">
          </label>
          <label class="crud-field crud-field-full">
            <span>Zalo</span>
            <input v-model="form.social_zalo" type="url" placeholder="https://zalo.me/...">
          </label>
        </div>

        <!-- SMTP -->
        <div v-show="activeTab === 'smtp'" class="crud-form-grid">
          <label class="crud-field"><span>SMTP host</span><input v-model="form.smtp_host" type="text" placeholder="smtp.gmail.com"></label>
          <label class="crud-field"><span>SMTP port</span><input v-model="form.smtp_port" type="text" placeholder="587"></label>
          <label class="crud-field"><span>SMTP username</span><input v-model="form.smtp_username" type="text" placeholder="noreply@example.com"></label>
          <label class="crud-field"><span>SMTP password</span><input v-model="form.smtp_password" type="password" placeholder="••••••••" autocomplete="new-password"></label>
          <label class="crud-field">
            <span>Mã hóa</span>
            <select v-model="form.smtp_encryption">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="none">None</option>
            </select>
          </label>
          <label class="crud-field"><span>Email gửi đi</span><input v-model="form.smtp_from_address" type="email" placeholder="noreply@example.com"></label>
          <label class="crud-field"><span>Tên người gửi</span><input v-model="form.smtp_from_name" type="text" placeholder="ERIPT LMS"></label>

          <div class="crud-field crud-field-full settings-test-block">
            <span>Gửi email kiểm tra</span>
            <div class="settings-test-row">
              <input v-model="testEmail" type="email" placeholder="Email nhận thử (vd: ban@example.com)">
              <button class="crud-secondary-btn" type="button" :disabled="sendingTest || !hasSmtpConfigured" @click="sendTestEmail">
                {{ sendingTest ? 'Đang gửi...' : 'Gửi thử' }}
              </button>
            </div>
            <small class="settings-help">Hệ thống sẽ lưu cấu hình rồi gửi một email kiểm tra tới địa chỉ ở trên.</small>
          </div>
        </div>

        <!-- Legal / Footer -->
        <div v-show="activeTab === 'legal'" class="crud-form-grid">
          <label class="crud-field crud-field-full">
            <span>Dòng bản quyền (footer)</span>
            <input v-model="form.footer_copyright" type="text" placeholder="© 2026 ERIPT LMS. All rights reserved.">
          </label>
          <label class="crud-field">
            <span>Tên doanh nghiệp</span>
            <input v-model="form.legal_company_name" type="text" placeholder="Công ty TNHH ABC">
          </label>
          <label class="crud-field">
            <span>Mã số thuế</span>
            <input v-model="form.legal_tax_code" type="text" placeholder="0312345678">
          </label>
          <label class="crud-field crud-field-full">
            <span>URL Điều khoản sử dụng</span>
            <input v-model="form.terms_url" type="url" placeholder="https://example.com/terms">
          </label>
          <label class="crud-field crud-field-full">
            <span>URL Chính sách bảo mật</span>
            <input v-model="form.privacy_url" type="url" placeholder="https://example.com/privacy">
          </label>
        </div>

        <!-- Locale -->
        <div v-show="activeTab === 'locale'" class="crud-form-grid">
          <label class="crud-field">
            <span>Ngôn ngữ mặc định</span>
            <select v-model="form.default_locale">
              <option value="vi">Tiếng Việt</option>
              <option value="en">English</option>
            </select>
          </label>
          <label class="crud-field">
            <span>Tiền tệ mặc định</span>
            <select v-model="form.default_currency">
              <option value="VND">VND - Đồng Việt Nam</option>
              <option value="USD">USD - US Dollar</option>
              <option value="EUR">EUR - Euro</option>
              <option value="JPY">JPY - Yên Nhật</option>
            </select>
          </label>
          <label class="crud-field crud-field-full">
            <span>Múi giờ</span>
            <select v-model="form.timezone">
              <option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh (UTC+7)</option>
              <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
              <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
              <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
              <option value="UTC">UTC</option>
            </select>
          </label>
        </div>
      </div>
    </section>
  </AdminWorkspaceShell>
</template>

<style scoped>
.settings-help {
  display: block;
  margin-top: 6px;
  color: var(--color-outline, #64748b);
  font-size: 12px;
}
.settings-upload-wrap { display: grid; gap: 12px; }
.upload-dropzone {
  position: relative;
  display: grid;
  gap: 6px;
  padding: 16px;
  border: 1px dashed #cbd5e1;
  border-radius: 16px;
  background: #f8fafc;
}
.upload-dropzone-compact { min-width: 320px; }
.upload-dropzone-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-dropzone-icon { font-size: 24px; }

.settings-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 4px;
  margin: 8px 0 16px;
  background: #f1f5f9;
  border-radius: 12px;
}
.settings-tab {
  flex: 1 1 auto;
  min-width: 120px;
  padding: 8px 14px;
  border: none;
  background: transparent;
  border-radius: 10px;
  font-weight: 500;
  font-size: 13px;
  color: #475569;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.settings-tab:hover { color: #0f172a; }
.settings-tab.is-active {
  background: #fff;
  color: #0f172a;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.settings-test-block { gap: 8px; }
.settings-test-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.settings-test-row input {
  flex: 1 1 240px;
  min-width: 240px;
}
</style>
