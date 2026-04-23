<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AdminWorkspaceShell from '~/components/dashboard/AdminWorkspaceShell.vue'
import { useAuthUserCookie } from '~/composables/useAuthSession'
import { useAdminUpload } from '~/composables/useAdminUpload'

definePageMeta({ layout: 'admin', adminSearchPlaceholder: 'Tìm cài đặt...' })
interface SiteSettings { site_name?: string | null; site_description?: string | null; site_logo?: string | null; site_favicon?: string | null; smtp_host?: string | null; smtp_port?: string | null; smtp_username?: string | null; smtp_password?: string | null; smtp_encryption?: string | null; smtp_from_address?: string | null; smtp_from_name?: string | null }
const user = useAuthUserCookie(); if (!user.value) await navigateTo('/login', { replace: true })
const token = useAuthTokenCookie(); const { uploadImage } = useAdminUpload(); const loading = ref(false); const saving = ref(false); const uploadingLogo = ref(false); const uploadingFavicon = ref(false)
const errorMessage = ref(''); const successMessage = ref(''); const logoFile = ref<File | null>(null); const faviconFile = ref<File | null>(null)
const form = reactive<Required<SiteSettings>>({ site_name: '', site_description: '', site_logo: '', site_favicon: '', smtp_host: '', smtp_port: '', smtp_username: '', smtp_password: '', smtp_encryption: 'tls', smtp_from_address: '', smtp_from_name: '' })
const authHeaders = () => ({ Authorization: `Bearer ${token.value}` })
async function fetchSettings() { loading.value = true; try { Object.assign(form, await useApi<SiteSettings>('/admin/settings', { headers: authHeaders() })) } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải cài đặt.' } finally { loading.value = false } }
async function saveSettings() { saving.value = true; try { await useApi('/admin/settings', { method: 'PUT', headers: authHeaders(), body: { ...form } }); successMessage.value = 'Đã lưu cài đặt hệ thống.' } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể lưu cài đặt.' } finally { saving.value = false } }
async function uploadSettingImage(type: 'logo' | 'favicon') {
  const file = type === 'logo' ? logoFile.value : faviconFile.value
  if (!file) return
  type === 'logo' ? uploadingLogo.value = true : uploadingFavicon.value = true
  try {
    const uploaded = await uploadImage(file, 'settings', type === 'logo' ? form.site_logo : form.site_favicon)
    if (type === 'logo') form.site_logo = uploaded.url
    else form.site_favicon = uploaded.url
    successMessage.value = type === 'logo' ? 'Đã tải logo lên.' : 'Đã tải favicon lên.'
  } catch (error: any) { errorMessage.value = error?.data?.message || 'Không thể tải ảnh lên.' } finally { type === 'logo' ? uploadingLogo.value = false : uploadingFavicon.value = false }
}
onMounted(fetchSettings)
</script>

<template>
  <AdminWorkspaceShell :breadcrumb="['Trang chủ', 'Quản trị hệ thống', 'Cài đặt']" description="Quản lý thông tin website, logo, favicon và cấu hình SMTP trên cùng một chuẩn form quản trị thống nhất." title="Cài đặt hệ thống">
    <section class="crud-overview-grid">
      <article class="dashboard-card mini-card tone-green"><p class="mini-title">Website</p><div class="mini-head"><strong>{{ form.site_name || 'Chưa đặt tên' }}</strong><span>Tên hiển thị của hệ thống</span></div></article>
      <article class="dashboard-card mini-card tone-amber"><p class="mini-title">Logo</p><div class="mini-head"><strong>{{ form.site_logo ? 'Đã có' : 'Chưa có' }}</strong><span>Tải lên trực tiếp thay vì nhập URL thủ công</span></div></article>
      <article class="dashboard-card mini-card"><p class="mini-title">SMTP</p><div class="mini-head"><strong>{{ form.smtp_host || '--' }}</strong><span>Máy chủ gửi email hiện tại</span></div></article>
    </section>
    <section class="dashboard-card crud-panel">
      <div class="crud-toolbar"><div><p class="section-kicker">Thông tin website</p><h3>Cấu hình thương hiệu và email</h3></div><button class="crud-primary-btn" type="button" :disabled="saving" @click="saveSettings">{{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}</button></div>
      <div v-if="errorMessage" class="crud-alert is-error">{{ errorMessage }}</div><div v-if="successMessage" class="crud-alert is-success">{{ successMessage }}</div>
      <div v-if="loading" class="crud-empty">Đang tải cài đặt...</div>
      <div v-else class="crud-form-grid">
        <label class="crud-field"><span>Tên website</span><input v-model="form.site_name" type="text" placeholder="PTIT LMS"></label>
        <div class="crud-field"><span>Mô tả website</span><RichTextEditor v-model="form.site_description" placeholder="Nền tảng học trực tuyến" /></div>
        <div class="crud-field crud-field-full"><span>Logo website</span><div class="crud-image-preview"><img v-if="form.site_logo" :src="form.site_logo" alt="Logo website"><div v-else class="crud-image-fallback">LG</div><div><input type="file" accept="image/*" @change="logoFile = ($event.target as HTMLInputElement)?.files?.[0] || null"><div class="crud-inline-actions crud-modal-foot"><button class="crud-secondary-btn" type="button" :disabled="uploadingLogo || !logoFile" @click="uploadSettingImage('logo')">{{ uploadingLogo ? 'Đang tải...' : 'Tải logo lên' }}</button></div></div></div></div>
        <div class="crud-field crud-field-full"><span>Favicon</span><div class="crud-image-preview"><img v-if="form.site_favicon" :src="form.site_favicon" alt="Favicon website"><div v-else class="crud-image-fallback">FV</div><div><input type="file" accept="image/*" @change="faviconFile = ($event.target as HTMLInputElement)?.files?.[0] || null"><div class="crud-inline-actions crud-modal-foot"><button class="crud-secondary-btn" type="button" :disabled="uploadingFavicon || !faviconFile" @click="uploadSettingImage('favicon')">{{ uploadingFavicon ? 'Đang tải...' : 'Tải favicon lên' }}</button></div></div></div></div>
        <label class="crud-field"><span>SMTP host</span><input v-model="form.smtp_host" type="text" placeholder="smtp.gmail.com"></label>
        <label class="crud-field"><span>SMTP port</span><input v-model="form.smtp_port" type="text" placeholder="587"></label>
        <label class="crud-field"><span>SMTP username</span><input v-model="form.smtp_username" type="text" placeholder="noreply@example.com"></label>
        <label class="crud-field"><span>SMTP password</span><input v-model="form.smtp_password" type="password" placeholder="••••••••"></label>
        <label class="crud-field"><span>Mã hóa</span><select v-model="form.smtp_encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></label>
        <label class="crud-field"><span>Email gửi đi</span><input v-model="form.smtp_from_address" type="email" placeholder="noreply@example.com"></label>
        <label class="crud-field"><span>Tên người gửi</span><input v-model="form.smtp_from_name" type="text" placeholder="PTIT LMS"></label>
      </div>
    </section>
  </AdminWorkspaceShell>
</template>
