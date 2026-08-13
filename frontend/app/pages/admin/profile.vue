<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

definePageMeta({
  layout: 'admin',
  middleware: ['auth', 'admin'],
})

const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()

const profileForm = reactive({
  name: '',
  avatar: '',
})
const savingProfile = ref(false)

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const savingPassword = ref(false)

function syncFromAuth() {
  profileForm.name = auth.user?.name || ''
  profileForm.avatar = auth.user?.avatar || ''
}

async function saveProfile() {
  savingProfile.value = true
  try {
    const res = await useApi<{ message?: string, user?: unknown }>('/auth/profile', {
      method: 'PUT',
      body: {
        name: profileForm.name,
        avatar: profileForm.avatar || null,
      },
    })
    await auth.fetchMe()
    toast.add({ severity: 'success', summary: res.message || t('admin.profile.saved'), life: 2500 })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.profile.saveError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  if (passwordForm.password !== passwordForm.password_confirmation) {
    toast.add({ severity: 'warn', summary: t('admin.profile.passwordMismatch'), life: 3000 })
    return
  }
  savingPassword.value = true
  try {
    const res = await useApi<{ message?: string }>('/auth/change-password', {
      method: 'PUT',
      body: { ...passwordForm },
    })
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    toast.add({ severity: 'success', summary: res.message || t('admin.profile.passwordSaved'), life: 2500 })
  }
  catch (error: any) {
    toast.add({
      severity: 'error',
      summary: t('admin.profile.passwordError'),
      detail: error?.data?.message,
      life: 3500,
    })
  }
  finally {
    savingPassword.value = false
  }
}

onMounted(syncFromAuth)
</script>

<template>
  <div class="page profile-page">
    <header class="workspace-head">
      <div>
        <h1>{{ t('admin.profile.title') }}</h1>
        <p>{{ t('admin.profile.subtitle') }}</p>
      </div>
    </header>

    <section class="panel">
      <h2>{{ t('admin.profile.infoTitle') }}</h2>
      <div class="form-grid">
        <div class="field">
          <CommonMediaUpload
            v-model="profileForm.avatar"
            folder="users"
            variant="avatar"
            :label="t('admin.profile.avatar')"
            :placeholder-initial="(profileForm.name || 'AD').slice(0, 2).toUpperCase()"
          />
        </div>
        <label class="field">
          <span>{{ t('admin.profile.name') }}</span>
          <InputText v-model="profileForm.name" class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.profile.email') }}</span>
          <InputText :model-value="auth.user?.email" class="w-full" disabled />
        </label>
      </div>
      <div class="panel-actions">
        <Button
          :label="t('common.save')"
          icon="pi pi-check"
          :loading="savingProfile"
          @click="saveProfile"
        />
      </div>
    </section>

    <section class="panel">
      <h2>{{ t('admin.profile.passwordTitle') }}</h2>
      <div class="form-grid">
        <label class="field">
          <span>{{ t('admin.profile.currentPassword') }}</span>
          <Password v-model="passwordForm.current_password" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.profile.newPassword') }}</span>
          <Password v-model="passwordForm.password" toggle-mask class="w-full" input-class="w-full" />
        </label>
        <label class="field">
          <span>{{ t('admin.profile.confirmPassword') }}</span>
          <Password v-model="passwordForm.password_confirmation" :feedback="false" toggle-mask class="w-full" input-class="w-full" />
        </label>
      </div>
      <div class="panel-actions">
        <Button
          :label="t('admin.profile.changePassword')"
          icon="pi pi-lock"
          severity="secondary"
          :loading="savingPassword"
          @click="savePassword"
        />
      </div>
    </section>
  </div>
</template>

<style scoped>
.profile-page { gap: 14px; }
.workspace-head h1 { margin: 0 0 4px; font-size: clamp(1.5rem, 2vw, 1.85rem); }
.workspace-head p { margin: 0; color: var(--text-muted); font-size: .95rem; font-weight: 500; }

.panel {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(8px);
  padding: 18px;
  margin-bottom: 16px;
}

.panel h2 {
  margin: 0 0 14px;
  font-size: 1.05rem;
  font-weight: 700;
}

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field.full { grid-column: 1 / -1; }
.field > span { color: var(--text-muted); font-size: .72rem; font-weight: 700; }
.w-full { width: 100%; }

.panel-actions { display: flex; justify-content: flex-end; margin-top: 16px; }

@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
