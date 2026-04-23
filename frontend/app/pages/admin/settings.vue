<template>
  <NuxtLayout name="admin">
    <section class="space-y-8 max-w-4xl">
      <AppPageHeader eyebrow="Hệ thống" title="Cài đặt Website" description="Tùy chỉnh logo, tiêu đề, favicon và cấu hình email SMTP." />

      <div v-if="loading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-20 rounded-2xl bg-surface-high animate-pulse" />
      </div>

      <template v-else>
        <!-- Site Identity -->
        <UiCard>
          <h3 class="text-lg font-bold font-headline text-on-surface mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">language</span> Thông tin Website
          </h3>
          <div class="space-y-5">
            <UiInput v-model="form.site_name" label="Tên website" placeholder="EduPress LMS" />
            <UiTextarea v-model="form.site_description" label="Mô tả ngắn" :rows="2" placeholder="Nền tảng học tập trực tuyến" />
            <UiInput v-model="form.site_logo" label="URL Logo" type="url" placeholder="https://..." />
            <div v-if="form.site_logo" class="flex items-center gap-4 rounded-xl bg-surface-low p-4 border border-surface-dim">
              <img :src="form.site_logo" alt="Logo preview" class="h-12 max-w-[200px] object-contain" @error="form.site_logo = ''">
              <span class="text-sm text-on-surface-variant">Xem trước logo</span>
            </div>
            <UiInput v-model="form.site_favicon" label="URL Favicon" type="url" placeholder="https://..." />
          </div>
        </UiCard>

        <!-- SMTP Config -->
        <UiCard>
          <h3 class="text-lg font-bold font-headline text-on-surface mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">mail</span> Cấu hình Email (SMTP)
          </h3>
          <div class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
              <UiInput v-model="form.smtp_host" label="SMTP Host" placeholder="smtp.gmail.com" />
              <UiInput v-model="form.smtp_port" label="SMTP Port" placeholder="587" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <UiInput v-model="form.smtp_username" label="SMTP Username" placeholder="your@email.com" />
              <UiInput v-model="form.smtp_password" label="SMTP Password" type="password" placeholder="App password" />
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <label class="block space-y-2 text-sm font-semibold text-on-surface-variant">
                <span>Mã hóa</span>
                <select v-model="form.smtp_encryption" class="w-full rounded-xl border border-surface-dim bg-surface-lowest px-4 py-3 text-sm outline-none focus:border-primary">
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                  <option value="none">Không mã hóa</option>
                </select>
              </label>
              <UiInput v-model="form.smtp_from_address" label="Email gửi" type="email" placeholder="noreply@example.com" />
              <UiInput v-model="form.smtp_from_name" label="Tên hiển thị" placeholder="EduPress LMS" />
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
              <p class="font-semibold">Hướng dẫn sử dụng Gmail SMTP</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sky-700">
                <li>Host: smtp.gmail.com, Port: 587, Encryption: TLS</li>
                <li>Username: email Gmail, Password: App Password (không phải mật khẩu Gmail)</li>
                <li>Tạo App Password tại Google Account &gt; Security &gt; 2-Step Verification &gt; App Passwords</li>
              </ul>
            </div>
          </div>
        </UiCard>

        <!-- Actions -->
        <div class="flex items-center justify-between">
          <div>
            <span v-if="success" class="text-sm text-secondary font-semibold">{{ success }}</span>
            <span v-if="error" class="text-sm text-error font-semibold">{{ error }}</span>
          </div>
          <UiButton :disabled="saving" @click="saveSettings">
            {{ saving ? 'Đang lưu...' : 'Lưu cài đặt' }}
          </UiButton>
        </div>
      </template>
    </section>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: false, middleware: ['auth', 'admin'] })

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const success = ref('')
const error = ref('')

const form = reactive({
  site_name: '',
  site_description: '',
  site_logo: '',
  site_favicon: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls',
  smtp_from_address: '',
  smtp_from_name: '',
})

onMounted(async () => {
  try {
    const data = await $fetch<Record<string, string>>('/api/admin/settings', {
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    Object.entries(data).forEach(([key, value]) => {
      if (key in form) {
        ;(form as any)[key] = value || ''
      }
    })
  } catch {
    // Settings table might not exist yet
  } finally {
    loading.value = false
  }
})

async function saveSettings() {
  saving.value = true
  success.value = ''
  error.value = ''
  try {
    await $fetch('/api/admin/settings', {
      method: 'PUT',
      body: { ...form },
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    success.value = 'Đã lưu cài đặt thành công!'
  } catch (e: any) {
    error.value = e?.data?.message || 'Không thể lưu cài đặt.'
  } finally {
    saving.value = false
  }
}
</script>
