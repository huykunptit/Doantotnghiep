<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()

const initials = computed(() =>
  (auth.user?.name || 'U')
    .split(' ')
    .filter(Boolean)
    .slice(-2)
    .map(part => part[0])
    .join('')
    .toUpperCase(),
)

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
  if (!profileForm.name.trim()) {
    toast.add({ severity: 'warn', summary: t('admin.profile.nameRequired'), life: 2500 })
    return
  }
  savingProfile.value = true
  try {
    const res = await useApi<{ message?: string }>('/auth/profile', {
      method: 'PUT',
      body: {
        name: profileForm.name.trim(),
        avatar: profileForm.avatar || null,
      },
    })
    await auth.fetchMe()
    syncFromAuth()
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
  if (!passwordForm.current_password || !passwordForm.password) {
    toast.add({ severity: 'warn', summary: t('admin.profile.passwordRequired'), life: 2800 })
    return
  }
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

watch(() => auth.user, syncFromAuth, { immediate: true, deep: true })

onMounted(async () => {
  if (!auth.ready) auth.hydrate()
  if (auth.token && !auth.user) await auth.fetchMe()
  syncFromAuth()
})
</script>

<template>
  <div class="profile-form">
    <header class="hero">
      <Avatar
        v-if="profileForm.avatar || auth.user?.avatar"
        :image="profileForm.avatar || auth.user?.avatar || undefined"
        shape="circle"
        size="xlarge"
        class="hero-avatar"
      />
      <Avatar v-else :label="initials" shape="circle" size="xlarge" class="hero-avatar" />
      <div class="hero-copy">
        <h1>{{ t('admin.profile.title') }}</h1>
        <p>{{ t('admin.profile.subtitle') }}</p>
        <span class="hero-email">{{ auth.user?.email }}</span>
      </div>
    </header>

    <section class="card">
      <div class="card-head">
        <i class="pi pi-user" />
        <div>
          <h2>{{ t('admin.profile.infoTitle') }}</h2>
          <p>{{ t('admin.profile.infoHint') }}</p>
        </div>
      </div>

      <div class="info-layout">
        <CommonMediaUpload
          v-model="profileForm.avatar"
          folder="users"
          variant="avatar"
          :label="t('admin.profile.avatar')"
          :hint="t('admin.profile.avatarHint')"
          :placeholder-initial="initials"
        />
        <div class="fields">
          <label class="field">
            <span>{{ t('admin.profile.name') }}</span>
            <InputText v-model="profileForm.name" class="w-full" />
          </label>
          <label class="field">
            <span>{{ t('admin.profile.email') }}</span>
            <InputText :model-value="auth.user?.email" class="w-full" disabled />
            <small>{{ t('admin.profile.emailLocked') }}</small>
          </label>
          <div class="actions">
            <Button
              :label="t('common.save')"
              icon="pi pi-check"
              :loading="savingProfile"
              @click="saveProfile"
            />
          </div>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <i class="pi pi-lock" />
        <div>
          <h2>{{ t('admin.profile.passwordTitle') }}</h2>
          <p>{{ t('admin.profile.passwordHint') }}</p>
        </div>
      </div>
      <div class="fields password-grid">
        <label class="field full">
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
        <div class="actions full">
          <Button
            :label="t('admin.profile.changePassword')"
            icon="pi pi-lock"
            severity="secondary"
            :loading="savingPassword"
            @click="savePassword"
          />
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.profile-form { display: grid; gap: 16px; max-width: 860px; }

.hero {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border: 1px solid var(--border);
  border-radius: 18px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--brand) 14%, transparent), transparent 55%),
    color-mix(in srgb, var(--surface) 94%, transparent);
}
.hero-avatar { flex: 0 0 auto; }
.hero-copy { min-width: 0; }
.hero-copy h1 { margin: 0 0 4px; font-size: clamp(1.35rem, 2vw, 1.7rem); }
.hero-copy p { margin: 0; color: var(--text-muted); font-size: .92rem; font-weight: 500; }
.hero-email {
  display: inline-block;
  margin-top: 8px;
  color: var(--brand);
  font-size: .82rem;
  font-weight: 700;
}

.card {
  border: 1px solid var(--border);
  border-radius: 16px;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  padding: 18px 20px 20px;
}
.card-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}
.card-head i {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--brand-soft, color-mix(in srgb, var(--brand) 16%, transparent));
  color: var(--brand);
  font-size: .95rem;
}
.card-head h2 { margin: 0 0 2px; font-size: 1.02rem; font-weight: 750; }
.card-head p { margin: 0; color: var(--text-muted); font-size: .82rem; font-weight: 500; }

.info-layout {
  display: grid;
  grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
  gap: 18px;
  align-items: start;
}
.fields { display: grid; gap: 12px; }
.password-grid { grid-template-columns: 1fr 1fr; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field.full, .actions.full { grid-column: 1 / -1; }
.field > span { color: var(--text-muted); font-size: .74rem; font-weight: 700; }
.field small { color: var(--text-muted); font-size: .75rem; }
.w-full { width: 100%; }
.actions { display: flex; justify-content: flex-end; }

@media (max-width: 760px) {
  .hero { flex-direction: column; align-items: flex-start; }
  .info-layout, .password-grid { grid-template-columns: 1fr; }
}
</style>
